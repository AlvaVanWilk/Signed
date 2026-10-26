/**
 * Instrumente nach Güteklasse gezeichnet (64 × 64, wie icons.js):
 * 0 grau – schlicht, flach, ohne Klimbim;
 * 1 grün – natürliche Hölzer bzw. Metalle, je Instrument fest gelost (Ahorn, Kirsche, Nussbaum …), Chrom;
 * 2 blau – farbig lackiert (Farbton je Instrument), Gold und Perlmutt;
 * 3 gold – ein Einzelstück, dessen Aussehen seine goldene Fähigkeit verrät:
 *   Publikumsmagnet: nachtblau mit goldenen Ranken und warmem Leuchten (zieht alle an),
 *   Showstück: Galaxie mit Strass, kräftigem Leuchten und Funkeln (laut und imposant),
 *   Studio-Legende: elfenbein mit feinen Goldlinien und Plakette (sauber, ruhig),
 *   Kultobjekt: dunkles geschnitztes Holz mit Kerbmustern, Perlen und Feder (ein Glücksbringer),
 *   Hitmaschine: poliertes Gold mit Rillen wie eine goldene Schallplatte.
 * Jede Form liefert ihre „Flächen“ (Korpus, Fell, Gehäuse …); Farben, Muster und Schmuck kommen aus dem Look.
 */
const LINE = 'stroke-linejoin="round" stroke-linecap="round"';
const f = (n) => +n.toFixed(2);
const tilt = (inner, angle, scale) => `<g transform="rotate(${angle} 32 32) translate(32 32) scale(${scale}) translate(-32 -32)">${inner}</g>`;

// Grün: natürliche Hölzer [hell, mittel, dunkel] und Metalle
const WOODS = [
  ['#f6dcaa', '#e0b070', '#a8743a'], // Ahorn
  ['#eba476', '#b85a2e', '#6e2a12'], // Kirsche
  ['#b88a62', '#7a5030', '#3e2618'], // Nussbaum
  ['#d47c54', '#8e3a1e', '#4a1a0c'], // Mahagoni
  ['#f1e4c6', '#cfb68c', '#8e7650'], // Esche
  ['#dccc94', '#a08a50', '#5e4e26'], // Olive
  ['#f8c878', '#d8902e', '#8a5010'], // Honig
];
const ALLOYS = [
  ['#fff1b0', '#e2b84a', '#8a6414'], // Messing
  ['#ffd9c0', '#d08a5a', '#7a3e1c'], // Rotmessing
  ['#ffffff', '#c9ccd4', '#6b6f7a'], // Silber
  ['#e8d8a0', '#a8894a', '#5a4418'], // Altmessing
  ['#f0c890', '#b0743a', '#5e3412'], // Bronze
];

const THEMES = ['magnet', 'show', 'studio', 'cult', 'hit'];

/** Kleiner Funkelstern (vierzackig). */
const sparkle = (x, y, r, o = 1, c = '#fff6c8') => `<path d="M${f(x)} ${f(y - r)} Q${f(x + r * 0.18)} ${f(y - r * 0.18)} ${f(x + r)} ${f(y)} Q${f(x + r * 0.18)} ${f(y + r * 0.18)} ${f(x)} ${f(y + r)} Q${f(x - r * 0.18)} ${f(y + r * 0.18)} ${f(x - r)} ${f(y)} Q${f(x - r * 0.18)} ${f(y - r * 0.18)} ${f(x)} ${f(y - r)} Z" fill="${c}" opacity="${o}"/>`;

/** Pfad in Einheitskoordinaten (0–1) auf ein Rechteck [x, y, b, h] abbilden. */
const mapPath = (bb, d) => d.replace(/(-?\d*\.?\d+)\s+(-?\d*\.?\d+)/g, (_, x, y) => `${f(bb[0] + x * bb[2])} ${f(bb[1] + y * bb[3])}`);
const at = (bb, ux, uy) => [bb[0] + ux * bb[2], bb[1] + uy * bb[3]];
const draw = (shapes, attrs) => shapes.map((s) => `<${s} ${attrs}/>`).join('');
const around = (bb, k) => {
  const cx = f(bb[0] + bb[2] / 2);
  const cy = f(bb[1] + bb[3] / 2);
  return `transform="translate(${cx} ${cy}) scale(${k}) translate(${-cx} ${-cy})"`;
};

/**
 * Der Look eines Instruments: Farben, Muster und Schmuck je Klasse (und bei Gold je Fähigkeit).
 * @param {number} tier 0–3  @param {?number} hue Lackfarbe (blau)  @param {number} variant Holz/Metall (grün)  @param {?string} gold goldene Fähigkeit
 */
export function makeLook(tier, hue, variant = 0, gold = null) {
  const defs = [];
  let n = 0;
  const id = () => `ga${++n}`;
  const theme = tier === 3 ? (THEMES.includes(gold) ? gold : 'show') : null;
  const h = hue ?? 210;
  const wood = WOODS[Math.abs(variant) % WOODS.length];
  const alloy = ALLOYS[Math.abs(variant) % ALLOYS.length];
  const grad = (stops, radial = true, at = 'cx="45%" cy="55%" r="65%"') => {
    const g = id();
    const s = stops.map(([o, c]) => `<stop offset="${o}" stop-color="${c}"/>`).join('');
    defs.push(radial ? `<radialGradient id="${g}" ${at}>${s}</radialGradient>` : `<linearGradient id="${g}" x1="0" x2="1" y1="0" y2="1">${s}</linearGradient>`);
    return `url(#${g})`;
  };
  const tri = ([a, b, c], radial = true) => grad([[0, a], [0.6, b], [1, c]], radial);
  const blur = (() => {
    let g = null;
    return () => {
      if (!g) {
        g = id();
        defs.push(`<filter id="${g}" x="-40%" y="-40%" width="180%" height="180%"><feGaussianBlur stdDeviation="1.4"/></filter>`);
      }
      return `url(#${g})`;
    };
  })();

  const L = { tier, theme, hue: h, defs };

  /** Füllung einer Fläche. mat: wood | paint | metal | brass | head (Fell, Front). */
  L.fill = (mat = 'wood') => {
    if (tier === 0) {
      return {
        wood: grad([[0, '#c49a6c'], [1, '#b0865a']], false), paint: grad([[0, '#4a4f5c'], [1, '#3e434e']], false),
        metal: grad([[0, '#c3c6cc'], [1, '#aeb1b8']], false), brass: grad([[0, '#cfb06a'], [1, '#bb9a54']], false), head: '#ece6da',
      }[mat];
    }
    if (tier === 1) {
      if (mat === 'head') return grad([[0, '#ffffff'], [1, '#e2dccd']]);
      if (mat === 'metal' || mat === 'brass') return tri(alloy);
      return tri(wood);
    }
    if (tier === 2) {
      if (mat === 'head') return grad([[0, '#ffffff'], [0.7, '#ece6da'], [1, `hsl(${h} 40% 70%)`]]);
      return grad([[0, `hsl(${h + 20} 88% 72%)`], [0.5, `hsl(${h} 78% 50%)`], [0.85, `hsl(${h - 8} 72% 26%)`], [1, `hsl(${h - 12} 72% 13%)`]]);
    }
    return {
      magnet: () => grad([[0, '#2e2a5c'], [0.7, '#15132e'], [1, '#07060f']]),
      show: () => grad([[0, '#36207a'], [0.6, '#160c36'], [1, '#05030f']]),
      studio: () => grad([[0, '#fffdf7'], [0.7, '#f1ead8'], [1, '#d9ceb2']]),
      cult: () => grad([[0, '#7a5030'], [0.6, '#4a2c18'], [1, '#22140a']]),
      hit: () => grad([[0, '#fff6c0'], [0.35, '#f0c85a'], [0.75, '#c08a22'], [1, '#7a5410']]),
    }[theme]();
  };

  /** Umrisslinie einer Fläche. */
  L.line = (mat = 'wood') => {
    if (tier === 0) return mat === 'paint' ? '#1c1e24' : mat === 'head' ? '#a8a296' : '#4a3420';
    if (tier === 1) return mat === 'head' ? '#a8a296' : mat === 'metal' || mat === 'brass' ? alloy[2] : wood[2];
    if (tier === 2) return mat === 'head' ? '#8e8a80' : `hsl(${h} 70% 12%)`;
    return { magnet: '#e8c35a', show: '#cdb8ff', studio: '#b49a5a', cult: '#140a04', hit: '#7a5410' }[theme];
  };

  /** Beschläge (Mechaniken, Spannböcke, Klappen …). */
  L.hw = tier === 0 ? { fill: '#2a2830', line: '#111', shine: '#55525c' }
    : tier === 1 ? { fill: '#d9dbe2', line: '#6b6f7a', shine: '#ffffff' }
      : tier === 2 ? { fill: '#e8c35a', line: '#8a6418', shine: '#fff3b8' }
        : {
          magnet: { fill: '#e8c35a', line: '#8a6418', shine: '#f4eefc' },
          show: { fill: '#f4f6fb', line: '#8a90a0', shine: '#ffffff' },
          studio: { fill: '#d8b45a', line: '#7a5a18', shine: '#fff3c8' },
          cult: { fill: '#efe6d2', line: '#7a6a4a', shine: '#ffffff' },
          hit: { fill: '#1c1a20', line: '#000000', shine: '#5a5660' },
        }[theme];

  /** Dunkle Nebenteile (Kopfplatte, Gehäuserand) passend zum Look. */
  L.dark = tier === 0 ? '#3a2c20' : tier === 1 ? wood[2] : tier === 2 ? `hsl(${h - 10} 60% 18%)`
    : { magnet: '#14122c', show: '#120a2c', studio: '#efe7d4', cult: '#24140a', hit: '#c99a30' }[theme];

  /** Glanzlicht (ab grün). */
  L.sheen = (d, w = 1, o = 0.3) => (tier === 0 ? '' : `<path d="${d}" fill="none" stroke="#fff" stroke-width="${w}" opacity="${theme === 'cult' ? o * 0.4 : o}" ${LINE}/>`);

  /** Leuchten hinter der Fläche (Gold: Publikumsmagnet warm, Showstück kräftig, Hitmaschine leise). */
  L.glow = (shapes) => {
    const c = { magnet: ['#f2cf6a', 0.4], show: ['#b56bff', 0.55], hit: ['#f2cf6a', 0.3] }[theme];
    return c ? draw(shapes, `fill="none" stroke="${c[0]}" stroke-width="3" opacity="${c[1]}" filter="${blur()}"`) : '';
  };

  /**
   * Muster auf der Fläche (geclippt) und Einfassung. bb = [x, y, b, h] der Fläche.
   * o.grain: 'v' | 'h' Maserung; o.center: [ux, uy] Mitte der Rillen; o.binding: false ohne Einfassung; o.k Einfassung nach innen.
   */
  L.decor = (shapes, bb, mat = 'wood', o = {}) => {
    const clip = id();
    defs.push(`<clipPath id="${clip}">${draw(shapes, '')}</clipPath>`);
    const s = Math.min(bb[2], bb[3]);
    const inner = [];
    const isWood = mat === 'wood' || (tier === 1 && mat === 'paint');
    if ((tier === 1 && isWood) || (tier === 2 && mat === 'wood')) inner.push(grain(bb, o.grain ?? 'v', tier === 1 ? wood[2] : '#000', tier === 1 ? 0.3 : 0.16));
    if (tier === 2 && mat === 'paint') inner.push(flame(bb));
    if (tier === 2 && (mat === 'metal' || mat === 'brass')) inner.push(swirl(bb, '#fff3b8', 0.5));
    if (theme === 'magnet') inner.push(vines(bb));
    if (theme === 'show') inner.push(galaxy(bb, blur()));
    if (theme === 'cult') inner.push(carvings(bb));
    if (theme === 'hit') inner.push(grooves(bb, o.center ?? [0.5, 0.5], o.label !== false));
    let out = inner.length ? `<g clip-path="url(#${clip})">${inner.join('')}</g>` : '';
    if (o.binding === false || tier === 0) return out;
    const k = o.k ?? 0.94;
    const ring = (kk, stroke, w, extra = '') => draw(shapes, `fill="none" stroke="${stroke}" stroke-width="${w}" ${around(bb, kk)} ${extra}`);
    if (tier === 1) out += ring(k, '#f4e6c6', 0.5, 'opacity="0.85"');
    else if (tier === 2) out += ring(k, '#f6efe0', 0.6);
    else if (theme === 'magnet') out += ring(k, '#e8c35a', 0.8);
    else if (theme === 'show') out += ring(k, '#ffffff', 1.1, `stroke-dasharray="0.01 1.5" ${LINE}`) + ring(k, '#bfe8ff', 0.8, `stroke-dasharray="0.01 1.5" stroke-dashoffset="0.75" ${LINE}`);
    else if (theme === 'studio') out += ring(k, '#c9a24a', 0.45) + ring(k - 0.05, '#c9a24a', 0.22);
    else if (theme === 'cult') out += ring(k, '#efe6d2', 0.55, 'stroke-dasharray="1.2 0.8"');
    return out;
  };

  /**
   * Schmuck außen: Funkeln (Magnet, Show, Hit), Plakette (Studio), Anhänger mit Perlen und Feder (Kult).
   * o.spark: Liste [ux, uy] im bb; o.anchor: [x, y] für den Anhänger; o.badge: [x, y] für die Plakette.
   */
  L.extras = (bb, o = {}) => {
    if (tier !== 3) return '';
    const s = Math.min(bb[2], bb[3]);
    const spots = o.spark ?? [[0.92, 0.08], [0.06, 0.82], [0.7, 0.98]];
    if (theme === 'magnet') return spots.slice(0, 2).map(([x, y], i) => sparkle(...at(bb, x, y), Math.max(0.9, s * (i ? 0.045 : 0.065)), i ? 0.85 : 1)).join('');
    if (theme === 'show') return spots.map(([x, y], i) => sparkle(...at(bb, x, y), Math.max(1, s * [0.08, 0.055, 0.045][i % 3]), 1, i === 1 ? '#e6f4ff' : '#fff6c8')).join('') + sparkle(...at(bb, 0.2, 0.05), Math.max(0.7, s * 0.035), 0.8, '#ffd8f4');
    if (theme === 'hit') return sparkle(...at(bb, spots[0][0], spots[0][1]), Math.max(0.9, s * 0.055), 0.95) + note(...at(bb, spots[1][0], spots[1][1]), Math.max(1.4, s * 0.09));
    if (theme === 'studio' && o.badge) return badge(...o.badge);
    if (theme === 'cult' && o.anchor) return charm(...o.anchor, o.charmScale ?? 1.4);
    return '';
  };

  return L;
}

