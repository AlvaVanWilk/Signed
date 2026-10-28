/**
 * Bühnen der Auftrittsorte, gezeichnet wie der Proberaum (ruhige Flächen, dunkle Kontur, weicher Glanz).
 * Je größer der Ort, desto prächtiger: von der Ecke in der Kneipe bis zur Arena mit Leinwänden und Lichtstrahlen.
 * Die Mitte bleibt frei für die Band; das Publikum liegt als eigener Streifen vorne darüber (crowdSvg).
 */
const INK = '#1d1a2a';
const W = 960;
const H = 320;
/** Vorderkante der Bühne (die vordere Reihe steht hier). */
const TOP = 236;
/** Tiefe der Bühnenfläche: Hinter der Vorderkante ist so viel Boden zu sehen (die hintere Reihe steht hinten darauf, label.css .lb-venue). */
const DECK = 38;

function rng(seed) {
  let s = (seed * 2654435761) >>> 0 || 1;
  return () => {
    s ^= s << 13; s >>>= 0; s ^= s >>> 17; s ^= s << 5; s >>>= 0;
    return (s % 100000) / 100000;
  };
}
const st = (w = 3) => `stroke="${INK}" stroke-width="${w}" stroke-linejoin="round" stroke-linecap="round"`;
const box = (x, y, w, h, fill, rx = 3, sw = 3) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${rx}" fill="${fill}" ${st(sw)}/>`;
const gloss = (d, w = 3, o = 0.14) => `<path d="${d}" fill="none" stroke="#fff" stroke-width="${w}" stroke-linecap="round" opacity="${o}"/>`;
const beam = (x, y, x1, x2, color, o) => `<path d="M${x} ${y} L${x1} ${TOP + 4} L${x2} ${TOP + 4} Z" fill="${color}" opacity="${o}"/>`;
/** Scheinwerfer an einer Stange: kleines Gehäuse mit hellem Rand. */
const lamp = (x, y, color = '#ffe9a8') => `<g><rect x="${x - 9}" y="${y - 6}" width="18" height="14" rx="4" fill="#3a3448" ${st(2.4)}/><ellipse cx="${x}" cy="${y + 8}" rx="6" ry="2.4" fill="${color}"/></g>`;
/** Lautsprecher-Box. */
const speaker = (x, y, w, h) => `${box(x, y, w, h, '#2a2636', 4)}<circle cx="${x + w / 2}" cy="${y + h * 0.36}" r="${w * 0.18}" fill="#17141f" ${st(2)}/><circle cx="${x + w / 2}" cy="${y + h * 0.74}" r="${w * 0.28}" fill="#17141f" ${st(2)}/>${gloss(`M${x + 6} ${y + 6} H${x + w - 10}`, 2, 0.12)}`;
/** Bühne: Fläche mit Tiefe (hinten etwas dunkler), Kante und Vorderseite. */
const platform = (top, face, x0 = 0, x1 = W, lip = 10) => `
  <path d="M${x0} ${TOP - DECK} H${x1} V${TOP} H${x0} Z" fill="${top}" ${st(3)}/>
  <path d="M${x0} ${TOP - DECK} H${x1} V${TOP - DECK + 12} H${x0} Z" fill="#000" opacity="0.18"/>
  ${[0.35, 0.7].map((k) => `<path d="M${x0} ${TOP - DECK * k} H${x1}" stroke="#000" stroke-width="1.4" opacity="0.12"/>`).join('')}
  <path d="M${x0} ${TOP} H${x1} V${TOP + lip} H${x0} Z" fill="${top}" ${st(3)}/>
  <path d="M${x0} ${TOP + lip} H${x1} V${H} H${x0} Z" fill="${face}" ${st(3)}/>
  ${gloss(`M${x0 + 20} ${TOP + 3} H${x1 - 20}`, 2, 0.16)}`;

