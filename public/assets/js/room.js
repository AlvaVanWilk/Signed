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

/**
 * Gestaltung nach Genre (nur mit Genre-Bonus): Wand und Tapete, Boden, Teppich, Lichter, Lamellen, Polster.
 * Ohne Genre bleibt der Raum, wie er ist (null). Die Bruchbude ändert sich kaum, je besser der Raum, desto mehr.
 * rug: Form des Teppichs (oval, rund, eckig, zacken, ringe); lights: Farben der Lichterkette; slat: Lamellen [Fläche, Glanz, Rahmen].
 */
const THEMES = {
  pop: { wall: '#8e6a86', wall2: '#9c7894', paper: 'dots', floor: '#c9a27a', floorLine: '#a07c58', rug: '#e07aa8', rugShape: 'rund', lights: ['#ff9ec7', '#ffe08a', '#9fe6ff', '#c9a8ff'], slat: ['#f2d6e4', '#fff', '#b07a98'], panels: ['#e07aa8', '#7fd0e6'], sofa: '#c95f8f', chairs: ['#e07aa8', '#7fd0e6'], shade: '#ff9ec7', led: '#ff9ec7' },
  rock: { wall: '#5a3a36', wall2: '#4c302c', paper: 'bricks', floor: '#5a3e2c', floorLine: '#3e2a1e', rug: '#1d1a24', rugEdge: '#c0392b', rugShape: 'eckig', lights: ['#ffb05a', '#ff7a3a', '#ffd27a'], slat: ['#6a4024', '#a06a3b', '#3a2416'], panels: ['#c0392b', '#2a2433'], sofa: '#2a2433', chairs: ['#c0392b', '#2a2433'], shade: '#c0392b', led: '#ff7a3a' },
  metal: { wall: '#2c2e36', wall2: '#363944', paper: 'plates', floor: '#3a3d46', floorLine: '#2a2c34', rug: '#4a4e58', rugEdge: '#9aa3b0', rugShape: 'zacken', lights: ['#e8eef6', '#c9d2de'], slat: ['#8a929e', '#e8eef6', '#4a4e58'], panels: ['#4a4e58', '#1d1a24'], sofa: '#1d1a24', chairs: ['#4a4e58', '#1d1a24'], shade: '#6b7280', led: '#e8eef6' },
  punk: { wall: '#4a4a4e', wall2: '#3c3c40', paper: 'diag', floor: '#5a5a5e', floorLine: '#464648', rug: '#b9d63a', rugEdge: '#1d1a24', rugShape: 'schach', lights: ['#b9d63a', '#ff4f8b', '#ffe24a'], slat: ['#b9d63a', '#ff4f8b', '#1d1a24'], panels: ['#b9d63a', '#ff4f8b'], sofa: '#ff4f8b', chairs: ['#b9d63a', '#ff4f8b'], shade: '#b9d63a', led: '#b9d63a' },
  jazz: { wall: '#2f3556', wall2: '#383f63', paper: 'pinstripe', floor: '#5e3a28', floorLine: '#47291b', rug: '#7a2638', rugEdge: '#d9b45a', rugShape: 'oval', lights: ['#ffd27a', '#ffe9b0'], slat: ['#6e2f1c', '#b0683a', '#3a1a0e'], panels: ['#7a2638', '#d9b45a'], sofa: '#7a2638', chairs: ['#7a2638', '#5e3a28'], shade: '#d9b45a', led: '#ffd27a' },
  elektro: { wall: '#1c2236', wall2: '#2a3354', paper: 'grid', floor: '#232a3e', floorLine: '#384466', rug: '#1d1a2a', rugEdge: '#5fd6ff', rugShape: 'eckig', lights: ['#5fd6ff', '#ff4fd8'], slat: ['#7fd0e6', '#ffffff', '#2a3354'], slatGlass: true, panels: ['#2a3354', '#5fd6ff'], sofa: '#2a3354', chairs: ['#5fd6ff', '#ff4fd8'], shade: '#5fd6ff', led: '#5fd6ff' },
  hiphop: { wall: '#4e4660', wall2: '#5a5270', paper: 'bricks', floor: '#585862', floorLine: '#46464e', rug: '#f2c14e', rugEdge: '#6b4fb0', rugShape: 'rund', lights: ['#a58cff', '#f2c14e'], slat: ['#6b4fb0', '#a58cff', '#2a2240'], panels: ['#6b4fb0', '#f2c14e'], sofa: '#6b4fb0', chairs: ['#f2c14e', '#6b4fb0'], shade: '#f2c14e', led: '#a58cff' },
  folk: { wall: '#7a5a3e', wall2: '#86664a', paper: 'planks', floor: '#c49a66', floorLine: '#9a7444', rug: '#5f8f5a', rugEdge: '#d9a54a', rugShape: 'ringe', lights: ['#ffd27a', '#ffb05a'], lanterns: true, slat: ['#d9b98a', '#fff3d6', '#8a6a44'], panels: ['#5f8f5a', '#d9a54a'], sofa: '#5f8f5a', chairs: ['#d9a54a', '#5f8f5a'], shade: '#d9a54a', led: '#ffd27a' },
};

