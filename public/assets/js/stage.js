/**
 * Bühnen der Auftrittsorte, gezeichnet wie der Proberaum (ruhige Flächen, dunkle Kontur, weicher Glanz).
 * Je größer der Ort, desto prächtiger: von der Ecke in der Kneipe bis zur Arena mit Leinwänden und Lichtstrahlen.
 * Die Mitte bleibt frei für die Band; das Publikum liegt als eigener Streifen vorne darüber (crowdSvg).
 */
const INK = '#1d1a2a';
const W = 960;
const H = 320;
/** Oberkante der Bühne: Hier stehen die Füße der Figuren ungefähr. */
const TOP = 236;

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
/** Bühnenkante: Oberfläche und Vorderseite. */
const platform = (top, face, x0 = 0, x1 = W, lip = 10) => `
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
    <ellipse cx="480" cy="${TOP + 6}" rx="170" ry="10" fill="#f4efe6" opacity="0.22"/>
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
};

/** Bühne eines Orts als SVG-Text. key: kneipe, club, saal, halle, arena; seed hält Kleinigkeiten fest. */
export function stageSvg(key, seed = 1) {
  const scene = SCENES[key] ?? SCENES.kneipe;
  return `<svg class="lb-stageart" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMax slice" aria-hidden="true">${scene(rng(seed))}</svg>`;
}

const HAIR = ['#3b2a20', '#c8a165', '#6b3d2a', '#2a2233', '#a0522d', '#e0c48a', '#7a4a2e', '#d9a27e'];
const COUNT = { kneipe: 7, club: 14, saal: 18, halle: 24, arena: 30 };

/**
 * Publikum vorne als Streifen: Köpfe und Schultern von hinten, die im Takt wippen (CSS, Klasse .crowd-bob).
 * In Halle und Arena gehen Hände hoch, in der Arena leuchten Handys.
 */
export function crowdSvg(key, seed = 1) {
  const r = rng(seed + 31);
  const n = COUNT[key] ?? 7;
  const step = W / n;
  let out = '';
  for (let i = 0; i < n; i++) {
    const x = step * (i + 0.5) + (r() - 0.5) * step * 0.4;
    const y = 42 + r() * 10;
    const hair = HAIR[Math.floor(r() * HAIR.length)];
    const shirt = `hsl(${Math.floor(r() * 360)} 30% 32%)`;
    const hand = (key === 'halle' || key === 'arena') && r() > 0.55;
    const phone = key === 'arena' && hand && r() > 0.4;
    out += `<g class="crowd-bob" style="--d:${(-r() * 1.2).toFixed(2)}s">
      ${hand ? `<path d="M${x + 16} ${y + 10} L${x + 22} ${y - 22}" stroke="${shirt}" stroke-width="9" stroke-linecap="round"/><circle cx="${x + 22}" cy="${y - 24}" r="5.5" fill="#c98a62" stroke="${INK}" stroke-width="2"/>` : ''}
      ${phone ? `<rect x="${x + 17}" y="${y - 40}" width="10" height="15" rx="2" fill="#2a2233" stroke="${INK}" stroke-width="1.6"/><rect x="${x + 19}" y="${y - 38}" width="6" height="10" fill="#dff3ff"/>` : ''}
      <path d="M${x - 26} 80 Q${x - 24} ${y + 14} ${x} ${y + 12} Q${x + 24} ${y + 14} ${x + 26} 80 Z" fill="${shirt}" stroke="${INK}" stroke-width="2.4"/>
      <circle cx="${x}" cy="${y}" r="15" fill="${hair}" stroke="${INK}" stroke-width="2.4"/>
      <path d="M${x - 8} ${y - 8} Q${x - 2} ${y - 12} ${x + 4} ${y - 11}" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" opacity="0.1"/>
    </g>`;
  }
  return `<svg class="lb-crowd" viewBox="0 0 ${W} 80" preserveAspectRatio="xMidYMax slice" aria-hidden="true">${out}</svg>`;
}
