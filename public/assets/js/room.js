/**
 * Proberaum, gezeichnet im Stil der Signies (ruhige Flächen, dunkle Kontur). Vier Stufen vom Kellerloch bis zum Profi-Raum.
 * Die Mitte bleibt frei: Dort steht die Band (Figuren liegen als eigene Ebene darüber).
 */
const INK = '#1d1a2a';
const W = 960;
const H = 320;
const FLOOR = 228;
/** Mitte des Raums: Dort steht die Band, Möbel bleiben an den Rändern. */
const MID = 160;

function rng(seed) {
  let s = (seed * 2654435761) >>> 0 || 1;
  return () => {
    s ^= s << 13; s >>>= 0; s ^= s >>> 17; s ^= s << 5; s >>>= 0;
    return (s % 100000) / 100000;
  };
}
const f = (n) => Math.round(n * 10) / 10;
const esc = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
function curve(pts, closed = false) {
  const n = pts.length;
  const P = (i) => pts[closed ? (i + n) % n : Math.max(0, Math.min(n - 1, i))];
  let d = `M${f(pts[0][0])} ${f(pts[0][1])}`;
  for (let i = 0; i < (closed ? n : n - 1); i++) {
    const p0 = P(i - 1), p1 = P(i), p2 = P(i + 1), p3 = P(i + 2);
    d += ` C${f(p1[0] + (p2[0] - p0[0]) / 6)} ${f(p1[1] + (p2[1] - p0[1]) / 6)} ${f(p2[0] - (p3[0] - p1[0]) / 6)} ${f(p2[1] - (p3[1] - p1[1]) / 6)} ${f(p2[0])} ${f(p2[1])}`;
  }
  return d + (closed ? 'Z' : '');
}
function blob(cx, cy, rx, ry, r, amp = 2, n = 12) {
  const pts = [];
  for (let i = 0; i < n; i++) {
    const a = (i / n) * Math.PI * 2;
    const k = 1 + ((r() - 0.5) * amp * 2) / Math.max(rx, ry);
    pts.push([cx + Math.cos(a) * rx * k, cy + Math.sin(a) * ry * k]);
  }
  return curve(pts, true);
}
const st = (w = 3) => `stroke="${INK}" stroke-width="${w}" stroke-linejoin="round" stroke-linecap="round"`;
const box = (x, y, w, h, fill, rx = 3, sw = 3) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${rx}" fill="${fill}" ${st(sw)}/>`;
const line = (d, c = INK, w = 2) => `<path d="${d}" stroke="${c}" stroke-width="${w}" fill="none" stroke-linecap="round"/>`;

function walls(level, r) {
  let s = '';
  if (level === 0) {
    s += `<rect width="${W}" height="${FLOOR}" fill="#3a4350"/>`;
    for (let i = 0; i < 11; i++) s += `<path d="${blob(30 + r() * (W - 60), 40 + r() * 160, 14 + r() * 30, 8 + r() * 16, r, 6)}" fill="#323a46" opacity="0.85"/>`;
    s += `<path d="${blob(760, 120, 52, 66, r, 12)}" fill="#2d3c40" opacity="0.9"/>`;
    s += line('M190 30 L178 80 L196 128 M178 80 L154 106', '#222a32', 2.4);
  } else if (level === 1) {
    // Derselbe Keller, nur aufgeräumt: hellerer Anstrich, ein paar Flecken sind geblieben.
    s += `<rect width="${W}" height="${FLOOR}" fill="#4a5462"/>`;
    for (let i = 0; i < 4; i++) s += `<path d="${blob(30 + r() * (W - 60), 40 + r() * 160, 12 + r() * 20, 7 + r() * 10, r, 5)}" fill="#424b58" opacity="0.7"/>`;
  } else if (level === 2) {
    s += `<rect width="${W}" height="${FLOOR}" fill="#7a4e58"/>`;
    for (let x = 0; x < W; x += 46) s += `<rect x="${x}" y="0" width="23" height="${FLOOR}" fill="#835560" opacity="0.55"/>`;
  } else {
    // Profi-Studio: dunkle Wände, Holzlamellen hinter der Band.
    s += `<rect width="${W}" height="${FLOOR}" fill="#262c3d"/>`;
    s += box(MID + 150, 34, 340, FLOOR - 40, '#5a3b2a', 4);
    for (let x = MID + 158; x < MID + 484; x += 18) s += `<rect x="${x}" y="40" width="11" height="${FLOOR - 52}" rx="2" fill="#a06a3b"/><rect x="${x}" y="40" width="3" height="${FLOOR - 52}" fill="#fff" opacity="0.08"/>`;
  }
  return s;
}

