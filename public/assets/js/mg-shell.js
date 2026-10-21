import { getAudio } from './audio.js';
import { esc } from './ui.js';

/**
 * Gemeinsamer Rahmen aller Minispiele: Kopf mit Abbrechen, Anzeige, Spielfläche mit Startknopf,
 * Bereich unter der Spielfläche und Ergebnis.
 */
export function mountShell(container, game, { hud, hint, below = '' }) {
  container.innerHTML = `
    <div class="mg mg-${esc(game.mode)}">
      <div class="mg-head">
        <div><h2>${esc(game.title)}</h2><div class="muted">${esc(game.subtitle)}</div></div>
        <button class="btn ghost small mg-abort">Abbrechen</button>
      </div>
      <div class="mg-hud">${hud}</div>
      <div class="mg-stage">
        <canvas class="mg-canvas"></canvas>
        <div class="mg-stalled" hidden>Es geht nicht los? <b>Tipp einmal aufs Spielfeld.</b></div>
        <div class="mg-overlay">
          <p>${hint}</p>
          <button class="btn primary big mg-start">Los geht’s</button>
          <button type="button" class="link small mg-calibrate" data-act="calibrate">Ton kommt zu spät? Kopfhörer einstellen</button>
        </div>
      </div>
      ${below}
      <div class="mg-result" hidden></div>
    </div>`;
  const el = (selector) => container.querySelector(selector);
  const canvas = el('.mg-canvas');
  const draw = canvas.getContext('2d');
  const resultEl = el('.mg-result');

  // Langes Drücken (Halten-Knopf, Tasten, Spielfeld) soll auf dem iPad kein Menü, keine Lupe und keine Textauswahl öffnen.
  const block = (e) => e.preventDefault();
  container.addEventListener('contextmenu', block);
  container.addEventListener('selectstart', block);
  container.addEventListener('touchstart', (e) => {
    if (e.target.closest('.mg-hold, .mg-key, .mg-stage') && !e.target.closest('.mg-overlay')) e.preventDefault();
  }, { passive: false });

  // Steht die Audio-Uhr still (iPad), läuft das Spiel nicht los: Hinweis zeigen, bis sie wieder tickt.
  let watchdog = 0;
  function watchClock() {
    const audio = getAudio();
    if (!audio) return;
    let lastTime = audio.currentTime;
    let still = 0;
    clearInterval(watchdog);
    watchdog = setInterval(() => {
      if (!container.isConnected) return clearInterval(watchdog);
      still = audio.currentTime === lastTime ? still + 1 : 0;
      lastTime = audio.currentTime;
      el('.mg-stalled').hidden = still < 3;
    }, 500);
  }

  return {
    el,
    canvas,
    draw,
    resize() {
      const dpr = window.devicePixelRatio || 1;
      canvas.width = canvas.clientWidth * dpr;
      canvas.height = canvas.clientHeight * dpr;
      draw.setTransform(dpr, 0, 0, dpr, 0, 0);
    },
    hideOverlay() {
      el('.mg-overlay').hidden = true;
      watchClock();
    },
    resultShown: () => !resultEl.hidden,
    /** buttons: [{label, cls, onClick}] */
    showResult(html, buttons = []) {
      clearInterval(watchdog);
      el('.mg-stalled').hidden = true;
      el('.mg-abort').hidden = true;
      resultEl.hidden = false;
      resultEl.innerHTML = `${html}<div class="dialog-actions"></div>`;
      const actions = resultEl.querySelector('.dialog-actions');
      for (const b of buttons) {
        const button = document.createElement('button');
        button.className = `btn ${b.cls || 'ghost'}`;
        button.textContent = b.label;
        button.addEventListener('click', b.onClick);
        actions.append(button);
      }
      resultEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    },
  };
}

/** Bewertet einen Abschnitt nach dem Anteil der Zeit, in der man richtig lag. */
export function judgeRatio(ratio) {
  if (ratio >= 0.7) return 'perfect';
  if (ratio >= 0.4) return 'good';
  return 'miss';
}

export const COLORS = ['#ff4f8b', '#ffb547', '#4fd1ff', '#9b7bff', '#3ddc97', '#ff8a5c'];
