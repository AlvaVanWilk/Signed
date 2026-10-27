import { HAIR_BACK, HAIR_FRONT, HATS, HAT_COLORS, hairExtras, hatOf } from './avatar.js';
import { ALLOYS, WOODS, tierArt } from './gearart.js';

/**
 * Niedliche Ganzkörper-Figur eines Musikers: großer Kopf aus denselben Merkmalen wie das Kartenbild,
 * kleiner Körper, Instrument. Bewegt wird nur mit CSS (Drehen, Verschieben) – leicht genug fürs iPad.
 * Das Tempo kommt über die CSS-Variable --beat (Sekunden pro Schlag).
 */
const INK = '#2a2233';
const PANTS = '#2f3a56';
// Weiche Licht- und Schattenflächen: halbdurchsichtig, damit sie auf jeder Farbe passen.
const SHADE = 'fill="#140f1e" opacity="0.16"';
const LIGHT = 'fill="#fff" opacity="0.16"';
const gloss = (d, w = 2.2, o = 0.2) => `<path d="${d}" fill="none" stroke="#fff" stroke-width="${w}" stroke-linecap="round" opacity="${o}"/>`;
const STROKE = `stroke="${INK}" stroke-width="2" stroke-linejoin="round"`;

/**
 * Bewegliches Teil mit festem Drehpunkt (x/y): verschoben, gedreht um 0/0, zurückgeschoben. So dreht Safari genauso
 * wie Chrome – Drehpunkte in Prozent (transform-box) rechnet Safari anders, dann schweben die Köpfe.
 */
const pivot = (x, y, cls, inner) => `<g transform="translate(${x} ${y})"><g class="${cls}"><g transform="translate(${-x} ${-y})">${inner}</g></g></g>`;

/**
 * Farben des eigenen Instruments an der Figur, wie auf der Karte (gearart.js). finish = { tier, hue, variant, gold }.
 * Ohne eigenes Instrument oder grau: Grundfarben (null). Grün: Holz bzw. Metall mit Chrom. Blau: Lackfarbe mit Gold.
 * Gold: Farben des Einzelstücks, Kante in der Farbe seiner Fähigkeit und leises Funkeln.
 */
const GOLD_PAINT = {
  magnet: { body: '#2e2a5c', dark: '#14122c', hw: '#e8c35a', edge: '#e8c35a', spark: '#fff1b8' },
  show: { body: '#3a2384', dark: '#120a2c', hw: '#f4f6fb', edge: '#cdb8ff', spark: '#ffffff' },
  studio: { body: '#f4eddc', dark: '#d9ceb2', hw: '#d8b45a', edge: '#b49a5a', spark: '#fff3c8' },
  cult: { body: '#5a3820', dark: '#24140a', hw: '#efe6d2', edge: '#efe6d2', spark: '#fff1b8' },
  hit: { body: '#f0c85a', dark: '#c08a22', hw: '#1c1a20', edge: '#7a5410', spark: '#fffbe6' },
};
export function paintOf(finish) {
  const tier = finish?.tier ?? 0;
  if (!tier) return null;
  const v = Math.abs(finish.variant ?? 0);
  if (tier === 1) {
    const wood = WOODS[v % WOODS.length];
    const alloy = ALLOYS[v % ALLOYS.length];
    return { tier, body: wood[1], dark: wood[2], metal: alloy[1], metalDark: alloy[2], hw: '#d9dbe2' };
  }
  if (tier === 2) {
    const h = finish.hue ?? 210;
    return { tier, body: `hsl(${h} 72% 50%)`, dark: `hsl(${h - 10} 60% 22%)`, metal: `hsl(${h} 72% 52%)`, metalDark: `hsl(${h} 70% 30%)`, hw: '#e8c35a' };
  }
  const g = GOLD_PAINT[finish.gold] ?? GOLD_PAINT.show;
  return { tier, ...g, metal: g.body, metalDark: g.dark };
}
/** Funkeln an einem goldenen Instrument (vierzackige Sterne, die nacheinander aufleuchten). */
const twinkle = (p, spots) => (p?.tier !== 3 ? '' : spots.map(([x, y, r = 2.6], i) => `<path class="fig-twinkle" style="animation-delay:${(i * 0.9).toFixed(1)}s" d="M${x} ${y - r} Q${x + r * 0.2} ${y - r * 0.2} ${x + r} ${y} Q${x + r * 0.2} ${y + r * 0.2} ${x} ${y + r} Q${x - r * 0.2} ${y + r * 0.2} ${x - r} ${y} Q${x - r * 0.2} ${y - r * 0.2} ${x} ${y - r} Z" fill="${p.spark}"/>`).join(''));

export function figure(look = {}, instrument = 'guitar', { electric = false, hue = null, finish = null, mood = null, bored = true, phones = false } = {}) {
  const skin = look.skin || '#d9a27e';
  const shirt = look.shirt || '#4a6fa5';
  const pose = POSES[instrument] ? instrument : instrument === 'synth' ? 'keys' : instrument === 'none' ? 'none' : 'guitar';
  const fin = finish ?? (hue === null ? null : { tier: 2, hue });
  const paint = paintOf(fin);
  const sing = pose === 'vocals';
  return `
    <svg class="fig fig-${pose}" viewBox="0 0 120 150" aria-hidden="true">
      <ellipse cx="60" cy="141" rx="32" ry="5" fill="rgba(0,0,0,0.25)"/>
      <g class="fig-body">
        ${look.hair === 'lang' ? pivot(60, 74, 'fig-head', hairBehind(look.hairColor || '#3b2a20')) : ''}
        <g ${STROKE}>
          <path d="M46 106 Q46 103 51.5 103 Q57 103 57 106 L56.4 133 Q51.5 135.5 46.6 133 Z" fill="${PANTS}"/>
          <path d="M63 106 Q63 103 68.5 103 Q74 103 74 106 L73.4 133 Q68.5 135.5 63.6 133 Z" fill="${PANTS}"/>
          ${shoe(50)}
          ${pivot(63, 138, 'fig-tap', shoe(70))}
          <path d="M43 76 Q60 68 77 76 L81 108 Q60 115 39 108 Z" fill="${shirt}"/>
        </g>
        <path d="M53.5 106 L55.4 132 Q53.5 133 52 132.6 Z M70.5 106 L72.4 132 Q70.5 133 69 132.6 Z" ${SHADE}/>
        <path d="M70 75.5 Q74 75.5 77 76 L81 108 Q76 110.6 70.5 111.6 Q75 94 70 75.5 Z" ${SHADE}/>
        <path d="M44 79 Q46 77.5 49 77 L45.5 104 Q43.5 104 42 103.2 Z" ${LIGHT}/>
        <path d="M41 104.5 Q60 111 79 104.5" fill="none" stroke="${INK}" stroke-width="1" opacity="0.35"/>
        <ellipse cx="60" cy="78.5" rx="15" ry="4.5" ${SHADE}/>
        <path d="M52.5 74.2 Q60 82 67.5 74.2 Z" fill="${skin}" stroke="${INK}" stroke-width="1.4" stroke-linejoin="round"/>
        <path d="M52.5 74.2 Q60 79 67.5 74.2 Q60 77 52.5 74.2 Z" ${SHADE}/>
        ${POSES[pose].back(skin, shirt, paint, fin)}
        ${pivot(60, 74, 'fig-head', head(look, skin, sing, pose === 'none' && bored ? 'bored' : mood, phones))}
        ${POSES[pose].front(skin, shirt, electric, paint, fin)}
      </g>
    </svg>`;
}

