/**
 * Gezeichnete Instrumenten-Symbole statt Emojis, illustriert mit Verläufen und Glanz: Gitarre, E-Gitarre und Bass
 * sehen verschieden aus. Für Stellen, die nur Text können (Auswahllisten, Canvas), bleibt das Emoji aus dem Katalog.
 * Gezeichnet auf 64 × 64; Gitarren, Bass und Geige stehen aufrecht und werden schräg gestellt.
 */
import { tierArt } from './gearart.js';

const LINE = 'stroke-linejoin="round" stroke-linecap="round"';

/** Aufrechtes Instrument schräg stellen und verkleinern, damit es ins Quadrat passt. */
const tilt = (inner, angle, scale) => `<g transform="rotate(${angle} 32 32) translate(32 32) scale(${scale}) translate(-32 -32)">${inner}</g>`;

/** Bünde und Saiten eines Griffbretts (aufrecht, x = Mitte). */
function fretboard(x, top, bottom, width, strings, inlay = '#e9e2d0') {
  const frets = [];
  for (let y = top + 3, step = 3.2; y < bottom - 1; y += step, step *= 0.97) frets.push(y);
  const half = width / 2 - 0.5;
  return `
    ${frets.map((y) => `<path d="M${x - half} ${y.toFixed(1)} H${x + half}" stroke="#c9c2b4" stroke-width="0.35"/>`).join('')}
    ${[2, 4, 6].map((i) => frets[i] ? `<circle cx="${x}" cy="${(frets[i] + 1.4).toFixed(1)}" r="0.5" fill="${inlay}"/>` : '').join('')}
    ${Array.from({ length: strings }, (_, i) => {
      const sx = x - half + 0.4 + (i * (width - 1.8)) / (strings - 1);
      return `<path d="M${sx.toFixed(2)} ${top} V${bottom}" stroke="#eef0f4" stroke-width="${strings === 4 ? 0.45 : 0.28}"/>`;
    }).join('')}`;
}

const acousticGuitar = (hue) => tilt(`
  <defs>
    <radialGradient id="ag-top" cx="45%" cy="62%" r="62%">${hue === null
      ? '<stop offset="0" stop-color="#f8d79c"/><stop offset="0.65" stop-color="#e2a456"/><stop offset="1" stop-color="#a8611f"/>'
      : `<stop offset="0" stop-color="hsl(${hue} 75% 78%)"/><stop offset="0.65" stop-color="hsl(${hue} 62% 52%)"/><stop offset="1" stop-color="hsl(${hue} 60% 26%)"/>`}</radialGradient>
    <linearGradient id="ag-neck" x1="0" x2="1"><stop offset="0" stop-color="#3a2416"/><stop offset="0.5" stop-color="#5a3a22"/><stop offset="1" stop-color="#3a2416"/></linearGradient>
  </defs>
  <path d="M29.4 1 H34.6 L35.6 9.4 H28.4 Z" fill="#4a2c18"/>
  <g fill="#d9dbe2" stroke="#6b6f7a" stroke-width="0.3"><circle cx="27.6" cy="3" r="1"/><circle cx="27.4" cy="5.4" r="1"/><circle cx="27.2" cy="7.8" r="1"/><circle cx="36.4" cy="3" r="1"/><circle cx="36.6" cy="5.4" r="1"/><circle cx="36.8" cy="7.8" r="1"/></g>
  <rect x="29.9" y="9.2" width="4.2" height="21" fill="url(#ag-neck)"/>
  ${fretboard(32, 9.6, 30, 4.2, 6)}
  <path d="M32 21 C25.4 21 23 25 24 29.4 C24.6 31.8 25.6 33.2 24.4 35.2 C20.2 38 18.6 42 19.6 47 C21.4 55 26.8 59 32 59 C37.2 59 42.6 55 44.4 47 C45.4 42 43.8 38 39.6 35.2 C38.4 33.2 39.4 31.8 40 29.4 C41 25 38.6 21 32 21 Z" fill="url(#ag-top)" stroke="#6b3a12" stroke-width="0.9"/>
  <path d="M32 22.4 C26.6 22.4 24.8 25.6 25.6 29.2 C26.2 31.6 27 33.4 25.8 35.6 C22 38.4 20.8 42 21.6 46.6 C23.2 53.8 27.8 57.4 32 57.4 C36.2 57.4 40.8 53.8 42.4 46.6 C43.2 42 42 38.4 38.2 35.6 C37 33.4 37.8 31.6 38.4 29.2 C39.2 25.6 37.4 22.4 32 22.4 Z" fill="none" stroke="#f4e6c6" stroke-width="0.5" opacity="0.8"/>
  <path d="M36.4 33.6 C40 34.4 41.2 37.8 39.4 40.4 C37.6 42.6 35.2 41.4 35 39 Z" fill="#5a2c14" opacity="0.85"/>
  <circle cx="32" cy="36.2" r="4.6" fill="#3a2a1c" stroke="#7a4a1c" stroke-width="0.8"/>
  <circle cx="32" cy="36.2" r="3.4" fill="#1e140c"/>
  <circle cx="32" cy="36.2" r="5.6" fill="none" stroke="#5a3a20" stroke-width="0.5"/>
  <rect x="27.4" y="47" width="9.2" height="2.6" rx="1.2" fill="#3a2416"/>
  <path d="M28.6 47.8 H35.4" stroke="#efe6d2" stroke-width="0.6"/>
  ${Array.from({ length: 6 }, (_, i) => `<path d="M${(30.3 + i * 0.68).toFixed(2)} 30 V48" stroke="#eef0f4" stroke-width="0.28"/>`).join('')}
  <path d="M25.6 44 C26.6 50 29.4 53.4 32 54" fill="none" stroke="#fff" stroke-width="1" opacity="0.25"/>`, 40, 0.8);

