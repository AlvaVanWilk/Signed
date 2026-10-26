import { PRODUCERS } from './producers.js';

/**
 * Tonstudio, gezeichnet wie der Proberaum (960 × 320, ruhige Flächen, dunkle Kontur). Vier Stufen: Garage, Tonstudio, Profi-Studio,
 * legendäres Studio. Hinter der Glasscheibe sitzt der gewählte Produzent am Mischpult; in der Garage steht er an einem Klapptisch.
 * Die Band steht als eigene Ebene davor (wie im Proberaum).
 */
const INK = '#1d1a2a';
const W = 960;
const H = 320;
const FLOOR = 236;

const line = (d, c = INK, w = 2) => `<path d="${d}" fill="none" stroke="${c}" stroke-width="${w}" stroke-linecap="round" stroke-linejoin="round"/>`;

/** Produzent als eingebettete Figur (Ausschnitt), x/y = linke obere Ecke, h = Höhe. */
function producer(key, x, y, h) {
  const draw = PRODUCERS[key];
  if (!draw) return '';
  const w = h * 0.8;
  return `<svg x="${x}" y="${y}" width="${w}" height="${h}" viewBox="0 0 120 150" overflow="visible">${draw()}</svg>`;
}

/** Mischpult: Pult mit Reglern und Pegeln. size 0–3 (je Studio größer und schöner). */
function desk(x, y, w, level, on = true) {
  const body = ['#3a3f4a', '#2c3140', '#2a2232', '#1c1414'][level];
  const top = ['#5a606c', '#4a5160', '#4a3a48', '#3a2a22'][level];
  const faders = Math.round(w / 14);
  let s = `<path d="M${x} ${y + 10} L${x + 10} ${y} H${x + w - 10} L${x + w} ${y + 10} V${y + 34} H${x} Z" fill="${body}" stroke="${INK}" stroke-width="2"/>`;
  s += `<path d="M${x + 6} ${y + 10} L${x + 13} ${y + 4} H${x + w - 13} L${x + w - 6} ${y + 10} Z" fill="${top}"/>`;
  for (let i = 0; i < faders; i++) {
    const fx = x + 12 + i * ((w - 24) / Math.max(1, faders - 1));
    const fy = y + 14 + ((i * 37) % 11);
    s += `<path d="M${fx.toFixed(1)} ${y + 13} V${y + 30}" stroke="#14121a" stroke-width="2"/><rect x="${(fx - 3).toFixed(1)}" y="${fy}" width="6" height="4" rx="1" fill="#d6d9e0"/>`;
    if (on && level >= 1) s += `<circle cx="${fx.toFixed(1)}" cy="${y + 8}" r="1.6" fill="${i % 4 === 3 ? '#ffd25a' : '#3ddc97'}"/>`;
  }
  if (level >= 2) {
    // VU-Meter (im legendären Studio warm leuchtend)
    for (const vx of [x + w * 0.3, x + w * 0.62]) {
      s += `<rect x="${vx - 16}" y="${y - 22}" width="32" height="20" rx="3" fill="${level === 3 ? '#f6d98a' : '#e8e4d8'}" stroke="${INK}" stroke-width="1.6"/>
        <path d="M${vx - 11} ${y - 7} Q${vx} ${y - 18} ${vx + 11} ${y - 7}" fill="none" stroke="#7a5410" stroke-width="1"/>
        <path d="M${vx} ${y - 4} L${vx + 6} ${y - 15}" stroke="#c0392b" stroke-width="1.6" class="st-needle"/>`;
    }
  }
  return s;
}

