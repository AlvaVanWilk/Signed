import { app, bandById, cat, defineActions, mutate, navigate, serverNow } from './core.js';
import { genreGlyph } from './cards.js';
import { closeAllModals, duration, esc, fmt1, fmtGain, money, off, openModal, percent } from './ui.js';

/**
 * Tournee: Weltkarte mit der Route, was die Band gerade macht (fliegt, ist angekommen, hat gespielt), und die Knöpfe für die nächste
 * Station. Ziele werden ausgewürfelt (der Server entscheidet); hier läuft dazu ein kleiner Würfel, der auf dem Ziel landet.
 */

const W = 1000;
const TOP = 40;
const H = 350;
const xy = ([lon, lat]) => [((lon + 180) / 360) * W, ((90 - lat) / 180) * 500];

/** Stark vereinfachte Umrisse der Kontinente (Länge, Breite): eine ruhige Karte, kein Atlas. */
const LAND = [
  // Nordamerika
  [[-168, 66], [-162, 70], [-150, 71], [-140, 70], [-128, 70], [-115, 68], [-100, 68], [-90, 69], [-82, 69], [-80, 63], [-90, 58], [-94, 56], [-82, 52], [-79, 55], [-76, 60], [-70, 61], [-64, 60], [-60, 55], [-56, 52], [-60, 48], [-65, 48], [-66, 45], [-70, 43], [-70, 41], [-74, 40], [-76, 37], [-76, 35], [-80, 32], [-81, 30], [-80, 26], [-81, 25], [-82, 27], [-83, 29], [-85, 30], [-89, 30], [-91, 29], [-94, 29.5], [-97, 27.5], [-97.5, 25], [-97.5, 22], [-96, 19], [-94, 18.2], [-91, 18.8], [-90.5, 21], [-87, 21.5], [-88, 18], [-88.5, 16], [-86, 15.8], [-84, 15], [-83.5, 12], [-83.5, 10.5], [-82, 9], [-79.5, 9.5], [-77.5, 8.5], [-78.5, 7.5], [-80.5, 7.5], [-81.5, 8.2], [-84, 9.5], [-85.7, 11], [-87.5, 13], [-91, 14], [-94, 16], [-97, 16], [-100, 17], [-104, 19], [-105.5, 20.5], [-105.5, 23], [-108, 25.5], [-110.5, 28], [-112.8, 31.5], [-114.5, 31], [-112, 28], [-110, 23], [-112, 25], [-114, 28], [-115, 30], [-117, 32.5], [-118.5, 34], [-121, 35], [-122.5, 37.5], [-124, 40], [-124.5, 43], [-124, 46.5], [-124.5, 48.5], [-123, 49], [-126, 50], [-128, 51], [-130, 54], [-133, 57], [-136, 58.5], [-140, 60], [-145, 60.5], [-150, 61], [-152, 59], [-154, 58], [-157, 57], [-162, 55], [-158, 57.5], [-158, 59], [-162, 59.5], [-164, 61], [-166, 62], [-164, 64], [-166, 65]],
  [[-80, 73], [-68, 70], [-62, 67], [-64, 63], [-73, 64], [-78, 66], [-86, 70]],
  [[-118, 73], [-100, 69], [-100, 73], [-110, 76]],
  [[-90, 80], [-70, 77], [-62, 82], [-80, 83]],
  // Grönland, Island
  [[-73, 78], [-60, 82], [-40, 83], [-22, 82], [-18, 77], [-20, 72], [-22, 70], [-28, 68], [-35, 66], [-40, 65], [-43, 60], [-48, 61], [-52, 64], [-54, 67], [-55, 70], [-58, 75], [-66, 76]],
  [[-24.3, 65.5], [-22, 64], [-18.5, 63.4], [-14.5, 64.4], [-13.6, 65.3], [-15, 66.4], [-17, 66.2], [-20, 66.1], [-22.4, 66.4]],
  // Mittelamerika-Inseln
  [[-85, 21.9], [-82, 23.2], [-77.5, 21.8], [-74.2, 20.2], [-77.7, 19.9], [-80.5, 22]],
  [[-74.4, 18.4], [-72.6, 19.9], [-69, 19.7], [-68.4, 18.5], [-71, 18.2]],
  // Südamerika
  [[-77.5, 8.5], [-75, 11], [-72, 12], [-71, 10.5], [-68, 10.5], [-63, 10.5], [-61, 10], [-58, 7], [-55, 6], [-52, 5], [-51, 4], [-50, 1], [-48, -1], [-44, -2.5], [-40, -3], [-37, -5], [-35, -7.5], [-35.5, -10], [-38, -13], [-39, -17], [-40, -21], [-42, -23], [-45, -24], [-48, -26], [-48.5, -28.5], [-51, -31], [-53, -34], [-55, -35], [-58, -34.5], [-57.5, -37], [-62, -39], [-62.5, -41], [-65, -42], [-64, -45], [-67.5, -46.5], [-66, -48], [-69, -51], [-68.5, -52.5], [-71, -54], [-74, -52], [-75, -48], [-73.5, -42], [-73.5, -37], [-71.5, -32], [-71.5, -27], [-70.5, -22], [-70.3, -18.5], [-72, -17], [-75, -15.5], [-76.5, -13], [-78, -10], [-79.5, -7.5], [-81, -6], [-80.5, -3.5], [-80, -1], [-80, 1], [-79, 1.5], [-77.5, 4], [-77.3, 6.5]],
  // Eurasien
  [[-9.5, 37], [-9, 39], [-9.5, 43], [-8, 43.7], [-1.8, 43.4], [-1.2, 46], [-2.5, 47.3], [-4.7, 48], [-1.5, 48.7], [0.2, 49.5], [1.6, 50.8], [3, 51.3], [4.2, 52], [4.8, 53], [7, 53.5], [8.5, 54], [8.2, 55.5], [8, 57], [10.5, 57.7], [10.5, 56], [11, 54], [12.5, 54.4], [14, 54], [18, 54.8], [20, 54.5], [21, 55.8], [21.5, 57.5], [23.5, 58.5], [24.5, 59.5], [30, 59.9], [27, 60.5], [22.5, 60], [21.3, 61], [21.5, 63], [24.5, 64.8], [25.5, 65.4], [24, 65.8], [22, 65.5], [21.2, 64.3], [19, 63.3], [17.5, 62.3], [17.3, 61], [18.7, 60], [18.3, 59.3], [16.6, 58.5], [16.5, 57], [15.8, 56.1], [14.2, 55.4], [12.7, 56.3], [11.8, 58], [11.2, 59], [10.5, 59.4], [9.5, 59], [8, 58.1], [6.5, 58.1], [5.5, 58.8], [5, 60.5], [5, 62], [7, 63], [9, 63.8], [10.5, 64.6], [12.4, 66], [14, 67.5], [15.8, 68.6], [18, 69.5], [20.5, 70], [23, 70.6], [25.8, 71.1], [28.3, 71], [31, 70.3], [33, 69.4], [36, 69], [40, 67.8], [41, 66.2], [44, 68.4], [46, 68], [53, 68.6], [58, 68.8], [60, 69.6], [66, 70.6], [68, 72.5], [72, 72.8], [75, 72.5], [80, 73.4], [87, 75], [95, 76], [104, 77.6], [108, 76.7], [113, 74], [118, 73.3], [126, 73.3], [130, 71], [136, 71.6], [140, 72.4], [148, 72.2], [152, 70.8], [160, 70.4], [165, 69.6], [170, 70], [175, 69.8], [180, 68.8], [180, 65.5], [178, 64.5], [174, 61.8], [170, 60], [166, 60.2], [163, 58.5], [160, 53], [156.5, 51], [156, 57], [152, 59], [143, 59.5], [137, 54], [141, 52], [140, 48], [135, 43], [131, 42.5], [129.5, 41], [129, 35.3], [126.5, 34.5], [126, 37], [125, 39.5], [121.5, 39], [122, 40.8], [118, 39], [119, 37], [122.5, 37], [120.5, 34], [122, 30.5], [121, 28], [119, 25], [116, 22.8], [110.5, 21], [108, 21.5], [106, 20], [106.5, 18], [109, 13], [109, 11.5], [105, 8.6], [103, 10.5], [100.5, 13.5], [100, 12], [99.2, 9], [100.5, 7], [103.5, 4], [103.5, 1.5], [101, 2.8], [98.5, 8], [98.5, 13], [97.7, 16.5], [94.5, 16], [94, 19], [92, 21], [90, 22], [88, 21.8], [86.5, 20], [84, 18], [80.2, 15.5], [80, 13], [79.8, 10], [77.5, 8], [76, 10], [74.5, 14], [73, 18.5], [72.8, 21], [70, 21], [68.5, 23.5], [66.5, 25.4], [61.5, 25.2], [57.3, 25.8], [56.3, 27.2], [54, 26.6], [51.5, 27.9], [50, 30], [48, 30], [48.5, 28.5], [49.5, 27], [51.5, 25], [51.5, 24], [54, 24], [56, 24.8], [56.3, 26.3], [57, 23.5], [59.8, 22.4], [58.5, 20.5], [56.5, 18], [55, 17], [52.2, 16], [48, 14], [45, 12.8], [43.3, 13], [42.7, 15.5], [41, 19], [39, 21.5], [37, 25], [35, 28], [34.5, 29.5], [34.8, 32.5], [35.8, 34.6], [36, 36.8], [32, 36.2], [30, 36.3], [27.5, 37], [26.3, 39.4], [26.8, 40.4], [29, 41], [31, 41.2], [35, 42], [38, 41], [41.5, 41.6], [40, 43.5], [38, 44.5], [36.5, 45.3], [35, 45], [33.5, 44.5], [32.5, 45.5], [30.5, 46.5], [29.8, 45.2], [28.6, 44], [28, 42], [26, 40.8], [23.5, 40.2], [23.8, 38], [22.5, 36.5], [21.2, 37.8], [21, 39.5], [19.5, 40.5], [19.4, 41.8], [17, 43], [15, 44.5], [13.7, 45.6], [12.3, 45.3], [12.3, 44.3], [14, 42.5], [16, 41.3], [18.5, 40.2], [17, 39], [16.5, 38], [15.7, 38], [16, 39.5], [15.6, 40.1], [14, 41], [12, 41.8], [10.5, 43], [9.5, 44.2], [8, 43.8], [6.5, 43.1], [4.5, 43.5], [3.2, 43], [3.2, 41.9], [0.8, 41], [0, 39.5], [-0.5, 38.5], [-0.8, 37.5], [-2.1, 36.7], [-4.5, 36.7], [-5.6, 36], [-6.4, 36.8], [-7.5, 37.2], [-8.8, 37]],
  // Britische Inseln
  [[-5.7, 50], [-3, 50.6], [1.4, 51.2], [1.7, 52.6], [0.3, 53.5], [-0.3, 54.3], [-1.6, 55.6], [-2.2, 56.8], [-1.8, 57.6], [-3.4, 58.6], [-5, 58.6], [-6.2, 57.4], [-5.6, 56.3], [-6.3, 55.7], [-4.9, 55], [-3.1, 54.9], [-3.4, 54.3], [-2.9, 53.4], [-4.4, 53.4], [-4.6, 52.8], [-4.1, 52.3], [-5.2, 51.8], [-3.1, 51.5], [-4.3, 51.2]],
  [[-10.3, 51.6], [-8, 51.6], [-6, 52.2], [-6, 53.3], [-6.2, 54], [-5.5, 54.5], [-6.2, 55.2], [-7.5, 55.3], [-8.5, 54.9], [-8.4, 54.3], [-10, 54.2], [-9.6, 53.4], [-10.3, 52.8], [-9.5, 52.2]],
  // Afrika, Madagaskar
  [[-17.1, 21], [-16.2, 24], [-14.5, 26.3], [-13.2, 27.8], [-10, 29.5], [-9.6, 30.6], [-8.3, 33.4], [-6.8, 34.1], [-5.9, 35.8], [-2, 35.2], [1, 36.5], [5.5, 36.9], [9.5, 37.3], [11.1, 36.9], [10.3, 35], [11.1, 33.3], [15.3, 32.3], [19.3, 30.3], [20.3, 32], [23, 32.6], [25, 31.7], [29, 30.8], [32.3, 31.3], [34.3, 31.2], [34.5, 29.5], [33, 28], [34.5, 26], [35.7, 23.9], [37.3, 21], [38.5, 18], [39.7, 15.6], [41.6, 13.5], [43.3, 12.6], [44.5, 10.4], [51.2, 11.8], [51.1, 10.5], [49.5, 7], [47.6, 4.5], [44.5, 1.6], [41.5, -1.7], [39.2, -4.7], [39.2, -8.3], [40.5, -10.5], [40.6, -15.2], [37.5, -17.6], [35, -20], [35.5, -24], [32.9, -25.9], [32.5, -28.6], [30.1, -31.3], [27.4, -33.6], [25.6, -34], [22.6, -33.9], [20, -34.8], [18.4, -34], [18.2, -31.7], [16.5, -28.6], [15.2, -27], [14.4, -23], [11.8, -17.2], [12.2, -13.5], [13.7, -10.7], [12.2, -6], [11.9, -3], [9.4, -0.1], [9.8, 3], [8.5, 4.5], [5.8, 4.3], [4.3, 6.3], [1, 6], [-2, 4.7], [-4.6, 5.2], [-7.5, 4.4], [-9.5, 5.5], [-11.4, 6.9], [-13.2, 8.6], [-15, 10.9], [-16.7, 12.4], [-17.2, 14.7], [-16.5, 16.2], [-16.1, 18.6]],
  [[44.1, -25], [47.1, -24.9], [48.5, -20], [49.9, -15.5], [49.2, -12], [47.9, -13.6], [44.4, -16.3], [43.3, -22]],
  // Asiatische Inseln
  [[130, 31.3], [131.4, 31.4], [132, 33.2], [133.7, 33.4], [135.2, 33.5], [135.8, 34.6], [137, 34.6], [139, 34.9], [140.9, 35.7], [140.6, 36.9], [141, 38.3], [142, 39.6], [141.4, 41.4], [140.2, 41.2], [140, 39.6], [139.6, 38.2], [138.5, 37.4], [136.8, 37.1], [135.6, 35.6], [133, 35.5], [131, 34.4], [130, 33.5], [129.8, 32.6]],
  [[140, 41.5], [141.1, 41.8], [143.2, 42], [145.5, 43.3], [144.7, 44.1], [142, 45.4], [141.6, 44.3], [140.3, 43.3]],
  [[120.1, 23], [121, 25.2], [121.9, 24.6], [120.8, 21.9]],
  [[79.8, 6.5], [80, 9.6], [81.4, 8.5], [81.8, 6.6], [80.6, 5.9]],
  [[120, 18.5], [122.2, 18.5], [122, 16], [124, 14], [123, 13], [120.6, 14.3], [120, 16.5]],
  [[122, 7.3], [125.5, 9.6], [126.5, 7], [125.4, 5.6], [123.5, 7.6]],
  [[95.3, 5.5], [98, 4], [100.5, 1.5], [104, -1.7], [106, -5.8], [104.5, -5.9], [101.5, -3], [100, -1], [98, 1.7]],
  [[105.2, -6.8], [106.5, -6], [110.5, -6.9], [114.5, -7.8], [114.4, -8.7], [110, -8.2], [106.5, -7.4]],
  [[109, 1.8], [110.5, 1.6], [111.5, 2.6], [113.3, 3.5], [115.5, 5.3], [117, 6.9], [119.2, 5.4], [118.2, 4.2], [118.5, 1], [117.6, 0.4], [116.5, -2.3], [116, -3.9], [114.5, -3.6], [111.7, -3], [110.2, -2.9], [109, -0.3]],
  [[131, -1.2], [134, -0.9], [137, -1.6], [141, -2.6], [145, -4.4], [147.6, -6.1], [150.3, -10.3], [147, -10], [144, -7.7], [141, -9.1], [138, -8.3], [137.6, -5.2], [134, -3.9], [132, -2.8]],
  // Australien, Neuseeland
  [[113.5, -22], [114, -26], [115, -34], [118, -35], [123, -33.8], [126, -32.3], [131, -31.5], [135.5, -34.8], [138, -35.6], [140, -38], [144, -38.3], [146.4, -39], [150, -37.5], [150.9, -34.4], [153.1, -30.5], [153, -25.5], [151, -23.4], [149, -21.4], [146.3, -19], [145.4, -15], [143.5, -14], [142.5, -10.7], [141.6, -13], [141.4, -16.5], [140.6, -17.5], [139, -17.3], [136.7, -15.9], [136, -13], [132.5, -11.4], [130, -12.6], [129, -15], [126.2, -14], [124, -16.4], [122.2, -18], [121.2, -19.5], [116.7, -20.6]],
  [[144.6, -40.7], [148.3, -40.9], [148, -43.2], [146, -43.6]],
  [[172.7, -34.4], [174.5, -36], [176, -37.6], [178.5, -37.7], [177, -39.3], [176, -41.3], [174.7, -41.3], [175.2, -39.8], [173.8, -39.2], [174.6, -37]],
  [[172.7, -40.5], [174.3, -41.7], [173, -43.6], [171.2, -44.5], [170.6, -45.9], [169, -46.6], [166.5, -46], [168.3, -44], [170.8, -42.7]],
];
const LAND_PATH = LAND.map((poly) => `M${poly.map((p) => xy(p).map((v) => v.toFixed(1)).join(' ')).join(' L')} Z`).join(' ');

