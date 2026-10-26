/**
 * Die vier Produzenten: feste Personen, immer dieselben (nie zufällig), gezeichnet im Stil der Signies (großer Kopf, kleiner Körper,
 * dunkle Kontur), aber mit Merkmalen, die kein Signie hat. Je größer der Produzent, desto besonderer sieht er aus –
 * bis auf den größten: barfuß, langer Bart, Yoga-Kleidung, rote Sonnenbrille.
 * Kopf im Koordinatensystem des Kartenbilds (Mitte 50/42), wie in figure.js 1,5-fach auf die Figur gesetzt.
 */
const INK = '#2a2233';
const SHADE = 'fill="#140f1e" opacity="0.16"';
const LIGHT = 'fill="#fff" opacity="0.18"';
const STROKE = `stroke="${INK}" stroke-width="2" stroke-linejoin="round"`;
const line = (d, w = 1.2, c = INK) => `<path d="${d}" fill="none" stroke="${c}" stroke-width="${w}" stroke-linecap="round"/>`;
const headAt = (inner) => `<g transform="translate(60 44) scale(1.5) translate(-50 -42)">${inner}</g>`;

const face = (skin, { eyes = 'dots', mouth = 'smile', cheeks = true } = {}) => `
  <g stroke="${INK}" stroke-width="1.2"><ellipse cx="31.5" cy="45" rx="3.5" ry="4.5" fill="${skin}"/><ellipse cx="68.5" cy="45" rx="3.5" ry="4.5" fill="${skin}"/>
  <ellipse cx="50" cy="43" rx="19" ry="18" fill="${skin}"/></g>
  <path d="M64.5 31 Q70.6 42 66.4 53 Q60.4 60.4 50 61 Q63.6 54.6 64.5 31 Z" fill="#140f1e" opacity="0.09"/>
  ${eyes === 'dots' ? `<ellipse cx="43" cy="45" rx="2.3" ry="2.8" fill="${INK}"/><ellipse cx="57" cy="45" rx="2.3" ry="2.8" fill="${INK}"/><circle cx="43.8" cy="44" r="0.8" fill="#fff"/><circle cx="57.8" cy="44" r="0.8" fill="#fff"/>` : ''}
  ${cheeks ? '<ellipse cx="38" cy="51" rx="3.2" ry="1.9" fill="#ff8fa3" opacity="0.45"/><ellipse cx="62" cy="51" rx="3.2" ry="1.9" fill="#ff8fa3" opacity="0.45"/>' : ''}
  ${mouth === 'smile' ? line('M45.5 52.5 Q50 56 54.5 52.5', 1.3) : ''}
  ${mouth === 'grin' ? `<path d="M44.5 51.5 Q50 58.5 55.5 51.5 Z" fill="#fff" stroke="${INK}" stroke-width="1.2" stroke-linejoin="round"/>` : ''}`;

/** Arm im Ärmel mit Hand. */
const arm = (d, hand, skin, sleeve, w = 7) => `
  <path d="${d}" fill="none" stroke="${INK}" stroke-width="${w + 3}" stroke-linecap="round"/>
  <path d="${d}" fill="none" stroke="${sleeve}" stroke-width="${w}" stroke-linecap="round"/>
  <circle cx="${hand[0]}" cy="${hand[1]}" r="4.2" fill="${skin}" stroke="${INK}" stroke-width="1.6"/>`;

const legs = (color) => `
  <path d="M46 106 Q46 103 51.5 103 Q57 103 57 106 L56.4 133 Q51.5 135.5 46.6 133 Z" fill="${color}"/>
  <path d="M63 106 Q63 103 68.5 103 Q74 103 74 106 L73.4 133 Q68.5 135.5 63.6 133 Z" fill="${color}"/>`;
const shoe = (x, color = INK, sole = '#5a5068') => `<path d="M${x - 8} 137.6 Q${x - 8.4} 131.4 ${x - 1} 131.2 Q${x + 7.6} 131 ${x + 8} 137.6 Z" fill="${color}"/>
  <path d="M${x - 8} 137.6 L${x + 8} 137.6" stroke="${sole}" stroke-width="1.6" stroke-linecap="round"/>`;
const torso = (color) => `<path d="M43 76 Q60 68 77 76 L81 108 Q60 115 39 108 Z" fill="${color}"/>`;

