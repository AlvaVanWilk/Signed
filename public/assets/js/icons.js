/**
 * Gezeichnete Instrumenten-Symbole statt Emojis: Gitarre, E-Gitarre und Bass sehen verschieden aus.
 * Für Stellen, die nur Text können (Auswahllisten, Canvas), bleibt das Emoji aus dem Katalog.
 * Gezeichnet auf 48 × 48; Gitarren, Bass und Geige stehen aufrecht und werden schräg gestellt.
 */
const INK = '#2a2233';
const S = `stroke="${INK}" stroke-width="1.4" stroke-linejoin="round" stroke-linecap="round"`;
const GOLD = '#f2c14e';
const GOLD_DARK = '#c99528';

/** Aufrechtes Instrument schräg stellen und etwas verkleinern, damit es ins Quadrat passt. */
const tilt = (inner, angle = 38, scale = 0.92) => `<g transform="rotate(${angle} 24 24) translate(24 24) scale(${scale}) translate(-24 -24)">${inner}</g>`;

const acousticGuitar = tilt(`
  <rect x="22.4" y="5" width="3.2" height="16" fill="#8a5a33" ${S}/>
  <path d="M21.2 0.8 h5.6 v6 h-5.6 Z" fill="#5b3a21" ${S}/>
  <g fill="#e6e1d6" stroke="${INK}" stroke-width="0.6"><circle cx="20.4" cy="2.2" r="0.9"/><circle cx="20.4" cy="5.2" r="0.9"/><circle cx="27.6" cy="2.2" r="0.9"/><circle cx="27.6" cy="5.2" r="0.9"/></g>
  <path d="M24 14 C18.6 14 16.6 17.4 17.4 20.8 C17.9 22.8 18.8 24 17.8 25.6 C14.4 27.8 13.2 31 14 35 C15.4 41 19.8 44 24 44 C28.2 44 32.6 41 34 35 C34.8 31 33.6 27.8 30.2 25.6 C29.2 24 30.1 22.8 30.6 20.8 C31.4 17.4 29.4 14 24 14 Z" fill="#e9a24b" ${S}/>
  <circle cx="24" cy="25.6" r="3.4" fill="${INK}"/>
  <rect x="20.6" y="35.2" width="6.8" height="2" rx="0.8" fill="#5b3a21"/>
  <path d="M23.2 6 V36 M24.8 6 V36" stroke="#f4efe6" stroke-width="0.4"/>`);

const electricGuitar = tilt(`
  <rect x="22.5" y="5" width="3" height="20" fill="#d9b38c" ${S}/>
  <g fill="${INK}"><circle cx="24" cy="11" r="0.6"/><circle cx="24" cy="15" r="0.6"/><circle cx="24" cy="19" r="0.6"/></g>
  <path d="M22.6 0.6 L27 1.6 L28.2 6.6 L22.6 6.6 Z" fill="${INK}"/>
  <path d="M18.6 22.4 C17 19.4 13.2 19.8 13.8 23.6 C14.2 26.2 15.2 27.4 13.6 30 C11 34.2 12 41 17.4 43 C21.6 44.4 27 44.4 30.8 43 C36 41 37 34.6 34.6 30.8 C33.2 28.6 33.8 26.6 35 24.4 C36.6 21 33.8 18.6 31.6 21 C29.8 23 27.6 23.8 24 23.8 C21.4 23.8 19.8 23.4 18.6 22.4 Z" fill="#e4572e" ${S}/>
  <path d="M18.2 25.4 C20 26.2 22 26.4 24.6 26.4 L27.6 38.6 C24.6 40.6 19.4 40.2 17.2 37.4 C15.6 35.2 16.4 31.6 17.6 29.4 C18.4 28 18.6 26.8 18.2 25.4 Z" fill="#fff" ${S}/>
  <g fill="${INK}"><rect x="20.4" y="28" width="7.2" height="2" rx="0.8"/><rect x="20.4" y="32.4" width="7.2" height="2" rx="0.8"/><rect x="20.8" y="36.8" width="6.4" height="1.6" rx="0.6"/></g>
  <path d="M23.2 6 V37 M24.8 6 V37" stroke="#f4efe6" stroke-width="0.4"/>
  <g fill="#e6e1d6" stroke="${INK}" stroke-width="0.5"><circle cx="30.6" cy="36.6" r="1.1"/><circle cx="31.4" cy="39.6" r="1.1"/></g>`);