function floor(level, r) {
  let s = '';
  if (level <= 1) {
    s += `<rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="${level ? '#5a5a62' : '#4a4a50'}"/>`;
    for (let i = 0; i < (level ? 3 : 8); i++) s += `<path d="${blob(40 + r() * (W - 80), FLOOR + 20 + r() * 60, 20 + r() * 30, 5 + r() * 6, r, 4)}" fill="#3e3e44" opacity="0.7"/>`;
    if (level) s += `<g transform="translate(${MID} 14)"><path d="${curve([[170, 262], [230, 246], [410, 246], [470, 262], [420, 284], [220, 284]], true)}" fill="#8a4a3a" ${st(3)}/></g>`;
  } else if (level === 2) {
    s += `<rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="#b7814e"/>`;
    for (let x = 0; x < W + 60; x += 58) s += line(`M${x} ${FLOOR} L${x - 26} ${H}`, '#8a5d36', 2);
    s += `<g transform="translate(${MID} 14)"><path d="${curve([[150, 262], [220, 244], [420, 244], [490, 262], [430, 288], [210, 288]], true)}" fill="#3f8f86" ${st(3)}/></g>`;
  } else {
    // Dunkles, geöltes Parkett mit Glanz, großer runder Teppich.
    s += `<rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="#4a3226"/>`;
    for (let x = 0; x < W + 60; x += 40) s += line(`M${x} ${FLOOR} L${x - 18} ${H}`, '#3a261c', 2);
    s += `<rect y="${FLOOR}" width="${W}" height="14" fill="#fff" opacity="0.05"/>`;
    s += `<g transform="translate(${MID} 14)"><path d="${curve([[70, 262], [170, 238], [470, 238], [570, 262], [490, 298], [150, 298]], true)}" fill="#6e2a3a" ${st(3)}/><path d="${curve([[120, 262], [200, 248], [440, 248], [520, 262], [450, 288], [190, 288]], true)}" fill="none" stroke="#c9a86a" stroke-width="2.4"/></g>`;
  }
  return s + line(`M0 ${FLOOR} H${W}`, INK, 3);
}

function ceiling(level) {
  let s = '';
  if (level <= 1) {
    s += box(-6, 10, W + 12, 12, level ? '#6a6460' : '#6a5648', 6) + line(`M80 16 v6 M${W / 2} 16 v6 M${W - 80} 16 v6`, '#a8805a', 3);
  }
  s += `<g transform="translate(${MID} 0)">`;
  if (level === 0) {
    s += `<path d="M320 22 L320 70" ${st(2.5)} fill="none"/><ellipse cx="320" cy="80" rx="11" ry="14" fill="#ffe08a" ${st(3)}/>`;
    s += `<path d="M320 92 L200 ${FLOOR + 40} L440 ${FLOOR + 40} Z" fill="url(#rmCone)" opacity="0.85"/>`;
  } else if (level === 1) {
    s += `<path d="M320 22 L320 56" ${st(2.5)} fill="none"/><path d="M296 74 L344 74 L332 54 L308 54 Z" fill="#e4a73e" ${st(3)}/><ellipse cx="320" cy="76" rx="9" ry="5" fill="#ffe9a8"/>`;
    s += `<path d="M300 76 L170 ${FLOOR + 40} L470 ${FLOOR + 40} L340 76 Z" fill="url(#rmCone)" opacity="0.7"/>`;
  } else if (level === 3) {
    s += `</g><g>`;
    s += `<rect x="-6" y="6" width="${W + 12}" height="10" fill="#3b4256" ${st(2.5)}/><rect x="0" y="16" width="${W}" height="3" fill="#7fd0e6" opacity="0.7"/><rect x="0" y="16" width="${W}" height="16" fill="#7fd0e6" opacity="0.08"/>`;
    for (const x of [MID + 200, MID + 320, MID + 440]) s += `<path d="M${x - 12} 16 h24 l-4 18 h-16 Z" fill="#1d1a2a" ${st(2.2)}/><path d="M${x - 8} 34 L${x - 70} ${FLOOR + 30} L${x + 70} ${FLOOR + 30} L${x + 8} 34 Z" fill="url(#rmCone)" opacity="0.45"/>`;
  } else {
    const n = 6;
    s += `</g><g>`;
    s += `<path d="${curve([[0, 12], [160, 36], [320, 14], [480, 38], [640, 14], [800, 36], [960, 12]])}" ${st(2)} fill="none"/>`;
    const colors = ['#f2c14e', '#ee7a8a', '#7fd0e6'];
    for (let i = 0; i < n; i++) {
      const x = 40 + i * ((W - 80) / (n - 1)), y = 22 + Math.sin(i * 1.7) * 8;
      s += `<circle cx="${f(x)}" cy="${f(y)}" r="11" fill="${colors[i % 3]}" opacity="0.25"/><circle cx="${f(x)}" cy="${f(y)}" r="5" fill="${colors[i % 3]}" ${st(1.8)}/>`;
    }
  }
  return s + '</g>';
}

