import { genreGlyph, genreName, instrumentIcon, instrumentName, photo, sortedGenres } from '../cards.js';
import { api } from '../api.js';
import { app, cat, defineActions, mutate, rerender, serverNow } from '../core.js';
import { esc, money, toast } from '../ui.js';
import { levelClass, levelProgress } from './label.js';

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
          <button class="btn small primary" data-act="submissionOpen" data-id="${s.submissionId}" ${busy ? 'disabled title="Du sichtest gerade schon eine andere"' : ''}>Sichten</button>
          <button class="btn small ghost" data-act="submissionDrop" data-id="${s.submissionId}">Wegwerfen</button>
          <small>${expires(s)}</small></div>` : ''}
      </div>
    </div>`;
}

const split = (genres) => sortedGenres(genres).map((g, i, all) => `var(--lb-c-${g}) ${(i * 100) / all.length}% ${((i + 1) * 100) / all.length}%`).join(', ');
const genreTags = (genres) => `<div class="lb-genres">${sortedGenres(genres).map((g) => `<button class="lb-gt g-${g}" data-act="tip" data-tip="${esc(genreName(g))}">${genreGlyph(g)}</button>`).join('')}</div>`;
const instrumentRow = (i) => `<div class="lb-ins ${levelClass(i.skill)}"><span class="ico" data-act="tip" data-tip="${esc(instrumentName(i.type))}">${instrumentIcon(i.type)}</span>
  <span class="sk" title="Können ${Math.floor(i.skill)}"><i style="width:${levelProgress(i.skill)}%"></i></span>
  <b class="lv" data-act="tip" data-tip="Können ${Math.floor(i.skill)}">${levelCount(i.skill)}</b></div>`;

/** Visitenkarte eines einzelnen Musikers: fast so groß und so schräg wie die Kassette, aus der sie kommt. */
function card(s) {
  const chars = s.personality.map((p) => cat().personalities[p]).join(' · ');
  return `
    <article class="lb-card2">
      <div class="lb-sub-top">
        <span class="lb-ph" style="--split:linear-gradient(120deg, ${split(s.genres)})">${photo(s)}</span>
        <div class="lb-sub-name"><b title="${esc(chars)}">${esc(s.firstName)} ${esc(s.lastName)}</b>
          ${s.fameLevel ? `<span class="lb-sub-fame" title="${esc(cat().fameNames[s.fameLevel])}">${'★'.repeat(s.fameLevel)}</span>` : '<small class="muted">noch unbekannt</small>'}
          ${genreTags(s.genres)}</div>
      </div>
      <div class="lb-card-ins">${s.instruments.map(instrumentRow).join('')}</div>
      ${cardFoot(s, s.price, true)}
    </article>`;
}

function cardFoot(s, price, enough) {
  const money0 = app.state.label.money;
  return `<div class="lb-sub-foot">
    <b class="lb-price" title="${expires(s)}">${money(price)}</b>
    <button class="btn small primary" data-act="submissionSign" data-id="${s.submissionId}" ${money0 < price || !enough ? 'disabled' : ''}>Unterschreiben</button>
    <button class="btn small ghost lb-drop" data-act="submissionDrop" data-id="${s.submissionId}" title="Wegwerfen" aria-label="Wegwerfen">✕</button>
  </div>`;
}

/** Visitenkarte einer Band: Gesichter antippen, wer mit unterschreibt; die anderen bleiben liegen. */
function bandCard(s) {
  const picked = app.ui.subPick?.[s.submissionId] ?? new Set(s.members.map((m) => m.index));
  const chosen = s.members.filter((m) => picked.has(m.index));
  const price = chosen.reduce((a, m) => a + m.price, 0);
  return `
    <article class="lb-card2 band">
      <div class="lb-band-head"><small class="muted">Band</small><b>${esc(s.bandName)}</b></div>
      <div class="lb-band-faces">${s.members.map((m) => `
        <button class="lb-bm ${picked.has(m.index) ? 'on' : ''}" data-act="subPick" data-id="${s.submissionId}" data-index="${m.index}" aria-pressed="${picked.has(m.index)}"
          title="${esc(m.firstName)} · ${esc(m.instruments.map((i) => instrumentName(i.type)).join(', '))} · ${money(m.price)} – antippen zum Wählen">
          <span class="lb-ph sm" style="--split:linear-gradient(120deg, ${split(m.genres)})">${photo(m)}<i class="lb-tick">${picked.has(m.index) ? '✓' : ''}</i></span>
          <span class="lb-bm-row"><span class="ico">${instrumentIcon(m.instruments[0].type)}</span><b class="lv ${levelClass(m.instruments[0].skill)}">${levelCount(m.instruments[0].skill)}</b></span>
        </button>`).join('')}</div>
      ${cardFoot(s, price, chosen.length > 0)}
    </article>`;
}