/** 1 – Nachwuchs: junger Beatbastler mit Kappe verkehrt herum, großen Kopfhörern um den Hals, Hoodie und Laptop. */
function mo() {
  const skin = '#8d5a3b';
  const hoodie = '#e0a72e';
  return `
    <g ${STROKE}>${legs('#3b4a6b')}${shoe(50, '#f4f1ea', '#e0475a')}${shoe(70, '#f4f1ea', '#e0475a')}${torso(hoodie)}</g>
    <path d="M70 75.5 Q74 75.5 77 76 L81 108 Q76 110.6 70.5 111.6 Q75 94 70 75.5 Z" ${SHADE}/>
    <path d="M47 96 H73 L71 106 H49 Z" fill="#c98f1c" stroke="${INK}" stroke-width="1.2"/>
    ${line('M56 76 L55 88', 1)}${line('M64 76 L65 88', 1)}<circle cx="55" cy="88.5" r="1" fill="${INK}"/><circle cx="65" cy="88.5" r="1" fill="${INK}"/>
    <!-- Kopfhörer um den Hals -->
    <path d="M44 74 Q60 86 76 74" fill="none" stroke="${INK}" stroke-width="4.4" stroke-linecap="round"/>
    <path d="M44 74 Q60 86 76 74" fill="none" stroke="#e0475a" stroke-width="2.4" stroke-linecap="round"/>
    <rect x="38.5" y="69" width="9" height="11" rx="4" fill="#2b2d42" stroke="${INK}" stroke-width="1.4"/><rect x="72.5" y="69" width="9" height="11" rx="4" fill="#2b2d42" stroke="${INK}" stroke-width="1.4"/>
    <!-- Laptop unterm Arm, mit Stickern -->
    <g transform="rotate(-8 30 96)"><rect x="17" y="86" width="22" height="15" rx="2" fill="#c9ccd4" stroke="${INK}" stroke-width="1.6"/>
      <circle cx="24" cy="92" r="2.4" fill="#4fd1ff"/><rect x="29" y="94" width="6" height="3.4" rx="1" fill="#ff4f8b"/><path d="M26 97 l1 2 2 .2 -1.5 1.4 .4 2 -1.9-1 -1.9 1 .4-2 -1.5-1.4 2-.2z" fill="#ffd25a"/></g>
    ${arm('M44 80 Q36 90 32 96', [32, 96], skin, hoodie)}
    ${arm('M76 80 Q82 92 78 102', [78, 102], skin, hoodie)}
    <path d="M52.5 74.2 Q60 82 67.5 74.2 Z" fill="${skin}" stroke="${INK}" stroke-width="1.4"/>
    ${headAt(`
      <g fill="#1f1612">${[[35, 33, 6], [42, 29, 6], [58, 29, 6], [65, 33, 6], [33, 40, 4.4], [67, 40, 4.4]].map(([x, y, r]) => `<circle cx="${x}" cy="${y}" r="${r}"/>`).join('')}</g>
      ${face(skin, { mouth: 'grin' })}
      <!-- Kappe verkehrt herum: Schirm hinten links -->
      <path d="M31.5 35 C32 22 68 22 68.5 35 C60 30.5 40 30.5 31.5 35 Z" fill="#3a86ff" stroke="${INK}" stroke-width="1.2"/>
      <path d="M31.8 33.4 C27 33 22.6 34.4 21.4 36.6 C25.6 37.6 29.6 37 32.4 35.6 Z" fill="#2a5fc0" stroke="${INK}" stroke-width="1.1"/>
      <path d="M44 26.5 Q50 24.6 56 26.5" fill="none" stroke="#fff" stroke-width="1" opacity="0.6"/>
      <circle cx="40" cy="50" r="0.6" fill="#5a3420"/><circle cx="42.5" cy="51.4" r="0.6" fill="#5a3420"/><circle cx="60" cy="50" r="0.6" fill="#5a3420"/>`)}`;
}

