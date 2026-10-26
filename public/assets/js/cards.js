import { avatar } from './avatar.js';
import { portrait } from './figure.js';
import { instrumentSvg } from './icons.js';
import { app, bandById, cat, isBusy, musicianById } from './core.js';
import { esc, fmt0, fmt1, percent } from './ui.js';

export const instrumentName = (type) => cat().instruments[type]?.name ?? type;
/** Emoji für Stellen, die nur Text können (Auswahllisten, Canvas). */
export const instrumentEmoji = (type) => cat().instruments[type]?.icon ?? '🎵';
/** Gezeichnetes Symbol; electric zeigt die E-Gitarre, tier die Güteklasse 0–3 (null = bisheriges Aussehen). */
export const instrumentIcon = (type, electric = false, hue = null, tier = null, art = {}) => instrumentSvg(type, electric, hue, tier, art) ?? instrumentEmoji(type);

/** Güteklasse eines Instruments: 0 grau (gewöhnlich), 1 grün (selten), 2 blau (episch), 3 gold (legendär). */
export const TIER = { common: 0, rare: 1, epic: 2, legendary: 3 };
export const gearTier = (g) => g.tier ?? TIER[cat().gear[g.model]?.rarity] ?? 0;

/** Farbe eines Instruments für Symbole und Figuren: Blaue (epische) gibt es in vielen Lackfarben, fest je Instrument; sonst Grundfarbe. */
export function gearHue(g) {
  if (gearTier(g) !== 2) return null;
  const seed = g.id ?? ((g.slot ?? 0) * 31 + (g.idx ?? 0) * 7);
  return (seed * 47 + 200) % 360;
}

/** Symbol eines bestimmten Instruments: Klasse, Lackfarbe (blau), Holz oder Metall (grün, fest je Instrument), goldene Fähigkeit (gold). */
export const gearIcon = (g) => {
  const model = cat().gear[g.model];
  const seed = g.id ?? ((g.slot ?? 0) * 31 + (g.idx ?? 0) * 7);
  const gold = (g.bonuses ?? []).find((b) => b.slot === 'gold')?.kind ?? null;
  return instrumentIcon(model.type, !!model.electric, gearHue(g), gearTier(g), { variant: (seed * 5 + 3) % 35, gold });
};

const BONUS_SLOT = { green: 'Übung', blue: 'Klang', gold: 'Besonders' };
/** Ein Bonus in einfachen Worten, z. B. „Ausdauer: Hält beim Proben +20 % länger durch.“ */
export function bonusText(b) {
  const info = cat().gearBonusInfo[b.kind] ?? { name: b.kind, desc: '{v}' };
  return { name: info.name, slot: BONUS_SLOT[b.slot] ?? '', text: info.desc.replace('{v}', `+${Math.round(b.value * 100)}\u00a0%`) };
}
/** Liste der Boni, jeder in seiner Farbe (grün, blau, gold). Graue Instrumente haben keine. */
export function bonusList(bonuses = [], short = false) {
  if (!bonuses.length) return '<p class="gb-none">Keine Boni – einfach ein Instrument.</p>';
  return `<ul class="gb-list">${bonuses.map((b) => {
    const t = bonusText(b);
    return `<li class="gb-${b.slot}"><i></i><b>${esc(t.name)}</b>${short ? '' : ` <span>${esc(t.text)}</span>`}</li>`;
  }).join('')}</ul>`;
}

/**
 * Wer im Label dieses Instrument spielen kann (Können-Stufe am Instrument ≥ Klasse).
 * Kann es noch niemand: wer am nächsten dran ist und wie viel Können fehlt.
 */
