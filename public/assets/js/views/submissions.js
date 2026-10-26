import { genreGlyph, genreName, hiddenTraits, traitNames, instrumentIcon, instrumentName, photo, sortedGenres } from '../cards.js';
import { api } from '../api.js';
import { actions, app, cat, defineActions, mutate, musicianById, rerender, serverNow } from '../core.js';
import { confirmDialog, esc, money, off, openModal, toast } from '../ui.js';
import { levelClass, levelProgress, relColor } from './label.js';

/**
 * Einsendungen: Musiker bewerben sich von selbst beim Label. Man sieht sie, unterschreibt sie gegen eine einmalige Prämie
 * oder wirft sie weg. Kassetten und Visitenkarten teilen sich dieselben Plätze (nichts verschiebt sich beim Sichten) und lassen sich frei sortieren.
 * (Assistenz und Scout kommen später.)
 */

const isTest = () => document.documentElement.classList.contains('is-test') || ['localhost', '127.0.0.1'].includes(location.hostname);

function expires(s) {
  const left = s.expiresAt - serverNow();
  if (left <= 0) return 'läuft ab';
  return left >= 86400 ? `läuft ab in ${Math.ceil(left / 86400)} Tagen` : `läuft ab in ${Math.max(1, Math.ceil(left / 3600))} Std.`;
}

/** Kurz und sichtbar auf jeder Einsendung: wie lange sie noch da ist (rot ab den letzten 12 Stunden). */
function leftPill(s) {
  const left = s.expiresAt - serverNow();
  const text = left >= 86400 ? `noch ${Math.ceil(left / 86400)} ${Math.ceil(left / 86400) === 1 ? 'Tag' : 'Tage'}` : `noch ${Math.max(1, Math.ceil(left / 3600))} Std.`;
  return `<span class="lb-left ${left < 12 * 3600 ? 'soon' : ''}" title="Danach zieht ${s.kind === 'band' ? 'die Band' : 'die Einsendung'} weiter">${text}</span>`;
}

const BOOKMARK = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h12v18l-6-4.5L6 21z"/></svg>';
/** Merken: legt die Einsendung zu den gespeicherten (oder zurück in den Eingang). */
const saveBtn = (s) => `<button class="lb-save ${s.saved ? 'on' : ''}" data-act="subSave" data-id="${s.submissionId}" data-saved="${s.saved ? '' : '1'}" title="${s.saved ? 'Zurück in den Eingang' : 'Merken'}" aria-label="${s.saved ? 'Zurück in den Eingang' : 'Merken'}">${BOOKMARK}</button>`;

/** Die Kassette: Außen steht nichts drauf – man muss sie sichten, um zu wissen, was drin ist. */
const cassette = (hint = null) => `<span class="lb-tape-wrap"><svg class="lb-tape-svg" viewBox="0 0 120 80" aria-hidden="true">
  <rect x="4" y="6" width="112" height="68" rx="9" fill="#2b2836" stroke="#4a4458" stroke-width="2"/>
  <rect x="14" y="14" width="92" height="30" rx="4" fill="#d8d2c4"/>
  <g transform="translate(36 58)"><circle r="9" fill="#18161f" stroke="#4a4458" stroke-width="2"/><g class="lb-reel"><circle r="5" fill="none" stroke="#c9a86a" stroke-width="2" stroke-dasharray="4 3"/></g></g>
  <g transform="translate(84 58)"><circle r="9" fill="#18161f" stroke="#4a4458" stroke-width="2"/><g class="lb-reel"><circle r="5" fill="none" stroke="#c9a86a" stroke-width="2" stroke-dasharray="4 3"/></g></g>
  <rect x="46" y="52" width="28" height="12" rx="3" fill="#18161f"/>
</svg><span class="lb-tape-label">${hint?.genres?.length ? hint.genres.map((g) => `<i class="g-${g}">${genreGlyph(g)}</i>`).join('') : '<b>?</b>'}</span></span>`;