/** Glasscheibe zum Regieraum mit Produzent und Pult dahinter. */
function controlRoom(x, y, w, h, level, key) {
  const frame = ['#5a606c', '#6a7280', '#6a4a36', '#3a2418'][level];
  const room = ['#2a3140', '#26303e', '#2e2230', '#1e1418'][level];
  const glow = level === 3 ? '#ffcf8a' : '#9ad1ff';
  let s = `<rect x="${x - 8}" y="${y - 8}" width="${w + 16}" height="${h + 16}" rx="6" fill="${frame}" stroke="${INK}" stroke-width="2.4"/>`;
  s += `<clipPath id="st-glass"><rect x="${x}" y="${y}" width="${w}" height="${h}" rx="3"/></clipPath>`;
  s += `<g clip-path="url(#st-glass)"><rect x="${x}" y="${y}" width="${w}" height="${h}" fill="${room}"/>`;
  s += `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="${glow}" opacity="0.07"/>`;
  // Hinten im Regieraum: Lautsprecher und (ab Profi) Platten an der Wand
  s += `<rect x="${x + 16}" y="${y + 12}" width="22" height="34" rx="3" fill="#14121a"/><circle cx="${x + 27}" cy="${y + 36}" r="7" fill="#2a2830"/><rect x="${x + w - 38}" y="${y + 12}" width="22" height="34" rx="3" fill="#14121a"/><circle cx="${x + w - 27}" cy="${y + 36}" r="7" fill="#2a2830"/>`;
  if (level === 3) {
    for (const [gx, gy] of [[x + 70, y + 14], [x + w - 110, y + 14]]) s += `<rect x="${gx}" y="${gy}" width="40" height="30" fill="#2a1a10" stroke="#e8c35a" stroke-width="1.6"/><circle cx="${gx + 20}" cy="${gy + 15}" r="11" fill="#e8b84a"/><circle cx="${gx + 20}" cy="${gy + 15}" r="3" fill="#c8202a"/>`;
  }
  if (key && key !== 'none') s += producer(key, x + w / 2 - h * 0.42, y + h * 0.05, h * 1.05);
  s += desk(x + w * 0.14, y + h - 34, w * 0.72, level) + '</g>';
  // Spiegelung im Glas
  s += `<path d="M${x + 12} ${y + h - 6} L${x + 60} ${y + 4} M${x + 40} ${y + h - 6} L${x + 74} ${y + 30}" stroke="#fff" stroke-width="5" opacity="0.08" stroke-linecap="round"/>`;
  return s;
}

/** Schaumstoff-Platten (Pyramiden) als Raster. */
function foam(x, y, w, h, color, shade) {
  let s = `<rect x="${x}" y="${y}" width="${w}" height="${h}" fill="${color}" stroke="${INK}" stroke-width="1.6"/>`;
  for (let i = x + 6; i < x + w - 4; i += 12) {
    for (let j = y + 6; j < y + h - 4; j += 12) s += `<path d="M${i} ${j + 10} L${i + 5} ${j} L${i + 10} ${j + 10} Z" fill="${shade}"/>`;
  }
  return s;
}

