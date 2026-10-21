import { createVoice, getAudio, scaleMidi, scheduleBacking } from './audio.js';
import { loadStems, playStems } from './music.js';
import { judgeRatio, mountShell } from './mg-shell.js';
import { duration, percent } from './ui.js';

/** Sekunden, die man vor bzw. hinter der eigenen Stimme sieht. */
const AHEAD = 3.2;
const BEHIND = 1.0;
const MARKER_X = 0.25;
/** So schnell steigt bzw. sinkt der Ton (Anteil der Tonleiter pro Sekunde), mindestens bzw. höchstens. */
const SPEED = 0.5;
const MAX_SPEED = 1.0;

/**
 * Echte Melodien springen weiter: Die Geschwindigkeit reicht für die meisten Sprünge
 * (drei Viertel davon schafft man rechtzeitig), bleibt aber steuerbar.
 */
function speedFor(notes) {
  const rates = [];
  for (let i = 1; i < notes.length; i++) {
    const gap = Math.max(0.25, notes[i].t - notes[i - 1].t);
    rates.push(Math.abs(notes[i].p - notes[i - 1].p) / 7 / gap);
  }
  rates.sort((a, b) => a - b);
  const typical = rates[Math.floor(rates.length * 0.75)] ?? 0;
  return Math.max(SPEED, Math.min(MAX_SPEED, typical));
}
const HOLD_KEYS = [' ', 'arrowup', 'w', 'j'];

/**
 * Tonhöhen-Spiel: Gedrückt halten lässt den Ton steigen, Loslassen lässt ihn sinken.
 * Gewertet wird, wie lange man bei jedem Ton der Melodie im richtigen Bereich bleibt – nicht der genaue Moment.
 */
