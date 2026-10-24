import { HAIR_BACK, HAIR_FRONT, HATS, HAT_COLORS } from './avatar.js';

/**
 * Niedliche Ganzkörper-Figur eines Musikers: großer Kopf aus denselben Merkmalen wie das Kartenbild,
 * kleiner Körper, Instrument. Bewegt wird nur mit CSS (Drehen, Verschieben) – leicht genug fürs iPad.
 * Das Tempo kommt über die CSS-Variable --beat (Sekunden pro Schlag).
 */
const INK = '#2a2233';
const PANTS = '#2f3a56';
const STROKE = `stroke="${INK}" stroke-width="2" stroke-linejoin="round"`;

/**
 * Bewegliches Teil mit festem Drehpunkt (x/y): verschoben, gedreht um 0/0, zurückgeschoben. So dreht Safari genauso
 * wie Chrome – Drehpunkte in Prozent (transform-box) rechnet Safari anders, dann schweben die Köpfe.
 */
const pivot = (x, y, cls, inner) => `<g transform="translate(${x} ${y})"><g class="${cls}"><g transform="translate(${-x} ${-y})">${inner}</g></g></g>`;

export function figure(look = {}, instrument = 'guitar', { electric = false, hue = null, mood = null } = {}) {
  const skin = look.skin || '#d9a27e';
  const shirt = look.shirt || '#4a6fa5';
  const pose = POSES[instrument] ? instrument : instrument === 'synth' ? 'keys' : instrument === 'none' ? 'none' : 'guitar';
  const sing = pose === 'vocals';
  return `
    <svg class="fig fig-${pose}" viewBox="0 0 120 150" aria-hidden="true">
      <ellipse cx="60" cy="141" rx="32" ry="5" fill="rgba(0,0,0,0.25)"/>
      <g class="fig-body">
        <g ${STROKE}>
          <rect x="46" y="104" width="11" height="30" rx="5" fill="${PANTS}"/>
          <rect x="63" y="104" width="11" height="30" rx="5" fill="${PANTS}"/>
          <ellipse cx="50" cy="136" rx="8" ry="4.5" fill="${INK}"/>
          ${pivot(63, 138, 'fig-tap', `<ellipse cx="70" cy="136" rx="8" ry="4.5" fill="${INK}"/>`)}
          <path d="M43 76 Q60 68 77 76 L81 108 Q60 115 39 108 Z" fill="${shirt}"/>
        </g>
        ${POSES[pose].back(skin, shirt)}
        ${pivot(60, 74, 'fig-head', head(look, skin, sing, pose === 'none' ? 'bored' : mood))}
        ${POSES[pose].front(skin, shirt, electric, hue)}
      </g>
    </svg>`;
}

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
  bored: { mouth: 'flat', eyes: 'lidded' },
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
  for (const ch of `${salt}|${look.skin}|${look.hair}|${look.hairColor}|${look.shirt}|${look.glasses}|${look.hat}`) h = Math.imul(h ^ ch.charCodeAt(0), 16777619);
  return ((h >>> 0) % 100) / 100;
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