/** 2 – Etabliert: Profi mit grauer Strähne im Bob, schwarzem Blazer, Headset, Tablet und feiner Brille. */
function vera() {
  const skin = '#f0c8a8';
  const blazer = '#262433';
  return `
    <g ${STROKE}>${legs('#262433')}${shoe(50, '#7a2338')}${shoe(70, '#7a2338')}${torso(blazer)}</g>
    <path d="M54 72 L60 92 L66 72 Z" fill="#f4f1ea" stroke="${INK}" stroke-width="1.2"/>
    <path d="M46 75 L57 84 L54 100 L44 92 Z" fill="#33303f" stroke="${INK}" stroke-width="1.1"/><path d="M74 75 L63 84 L66 100 L76 92 Z" fill="#33303f" stroke="${INK}" stroke-width="1.1"/>
    <circle cx="60" cy="96" r="1.2" fill="#c9ccd4"/><circle cx="60" cy="102" r="1.2" fill="#c9ccd4"/>
    <path d="M70 75.5 Q74 75.5 77 76 L81 108 Q76 110.6 70.5 111.6 Q75 94 70 75.5 Z" ${SHADE}/>
    <!-- Tablet mit Pegelanzeige -->
    <g transform="rotate(10 80 98)"><rect x="73" y="86" width="16" height="22" rx="2.4" fill="#1c1d28" stroke="${INK}" stroke-width="1.6"/>
      ${[0, 1, 2, 3, 4].map((i) => `<rect x="${75.6 + i * 2.4}" y="${100 - [6, 10, 13, 8, 4][i]}" width="1.6" height="${[6, 10, 13, 8, 4][i]}" fill="${['#3ddc97', '#3ddc97', '#ffd25a', '#3ddc97', '#3ddc97'][i]}"/>`).join('')}</g>
    ${arm('M44 80 Q40 94 46 102', [46, 102], skin, blazer)}
    ${arm('M76 80 Q82 90 80 98', [80, 98], skin, blazer)}
    <path d="M52.5 74.2 Q60 82 67.5 74.2 Z" fill="${skin}" stroke="${INK}" stroke-width="1.4"/>
    ${headAt(`
      <path d="M26.5 44 C25 18 75 18 73.5 44 L74 57 Q68 60.5 64.5 56 L64 46 L36 46 L35.5 56 Q32 60.5 26 57 Z" fill="#2a1f1c"/>
      ${face(skin)}
      <path d="M30 44 C29 22 71 22 70 44 C68 37 64 33 58 31.4 C55 35 47 37.6 38 37 C35 38.4 32 41 30 44 Z" fill="#2a1f1c"/>
      <path d="M44 24.4 C52 22.4 62 25 66 31 C62 28.6 56 27.4 50 28.4 Z" fill="#c9ccd4"/>
      <g fill="none" stroke="#8a6418" stroke-width="0.9"><rect x="38.4" y="42.4" width="9.2" height="6" rx="2.6"/><rect x="52.4" y="42.4" width="9.2" height="6" rx="2.6"/><path d="M47.6 45 L52.4 45"/></g>
      <!-- Headset -->
      <path d="M30.5 41 C30 22 70 22 69.5 41" fill="none" stroke="${INK}" stroke-width="1.6"/>
      <rect x="27.6" y="40" width="5" height="8" rx="2" fill="#3a3a44" stroke="${INK}" stroke-width="0.9"/>
      <path d="M30 47.5 Q33 56 42 55" fill="none" stroke="${INK}" stroke-width="1"/><circle cx="42.6" cy="55" r="1.4" fill="#3a3a44"/>
      ${line('M40 39.4 Q43 38.4 46 39.6', 1)}${line('M54 39.6 Q57 38.4 60 39.4', 1)}`)}`;
}