function left(level, r) {
  if (level === 0) {
    return box(56, 56, 66, 50, '#6f8a96', 3) + line('M89 56 v50 M56 81 h66') + box(52, 52, 74, 58, 'none', 3)
      + `<path d="${curve([[30, 208], [62, 190], [150, 188], [178, 208], [164, 220], [44, 220]], true)}" fill="#5c5a63" ${st(3)}/>`;
  }
  if (level === 1) {
    // Fenster geputzt, der Schutt ist weg: zwei einfache Klappstühle.
    const chair = (x, c) => `<path d="M${x} 214 L${x + 6} 178 M${x + 34} 214 L${x + 28} 178 M${x + 4} 192 H${x + 30}" ${st(3.4)} fill="none"/>`
      + box(x, 172, 34, 8, c, 3, 2.6) + box(x + 3, 138, 28, 30, c, 4, 2.6) + `<path d="M${x + 6} 168 V178 M${x + 28} 168 V178" ${st(3)} fill="none"/>`;
    return box(56, 56, 66, 50, '#7f9ca8', 3) + line('M89 56 v50 M56 81 h66') + box(52, 52, 74, 58, 'none', 3) + chair(40, '#8a8f99') + chair(104, '#a0705a');
  }
  if (level === 3) {
    // Schaumstoff-Akustikplatten und ein Verstärkerturm.
    let s = '';
    for (let i = 0; i < 4; i++) for (let k = 0; k < 3; k++) {
      const x = 18 + i * 52, y = 40 + k * 48;
      s += box(x, y, 46, 42, '#3a3f4e', 4, 2.4);
      for (let j = 0; j < 3; j++) s += `<path d="M${x + 4 + j * 13} ${y + 36} l6.5 -30 l6.5 30 Z" fill="#454b5c"/>`;
    }
    s += box(30, 120, 110, 96, '#1d1a2a', 6) + box(38, 128, 94, 18, '#2a2636', 3, 2) + `<rect x="74" y="132" width="22" height="9" rx="2" fill="#c9a86a"/>`;
    s += box(38, 152, 94, 56, '#2f2b3c', 4, 2) + [0, 1, 2, 3, 4, 5].map((j) => `<path d="M42 ${158 + j * 8} H128" stroke="#1d1a2a" stroke-width="2"/>`).join('');
    return s;
  }
  // Akustikpaneele im gemütlichen Raum
  let s = '';
  const cols = 3;
  for (let i = 0; i < cols; i++) for (let k = 0; k < 2; k++) s += box(20 + i * 56, 42 + k * 60, 50, 54, k ? '#e4a73e' : '#cf5b4a', 5, 2.5) + `<path d="M${20 + i * 56} ${42 + k * 60 + 27} h50 M${20 + i * 56 + 25} ${42 + k * 60} v54" stroke="${INK}" stroke-width="1.4" opacity="0.5"/>`;
  s += `<path d="M24 214 L30 186 L54 186 L60 214 Z" fill="#b6643a" ${st(3)}/><path d="${blob(42, 166, 11, 20, r, 3)}" fill="#3f8f4a" ${st(2.5)}/><path d="${blob(30, 172, 8, 16, r, 3)}" fill="#4fa85a" ${st(2.5)}/><path d="${blob(54, 170, 8, 16, r, 3)}" fill="#4fa85a" ${st(2.5)}/>`;
  return s;
}

