/**
 * Kleine Bilder der Auftrittsorte von außen im Stil der Signies: weiche Formen, dunkle Kontur, sanfter Glanz.
 * Wie es innen aussieht, sieht man erst beim Auftritt (stage.js).
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

/** Nachthimmel mit ein paar Sternen; jeder Ort zeigt sich von außen, wie man ihn auf dem Weg dorthin sieht. */
const sky = (top, bottom, stars) => `<rect width="120" height="80" fill="${top}"/><rect y="40" width="120" height="40" fill="${bottom}"/>
  ${stars.map(([x, y]) => `<circle cx="${x}" cy="${y}" r="0.7" fill="#fff" opacity="0.6"/>`).join('')}`;
const win = (x, y, w, h, lit = true) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="1" fill="${lit ? '#f6cf6a' : '#3a3550'}" ${S} stroke-width="1.2"/>${lit ? `<path d="M${x + w / 2} ${y} V${y + h} M${x} ${y + h / 2} H${x + w}" stroke="${INK}" stroke-width="0.8" opacity="0.6"/>` : ''}`;

const ART = {
  // Kneipe: kleines Fachwerkhaus an der Ecke, warmes Fenster, Schild mit Bierglas, Laterne.
  kneipe: `
    ${sky('#1c2240', '#232a4a', [[10, 8], [30, 14], [96, 6], [110, 18]])}
    <circle cx="100" cy="14" r="6" fill="#f4efe6" opacity="0.85"/><circle cx="102.5" cy="12.5" r="5" fill="#1c2240"/>
    <path d="M0 66 H120 V80 H0 Z" fill="#4a4458"/>
    ${[6, 20, 34, 48, 62, 76, 90, 104].map((x, i) => `<path d="M${x} ${70 + (i % 2) * 4} h10" stroke="#5a5468" stroke-width="1.4" stroke-linecap="round"/>`).join('')}
    <path d="M22 34 L52 14 L82 34 Z" fill="#8f3b35" ${S}/>
    <path d="M26 34 H78 V66 H26 Z" fill="#e8d8b8" ${S}/>
    <path d="M26 44 H78 M40 34 V66 M64 34 V66 M26 34 L40 44 M78 34 L64 44" stroke="#6b4128" stroke-width="1.8"/>
    ${win(30, 47, 8, 9)}${win(66, 47, 8, 9)}
    <path d="M45 66 V50 Q52 45 59 50 V66 Z" fill="#7a4a2e" ${S}/><circle cx="56.4" cy="58" r="0.9" fill="#f2c14e"/>
    <path d="M52 20 v4" stroke="${INK}" stroke-width="1"/>
    <ellipse cx="52" cy="40" rx="30" ry="22" fill="#f6cf6a" opacity="0.08"/>
    <path d="M80 40 H92" stroke="${INK}" stroke-width="1.4"/><path d="M88 40 V44 M92 40 V44" stroke="${INK}" stroke-width="0.8"/>
    <rect x="85" y="44" width="10" height="11" rx="2" fill="#c9a86a" ${S} stroke-width="1.1"/>
    <path d="M87.5 47 h4 v5.6 h-4 Z" fill="#f2c14e" ${S} stroke-width="0.8"/><path d="M87.5 47 h4 v1.4 h-4 Z" fill="#fff"/>
    <path d="M108 66 V44" stroke="${INK}" stroke-width="1.8"/><path d="M104.6 44 h6.8 l-1.4 -5 h-4 Z" fill="#f6cf6a" ${S} stroke-width="1.1"/>
    <ellipse cx="108" cy="44" rx="9" ry="7" fill="#f6cf6a" opacity="0.14"/>`,

  // Club: dunkles Backsteinhaus, Neonschild, Tür mit Licht, eine kleine Schlange davor.
  club: `
    ${sky('#15122a', '#1c1836', [[8, 6], [24, 12], [112, 9]])}
    <path d="M14 12 H106 V68 H14 Z" fill="#3a2a3a" ${S}/>
    ${Array.from({ length: 7 }, (_, r) => Array.from({ length: 6 }, (_, i) => `<rect x="${16 + i * 15 - (r % 2) * 7}" y="${15 + r * 7}" width="13" height="5" rx="1" fill="#46323f" opacity="${(i + r) % 3 ? 0.9 : 0.6}"/>`).join('')).join('')}
    <rect x="30" y="18" width="60" height="16" rx="5" fill="#1a1328" ${S} stroke-width="1.4"/>
    <text x="60" y="30.6" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="800" font-size="11" letter-spacing="2" fill="none" stroke="#ff7ab0" stroke-width="1.2">CLUB</text>
    <ellipse cx="60" cy="26" rx="34" ry="12" fill="#ff4f8b" opacity="0.12"/>
    ${win(22, 40, 10, 8, false)}${win(88, 40, 10, 8, false)}
    <path d="M50 68 V46 H70 V68 Z" fill="#1a1328" ${S}/>
    <path d="M53 46 V43 H67 V46" fill="none" stroke="${INK}" stroke-width="1.2"/><circle cx="60" cy="42" r="1.6" fill="#4fd1ff"/>
    <ellipse cx="60" cy="50" rx="12" ry="7" fill="#4fd1ff" opacity="0.14"/>
    <path d="M0 68 H120 V80 H0 Z" fill="#2a2638" ${S} stroke-width="1"/>
    ${[[78, '#c8a165'], [86, '#3b2a20'], [94, '#a0522d'], [102, '#e0c48a']].map(([x, c]) => `<path d="M${x - 4} 80 Q${x - 4} 71 ${x} 71 Q${x + 4} 71 ${x + 4} 80 Z" fill="#5a4a7a" ${S} stroke-width="1"/><circle cx="${x}" cy="67" r="3.4" fill="${c}" ${S} stroke-width="1"/>`).join('')}
    <path d="M73 70 H107" stroke="#c9a86a" stroke-width="1" opacity="0.6"/>`,

  // Konzertsaal: Säulen, Giebel, Lichterleiste über dem Eingang, breite Treppe.
  saal: `
    ${sky('#1a1f3a', '#212748', [[12, 7], [104, 10], [116, 4]])}
    <path d="M14 30 L60 10 L106 30 Z" fill="#e6dcc8" ${S}/>
    <circle cx="60" cy="23" r="3.4" fill="#c9a86a" ${S} stroke-width="1"/>
    <path d="M18 30 H102 V64 H18 Z" fill="#d8ccb4" ${S}/>
    <path d="M18 34 H102" stroke="${INK}" stroke-width="1.2"/>
    ${[30, 46, 74, 90].map((x) => `<path d="M${x - 3.6} 34 H${x + 3.6} V64 H${x - 3.6} Z" fill="#f2ece0" ${S} stroke-width="1.2"/><path d="M${x - 1.2} 36 V62" stroke="#c9bfa8" stroke-width="1"/>`).join('')}
    <path d="M52 64 V44 Q60 38 68 44 V64 Z" fill="#7a2836" ${S}/>
    <ellipse cx="60" cy="52" rx="16" ry="10" fill="#f6cf6a" opacity="0.16"/>
    <rect x="50" y="36" width="20" height="5" rx="1.5" fill="#2a2233" ${S} stroke-width="1"/>
    ${[52.5, 56, 59.5, 63, 66.5].map((x) => `<circle cx="${x + 0.5}" cy="38.5" r="0.9" fill="#f6cf6a"/>`).join('')}
    ${[[36, '#b23a48'], [80, '#3a86ff']].map(([x, c]) => `<rect x="${x}" y="44" width="8" height="12" rx="1" fill="${c}" ${S} stroke-width="1"/><path d="M${x + 2} 48 h4 M${x + 2} 51 h3" stroke="#fff" stroke-width="0.8" opacity="0.7"/>`).join('')}
    <path d="M10 64 H110 L114 70 H6 Z" fill="#c9bfa8" ${S} stroke-width="1.2"/><path d="M4 70 H116 L118 76 H2 Z" fill="#b8ad96" ${S} stroke-width="1.2"/>
    <path d="M0 76 H120 V80 H0 Z" fill="#3a3550"/>`,

  // Halle: breites Gebäude mit Bogendach, helle Glasfront, großes Banner, Laternen am Platz.
  halle: `
    ${sky('#141a30', '#1b2238', [[10, 6], [40, 4], [84, 8], [112, 12]])}
    <path d="M6 36 Q60 4 114 36 Z" fill="#5a6278" ${S}/>
    ${[24, 42, 60, 78, 96].map((x) => `<path d="M${x} ${(36 - 64 * ((x - 6) / 108) * (1 - (x - 6) / 108) + 2.4).toFixed(1)} V36" stroke="#3b4256" stroke-width="1.2"/>`).join('')}
    ${gloss('M26 29.4 Q40 24 54 22.8', 1.6, 0.25)}
    <path d="M8 36 H112 V66 H8 Z" fill="#3b4256" ${S}/>
    ${Array.from({ length: 10 }, (_, i) => `<rect x="${11 + i * 10}" y="40" width="8" height="${i === 4 || i === 5 ? 26 : 14}" fill="${i % 3 ? '#9fd0ff' : '#f6cf6a'}" opacity="0.85" ${S} stroke-width="0.8"/>`).join('')}
    <rect x="30" y="22" width="60" height="11" rx="2" fill="#3a86ff" ${S} stroke-width="1.2"/>
    <path d="M38 27.5 H82" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity="0.7" stroke-dasharray="6 3"/>
    <path d="M0 66 H120 V80 H0 Z" fill="#2a3042"/>
    ${[4, 116].map((x) => `<path d="M${x} 78 V54" stroke="${INK}" stroke-width="1.6"/><circle cx="${x}" cy="53" r="2.4" fill="#f6cf6a" ${S} stroke-width="1"/><ellipse cx="${x}" cy="56" rx="8" ry="6" fill="#f6cf6a" opacity="0.12"/>`).join('')}
    ${[30, 38, 82, 92].map((x, i) => `<circle cx="${x}" cy="74" r="2.2" fill="${HAIR[i]}" ${S} stroke-width="0.8"/>`).join('')}`,

  // Arena: großes Rund mit Fassadenbändern, Flutlichtmasten, Lichtschein über dem Dach.
  arena: `
    ${sky('#10142c', '#171c3a', [[8, 4], [30, 10], [70, 3], [110, 8]])}
    <ellipse cx="60" cy="26" rx="54" ry="14" fill="#ffe9a8" opacity="0.1"/>
    <path d="M30 26 L24 4 M90 26 L96 4" stroke="#ffe9a8" stroke-width="5" opacity="0.08"/>
    ${[[14, 70], [106, 70]].map(([x, y]) => `<path d="M${x} ${y} V18" stroke="#5a6278" stroke-width="2"/><rect x="${x - 6}" y="12" width="12" height="7" rx="1.5" fill="#d6d9e0" ${S} stroke-width="1"/>${[-3, 0, 3].map((d) => `<circle cx="${x + d}" cy="15.5" r="1.1" fill="#fff6c8"/>`).join('')}<path d="M${x} 19 L${x < 60 ? x + 26 : x - 26} 44 L${x < 60 ? x + 10 : x - 10} 46 Z" fill="#fff6c8" opacity="0.08"/>`).join('')}
    <path d="M18 42 Q18 26 60 24 Q102 26 102 42 V64 Q102 72 60 72 Q18 72 18 64 Z" fill="#3a4266" ${S}/>
    <path d="M18 42 Q60 52 102 42" fill="none" stroke="${INK}" stroke-width="1.2"/>
    <path d="M18 42 Q18 26 60 24 Q102 26 102 42 Q60 52 18 42 Z" fill="#4a5280"/>
    ${Array.from({ length: 13 }, (_, i) => { const x = 22 + i * 6.3; return `<path d="M${x} ${45 + Math.sin((i / 12) * Math.PI) * 4} V${66 + Math.sin((i / 12) * Math.PI) * 4}" stroke="#2b3152" stroke-width="1.4"/>`; }).join('')}
    <path d="M18 56 Q60 66 102 56" fill="none" stroke="#ff7ab0" stroke-width="1.6" opacity="0.8"/>
    <path d="M52 72 V62 Q60 58 68 62 V72 Z" fill="#1a1f3c" ${S} stroke-width="1.2"/>
    <ellipse cx="60" cy="66" rx="10" ry="6" fill="#4fd1ff" opacity="0.18"/>
    ${gloss('M28 32 Q42 27 56 26', 1.4, 0.25)}
    <path d="M0 72 H120 V80 H0 Z" fill="#232840"/>`,
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