const place = (key) => (key === 'home' ? cat().home : cat().countries[key]);
const at = (key) => {
  const p = place(key);
  return xy([p.lon, p.lat]);
};
/** Flugbogen zwischen zwei Orten (leicht nach oben gewölbt). */
function arc(from, to) {
  const [a, b] = [at(from), at(to)];
  const d = Math.hypot(b[0] - a[0], b[1] - a[1]);
  const c = [(a[0] + b[0]) / 2, (a[1] + b[1]) / 2 - Math.min(80, d * 0.22)];
  return { a, b, c };
}
const onArc = ({ a, b, c }, t) => [
  (1 - t) ** 2 * a[0] + 2 * (1 - t) * t * c[0] + t ** 2 * b[0],
  (1 - t) ** 2 * a[1] + 2 * (1 - t) * t * c[1] + t ** 2 * b[1],
];
const PLANE = '<path d="M-7 0 L5 -1.4 L9 0 L5 1.4 Z M-1 0 L-4 -6 L-1.5 -6 L3 0 L-1.5 6 L-4 6 Z M-6 0 L-8 -3 L-6.6 -3 L-4.5 0 L-6.6 3 L-8 3 Z" fill="#fff" stroke="#1d1a2a" stroke-width="0.8"/>';

/** Weltkarte mit allen Ländern der Tournee-Liste, der Route und (beim Fliegen) dem Flugzeug. */
export function worldMap(tour, travel) {
  const countries = cat().countries;
  const visited = new Set((tour?.visits ?? []).map((v) => v.country));
  const target = tour?.country;
  const legs = tour?.legs ?? [];
  const now = serverNow();
  const routes = legs.map((l, i) => {
    const g = arc(l.from, l.to);
    const flying = i === legs.length - 1 && l.until > now;
    return `<path class="tr-route ${flying ? 'flying' : ''}" d="M${g.a.join(' ')} Q${g.c.join(' ')} ${g.b.join(' ')}"/>`;
  }).join('');
  const last = legs[legs.length - 1];
  const plane = last && last.until > now && travel
    ? (() => {
      const g = arc(last.from, last.to);
      return `<g class="tr-plane" data-from="${last.from}" data-to="${last.to}" data-at="${last.at}" data-until="${last.until}" transform="translate(${onArc(g, 0).join(' ')})">${PLANE}</g>`;
    })()
    : '';
  const pins = Object.entries(countries).map(([key, c]) => {
    const [x, y] = xy([c.lon, c.lat]);
    const state = key === target ? 'here' : visited.has(key) ? 'done' : '';
    return `<g class="tr-pin ${state}"><circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="${state ? 5 : 3}"/>${state ? `<text x="${(x + 8).toFixed(1)}" y="${(y + 4).toFixed(1)}">${esc(c.city)}</text>` : ''}</g>`;
  }).join('');
  const [hx, hy] = at('home');
  return `<svg class="tr-map" viewBox="0 ${TOP} ${W} ${H}" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
    <rect x="0" y="${TOP}" width="${W}" height="${H}" fill="#18263d"/>
    <path d="${LAND_PATH}" fill="#33435a" stroke="#4a5d78" stroke-width="0.8" stroke-linejoin="round"/>
    ${routes}${pins}
    <g class="tr-home"><path d="M${hx} ${hy - 7} l2 5 h5 l-4 3 l1.6 5 l-4.6 -3 l-4.6 3 l1.6 -5 l-4 -3 h5 Z"/></g>
    ${plane}
  </svg>`;
}