function right(level, r, name) {
  if (level === 0) {
    return box(540, 160, 70, 54, '#a88458', 3) + box(556, 120, 52, 42, '#b89462', 3) + line('M548 172 H600 M566 132 H602', '#6e5636')
      + `<path d="M480 214 L478 170 M520 214 L522 182 M474 170 L528 166 M474 170 L470 126" ${st(4)} fill="none"/>`
      + line(`M${200} ${FLOOR + 50} C260 ${FLOOR + 66} 380 ${FLOOR + 40} 470 ${FLOOR + 64}`, INK, 3) + line(`M240 ${FLOOR + 62} C300 ${FLOOR + 44} 340 ${FLOOR + 62} 380 ${FLOOR + 54}`, '#7a4a3a', 3);
  }
  if (level === 1) {
    return box(530, 170, 90, 44, '#a88458', 3) + box(530, 128, 90, 44, '#a88458', 3) + line('M530 150 H620 M530 192 H620', '#6e5636')
      + `<rect x="546" y="132" width="20" height="14" fill="#cf5b4a" ${st(2)}/><rect x="574" y="136" width="30" height="10" fill="#3f8f86" ${st(2)}/>`
      + box(420, 60, 54, 72, '#f1e5c8', 3) + `<path d="${blob(447, 94, 15, 20, r, 3)}" fill="#3f6fa8"/>`;
  }
  if (level === 3) {
    // Goldene Platte an der Wand, Mischpult mit Bildschirm, Neonschrift.
    let s = box(360, 44, 62, 62, '#1d1a2a', 4) + `<circle cx="391" cy="75" r="22" fill="#e6b84f" ${st(2.4)}/><circle cx="391" cy="75" r="5" fill="#1d1a2a"/><path d="M376 64 Q384 56 394 56" stroke="#fff" stroke-width="2.4" fill="none" opacity="0.45" stroke-linecap="round"/>`;
    // Neonschild mit dem Namen der Band (lange Namen werden schmaler gesetzt, damit sie aufs Schild passen).
    const text = esc((name || 'Studio').toUpperCase());
    const size = Math.max(11, Math.min(22, 250 / Math.max(6, (name || 'Studio').length)));
    s += `<rect x="440" y="50" width="180" height="40" rx="10" fill="#141824" ${st(3)}/><text x="530" y="${70 + size * 0.36}" text-anchor="middle" font-family="system-ui, sans-serif" font-size="${size.toFixed(1)}" font-weight="800" letter-spacing="2" fill="none" stroke="#7fd0e6" stroke-width="${size > 15 ? 2 : 1.4}" textLength="${Math.min(164, (name || 'Studio').length * size * 0.78).toFixed(0)}" lengthAdjust="spacingAndGlyphs">${text}</text><ellipse cx="530" cy="70" rx="86" ry="22" fill="#7fd0e6" opacity="0.08"/>`;
    s += box(470, 132, 64, 40, '#141824', 3) + `<rect x="476" y="138" width="52" height="28" fill="#2f5f8a"/><path d="M480 160 l10 -10 l8 6 l12 -12 l14 8" stroke="#7fd0e6" stroke-width="2" fill="none"/><path d="M502 172 v10" ${st(3)}/>`;
    s += `<path d="M450 182 H630 L640 214 H440 Z" fill="#3a2a22" ${st(3)}/>` + [0, 1, 2, 3, 4, 5, 6].map((j) => `<rect x="${462 + j * 22}" y="188" width="12" height="16" rx="2" fill="#262c3d"/><circle cx="${468 + j * 22}" cy="${192 + (j % 3) * 4}" r="2.4" fill="${['#f2c14e', '#7fd0e6', '#ee7a8a'][j % 3]}"/>`).join('');
    return s;
  }
  let s = box(380, 40, 56, 78, '#f1e5c8', 3) + `<path d="${blob(408, 76, 16, 22, r, 3)}" fill="#cf5b4a"/>` + line('M388 102 H428', INK, 3);
  s += `<path d="${curve([[456, 214], [456, 178], [476, 166], [604, 166], [624, 178], [624, 214]], true)}" fill="#2f5f8a" ${st(3)}/><path d="M472 184 Q540 200 608 184" stroke="${INK}" stroke-width="2" fill="none" opacity="0.55"/>`;
  return s;
}

/** Proberaum als SVG; seed sorgt für kleine Unterschiede zwischen den Räumen verschiedener Bands. */
export function roomSvg(level = 0, seed = 1, name = '') {
  const r = rng(seed * 7 + level);
  const dark = level === 0 ? 0.36 : level === 1 ? 0.16 : 0;
  return `<svg class="lb-roomart" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <defs><linearGradient id="rmCone" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffe9a8" stop-opacity="0.5"/><stop offset="1" stop-color="#ffe9a8" stop-opacity="0"/></linearGradient></defs>
    ${walls(level, r)}${floor(level, r)}<g transform="translate(0 14)">${left(level, r)}</g><g transform="translate(${W - 640} 14)">${right(level, r, name)}</g>${ceiling(level)}
    ${dark ? `<rect width="${W}" height="${H}" fill="#0b0d14" opacity="${dark}"/>` : ''}</svg>`;
}
