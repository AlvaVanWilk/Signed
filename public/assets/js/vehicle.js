/**
 * Bus und Flugzeug von innen, gezeichnet wie der Proberaum (room.js): gleiche Größe, die Mitte bleibt frei für die Band.
 * Je vier Stufen (Balance::VEHICLES): VW-Bus, Kleinbus mit Anhänger, Tourbus, Nightliner; Propellerflugzeug, Privatjet, Großer Jet, Tour-Jumbo.
 * Unterwegs zieht hinter den Fenstern die Landschaft (Bus) oder ziehen Wolken (Flugzeug) vorbei; steht das Fahrzeug, sieht man die Stadt.
 */
const INK = '#1d1a2a';
const W = 960;
const H = 320;
const FLOOR = 228;

const f = (n) => Math.round(n * 10) / 10;
const esc = (t) => String(t).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const st = (w = 3) => `stroke="${INK}" stroke-width="${w}" stroke-linejoin="round" stroke-linecap="round"`;
const box = (x, y, w, h, fill, rx = 3, sw = 3) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${rx}" fill="${fill}" ${st(sw)}/>`;
const line = (d, c = INK, w = 2) => `<path d="${d}" stroke="${c}" stroke-width="${w}" fill="none" stroke-linecap="round"/>`;

/** Farben und Fenster je Fahrzeug und Stufe. win: [x, y, b, h, rx]; band: Ort des Namensschilds (null = keins). */
const LOOK = {
  bus: [
    { name: 'VW-Bus', wall: '#e9dcc0', wall2: '#d8c7a4', trim: '#c4572e', floor: '#6b6f78', ceil: '#d8c7a4', win: { y: 58, h: 86, rx: 18, gap: 30, w: 150 }, curtain: '#e0a23a' },
    { name: 'Kleinbus mit Anhänger', wall: '#b9c0c9', wall2: '#a7afba', trim: '#4f6d8c', floor: '#5b6068', ceil: '#9aa3ae', win: { y: 52, h: 92, rx: 10, gap: 22, w: 160 }, rack: true },
    { name: 'Tourbus', wall: '#8a5e3c', wall2: '#7a5034', trim: '#c9a86a', floor: '#4a3a30', ceil: '#3a2c24', win: { y: 50, h: 88, rx: 8, gap: 26, w: 170 }, led: '#ffd27a' },
    { name: 'Nightliner', wall: '#2a2c3a', wall2: '#33364a', trim: '#c9a86a', floor: '#2a2430', ceil: '#1d1a2a', win: { y: 48, h: 80, rx: 6, gap: 30, w: 150 }, led: '#7fd0e6', sign: true },
  ],
  plane: [
    { name: 'Propellerflugzeug', wall: '#dfe3ea', wall2: '#cfd5de', trim: '#5f7fa6', floor: '#4f5664', ceil: '#cfd5de', win: { y: 70, h: 54, rx: 22, gap: 64, w: 44 }, round: true },
    { name: 'Privatjet', wall: '#efe6d6', wall2: '#e2d6c0', trim: '#8a5e3c', floor: '#6a5a4a', ceil: '#e6dcc8', win: { y: 64, h: 66, rx: 26, gap: 58, w: 52 }, round: true },
    { name: 'Großer Jet', wall: '#e4e7ee', wall2: '#d6dae4', trim: '#2f5f8a', floor: '#3a4256', ceil: '#cdd3de', win: { y: 60, h: 70, rx: 22, gap: 46, w: 54 }, round: true, led: '#9fd0ff' },
    { name: 'Tour-Jumbo', wall: '#22253a', wall2: '#2c3050', trim: '#e8c35a', floor: '#1d1a2a', ceil: '#141824', win: { y: 58, h: 70, rx: 22, gap: 44, w: 56 }, round: true, led: '#e8c35a', sign: true },
  ],
};

/** Fensterreihe: Rechtecke über die Wand verteilt (die Mitte hinter der Band bleibt frei sichtbar). */
function windows(L) {
  const { y, h, rx, gap, w } = L.win;
  const out = [];
  for (let x = 40; x + w <= W - 30; x += w + gap) out.push([x, y, w, h, rx]);
  return out;
}