export function whoCanPlay(g) {
  const model = cat().gear[g.model];
  const tier = gearTier(g);
  const need = tier ? cat().rules.skillLevels[tier - 1] : 0;
  const players = (app.state?.musicians ?? []).map((m) => ({ m, skill: m.instruments.find((i) => i.type === model.type)?.skill })).filter((x) => x.skill !== undefined);
  if (!players.length) return 'Im Label spielt niemand dieses Instrument.';
  const can = players.filter((x) => x.skill >= need);
  if (can.length) return `Spielen können: ${can.map((x) => esc(x.m.stageName ?? x.m.firstName)).join(', ')}`;
  const near = players.sort((a, b) => b.skill - a.skill)[0];
  return `Noch niemand. Am nächsten: ${esc(near.m.stageName ?? near.m.firstName)} (braucht noch ${Math.ceil(need - near.skill)} Können)`;
}

/** Wie das eigene Instrument eines Musikers aussieht: elektrisch? welche Farbe? Bei fremden Musikern unbekannt. */
export function ownGear(m, type) {
  const g = app.state?.gear?.find((x) => x.musicianId === m.id && cat().gear[x.model]?.type === type);
  return g ? { electric: !!cat().gear[g.model].electric, hue: gearHue(g) } : { electric: false, hue: null };
}
export const playsElectric = (m, type) => ownGear(m, type).electric;
export const genreName = (key) => cat().genres[key]?.name ?? key;
/** Genres immer in derselben Reihenfolge anzeigen (wie im Katalog: Pop, Rock, Metal …). */
export const genreOrder = (key) => Object.keys(cat().genres).indexOf(key);
export const sortedGenres = (genres) => [...genres].sort((a, b) => genreOrder(a) - genreOrder(b));
export const rarityName = (key) => cat().rarities[key]?.name ?? key;
export const fullName = (p) => `${p.firstName} ${p.lastName}`;

/** Noch unbekannte Charakterzüge: je einer ein „?“ (kennt nur ein Manager, oder man hört sie, wenn jemand richtig unzufrieden ist). */
export const HIDDEN_TRAIT_TIP = 'Noch unbekannt: Diesen Charakterzug kennt nur ein Manager. Ohne Manager erfährst du ihn erst, wenn jemand richtig unzufrieden ist.';
export function hiddenTraits(m, cls = 'lb-trait') {
  return Array.from({ length: m.hiddenTraits ?? 0 }, () => `<button class="${cls} unknown" data-act="tip" data-tip="${HIDDEN_TRAIT_TIP}" title="${HIDDEN_TRAIT_TIP}" aria-label="Unbekannter Charakterzug">?</button>`).join('');
}
/** Charakterzüge als Text, unbekannte als „?“. */
export function traitNames(m, sep = ', ') {
  return [...m.personality.map((p) => cat().personalities[p]), ...Array(m.hiddenTraits ?? 0).fill('?')].join(sep);
}

export function chip(text, cls = '') {
  return `<span class="chip ${cls}">${esc(text)}</span>`;
}

export function genreChips(genres) {
  return genres.map(genreTag).join('');
}

/** Genre als farbige Marke mit Symbol und Namen – überall dieselbe Farbe wie die Bands des Genres. */
export function genreTag(key) {
  return `<span class="genre-tag g-${key}">${genreGlyph(key)}${esc(genreName(key))}</span>`;
}

/**
 * Genre einer Besetzung, wie der Server es rechnet (BandCalc::identity): alle mögen das Genre und die Instrumente passen.
 * members: [{ genres, type }]; prefer/preferSecond halten die bisherige Ausrichtung.
 */
export function bandIdentity(members, prefer = '', preferSecond = null) {
  const c = cat();
  const types = {};
  for (const m of members) types[m.type] = (types[m.type] || 0) + 1;
  const keys = Object.keys(c.genreLineup);
  const allFans = (g) => members.length >= c.rules.minMembers && members.every((m) => m.genres.includes(g));
  const lineup = (g) => c.genreLineup[g].every(([options, need]) => options.reduce((sum, t) => sum + (types[t] || 0), 0) >= need);
  const eligible = keys.filter((g) => allFans(g) && lineup(g));
  const primary = eligible.includes(prefer) ? prefer : eligible[0] ?? null;
  let second = null;
  let third = null;
  if (primary) {
    const shared = eligible.filter((g) => g !== primary);
    second = shared.includes(preferSecond) ? preferSecond : shared[0] ?? null;
    third = shared.find((g) => g !== second) ?? null;
  }
  const bonus = !primary ? (keys.some(allFans) ? 1 : c.rules.mixedBonus) : !second ? c.rules.genreBonus : !third ? c.rules.hybridBonus : c.rules.crossoverBonus;
  return { primary, second, third, title: primary ? bandTitle(primary, second, third) : null, bonus };
}