/** Muster der Tapete über die Wand (nur ab dem aufgeräumten Keller). */
function paper(kind, c) {
  let s = '';
  if (kind === 'dots') for (let y = 20; y < FLOOR; y += 34) for (let x = (y / 34) % 2 ? 0 : 17; x < W; x += 34) s += `<circle cx="${x}" cy="${y}" r="4" fill="${c}"/>`;
  if (kind === 'bricks') for (let y = 0, k = 0; y < FLOOR; y += 22, k++) for (let x = k % 2 ? -24 : 0; x < W; x += 48) s += `<rect x="${x + 2}" y="${y + 2}" width="44" height="18" rx="2" fill="${c}"/>`;
  if (kind === 'plates') for (let y = 0; y < FLOOR; y += 76) for (let x = 0; x < W; x += 120) s += `<rect x="${x + 3}" y="${y + 3}" width="114" height="70" rx="3" fill="${c}"/>` + [[10, 10], [107, 10], [10, 63], [107, 63]].map(([dx, dy]) => `<circle cx="${x + dx}" cy="${y + dy}" r="2.6" fill="#5a5e6a"/>`).join('');
  if (kind === 'diag') for (let x = -FLOOR; x < W; x += 40) s += `<path d="M${x} ${FLOOR} L${x + FLOOR} 0" stroke="${c}" stroke-width="14"/>`;
  if (kind === 'pinstripe') for (let x = 0; x < W; x += 24) s += `<rect x="${x}" y="0" width="3" height="${FLOOR}" fill="${c}"/>`;
  if (kind === 'grid') for (let x = 0; x < W; x += 40) s += `<path d="M${x} 0 V${FLOOR}" stroke="${c}" stroke-width="1.4"/>` + (x < FLOOR ? `<path d="M0 ${x} H${W}" stroke="${c}" stroke-width="1.4"/>` : '');
  if (kind === 'planks') for (let x = 0; x < W; x += 52) s += `<path d="M${x} 0 V${FLOOR}" stroke="${c}" stroke-width="3"/><circle cx="${x + 26}" cy="${(x * 7) % FLOOR}" r="3" fill="${c}"/>`;
  return s;
}

