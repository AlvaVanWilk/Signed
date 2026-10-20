/**
 * Profilbild aus zufälligen Merkmalen. Bewusst ohne ausgearbeitetes Gesicht: nur Kopf, Frisur und Accessoires.
 */
const HAIR_BACK = {
  lang: 'M29 44 C29 15 71 15 71 44 L73 78 L61 78 L62 46 C58 34 42 34 38 46 L39 78 L27 78 Z',
  bob: 'M30 44 C30 17 70 17 70 44 L71 58 L62 58 L62 42 C57 33 43 33 38 42 L38 58 L29 58 Z',
  zopf: 'M64 40 C78 44 78 66 70 74 C72 62 70 52 62 46 Z',
};

const HAIR_FRONT = {
  kurz: 'M32 42 C30 9 70 9 68 42 C64 31 40 29 32 42 Z',
  bob: 'M31 41 C29 8 71 8 69 41 C60 30 40 30 31 41 Z',
  lang: 'M31 41 C29 8 71 8 69 41 C62 29 38 29 31 41 Z',
  zopf: 'M32 41 C30 9 70 9 68 41 C63 30 37 30 32 41 Z',
  dutt: 'M32 41 C30 9 70 9 68 41 C63 31 37 31 32 41 Z',
  sidecut: 'M36 38 C33 8 71 9 69 40 C64 28 50 26 36 38 Z',
  irokese: 'M45 12 L55 12 L57 34 L43 34 Z',
};

const HATS = {
  beanie: '<path d="M31 37 C31 13 69 13 69 37 Z" fill="HAT"/><rect x="29" y="32" width="42" height="7" rx="3" fill="HAT2"/>',
  cap: '<path d="M31 35 C30 13 70 13 69 35 Z" fill="HAT"/><path d="M60 33 C70 32 78 34 80 37 L60 37 Z" fill="HAT2"/>',
  fedora: '<ellipse cx="50" cy="31" rx="27" ry="5" fill="HAT2"/><path d="M36 31 C36 15 64 15 64 31 Z" fill="HAT"/><rect x="36" y="25" width="28" height="4" fill="HAT2"/>',
  cowboy: '<path d="M18 30 C30 38 70 38 82 30 C76 34 24 34 18 30 Z" fill="HAT2"/><path d="M36 32 C34 14 66 14 64 32 Z" fill="HAT"/>',
};

const HAT_COLORS = { beanie: ['#ff4f8b', '#c73a6c'], cap: ['#3a86ff', '#2763c4'], fedora: ['#3b3245', '#1d1824'], cowboy: ['#a0703f', '#7a5230'] };

export function avatar(look = {}) {
  const skin = look.skin || '#d9a27e';
  const hair = look.hairColor || '#3b2a20';
  const style = look.hair || 'kurz';
  const parts = [];

  parts.push(`<path d="M12 100 C12 78 30 69 50 69 C70 69 88 78 88 100 Z" fill="${look.shirt || '#2b2d42'}"/>`);
  if (style === 'afro') parts.push(`<circle cx="50" cy="35" r="26" fill="${hair}"/>`);
  if (HAIR_BACK[style]) parts.push(`<path d="${HAIR_BACK[style]}" fill="${hair}"/>`);
  parts.push(`<rect x="43" y="56" width="14" height="16" rx="6" fill="${skin}"/>`);
  parts.push(`<ellipse cx="33" cy="45" rx="3.5" ry="5" fill="${skin}"/><ellipse cx="67" cy="45" rx="3.5" ry="5" fill="${skin}"/>`);
  parts.push(`<ellipse cx="50" cy="42" rx="17" ry="20" fill="${skin}"/>`);

  if (style === 'locken') {
    for (const [x, y, r] of [[36, 30, 7], [44, 24, 8], [54, 23, 8], [63, 29, 7], [33, 38, 5], [67, 38, 5]]) {
      parts.push(`<circle cx="${x}" cy="${y}" r="${r}" fill="${hair}"/>`);
    }
  } else if (style === 'afro') {
    parts.push(`<path d="M33 38 C34 24 66 24 67 38 C60 32 40 32 33 38 Z" fill="${hair}"/>`);
  } else if (HAIR_FRONT[style]) {
    parts.push(`<path d="${HAIR_FRONT[style]}" fill="${hair}"/>`);
  }
  if (style === 'dutt') parts.push(`<circle cx="50" cy="17" r="8" fill="${hair}"/>`);
  if (style === 'sidecut') parts.push(`<path d="M33 40 C33 33 35 30 37 29 L37 40 Z" fill="${hair}" opacity="0.35"/>`);

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
  if (look.hat && HATS[look.hat]) {
    const [c1, c2] = HAT_COLORS[look.hat];
    parts.push(HATS[look.hat].replaceAll('HAT2', c2).replaceAll('HAT', c1));
  }

  return `<svg class="avatar-svg" viewBox="0 0 100 100" aria-hidden="true">${parts.join('')}</svg>`;
}