/** Jede Sekunde: Das Flugzeug rückt auf seinem Bogen vor. */
export function tick() {
  const now = serverNow();
  for (const el of document.querySelectorAll('.tr-plane')) {
    const g = arc(el.dataset.from, el.dataset.to);
    const t = Math.max(0, Math.min(1, (now - Number(el.dataset.at)) / (Number(el.dataset.until) - Number(el.dataset.at))));
    const [x, y] = onArc(g, t);
    const [x2, y2] = onArc(g, Math.min(1, t + 0.01));
    const angle = (Math.atan2(y2 - y, x2 - x) * 180) / Math.PI;
    el.setAttribute('transform', `translate(${x.toFixed(1)} ${y.toFixed(1)}) rotate(${angle.toFixed(0)})`);
  }
}

const genreName = (g) => cat().genres[g]?.name ?? g;
/** „In Japan“, „In den USA“ (am Satzanfang). */
const inCountry = (key) => {
  const c = place(key);
  const text = c.in ?? `in ${c.name}`;
  return text[0].toUpperCase() + text.slice(1);
};
/** „In Japan lieben sie Pop.“ und ob die Band das spielt. */
export function matchText(country, match) {
  const g = genreName(place(country).genre);
  const where = inCountry(country);
  return match
    ? `${where} lieben sie ${g}. Und genau das spielt ihr! Das wird ein besonderer Abend: mehr Ruhm und mehr Gage.`
    : `${where} lieben sie ${g}. Das spielt ihr nicht: Die Leute sind neugierig, aber so richtig begeistert wären sie bei ${g}.`;
}