/** Teppich in der Mitte; Form und Rand nach Genre. pts: Umriss wie bisher, k: Größe. */
function rug(T, pts, fallback) {
  if (!T) return `<path d="${curve(pts, true)}" fill="${fallback}" ${st(3)}/>`;
  const xs = pts.map((p) => p[0]); const ys = pts.map((p) => p[1]);
  const cx = (Math.min(...xs) + Math.max(...xs)) / 2; const cy = (Math.min(...ys) + Math.max(...ys)) / 2;
  const rx = (Math.max(...xs) - Math.min(...xs)) / 2; const ry = (Math.max(...ys) - Math.min(...ys)) / 2;
  const edge = T.rugEdge ?? INK;
  switch (T.rugShape) {
    case 'eckig': return `<rect x="${cx - rx * 0.92}" y="${cy - ry}" width="${rx * 1.84}" height="${ry * 2}" rx="4" fill="${T.rug}" ${st(3)}/><rect x="${cx - rx * 0.92 + 8}" y="${cy - ry + 5}" width="${rx * 1.84 - 16}" height="${ry * 2 - 10}" rx="3" fill="none" stroke="${edge}" stroke-width="3"/>`;
    case 'rund': return `<ellipse cx="${cx}" cy="${cy}" rx="${rx * 0.8}" ry="${ry}" fill="${T.rug}" ${st(3)}/><ellipse cx="${cx}" cy="${cy}" rx="${rx * 0.8 - 12}" ry="${ry - 6}" fill="none" stroke="${edge}" stroke-width="3"/>`;
    case 'zacken': {
      const n = 26; let d = '';
      for (let i = 0; i < n; i++) { const a = (i / n) * Math.PI * 2; const k = i % 2 ? 0.86 : 1.04; d += `${i ? 'L' : 'M'}${f(cx + Math.cos(a) * rx * k)} ${f(cy + Math.sin(a) * ry * k)} `; }
      return `<path d="${d}Z" fill="${T.rug}" ${st(3)}/><ellipse cx="${cx}" cy="${cy}" rx="${rx * 0.6}" ry="${ry * 0.55}" fill="none" stroke="${edge}" stroke-width="2.4"/>`;
    }
    case 'schach': {
      // Schachbrett: ganze Felder über die Fläche verteilt (ohne Ausschnitt-Kennung, damit mehrere Räume auf einer Seite nicht kollidieren).
      const cols = Math.max(4, Math.round((rx * 1.8) / 18)); const rows = Math.max(2, Math.round((ry * 2) / 12));
      const w = (rx * 1.8) / cols; const h = (ry * 2) / rows; const x0 = cx - rx * 0.9; const y0 = cy - ry;
      let c = `<rect x="${f(x0)}" y="${f(y0)}" width="${f(rx * 1.8)}" height="${f(ry * 2)}" rx="4" fill="${T.rug}"/>`;
      for (let i = 0; i < cols; i++) for (let k = 0; k < rows; k++) if ((i + k) % 2) c += `<rect x="${f(x0 + i * w)}" y="${f(y0 + k * h)}" width="${f(w)}" height="${f(h)}" fill="${edge}"/>`;
      return `${c}<rect x="${f(x0)}" y="${f(y0)}" width="${f(rx * 1.8)}" height="${f(ry * 2)}" rx="4" fill="none" ${st(3)}/>`;
    }
    case 'ringe': return [1, 0.78, 0.56, 0.34].map((k, i) => `<ellipse cx="${cx}" cy="${cy}" rx="${f(rx * 0.85 * k)}" ry="${f(ry * k)}" fill="${i % 2 ? edge : T.rug}" ${i ? '' : st(3)}/>`).join('');
    default: return `<path d="${curve(pts, true)}" fill="${T.rug}" ${st(3)}/><path d="${curve(pts.map(([x, y]) => [cx + (x - cx) * 0.86, cy + (y - cy) * 0.8]), true)}" fill="none" stroke="${edge}" stroke-width="2.4"/>`
      + Array.from({ length: 22 }, (_, i) => `<path d="M${f(cx - rx * 0.8 + i * rx * 1.6 / 21)} ${f(cy + ry - 2)} v7" stroke="${edge}" stroke-width="2"/>`).join('');
  }
}

function walls(level, r, T) {
  let s = '';
  if (level === 0) {
    s += `<rect width="${W}" height="${FLOOR}" fill="#3a4350"/>`;
    for (let i = 0; i < 11; i++) s += `<path d="${blob(30 + r() * (W - 60), 40 + r() * 160, 14 + r() * 30, 8 + r() * 16, r, 6)}" fill="#323a46" opacity="0.85"/>`;
    s += `<path d="${blob(760, 120, 52, 66, r, 12)}" fill="#2d3c40" opacity="0.9"/>`;
    s += line('M190 30 L178 80 L196 128 M178 80 L154 106', '#222a32', 2.4);
  } else if (level === 1) {
    // Derselbe Keller, nur aufgeräumt: hellerer Anstrich (mit Genre in dessen Farbe, dezent gemustert), ein paar Flecken sind geblieben.
    s += `<rect width="${W}" height="${FLOOR}" fill="${T ? T.wall : '#4a5462'}"/>`;
    if (T) s += `<g opacity="0.45">${paper(T.paper, T.wall2)}</g>`;
    for (let i = 0; i < 4; i++) s += `<path d="${blob(30 + r() * (W - 60), 40 + r() * 160, 12 + r() * 20, 7 + r() * 10, r, 5)}" fill="${T ? '#000' : '#424b58'}" opacity="${T ? 0.1 : 0.7}"/>`;
  } else if (level === 2) {
    s += `<rect width="${W}" height="${FLOOR}" fill="${T ? T.wall : '#7a4e58'}"/>`;
    if (T) s += paper(T.paper, T.wall2);
    else for (let x = 0; x < W; x += 46) s += `<rect x="${x}" y="0" width="23" height="${FLOOR}" fill="#835560" opacity="0.55"/>`;
  } else {
    // Profi-Studio: dunkle Wände, Lamellen hinter der Band (Holz, mit Genre auch Glas, Metall oder Lack).
    const [slat, hi, frame] = T?.slat ?? ['#a06a3b', '#fff', '#5a3b2a'];
    s += `<rect width="${W}" height="${FLOOR}" fill="${T ? T.wall : '#262c3d'}"/>`;
    if (T) s += `<g opacity="0.35">${paper(T.paper, T.wall2)}</g>`;
    s += box(MID + 150, 34, 340, FLOOR - 40, frame, 4);
    for (let x = MID + 158, i = 0; x < MID + 484; x += 18, i++) {
      const c = T?.rugShape === 'schach' ? T.slat[i % 2] : slat;
      s += `<rect x="${x}" y="40" width="11" height="${FLOOR - 52}" rx="2" fill="${c}" opacity="${T?.slatGlass ? 0.45 : 1}"/><rect x="${x}" y="40" width="3" height="${FLOOR - 52}" fill="${hi}" opacity="${T?.slatGlass ? 0.5 : 0.08}"/>`;
    }
  }
  return s;
}