/** Die Genre-Stufen in einem Satz: Sound. */
export function genreRuleText() {
  const r = cat().rules;
  const pct = (x) => `${x >= 1 ? '+' : '−'}${Math.round(Math.abs(x - 1) * 100)} %`;
  return `Gemischte Band (kein gemeinsames Genre): Sound ${pct(r.mixedBonus)}. Teilen alle ein Genre, aber die Besetzung fehlt noch: kein Abzug, aber auch kein Bonus. `
    + `Genre-Band (alle mögen das Genre, die Besetzung passt): Sound ${pct(r.genreBonus)}. `
    + `Hybrid (dasselbe für ein zweites Genre, auch dessen Besetzung): Sound ${pct(r.hybridBonus)}. `
    + `Crossover (sogar für ein drittes, sehr selten): Sound ${pct(r.crossoverBonus)}.`;
}

export function bandTitle(primary, second, third = null) {
  const name = genreName(primary);
  if (third) return `${name}-Crossover-Band`;
  if (second) return `${genreName(second)}-${name}-Band`;
  return name.includes('-') ? `${name}-Band` : `${name}band`;
}

/** Genre-Marken einer Band; ohne gemeinsames Genre eine neutrale Marke. */
export function bandGenreTags(b) {
  return b.genres.length ? b.genres.map(genreTag).join('') : '<span class="genre-tag g-none">ohne Genre</span>';
}

/** Farbe einer Band in Menü und Seitenleiste: Genre-Farbe, bei Hybriden beide, sonst neutral. */
export function bandFill(b) {
  if (b.genres.length === 2) return { cls: 'genre-fill-split', style: `--fill: linear-gradient(110deg, var(--c-${b.genres[1]}) 0 50%, var(--c-${b.genres[0]}) 50% 100%)` };
  if (b.genres.length === 3) return { cls: 'genre-fill-split', style: `--fill: linear-gradient(110deg, var(--c-${b.genres[1]}) 0 33%, var(--c-${b.genres[0]}) 33% 67%, var(--c-${b.genres[2]}) 67% 100%)` };
  return { cls: b.genres.length ? `genre-fill-${b.genres[0]}` : 'genre-fill-none', style: '' };
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
    const away = app.state.running.find((a) => ['promo', 'practice', 'course', 'interview'].includes(a.type) && a.musicianId === m.id);
    if (away) return `${{ promo: 'Promo-Tour', practice: 'Übt allein', course: 'Im Kurs', interview: 'Beim Interview' }[away.type]} · <span data-until="${away.endsAt}"></span>`;
    return 'Beschäftigt';
  }
  if (m.bandId) {
    const band = bandById(m.bandId);
    if (band && isBusy(band.busyUntil)) return `Mit ${esc(band.name)} unterwegs`;
  }
  return '';
}

// ---------- Künstlerkartei (Passfotos im Stil der Minifiguren) ----------

/** Passfoto: Kopf und Schultern der Figur, blinzelt und schaut umher – jedes im eigenen Takt. */
export function photo(m) {
  return `<span class="photo-pic fig-stage idle"><span class="fig-member" style="--d:${(-((m.id * 7919) % 4300) / 1000).toFixed(2)}s">${portrait(m.look, m.personality?.[0] ?? null)}</span></span>`;
}

const fameStars = (m) => `<span class="photo-stars" title="${esc(cat().fameNames[m.fameLevel])}">${'★'.repeat(m.fameLevel)}<i>${'★'.repeat(3 - m.fameLevel)}</i></span>`;
const genreDots = (m) => `<span class="photo-dots">${sortedGenres(m.genres).map((g) => `<i class="g-${g}" title="${esc(genreName(g))}"></i>`).join('')}</span>`;
const instrumentLine = (m) => m.instruments.map((i) => esc(instrumentName(i.type))).join(' / ');

