/**
 * Plattencover aus vier Zutaten: Motiv, Farben, Stil und Anordnung – alles als SVG, ohne Bilddateien.
 * Jede Zutat hat eine Wertigkeit (0 gewöhnlich, 1 grün, 2 blau, 3 gold). Aus lauter gewöhnlichen Zutaten
 * entsteht ein blasses, leicht unscharfes Cover; je wertvoller, desto klarer, mit Glanz und bei Gold mit Folie.
 */

const circle = (cx, cy, r) => `M${cx - r} ${cy} a${r} ${r} 0 1 0 ${2 * r} 0 a${r} ${r} 0 1 0 ${-2 * r} 0 Z`;

function starPath(points, outer, inner) {
  const d = [];
  for (let i = 0; i < points * 2; i++) {
    const r = i % 2 ? inner : outer;
    const a = (Math.PI / points) * i - Math.PI / 2;
    d.push(`${(50 + r * Math.cos(a)).toFixed(1)} ${(50 + r * Math.sin(a)).toFixed(1)}`);
  }
  return `M${d.join(' L')} Z`;
}

/** Motive in einem 100×100-Feld; Löcher über fill-rule evenodd. */
export const MOTIFS = {
  blitz: { name: 'Blitz', d: 'M58 6 L22 56 H46 L38 94 L78 40 H54 Z' },
  herz: { name: 'Herz', d: 'M50 88 C20 66 6 50 6 32 C6 18 17 8 30 8 C40 8 47 14 50 22 C53 14 60 8 70 8 C83 8 94 18 94 32 C94 50 80 66 50 88 Z' },
  stern: { name: 'Stern', d: starPath(5, 46, 19) },
  mond: { name: 'Mond', d: 'M62 6 A44 44 0 1 0 94 68 A36 36 0 1 1 62 6 Z' },
  totenkopf: {
    name: 'Totenkopf',
    d: `M50 8 C26 8 12 26 12 46 C12 60 20 68 28 72 V88 H72 V72 C80 68 88 60 88 46 C88 26 74 8 50 8 Z ${circle(35, 46, 10)} ${circle(65, 46, 10)} M50 57 L44 68 H56 Z M39 76 h4 v12 h-4 Z M48 76 h4 v12 h-4 Z M57 76 h4 v12 h-4 Z`,
  },
  auge: { name: 'Auge', d: `M4 50 C24 18 76 18 96 50 C76 82 24 82 4 50 Z ${circle(50, 50, 20)} ${circle(50, 50, 9)}` },
  welle: { name: 'Welle', d: 'M4 62 C14 40 30 34 42 46 C46 30 62 22 78 30 C64 30 56 42 64 54 C72 64 86 62 96 52 V94 H4 Z' },
  rakete: {
    name: 'Rakete',
    d: `M50 4 C66 20 70 44 66 70 H34 C30 44 34 20 50 4 Z ${circle(50, 36, 8)} M34 52 L16 78 L34 72 Z M66 52 L84 78 L66 72 Z M42 74 L50 96 L58 74 Z`,
  },
  krone: { name: 'Krone', d: `M10 28 L28 52 L50 14 L72 52 L90 28 L82 78 H18 Z ${circle(50, 60, 6)} ${circle(30, 64, 4)} ${circle(70, 64, 4)} M18 84 H82 V93 H18 Z` },
  katze: {
    name: 'Katze',
    d: 'M14 40 L18 6 L38 26 C46 24 54 24 62 26 L82 6 L86 40 C90 70 70 92 50 92 C30 92 10 70 14 40 Z M28 50 C33 41 41 41 44 50 C41 59 33 59 28 50 Z M56 50 C59 41 67 41 72 50 C67 59 59 59 56 50 Z M45 64 H55 L50 70 Z',
  },
  pilz: { name: 'Pilz', d: `M6 54 C6 26 28 10 50 10 C72 10 94 26 94 54 Z ${circle(30, 36, 7)} ${circle(55, 25, 6)} ${circle(73, 42, 6)} M36 58 H64 L68 92 H32 Z` },
  flamme: {
    name: 'Flamme',
    d: 'M50 4 C58 24 80 34 80 60 C80 80 66 94 50 94 C34 94 20 80 20 60 C20 44 30 36 34 22 C40 34 42 40 46 42 C48 30 46 16 50 4 Z M50 56 C56 64 62 70 60 80 C58 88 42 88 40 80 C38 72 44 66 50 56 Z',
  },
  vinyl: { name: 'Schallplatte', d: `${circle(50, 50, 45)} ${circle(50, 50, 33)} ${circle(50, 50, 30)} ${circle(50, 50, 13)} ${circle(50, 50, 2.5)}` },
};

