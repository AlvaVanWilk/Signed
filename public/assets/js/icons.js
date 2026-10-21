/**
 * Gezeichnete Instrumenten-Symbole statt Emojis: Gitarre, E-Gitarre und Bass sehen verschieden aus.
 * Für Stellen, die nur Text können (Auswahllisten, Canvas), bleibt das Emoji aus dem Katalog.
 */
const INK = '#2a2233';
const S = `stroke="${INK}" stroke-width="1.1" stroke-linejoin="round" stroke-linecap="round"`;

const ICONS = {
  guitar: `<path d="M13.5 10.5 L20 4" stroke="${INK}" stroke-width="2.6" stroke-linecap="round"/><path d="M13.5 10.5 L20 4" stroke="#a06a3b" stroke-width="1.4" stroke-linecap="round"/>
    <rect x="18.6" y="1.8" width="3.6" height="3.6" rx="0.8" fill="#5b3a21" ${S}/>
    <circle cx="11" cy="13" r="4" fill="#e9a24b" ${S}/><circle cx="7.5" cy="16.5" r="5" fill="#e9a24b" ${S}/>
    <path d="M9.6 13.6 Q12 13 11 15" fill="none" stroke="#e9a24b" stroke-width="2.2"/><circle cx="9.6" cy="14.4" r="1.4" fill="${INK}"/>`,
  eguitar: `<path d="M12.5 11.5 L20 4" stroke="${INK}" stroke-width="2.6" stroke-linecap="round"/><path d="M12.5 11.5 L20 4" stroke="#d9b38c" stroke-width="1.4" stroke-linecap="round"/>
    <path d="M19 1.6 L22.6 2.6 L21.2 5.6 L18.6 4.6 Z" fill="${INK}"/>
    <path d="M10.5 9.5 C12 8.5 14 9.5 13.6 11.4 C15.4 11.6 15.8 13.6 14.2 14.6 C13.6 17.8 11 21.4 7 21.4 C3.6 21.4 2.4 18.6 3.4 16.2 C4 14.6 5.4 14.4 6.6 13.6 C7.6 12 8.6 10.6 10.5 9.5 Z" fill="#e4572e" ${S}/>
    <path d="M7.4 14.6 L11.6 18.2 C10 19.6 7.6 19.8 6.2 18.6 C5.2 17.6 5.8 15.6 7.4 14.6 Z" fill="#fff" ${S}/>
    <path d="M9.4 13.2 L11.4 15 M10.8 11.8 L12.8 13.6" stroke="${INK}" stroke-width="1.2"/>`,
  bass: `<path d="M11 13 L21.4 2.6" stroke="${INK}" stroke-width="2.4" stroke-linecap="round"/><path d="M11 13 L21.4 2.6" stroke="#8a5a33" stroke-width="1.2" stroke-linecap="round"/>
    <path d="M20.2 0.8 L23.4 2 L22.4 4.2 L19.6 3 Z" fill="${INK}"/>
    <path d="M9.6 10.6 C11.6 10 13.4 11.6 12.8 13.4 C14.4 14 14.6 16 13 17 C12.4 20.2 9.8 23 6.6 23 C3.4 23 1.8 20.4 2.6 17.8 C3.2 16 4.8 15.6 6 14.8 C6.8 12.8 8 11.2 9.6 10.6 Z" fill="#3a86ff" ${S}/>
    <path d="M7.6 16.2 L10 18.4" stroke="${INK}" stroke-width="1.6"/>`,
  drums: `<path d="M3 9 L21 9 L19.6 18 Q12 21 4.4 18 Z" fill="#e4572e" ${S}/><ellipse cx="12" cy="9" rx="9" ry="2.6" fill="#f4efe6" ${S}/>
    <path d="M5 12.6 L19 12.6" stroke="#fff" stroke-width="1.2"/>
    <path d="M7 1.6 L11 8 M18 1.6 L13.6 8" stroke="#c8a165" stroke-width="1.8" stroke-linecap="round"/>`,
  keys: `<rect x="1.5" y="6" width="21" height="12" rx="2" fill="#2b2d42" ${S}/><rect x="3" y="8" width="18" height="8" fill="#fff" ${S}/>
    <g fill="${INK}"><rect x="5.6" y="8" width="1.4" height="4.6"/><rect x="9" y="8" width="1.4" height="4.6"/><rect x="13.6" y="8" width="1.4" height="4.6"/><rect x="17" y="8" width="1.4" height="4.6"/></g>`,
  vocals: `<path d="M12 15 L12 21 M8 21.5 L16 21.5" stroke="${INK}" stroke-width="1.6" stroke-linecap="round"/>
    <path d="M7 9.5 Q7 15.5 12 15.5 Q17 15.5 17 9.5" fill="none" stroke="${INK}" stroke-width="1.3"/>
    <rect x="8.6" y="1.5" width="6.8" height="11" rx="3.4" fill="#9a9aa8" ${S}/><path d="M8.8 6 L15.2 6 M8.8 8.4 L15.2 8.4" stroke="${INK}" stroke-width="0.8"/>`,
  sax: `<path d="M8 2 L10 4 L10 15 Q10 21 15 21 Q19 21 19.6 16" fill="none" stroke="${INK}" stroke-width="3.6" stroke-linecap="round"/>
    <path d="M8 2 L10 4 L10 15 Q10 21 15 21 Q19 21 19.6 16" fill="none" stroke="#f2c14e" stroke-width="2.2" stroke-linecap="round"/>
    <path d="M17 16.6 L21.6 12.6 L22.6 17 Z" fill="#f2c14e" ${S}/><circle cx="10" cy="8" r="0.9" fill="${INK}"/><circle cx="10" cy="11" r="0.9" fill="${INK}"/>`,
  trumpet: `<path d="M2 12 L15 12" stroke="${INK}" stroke-width="2.6" stroke-linecap="round"/><path d="M2 12 L15 12" stroke="#f2c14e" stroke-width="1.4" stroke-linecap="round"/>
    <path d="M14 12 L22 6.5 L22 17.5 Z" fill="#f2c14e" ${S}/><rect x="6" y="9" width="6" height="5.5" rx="1.2" fill="#f2c14e" ${S}/>
    <path d="M7.5 9 L7.5 6.6 M9 9 L9 6.6 M10.5 9 L10.5 6.6" stroke="${INK}" stroke-width="1.1" stroke-linecap="round"/>`,
  violin: `<path d="M13 10 L20.6 2.4" stroke="${INK}" stroke-width="2" stroke-linecap="round"/><circle cx="21" cy="2" r="1.4" fill="#5b3a21" ${S}/>
    <path d="M10 7.5 C12.4 7 13.8 8.6 13.4 10.6 C15 11.4 15.4 13.6 14 14.8 C14.4 18.4 11.6 21.6 7.8 21.4 C4 21.2 2.2 18 3.2 15 C2 13.6 2.6 11.4 4.6 10.8 C5 8.6 7.6 7.6 10 7.5 Z" fill="#b5651d" ${S}/>
    <path d="M6.6 12.4 Q5.8 14 7 15.2 M10.4 15.6 Q11.6 14.6 10.8 13" fill="none" stroke="${INK}" stroke-width="0.9"/>
    <path d="M2 22.5 L18.5 6" stroke="#d6c39a" stroke-width="1.1" stroke-linecap="round"/>`,
  flute: `<path d="M2 18 L22 6" stroke="${INK}" stroke-width="3" stroke-linecap="round"/><path d="M2 18 L22 6" stroke="#d6d9e0" stroke-width="1.6" stroke-linecap="round"/>
    <g fill="${INK}"><circle cx="9" cy="13.8" r="0.8"/><circle cx="12" cy="12" r="0.8"/><circle cx="15" cy="10.2" r="0.8"/><circle cx="18" cy="8.4" r="0.8"/></g>`,
  turntables: `<rect x="1.5" y="5" width="21" height="14" rx="2.4" fill="#2b2d42" ${S}/><circle cx="10" cy="12" r="5.4" fill="#111" ${S}/>
    <circle cx="10" cy="12" r="1.6" fill="#ff4f8b"/><path d="M19 7 L19 13 L16 16" fill="none" stroke="#d6d9e0" stroke-width="1.3" stroke-linecap="round"/>`,
};
ICONS.synth = ICONS.keys;

/** Symbol eines Instruments als kleines SVG; electric unterscheidet Gitarre und E-Gitarre. */
export function instrumentSvg(type, electric = false) {
  const key = type === 'guitar' && electric ? 'eguitar' : type;
  const body = ICONS[key];
  return body ? `<svg class="ico" viewBox="0 0 24 24" aria-hidden="true">${body}</svg>` : null;
}