/** Passfoto an der Pinnwand des Katalogs: Rand = Stufe, Sterne = Bekanntheit, Punkte = Genres, Zettel = unterwegs. */
export function photoCard(m, { act = 'openMusician' } = {}) {
  const status = musicianStatus(m);
  const tilt = [-2.5, 1.8, -1, 2.6, -1.8, 1][m.id % 6];
  return `
    <button class="photo lvl-${m.level} ${status ? 'busy' : ''}" style="--r:${tilt}deg" data-act="${act}" data-id="${m.id}">
      ${photo(m)}
      ${status ? `<span class="photo-note">${status}</span>` : ''}
      <span class="photo-name">${esc(fullName(m))}</span>
      <span class="photo-sub">${instrumentLine(m)} · ${esc(cat().levelNames[m.level])}</span>
      ${fameStars(m)}${genreDots(m)}
    </button>`;
}

/** Dieselben Musiker als Karteikarte in einer Liste: mehr Platz für Können und Band. */
export function fileRow(m, { act = 'openMusician' } = {}) {
  const status = musicianStatus(m);
  const band = m.bandId ? bandById(m.bandId) : null;
  return `
    <button class="file-row lvl-${m.level} ${status ? 'busy' : ''}" data-act="${act}" data-id="${m.id}">
      ${photo(m)}
      <span class="file-who"><b>${esc(fullName(m))}</b><small>${esc(cat().levelNames[m.level])} · ${m.genres.map((g) => esc(genreName(g))).join(', ')}</small></span>
      <span class="file-skills">${m.instruments.map((i) => `<span class="skill-row"><span class="skill-name">${instrumentIcon(i.type, ownGear(m, i.type).electric, ownGear(m, i.type).hue)}<span> ${esc(instrumentName(i.type))}</span></span>${skillBar(i.skill)}</span>`).join('')}</span>
      <span class="file-side">${fameStars(m)}<small>${status || (band ? esc(band.name) : 'frei')}</small></span>
    </button>`;
}

// ---------- Sammelkarten ----------

/**
 * Gemeinsamer Aufbau aller Karten: Kopf mit Name und Seltenheit, Bildfeld, Typzeile, Textfeld, Werte.
 * opts: act/data (Klick-Aktion), size ('' | 'sm'), extra (HTML unter der Karte), tag ('button' | 'div')
 */
function tcg({ rarity = '', frame = '', theme = '', style = '', name, badge = '', art, type = '', text, stats = [], minis = '', foot = '', overlay = '', act = null, data = {}, size = '', extra = '' }) {
  const tag = act ? 'button' : 'div';
  const attrs = act ? ` data-act="${act}" ${Object.entries(data).map(([k, v]) => `data-${k}="${esc(v)}"`).join(' ')}` : '';
  const card = `
    <${tag} class="tcg ${frame || `rarity-${rarity}`} ${theme} ${size}"${style ? ` style="${style}"` : ''}${attrs}>
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
      ${overlay}
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

export function genreGlyph(key) {
  return `<svg class="genre-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="${GENRE_PATHS[key] ?? ''}"/></svg>`;
}

/** Bildhintergrund in den Farben aller Lieblingsgenres, schräg geteilt. */
function genreSplit(genres) {
  const step = 100 / genres.length;
  const stops = genres.map((g, i) => `var(--c-${g}) ${(i * step).toFixed(1)}% ${((i + 1) * step).toFixed(1)}%`).join(', ');
  return `--split: linear-gradient(120deg, ${stops})`;
}

/** Rundes Genre-Abzeichen in der Farbe des Genres. */
export function genreIcon(key) {
  return `<span class="genre-badge g-${key}" title="${esc(genreName(key))}">${genreGlyph(key)}</span>`;
}

