import { genreGlyph, genreName, hiddenTraits, traitNames, instrumentIcon, instrumentName, photo, sortedGenres } from '../cards.js';
import { api } from '../api.js';
import { actions, app, bandById, cat, defineActions, isBusy, mutate, musicianById, rerender, serverNow } from '../core.js';
import { confirmDialog, esc, money, off, openModal, toast } from '../ui.js';
import { fitWord, levelClass, levelProgress, relColor } from './label.js';
import { INTERN_JOBS, closeTalk, officeScene } from '../office.js';

/**
 * Posteingang: Demos (Musiker bewerben sich von selbst beim Label), Interview-Anfragen und Bewerbungen fürs Label.
 * Demos unterschreibt man gegen eine einmalige Prämie oder wirft sie weg; Kassetten und Visitenkarten teilen sich dieselben Plätze
 * (nichts verschiebt sich beim Sichten) und lassen sich frei sortieren. Ohne Assistenz liegt alles auf einem Stapel, mit Assistenz in Reitern.
 */

const isTest = () => document.documentElement.classList.contains('is-test') || ['localhost', '127.0.0.1'].includes(location.hostname);

function expires(s) {
  const left = s.expiresAt - serverNow();
  if (left <= 0) return 'läuft ab';
  return left >= 86400 ? `läuft ab in ${Math.ceil(left / 86400)} Tagen` : `läuft ab in ${Math.max(1, Math.ceil(left / 3600))} Std.`;
}

