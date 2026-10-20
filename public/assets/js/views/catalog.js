import { musicianCard } from '../cards.js';
import { app, cat, defineActions, rerender } from '../core.js';
import { esc } from '../ui.js';

const SORTS = {
  level: (a, b) => b.level - a.level || b.fameLevel - a.fameLevel || maxSkill(b) - maxSkill(a),
  skill: (a, b) => maxSkill(b) - maxSkill(a),
  presence: (a, b) => b.presence - a.presence,
  fame: (a, b) => b.fame - a.fame,
  newest: (a, b) => b.id - a.id,
};

const maxSkill = (m) => Math.max(...m.instruments.map((i) => i.skill));

export function render() {
  const f = app.ui.catalog;
  const c = cat();
  const list = app.state.musicians
    .filter((m) => !f.instrument || m.instruments.some((i) => i.type === f.instrument))
    .filter((m) => !f.genre || m.genres.includes(f.genre))
    .filter((m) => !f.status || (f.status === 'free' ? !m.bandId : !!m.bandId))
    .sort(SORTS[f.sort] || SORTS.level);
  // Wer schon in einer Band spielt, rückt nach hinten und tritt zurück.
  const free = list.filter((m) => !m.bandId);
  const taken = list.filter((m) => m.bandId);

  return `
    <div class="section-head"><h1>Katalog</h1></div>
    <div class="filters">
      ${select('instrument', f.instrument, 'Alle Instrumente', Object.entries(c.instruments).map(([k, v]) => [k, `${v.icon} ${v.name}`]))}
      ${select('genre', f.genre, 'Alle Genres', Object.entries(c.genres).map(([k, v]) => [k, v.name]))}
      ${select('status', f.status, 'Alle', [['free', 'Ohne Band'], ['band', 'In einer Band']])}
      ${select('sort', f.sort, null, [['level', 'Stufe'], ['skill', 'Können'], ['presence', 'Präsenz'], ['fame', 'Ruhm'], ['newest', 'Neueste']])}
    </div>
    ${list.length ? '' : '<div class="empty">Keine Musiker.</div>'}
    ${free.length ? `<div class="tcg-grid">${free.map((m) => musicianCard(m)).join('')}</div>` : ''}
    ${taken.length ? `<h2 class="catalog-split">In einer Band</h2><div class="tcg-grid in-band">${taken.map((m) => musicianCard(m)).join('')}</div>` : ''}`;
}

function select(key, value, emptyLabel, options) {
  return `
    <select data-change="catalogFilter" data-key="${key}">
      ${emptyLabel !== null ? `<option value="">${esc(emptyLabel)}</option>` : ''}
      ${options.map(([k, label]) => `<option value="${k}" ${k === value ? 'selected' : ''}>${esc(label)}</option>`).join('')}
    </select>`;
}

defineActions({
  catalogFilter(value, el) {
    app.ui.catalog[el.dataset.key] = value;
    rerender();
  },
});
