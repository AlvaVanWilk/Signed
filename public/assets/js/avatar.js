// Ohrringe und Lippenstift kommen aus figure.js (gegenseitiger Import, nur zur Laufzeit benutzt).
import { earringOf, hasLipstick } from './figure.js';

/**
 * Profilbild aus zufälligen Merkmalen. Bewusst ohne ausgearbeitetes Gesicht: nur Kopf, Frisur und Accessoires.
 */
export const HAIR_BACK = {
  lang: 'M29 44 C29 15 71 15 71 44 L73 78 L61 78 L62 46 C58 34 42 34 38 46 L39 78 L27 78 Z',
  bob: 'M30 44 C30 17 70 17 70 44 L71 58 L62 58 L62 42 C57 33 43 33 38 42 L38 58 L29 58 Z',
  zopf: 'M64 40 C78 44 78 66 70 74 C72 62 70 52 62 46 Z',
  // Neuere Schnitte: Wolf Cut (gestufte Spitzen an den Seiten), Mittelscheitel bis zum Kinn, Box Braids, Vokuhila (lang im Nacken)
  wolf: 'M31 38 L25 62 L30 58 L31 66 L36 58 L36 46 Z M69 38 L75 62 L70 58 L69 66 L64 58 L64 46 Z',
  curtain: 'M30 42 C29 16 71 16 70 42 L72 63 C69 66 65 66 62 63 L62 44 C57 34 43 34 38 44 L38 63 C35 66 31 66 28 63 Z',
  braids: 'M27 40 L30.2 40 L30 74 Q28.3 76 26.6 74 Z M31 42 L34 42 L34.2 79 Q32.6 81 31 79 Z M34.8 47 L37.6 47 L37.6 70 Q36.2 72 34.8 70 Z M73 40 L69.8 40 L70 74 Q71.7 76 73.4 74 Z M69 42 L66 42 L65.8 79 Q67.4 81 69 79 Z M65.2 47 L62.4 47 L62.4 70 Q63.8 72 65.2 70 Z',
  mullet: 'M30.5 44 C29 54 28 61 24 67 C30 68 35 66 38 61 L37.5 52 Z M69.5 44 C71 54 72 61 76 67 C70 68 65 66 62 61 L62.5 52 Z',
};

export const HAIR_FRONT = {
  kurz: 'M32 42 C30 9 70 9 68 42 C64 31 40 29 32 42 Z',
  bob: 'M31 41 C29 8 71 8 69 41 C60 30 40 30 31 41 Z',
  lang: 'M31 41 C29 8 71 8 69 41 C62 29 38 29 31 41 Z',
  zopf: 'M32 41 C30 9 70 9 68 41 C63 30 37 30 32 41 Z',
  dutt: 'M32 41 C30 9 70 9 68 41 C63 31 37 31 32 41 Z',
  sidecut: 'M40 34 C38 8 72 7 70 41 C66 29 54 25 40 34 Z',
  irokese: 'M45 12 L55 12 L57 34 L43 34 Z',
  wolf: 'M30 44 C27 9 73 9 70 44 L67.5 37 L65 41 L62 33 L58 38 L54 32 L50 37 L46 32 L42 38 L38 33 L35 41 L32.5 37 Z',
  curtain: 'M31 44 C29 10 71 10 69 44 C68 36 63 33 57 33 C53 32 51 28 50 24 C49 28 47 32 43 33 C37 33 32 36 31 44 Z',
  buzz: 'M32.5 39 C31.5 19 68.5 19 67.5 39 C61 33.5 39 33.5 32.5 39 Z',
  spacebuns: 'M32 41 C30 10 70 10 68 41 C63 31 53 30 50 27 C47 30 37 31 32 41 Z',
  braids: 'M31 42 C29 10 71 10 69 42 C63 31 54 29 50 25 C46 29 37 31 31 42 Z',
  mullet: 'M32 40 C30 9 70 9 68 40 L65 34 L62 37 L59 31 L55 35 L51 30 L47 35 L43 31 L40 36 L37 33 L35 38 Z',
  tolle: 'M32 41 C30 25 35 15 45 11 C55 7 68 9 73 16 C67 14 61 16 59 20 C65 22 68 30 68 41 C62 33 41 31 32 41 Z',
  pixie: 'M31 43 C28 10 72 10 69 40 C67 33 62 31 56 31 C47 31 39 35 33.5 46 C32.6 44.6 31.8 43.6 31 43 Z',
};

/**
 * Was zu manchen Frisuren noch dazugehört (über dem Gesicht gezeichnet): zwei Knoten bei den Space Buns, Perlen an den Braids,
 * ausrasierte Seiten beim Buzzcut und bei der Tolle. Gemeinsam für Porträt und Figur.
 */