/**
 * Lange Haare fallen auch hinten über die Schultern: eine Haarfläche hinter dem Körper, etwas dunkler (liegt im Schatten),
 * damit zwischen den Strähnen und dem Hals kein Loch bleibt. Sie nickt mit dem Kopf mit (gleicher Drehpunkt und dieselbe Bewegung wie .fig-head).
 */
const hairBehind = (hair) => `<path d="M33 60 C33 50 87 50 87 60 L91 101 Q60 106 29 101 Z" fill="${hair}"/><path d="M33 60 C33 50 87 50 87 60 L91 101 Q60 106 29 101 Z" ${SHADE}/>`;

/** Schuh mit Sohle und kleinem Glanz auf der Kappe. */
const shoe = (x) => `<path d="M${x - 8} 137.6 Q${x - 8.4} 131.4 ${x - 1} 131.2 Q${x + 7.6} 131 ${x + 8} 137.6 Z" fill="${INK}"/>
  <path d="M${x - 8} 137.6 L${x + 8} 137.6" stroke="#5a5068" stroke-width="1.4" stroke-linecap="round"/>
  ${gloss(`M${x - 4.5} 133.4 Q${x - 1} 132.2 ${x + 2.5} 133`, 1.3, 0.28)}`;

/** Kopf im Koordinatensystem des Kartenbilds (Mitte 50/42), vergrößert auf die Figur gesetzt. */
/**
 * Gesichtsausdruck nach der ersten Charaktereigenschaft: Brauen, Augen (offen, halb geschlossen, zufriedene Bögen)
 * und Mund. Ohne Eigenschaft ein einfaches Lächeln.
 */
const line = (d, w = 1.2) => `<path d="${d}" fill="none" stroke="${INK}" stroke-width="${w}" stroke-linecap="round"/>`;
const BROWS = {
  ehrgeizig: line('M39.5 38.8 L46 40.6') + line('M60.5 38.8 L54 40.6'),
  perfektionist: line('M40 39.6 L46 39.6') + line('M54 38.4 Q57 36.4 60 38.4'),
  chaot: line('M40 38.6 Q43 37 46 39.2') + line('M54 40.6 L60 39.2'),
  rampensau: line('M40 38.4 Q43 36.2 46 38.4') + line('M54 38.4 Q57 36.2 60 38.4'),
  partytier: line('M40 38.4 Q43 36.2 46 38.4') + line('M54 38.4 Q57 36.2 60 38.4'),
  diva: line('M40 38.2 Q43 36.8 46 37.8', 1) + line('M54 37.8 Q57 36.8 60 38.2', 1),
};
const MOUTHS = {
  smile: line('M46.5 52 Q50 55 53.5 52'),
  wide: line('M45.3 51.4 Q50 56.4 54.7 51.4'),
  soft: line('M47.5 52.6 Q50 54.2 52.5 52.6'),
  smirk: line('M46.5 53.2 Q50.5 54.2 53.8 51.4'),
  flat: line('M47 53.2 L53 53.2'),
  grin: `<path d="M45 51.2 Q50 58.4 55 51.2 Z" fill="#7a2338" stroke="${INK}" stroke-width="1.1" stroke-linejoin="round"/>`,
  teeth: `<path d="M45 51.2 Q50 58.4 55 51.2 Z" fill="#7a2338" stroke="${INK}" stroke-width="1.1" stroke-linejoin="round"/><path d="M45.8 51.6 L54.2 51.6 L53.6 53 L46.4 53 Z" fill="#fff"/>`,
  // Schmollmund in natürlichem Lippenton (dunkler als die Haut); rot nur bei roten Lippen.
  pout: '<ellipse cx="50.6" cy="53.2" rx="1.7" ry="1.4" fill="rgba(90, 30, 40, 0.45)"/>',
};
/** Mund zwischen den Ausdrücken: offene Münder schließen sich, sonst bleibt der eigene Mund (z. B. gerade beim Perfektionisten). */
const CALM_MOUTH = { grin: 'wide', teeth: 'wide', pout: 'soft' };
const MOODS = {
  // Gelangweilt (kein Instrument): normale Augen, gerader Mund
  bored: { mouth: 'flat' },
  ehrgeizig: { mouth: 'smirk' },
  gelassen: { mouth: 'soft', eyes: 'lidded' },
  perfektionist: { mouth: 'flat' },
  chaot: { mouth: 'smirk' },
  rampensau: { mouth: 'grin' },
  diva: { mouth: 'pout', eyes: 'lidded' },
  teamplayer: { mouth: 'wide' },
  ruhepol: { mouth: 'soft', eyes: 'happy' },
  partytier: { mouth: 'teeth' },
};

/**
 * Merkmale, die nicht im Aussehen gespeichert sind (Ohrringe, rote Lippen), fest aus dem Aussehen abgeleitet:
 * dieselbe Musikerin sieht immer gleich aus, aber nicht alle haben sie.
 */
