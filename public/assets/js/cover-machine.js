import { LAYOUTS, MOTIFS, PALETTES, RARITY_NAMES, STYLES, coverSvg } from './cover.js';

/**
 * Cover-Automat: Die Bänder (Motiv, Farben, Stil, Anordnung) öffnen sich nacheinander; immer läuft nur eins.
 * Tippen hält es an – das Feld, das gerade im Rahmen steht, gilt. Kein Nachrutschen, dafür flott.
 * Die Bänder (Felder und Wertigkeit) kommen vom Server; zurück geht nur, welches Feld gewählt wurde.
 */

const CELL_W = 88;
const OPEN_MS = 450;

const CELL = {
  motif: (k) => `<svg class="cm-motif" viewBox="0 0 100 100"><path d="${MOTIFS[k].d}" fill-rule="evenodd" fill="#f3eefc"/></svg>`,
  palette: (k) => {
    const p = PALETTES[k];
    return `<div class="cm-swatch"><div style="background:${p.bg}"></div><div style="background:${p.main}"></div><div style="background:${p.accent}"></div></div>`;
  },
  style: (k) => coverSvg({ motif: 'stern', palette: 'neon', style: k, layout: 'mitte', rarity: [2, 2, 2, 2] }),
  layout: (k) => coverSvg({ motif: 'herz', palette: 'mono', style: 'popart', layout: k, rarity: [2, 2, 2, 2] }),
};
const LABEL = {
  motif: (k) => MOTIFS[k].name,
  palette: (k) => PALETTES[k].name,
  style: (k) => STYLES[k].name,
  layout: (k) => LAYOUTS[k].name,
};
export const REEL_NAMES = { motif: 'Motiv', palette: 'Farben', style: 'Stil', layout: 'Anordnung' };
const RARITY_COLOR = ['', 'var(--cm-green)', 'var(--cm-blue)', 'var(--cm-gold)'];

export function reelLabel(key, item) {
  return LABEL[key](item);
}

/**
 * reels: [{ key, items: [{ key, rarity }] }], speeds: Felder pro Sekunde je Band.
 * onDone(picks): picks = gewählter Feld-Index je Band.
 */
export function createCoverMachine(root, { reels, speeds, onDone }) {
  root.innerHTML = `
    <div class="cm">
      ${reels.map((r, i) => `
        <div class="cm-row ${i === 0 ? 'cm-active' : 'cm-closed'}" data-row="${i}">
          <div class="cm-label">${REEL_NAMES[r.key]}<small data-pick>${i === 0 ? '' : '?'}</small></div>
          <div class="cm-reel" data-reel="${i}"><div class="cm-strip">${[0, 1, 2].map(() => r.items.map((it) => `<div class="cm-cell r${it.rarity}">${CELL[r.key](it.key)}</div>`).join('')).join('')}</div></div>
        </div>`).join('')}
      <div class="cm-controls"><button class="btn primary cm-stop" data-cm-stop>Stopp!</button></div>
    </div>`;
  const state = reels.map((r) => ({ pos: Math.random() * r.items.length, target: null, stopped: false }));
  const picks = [];
  let current = 0;
  let runningFrom = performance.now() + 300;
  let last = performance.now();
  let raf = 0;
  let done = false;

  function draw(i) {
    const el = root.querySelector(`[data-reel="${i}"]`);
    const n = reels[i].items.length;
    const x = el.clientWidth / 2 - (((state[i].pos % n) + n) % n + n) * CELL_W - CELL_W / 2;
    el.firstElementChild.style.transform = `translateX(${x}px)`;
  }

  function frame(now) {
    const dt = Math.min(0.05, (now - last) / 1000);
    last = now;
    const i = current;
    const s = state[i];
    if (s) {
      if (s.target !== null) {
        // Kurz einrasten auf das Feld im Rahmen.
        const d = s.target - s.pos;
        s.pos += d * Math.min(1, 18 * dt);
        if (Math.abs(d) < 0.01) s.pos = s.target;
      } else if (now >= runningFrom) {
        s.pos += speeds[i] * dt;
      }
      draw(i);
      if (s.target !== null && s.pos === s.target) land(i);
    }
    if (!done) raf = requestAnimationFrame(frame);
  }

  function stop() {
    const s = state[current];
    if (!s || s.target !== null || performance.now() < runningFrom) return;
    s.target = Math.round(s.pos);
  }

  function land(i) {
    const s = state[i];
    s.stopped = true;
    const n = reels[i].items.length;
    const index = ((Math.round(s.pos) % n) + n) % n;
    picks.push(index);
    const item = reels[i].items[index];
    root.querySelector(`[data-row="${i}"] [data-pick]`).innerHTML = `${LABEL[reels[i].key](item.key)}${item.rarity
      ? ` · <span style="color:${RARITY_COLOR[item.rarity]}">${RARITY_NAMES[item.rarity]}</span>` : ''}`;
    root.querySelector(`[data-row="${i}"]`).classList.replace('cm-active', 'cm-done');
    current++;
    if (current >= reels.length) {
      done = true;
      root.querySelector('[data-cm-stop]').disabled = true;
      setTimeout(() => onDone(picks), 500);
      return;
    }
    // Nächste Reihe öffnet sich; erst danach läuft das Band.
    const next = root.querySelector(`[data-row="${current}"]`);
    next.classList.replace('cm-closed', 'cm-active');
    next.querySelector('[data-pick]').textContent = '';
    runningFrom = performance.now() + OPEN_MS;
    draw(current);
  }

  const onPointer = (e) => {
    if (e.target.closest('[data-reel], [data-cm-stop]')) {
      e.preventDefault();
      stop();
    }
  };
  const onKey = (e) => {
    if (e.code === 'Space' || e.key === 'Enter') {
      e.preventDefault();
      stop();
    }
  };
  const onMenu = (e) => e.preventDefault();
  root.addEventListener('pointerdown', onPointer);
  root.addEventListener('contextmenu', onMenu);
  window.addEventListener('keydown', onKey);
  requestAnimationFrame(() => reels.forEach((_, i) => draw(i)));
  raf = requestAnimationFrame(frame);

  return {
    destroy() {
      done = true;
      cancelAnimationFrame(raf);
      root.removeEventListener('pointerdown', onPointer);
      root.removeEventListener('contextmenu', onMenu);
      window.removeEventListener('keydown', onKey);
    },
  };
}
