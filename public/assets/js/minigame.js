import { getAudio, playLane, playMiss, scheduleBacking } from './audio.js';
import { instrumentIcon } from './cards.js';
import { COLORS, mountShell } from './mg-shell.js';
import { createMixerGame } from './mixer.js';
import { createPitchGame } from './pitch.js';
import { duration, esc, percent } from './ui.js';

const KEYS = { d: 0, f: 1, j: 2, k: 3, arrowleft: 0, arrowdown: 1, arrowup: 2, arrowright: 3, 1: 0, 2: 1, 3: 2, 4: 3 };
const KEY_LABELS = ['D', 'F', 'J', 'K'];
/** Sekunden, die eine Note von oben bis zur Linie fällt. */
const TRAVEL = 1.8;

/**
 * Startet das passende Minispiel: Rhythmus (Tasten im Takt), Tonhöhe (Gesang, Geige) oder Mischpult (Studio).
 * Alle liefern über play() {perfect, good, miss} oder {aborted: true}.
 */
export function createMinigame(container, game) {
  if (game.mode === 'pitch') return createPitchGame(container, game);
  if (game.mode === 'mixer') return createMixerGame(container, game);
  return createRhythmGame(container, game);
}

function createRhythmGame(container, game) {
  const shell = mountShell(container, game, {
    hud: '<span>Treffer <b class="mg-acc">–</b></span><span class="mg-combo"></span><span>Zeit <b class="mg-time"></b></span>',
    hint: 'Drück <b>D F J K</b> (oder tipp auf die Felder), wenn eine Note die Linie erreicht.',
    below: `<div class="mg-keys">${KEY_LABELS.map((k, i) => `
      <button class="mg-key" data-lane="${i}" style="--lane:${COLORS[i]}">
        <b>${k}</b><small>${instrumentIcon(game.lanes[i])} ${esc(game.laneNames[i])}</small>
      </button>`).join('')}</div>`,
  });
  const { canvas, draw } = shell;
  const accEl = shell.el('.mg-acc');
  const comboEl = shell.el('.mg-combo');
  const timeEl = shell.el('.mg-time');
  const keyButtons = [...container.querySelectorAll('.mg-key')];
  timeEl.textContent = duration(game.duration);

  const notes = game.notes.map((n) => ({ t: n.t, l: n.l, judged: null }));
  const perfectWin = game.windows.perfect / 1000;
  const goodWin = game.windows.good / 1000;
  const beat = 60 / game.bpm;
  const counts = { perfect: 0, good: 0, miss: 0 };
  const pressed = [0, 0, 0, 0];
  const popups = [];
  let combo = 0;
  let audio = null;
  let t0 = 0;
  let raf = 0;
  let running = false;
  let stopBacking = () => {};
  let resolvePlay;

  const clock = () => (audio ? audio.currentTime : performance.now() / 1000);
  const songTime = () => clock() - t0;

  function judge(lane) {
    if (!running) return;
    const now = songTime();
    pressed[lane] = clock();
    keyButtons[lane].classList.add('down');
    setTimeout(() => keyButtons[lane].classList.remove('down'), 90);
    let best = null;
    let bestDiff = Infinity;
    for (const note of notes) {
      if (note.t - now > goodWin) break;
      if (note.judged || note.l !== lane) continue;
      const diff = Math.abs(note.t - now);
      if (diff < bestDiff) {
        best = note;
        bestDiff = diff;
      }
    }
    if (best && bestDiff <= goodWin) {
      best.judged = bestDiff <= perfectWin ? 'perfect' : 'good';
      counts[best.judged]++;
      combo++;
      popups.push({ lane, text: best.judged === 'perfect' ? 'Perfekt!' : 'Gut', color: COLORS[lane], at: clock() });
      playLane(game.lanes[lane], lane, now, game.bpm);
    }
  }

  function markMisses(now) {
    for (const note of notes) {
      if (note.t > now) break;
      if (!note.judged && now - note.t > goodWin) {
        note.judged = 'miss';
        counts.miss++;
        combo = 0;
        popups.push({ lane: note.l, text: 'Daneben', color: '#8b839f', at: clock() });
        playMiss();
      }
    }
  }

  function roundRect(x, y, w, h, r) {
    draw.beginPath();
    draw.moveTo(x + r, y);
    draw.arcTo(x + w, y, x + w, y + h, r);
    draw.arcTo(x + w, y + h, x, y + h, r);
    draw.arcTo(x, y + h, x, y, r);
    draw.arcTo(x, y, x + w, y, r);
    draw.fill();
  }

  function render(now) {
    const w = canvas.clientWidth;
    const h = canvas.clientHeight;
    const laneW = w / 4;
    const hitY = h - 46;
    draw.clearRect(0, 0, w, h);

    for (let l = 0; l < 4; l++) {
      draw.fillStyle = l % 2 ? 'rgba(255,255,255,0.025)' : 'rgba(255,255,255,0.05)';
      draw.fillRect(l * laneW, 0, laneW, h);
      const glow = Math.max(0, 1 - (clock() - pressed[l]) / 0.18);
      if (glow > 0) {
        const gradient = draw.createLinearGradient(0, hitY - 120, 0, hitY);
        gradient.addColorStop(0, 'rgba(0,0,0,0)');
        gradient.addColorStop(1, `${COLORS[l]}55`);
        draw.globalAlpha = glow;
        draw.fillStyle = gradient;
        draw.fillRect(l * laneW, hitY - 120, laneW, 120);
        draw.globalAlpha = 1;
      }
      draw.strokeStyle = COLORS[l];
      draw.lineWidth = 2;
      draw.beginPath();
      draw.arc(l * laneW + laneW / 2, hitY, Math.min(24, laneW * 0.3), 0, Math.PI * 2);
      draw.stroke();
    }
    draw.fillStyle = 'rgba(255,255,255,0.35)';
    draw.fillRect(0, hitY - 1, w, 2);

    for (const note of notes) {
      if (note.judged === 'perfect' || note.judged === 'good') continue;
      const y = hitY - ((note.t - now) / TRAVEL) * (hitY + 20);
      if (y < -20) break;
      if (y > h + 20) continue;
      const x = note.l * laneW + laneW / 2;
      const nw = Math.min(64, laneW * 0.62);
      draw.globalAlpha = note.judged === 'miss' ? 0.25 : 1;
      draw.fillStyle = COLORS[note.l];
      roundRect(x - nw / 2, y - 9, nw, 18, 9);
      draw.globalAlpha = 1;
    }

    const at = clock();
    for (let i = popups.length - 1; i >= 0; i--) {
      const p = popups[i];
      const age = at - p.at;
      if (age > 0.5) {
        popups.splice(i, 1);
        continue;
      }
      draw.globalAlpha = 1 - age / 0.5;
      draw.fillStyle = p.color;
      draw.font = '700 16px system-ui, sans-serif';
      draw.textAlign = 'center';
      draw.fillText(p.text, p.lane * laneW + laneW / 2, hitY - 36 - age * 40);
      draw.globalAlpha = 1;
    }

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
    const now = songTime();
    markMisses(now);
    render(now);
    const judged = counts.perfect + counts.good + counts.miss;
    accEl.textContent = judged ? percent((counts.perfect + 0.6 * counts.good) / judged) : '–';
    comboEl.textContent = combo >= 5 ? `${combo}er Combo` : '';
    timeEl.textContent = duration(Math.min(game.duration, game.duration - Math.max(0, now)));
    if (now > game.duration + 0.4 && notes.every((n) => n.judged)) {
      finish({ ...counts });
      return;
    }
    raf = requestAnimationFrame(frame);
  }

  function onKey(e) {
    if (!running || e.repeat || e.ctrlKey || e.metaKey || e.altKey) return;
    const lane = KEYS[e.key.toLowerCase()];
    if (lane === undefined) return;
    e.preventDefault();
    judge(lane);
  }

  function onPointer(e) {
    const rect = canvas.getBoundingClientRect();
    judge(Math.max(0, Math.min(3, Math.floor(((e.clientX - rect.left) / rect.width) * 4))));
  }

  function start() {
    shell.hideOverlay();
    shell.resize();
    audio = getAudio();
    t0 = clock() + 0.15 + 4 * beat;
    stopBacking = scheduleBacking({
      genre: game.genre,
      bpm: game.bpm,
      start: t0,
      duration: game.duration,
      drums: !game.lanes.every((l) => l === 'drums'),
      bass: !game.lanes.every((l) => l === 'bass'),
    });
    running = true;
    raf = requestAnimationFrame(frame);
  }

  function finish(result) {
    running = false;
    cancelAnimationFrame(raf);
    stopBacking();
    resolvePlay?.(result);
  }

  window.addEventListener('keydown', onKey, true);
  window.addEventListener('resize', shell.resize);
  canvas.addEventListener('pointerdown', onPointer);
  keyButtons.forEach((button, lane) => button.addEventListener('pointerdown', (e) => {
    e.preventDefault();
    judge(lane);
  }));
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
      window.removeEventListener('keydown', onKey, true);
      window.removeEventListener('resize', shell.resize);
    },
  };
}