/** Kleine Streuung aus der Nummer: Schräglage und Versatz bleiben beim Neuladen gleich, wirken aber unregelmäßig. */
function scatter(id) {
  const h = (n) => (Math.imul(id + n, 2654435761) >>> 0) / 4294967296;
  return `--r:${(Math.round((h(1) * 8 - 4) * 10) / 10)}deg;--y:${Math.round(h(2) * 10 - 5)}px`;
}

const levelCount = (skill) => Math.min(3, cat().rules.skillLevels.filter((t) => skill >= t).length);
const levelOf = (s) => Math.max(...(s.kind === 'band' ? s.members : [s]).flatMap((m) => m.instruments.map((i) => i.skill)).map(levelCount));

/** Kassette: Anklicken zeigt „Sichten“ oder „Wegwerfen“ direkt auf ihr. Sonst steht nichts darunter. */
function tape(s, busy) {
  const listening = s.state === 'listening';
  const total = cat().rules.submissionListen ?? 5;
  const elapsed = listening ? Math.max(0, total - (s.opensAt - serverNow())) : 0;
  const sel = !listening && app.ui.subSel === s.submissionId;
  return `
    <div class="lb-tapeitem ${listening ? 'playing' : ''} ${sel ? 'sel' : ''}">
      <div class="lb-tape-big" data-act="subSel" data-id="${s.submissionId}" title="${expires(s)}">${cassette(s.hint)}
        ${listening ? `<span class="lb-prog tiny"><i style="animation-duration:${total}s;animation-delay:-${elapsed.toFixed(2)}s"></i></span>` : ''}
        ${sel ? `<div class="lb-tape-menu">
          <button class="btn small primary" data-act="submissionOpen" data-id="${s.submissionId}" ${off(busy ? 'Du sichtest gerade schon eine andere Kassette.' : '')}>Sichten</button>
          <button class="btn small ghost" data-act="submissionDrop" data-id="${s.submissionId}">Wegwerfen</button>
          <small>${expires(s)}</small></div>` : ''}
      </div>
    </div>`;
}

const split = (genres) => sortedGenres(genres).map((g, i, all) => `var(--lb-c-${g}) ${(i * 100) / all.length}% ${((i + 1) * 100) / all.length}%`).join(', ');
const genreTags = (genres) => `<div class="lb-genres">${sortedGenres(genres).map((g) => `<button class="lb-gt g-${g}" data-act="tip" data-tip="${esc(genreName(g))}">${genreGlyph(g)}</button>`).join('')}</div>`;
const instrumentRow = (i, compact = false) => `<div class="lb-ins ${levelClass(i.skill)} ${compact ? 'compact' : ''}"><span class="ico" data-act="tip" data-tip="${esc(instrumentName(i.type))}">${instrumentIcon(i.type)}</span>
  <span class="sk" title="Können ${Math.floor(i.skill)}"><i style="width:${levelProgress(i.skill)}%"></i></span>
  <b class="lv" data-act="tip" data-tip="Können ${Math.floor(i.skill)}">${levelCount(i.skill)}</b></div>`;

const PEN = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20l1-4L16.5 4.5a2 2 0 013 3L8 19l-4 1z M14 7l3 3 M3 21h6"/></svg>';
const fameStars = (s) => (s.fameLevel ? `<span class="lb-sub-fame" title="${esc(cat().fameNames[s.fameLevel])}">${'★'.repeat(s.fameLevel)}</span>` : '<small class="muted" title="Noch keine Bekanntheit">unbekannt</small>');
const traitChips = (m, act = 'subInfo', sid = 0, index = 0) => m.personality.map((p) => `<button class="lb-trait" data-act="${act}" data-id="${sid}" data-index="${index}" data-tip="${esc(cat().personalities[p])}: ${esc(cat().personalityInfo[p])}" title="${esc(cat().personalities[p])}: ${esc(cat().personalityInfo[p])}">${esc(cat().personalities[p])}</button>`).join('') + hiddenTraits(m);