/** Was draußen ist: Landschaft oder Wolken, die vorbeiziehen (moving), oder die Stadt, wenn man steht. Doppelt breit, damit es endlos läuft. */
function outside(kind, moving, seed) {
  const sky = kind === 'plane' ? (moving ? '#8fc2ea' : '#9cc7e8') : '#a9d4ef';
  let s = `<rect x="0" y="0" width="${W}" height="${FLOOR}" fill="${sky}"/>`;
  if (!moving) {
    // Stehend: Silhouette einer Stadt (Hochhäuser, ein Turm, ein Dach) und ein bisschen Himmel.
    let x = 0;
    let i = 0;
    let city = '';
    while (x < W) {
      const bw = 40 + ((seed * 13 + i * 29) % 50);
      const bh = 50 + ((seed * 7 + i * 41) % 70);
      city += `<rect x="${x}" y="${150 - bh}" width="${bw}" height="${bh + 80}" fill="${i % 2 ? '#6e7f99' : '#5d6d86'}"/>`;
      for (let wy = 160 - bh; wy < 145; wy += 14) for (let wx = x + 7; wx < x + bw - 8; wx += 12) city += `<rect x="${wx}" y="${wy}" width="5" height="6" fill="#ffe9a8" opacity="${(wx + wy + seed) % 3 ? 0.25 : 0.7}"/>`;
      x += bw + 4;
      i++;
    }
    return s + city + `<rect y="140" width="${W}" height="${FLOOR}" fill="#4a5a70"/>`;
  }
  let strip = '';
  for (const off of [0, W]) {
    if (kind === 'plane') {
      // Wolken unter und neben dem Flugzeug
      for (let k = 0; k < 6; k++) {
        const cx = off + 60 + k * 160 + ((seed + k * 37) % 40);
        const cy = 70 + ((k * 53 + seed) % 70);
        strip += `<g fill="#fff" opacity="${k % 2 ? 0.9 : 0.75}"><ellipse cx="${cx}" cy="${cy}" rx="46" ry="16"/><ellipse cx="${cx - 24}" cy="${cy + 6}" rx="28" ry="12"/><ellipse cx="${cx + 26}" cy="${cy + 5}" rx="30" ry="12"/><ellipse cx="${cx + 4}" cy="${cy - 10}" rx="24" ry="14"/></g>`;
      }
      strip += `<rect x="${off}" y="168" width="${W}" height="60" fill="#e8f2fb" opacity="0.8"/>`;
    } else {
      // Hügel, Bäume, Leitpfosten: die Landschaft am Straßenrand
      strip += `<path d="M${off} 120 Q${off + 120} 82 ${off + 240} 116 T${off + 480} 112 T${off + 720} 118 T${off + 960} 120 V${FLOOR} H${off} Z" fill="#7fae6a"/>`;
      strip += `<path d="M${off} 140 Q${off + 160} 120 ${off + 320} 140 T${off + 640} 138 T${off + 960} 140 V${FLOOR} H${off} Z" fill="#5f9a56"/>`;
      for (let k = 0; k < 9; k++) {
        const tx = off + 30 + k * 108 + ((seed + k * 23) % 30);
        strip += `<path d="M${tx} 150 V128" stroke="#5e3e22" stroke-width="4"/><circle cx="${tx}" cy="120" r="${12 + (k % 3) * 3}" fill="#3f7f45"/>`;
      }
      strip += `<rect x="${off}" y="156" width="${W}" height="${FLOOR}" fill="#6b6f78"/>`;
      for (let k = 0; k < 16; k++) strip += `<rect x="${off + k * 60}" y="166" width="30" height="5" fill="#f2f2f2" opacity="0.8"/>`;
    }
  }
  return s + `<g class="veh-scroll ${kind}">${strip}</g>`;
}