function floor(level, r, T) {
  let s = '';
  if (level <= 1) {
    s += `<rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="${level ? '#5a5a62' : '#4a4a50'}"/>`;
    for (let i = 0; i < (level ? 3 : 8); i++) s += `<path d="${blob(40 + r() * (W - 80), FLOOR + 20 + r() * 60, 20 + r() * 30, 5 + r() * 6, r, 4)}" fill="#3e3e44" opacity="0.7"/>`;
    if (level) s += `<g transform="translate(${MID} 14)">${rug(T, [[170, 262], [230, 246], [410, 246], [470, 262], [420, 284], [220, 284]], '#8a4a3a')}</g>`;
  } else if (level === 2) {
    s += `<rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="${T ? T.floor : '#b7814e'}"/>`;
    for (let x = 0; x < W + 60; x += 58) s += line(`M${x} ${FLOOR} L${x - 26} ${H}`, T ? T.floorLine : '#8a5d36', 2);
    s += `<g transform="translate(${MID} 14)">${rug(T, [[150, 262], [220, 244], [420, 244], [490, 262], [430, 288], [210, 288]], '#3f8f86')}</g>`;
  } else {
    // Dunkles, geöltes Parkett mit Glanz, großer runder Teppich (mit Genre: eigener Boden und Teppich).
    s += `<rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="${T ? T.floor : '#4a3226'}"/>`;
    for (let x = 0; x < W + 60; x += 40) s += line(`M${x} ${FLOOR} L${x - 18} ${H}`, T ? T.floorLine : '#3a261c', 2);
    s += `<rect y="${FLOOR}" width="${W}" height="14" fill="#fff" opacity="0.05"/>`;
    s += T ? `<g transform="translate(${MID} 14)">${rug(T, [[70, 262], [170, 238], [470, 238], [570, 262], [490, 298], [150, 298]])}</g>`
      : `<g transform="translate(${MID} 14)"><path d="${curve([[70, 262], [170, 238], [470, 238], [570, 262], [490, 298], [150, 298]], true)}" fill="#6e2a3a" ${st(3)}/><path d="${curve([[120, 262], [200, 248], [440, 248], [520, 262], [450, 288], [190, 288]], true)}" fill="none" stroke="#c9a86a" stroke-width="2.4"/></g>`;
  }
  return s + line(`M0 ${FLOOR} H${W}`, INK, 3);
}