const runningOf = (b, type) => app.state.running.find((a) => a.bandId === b.id && a.type === type);

/** Auf der Bandseite statt des Proberaums: Karte, was gerade los ist, und was als Nächstes geht. */
export function tourPanel(b) {
  const t = b.tour;
  const travel = runningOf(b, 'tourtravel') ?? runningOf(b, 'tour');
  const station = t.visits.length + (t.state === 'travel' || t.state === 'arrived' ? 1 : 0);
  const c = t.country ? place(t.country) : null;
  const lastVisit = t.visits[t.visits.length - 1];
  const left = t.stations - t.visits.length;
  let status = '';
  if (t.state === 'travel') {
    status = `<p class="tr-status">Unterwegs nach <b>${esc(c.city)}, ${esc(c.name)}</b>. Ankunft in <span data-until="${travel?.endsAt ?? 0}"></span></p>`;
  } else if (t.state === 'arrived') {
    const match = (b.genres ?? []).includes(c.genre);
    status = `<p class="tr-status">Angekommen in <b>${esc(c.city)}, ${esc(c.name)}</b>!</p>
      <p class="muted">${esc(matchText(t.country, match))}</p>
      <div class="tr-btns"><button class="btn primary" data-act="tourShow" data-id="${b.id}">Auftritt starten</button>
        <button class="btn ghost" data-act="tourHome" data-id="${b.id}">Gleich nach Hause fliegen</button></div>
      <p class="muted small">Die Band wartet hier noch <span data-until="${t.deadline}"></span>, dann fliegt sie von selbst nach Hause.</p>`;
  } else if (t.state === 'after') {
    const roll = t.roll ? place(t.roll.country) : null;
    status = `<p class="tr-status">Der Auftritt in <b>${esc(c.city)}</b> ist vorbei${lastVisit ? `: +${fmtGain(lastVisit.fame)} Ruhm, +${money(lastVisit.money)}` : ''}.</p>
      ${roll ? `<p class="tr-short">Ausgewürfelt: <b>${esc(roll.city)}, ${esc(roll.name)}</b>. Der Flug kostet ${money(t.roll.cost)}, dafür fehlt noch Geld. Verdient erst mehr oder fliegt nach Hause.</p>` : ''}
      <div class="tr-btns">${left > 0 ? `<button class="btn primary" data-act="tourNext" data-id="${b.id}">${roll ? `Weiter nach ${esc(roll.name)}` : 'Weiterreisen: nächstes Ziel auswürfeln'}</button>` : ''}
        <button class="btn ${left > 0 ? 'ghost' : 'primary'}" data-act="tourHome" data-id="${b.id}">Nach Hause fliegen</button></div>
      <p class="muted small">${left > 0 ? `Noch ${left} ${left === 1 ? 'Station' : 'Stationen'}. ` : 'Das war die letzte Station. '}Die Band wartet hier noch <span data-until="${t.deadline}"></span>, dann fliegt sie von selbst nach Hause.</p>`;
  } else if (t.state === 'home') {
    status = `<p class="tr-status">Auf dem Heimflug nach ${esc(cat().home.city)}. Zurück in <span data-until="${travel?.endsAt ?? 0}"></span></p>`;
  }
  return `<section class="tr-panel">
    <div class="tr-head"><div><div class="eyebrow">Auf Tournee</div><h2>${esc(b.name)}</h2></div>
      <span class="tr-count">Station ${Math.min(station, t.stations)} von ${t.stations}</span></div>
    ${worldMap(t, travel)}
    ${status}
    ${visitList(t.visits)}
  </section>`;
}