/** Namensschild der Band (Nightliner, Tour-Jumbo): Leuchtschrift auf dunklem Kasten über der Mitte. */
function sign(name, color) {
  const size = 20;
  const tw = Math.min(170, Math.max(60, name.length * size * 0.6));
  const w = tw + 30;
  const x = W / 2 - w / 2;
  const y = 150;
  const label = (extra) => `<text x="${f(W / 2)}" y="${f(y + 25)}" text-anchor="middle" font-family="system-ui, sans-serif" font-size="${size}" font-weight="900" textLength="${f(tw)}" lengthAdjust="spacingAndGlyphs" fill="none" ${extra}>${esc(name)}</text>`;
  return `<rect x="${f(x)}" y="${y}" width="${f(w)}" height="36" rx="9" fill="#141824" ${st(3)}/>` + label(`stroke="${color}" stroke-width="5" opacity="0.25"`) + label(`stroke="${color}" stroke-width="1.6"`);
}

/** Möbel links und rechts (die Mitte bleibt frei), je nach Fahrzeug und Stufe. */
function furniture(kind, level, L) {
  let s = '';
  if (kind === 'bus') {
    if (level === 1) {
      // Rückbank, Kühlbox und ein Gitarrenkoffer
      s += `<path d="M20 214 V176 Q20 166 30 166 H176 Q186 166 186 176 V214 Z" fill="#c4572e" ${st(3)}/>` + box(26, 120, 150, 50, '#d9693c', 10) + line('M100 124 V166', '#a8461f', 2);
      s += box(782, 176, 70, 38, '#3f8fbf', 6) + box(782, 168, 70, 12, '#f2f2f2', 4, 2.4);
      s += `<g transform="rotate(-14 900 170)"><path d="M880 214 Q872 160 890 130 Q900 118 910 130 Q928 160 920 214 Z" fill="#2a2433" ${st(3)}/></g>`;
    } else if (level === 2) {
      // Sitzreihen links und rechts, Ablage über den Fenstern, ein Klapptisch
      const seat = (x, c) => box(x, 150, 58, 26, c, 6) + box(x + 6, 104, 46, 50, c, 8) + `<rect x="${x + 10}" y="108" width="38" height="10" rx="4" fill="#fff" opacity="0.3"/>`;
      s += seat(16, '#4f6d8c') + seat(84, '#4f6d8c') + seat(800, '#4f6d8c') + seat(868, '#4f6d8c');
      s += `<path d="M24 214 V176 M140 214 V176 M808 214 V176 M926 214 V176" ${st(4)}/>`;
      s += box(700, 168, 80, 8, '#c9a86a', 3, 2.4) + `<path d="M740 176 V214" ${st(4)}/>`;
    } else if (level === 3) {
      // Lounge: Sofa links, Fernseher, Kühlschrank rechts
      s += `<path d="M14 214 V170 Q14 156 28 156 H184 Q198 156 198 170 V214 Z" fill="#5c2a3a" ${st(3)}/>` + box(20, 118, 172, 46, '#6e3446', 12) + `<path d="M74 122 V160 M128 122 V160" stroke="${INK}" stroke-width="1.6" opacity="0.5"/>`;
      s += box(790, 74, 120, 70, '#141824', 6) + `<rect x="798" y="82" width="104" height="54" fill="#2f5f8a"/><path d="M806 128 l20 -18 l14 10 l22 -22 l30 24" stroke="#7fd0e6" stroke-width="2.4" fill="none"/>`;
      s += box(860, 150, 70, 64, '#e4e7ee', 6) + line('M868 176 H922', INK, 2) + box(800, 186, 48, 28, '#c9a86a', 4);
    } else {
      // Kojen mit Vorhängen links (drei übereinander), Ledersofa rechts
      for (let k = 0; k < 3; k++) {
        const y = 40 + k * 58;
        s += box(14, y, 170, 52, '#1d1a2a', 6) + `<rect x="20" y="${y + 6}" width="${k === 1 ? 70 : 158}" height="40" rx="4" fill="#7a2638"/>`;
        if (k === 1) s += `<rect x="92" y="${y + 6}" width="86" height="40" rx="4" fill="#33364a"/><rect x="100" y="${y + 20}" width="70" height="18" rx="6" fill="#e4e7ee"/>`;
        s += `<path d="M20 ${y + 6} Q40 ${y + 26} 22 ${y + 46}" stroke="#5c1a28" stroke-width="2" fill="none"/>`;
      }
      s += `<path d="M770 214 V172 Q770 158 784 158 H934 Q948 158 948 172 V214 Z" fill="#3a2a22" ${st(3)}/>` + box(776, 120, 166, 46, '#4a3428', 12) + `<path d="M832 124 V162 M886 124 V162" stroke="#c9a86a" stroke-width="1.4" opacity="0.6"/>`;
    }
    return s;
  }
  // Flugzeug
  if (level === 1) {
    const seat = (x) => box(x, 152, 52, 24, '#5f7fa6', 6) + box(x + 6, 100, 40, 56, '#6f8fb6', 10) + `<rect x="${x + 10}" y="104" width="32" height="12" rx="5" fill="#fff" opacity="0.5"/>`;
    s += seat(18) + seat(86) + seat(822) + seat(890) + `<path d="M24 214 V176 M146 214 V176 M828 214 V176 M944 214 V176" ${st(4)}/>`;
  } else if (level === 2) {
    // Lederne Clubsessel, Holzleiste, ein Tischchen mit Gläsern
    const club = (x) => `<path d="M${x} 214 V158 Q${x} 140 ${x + 18} 140 H${x + 66} Q${x + 84} 140 ${x + 84} 158 V214 Z" fill="#c9a06a" ${st(3)}/>` + box(x + 8, 96, 68, 54, '#d8b27a', 14);
    s += club(16) + club(860) + box(118, 176, 60, 8, '#8a5e3c', 3, 2.4) + `<path d="M148 184 V214" ${st(4)}/><path d="M134 176 v-14 h8 v14 M156 176 v-12 h8 v12" fill="#dff3ff" ${st(1.6)}/>`;
    s += box(780, 176, 60, 8, '#8a5e3c', 3, 2.4) + `<path d="M810 184 V214" ${st(4)}/>`;
  } else if (level === 3) {
    // Lounge mit Sofa und Bar
    s += `<path d="M14 214 V170 Q14 156 28 156 H200 Q214 156 214 170 V214 Z" fill="#2f5f8a" ${st(3)}/>` + box(20, 116, 188, 48, '#3a6f9e', 12);
    s += box(768, 150, 180, 64, '#e4e7ee', 6) + box(762, 140, 192, 14, '#2f5f8a', 4) + [0, 1, 2, 3].map((j) => `<rect x="${790 + j * 40}" y="${110 - (j % 2) * 8}" width="12" height="${30 + (j % 2) * 8}" rx="3" fill="${['#e8c35a', '#7fd0e6', '#ee7a8a', '#9fe6a0'][j]}" ${st(1.6)}/>`).join('');
  } else {
    // Tour-Jumbo: rundes Ledersofa, Gitarrenständer, goldene Schallplatte an der Wand
    s += `<path d="M14 214 V168 Q14 150 32 150 H206 Q224 150 224 168 V214 Z" fill="#7a2638" ${st(3)}/>` + box(20, 108, 198, 52, '#8e2f44', 14) + `<path d="M84 112 V156 M152 112 V156" stroke="#e8c35a" stroke-width="1.4" opacity="0.6"/>`;
    s += `<circle cx="880" cy="88" r="30" fill="#1d1a2a" ${st(3)}/><circle cx="880" cy="88" r="22" fill="#e8c35a"/><circle cx="880" cy="88" r="5" fill="#1d1a2a"/>`;
    for (let j = 0; j < 3; j++) {
      const x = 780 + j * 52;
      s += `<path d="M${x} 214 V186 M${x - 12} 214 L${x} 196 L${x + 12} 214" ${st(3)} fill="none"/><path d="M${x - 10} 196 Q${x - 16} 160 ${x - 4} 140 L${x - 2} 100 H${x + 4} L${x + 6} 140 Q${x + 18} 160 ${x + 10} 196 Z" fill="${['#c0392b', '#2f5f8a', '#e8c35a'][j]}" ${st(2.4)}/>`;
    }
  }
  return s;
}