const electricGuitar = (hue = 18) => tilt(`
  <defs>
    <radialGradient id="eg-burst" cx="46%" cy="62%" r="60%"><stop offset="0" stop-color="hsl(${hue + 30} 95% 66%)"/><stop offset="0.45" stop-color="hsl(${hue} 86% 54%)"/><stop offset="0.8" stop-color="hsl(${hue - 6} 76% 40%)"/><stop offset="1" stop-color="hsl(${hue - 10} 72% 16%)"/></radialGradient>
    <linearGradient id="eg-maple" x1="0" x2="1"><stop offset="0" stop-color="#e8c896"/><stop offset="0.5" stop-color="#f6dfb4"/><stop offset="1" stop-color="#d9b37c"/></linearGradient>
    <linearGradient id="eg-guard" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#e2e0da"/></linearGradient>
  </defs>
  <g transform="translate(0 -5)">
  <path d="M29.6 0.6 C31.4 0.2 33.6 0.6 34.6 1.8 L35.4 10.6 L30.6 10.6 C29.4 7.6 28.6 4 29.6 0.6 Z" fill="url(#eg-maple)" stroke="#9a7444" stroke-width="0.5"/>
  <g fill="#d9dbe2" stroke="#6b6f7a" stroke-width="0.3">${[1.8, 3.4, 5, 6.6, 8.2, 9.8].map((y) => `<circle cx="${(29 + (y - 1.8) * 0.06).toFixed(2)}" cy="${y}" r="0.75"/>`).join('')}</g>
  <rect x="30.1" y="10.4" width="3.8" height="27" fill="url(#eg-maple)" stroke="#9a7444" stroke-width="0.4"/>
  ${fretboard(32, 10.6, 36, 3.8, 6, '#2a2233')}
  </g>
  <g transform="translate(32 44) scale(0.8 0.9) translate(-32 -44)">
  <path d="M25 30.2 C22.6 25.4 17.2 26 18.2 31.4 C18.8 34.6 20 36 18 39.4 C14.6 45 16 53.6 23.2 56.4 C28.6 58.4 35.6 58.4 40.6 56.4 C47.6 53.6 48.6 45.2 45.6 40.2 C43.8 37.4 44.4 34.8 46 31.8 C48.2 27.4 44.4 24.6 41.6 27.8 C39.4 30.4 36.6 31.6 32 31.6 C28.8 31.6 26.6 31.2 25 30.2 Z" fill="url(#eg-burst)" stroke="#3a0e06" stroke-width="0.9"/>
  <path d="M24.4 33.6 C26.8 34.6 29.6 34.8 33 34.8 L35.8 34.6 L37 44 L36.4 50.4 C32.6 53.4 25.6 53 22.6 49.4 C20.6 46.8 21.4 42 23 39 C24 37.2 24.6 35.4 24.4 33.6 Z" fill="url(#eg-guard)" stroke="#a9a59a" stroke-width="0.5"/>
  ${[37.4, 41.6, 46.2].map((y, i) => `<g transform="rotate(${i === 2 ? -10 : 0} 32 ${y + 0.9})"><rect x="28.4" y="${y}" width="7.2" height="1.9" rx="0.9" fill="#fbfaf6" stroke="#8e8a80" stroke-width="0.4"/>${[29.4, 30.4, 31.4, 32.6, 33.6, 34.6].map((x) => `<circle cx="${x}" cy="${y + 0.95}" r="0.28" fill="#8e8a80"/>`).join('')}</g>`).join('')}
  <rect x="28.2" y="49.6" width="7.6" height="2.4" rx="0.6" fill="#c9ccd4" stroke="#6b6f7a" stroke-width="0.4"/>
  <g fill="#fbfaf6" stroke="#8e8a80" stroke-width="0.4"><circle cx="38.8" cy="47.6" r="1.3"/><circle cx="38" cy="51" r="1.3"/><circle cx="36.4" cy="54" r="1.3"/></g>
  ${Array.from({ length: 6 }, (_, i) => `<path d="M${(30.4 + i * 0.64).toFixed(2)} 32 V50" stroke="#eef0f4" stroke-width="0.28"/>`).join('')}
  <path d="M42.6 33.6 C44 36 43.6 38.6 42.2 40.6" fill="none" stroke="#fff" stroke-width="1" opacity="0.35"/>  </g>
`, 40, 0.8);