// ---------- Muster ----------

function grain(bb, dir, color, opacity) {
  const lines = [];
  const count = 9;
  for (let i = 0; i < count; i++) {
    const t = (i + 0.5) / count;
    const wob = ((i * 37) % 7) / 7 - 0.5;
    lines.push(dir === 'v'
      ? `M ${t} 0 Q ${f(t + wob * 0.06)} .5 ${f(t - wob * 0.03)} 1`
      : `M 0 ${t} Q .5 ${f(t + wob * 0.06)} 1 ${f(t - wob * 0.03)}`);
  }
  return `<path d="${mapPath(bb, lines.join(' '))}" fill="none" stroke="${color}" stroke-width="0.25" opacity="${opacity}"/>`;
}

/** Geflammtes Ahorn unter farbigem Lack: weiche, unregelmäßige Bänder. */
function flame(bb) {
  return [[0.1, 0.05, 0.9, 0.22], [0.22, 0.08, 1.5, 0.16], [0.33, 0.04, 0.7, 0.2], [0.45, 0.09, 1.8, 0.14], [0.56, 0.05, 1, 0.2], [0.67, 0.08, 1.4, 0.15], [0.78, 0.045, 0.8, 0.2], [0.9, 0.07, 1.6, 0.14]]
    .map(([y, a, w, o], i) => `<path d="${mapPath(bb, `M -.05 ${y} C .2 ${f(y - a)} .3 ${f(y + a * 0.6)} .5 ${f(y - a * 0.2)} S .8 ${f(y + a)} 1.05 ${f(y - a * 0.4)}`)}" fill="none" stroke="${i % 2 ? '#000' : '#fff'}" stroke-width="${w}" stroke-linecap="round" opacity="${o}"/>`).join('');
}

/** Feine Gravur (blau, Metall). */
function swirl(bb, color, opacity) {
  return `<path d="${mapPath(bb, 'M .3 .62 C .2 .52 .3 .4 .42 .46 C .52 .52 .46 .64 .38 .6 M .58 .74 C .7 .66 .82 .74 .76 .84 C .7 .92 .6 .86 .64 .8')}" fill="none" stroke="${color}" stroke-width="0.35" opacity="${opacity}" ${LINE}/>`;
}

/** Publikumsmagnet: goldene Ranken mit Blättern, links und gespiegelt rechts. */
function vines(bb) {
  const s = Math.min(bb[2], bb[3]);
  const leaf = Math.max(0.6, s * 0.062);
  const side = (flip) => {
    const X = (x) => (flip ? 1 - x : x);
    const d = `M ${X(0.18)} 0.96 C ${X(0.04)} 0.8 ${X(0.06)} 0.62 ${X(0.2)} 0.56 C ${X(0.32)} 0.5 ${X(0.34)} 0.38 ${X(0.22)} 0.34 M ${X(0.2)} 0.56 C ${X(0.28)} 0.62 ${X(0.34)} 0.66 ${X(0.4)} 0.66 M ${X(0.12)} 0.8 C ${X(0.22)} 0.86 ${X(0.32)} 0.88 ${X(0.4)} 0.84`;
    const leaves = [[0.22, 0.34, -40], [0.06, 0.68, -70], [0.4, 0.84, 15], [0.4, 0.66, 25], [0.16, 0.46, -20], [0.27, 0.9, 30]]
      .map(([x, y, r]) => {
        const [px, py] = at(bb, X(x), y);
        return `<ellipse cx="${f(px)}" cy="${f(py)}" rx="${f(leaf * 1.2)}" ry="${f(leaf * 0.5)}" fill="#f2cf6a" transform="rotate(${flip ? -r : r} ${f(px)} ${f(py)})"/>`;
      }).join('');
    const [bx, by] = at(bb, X(0.22), 0.34);
    return `<path d="${mapPath(bb, d)}" fill="none" stroke="#e8c35a" stroke-width="${f(Math.max(0.45, s * 0.03))}" ${LINE}/>${leaves}<circle cx="${f(bx)}" cy="${f(by - leaf * 1.2)}" r="${f(leaf * 0.45)}" fill="#fff3b8"/>`;
  };
  return side(false) + side(true);
}

/** Showstück: Galaxie mit Nebeln und Sternen. */
function galaxy(bb, blur) {
  const neb = [[0.32, 0.62, 0.36, 0.2, '#ff4fa3', 0.38], [0.66, 0.8, 0.32, 0.16, '#4fd8ff', 0.34], [0.56, 0.36, 0.26, 0.13, '#b56bff', 0.45]]
    .map(([x, y, rx, ry, c, o]) => {
      const [px, py] = at(bb, x, y);
      return `<ellipse cx="${f(px)}" cy="${f(py)}" rx="${f(rx * bb[2])}" ry="${f(ry * bb[3])}" fill="${c}" opacity="${o}" filter="${blur}"/>`;
    }).join('');
  const s = Math.min(bb[2], bb[3]);
  const stars = [[0.2, 0.42], [0.3, 0.8], [0.45, 0.2], [0.78, 0.4], [0.84, 0.62], [0.6, 0.92], [0.28, 0.6], [0.7, 0.14], [0.52, 0.56], [0.4, 0.4], [0.9, 0.86], [0.12, 0.2]]
    .map(([x, y], i) => {
      const [px, py] = at(bb, x, y);
      return `<circle cx="${f(px)}" cy="${f(py)}" r="${f(Math.max(0.18, s * (i % 3 ? 0.012 : 0.02)))}" fill="#fff"/>`;
    }).join('');
  return neb + stars;
}