/** Unterschreiben (Stift) und Wegwerfen (✕): klein und eindeutig; Unterschreiben fragt nach. */
function iconFoot(s, price, enough) {
  const money0 = app.state.label.money;
  return `<div class="lb-card-foot">
    <b class="lb-price" title="${expires(s)}">${money(price)}</b>
    <button class="lb-iconbtn drop" data-act="submissionDrop" data-id="${s.submissionId}" title="Wegwerfen" aria-label="Wegwerfen">✕</button>
    <button class="lb-iconbtn sign" data-act="subSign" data-id="${s.submissionId}" ${off(money0 < price ? `Dafür fehlen noch ${money(price - money0)}.` : !enough ? 'Wähle mindestens einen aus.' : '')} title="Unterschreiben – ${money(price)}" aria-label="Unterschreiben">${PEN}</button>
  </div>`;
}

const flipped = (s) => app.ui.subFlipped?.has(s.submissionId);

/** Visitenkarte: vorn Genres, Instrument mit Leiste und Bekanntheit; ein Tipp dreht sie um (hinten Person und Eigenschaften). */
function card(s) {
  return `
    <div class="lb-flipper ${flipped(s) ? 'flipped' : ''}" data-act="subFlip" data-id="${s.submissionId}">
      <article class="lb-face front">
        <div class="lb-fr-name"><b>${esc(s.firstName)} ${esc(s.lastName)}</b></div>
        <div class="lb-fr-row">${genreTags(s.genres)}${fameStars(s)}</div>
        <div class="lb-card-ins">${s.instruments.map(instrumentRow).join('')}</div>
        ${iconFoot(s, s.price, true)}
      </article>
      <article class="lb-face back">
        <div class="lb-bk-head"><span class="lb-ph" style="--split:linear-gradient(120deg, ${split(s.genres)})" data-act="subInfo" data-id="${s.submissionId}" data-index="0" title="Mehr über ${esc(s.firstName)}">${photo(s)}</span>
          <div><b>${esc(s.firstName)} ${esc(s.lastName)}</b><small class="muted">Tipp auf Gesicht oder Eigenschaft: mehr erfahren</small></div></div>
        <div class="lb-traits">${traitChips(s, 'subInfo', s.submissionId, 0)}</div>
        ${iconFoot(s, s.price, true)}
      </article>
    </div>`;
}

/** Karte einer Band: vorn die Instrumente aller, hinten die Gesichter mit ihren Eigenschaften (antippen = mehr erfahren, wie bei Einzelnen). */
function bandCard(s) {
  const price = s.members.reduce((a, m) => a + m.price, 0);
  const genres = [...new Set(s.members.flatMap((m) => m.genres))].slice(0, 4);
  return `
    <div class="lb-flipper band ${flipped(s) ? 'flipped' : ''}" data-act="subFlip" data-id="${s.submissionId}">
      <article class="lb-face front">
        <div class="lb-fr-name"><small class="muted">Band</small> <b>${esc(s.bandName)}</b></div>
        <div class="lb-fr-row">${genreTags(genres)}</div>
        <div class="lb-card-ins grid">${s.members.map((m) => instrumentRow(m.instruments[0], true)).join('')}</div>
        ${iconFoot(s, price, true)}
      </article>
      <article class="lb-face back">
        <div class="lb-band-faces">${s.members.map((m) => `
          <div class="lb-bm">
            <button class="lb-ph sm" style="--split:linear-gradient(120deg, ${split(m.genres)})" data-act="subInfo" data-id="${s.submissionId}" data-index="${m.index}"
              title="Mehr über ${esc(m.firstName)}">${photo(m)}</button>
            <div class="lb-bm-traits">${traitChips(m, 'subInfo', s.submissionId, m.index)}</div>
          </div>`).join('')}</div>
        ${iconFoot(s, price, true)}
      </article>
    </div>`;
}

function slot(s, busy) {
  const inner = s.state !== 'open' ? tape(s, busy) : (s.kind === 'band' ? bandCard(s) : card(s));
  // Eine eben gesichtete Kassette wird an ihrem Platz zur Karte (kurzes Umdrehen, nur beim ersten Zeigen).
  const fresh = s.state === 'open' && !app.ui.subSeen.has(s.submissionId);
  if (s.state === 'open') app.ui.subSeen.add(s.submissionId);
  return `<div class="lb-slotitem ${fresh ? 'fresh' : ''}" data-sid="${s.submissionId}" style="${scatter(s.submissionId)}">${inner}<span class="lb-slotbar">${leftPill(s)}${saveBtn(s)}</span></div>`;
}

