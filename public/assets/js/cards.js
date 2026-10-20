import { avatar } from './avatar.js';
import { app, bandById, cat, isBusy } from './core.js';
import { esc, fmt0, fmt1 } from './ui.js';

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
  const frame = person.level === undefined ? `rarity-${person.rarity}` : `lvl-${person.level}`;
  return `<span class="face ${size} ${frame}">${avatar(person.look)}</span>`;
}

/** Was der Musiker gerade tut, oder leer. */
export function musicianStatus(m) {
  const listing = app.state.market?.mine.find((t) => t.musician.id === m.id);
  if (listing) return `Auf der Wechselbörse · <span data-until="${listing.endsAt}"></span>`;
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

// ---------- Sammelkarten ----------

/**
 * Gemeinsamer Aufbau aller Karten: Kopf mit Name und Seltenheit, Bildfeld, Typzeile, Textfeld, Werte.
 * opts: act/data (Klick-Aktion), size ('' | 'sm'), extra (HTML unter der Karte), tag ('button' | 'div')
 */
function tcg({ rarity = '', frame = '', theme = '', name, badge = '', art, type = '', text, stats = [], minis = '', foot = '', act = null, data = {}, size = '', extra = '' }) {
  const tag = act ? 'button' : 'div';
  const attrs = act ? ` data-act="${act}" ${Object.entries(data).map(([k, v]) => `data-${k}="${esc(v)}"`).join(' ')}` : '';
  const card = `
    <${tag} class="tcg ${frame || `rarity-${rarity}`} ${theme} ${size}"${attrs}>
      <span class="tcg-frame">
        <span class="tcg-head">
          <span class="tcg-name">${esc(name)}</span>
          ${badge || `<span class="tcg-gem" title="${esc(rarityName(rarity))}"></span>`}
        </span>
        <span class="tcg-art">${art}</span>
        ${type ? `<span class="tcg-type">${type}</span>` : ''}
        <span class="tcg-text">${text}</span>
        ${stats.length ? `<span class="tcg-stats">${stats.map(([value, label]) => `<span class="tcg-stat"><b>${value}</b><small>${esc(label)}</small></span>`).join('')}</span>` : ''}
        ${minis ? `<span class="tcg-minis">${minis}</span>` : ''}
        ${foot ? `<span class="tcg-foot">${foot}</span>` : ''}
      </span>
    </${tag}>`;
  return extra ? `<div class="tcg-wrap ${size}">${card}${extra}</div>` : card;
}

export function abilityInfo(key) {
  const info = cat().abilities[key];
  return { ...info, desc: info.desc ?? cat().techniqueDesc };
}

/** Kartenfarbe nach Können-Stufe, Funkeln nach Bekanntheits-Stufe; beides voll = Legende. */
export function musicianFrame(m) {
  const legend = m.level >= 3 && m.fameLevel >= 3;
  return `lvl-${m.level} fame-${m.fameLevel}${legend ? ' legend' : ''}`;
}

/** Genre als flaches Umriss-Symbol. */
const GENRE_PATHS = {
  pop: 'M12 3l2.6 5.6 6.1.7-4.5 4.2 1.2 6L12 16.6l-5.4 2.9 1.2-6-4.5-4.2 6.1-.7z',
  rock: 'M13.5 2L5 13.5h6.5L10 22l9-12h-6.5z',
  metal: 'M7.5 13V4.5a1.5 1.5 0 013 0V11 M15 11V5.5a1.5 1.5 0 013 0V14c0 4.5-2.7 7.5-6.2 7.5S6.5 19 6.5 15.5V13 M10.5 11.5a1.3 1.3 0 012.5 0v1 M13 11.5a1.2 1.2 0 012 0 M6.5 15.5l5.5-2',
  punk: 'M12 3a9 9 0 100 18 9 9 0 100-18 M7 19L12 5l5 14 M7.5 13.5h9',
  jazz: 'M3 16.5c4 2.5 14 2.5 18 0 M5.5 16l1.6-7.2c.4-1.7 2.2-2.3 3.4-1.2L12 9l1.5-1.4c1.2-1.1 3-.5 3.4 1.2l1.6 7.2 M7 13.5c3 1 7 1 10 0',
  elektro: 'M2 12h4l2.5-7 3.5 14 3.5-14 2.5 7h4',
  hiphop: 'M3 8.5h18V19H3z M7 8.5V5.5h10v3 M8 15.5a2 2 0 100-.1z M16 15.5a2 2 0 100-.1z M11 11.5h2',
  folk: 'M5 19C5 10 11 5 20 4c-.8 9-6.2 15-15 15z M5 19l8.5-8.5',
};

export function genreIcon(key) {
  return `<svg class="genre-icon" viewBox="0 0 24 24" aria-label="${esc(genreName(key))}"><title>${esc(genreName(key))}</title><path d="${GENRE_PATHS[key] ?? ''}"/></svg>`;
}

/** Können als Leiste aus Kästchen: 10 Kästchen à 10 Punkte bis zum Höchstwert 100. */
export function skillBar(skill) {
  return `<span class="skill-cells" title="Können ${fmt0(skill)} von 100">${Array.from({ length: 10 }, (_, i) => {
    const fill = Math.max(0, Math.min(1, (skill - i * 10) / 10));
    return `<i style="--f:${Math.round(fill * 100)}%"></i>`;
  }).join('')}</span>`;
}

export function musicianCard(m, { act = 'openMusician', size = '', extra = '', showStatus = true } = {}) {
  const c = cat();
  const band = m.bandId ? bandById(m.bandId) : null;
  const status = showStatus ? (musicianStatus(m) || (band ? esc(band.name) : '')) : '';
  const abilities = m.abilities.map((a) => abilityInfo(a));
  return tcg({
    frame: musicianFrame(m),
    theme: `genre-bg-${m.genres[0]}`,
    name: fullName(m),
    badge: `<span class="tcg-genres">${m.genres.map(genreIcon).join('')}</span>`,
    art: `${avatar(m.look)}
      <span class="tcg-badge">${m.instruments.map((i) => instrumentIcon(i.type)).join('')}</span>
      ${abilities.length ? `<span class="tcg-abilities">${abilities.map((a) => `<i title="${esc(a.name)}">${a.icon}</i>`).join('')}</span>` : ''}`,
    text: `
      ${m.instruments.map((i) => `<span class="skill-row"><span class="skill-name">${instrumentIcon(i.type)}<span> ${esc(instrumentName(i.type))}</span></span>${skillBar(i.skill)}<b>${fmt0(i.skill)}</b></span>`).join('')}
      <span class="tcg-traits">${abilities.length
        ? abilities.map((a) => `<b>${esc(a.name)}</b>`).join(' · ')
        : `<i>${m.personality.map((p) => esc(c.personalities[p])).join(' · ')}</i>`}</span>`,
    foot: status,
    act,
    data: { id: m.id },
    size,
    extra,
  });
}

export function gearModel(key) {
  return cat().gear[key];
}

export function gearCard(g, { act = null, data = {}, size = '', extra = '' } = {}) {
  const model = { ...gearModel(g.model), ...(g.klang ? { klang: g.klang, spielbarkeit: g.spielbarkeit, design: g.design } : {}) };
  return tcg({
    rarity: model.rarity,
    theme: 'gear-bg',
    name: model.name,
    art: `<span class="tcg-icon">${instrumentIcon(model.type)}</span>`,
    type: `Instrument<em>${esc(instrumentName(model.type))}</em>`,
    text: `Voller Klang ab Können ${fmt0(100 - model.spielbarkeit)}`,
    stats: [[model.klang, 'Klang'], [model.spielbarkeit, 'Spielb.'], [model.design, 'Design']],
    act,
    data,
    size,
    extra,
  });
}

export function managerSkills(skills) {
  return skills.map((s) => {
    const info = cat().managerSkills[s];
    return `<span class="chip ${info.active ? 'skill' : 'skill inactive'}" title="${esc(info.desc)}${info.active ? '' : ' (kommt mit einem späteren Update)'}">${esc(info.name)}</span>`;
  }).join('');
}

export function managerCard(m, { act = null, data = {}, size = '', extra = '' } = {}) {
  const skills = cat().managerSkills;
  return tcg({
    rarity: m.rarity,
    theme: 'manager-bg',
    name: fullName(m),
    art: `${avatar(m.look)}<span class="tcg-badge">💼</span>`,
    type: `Manager<em>Stufe ${fmt0(m.level)}</em>`,
    text: m.skills.map((s) => `<b>${esc(skills[s].name)}</b> ${esc(skills[s].desc)}${skills[s].active ? '' : ' <i>(später)</i>'}`).join('<br>'),
    stats: [[fmt0(m.level), 'Stufe'], [fmt0(m.bandIds.length), 'Bands']],
    act,
    data,
    size,
    extra,
  });
}

/** Leerer Kartenplatz, z. B. für ein fehlendes Instrument oder einen Manager. */
export function emptySlot(label, { act = null, data = {}, size = '', icon = '+' } = {}) {
  const attrs = act ? ` data-act="${act}" ${Object.entries(data).map(([k, v]) => `data-${k}="${esc(v)}"`).join(' ')}` : '';
  const tag = act ? 'button' : 'div';
  return `<${tag} class="tcg-slot ${size}"${attrs}><span class="tcg-slot-icon">${icon}</span><span>${esc(label)}</span></${tag}>`;
}

/** Meldung, wenn Musiker eine Können-Stufe aufsteigen. */
export function levelUpBanners(levelUps = []) {
  return levelUps.map((l) => `
    <div class="banner">⭐ ${esc(l.name)} erreicht Können-Stufe ${l.level}${l.abilities.length ? ` und lernt ${l.abilities.map((a) => `${cat().abilities[a].icon} ${esc(cat().abilities[a].name)}`).join(', ')}` : ''}${l.choice ? ' und darf eine Fähigkeit wählen' : ''}.</div>`).join('');
}