function head(look, skin, sing, mood = null) {
  const hair = look.hairColor || '#3b2a20';
  const style = look.hair || 'kurz';
  const hatted = look.hat && look.hat !== 'none';
  const p = [];
  if (style === 'afro') p.push(`<circle cx="50" cy="35" r="26" fill="${hair}"/>`);
  if (HAIR_BACK[style]) p.push(`<path d="${HAIR_BACK[style]}" fill="${hair}"/>`);
  p.push(`<g stroke="${INK}" stroke-width="1.2"><ellipse cx="31.5" cy="45" rx="3.5" ry="4.5" fill="${skin}"/><ellipse cx="68.5" cy="45" rx="3.5" ry="4.5" fill="${skin}"/>`);
  p.push(`<ellipse cx="50" cy="43" rx="19" ry="18" fill="${skin}"/></g>`);
  if (look.gender === 'f' && trait(look, 'earrings') < 0.6) {
    p.push(`<g fill="#f2c14e" stroke="${INK}" stroke-width="0.7"><circle cx="31" cy="50.4" r="1.2"/><circle cx="31" cy="53.6" r="2.2"/><circle cx="69" cy="50.4" r="1.2"/><circle cx="69" cy="53.6" r="2.2"/></g>`);
  }
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
  // Ein Teil der Musikerinnen hat rote Lippen: derselbe Mund, nur rot und etwas kräftiger.
  const lips = look.gender === 'f' && trait(look, 'lips') < 0.4;
  const tint = (m) => (lips ? m.replace(`stroke="${INK}" stroke-width="1.2"`, 'stroke="#c2405a" stroke-width="1.8"').replace('fill="rgba(90, 30, 40, 0.45)"', 'fill="#d23a5c"') : m);
  const mouth = tint(MOUTHS[face.mouth ?? 'smile']);
  const smile = changes ? `<g class="fig-mood">${mouth}</g><g class="fig-calm">${tint(MOUTHS[calmMouth])}</g>` : mouth;
  // Sänger: singender Mund beim Spielen, Lächeln beim Warten (CSS blendet um).
  p.push(sing
    ? `<g class="fig-singing">${pivot(50, 53.5, 'fig-mouth', `<ellipse cx="50" cy="53.5" rx="2.6" ry="2.4" fill="#7a2338" stroke="${INK}" stroke-width="0.8"/>`)}</g><g class="fig-resting">${smile}</g>`
    : smile);
  if (look.freckles) {
    for (const [x, y] of [[40, 49], [42.5, 50.5], [57.5, 50.5], [60, 49]]) p.push(`<circle cx="${x}" cy="${y}" r="0.6" fill="#8a4b2a" opacity="0.6"/>`);
  }
  if (look.beard === 'full') p.push(`<path d="M32 46 C32 68 68 68 68 46 C64 58 58 56 50 56 C42 56 36 58 32 46 Z" fill="${hair}"/>`);
  if (look.beard === 'stubble') p.push(`<path d="M33 47 C34 63 66 63 67 47 C63 58 37 58 33 47 Z" fill="${hair}" opacity="0.3"/>`);

  if (style === 'locken') {
    for (const [x, y, r] of [[35, 30, 7], [44, 24, 8], [55, 23, 8], [64, 29, 7], [32, 38, 5], [68, 38, 5]]) p.push(`<circle cx="${x}" cy="${y}" r="${r}" fill="${hair}"/>`);
  } else if (style === 'afro') {
    p.push(`<path d="M32 40 C29 13 71 13 68 40 C60 31 40 31 32 40 Z" fill="${hair}"/>`);
  } else if (HAIR_FRONT[style] && !(hatted && style === 'irokese')) {
    p.push(`<path d="${HAIR_FRONT[hatted && style === 'sidecut' ? 'kurz' : style]}" fill="${hair}"/>`);
  }
  if (style === 'dutt' && !hatted) p.push(`<circle cx="50" cy="17" r="8" fill="${hair}"/>`);

  if (look.glasses === 'round') p.push(`<g fill="none" stroke="${INK}" stroke-width="1.3"><circle cx="43" cy="45" r="5"/><circle cx="57" cy="45" r="5"/><path d="M48 45 L52 45"/></g>`);
  if (look.glasses === 'square') p.push(`<g fill="none" stroke="${INK}" stroke-width="1.3"><rect x="37.5" y="41" width="10" height="8" rx="1.5"/><rect x="52.5" y="41" width="10" height="8" rx="1.5"/><path d="M47.5 45 L52.5 45"/></g>`);
  if (look.glasses === 'sun') p.push('<g fill="#111"><rect x="37" y="41.5" width="11" height="7" rx="3"/><rect x="52" y="41.5" width="11" height="7" rx="3"/><rect x="47" y="43.5" width="6" height="1.6"/></g>');
  if (look.hat && HATS[look.hat]) {
    const [c1, c2] = HAT_COLORS[look.hat];
    p.push(HATS[look.hat].replaceAll('HAT2', c2).replaceAll('HAT', c1));
  }
  return `<g transform="translate(60 44) scale(1.5) translate(-50 -42)">${p.join('')}</g>`;
}