/** Eigene Reihenfolge: Neues zuerst, danach die gemerkte Anordnung. */
function ordered(list) {
  const saved = app.state.label.subOrder ?? [];
  const known = list.filter((x) => saved.includes(x.submissionId)).sort((a, b) => saved.indexOf(a.submissionId) - saved.indexOf(b.submissionId));
  return [...list.filter((x) => !saved.includes(x.submissionId)), ...known];
}

export function render() {
  const all = app.state.submissions ?? [];
  // Beim ersten Besuch gilt alles Gesichtete als schon gesehen (kein Umdrehen auf einen Schlag).
  app.ui.subSeen ??= new Set(all.filter((x) => x.state === 'open').map((x) => x.submissionId));
  const f = app.ui.subFilter ?? 'all';
  const box = app.ui.subBox ?? 'inbox';
  const inBox = all.filter((s) => (box === 'saved') === !!s.saved);
  const list = ordered(inBox).filter((s) => f === 'all' || (f === 'unheard' ? s.state !== 'open' : s.state === 'open' && levelOf(s) === Number(f)));
  const savedCount = all.filter((s) => s.saved).length;
  const busy = all.some((x) => x.state === 'listening');
  return `
    <div class="section-head"><h1>Einsendungen</h1>${isTest() ? '<span class="lb-test"><button class="btn small" data-act="submissionsTest" data-closed="1">Test: neue Kassetten</button><button class="btn small" data-act="submissionsTest">Test: gesichtete Bewerber aller Stufen</button></span>' : ''}</div>
    <p class="muted">Jede Einsendung ist eine Kassette. Tipp sie an und sichte sie, dann siehst du, wer sich da bewirbt – ein Musiker oder eine ganze Band. Unterschreiben kostet einmalig eine Prämie, wegwerfen nichts. Nach 4 Tagen zieht jede Einsendung weiter, auch gemerkte. Du kannst alles frei hin und her schieben.</p>
    <div class="lb-boxes">${[['inbox', 'Eingang', all.length - savedCount], ['saved', 'Gespeichert', savedCount]].map(([k, label, n]) => `<button class="${box === k ? 'on' : ''}" data-act="subBox" data-key="${k}">${label} <small>${n}</small></button>`).join('')}</div>
    <div class="lb-filter">${[['all', 'Alle'], ['unheard', 'Ungehört'], ['0', 'Stufe 0'], ['1', 'Stufe 1'], ['2', 'Stufe 2'], ['3', 'Stufe 3']].map(([k, label]) => `<button class="${f === k ? 'on' : ''}" data-act="subFilter" data-key="${k}">${label}</button>`).join('')}</div>
    ${list.length ? `<div class="lb-slots" data-slots>${list.map((x) => slot(x, busy)).join('')}</div>` : `<div class="empty">${box === 'saved' ? 'Noch nichts gemerkt. Mit dem Lesezeichen an einer Einsendung legst du sie hierher – ablaufen tut sie trotzdem.' : 'Gerade liegt nichts im Briefkasten. Es trudelt laufend etwas ein.'}</div>`}`;
}

// ---------- Info-Fenster: Eigenschaften und wer zu wem passt ----------

const COMPAT_WORD = (v) => (v >= 7 ? 'sehr gut' : v >= 5 ? 'gut' : v > 1 ? 'geht so' : 'schwierig');