function ceiling(level, T) {
  let s = '';
  if (level <= 1) {
    s += box(-6, 10, W + 12, 12, level ? '#6a6460' : '#6a5648', 6) + line(`M80 16 v6 M${W / 2} 16 v6 M${W - 80} 16 v6`, '#a8805a', 3);
  }
  s += `<g transform="translate(${MID} 0)">`;
  if (level === 0) {
    s += `<path d="M320 22 L320 70" ${st(2.5)} fill="none"/><ellipse cx="320" cy="80" rx="11" ry="14" fill="#ffe08a" ${st(3)}/>`;
    s += `<path d="M320 92 L200 ${FLOOR + 40} L440 ${FLOOR + 40} Z" fill="url(#rmCone)" opacity="0.85"/>`;
  } else if (level === 1) {
    s += `<path d="M320 22 L320 56" ${st(2.5)} fill="none"/><path d="M296 74 L344 74 L332 54 L308 54 Z" fill="${T ? T.shade : '#e4a73e'}" ${st(3)}/><ellipse cx="320" cy="76" rx="9" ry="5" fill="#ffe9a8"/>`;
    s += `<path d="M300 76 L170 ${FLOOR + 40} L470 ${FLOOR + 40} L340 76 Z" fill="url(#rmCone)" opacity="0.7"/>`;
  } else if (level === 3) {
    s += `</g><g>`;
    s += `<rect x="-6" y="6" width="${W + 12}" height="10" fill="#3b4256" ${st(2.5)}/><rect x="0" y="16" width="${W}" height="3" fill="${T ? T.led : '#7fd0e6'}" opacity="0.8"/><rect x="0" y="16" width="${W}" height="16" fill="${T ? T.led : '#7fd0e6'}" opacity="0.1"/>`;
    for (const x of [MID + 200, MID + 320, MID + 440]) s += `<path d="M${x - 12} 16 h24 l-4 18 h-16 Z" fill="#1d1a2a" ${st(2.2)}/><path d="M${x - 8} 34 L${x - 70} ${FLOOR + 30} L${x + 70} ${FLOOR + 30} L${x + 8} 34 Z" fill="url(#rmCone)" opacity="0.45"/>`;
  } else {
    const n = 6;
    s += `</g><g>`;
    s += `<path d="${curve([[0, 12], [160, 36], [320, 14], [480, 38], [640, 14], [800, 36], [960, 12]])}" ${st(2)} fill="none"/>`;
    const colors = T?.lights ?? ['#f2c14e', '#ee7a8a', '#7fd0e6'];
    for (let i = 0; i < n; i++) {
      const x = 40 + i * ((W - 80) / (n - 1)), y = 22 + Math.sin(i * 1.7) * 8;
      const c = colors[i % colors.length];
      // Folk: kleine Laternen statt Glühbirnen
      s += T?.lanterns ? `<circle cx="${f(x)}" cy="${f(y + 6)}" r="13" fill="${c}" opacity="0.22"/><path d="M${f(x - 6)} ${f(y)} h12 l3 14 h-18 Z" fill="${c}" ${st(1.8)}/><path d="M${f(x - 4)} ${f(y - 3)} h8" ${st(2)}/>`
        : `<circle cx="${f(x)}" cy="${f(y)}" r="11" fill="${c}" opacity="0.25"/><circle cx="${f(x)}" cy="${f(y)}" r="5" fill="${c}" ${st(1.8)}/>`;
    }
  }
  return s + '</g>';
}