function visitList(visits) {
  if (!visits.length) return '';
  return `<ol class="tr-visits">${visits.map((v) => {
    const c = place(v.country);
    return `<li><span><b>${esc(c.city)}</b> <small>${esc(c.name)}</small></span>
      <span class="tr-genre ${v.match ? 'hit' : ''}">${genreGlyph(c.genre)}${v.match ? 'euer Genre' : esc(genreName(c.genre))}</span>
      <span>+${fmtGain(v.fame)} Ruhm</span><span>+${money(v.money)}</span>${v.meter !== null && v.meter !== undefined ? `<span class="muted">Publikum ${percent(v.meter / 100)}</span>` : ''}</li>`;
  }).join('')}</ol>`;
}

/** Würfel: Ländernamen laufen durch und bleiben beim Ziel stehen. */
function rollReveal(country, lines) {
  const keys = Object.keys(cat().countries);
  const c = place(country);
  const modal = openModal({
    className: 'tr-roll-modal',
    html: `<div class="eyebrow">Das nächste Ziel wird ausgewürfelt</div>
      <div class="tr-roll"><span class="tr-die">⚂</span><b class="tr-roll-name">…</b></div>
      <div class="tr-roll-after" hidden>${lines}</div>
      <div class="dialog-actions"><button class="btn primary" data-close hidden>Gute Reise!</button></div>`,
  });
  modal.body.addEventListener('click', (e) => {
    if (e.target.closest('[data-close]')) modal.close();
  });
  const name = modal.body.querySelector('.tr-roll-name');
  const die = modal.body.querySelector('.tr-die');
  const done = () => {
    name.textContent = `${c.city}, ${c.name}`;
    name.classList.add('landed');
    modal.body.querySelector('.tr-roll-after').hidden = false;
    modal.body.querySelector('[data-close]').hidden = false;
  };
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return done();
  let i = 0;
  let delay = 60;
  const faces = ['⚀', '⚁', '⚂', '⚃', '⚄', '⚅'];
  const step = () => {
    if (modal.closed) return;
    if (delay > 320) return done();
    const k = keys[(i += 1 + Math.floor(Math.random() * 3)) % keys.length];
    name.textContent = `${place(k).city}, ${place(k).name}`;
    die.textContent = faces[i % 6];
    delay *= 1.14;
    setTimeout(step, delay);
  };
  step();
}