/** Arm als dicker Strich im Hemd, mit Hand am Ende. */
const arm = (d, hand, skin, shirt, cls = '') => {
  const inner = `<path d="${d}" fill="none" stroke="${INK}" stroke-width="10" stroke-linecap="round"/>
  <path d="${d}" fill="none" stroke="${shirt}" stroke-width="7" stroke-linecap="round"/>
  <circle cx="${hand[0]}" cy="${hand[1]}" r="4.2" fill="${skin}" stroke="${INK}" stroke-width="1.6"/>`;
  if (!cls) return inner;
  const [x, y] = d.match(/M\s*([\d.]+)\s+([\d.]+)/).slice(1).map(Number);
  return pivot(x, y, cls, inner);
};

/** Gitarre/Bass: Korpus vor dem Bauch, Hals schräg nach links oben. reach = wie weit der Hals reicht. */
const guitarLike = (baseColor, reach, baseE, baseHue) => (skin, shirt, electric, hue) => {
  const color = hue === null ? baseColor : `hsl(${hue} 62% 52%)`;
  const eColor = hue === null ? baseE : `hsl(${hue} 80% 50%)`;
  const end = [36 - reach, 84 - reach * 0.45];
  const grip = [end[0] + 8, end[1] + 4];
  // Akustisch: runder Holzkorpus mit Schallloch. Elektrisch: schmaler, geschwungener Korpus mit Schlagbrett und Tonabnehmern.
  const body = electric ? `
    <path d="M58 94 C62 91 67 93 67 97 C71 96 75 98 74 102 C79 104 81 109 77 113 C73 118 64 117 60 112 C55 112 52 107 55 103 C53 99 55 96 58 94 Z" fill="${eColor}"/>
    <path d="M59 103 C62 101 67 104 68 108 C64 112 58 110 57 107 Z" fill="#fff"/>
    <path d="M61 99 L65 103 M64 96.5 L68 100.5" stroke="${INK}" stroke-width="2.2"/>` : `
    <circle cx="61" cy="99" r="9" fill="${color}"/><circle cx="71" cy="105" r="11" fill="${color}"/>
    <path d="M60 99 Q66 96 71 105" fill="none" stroke="${color}" stroke-width="6"/>
    <circle cx="65" cy="101" r="3" fill="${INK}"/>`;
  return `
  <g ${STROKE}>
    <path d="M60 97 L${end[0]} ${end[1]}" stroke="${INK}" stroke-width="7" stroke-linecap="round"/>
    <path d="M60 97 L${end[0]} ${end[1]}" stroke="${electric ? '#d9b38c' : '#a06a3b'}" stroke-width="4" stroke-linecap="round"/>
    <rect x="${end[0] - 5}" y="${end[1] - 4}" width="9" height="7" rx="2" fill="${electric ? INK : '#5b3a21'}" transform="rotate(25 ${end[0]} ${end[1]})"/>
    ${body}
  </g>
  ${arm(`M45 80 Q${grip[0] + 4} ${grip[1] - 6} ${grip[0]} ${grip[1]}`, grip, skin, shirt)}
  ${arm('M75 80 Q84 92 72 102', [72, 102], skin, shirt, 'fig-strum')}`;
};

/**
 * Bass: deutlich anders als die Gitarre – viel längerer, dickerer Hals, der weit über die Schulter ragt,
 * großer Kopf mit vier dicken Mechaniken, größerer, länglicher Korpus mit dunklem Schlagbrett.
 */