function trait(look, salt) {
  let h = 2166136261;
  for (const ch of `${salt}|${look.gender}|${look.skin}|${look.hair}|${look.hairColor}|${look.shirt}|${look.glasses}|${look.hat}`) h = Math.imul(h ^ ch.charCodeAt(0), 16777619);
  return ((h >>> 0) % 100) / 100;
}

/**
 * Ohrringe: nur ein Teil trägt welche (Frauen etwa die Hälfte, diverse Personen gut ein Drittel, Männer wenige und dann schlicht),
 * in verschiedenen Formen und Farben. Fest aus dem Aussehen abgeleitet, wie die anderen Merkmale.
 */
const EAR_SHAPES = ['stud', 'hoop', 'drop', 'dangle', 'double', 'pearl'];
const EAR_COLORS = ['#f2c14e', '#d6d9e0', '#e8a89a', '#4fc1b8', '#3a3448', '#d0475a'];
const EAR_SHARE = { f: 0.55, d: 0.35, m: 0.12 };
const LIP_SHARE = { f: 0.4, d: 0.3, m: 0 };
export function earringOf(look) {
  if (trait(look, 'earrings') >= (EAR_SHARE[look.gender] ?? 0)) return null;
  const shapes = look.gender === 'm' ? ['stud', 'hoop', 'double'] : EAR_SHAPES;
  return { shape: shapes[Math.floor(trait(look, 'earShape') * shapes.length)], color: EAR_COLORS[Math.floor(trait(look, 'earColor') * EAR_COLORS.length)] };
}
export const hasLipstick = (look) => trait(look, 'lips') < (LIP_SHARE[look.gender] ?? 0);

/** Piercing (wer eins hat, steht im Aussehen): Art fest aus dem Aussehen – Augenbraue, Lippe, Nase oder oben am Ohr. */
const PIERCING_KINDS = ['brow', 'lip', 'nose', 'helix'];
export const piercingOf = (look) => (look.piercing ? PIERCING_KINDS[Math.floor(trait(look, 'piercing') * PIERCING_KINDS.length)] : null);
/** Metall der Piercings: wie die Ohrringe, wenn es welche gibt (passt zusammen), sonst Silber. Perlen-Ohrringe haben Silber dazu. */
const pierceColor = (ear) => (!ear || ear.shape === 'pearl' ? '#d6d9e0' : ear.color);
/**
 * Metall in klein: dunkler Rand, die Farbe, ein heller Lichtpunkt auf einem Stück des Bogens (pathLength 10: dort sitzt das Licht).
 * So wirken auch farbige Ringe (Rosé, Türkis, Rot) wie Metall und nicht wie Haut.
 */
const metalArc = (d, c, w, light = '0 2 2.2 10') => `<path d="${d}" fill="none" stroke="${INK}" stroke-width="${w + 0.22}" stroke-linecap="round" opacity="0.55"/>`
  + `<path d="${d}" fill="none" stroke="${c}" stroke-width="${w}" stroke-linecap="round"/>`
  + `<path d="${d}" pathLength="10" fill="none" stroke="#fff" stroke-width="${w * 0.45}" stroke-linecap="round" stroke-dasharray="${light}" opacity="0.85"/>`;
const metalBead = (x, y, r, c) => `<circle cx="${x}" cy="${y}" r="${r}" fill="${c}" stroke="${INK}" stroke-width="0.3"/><circle cx="${x - r * 0.35}" cy="${y - r * 0.35}" r="${r * 0.38}" fill="#fff" opacity="0.85"/>`;
export const piercingSvg = (kind, c) => ({
  brow: metalBead(58.8, 37.6, 0.75, c) + metalBead(60.3, 40.2, 0.75, c),
  // Lippenring: sitzt am Rand der Unterlippe, etwas im Uhrzeigersinn gedreht.
  lip: `<g transform="rotate(28 54 53.2)">${metalArc('M53.4 52.7 A0.85 0.85 0 1 1 54.4 53.9', c, 0.5)}</g>`,
  // Nasenring ohne Nase: nur ein dünner halber Bogen, der am Nasenflügel hervorschaut.
  nose: `<g transform="rotate(-160 51.35 49.5)">${metalArc('M50.75 49.15 A0.75 0.75 0 0 0 51.85 49.9', c, 0.34, '0 3 3 10')}</g>`,
  // Helix: ein Ring durch den oberen Rand der Ohrmuschel, darunter ein kleiner Stecker.
  // Helix: Der Ring geht durch den Ohrrand – dort, wo er im Ohr steckt, ist er unterbrochen (nur der äußere Bogen ist zu sehen).
  helix: metalArc('M69.9 40.5 A1.15 1.15 0 1 1 70.3 42.5', c, 0.5, '0 6 2 10') + metalBead(71, 44.4, 0.5, c),
}[kind]);

/** Beide Ohrringe am Ohrläppchen (links 31, rechts 69, Höhe 50). */
function earrings({ shape, color }) {
  const one = (x) => ({
    stud: `<circle cx="${x}" cy="50.6" r="1.3" fill="${color}"/>`,
    pearl: `<circle cx="${x}" cy="51" r="1.7" fill="#f4efe6"/><circle cx="${x - 0.5}" cy="50.4" r="0.5" fill="#fff" stroke="none"/>`,
    hoop: `<circle cx="${x}" cy="52.6" r="2.4" fill="none" stroke="${color}" stroke-width="1.1"/>`,
    drop: `<circle cx="${x}" cy="50.4" r="1.1" fill="${color}"/><circle cx="${x}" cy="53.6" r="2.1" fill="${color}"/>`,
    dangle: `<path d="M${x} 50.2 V53" stroke="${color}" stroke-width="0.8"/><path d="M${x} 52.6 L${x + 1.8} 55.2 L${x} 57.8 L${x - 1.8} 55.2 Z" fill="${color}"/>`,
    double: `<circle cx="${x}" cy="50.6" r="1.1" fill="${color}"/><circle cx="${x + (x < 50 ? 0.6 : -0.6)}" cy="47.6" r="0.9" fill="${color}"/>`,
  }[shape]);
  // Dunkle Kontur nur um gefüllte Formen; der Creolen-Ring ist selbst eine Linie.
  return shape === 'hoop' ? `<g>${one(31)}${one(69)}</g>` : `<g stroke="${INK}" stroke-width="0.6">${one(31)}${one(69)}</g>`;
}

