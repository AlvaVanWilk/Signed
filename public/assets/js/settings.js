import { getAudio, scheduleMetronome, setLatency, storedLatency } from './audio.js';
import { defineActions } from './core.js';
import { esc, fmt0, openModal } from './ui.js';

/**
 * Kopfhörer einstellen: Man tippt im Takt zu Klicks. Wie viel später man tippt, ist die Verzögerung von
 * Bluetooth-Kopfhörern (samt Bildschirm). Die Minispiele rechnen sie danach heraus. Gilt pro Gerät.
 */
const BEAT = 1.0;
const CLICKS = 12;
const SKIP = 3;
const MAX_MS = 600;

export function openCalibration() {
  let taps = [];
  let t0 = 0;
  let stop = () => {};
  let timer = 0;
  let measured = null;
  const modal = openModal({
    className: 'calibrate-modal',
    html: `
      <h2>Kopfhörer einstellen</h2>
      <p>Setz deine Kopfhörer auf. Tippe auf den großen Knopf, <b>immer genau wenn du den Klick hörst</b>.</p>
      <p class="muted small">Jetzt: ${current()}</p>
      <button class="btn tap-button" data-tap disabled>Tippen</button>
      <p class="calibrate-status muted"></p>
      <div class="dialog-actions">
        <button class="btn ghost small" data-reset>Automatisch</button>
        <button class="btn" data-run>Start</button>
        <button class="btn primary" data-save hidden>Übernehmen</button>
      </div>`,
    onClose: () => {
      stop();
      clearTimeout(timer);
      window.removeEventListener('keydown', onKey, true);
    },
  });
  const el = (s) => modal.body.querySelector(s);
  const status = el('.calibrate-status');

  function tap() {
    const ctx = getAudio();
    if (!ctx || !t0) return;
    taps.push(ctx.currentTime);
    const button = el('[data-tap]');
    button.classList.add('down');
    setTimeout(() => button.classList.remove('down'), 90);
  }
  function onKey(e) {
    if (e.key === ' ' || e.key === 'Enter') {
      e.preventDefault();
      tap();
    }
  }
  window.addEventListener('keydown', onKey, true);

  function run() {
    const ctx = getAudio();
    if (!ctx) return;
    taps = [];
    measured = null;
    t0 = ctx.currentTime + 0.6;
    stop();
    stop = scheduleMetronome({ bpm: 60 / BEAT, start: t0, duration: CLICKS * BEAT, countIn: 0 });
    el('[data-tap]').disabled = false;
    el('[data-save]').hidden = true;
    status.textContent = 'Hör hin und tippe mit …';
    clearTimeout(timer);
    timer = setTimeout(finish, (CLICKS * BEAT + 1.2) * 1000);
  }

  function finish() {
    el('[data-tap]').disabled = true;
    // Jeder Tipp gehört zum Klick davor (bis 0,8 s später); die ersten zählen nicht, man findet erst den Takt.
    const offsets = taps
      .map((t) => {
        const k = Math.round((t - t0 - 0.3) / BEAT);
        return k >= SKIP && k < CLICKS ? (t - (t0 + k * BEAT)) * 1000 : null;
      })
      .filter((ms) => ms !== null)
      .sort((a, b) => a - b);
    if (offsets.length < 5) {
      status.textContent = 'Da waren zu wenige Tipper. Noch mal?';
      return;
    }
    const median = offsets[Math.floor(offsets.length / 2)];
    const spread = offsets[Math.floor(offsets.length * 0.8)] - offsets[Math.floor(offsets.length * 0.2)];
    measured = Math.max(0, Math.min(MAX_MS, median));
    status.innerHTML = spread > 120
      ? `Ungefähr <b>${fmt0(measured)} ms</b>, aber recht ungleichmäßig getippt. Lieber noch mal?`
      : `Deine Verzögerung: <b>${fmt0(measured)} ms</b>.`;
    el('[data-save]').hidden = false;
  }

  modal.body.addEventListener('pointerdown', (e) => {
    if (e.target.closest('[data-tap]')) {
      e.preventDefault();
      tap();
    }
  });
  modal.body.addEventListener('click', (e) => {
    if (e.target.closest('[data-run]')) run();
    if (e.target.closest('[data-save]') && measured !== null) {
      setLatency(measured);
      status.innerHTML = `Gespeichert: <b>${fmt0(measured)} ms</b>. Gilt für dieses Gerät.`;
      el('[data-save]').hidden = true;
    }
    if (e.target.closest('[data-reset]')) {
      setLatency(null);
      status.textContent = 'Zurück auf automatisch.';
    }
  });
}

function current() {
  const ms = storedLatency();
  return ms === null ? 'automatisch' : `${esc(fmt0(ms))} ms (eingestellt)`;
}

defineActions({
  calibrate() {
    openCalibration();
  },
});