/** Was nach dem Würfeln dasteht: Flugzeit, Kosten, Lieblings-Genre. */
function legLines(b, country, activity, cost) {
  const c = place(country);
  const match = (b.genres ?? []).includes(c.genre);
  return `<p>Flug: ${duration(activity.endsAt - activity.startedAt)}${cost ? ` · ${money(cost)}` : ''}</p>
    <p class="muted">${esc(inCountry(country))} lieben sie <b>${genreGlyph(c.genre)}${esc(genreName(c.genre))}</b>.${match ? ' Das spielt ihr! Daumen drücken für einen besonderen Abend.' : ''}</p>`;
}

/** Was eine Strecke für diese Band ungefähr kostet (nächstes und weitestes Ziel von zu Hause). */
function costRange(n) {
  const r = cat().tour;
  const home = cat().home;
  const km = (c) => {
    const rad = Math.PI / 180;
    const h = Math.sin(((c.lat - home.lat) * rad) / 2) ** 2 + Math.cos(home.lat * rad) * Math.cos(c.lat * rad) * Math.sin(((c.lon - home.lon) * rad) / 2) ** 2;
    return 2 * 6371 * Math.asin(Math.min(1, Math.sqrt(h)));
  };
  const costs = Object.values(cat().countries).map((c) => Math.round(Math.max(1, n) * (r.cost.perMember + km(c) * r.cost.perKm)));
  return [Math.min(...costs), Math.max(...costs)];
}

