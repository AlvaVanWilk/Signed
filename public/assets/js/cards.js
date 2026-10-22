import { avatar } from './avatar.js';
import { instrumentSvg } from './icons.js';
import { app, bandById, cat, isBusy, musicianById } from './core.js';
import { esc, fmt0, fmt1, percent } from './ui.js';

export const instrumentName = (type) => cat().instruments[type]?.name ?? type;
/** Emoji für Stellen, die nur Text können (Auswahllisten, Canvas). */
export const instrumentEmoji = (type) => cat().instruments[type]?.icon ?? '🎵';
/** Gezeichnetes Symbol; electric zeigt die E-Gitarre. */
export const instrumentIcon = (type, electric = false, hue = null) => instrumentSvg(type, electric, hue) ?? instrumentEmoji(type);

/** Farbe eines Instruments für Symbole und Figuren: Farbvariante und Sonderlackierung färben, sonst Grundfarbe. */
export function gearHue(g) {
  const model = { ...gearModel(g.model), ...(g.klang ? { klang: g.klang, spielbarkeit: g.spielbarkeit, design: g.design } : {}) };
  const finish = gearFinish(model);
  return finish.tier === 1 || finish.tier === 2 ? finish.hue : null;
}

/** Wie das eigene Instrument eines Musikers aussieht: elektrisch? welche Farbe? Bei fremden Musikern unbekannt. */
export function ownGear(m, type) {
  const g = app.state?.gear?.find((x) => x.musicianId === m.id && cat().gear[x.model]?.type === type);
  return g ? { electric: !!cat().gear[g.model].electric, hue: gearHue(g) } : { electric: false, hue: null };
}
export const playsElectric = (m, type) => ownGear(m, type).electric;
export const genreName = (key) => cat().genres[key]?.name ?? key;
export const rarityName = (key) => cat().rarities[key]?.name ?? key;
export const fullName = (p) => `${p.firstName} ${p.lastName}`;

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
  if (primary) {
    const shared = keys.filter((g) => g !== primary && allFans(g));
    second = shared.includes(preferSecond) ? preferSecond : shared[0] ?? null;
  }
  return { primary, second, title: primary ? bandTitle(primary, second) : null, bonus: primary ? (second ? c.rules.hybridBonus : c.rules.genreBonus) : 1 };
}

