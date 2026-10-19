import { avatar } from './avatar.js';
import { app, bandById, cat, isBusy } from './core.js';
import { bar, esc, fmt0, fmt1 } from './ui.js';

export const instrumentName = (type) => cat().instruments[type]?.name ?? type;
export const instrumentIcon = (type) => cat().instruments[type]?.icon ?? '🎵';
export const genreName = (key) => cat().genres[key]?.name ?? key;
export const rarityName = (key) => cat().rarities[key]?.name ?? key;
export const fullName = (p) => `${p.firstName} ${p.lastName}`;

export function chip(text, cls = '') {
  return `<span class="chip ${cls}">${esc(text)}</span>`;
}

export function genreChips(genres) {
  return genres.map((g) => chip(genreName(g), `genre genre-${g}`)).join('');
}

export function face(person, size = '') {
  return `<span class="face ${size} rarity-${person.rarity}">${avatar(person.look)}</span>`;
}

/** Was der Musiker gerade tut, oder leer. */
export function musicianStatus(m) {
  if (isBusy(m.busyUntil)) {
    const promo = app.state.running.find((a) => a.type === 'promo' && a.musicianId === m.id);
    return promo ? `Promo-Tour · <span data-until="${promo.endsAt}"></span>` : 'Beschäftigt';
  }
  if (m.bandId) {
    const band = bandById(m.bandId);
    if (band && isBusy(band.busyUntil)) return `Mit ${esc(band.name)} unterwegs`;
  }
  return '';
}

export function musicianCard(m, { act = 'openMusician', extra = '' } = {}) {
  const band = m.bandId ? bandById(m.bandId) : null;
  const status = musicianStatus(m);
  return `
    <button class="card card-musician rarity-${m.rarity}" data-act="${act}" data-id="${m.id}">
      <span class="rarity-tag">${esc(rarityName(m.rarity))}</span>
      ${face(m, 'lg')}
      <span class="card-title">${esc(fullName(m))}</span>
      <span class="card-instruments">${m.instruments.map((i) => `
        <span class="skill-line"><span>${instrumentIcon(i.type)} ${esc(instrumentName(i.type))}</span><b>${fmt1(i.skill)}</b></span>
        ${bar(i.skill, 100, 'thin')}`).join('')}
      </span>
      <span class="chips">${genreChips(m.genres)}${m.unusual ? chip('Ungewöhnlich', 'special') : ''}</span>
      <span class="card-stats">
        <span title="Präsenz">✨ ${fmt1(m.presence)}</span>
        <span title="Bekanntheit">⭐ ${fmt1(m.fame)}</span>
        ${m.traits.length + m.techniques.length ? `<span title="Besonderheiten">◆ ${m.traits.length + m.techniques.length}</span>` : ''}
      </span>
      <span class="card-foot">${status || (band ? `🎵 ${esc(band.name)}` : '<span class="muted">frei</span>')}</span>
      ${extra}
    </button>`;
}

export function gearModel(key) {
  return cat().gear[key];
}

export function gearStats(model) {
  return `
    <span class="stat-row"><span>Klang</span>${bar(model.klang, 100, 'thin')}<b>${model.klang}</b></span>
    <span class="stat-row"><span>Spielbarkeit</span>${bar(model.spielbarkeit, 100, 'thin')}<b>${model.spielbarkeit}</b></span>
    <span class="stat-row"><span>Design</span>${bar(model.design, 100, 'thin')}<b>${model.design}</b></span>`;
}

export function gearCard(g, extra = '') {
  const model = gearModel(g.model);
  return `
    <div class="card card-gear rarity-${model.rarity}">
      <span class="rarity-tag">${esc(rarityName(model.rarity))}</span>
      <span class="gear-icon">${instrumentIcon(model.type)}</span>
      <span class="card-title">${esc(model.name)}</span>
      <span class="muted small">${esc(instrumentName(model.type))}</span>
      <span class="gear-stats">${gearStats(model)}</span>
      ${extra}
    </div>`;
}

export function managerSkills(skills) {
  return skills.map((s) => {
    const info = cat().managerSkills[s];
    return `<span class="chip ${info.active ? 'skill' : 'skill inactive'}" title="${esc(info.desc)}${info.active ? '' : ' (kommt mit einem späteren Update)'}">${esc(info.name)}</span>`;
  }).join('');
}

export function managerCard(m, extra = '') {
  return `
    <div class="card card-manager rarity-${m.rarity}">
      <span class="rarity-tag">${esc(rarityName(m.rarity))}</span>
      ${face(m, 'lg')}
      <span class="card-title">${esc(fullName(m))}</span>
      <span class="muted small">Manager · Stufe ${fmt0(m.level)}</span>
      <span class="chips">${managerSkills(m.skills)}</span>
      ${extra}
    </div>`;
}