function openInfo(s, m) {
  const modal = openModal({
    className: 'lb-info-modal',
    html: `
      <div class="lb-cap">Einsendung</div>
      <div class="lb-info-head"><span class="lb-ph" style="--split:linear-gradient(120deg, ${split(m.genres)})">${photo(m)}</span>
        <div><h2>${esc(m.firstName)} ${esc(m.lastName)}</h2>${genreTags(m.genres)}</div></div>
      <h3>Eigenschaften</h3>
      <ul class="lb-trait-list">${m.personality.map((p) => `<li><b>${esc(cat().personalities[p])}</b> ${esc(cat().personalityInfo[p])}</li>`).join('')}${m.hiddenTraits ? `<li><b>?</b> ${m.hiddenTraits > 1 ? 'Diese Charakterzüge zeigen' : 'Dieser Charakterzug zeigt'} sich erst, wenn ${esc(m.firstName)} eine Weile bei dir ist.</li>` : ''}</ul>
      <h3>Wer zu ${esc(m.firstName)} passt</h3>
      <div class="lb-compat" data-compat><small class="muted">Einen Moment …</small></div>
      <div class="dialog-actions"><button class="btn primary" data-close>Schließen</button></div>`,
  });
  modal.body.querySelector('[data-close]').addEventListener('click', () => modal.close());
  api('submissionCompat', { submissionId: s.submissionId, index: s.kind === 'band' ? m.index : 0 }).then(({ compat }) => {
    const box = modal.body.querySelector('[data-compat]');
    if (!box) return;
    const row = (c) => {
      const mm = musicianById(c.id);
      const band = c.bandId ? app.state.bands.find((b) => b.id === c.bandId) : null;
      return `<div class="lb-cp"><span class="lb-rel" style="--rc:${relColor(c.target)}"><span class="lb-relpic" style="box-shadow:0 0 0 3px var(--rc)">${mm ? photo(mm) : ''}</span></span>
        <b>${esc(c.name)}</b><small class="muted">${band ? esc(band.name) : 'ohne Band'}</small><em>${COMPAT_WORD(c.target)}</em></div>`;
    };
    const good = compat.filter((c) => c.target >= 5).slice(0, 5);
    const bad = compat.filter((c) => c.target <= 1).slice(-4).reverse();
    box.innerHTML = !compat.length ? '<p class="muted">Du hast noch keine Musiker im Label, mit denen man vergleichen könnte.</p>'
      : `${good.length ? `<h4>Versteht sich gut mit</h4>${good.map(row).join('')}` : ''}
         ${bad.length ? `<h4>Schwierig mit</h4>${bad.map(row).join('')}` : ''}
         ${!good.length && !bad.length ? '<p class="muted">Mit niemandem besonders gut oder schlecht – die Beziehungen wachsen langsam auf ein mittleres Niveau.</p>' : ''}`;
  }).catch((e) => { const box = modal.body.querySelector('[data-compat]'); if (box) box.textContent = e.message; });
}

// ---------- Band unterschreiben ----------

const andList = (names) => (names.length > 1 ? `${names.slice(0, -1).join(', ')} und ${names.at(-1)}` : names[0] ?? '');

/**
 * Band unterschreiben: Alle sind vorgewählt. Wer abgewählt wird, zieht weiter – und wer bleibt, ist gekränkt
 * (Teamplayer besonders, Ehrgeizige weniger). Das steht im Fenster, bevor man unterschreibt.
 */