const bassGuitar = tilt(`
  <rect x="22.6" y="2" width="2.8" height="24" fill="#8a5a33" ${S}/>
  <g fill="#f4efe6"><circle cx="24" cy="9" r="0.55"/><circle cx="24" cy="14" r="0.55"/><circle cx="24" cy="19" r="0.55"/></g>
  <path d="M22.4 -3.6 h3.2 l1.6 6.4 h-4.8 Z" fill="${INK}"/>
  <g fill="#d6d9e0" stroke="${INK}" stroke-width="0.5"><circle cx="28" cy="-2.4" r="1.2"/><circle cx="28" cy="0.8" r="1.2"/><circle cx="20" cy="-2.4" r="1.2"/><circle cx="20" cy="0.8" r="1.2"/></g>
  <path d="M19 25 C17.6 22.4 14.4 22.8 15 26 C15.4 28.4 16 29.4 14.6 31.6 C12.4 35.4 13.4 41.6 18.2 43.2 C21.8 44.4 26.6 44.4 30 43.2 C34.8 41.4 35.6 35.4 33.4 31.8 C32 29.6 32.6 28 33.6 26 C35 23.2 32.4 21.4 30.4 23.4 C28.8 25.2 27 25.8 24 25.8 C21.6 25.8 20.2 25.6 19 25 Z" fill="#3a86ff" ${S}/>
  <path d="M19.2 28.4 C21 29 22.6 29.2 24.6 29.2 L26.6 39.6 C23.8 41.2 20 40.8 18.4 38.6 C17 36.6 17.8 33.6 18.8 31.6 C19.4 30.6 19.6 29.4 19.2 28.4 Z" fill="#1f2a44" ${S}/>
  <rect x="20.6" y="32" width="6.8" height="2.4" rx="1" fill="${INK}"/>
  <rect x="21" y="38.4" width="6" height="1.6" rx="0.6" fill="${INK}"/>
  <path d="M22.9 -1 V39 M23.7 -1 V39 M24.4 -1 V39 M25.1 -1 V39" stroke="#f4efe6" stroke-width="0.3"/>`, 38, 0.84);

