import { genreGlyph, genreName, instrumentIcon, instrumentName, photo, sortedGenres } from '../cards.js';
import { app, cat, defineActions, mutate, rerender, serverNow } from '../core.js';
import { esc, money, toast } from '../ui.js';
import { levelClass, levelProgress } from './label.js';

/**
 * Einsendungen: Musiker bewerben sich von selbst beim Label. Man sieht sie, unterschreibt sie gegen eine einmalige Prämie
 * oder wirft sie weg. (Das Öffnen der Umschläge, die Assistenz und der Scout kommen später.)
 */

const isTest = () => document.documentElement.classList.contains('is-test') || ['localhost', '127.0.0.1'].includes(location.hostname);

function expires(s) {
  const left = s.expiresAt - serverNow();
  if (left <= 0) return 'läuft ab';
  return left >= 86400 ? `läuft ab in ${Math.ceil(left / 86400)} Tagen` : `läuft ab in ${Math.max(1, Math.ceil(left / 3600))} Std.`;
}

/** Die Kassette: Außen steht nichts drauf – man muss sie sich anhören, um zu wissen, was drin ist. */
const cassette = (hint = null) => `<span class="lb-tape-wrap"><svg class="lb-tape-svg" viewBox="0 0 120 80" aria-hidden="true">
  <rect x="4" y="6" width="112" height="68" rx="9" fill="#2b2836" stroke="#4a4458" stroke-width="2"/>
  <rect x="14" y="14" width="92" height="30" rx="4" fill="#d8d2c4"/>
  <g transform="translate(36 58)"><circle r="9" fill="#18161f" stroke="#4a4458" stroke-width="2"/><g class="lb-reel"><circle r="5" fill="none" stroke="#c9a86a" stroke-width="2" stroke-dasharray="4 3"/></g></g>
  <g transform="translate(84 58)"><circle r="9" fill="#18161f" stroke="#4a4458" stroke-width="2"/><g class="lb-reel"><circle r="5" fill="none" stroke="#c9a86a" stroke-width="2" stroke-dasharray="4 3"/></g></g>
  <rect x="46" y="52" width="28" height="12" rx="3" fill="#18161f"/>
</svg><span class="lb-tape-label">${hint?.genres?.length ? hint.genres.map((g) => `<i class="g-${g}">${genreGlyph(g)}</i>`).join('') : '<b>?</b>'}</span></span>`;

/** Kassette im Briefkasten: ohne Kachel, mit leichter Schräglage, damit es nicht wie eine Tabelle wirkt. */
function tape(s, busy = false) {
  const listening = s.state === 'listening';
  const total = cat().rules.submissionListen ?? 5;
  const elapsed = listening ? Math.max(0, total - (s.opensAt - serverNow())) : 0;
  const h = (n) => (Math.imul(s.submissionId + n, 2654435761) >>> 0) / 4294967296;
  const rot = Math.round((h(1) * 8 - 4) * 10) / 10;
  const lift = Math.round(h(2) * 10 - 5);
  return `
    <div class="lb-tapeitem ${listening ? 'playing' : ''}" style="--r:${rot}deg;--y:${lift}px">
      <div class="lb-tape-big">${cassette(s.hint)}</div>
      ${listening
    ? `<div class="lb-listen"><span class="lb-eq"><i></i><i></i><i></i><i></i></span><b>Du hörst rein …</b>
        <span class="lb-prog wide"><i style="animation-duration:${total}s;animation-delay:-${elapsed.toFixed(2)}s"></i></span></div>`
    : `<div class="lb-listen"><b>Neue Einsendung</b></div>
      <div class="lb-sub-foot"><button class="btn small primary" data-act="submissionOpen" data-id="${s.submissionId}" ${busy ? 'disabled title="Es läuft schon eine Kassette"' : ''}>Anhören</button>
        <button class="btn small ghost" data-act="submissionDrop" data-id="${s.submissionId}">Wegwerfen</button></div>
      <small class="muted">${expires(s)}</small>`}
    </div>`;
}