/** Farben: Hintergrund, Hauptfarbe, Akzent, Tinte (Linien). */
export const PALETTES = {
  neon: { name: 'Neon', bg: '#1b0f2e', main: '#ff2fb3', accent: '#2ff3ff', ink: '#0b0614' },
  pastell: { name: 'Pastell', bg: '#fde2e4', main: '#8fb8e8', accent: '#ffc98a', ink: '#4a3f5c' },
  schwarzrot: { name: 'Schwarz-Rot', bg: '#111111', main: '#e01e37', accent: '#f2f2f2', ink: '#000000' },
  goldlila: { name: 'Gold-Lila', bg: '#2a0f45', main: '#f6c343', accent: '#b07cff', ink: '#14061f' },
  ozean: { name: 'Ozean', bg: '#023e8a', main: '#48cae4', accent: '#caf0f8', ink: '#011a3a' },
  wald: { name: 'Wald', bg: '#1b4332', main: '#95d5b2', accent: '#ffd166', ink: '#081c15' },
  sonne: { name: 'Sonnenuntergang', bg: '#ff7b00', main: '#ffd000', accent: '#9d0208', ink: '#370617' },
  kaugummi: { name: 'Kaugummi', bg: '#ff8fab', main: '#ffffff', accent: '#7b2cbf', ink: '#3c096c' },
  mono: { name: 'Schwarz-Weiß', bg: '#e9ecef', main: '#212529', accent: '#adb5bd', ink: '#000000' },
  gift: { name: 'Giftgrün', bg: '#141414', main: '#b7ff00', accent: '#ff00a8', ink: '#000000' },
};

export const STYLES = {
  popart: { name: 'Pop-Art', font: '"Arial Black", "Helvetica Neue", sans-serif' },
  neon: { name: 'Neon', font: '"Futura", "Trebuchet MS", sans-serif' },
  holzschnitt: { name: 'Holzschnitt', font: 'Georgia, "Times New Roman", serif' },
  aquarell: { name: 'Aquarell', font: '"Snell Roundhand", "Brush Script MT", cursive' },
  kaleido: { name: 'Kaleidoskop', font: '"Avenir Next", "Segoe UI", sans-serif' },
  retro: { name: 'Retro', font: '"Cooper Black", "Rockwell", Georgia, serif' },
  pixel: { name: 'Pixel', font: '"Courier New", Courier, monospace' },
  graffiti: { name: 'Graffiti', font: '"Marker Felt", "Comic Sans MS", cursive' },
};

export const LAYOUTS = {
  mitte: { name: 'Mitte' },
  riesig: { name: 'Riesig' },
  muster: { name: 'Muster' },
  schraeg: { name: 'Schräg' },
};

/** Bandlogo: Schriften (nur Systemschriften, nichts nachladen) und Farben; die Namen stehen in Catalog::LOGO_FONTS / LOGO_COLORS. */
export const LOGO_FONTS = {
  block: '"Arial Black", "Helvetica Neue", Impact, sans-serif',
  serif: 'Georgia, "Times New Roman", serif',
  script: '"Snell Roundhand", "Brush Script MT", "Segoe Script", cursive',
  mono: '"Courier New", Courier, monospace',
  round: '"Cooper Black", "Arial Rounded MT Bold", Georgia, serif',
  marker: '"Marker Felt", "Comic Sans MS", "Chalkboard SE", cursive',
};
export const LOGO_COLORS = { weiss: '#ffffff', gold: '#f2c14e', rot: '#e63946', tuerkis: '#2ec4b6', pink: '#ff70a6', schwarz: '#111111' };