const SCENES = {
  // Kneipe: Holzwand, Lampe, Tresen am Rand, die Band steht auf einem Teppich.
  kneipe: (r) => `
    <rect width="${W}" height="${H}" fill="#5b3b2e"/>
    ${Array.from({ length: 13 }, (_, i) => `<path d="M${i * 80 + 20} 0 V${TOP}" stroke="#4a2f25" stroke-width="3"/>`).join('')}
    <path d="M0 150 H${W}" stroke="#4a2f25" stroke-width="5"/>
    ${[[110, 60], [820, 70]].map(([x, y]) => `${box(x, y, 70, 54, '#3b2a20', 4)}<rect x="${x + 8}" y="${y + 8}" width="54" height="38" rx="2" fill="${r() > 0.5 ? '#8a6a3c' : '#6a7a5a'}"/>`).join('')}
    <path d="M480 0 V40" stroke="${INK}" stroke-width="2.4"/>
    <path d="M452 64 Q480 30 508 64 Z" fill="#f2c14e" ${st(3)}/>
    <ellipse cx="480" cy="150" rx="260" ry="130" fill="#f2c14e" opacity="0.1"/>
    <path d="M0 ${TOP} H${W} V${H} H0 Z" fill="#8a5a3c" ${st(3)}/>
    ${Array.from({ length: 6 }, (_, i) => `<path d="M0 ${TOP + 14 + i * 14} H${W}" stroke="#7a4a2e" stroke-width="2"/>`).join('')}
    <path d="M250 ${TOP + 6} Q480 ${TOP - 8} 710 ${TOP + 6} L740 ${TOP + 40} Q480 ${TOP + 56} 220 ${TOP + 40} Z" fill="#8f3b45" ${st(3)}/>
    <path d="M262 ${TOP + 14} Q480 ${TOP + 2} 698 ${TOP + 14}" fill="none" stroke="#c9a86a" stroke-width="2" opacity="0.6"/>
    ${box(-10, 160, 150, 80, '#6e4030', 4)}${box(-10, 150, 160, 16, '#a06a3b', 4)}
    ${[30, 70, 110].map((x) => `<path d="M${x} 150 V128" stroke="${INK}" stroke-width="6"/><path d="M${x} 150 V128" stroke="#d6d9e0" stroke-width="3"/><circle cx="${x}" cy="125" r="5" fill="#e4572e" ${st(2)}/>`).join('')}
    ${speaker(860, 150, 70, 92)}`,

  // Club: dunkles Mauerwerk, Neonschrift, Discokugel, farbige Strahlen, niedrige Bühne.
  club: (r) => `
    <rect width="${W}" height="${H}" fill="#221a33"/>
    ${Array.from({ length: 9 }, (_, row) => Array.from({ length: 13 }, (_, i) => `<rect x="${i * 80 - (row % 2) * 40}" y="${row * 26}" width="76" height="22" rx="3" fill="#2a2140"/>`).join('')).join('')}
    ${beam(300, 0, 200, 420, '#ff4f8b', 0.16)}${beam(660, 0, 540, 780, '#4fd1ff', 0.16)}${beam(480, 40, 400, 560, '#f2c14e', 0.1)}
    <path d="M480 0 V22" stroke="#9a9aa8" stroke-width="2"/>
    <circle cx="480" cy="44" r="22" fill="#b9bccb" ${st(3)}/>
    <path d="M460 36 H500 M460 52 H500 M470 24 V64 M490 24 V64" stroke="#7d8092" stroke-width="1.6"/>
    ${gloss('M468 32 Q474 26 482 25', 3, 0.6)}
    <g transform="translate(120 70)"><rect width="170" height="54" rx="12" fill="#1a1328" ${st(3)}/>
      <text x="85" y="37" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="700" font-size="26" fill="none" stroke="#ff7ab0" stroke-width="2.4">LIVE</text>
      <text x="85" y="37" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="700" font-size="26" fill="#ffd1e4" opacity="0.5">LIVE</text></g>
    ${speaker(40, 150, 76, 96)}${speaker(844, 150, 76, 96)}
    ${platform('#3a2d55', '#1a1328')}
    ${Array.from({ length: 8 }, (_, i) => `<circle cx="${60 + i * 120 + r() * 30}" cy="${TOP + 5}" r="3" fill="${['#ff4f8b', '#4fd1ff', '#f2c14e'][i % 3]}" opacity="0.8"/>`).join('')}`,

  // Konzertsaal: roter Vorhang, Holzbühne, Lichtkegel von oben.
  saal: () => `
    <rect width="${W}" height="${H}" fill="#2a2030"/>
    <rect x="150" y="30" width="660" height="${TOP - 30}" fill="#3a2c45"/>
    ${beam(480, 0, 330, 630, '#f4efe6', 0.1)}
    <ellipse cx="480" cy="${TOP - DECK / 2}" rx="190" ry="14" fill="#f4efe6" opacity="0.22"/>
    <path d="M0 0 H200 Q186 120 214 ${TOP} H0 Z" fill="#b23a48" ${st(3)}/>
    <path d="M${W} 0 H760 Q774 120 746 ${TOP} H${W} Z" fill="#b23a48" ${st(3)}/>
    ${[40, 90, 140, 820, 870, 920].map((x) => `<path d="M${x} 20 Q${x + (x < 480 ? -8 : 8)} 130 ${x + (x < 480 ? 6 : -6)} ${TOP}" fill="none" stroke="#8a2836" stroke-width="4"/>`).join('')}
    ${gloss('M22 20 Q16 120 24 210', 4, 0.12)}${gloss(`M${W - 22} 20 Q${W - 16} 120 ${W - 24} 210`, 4, 0.12)}
    <path d="M0 0 H${W} V36 Q720 60 480 46 Q240 60 0 36 Z" fill="#c74656" ${st(3)}/>
    ${[160, 320, 640, 800].map((x) => `<path d="M${x} 44 Q${x + 10} 60 ${x} 70" fill="none" stroke="#e6b84f" stroke-width="3"/><circle cx="${x}" cy="72" r="4" fill="#e6b84f" ${st(1.6)}/>`).join('')}
    ${platform('#b07a48', '#7a4e2c', 0, W, 12)}
    ${Array.from({ length: 12 }, (_, i) => `<path d="M${i * 80 + 40} ${TOP + 12} V${H}" stroke="#6a4024" stroke-width="2"/>`).join('')}
    ${[200, 380, 580, 760].map((x) => `<path d="M${x - 14} ${TOP + 1} h28 l-4 -7 h-20 Z" fill="#2a2233" ${st(2)}/><ellipse cx="${x}" cy="${TOP - 5}" rx="8" ry="2" fill="#ffe9a8" opacity="0.8"/>`).join('')}`,

  // Halle: Traversen mit Scheinwerfern, große Leinwand, Boxentürme.
  halle: (r) => `
    <rect width="${W}" height="${H}" fill="#1b2030"/>
    ${beam(180, 26, 80, 300, '#f2c14e', 0.12)}${beam(360, 26, 280, 460, '#4fd1ff', 0.12)}${beam(600, 26, 500, 680, '#4fd1ff', 0.12)}${beam(780, 26, 660, 880, '#f2c14e', 0.12)}
    ${box(300, 56, 360, 150, '#3a86ff', 8)}
    <path d="M330 170 Q400 90 470 150 Q540 70 630 160" fill="none" stroke="#fff" stroke-width="5" opacity="0.5"/>
    <path d="M330 190 Q420 140 500 180 Q570 130 630 185" fill="none" stroke="#9fd0ff" stroke-width="4" opacity="0.5"/>
    ${gloss('M314 68 H470', 4, 0.25)}
    <path d="M20 22 H940" stroke="#4a5268" stroke-width="12"/><path d="M20 22 H940" stroke="#2a3042" stroke-width="5" stroke-dasharray="14 10"/>
    ${[120, 180, 360, 420, 540, 600, 780, 840].map((x, i) => lamp(x, 34, i % 2 ? '#bfe9ff' : '#ffe9a8')).join('')}
    ${speaker(30, 70, 84, 80)}${speaker(30, 152, 84, 86)}${speaker(846, 70, 84, 80)}${speaker(846, 152, 84, 86)}
    ${platform('#3b4256', '#232838')}
    ${Array.from({ length: 16 }, (_, i) => `<rect x="${i * 60 + 20}" y="${TOP + 22}" width="30" height="6" rx="3" fill="${r() > 0.5 ? '#4fd1ff' : '#f2c14e'}" opacity="0.5"/>`).join('')}`,

  // Arena: zwei Leinwände, Lichtstrahlen in die Kuppel, Lichterkette an der Bühnenkante.
  arena: (r) => `
    <rect width="${W}" height="${H}" fill="#121733"/>
    ${Array.from({ length: 28 }, () => `<circle cx="${(r() * W).toFixed(0)}" cy="${(r() * 120).toFixed(0)}" r="${(1 + r() * 1.6).toFixed(1)}" fill="#fff" opacity="${(0.25 + r() * 0.4).toFixed(2)}"/>`).join('')}
    <path d="M0 150 Q480 70 ${W} 150 V${TOP} H0 Z" fill="#232a55"/>
    ${Array.from({ length: 60 }, (_, i) => { const x = i * 16 + 8; const y = 150 - Math.sin((x / W) * Math.PI) * 70 + 16 + r() * 50; return `<circle cx="${x}" cy="${y.toFixed(0)}" r="2.2" fill="${['#ffd27a', '#ff9ab8', '#9fd0ff'][i % 3]}" opacity="${(0.35 + r() * 0.4).toFixed(2)}"/>`; }).join('')}
    ${beam(240, 0, 230, 330, '#ff4f8b', 0.14)}${beam(480, 0, 410, 550, '#f2c14e', 0.12)}${beam(720, 0, 630, 730, '#4fd1ff', 0.14)}
    <path d="M200 ${TOP} L100 0 H130 Z" fill="#ff4f8b" opacity="0.14"/><path d="M760 ${TOP} L860 0 H830 Z" fill="#4fd1ff" opacity="0.14"/>
    ${[[26, '#ff4f8b'], [784, '#4fd1ff']].map(([x, c]) => `${box(x, 60, 150, 100, '#1a1f3c', 8)}<rect x="${x + 10}" y="70" width="130" height="80" rx="4" fill="${c}" opacity="0.55"/>${gloss(`M${x + 16} 76 H${x + 80}`, 3, 0.3)}<path d="M${x + 75} 160 V${TOP}" stroke="#3b4256" stroke-width="8"/>`).join('')}
    <path d="M230 40 H730" stroke="#4a5268" stroke-width="12"/>
    ${[270, 340, 410, 550, 620, 690].map((x, i) => lamp(x, 52, i % 2 ? '#bfe9ff' : '#ffe9a8')).join('')}
    ${platform('#2b2d52', '#151933')}
    ${Array.from({ length: 30 }, (_, i) => `<circle cx="${i * 32 + 16}" cy="${TOP + 5}" r="3" fill="#ffe9a8" opacity="${i % 2 ? 0.9 : 0.5}"/>`).join('')}
    <ellipse cx="480" cy="${TOP + 2}" rx="300" ry="12" fill="#ffe9a8" opacity="0.08"/>`,

  // Festival unter freiem Himmel: Abendhimmel über Hügeln, Bühnendach auf Traversen, Wimpel, Leinwand, Boxentürme.
  openair: (r) => `
    <defs><linearGradient id="oa-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1b2150"/><stop offset="0.55" stop-color="#5a4377"/><stop offset="1" stop-color="#e58f5b"/></linearGradient></defs>
    <rect width="${W}" height="${H}" fill="url(#oa-sky)"/>
    ${Array.from({ length: 18 }, () => `<circle cx="${(r() * W).toFixed(0)}" cy="${(r() * 70).toFixed(0)}" r="${(0.8 + r() * 1.2).toFixed(1)}" fill="#fff" opacity="${(0.3 + r() * 0.4).toFixed(2)}"/>`).join('')}
    <path d="M0 196 Q120 160 260 182 T560 176 T820 170 T${W} 186 V${TOP} H0 Z" fill="#2c3050"/>
    <path d="M0 212 Q200 190 420 206 T${W} 204 V${TOP} H0 Z" fill="#232742"/>
    <rect x="150" y="62" width="660" height="${TOP - 62}" fill="#15131f"/>
    ${beam(300, 64, 210, 420, '#f2c14e', 0.12)}${beam(660, 64, 540, 750, '#ff8fc0', 0.12)}${beam(480, 64, 400, 560, '#9fd0ff', 0.1)}
    ${box(370, 84, 220, 104, '#2b6fd6', 6)}
    <path d="M392 160 Q440 110 482 146 Q528 100 572 152" fill="none" stroke="#fff" stroke-width="4" opacity="0.45"/>
    ${gloss('M382 94 H480', 3, 0.25)}
    ${[112, 808].map((x) => `${box(x, 34, 40, TOP - 34, '#3b4256', 3)}${Array.from({ length: 7 }, (_, i) => `<path d="M${x + 4} ${44 + i * 28} L${x + 36} ${58 + i * 28} M${x + 36} ${44 + i * 28} L${x + 4} ${58 + i * 28}" stroke="#5a6480" stroke-width="2"/>`).join('')}`).join('')}
    <path d="M96 30 H864 L836 62 H124 Z" fill="#242131" ${st(3)}/>
    <path d="M130 46 H830" stroke="#4a5268" stroke-width="6" stroke-dasharray="12 8"/>
    ${[200, 270, 340, 620, 690, 760].map((x, i) => lamp(x, 70, i % 2 ? '#bfe9ff' : '#ffe9a8')).join('')}
    <path d="M124 62 Q480 104 836 62" fill="none" stroke="${INK}" stroke-width="1.6"/>
    ${Array.from({ length: 16 }, (_, i) => { const x = 150 + i * 42; const y = 62 + Math.sin(((x - 124) / 712) * Math.PI) * 21; return `<path d="M${x} ${y.toFixed(1)} l16 1 l-8 16 Z" fill="${['#f2c14e', '#ff6f91', '#4fd1ff', '#7bd389'][i % 4]}" ${st(1.4)}/>`; }).join('')}
    ${speaker(18, 120, 76, 116)}${speaker(866, 120, 76, 116)}
    ${platform('#3a3448', '#211d2c')}
    ${Array.from({ length: 22 }, (_, i) => `<circle cx="${i * 44 + 22}" cy="${TOP + 5}" r="2.6" fill="#ffe9a8" opacity="${i % 2 ? 0.85 : 0.45}"/>`).join('')}`,

  // Zeltbühne: gestreifte Plane bis zur Spitze, zwei Masten, Lichterkette, Holzbühne.
  zelt: () => `
    <rect width="${W}" height="${H}" fill="#2e2130"/>
    ${Array.from({ length: 12 }, (_, i) => `<path d="M480 -60 L${i * 80} ${TOP} H${i * 80 + 80} Z" fill="${i % 2 ? '#a13f3c' : '#d9ccb4'}" opacity="0.92"/>`).join('')}
    <path d="M0 0 H${W} V${TOP} H0 Z" fill="#1a1020" opacity="0.38"/>
    <ellipse cx="480" cy="140" rx="300" ry="120" fill="#f2c14e" opacity="0.1"/>
    ${[296, 664].map((x) => `${box(x - 7, 10, 14, TOP - 10, '#7a5636', 3, 2.4)}${gloss(`M${x - 2} 20 V${TOP - 10}`, 2, 0.18)}`).join('')}
    ${[[0, 70, 300, 50], [296, 50, 664, 50], [664, 50, W, 70]].map(([x1, y1, x2, y2]) => { const mid = (x1 + x2) / 2; return `<path d="M${x1} ${y1} Q${mid} ${y1 + 46} ${x2} ${y2}" fill="none" stroke="${INK}" stroke-width="1.6"/>${Array.from({ length: 7 }, (_, i) => { const t = (i + 0.5) / 7; const x = (1 - t) * (1 - t) * x1 + 2 * (1 - t) * t * mid + t * t * x2; const y = (1 - t) * (1 - t) * y1 + 2 * (1 - t) * t * (y1 + 46) + t * t * y2; return `<circle cx="${x.toFixed(0)}" cy="${(y + 4).toFixed(0)}" r="4" fill="${['#ffd27a', '#ff9ab8', '#9fd0ff'][(i + x1) % 3]}" opacity="0.9"/>`; }).join('')}`; }).join('')}
    ${speaker(40, 140, 66, 96)}${speaker(854, 140, 66, 96)}
    ${platform('#a07448', '#6e4c2c', 0, W, 12)}
    ${Array.from({ length: 12 }, (_, i) => `<path d="M${i * 80 + 40} ${TOP + 12} V${H}" stroke="#5e3e22" stroke-width="2"/>`).join('')}`,
};

