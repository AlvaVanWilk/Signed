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

export function figure(look = {}, instrument = 'guitar') {
  const skin = look.skin || '#d9a27e';
  const shirt = look.shirt || '#4a6fa5';
  const pose = POSES[instrument] ? instrument : instrument === 'synth' ? 'keys' : 'guitar';
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
        ${pivot(60, 74, 'fig-head', head(look, skin, sing))}
        ${POSES[pose].front(skin, shirt)}
      </g>
    </svg>`;
}

/** Kopf im Koordinatensystem des Kartenbilds (Mitte 50/42), vergrößert auf die Figur gesetzt. */
function head(look, skin, sing) {
  const hair = look.hairColor || '#3b2a20';
  const style = look.hair || 'kurz';
  const hatted = look.hat && look.hat !== 'none';
  const p = [];
  if (style === 'afro') p.push(`<circle cx="50" cy="35" r="26" fill="${hair}"/>`);
  if (HAIR_BACK[style]) p.push(`<path d="${HAIR_BACK[style]}" fill="${hair}"/>`);
  p.push(`<g stroke="${INK}" stroke-width="1.2"><ellipse cx="31.5" cy="45" rx="3.5" ry="4.5" fill="${skin}"/><ellipse cx="68.5" cy="45" rx="3.5" ry="4.5" fill="${skin}"/>`);
  p.push(`<ellipse cx="50" cy="43" rx="19" ry="18" fill="${skin}"/></g>`);
  // Gesicht: Punktaugen mit Glanz, rote Bäckchen, Lächeln – beim Singen ein runder Mund.
  p.push(pivot(50, 45, 'fig-eyes', `<ellipse cx="43" cy="45" rx="2.3" ry="2.8" fill="${INK}"/><ellipse cx="57" cy="45" rx="2.3" ry="2.8" fill="${INK}"/>
    <circle cx="43.8" cy="44" r="0.8" fill="#fff"/><circle cx="57.8" cy="44" r="0.8" fill="#fff"/>`));
  p.push('<ellipse cx="38" cy="51" rx="3.2" ry="1.9" fill="#ff8fa3" opacity="0.6"/><ellipse cx="62" cy="51" rx="3.2" ry="1.9" fill="#ff8fa3" opacity="0.6"/>');
  p.push(sing
    ? pivot(50, 53.5, 'fig-mouth', `<ellipse cx="50" cy="53.5" rx="2.6" ry="2.4" fill="#7a2338" stroke="${INK}" stroke-width="0.8"/>`)
    : `<path d="M46.5 52 Q50 55 53.5 52" fill="none" stroke="${INK}" stroke-width="1.2" stroke-linecap="round"/>`);
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
const guitarLike = (color, reach) => (skin, shirt) => {
  const end = [36 - reach, 84 - reach * 0.45];
  const grip = [end[0] + 8, end[1] + 4];
  return `
  <g ${STROKE}>
    <path d="M60 97 L${end[0]} ${end[1]}" stroke="${INK}" stroke-width="7" stroke-linecap="round"/>
    <path d="M60 97 L${end[0]} ${end[1]}" stroke="#a06a3b" stroke-width="4" stroke-linecap="round"/>
    <rect x="${end[0] - 5}" y="${end[1] - 4}" width="9" height="7" rx="2" fill="#5b3a21" transform="rotate(25 ${end[0]} ${end[1]})"/>
    <circle cx="61" cy="99" r="9" fill="${color}"/><circle cx="71" cy="105" r="11" fill="${color}"/>
    <path d="M60 99 Q66 96 71 105" fill="none" stroke="${color}" stroke-width="6"/>
    <circle cx="65" cy="101" r="3" fill="${INK}"/>
  </g>
  ${arm(`M45 80 Q${grip[0] + 4} ${grip[1] - 6} ${grip[0]} ${grip[1]}`, grip, skin, shirt)}
  ${arm('M75 80 Q84 92 72 102', [72, 102], skin, shirt, 'fig-strum')}`;
};

const POSES = {
  guitar: { back: () => '', front: guitarLike('#e4572e', 6) },
  bass: { back: () => '', front: guitarLike('#3a86ff', 14) },
  vocals: {
    back: () => '',
    front: (skin, shirt) => `
      ${arm('M45 80 Q34 72 29 60', [29, 60], skin, shirt, 'fig-wave')}
      ${pivot(75, 80, 'fig-mic-arm', `${arm('M75 80 Q84 76 73 70', [73, 70], skin, shirt)}
        <rect x="70" y="58" width="5" height="12" rx="2" transform="rotate(-25 72 64)" fill="#444" stroke="${INK}" stroke-width="1.4"/>
        <circle cx="69.5" cy="59" r="4" fill="#9a9aa8" stroke="${INK}" stroke-width="1.4"/>`)}`,
  },
  drums: {
    back: () => `
      <g ${STROKE}><path d="M98 140 L98 96" fill="none"/>${pivot(98, 94, 'fig-cymbal', '<ellipse cx="98" cy="94" rx="14" ry="3.5" fill="#f2c14e"/>')}</g>`,
    front: (skin, shirt) => `
      <g ${STROKE}>
        <rect x="36" y="112" width="48" height="16" rx="3" fill="#e4572e"/>
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
export function playedInstrument(m) {
  const slot = m.bandSlot === 2 && m.instruments[1] ? 1 : 0;
  return m.instruments[slot].type;
}

/**
 * Bühne mit Figuren. bpm setzt das Tempo; mood: 'hype' (Publikum tobt – die Band springt), 'calm' oder ''.
 * names: Vornamen unter den Figuren zeigen.
 */
export function stage(members, { bpm = 120, names = true, className = '' } = {}) {
  return `<div class="fig-stage ${className}" style="--beat:${(60 / bpm).toFixed(3)}s">${members.map((m) => `
    <div class="fig-member">${figure(m.look, playedInstrument(m))}${names ? `<b>${escapeHtml(m.firstName)}</b>` : ''}</div>`).join('')}
  </div>`;
}

const escapeHtml = (text) => String(text).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