/** Kultobjekt: geschnitzte Kerbbänder mit Türkis- und Ocker-Einlagen. */
function carvings(bb) {
  const s = Math.min(bb[2], bb[3]);
  const bands = [0.26, 0.52, 0.78].map((y, b) => {
    const teeth = 9;
    const a = 0.035;
    let d = `M 0 ${y}`;
    for (let i = 0; i < teeth; i++) d += ` L ${f((i + 0.5) / teeth)} ${f(y + (i % 2 ? -a : a) * (bb[2] / bb[3] > 2 ? 3 : 1))} L ${f((i + 1) / teeth)} ${y}`;
    const dots = Array.from({ length: 7 }, (_, i) => {
      const [px, py] = at(bb, (i + 0.5) / 7, y + 0.075);
      return `<circle cx="${f(px)}" cy="${f(py)}" r="${f(Math.max(0.3, s * 0.022))}" fill="${(i + b) % 2 ? '#3fb7a8' : '#c0562c'}"/>`;
    }).join('');
    return `<path d="${mapPath(bb, d)}" fill="none" stroke="#e0b070" stroke-width="${f(Math.max(0.35, s * 0.02))}" opacity="0.85" ${LINE}/><path d="${mapPath(bb, d)}" fill="none" stroke="#000" stroke-width="${f(Math.max(0.2, s * 0.01))}" opacity="0.45" transform="translate(0 ${f(Math.max(0.35, s * 0.02))})"/>${dots}`;
  }).join('');
  return bands;
}

/** Hitmaschine: Rillen wie auf einer goldenen Schallplatte, mit rotem Etikett. */
function grooves(bb, [ux, uy], label) {
  const [cx, cy] = at(bb, ux, uy);
  const s = Math.min(bb[2], bb[3]);
  const max = Math.max(bb[2], bb[3]);
  const rings = [];
  for (let r = s * 0.2, i = 0; r < max; r += Math.max(0.7, s * 0.06), i++) {
    rings.push(`<circle cx="${f(cx)}" cy="${f(cy)}" r="${f(r)}" fill="none" stroke="${i % 2 ? '#fff6c8' : '#7a5410'}" stroke-width="0.22" opacity="${i % 2 ? 0.5 : 0.4}"/>`);
  }
  const lab = label ? `<circle cx="${f(cx)}" cy="${f(cy)}" r="${f(s * 0.15)}" fill="#c8202a"/><circle cx="${f(cx)}" cy="${f(cy)}" r="${f(s * 0.15)}" fill="none" stroke="#fff3b8" stroke-width="0.3"/><circle cx="${f(cx)}" cy="${f(cy)}" r="${f(Math.max(0.3, s * 0.025))}" fill="#fff3b8"/>` : '';
  return rings.join('') + lab;
}

/** Kleine Note (Hitmaschine). */
function note(x, y, size) {
  const r = size * 0.32;
  return `<g fill="#fff3b8" stroke="#fff3b8"><ellipse cx="${f(x)}" cy="${f(y)}" rx="${f(r * 1.2)}" ry="${f(r * 0.9)}" transform="rotate(-20 ${f(x)} ${f(y)})" stroke="none"/><path d="M${f(x + r)} ${f(y)} V${f(y - size)} Q${f(x + r + size * 0.5)} ${f(y - size * 0.7)} ${f(x + r + size * 0.4)} ${f(y - size * 0.35)}" fill="none" stroke-width="${f(Math.max(0.3, size * 0.12))}" stroke-linecap="round"/></g>`;
}

/** Studio-Legende: kleine Messingplakette. */
function badge(x, y) {
  return `<rect x="${f(x - 2.4)}" y="${f(y - 0.9)}" width="4.8" height="1.8" rx="0.5" fill="#d8b45a" stroke="#7a5a18" stroke-width="0.25"/><path d="M${f(x - 1.6)} ${f(y)} H${f(x + 1.6)}" stroke="#7a5a18" stroke-width="0.3"/><circle cx="${f(x - 2)}" cy="${f(y)}" r="0.18" fill="#7a5a18"/><circle cx="${f(x + 2)}" cy="${f(y)}" r="0.18" fill="#7a5a18"/>`;
}

/** Kultobjekt: Glücksbringer an einer Schnur – Perlen und eine Feder. */
function charm(x, y, k = 1) {
  const Y = (v) => f(y + v * k);
  const X = (v) => f(x + v * k);
  return `<g>
    <path d="M${f(x)} ${f(y)} Q${X(0.7)} ${Y(2.4)} ${X(0.3)} ${Y(5)}" fill="none" stroke="#6a4a2a" stroke-width="${f(0.32 * k)}"/>
    <circle cx="${X(0.42)}" cy="${Y(1.5)}" r="${f(0.7 * k)}" fill="#3fb7a8" stroke="#1e6a60" stroke-width="${f(0.15 * k)}"/>
    <circle cx="${X(0.55)}" cy="${Y(2.8)}" r="${f(0.55 * k)}" fill="#efe6d2" stroke="#8a7a5a" stroke-width="${f(0.15 * k)}"/>
    <circle cx="${X(0.46)}" cy="${Y(3.95)}" r="${f(0.6 * k)}" fill="#c0562c" stroke="#6a2a10" stroke-width="${f(0.15 * k)}"/>
    <path d="M${X(0.3)} ${Y(5)} C${X(1.9)} ${Y(6)} ${X(1.7)} ${Y(8.6)} ${X(0.4)} ${Y(9.8)} C${X(-0.9)} ${Y(8.6)} ${X(-1.1)} ${Y(6)} ${X(0.3)} ${Y(5)} Z" fill="#f4efe6" stroke="#8a7a5a" stroke-width="${f(0.18 * k)}"/>
    <path d="M${X(0.8)} ${Y(8.2)} C${X(0.4)} ${Y(8.6)} ${X(0)} ${Y(8.6)} ${X(-0.4)} ${Y(8.2)}" fill="none" stroke="#c0562c" stroke-width="${f(0.5 * k)}"/>
    <path d="M${X(0.3)} ${Y(5)} L${X(0.4)} ${Y(9.6)}" stroke="#6a4a2a" stroke-width="${f(0.2 * k)}"/>
  </g>`;
}

// ---------- Teile, die viele Instrumente teilen ----------

/** Bünde und Saiten eines Griffbretts (aufrecht, x = Mitte). Grau ohne Bundpunkte. */
function fretboard(L, x, top, bottom, width, strings) {
  const frets = [];
  for (let y = top + 3, step = 3.2; y < bottom - 1; y += step, step *= 0.97) frets.push(y);
  const half = width / 2 - 0.5;
  const inlay = L.tier === 0 ? ''
    : L.tier === 1 ? [2, 4, 6].map((i) => (frets[i] ? `<circle cx="${x}" cy="${f(frets[i] + 1.4)}" r="0.5" fill="#e9e2d0"/>` : '')).join('')
      : L.theme === 'magnet' || L.theme === 'cult'
        ? `<path d="M${x} ${top + 1} C${x - 1.4} ${top + 5} ${x + 1.4} ${top + 9} ${x} ${top + 13} C${x - 1.4} ${top + 16} ${x + 1.4} ${f((bottom + top) / 2 + 4)} ${x} ${bottom - 1}" fill="none" stroke="${L.theme === 'cult' ? '#3fb7a8' : '#f2cf6a'}" stroke-width="0.5"/>`
        : L.theme === 'studio' ? ''
          : [2, 4, 6, 8].map((i) => (frets[i] ? `<rect x="${x - 1.4}" y="${f(frets[i] + 0.9)}" width="2.8" height="1.1" fill="${L.theme === 'hit' ? '#f2cf6a' : L.theme === 'show' ? '#e6f4ff' : '#eaf2f6'}" opacity="0.9"/>` : '')).join('');
  return `
    ${frets.map((y) => `<path d="M${f(x - half)} ${f(y)} H${f(x + half)}" stroke="#c9c2b4" stroke-width="0.35"/>`).join('')}
    ${inlay}
    ${Array.from({ length: strings }, (_, i) => {
    const sx = x - half + 0.4 + (i * (width - 1.8)) / (strings - 1);
    return `<path d="M${f(sx)} ${top} V${bottom}" stroke="#eef0f4" stroke-width="${strings === 4 ? 0.45 : 0.28}"/>`;
  }).join('')}`;
}

/** Mechaniken am Kopf. */
const pegs = (L, pts, r = 1) => pts.map(([x, y]) => `<circle cx="${x}" cy="${y}" r="${r}" fill="${L.hw.fill}" stroke="${L.hw.line}" stroke-width="0.3"/>${L.theme === 'magnet' || L.theme === 'show' ? `<circle cx="${x}" cy="${y}" r="${f(r * 0.45)}" fill="${L.hw.shine}"/>` : ''}`).join('');

/** Griffbrett-Holz (dunkel; Studio hell wie Ahorn). */
const boardFill = (L) => (L.tier === 0 ? '#4a3424' : L.theme === 'studio' ? '#e8d2a6' : L.theme === 'show' ? '#120a24' : L.theme === 'hit' ? '#2a1a0c' : '#3a2416');

// ---------- Die Formen ----------