const bassGuitar = (hue = 218) => tilt(`
  <defs>
    <linearGradient id="bs-body" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="hsl(${hue} 100% 69%)"/><stop offset="0.55" stop-color="hsl(${hue} 72% 49%)"/><stop offset="1" stop-color="hsl(${hue} 74% 23%)"/></linearGradient>
    <linearGradient id="bs-neck" x1="0" x2="1"><stop offset="0" stop-color="#3a2416"/><stop offset="0.5" stop-color="#5a3a22"/><stop offset="1" stop-color="#3a2416"/></linearGradient>
    <radialGradient id="bs-tort" cx="40%" cy="40%" r="70%"><stop offset="0" stop-color="#8a4a1c"/><stop offset="0.5" stop-color="#4a2210"/><stop offset="1" stop-color="#2a1208"/></radialGradient>
  </defs>
  <path d="M29.6 -5 C31.6 -5.6 34.2 -5 35 -3.6 L35.4 4 H30.6 C29.6 1 29 -2 29.6 -5 Z" fill="#e2c08a" stroke="#9a7444" stroke-width="0.5"/>
  <g fill="#d9dbe2" stroke="#6b6f7a" stroke-width="0.35">${[-3.8, -1.2, 1.4, 4].map((y, i) => `<circle cx="${(28.4 + i * 0.2).toFixed(1)}" cy="${y}" r="1.25"/>`).join('')}</g>
  <rect x="30.2" y="3.8" width="3.6" height="30" fill="url(#bs-neck)"/>
  ${fretboard(32, 4, 34, 3.6, 4)}
  <path d="M25.4 33.2 C23.6 29.4 19 29.8 19.8 34.4 C20.4 37.4 21.2 38.8 19.4 41.8 C16.4 46.8 17.6 54.6 24 57 C28.8 58.8 35.2 58.8 39.6 57 C45.8 54.6 46.8 47.2 44 42.6 C42.4 40 43 37.8 44.4 35.2 C46.4 31.4 43 29 40.6 31.8 C38.6 34 36.2 35 32 35 C29 35 27 34.4 25.4 33.2 Z" fill="url(#bs-body)" stroke="#0a1838" stroke-width="0.9"/>
  <path d="M25.2 36.6 C27.4 37.4 29.6 37.6 32.4 37.6 L35 51 C31.8 53.4 26.6 53 24 50 C22 47.6 22.8 43.4 24.2 40.8 C25 39.4 25.4 38 25.2 36.6 Z" fill="url(#bs-tort)" stroke="#1a0a04" stroke-width="0.4"/>
  <rect x="28.6" y="41.6" width="4" height="2.4" rx="0.8" fill="#1a1a20" transform="rotate(-6 30.6 42.8)"/>
  <rect x="31.6" y="43.4" width="4" height="2.4" rx="0.8" fill="#1a1a20" transform="rotate(-6 33.6 44.6)"/>
  <rect x="28.4" y="51.2" width="7.2" height="2.6" rx="0.6" fill="#c9ccd4" stroke="#6b6f7a" stroke-width="0.4"/>
  <g fill="#d9dbe2" stroke="#6b6f7a" stroke-width="0.35"><circle cx="38.4" cy="49.6" r="1.4"/><circle cx="37" cy="53.2" r="1.4"/></g>
  ${Array.from({ length: 4 }, (_, i) => `<path d="M${(30.7 + i * 0.86).toFixed(2)} 34 V52" stroke="#eef0f4" stroke-width="0.45"/>`).join('')}
  <path d="M41.4 37 C43 39.4 42.8 42 41.4 44" fill="none" stroke="#fff" stroke-width="1" opacity="0.4"/>`, 40, 0.74);