/** Stufe und Fortschritt darin (0–1) für einen Wert und die Schwellen der Stufen 1–3. */
function stepOf(value, thresholds) {
  const steps = [0, ...thresholds];
  let level = 0;
  while (level < thresholds.length && value >= thresholds[level]) level++;
  if (level >= thresholds.length) return { level, progress: 1, next: null };
  return { level, progress: (value - steps[level]) / (steps[level + 1] - steps[level]), next: thresholds[level] };
}

/** Bekanntheit auf der Karte: 10 Sternchen für den Weg zur nächsten Stufe, dahinter die Stufe. */
export function fameBar(fame) {
  const { level, progress, next } = stepOf(fame, cat().rules.fameLevels);
  const title = `${cat().fameNames[level]} · Bekanntheit ${fmt0(fame)}${next ? ` · nächste Stufe ab ${fmt0(next)}` : ''}`;
  return `<span class="fame-stars" title="${esc(title)}">${Array.from({ length: 10 }, (_, i) => {
    const fill = Math.max(0, Math.min(1, progress * 10 - i));
    return `<i style="--f:${Math.round(fill * 100)}%">★</i>`;
  }).join('')}</span><b class="step-badge fame-step-${level}" title="${esc(title)}">${level}</b>`;
}

/**
 * Können als Messleiste für den Weg zur nächsten Stufe: gefüllt bis zum aktuellen Können, ein kurzer Strich pro
 * Können-Punkt, ein langer alle 5 Punkte (Stufe 0 ist 35 Punkte lang, die übrigen 25). Dahinter die Stufe.
 */
export function skillBar(skill) {
  const levels = cat().rules.skillLevels;
  const { level, next } = stepOf(skill, levels);
  const from = [0, ...levels][level];
  const width = next === null ? 15 : next - from;
  const filled = next === null ? 1 : (skill - from) / width;
  const title = `${cat().levelNames[level]} · Können ${fmt0(skill)}${next ? ` · nächste Stufe ab ${fmt0(next)}` : ''}`;
  return `<span class="skill-meter skill-step-${level}" style="--units:${width};--f:${(filled * 100).toFixed(1)}%" title="${esc(title)}"></span><b class="step-badge skill-step-${level}" title="${esc(title)}">${level}</b>`;
}

/**
 * Glitzern bekannter Musiker: Funkel, die nacheinander an je drei Stellen aufblitzen. Stellen und Takt
 * hängen fest an der Musiker-ID, damit sie beim Neuzeichnen nicht springen. Nur Deckkraft und Verschiebung.
 */
const SPARKLE_COUNT = [0, 5, 8, 12];
function sparkles(count, id) {
  if (!count) return '';
  let seed = (id * 2654435761) >>> 0;
  const rnd = () => {
    seed = (seed * 1664525 + 1013904223) >>> 0;
    return seed / 4294967296;
  };
  const pos = () => `${Math.round(8 + rnd() * 84)}%`;
  return `<span class="sparkles" aria-hidden="true">${Array.from({ length: count }, () => {
    const vars = [1, 2, 3].map((n) => `--x${n}:${pos()};--y${n}:${pos()}`).join(';');
    const duration = 3.5 + rnd() * 2.5;
    return `<i style="${vars};--t:${duration.toFixed(2)}s;--d:-${(rnd() * duration).toFixed(2)}s"></i>`;
  }).join('')}</span>`;
}