function left(level, r, T) {
  if (level === 0) {
    // Altes Sofa; mit Genre liegt eine Decke in dessen Farbe darauf.
    return box(56, 56, 66, 50, '#6f8a96', 3) + line('M89 56 v50 M56 81 h66') + box(52, 52, 74, 58, 'none', 3)
      + `<path d="${curve([[30, 208], [62, 190], [150, 188], [178, 208], [164, 220], [44, 220]], true)}" fill="#5c5a63" ${st(3)}/>`
      + (T ? `<path d="M70 192 Q100 186 132 190 L128 214 Q98 218 74 214 Z" fill="${T.sofa}" ${st(2.4)} opacity="0.9"/>` : '');
  }
  if (level === 1) {
    // Fenster geputzt, der Schutt ist weg: zwei einfache Klappstühle.
    const chair = (x, c) => `<path d="M${x} 214 L${x + 6} 178 M${x + 34} 214 L${x + 28} 178 M${x + 4} 192 H${x + 30}" ${st(3.4)} fill="none"/>`
      + box(x, 172, 34, 8, c, 3, 2.6) + box(x + 3, 138, 28, 30, c, 4, 2.6) + `<path d="M${x + 6} 168 V178 M${x + 28} 168 V178" ${st(3)} fill="none"/>`;
    return box(56, 56, 66, 50, '#7f9ca8', 3) + line('M89 56 v50 M56 81 h66') + box(52, 52, 74, 58, 'none', 3) + chair(40, T ? T.chairs[0] : '#8a8f99') + chair(104, T ? T.chairs[1] : '#a0705a');
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
  const [pa, pb] = T?.panels ?? ['#cf5b4a', '#e4a73e'];
  for (let i = 0; i < cols; i++) for (let k = 0; k < 2; k++) s += box(20 + i * 56, 42 + k * 60, 50, 54, k ? pb : pa, 5, 2.5) + `<path d="M${20 + i * 56} ${42 + k * 60 + 27} h50 M${20 + i * 56 + 25} ${42 + k * 60} v54" stroke="${INK}" stroke-width="1.4" opacity="0.5"/>`;
  s += `<path d="M24 214 L30 186 L54 186 L60 214 Z" fill="#b6643a" ${st(3)}/><path d="${blob(42, 166, 11, 20, r, 3)}" fill="#3f8f4a" ${st(2.5)}/><path d="${blob(30, 172, 8, 16, r, 3)}" fill="#4fa85a" ${st(2.5)}/><path d="${blob(54, 170, 8, 16, r, 3)}" fill="#4fa85a" ${st(2.5)}/>`;
  return s;
}

function right(level, r, name, T) {
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
    // Goldene Platte an der Wand, Mischpult mit Bildschirm (der Name steht als Leuchtschrift links, nameSign).
    let s = box(360, 44, 62, 62, '#1d1a2a', 4) + `<circle cx="391" cy="75" r="22" fill="#e6b84f" ${st(2.4)}/><circle cx="391" cy="75" r="5" fill="#1d1a2a"/><path d="M376 64 Q384 56 394 56" stroke="#fff" stroke-width="2.4" fill="none" opacity="0.45" stroke-linecap="round"/>`;
    // Neonschild mit dem Namen der Band (lange Namen werden schmaler gesetzt, damit sie aufs Schild passen).
    return s + rightDesk();
  }
  let s = box(380, 40, 56, 78, '#f1e5c8', 3) + `<path d="${blob(408, 76, 16, 22, r, 3)}" fill="#cf5b4a"/>` + line('M388 102 H428', INK, 3);
  s += `<path d="${curve([[456, 214], [456, 178], [476, 166], [604, 166], [624, 178], [624, 214]], true)}" fill="${T ? T.sofa : '#2f5f8a'}" ${st(3)}/><path d="M472 184 Q540 200 608 184" stroke="${INK}" stroke-width="2" fill="none" opacity="0.55"/>`;
  return s;
}

/**
 * Name der Band als Schild an der Wand, je nach Raum: Holzbrett (Bruchbude), Pappe mit Klebeband (Keller), Metallschild (Proberaum),
 * Leuchtschrift (Profi-Raum, mittig auf der Lamellenwand). Es hängt oben über der Band an der Wand; alles im Raum liegt davor.
 */
