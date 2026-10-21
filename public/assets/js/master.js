import { getAudio, scheduleBacking } from './audio.js';
import { loadStems, playStems } from './music.js';
import { COLORS, judgeRatio, mountShell } from './mg-shell.js';
import { duration, percent } from './ui.js';

const BANDS = [
  { name: 'Bässe', type: 'lowshelf', freq: 250 },
  { name: 'Mitten', type: 'peaking', freq: 1000 },
  { name: 'Höhen', type: 'highshelf', freq: 4000 },
];
/** Tasten pro Regler: hoch / runter. */
const KEYS = [['q', 'a'], ['w', 's'], ['e', 'd']];
const KEY_SPEED = 0.6;
/** So viele Dezibel klingt es daneben, wenn der Regler ganz falsch steht. */
const DB_PER_UNIT = 24;

/**
 * Master-Mix (Studio, letzte Aufnahme): Die ganze Band läuft durch einen Klangregler mit Bässen, Mitten und Höhen.
 * Jeder Regler hat eine richtige Stellung; steht er daneben, klingt es dumpf, dröhnend oder spitz. Eine Lampe pro
 * Regler zeigt nur warm oder kalt (rot, gelb, grün), nicht die Richtung. Zur Hälfte verschiebt sich die Stellung einmal.
 */