const AG_BODY = 'M32 21 C25.4 21 23 25 24 29.4 C24.6 31.8 25.6 33.2 24.4 35.2 C20.2 38 18.6 42 19.6 47 C21.4 55 26.8 59 32 59 C37.2 59 42.6 55 44.4 47 C45.4 42 43.8 38 39.6 35.2 C38.4 33.2 39.4 31.8 40 29.4 C41 25 38.6 21 32 21 Z';
const EG_BODY = 'M25 30.2 C22.6 25.4 17.2 26 18.2 31.4 C18.8 34.6 20 36 18 39.4 C14.6 45 16 53.6 23.2 56.4 C28.6 58.4 35.6 58.4 40.6 56.4 C47.6 53.6 48.6 45.2 45.6 40.2 C43.8 37.4 44.4 34.8 46 31.8 C48.2 27.4 44.4 24.6 41.6 27.8 C39.4 30.4 36.6 31.6 32 31.6 C28.8 31.6 26.6 31.2 25 30.2 Z';
const BS_BODY = 'M25.4 33.2 C23.6 29.4 19 29.8 19.8 34.4 C20.4 37.4 21.2 38.8 19.4 41.8 C16.4 46.8 17.6 54.6 24 57 C28.8 58.8 35.2 58.8 39.6 57 C45.8 54.6 46.8 47.2 44 42.6 C42.4 40 43 37.8 44.4 35.2 C46.4 31.4 43 29 40.6 31.8 C38.6 34 36.2 35 32 35 C29 35 27 34.4 25.4 33.2 Z';
const VL_BODY = 'M32 17.4 C26.2 17.4 23.2 19.6 23.2 23.8 C23.2 26.6 25.4 27.8 24.8 29.6 L23.6 30.8 C25.4 32.6 25.4 33.8 23.6 35.6 C21.2 38.2 20.4 41.8 21.6 45.4 C23.4 51.4 28.2 53.6 32 53.6 C35.8 53.6 40.6 51.4 42.4 45.4 C43.6 41.8 42.8 38.2 40.4 35.6 C38.6 33.8 38.6 32.6 40.4 30.8 L39.2 29.6 C38.6 27.8 40.8 26.6 40.8 23.8 C40.8 19.6 37.8 17.4 32 17.4 Z';
const SX_BODY = 'M25 8.6 L30.4 8.6 L32.6 42 C33 47.6 36.2 50.4 39.8 49.4 C42.2 48.6 42.8 46 42.2 42.4 L41.4 37.4 L51.4 28.6 L54.6 41.6 L49.8 43 C51 52.6 46.8 59.8 39.2 60.4 C30.8 61 25.8 55 26 46.8 Z';
const strings = (n, x0, dx, top, bottom, w) => Array.from({ length: n }, (_, i) => `<path d="M${f(x0 + i * dx)} ${top} V${bottom}" stroke="#eef0f4" stroke-width="${w}"/>`).join('');

