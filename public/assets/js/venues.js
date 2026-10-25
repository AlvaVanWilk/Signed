/**
 * Kleine Bilder der Auftrittsorte im Stil der Signies: weiche Formen, dunkle Kontur, sanfter Glanz und Schatten.
 * Bildfläche 120 × 80, die Ecken rundet das umgebende Kästchen per CSS ab.
 */
const INK = '#2a2233';
const S = `stroke="${INK}" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round"`;
const SHADE = 'fill="#140f1e" opacity="0.18"';
const gloss = (d, w = 1.6, o = 0.25) => `<path d="${d}" fill="none" stroke="#fff" stroke-width="${w}" stroke-linecap="round" opacity="${o}"/>`;

/** Reihe kleiner Köpfe von hinten (Publikum). */
const crowd = (y, from, to, step, colors, r = 4) => {
  let out = '';
  for (let x = from, i = 0; x <= to; x += step, i++) {
    const c = colors[i % colors.length];
    out += `<circle cx="${x}" cy="${y + (i % 2) * 1.2}" r="${r}" fill="${c}" ${S} stroke-width="1.2"/>`;
  }
  return out;
};
const HAIR = ['#3b2a20', '#c8a165', '#6b3d2a', '#2a2233', '#a0522d', '#e0c48a'];