/** Bäckchen passend zur Haut: auf heller Haut rosa, auf dunkler ein dezentes warmes Rotbraun. */
function blush(skin) {
  const n = parseInt(skin.slice(1), 16);
  const light = (0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255)) / 255;
  if (light < 0.4) return ['#b0504f', 0.32];
  if (light < 0.6) return ['#d9687a', 0.38];
  return ['#ff8fa3', 0.6];
}

function eyes(kind, skin) {
  const open = `<ellipse cx="43" cy="45" rx="2.3" ry="2.8" fill="${INK}"/><ellipse cx="57" cy="45" rx="2.3" ry="2.8" fill="${INK}"/>
    <circle cx="43.8" cy="44" r="0.8" fill="#fff"/><circle cx="57.8" cy="44" r="0.8" fill="#fff"/>`;
  // Zufrieden geschlossen – ab und zu gehen sie doch kurz auf (CSS blendet um, jede Figur in eigenem Takt).
  if (kind === 'happy') {
    return `<g class="fig-shut">${line('M40.6 45.6 Q43 42.8 45.4 45.6', 1.4) + line('M54.6 45.6 Q57 42.8 59.4 45.6', 1.4)}</g><g class="fig-open">${open}</g>`;
  }
  const dots = `<ellipse cx="43" cy="45" rx="2.3" ry="2.8" fill="${INK}"/><ellipse cx="57" cy="45" rx="2.3" ry="2.8" fill="${INK}"/>
    <circle cx="43.8" cy="44" r="0.8" fill="#fff"/><circle cx="57.8" cy="44" r="0.8" fill="#fff"/>`;
  return dots;
}

/** Halb geschlossen: das obere Lid deckt die Augen bis zur Mitte (liegt über den Augen, gehört zum Ausdruck). */
const lids = (skin) => `<rect x="40" y="41.6" width="6" height="3.4" fill="${skin}"/><rect x="54" y="41.6" width="6" height="3.4" fill="${skin}"/>`
  + line('M40.4 45 L45.6 45') + line('M54.4 45 L59.6 45');

/** Haar unter einer Mütze: ein durchgehender Pony unter dem Rand, von Schläfe zu Schläfe (nicht in der Mitte ausgeschnitten). */
const HAT_HAIR = 'M31.2 42.4 C30 34 33 28 40 28 H60 C67 28 70 34 68.8 42.4 C66 39.4 62 38.2 56 38 C53 37.9 51.6 38.6 50 38.6 C48.4 38.6 47 37.9 44 38 C38 38.2 34 39.4 31.2 42.4 Z';

/** Lippenstift: Hautton mit Rosé gemischt und etwas dunkler – auf heller Haut zartrosa, auf dunkler ein warmes Beerenbraun. */
function lipColor(skin) {
  const n = parseInt(skin.slice(1), 16);
  const c = [n >> 16, (n >> 8) & 255, n & 255];
  const rose = [176, 62, 86];
  return `rgb(${c.map((v, i) => Math.round((v * 0.45 + rose[i] * 0.55) * 0.86)).join(',')})`;
}