const LEVELS = {
  /** Garage: Betonwand, Rolltor, ein paar Eierkarton-Platten, Klapptisch mit Laptop. */
  0: (key) => `
    <rect width="${W}" height="${FLOOR}" fill="#565c66"/>
    ${[...Array(9)].map((_, i) => `<path d="M0 ${24 + i * 24} H${W}" stroke="#4c525c" stroke-width="1.4"/>`).join('')}
    <rect x="${W - 250}" y="18" width="220" height="${FLOOR - 18}" fill="#7a808a" stroke="${INK}" stroke-width="2.4"/>
    ${[...Array(10)].map((_, i) => `<path d="M${W - 250} ${36 + i * 20} H${W - 30}" stroke="#646a74" stroke-width="2"/>`).join('')}
    ${foam(150, 40, 90, 70, '#8a6a4a', '#6e5236')}${foam(250, 52, 70, 56, '#8a6a4a', '#6e5236')}
    <rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="#6c6a66"/>
    <path d="M90 ${FLOOR + 30} Q160 ${FLOOR + 20} 220 ${FLOOR + 40}" stroke="#5c5a56" stroke-width="10" opacity="0.6" fill="none"/>
    ${key && key !== 'none' ? producer(key, W - 205, FLOOR - 150, 160) : ''}
    <rect x="${W - 240}" y="${FLOOR - 40}" width="150" height="10" rx="2" fill="#c9b48a" stroke="${INK}" stroke-width="2"/>
    ${line(`M${W - 228} ${FLOOR - 30} L${W - 236} ${FLOOR + 12} M${W - 102} ${FLOOR - 30} L${W - 94} ${FLOOR + 12}`, INK, 3)}
    <path d="M${W - 196} ${FLOOR - 40} L${W - 188} ${FLOOR - 62} H${W - 140} L${W - 136} ${FLOOR - 40} Z" fill="#c9ccd4" stroke="${INK}" stroke-width="1.8"/><rect x="${W - 184}" y="${FLOOR - 58}" width="40" height="14" fill="#4fd1ff" opacity="0.6"/>`,
  /** Tonstudio: graue Schaumstoffwände, Glasscheibe mit einfachem Pult. */
  1: (key) => `
    <rect width="${W}" height="${FLOOR}" fill="#4a5262"/>
    ${foam(30, 40, 170, 150, '#5a6272', '#4a5262')}${foam(210, 40, 110, 150, '#5a6272', '#4a5262')}
    ${controlRoom(W - 330, 46, 300, 130, 1, key)}
    <rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="#3a3f4c"/>
    ${[...Array(8)].map((_, i) => `<path d="M${i * 130} ${FLOOR} L${i * 130 - 40} ${H}" stroke="#343946" stroke-width="2"/>`).join('')}`,
  /** Profi-Studio: warmes Holz, Diffusoren, größere Scheibe mit Pegeln. */
  2: (key) => `
    <rect width="${W}" height="${FLOOR}" fill="#6a4a3a"/>
    ${[...Array(24)].map((_, i) => `<rect x="${i * 40}" y="0" width="20" height="${FLOOR}" fill="#7a5644" opacity="0.6"/>`).join('')}
    ${[30, 220].map((x) => `<g>${[...Array(6)].map((_, r) => [...Array(6)].map((__, c) => `<rect x="${x + c * 30}" y="${30 + r * 26}" width="26" height="22" fill="${(r + c) % 3 ? '#a06a3b' : '#8a5630'}" stroke="${INK}" stroke-width="1"/>`).join('')).join('')}</g>`).join('')}
    ${controlRoom(W - 340, 44, 310, 136, 2, key)}
    <rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="#4a3226"/>
    ${[...Array(12)].map((_, i) => `<path d="M0 ${FLOOR + 8 + i * 7} H${W}" stroke="#56392b" stroke-width="1.6"/>`).join('')}
    <ellipse cx="${W / 2}" cy="${FLOOR + 50}" rx="300" ry="34" fill="#7a2a2a" opacity="0.55"/>`,
  /** Legendäres Studio: dunkles Edelholz, goldene Platten, warmes Licht, rote ON-AIR-Lampe. */
  3: (key) => `
    <defs><radialGradient id="st-warm" cx="50%" cy="0%" r="80%"><stop offset="0" stop-color="#ffcf8a" stop-opacity="0.35"/><stop offset="1" stop-color="#ffcf8a" stop-opacity="0"/></radialGradient></defs>
    <rect width="${W}" height="${FLOOR}" fill="#2e1c16"/>
    ${[...Array(16)].map((_, i) => `<rect x="${i * 60}" y="0" width="56" height="${FLOOR}" fill="#3a2419" stroke="#24150f" stroke-width="2"/>`).join('')}
    <rect width="${W}" height="${FLOOR}" fill="url(#st-warm)"/>
    ${[[40, 44], [130, 44], [220, 44], [40, 130], [130, 130]].map(([x, y], i) => `<g><rect x="${x}" y="${y}" width="70" height="70" fill="#14100c" stroke="#e8c35a" stroke-width="2.4"/><circle cx="${x + 35}" cy="${y + 35}" r="26" fill="${i % 3 === 2 ? '#d9dde6' : '#e8b84a'}"/><circle cx="${x + 35}" cy="${y + 35}" r="26" fill="none" stroke="#7a5410" stroke-width="0.8" opacity="0.6"/><circle cx="${x + 35}" cy="${y + 35}" r="7" fill="#c8202a"/></g>`).join('')}
    ${controlRoom(W - 340, 52, 310, 136, 3, key)}
    <rect x="${W - 231}" y="28" width="92" height="18" rx="4" fill="#5a0e0e" stroke="${INK}" stroke-width="1.6"/><text x="${W - 185}" y="41.5" text-anchor="middle" font-family="Arial Black, sans-serif" font-size="12" fill="#ff5a4a" class="st-onair">ON AIR</text>
    <rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="#2a1712"/>
    <ellipse cx="${W / 2}" cy="${FLOOR + 50}" rx="340" ry="38" fill="#6a1a1a" opacity="0.7"/>
    <ellipse cx="${W / 2}" cy="${FLOOR + 50}" rx="300" ry="30" fill="none" stroke="#e8c35a" stroke-width="2" opacity="0.5"/>
    <g transform="translate(250 ${FLOOR - 70})"><rect x="0" y="40" width="34" height="30" rx="4" fill="#7a4a2a" stroke="${INK}" stroke-width="2"/>${[[-16, 8], [0, 0], [16, 6], [-8, 22], [10, 20]].map(([dx, dy]) => `<ellipse cx="${17 + dx}" cy="${30 + dy - 20}" rx="10" ry="18" fill="#3a7a4a" stroke="${INK}" stroke-width="1.4" transform="rotate(${dx * 2} ${17 + dx} ${30 + dy})"/>`).join('')}</g>`,
};

/** Das Studio als SVG für die Bandseite (Ebene hinter der Band). level 0–3, key = Produzent oder 'none'. */
export function studioSvg(level = 0, key = 'none') {
  const art = LEVELS[Math.max(0, Math.min(3, level))](key);
  return `<svg class="lb-roomart st-art" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid slice" aria-hidden="true">${art}</svg>`;
}
