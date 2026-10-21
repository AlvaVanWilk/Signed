import { getAudio, playTrackBeat, scheduleBacking } from './audio.js';
import { loadStems, playStems } from './music.js';
import { instrumentEmoji } from './cards.js';
import { COLORS, judgeRatio, mountShell } from './mg-shell.js';
import { duration, percent } from './ui.js';

const ZONE_CENTER = 0.6;
/** Tasten pro Spur: hoch / runter. „y“ zusätzlich für englische Tastaturen. */
const KEYS = [['q', 'a'], ['w', 's'], ['e', 'd'], ['r', 'f'], ['t', 'g'], ['z', 'h']];
const KEY_SPEED = 0.9;

/**
 * Mischpult (Studio): Die Band spielt von selbst, die Pegel der Spuren gleiten ständig – bei schwächeren Musikern
 * schneller und weiter. Du hältst jede Spur mit ihrem Regler im grünen Bereich. Große Bands kommen in Gruppen.
 */
export function createMixerGame(container, game) {
  const tracks = game.tracks;
  const keyHint = tracks.map((_, i) => KEYS[i].map((k) => k.toUpperCase()).join('/')).join(', ');
  const shell = mountShell(container, game, {
    hud: '<span>Im grünen Bereich <b class="mg-acc">–</b></span><span class="mg-combo"></span><span>Zeit <b class="mg-time"></b></span>',
    hint: `Die Band spielt, du mischst. Halte jede Spur im <b>grünen Bereich</b>: Regler mit dem Finger oder der Maus ziehen – oder mit den Tasten ${keyHint}.`,
  });
  const { canvas, draw } = shell;
  const accEl = shell.el('.mg-acc');
  const comboEl = shell.el('.mg-combo');
  const timeEl = shell.el('.mg-time');
  timeEl.textContent = duration(game.duration);

  const zone = game.zone;
  const segmentLength = game.duration / game.segments;
  const beat = 60 / game.bpm;
  const faders = tracks.map(() => ZONE_CENTER);
  const offsets = tracks.map(() => 0);
  const targets = tracks.map(() => 0);
  const phases = tracks.map(() => Math.random() * Math.PI * 2);
  const inZone = tracks.map(() => Array(game.segments).fill(0));
  const totals = tracks.map(() => Array(game.segments).fill(0));
  const held = new Set();
  const pointers = new Map();
  let driftIndex = 0;
  let lastBeat = -1;
  let audio = null;
  let t0 = 0;
  let last = 0;
  let raf = 0;
  let running = false;
  let stopBacking = () => {};
  let resolvePlay;
  // Echte Aufnahmen, sofern es sie gibt; sonst spielt der Synthesizer.
  let stems = null;
  let stemPlayer = null;
  const startButton = shell.el('.mg-start');
  startButton.disabled = true;
  loadStems(tracks.map((t) => t.stems || [])).then((result) => {
    stems = result;
    startButton.disabled = false;
  });

  const clock = () => (audio ? audio.currentTime : performance.now() / 1000);
  const levelOf = (k, now) => Math.max(0, Math.min(1, faders[k] + offsets[k] + Math.sin(now * 1.7 + phases[k]) * 0.015));

  function layout() {
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    const colW = Math.min(150, w / tracks.length);
    const left = (w - colW * tracks.length) / 2;
    return { w, h, colW, left, top: 30, range: h - 90 };
  }

  function step(now, dt) {
    while (driftIndex < game.drifts.length && game.drifts[driftIndex].t <= now) {
      const d = game.drifts[driftIndex++];
      targets[d.k] = d.v;
    }
    // Weich zum nächsten Zielpunkt gleiten: ständige Bewegung statt Sprünge.
    tracks.forEach((_, k) => {
      offsets[k] += (targets[k] - offsets[k]) * Math.min(1, dt * 1.6);
    });
    // Mit Aufnahmen hört man den Pegel direkt: Sollwert = volle Lautstärke, darüber lauter, darunter leiser.
    if (stemPlayer) tracks.forEach((_, k) => stemPlayer.gains[k].gain.setTargetAtTime(Math.min(1.8, levelOf(k, now) / ZONE_CENTER), audio.currentTime, 0.03));
    // Ziehen verschiebt den Regler; die Spur schwankt aber weiter – Festhalten allein hält sie nicht im grünen Bereich.
    for (const p of pointers.values()) moveFader(p);
    tracks.forEach((_, k) => {
      const [up, downKey] = KEYS[k];
      if (held.has(up)) faders[k] = Math.min(1.6, faders[k] + KEY_SPEED * dt);
      if (held.has(downKey) || (k === 5 && held.has('y'))) faders[k] = Math.max(-0.6, faders[k] - KEY_SPEED * dt);
      if (now >= 0 && now < game.duration) {
        const segment = Math.min(game.segments - 1, Math.floor(now / segmentLength));
        totals[k][segment] += dt;
        if (Math.abs(levelOf(k, now) - ZONE_CENTER) <= zone) inZone[k][segment] += dt;
      }
    });
    const beatIndex = Math.floor(now / beat);
    if (now >= 0 && now < game.duration && beatIndex > lastBeat) {
      lastBeat = beatIndex;
      if (!stemPlayer) tracks.forEach((track, k) => track.instruments.forEach((instrument) => playTrackBeat(instrument, beatIndex, now, game.bpm, Math.min(1, levelOf(k, now) * 1.25))));
    }
  }

  function render(now) {
    const { w, h, colW, left, top, range } = layout();
    draw.clearRect(0, 0, w, h);
    const yOf = (level) => top + (1 - level) * range;
    tracks.forEach((track, k) => {
      const cx = left + colW * k + colW / 2;
      const meterW = Math.min(46, colW * 0.42);
      const level = levelOf(k, now);
      const ok = Math.abs(level - ZONE_CENTER) <= zone;

      draw.fillStyle = 'rgba(255,255,255,0.05)';
      draw.fillRect(cx - meterW / 2, top, meterW, range);
      draw.fillStyle = 'rgba(61,220,151,0.22)';
      draw.fillRect(cx - meterW / 2 - 6, yOf(ZONE_CENTER + zone), meterW + 12, (zone * 2) * range);
      draw.fillStyle = ok ? '#3ddc97' : level > ZONE_CENTER ? '#ff6060' : '#ffb547';
      draw.fillRect(cx - meterW / 2 + 6, yOf(level), meterW - 12, top + range - yOf(level));

      // Der Regler sitzt auf dem tatsächlichen Pegel: Er driftet weg, und man zieht ihn zurück.
      const knobY = yOf(level);
      draw.fillStyle = '#f3eefc';
      draw.beginPath();
      draw.roundRect(cx - meterW / 2 - 10, knobY - 7, meterW + 20, 14, 5);
      draw.fill();
      draw.fillStyle = COLORS[k % COLORS.length];
      draw.fillRect(cx - meterW / 2 - 10, knobY - 1.5, meterW + 20, 3);

      for (let s = 0; s < game.segments; s++) {
        const done = now >= (s + 1) * segmentLength;
        const ratio = totals[k][s] ? inZone[k][s] / totals[k][s] : 0;
        draw.fillStyle = !done ? 'rgba(255,255,255,0.15)' : ratio >= 0.7 ? '#ffc23d' : ratio >= 0.4 ? '#3ddc97' : '#ff6060';
        draw.fillRect(cx - meterW / 2 + s * (meterW / game.segments), 10, meterW / game.segments - 2, 6);
      }

      draw.fillStyle = '#f3eefc';
      draw.font = '600 13px system-ui, sans-serif';
      draw.textAlign = 'center';
      draw.fillText(`${track.instruments.map(instrumentEmoji).join('')} ${track.name}`, cx, h - 30);
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
    const allOk = tracks.every((_, k) => Math.abs(levelOf(k, now) - ZONE_CENTER) <= zone);
    comboEl.textContent = now >= 0 && allOk ? 'Sauberer Mix!' : '';
    timeEl.textContent = duration(Math.min(game.duration, game.duration - Math.max(0, now)));
    if (now > game.duration + 0.2) {
      const counts = { perfect: 0, good: 0, miss: 0 };
      tracks.forEach((_, k) => {
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
    return k >= 0 && k < tracks.length ? k : null;
  }

  /** Der Regler folgt der Bewegung des Fingers (nicht seiner Position): Wer stillhält, lässt die Spur wegschwanken. */
  function moveFader(p) {
    const { range } = layout();
    faders[p.k] = Math.max(-0.6, Math.min(1.6, p.f0 + (p.y0 - p.y) / range));
  }

  function onPointerDown(e) {
    if (!running) return;
    const k = columnAt(e.clientX);
    if (k === null) return;
    e.preventDefault();
    try { canvas.setPointerCapture(e.pointerId); } catch { /* Zeiger schon weg */ }
    pointers.set(e.pointerId, { k, y: e.clientY, y0: e.clientY, f0: faders[k] });
  }
  function onPointerMove(e) {
    const held = pointers.get(e.pointerId);
    if (held) held.y = e.clientY;
  }
  function onPointerUp(e) {
    pointers.delete(e.pointerId);
  }
  function onKeyDown(e) {
    if (!running || e.ctrlKey || e.metaKey || e.altKey) return;
    const key = e.key.toLowerCase();
    if (KEYS.slice(0, tracks.length).flat().includes(key) || key === 'y') {
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
    stopBacking = scheduleBacking({ genre: game.genre, bpm: game.bpm, start: t0, duration: game.duration, drums: false, bass: false });
    if (stems) stemPlayer = playStems(stems, t0);
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
  shell.el('.mg-start').addEventListener('click', start);
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
      window.removeEventListener('keydown', onKeyDown, true);
      window.removeEventListener('keyup', onKeyUp, true);
      window.removeEventListener('resize', shell.resize);
    },
  };
}