function head(look, skin, sing, mood = null, phones = false) {
  const hair = look.hairColor || '#3b2a20';
  const style = look.hair || 'kurz';
  const hat = hatOf(look);
  const hatted = hat !== 'none';
  const p = [];
  if (style === 'afro') p.push(`<circle cx="50" cy="35" r="26" fill="${hair}"/>`);
  if (HAIR_BACK[style]) p.push(`<path d="${HAIR_BACK[style]}" fill="${hair}"/>`);
  p.push(`<g stroke="${INK}" stroke-width="1.2"><ellipse cx="31.5" cy="45" rx="3.5" ry="4.5" fill="${skin}"/><ellipse cx="68.5" cy="45" rx="3.5" ry="4.5" fill="${skin}"/>`);
  // Ohne Haare wäre der Kopf oben zu flach: Die Glatze bekommt einen etwas höheren Schädel.
  p.push(style === 'glatze' && !hatted ? `<ellipse cx="50" cy="41.2" rx="19" ry="19.8" fill="${skin}"/></g>` : `<ellipse cx="50" cy="43" rx="19" ry="18" fill="${skin}"/></g>`);
  p.push(`<path d="M30.6 43 Q32.4 41.6 33 45 Q32.6 47.6 31.2 47.4" fill="none" stroke="${INK}" stroke-width="0.7" opacity="0.45"/><path d="M69.4 43 Q67.6 41.6 67 45 Q67.4 47.6 68.8 47.4" fill="none" stroke="${INK}" stroke-width="0.7" opacity="0.45"/>`);
  p.push(`<path d="M64.5 31 Q70.6 42 66.4 53 Q60.4 60.4 50 61 Q63.6 54.6 64.5 31 Z" fill="#140f1e" opacity="0.09"/>`);
  const ear = earringOf(look);
  if (ear) p.push(earrings(ear));
  // Gesicht: Augen, Brauen und Mund nach dem Charakter, rote Bäckchen – beim Singen ein runder Mund.
  const face = MOODS[mood] ?? {};
  p.push(pivot(50, 45, 'fig-eyes', eyes(face.eyes, skin)));
  // Der typische Ausdruck (Brauen, Lider, Mund) kommt nur ab und zu, dazwischen schaut die Figur freundlich (CSS blendet um).
  // Wer Brauen hat, behält sie: dazwischen ruhige Brauen. Offene Münder und Schmollmund schließen sich dazwischen.
  // Halb geschlossene Augen (Diva, Gelassen) bleiben immer, nur Brauen und Mund wechseln.
  if (face.eyes === 'lidded') p.push(lids(skin));
  const upper = BROWS[mood] ?? '';
  const calmUpper = BROWS[mood] ? line('M40 39.2 Q43 37.9 46 39.2') + line('M54 39.2 Q57 37.9 60 39.2') : '';
  const calmMouth = CALM_MOUTH[face.mouth] ?? face.mouth ?? 'smile';
  const changes = calmMouth !== (face.mouth ?? 'smile');
  if (upper) p.push(`<g class="fig-mood">${upper}</g>${calmUpper ? `<g class="fig-calm">${calmUpper}</g>` : ''}`);
  const [cheek, alpha] = blush(skin);
  p.push(`<ellipse cx="38" cy="51" rx="3.2" ry="1.9" fill="${cheek}" opacity="${alpha}"/><ellipse cx="62" cy="51" rx="3.2" ry="1.9" fill="${cheek}" opacity="${alpha}"/>`);
  // Ein Teil der Musikerinnen trägt Lippenstift: ein Rosenton, der aus der Hautfarbe kommt, kaum dicker als der Mund sonst.
  const lips = hasLipstick(look);
  const lip = lipColor(skin);
  const tint = (m) => (lips ? m.replace(`stroke="${INK}" stroke-width="1.2"`, `stroke="${lip}" stroke-width="1.45"`).replace('fill="rgba(90, 30, 40, 0.45)"', `fill="${lip}"`) : m);
  const mouth = tint(MOUTHS[face.mouth ?? 'smile']);
  const smile = changes ? `<g class="fig-mood">${mouth}</g><g class="fig-calm">${tint(MOUTHS[calmMouth])}</g>` : mouth;
  // Sänger: singender Mund beim Spielen, Lächeln beim Warten (CSS blendet um).
  p.push(sing
    ? `<g class="fig-singing">${pivot(50, 53.5, 'fig-mouth', `<ellipse cx="50" cy="53.5" rx="2.6" ry="2.4" fill="#7a2338" stroke="${INK}" stroke-width="0.8"/>`)}</g><g class="fig-resting">${smile}</g>`
    : smile);
  const pierce = piercingOf(look);
  if (pierce) p.push(piercingSvg(pierce, pierceColor(ear)));
  if (look.freckles) {
    for (const [x, y] of [[40, 49], [42.5, 50.5], [57.5, 50.5], [60, 49]]) p.push(`<circle cx="${x}" cy="${y}" r="0.6" fill="#8a4b2a" opacity="0.6"/>`);
  }
  if (look.beard === 'full') p.push(`<path d="M32 46 C32 68 68 68 68 46 C64 58 58 56 50 56 C42 56 36 58 32 46 Z" fill="${hair}"/>`);
  if (look.beard === 'stubble') p.push(`<path d="M33 47 C34 63 66 63 67 47 C63 58 37 58 33 47 Z" fill="${hair}" opacity="0.3"/>`);

  // Mit Mütze schaut vom Haar nur der Pony unter dem Rand heraus (Locken, Afro, Dutt, Undercut und Irokese tragen keine).
  if (style === 'locken') {
    for (const [x, y, r] of [[35, 30, 7], [44, 24, 8], [55, 23, 8], [64, 29, 7], [32, 38, 5], [68, 38, 5]]) p.push(`<circle cx="${x}" cy="${y}" r="${r}" fill="${hair}"/>`);
  } else if (style === 'afro') {
    p.push(`<path d="M32 40 C29 13 71 13 68 40 C60 31 40 31 32 40 Z" fill="${hair}"/>`);
  } else if (hatted && HAIR_FRONT[style]) {
    p.push(`<path d="${HAT_HAIR}" fill="${hair}"/>`);
  } else if (HAIR_FRONT[style]) {
    p.push(`<path d="${HAIR_FRONT[hatted && style === 'sidecut' ? 'kurz' : style]}" fill="${hair}"/>`);
  }
  if (style === 'dutt' && !hatted) p.push(`<circle cx="50" cy="17" r="8" fill="${hair}"/>`);
  p.push(hairExtras(style, hair, hatted));
  // Haar ohne Glanz (wirkte wie Glas); Struktur kommt später nach Sandras Vorlage.

  if (look.glasses === 'round') p.push(`<g fill="none" stroke="${INK}" stroke-width="1.3"><circle cx="43" cy="45" r="5"/><circle cx="57" cy="45" r="5"/><path d="M48 45 L52 45"/></g>`);
  if (look.glasses === 'square') p.push(`<g fill="none" stroke="${INK}" stroke-width="1.3"><rect x="37.5" y="41" width="10" height="8" rx="1.5"/><rect x="52.5" y="41" width="10" height="8" rx="1.5"/><path d="M47.5 45 L52.5 45"/></g>`);
  if (look.glasses === 'sun') p.push('<g fill="#111"><rect x="37" y="41.5" width="11" height="7" rx="3"/><rect x="52" y="41.5" width="11" height="7" rx="3"/><rect x="47" y="43.5" width="6" height="1.6"/></g>');
  if (HATS[hat]) {
    const [c1, c2] = HAT_COLORS[hat];
    p.push(`<g stroke="${INK}" stroke-width="1.1" stroke-linejoin="round">${HATS[hat].replaceAll('HAT2', c2).replaceAll('HAT', c1)}</g>`);
  }
  // Kopfhörer (z. B. beim Demo-Hören in der Poststelle): Bügel über den Kopf, Muscheln auf den Ohren; über Haar und Mütze.
  if (phones) {
    const top = style === 'afro' ? 8 : style === 'dutt' && !hatted ? 12 : hatted ? 16 : 20;
    p.push(`<path d="M30.5 42 C29 ${top} 71 ${top} 69.5 42" fill="none" stroke="${INK}" stroke-width="4.6" stroke-linecap="round"/><path d="M30.5 42 C29 ${top} 71 ${top} 69.5 42" fill="none" stroke="#3a3f52" stroke-width="2.6" stroke-linecap="round"/>`
      + [27, 65.6].map((x) => `<rect x="${x}" y="37.5" width="7.4" height="14" rx="3.4" fill="#2ec4b6" stroke="${INK}" stroke-width="1.3"/><rect x="${x + 1.6}" y="40" width="2" height="8" rx="1" fill="#fff" opacity="0.35"/>`).join(''));
  }
  return `<g transform="translate(60 44) scale(1.5) translate(-50 -42)">${p.join('')}</g>`;
}

/** Nur die Hand (Faust), um sie vor ein Instrument zu legen, das vor dem Arm liegt. */
const fist = (hand, skin) => `<circle cx="${hand[0]}" cy="${hand[1]}" r="4.2" fill="${skin}" stroke="${INK}" stroke-width="1.6"/>
  <path d="M${hand[0] + 1.2} ${hand[1] - 3.2} A3.4 3.4 0 0 1 ${hand[0] + 1.6} ${hand[1] + 3}" fill="none" stroke="#140f1e" stroke-width="1.6" opacity="0.13"/>`;