export function createMasterGame(container, game) {
  const shell = mountShell(container, game, {
    hud: '<span>Richtig eingestellt <b class="mg-acc">–</b></span><span class="mg-combo"></span><span>Zeit <b class="mg-time"></b></span>',
    hint: 'Finde für <b>Bässe, Mitten und Höhen</b> die richtige Stellung. Hör hin! Die Lampe wird grün, wenn es passt. Regler ziehen oder Tasten Q/A, W/S, E/D.',
  });
  const { canvas, draw } = shell;
  const accEl = shell.el('.mg-acc');
  const comboEl = shell.el('.mg-combo');
  const timeEl = shell.el('.mg-time');
  timeEl.textContent = duration(game.duration);

  const zone = game.zone;
  const segmentLength = game.duration / game.segments;
  const beat = 60 / game.bpm;
  const faders = BANDS.map(() => 0.5);
  const inZone = BANDS.map(() => Array(game.segments).fill(0));
  const totals = BANDS.map(() => Array(game.segments).fill(0));
  const held = new Set();
  const pointers = new Map();
  let audio = null;
  let t0 = 0;
  let last = 0;
  let raf = 0;
  let running = false;
  let stopBacking = () => {};
  let resolvePlay;
  let filters = null;
  let stems = null;
  let stemPlayer = null;
  const startButton = shell.el('.mg-start');
  startButton.disabled = true;
  loadStems([game.stems || []]).then((result) => {
    stems = result;
    startButton.disabled = false;
  });

  const clock = () => (audio ? audio.currentTime : performance.now() / 1000);
  const segmentAt = (now) => Math.max(0, Math.min(game.segments - 1, Math.floor(now / segmentLength)));
  const offBy = (k, now) => faders[k] - game.targets[segmentAt(now)][k];

  function layout() {
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    const colW = Math.min(150, w / BANDS.length);
    const left = (w - colW * BANDS.length) / 2;
    return { w, h, colW, left, top: 64, range: h - 120 };
  }

  function step(now, dt) {
    for (const { k, y } of pointers.values()) setFaderFromPointer(k, y);
    BANDS.forEach((_, k) => {
      const [up, downKey] = KEYS[k];
      if (held.has(up)) faders[k] = Math.min(1, faders[k] + KEY_SPEED * dt);
      if (held.has(downKey)) faders[k] = Math.max(0, faders[k] - KEY_SPEED * dt);
      // Was falsch steht, hört man: zu viel oder zu wenig in diesem Frequenzband.
      if (filters) filters[k].gain.setTargetAtTime(Math.max(-18, Math.min(18, offBy(k, now) * DB_PER_UNIT)), audio.currentTime, 0.04);
      if (now >= 0 && now < game.duration) {
        const segment = segmentAt(now);
        totals[k][segment] += dt;
        if (Math.abs(offBy(k, now)) <= zone) inZone[k][segment] += dt;
      }
    });
  }

  function lampColor(k, now) {
    const off = Math.abs(offBy(k, now));
    return off <= zone ? '#3ddc97' : off <= zone * 2.2 ? '#ffb547' : '#ff6060';
  }

  function render(now) {
    const { w, h, colW, left, top, range } = layout();
    draw.clearRect(0, 0, w, h);
    const yOf = (level) => top + (1 - level) * range;
    BANDS.forEach((band, k) => {
      const cx = left + colW * k + colW / 2;
      const slotW = 10;

      draw.fillStyle = now >= 0 ? lampColor(k, now) : 'rgba(255,255,255,0.15)';
      draw.beginPath();
      draw.arc(cx, 38, 13, 0, Math.PI * 2);
      draw.fill();

      draw.fillStyle = 'rgba(255,255,255,0.08)';
      draw.fillRect(cx - slotW / 2, top, slotW, range);
      for (let i = 0; i <= 10; i++) {
        draw.fillStyle = 'rgba(255,255,255,0.18)';
        draw.fillRect(cx - 18, yOf(i / 10), i % 5 === 0 ? 36 : 22, 1);
      }
      const knobY = yOf(faders[k]);
      draw.fillStyle = '#f3eefc';
      draw.beginPath();
      draw.roundRect(cx - 28, knobY - 9, 56, 18, 5);
      draw.fill();
      draw.fillStyle = COLORS[k % COLORS.length];
      draw.fillRect(cx - 28, knobY - 1.5, 56, 3);

      for (let s = 0; s < game.segments; s++) {
        const done = now >= (s + 1) * segmentLength;
        const ratio = totals[k][s] ? inZone[k][s] / totals[k][s] : 0;
        draw.fillStyle = !done ? 'rgba(255,255,255,0.15)' : ratio >= 0.7 ? '#ffc23d' : ratio >= 0.4 ? '#3ddc97' : '#ff6060';
        draw.fillRect(cx - 28 + s * (56 / game.segments), 10, 56 / game.segments - 2, 5);
      }

      draw.fillStyle = '#f3eefc';
      draw.font = '600 13px system-ui, sans-serif';
      draw.textAlign = 'center';
      draw.fillText(band.name, cx, h - 30);
      draw.fillStyle = '#a49bbd';
      draw.font = '600 11px system-ui, sans-serif';
      draw.fillText(KEYS[k].map((key) => key.toUpperCase()).join(' / '), cx, h - 12);
    });

    if (now < 0) {
      draw.fillStyle = '#fff';
      draw.font = '800 64px system-ui, sans-serif';
      draw.textAlign = 'center';
      draw.fillText(String(Math.ceil(-now / beat)), w / 2, h / 2);
    }
  }

  function frame() {
    const t = clock();
    const now = t - t0;
    const dt = Math.min(0.05, t - last);
    last = t;
    step(now, dt);
    render(now);
    const total = totals.flat().reduce((a, b) => a + b, 0);
    const good = inZone.flat().reduce((a, b) => a + b, 0);
    accEl.textContent = total > 0.3 ? percent(good / total) : '–';
    const changed = now >= 0 && segmentAt(now) > 0 && game.targets[segmentAt(now)].join() !== game.targets[segmentAt(now) - 1].join()
      && now - segmentAt(now) * segmentLength < 1.5;
    const allOk = BANDS.every((_, k) => Math.abs(offBy(k, now)) <= zone);
    comboEl.textContent = changed ? 'Neuer Teil – hör noch mal hin!' : now >= 0 && allOk ? 'Klingt richtig gut!' : '';
    timeEl.textContent = duration(Math.min(game.duration, game.duration - Math.max(0, now)));
    if (now > game.duration + 0.2) {
      const counts = { perfect: 0, good: 0, miss: 0 };
      BANDS.forEach((_, k) => {
        for (let s = 0; s < game.segments; s++) counts[judgeRatio(totals[k][s] ? inZone[k][s] / totals[k][s] : 0)]++;
      });
      finish(counts);
      return;
    }
    raf = requestAnimationFrame(frame);
  }

  function columnAt(clientX) {
    const rect = canvas.getBoundingClientRect();
    const { colW, left } = layout();
    const k = Math.floor((clientX - rect.left - left) / colW);
    return k >= 0 && k < BANDS.length ? k : null;
  }

  function setFaderFromPointer(k, clientY) {
    const rect = canvas.getBoundingClientRect();
    const { top, range } = layout();
    faders[k] = Math.max(0, Math.min(1, 1 - (clientY - rect.top - top) / range));
  }

  function onPointerDown(e) {
    if (!running) return;
    const k = columnAt(e.clientX);
    if (k === null) return;
    e.preventDefault();
    try { canvas.setPointerCapture(e.pointerId); } catch { /* Zeiger schon weg */ }
    pointers.set(e.pointerId, { k, y: e.clientY });
  }
  function onPointerMove(e) {
    const p = pointers.get(e.pointerId);
    if (p) p.y = e.clientY;
  }
  function onPointerUp(e) {
    pointers.delete(e.pointerId);
  }
  function onKeyDown(e) {
    if (!running || e.ctrlKey || e.metaKey || e.altKey) return;
    const key = e.key.toLowerCase();
    if (KEYS.flat().includes(key)) {
      e.preventDefault();
      held.add(key);
    }
  }
  function onKeyUp(e) {
    held.delete(e.key.toLowerCase());
  }

  function start() {
    shell.hideOverlay();
    shell.resize();
    audio = getAudio();
    t0 = clock() + 0.15 + 4 * beat;
    last = clock();
    if (stems) {
      filters = BANDS.map((band) => {
        const filter = audio.createBiquadFilter();
        filter.type = band.type;
        filter.frequency.value = band.freq;
        if (band.type === 'peaking') filter.Q.value = 0.9;
        return filter;
      });
      filters.reduce((a, b) => a.connect(b)).connect(audio.destination);
      stemPlayer = playStems(stems, t0, { destination: filters[0] });
    } else {
      stopBacking = scheduleBacking({ genre: game.genre, bpm: game.bpm, start: t0, duration: game.duration });
    }
    running = true;
    raf = requestAnimationFrame(frame);
  }

  function finish(result) {
    running = false;
    cancelAnimationFrame(raf);
    stopBacking();
    stemPlayer?.stop();
    resolvePlay?.(result);
  }

  canvas.addEventListener('pointerdown', onPointerDown);
  canvas.addEventListener('pointermove', onPointerMove);
  canvas.addEventListener('pointerup', onPointerUp);
  canvas.addEventListener('pointercancel', onPointerUp);
  window.addEventListener('keydown', onKeyDown, true);
  window.addEventListener('keyup', onKeyUp, true);
  window.addEventListener('resize', shell.resize);
  startButton.addEventListener('click', start);
  shell.el('.mg-abort').addEventListener('click', () => {
    if (!shell.resultShown()) finish({ aborted: true });
  });
  requestAnimationFrame(() => {
    shell.resize();
    render(-4 * beat);
  });

  return {
    play: () => new Promise((resolve) => {
      resolvePlay = resolve;
    }),
    showResult: shell.showResult,
    destroy() {
      running = false;
      cancelAnimationFrame(raf);
      stopBacking();
      stemPlayer?.stop();
      window.removeEventListener('keydown', onKeyDown, true);
      window.removeEventListener('keyup', onKeyUp, true);
      window.removeEventListener('resize', shell.resize);
    },
  };
}
