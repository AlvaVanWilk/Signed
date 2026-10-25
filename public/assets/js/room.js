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
    s += `<rect width="${W}" height="${FLOOR}" fill="#55657a"/><rect y="${FLOOR - 40}" width="${W}" height="40" fill="#4c5b6e"/>${line(`M0 ${FLOOR - 40} H${W}`, '#3e4a5a', 3)}`;
  } else if (level === 2) {
    s += `<rect width="${W}" height="${FLOOR}" fill="#7a4e58"/>`;
    for (let x = 0; x < W; x += 46) s += `<rect x="${x}" y="0" width="23" height="${FLOOR}" fill="#835560" opacity="0.55"/>`;
  } else {
    s += `<rect width="${W}" height="${FLOOR}" fill="#6b3f4d"/>`;
    for (let x = 0; x < W; x += 46) s += `<rect x="${x}" y="0" width="23" height="${FLOOR}" fill="#74455a" opacity="0.55"/>`;
  }
  return s;
}

function floor(level, r) {
  let s = '';
  if (level <= 1) {
    s += `<rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="${level ? '#5a5a62' : '#4a4a50'}"/>`;
    for (let i = 0; i < (level ? 3 : 8); i++) s += `<path d="${blob(40 + r() * (W - 80), FLOOR + 20 + r() * 60, 20 + r() * 30, 5 + r() * 6, r, 4)}" fill="#3e3e44" opacity="0.7"/>`;
    if (level) s += `<g transform="translate(${MID} 14)"><path d="${curve([[170, 262], [230, 246], [410, 246], [470, 262], [420, 284], [220, 284]], true)}" fill="#8a4a3a" ${st(3)}/></g>`;
  } else {
    s += `<rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="#b7814e"/>`;
    for (let x = 0; x < W + 60; x += 58) s += line(`M${x} ${FLOOR} L${x - 26} ${H}`, '#8a5d36', 2);
    s += `<g transform="translate(${MID} 14)">` + (level === 2
      ? `<path d="${curve([[150, 262], [220, 244], [420, 244], [490, 262], [430, 288], [210, 288]], true)}" fill="#3f8f86" ${st(3)}/>`
      : `<path d="${curve([[90, 262], [180, 240], [460, 240], [550, 262], [480, 296], [160, 296]], true)}" fill="#3f8f86" ${st(3)}/><path d="${curve([[130, 262], [200, 248], [440, 248], [510, 262], [450, 286], [190, 286]], true)}" fill="none" stroke="#f1e0b0" stroke-width="3" stroke-dasharray="10 8"/>`) + '</g>';
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
  } else {
    const n = level === 2 ? 6 : 11;
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
    return box(56, 56, 66, 50, '#8fb0bd', 3) + line('M89 56 v50 M56 81 h66') + `<path d="M50 52 Q60 84 54 112 L44 112 L44 52 Z M128 52 Q118 84 124 112 L134 112 L134 52 Z" fill="#c95c4e" ${st(2.5)}/>`
      + `<path d="${curve([[26, 214], [26, 182], [44, 172], [156, 172], [172, 182], [172, 214]], true)}" fill="#6a5a7a" ${st(3)}/><path d="M44 190 Q98 204 156 190" stroke="${INK}" stroke-width="2" fill="none" opacity="0.5"/>`;
  }
  // Akustikpaneele, bei Stufe 2 nur zum Teil
  let s = '';
  const cols = level === 2 ? 3 : 5;
  for (let i = 0; i < cols; i++) for (let k = 0; k < 2; k++) s += box(20 + i * 56, 42 + k * 60, 50, 54, k ? '#e4a73e' : '#cf5b4a', 5, 2.5) + `<path d="M${20 + i * 56} ${42 + k * 60 + 27} h50 M${20 + i * 56 + 25} ${42 + k * 60} v54" stroke="${INK}" stroke-width="1.4" opacity="0.5"/>`;
  s += `<path d="M24 214 L30 186 L54 186 L60 214 Z" fill="#b6643a" ${st(3)}/><path d="${blob(42, 166, 11, 20, r, 3)}" fill="#3f8f4a" ${st(2.5)}/><path d="${blob(30, 172, 8, 16, r, 3)}" fill="#4fa85a" ${st(2.5)}/><path d="${blob(54, 170, 8, 16, r, 3)}" fill="#4fa85a" ${st(2.5)}/>`;
  return s;
}

function right(level, r) {
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
  let s = box(380, 40, 56, 78, '#f1e5c8', 3) + `<path d="${blob(408, 76, 16, 22, r, 3)}" fill="#cf5b4a"/>` + line('M388 102 H428', INK, 3);
  s += `<path d="${curve([[456, 214], [456, 178], [476, 166], [604, 166], [624, 178], [624, 214]], true)}" fill="#2f5f8a" ${st(3)}/><path d="M472 184 Q540 200 608 184" stroke="${INK}" stroke-width="2" fill="none" opacity="0.55"/>`;
  if (level === 3) {
    s += box(452, 50, 50, 64, '#2f5f8a', 3) + `<circle cx="477" cy="80" r="13" fill="#f2c14e"/>`;
    s += `<rect x="520" y="104" width="104" height="36" rx="8" fill="#241b34" ${st(3)}/><text x="572" y="129" text-anchor="middle" font-family="Georgia,serif" font-size="21" font-weight="700" fill="#ff8fb0" letter-spacing="3">LIVE</text>`;
    s += box(540, 82, 70, 6, '#5a3b2a', 2) + `<path d="M558 82 l0 -18 l20 0 l0 18 z M564 64 l0 -7 l8 0 l0 7" fill="#f2c14e" ${st(2.2)}/>`;
  }
  return s;
}

/** Proberaum als SVG; seed sorgt für kleine Unterschiede zwischen den Räumen verschiedener Bands. */
export function roomSvg(level = 0, seed = 1) {
  const r = rng(seed * 7 + level);
  const dark = level === 0 ? 0.36 : level === 1 ? 0.16 : 0;
  return `<svg class="lb-roomart" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <defs><linearGradient id="rmCone" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffe9a8" stop-opacity="0.5"/><stop offset="1" stop-color="#ffe9a8" stop-opacity="0"/></linearGradient></defs>
    ${walls(level, r)}${floor(level, r)}<g transform="translate(0 14)">${left(level, r)}</g><g transform="translate(${W - 640} 14)">${right(level, r)}</g>${ceiling(level)}
    ${dark ? `<rect width="${W}" height="${H}" fill="#0b0d14" opacity="${dark}"/>` : ''}</svg>`;
}