/**
 * Fahrzeug von innen als SVG. kind: bus oder plane, level 1–4, seed für kleine Unterschiede, name: Bandname (auf dem Schild im Nightliner
 * und im Tour-Jumbo), moving: unterwegs (draußen zieht es vorbei) oder steht in einer Stadt.
 */
export function vehicleSvg(kind = 'bus', level = 1, seed = 1, name = '', moving = true) {
  const L = LOOK[kind][Math.max(1, Math.min(4, level)) - 1];
  const wins = windows(L);
  const clip = `veh-clip-${kind}-${level}-${seed}`;
  const winShapes = wins.map(([x, y, w, h, rx]) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${rx}"/>`).join('');
  let s = `<defs><clipPath id="${clip}">${winShapes}</clipPath></defs>`;
  // Wand mit Fenstern, Decke, Boden
  s += `<rect width="${W}" height="${FLOOR}" fill="${L.wall}"/>`;
  s += `<rect y="${L.win.y + L.win.h + 18}" width="${W}" height="${FLOOR - L.win.y - L.win.h - 18}" fill="${L.wall2}"/>`;
  s += `<g clip-path="url(#${clip})">${outside(kind, moving, seed)}</g>`;
  s += wins.map(([x, y, w, h, rx]) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${rx}" fill="none" ${st(4)}/><rect x="${x + 4}" y="${y + 4}" width="${f(w * 0.3)}" height="${f(h * 0.5)}" rx="${Math.max(2, rx - 4)}" fill="#fff" opacity="0.18"/>`).join('');
  if (L.curtain) s += wins.map(([x, y, , h]) => `<path d="M${x - 6} ${y - 6} Q${x + 10} ${y + h / 2} ${x - 4} ${y + h + 6} L${x - 10} ${y + h + 6} L${x - 10} ${y - 6} Z" fill="${L.curtain}" ${st(2)}/>`).join('');
  if (L.rack) s += box(-6, L.win.y - 22, W + 12, 12, '#7a838e', 3, 2.4) + Array.from({ length: 6 }, (_, i) => box(70 + i * 150, L.win.y - 40, 60 + (i % 2) * 20, 20, ['#c0392b', '#2f5f8a', '#e8c35a'][i % 3], 4, 2.2)).join('');
  // Leiste unter den Fenstern, Decke mit Licht
  s += `<rect y="${L.win.y + L.win.h + 12}" width="${W}" height="8" fill="${L.trim}"/>`;
  s += `<path d="M0 0 H${W} V${L.round ? 34 : 26} Q${W / 2} ${L.round ? 46 : 30} 0 ${L.round ? 34 : 26} Z" fill="${L.ceil}" ${st(2.5)}/>`;
  if (L.led) s += `<rect x="0" y="${L.round ? 36 : 28}" width="${W}" height="3" fill="${L.led}" opacity="0.8"/><rect x="0" y="${L.round ? 36 : 28}" width="${W}" height="14" fill="${L.led}" opacity="0.1"/>`;
  s += `<rect y="${FLOOR}" width="${W}" height="${H - FLOOR}" fill="${L.floor}"/>`;
  if (kind === 'plane') s += `<rect x="260" y="${FLOOR + 6}" width="440" height="${H - FLOOR - 6}" fill="${level >= 3 ? '#7a2638' : '#5f7fa6'}" opacity="0.55"/>`;
  else for (let x = 0; x < W + 60; x += 48) s += line(`M${x} ${FLOOR} L${x - 20} ${H}`, '#000', 1.4).replace('stroke="#000"', 'stroke="#000" opacity="0.18"');
  s += line(`M0 ${FLOOR} H${W}`, INK, 3);
  s += furniture(kind, level, L);
  if (L.sign && name) s += sign(name, L.led);
  return `<svg class="lb-roomart lb-vehicle ${moving ? 'moving' : 'parked'}" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid slice" aria-hidden="true">${s}</svg>`;
}

/** Kleines Fahrzeug für die Karte (fährt bzw. fliegt auf der Route). */
export const BUS_GLYPH = '<path d="M-9 -4.5 H6 Q9 -4.5 9 -1.5 V3 H-9 Z" fill="#fff" stroke="#1d1a2a" stroke-width="0.8"/><rect x="-7" y="-3" width="4" height="2.6" fill="#7fd0e6"/><rect x="-2" y="-3" width="4" height="2.6" fill="#7fd0e6"/><rect x="3" y="-3" width="4" height="2.6" fill="#7fd0e6"/><circle cx="-5" cy="3.5" r="1.8" fill="#1d1a2a"/><circle cx="5" cy="3.5" r="1.8" fill="#1d1a2a"/>';