function nameSign(level, name) {
  const text = esc(name);
  const size = 22;
  const tw = Math.min(180, Math.max(60, name.length * size * 0.6));
  const w = tw + 34;
  const h = 40;
  // Im Profi-Raum leuchtet der Name genau mittig oben auf der Lamellenwand (sie reicht von MID + 150 bis MID + 490).
  const x = (level === 3 ? MID + 320 : MID + 200) - w / 2;
  const y = level === 2 ? 50 : 40;
  const label = (fill, extra = '') => `<text x="${f(x + w / 2)}" y="${f(y + h / 2 + size * 0.36)}" text-anchor="middle" font-family="system-ui, sans-serif" font-size="${size}" font-weight="900" textLength="${f(tw)}" lengthAdjust="spacingAndGlyphs" fill="${fill}" ${extra}>${text}</text>`;
  if (level === 0) {
    return `<path d="M${f(x + 18)} ${y} L${f(x + 30)} 18 M${f(x + w - 18)} ${y} L${f(x + w - 30)} 18" ${st(2)} fill="none"/>`
      + `<g transform="rotate(-2 ${f(x + w / 2)} ${y + h / 2})"><rect x="${f(x)}" y="${y}" width="${f(w)}" height="${h}" rx="5" fill="#7a4c2c" ${st(3)}/><path d="M${f(x + 6)} ${y + 14} H${f(x + w - 10)} M${f(x + 10)} ${y + 28} H${f(x + w - 6)}" stroke="#5e3a20" stroke-width="2"/>`
      + label('#f6e7c8', `stroke="#3a2416" stroke-width="1" paint-order="stroke"`) + '</g>';
  }
  if (level === 1) {
    const tape = (tx, ty, a) => `<rect x="${f(tx - 15)}" y="${f(ty - 5)}" width="30" height="10" fill="#efe6cc" opacity="0.85" transform="rotate(${a} ${f(tx)} ${f(ty)})"/>`;
    return `<g transform="rotate(1.5 ${f(x + w / 2)} ${y + h / 2})"><path d="M${f(x)} ${y + 2} L${f(x + w * 0.4)} ${y} L${f(x + w)} ${y + 3} L${f(x + w - 2)} ${y + h} L${f(x + w * 0.55)} ${y + h - 2} L${f(x + 2)} ${y + h + 1} Z" fill="#c9a46c" stroke="#8a6a3e" stroke-width="1.5"/>`
      + label('#2a2233') + tape(x + 2, y + 3, -38) + tape(x + w - 2, y + 4, 38) + '</g>';
  }
  if (level === 2) {
    const rivet = (rx, ry) => `<circle cx="${f(rx)}" cy="${f(ry)}" r="2.6" fill="#7a818e"/><circle cx="${f(rx - 0.6)}" cy="${f(ry - 0.6)}" r="1" fill="#fff" opacity="0.8"/>`;
    return `<rect x="${f(x)}" y="${y}" width="${f(w)}" height="${h}" rx="6" fill="#c3cad4" ${st(3)}/><rect x="${f(x + 3)}" y="${y + 3}" width="${f(w - 6)}" height="${h / 2 - 3}" rx="4" fill="#fff" opacity="0.35"/>`
      + rivet(x + 8, y + 8) + rivet(x + w - 8, y + 8) + rivet(x + 8, y + h - 8) + rivet(x + w - 8, y + h - 8) + label('#2b3240', `letter-spacing="2" stroke="#fff" stroke-width="0.6" paint-order="stroke"`);
  }
  // Leuchtschrift: dunkler Kasten, Buchstaben als leuchtende Kontur.
  return `<ellipse cx="${f(x + w / 2)}" cy="${y + h / 2}" rx="${f(w * 0.62)}" ry="${h}" fill="#7fd0e6" opacity="0.07"/><rect x="${f(x)}" y="${y}" width="${f(w)}" height="${h}" rx="10" fill="#141824" ${st(3)}/>`
    + label('none', `stroke="#7fd0e6" stroke-width="5" opacity="0.25"`) + label('none', `stroke="#7fd0e6" stroke-width="1.8"`);
}

/** Mischpult mit Bildschirm im Profi-Raum. */
function rightDesk() {
  return box(470, 132, 64, 40, '#141824', 3) + `<rect x="476" y="138" width="52" height="28" fill="#2f5f8a"/><path d="M480 160 l10 -10 l8 6 l12 -12 l14 8" stroke="#7fd0e6" stroke-width="2" fill="none"/><path d="M502 172 v10" ${st(3)}/>`
    + `<path d="M450 182 H630 L640 214 H440 Z" fill="#3a2a22" ${st(3)}/>` + [0, 1, 2, 3, 4, 5, 6].map((j) => `<rect x="${462 + j * 22}" y="188" width="12" height="16" rx="2" fill="#262c3d"/><circle cx="${468 + j * 22}" cy="${192 + (j % 3) * 4}" r="2.4" fill="${['#f2c14e', '#7fd0e6', '#ee7a8a'][j % 3]}"/>`).join('');
}

/**
 * Proberaum als SVG; seed sorgt für kleine Unterschiede zwischen den Räumen verschiedener Bands.
 * genre: gemeinsames Genre der Band (nur mit Genre-Bonus), dann ist der Raum in dessen Stil eingerichtet.
 */
export function roomSvg(level = 0, seed = 1, name = '', genre = null) {
  const r = rng(seed * 7 + level);
  const T = THEMES[genre] ?? null;
  const dark = level === 0 ? 0.36 : level === 1 ? 0.16 : 0;
  return `<svg class="lb-roomart" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
    <defs><linearGradient id="rmCone" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ffe9a8" stop-opacity="0.5"/><stop offset="1" stop-color="#ffe9a8" stop-opacity="0"/></linearGradient></defs>
    ${walls(level, r, T)}${name ? nameSign(level, name) : ''}${floor(level, r, T)}<g transform="translate(0 14)">${left(level, r, T)}</g><g transform="translate(${W - 640} 14)">${right(level, r, name, T)}</g>${ceiling(level, T)}
    ${dark ? `<rect width="${W}" height="${H}" fill="#0b0d14" opacity="${dark}"/>` : ''}</svg>`;
}