/** Kurz und sichtbar auf jeder Einsendung: wie lange sie noch da ist (rot ab den letzten 12 Stunden). */
function leftPill(s, soon = 12 * 3600) {
  const left = s.expiresAt - serverNow();
  const text = left >= 86400 ? `noch ${Math.ceil(left / 86400)} ${Math.ceil(left / 86400) === 1 ? 'Tag' : 'Tage'}` : `noch ${Math.max(1, Math.ceil(left / 3600))} Std.`;
  return `<span class="lb-left ${left < soon ? 'soon' : ''}" title="Danach zieht ${s.kind === 'band' ? 'die Band' : 'die Einsendung'} weiter">${text}</span>`;
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

/** Kassette: Antippen hört sie gleich an (ein paar Sekunden), das kleine × wirft sie ungehört weg. */
function tape(s, busy) {
  const listening = s.state === 'listening';
  const total = cat().rules.submissionListen ?? 5;
  const elapsed = listening ? Math.max(0, total - (s.opensAt - serverNow())) : 0;
  return `
    <div class="lb-tapeitem ${listening ? 'playing' : ''}">
      <div class="lb-tape-big" ${listening ? '' : `data-act="submissionOpen" data-id="${s.submissionId}" ${off(busy ? 'Du hörst gerade schon eine andere Kassette an.' : '')}`} title="${listening ? 'Wird angehört …' : 'Anhören'}">${cassette(s.hint)}
        ${listening && s.opensAt - serverNow() <= total ? `<span class="lb-prog tiny"><i style="animation-duration:${total}s;animation-delay:-${elapsed.toFixed(2)}s"></i></span>` : ''}
        ${listening && s.opensAt - serverNow() > total ? '<small class="lb-env-wait">wartet aufs Praktikum</small>' : ''}
      </div>
      ${listening ? '' : `<button class="lb-tape-x" data-act="submissionDrop" data-id="${s.submissionId}" title="Ungehört wegwerfen" aria-label="Ungehört wegwerfen">✕</button>`}
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

// ---------- Umschläge: Man weiß nicht, was drin ist, bis man auspackt ----------

const ENVELOPE = `<svg class="lb-env-svg" viewBox="0 0 120 80" aria-hidden="true">
  <rect x="4" y="8" width="112" height="66" rx="4" fill="#d9c7a3" stroke="#a88f63" stroke-width="2"/>
  <path d="M4 12 L60 46 L116 12" fill="none" stroke="#a88f63" stroke-width="2"/>
  <path d="M4 74 L48 40 M116 74 L72 40" fill="none" stroke="#c2ad85" stroke-width="1.5"/>
  <rect x="88" y="16" width="18" height="14" rx="1.5" fill="#efe4cc" stroke="#b9483f" stroke-width="1.2" stroke-dasharray="2 1.5"/>
</svg>`;

/** Neu seit dem letzten Besuch im Posteingang (bleibt bis man wieder hereinkommt). */
const isNew = (x) => x.createdAt > (app.ui.postSeenAt ?? Infinity);
const newTag = (x) => (isNew(x) ? '<span class="lb-new">NEU</span>' : '');

/** Ein Umschlag (Kassette oder Brief): antippen = auspacken (ein paar Sekunden); kleines ✕ = ungeöffnet wegwerfen. */
function envelopeSlot(x, key, id) {
  const unpacking = x.state === 'unpacking';
  const total = cat().rules.postUnpack ?? 3;
  const left = unpacking ? x.unpacksAt - serverNow() : 0;
  // Beim Praktikum warten die anderen, bis sie dran sind; nur der aktuelle zeigt den Fortschritt.
  const current = unpacking && left <= total;
  const busy = !unpacking && [...(app.state.submissions ?? []), ...(app.state.mail ?? [])].some((y) => y.state === 'unpacking' && !y.byIntern);
  const drop = key === 's' ? 'submissionDrop' : 'mailDrop';
  return `<div class="lb-slotitem lb-mailitem" data-post="${key}-${id}" style="${scatter(key === 's' ? id : id * 7 + 3)}">
    <div class="lb-tapeitem lb-envitem ${unpacking ? 'playing' : ''}">
      <div class="lb-env" ${unpacking ? '' : `data-act="postUnpack" data-table="${key}" data-id="${id}" ${off(busy ? 'Du packst gerade schon einen Umschlag aus.' : '')}`} title="${unpacking ? 'Wird ausgepackt …' : 'Auspacken'}">${ENVELOPE}
        ${current ? `<span class="lb-prog tiny"><i style="animation-duration:${total}s;animation-delay:-${Math.max(0, total - left).toFixed(2)}s"></i></span>` : ''}
        ${unpacking && !current ? '<small class="lb-env-wait">wartet aufs Praktikum</small>' : ''}
      </div>
      ${unpacking ? '' : `<button class="lb-tape-x" data-act="${drop}" data-id="${id}" title="Ungeöffnet wegwerfen" aria-label="Ungeöffnet wegwerfen">✕</button>`}
    </div>
    <span class="lb-slotbar"><span class="lb-slotleft">${newTag(x)}${leftPill(x)}</span>${key === 's' ? saveBtn(x) : mailSave(x)}</span></div>`;
}

function slot(s, busy) {
  if (s.state === 'envelope' || s.state === 'unpacking') return envelopeSlot(s, 's', s.submissionId);
  const inner = s.state !== 'open' ? tape(s, busy) : (s.kind === 'band' ? bandCard(s) : card(s));
  // Eine eben gesichtete Kassette wird an ihrem Platz zur Karte (kurzes Umdrehen, nur beim ersten Zeigen).
  const fresh = s.state === 'open' && !app.ui.subSeen.has(s.submissionId);
  if (s.state === 'open') app.ui.subSeen.add(s.submissionId);
  return `<div class="lb-slotitem ${fresh ? 'fresh' : ''}" data-sid="${s.submissionId}" data-post="s-${s.submissionId}" style="${scatter(s.submissionId)}">${inner}<span class="lb-slotbar"><span class="lb-slotleft">${newTag(s)}${leftPill(s)}</span>${saveBtn(s)}</span></div>`;
}

/** Merkzahl für die eigene Reihenfolge: Kassetten mit ihrer Nummer, Briefe mit minus ihrer Nummer. */
const orderKey = (x) => (x.submissionId ? x.submissionId : -x.mailId);
/**
 * Eigene Reihenfolge: Neues zuerst (neueste vorn), danach die gemerkte Anordnung. Ein ausgepackter Umschlag behält
 * seine Merkzahl, sein Inhalt liegt also genau dort, wo vorher der Umschlag lag.
 */
function ordered(list) {
  const saved = app.state.label.subOrder ?? [];
  const known = list.filter((x) => saved.includes(orderKey(x))).sort((a, b) => saved.indexOf(orderKey(a)) - saved.indexOf(orderKey(b)));
  return [...list.filter((x) => !saved.includes(orderKey(x))).sort((a, b) => b.createdAt - a.createdAt), ...known];
}

// ---------- Briefe: Interview-Anfragen und Bewerbungen ----------

const days = (sec) => (sec >= 86400 ? `${Math.round(sec / 86400)} ${Math.round(sec / 86400) === 1 ? 'Tag' : 'Tage'}` : mins(sec));
const fmtFactor = (f) => `${String(Math.round(f * 100) / 100).replace('.', ',')}-mal`;
const instrumentAt = (type) => cat().instruments[type]?.at ?? '';
const mins = (sec) => (sec >= 3600 ? `${Math.round(sec / 360) / 10} Std.` : `${Math.round(sec / 60)} Min.`);
const roleName = (m) => cat().staffRoles[m.role]?.tiers[m.tier] ?? '';
const fmtFame = (v) => (v >= 10 ? Math.round(v) : Math.round(v * 10) / 10).toLocaleString('de-DE');

function interviewLetter(m) {
  const who = musicianById(m.musicianId);
  if (!who) return '';
  const away = isBusy(who.busyUntil) || (who.bandId && isBusy(bandById(who.bandId)?.busyUntil));
  return `
    <article class="lb-letter interview">
      <div class="lb-lt-head"><span class="lb-lt-kind">Interview-Anfrage</span></div>
      <div class="lb-lt-body"><span class="lb-ph sm">${photo(who)}</span>
        <p><b>${esc(m.medium)}</b> möchte mit <b>${esc(who.firstName)}</b> sprechen.<small class="lb-lt-gain">+${fmtFame(m.fame)} Bekanntheit · dauert ${mins(m.seconds)}</small></p></div>
      <div class="lb-card-foot"><span class="lb-lt-space"></span>
        <button class="lb-iconbtn drop" data-act="mailDrop" data-id="${m.mailId}" title="Absagen" aria-label="Absagen">✕</button>
        <button class="btn small primary" data-act="mailAccept" data-id="${m.mailId}" ${off(away ? `${who.firstName} ist gerade unterwegs.` : '')}>Zusagen</button>
      </div>
    </article>`;
}

function jobLetter(m) {
  const money0 = app.state.label.money;
  const what = m.role === 'intern' ? 'sortiert die Post und schreibt dir Notizen auf die Übersicht'
    : m.role === 'assistant' ? `sortiert die Post, hört ${m.perDay} Kassetten am Tag an oder kümmert sich um die Presse`
    : m.role === 'scout' ? `${m.candidates} Talente in ${mins(m.seconds)} (Können bis ${m.maxSkill})`
    : m.role === 'teacher' ? `${instrumentName(m.instrument)} für einen Musiker: +${m.gain} Können (bis ${m.maxSkill})${m.genre ? `, bei ${genreName(m.genre)} mehr` : ''}`
    : m.role === 'coach' ? `${days(m.seconds)} für eine Band: Songs ${fmtFactor(m.speed)} so schnell, Platte +${Math.round(m.quality * 100)} %`
    : m.role === 'manager' ? `${m.genres.map((g) => genreName(g)).join(', ')} · ${m.skills.map((k) => cat().managerSkills[k]?.name ?? k).join(', ')}` : '';
  const book = ['teacher', 'coach'].includes(m.role);
  const temporary = book || m.role === 'intern';
  return `
    <article class="lb-letter job">
      <div class="lb-lt-head"><span class="lb-lt-kind">${m.role === 'teacher' ? 'Unterricht' : 'Bewerbung'}</span>
        ${temporary ? `<span class="lb-lt-term" title="Kommt nur für diese Zeit, danach geht ${esc(m.firstName)} wieder">${days(m.seconds)}</span>` : '<span class="lb-lt-term fix" title="Bleibt für immer beim Label, du bezahlst nur einmal">fest</span>'}</div>
      <div class="lb-lt-body"><span class="lb-ph sm">${photo({ id: m.mailId, look: m.look })}</span>
        <p><b>${esc(m.firstName)} ${esc(m.lastName)}</b><br>${esc(roleName(m))}<small class="muted">${esc(what)}</small></p></div>
      <div class="lb-card-foot">
        <b class="lb-price" title="Einmal bezahlen, keine laufenden Kosten">${money(m.price)}</b>
        <button class="lb-iconbtn drop" data-act="mailDrop" data-id="${m.mailId}" title="Absagen" aria-label="Absagen">✕</button>
        <button class="btn small primary" data-act="${book ? 'tutorAsk' : 'mailHire'}" data-id="${m.mailId}" ${off(money0 < m.price ? `Dafür fehlen noch ${money(m.price - money0)}.` : '')}>${book ? 'Buchen' : 'Einstellen'}</button>
      </div>
    </article>`;
}

/** Bericht des Scouts: wer gefunden wurde, sieht man erst beim Ansehen (eins nach dem anderen). */
function reportLetter(m) {
  const open = m.cards.filter((c) => !c.signed).length;
  const task = [m.genre ? genreName(m.genre) : '', m.instrument ? instrumentName(m.instrument) : ''].filter(Boolean).join(' · ') || 'ohne Vorgabe';
  return `
    <article class="lb-letter report">
      <div class="lb-lt-head"><span class="lb-lt-kind">Bericht vom Scout</span></div>
      <div class="lb-lt-body"><span class="lb-ph sm">${photo({ id: m.mailId, look: m.look })}</span>
        <p><b>${esc(m.scoutName)}</b> stellt dir ${m.cards.length} Talente vor.<small>Auftrag: ${esc(task)}</small></p></div>
      <div class="lb-card-foot"><span class="lb-lt-space">${open < m.cards.length ? `<small class="lb-lt-note">${m.cards.length - open} unterschrieben</small>` : ''}</span>
        <button class="lb-iconbtn drop" data-act="mailDrop" data-id="${m.mailId}" title="Wegwerfen" aria-label="Wegwerfen">✕</button>
        <button class="btn small primary" data-act="reportShow" data-id="${m.mailId}">Ansehen</button>
      </div>
    </article>`;
}

const mailSave = (m) => `<button class="lb-save ${m.saved ? 'on' : ''}" data-act="mailSave" data-id="${m.mailId}" data-saved="${m.saved ? '' : '1'}" title="${m.saved ? 'Zurück in den Eingang' : 'Merken'}" aria-label="${m.saved ? 'Zurück in den Eingang' : 'Merken'}">${BOOKMARK}</button>`;
const letterSlot = (m) => (m.kind === 'envelope' ? envelopeSlot(m, 'm', m.mailId) : `<div class="lb-slotitem lb-mailitem" data-post="m-${m.mailId}" style="${scatter(m.mailId * 7 + 3)}">${m.kind === 'interview' ? interviewLetter(m) : m.kind === 'report' ? reportLetter(m) : jobLetter(m)}<span class="lb-slotbar"><span class="lb-slotleft">${newTag(m)}${leftPill(m)}</span>${mailSave(m)}</span></div>`);

/** Gebuchte Lehrer und Coaches: bei wem, was schon gebracht ist, wie lange noch. */
function tutorBar(t) {
  const who = t.role === 'teacher' ? musicianById(t.musicianId) : bandById(t.bandId);
  if (!who) return '';
  const text = t.role === 'teacher'
    ? `<b>${esc(t.firstName)}</b> unterrichtet ${esc(who.firstName)} ${esc(instrumentAt(t.instrument))}.<small class="muted">Schon +${fmtFame(t.done)} von ${fmtFame(t.gain)} Können, noch <span data-until="${t.until}"></span></small>`
    : `<b>${esc(t.firstName)}</b> hilft ${esc(who.name)} beim Songschreiben.<small class="muted">Songs ${fmtFactor(t.speed)} so schnell, die nächste Platte ${Math.round(t.quality * 100)} % besser. Noch <span data-until="${t.until}"></span></small>`;
  return `<div class="lb-assist"><span class="lb-assist-faces"><span class="lb-ph sm">${photo({ id: t.id, look: t.look })}</span></span><span>${text}</span></div>`;
}

export function render() {
  const s = app.state;
  const all = s.submissions ?? [];
  const mail = s.mail ?? [];
  const a = s.assistant;
  // Beim ersten Besuch gilt alles Gesichtete als schon gesehen (kein Umdrehen auf einen Schlag).
  app.ui.subSeen ??= new Set(all.filter((x) => x.state === 'open').map((x) => x.submissionId));
  // Alles kommt in den Eingang; erst wer sortiert (Praktikum auf Zuruf, Assistenz laufend), verteilt es auf die Fächer.
  const tabOf = (x) => (x.submissionId ? 'demos' : x.kind === 'report' ? 'demos' : x.kind);
  const fach = (x) => (x.saved ? 'saved' : x.box ?? (!x.sorted ? 'inbox' : tabOf(x)));
  const items = [...mail, ...all];
  const count = (k) => items.filter((x) => fach(x) === k).length;
  const tabs = [['inbox', 'Eingang'], ['demos', 'Demos'], ['interview', 'Anfragen'], ['job', 'Bewerbungen'], ['saved', 'Gespeichert']];
  const box = app.ui.subBox ?? (count('inbox') ? 'inbox' : 'demos');
  app.ui.subBoxShown = box;
  // NEU gilt für alles, was seit dem letzten Besuch kam (auch, was während des Besuchs kommt).
  app.ui.postSeenAt ??= s.label.inboxSeenAt ?? 0;
  const f = app.ui.subFilter ?? 'all';
  const showFilter = box === 'demos' || box === 'inbox';
  const inBox = items.filter((x) => fach(x) === box);
  // Alles liegt in einer Reihe und lässt sich frei sortieren; der Filter zeigt nur ausgepackte Kassetten.
  const isDemo = (x) => x.submissionId && x.state !== 'envelope' && x.state !== 'unpacking';
  const shown = ordered(f === 'all' ? inBox : inBox.filter((x) => isDemo(x) && (f === 'unheard' ? x.state !== 'open' : x.state === 'open' && levelOf(x) === Number(f))));
  const busy = all.some((x) => x.state === 'listening' && !x.byIntern);
  const empty = box === 'saved' ? 'Noch nichts gemerkt. Mit dem Lesezeichen legst du etwas hierher – ablaufen tut es trotzdem.'
    : box === 'interview' ? 'Gerade fragt niemand nach einem Interview.' : box === 'job' ? 'Gerade bewirbt sich niemand.'
    : box === 'demos' ? 'Gerade liegt keine Kassette in diesem Fach.'
    : 'Gerade liegt nichts im Eingang. Es trudelt laufend etwas ein.';
  // Wer hineinschaut, hat das Neue gesehen: Die Zahl am Menüpunkt fängt wieder bei null an.
  if (s.inboxNew > 0) {
    s.inboxNew = 0;
    s.label.inboxSeenAt = Math.floor(serverNow());
    api('inboxSeen', {}).catch(() => {});
    setTimeout(rerender, 0);
  }
  const slotOf = (x) => (x.submissionId ? slot(x, busy) : letterSlot(x));
  return `
    <div class="section-head"><h1>Posteingang</h1>${isTest() ? '<span class="lb-test"><button class="btn small" data-act="submissionsTest" data-closed="1">Test: neue Kassetten</button><button class="btn small" data-act="submissionsTest">Test: gesichtete Bewerber</button><button class="btn small" data-act="mailTest">Test: Briefe</button></span>' : ''}</div>
    <p class="muted">Hier kommt alles an, in Umschlägen: Kassetten von Musikern, die zu dir wollen, Interview-Anfragen für deine Leute und Bewerbungen fürs Label. Tipp einen Umschlag an, dann packst du ihn aus. Halt etwas fest und zieh es auf einen Reiter, dann liegt es in diesem Fach. Alles liegt vier Tage, dann ist es weg.</p>
    ${(s.tutors ?? []).map(tutorBar).join('')}
    ${officeScene(a, count('inbox'))}
    <div class="lb-boxes">${tabs.map(([k, label]) => `<button class="${box === k ? 'on' : ''}" data-act="subBox" data-key="${k}" data-box="${k}">${label} <small>${count(k)}</small></button>`).join('')}</div>
    ${showFilter ? `<div class="lb-filter">${[['all', 'Alle'], ['unheard', 'Ungehört'], ['0', 'Stufe 0'], ['1', 'Stufe 1'], ['2', 'Stufe 2'], ['3', 'Stufe 3']].map(([k, label]) => `<button class="${f === k ? 'on' : ''}" data-act="subFilter" data-key="${k}">${label}</button>`).join('')}</div>` : ''}
    ${shown.length ? `<div class="lb-slots" data-slots>${shown.map(slotOf).join('')}</div>` : `<div class="empty">${empty}</div>`}`;
}

// ---------- Scout losschicken und Bericht ansehen ----------

function scoutDialog(x) {
  const pick = { genre: '', instrument: '' };
  const modal = openModal({
    className: 'lb-rules-modal',
    render: () => `
      <div class="lb-cap">Scout</div>
      <h2>${esc(x.firstName)} losschicken</h2>
      <p class="muted">${esc(x.firstName)} ist ${mins(x.seconds)} unterwegs (auch wenn du nicht spielst) und stellt dir dann ${x.candidates} Talente vor. Du kannst so viele davon unterschreiben, wie du willst.</p>
      <h3>Genre</h3>
      <div class="lb-filter"><button class="${pick.genre === '' ? 'on' : ''}" data-pick="genre" data-v="">Egal</button>${Object.keys(cat().genres).map((g) => `<button class="${pick.genre === g ? 'on' : ''}" data-pick="genre" data-v="${g}">${esc(genreName(g))}</button>`).join('')}</div>
      <h3>Instrument</h3>
      <div class="lb-filter"><button class="${pick.instrument === '' ? 'on' : ''}" data-pick="instrument" data-v="">Egal</button>${Object.keys(cat().instruments).map((i) => `<button class="${pick.instrument === i ? 'on' : ''}" data-pick="instrument" data-v="${i}">${esc(instrumentName(i))}</button>`).join('')}</div>
      <div class="dialog-actions"><button class="btn ghost" data-close>Abbrechen</button><button class="btn primary" data-ok>Losschicken</button></div>`,
  });
  modal.body.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-pick]');
    if (b) {
      pick[b.dataset.pick] = b.dataset.v;
      modal.refresh();
      return;
    }
    if (e.target.closest('[data-close]')) modal.close();
    if (e.target.closest('[data-ok]')) {
      if (await mutate('scoutSend', { staffId: x.id, ...pick })) {
        modal.close();
        toast(`${x.firstName} ist unterwegs.`, 'ok');
      }
    }
  });
}

/** Ein Talent aus dem Bericht, als ganze Person: Bild, Genres, Instrumente, Charakter, Prämie. Durchblättern mit dem Pfeil. */
function reportDialog(mailId) {
  let i = 0;
  const modal = openModal({
    className: 'lb-report-modal',
    render: () => {
      const m = app.state.mail.find((x) => x.mailId === mailId);
      if (!m) return '<p class="muted">Der Bericht ist nicht mehr da.</p><div class="dialog-actions"><button class="btn primary" data-close>Schließen</button></div>';
      i = Math.min(i, m.cards.length - 1);
      const c = m.cards[i];
      const money0 = app.state.label.money;
      return `
        <div class="lb-cap">Bericht von ${esc(m.scoutName)}</div>
        <div class="lb-rp">
          <button class="lb-rp-arrow" data-step="-1" ${off(i === 0 ? 'Das ist das erste Talent.' : '')} aria-label="Zurück">‹</button>
          <div class="lb-rp-card">
            <div class="lb-info-head"><span class="lb-ph" style="--split:linear-gradient(120deg, ${split(c.genres)})">${photo(c)}</span>
              <div><h2>${esc(c.firstName)} ${esc(c.lastName)}</h2>${genreTags(c.genres)}</div></div>
            <div class="lb-rp-ins">${c.instruments.map((x) => `<div>${instrumentRow(x)}<span>${esc(instrumentName(x.type))} · Können ${Math.floor(x.skill)}</span></div>`).join('')}</div>
            <div class="lb-traits">${traitChips(c, 'tip')}</div>
            ${c.signed ? '<p class="lb-rp-done">Hat schon bei dir unterschrieben.</p>' : `<div class="dialog-actions">
              <button class="btn primary" data-sign="${c.index}" ${off(money0 < c.price ? `Dafür fehlen noch ${money(c.price - money0)}.` : '')}>Unterschreiben · ${money(c.price)}</button></div>`}
          </div>
          <button class="lb-rp-arrow" data-step="1" ${off(i >= m.cards.length - 1 ? 'Das ist das letzte Talent.' : '')} aria-label="Weiter">›</button>
        </div>
        <p class="lb-rp-count muted">${i + 1} von ${m.cards.length}</p>
        <div class="dialog-actions"><button class="btn ghost" data-close>Schließen</button></div>`;
    },
  });
  modal.body.addEventListener('click', async (e) => {
    const step = e.target.closest('[data-step]');
    if (step && step.getAttribute('aria-disabled') !== 'true') {
      i += Number(step.dataset.step);
      modal.refresh();
      return;
    }
    const sign = e.target.closest('[data-sign]');
    if (sign && sign.getAttribute('aria-disabled') !== 'true') {
      const m = app.state.mail.find((x) => x.mailId === mailId);
      const c = m.cards[Number(sign.dataset.sign)];
      if (await mutate('scoutReportSign', { mailId, index: c.index })) toast(`${c.firstName} hat unterschrieben.`, 'ok');
      return;
    }
    if (e.target.closest('[data-close]')) modal.close();
  });
}

// ---------- Manager einstellen ----------

/** Bewerbung eines Managers: was er kann, wofür er steht und zu welcher Band er passen würde. */
function managerDialog(m) {
  const best = app.state.bands.map((b) => ({ b, f: m.fits?.[b.id] ?? 1 })).sort((x, y) => y.f - x.f).slice(0, 3);
  const modal = openModal({
    className: 'lb-rules-modal',
    html: `
      <div class="lb-cap">Bewerbung</div>
      <div class="lb-info-head"><span class="lb-ph">${photo({ id: m.mailId, look: m.look })}</span>
        <div><h2>${esc(m.firstName)} ${esc(m.lastName)}</h2><small class="muted">${esc(roleName(m))} · Stärke ${m.level}</small></div></div>
      <h3>Kann</h3>
      <ul class="lb-trait-list">${m.skills.map((k) => `<li><b>${esc(cat().managerSkills[k]?.name ?? k)}</b> ${esc(cat().managerSkills[k]?.desc ?? '')}</li>`).join('')}</ul>
      <h3>Am liebsten</h3>
      <p>${m.genres.map((g) => esc(genreName(g))).join(' und ')}, Stil: ${esc(cat().personalities[m.style] ?? '')}. Bands mit diesem Genre und Musiker mit diesem Charakterzug passen am besten.</p>
      ${best.length ? `<p class="muted">${best.map(({ b, f }) => `${esc(b.name)}: ${fitWord(f)}`).join(' · ')}</p>` : ''}
      <div class="dialog-actions"><button class="btn ghost" data-close>Abbrechen</button><button class="btn primary" data-ok ${off(app.state.label.money < m.price ? `Dafür fehlen noch ${money(m.price - app.state.label.money)}.` : '')}>Einstellen · ${money(m.price)}</button></div>`,
  });
  modal.body.addEventListener('click', async (e) => {
    if (e.target.closest('[data-close]')) return modal.close();
    const ok = e.target.closest('[data-ok]');
    if (!ok || ok.getAttribute('aria-disabled') === 'true') return;
    if (await mutate('mailHire', { mailId: m.mailId })) {
      modal.close();
      toast(`${m.firstName} ist jetzt Manager. Auf der Seite einer Band kannst du ${m.firstName} einsetzen.`, 'ok');
    }
  });
}

// ---------- Lehrer und Coaches buchen ----------

/** Für wen? Lehrer: Musiker, die das Instrument spielen und noch lernen können. Coach: eine Band oder ein Solokünstler. */
function tutorDialog(m) {
  const s = app.state;
  const taken = new Set((s.tutors ?? []).filter((t) => t.role === m.role).map((t) => (m.role === 'teacher' ? t.musicianId : t.bandId)));
  const rows = m.role === 'teacher'
    ? s.musicians.filter((x) => x.instruments.some((i) => i.type === m.instrument)).map((x) => {
      const skill = x.instruments.find((i) => i.type === m.instrument).skill;
      const why = taken.has(x.id) ? `${x.firstName} hat schon Unterricht.` : skill >= m.maxSkill ? `${x.firstName} kann schon mehr, als ${m.firstName} beibringt.` : '';
      const extra = m.genre && x.genres.includes(m.genre) ? ` · mag auch ${genreName(m.genre)}: mehr Können` : '';
      return { id: x.id, html: `<span class="lb-ph sm">${photo(x)}</span><span><b>${esc(x.firstName)} ${esc(x.lastName)}</b><small class="muted">Können ${Math.floor(skill)}${esc(extra)}</small></span>`, why };
    })
    : s.bands.map((b) => ({ id: b.id, html: `<span><b>${esc(b.name)}</b><small class="muted">${b.memberIds.length === 1 ? 'Solokünstler' : `${b.memberIds.length} Musiker`} · ${Math.floor(b.mood.songs ?? 0)} Songs</small></span>`, why: taken.has(b.id) ? `${b.name} hat schon einen Coach.` : '' }));
  const modal = openModal({
    className: 'lb-rules-modal',
    html: `
      <div class="lb-cap">${m.role === 'teacher' ? 'Unterricht buchen' : 'Coach buchen'}</div>
      <h2>${m.role === 'teacher' ? `Wer bekommt ${esc(instrumentName(m.instrument))}-Unterricht?` : `Wem hilft ${esc(m.firstName)}?`}</h2>
      <p class="muted">${esc(m.firstName)} ist ${days(m.seconds)} nur für ${m.role === 'teacher' ? 'diesen einen Musiker' : 'diese eine Band'} da und geht danach wieder. Du bezahlst einmal ${money(m.price)}.</p>
      <p class="muted">${m.role === 'teacher' ? 'Der Unterricht läuft nebenbei: Der Musiker kann in der Zeit ganz normal proben, auftreten und aufnehmen. Das Können wächst trotzdem jede Stunde.' : 'Die Band kann in der Zeit ganz normal proben, auftreten und aufnehmen.'}</p>
      ${rows.length ? `<div class="lb-pick">${rows.map((r) => `<button class="lb-pick-row" data-target="${r.id}" ${off(r.why)}>${r.html}</button>`).join('')}</div>`
        : `<p class="empty">${m.role === 'teacher' ? `Bei dir spielt niemand ${esc(instrumentName(m.instrument))}.` : 'Du hast noch keine Band.'}</p>`}
      <div class="dialog-actions"><button class="btn ghost" data-close>Abbrechen</button></div>`,
  });
  modal.body.addEventListener('click', async (e) => {
    if (e.target.closest('[data-close]')) modal.close();
    const row = e.target.closest('[data-target]');
    if (!row || row.getAttribute('aria-disabled') === 'true') return;
    if (await mutate('mailHire', { mailId: m.mailId, target: Number(row.dataset.target) })) {
      modal.close();
      toast(`${m.firstName} ist gebucht.`, 'ok');
    }
  });
}

// ---------- Regeln der Assistenz ----------

function rulesDialog() {
  const a = app.state.assistant;
  const r = { ...a.rules, genres: [...a.rules.genres] };
  const need = () => '';
  const modal = openModal({
    className: 'lb-rules-modal',
    render: () => `
      <div class="lb-cap">Assistenz</div>
      <h2>Was soll sie behalten?</h2>
      <p class="muted">Was nicht passt, wirft sie gleich nach dem Anhören weg. Gemerkte Kassetten fasst sie nicht an.</p>
      <h3>Können</h3>
      <div class="lb-filter">${[0, 1, 2, 3].map((l) => `<button class="${r.minLevel === l ? 'on' : ''}" data-rule="minLevel" data-v="${l}" ${off(need('minLevel'))}>${l === 0 ? 'Alle' : `ab Stufe ${l}`}</button>`).join('')}</div>
      <h3>Genres</h3>
      <div class="lb-filter">${Object.keys(cat().genres).map((g) => `<button class="${r.genres.includes(g) ? 'on' : ''}" data-rule="genre" data-v="${g}" ${off(need('genres'))}>${esc(genreName(g))}</button>`).join('')}</div>
      <small class="muted">Nichts gewählt heißt: alle Genres.</small>
      <h3>Einzeln oder Band</h3>
      <div class="lb-filter">${[['any', 'Beides'], ['solo', 'Nur Einzelne'], ['band', 'Nur Bands']].map(([k, label]) => `<button class="${r.kind === k ? 'on' : ''}" data-rule="kind" data-v="${k}" ${off(need('kind'))}>${label}</button>`).join('')}</div>
      <div class="dialog-actions"><button class="btn ghost" data-close>Abbrechen</button><button class="btn primary" data-ok>Speichern</button></div>`,
  });
  modal.body.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-rule]');
    if (b && b.getAttribute('aria-disabled') !== 'true') {
      const v = b.dataset.v;
      if (b.dataset.rule === 'minLevel') r.minLevel = Number(v);
      if (b.dataset.rule === 'kind') r.kind = v;
      if (b.dataset.rule === 'genre') r.genres = r.genres.includes(v) ? r.genres.filter((x) => x !== v) : [...r.genres, v];
      modal.refresh?.();
      return;
    }
    if (e.target.closest('[data-close]')) modal.close();
    if (e.target.closest('[data-ok]')) {
      if (await mutate('assistRules', { rules: r })) {
        modal.close();
        toast('Die Regeln gelten ab jetzt.', 'ok');
      }
    }
  });
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
  const hit = (c) => {
    const r = c.getBoundingClientRect();
    return x >= r.left && x <= r.right && y >= r.top && y <= r.bottom;
  };
  // Über einem Reiter: Dort hinein legen (beim Loslassen).
  const tab = [...document.querySelectorAll('.lb-boxes [data-box]')].find((t) => t.dataset.box !== app.ui.subBoxShown && hit(t));
  for (const t of document.querySelectorAll('.lb-boxes [data-box]')) t.classList.toggle('drop', t === tab);
  press.tab = tab?.dataset.box ?? null;
  if (tab) return;
  // Platz unter dem Zeiger suchen und tauschen (Kassetten, Briefe und Umschläge).
  const over = [...box.children].find((c) => c !== el && c.dataset.post && hit(c));
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
  for (const t of document.querySelectorAll('.lb-boxes .drop')) t.classList.remove('drop');
  swallowUntil = Date.now() + 400;
  if (cancelled || !p.el.isConnected) {
    p.el.style.transform = '';
    return;
  }
  if (p.tab) {
    const [table, id] = p.el.dataset.post.split('-');
    p.el.classList.add('moved');
    mutate('postMove', { table, id: Number(id), box: p.tab });
    return;
  }
  p.el.style.transform = '';
  const order = [...p.box.children].map((c) => c.dataset.post?.split('-')).filter(Boolean).map(([t, id]) => (t === 's' ? Number(id) : -Number(id)));
  app.state.label.subOrder = order;
  api('submissionOrder', { order }).catch(() => {});
}

document.addEventListener('pointerdown', (e) => {
  const el = e.target.closest?.('.lb-slotitem');
  if (!el?.dataset.post || e.button > 0 || e.target.closest('button, a, input, select, [data-act]:not(.lb-tape-big):not(.lb-flipper):not(.lb-env)')) return;
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

defineActions({
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
    await mutate('submissionOpen', { submissionId: Number(id) });
  },
  async submissionDrop({ id }) {
    await mutate('submissionDrop', { submissionId: Number(id) });
  },
  async mailAccept({ id }) {
    const m = app.state.mail.find((x) => x.mailId === Number(id));
    const who = musicianById(m?.musicianId);
    if (await mutate('mailAccept', { mailId: Number(id) })) toast(`${who?.firstName ?? 'Dein Musiker'} ist beim Interview.`, 'ok');
  },
  async mailHire({ id }) {
    const m = app.state.mail.find((x) => x.mailId === Number(id));
    if (m?.role === 'manager') {
      managerDialog(m);
      return;
    }
    if (!m || !(await confirmDialog(`${m.firstName} ${m.lastName} für ${money(m.price)} einstellen? Du bezahlst nur einmal.`, { ok: 'Einstellen' }))) return;
    if (await mutate('mailHire', { mailId: Number(id) })) toast(`${m.firstName} arbeitet jetzt für dich.`, 'ok');
  },
  async mailDrop({ id }) {
    await mutate('mailDrop', { mailId: Number(id) });
  },
  async mailSave({ id, saved }) {
    if (await mutate('mailSave', { mailId: Number(id), saved: !!saved })) toast(saved ? 'Gemerkt – du findest es unter „Gespeichert“.' : 'Zurück im Eingang.', 'ok');
  },
  async postUnpack({ table, id }) {
    await mutate('postUnpack', { table, id: Number(id) });
  },
  async internOrder({ id, job }) {
    const x = app.state.assistant?.staff.find((y) => y.id === Number(id));
    closeTalk();
    if (await mutate('internOrder', { staffId: Number(id), job })) toast(`${x?.firstName ?? 'Das Praktikum'} ${INTERN_JOBS[job][1]}. Du kannst derweil etwas anderes machen.`, 'ok');
  },
  async internCancel({ id }) {
    const x = app.state.assistant?.staff.find((y) => y.id === Number(id));
    closeTalk();
    if (await mutate('internCancel', { staffId: Number(id) })) toast(`${x?.firstName ?? 'Das Praktikum'} hört auf. Was noch nicht fertig ist, liegt wieder da.`, 'ok');
  },
  async mailTest() {
    await mutate('mailTest');
  },
  /** Von den Notizen auf der Übersicht in den passenden Reiter. */
  inboxGo({ key }) {
    app.ui.subBox = key;
    location.hash = '#/einsendungen';
  },
  tutorAsk({ id }) {
    tutorDialog(app.state.mail.find((x) => x.mailId === Number(id)));
  },
  scoutAsk({ id }) {
    scoutDialog(app.state.scouts.find((x) => x.id === Number(id)));
  },
  reportShow({ id }) {
    reportDialog(Number(id));
  },
  async assistTask({ id, task }) {
    const x = app.state.assistant?.staff.find((y) => y.id === Number(id));
    if (!x || x.task === task) return;
    if (await mutate('assistTask', { staffId: Number(id), task })) toast({ presse: `${x.firstName} kümmert sich jetzt um die Presse.`, demos: `${x.firstName} hört jetzt Kassetten an.` }[task] ?? `${x.firstName} packt jetzt nur noch die Post aus.`, 'ok');
  },
  async assistAdopt({ id }) {
    const x = app.state.assistant?.staff.find((y) => y.id === Number(id));
    if (!x || !(await confirmDialog(`${x.firstName} für ${money(app.state.assistant.adoptPrice)} als Assistenz übernehmen? Dann bleibt ${x.firstName} und kann auch Kassetten anhören oder sich um die Presse kümmern.`, { ok: 'Übernehmen' }))) return;
    if (await mutate('assistAdopt', { staffId: Number(id) })) toast(`${x.firstName} bleibt jetzt als Assistenz.`, 'ok');
  },
  assistRulesAsk() {
    rulesDialog();
  },
  async submissionsTest({ closed }) {
    await mutate('submissionsTest', { closed: !!closed });
  },
});