export function createPitchGame(container, game) {
  const shell = mountShell(container, game, {
    hud: '<span>Im Ton <b class="mg-acc">–</b></span><span class="mg-combo"></span><span>Zeit <b class="mg-time"></b></span>',
    hint: 'Halte die <b>Leertaste</b> gedrückt (oder den Finger auf dem Feld), dann steigt dein Ton. Lass los, dann sinkt er. Bleib in den Balken der Melodie.',
    below: '<button class="mg-hold">Gedrückt halten: Ton steigt</button>',
  });
  const { canvas, draw } = shell;
  const accEl = shell.el('.mg-acc');
  const comboEl = shell.el('.mg-combo');
  const timeEl = shell.el('.mg-time');
  const holdButton = shell.el('.mg-hold');
  timeEl.textContent = duration(game.duration);

  const notes = game.notes.map((n) => ({ ...n, level: n.p / 7, total: 0, inTune: 0, judged: null }));
  const speed = speedFor(game.notes);
  const tolerance = game.tolerance;
  const beat = 60 / game.bpm;
  const counts = { perfect: 0, good: 0, miss: 0 };
  const trail = [];
  let pos = notes.length ? notes[0].level : 0.4;
  let velocity = 0;
  let holding = false;
  let audio = null;
  let voice = null;
  let t0 = 0;
  let last = 0;
  let raf = 0;
  let running = false;
  let stopBacking = () => {};
  let resolvePlay;
  let streak = 0;
  // Aufnahmen: Die Band spielt, die eigene Stimme ist man selbst (die eigene Spur bleibt stumm).
  let buffers = null;
  let player = null;
  if (game.music?.backing.length) {
    const startButton = shell.el('.mg-start');
    startButton.disabled = true;
    loadStems(game.music.backing.map((s) => [s])).then((loaded) => {
      buffers = loaded;
      startButton.disabled = false;
    });
  }

  const clock = () => (audio ? audio.currentTime : performance.now() / 1000);

  function setHolding(value) {
    holding = value;
    holdButton.classList.toggle('down', value);
  }

  function step(now, dt) {
    // Weiche Beschleunigung: schnelles Tippen hält den Ton, langes Halten lässt ihn deutlich steigen.
    const target = holding ? speed : -speed;
    velocity += (target - velocity) * Math.min(1, dt * 6);
    pos += velocity * dt;
    if (pos <= 0 || pos >= 1) {
      pos = Math.max(0, Math.min(1, pos));
      velocity = 0;
    }
    trail.push({ t: now, pos });
    while (trail.length && now - trail[0].t > BEHIND) trail.shift();

    let active = null;
    for (const note of notes) {
      if (note.judged) continue;
      if (now >= note.t && now < note.t + note.d) {
        active = note;
        note.total += dt;
        if (Math.abs(pos - note.level) <= tolerance) note.inTune += dt;
      } else if (now >= note.t + note.d) {
        note.judged = judgeRatio(note.total ? note.inTune / note.total : 0);
        counts[note.judged]++;
        streak = note.judged === 'perfect' ? streak + 1 : 0;
      }
    }
    if (voice) {
      voice.setPitch(scaleMidi(game.instrument, pos * 7, game.tonality));
      voice.setLevel(active ? 1 : 0);
    }
    return active;
  }

  function render(now, active) {
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    const top = 26;
    const range = h - 52;
    const markerX = w * MARKER_X;
    const xOf = (t) => markerX + ((t - now) / AHEAD) * (w - markerX);
    const yOf = (level) => top + (1 - level) * range;
    draw.clearRect(0, 0, w, h);

    for (let d = 0; d <= 7; d++) {
      draw.fillStyle = d % 2 ? 'rgba(255,255,255,0.025)' : 'rgba(255,255,255,0.045)';
      draw.fillRect(0, yOf(d / 7) - range / 14, w, range / 7);
    }

    const band = tolerance * range;
    for (const note of notes) {
      const x1 = xOf(note.t);
      const x2 = xOf(note.t + note.d);
      if (x2 < 0) continue;
      if (x1 > w) break;
      const inTune = note === active && Math.abs(pos - note.level) <= tolerance;
      draw.globalAlpha = note.judged ? 0.3 : 1;
      draw.fillStyle = inTune ? '#3ddc97' : note === active ? '#ffb547' : '#9b7bff';
      const y = yOf(note.level);
      draw.beginPath();
      draw.roundRect(x1, y - band, Math.max(4, x2 - x1), band * 2, Math.min(band, 10));
      draw.fill();
      draw.globalAlpha = 1;
    }

    draw.strokeStyle = 'rgba(255,255,255,0.25)';
    draw.lineWidth = 2;
    draw.beginPath();
    draw.moveTo(markerX, 0);
    draw.lineTo(markerX, h);
    draw.stroke();

    if (trail.length > 1) {
      draw.strokeStyle = 'rgba(255,79,139,0.6)';
      draw.lineWidth = 3;
      draw.beginPath();
      trail.forEach((p, i) => {
        const x = xOf(p.t);
        const y = yOf(p.pos);
        if (i === 0) draw.moveTo(x, y);
        else draw.lineTo(x, y);
      });
      draw.stroke();
    }
    draw.fillStyle = '#ff4f8b';
    draw.beginPath();
    draw.arc(markerX, yOf(pos), 10, 0, Math.PI * 2);
    draw.fill();
    draw.strokeStyle = '#fff';
    draw.lineWidth = 2;
    draw.stroke();

    if (now < 0) {
      draw.fillStyle = '#fff';
      draw.font = '800 64px system-ui, sans-serif';
      draw.textAlign = 'center';
      draw.fillText(String(Math.ceil(-now / beat)), w / 2, h / 2);
    }
    draw.fillStyle = 'rgba(255,255,255,0.12)';
    draw.fillRect(0, 0, w, 4);
    draw.fillStyle = '#ff4f8b';
    draw.fillRect(0, 0, w * Math.max(0, Math.min(1, now / game.duration)), 4);
  }

  function frame() {
    const t = clock();
    const now = t - t0;
    const dt = Math.min(0.05, t - last);
    last = t;
    const active = step(now, dt);
    render(now, active);
    const total = notes.reduce((sum, n) => sum + n.total, 0);
    const inTune = notes.reduce((sum, n) => sum + n.inTune, 0);
    accEl.textContent = total > 0.3 ? percent(inTune / total) : '–';
    comboEl.textContent = streak >= 3 ? `${streak} Töne perfekt` : '';
    timeEl.textContent = duration(Math.min(game.duration, game.duration - Math.max(0, now)));
    if (now > game.duration + 0.3 && notes.every((n) => n.judged)) {
      finish({ ...counts });
      return;
    }
    raf = requestAnimationFrame(frame);
  }

  function onKeyDown(e) {
    if (!running || e.ctrlKey || e.metaKey || e.altKey) return;
    if (HOLD_KEYS.includes(e.key.toLowerCase())) {
      e.preventDefault();
      setHolding(true);
    }
  }
  function onKeyUp(e) {
    if (HOLD_KEYS.includes(e.key.toLowerCase())) setHolding(false);
  }
  const down = (e) => {
    e.preventDefault();
    if (running) setHolding(true);
  };
  const up = () => setHolding(false);

  function start() {
    shell.hideOverlay();
    shell.resize();
    audio = getAudio();
    voice = createVoice(game.instrument);
    t0 = clock() + 0.15 + 4 * beat;
    last = clock();
    stopBacking = scheduleBacking({ genre: game.genre, bpm: game.bpm, start: t0, duration: game.duration, drums: !buffers, bass: !buffers });
    if (buffers) player = playStems(buffers, t0, { volume: 0.8 });
    running = true;
    raf = requestAnimationFrame(frame);
  }

  function finish(result) {
    running = false;
    setHolding(false);
    cancelAnimationFrame(raf);
    stopBacking();
    player?.stop();
    player = null;
    voice?.stop();
    voice = null;
    resolvePlay?.(result);
  }

  window.addEventListener('keydown', onKeyDown, true);
  window.addEventListener('keyup', onKeyUp, true);
  window.addEventListener('resize', shell.resize);
  for (const el of [canvas, holdButton]) {
    el.addEventListener('pointerdown', down);
    el.addEventListener('pointerup', up);
    el.addEventListener('pointercancel', up);
    el.addEventListener('pointerleave', up);
  }
  shell.el('.mg-start').addEventListener('click', start);
  shell.el('.mg-abort').addEventListener('click', () => {
    if (!shell.resultShown()) finish({ aborted: true });
  });
  requestAnimationFrame(() => {
    shell.resize();
    render(-4 * beat, null);
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
      player?.stop();
      voice?.stop();
      window.removeEventListener('keydown', onKeyDown, true);
      window.removeEventListener('keyup', onKeyUp, true);
      window.removeEventListener('resize', shell.resize);
    },
  };
}