const ART = {
  // Kneipe: Holz, Tresen mit Zapfhähnen, eine Lampe, kleine Ecke als Bühne mit Mikro.
  kneipe: `
    <rect width="120" height="80" fill="#5b3b2e"/>
    <path d="M0 0 H120 V12 H0 Z" fill="#4a2f25"/>
    ${[18, 42, 66, 90, 114].map((x) => `<path d="M${x} 12 V58" stroke="#4a2f25" stroke-width="1.4"/>`).join('')}
    <path d="M0 58 H120 V80 H0 Z" fill="#8a5a3c"/>
    <path d="M0 58 H120" ${S}/>
    <path d="M62 0 V14" stroke="${INK}" stroke-width="1.2"/>
    <path d="M54 22 Q62 12 70 22 Z" fill="#f2c14e" ${S}/>
    <ellipse cx="62" cy="40" rx="30" ry="22" fill="#f2c14e" opacity="0.12"/>
    <path d="M72 40 H116 Q118 40 118 42 V62 H72 Z" fill="#6e4030" ${S}/>
    <path d="M70 38 H120 V43 H70 Z" fill="#a06a3b" ${S}/>
    ${gloss('M74 39.6 H104', 1.2, 0.3)}
    ${[82, 92, 102].map((x) => `<path d="M${x} 38 V30" stroke="${INK}" stroke-width="2.6"/><path d="M${x} 38 V30" stroke="#d6d9e0" stroke-width="1.2"/><circle cx="${x}" cy="29" r="2" fill="#e4572e" ${S} stroke-width="1"/>`).join('')}
    <path d="M110 30 h6 v8 h-6 Z" fill="#f2c14e" ${S} stroke-width="1.1"/><path d="M110 30 h6 v2.4 h-6 Z" fill="#fff"/>
    <path d="M6 58 L8 52 H46 L48 58 Z" fill="#7a4a2e" ${S}/>
    <path d="M27 52 V36" stroke="${INK}" stroke-width="1.6"/>
    <circle cx="27" cy="34" r="3" fill="#9a9aa8" ${S} stroke-width="1.2"/>
    <path d="M72 62 H118 V66 H72 Z" ${SHADE}/>`,

  // Club: dunkel, Discokugel, farbige Lichtkegel, Leute auf der Tanzfläche.
  club: `
    <rect width="120" height="80" fill="#241a36"/>
    <path d="M60 14 L20 80 H44 Z" fill="#ff4f8b" opacity="0.18"/>
    <path d="M60 14 L76 80 H100 Z" fill="#4fd1ff" opacity="0.18"/>
    <path d="M60 14 L48 80 H66 Z" fill="#f2c14e" opacity="0.12"/>
    <path d="M60 0 V7" stroke="#9a9aa8" stroke-width="1"/>
    <circle cx="60" cy="14" r="7.5" fill="#b9bccb" ${S}/>
    <path d="M53 12 H67 M53 16 H67 M57 7.5 V20.5 M63 7.5 V20.5" stroke="#7d8092" stroke-width="0.7"/>
    ${gloss('M56 11 Q58 8.6 61 8.4', 1.4, 0.7)}
    <path d="M0 60 H120 V80 H0 Z" fill="#1a1328"/>
    ${[14, 34, 54, 74, 94, 114].map((x, i) => `<rect x="${x - 9}" y="61" width="18" height="6" fill="${['#3a2d55', '#4a2a4f', '#2d3a55'][i % 3]}" opacity="0.9"/>`).join('')}
    ${crowd(70, 10, 112, 13, HAIR, 4.4)}
    <circle cx="16" cy="22" r="1.2" fill="#fff" opacity="0.5"/><circle cx="98" cy="30" r="1.2" fill="#fff" opacity="0.5"/><circle cx="84" cy="12" r="1" fill="#fff" opacity="0.4"/>`,

  // Saal: roter Vorhang, Holzbühne, ein Lichtkreis.
  saal: `
    <rect width="120" height="80" fill="#2e2338"/>
    <path d="M24 16 H96 V56 H24 Z" fill="#3a2c45"/>
    <path d="M14 58 L24 50 H96 L106 58 Z" fill="#9a6a40" ${S}/>
    ${gloss('M28 52 H70', 1, 0.3)}
    <ellipse cx="60" cy="53" rx="18" ry="3.4" fill="#f4efe6" opacity="0.35"/>
    <path d="M60 0 L44 53 H76 Z" fill="#f4efe6" opacity="0.08"/>
    <path d="M0 0 H30 Q28 30 34 52 Q20 56 0 54 Z" fill="#b23a48" ${S}/>
    <path d="M120 0 H90 Q92 30 86 52 Q100 56 120 54 Z" fill="#b23a48" ${S}/>
    <path d="M10 4 Q8 30 12 52 M20 4 Q18 30 24 53 M110 4 Q112 30 108 52 M100 4 Q102 30 96 53" fill="none" stroke="#8a2836" stroke-width="1.6"/>
    ${gloss('M5 6 Q4 26 6 44', 1.6, 0.18)}${gloss('M115 6 Q116 26 114 44', 1.6, 0.18)}
    <path d="M0 0 H120 V10 Q90 16 60 12 Q30 16 0 10 Z" fill="#c74656" ${S}/>
    <path d="M0 58 H120 V80 H0 Z" fill="#241b2c"/>
    ${[0, 1].map((r) => `<g>${[12, 30, 48, 66, 84, 102].map((x) => `<path d="M${x - 7 + r * 4} ${66 + r * 8} h14 v6 h-14 Z" fill="#7a2836" ${S} stroke-width="1.1"/>`).join('')}</g>`).join('')}`,

  // Halle: hohes Dach mit Trägern, Leinwand, Scheinwerfer, viele Köpfe.
  halle: `
    <rect width="120" height="80" fill="#1f2433"/>
    <path d="M0 14 L60 2 L120 14" fill="none" stroke="#3b4256" stroke-width="2"/>
    ${[12, 30, 48, 72, 90, 108].map((x) => `<path d="M${x} ${14 - (x < 60 ? x : 120 - x) / 5} V18" stroke="#3b4256" stroke-width="1.2"/>`).join('')}
    <path d="M6 18 H114" stroke="#3b4256" stroke-width="2"/>
    ${[18, 38, 82, 102].map((x, i) => `<path d="M${x} 18 L${x + (i < 2 ? 10 : -10)} 60 H${x + (i < 2 ? 22 : 2)} Z" fill="${i % 2 ? '#4fd1ff' : '#f2c14e'}" opacity="0.13"/><rect x="${x - 3}" y="17" width="6" height="4" rx="1.4" fill="#d6d9e0" ${S} stroke-width="1"/>`).join('')}
    <rect x="38" y="24" width="44" height="22" rx="2" fill="#3a86ff" ${S}/>
    <path d="M44 40 Q52 30 60 38 Q68 28 76 38" fill="none" stroke="#fff" stroke-width="1.6" opacity="0.6"/>
    ${gloss('M41 27 H58', 1.2, 0.35)}
    <path d="M22 50 H98 V56 H22 Z" fill="#2b2d42" ${S}/>
    <path d="M0 56 H120 V80 H0 Z" fill="#171b27"/>
    ${crowd(62, 6, 116, 9, HAIR, 3.6)}
    ${crowd(71, 2, 118, 9, ['#2a2233', '#3b2a20', '#4a3a30'], 4.2)}`,

  // Arena: Ränge im Rund, Bühne in der Mitte, Lichtstrahlen in den Himmel.
  arena: `
    <rect width="120" height="80" fill="#151a33"/>
    ${[[14, 8], [36, 5], [86, 9], [104, 4], [62, 3]].map(([x, y]) => `<circle cx="${x}" cy="${y}" r="0.9" fill="#fff" opacity="0.6"/>`).join('')}
    <path d="M44 46 L22 0 H30 Z" fill="#ff4f8b" opacity="0.2"/>
    <path d="M60 46 L58 0 H66 Z" fill="#f2c14e" opacity="0.18"/>
    <path d="M76 46 L94 0 H102 Z" fill="#4fd1ff" opacity="0.2"/>
    <ellipse cx="60" cy="62" rx="62" ry="24" fill="#2b2d52" ${S}/>
    <ellipse cx="60" cy="62" rx="50" ry="18" fill="#353a66" ${S} stroke-width="1.2"/>
    <ellipse cx="60" cy="63" rx="38" ry="12" fill="#1f6f5c" ${S} stroke-width="1.2"/>
    ${Array.from({ length: 22 }, (_, i) => { const a = Math.PI * (0.06 + 0.88 * i / 21); return `<circle cx="${(60 - Math.cos(a) * 56).toFixed(1)}" cy="${(60 - Math.sin(a) * 19).toFixed(1)}" r="1.7" fill="${HAIR[i % 6]}"/>`; }).join('')}
    ${Array.from({ length: 18 }, (_, i) => { const a = Math.PI * (0.08 + 0.84 * i / 17); return `<circle cx="${(60 - Math.cos(a) * 44).toFixed(1)}" cy="${(61 - Math.sin(a) * 14).toFixed(1)}" r="1.6" fill="${HAIR[(i + 3) % 6]}"/>`; }).join('')}
    <path d="M44 52 H76 L80 58 H40 Z" fill="#2b2d42" ${S}/>
    <path d="M46 46 H74 V52 H46 Z" fill="#3a86ff" ${S} stroke-width="1.2"/>
    ${gloss('M48 47.6 H60', 1, 0.4)}
    ${crowd(68, 34, 86, 6, HAIR, 2.4)}`,
};