const ICONS = {
  guitar: acousticGuitar,
  eguitar: electricGuitar,
  bass: bassGuitar,
  drums: `
    <path d="M37 9 L37 40" stroke="${INK}" stroke-width="1.4"/>
    <ellipse cx="37" cy="9" rx="9" ry="2.4" fill="${GOLD}" ${S}/>
    <ellipse cx="12" cy="20" rx="8" ry="2.6" fill="#f4efe6" ${S}/>
    <path d="M4 20 V25 C4 28.4 20 28.4 20 25 V20" fill="#e4572e" ${S}/>
    <ellipse cx="12" cy="20" rx="8" ry="2.6" fill="#f4efe6" ${S}/>
    <circle cx="25" cy="31" r="12" fill="#e4572e" ${S}/>
    <circle cx="25" cy="31" r="8.4" fill="#f4efe6" ${S}/>
    <rect x="19.4" y="29" width="11.2" height="4" rx="2" fill="#2b2d42"/>
    <path d="M22 31 h6" stroke="${GOLD}" stroke-width="1.2" stroke-linecap="round"/>
    <path d="M17 41.6 L15 44.6 M33 41.6 L35 44.6" stroke="${INK}" stroke-width="1.6" stroke-linecap="round"/>
    <path d="M9 6 L16 17 M20 5 L14.6 17" stroke="#c8a165" stroke-width="2.2" stroke-linecap="round"/>
    <path d="M9 6 L16 17 M20 5 L14.6 17" stroke="${INK}" stroke-width="0.6" stroke-linecap="round" opacity="0.4"/>`,
  keys: `
    <rect x="2" y="12" width="44" height="24" rx="3" fill="#2b2d42" ${S}/>
    <circle cx="8" cy="17" r="1.6" fill="#ff4f8b"/><circle cx="13" cy="17" r="1.6" fill="#4fd1ff"/>
    <rect x="22" y="15.6" width="18" height="2.8" rx="1" fill="#3ddc97"/>
    <rect x="5" y="21" width="38" height="12" fill="#fff" stroke="${INK}" stroke-width="1"/>
    <path d="M10.4 21 V33 M15.8 21 V33 M21.2 21 V33 M26.6 21 V33 M32 21 V33 M37.4 21 V33" stroke="${INK}" stroke-width="0.8"/>
    <g fill="${INK}"><rect x="8.9" y="21" width="3" height="7"/><rect x="14.3" y="21" width="3" height="7"/><rect x="25.1" y="21" width="3" height="7"/><rect x="30.5" y="21" width="3" height="7"/><rect x="35.9" y="21" width="3" height="7"/></g>`,
  vocals: `
    <path d="M27.4 23.6 L40 41.4 C41 42.8 39.6 44.4 38.2 43.4 L22.6 28" fill="#3b3245" ${S}/>
    <path d="M29.6 26.8 L25.8 30.6" stroke="#9a9aa8" stroke-width="1.6"/>
    <circle cx="19" cy="17.6" r="11" fill="#b8b9c6" ${S}/>
    <path d="M10 13.6 C14 16 22 9 26.8 10 M9 19 C14 21.6 24 13.4 29.6 15.2 M11.2 24.4 C16 26 24.6 19 29 21.6" fill="none" stroke="${INK}" stroke-width="0.7" opacity="0.6"/>
    <path d="M12.6 9.6 C15 7.6 18.6 7 21.6 7.8" fill="none" stroke="#fff" stroke-width="1.6" stroke-linecap="round"/>`,
  sax: `
    <path d="M15.4 6.4 C16.8 4.4 19.6 4 21.4 5.6 L22 6.4" fill="none" stroke="${INK}" stroke-width="4.6" stroke-linecap="round"/>
    <path d="M15.4 6.4 C16.8 4.4 19.6 4 21.4 5.6 L22 6.4" fill="none" stroke="${GOLD}" stroke-width="2.6" stroke-linecap="round"/>
    <path d="M12.4 9.6 L15.8 6" stroke="${INK}" stroke-width="3" stroke-linecap="round"/>
    <path d="M20 7 L23.6 7 L25.4 32 C25.8 36.4 28.6 38.4 31.4 37.4 C33.2 36.6 33.4 34.4 33 31.4 L32.2 27.4 L39.8 21.4 L41.6 30.8 L37.4 31.8 C38.4 38.6 35.4 44 29.6 44.4 C23 44.8 19.4 40 19.6 33.6 Z" fill="${GOLD}" ${S}/>
    <ellipse cx="36" cy="24.6" rx="4.4" ry="2" transform="rotate(-38 36 24.6)" fill="${GOLD_DARK}" ${S}/>
    <g fill="#f4efe6" stroke="${INK}" stroke-width="0.8"><circle cx="22.2" cy="13" r="1.4"/><circle cx="22.6" cy="18" r="1.4"/><circle cx="23" cy="23" r="1.4"/><circle cx="23.4" cy="28" r="1.4"/></g>
    <path d="M26.6 38.2 C28.6 40.6 33 40.4 35 37.6" fill="none" stroke="${GOLD_DARK}" stroke-width="1"/>`,
  trumpet: `
    <path d="M30 20.4 L41.6 13 C44.4 11.6 46.4 17 46.4 23.2 C46.4 29.4 44.4 34.6 41.6 33.2 L30 26 Z" fill="${GOLD}" ${S}/>
    <ellipse cx="44" cy="23.2" rx="2.2" ry="9.6" fill="${GOLD_DARK}" ${S}/>
    <path d="M14 25.6 C9 25.6 9 34 14 34 L30 34 C35 34 35 25.6 30 25.6" fill="none" stroke="${INK}" stroke-width="4" />
    <path d="M14 25.6 C9 25.6 9 34 14 34 L30 34 C35 34 35 25.6 30 25.6" fill="none" stroke="${GOLD}" stroke-width="2.2"/>
    <rect x="6" y="20.6" width="25" height="4.6" rx="2.2" fill="${GOLD}" ${S}/>
    <path d="M1.6 21 L1.6 24.8 L6 23.6 L6 22.2 Z" fill="#d6d9e0" ${S}/>
    <g fill="${GOLD}" ${S}><rect x="15" y="13" width="3.4" height="12" rx="1"/><rect x="19.6" y="13" width="3.4" height="12" rx="1"/><rect x="24.2" y="13" width="3.4" height="12" rx="1"/></g>
    <g fill="#f4efe6" ${S}><rect x="14.4" y="9.6" width="4.6" height="2.6" rx="1.2"/><rect x="19" y="9.6" width="4.6" height="2.6" rx="1.2"/><rect x="23.6" y="9.6" width="4.6" height="2.6" rx="1.2"/></g>
    <path d="M16.7 12.2 V13 M21.3 12.2 V13 M25.9 12.2 V13" stroke="${INK}" stroke-width="1.6"/>`,
  violin: `
    ${tilt(`
      <rect x="22.6" y="5" width="2.8" height="24" rx="1" fill="${INK}"/>
      <path d="M24 1 C26.6 1 26.8 4.4 24.8 4.6 L24.8 6.6 H23.2 V3.6 C23.2 2.4 23.6 1 24 1 Z" fill="#8a4f1c" ${S}/>
      <path d="M24 13.6 C19.4 13.6 17 15.4 17 18.8 C17 21 18.8 22 18.4 23.4 L17.4 24.4 C18.8 25.8 18.8 26.8 17.4 28.2 C15.4 30.2 14.8 33.2 15.8 36 C17.2 40.8 21 42.6 24 42.6 C27 42.6 30.8 40.8 32.2 36 C33.2 33.2 32.6 30.2 30.6 28.2 C29.2 26.8 29.2 25.8 30.6 24.4 L29.6 23.4 C29.2 22 31 21 31 18.8 C31 15.4 28.6 13.6 24 13.6 Z" fill="#b5651d" ${S}/>
      <rect x="22.6" y="14" width="2.8" height="15" rx="1" fill="${INK}"/>
      <path d="M19.4 26.6 C18.4 28.4 19.8 30.2 18.8 32 M28.6 26.6 C29.6 28.4 28.2 30.2 29.2 32" fill="none" stroke="${INK}" stroke-width="1.1"/>
      <path d="M22.2 34 H25.8 L25 40.6 H23 Z" fill="${INK}"/>
      <path d="M23.4 4 V35 M24.6 4 V35" stroke="#e6e1d6" stroke-width="0.35"/>`, -32, 0.86)}
    <path d="M6 40 L43 7" stroke="#8a4f1c" stroke-width="1.8" stroke-linecap="round"/>
    <path d="M7.6 41.6 L44.4 8.6" stroke="#f4efe6" stroke-width="0.9" stroke-linecap="round"/>
    <path d="M5 39 L8.6 42.4" stroke="${INK}" stroke-width="2.2" stroke-linecap="round"/>`,
  flute: `
    <g transform="rotate(-28 24 24)">
      <rect x="1" y="21.6" width="46" height="4.8" rx="2.4" fill="#d6d9e0" ${S}/>
      <path d="M1 24 H47" stroke="#fff" stroke-width="1" opacity="0.8"/>
      <ellipse cx="8" cy="22.6" rx="2.4" ry="1.2" fill="#b8b9c6" ${S}/>
      <g fill="#b8b9c6" ${S}><circle cx="19" cy="24" r="1.7"/><circle cx="24" cy="24" r="1.7"/><circle cx="29" cy="24" r="1.7"/><circle cx="34" cy="24" r="1.7"/><circle cx="39" cy="24" r="1.7"/></g>
      <path d="M17 24 H41" stroke="${INK}" stroke-width="0.6" opacity="0.5"/>
    </g>`,
  turntables: `
    <rect x="2" y="9" width="44" height="30" rx="3.6" fill="#2b2d42" ${S}/>
    <circle cx="20" cy="24" r="12.4" fill="#4a4a5a" ${S}/>
    <circle cx="20" cy="24" r="11" fill="#141218"/>
    <g fill="none" stroke="#3a3842" stroke-width="0.6"><circle cx="20" cy="24" r="9"/><circle cx="20" cy="24" r="7"/></g>
    <circle cx="20" cy="24" r="4.2" fill="#ff4f8b"/><circle cx="20" cy="24" r="0.9" fill="#f4efe6"/>
    <circle cx="38.4" cy="14.4" r="2.6" fill="#b8b9c6" ${S}/>
    <path d="M38.4 14.4 L39.6 26 L33.6 31" fill="none" stroke="#d6d9e0" stroke-width="1.6" stroke-linecap="round"/>
    <rect x="31" y="30" width="4" height="2.6" rx="0.6" transform="rotate(-40 33 31.3)" fill="#d6d9e0" ${S}/>
    <rect x="41" y="25" width="2" height="10" rx="1" fill="#141218"/><rect x="40.4" y="28" width="3.2" height="2" rx="0.6" fill="#4fd1ff"/>`,
};
ICONS.synth = ICONS.keys;

/** Symbol eines Instruments als kleines SVG; electric unterscheidet Gitarre und E-Gitarre. */
export function instrumentSvg(type, electric = false) {
  const key = type === 'guitar' && electric ? 'eguitar' : type;
  const body = ICONS[key];
  return body ? `<svg class="ico" viewBox="0 0 48 48" aria-hidden="true">${body}</svg>` : null;
}