const bassLike = (skin, shirt, electric, hue) => {
  const body = hue === null ? (electric ? '#3a86ff' : '#c98a4b') : `hsl(${hue} 72% 50%)`;
  const end = [10, 66];
  const grip = [30, 80];
  return `
  <g ${STROKE}>
    <path d="M62 100 L${end[0]} ${end[1]}" stroke="${INK}" stroke-width="8.5" stroke-linecap="round"/>
    <path d="M62 100 L${end[0]} ${end[1]}" stroke="${electric ? '#d9b38c' : '#7a4a22'}" stroke-width="5.4" stroke-linecap="round"/>
    <path d="M${end[0] + 2} ${end[1] + 1} L${end[0] - 8} ${end[1] - 6} L${end[0] - 10} ${end[1] - 2} L${end[0] - 1} ${end[1] + 5} Z" fill="${INK}"/>
    ${[0, 1, 2, 3].map((i) => `<circle cx="${end[0] - 3 - i * 2.4}" cy="${end[1] - 5.4 + i * 1.2}" r="1.7" fill="#d6d9e0" stroke-width="1"/>`).join('')}
    <path d="M55 96 C60 91 68 93 69 98 C74 96 80 99 79 104 C85 107 87 114 82 119 C76 125 63 124 58 117 C52 117 48 111 52 106 C49 101 51 97 55 96 Z" fill="${body}"/>
    <path d="M56 104 C60 101 67 104 68 109 C64 115 56 113 54 110 Z" fill="#2a2233" opacity="0.85"/>
    ${electric ? '' : `<circle cx="66" cy="107" r="3.4" fill="${INK}"/>`}
    <path d="M60 102 L67 108" stroke="${INK}" stroke-width="2.6"/>
  </g>
  ${arm(`M45 80 Q${grip[0] + 6} ${grip[1] - 6} ${grip[0]} ${grip[1]}`, grip, skin, shirt)}
  ${arm('M75 80 Q86 94 70 106', [70, 106], skin, shirt, 'fig-strum')}`;
};

