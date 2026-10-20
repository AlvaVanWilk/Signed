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
function tcg({ rarity = '', frame = '', theme = '', name, badge = '', art, type, text, stats, foot = '', act = null, data = {}, size = '', extra = '' }) {
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
        <span class="tcg-type">${type}</span>
        <span class="tcg-text">${text}</span>
        <span class="tcg-stats">${stats.map(([value, label]) => `<span class="tcg-stat"><b>${value}</b><small>${esc(label)}</small></span>`).join('')}</span>
        ${foot ? `<span class="tcg-foot">${foot}</span>` : ''}
      </span>
    </${tag}>`;
  return extra ? `<div class="tcg-wrap ${size}">${card}${extra}</div>` : card;
}

export function abilityInfo(key) {
  const info = cat().abilities[key];
  return { ...info, desc: info.desc ?? cat().techniqueDesc };
}

/** Kartenrahmen nach Können-Stufe, Schein nach Bekanntheits-Stufe; beides voll = Legende. */
export function musicianFrame(m) {
  const legend = m.level >= 3 && m.fameLevel >= 3;
  return `lvl-${m.level} fame-${m.fameLevel}${legend ? ' legend' : ''}`;
}

export function levelPips(level) {
  return `<span class="tcg-pips" title="Können-Stufe ${level}">${[1, 2, 3].map((i) => `<i class="${i <= level ? 'on' : ''}"></i>`).join('')}</span>`;
}

export function musicianCard(m, { act = 'openMusician', size = '', extra = '', showStatus = true } = {}) {
  const c = cat();
  const main = m.instruments.reduce((best, i) => (i.skill > best.skill ? i : best), m.instruments[0]);
  const band = m.bandId ? bandById(m.bandId) : null;
  const status = showStatus ? (musicianStatus(m) || (band ? esc(band.name) : '')) : '';
  const abilities = m.abilities.map((a) => abilityInfo(a));
  return tcg({
    frame: musicianFrame(m),
    theme: `genre-bg-${m.genres[0]}`,
    name: fullName(m),
    badge: levelPips(m.level),
    art: `${avatar(m.look)}
      <span class="tcg-badge">${m.instruments.map((i) => instrumentIcon(i.type)).join('')}</span>
      ${abilities.length ? `<span class="tcg-abilities">${abilities.map((a) => `<i title="${esc(a.name)}">${a.icon}</i>`).join('')}</span>` : ''}`,
    type: `${m.instruments.map((i) => `${esc(instrumentName(i.type))} ${fmt0(i.skill)}`).join(' · ')}<em>${m.genres.map(genreName).map(esc).join(' · ')}</em>`,
    text: abilities.length
      ? abilities.map((a) => `<b>${esc(a.name)}</b>`).join(' · ')
      : `<i>${m.personality.map((p) => esc(c.personalities[p])).join(' · ')}</i>`,
    stats: [[fmt0(main.skill), 'Können'], [fmt0(m.presence), 'Präsenz'], [fmt1(m.fame), 'Ruhm']],
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