/** Arm als dicker Strich im Hemd, mit Hand am Ende. */
const arm = (d, hand, skin, shirt, cls = '') => {
  const inner = `<path d="${d}" fill="none" stroke="${INK}" stroke-width="10" stroke-linecap="round"/>
  <path d="${d}" fill="none" stroke="${shirt}" stroke-width="7" stroke-linecap="round"/>
  <path d="${d}" fill="none" stroke="#140f1e" stroke-width="2.4" stroke-linecap="round" opacity="0.14" transform="translate(1.6 1)"/>
  <circle cx="${hand[0]}" cy="${hand[1]}" r="4.2" fill="${skin}" stroke="${INK}" stroke-width="1.6"/>
  <path d="M${hand[0] + 1.2} ${hand[1] - 3.2} A3.4 3.4 0 0 1 ${hand[0] + 1.6} ${hand[1] + 3}" fill="none" stroke="#140f1e" stroke-width="1.6" opacity="0.13"/>`;
  if (!cls) return inner;
  const [x, y] = d.match(/M\s*([\d.]+)\s+([\d.]+)/).slice(1).map(Number);
  return pivot(x, y, cls, inner);
};

let heldSerial = 0;
/**
 * Das Instrument aus dem Laden (gearart.js, 64 × 64) in den Händen der Figur, in seiner Güteklasse und Farbe (fin = finish, ohne eigenes: grau).
 * a: Punkt in der Zeichnung, der auf at landet; r: Drehung; s: Größe; flip: gespiegelt (Hals nach links); sx/sy: eigene Breite/Höhe (schmal von der Seite, flach gelegt).
 * Jede Kopie bekommt eigene Kennungen für ihre Farbverläufe.
 */