/** 3 – Hitproduzent: viel Gold – Goldketten, Ringe, Glitzerjacke, goldene Brille, Goldzahn und weiße Sneaker mit Goldkappen. */
function diamond() {
  const skin = '#5a3a2a';
  const jacket = '#5b2a86';
  const gold = '#f2c14e';
  return `
    <defs><linearGradient id="pr-gold" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="#fff3b8"/><stop offset="0.5" stop-color="#e8b84a"/><stop offset="1" stop-color="#a87a1a"/></linearGradient>
      <pattern id="pr-glitter" width="5" height="5" patternUnits="userSpaceOnUse"><circle cx="1.2" cy="1.2" r="0.6" fill="#e6c8ff"/><circle cx="3.6" cy="3.4" r="0.45" fill="#fff"/></pattern></defs>
    <g ${STROKE}>${legs('#1c1a20')}${shoe(50, '#f4f1ea', gold)}${shoe(70, '#f4f1ea', gold)}${torso(jacket)}</g>
    <path d="M43 76 Q60 68 77 76 L81 108 Q60 115 39 108 Z" fill="url(#pr-glitter)" opacity="0.8"/>
    <path d="M54 72 L60 98 L66 72 Z" fill="#1c1a20" stroke="${INK}" stroke-width="1.2"/>
    <path d="M44 74 Q50 70 56 73 L52 84 Z M76 74 Q70 70 64 73 L68 84 Z" fill="#f4f1ea" stroke="${INK}" stroke-width="1"/>
    <!-- Goldketten -->
    <path d="M51 75 Q60 92 69 75" fill="none" stroke="#7a5410" stroke-width="3.6" stroke-linecap="round"/>
    <path d="M51 75 Q60 92 69 75" fill="none" stroke="url(#pr-gold)" stroke-width="2.4" stroke-linecap="round" stroke-dasharray="2.2 0.8"/>
    <path d="M49 76 Q60 102 71 76" fill="none" stroke="url(#pr-gold)" stroke-width="1.8" stroke-linecap="round"/>
    <circle cx="60" cy="98" r="5" fill="url(#pr-gold)" stroke="#7a5410" stroke-width="1"/><path d="M57.4 98 L60 95.4 L62.6 98 L60 101 Z" fill="#fff" opacity="0.9"/>
    ${arm('M44 80 Q34 88 30 80', [30, 80], skin, jacket)}
    ${arm('M76 80 Q86 88 90 80', [90, 80], skin, jacket)}
    <circle cx="28.4" cy="78.6" r="1.4" fill="${gold}" stroke="#7a5410" stroke-width="0.6"/><circle cx="91.6" cy="78.6" r="1.4" fill="${gold}" stroke="#7a5410" stroke-width="0.6"/>
    <rect x="33" y="80.4" width="5" height="3" rx="1" fill="url(#pr-gold)" stroke="#7a5410" stroke-width="0.6" transform="rotate(30 35.5 82)"/>
    <path d="M52.5 74.2 Q60 82 67.5 74.2 Z" fill="${skin}" stroke="${INK}" stroke-width="1.4"/>
    ${headAt(`
      <path d="M31.2 40 C30 22 70 22 68.8 40 C62 32 38 32 31.2 40 Z" fill="#141014"/>
      ${face(skin, { mouth: 'none', cheeks: false })}
      <path d="M31.2 40 C30 24 70 24 68.8 40 C66 33 60 30 50 30 C40 30 34 33 31.2 40 Z" fill="#141014"/>
      <path d="M36 34 Q50 27 64 34" fill="none" stroke="${gold}" stroke-width="0.8" opacity="0.9"/>
      <!-- goldene Sonnenbrille -->
      <path d="M36 41 H64 L62.6 48 Q57 50 52.4 47 L50 44.6 L47.6 47 Q43 50 37.4 48 Z" fill="#1c1a20" stroke="url(#pr-gold)" stroke-width="1.3"/>
      <path d="M39 42.4 L42 42.4 M53 42.4 L56 42.4" stroke="#fff" stroke-width="0.8" opacity="0.6"/>
      <path d="M44 52 Q50 57 56 52 Q50 55 44 52 Z" fill="#fff" stroke="${INK}" stroke-width="1"/><rect x="50.2" y="52.6" width="2.2" height="2" fill="url(#pr-gold)"/>
      <circle cx="31.5" cy="49.6" r="1.4" fill="${gold}" stroke="#7a5410" stroke-width="0.4"/><circle cx="68.5" cy="49.6" r="1.4" fill="${gold}" stroke="#7a5410" stroke-width="0.4"/>`)}`;
}