/** Hinweis am Knopf auf der Bandseite. */
export function tourHint(b) {
  return b.records ? 'Länder werden ausgewürfelt · gut für die Band' : 'erst mit einer Platte';
}

defineActions({
  /** Tournee starten: Länge wählen, dann wird das erste Ziel ausgewürfelt. */
  tourAsk({ id }) {
    const b = bandById(id);
    const n = b.memberIds.length;
    const [low, high] = costRange(n);
    const lengths = cat().tour.lengths;
    const names = { [lengths[0]]: 'Kurz', [lengths[1]]: 'Mittel', [lengths[2]]: 'Lang' };
    const why = !b.records ? 'Auf Tournee geht es erst mit einer Platte: Die Band braucht Lieder für die Auftritte.' : '';
    const modal = openModal({
      className: 'lb-rules-modal',
      html: `
        <div class="lb-cap">Anordnung</div>
        <h2>${esc(b.name)} geht auf Tournee</h2>
        <p class="muted">Die Band fliegt von Land zu Land und spielt in jedem Land einmal. Wohin es geht, wird ausgewürfelt. Jedes Land hat ein Lieblings-Genre: Passt es zur Band, wird der Abend besonders gut. Jeden Auftritt startet ihr selbst, danach entscheidet ihr: weiter oder nach Hause. Unterwegs wächst die Band zusammen.</p>
        <p class="muted">Die Flüge dauern lange (in Europa etwa eine Stunde, bis Australien vier Stunden) und kosten je Strecke ${money(low)} bis ${money(high)}. Der Heimflug ist bezahlt. So lange kann die Band nichts anderes machen.</p>
        <div class="lb-pick">${lengths.map((k) => `<button class="lb-pick-row" data-stations="${k}" ${off(why)}><span><b>${names[k] ?? ''}</b> · ${k} Stationen</span></button>`).join('')}</div>
        <div class="dialog-actions"><button class="btn ghost" data-close>Abbrechen</button></div>`,
    });
    modal.body.addEventListener('click', async (e) => {
      if (e.target.closest('[data-close]')) return modal.close();
      const row = e.target.closest('[data-stations]');
      if (!row || row.getAttribute('aria-disabled') === 'true') return;
      modal.close();
      const res = await mutate('tourStart', { bandId: b.id, stations: Number(row.dataset.stations) });
      if (res?.activity) {
        const t = bandById(b.id)?.tour;
        rollReveal(t.country, legLines(b, t.country, res.activity, t.legs[t.legs.length - 1]?.cost ?? 0));
      }
    });
  },
  /** Angekommen: Auftritt starten (auch aus der Ankunfts-Meldung). */
  async tourShow({ id }) {
    closeAllModals();
    if (await mutate('tourShow', { bandId: Number(id) })) navigate(`#/band/${id}`);
  },
  async tourNext({ id }) {
    closeAllModals();
    const b = bandById(id);
    const res = await mutate('tourNext', { bandId: Number(id) });
    if (!res) return;
    if (res.short) {
      const c = place(res.roll);
      rollReveal(res.roll, `<p class="tr-short">Der Flug nach ${esc(c.city)} kostet ${money(res.cost)}. So viel hat das Label gerade nicht. Verdient erst mehr oder fliegt nach Hause.</p>`);
      return;
    }
    rollReveal(res.roll, legLines(b, res.roll, res.activity, res.cost));
  },
  async tourHome({ id }) {
    closeAllModals();
    await mutate('tourHome', { bandId: Number(id) });
  },
});