export function bandTitle(primary, second) {
  const name = genreName(primary);
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
    const away = app.state.running.find((a) => ['promo', 'practice', 'course'].includes(a.type) && a.musicianId === m.id);
    if (away) return `${{ promo: 'Promo-Tour', practice: 'Übt allein', course: 'Im Kurs' }[away.type]} · <span data-until="${away.endsAt}"></span>`;
    return 'Beschäftigt';
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
    style: genreSplit(m.genres),
    name: fullName(m),
    badge: `<span class="tcg-genres">${m.genres.map((g) => `<span class="genre-hit" role="button" data-act="genreInfo" data-genre="${g}" data-id="${m.id}" title="${esc(genreName(g))}: Was heißt das?">${genreIcon(g)}</span>`).join('')}</span>`,
    art: `${avatar(m.look)}
      <span class="tcg-badge">${m.instruments.map((i) => instrumentIcon(i.type, ownGear(m, i.type).electric, ownGear(m, i.type).hue)).join('')}</span>
      ${abilities.length ? `<span class="tcg-abilities">${abilities.map((a) => `<i title="${esc(a.name)}">${a.icon}</i>`).join('')}</span>` : ''}`,
    text: `
      ${m.instruments.map((i) => `<span class="skill-row"><span class="skill-name">${instrumentIcon(i.type, ownGear(m, i.type).electric, ownGear(m, i.type).hue)}<span> ${esc(instrumentName(i.type))}</span></span>${skillBar(i.skill)}</span>`).join('')}
      <span class="skill-row fame-row"><span class="skill-name">⭐<span> Bekanntheit</span></span>${fameBar(m.fame)}</span>
      <span class="tcg-traits">${abilities.length
        ? abilities.map((a) => `<b>${esc(a.name)}</b>`).join(' · ')
        : `<i>${m.personality.map((p) => esc(c.personalities[p])).join(' · ')}</i>`}</span>`,
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
 * Instrumentenkarte. skill = Können dessen, der es spielt (oder spielen soll): Die Schwelle für vollen Klang
 * wird grün, wenn er sie erreicht, sonst rot. Ohne skill nimmt die Karte ihren aktuellen Besitzer.
 * Ein grüner Pfeil zeigt, dass ein freies Instrument jemandem in einer Band helfen würde.
 */
/**
 * Aussehen nach Design: ab 30 eine eigene Farbe, ab 60 Sonderlackierung mit Metallic-Hintergrund,
 * ab 85 Gold-Edition mit Glitzern. Der Farbton hängt fest an den Werten des einzelnen Instruments.
 */
const FINISH_NAMES = ['', 'Farbvariante', 'Sonderlackierung', 'Gold-Edition'];
function gearFinish(model) {
  const tier = model.design >= 85 ? 3 : model.design >= 60 ? 2 : model.design >= 30 ? 1 : 0;
  const hue = (model.klang * 31 + model.design * 17 + model.spielbarkeit * 7) % 360;
  return { tier, hue };
}

export function gearCard(g, { act = null, data = {}, size = '', extra = '', skill = null, up = null } = {}) {
  const model = { ...gearModel(g.model), ...(g.klang ? { klang: g.klang, spielbarkeit: g.spielbarkeit, design: g.design } : {}) };
  const needed = 100 - model.spielbarkeit;
  const holder = g.musicianId ? musicianById(g.musicianId) : null;
  skill ??= holder?.instruments.find((i) => i.type === model.type)?.skill ?? null;
  const threshold = skill === null ? fmt0(needed)
    : `<b class="${skill >= needed ? 'ok' : 'bad'}" title="${skill >= needed ? 'Erreicht' : 'Noch nicht erreicht'}: Können ${fmt0(skill)}">${fmt0(needed)}</b>`;
  // Wie viel Klang das Instrument bringt: voll erst ab dem nötigen Können, darunter deutlich weniger.
  const rules = cat().rules;
  const full = (model.klang / 100) * rules.gearMaxBonus;
  const now = skill === null ? null : full * Math.min(1, needed <= 0 ? 1 : skill / needed) ** rules.gearUnlockCurve;
  const klangText = now === null || now >= full - 0.001
    ? `Klang <em>+${percent(full)}</em> ab Können ${threshold}`
    : `Klang jetzt <em>+${percent(now)}</em> · voll <b>+${percent(full)}</b> ab Können ${threshold}`;
  const arrow = (up ?? g.upgrades?.some((u) => u.bandId)) ? upArrow(up ? { musicianId: holder?.id ?? null } : { gear: g }) : '';
  const finish = gearFinish(model);
  const perks = (g.perks ?? []).map((p) => cat().gearPerks[p]).filter(Boolean);
  return tcg({
    rarity: model.rarity,
    theme: `gear-bg finish-${finish.tier}`,
    style: `--hue:${finish.hue}`,
    name: model.name,
    art: `<span class="tcg-icon">${instrumentIcon(model.type, model.electric, finish.tier === 1 || finish.tier === 2 ? finish.hue : null)}</span>${arrow}
      ${perks.length ? `<span class="tcg-abilities">${perks.map((p) => `<i title="${esc(p.name)}: ${esc(p.desc)}">${p.icon}</i>`).join('')}</span>` : ''}`,
    type: `Instrument<em>${esc(instrumentName(model.type))}${finish.tier ? ` · ${FINISH_NAMES[finish.tier]}` : ''}</em>`,
    text: `<span>${klangText}</span>${perks.length ? `<span>${perks.map((p) => `<b>${esc(p.name)}</b>`).join(' · ')}</span>` : ''}`,
    stats: [[model.klang, 'Klang'], [model.spielbarkeit, 'Spielb.'], [model.design, 'Design']],
    overlay: finish.tier === 3 ? sparkles(6, model.klang * 7 + model.design) : '',
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
    <div class="banner">⭐ ${esc(l.name)} ist ${esc(cat().instruments[l.instrument]?.at ?? '')} jetzt ${esc(cat().levelWords[l.level])}${l.abilities.length ? ` und lernt ${l.abilities.map((a) => `${cat().abilities[a].icon} ${esc(cat().abilities[a].name)}`).join(', ')}` : ''}${l.choice ? ' und darf eine Fähigkeit wählen' : ''}.</div>`).join('');
}