export function musicianCard(m, { act = 'openMusician', size = '', extra = '', showStatus = true } = {}) {
  const c = cat();
  const band = m.bandId ? bandById(m.bandId) : null;
  const status = showStatus ? (musicianStatus(m) || (band ? esc(band.name) : '')) : '';
  const abilities = m.abilities.map((a) => abilityInfo(a));
  return tcg({
    frame: musicianFrame(m),
    theme: 'genre-split',
    style: genreSplit(sortedGenres(m.genres)),
    name: fullName(m),
    badge: `<span class="tcg-genres">${sortedGenres(m.genres).map((g) => `<span class="genre-hit" role="button" data-act="genreInfo" data-genre="${g}" data-id="${m.id}" title="${esc(genreName(g))}: Was heißt das?">${genreIcon(g)}</span>`).join('')}</span>`,
    art: `${avatar(m.look)}
      <span class="tcg-badge">${m.instruments.map((i) => instrumentIcon(i.type, ownGear(m, i.type).electric, ownGear(m, i.type).hue)).join('')}</span>
      ${abilities.length ? `<span class="tcg-abilities">${abilities.map((a) => `<i title="${esc(a.name)}">${a.icon}</i>`).join('')}</span>` : ''}`,
    text: `
      ${m.instruments.map((i) => `<span class="skill-row"><span class="skill-name">${instrumentIcon(i.type, ownGear(m, i.type).electric, ownGear(m, i.type).hue)}<span> ${esc(instrumentName(i.type))}</span></span>${skillBar(i.skill)}</span>`).join('')}
      <span class="skill-row fame-row"><span class="skill-name">⭐<span> Bekanntheit</span></span>${fameBar(m.fame)}</span>
      <span class="tcg-traits">${abilities.length
        ? abilities.map((a) => `<b>${esc(a.name)}</b>`).join(' · ')
        : `<i>${esc(traitNames(m, ' · '))}</i>`}</span>`,
    foot: status,
    overlay: sparkles(SPARKLE_COUNT[m.fameLevel] ?? 0, m.id),
    act,
    data: { id: m.id },
    size,
    extra,
  });
}

export function gearModel(key) {
  return cat().gear[key];
}

/**
 * Instrumentenkarte im Stil des Music Store: Bild auf Filz in der Farbe seiner Klasse (grau, grün, blau, gold), Name, Boni in ihren Farben,
 * wer es spielen kann. Ein grüner Pfeil zeigt, dass ein freies Instrument jemandem in einer Band helfen würde.
 */
export function gearCard(g, { act = null, data = {}, size = '', extra = '', up = null } = {}) {
  const model = gearModel(g.model);
  const holder = g.musicianId ? musicianById(g.musicianId) : null;
  const arrow = (up ?? g.upgrades?.some((u) => u.bandId)) ? upArrow(up ? { musicianId: holder?.id ?? null } : { gear: g }) : '';
  const attrs = act ? ` data-act="${act}" ${Object.entries(data).map(([k, v]) => `data-${k}="${esc(v)}"`).join(' ')}` : '';
  const tier = gearTier(g);
  return `
    <div class="gt r-${model.rarity} t-${tier} ${size}"${attrs}>
      <div class="gt-art"><span class="gt-ico">${gearIcon(g)}</span>
        <b class="gt-rar">${esc(rarityName(model.rarity))}</b>${arrow}</div>
      <div class="gt-body">
        <b class="gt-name">${esc(model.name)}</b>
        <small class="muted">${esc(instrumentName(model.type))}${holder ? ` · spielt ${esc(holder.stageName ?? holder.firstName)}` : ''}</small>
        ${bonusList(g.bonuses ?? [], size === 'sm')}
        ${size === 'sm' ? '' : `<small class="gt-reach">${whoCanPlay(g)}</small>`}
        ${extra ? `<span class="gt-extra">${extra}</span>` : ''}
      </div>
    </div>`;
}

export function managerSkills(skills) {
  return skills.map((s) => {
    const info = cat().managerSkills[s];
    return `<span class="chip ${info.active ? 'skill' : 'skill inactive'}" title="${esc(info.desc)}${info.active ? '' : ' (kommt mit einem späteren Update)'}">${esc(info.name)}</span>`;
  }).join('');
}

/** Was eine Manager-Fähigkeit bei dieser Stufe konkret bringt (wie BandCalc::managerBonus und Packs::withScout). */
export function managerEffect(skill, level) {
  const r = cat().rules;
  const pct = (x) => `${Math.round(x * 100)} %`;
  switch (skill) {
    case 'verhandler': return `Gigs: <em>+${pct(r.negotiatorBonus * level)}</em> Gage.`;
    case 'prprofi': return `Auftritte: <em>+${pct(r.prBonus * level)}</em> Ruhm.`;
    case 'scout': return `Packs: <em>−${pct(Math.min(0.6, r.scoutShift * level))}</em> gewöhnliche Karten (ganzes Label).`;
    default: return '<i>wirkt erst mit einem späteren Update</i>';
  }
}