function signBand(s) {
  const picked = new Set(s.members.map((m) => m.index));
  const team = (m) => m.personality.includes('teamplayer');
  const modal = openModal({
    className: 'lb-sign-modal',
    html: `
      <div class="lb-cap">Unterschreiben</div>
      <h2>${esc(s.bandName)}</h2>
      <p class="muted">Wen nimmst du? Am liebsten kommen sie alle zusammen.</p>
      <div class="lb-sign-list">${s.members.map((m) => `
        <label class="lb-sign-row"><input type="checkbox" data-pick="${m.index}" checked>
          <span class="lb-ph sm" style="--split:linear-gradient(120deg, ${split(m.genres)})">${photo(m)}</span>
          <span><b>${esc(m.firstName)} ${esc(m.lastName)}</b><small class="muted">${esc(instrumentName(m.instruments[0].type))} · ${esc(traitNames(m))}</small></span>
          <em>${money(m.price)}</em></label>`).join('')}</div>
      <p class="lb-sign-note" data-note></p>
      <div class="dialog-actions"><button class="btn ghost" data-close>Abbrechen</button><button class="btn primary" data-ok></button></div>`,
  });
  const body = modal.body;
  const update = () => {
    const chosen = s.members.filter((m) => picked.has(m.index));
    const left = s.members.filter((m) => !picked.has(m.index));
    const price = chosen.reduce((a, m) => a + m.price, 0);
    const ok = body.querySelector('[data-ok]');
    ok.textContent = `Aufnehmen · ${money(price)}`;
    ok.disabled = !chosen.length || app.state.label.money < price;
    const note = body.querySelector('[data-note]');
    note.className = `lb-sign-note ${left.length && chosen.length ? 'warn' : ''}`;
    note.textContent = !chosen.length ? 'Wähle mindestens einen.'
      : !left.length ? `Sie kommen alle als Band ${s.bandName} ins Label und kennen sich schon.`
      : `${andList(left.map((m) => m.firstName))} ${left.length > 1 ? 'ziehen' : 'zieht'} enttäuscht weiter. ${andList(chosen.map((m) => m.firstName))} ${chosen.length > 1 ? 'sind gekränkt und starten' : 'ist gekränkt und startet'} schlechter gelaunt`
        + `${chosen.some(team) ? ` – ${andList(chosen.filter(team).map((m) => m.firstName))} (kooperativ) besonders` : ''}.${chosen.length === 1 ? ' Ohne die anderen gibt es keine Band, nur einen Solokünstler.' : ''}`;
    if (app.state.label.money < price && chosen.length) note.textContent += ' Dafür reicht das Geld gerade nicht.';
  };
  body.addEventListener('change', (e) => {
    const box = e.target.closest('[data-pick]');
    if (!box) return;
    if (box.checked) picked.add(Number(box.dataset.pick));
    else picked.delete(Number(box.dataset.pick));
    update();
  });
  body.querySelector('[data-close]').addEventListener('click', () => modal.close());
  body.querySelector('[data-ok]').addEventListener('click', async (e) => {
    e.target.disabled = true;
    const res = await mutate('submissionSign', { submissionId: s.submissionId, members: [...picked] });
    if (res) {
      modal.close();
      toast(picked.size === s.members.length ? `${s.bandName} hat unterschrieben.` : 'Unterschrieben.', 'ok');
    } else update();
  });
  update();
}

// ---------- Verschieben ----------

const HOLD_MS = 320;
const SLOP = 7;
let press = null;
let swallowUntil = 0;

function beginDrag() {
  if (!press || !press.el.isConnected) return;
  press.active = true;
  press.el.classList.add('dragging');
  navigator.vibrate?.(12);
}

function dragMove(x, y) {
  const { el, box, grab } = press;
  const rect = box.getBoundingClientRect();
  // Folgt dem Zeiger: Abstand von der natürlichen Mitte des Platzes (Layout ohne Transform).
  const cx = el.offsetLeft + el.offsetWidth / 2;
  const cy = el.offsetTop + el.offsetHeight / 2;
  el.style.transform = `translate(${x - rect.left - grab.x - cx}px, ${y - rect.top - grab.y - cy}px) scale(1.04)`;
  // Platz unter dem Zeiger suchen und tauschen.
  const over = [...box.children].find((c) => c !== el && (() => {
    const r = c.getBoundingClientRect();
    return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
  })());
  if (over) {
    const kids = [...box.children];
    if (kids.indexOf(over) < kids.indexOf(el)) box.insertBefore(el, over);
    else box.insertBefore(el, over.nextSibling);
  }
}

function dragEnd(cancelled) {
  const p = press;
  press = null;
  if (!p) return;
  clearTimeout(p.timer);
  if (!p.active) return;
  p.el.classList.remove('dragging');
  p.el.style.transform = '';
  swallowUntil = Date.now() + 400;
  if (cancelled || !p.el.isConnected) return;
  const order = [...p.box.children].map((c) => Number(c.dataset.sid));
  app.state.label.subOrder = order;
  api('submissionOrder', { order }).catch(() => {});
}