/** Jedes Symbol als Funktion des Farbtons (null = Grundfarbe); nur Korpus bzw. Kessel werden eingefärbt. */
const ICONS = {
  guitar: (hue) => acousticGuitar(hue),
  eguitar: (hue) => electricGuitar(hue ?? 18),
  bass: (hue) => bassGuitar(hue ?? 218),
  drums: (hue) => `
    <defs>
      <radialGradient id="dr-cym" cx="45%" cy="40%" r="60%"><stop offset="0" stop-color="#fff2b0"/><stop offset="0.5" stop-color="#e8b84a"/><stop offset="1" stop-color="#a87a1a"/></radialGradient>
      <linearGradient id="dr-shell" x1="0" x2="1">${[[0, 76, 31], [0.35, 77, 54], [0.7, 83, 65], [1, 76, 31]].map(([o, sat, l]) => `<stop offset="${o}" stop-color="hsl(${hue ?? 12} ${sat}% ${l}%)"/>`).join('')}</linearGradient>
      <radialGradient id="dr-head" cx="45%" cy="40%" r="65%"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#ddd6c8"/></radialGradient>
    </defs>
    <g stroke="#55585f" stroke-width="0.9" ${LINE}><path d="M51 15 V58 M47 58 L51 52 L55 58"/><path d="M11 27 V58 M7.4 58 L11 52 L14.6 58"/></g>
    <ellipse cx="51" cy="14" rx="11.6" ry="2.8" fill="url(#dr-cym)" stroke="#7a5410" stroke-width="0.5" transform="rotate(-8 51 14)"/>
    <ellipse cx="11" cy="24.4" rx="8.4" ry="1.8" fill="url(#dr-cym)" stroke="#7a5410" stroke-width="0.4"/>
    <ellipse cx="11" cy="26.6" rx="8.4" ry="1.8" fill="url(#dr-cym)" stroke="#7a5410" stroke-width="0.4"/>
    <path d="M24 17 V24 C24 26.4 38 26.4 38 24 V17" fill="url(#dr-shell)" stroke="#5a1408" stroke-width="0.6"/>
    <ellipse cx="31" cy="17" rx="7" ry="2.2" fill="url(#dr-head)" stroke="#8e8a80" stroke-width="0.5"/>
    <circle cx="31" cy="42" r="16" fill="url(#dr-shell)" stroke="#5a1408" stroke-width="0.8"/>
    <circle cx="31" cy="42" r="13.2" fill="url(#dr-head)" stroke="#c9ccd4" stroke-width="1.6"/>
    <g fill="#c9ccd4">${Array.from({ length: 8 }, (_, i) => {
      const a = (i / 8) * Math.PI * 2;
      return `<circle cx="${(31 + Math.cos(a) * 14.6).toFixed(1)}" cy="${(42 + Math.sin(a) * 14.6).toFixed(1)}" r="0.9"/>`;
    }).join('')}</g>
    <circle cx="31" cy="42" r="6" fill="#2b2d42"/>
    <path d="M27.4 42 L29.6 39.6 L31.6 43.4 L34.6 40" fill="none" stroke="#ffd25a" stroke-width="1.2" ${LINE}/>
    <path d="M22 35.6 C24.4 32.6 28 31 31.6 31" fill="none" stroke="#fff" stroke-width="1.2" opacity="0.6"/>
    <g ${LINE}><path d="M18 5 L28 19" stroke="#8a5a2c" stroke-width="2"/><path d="M18 5 L28 19" stroke="#e6c48a" stroke-width="1.1"/><path d="M41 4 L33 18.6" stroke="#8a5a2c" stroke-width="2"/><path d="M41 4 L33 18.6" stroke="#e6c48a" stroke-width="1.1"/></g>`,
  keys: () => `
    <defs>
      <linearGradient id="ky-case" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#4a4d62"/><stop offset="1" stop-color="#1c1d28"/></linearGradient>
      <linearGradient id="ky-white" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="1" stop-color="#e2e0da"/></linearGradient>
    </defs>
    <path d="M2 22 L8 15 H56 L62 22 Z" fill="#5a5d74"/>
    <rect x="2" y="22" width="60" height="26" rx="2.4" fill="url(#ky-case)"/>
    <rect x="5" y="25" width="6" height="8" rx="1.2" fill="#2b2d42"/><rect x="6.2" y="27.6" width="3.6" height="2.4" rx="1" fill="#9a9aa8"/>
    <circle cx="16" cy="28" r="1.8" fill="#ff4f8b"/><circle cx="21" cy="28" r="1.8" fill="#4fd1ff"/><circle cx="26" cy="28" r="1.8" fill="#ffd25a"/>
    <rect x="32" y="25.8" width="20" height="4.6" rx="1" fill="#0f2a1e"/><path d="M34 28 H46" stroke="#3ddc97" stroke-width="1.4" stroke-linecap="round"/>
    <rect x="5" y="33.6" width="54" height="12.4" fill="#111"/>
    ${Array.from({ length: 14 }, (_, i) => `<rect x="${(5.4 + i * 3.84).toFixed(2)}" y="34" width="3.5" height="11.6" rx="0.5" fill="url(#ky-white)"/>`).join('')}
    ${[0, 1, 3, 4, 5, 7, 8, 10, 11, 12].map((i) => `<rect x="${(8.1 + i * 3.84).toFixed(2)}" y="34" width="2.3" height="7" rx="0.4" fill="#1a1a1e"/>`).join('')}
    <path d="M3.6 23.4 H60.4" stroke="#fff" stroke-width="0.6" opacity="0.25"/>`,
  vocals: () => `
    <defs>
      <radialGradient id="mc-ball" cx="38%" cy="32%" r="70%"><stop offset="0" stop-color="#ffffff"/><stop offset="0.35" stop-color="#cfd2da"/><stop offset="1" stop-color="#6b6f7a"/></radialGradient>
      <linearGradient id="mc-body" x1="0" x2="1"><stop offset="0" stop-color="#1c1d24"/><stop offset="0.45" stop-color="#4a4d5a"/><stop offset="1" stop-color="#15161c"/></linearGradient>
      <pattern id="mc-mesh" width="2" height="2" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path d="M0 0 H2 M0 0 V2" stroke="#5a5d68" stroke-width="0.35"/></pattern>
    </defs>
    <g transform="translate(32 32) scale(0.78) translate(-32 -32) rotate(-38 32 32)">
      <path d="M26.4 30 L37.6 30 L35 60 C34.8 62 29.2 62 29 60 Z" fill="url(#mc-body)"/>
      <rect x="25.6" y="27.6" width="12.8" height="3.6" rx="1" fill="#c9ccd4"/>
      <path d="M26 29 H38" stroke="#fff" stroke-width="0.6" opacity="0.7"/>
      <circle cx="32" cy="16" r="13" fill="url(#mc-ball)"/>
      <circle cx="32" cy="16" r="13" fill="url(#mc-mesh)" opacity="0.55"/>
      <path d="M19.4 18.4 H44.6" stroke="#8e929c" stroke-width="1.2"/>
      <ellipse cx="27" cy="9.6" rx="4.4" ry="2.4" fill="#fff" opacity="0.7" transform="rotate(-30 27 9.6)"/>
    </g>`,
  sax: () => `
    <defs>
      <linearGradient id="sx-brass" x1="0" x2="1"><stop offset="0" stop-color="#a87418"/><stop offset="0.3" stop-color="#ffe08a"/><stop offset="0.6" stop-color="#e9b53c"/><stop offset="1" stop-color="#9a6a12"/></linearGradient>
      <radialGradient id="sx-bell" cx="50%" cy="50%" r="55%"><stop offset="0" stop-color="#5a3a08"/><stop offset="0.7" stop-color="#b8861e"/><stop offset="1" stop-color="#ffe08a"/></radialGradient>
    </defs>
    <path d="M14 7.6 C17 3.8 23 3.4 26.6 6.4 L28.4 8.2 L26.2 10.4 C24 8.4 20.4 8 17.6 10.4 Z" fill="url(#sx-brass)" stroke="#7a5410" stroke-width="0.5"/>
    <path d="M9.6 12.6 L14.6 7.4 L17.2 10 L12.2 15.2 Z" fill="#1a1a1e"/><rect x="13.6" y="9" width="2.6" height="1.2" fill="#c9ccd4" transform="rotate(45 14.9 9.6)"/>
    <path d="M25 8.6 L30.4 8.6 L32.6 42 C33 47.6 36.2 50.4 39.8 49.4 C42.2 48.6 42.8 46 42.2 42.4 L41.4 37.4 L51.4 28.6 L54.6 41.6 L49.8 43 C51 52.6 46.8 59.8 39.2 60.4 C30.8 61 25.8 55 26 46.8 Z" fill="url(#sx-brass)" stroke="#7a5410" stroke-width="0.7"/>
    <ellipse cx="47.4" cy="33.6" rx="5.6" ry="2.8" transform="rotate(-42 47.4 33.6)" fill="url(#sx-bell)" stroke="#7a5410" stroke-width="0.6"/>
    <path d="M28.2 12 L29.6 44" stroke="#fff3c4" stroke-width="0.9" opacity="0.7"/>
    <g>${[15, 20.4, 25.8, 31.2, 36.6].map((y, i) => `<circle cx="${(30.2 + i * 0.18).toFixed(2)}" cy="${y}" r="1.9" fill="#f7f1e2" stroke="#7a5410" stroke-width="0.6"/><circle cx="${(29.8 + i * 0.18).toFixed(2)}" cy="${y - 0.5}" r="0.6" fill="#fff"/>`).join('')}</g>
    <path d="M33 16 C35 17 35.4 24 33.6 26 M33.8 29 C36 30.4 36.2 36 34.4 38" fill="none" stroke="#7a5410" stroke-width="0.9" ${LINE}/>
    <path d="M30.4 50 C33 54.4 39.4 55 42.4 51.4" fill="none" stroke="#7a5410" stroke-width="0.9"/>
    <circle cx="44.6" cy="45.6" r="1.6" fill="#f7f1e2" stroke="#7a5410" stroke-width="0.5"/>`,
  trumpet: () => `<g transform="translate(32 32) scale(0.8) translate(-32 -32)">
    <defs>
      <linearGradient id="tp-brass" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#ffe08a"/><stop offset="0.5" stop-color="#e9b53c"/><stop offset="1" stop-color="#9a6a12"/></linearGradient>
      <radialGradient id="tp-bell" cx="40%" cy="50%" r="60%"><stop offset="0" stop-color="#5a3a08"/><stop offset="0.75" stop-color="#c9921e"/><stop offset="1" stop-color="#ffe08a"/></radialGradient>
    </defs>
    <path d="M40 26 L54 16.6 C58.6 14.6 61.6 22 61.6 31 C61.6 40 58.6 47.4 54 45.4 L40 36 Z" fill="url(#tp-brass)" stroke="#7a5410" stroke-width="0.6"/>
    <ellipse cx="58.4" cy="31" rx="3.2" ry="13.6" fill="url(#tp-bell)" stroke="#7a5410" stroke-width="0.6"/>
    <path d="M18 34.4 C11.6 34.4 11.6 45 18 45 H40 C46.4 45 46.4 34.4 40 34.4" fill="none" stroke="#7a5410" stroke-width="4.4"/>
    <path d="M18 34.4 C11.6 34.4 11.6 45 18 45 H40 C46.4 45 46.4 34.4 40 34.4" fill="none" stroke="url(#tp-brass)" stroke-width="3"/>
    <rect x="7" y="27.4" width="35" height="5.8" rx="2.9" fill="url(#tp-brass)" stroke="#7a5410" stroke-width="0.6"/>
    <path d="M1.6 27.8 C3.6 27.8 4.6 28.8 5 29.4 H7.4 V31.2 H5 C4.6 31.8 3.6 32.8 1.6 32.8 Z" fill="#d9dbe2" stroke="#6b6f7a" stroke-width="0.5"/>
    <g>${[20, 26, 32].map((x) => `
      <rect x="${x}" y="17" width="4.4" height="17" rx="1.2" fill="url(#tp-brass)" stroke="#7a5410" stroke-width="0.6"/>
      <rect x="${x + 1.4}" y="12.6" width="1.6" height="4.6" fill="#c9ccd4"/>
      <ellipse cx="${x + 2.2}" cy="12" rx="2.8" ry="1.4" fill="#f7f1e2" stroke="#7a5410" stroke-width="0.5"/>`).join('')}</g>
    <path d="M9 28.6 H40" stroke="#fff3c4" stroke-width="0.8" opacity="0.8"/></g>`,
  violin: () => `
    <defs>
      <radialGradient id="vl-wood" cx="45%" cy="55%" r="65%"><stop offset="0" stop-color="#f0a24e"/><stop offset="0.6" stop-color="#b8571a"/><stop offset="1" stop-color="#5a2208"/></radialGradient>
    </defs>
    ${tilt(`
      <path d="M32 1.4 C35.4 1.4 35.8 5.6 33.4 6 C33 6.2 33 7 33.2 8.2 H30.8 V4.4 C30.8 2.6 31.4 1.4 32 1.4 Z" fill="#7a3510"/>
      <circle cx="32.6" cy="3.4" r="1.2" fill="none" stroke="#3a1606" stroke-width="0.5"/>
      <g fill="#2a1208"><rect x="28.6" y="5.4" width="2.4" height="1.2" rx="0.5"/><rect x="33" y="5.4" width="2.4" height="1.2" rx="0.5"/></g>
      <path d="M32 17.4 C26.2 17.4 23.2 19.6 23.2 23.8 C23.2 26.6 25.4 27.8 24.8 29.6 L23.6 30.8 C25.4 32.6 25.4 33.8 23.6 35.6 C21.2 38.2 20.4 41.8 21.6 45.4 C23.4 51.4 28.2 53.6 32 53.6 C35.8 53.6 40.6 51.4 42.4 45.4 C43.6 41.8 42.8 38.2 40.4 35.6 C38.6 33.8 38.6 32.6 40.4 30.8 L39.2 29.6 C38.6 27.8 40.8 26.6 40.8 23.8 C40.8 19.6 37.8 17.4 32 17.4 Z" fill="url(#vl-wood)" stroke="#3a1606" stroke-width="0.8"/>
      <path d="M32 18.6 C27 18.6 24.4 20.4 24.4 23.8 C24.4 26.4 26.4 27.6 25.8 29.8 M32 52.4 C28.6 52.4 24.4 50.4 22.8 45.2" fill="none" stroke="#ffd29a" stroke-width="0.4" opacity="0.7"/>
      <rect x="30.4" y="7.6" width="3.2" height="30" rx="1.2" fill="#14100c"/>
      <path d="M27.4 33.2 C26 35.4 28 37.4 26.6 39.8 M36.6 33.2 C38 35.4 36 37.4 37.4 39.8" fill="none" stroke="#2a1208" stroke-width="1"/>
      <rect x="28.4" y="41.4" width="7.2" height="1.4" rx="0.5" fill="#e6c48a"/>
      <path d="M29.6 44.4 H34.4 L33.4 51.4 H30.6 Z" fill="#14100c"/>
      ${Array.from({ length: 4 }, (_, i) => `<path d="M${(31.1 + i * 0.6).toFixed(2)} 7 V45" stroke="#eef0f4" stroke-width="0.25"/>`).join('')}
      <ellipse cx="27.6" cy="24" rx="2.4" ry="3.4" fill="#fff" opacity="0.2"/>`, -34, 0.95)}
    <path d="M8 54 L56 8" stroke="#5a2a0c" stroke-width="1.6" stroke-linecap="round"/>
    <path d="M9.8 55.8 L57.6 10" stroke="#f4efe6" stroke-width="0.9" stroke-linecap="round"/>
    <path d="M6.4 52.4 L10.6 56.6" stroke="#1a1a1e" stroke-width="2.6" stroke-linecap="round"/>
    <circle cx="56.6" cy="8.6" r="1" fill="#1a1a1e"/>`,
  flute: () => `
    <defs>
      <linearGradient id="fl-tube" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#ffffff"/><stop offset="0.45" stop-color="#c9ccd4"/><stop offset="1" stop-color="#7a7e88"/></linearGradient>
    </defs>
    <g transform="rotate(-30 32 32)">
      <rect x="1" y="29" width="62" height="6" rx="3" fill="url(#fl-tube)" stroke="#5a5d68" stroke-width="0.5"/>
      <rect x="8" y="27.6" width="6.4" height="2.4" rx="1.2" fill="url(#fl-tube)" stroke="#5a5d68" stroke-width="0.4"/>
      <ellipse cx="11.2" cy="28" rx="1.4" ry="0.6" fill="#2a2233"/>
      <path d="M17 29 V35 M45 29 V35" stroke="#7a7e88" stroke-width="0.6"/>
      <g>${[22, 27, 32, 37, 42, 51, 56].map((x) => `<circle cx="${x}" cy="32" r="2" fill="url(#fl-tube)" stroke="#5a5d68" stroke-width="0.5"/><circle cx="${x - 0.5}" cy="31.4" r="0.6" fill="#fff"/>`).join('')}</g>
      <path d="M20 33.8 H58" stroke="#5a5d68" stroke-width="0.5"/>
    </g>`,
  turntables: () => `
    <defs>
      <linearGradient id="tt-base" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#4a4d62"/><stop offset="1" stop-color="#1c1d28"/></linearGradient>
      <radialGradient id="tt-vinyl" cx="50%" cy="50%" r="50%"><stop offset="0" stop-color="#2a2830"/><stop offset="0.5" stop-color="#121016"/><stop offset="0.85" stop-color="#2a2830"/><stop offset="1" stop-color="#0c0a0e"/></radialGradient>
    </defs>
    <rect x="2" y="10" width="60" height="44" rx="4" fill="url(#tt-base)"/>
    <path d="M4 12 H60" stroke="#fff" stroke-width="0.6" opacity="0.2"/>
    <circle cx="26" cy="32" r="18.6" fill="#8e929c"/>
    <circle cx="26" cy="32" r="17" fill="url(#tt-vinyl)"/>
    <g fill="none" stroke="#3a3842" stroke-width="0.4">${[15, 13, 11, 9].map((r) => `<circle cx="26" cy="32" r="${r}"/>`).join('')}</g>
    <path d="M14.6 22.4 C18 19 23 17.6 27.6 18.4" fill="none" stroke="#fff" stroke-width="1.2" opacity="0.25"/>
    <circle cx="26" cy="32" r="6" fill="#ff4f8b"/><circle cx="26" cy="32" r="3" fill="#ff8fb4"/><circle cx="26" cy="32" r="1" fill="#e6e6ea"/>
    <circle cx="52" cy="17.6" r="3.6" fill="#c9ccd4" stroke="#5a5d68" stroke-width="0.5"/><circle cx="52" cy="17.6" r="1.4" fill="#6b6f7a"/>
    <path d="M52 17.6 L53.6 38 L44.4 46" fill="none" stroke="#e6e6ea" stroke-width="1.6" ${LINE}/>
    <rect x="41" y="44.4" width="5.6" height="3.6" rx="0.8" fill="#2b2d42" stroke="#c9ccd4" stroke-width="0.5" transform="rotate(-40 43.8 46.2)"/>
    <rect x="56" y="34" width="2.6" height="15" rx="1.2" fill="#0c0a0e"/><rect x="55" y="39" width="4.6" height="2.8" rx="0.8" fill="#4fd1ff"/>
    <circle cx="8" cy="49" r="2" fill="#3ddc97"/>`,
};
ICONS.synth = ICONS.keys;