const POSES = {
  // Ohne Instrument: Er steht nur dabei, die Arme hängen.
  none: {
    back: () => '',
    front: (skin, shirt) => `${arm('M45 80 Q40 92 42 103', [42, 103], skin, shirt)}${arm('M75 80 Q81 92 78 103', [78, 103], skin, shirt)}`,
  },
  guitar: { back: () => '', front: guitarLike('#e9a24b', 6, '#e4572e') },
  bass: { back: () => '', front: bassLike },
  vocals: {
    back: () => '',
    front: (skin, shirt) => `
      <g class="fig-resting">
        ${arm('M45 80 Q40 92 42 103', [42, 103], skin, shirt)}
        ${arm('M75 80 Q81 92 78 103', [78, 103], skin, shirt)}
        <rect x="76.6" y="103" width="3.6" height="9" rx="1.6" fill="#444" stroke="${INK}" stroke-width="1.2" transform="rotate(12 78 106)"/>
        <circle cx="79.6" cy="113.6" r="3.2" fill="#9a9aa8" stroke="${INK}" stroke-width="1.2"/>
      </g>
      <g class="fig-singing">
      ${arm('M45 80 Q34 72 29 60', [29, 60], skin, shirt, 'fig-wave')}
      ${pivot(75, 80, 'fig-mic-arm', `${arm('M75 80 Q84 76 73 70', [73, 70], skin, shirt)}
        <rect x="70" y="58" width="5" height="12" rx="2" transform="rotate(-25 72 64)" fill="#444" stroke="${INK}" stroke-width="1.4"/>
        <circle cx="69.5" cy="59" r="4" fill="#9a9aa8" stroke="${INK}" stroke-width="1.4"/>`)}
      </g>`,
  },
  drums: {
    back: () => `
      <g ${STROKE}><path d="M98 140 L98 96" fill="none"/>${pivot(98, 94, 'fig-cymbal', '<ellipse cx="98" cy="94" rx="14" ry="3.5" fill="#f2c14e"/>')}</g>`,
    front: (skin, shirt, electric, hue) => `
      <g ${STROKE}>
        <rect x="36" y="112" width="48" height="16" rx="3" fill="${hue === null ? '#e4572e' : `hsl(${hue} 72% 50%)`}"/>
        <ellipse cx="60" cy="112" rx="24" ry="6" fill="#f4efe6"/>
        <path d="M38 120 L82 120" stroke="#fff" stroke-width="2"/>
      </g>
      ${pivot(45, 80, 'fig-hit-l', `${arm('M45 80 Q38 94 47 102', [47, 102], skin, shirt)}<path d="M47 102 L62 109" stroke="#c8a165" stroke-width="3" stroke-linecap="round"/>`)}
      ${pivot(75, 80, 'fig-hit-r', `${arm('M75 80 Q84 90 82 98', [82, 98], skin, shirt)}<path d="M82 98 L96 92" stroke="#c8a165" stroke-width="3" stroke-linecap="round"/>`)}`,
  },
  keys: {
    back: () => '',
    front: (skin, shirt) => `
      <g ${STROKE}>
        <path d="M38 112 L34 140 M82 112 L86 140" fill="none"/>
        <rect x="24" y="100" width="72" height="12" rx="2" fill="#2b2d42"/>
        <rect x="27" y="102" width="66" height="6" fill="#fff" stroke-width="1"/>
      </g>
      <g fill="${INK}">${Array.from({ length: 10 }, (_, i) => `<rect x="${30 + i * 6.4}" y="102" width="2.4" height="3.6"/>`).join('')}</g>
      <g class="fig-key-l">${arm('M45 80 Q38 92 42 101', [42, 101], skin, shirt)}</g>
      <g class="fig-key-r">${arm('M75 80 Q82 92 78 101', [78, 101], skin, shirt)}</g>`,
  },
  turntables: {
    back: () => '',
    front: (skin, shirt) => `
      <g ${STROKE}>
        <path d="M34 112 L32 140 M86 112 L88 140" fill="none"/>
        <rect x="22" y="100" width="76" height="12" rx="3" fill="#2b2d42"/>
        <ellipse cx="40" cy="100" rx="13" ry="3.5" fill="#111"/><ellipse cx="80" cy="100" rx="13" ry="3.5" fill="#111"/>
        <ellipse cx="40" cy="100" rx="3" ry="1" fill="#ff4f8b" stroke-width="0.6"/><ellipse cx="80" cy="100" rx="3" ry="1" fill="#4fd1ff" stroke-width="0.6"/>
      </g>
      ${arm('M45 80 Q50 92 58 99', [58, 99], skin, shirt)}
      ${arm('M75 80 Q86 88 80 98', [80, 98], skin, shirt, 'fig-scratch')}`,
  },
  sax: {
    back: () => '',
    front: (skin, shirt) => pivot(60, 62, 'fig-horn', `
      <path d="M61 62 Q66 66 64 74 L64 98 Q66 108 76 104 L82 94" fill="none" stroke="${INK}" stroke-width="9" stroke-linecap="round"/>
      <path d="M61 62 Q66 66 64 74 L64 98 Q66 108 76 104 L82 94" fill="none" stroke="#f2c14e" stroke-width="6" stroke-linecap="round"/>
      <path d="M78 96 L88 88 L90 96 Z" fill="#f2c14e" ${STROKE}/>
      ${arm('M45 80 Q56 78 63 80', [63, 80], skin, shirt)}
      ${arm('M75 80 Q76 92 67 94', [67, 94], skin, shirt)}`),
  },
  trumpet: {
    back: () => '',
    front: (skin, shirt) => pivot(60, 62, 'fig-horn', `
      <path d="M62 62 L88 57" stroke="${INK}" stroke-width="6" stroke-linecap="round"/>
      <path d="M62 62 L88 57" stroke="#f2c14e" stroke-width="3.5" stroke-linecap="round"/>
      <path d="M86 57 L97 50 L99 64 Z" fill="#f2c14e" ${STROKE}/>
      <rect x="70" y="56" width="10" height="6" rx="1.5" fill="#f2c14e" ${STROKE} stroke-width="1.2"/>
      ${arm('M45 80 Q58 76 68 64', [68, 64], skin, shirt)}
      <g class="fig-key-r">${arm('M75 80 Q80 72 77 63', [77, 63], skin, shirt)}</g>`),
  },
  flute: {
    back: () => '',
    front: (skin, shirt) => pivot(60, 62, 'fig-horn', `
      <path d="M62 61 L22 69" stroke="${INK}" stroke-width="5" stroke-linecap="round"/>
      <path d="M62 61 L22 69" stroke="#d6d9e0" stroke-width="2.6" stroke-linecap="round"/>
      ${arm('M45 80 Q36 76 32 67', [32, 67], skin, shirt)}
      <g class="fig-key-r">${arm('M75 80 Q64 76 50 64', [50, 64], skin, shirt)}</g>`),
  },
  violin: {
    back: () => '',
    front: (skin, shirt) => `
      <g ${STROKE}>
        <path d="M44 70 L20 58" stroke="${INK}" stroke-width="5" stroke-linecap="round"/>
        <path d="M44 70 L20 58" stroke="#5b3a21" stroke-width="2.5" stroke-linecap="round"/>
        <g transform="rotate(28 44 72)"><ellipse cx="44" cy="72" rx="9" ry="6.5" fill="#b5651d"/><ellipse cx="53" cy="72" rx="7" ry="5.5" fill="#b5651d"/></g>
      </g>
      ${arm('M45 80 Q28 80 22 61', [22, 61], skin, shirt)}
      ${pivot(75, 80, 'fig-bow', `${arm('M75 80 Q78 72 62 70', [62, 70], skin, shirt)}
        <path d="M44 60 L86 80" stroke="${INK}" stroke-width="2" stroke-linecap="round"/>`)}`,
  },
};