document.addEventListener('pointerdown', (e) => {
  const el = e.target.closest?.('.lb-slotitem');
  if (!el || e.button > 0 || e.target.closest('button, a, input, select, [data-act]:not(.lb-tape-big):not(.lb-flipper)')) return;
  const box = el.parentElement;
  const rect = box.getBoundingClientRect();
  const cx = el.offsetLeft + el.offsetWidth / 2;
  const cy = el.offsetTop + el.offsetHeight / 2;
  press = { el, box, x: e.clientX, y: e.clientY, active: false, touch: e.pointerType !== 'mouse', grab: { x: e.clientX - rect.left - cx, y: e.clientY - rect.top - cy } };
  if (press.touch) press.timer = setTimeout(beginDrag, HOLD_MS);
});

document.addEventListener('pointermove', (e) => {
  if (!press) return;
  if (!press.active) {
    const far = Math.hypot(e.clientX - press.x, e.clientY - press.y) > SLOP;
    if (press.touch) {
      if (far) { clearTimeout(press.timer); press = null; }
    } else if (far) {
      beginDrag();
    }
    if (!press?.active) return;
  }
  e.preventDefault();
  dragMove(e.clientX, e.clientY);
}, { passive: false });

document.addEventListener('touchmove', (e) => { if (press?.active) e.preventDefault(); }, { passive: false });
document.addEventListener('pointerup', () => dragEnd(false));
document.addEventListener('pointercancel', () => dragEnd(true));
document.addEventListener('click', (e) => { if (Date.now() < swallowUntil) { e.stopPropagation(); e.preventDefault(); } }, true);

// Ein Tipp neben die Kassette nimmt ihre Auswahl wieder weg.
document.addEventListener('click', (e) => {
  if (app.ui?.subSel != null && document.querySelector('[data-slots]') && !e.target.closest('.lb-tapeitem')) {
    app.ui.subSel = null;
    rerender();
  }
});

defineActions({
  subSel({ id }) {
    app.ui.subSel = app.ui.subSel === Number(id) ? null : Number(id);
    rerender();
  },
  subBox({ key }) {
    app.ui.subBox = key;
    rerender();
  },
  async subSave({ id, saved }) {
    if (await mutate('submissionSave', { submissionId: Number(id), saved: !!saved })) toast(saved ? 'Gemerkt – du findest sie unter „Gespeichert“.' : 'Zurück im Eingang.', 'ok');
  },
  subFilter({ key }) {
    app.ui.subFilter = key;
    rerender();
  },
  /** Karte umdrehen (ohne neu zu zeichnen, damit die Drehung zu sehen ist). */
  subFlip({ id }, el) {
    app.ui.subFlipped ??= new Set();
    const sid = Number(id);
    if (app.ui.subFlipped.has(sid)) app.ui.subFlipped.delete(sid);
    else app.ui.subFlipped.add(sid);
    el.classList.toggle('flipped');
  },
  /** Unterschreiben nach Rückfrage: „… für … ₲ in das Label aufnehmen?“ Bei Bands wählt man dort, wen man nimmt. */
  async subSign({ id }) {
    const s = app.state.submissions.find((x) => x.submissionId === Number(id));
    if (s.kind === 'band') {
      signBand(s);
      return;
    }
    if (await confirmDialog(`${s.firstName} für ${money(s.price)} in das Label aufnehmen?`, { ok: 'Aufnehmen' })) await actions.submissionSign({ id: String(id) });
  },
  subInfo({ id, index }) {
    const s = app.state.submissions.find((x) => x.submissionId === Number(id));
    openInfo(s, s.kind === 'band' ? s.members.find((m) => m.index === Number(index)) : s);
  },
  async submissionSign({ id }) {
    const s = app.state.submissions.find((x) => x.submissionId === Number(id));
    const res = await mutate('submissionSign', { submissionId: Number(id) });
    if (res) toast(`${s.firstName} ${s.lastName} hat unterschrieben.`, 'ok');
  },
  async submissionOpen({ id }) {
    app.ui.subSel = null;
    await mutate('submissionOpen', { submissionId: Number(id) });
  },
  async submissionDrop({ id }) {
    await mutate('submissionDrop', { submissionId: Number(id) });
  },
  async submissionsTest({ closed }) {
    await mutate('submissionsTest', { closed: !!closed });
  },
});
