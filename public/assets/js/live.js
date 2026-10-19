import { app, bandById, cat, serverNow } from './core.js';
import { duration, esc, openModal } from './ui.js';

const ICONS = { song: '🎵', mistake: '😬', highlight: '🔥', crowd: '👥' };

/** Live-Ansicht eines Auftritts: Publikumsleiste und Live-Ticker, abgespielt nach Serverzeit. */
export function openLive(activityId) {
  let timer = null;
  const modal = openModal({ wide: true, className: 'live-modal', html: '<div class="live"></div>', onClose: () => clearInterval(timer) });
  const root = modal.body.querySelector('.live');
  const activity = app.state.running.find((a) => a.id === Number(activityId));
  if (!activity) {
    modal.close();
    return;
  }
  const band = bandById(activity.bandId);
  const songSeconds = cat().rules.songSeconds;
  const total = activity.endsAt - activity.startedAt;

  root.innerHTML = `
    <div class="live-head">
      <div><div class="eyebrow">Live</div><h2>${esc(activity.title)}</h2><div class="muted">${esc(band?.name ?? '')}</div></div>
      <div class="live-clock"><span class="live-song"></span><b class="live-left"></b></div>
    </div>
    <div class="meter">
      <div class="meter-labels"><span>Buhrufe</span><span>Publikum</span><span>Ekstase</span></div>
      <div class="meter-track"><div class="meter-fill"></div><div class="meter-needle"></div></div>
    </div>
    <div class="live-progress"><div class="live-progress-fill"></div></div>
    <ol class="ticker"></ol>
    <p class="muted small live-note">Du kannst das Fenster schließen – die Band spielt weiter, und das Ergebnis kommt automatisch.</p>`;

  const fill = root.querySelector('.meter-fill');
  const needle = root.querySelector('.meter-needle');
  const ticker = root.querySelector('.ticker');
  const songEl = root.querySelector('.live-song');
  const leftEl = root.querySelector('.live-left');
  const progress = root.querySelector('.live-progress-fill');
  let shown = 0;

  function draw() {
    if (modal.closed) return;
    const elapsed = Math.max(0, Math.min(total, serverNow() - activity.startedAt));
    const events = activity.timeline.filter((e) => e.t <= elapsed);
    for (; shown < events.length; shown++) {
      const e = events[shown];
      const li = document.createElement('li');
      li.className = `tick tick-${e.kind}`;
      li.innerHTML = `<span class="tick-icon">${ICONS[e.kind] ?? '•'}</span><span>${esc(e.text)}</span>`;
      ticker.prepend(li);
    }
    const meter = events.length ? events[events.length - 1].meter : 50;
    fill.style.width = `${meter}%`;
    needle.style.left = `${meter}%`;
    fill.dataset.mood = meter >= 65 ? 'high' : meter <= 35 ? 'low' : 'mid';
    progress.style.width = `${(elapsed / total) * 100}%`;
    songEl.textContent = `Lied ${Math.min(activity.songs, Math.floor(elapsed / songSeconds) + 1)} von ${activity.songs}`;
    leftEl.textContent = elapsed >= total ? 'Vorbei!' : duration(total - elapsed);
    if (elapsed >= total) {
      clearInterval(timer);
      setTimeout(() => modal.close(), 1500);
    }
  }

  timer = setInterval(draw, 250);
  draw();
}