/** 4 – die Legende: barfuß, sehr langer grauweißer Bart und lange Haare, weite helle Yoga-Kleidung, runde rote Sonnenbrille, Mala-Kette. */
function legend() {
  const skin = '#e8b896';
  const hair = '#d8d2c6';
  const hairShade = '#9a9286';
  const shirt = '#7d8f86';
  const foot = (x) => `<path d="M${x - 7} 138 Q${x - 8} 132 ${x - 1} 131.4 Q${x + 7} 131 ${x + 7.4} 137.4 Q${x} 139.4 ${x - 7} 138 Z" fill="${skin}" stroke="${INK}" stroke-width="1.6"/>
    ${[0, 1, 2, 3].map((i) => `<circle cx="${x + 3.4 - i * 2.4}" cy="${136.6 - (i === 0 ? 0.6 : 0)}" r="${i === 0 ? 1.3 : 1}" fill="${skin}" stroke="${INK}" stroke-width="0.8"/>`).join('')}`;
  return `
    <!-- weite Yogahose, unten gerafft -->
    <g ${STROKE}>
      <path d="M44 104 Q44 100 52 100 Q58 100 58 104 L58 128 Q51.5 133 45 128 Z" fill="#c8bfae"/>
      <path d="M62 104 Q62 100 68 100 Q76 100 76 104 L75 128 Q68.5 133 62 128 Z" fill="#c8bfae"/>
    </g>
    <path d="M45.4 126 Q51.5 129 57.6 126 M62.4 126 Q68.5 129 74.6 126" stroke="${INK}" stroke-width="1" fill="none" opacity="0.5"/>
    ${foot(51)}${foot(69)}
    <g ${STROKE}><path d="M41 76 Q60 67 79 76 L84 110 Q60 117 36 110 Z" fill="${shirt}"/></g>
    <path d="M70 75.5 Q75 75.5 79 76 L84 110 Q78 112.6 71 113.6 Q76 94 70 75.5 Z" ${SHADE}/>
    ${arm('M43 80 Q38 96 48 104', [48, 104], skin, shirt, 8)}
    ${arm('M77 80 Q82 96 72 104', [72, 104], skin, shirt, 8)}
    <!-- Mala-Kette -->
    <g>${Array.from({ length: 13 }, (_, i) => { const t = i / 12; const x = 46 + t * 28; const y = 76 + Math.sin(t * Math.PI) * 30; return `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="1.5" fill="#8a4a2a" stroke="${INK}" stroke-width="0.5"/>`; }).join('')}</g>
    ${headAt(`
      <!-- lange Haare hinten bis über die Schultern -->
      <path d="M29 40 C27 18 73 18 71 40 L73 66 Q66 70 62 62 L50 60 L38 62 Q34 70 27 66 Z" fill="${hair}" stroke="${INK}" stroke-width="1.1"/>
      <path d="M30.6 50 L28.6 64 M69.4 50 L71.4 64" stroke="${hairShade}" stroke-width="1" fill="none"/>
      ${face(skin, { mouth: 'none' })}
      <!-- Stirn frei, Haar oben zurückgelegt und etwas schütter -->
      <path d="M31 38 C32 22 68 22 69 38 C66 30 58 27 50 27 C42 27 34 30 31 38 Z" fill="${hair}" stroke="${INK}" stroke-width="1"/>
      <path d="M33 38 Q31 46 33 52 M67 38 Q69 46 67 52" stroke="${hair}" stroke-width="3" fill="none"/>
      <!-- runde rote Sonnenbrille -->
      <g stroke="${INK}" stroke-width="0.9"><circle cx="43" cy="45" r="5" fill="#d0342c"/><circle cx="57" cy="45" r="5" fill="#d0342c"/></g>
      <path d="M48 45 L52 45" stroke="${INK}" stroke-width="1"/><path d="M40.6 42.6 Q42 41.6 43.6 42 M54.6 42.6 Q56 41.6 57.6 42" stroke="#fff" stroke-width="0.8" fill="none" opacity="0.7"/>
      ${line('M39.6 38.6 Q43 37.6 46 38.8', 1.4, hairShade)}${line('M54 38.8 Q57 37.6 60.4 38.6', 1.4, hairShade)}
      <!-- sehr langer, voller Bart bis auf die Brust -->
      <path d="M31 47 C30 60 34 70 40 76 C44 82 47 88 50 92 C53 88 56 82 60 76 C66 70 70 60 69 47 C66 56 60 56 56 54 Q50 52 44 54 C40 56 34 56 31 47 Z" fill="${hair}" stroke="${INK}" stroke-width="1.1"/>
      <path d="M41 60 Q43 65 46 70 M50 58 Q50 66 50 73 M59 60 Q57 65 54 70 M36 56 Q37.6 61 40 65 M64 56 Q62.4 61 60 65" stroke="${hairShade}" stroke-width="0.8" fill="none"/>
      <path d="M43 52 Q47 50 50 51.6 Q53 50 57 52 Q53 54.6 50 53.4 Q47 54.6 43 52 Z" fill="${hairShade}" stroke="${INK}" stroke-width="0.7"/>`)}`;
}

export const PRODUCERS = { mo, vera, diamond, legend };

/** Ganze Figur eines Produzenten (120 × 150 wie die Signies). */
export function producerFigure(key) {
  const draw = PRODUCERS[key];
  if (!draw) return '';
  return `<svg class="fig fig-producer pr-${key}" viewBox="0 0 120 150" aria-hidden="true"><ellipse cx="60" cy="141" rx="32" ry="5" fill="rgba(0,0,0,0.25)"/>${draw()}</svg>`;
}