function held(key, fin, { a, at, r = 0, s = 1, flip = false, sx = null, sy = null }) {
  const art = tierArt(key, fin?.tier ?? 0, fin?.hue ?? null, fin?.variant ?? 0, fin?.gold ?? null, true);
  if (!art) return '';
  const n = ++heldSerial;
  const unique = art.replace(/id="([\w-]+)"/g, `id="$1-h${n}"`).replace(/url\(#([\w-]+)\)/g, `url(#$1-h${n})`);
  return `<g transform="translate(${at[0]} ${at[1]}) rotate(${r}) scale(${(flip ? -1 : 1) * (sx ?? s)} ${sy ?? s}) translate(${-a[0]} ${-a[1]})">${unique}</g>`;
}

/** Gitarre: Korpus vor dem Bauch, Hals schräg nach links oben; die linke Hand greift am Hals, die rechte schlägt an. */
const guitarPose = (skin, shirt, electric, p, fin) => `
  ${electric ? held('eguitar', fin, { a: [26.9, 40.5], at: [66, 104], r: -22, s: 1.24, flip: true }) : held('guitar', fin, { a: [27.7, 40.5], at: [67, 103], r: -20, s: 1.35, flip: true })}
  ${twinkle(p, [[48, 90], [84, 112, 2.2]])}
  ${arm('M45 80 Q42 79 38 85', [38, 85], skin, shirt)}
  ${arm('M75 80 Q84 92 72 102', [72, 102], skin, shirt, 'fig-strum')}`;

/** Bass: länger als die Gitarre, der Hals ragt weit über die Schulter. */
const bassPose = (skin, shirt, electric, p, fin) => `
  ${held('bass', fin, { a: [24.5, 41.6], at: [68, 107], r: -15, s: 1.55, flip: true })}
  ${twinkle(p, [[48, 92], [88, 114, 2.2]])}
  ${arm('M45 80 Q36 74 30 80', [30, 80], skin, shirt)}
  ${arm('M75 80 Q86 94 70 106', [70, 106], skin, shirt, 'fig-strum')}`;

/** Bläser und Geige ohne Arme, damit sie beim Spielen und in der Pause gleich aussehen (die Arme kommen je Haltung dazu). */
const SAX = (p, fin) => `${held('sax', fin, { a: [12.6, 11.3], at: [61, 62], r: 13.6, s: 0.85 })}${twinkle(p, [[92, 84], [58, 100, 2.2]])}`;
// Trompete schräg nach unten zum Publikum (nicht quer übers Gesicht); in der Pause waagerecht an der Seite, die Hand an den Ventilen.
const TRUMPET = (p, fin) => `${held('trumpet', fin, { a: [9.2, 30.5], at: [60, 61], r: 30, s: 0.81 })}${twinkle(p, [[95, 80], [80, 56, 2]])}`;
const TRUMPET_REST = (p, fin) => `${held('trumpet', fin, { a: [28, 27], at: [79, 104], r: 8, s: 0.7 })}${twinkle(p, [[100, 104, 2]])}`;
// Querflöte: das Mundloch an der Unterlippe, fast waagerecht zur Seite, lang genug für beide Hände.
const FLUTE = (p, fin) => `${held('flute', fin, { a: [12, 39], at: [60, 61], r: -33.7, s: 0.95, flip: true })}${twinkle(p, [[12, 65], [40, 61, 2]])}`;
const VIOLIN = (p, fin) => `${held('violin', fin, { a: [32, 2], at: [26, 55], r: -61, s: 0.75, sx: 0.55 })}${twinkle(p, [[38, 58], [66, 78, 2]])}`;
/** Mikrofon: in der Hand am Mund (singen) oder hängend in der Hand (Pause). */
// Singen: der Kopf des Mikros knapp unter dem Mund, die Faust um den Griff. Pause: locker in der Hand, der Kopf zeigt nach vorn oben.
const MIC_UP = (fin) => held('vocals', fin, { a: [24.5, 21.3], at: [62, 67], r: 15, s: 0.5 });
const MIC_DOWN = (fin) => held('vocals', fin, { a: [24.5, 21.3], at: [84, 96], r: 66.6, s: 0.5 });

const POSES = {
  // Ohne Instrument: Er steht nur dabei, die Arme hängen.
  none: {
    back: () => '',
    front: (skin, shirt) => `${arm('M45 80 Q40 92 42 103', [42, 103], skin, shirt, 'fig-shrug-l')}${arm('M75 80 Q81 92 78 103', [78, 103], skin, shirt, 'fig-shrug-r')}`,
  },
  guitar: { back: () => '', front: guitarPose },
  bass: { back: () => '', front: bassPose },
  vocals: {
    back: () => '',
    front: (skin, shirt, electric, p, fin) => `
      <g class="fig-resting">
        ${arm('M45 80 Q40 92 42 103', [42, 103], skin, shirt)}
        ${arm('M75 80 Q81 92 78 103', [78, 103], skin, shirt)}
        ${MIC_DOWN(fin)}${fist([78, 103], skin)}
        ${twinkle(p, [[90, 92, 2]])}
      </g>
      <g class="fig-singing">
      ${arm('M45 80 Q34 72 29 60', [29, 60], skin, shirt, 'fig-wave')}
      ${pivot(75, 80, 'fig-mic-arm', `${arm('M75 80 Q82 84 66 77', [66, 77], skin, shirt)}${MIC_UP(fin)}${fist([66, 77], skin)}${twinkle(p, [[54, 60, 2.2]])}`)}
      </g>`,
  },
  // Schlagzeug: das ganze Set vor der Figur, die Stöcke in den Händen treffen Trommel und Becken.
  drums: {
    back: () => '',
    front: (skin, shirt, electric, p, fin) => `
      ${electric ? held('edrums', fin, { a: [32, 40], at: [60, 117], s: 1.1 }) : held('drums', fin, { a: [31, 42], at: [60, 124], s: 1.1 })}
      ${twinkle(p, [[30, 104], [92, 126, 2.2]])}
      ${pivot(45, 80, 'fig-hit-l', `${arm('M45 80 Q38 94 47 102', [47, 102], skin, shirt)}<path d="M47 102 L${electric ? '54 106' : '60 97'}" stroke="#c8a165" stroke-width="3" stroke-linecap="round"/>`)}
      ${pivot(75, 80, 'fig-hit-r', `${arm('M75 80 Q84 90 82 98', [82, 98], skin, shirt)}<path d="M82 98 L92 91" stroke="#c8a165" stroke-width="3" stroke-linecap="round"/>`)}`,
  },
  keys: {
    back: () => '',
    front: (skin, shirt, electric, p, fin) => `
      <path d="M40 112 L36 140 M80 112 L84 140" fill="none" stroke="${INK}" stroke-width="2" stroke-linecap="round"/>
      ${held('synth', fin, { a: [32, 31], at: [60, 106], s: 1.2 })}
      ${twinkle(p, [[30, 86], [92, 114, 2.2]])}
      <g class="fig-key-l">${arm('M45 80 Q38 92 44 101', [44, 101], skin, shirt)}</g>
      <g class="fig-key-r">${arm('M75 80 Q82 92 76 101', [76, 101], skin, shirt)}</g>`,
  },
  // Zwei Plattenspieler flach auf dem Tisch (von schräg oben), davor die Tischkante.
  turntables: {
    back: () => '',
    front: (skin, shirt, electric, p, fin) => `
      <g ${STROKE}>
        <path d="M30 112 L28 140 M90 112 L92 140" fill="none"/>
        <rect x="20" y="102" width="80" height="12" rx="3" fill="${p?.dark ?? '#2b2d42'}"/>
      </g>
      ${held('turntables', fin, { a: [32, 32], at: [41, 100], s: 0.62, sy: 0.26 })}
      ${held('turntables', fin, { a: [32, 32], at: [79, 100], s: 0.62, sy: 0.26 })}
      ${twinkle(p, [[18, 97], [102, 113, 2.2]])}
      ${arm('M45 80 Q50 92 56 99', [56, 99], skin, shirt)}
      ${arm('M75 80 Q86 88 80 98', [80, 98], skin, shirt, 'fig-scratch')}`,
  },
  // Bläser und Geige: beim Spielen am Mund oder unterm Kinn, in der Pause (Bühne still) weg vom Gesicht.
  sax: {
    back: () => '',
    front: (skin, shirt, electric, p, fin) => `
      <g class="fig-play">${pivot(60, 62, 'fig-horn', `${SAX(p, fin)}
        ${arm('M45 80 Q56 78 63 80', [63, 80], skin, shirt)}
        ${arm('M75 80 Q76 92 67 94', [67, 94], skin, shirt)}`)}</g>
      <g class="fig-rest">
        ${arm('M45 80 Q40 92 42 103', [42, 103], skin, shirt)}
        <path d="M53 75 L59 84 M67 75 L61 84" stroke="${INK}" stroke-width="1" opacity="0.7"/>
        <g transform="translate(-4 20) rotate(-8 61 62)">${SAX(p, fin)}</g>
        ${arm('M75 80 Q82 94 72 104', [72, 104], skin, shirt)}
      </g>`,
  },
  trumpet: {
    back: () => '',
    front: (skin, shirt, electric, p, fin) => `
      <g class="fig-play">${pivot(60, 62, 'fig-horn', `${arm('M45 80 Q58 82 70 70', [70, 70], skin, shirt)}
        <g class="fig-key-r">${arm('M75 80 Q84 72 78 61', [78, 61], skin, shirt)}</g>
        ${TRUMPET(p, fin)}${fist([70, 70], skin)}<g class="fig-key-r">${fist([78, 61], skin)}</g>`)}</g>
      <g class="fig-rest">
        ${arm('M45 80 Q40 92 42 103', [42, 103], skin, shirt)}
        ${arm('M75 80 Q81 92 79 103', [79, 103], skin, shirt)}
        ${TRUMPET_REST(p, fin)}${fist([79, 103], skin)}
      </g>`,
  },
  flute: {
    back: () => '',
    front: (skin, shirt, electric, p, fin) => `
      <g class="fig-play">${pivot(60, 62, 'fig-horn', `${FLUTE(p, fin)}
        ${arm('M45 80 Q30 80 25 66', [25, 66], skin, shirt)}
        <g class="fig-key-r">${arm('M75 80 Q62 78 48 63', [48, 63], skin, shirt)}</g>`)}</g>
      <g class="fig-rest">
        ${arm('M45 80 Q40 92 42 103', [42, 103], skin, shirt)}
        <g transform="translate(80 104) rotate(-88) translate(-50 -63.4)">${FLUTE(p, fin)}</g>
        ${arm('M75 80 Q81 92 79 103', [79, 103], skin, shirt)}
      </g>`,
  },
  violin: {
    back: () => '',
    front: (skin, shirt, electric, p, fin) => `
      <g class="fig-play">${VIOLIN(p, fin)}
        ${arm('M45 80 Q30 78 26 58', [26, 58], skin, shirt)}
        ${pivot(75, 80, 'fig-bow', `${arm('M75 80 Q78 72 62 70', [62, 70], skin, shirt)}
          <path d="M44 60 L86 80" stroke="${INK}" stroke-width="2" stroke-linecap="round"/>`)}</g>
      <g class="fig-rest">
        <g transform="translate(41 103) rotate(70) translate(-23 -59.5)">${VIOLIN(p, fin)}</g>
        ${arm('M45 80 Q39 92 41 103', [41, 103], skin, shirt)}
        <path d="M80 98 L84 134" stroke="${INK}" stroke-width="2" stroke-linecap="round"/>
        ${arm('M75 80 Q81 92 79 103', [79, 103], skin, shirt)}
      </g>`,
  },
};

/** Ab so vielen Leuten steht jeder Zweite hinten (kleiner, weiter oben), damit bis zu sieben in einen Raum passen. */
const DEPTH_FROM = 5;
/** Platz im Raum: Mitte (Anteil der Breite) und Breite einer Figur; die Lage regelt label.css (.fig-stage.depth). */
function depthOf(i, n) {
  const x = (i + 0.5) / n;
  const w = n >= DEPTH_FROM ? Math.min(1.9 / n, 0.3) : Math.min(0.92 / n, 0.3);
  return `--x:${x.toFixed(4)};--w:${w.toFixed(4)}`;
}

/** Instrument, das ein Musiker in der Band spielt (wie BandCalc::instrument). */
/**
 * Passfoto im Stil der Figuren: Kopf und Schultern derselben Figur, ohne Instrument. Blinzelt und schaut umher,
 * wenn es in einem .fig-stage.idle steht. mood: Charaktereigenschaft für den Gesichtsausdruck.
 */
/** Praktikum: Der Kopf ist etwas kleiner (wirkt jünger); um den Hals herum verkleinert. */
const young = (look, svg) => (look.young ? `<g transform="translate(60 76) scale(0.84) translate(-60 -76)">${svg}</g>` : svg);

export function portrait(look = {}, mood = null) {
  const skin = look.skin || '#d9a27e';
  const shirt = look.shirt || '#4a6fa5';
  return `
    <svg class="fig fig-portrait" viewBox="14 -6 92 96" aria-hidden="true">
      <g class="fig-body">
        ${look.hair === 'lang' ? young(look, pivot(60, 74, 'fig-head', hairBehind(look.hairColor || '#3b2a20'))) : ''}
        <path d="M43 76 Q60 68 77 76 L81 108 Q60 115 39 108 Z" fill="${shirt}" ${STROKE}/>
        ${young(look, pivot(60, 74, 'fig-head', head(look, skin, false, mood)))}
      </g>
    </svg>`;
}

export function playedInstrument(m) {
  const slot = m.bandSlot === 2 && m.instruments[1] ? 1 : 0;
  return m.instruments[slot].type;
}

/**
 * Bühne mit Figuren. electricOf(m): spielt er elektrisch (E-Gitarre, E-Bass)? bpm setzt das Tempo; mood: 'hype' (Publikum tobt – die Band springt), 'calm' oder ''.
 * names: Vornamen unter den Figuren zeigen.
 */
/**
 * Bühne mit allen Mitgliedern. awayOf(m) liefert für jemanden, der gerade allein unterwegs ist (Üben, Promo, Kurs),
 * { until, label } – dann steht statt der Figur an seinem Platz, wann er zurück ist.
 */
/** Frisuren und Hüte, die über den Kopf hinausragen: Der Name darüber rückt ein Stück höher. */
const TALL = ['afro', 'dutt', 'irokese', 'locken', 'spacebuns', 'tolle'];

export function stage(members, { bpm = 120, names = true, className = '', electricOf = () => false, hueOf = () => null, finishOf = null, awayOf = () => null, nameOf = null, below = () => '', instrumentOf = playedInstrument, act = null } = {}) {
  // Im Raum (Proberaum, Studio, Bühne) stehen alle auf dem Boden: bis vier nebeneinander, ab fünf abwechselnd vorn und versetzt dahinter.
  const room = /\bin-room\b/.test(className);
  const place = (i) => (room ? `;${depthOf(i, members.length)}` : '');
  return `<div class="fig-stage ${className}${room ? ' depth' : ''}" style="--beat:${(60 / bpm).toFixed(3)}s">${members.map((m, i) => {
    const away = awayOf(m);
    const back = room && members.length >= DEPTH_FROM && i % 2 === 1 ? ' fig-back' : '';
    if (away) {
      return `<div class="fig-member fig-away${back}" title="${escapeHtml(away.label)}" style="${place(i).slice(1)}"><span class="fig-away-box"><span class="fig-away-label">${escapeHtml(away.label)}</span>
        <span class="countdown" data-until="${away.until}"></span></span>${names ? `<b>${escapeHtml(m.firstName)}</b>` : ''}</div>`;
    }
    return `
    <div class="fig-member${back}${act ? ' clickable' : ''}${TALL.includes(m.look?.hair) || ['cowboy', 'fedora'].includes(hatOf(m.look ?? {})) ? ' tall' : ''}" style="--d:${(-((m.id * 7919) % 4300) / 1000).toFixed(2)}s;--tilt:${((m.id * 37) % 13) - 6}deg${place(i)}"${act ? ` data-act="${act}" data-id="${m.id}" title="${escapeHtml(m.firstName)} öffnen"` : ''}>${figure(m.look, instrumentOf(m), { electric: electricOf(m), hue: hueOf(m), finish: finishOf ? finishOf(m) : null, mood: m.personality?.[0] ?? null })}${names ? `<b>${nameOf ? nameOf(m) : escapeHtml(m.stageName ?? m.firstName)}</b>` : ''}${below(m)}</div>`;
  }).join('')}
  </div>`;
}

const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