let serial = 0;

/** Welche gezeichnete Form ein Instrument in seiner Güteklasse hat (E-Gitarre, E-Drums, Synthesizer oder Klavier). */
const artKey = (type, electric) => ({ guitar: electric ? 'eguitar' : 'guitar', drums: electric ? 'edrums' : 'drums', keys: electric ? 'synth' : 'piano' }[type] ?? type);

/**
 * Symbol eines Instruments als kleines SVG; electric unterscheidet Gitarre und E-Gitarre, hue färbt den Korpus (Farbvariante),
 * tier ist die Güteklasse 0–3 (null = einfaches Symbol ohne bestimmtes Instrument); art.variant wählt Holz/Metall (grün),
 * art.gold die goldene Fähigkeit, nach der ein goldenes Instrument aussieht (gearart.js).
 * Jede Kopie bekommt eigene Kennungen für ihre Farbverläufe – sonst verweisen alle auf die erste,
 * und liegt die versteckt (z. B. in einem geschlossenen Fenster), bleiben die anderen farblos.
 */
export function instrumentSvg(type, electric = false, hue = null, tier = null, art = {}) {
  const key = tier === null ? (type === 'guitar' && electric ? 'eguitar' : type) : artKey(type, electric);
  const body = tier === null ? ICONS[key]?.(hue) : tierArt(key, tier, hue, art.variant ?? 0, art.gold ?? null);
  if (!body) return null;
  const n = ++serial;
  const unique = body.replace(/id="([\w-]+)"/g, `id="$1-${n}"`).replace(/url\(#([\w-]+)\)/g, `url(#$1-${n})`);
  return `<svg class="ico ico-${key}" viewBox="0 0 64 64" aria-hidden="true">${unique}</svg>`;
}