const ART = {
  guitar(L) {
    const body = [`path d="${AG_BODY}"`];
    const bb = [19.4, 21, 25.2, 38];
    const guard = L.tier === 1 ? '#5a2c14' : L.tier === 2 ? '#14100c' : L.theme === 'studio' ? '#d8c49a' : L.theme === 'cult' ? '#c0562c' : null;
    const rosette = L.tier === 0 ? '' : L.tier === 1
      ? `<circle cx="32" cy="36.2" r="5.6" fill="none" stroke="${L.line('wood')}" stroke-width="0.5"/><circle cx="32" cy="36.2" r="6.4" fill="none" stroke="${L.line('wood')}" stroke-width="0.25"/>`
      : L.tier === 2 ? '<circle cx="32" cy="36.2" r="6" fill="none" stroke="#f6efe0" stroke-width="1.2"/>'
        : `<circle cx="32" cy="36.2" r="6.2" fill="none" stroke="${{ magnet: '#e8c35a', show: '#ffffff', studio: '#c9a24a', cult: '#3fb7a8', hit: '#1c1a20' }[L.theme]}" stroke-width="${L.theme === 'studio' ? 0.6 : 1.3}" ${L.theme === 'show' ? `stroke-dasharray="0.01 1.3" ${LINE}` : ''}/>`;
    return tilt(`
      ${L.glow(body)}
      <path d="M29.4 1 H34.6 L35.6 9.4 H28.4 Z" fill="${L.dark}" stroke="${L.tier === 3 ? L.line() : 'none'}" stroke-width="0.4"/>
      ${pegs(L, [[27.6, 3], [27.4, 5.4], [27.2, 7.8], [36.4, 3], [36.6, 5.4], [36.8, 7.8]])}
      <rect x="29.9" y="9.2" width="4.2" height="21" fill="${boardFill(L)}"/>
      ${fretboard(L, 32, 9.6, 30, 4.2, 6)}
      ${draw(body, `fill="${L.fill('wood')}" stroke="${L.line('wood')}" stroke-width="0.9"`)}
      ${L.decor(body, bb, 'wood', { center: [0.5, 0.4], label: false })}
      ${guard ? `<path d="M36.4 33.6 C40 34.4 41.2 37.8 39.4 40.4 C37.6 42.6 35.2 41.4 35 39 Z" fill="${guard}" opacity="0.9"/>` : ''}
      <circle cx="32" cy="36.2" r="4.6" fill="#2a1e14" stroke="${L.tier === 0 ? '#2a1e14' : '#7a4a1c'}" stroke-width="0.8"/>
      <circle cx="32" cy="36.2" r="3.4" fill="#120c08"/>
      ${rosette}
      <rect x="27.4" y="47" width="9.2" height="2.6" rx="1.2" fill="${L.tier === 3 && L.theme !== 'hit' ? L.hw.fill : '#3a2416'}"/>
      <path d="M28.6 47.8 H35.4" stroke="#efe6d2" stroke-width="0.6"/>
      ${strings(6, 30.3, 0.68, 30, 48, 0.28)}
      ${L.sheen('M25.6 44 C26.6 50 29.4 53.4 32 54')}
      ${L.extras(bb, { anchor: [32, 58.6], charmScale: 1.4, badge: [32, 55.6], spark: [[0.95, 0.15], [0.08, 0.85], [0.7, 1]] })}`, 40, 0.8);
  },

  eguitar(L) {
    const body = [`path d="${EG_BODY}"`];
    const bb = [15.4, 25.8, 33.2, 32.6];
    const metal = L.tier === 0 ? '#3a3a40' : L.hw.fill;
    const guard = L.tier <= 1 ? (L.tier === 0 ? '#ecebe6' : '#fbfaf6') : L.theme === 'studio' ? '#1c1a20' : null;
    const pickups = (L.tier === 0 ? [41.6] : [37.4, 46.2]).map((y) => `<rect x="28.4" y="${y}" width="7.2" height="${L.tier >= 2 ? 2.4 : 1.9}" rx="0.9" fill="${L.tier >= 2 ? metal : L.tier === 0 ? '#1a1a1e' : '#fbfaf6'}" stroke="${L.tier >= 2 ? L.hw.line : '#8e8a80'}" stroke-width="0.4"/>`).join('');
    return tilt(`
      <g transform="translate(0 -5)">
      <path d="M29.6 0.6 C31.4 0.2 33.6 0.6 34.6 1.8 L35.4 10.6 L30.6 10.6 C29.4 7.6 28.6 4 29.6 0.6 Z" fill="${L.tier === 0 ? '#d4b486' : L.tier === 3 ? L.dark : '#e8c896'}" stroke="${L.tier === 3 ? L.line() : '#9a7444'}" stroke-width="0.5"/>
      ${pegs(L, [1.8, 3.4, 5, 6.6, 8.2, 9.8].map((y) => [f(29 + (y - 1.8) * 0.06), y]), 0.75)}
      <rect x="30.1" y="10.4" width="3.8" height="27" fill="${L.tier === 0 ? '#d4b486' : L.tier === 3 ? boardFill(L) : '#f0d6a6'}" stroke="#9a7444" stroke-width="0.4"/>
      ${fretboard(L, 32, 10.6, 36, 3.8, 6)}
      </g>
      <g transform="translate(32 44) scale(0.8 0.9) translate(-32 -44)">
      ${L.glow(body)}
      ${draw(body, `fill="${L.fill('paint')}" stroke="${L.line('paint')}" stroke-width="0.9"`)}
      ${L.decor(body, bb, 'paint', { k: 0.955, center: [0.5, 0.62] })}
      ${guard ? `<path d="M24.4 33.6 C26.8 34.6 29.6 34.8 33 34.8 L35.8 34.6 L37 44 L36.4 50.4 C32.6 53.4 25.6 53 22.6 49.4 C20.6 46.8 21.4 42 23 39 C24 37.2 24.6 35.4 24.4 33.6 Z" fill="${guard}" stroke="#a9a59a" stroke-width="0.5"/>` : ''}
      ${pickups}
      <rect x="28.2" y="49.6" width="7.6" height="2.4" rx="0.6" fill="${metal}" stroke="${L.hw.line}" stroke-width="0.4"/>
      <g fill="${L.tier >= 2 ? metal : '#fbfaf6'}" stroke="${L.tier >= 2 ? L.hw.line : '#8e8a80'}" stroke-width="0.4"><circle cx="38.8" cy="47.6" r="1.3"/><circle cx="38" cy="51" r="1.3"/>${L.tier >= 1 ? '<circle cx="36.4" cy="54" r="1.3"/>' : ''}</g>
      ${strings(6, 30.4, 0.64, 32, 50, 0.28)}
      ${L.sheen('M42.6 33.6 C44 36 43.6 38.6 42.2 40.6', 1, 0.35)}
      ${L.extras(bb, { anchor: [44.4, 29.4], badge: [41.2, 52.8], spark: [[0.92, 0.12], [0.08, 0.78], [0.62, 1]] })}
      </g>`, 40, 0.8);
  },

  bass(L) {
    const body = [`path d="${BS_BODY}"`];
    const bb = [17.6, 29.8, 28.8, 29];
    const guard = L.tier === 0 ? '#1c1c20' : L.tier === 1 ? '#3a1a0c' : L.tier === 2 ? '#f6efe0' : L.theme === 'studio' ? '#1c1a20' : null;
    return tilt(`
      <path d="M29.6 -5 C31.6 -5.6 34.2 -5 35 -3.6 L35.4 4 H30.6 C29.6 1 29 -2 29.6 -5 Z" fill="${L.tier === 3 ? L.dark : L.tier === 0 ? '#c8aa7c' : '#e2c08a'}" stroke="${L.tier === 3 ? L.line() : '#9a7444'}" stroke-width="0.5"/>
      ${pegs(L, [-3.8, -1.2, 1.4, 4].map((y, i) => [f(28.4 + i * 0.2), y]), 1.25)}
      <rect x="30.2" y="3.8" width="3.6" height="30" fill="${boardFill(L)}"/>
      ${fretboard(L, 32, 4, 34, 3.6, 4)}
      ${L.glow(body)}
      ${draw(body, `fill="${L.fill('paint')}" stroke="${L.line('paint')}" stroke-width="0.9"`)}
      ${L.decor(body, bb, 'paint', { k: 0.95, center: [0.5, 0.64] })}
      ${guard ? `<path d="M25.2 36.6 C27.4 37.4 29.6 37.6 32.4 37.6 L35 51 C31.8 53.4 26.6 53 24 50 C22 47.6 22.8 43.4 24.2 40.8 C25 39.4 25.4 38 25.2 36.6 Z" fill="${guard}" stroke="#1a0a04" stroke-width="0.4" opacity="0.95"/>` : ''}
      <rect x="28.6" y="41.6" width="4" height="2.4" rx="0.8" fill="#1a1a20" transform="rotate(-6 30.6 42.8)"/>
      <rect x="31.6" y="43.4" width="4" height="2.4" rx="0.8" fill="#1a1a20" transform="rotate(-6 33.6 44.6)"/>
      <rect x="28.4" y="51.2" width="7.2" height="2.6" rx="0.6" fill="${L.tier === 0 ? '#3a3a40' : L.hw.fill}" stroke="${L.hw.line}" stroke-width="0.4"/>
      <g fill="${L.tier === 0 ? '#3a3a40' : L.hw.fill}" stroke="${L.hw.line}" stroke-width="0.35"><circle cx="38.4" cy="49.6" r="1.4"/><circle cx="37" cy="53.2" r="1.4"/></g>
      ${strings(4, 30.7, 0.86, 34, 52, 0.45)}
      ${L.sheen('M41.4 37 C43 39.4 42.8 42 41.4 44', 1, 0.4)}
      ${L.extras(bb, { anchor: [43.6, 33], badge: [40, 55], spark: [[0.92, 0.12], [0.06, 0.8], [0.62, 1]] })}`, 40, 0.74);
  },

  drums(L) {
    // Kessel tragen die Holz-/Lackfarbe; das Frontfell der großen Trommel ist die Leinwand für Muster.
    const shell = L.tier === 3 ? (L.theme === 'studio' ? '#efe7d4' : L.theme === 'hit' ? L.fill('wood') : L.dark) : L.fill('wood');
    const shellLine = L.tier === 3 ? L.line() : L.line('wood');
    const head = L.tier === 3 ? L.fill('wood') : L.fill('head');
    const cym = L.tier === 0 ? '#b89a54' : 'url(#cym)';
    const hoop = L.tier === 0 ? '#8e9098' : L.hw.fill === '#1c1a20' ? '#e8c35a' : L.hw.fill;
    const headShape = ['circle cx="31" cy="42" r="13.2"'];
    const bb = [17.8, 28.8, 26.4, 26.4];
    const logo = L.tier === 1 ? `<circle cx="31" cy="42" r="5.4" fill="none" stroke="${L.line('wood')}" stroke-width="0.6"/><path d="M28 42 H34" stroke="${L.line('wood')}" stroke-width="0.8" ${LINE}/>`
      : L.tier === 2 ? `<circle cx="31" cy="42" r="6.6" fill="hsl(${L.hue} 70% 46%)" opacity="0.85"/><path d="M27.4 42 L29.6 39.6 L31.6 43.4 L34.6 40" fill="none" stroke="#fff3b8" stroke-width="1.1" ${LINE}/>` : '';
    return `
      <defs><radialGradient id="cym" cx="45%" cy="40%" r="60%"><stop offset="0" stop-color="#fff2b0"/><stop offset="0.5" stop-color="#e8b84a"/><stop offset="1" stop-color="#a87a1a"/></radialGradient></defs>
      <g stroke="#55585f" stroke-width="0.9" ${LINE}><path d="M51 15 V58 M47 58 L51 52 L55 58"/><path d="M11 27 V58 M7.4 58 L11 52 L14.6 58"/></g>
      <ellipse cx="51" cy="14" rx="11.6" ry="2.8" fill="${cym}" stroke="#7a5410" stroke-width="0.5" transform="rotate(-8 51 14)"/>
      <ellipse cx="11" cy="24.4" rx="8.4" ry="1.8" fill="${cym}" stroke="#7a5410" stroke-width="0.4"/>
      <ellipse cx="11" cy="26.6" rx="8.4" ry="1.8" fill="${cym}" stroke="#7a5410" stroke-width="0.4"/>
      <path d="M24 17 V24 C24 26.4 38 26.4 38 24 V17" fill="${shell}" stroke="${shellLine}" stroke-width="0.6"/>
      <ellipse cx="31" cy="17" rx="7" ry="2.2" fill="${L.fill('head')}" stroke="#8e8a80" stroke-width="0.5"/>
      ${L.glow(['circle cx="31" cy="42" r="16"'])}
      <circle cx="31" cy="42" r="16" fill="${shell}" stroke="${shellLine}" stroke-width="0.8"/>
      ${draw(headShape, `fill="${head}" stroke="${hoop}" stroke-width="1.6"`)}
      ${L.tier === 3 ? L.decor(headShape, bb, 'wood', { binding: false, center: [0.5, 0.5] }) : logo}
      ${L.tier === 3 && L.theme === 'studio' ? '<circle cx="31" cy="42" r="11.4" fill="none" stroke="#c9a24a" stroke-width="0.45"/><circle cx="31" cy="42" r="10.6" fill="none" stroke="#c9a24a" stroke-width="0.22"/>' : ''}
      ${L.tier === 3 && L.theme === 'show' ? '<circle cx="31" cy="42" r="14.6" fill="none" stroke="#fff" stroke-width="1" stroke-dasharray="0.01 1.5" stroke-linecap="round"/>' : ''}
      <g fill="${hoop}">${Array.from({ length: 8 }, (_, i) => {
    const a = (i / 8) * Math.PI * 2;
    return `<circle cx="${f(31 + Math.cos(a) * 14.6)}" cy="${f(42 + Math.sin(a) * 14.6)}" r="0.9"/>`;
  }).join('')}</g>
      ${L.tier === 0 ? '' : '<path d="M22 35.6 C24.4 32.6 28 31 31.6 31" fill="none" stroke="#fff" stroke-width="1.2" opacity="0.5"/>'}
      <g ${LINE}><path d="M18 5 L28 19" stroke="#8a5a2c" stroke-width="2"/><path d="M18 5 L28 19" stroke="#e6c48a" stroke-width="1.1"/><path d="M41 4 L33 18.6" stroke="#8a5a2c" stroke-width="2"/><path d="M41 4 L33 18.6" stroke="#e6c48a" stroke-width="1.1"/></g>
      ${L.extras([15, 26, 32, 32], { anchor: [44.6, 37], badge: [31, 56.6], spark: [[0.98, 0.12], [0.02, 0.9], [0.8, 1]], charmScale: 1.1 })}`;
  },

  edrums(L) {
    // E-Drums: Gestell, runde Pads, Becken-Pads, Modul; der Rand der Pads und die Kick-Front tragen den Look.
    const rim = L.tier === 3 && L.theme !== 'studio' ? L.dark : L.fill('paint');
    const pad = (cx, cy, r) => `<circle cx="${cx}" cy="${cy}" r="${r}" fill="${rim}" stroke="${L.line('paint')}" stroke-width="0.6"/><circle cx="${cx}" cy="${cy}" r="${f(r - 1.3)}" fill="#1c1c22"/><circle cx="${cx}" cy="${cy}" r="${f(r - 1.3)}" fill="url(#mesh)" opacity="0.6"/>`;
    const kick = ['rect x="23" y="40" width="18" height="16" rx="2"'];
    const bb = [23, 40, 18, 16];
    const led = L.tier === 0 ? '#3ddc97' : L.tier === 1 ? '#ffd25a' : L.tier === 2 ? `hsl(${L.hue} 90% 62%)` : { magnet: '#f2cf6a', show: '#ff4fa3', studio: '#3ddc97', cult: '#3fb7a8', hit: '#ff5a4a' }[L.theme];
    return `
      <defs><pattern id="mesh" width="1.4" height="1.4" patternUnits="userSpaceOnUse"><path d="M0 0 H1.4 M0 0 V1.4" stroke="#3a3a44" stroke-width="0.3"/></pattern></defs>
      <g stroke="${L.tier === 0 ? '#55585f' : '#9a9ea8'}" stroke-width="1.4" ${LINE} fill="none"><path d="M10 58 V20 M54 58 V20 M8 24 H56 M10 58 H16 M54 58 H48"/></g>
      <ellipse cx="13" cy="16" rx="8" ry="1.6" fill="#1c1c22" stroke="#4a4a54" stroke-width="0.5"/>
      <ellipse cx="51" cy="14" rx="9" ry="1.8" fill="#1c1c22" stroke="#4a4a54" stroke-width="0.5" transform="rotate(-6 51 14)"/>
      ${pad(22, 28, 6.4)}${pad(42, 28, 6.4)}${pad(13, 38, 5.6)}
      <rect x="44" y="34" width="12" height="7.4" rx="1.2" fill="${rim}" stroke="${L.line('paint')}" stroke-width="0.5"/>
      <rect x="45.4" y="35.4" width="9.2" height="3" rx="0.6" fill="#0f1a22"/><path d="M46.4 36.9 H51" stroke="${led}" stroke-width="0.8" stroke-linecap="round"/>
      <circle cx="47" cy="40" r="0.6" fill="${led}"/><circle cx="49.4" cy="40" r="0.6" fill="#9a9ea8"/><circle cx="51.8" cy="40" r="0.6" fill="#9a9ea8"/>
      ${L.glow(kick)}
       ${draw(kick, `fill="${L.fill('paint')}" stroke="${L.line('paint')}" stroke-width="0.7"`)}
      ${L.decor(kick, bb, 'paint', { k: 0.86, center: [0.5, 0.5] })}
      <circle cx="32" cy="48" r="4.6" fill="#1c1c22" stroke="${L.tier === 0 ? '#3a3a40' : L.hw.fill}" stroke-width="0.6"/><circle cx="32" cy="48" r="4.6" fill="url(#mesh)" opacity="0.6"/>
      <path d="M17 29 L23 40 M47 29 L41 40" stroke="#2a2a30" stroke-width="0.6"/>
      ${L.tier === 0 ? '' : '<path d="M24.6 41.6 H32" stroke="#fff" stroke-width="0.7" opacity="0.3" stroke-linecap="round"/>'}
      ${L.extras([8, 10, 48, 48], { anchor: [56, 41.4], badge: [32, 54.2], spark: [[0.98, 0.1], [0.04, 0.95], [0.72, 1]] })}`;
  },

  synth(L) {
    const caseShape = ['rect x="2" y="22" width="60" height="26" rx="2.4"'];
    const panel = ['rect x="3.4" y="23.2" width="57.2" height="9.6" rx="1.2"'];
    const bb = [3.4, 23.2, 57.2, 9.6];
    const knob = L.tier === 0 ? ['#9a9aa8', '#9a9aa8', '#9a9aa8'] : L.tier === 1 ? ['#e8e2d4', '#e8e2d4', '#e8e2d4'] : L.tier === 2 ? [`hsl(${L.hue} 80% 62%)`, '#f6efe0', `hsl(${L.hue + 40} 80% 62%)`] : [L.hw.fill, L.hw.fill, L.hw.fill];
    const screen = L.tier === 3 && L.theme === 'show' ? '#ff4fa3' : L.tier === 2 ? `hsl(${L.hue} 90% 62%)` : '#3ddc97';
    return `
      <path d="M2 22 L8 15 H56 L62 22 Z" fill="${L.tier === 3 ? L.dark : L.tier === 0 ? '#3a3d4a' : '#5a5d74'}" stroke="${L.tier === 3 ? L.line() : 'none'}" stroke-width="0.4"/>
      ${L.tier === 1 ? `<path d="M2 22 L8 15 H11 V48 H2 Z M62 22 L56 15 H53 V48 H62 Z" fill="${L.fill('wood')}"/>` : ''}
      ${L.glow(caseShape)}
      ${draw(caseShape, `fill="${L.tier === 1 ? '#2a2b36' : L.fill('paint')}" stroke="${L.line('paint')}" stroke-width="0.6"`)}
      ${L.tier === 1 ? `<rect x="2" y="22" width="5" height="26" rx="1.6" fill="${L.fill('wood')}" stroke="${L.line('wood')}" stroke-width="0.4"/><rect x="57" y="22" width="5" height="26" rx="1.6" fill="${L.fill('wood')}" stroke="${L.line('wood')}" stroke-width="0.4"/>` : ''}
      ${L.tier >= 2 ? L.decor(panel, bb, 'paint', { binding: false, center: [0.5, 0.5], label: false }) : ''}
      <rect x="8" y="25" width="6" height="7" rx="1.2" fill="#2b2d42"/><rect x="9.2" y="27.2" width="3.6" height="2.4" rx="1" fill="${knob[0]}"/>
      <circle cx="18" cy="28.4" r="1.8" fill="${knob[0]}" stroke="#1c1d28" stroke-width="0.3"/><circle cx="23" cy="28.4" r="1.8" fill="${knob[1]}" stroke="#1c1d28" stroke-width="0.3"/><circle cx="28" cy="28.4" r="1.8" fill="${knob[2]}" stroke="#1c1d28" stroke-width="0.3"/>
      <rect x="33" y="25.8" width="19" height="4.6" rx="1" fill="#0f2a1e"/><path d="M35 28 H${L.tier === 0 ? 41 : 46}" stroke="${screen}" stroke-width="1.4" stroke-linecap="round"/>
      <rect x="7" y="33.6" width="50" height="12.4" fill="#111"/>
      ${Array.from({ length: 13 }, (_, i) => `<rect x="${f(7.4 + i * 3.84)}" y="34" width="3.5" height="11.6" rx="0.5" fill="${L.theme === 'studio' ? '#fffaf0' : '#f6f4ee'}"/>`).join('')}
      ${[0, 1, 3, 4, 5, 7, 8, 10, 11].map((i) => `<rect x="${f(10.1 + i * 3.84)}" y="34" width="2.3" height="7" rx="0.4" fill="${L.theme === 'hit' ? '#c99a30' : '#1a1a1e'}"/>`).join('')}
      ${L.sheen('M4 23.6 H60', 0.6, 0.25)}
      ${L.extras([2, 15, 60, 33], { anchor: [60, 46.4], badge: [55.2, 26.8], spark: [[0.98, 0.05], [0.03, 0.96], [0.6, 1]] })}`;
  },

  piano(L) {
    // Klavier von vorn: Gehäuse, große Vorderwand (Leinwand für Muster), Tastatur, Beine.
    const front = ['rect x="12" y="12" width="40" height="22" rx="1.4"'];
    const bb = [12, 12, 40, 22];
    const fill = L.fill('wood');
    const line = L.line('wood');
    const keyWhite = L.theme === 'studio' ? '#fffaf0' : '#f6f4ee';
    return `
      ${L.glow(['rect x="8" y="8" width="48" height="46" rx="2"'])}
      <rect x="8" y="8" width="48" height="30" rx="2" fill="${fill}" stroke="${line}" stroke-width="0.8"/>
      <rect x="6.6" y="6.4" width="50.8" height="3.2" rx="1" fill="${L.tier === 3 ? L.dark : fill}" stroke="${line}" stroke-width="0.6"/>
      ${draw(front, `fill="${fill}" stroke="${line}" stroke-width="0.5"`)}
      ${L.tier === 0 ? '' : draw(front, `fill="#000" opacity="${L.theme === 'studio' ? 0.04 : 0.12}"`)}
      ${L.decor(front, bb, 'wood', { k: 0.9, grain: 'h', center: [0.5, 0.55] })}
      <rect x="6" y="37" width="52" height="4.2" rx="1" fill="${L.tier === 3 ? L.dark : fill}" stroke="${line}" stroke-width="0.6"/>
      <rect x="8" y="41" width="48" height="7" fill="#111"/>
      ${Array.from({ length: 14 }, (_, i) => `<rect x="${f(8.3 + i * 3.42)}" y="41.2" width="3.1" height="6.6" rx="0.4" fill="${keyWhite}"/>`).join('')}
      ${[0, 1, 3, 4, 5, 7, 8, 10, 11, 12].map((i) => `<rect x="${f(10.6 + i * 3.42)}" y="41.2" width="2" height="4" rx="0.3" fill="${L.theme === 'hit' ? '#c99a30' : '#1a1a1e'}"/>`).join('')}
      <rect x="8" y="48" width="48" height="5.6" fill="${fill}" stroke="${line}" stroke-width="0.6"/>
      <rect x="10" y="53.6" width="3" height="5" fill="${L.tier === 3 ? L.dark : fill}" stroke="${line}" stroke-width="0.5"/><rect x="51" y="53.6" width="3" height="5" fill="${L.tier === 3 ? L.dark : fill}" stroke="${line}" stroke-width="0.5"/>
      <g fill="${L.tier === 0 ? '#8e8a80' : L.hw.fill === '#1c1a20' ? '#e8c35a' : L.hw.fill}"><rect x="28" y="55" width="2.4" height="1.2" rx="0.4"/><rect x="31.4" y="55" width="2.4" height="1.2" rx="0.4"/><rect x="34.8" y="55" width="2.4" height="1.2" rx="0.4"/></g>
      ${L.tier === 0 ? '' : `<g fill="${L.hw.fill === '#1c1a20' ? '#e8c35a' : L.hw.fill}"><rect x="18" y="38.4" width="2" height="1.2" rx="0.4"/><rect x="44" y="38.4" width="2" height="1.2" rx="0.4"/></g>`}
      ${L.sheen('M10 10.6 H54', 0.6, 0.3)}
      ${L.extras([6, 6, 52, 52], { anchor: [56.6, 9.4], badge: [32, 38.4 + 0.7], spark: [[0.98, 0.04], [0.02, 0.6], [0.82, 1]] })}`;
  },

  vocals(L) {
    // Handmikro: Korb oben, Griff trägt den Look.
    const handle = ['path d="M26.4 30 L37.6 30 L35 60 C34.8 62 29.2 62 29 60 Z"'];
    const bb = [26.4, 30, 11.2, 31.4];
    const grill = L.tier === 0 ? '#b9bcc2' : L.tier === 1 ? 'url(#ball)' : L.tier === 2 ? 'url(#ballg)' : { magnet: 'url(#ballg)', show: 'url(#ballc)', studio: 'url(#ballg)', cult: 'url(#ballb)', hit: 'url(#ballg)' }[L.theme];
    const mat = L.tier === 1 ? 'metal' : 'paint';
    return `
      <defs>
        <radialGradient id="ball" cx="38%" cy="32%" r="70%"><stop offset="0" stop-color="#ffffff"/><stop offset="0.35" stop-color="#cfd2da"/><stop offset="1" stop-color="#6b6f7a"/></radialGradient>
        <radialGradient id="ballg" cx="38%" cy="32%" r="70%"><stop offset="0" stop-color="#fff6c8"/><stop offset="0.4" stop-color="#e8c35a"/><stop offset="1" stop-color="#7a5410"/></radialGradient>
        <radialGradient id="ballc" cx="38%" cy="32%" r="70%"><stop offset="0" stop-color="#ffffff"/><stop offset="0.4" stop-color="#d6e8ff"/><stop offset="0.8" stop-color="#9a7ad8"/><stop offset="1" stop-color="#4a2a8a"/></radialGradient>
        <radialGradient id="ballb" cx="38%" cy="32%" r="70%"><stop offset="0" stop-color="#fffaf0"/><stop offset="0.5" stop-color="#e6dcc6"/><stop offset="1" stop-color="#8a7a5a"/></radialGradient>
        <pattern id="meshm" width="2" height="2" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path d="M0 0 H2 M0 0 V2" stroke="#5a5d68" stroke-width="0.35"/></pattern>
      </defs>
      <g transform="translate(32 32) scale(0.78) translate(-32 -32) rotate(-38 32 32)">
        ${L.glow(handle.concat(['circle cx="32" cy="16" r="13"']))}
        ${draw(handle, `fill="${L.tier === 0 ? '#2a2b32' : L.fill(mat)}" stroke="${L.tier === 0 ? '#111' : L.line(mat)}" stroke-width="0.6"`)}
        ${L.decor(handle, bb, mat, { k: 0.86, grain: 'v', center: [0.5, 0.3], label: false })}
        <rect x="25.6" y="27.6" width="12.8" height="3.6" rx="1" fill="${L.tier === 0 ? '#8e929c' : L.hw.fill === '#1c1a20' ? '#e8c35a' : L.hw.fill}"/>
        <path d="M26 29 H38" stroke="#fff" stroke-width="0.6" opacity="0.7"/>
        <circle cx="32" cy="16" r="13" fill="${grill}"/>
        <circle cx="32" cy="16" r="13" fill="url(#meshm)" opacity="0.55"/>
        ${L.theme === 'show' ? '<circle cx="32" cy="16" r="12.4" fill="none" stroke="#fff" stroke-width="1" stroke-dasharray="0.01 1.6" stroke-linecap="round"/>' : ''}
        ${L.theme === 'cult' ? '<path d="M19.6 13 C24 15 28 11 32 13.4 C36 15.8 40 11.6 44.4 13" fill="none" stroke="#c0562c" stroke-width="1"/><path d="M19.6 19 C24 21 28 17 32 19.4 C36 21.8 40 17.6 44.4 19" fill="none" stroke="#3fb7a8" stroke-width="1"/>' : ''}
        <path d="M19.4 18.4 H44.6" stroke="${L.tier >= 2 && L.theme !== 'show' ? '#8a6418' : '#8e929c'}" stroke-width="1.2"/>
        ${L.tier === 0 ? '' : '<ellipse cx="27" cy="9.6" rx="4.4" ry="2.4" fill="#fff" opacity="0.7" transform="rotate(-30 27 9.6)"/>'}
        ${L.extras([24, 2, 16, 60], { anchor: [32, 61.6], badge: [32, 40], spark: [[1.1, 0.02], [-0.2, 0.3], [1, 0.9]], charmScale: 1.3 })}
      </g>`;
  },

  sax(L) {
    const body = [`path d="${SX_BODY}"`];
    const bb = [25, 8.6, 29.6, 52];
    const keys = L.tier === 0 ? '#e6e0d0' : L.tier === 3 ? L.hw.fill : '#f7f1e2';
    const keyLine = L.tier === 0 ? '#8a7a50' : L.line('brass');
    const neck = L.tier === 0 ? '#bb9a54' : L.tier === 3 && L.theme !== 'hit' && L.theme !== 'studio' ? L.hw.fill === '#efe6d2' ? '#a8894a' : '#e8c35a' : L.fill('brass');
    return `
      <path d="M14 7.6 C17 3.8 23 3.4 26.6 6.4 L28.4 8.2 L26.2 10.4 C24 8.4 20.4 8 17.6 10.4 Z" fill="${neck}" stroke="${keyLine}" stroke-width="0.5"/>
      <path d="M9.6 12.6 L14.6 7.4 L17.2 10 L12.2 15.2 Z" fill="#1a1a1e"/><rect x="13.6" y="9" width="2.6" height="1.2" fill="#c9ccd4" transform="rotate(45 14.9 9.6)"/>
      ${L.glow(body)}
      ${draw(body, `fill="${L.fill('brass')}" stroke="${L.line('brass')}" stroke-width="0.7"`)}
      ${L.decor(body, bb, 'brass', { binding: false, center: [0.5, 0.84], label: false })}
      <ellipse cx="47.4" cy="33.6" rx="5.6" ry="2.8" transform="rotate(-42 47.4 33.6)" fill="${L.tier === 3 ? L.dark : '#5a3a08'}" stroke="${L.line('brass')}" stroke-width="0.6"/>
      ${L.tier === 0 ? '' : '<path d="M28.2 12 L29.6 44" stroke="#fff3c4" stroke-width="0.9" opacity="0.6"/>'}
      <g>${[15, 20.4, 25.8, 31.2, 36.6].map((y, i) => `<circle cx="${f(30.2 + i * 0.18)}" cy="${y}" r="1.9" fill="${keys}" stroke="${keyLine}" stroke-width="0.6"/>${L.tier === 0 ? '' : `<circle cx="${f(29.8 + i * 0.18)}" cy="${y - 0.5}" r="0.6" fill="#fff" opacity="${L.theme === 'hit' ? 0.3 : 1}"/>`}`).join('')}</g>
      <path d="M33 16 C35 17 35.4 24 33.6 26 M33.8 29 C36 30.4 36.2 36 34.4 38" fill="none" stroke="${keyLine}" stroke-width="0.9" ${LINE}/>
      <path d="M30.4 50 C33 54.4 39.4 55 42.4 51.4" fill="none" stroke="${keyLine}" stroke-width="0.9"/>
      <circle cx="44.6" cy="45.6" r="1.6" fill="${keys}" stroke="${keyLine}" stroke-width="0.5"/>
      ${L.extras(bb, { anchor: [26.4, 46], badge: [36, 56], spark: [[0.98, 0.3], [0.02, 0.5], [0.4, 1]] })}`;
  },

  trumpet(L) {
    const bell = ['path d="M40 26 L54 16.6 C58.6 14.6 61.6 22 61.6 31 C61.6 40 58.6 47.4 54 45.4 L40 36 Z"'];
    const bb = [40, 15.6, 21.6, 30.6];
    const fill = L.fill('brass');
    const line = L.line('brass');
    const tube = L.tier === 0 ? '#bb9a54' : L.tier === 3 && L.theme === 'studio' ? '#e8e0cc' : L.tier === 3 && L.theme === 'show' ? '#2a1a5a' : L.tier === 3 && L.theme === 'magnet' ? '#1c1a3a' : L.tier === 3 && L.theme === 'cult' ? '#4a2c18' : L.tier === 2 ? `hsl(${L.hue} 70% 44%)` : L.tier === 1 ? fill : '#e9b53c';
    const cap = L.tier === 0 ? '#d8d2c2' : L.tier === 3 ? L.hw.fill : '#f7f1e2';
    return `<g transform="translate(32 32) scale(0.8) translate(-32 -32)">
      ${L.glow(bell)}
      ${draw(bell, `fill="${fill}" stroke="${line}" stroke-width="0.6"`)}
      ${L.decor(bell, bb, 'brass', { binding: false, center: [1, 0.5], label: false })}
      <ellipse cx="58.4" cy="31" rx="3.2" ry="13.6" fill="${L.tier === 3 ? L.dark : '#5a3a08'}" stroke="${line}" stroke-width="0.6"/>
      ${L.tier === 0 ? '' : `<ellipse cx="58.4" cy="31" rx="3.2" ry="13.6" fill="none" stroke="${L.tier === 3 ? L.line() : '#ffe08a'}" stroke-width="0.6"/>`}
      <path d="M18 34.4 C11.6 34.4 11.6 45 18 45 H40 C46.4 45 46.4 34.4 40 34.4" fill="none" stroke="${line}" stroke-width="4.4"/>
      <path d="M18 34.4 C11.6 34.4 11.6 45 18 45 H40 C46.4 45 46.4 34.4 40 34.4" fill="none" stroke="${tube}" stroke-width="3"/>
      <rect x="7" y="27.4" width="35" height="5.8" rx="2.9" fill="${tube}" stroke="${line}" stroke-width="0.6"/>
      <path d="M1.6 27.8 C3.6 27.8 4.6 28.8 5 29.4 H7.4 V31.2 H5 C4.6 31.8 3.6 32.8 1.6 32.8 Z" fill="${L.tier === 0 ? '#b9bcc2' : L.tier >= 2 ? '#e8c35a' : '#d9dbe2'}" stroke="#6b6f7a" stroke-width="0.5"/>
      <g>${[20, 26, 32].map((x) => `
        <rect x="${x}" y="17" width="4.4" height="17" rx="1.2" fill="${tube}" stroke="${line}" stroke-width="0.6"/>
        <rect x="${x + 1.4}" y="12.6" width="1.6" height="4.6" fill="${L.tier === 0 ? '#9a9ea8' : '#c9ccd4'}"/>
        <ellipse cx="${x + 2.2}" cy="12" rx="2.8" ry="1.4" fill="${cap}" stroke="${line}" stroke-width="0.5"/>`).join('')}</g>
      ${L.tier === 0 ? '' : '<path d="M9 28.6 H40" stroke="#fff3c4" stroke-width="0.8" opacity="0.7"/>'}
      ${L.extras(bb, { anchor: [30, 45.6], badge: [24, 41.6 + 0.4], spark: [[1, 0], [-0.6, 1.02], [0.5, 1.05]], charmScale: 1.2 })}
    </g>`;
  },

  violin(L) {
    const body = [`path d="${VL_BODY}"`];
    const bb = [20.6, 17.4, 22.8, 36.2];
    const fill = L.tier === 2 ? L.fill('paint') : L.fill('wood');
    const line = L.tier === 3 ? L.line() : L.line('wood');
    const scroll = L.tier === 3 ? L.dark : L.tier === 0 ? '#6a4020' : L.tier === 2 ? `hsl(${L.hue - 10} 60% 22%)` : L.line('wood');
    return `${tilt(`
      <path d="M32 1.4 C35.4 1.4 35.8 5.6 33.4 6 C33 6.2 33 7 33.2 8.2 H30.8 V4.4 C30.8 2.6 31.4 1.4 32 1.4 Z" fill="${scroll}" stroke="${L.tier === 3 ? line : 'none'}" stroke-width="0.3"/>
      <circle cx="32.6" cy="3.4" r="1.2" fill="none" stroke="#3a1606" stroke-width="0.5"/>
      ${pegs(L, [[29.6, 6], [34.4, 6]], 0.8)}
      ${L.glow(body)}
      ${draw(body, `fill="${fill}" stroke="${line}" stroke-width="0.8"`)}
      ${L.decor(body, bb, L.tier === 2 ? 'paint' : 'wood', { k: 0.95, center: [0.5, 0.62] })}
      <rect x="30.4" y="7.6" width="3.2" height="30" rx="1.2" fill="${L.theme === 'studio' ? '#1c1a20' : L.theme === 'hit' ? '#2a1a0c' : '#14100c'}"/>
      ${L.theme === 'magnet' || L.theme === 'show' ? '<path d="M32 10 V35" stroke="#f2cf6a" stroke-width="0.35" opacity="0.8"/>' : ''}
      <path d="M27.4 33.2 C26 35.4 28 37.4 26.6 39.8 M36.6 33.2 C38 35.4 36 37.4 37.4 39.8" fill="none" stroke="#2a1208" stroke-width="1"/>
      <rect x="28.4" y="41.4" width="7.2" height="1.4" rx="0.5" fill="#e6c48a"/>
      <path d="M29.6 44.4 H34.4 L33.4 51.4 H30.6 Z" fill="${L.tier === 3 && L.theme !== 'hit' ? L.hw.fill : '#14100c'}"/>
      ${strings(4, 31.1, 0.6, 7, 45, 0.25)}
      ${L.tier === 0 ? '' : '<ellipse cx="27.6" cy="24" rx="2.4" ry="3.4" fill="#fff" opacity="0.2"/>'}
      ${L.extras(bb, { anchor: [33.6, 5.6], badge: [32, 49.6], spark: [[0.98, 0.1], [0.04, 0.86], [0.7, 1.02]] })}`, -34, 0.95)}
      <path d="M8 54 L56 8" stroke="${L.tier === 3 ? (L.theme === 'studio' ? '#e8e0cc' : L.dark) : '#5a2a0c'}" stroke-width="1.6" stroke-linecap="round"/>
      <path d="M9.8 55.8 L57.6 10" stroke="#f4efe6" stroke-width="0.9" stroke-linecap="round"/>
      <path d="M6.4 52.4 L10.6 56.6" stroke="${L.tier === 3 && L.theme !== 'hit' ? L.hw.fill : '#1a1a1e'}" stroke-width="2.6" stroke-linecap="round"/>
      <circle cx="56.6" cy="8.6" r="1" fill="#1a1a1e"/>`;
  },

  flute(L) {
    const tube = ['rect x="1" y="29" width="62" height="6" rx="3"'];
    const bb = [1, 29, 62, 6];
    // Grün ist eine Holzflöte (natürliche Hölzer); sonst Metall.
    const mat = L.tier === 1 ? 'wood' : 'metal';
    const keys = L.tier === 1 ? '#d9dbe2' : L.tier === 0 ? '#c3c6cc' : L.tier === 2 ? '#e8c35a' : L.hw.fill === '#1c1a20' ? '#1c1a20' : L.hw.fill;
    return `<g transform="rotate(-30 32 32)">
      ${L.glow(tube)}
      ${draw(tube, `fill="${L.fill(mat)}" stroke="${L.line(mat)}" stroke-width="0.5"`)}
      ${L.decor(tube, bb, mat, { binding: false, grain: 'h', center: [0.5, 0.5], label: false })}
      ${L.tier === 1 ? [6, 17, 45].map((x) => `<rect x="${x}" y="28.6" width="1.6" height="6.8" rx="0.4" fill="#d9dbe2" stroke="#6b6f7a" stroke-width="0.25"/>`).join('') : '<path d="M17 29 V35 M45 29 V35" stroke="#7a7e88" stroke-width="0.6"/>'}
      <rect x="8" y="27.6" width="6.4" height="2.4" rx="1.2" fill="${L.tier === 1 ? L.fill('wood') : keys}" stroke="${L.tier === 1 ? L.line('wood') : '#5a5d68'}" stroke-width="0.4"/>
      <ellipse cx="11.2" cy="28" rx="1.4" ry="0.6" fill="#2a2233"/>
      <g>${[22, 27, 32, 37, 42, 51, 56].map((x) => `<circle cx="${x}" cy="32" r="2" fill="${keys}" stroke="${L.tier === 3 ? L.hw.line : '#5a5d68'}" stroke-width="0.5"/>${L.tier === 0 ? '' : `<circle cx="${x - 0.5}" cy="31.4" r="0.6" fill="#fff" opacity="${L.theme === 'hit' ? 0.3 : 0.9}"/>`}`).join('')}</g>
      <path d="M20 33.8 H58" stroke="${L.tier === 1 ? '#6b6f7a' : '#5a5d68'}" stroke-width="0.5"/>
      ${L.tier === 0 ? '' : '<path d="M2.6 30.2 H62" stroke="#fff" stroke-width="0.6" opacity="0.5"/>'}
      ${L.extras([1, 20, 62, 24], { anchor: [62, 33], badge: [17, 37], spark: [[0.98, 0.1], [0.06, 0.9], [0.6, 0.9]], charmScale: 1.2 })}
    </g>`;
  },

  turntables(L) {
    const base = ['rect x="2" y="10" width="60" height="44" rx="4"'];
    const bb = [2, 10, 60, 44];
    const mat = L.tier === 1 ? 'wood' : 'paint';
    const vinyl = L.theme === 'hit' ? 'url(#vgold)' : 'url(#vinyl)';
    const label = L.tier === 0 ? '#8e929c' : L.tier === 1 ? '#e8d2a6' : L.tier === 2 ? `hsl(${L.hue} 80% 58%)` : { magnet: '#e8c35a', show: '#ff4fa3', studio: '#fffaf0', cult: '#3fb7a8', hit: '#c8202a' }[L.theme];
    const ring = L.tier === 0 ? '#6b6f7a' : L.hw.fill === '#1c1a20' ? '#e8c35a' : L.hw.fill;
    return `
      <defs>
        <radialGradient id="vinyl" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#2a2830"/><stop offset="0.5" stop-color="#121016"/><stop offset="0.85" stop-color="#2a2830"/><stop offset="1" stop-color="#0c0a0e"/></radialGradient>
        <radialGradient id="vgold" cx="45%" cy="40%" r="60%"><stop offset="0" stop-color="#fff6c0"/><stop offset="0.45" stop-color="#e8b84a"/><stop offset="1" stop-color="#8a5a10"/></radialGradient>
      </defs>
      ${L.glow(base)}
      ${draw(base, `fill="${L.fill(mat)}" stroke="${L.line(mat)}" stroke-width="0.7"`)}
      ${L.decor(base, bb, mat, { k: 0.94, grain: 'h', center: [0.4, 0.5], label: false })}
      ${L.sheen('M4 12 H60', 0.6, 0.2)}
      <circle cx="26" cy="32" r="18.6" fill="${ring}"/>
      <circle cx="26" cy="32" r="17" fill="${vinyl}"/>
      <g fill="none" stroke="${L.theme === 'hit' ? '#7a5410' : '#3a3842'}" stroke-width="0.4">${[15, 13, 11, 9].map((r) => `<circle cx="26" cy="32" r="${r}"/>`).join('')}</g>
      <path d="M14.6 22.4 C18 19 23 17.6 27.6 18.4" fill="none" stroke="#fff" stroke-width="1.2" opacity="0.25"/>
      <circle cx="26" cy="32" r="6" fill="${label}"/><circle cx="26" cy="32" r="3" fill="#fff" opacity="0.3"/><circle cx="26" cy="32" r="1" fill="#e6e6ea"/>
      <circle cx="52" cy="17.6" r="3.6" fill="${L.tier === 0 ? '#9a9ea8' : ring}" stroke="#5a5d68" stroke-width="0.5"/><circle cx="52" cy="17.6" r="1.4" fill="#6b6f7a"/>
      <path d="M52 17.6 L53.6 38 L44.4 46" fill="none" stroke="${L.tier === 0 ? '#c9ccd4' : '#e6e6ea'}" stroke-width="1.6" ${LINE}/>
      <rect x="41" y="44.4" width="5.6" height="3.6" rx="0.8" fill="#2b2d42" stroke="${ring}" stroke-width="0.5" transform="rotate(-40 43.8 46.2)"/>
      <rect x="56" y="34" width="2.6" height="15" rx="1.2" fill="#0c0a0e"/><rect x="55" y="39" width="4.6" height="2.8" rx="0.8" fill="${L.tier === 0 ? '#9a9ea8' : label}"/>
      <circle cx="8" cy="49" r="2" fill="${L.tier === 0 ? '#6b6f7a' : '#3ddc97'}"/>
      ${L.extras(bb, { anchor: [60, 46], badge: [52, 51], spark: [[0.98, 0.02], [0.02, 0.96], [0.5, 1]] })}`;
  },
};

/**
 * Zeichnung eines Instruments in seiner Güteklasse. key: guitar, eguitar, bass, drums, edrums, synth, piano, vocals, sax, trumpet, violin, flute, turntables.
 * @returns {?string} SVG-Inhalt (64 × 64) oder null, wenn es die Form nicht gibt
 */
export function tierArt(key, tier, hue = null, variant = 0, gold = null) {
  const art = ART[key];
  if (!art) return null;
  const L = makeLook(tier, hue, variant, gold);
  const body = art(L);
  return `<defs>${L.defs.join('')}</defs>${body}`;
}

export const TIER_ART_KEYS = Object.keys(ART);