function card(s) {
  const split = sortedGenres(s.genres).map((g, i, all) => `var(--lb-c-${g}) ${(i * 100) / all.length}% ${((i + 1) * 100) / all.length}%`).join(', ');
  const chars = s.personality.map((p) => cat().personalities[p]).join(' · ');
  const money0 = app.state.label.money;
  return `
    <article class="lb-sub">
      <div class="lb-sub-top">
        <span class="lb-ph" style="--split:linear-gradient(120deg, ${split})">${photo(s)}</span>
        <div class="lb-sub-name"><b>${esc(s.firstName)} ${esc(s.lastName)}</b>
          ${s.fameLevel ? `<span class="lb-sub-fame" title="${esc(cat().fameNames[s.fameLevel])}">${'★'.repeat(s.fameLevel)}</span>` : '<small class="muted">noch unbekannt</small>'}
          <span class="lb-sub-chars">${esc(chars)}</span></div>
      </div>
      ${s.instruments.map((i) => `<div class="lb-ins ${levelClass(i.skill)}"><span class="ico" data-act="tip" data-tip="${esc(instrumentName(i.type))}">${instrumentIcon(i.type)}</span>
        <span class="sk" title="Können ${Math.floor(i.skill)}"><i style="width:${levelProgress(i.skill)}%"></i></span>
        <b class="lv" data-act="tip" data-tip="Können ${Math.floor(i.skill)}">${Math.min(3, cat().rules.skillLevels.filter((t) => i.skill >= t).length)}</b></div>`).join('')}
      <div class="lb-genres">${sortedGenres(s.genres).map((g) => `<button class="lb-gt g-${g}" data-act="tip" data-tip="${esc(genreName(g))}">${genreGlyph(g)}</button>`).join('')}</div>
      <div class="lb-sub-foot">
        <button class="btn small primary" data-act="submissionSign" data-id="${s.submissionId}" ${money0 < s.price ? 'disabled title="Dafür reicht das Geld nicht"' : ''}>Unterschreiben · ${money(s.price)}</button>
        <button class="btn small ghost" data-act="submissionDrop" data-id="${s.submissionId}">Wegwerfen</button>
        <small>${expires(s)}</small>
      </div>
    </article>`;
}

export function render() {
  const all = app.state.submissions ?? [];
  const f = app.ui.subFilter ?? 'all';
  const levelOf = (s) => Math.max(...s.instruments.map((i) => cat().rules.skillLevels.filter((t) => i.skill >= t).length));
  const list = all.filter((s) => f === 'all' || (f === 'unheard' ? s.state !== 'open' : s.state === 'open' && levelOf(s) === Number(f)));
  const cards = list.filter((x) => x.state === 'open');
  const tapes = list.filter((x) => x.state !== 'open');
  return `
    <div class="section-head"><h1>Einsendungen</h1>${isTest() ? '<span class="lb-test"><button class="btn small" data-act="submissionsTest" data-closed="1">Test: neue Kassetten</button><button class="btn small" data-act="submissionsTest">Test: angehörte Bewerber aller Stufen</button></span>' : ''}</div>
    <p class="muted">Jede Einsendung ist eine Kassette: Erst wenn du sie dir anhörst, siehst du, wer sich da bewirbt. Unterschreiben kostet einmalig eine Prämie, wegwerfen nichts. Wer zu lange liegt, zieht weiter.</p>
    <div class="lb-filter">${[['all', 'Alle'], ['unheard', 'Ungehört'], ['0', 'Stufe 0'], ['1', 'Stufe 1'], ['2', 'Stufe 2'], ['3', 'Stufe 3']].map(([k, label]) => `<button class="${f === k ? 'on' : ''}" data-act="subFilter" data-key="${k}">${label}</button>`).join('')}</div>
    ${cards.length ? `<h2 class="lb-cap">Angehört</h2><div class="lb-subs">${cards.map(card).join('')}</div>` : ''}
    ${tapes.length ? `<h2 class="lb-cap">Im Briefkasten · ${tapes.length}</h2><div class="lb-tapes">${tapes.map((x) => tape(x, tapes.some((y) => y.state === 'listening'))).join('')}</div>` : ''}
    ${list.length ? '' : '<div class="empty">Gerade liegt nichts im Briefkasten. Es trudelt laufend etwas ein.</div>'}`;
}

defineActions({
  subFilter({ key }) {
    app.ui.subFilter = key;
    rerender();
  },
  async submissionSign({ id }) {
    const s = app.state.submissions.find((x) => x.submissionId === Number(id));
    const res = await mutate('submissionSign', { submissionId: Number(id) });
    if (res && s) toast(`${s.firstName} ${s.lastName} hat unterschrieben.`, 'ok');
  },
  async submissionOpen({ id }) {
    await mutate('submissionOpen', { submissionId: Number(id) });
  },
  async submissionDrop({ id }) {
    await mutate('submissionDrop', { submissionId: Number(id) });
  },
  async submissionsTest({ closed }) {
    await mutate('submissionsTest', { closed: !!closed });
  },
});