export function hairExtras(style, hair, hatted) {
  const out = [];
  if (style === 'spacebuns' && !hatted) out.push(`<circle cx="35.5" cy="18.5" r="7.5" fill="${hair}"/><circle cx="64.5" cy="18.5" r="7.5" fill="${hair}"/>`);
  if (style === 'braids') {
    out.push('<g fill="#e8b04a">' + [[28.3, 75.5], [32.6, 80.5], [36.2, 71.5], [71.7, 75.5], [67.4, 80.5], [63.8, 71.5]].map(([x, y]) => `<circle cx="${x}" cy="${y}" r="1.6"/>`).join('') + '</g>');
    // Flechtmuster: helle Querstriche auf den Zöpfen (sonst wirken sie wie Balken)
    out.push(`<g stroke="#fff" stroke-opacity="0.22" stroke-width="0.7">${[52, 58, 64, 70].map((y) => `<path d="M27 ${y} L30 ${y - 1.4} M31.2 ${y + 3} L34 ${y + 1.6} M73 ${y} L70 ${y - 1.4} M68.8 ${y + 3} L66 ${y + 1.6}"/>`).join('')}</g>`);
  }
  // Ausrasierte Seiten: ein zarter Schatten an den Schläfen, innerhalb des Kopfes
  if ((style === 'buzz' || style === 'tolle') && !hatted) out.push(`<path d="M31.6 45 C31.2 39 32.2 35 34.4 32 L35.8 34 C34.6 37 34 41 33.8 45 Z M68.4 45 C68.8 39 67.8 35 65.6 32 L64.2 34 C65.4 37 66 41 66.2 45 Z" fill="${hair}" opacity="0.45"/>`);
  return out.join('');
}

export const HATS = {
  beanie: '<path d="M31 37 C31 13 69 13 69 37 Z" fill="HAT"/><rect x="29" y="32" width="42" height="7" rx="3" fill="HAT2"/>',
  cap: '<path d="M31 35 C30 13 70 13 69 35 Z" fill="HAT"/><path d="M60 33 C70 32 78 34 80 37 L60 37 Z" fill="HAT2"/>',
  // Fedora sitzt tief: Die Krempe liegt knapp über den Brauen.
  fedora: '<path d="M31 33 C30 13 70 13 69 33 Z" fill="HAT"/><rect x="31" y="27" width="38" height="5" fill="HAT2"/><ellipse cx="50" cy="33" rx="29" ry="5" fill="HAT2"/>',
  // Cowboyhut: Krone mit Delle oben, Hutband, breite Krempe mit hochgebogenen Enden.
  cowboy: '<path d="M33.4 32.4 C32.6 20 34.6 13.4 40.4 12.6 C44 12.2 46.6 15.4 50 15.4 C53.4 15.4 56 12.2 59.6 12.6 C65.4 13.4 67.4 20 66.6 32.4 H33.4 Z" fill="HAT"/><path d="M33.4 27.6 H66.6 V30.4 H33.4 Z" fill="HAT2"/><path d="M15 25.6 C19 28.6 24 30.4 32 31 C38 31.4 44 31.6 50 31.6 C56 31.6 62 31.4 68 31 C76 30.4 81 28.6 85 25.6 C85.6 29.6 81 34.6 68 35.6 C62 36 56 36.2 50 36.2 C44 36.2 38 36 32 35.6 C19 34.6 14.4 29.6 15 25.6 Z" fill="HAT2"/>',
};

/** Zu diesen Frisuren passt keine Kopfbedeckung (Dutt, Undercut, Irokese, Locken, Afro): Sie tragen nie eine. */
export const NO_HAT = ['dutt', 'sidecut', 'irokese', 'locken', 'afro', 'spacebuns', 'tolle', 'buzz'];
export const hatOf = (look) => (!look.hat || NO_HAT.includes(look.hair) ? 'none' : look.hat);

export const HAT_COLORS = { beanie: ['#ff4f8b', '#c73a6c'], cap: ['#3a86ff', '#2763c4'], fedora: ['#3b3245', '#1d1824'], cowboy: ['#a0703f', '#7a5230'] };