function slot(s, busy) {
  const inner = s.state !== 'open' ? tape(s, busy) : (s.kind === 'band' ? bandCard(s) : card(s));
  // Eine eben gesichtete Kassette wird an ihrem Platz zur Karte (kurzes Umdrehen, nur beim ersten Zeigen).
  const fresh = s.state === 'open' && !app.ui.subSeen.has(s.submissionId);
  if (s.state === 'open') app.ui.subSeen.add(s.submissionId);
  return `<div class="lb-slotitem ${fresh ? 'fresh' : ''}" data-sid="${s.submissionId}" style="${scatter(s.submissionId)}">${inner}</div>`;
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
  const list = ordered(all).filter((s) => f === 'all' || (f === 'unheard' ? s.state !== 'open' : s.state === 'open' && levelOf(s) === Number(f)));
  const busy = all.some((x) => x.state === 'listening');
  return `
    <div class="section-head"><h1>Einsendungen</h1>${isTest() ? '<span class="lb-test"><button class="btn small" data-act="submissionsTest" data-closed="1">Test: neue Kassetten</button><button class="btn small" data-act="submissionsTest">Test: gesichtete Bewerber aller Stufen</button></span>' : ''}</div>
    <p class="muted">Jede Einsendung ist eine Kassette. Tipp sie an und sichte sie, dann siehst du, wer sich da bewirbt – ein Musiker oder eine ganze Band. Unterschreiben kostet einmalig eine Prämie, wegwerfen nichts. Wer zu lange liegt, zieht weiter. Du kannst alles frei hin und her schieben.</p>
    <div class="lb-filter">${[['all', 'Alle'], ['unheard', 'Ungehört'], ['0', 'Stufe 0'], ['1', 'Stufe 1'], ['2', 'Stufe 2'], ['3', 'Stufe 3']].map(([k, label]) => `<button class="${f === k ? 'on' : ''}" data-act="subFilter" data-key="${k}">${label}</button>`).join('')}</div>
    ${list.length ? `<div class="lb-slots" data-slots>${list.map((x) => slot(x, busy)).join('')}</div>` : '<div class="empty">Gerade liegt nichts im Briefkasten. Es trudelt laufend etwas ein.</div>'}`;
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
  if (!el || e.button > 0 || e.target.closest('button, a, input, select, [data-act]:not(.lb-tape-big)')) return;
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
  subFilter({ key }) {
    app.ui.subFilter = key;
    rerender();
  },
  subPick({ id, index }) {
    const s = app.state.submissions.find((x) => x.submissionId === Number(id));
    app.ui.subPick ??= {};
    const set = app.ui.subPick[id] ?? new Set(s.members.map((m) => m.index));
    if (set.has(Number(index))) set.delete(Number(index));
    else set.add(Number(index));
    app.ui.subPick[id] = set;
    rerender();
  },
  async submissionSign({ id }) {
    const s = app.state.submissions.find((x) => x.submissionId === Number(id));
    const body = { submissionId: Number(id) };
    if (s.kind === 'band') body.members = [...(app.ui.subPick?.[id] ?? new Set(s.members.map((m) => m.index)))];
    const res = await mutate('submissionSign', body);
    if (res) {
      delete app.ui.subPick?.[id];
      toast(s.kind === 'band' ? 'Unterschrieben.' : `${s.firstName} ${s.lastName} hat unterschrieben.`, 'ok');
    }
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