/** Bühne eines Orts als SVG-Text. key: kneipe, club, saal, halle, arena, openair (Festival), zelt (Festival); seed hält Kleinigkeiten fest. */
export function stageSvg(key, seed = 1) {
  const scene = SCENES[key] ?? SCENES.kneipe;
  return `<svg class="lb-stageart" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMax slice" aria-hidden="true">${scene(rng(seed))}</svg>`;
}

const COUNT = { kneipe: 5, club: 9, saal: 11, halle: 14, arena: 16, openair: 18, zelt: 11 };
/** Licht von der Bühne, das die Köpfe oben anstrahlt. */
const RIM = { kneipe: '#ffd27a', club: '#ff8fc0', saal: '#ffe9c0', halle: '#9fd0ff', arena: '#ffd27a', openair: '#ffc98f', zelt: '#ffd27a' };

/** Ein Mensch von hinten als Silhouette: Kopf mit Frisur, Hals, Schultern; dazu ein Lichtsaum oben. */
function person(x, y, k, r, color, rim, { hand = false, phone = false } = {}) {
  // Dieselben Verhältnisse wie die Signies: großer Kopf, schmale Schultern.
  const hr = 21 * k;
  const sh = 25 * k;
  const style = Math.floor(r() * 6);
  let hair = '';
  if (style === 1) hair = `<circle cx="${x + (r() - 0.5) * 6}" cy="${y - hr * 1.05}" r="${hr * 0.42}"/>`;
  if (style === 2) hair = `<path d="M${x + hr * 0.5} ${y - hr * 0.2} Q${x + hr * 1.3} ${y + hr * 0.6} ${x + hr * 0.7} ${y + hr * 1.5} Q${x + hr * 0.4} ${y + hr * 0.6} ${x + hr * 0.2} ${y + hr * 0.4} Z"/>`;
  if (style === 3) hair = [[-0.7, -0.5], [0, -0.95], [0.7, -0.5], [-0.95, 0.1], [0.95, 0.1]].map(([dx, dy]) => `<circle cx="${x + dx * hr}" cy="${y + dy * hr}" r="${hr * 0.5}"/>`).join('');
  if (style === 4) hair = `<path d="M${x - hr * 1.02} ${y - hr * 0.1} Q${x - hr} ${y - hr * 1.15} ${x} ${y - hr * 1.12} Q${x + hr} ${y - hr * 1.15} ${x + hr * 1.02} ${y - hr * 0.1} Z"/><path d="M${x + hr * 0.6} ${y - hr * 0.35} Q${x + hr * 1.6} ${y - hr * 0.45} ${x + hr * 1.75} ${y - hr * 0.2} L${x + hr * 0.8} ${y - hr * 0.05} Z"/>`;
  if (style === 5) hair = `<path d="M${x - hr * 1.05} ${y + hr * 0.2} Q${x - hr * 1.2} ${y + hr * 1.3} ${x - hr * 0.6} ${y + hr * 1.6} L${x + hr * 0.6} ${y + hr * 1.6} Q${x + hr * 1.2} ${y + hr * 1.3} ${x + hr * 1.05} ${y + hr * 0.2} Z"/>`;
  const lean = (r() - 0.5) * 3;
  // Hand neben dem Kopf hochgestreckt (bei den großen Köpfen nicht höher als anderthalb Köpfe).
  const hx = x + hr * 1.25 + lean;
  const hy = y - hr * 1.45;
  const arm = hand ? `<path d="M${x + sh * 0.8} ${y + hr * 1.5} Q${x + hr * 1.55} ${y} ${hx} ${hy}" fill="none" stroke="${color}" stroke-width="${8 * k}" stroke-linecap="round"/>
    <ellipse cx="${hx}" cy="${hy - 2 * k}" rx="${4.8 * k}" ry="${5.8 * k}"/>` : '';
  const glow = phone ? `<rect x="${hx - 4 * k}" y="${hy - 16 * k}" width="${8 * k}" height="${12 * k}" rx="${2 * k}" fill="#dff3ff" opacity="0.9"/>
    <ellipse cx="${hx}" cy="${hy - 10 * k}" rx="${14 * k}" ry="${12 * k}" fill="#dff3ff" opacity="0.12"/>` : '';
  return `<g class="crowd-bob" style="--d:${(-r() * 1.4).toFixed(2)}s">
    <g fill="${color}">
      ${arm}
      <path d="M${x - sh} 130 V${y + hr * 1.7} C${x - sh} ${y + hr * 1.25} ${x - sh * 0.6} ${y + hr * 1.0} ${x - hr * 0.3} ${y + hr * 0.95} L${x + hr * 0.3} ${y + hr * 0.95} C${x + sh * 0.6} ${y + hr * 1.0} ${x + sh} ${y + hr * 1.25} ${x + sh} ${y + hr * 1.7} V130 Z"/>
      <ellipse cx="${x}" cy="${y}" rx="${hr}" ry="${hr * 1.08}"/>${hair}
    </g>
    <path d="M${x - hr * 0.8} ${y - hr * 0.62} Q${x} ${y - hr * 1.32} ${x + hr * 0.8} ${y - hr * 0.62}" fill="none" stroke="${rim}" stroke-width="${2.2 * k}" stroke-linecap="round" opacity="0.35"/>
    ${glow}
  </g>`;
}

