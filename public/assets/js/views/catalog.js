import { fileRow, genreOrder, photoCard } from '../cards.js';
import { app, cat, defineActions, rerender } from '../core.js';
import { esc } from '../ui.js';

const SORTS = {
  level: (a, b) => b.level - a.level || b.fameLevel - a.fameLevel || maxSkill(b) - maxSkill(a),
  skill: (a, b) => maxSkill(b) - maxSkill(a),
  fame: (a, b) => b.fame - a.fame,
  newest: (a, b) => b.id - a.id,
};

const maxSkill = (m) => Math.max(...m.instruments.map((i) => i.skill));

/** Nach Genre gefiltert: nach den übrigen Genres gruppieren (nur dieses Genre zuerst, dann Pop, Rock … in fester Reihenfolge). */
function byOtherGenres(a, b, genre) {
  const rest = (m) => m.genres.filter((g) => g !== genre).map(genreOrder).sort((x, y) => x - y);
  const ra = rest(a);
  const rb = rest(b);
  for (let i = 0; i < Math.max(ra.length, rb.length); i++) {
    if (ra[i] === undefined || rb[i] === undefined) return ra.length - rb.length;
    if (ra[i] !== rb[i]) return ra[i] - rb[i];
  }
  return 0;
}

/** Die Musiker so gefiltert und sortiert, wie der Katalog sie zeigt: erst ohne Band, dann in Bands. */
export function catalogList() {
  const f = app.ui.catalog;
  const list = app.state.musicians
    .filter((m) => !f.instrument || m.instruments.some((i) => i.type === f.instrument))
    .filter((m) => !f.genre || m.genres.includes(f.genre))
    .filter((m) => !f.status || (f.status === 'free' ? !m.bandId : !!m.bandId))
    .sort((a, b) => (f.genre ? byOtherGenres(a, b, f.genre) : 0) || (SORTS[f.sort] || SORTS.level)(a, b));
  return [...list.filter((m) => !m.bandId), ...list.filter((m) => m.bandId)];
}

export function render() {
  const f = app.ui.catalog;
  const c = cat();
  const list = catalogList();
  // Wer schon in einer Band spielt, rückt nach hinten und tritt zurück.
  const free = list.filter((m) => !m.bandId);
  const taken = list.filter((m) => m.bandId);

  // Pinnwand mit Passfotos, oder als Kartei-Liste zum Vergleichen (merkt sich das Gerät).
  const asList = f.view === 'list';
  const show = (items) => (asList ? `<div class="file-list">${items.map((m) => fileRow(m)).join('')}</div>` : `<div class="photo-wall">${items.map((m) => photoCard(m)).join('')}</div>`);
  return `
    <div class="section-head"><h1>Katalog</h1>
      <div class="view-toggle">${[['wall', 'Fotowand'], ['list', 'Liste']].map(([k, label]) => `<button class="chip-btn ${(f.view || 'wall') === k ? 'active' : ''}" data-act="catalogView" data-view="${k}">${label}</button>`).join('')}</div>
    </div>
    <div class="filters">
      ${select('instrument', f.instrument, 'Alle Instrumente', Object.entries(c.instruments).map(([k, v]) => [k, `${v.icon} ${v.name}`]))}
      ${select('genre', f.genre, 'Alle Genres', Object.entries(c.genres).map(([k, v]) => [k, v.name]))}
      ${select('status', f.status, 'Alle', [['free', 'Ohne Band'], ['band', 'In einer Band']])}
      ${select('sort', f.sort, null, [['level', 'Stufe'], ['skill', 'Können'], ['fame', 'Bekanntheit'], ['newest', 'Neueste']])}
    </div>
    ${list.length ? '' : '<div class="empty">Keine Musiker.</div>'}
    ${free.length ? show(free) : ''}
    ${taken.length ? `<h2 class="catalog-split">In einer Band</h2><div class="in-band">${show(taken)}</div>` : ''}`;
}

function select(key, value, emptyLabel, options) {
  return `
    <select data-change="catalogFilter" data-key="${key}">
      ${emptyLabel !== null ? `<option value="">${esc(emptyLabel)}</option>` : ''}
      ${options.map(([k, label]) => `<option value="${k}" ${k === value ? 'selected' : ''}>${esc(label)}</option>`).join('')}
    </select>`;
}

const VIEW_KEY = 'signed.catalogView';
try {
  app.ui.catalog.view = localStorage.getItem(VIEW_KEY) || 'wall';
} catch {
  app.ui.catalog.view = 'wall';
}

defineActions({
  catalogView({ view }) {
    app.ui.catalog.view = view;
    try {
      localStorage.setItem(VIEW_KEY, view);
    } catch {
      // ohne Speicher gilt die Wahl nur bis zum Neuladen
    }
    rerender();
  },
  catalogFilter(value, el) {
    app.ui.catalog[el.dataset.key] = value;
    rerender();
  },
});