export function avatar(look = {}) {
  const skin = look.skin || '#d9a27e';
  const hair = look.hairColor || '#3b2a20';
  const style = look.hair || 'kurz';
  const hat = hatOf(look);
  const hatted = hat !== 'none';
  const parts = [];

  const female = look.gender === 'f';
  // Frauen: schmalere Schultern, runder Ausschnitt, Lippen und Ohrringe – sonst wirken die gesichtslosen Köpfe männlich.
  parts.push(female
    ? `<path d="M17 100 C17 80 32 70 50 70 C68 70 83 80 83 100 Z" fill="${look.shirt || '#2b2d42'}"/>`
    : `<path d="M12 100 C12 78 30 69 50 69 C70 69 88 78 88 100 Z" fill="${look.shirt || '#2b2d42'}"/>`);
  if (style === 'afro') parts.push(`<circle cx="50" cy="35" r="26" fill="${hair}"/>`);
  if (HAIR_BACK[style]) parts.push(`<path d="${HAIR_BACK[style]}" fill="${hair}"/>`);
  parts.push(`<rect x="43" y="56" width="14" height="16" rx="6" fill="${skin}"/>`);
  parts.push(`<ellipse cx="33" cy="45" rx="3.5" ry="5" fill="${skin}"/><ellipse cx="67" cy="45" rx="3.5" ry="5" fill="${skin}"/>`);
  if (female) parts.push(`<path d="M43 69 L57 69 L50.5 80 L49.5 80 Z" fill="${skin}"/>`);
  parts.push(`<ellipse cx="50" cy="42" rx="${female ? 16 : 17}" ry="20" fill="${skin}"/>`);
  // Lippenstift und Ohrringe wie bei den Figuren: nur ein Teil trägt sie (earringOf/hasLipstick).
  if (hasLipstick(look)) parts.push('<path d="M45 53 C47.5 51.6 52.5 51.6 55 53 C52.5 56.4 47.5 56.4 45 53 Z" fill="#b85068"/>');
  const ear = earringOf(look);
  if (ear) parts.push(`<g fill="${ear.color}"><circle cx="33.5" cy="51.6" r="1.5"/><circle cx="66.5" cy="51.6" r="1.5"/></g>`);

  if (style === 'locken') {
    for (const [x, y, r] of [[36, 30, 7], [44, 24, 8], [54, 23, 8], [63, 29, 7], [33, 38, 5], [67, 38, 5]]) {
      parts.push(`<circle cx="${x}" cy="${y}" r="${r}" fill="${hair}"/>`);
    }
  } else if (style === 'afro') {
    parts.push(`<path d="M33 40 C30 14 70 14 67 40 C60 32 40 32 33 40 Z" fill="${hair}"/>`);
  } else if (HAIR_FRONT[style] && !(hatted && style === 'irokese')) {
    // Unter einem Hut sieht man vom Undercut nur den Ansatz.
    parts.push(`<path d="${HAIR_FRONT[hatted && style === 'sidecut' ? 'kurz' : style]}" fill="${hair}"/>`);
  }
  if (style === 'dutt' && !hatted) parts.push(`<circle cx="50" cy="17" r="8" fill="${hair}"/>`);
  if (style === 'sidecut' && !hatted) parts.push(`<path d="M32 42 C30 12 52 10 56 22 C46 22 40 28 38 34 C35 36 33 39 32 42 Z" fill="${hair}" opacity="0.45"/>`);
  parts.push(hairExtras(style, hair, hatted));

  if (look.freckles) {
    for (const [x, y] of [[41, 48], [44, 50], [39, 51], [59, 48], [56, 50], [61, 51]]) {
      parts.push(`<circle cx="${x}" cy="${y}" r="0.9" fill="#8a4b2a" opacity="0.6"/>`);
    }
  }
  if (look.beard === 'stubble') {
    parts.push(`<path d="M34 46 C35 64 65 64 66 46 C63 58 37 58 34 46 Z" fill="${hair}" opacity="0.3"/>`);
  } else if (look.beard === 'full') {
    parts.push(`<path d="M33 44 C33 68 67 68 67 44 C64 58 58 54 50 54 C42 54 36 58 33 44 Z" fill="${hair}"/>`);
  }
  if (look.glasses === 'round') {
    parts.push('<g fill="none" stroke="#1b1b1b" stroke-width="1.6"><circle cx="43" cy="43" r="5"/><circle cx="57" cy="43" r="5"/><path d="M48 43 L52 43"/></g>');
  } else if (look.glasses === 'square') {
    parts.push('<g fill="none" stroke="#1b1b1b" stroke-width="1.6"><rect x="37.5" y="39" width="10" height="8" rx="1.5"/><rect x="52.5" y="39" width="10" height="8" rx="1.5"/><path d="M47.5 43 L52.5 43"/></g>');
  } else if (look.glasses === 'sun') {
    parts.push('<g fill="#111"><rect x="37" y="39" width="11" height="7" rx="3"/><rect x="52" y="39" width="11" height="7" rx="3"/><rect x="47" y="41" width="6" height="1.6"/></g>');
  }
  if (look.piercing) parts.push('<circle cx="33" cy="50" r="1.4" fill="#e6e6e6"/>');
  if (HATS[hat]) {
    const [c1, c2] = HAT_COLORS[hat];
    parts.push(HATS[hat].replaceAll('HAT2', c2).replaceAll('HAT', c1));
  }

  return `<svg class="avatar-svg" viewBox="0 0 100 100" aria-hidden="true">${parts.join('')}</svg>`;
}