/**
 * Publikum vorne als Silhouetten im Gegenlicht (keine Gesichter, keine bunten Figuren): hinten kleiner und heller,
 * vorne größer und dunkler. Die Köpfe wippen im Takt (CSS .crowd-bob). In Halle und Arena gehen Hände hoch,
 * in der Arena leuchten Handys.
 */
export function crowdSvg(key, seed = 1) {
  const r = rng(seed + 31);
  const n = COUNT[key] ?? 6;
  const rim = RIM[key] ?? RIM.kneipe;
  const big = key === 'halle' || key === 'arena' || key === 'openair';
  let out = '';
  for (const [row, k, color, yBase, count] of [[0, 0.74, '#2a2238', 52, Math.round(n * 1.15)], [1, 1, '#120e1a', 72, n]]) {
    const step = W / count;
    for (let i = 0; i < count; i++) {
      const x = step * (i + 0.5) + (r() - 0.5) * step * 0.5;
      const hand = big && r() > (row ? 0.6 : 0.7);
      out += person(x, yBase + r() * 6, k, r, color, rim, { hand, phone: key === 'arena' && hand && r() > 0.45 });
    }
  }
  return `<svg class="lb-crowd" viewBox="0 0 ${W} 110" preserveAspectRatio="xMidYMax slice" aria-hidden="true">${out}</svg>`;
}

