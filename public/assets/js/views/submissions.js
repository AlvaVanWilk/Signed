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
  const list = all.filter((s) => f === 'all' || Math.max(...s.instruments.map((i) => cat().rules.skillLevels.filter((t) => i.skill >= t).length)) === Number(f));
  return `
    <div class="section-head"><h1>Einsendungen</h1>${isTest() ? '<button class="btn small" data-act="submissionsTest">Test: Bewerber aller Stufen</button>' : ''}</div>
    <p class="muted">Musiker bewerben sich von selbst bei dir. Unterschreiben kostet einmalig eine Prämie, wegwerfen nichts. Wer zu lange liegt, zieht weiter.</p>
    <div class="lb-filter">${[['all', 'Alle'], ['0', 'Stufe 0'], ['1', 'Stufe 1'], ['2', 'Stufe 2'], ['3', 'Stufe 3']].map(([k, label]) => `<button class="${f === k ? 'on' : ''}" data-act="subFilter" data-key="${k}">${label}</button>`).join('')}</div>
    ${list.length ? `<div class="lb-subs">${list.map(card).join('')}</div>` : '<div class="empty">Gerade liegt nichts im Briefkasten. Es trudelt laufend etwas ein.</div>'}`;
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
  async submissionDrop({ id }) {
    await mutate('submissionDrop', { submissionId: Number(id) });
  },
  async submissionsTest() {
    await mutate('submissionsTest');
  },
});