/** Instrument, das ein Musiker in der Band spielt (wie BandCalc::instrument). */
/**
 * Passfoto im Stil der Figuren: Kopf und Schultern derselben Figur, ohne Instrument. Blinzelt und schaut umher,
 * wenn es in einem .fig-stage.idle steht. mood: Charaktereigenschaft für den Gesichtsausdruck.
 */
export function portrait(look = {}, mood = null) {
  const skin = look.skin || '#d9a27e';
  const shirt = look.shirt || '#4a6fa5';
  return `
    <svg class="fig fig-portrait" viewBox="14 -6 92 96" aria-hidden="true">
      <g class="fig-body">
        <path d="M43 76 Q60 68 77 76 L81 108 Q60 115 39 108 Z" fill="${shirt}" ${STROKE}/>
        ${pivot(60, 74, 'fig-head', head(look, skin, false, mood))}
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
export function stage(members, { bpm = 120, names = true, className = '', electricOf = () => false, hueOf = () => null, awayOf = () => null, nameOf = null, below = () => '', instrumentOf = playedInstrument } = {}) {
  return `<div class="fig-stage ${className}" style="--beat:${(60 / bpm).toFixed(3)}s">${members.map((m) => {
    const away = awayOf(m);
    if (away) {
      return `<div class="fig-member fig-away" title="${escapeHtml(away.label)}"><span class="fig-away-box"><span class="fig-away-label">${escapeHtml(away.label)}</span>
        <span class="countdown" data-until="${away.until}"></span></span>${names ? `<b>${escapeHtml(m.firstName)}</b>` : ''}</div>`;
    }
    return `
    <div class="fig-member" style="--d:${(-((m.id * 7919) % 4300) / 1000).toFixed(2)}s">${figure(m.look, instrumentOf(m), { electric: electricOf(m), hue: hueOf(m), mood: m.personality?.[0] ?? null })}${names ? `<b>${nameOf ? nameOf(m) : escapeHtml(m.firstName)}</b>` : ''}${below(m)}</div>`;
  }).join('')}
  </div>`;
}

const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