/** Punkte je Wertigkeit (gewöhnlich, grün, blau, gold) – daraus die Cover-Güte. */
export const RARITY_POINTS = [0, 1, 2, 4];
export const RARITY_NAMES = ['gewöhnlich', 'grün', 'blau', 'gold'];

/** Güte 0–1 aus den Wertigkeiten der vier Zutaten. */
export function coverQuality(rarity) {
  return rarity.reduce((sum, r) => sum + RARITY_POINTS[r], 0) / (RARITY_POINTS[3] * rarity.length);
}

let uid = 0;

function placed(layout, inner) {
  switch (layout) {
    case 'riesig': return `<g transform="translate(-12 4) scale(1.18)">${inner}</g>`;
    case 'schraeg': return `<g transform="translate(50 54) rotate(-18) scale(0.66) translate(-50 -50)">${inner}</g>`;
    case 'muster': {
      const cells = [];
      for (let y = 0; y < 3; y++) {
        for (let x = 0; x < 3; x++) {
          cells.push(`<g transform="translate(${8 + x * 29} ${16 + y * 25}) scale(0.24) rotate(${(x + y) % 2 ? 12 : -12} 50 50)">${inner}</g>`);
        }
      }
      return cells.join('');
    }
    default: return `<g transform="translate(21 22) scale(0.58)">${inner}</g>`;
  }
}