/**
 * Stellt die Band auf die Bühnenkante: Das Bild wird je nach Fenstergröße verschieden skaliert (slice, unten bündig),
 * also wird der Abstand nach unten nachgemessen und angepasst. Läuft für jede Bühne, sobald sie erscheint oder ihre Größe ändert.
 */
function fit(box) {
  const stageEl = box.querySelector('.fig-stage');
  const fig = stageEl?.querySelector('.fig');
  if (!fig) return;
  const b = box.getBoundingClientRect();
  if (!b.width || !b.height) return;
  const scale = Math.max(b.width / W, b.height / H);
  // Füße knapp hinter der Vorderkante der Bühnenoberfläche (sie ist 10 Einheiten tief).
  const want = b.bottom - (H - TOP - 6) * scale;
  const r = fig.getBoundingClientRect();
  const feet = r.bottom - (r.height * 12) / 150;
  const pad = parseFloat(getComputedStyle(stageEl).paddingBottom) || 0;
  const next = Math.max(0, pad + (feet - want));
  if (Math.abs(next - pad) > 0.5) stageEl.style.paddingBottom = `${next.toFixed(1)}px`;
}

if (typeof window !== 'undefined' && window.ResizeObserver && window.MutationObserver) {
  const sizes = new ResizeObserver((entries) => entries.forEach((e) => fit(e.target)));
  const seen = new WeakSet();
  const scan = () => document.querySelectorAll('.lb-venue').forEach((el) => {
    if (seen.has(el)) return;
    seen.add(el);
    sizes.observe(el);
  });
  new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
  scan();
}