/** Bild des Orts als SVG-Text; unbekannte Orte bekommen die Kneipe. */
export function venueArt(key) {
  return `<svg class="venue-art" viewBox="0 0 120 80" aria-hidden="true" preserveAspectRatio="xMidYMid slice">${ART[key] ?? ART.kneipe}</svg>`;
}

/**
 * Publikum als Reihe kleiner Köpfe: so viele farbig, wie der Ort voraussichtlich voll wird, der Rest leer.
 * fill 0…1.
 */
export function crowdMeter(fill, n = 10) {
  const on = Math.round(Math.max(0, Math.min(1, fill)) * n);
  let out = '';
  for (let i = 0; i < n; i++) {
    const x = 6 + i * 11;
    out += i < on
      ? `<g><circle cx="${x}" cy="7" r="4" fill="${HAIR[i % 6]}" ${S} stroke-width="1.1"/><path d="M${x - 5} 17 Q${x} 10 ${x + 5} 17 Z" fill="#9d93c4" ${S} stroke-width="1.1"/></g>`
      : `<g opacity="0.45"><circle cx="${x}" cy="7" r="4" fill="none" stroke="#a59cbf" stroke-width="1.1" stroke-dasharray="2 2"/><path d="M${x - 5} 17 Q${x} 10 ${x + 5} 17" fill="none" stroke="#a59cbf" stroke-width="1.1" stroke-dasharray="2 2"/></g>`;
  }
  return `<svg class="crowd-meter" viewBox="0 0 ${n * 11 + 1} 18" aria-hidden="true">${out}</svg>`;
}