/** Hintergrund und Motiv im gewählten Stil. */
function styled(style, motif, p, layout, id) {
  const shape = (attrs) => `<path d="${motif.d}" fill-rule="evenodd" ${attrs}/>`;
  const thick = 'vector-effect="non-scaling-stroke" stroke-linejoin="round"';
  switch (style) {
    case 'popart':
      return {
        defs: `<pattern id="${id}d" width="5" height="5" patternUnits="userSpaceOnUse"><circle cx="2.5" cy="2.5" r="1.3" fill="${p.accent}" opacity="0.55"/></pattern>`,
        bg: `<rect width="100" height="100" fill="${p.bg}"/><rect width="100" height="100" fill="url(#${id}d)"/>`,
        art: placed(layout, `<g transform="translate(4 4)">${shape(`fill="${p.ink}"`)}</g>${shape(`fill="${p.main}" stroke="${p.ink}" stroke-width="2.4" ${thick}`)}`),
      };
    case 'neon':
      return {
        defs: `<filter id="${id}g" x="-30%" y="-30%" width="160%" height="160%"><feGaussianBlur stdDeviation="1.6" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
          <radialGradient id="${id}r"><stop offset="0" stop-color="${p.bg}"/><stop offset="1" stop-color="#05030a"/></radialGradient>`,
        bg: `<rect width="100" height="100" fill="url(#${id}r)"/>`,
        art: `<g filter="url(#${id}g)">${placed(layout, `${shape(`fill="none" stroke="${p.main}" stroke-width="2.2" ${thick}`)}${shape(`fill="none" stroke="${p.accent}" stroke-width="0.7" ${thick} transform="translate(1.5 1.5)"`)}`)}</g>`,
      };
    case 'holzschnitt':
      return {
        defs: `<pattern id="${id}h" width="3" height="3" patternUnits="userSpaceOnUse" patternTransform="rotate(35)"><rect width="1.1" height="3" fill="${p.main}" opacity="0.5"/></pattern>
          <filter id="${id}x"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="1" seed="3"/><feDisplacementMap in="SourceGraphic" scale="1.6"/></filter>`,
        bg: `<rect width="100" height="100" fill="#efe4cc"/><rect width="100" height="100" fill="url(#${id}h)"/>`,
        art: `<g filter="url(#${id}x)">${placed(layout, `${shape(`fill="${p.ink}"`)}${shape(`fill="none" stroke="#efe4cc" stroke-width="0.8" stroke-dasharray="3 2" ${thick} transform="translate(50 50) scale(0.86) translate(-50 -50)"`)}`)}</g>`,
      };
    case 'aquarell':
      return {
        defs: `<filter id="${id}w" x="-20%" y="-20%" width="140%" height="140%"><feTurbulence type="fractalNoise" baseFrequency="0.06" numOctaves="3" seed="7"/><feDisplacementMap in="SourceGraphic" scale="7"/><feGaussianBlur stdDeviation="0.7"/></filter>`,
        bg: `<rect width="100" height="100" fill="#fbf7f0"/><g filter="url(#${id}w)" opacity="0.5"><circle cx="24" cy="26" r="22" fill="${p.accent}"/><circle cx="78" cy="74" r="26" fill="${p.bg}"/></g>`,
        // Dünner Tuschestrich über der Farbe, damit man das Motiv erkennt.
        art: `<g filter="url(#${id}w)" opacity="0.88">${placed(layout, shape(`fill="${p.main}"`))}</g>${placed(layout, shape(`fill="none" stroke="${p.ink}" stroke-width="0.8" opacity="0.55" ${thick}`))}`,
      };
    case 'kaleido': {
      const ring = [0, 90, 180, 270].map((a, i) => `<g transform="rotate(${a} 50 50)" opacity="${i % 2 ? 0.55 : 0.85}">${placed(layout, shape(`fill="${i % 2 ? p.accent : p.main}"`))}</g>`).join('');
      return {
        defs: `<radialGradient id="${id}k"><stop offset="0" stop-color="${p.accent}" stop-opacity="0.35"/><stop offset="1" stop-color="${p.bg}"/></radialGradient>`,
        bg: `<rect width="100" height="100" fill="${p.bg}"/><rect width="100" height="100" fill="url(#${id}k)"/>`,
        art: `<g style="mix-blend-mode:screen">${ring}</g>`,
      };
    }
    case 'retro': {
      const rays = Array.from({ length: 12 }, (_, i) => `<path d="M50 62 L${50 + 90 * Math.cos((i * 30 - 4) * Math.PI / 180)} ${62 + 90 * Math.sin((i * 30 - 4) * Math.PI / 180)} L${50 + 90 * Math.cos((i * 30 + 11) * Math.PI / 180)} ${62 + 90 * Math.sin((i * 30 + 11) * Math.PI / 180)} Z" fill="${p.accent}" opacity="0.35"/>`).join('');
      return {
        defs: '',
        bg: `<rect width="100" height="100" fill="${p.bg}"/>${rays}<rect y="78" width="100" height="22" fill="${p.ink}" opacity="0.35"/>`,
        art: placed(layout, shape(`fill="${p.main}" stroke="${p.ink}" stroke-width="1.6" ${thick}`)),
      };
    }
    case 'pixel':
      return {
        defs: `<mask id="${id}m">${placed(layout, shape('fill="#fff"'))}</mask>
          <pattern id="${id}p" width="4" height="4" patternUnits="userSpaceOnUse"><rect width="3.4" height="3.4" fill="${p.main}"/></pattern>
          <pattern id="${id}q" width="4" height="4" patternUnits="userSpaceOnUse"><rect width="3.4" height="3.4" fill="${p.accent}" opacity="0.18"/></pattern>`,
        bg: `<rect width="100" height="100" fill="${p.bg}"/><rect width="100" height="100" fill="url(#${id}q)"/>`,
        art: `<rect width="100" height="100" fill="url(#${id}p)" mask="url(#${id}m)"/>`,
      };
    case 'graffiti':
    default: {
      const drips = [30, 44, 58, 70].map((x, i) => `<rect x="${x}" y="70" width="2.4" height="${10 + i * 5}" rx="1.2" fill="${p.main}"/>`).join('');
      return {
        defs: `<pattern id="${id}b" width="20" height="10" patternUnits="userSpaceOnUse"><rect width="20" height="10" fill="${p.bg}"/><path d="M0 0 H20 M0 5 H20 M10 0 V5 M0 5 V10 M20 5 V10" stroke="${p.ink}" stroke-width="0.6" opacity="0.4"/></pattern>`,
        bg: `<rect width="100" height="100" fill="url(#${id}b)"/>`,
        art: placed(layout, `${drips}${shape(`fill="${p.main}" stroke="${p.accent}" stroke-width="2.6" ${thick}`)}`),
      };
    }
  }
}