// ---------- Meldungen (Ergebnis-Fenster) ----------

/** Angekommen: Wo, welches Genre dort geliebt wird, und los geht's. */
export function arrivalHtml(r) {
  const res = r.result;
  const b = bandById(r.bandId);
  const c = place(res.country);
  return `<div class="eyebrow">Tournee · Station ${res.station} von ${res.stations}</div>
    <h2>${esc(b?.name ?? '')} ist in ${esc(c.city)} angekommen!</h2>
    <p>${esc(c.name)} · ${genreGlyph(c.genre)}${esc(genreName(c.genre))}</p>
    <p class="muted">${esc(matchText(res.country, res.match))}</p>`;
}

export function arrivalActions(r) {
  return `<button class="btn ghost" data-close>Später</button><button class="btn primary" data-act="tourShow" data-id="${r.bandId}">Auftritt starten</button>`;
}

/** Nach einem Auftritt auf Tournee: welches Land, ob das Genre gepasst hat. */
export function gigExtras(res) {
  const t = res.tour;
  if (!t?.country) return '';
  return `<p class="muted">Tournee · Station ${t.station} von ${t.stations} · ${t.match ? `${esc(inCountry(t.country))} lieben sie euer Genre!` : `${esc(inCountry(t.country))} hätten sie lieber ${esc(genreName(place(t.country).genre))} gehört.`}</p>`;
}

export function gigActions(r) {
  const t = r.result.tour;
  if (!t?.country) return null;
  return `<button class="btn ghost" data-close>Später</button>
    <button class="btn ${t.last ? 'primary' : 'ghost'}" data-act="tourHome" data-id="${r.bandId}">Nach Hause</button>
    ${t.last ? '' : `<button class="btn primary" data-act="tourNext" data-id="${r.bandId}">Weiterreisen</button>`}`;
}

/** Zurück zu Hause: alle Stationen, was es gebracht hat. */
export function homeHtml(r) {
  const res = r.result;
  const b = bandById(r.bandId);
  const hits = (res.visits ?? []).filter((v) => v.match).length;
  return `<div class="eyebrow">Tournee vorbei</div>
    <h2>${esc(b?.name ?? '')} ist wieder zu Hause</h2>
    ${visitList(res.visits ?? [])}
    <div class="reward-grid">
      <div class="reward"><span>Ruhm</span><b>+${fmt1(res.fame ?? 0)}</b></div>
      <div class="reward reward-money"><span>Gagen</span><b>+${money(res.earned ?? 0)}</b></div>
      <div class="reward"><span>Flüge</span><b>−${money(res.spent ?? 0)}</b></div>
    </div>
    <p class="muted">${hits ? `${hits} ${hits === 1 ? 'Land liebte' : 'Länder liebten'} euer Genre. ` : ''}${res.together ? 'Die Tournee hat die Band zusammengeschweißt: Alle verstehen sich jetzt besser.' : ''}</p>`;
}
