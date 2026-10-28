/**
 * Bühnen der Tournee: je Land ein Bild voller Klischees (Frankreich mit Bistro und Baguettes, Japan mit Fuji und Kirschblüten …),
 * gezeichnet wie die übrigen Bühnen (stage.js): ruhige Flächen, dunkle Kontur. Die Mitte bleibt für die Band frei,
 * Wahrzeichen stehen oben und an den Seiten. h: Zeichenhilfen aus stage.js.
 */
export function worldScenes(h) {
  const { W, H, TOP, DECK, INK, st, box, platform } = h;
  const DY = TOP - DECK;

  const sky = (id, stops) => `<defs><linearGradient id="${id}" x1="0" y1="0" x2="0" y2="1">${stops.map(([o, c]) => `<stop offset="${o}" stop-color="${c}"/>`).join('')}</linearGradient></defs><rect width="${W}" height="${H}" fill="url(#${id})"/>`;
  /** Punkt auf einem durchhängenden Seil von (x1, y1) nach (x2, y2). */
  const rope = (x1, y1, x2, y2, sag, t) => {
    const mx = (x1 + x2) / 2;
    const my = (y1 + y2) / 2 + sag;
    return [(1 - t) ** 2 * x1 + 2 * (1 - t) * t * mx + t * t * x2, (1 - t) ** 2 * y1 + 2 * (1 - t) * t * my + t * t * y2];
  };
  const line = (x1, y1, x2, y2, sag) => `<path d="M${x1} ${y1} Q${(x1 + x2) / 2} ${(y1 + y2) / 2 + sag * 2} ${x2} ${y2}" fill="none" stroke="${INK}" stroke-width="1.6"/>`;
  /** Wimpelkette in Landesfarben. */
  const bunting = (x1, y1, x2, y2, sag, colors, n = 14) => line(x1, y1, x2, y2, sag) + Array.from({ length: n }, (_, i) => {
    const [x, y] = rope(x1, y1, x2, y2, sag, (i + 0.5) / n);
    return `<path d="M${(x - 8).toFixed(1)} ${y.toFixed(1)} h16 l-8 15 Z" fill="${colors[i % colors.length]}" ${st(1.3)}/>`;
  }).join('');
  /** Lichterkette. */
  const lights = (x1, y1, x2, y2, sag, colors = ['#ffd27a', '#fff1c4'], n = 12) => line(x1, y1, x2, y2, sag) + Array.from({ length: n }, (_, i) => {
    const [x, y] = rope(x1, y1, x2, y2, sag, (i + 0.5) / n);
    return `<circle cx="${x.toFixed(1)}" cy="${(y + 4).toFixed(1)}" r="4" fill="${colors[i % colors.length]}" opacity="0.95"/>`;
  }).join('');
  const stars = (r, n, maxY) => Array.from({ length: n }, () => `<circle cx="${(r() * W).toFixed(0)}" cy="${(r() * maxY).toFixed(0)}" r="${(0.8 + r() * 1.3).toFixed(1)}" fill="#fff" opacity="${(0.3 + r() * 0.5).toFixed(2)}"/>`).join('');
  /** Laubbaum/Palme/Tanne als Silhouette. */
  const pine = (x, y, s, fill = '#1f3b2c', snow = false) => `<path d="M${x} ${y - 70 * s} L${x + 26 * s} ${y - 30 * s} H${x + 14 * s} L${x + 34 * s} ${y} H${x - 34 * s} L${x - 14 * s} ${y - 30 * s} H${x - 26 * s} Z" fill="${fill}" ${st(2)}/>${snow ? `<path d="M${x} ${y - 70 * s} L${x + 12 * s} ${y - 52 * s} L${x} ${y - 56 * s} L${x - 12 * s} ${y - 52 * s} Z M${x - 20 * s} ${y - 30 * s} H${x + 20 * s} L${x + 14 * s} ${y - 24 * s} H${x - 14 * s} Z" fill="#f4f8ff"/>` : ''}<rect x="${x - 4 * s}" y="${y}" width="${8 * s}" height="${10 * s}" fill="#4a3424"/>`;
  const palm = (x, y, s, lean = 1) => `<path d="M${x} ${y} Q${x + 10 * lean * s} ${y - 60 * s} ${x + 4 * lean * s} ${y - 120 * s}" fill="none" stroke="#6a4a2e" stroke-width="${7 * s}" stroke-linecap="round"/>${[-150, -110, -60, -20, 20, 60].map((a) => {
    const rad = (a * Math.PI) / 180;
    const tx = x + 4 * lean * s;
    const ty = y - 120 * s;
    return `<path d="M${tx} ${ty} Q${tx + Math.cos(rad) * 30 * s} ${ty + Math.sin(rad) * 30 * s - 14 * s} ${tx + Math.cos(rad) * 58 * s} ${ty + Math.sin(rad) * 58 * s + 18 * s}" fill="none" stroke="#2f6b3a" stroke-width="${6 * s}" stroke-linecap="round"/>`;
  }).join('')}`;
  /** Gelbes Warnschild (Raute) mit einem Tier darauf. */
  const sign = (x, y, animal) => `<path d="M${x} ${y + 64} V${DY}" stroke="#7d8092" stroke-width="5"/><path d="M${x} ${y} L${x + 32} ${y + 32} L${x} ${y + 64} L${x - 32} ${y + 32} Z" fill="#f2c14e" ${st(2.6)}/><g transform="translate(${x} ${y + 34})" fill="${INK}">${animal}</g>`;
  const KANGAROO = '<path d="M-14 10 Q-6 6 -2 -4 Q0 -14 8 -16 L12 -22 L13 -15 Q17 -12 13 -8 Q8 -6 6 2 L10 12 H5 L2 6 Q-8 12 -20 14 Z"/>';
  const MOOSE = '<path d="M-16 4 Q-16 -6 -4 -6 H8 L12 -10 L18 -8 L16 -2 L12 0 L12 12 H9 V4 H-8 V12 H-11 V4 Q-14 4 -16 4 Z M10 -10 L6 -18 L10 -14 L12 -20 L14 -13 L18 -18 L16 -10 Z"/>';

  return {
    // Frankreich: Pariser Bistro mit Markise, Baguettes und Croissant, hinten der Eiffelturm in der Abenddämmerung.
    fr: () => `
      ${sky('w-fr', [[0, '#27325e'], [0.6, '#7a5a86'], [1, '#e9a27a']])}
      <path d="M760 14 L767 78 L786 130 L816 ${DY} H792 Q760 158 728 ${DY} H704 L734 130 L753 78 Z" fill="#3b2f4f" ${st(2.4)}/>
      <path d="M748 80 H772 M732 130 H788 M757 46 H763" stroke="${INK}" stroke-width="3"/>
      ${box(10, 44, 230, DY - 44, '#e9dcc3', 2)}
      <path d="M4 48 L24 20 H226 L246 48 Z" fill="#5d6b86" ${st(3)}/>
      ${[40, 100, 160].map((x) => `${box(x + 4, 28, 26, 16, '#3c4660', 6, 2)}${box(x, 58, 34, 40, '#7aa0c8', 2, 2.4)}<path d="M${x - 4} 100 H${x + 38}" stroke="${INK}" stroke-width="3"/>`).join('')}
      ${Array.from({ length: 9 }, (_, i) => `<path d="M${18 + i * 24} 112 h24 l-2 26 q-10 8 -20 0 Z" fill="${i % 2 ? '#fff6ea' : '#c8323a'}" ${st(2)}/>`).join('')}
      <text x="125" y="160" text-anchor="middle" font-family="Georgia, serif" font-style="italic" font-weight="700" font-size="22" fill="#7a2a2e">Bistro</text>
      ${box(60, 168, 34, 30, '#5a3a24', 2, 2)}${box(150, 168, 34, 30, '#5a3a24', 2, 2)}
      ${lights(240, 24, 700, 30, 26)}
      ${bunting(250, 60, 680, 66, 20, ['#2b4fa2', '#f4f1ea', '#c8323a'], 12)}
      ${box(822, 120, 120, 78, '#6a7a8e', 2)}${box(834, 132, 44, 40, '#ffe1a0', 2, 2)}${box(886, 132, 44, 40, '#ffe1a0', 2, 2)}
      ${platform('#9a6a40', '#6e4628')}
      <g transform="translate(96 ${DY - 2})"><path d="M-22 -2 H22" stroke="${INK}" stroke-width="3"/><ellipse cx="0" cy="-4" rx="24" ry="5" fill="#d9d4cc" ${st(2.2)}/><path d="M0 0 V26 M-10 26 H10" stroke="${INK}" stroke-width="3"/>
        ${[[-14, -18, -0.5], [-2, -22, -0.2], [10, -18, 0.3]].map(([x, y, a]) => `<g transform="translate(${x} ${y}) rotate(${a * 57})"><rect x="-3.5" y="-16" width="7" height="32" rx="3.5" fill="#d8a457" ${st(1.6)}/><path d="M-2 -8 l4 -3 M-2 0 l4 -3 M-2 8 l4 -3" stroke="#a8702e" stroke-width="1.4"/></g>`).join('')}
        <path d="M-14 -10 H14 L10 -2 H-10 Z" fill="#b58a52" ${st(1.8)}/></g>
      <g transform="translate(868 ${DY - 2})"><ellipse cx="0" cy="-4" rx="24" ry="5" fill="#d9d4cc" ${st(2.2)}/><path d="M0 0 V26 M-10 26 H10" stroke="${INK}" stroke-width="3"/>
        <path d="M-14 -8 Q-12 -20 0 -20 Q12 -20 14 -8 Q8 -12 0 -11 Q-8 -12 -14 -8 Z" fill="#e2a24e" ${st(1.8)}/><path d="M-6 -18 Q-4 -12 -6 -9 M0 -20 V-11 M6 -18 Q4 -12 6 -9" stroke="#a8702e" stroke-width="1.4" fill="none"/></g>`,

    // England: Big Ben, Union Jack als Fahne über der Bühne, rote Telefonzelle.
    gb: (r) => `
      ${sky('w-gb', [[0, '#141b36'], [1, '#3d4a7a']])}${stars(r, 14, 90)}
      ${[[180, 120], [250, 90], [300, 140], [660, 110], [720, 80], [790, 130]].map(([x, y]) => `<rect x="${x}" y="${y}" width="56" height="${DY - y}" fill="#252c4a"/>`).join('')}
      <path d="M100 0 L116 34 H84 Z" fill="#c9a24e" ${st(2.4)}/>${box(80, 34, 40, DY - 34, '#b89a6a', 2)}
      <circle cx="100" cy="72" r="16" fill="#f4ecd2" ${st(2.6)}/><path d="M100 72 V62 M100 72 L107 76" stroke="${INK}" stroke-width="2.4" stroke-linecap="round"/>
      ${[100, 130, 160].map((y) => `<path d="M84 ${y} H116" stroke="#8a7048" stroke-width="2"/>`).join('')}
      <defs><clipPath id="w-gb-flag"><rect x="350" y="18" width="260" height="104" rx="4"/></clipPath></defs>
      <path d="M480 0 V18" stroke="${INK}" stroke-width="2"/>
      <g clip-path="url(#w-gb-flag)"><rect x="350" y="18" width="260" height="104" fill="#1f3a7a"/>
        <path d="M350 18 L610 122 M610 18 L350 122" stroke="#f4f1ea" stroke-width="20"/><path d="M350 18 L610 122 M610 18 L350 122" stroke="#c8323a" stroke-width="7"/>
        <path d="M480 18 V122 M350 70 H610" stroke="#f4f1ea" stroke-width="30"/><path d="M480 18 V122 M350 70 H610" stroke="#c8323a" stroke-width="16"/></g>
      <rect x="350" y="18" width="260" height="104" rx="4" fill="none" ${st(3)}/>
      ${bunting(150, 140, 340, 136, 14, ['#c8323a', '#f4f1ea', '#1f3a7a'], 8)}${bunting(620, 136, 820, 140, 14, ['#1f3a7a', '#f4f1ea', '#c8323a'], 8)}
      ${box(856, 96, 64, DY - 96, '#c8323a', 6)}<rect x="862" y="104" width="52" height="10" rx="2" fill="#1d1a2a"/><text x="888" y="112" text-anchor="middle" font-family="system-ui, sans-serif" font-weight="700" font-size="7" fill="#f4f1ea">TELEPHONE</text>
      ${Array.from({ length: 4 }, (_, i) => Array.from({ length: 3 }, (_, j) => `<rect x="${866 + j * 15}" y="${122 + i * 16}" width="12" height="13" fill="#ffe9a8" opacity="0.8"/>`).join('')).join('')}
      ${platform('#3a3448', '#211d2c')}`,

    // Irland: gemütlicher Pub mit Holzwand, Flaschenregal, Kleeblatt-Girlande, grüne Hügel im Fenster, Fässer.
    ie: () => `
      <rect width="${W}" height="${H}" fill="#5a3a24"/>
      ${Array.from({ length: 16 }, (_, i) => `<path d="M${i * 60 + 30} 0 V${DY}" stroke="#4a2f1c" stroke-width="3"/>`).join('')}
      ${box(40, 46, 150, 104, '#9fd0ff', 4)}<path d="M40 120 Q90 92 140 112 T190 104 V150 H40 Z" fill="#4f9a4a"/><path d="M40 136 Q110 116 190 132 V150 H40 Z" fill="#2f7a3a"/><path d="M115 46 V150 M40 98 H190" stroke="#5a3a24" stroke-width="6"/>${box(40, 46, 150, 104, 'none', 4)}
      ${[86, 132].map((y) => `<path d="M330 ${y} H630" stroke="#3a2416" stroke-width="7"/>${Array.from({ length: 12 }, (_, i) => `<rect x="${342 + i * 24}" y="${y - 30}" width="12" height="28" rx="3" fill="${['#2f5a3a', '#6b3a1e', '#2a2a36'][i % 3]}" ${st(1.4)}/><rect x="${345 + i * 24}" y="${y - 38}" width="6" height="9" fill="${['#2f5a3a', '#6b3a1e', '#2a2a36'][i % 3]}"/>`).join('')}`).join('')}
      ${line(0, 22, W, 22, 18)}${Array.from({ length: 18 }, (_, i) => { const [x, y] = rope(0, 22, W, 22, 18, (i + 0.5) / 18); return `<g transform="translate(${x.toFixed(0)} ${(y + 8).toFixed(0)})" fill="#3aa04a" stroke="${INK}" stroke-width="1.2"><circle cx="-4" cy="-3" r="4.5"/><circle cx="4" cy="-3" r="4.5"/><circle cx="0" cy="4" r="4.5"/></g>`; }).join('')}
      ${box(760, 50, 130, 90, '#3a2416', 6)}<path d="M800 128 Q790 80 818 64 Q846 60 850 74 L852 128" fill="none" stroke="#e6b84f" stroke-width="5"/>${[0, 1, 2, 3, 4].map((i) => `<path d="M${808 + i * 8} ${72 + i * 2} V128" stroke="#e6b84f" stroke-width="1.6"/>`).join('')}
      ${platform('#8a5a3c', '#5e3a24')}
      ${[44, 912].map((x) => `<g transform="translate(${x} ${DY - 2})"><path d="M-22 0 Q-26 -24 -22 -48 H22 Q26 -24 22 0 Z" fill="#8a5a32" ${st(2.4)}/><path d="M-24 -12 H24 M-24 -36 H24" stroke="#3a3448" stroke-width="3"/></g>`).join('')}`,

    // Spanien: Plaza mit Arkaden im Abendlicht, Fächer, Gitarre, Orangenbaum, rot-gelbe Wimpel.
    es: () => `
      ${sky('w-es', [[0, '#e8774f'], [1, '#ffd59a']])}
      <circle cx="770" cy="50" r="30" fill="#ffe7a0" opacity="0.9"/>
      ${box(-10, 48, W + 20, DY - 48, '#d99a5b', 0)}
      <path d="M-10 48 H${W + 10} V62 H-10 Z" fill="#b8763e" ${st(2.4)}/>
      ${Array.from({ length: 8 }, (_, i) => `<path d="M${i * 124 + 20} ${DY} V120 Q${i * 124 + 62} 74 ${i * 124 + 104} 120 V${DY} Z" fill="#7a4a2e" ${st(2.4)}/>`).join('')}
      ${Array.from({ length: 8 }, (_, i) => `<g transform="translate(${i * 124 + 62} 70)">${box(-14, -6, 28, 10, '#6a3a24', 2, 1.6)}<circle cx="-8" cy="-9" r="4" fill="#d9324a"/><circle cx="0" cy="-11" r="4" fill="#ff6f6f"/><circle cx="8" cy="-9" r="4" fill="#d9324a"/></g>`).join('')}
      ${bunting(-10, 18, W + 10, 18, 22, ['#c8323a', '#f2c14e'], 22)}
      <g transform="translate(480 46)"><path d="M-46 22 A46 46 0 0 1 46 22 L0 30 Z" fill="#c8323a" ${st(2.4)}/>${[-60, -30, 0, 30, 60].map((a) => `<path d="M0 30 L${(Math.sin((a * Math.PI) / 180) * 46).toFixed(1)} ${(30 - Math.cos((a * Math.PI) / 180) * 46).toFixed(1)}" stroke="#1d1a2a" stroke-width="2"/>`).join('')}<circle cx="0" cy="30" r="4" fill="${INK}"/></g>
      <g transform="translate(70 ${DY})"><ellipse cx="0" cy="-60" rx="28" ry="30" fill="#2f7a3a" ${st(2.2)}/>${[[-12, -66], [8, -72], [14, -52], [-6, -48]].map(([x, y]) => `<circle cx="${x}" cy="${y}" r="5" fill="#f28c28" ${st(1.4)}/>`).join('')}<path d="M0 -32 V-10" stroke="#6a4a2e" stroke-width="6"/>${box(-18, -12, 36, 12, '#b8562e', 2, 2)}</g>
      <g transform="translate(900 ${DY - 4}) rotate(-12)"><path d="M0 0 Q-16 -6 -12 -22 Q-14 -34 0 -36 Q14 -34 12 -22 Q16 -6 0 0 Z" fill="#c98a4b" ${st(2)}/><circle cx="0" cy="-20" r="4" fill="#3a2416"/><rect x="-2.5" y="-82" width="5" height="48" fill="#5a3a24" ${st(1.4)}/></g>
      ${platform('#c0623a', '#8a3f24')}
      ${Array.from({ length: 12 }, (_, i) => `<path d="M${i * 80 + 40} ${TOP + 12} V${H}" stroke="#7a3820" stroke-width="2"/>`).join('')}`,

    // Niederlande: Grachtenhäuser mit Giebeln, Windmühle, Tulpen in Kästen, Fahrrad, orange Wimpel.
    nl: () => `
      ${sky('w-nl', [[0, '#7fb6f0'], [1, '#e2f1ff']])}
      ${[[150, '#9c4a32'], [236, '#2f5a4a'], [322, '#c98a3a'], [408, '#3a4a7a'], [494, '#8a3a3a'], [580, '#4a6a5a'], [666, '#b85a3a'], [752, '#2f4a6a']].map(([x, c], i) => {
        const top = 40 + (i % 3) * 12;
        const gable = i % 2
          ? `M${x} ${top + 30} L${x + 10} ${top + 30} V${top + 18} H${x + 22} V${top + 6} H${x + 64} V${top + 18} H${x + 76} V${top + 30} H${x + 86}`
          : `M${x} ${top + 30} Q${x + 8} ${top + 26} ${x + 14} ${top + 14} Q${x + 43} ${top - 8} ${x + 72} ${top + 14} Q${x + 78} ${top + 26} ${x + 86} ${top + 30}`;
        return `<path d="${gable} V${DY} H${x} Z" fill="${c}" ${st(2.4)}/>${Array.from({ length: 3 }, (_, row) => Array.from({ length: 2 }, (_, col) => `<rect x="${x + 16 + col * 32}" y="${top + 40 + row * 34}" width="22" height="24" rx="2" fill="#f4f1ea" ${st(1.6)}/>`).join('')).join('')}`;
      }).join('')}
      <g transform="translate(70 0)"><path d="M-26 ${DY} L-16 80 H16 L26 ${DY} Z" fill="#7a5a3a" ${st(2.6)}/><path d="M-20 80 Q0 58 20 80 Z" fill="#4a3424" ${st(2.4)}/>
        <g transform="translate(0 76) rotate(18)">${[0, 90, 180, 270].map((a) => `<g transform="rotate(${a})"><rect x="-4" y="-72" width="8" height="66" fill="#f4f1ea" ${st(1.8)}/>${[0, 1, 2, 3].map((k) => `<path d="M-4 ${-66 + k * 15} H10" stroke="${INK}" stroke-width="1.2"/>`).join('')}<rect x="4" y="-70" width="10" height="60" fill="none" ${st(1.4)}/></g>`).join('')}<circle r="5" fill="${INK}"/></g></g>
      ${bunting(140, 16, 840, 16, 14, ['#f28c28', '#f4f1ea', '#2b4fa2'], 18)}
      <g transform="translate(905 ${DY - 4})"><circle cx="-24" cy="-14" r="14" fill="none" ${st(2.6)}/><circle cx="24" cy="-14" r="14" fill="none" ${st(2.6)}/><path d="M-24 -14 L-6 -40 H18 L24 -14 M-6 -40 L4 -14 L18 -40 M-10 -46 H0 M18 -40 L20 -50 H28" fill="none" stroke="#2b4fa2" stroke-width="3" stroke-linejoin="round"/></g>
      ${platform('#5b6b8a', '#3a465e')}
      ${[[0, 150], [810, W]].map(([a, b]) => `${box(a + 6, DY - 16, b - a - 12, 16, '#7a5636', 2, 2)}${Array.from({ length: Math.floor((b - a - 20) / 14) }, (_, i) => `<path d="M${a + 16 + i * 14} ${DY - 16} V${DY - 34}" stroke="#2f7a3a" stroke-width="2.4"/><path d="M${a + 10 + i * 14} ${DY - 40} Q${a + 16 + i * 14} ${DY - 30} ${a + 22 + i * 14} ${DY - 40} L${a + 19 + i * 14} ${DY - 46} L${a + 16 + i * 14} ${DY - 41} L${a + 13 + i * 14} ${DY - 46} Z" fill="${['#e2324a', '#f2c14e', '#ff8fc0', '#f28c28'][i % 4]}" ${st(1.2)}/>`).join('')}`).join('')}`,

    // Schweden: Mittsommer mit rotem Holzhaus, Birken, Mittsommerbaum mit Blumen, blau-gelbe Wimpel, rotes Dala-Pferd.
    se: () => `
      ${sky('w-se', [[0, '#8fbde9'], [1, '#f6e3b4']])}
      ${[[150, 70], [640, 80]].map(([x, y]) => `<path d="M${x} ${y + 30} L${x + 85} ${y - 10} L${x + 170} ${y + 30} V${DY} H${x} Z" fill="#a8332e" ${st(2.6)}/><path d="M${x - 6} ${y + 32} L${x + 85} ${y - 14} L${x + 176} ${y + 32}" fill="none" stroke="#f4f1ea" stroke-width="6"/>${[30, 110].map((dx) => `${box(x + dx, y + 52, 30, 30, '#cfe4f6', 1, 2)}<rect x="${x + dx - 3}" y="${y + 49}" width="36" height="36" fill="none" stroke="#f4f1ea" stroke-width="4"/>`).join('')}`).join('')}
      ${[40, 100, 860, 920].map((x, i) => `<rect x="${x - 6}" y="${60 + (i % 2) * 20}" width="12" height="${DY - 60 - (i % 2) * 20}" fill="#f4f1ea" ${st(2)}/>${[90, 120, 150].map((y) => `<path d="M${x - 6} ${y + i * 4} h6" stroke="${INK}" stroke-width="2"/>`).join('')}<ellipse cx="${x}" cy="${60 + (i % 2) * 20}" rx="30" ry="34" fill="#8fc46a" opacity="0.9" ${st(1.8)}/>`).join('')}
      <g transform="translate(400 0)"><rect x="-5" y="40" width="10" height="${DY - 40}" fill="#7a5636" ${st(2)}/><path d="M-50 70 H50" stroke="#7a5636" stroke-width="8"/>${[-38, 38].map((x) => `<circle cx="${x}" cy="96" r="22" fill="none" stroke="#3f8a3a" stroke-width="8"/>${[0, 1, 2, 3, 4].map((k) => `<circle cx="${x + Math.cos(k * 1.26) * 22}" cy="${96 + Math.sin(k * 1.26) * 22}" r="3.6" fill="${['#f4f1ea', '#f2c14e', '#ff8fc0'][k % 3]}"/>`).join('')}`).join('')}<path d="M0 40 V30" stroke="#3f8a3a" stroke-width="10"/></g>
      ${bunting(560, 30, 960, 46, 16, ['#2b5fae', '#f2c14e'], 10)}
      <g transform="translate(66 ${DY - 2})"><path d="M-20 0 V-16 Q-22 -26 -12 -26 H6 L9 -38 Q11 -46 19 -44 L25 -36 L23 -32 L16 -33 L14 -20 V0 H7 V-13 H-12 V0 Z" fill="#c8323a" ${st(2)}/><path d="M9 -38 L4 -34 M10 -42 L5 -38" stroke="${INK}" stroke-width="1.6"/><path d="M-14 -22 Q-4 -16 6 -22 M-12 -18 h14" fill="none" stroke="#f2c14e" stroke-width="2"/><path d="M-10 -24 q4 3 8 0" fill="none" stroke="#2b5fae" stroke-width="2"/><circle cx="18" cy="-38" r="1.4" fill="${INK}"/></g>
      ${platform('#c8a06e', '#8a6a44')}`,

    // Norwegen: Fjord unter Polarlicht, Berge mit Schnee, Stabkirche, Fackeln an der Bühne.
    no: (r) => `
      ${sky('w-no', [[0, '#081028'], [1, '#1d2b4f']])}${stars(r, 30, 120)}
      ${[0, 1, 2].map((k) => `<path d="M-20 ${70 + k * 18} Q200 ${30 + k * 22} 420 ${70 + k * 10} T960 ${50 + k * 20}" fill="none" stroke="${['#5affb0', '#3ad49a', '#9affd8'][k]}" stroke-width="${18 - k * 4}" opacity="${0.22 - k * 0.05}" stroke-linecap="round"/>`).join('')}
      <path d="M-10 ${DY} L90 96 L170 150 L250 84 L360 160 L420 130 L480 ${DY} Z" fill="#2a3a5e" ${st(2.4)}/><path d="M90 96 L110 116 L96 112 L80 120 Z M250 84 L272 108 L254 102 L236 110 Z" fill="#e8eef8"/>
      <path d="M520 ${DY} L600 120 L680 160 L760 98 L860 150 L970 110 V${DY} Z" fill="#24334f" ${st(2.4)}/><path d="M760 98 L780 118 L764 112 L748 120 Z" fill="#e8eef8"/>
      <rect x="0" y="170" width="${W}" height="${DY - 170}" fill="#1a2a48" opacity="0.8"/>
      <g transform="translate(470 ${DY})" fill="#2a1e18" stroke="${INK}" stroke-width="2.2"><path d="M-40 0 V-34 L-30 -46 H30 L40 -34 V0 Z"/><path d="M-28 -46 L-20 -64 H20 L28 -46 Z"/><path d="M-16 -64 L-10 -80 H10 L16 -64 Z"/><path d="M-6 -80 L0 -98 L6 -80 Z"/><path d="M-30 -46 Q-38 -54 -34 -58 M30 -46 Q38 -54 34 -58" fill="none"/></g>
      ${[36, 924].map((x) => `<path d="M${x} ${DY} V${DY - 70}" stroke="#4a3424" stroke-width="7"/><path d="M${x - 9} ${DY - 70} h18 l-4 -10 h-10 Z" fill="#3a3448" ${st(1.6)}/><path d="M${x} ${DY - 110} Q${x + 14} ${DY - 92} ${x + 6} ${DY - 80} Q${x} ${DY - 74} ${x - 6} ${DY - 80} Q${x - 14} ${DY - 94} ${x} ${DY - 110} Z" fill="#ff8a3a"/><path d="M${x} ${DY - 98} Q${x + 6} ${DY - 88} ${x} ${DY - 81} Q${x - 6} ${DY - 88} ${x} ${DY - 98} Z" fill="#ffe08a"/><ellipse cx="${x}" cy="${DY - 86}" rx="40" ry="34" fill="#ff8a3a" opacity="0.12"/>`).join('')}
      ${platform('#2a2a35', '#17151f')}
      ${Array.from({ length: 24 }, (_, i) => `<circle cx="${i * 40 + 20}" cy="${TOP + 5}" r="2.2" fill="#9aa3b5" opacity="0.6"/>`).join('')}`,

    // Finnland: Winternacht mit Polarlicht, verschneite Tannen, Sauna-Hütte mit Rauch, zugefrorener See.
    fi: (r) => `
      ${sky('w-fi', [[0, '#0b1124'], [1, '#2a2350']])}${stars(r, 26, 110)}
      ${[0, 1].map((k) => `<path d="M-20 ${60 + k * 20} Q300 ${20 + k * 30} 620 ${70 + k * 8} T980 ${40 + k * 24}" fill="none" stroke="${['#8affc4', '#c08aff'][k]}" stroke-width="${16 - k * 4}" opacity="0.2" stroke-linecap="round"/>`).join('')}
      <path d="M0 150 Q240 120 480 146 T${W} 140 V${DY} H0 Z" fill="#dfe8f6" ${st(2)}/>
      <ellipse cx="560" cy="182" rx="170" ry="14" fill="#a8c8e8" ${st(1.6)}/>
      ${[[40, 1.1], [120, 0.8], [190, 1], [760, 0.9], [840, 1.2], [920, 0.9]].map(([x, s]) => pine(x, DY - 6, s, '#1f3b2c', true)).join('')}
      <g transform="translate(330 ${DY})">${box(-60, -56, 120, 56, '#8a5a32', 2)}<path d="M-68 -54 L0 -92 L68 -54 Z" fill="#5a3a24" ${st(2.6)}/><path d="M-68 -54 L0 -92 L68 -54" fill="none" stroke="#f4f8ff" stroke-width="6"/>${[-48, -36, -24, -12].map((y) => `<path d="M-60 ${y} H60" stroke="#6a4424" stroke-width="2"/>`).join('')}${box(-12, -40, 24, 40, '#4a2f1c', 2, 2)}<rect x="30" y="-104" width="12" height="30" fill="#4a3424" ${st(2)}/>${[0, 1, 2].map((k) => `<circle cx="${36 + k * 8}" cy="${-116 - k * 16}" r="${8 + k * 3}" fill="#e8eef8" opacity="${0.5 - k * 0.12}"/>`).join('')}</g>
      ${Array.from({ length: 40 }, () => `<circle cx="${(r() * W).toFixed(0)}" cy="${(r() * DY).toFixed(0)}" r="${(1 + r() * 1.6).toFixed(1)}" fill="#fff" opacity="0.7"/>`).join('')}
      ${platform('#2f3446', '#1c1f2c')}
      <path d="M0 ${DY + 1} Q120 ${DY - 6} 240 ${DY + 1} T480 ${DY + 1} T720 ${DY + 1} T${W} ${DY + 1}" fill="none" stroke="#f4f8ff" stroke-width="6" stroke-linecap="round"/>`,

    // USA: New Yorker Skyline bei Nacht, Freiheitsstatue in der Ferne, Backsteinwand mit Graffiti, Ghettoblaster.
    us: (r) => `
      ${sky('w-us', [[0, '#0f1430'], [1, '#3a2b5a']])}${stars(r, 12, 60)}
      ${[[30, 70, 60], [96, 40, 50], [152, 90, 44], [200, 20, 54], [258, 60, 40], [640, 50, 46], [690, 80, 60], [754, 30, 48], [806, 66, 52], [862, 44, 56], [920, 90, 40]].map(([x, y, w]) => `<rect x="${x}" y="${y}" width="${w}" height="${DY - y}" fill="#232848"/>${Array.from({ length: Math.floor((DY - y - 20) / 14) }, (_, row) => Array.from({ length: Math.floor((w - 8) / 10) }, (_, col) => (r() > 0.55 ? `<rect x="${x + 5 + col * 10}" y="${y + 8 + row * 14}" width="5" height="7" fill="#ffd27a" opacity="0.8"/>` : '')).join('')).join('')}`).join('')}
      <path d="M220 20 V8 L226 0 L232 8 V20" fill="#232848" ${st(1.6)}/><path d="M226 0 V-20" stroke="#232848" stroke-width="3"/>
      <g transform="translate(660 ${DY - 108}) scale(0.9)" fill="#6fae96" stroke="${INK}" stroke-width="1.6"><path d="M-8 100 V20 Q-12 8 -4 4 L0 -4 L4 4 Q12 8 8 20 V100 Z"/><path d="M4 0 L22 -40 L28 -38 L10 4 Z"/><path d="M22 -40 L18 -50 L28 -56 L32 -44 Z" fill="#ffd27a"/><path d="M-10 -2 L-6 -14 L-2 -8 L0 -16 L2 -8 L6 -14 L10 -2 Z"/><rect x="-18" y="100" width="36" height="20" fill="#4a6a5e"/></g>
      ${box(290, 104, 330, DY - 104, '#8a3f30', 0, 2.4)}
      ${Array.from({ length: 6 }, (_, row) => Array.from({ length: 8 }, (_, col) => `<rect x="${292 + col * 40 + (row % 2) * 20}" y="${106 + row * 16}" width="38" height="14" fill="#9a4a38"/>`).join('')).join('')}
      <path d="M300 170 Q320 120 360 150 Q380 110 420 148 Q450 118 470 160 L460 186 Q400 196 320 188 Z" fill="#ff5fa2" opacity="0.85" ${st(2)}/><path d="M470 176 Q490 128 520 152 Q550 116 576 150 Q606 132 612 172 L604 190 Q540 198 480 190 Z" fill="#4fd1ff" opacity="0.85" ${st(2)}/>
      <text x="480" y="182" text-anchor="middle" font-family="Impact, system-ui, sans-serif" font-weight="900" font-size="34" fill="#f2c14e" stroke="${INK}" stroke-width="2.4" paint-order="stroke">NYC</text>
      <g transform="translate(905 ${DY - 2})">${box(-36, -34, 72, 34, '#3a3448', 6)}<circle cx="-18" cy="-16" r="11" fill="#17141f" ${st(2)}/><circle cx="18" cy="-16" r="11" fill="#17141f" ${st(2)}/><rect x="-8" y="-30" width="16" height="10" rx="2" fill="#4fd1ff"/><path d="M-24 -34 Q0 -50 24 -34" fill="none" stroke="#9aa3b5" stroke-width="3"/></g>
      ${platform('#262230', '#151320')}
      ${Array.from({ length: 24 }, (_, i) => `<circle cx="${i * 40 + 20}" cy="${TOP + 5}" r="3" fill="${i % 2 ? '#ff5fa2' : '#4fd1ff'}" opacity="0.85"/>`).join('')}`,

    // Kanada: Berge und Tannen, CN Tower, große Ahornblatt-Fahne, Elch-Schild.
    ca: () => `
      ${sky('w-ca', [[0, '#7fb2e5'], [1, '#e2f0ff']])}
      <path d="M-10 ${DY} L120 70 L210 140 L320 60 L440 150 L520 100 L640 160 L760 80 L870 140 L970 90 V${DY} Z" fill="#7d8fa8" ${st(2)}/>
      ${[[120, 70], [320, 60], [760, 80]].map(([x, y]) => `<path d="M${x} ${y} L${x + 22} ${y + 26} L${x + 8} ${y + 20} L${x - 6} ${y + 28} L${x - 20} ${y + 24} Z" fill="#f4f8ff"/>`).join('')}
      ${Array.from({ length: 22 }, (_, i) => pine(i * 46 + 10, DY, 0.55 + (i % 3) * 0.12, i % 2 ? '#2f5a3a' : '#26503a')).join('')}
      <g transform="translate(830 0)"><path d="M-6 ${DY} L-3 60 H3 L6 ${DY} Z" fill="#c9ccd6" ${st(2)}/><ellipse cx="0" cy="60" rx="16" ry="9" fill="#c9ccd6" ${st(2)}/><path d="M0 50 V4" stroke="${INK}" stroke-width="3"/></g>
      <g transform="translate(480 24)"><rect x="-130" y="0" width="260" height="104" rx="4" fill="#f4f1ea" ${st(3)}/><rect x="-130" y="0" width="64" height="104" fill="#c8323a"/><rect x="66" y="0" width="64" height="104" fill="#c8323a"/><rect x="-130" y="0" width="260" height="104" rx="4" fill="none" ${st(3)}/>
        <path d="M0 18 L6 30 L14 26 L12 46 L24 38 L28 46 L38 44 L32 58 L38 62 L18 74 L20 82 L2 80 V96 H-2 V80 L-20 82 L-18 74 L-38 62 L-32 58 L-38 44 L-28 46 L-24 38 L-12 46 L-14 26 L-6 30 Z" fill="#c8323a" ${st(1.6)}/></g>
      ${sign(80, 104, MOOSE)}
      ${platform('#9a6a40', '#6e4628')}`,

    // Brasilien: Zuckerhut und Christus-Statue im Sonnenuntergang, Meer, Palmen, bunte Karnevals-Wimpel.
    br: () => `
      ${sky('w-br', [[0, '#5b3a7a'], [0.45, '#e86f6f'], [1, '#ffd27a']])}
      <circle cx="480" cy="140" r="44" fill="#ffe7a0" opacity="0.9"/>
      <rect x="0" y="150" width="${W}" height="${DY - 150}" fill="#2f6f9a"/>
      ${[0, 1, 2].map((k) => `<path d="M${380 + k * 30} ${160 + k * 10} h${200 - k * 60}" stroke="#ffe7a0" stroke-width="2" opacity="0.6"/>`).join('')}
      <path d="M100 150 Q120 64 170 70 Q200 80 206 150 Z" fill="#2a4a3a" ${st(2.4)}/><path d="M190 150 Q206 112 236 116 Q256 124 262 150 Z" fill="#24402f" ${st(2.4)}/>
      <path d="M660 150 Q740 72 820 90 Q880 110 900 150 Z" fill="#2a4a3a" ${st(2.4)}/>
      <g transform="translate(790 64)" fill="#f4f1ea" stroke="${INK}" stroke-width="1.6"><rect x="-3" y="0" width="6" height="26"/><rect x="-22" y="4" width="44" height="5" rx="2"/><circle cx="0" cy="-4" r="4"/></g>
      ${palm(40, DY, 1, 1)}${palm(920, DY, 1.05, -1)}
      ${bunting(60, 20, 900, 20, 22, ['#2f9a4a', '#f2c14e', '#2b5fae', '#ff6f91'], 22)}
      ${platform('#b08a5a', '#7a5a36')}`,

    // Südafrika: Tafelberg im Abendlicht, große Sonne, Akazie, bunt bemalte Wände im Ndebele-Muster.
    za: () => `
      ${sky('w-za', [[0, '#e8774f'], [1, '#ffcf8a']])}
      <circle cx="660" cy="96" r="54" fill="#ffe7a0" opacity="0.85"/>
      <path d="M-10 ${DY} L60 140 L120 86 H540 L600 120 L700 150 L800 130 L970 ${DY} Z" fill="#5a3a5e" ${st(2.4)}/>
      <path d="M120 86 H540" stroke="#7a5a7e" stroke-width="3"/>
      <g transform="translate(830 ${DY})"><path d="M0 0 Q4 -50 -2 -76" fill="none" stroke="#3a2416" stroke-width="7"/><path d="M-2 -60 L-24 -82 M0 -66 L26 -86" stroke="#3a2416" stroke-width="4"/><path d="M-70 -84 Q-40 -110 0 -104 Q50 -112 80 -86 Q40 -78 0 -82 Q-40 -76 -70 -84 Z" fill="#2f4a2a" ${st(2.4)}/></g>
      ${[[0, 110], [870, W]].map(([a, b]) => `${box(a, 104, b - a, DY - 104, '#f4f1ea', 0, 2.4)}${Array.from({ length: Math.ceil((b - a) / 30) }, (_, i) => `<rect x="${a + i * 30}" y="104" width="30" height="20" fill="${['#c8323a', '#2b5fae', '#f2c14e', '#2f9a4a'][i % 4]}" ${st(1.6)}/><path d="M${a + i * 30 + 6} 136 l9 18 l9 -18 Z" fill="${['#2b5fae', '#f2c14e', '#2f9a4a', '#c8323a'][i % 4]}" ${st(1.6)}/><rect x="${a + i * 30 + 4}" y="168" width="22" height="22" fill="none" ${st(2)}/>`).join('')}`).join('')}
      ${platform('#9a6a40', '#6e4628')}
      ${Array.from({ length: 12 }, (_, i) => `<path d="M${i * 80 + 20} ${TOP + 22} l20 14 l20 -14 l20 14" fill="none" stroke="${['#f2c14e', '#c8323a', '#2b5fae'][i % 3]}" stroke-width="4" opacity="0.7"/>`).join('')}`,

    // Japan: Fuji mit Schneekappe vor roter Sonne, Torii-Tore an den Seiten, Kirschblütenzweige, Papierlaternen.
    jp: () => `
      ${sky('w-jp', [[0, '#ffd1dc'], [1, '#fff4e6']])}
      <circle cx="480" cy="86" r="52" fill="#e04a4a" opacity="0.85"/>
      <path d="M180 ${DY} L400 72 Q480 50 560 72 L780 ${DY} Z" fill="#6f86b8" ${st(2.6)}/>
      <path d="M400 72 Q480 50 560 72 L590 96 L560 90 L536 104 L506 92 L480 106 L452 92 L424 104 L400 90 L370 96 Z" fill="#f4f8ff" ${st(2)}/>
      ${[60, 900].map((x) => `<g transform="translate(${x} 0)"><rect x="-34" y="64" width="12" height="${DY - 64}" fill="#c8323a" ${st(2.4)}/><rect x="22" y="64" width="12" height="${DY - 64}" fill="#c8323a" ${st(2.4)}/><path d="M-52 50 Q0 40 52 50 L48 62 H-48 Z" fill="${INK}"/><rect x="-44" y="76" width="88" height="9" fill="#c8323a" ${st(2)}/></g>`).join('')}
      ${[[0, 1], [W, -1]].map(([x, d]) => `<path d="M${x} 20 Q${x + d * 100} 30 ${x + d * 180} 16 M${x + d * 80} 26 Q${x + d * 110} 50 ${x + d * 150} 54" fill="none" stroke="#5a3a2e" stroke-width="5" stroke-linecap="round"/>${Array.from({ length: 14 }, (_, i) => `<circle cx="${x + d * (20 + (i * 47) % 170)}" cy="${12 + (i * 23) % 46}" r="${6 + (i % 3)}" fill="${i % 2 ? '#ffb3c8' : '#ff8fb0'}" opacity="0.92"/>`).join('')}`).join('')}
      ${line(150, 110, 810, 110, 18)}${Array.from({ length: 8 }, (_, i) => { const [x, y] = rope(150, 110, 810, 110, 18, (i + 0.5) / 8); return `<g transform="translate(${x.toFixed(0)} ${(y + 4).toFixed(0)})"><path d="M0 -4 V2" stroke="${INK}" stroke-width="1.6"/><ellipse cx="0" cy="14" rx="11" ry="13" fill="${i % 2 ? '#f4f1ea' : '#e04a4a'}" ${st(1.8)}/><path d="M-11 14 H11 M-9 7 H9 M-9 21 H9" stroke="${INK}" stroke-width="0.8" opacity="0.5"/><rect x="-5" y="0" width="10" height="3" fill="${INK}"/><rect x="-5" y="26" width="10" height="3" fill="${INK}"/></g>`; }).join('')}
      ${platform('#8a5a3c', '#5e3a24')}`,

    // Australien: Opernhaus und Harbour Bridge in Sydney, Eukalyptus, Känguru-Schild.
    au: () => `
      ${sky('w-au', [[0, '#5aa8f2'], [1, '#d8efff']])}
      <rect x="0" y="160" width="${W}" height="${DY - 160}" fill="#2f7ab0"/>
      <g transform="translate(340 162)">${[[-90, 40, 0.6], [-40, 62, 0.8], [10, 72, 1], [70, 50, 0.7], [-120, 26, 0.5]].map(([x, h, s]) => `<path d="M${x} 0 Q${x + 4} ${-h} ${x + 46 * s} ${-h - 8} Q${x + 30 * s} ${-h * 0.4} ${x + 52 * s} 0 Z" fill="#f4f1ea" ${st(2.4)}/><path d="M${x + 10} -4 Q${x + 14} ${-h * 0.8} ${x + 40 * s} ${-h - 4}" fill="none" stroke="#c9ccd6" stroke-width="1.6"/>`).join('')}<rect x="-130" y="0" width="230" height="10" fill="#c9a87a" ${st(2)}/></g>
      <g transform="translate(700 0)"><path d="M-120 168 Q60 20 240 168" fill="none" stroke="#5a6478" stroke-width="10"/><path d="M-100 168 Q60 44 220 168" fill="none" stroke="#5a6478" stroke-width="5"/>${Array.from({ length: 12 }, (_, i) => { const t = (i + 1) / 13; const x = -120 + t * 360; const y = (1 - t) ** 2 * 168 + 2 * (1 - t) * t * 20 + t * t * 168; return `<path d="M${x.toFixed(0)} ${(y + 4).toFixed(0)} V132" stroke="#5a6478" stroke-width="2"/>`; }).join('')}<path d="M-130 132 H250" stroke="#5a6478" stroke-width="6"/>${[-132, 232].map((x) => box(x, 112, 28, 60, '#c9b48a', 2, 2)).join('')}</g>
      ${[40, 920].map((x, i) => `<path d="M${x} ${DY} Q${x + (i ? -8 : 8)} 140 ${x} 90" fill="none" stroke="#c9b8a0" stroke-width="8"/><ellipse cx="${x}" cy="84" rx="40" ry="26" fill="#7fa88a" opacity="0.9" ${st(1.8)}/><ellipse cx="${x + (i ? -20 : 20)}" cy="108" rx="24" ry="14" fill="#8ab894" opacity="0.9" ${st(1.6)}/>`).join('')}
      ${sign(130, 104, KANGAROO)}
      ${platform('#3b4256', '#232838')}`,
  };
}