/**
 * SVG eines Covers. spec: { motif, palette, style, layout, rarity: [0–3 je Zutat] }.
 * band/title erscheinen oben und unten; ohne beides nur das Bild (z. B. für kleine Vorschauen).
 */
export function coverSvg(spec, { band = '', title = '', className = '' } = {}) {
  const id = `cv${++uid}`;
  const p = PALETTES[spec.palette];
  const motif = MOTIFS[spec.motif];
  const s = styled(spec.style, motif, p, spec.layout, id);
  const rarity = spec.rarity ?? [0, 0, 0, 0];
  const q = coverQuality(rarity);
  const gold = rarity.includes(3);
  // Gewöhnlich = verwaschen und etwas unscharf; je wertvoller, desto klarer und leuchtender.
  const look = q < 0.1 ? 'saturate(0.35) contrast(0.75) brightness(1.08) blur(0.6px)'
    : q < 0.25 ? 'saturate(0.65) contrast(0.88) blur(0.25px)'
    : q < 0.45 ? 'saturate(0.9)'
    : 'saturate(1.15) contrast(1.05)';
  const textColor = ['holzschnitt', 'aquarell'].includes(spec.style) ? p.ink : '#fff';
  const font = STYLES[spec.style].font;
  // Mit Bandlogo steht der Name immer in derselben Schrift und Farbe (Wiedererkennung).
  const logoFont = spec.logo ? LOGO_FONTS[spec.logo.font] ?? font : font;
  const logoColor = spec.logo ? LOGO_COLORS[spec.logo.color] ?? textColor : textColor;
  const logoStroke = spec.logo?.color === 'schwarz' ? '#ffffff' : p.ink;
  const text = (band || title) ? `
    <text x="50" y="11" text-anchor="middle" font-family='${logoFont}' font-size="8" font-weight="700" fill="${logoColor}" stroke="${logoStroke}" stroke-width="${spec.logo ? 0.6 : 0.4}" paint-order="stroke">${esc(band)}</text>
    <text x="50" y="95" text-anchor="middle" font-family='${font}' font-size="5" fill="${textColor}" opacity="0.9">${esc(title)}</text>` : '';
  return `<svg class="cover ${gold ? 'cover-gold' : ''} ${className}" viewBox="0 0 100 100" role="img" aria-label="Plattencover">
    <defs>${s.defs}
      <linearGradient id="${id}s" x1="0" y1="0" x2="1" y2="1"><stop offset="0.35" stop-color="#fff" stop-opacity="0"/><stop offset="0.5" stop-color="#fff" stop-opacity="0.35"/><stop offset="0.65" stop-color="#fff" stop-opacity="0"/></linearGradient>
      <linearGradient id="${id}f" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff6ad5"/><stop offset="0.25" stop-color="#ffe66d"/><stop offset="0.5" stop-color="#6dffb0"/><stop offset="0.75" stop-color="#6dc8ff"/><stop offset="1" stop-color="#c56dff"/></linearGradient>
    </defs>
    <g style="filter:${look}">${s.bg}${s.art}${text}</g>
    ${q < 0.1 ? '<rect width="100" height="100" fill="#fff" opacity="0.18"/>' : ''}
    ${q >= 0.45 ? `<rect width="100" height="100" fill="url(#${id}s)"/>` : ''}
    ${gold ? `<rect width="100" height="100" fill="url(#${id}f)" opacity="0.28" style="mix-blend-mode:overlay"/>` : ''}
  </svg>`;
}

function esc(s) {
  return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}