export function managerCard(m, { act = null, data = {}, size = '', extra = '' } = {}) {
  const skills = cat().managerSkills;
  const songs = Math.ceil(m.level * cat().rules.managerSongsPerLevel);
  return tcg({
    rarity: m.rarity,
    theme: 'manager-bg',
    name: fullName(m),
    art: `${avatar(m.look)}<span class="tcg-badge">💼</span>`,
    type: `Manager<em>Stufe ${fmt0(m.level)}</em>`,
    text: [
      ...m.skills.map((s) => `<span><b>${esc(skills[s].name)}</b> ${managerEffect(s, m.level)}</span>`),
      m.bandIds?.length > 1 ? `<span>Teilt sich auf ${m.bandIds.length} Bands auf.</span>` : '',
    ].join(''),
    stats: [[fmt0(m.level), 'Stufe'], [`+${songs}`, 'Lieder/Gig'], [fmt0(m.bandIds.length), 'Bands']],
    act,
    data,
    size,
    extra,
  });
}

/**
 * Wer mit welchem Instrument besser klingt und um wie viel: für ein freies Instrument (gearId) alle, die es verbessert,
 * für einen Musiker (musicianId) alle Instrumente im Lager, die ihn verbessern. Daten aus GearAdvice (upgrades).
 */
export function upgradeLines({ gear = null, musicianId = null }) {
  const lines = [];
  for (const g of gear ? [gear] : app.state.gear) {
    for (const u of g.upgrades ?? []) {
      if (musicianId !== null && u.musicianId !== musicianId) continue;
      if (gear && !u.bandId) continue;
      const m = musicianById(u.musicianId);
      if (!m) continue;
      const band = u.bandId ? bandById(u.bandId) : null;
      lines.push({
        text: `${gear ? m.firstName : gearModel(g.model).name}${band ? ` (${band.name})` : ''}: Sound ${fmt1(u.before)} → ${fmt1(u.after)} (+${fmt1(u.after - u.before)})`,
        gain: u.after - u.before,
      });
    }
  }
  return lines.sort((a, b) => b.gain - a.gain).map((l) => l.text);
}

/** Grüner Pfeil: Drüberfahren zeigt, wen es wie viel verbessert; Antippen öffnet die Liste. */
function upArrow(target) {
  const lines = upgradeLines(target);
  const head = target.gear ? 'Damit klingt besser:' : 'Im Lager liegt ein besseres Instrument:';
  return `<span class="tcg-up" data-act="upgradeInfo" data-head="${esc(head)}" data-lines="${esc(JSON.stringify(lines))}" title="${esc([head, ...lines].join('\n'))}">▲</span>`;
}

/** Leerer Kartenplatz, z. B. für ein fehlendes Instrument oder einen Manager. */
export function emptySlot(label, { act = null, data = {}, size = '', icon = '+', up = false } = {}) {
  const attrs = act ? ` data-act="${act}" ${Object.entries(data).map(([k, v]) => `data-${k}="${esc(v)}"`).join(' ')}` : '';
  const tag = act ? 'button' : 'div';
  const arrow = up && data.musician ? upArrow({ musicianId: Number(data.musician) }) : up ? '<span class="tcg-up" title="Im Lager liegt ein passendes Instrument">▲</span>' : '';
  return `<${tag} class="tcg-slot ${size}"${attrs}>${arrow}<span class="tcg-slot-icon">${icon}</span><span>${esc(label)}</span></${tag}>`;
}

/** Meldung, wenn Musiker aufsteigen: „Ada ist an der Gitarre jetzt Profi“. */
export function levelUpBanners(levelUps = []) {
  return levelUps.map((l) => `
    <div class="banner">⭐ ${esc(l.name)} ist ${esc(cat().instruments[l.instrument]?.at ?? '')} jetzt ${esc(cat().levelWords[l.level])}.</div>`).join('');
}
