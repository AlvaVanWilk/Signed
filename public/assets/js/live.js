import { app, bandById, cat, musicianById, serverNow } from './core.js';
import { duration, esc, openModal } from './ui.js';
import { getAudio } from './audio.js';
import { loadStems, memberStem, playStems } from './music.js';

const ICONS = { song: '🎵', mistake: '😬', highlight: '🔥', crowd: '👥' };

/** Live-Ansicht eines Auftritts: Publikumsleiste und Live-Ticker, abgespielt nach Serverzeit. */
export function openLive(activityId) {
  let timer = null;
  let player = null;
  const stopMusic = () => {
    player?.stop();
    player = null;
  };
  const modal = openModal({ wide: true, className: 'live-modal', html: '<div class="live"></div>', onClose: () => {
    clearInterval(timer);
    stopMusic();
  } });
  const root = modal.body.querySelector('.live');
  const activity = app.state.running.find((a) => a.id === Number(activityId));
  if (!activity) {
    modal.close();
    return;
  }
  const band = bandById(activity.bandId);
  const solo = band ? null : musicianById(activity.musicianId);
  const who = band ? band.name : solo ? `${solo.firstName} ${solo.lastName}` : '';
  const songSeconds = cat().rules.songSeconds;
  const total = activity.endsAt - activity.startedAt;
  const players = band ? band.memberIds.map(musicianById).filter(Boolean) : solo ? [solo] : [];
  const groups = players.map((m) => [memberStem(m, band, activity.id)]);

  // Die Aufnahmen laufen in Schleife mit, passend zur Serverzeit: Wer später zuschaut, hört mitten im Lied.
  loadStems(groups).then((buffers) => {
    const elapsed = serverNow() - activity.startedAt;
    if (!buffers || modal.closed || elapsed >= total) return;
    const ctx = getAudio();
    player = playStems(buffers, ctx.currentTime + 0.05, { offset: elapsed % songSeconds, loop: true, volume: 0.8 });
  });

  // Bei der Probe hört niemand zu: kein Publikum, keine Leiste.
  const crowd = activity.type !== 'rehearsal';
  root.innerHTML = `
    <div class="live-head">
      <div><div class="eyebrow">${crowd ? 'Live' : 'Probenraum'}</div><h2>${esc(activity.title)}</h2><div class="muted">${esc(who)}</div></div>
      <div class="live-clock"><span class="live-song"></span><b class="live-left"></b></div>
    </div>
    ${crowd ? `<div class="meter">
      <div class="meter-labels"><span>Buhrufe</span><span>Publikum</span><span>Ekstase</span></div>
      <div class="meter-track"><div class="meter-fill"></div><div class="meter-needle"></div></div>
    </div>` : ''}
    <div class="live-progress"><div class="live-progress-fill"></div></div>
    <ol class="ticker"></ol>
    `;

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
      // Stimmungs-Ereignisse bewegen nur die Leiste.
      if (!e.text) continue;
      const li = document.createElement('li');
      li.className = `tick tick-${e.kind}`;
      li.innerHTML = `<span class="tick-icon">${ICONS[e.kind] ?? '•'}</span><span>${esc(e.text)}</span>`;
      ticker.prepend(li);
    }
    target = meterAt(elapsed);
    progress.style.width = `${(elapsed / total) * 100}%`;
    songEl.textContent = `Lied ${Math.min(activity.songs, Math.floor(elapsed / songSeconds) + 1)} von ${activity.songs}`;
    leftEl.textContent = elapsed >= total ? 'Vorbei!' : duration(total - elapsed);
    if (elapsed >= total) {
      clearInterval(timer);
      stopMusic();
      setTimeout(() => modal.close(), 1500);
    }
  }

  /**
   * Stand der Leiste zur Zeit t: Patzer und Glanzmomente springen sofort,
   * die allgemeine Stimmung verändert sich fließend bis zum nächsten Stimmungspunkt.
   */
  function meterAt(t) {
    const line = activity.timeline;
    let i = -1;
    while (i + 1 < line.length && line[i + 1].t <= t) i++;
    const prev = i >= 0 ? { t: line[i].t, meter: line[i].meter } : { t: 0, meter: line[0]?.meter ?? 0 };
    const next = line[i + 1];
    if (!next || (next.kind !== 'mood' && next.kind !== 'crowd') || next.t <= prev.t) return prev.meter;
    return prev.meter + (next.meter - prev.meter) * ((t - prev.t) / (next.t - prev.t));
  }

  // Die Leiste gleitet zum Wert des Servers und wackelt dabei ständig: Das Publikum ist immer in Bewegung.
  let target = 0;
  let base = 0;
  let last = performance.now();
  const seed = Math.random() * 100;
  function animate(now) {
    if (modal.closed) return;
    const dt = Math.min(0.1, (now - last) / 1000);
    last = now;
    base += (target - base) * (1 - Math.exp(-dt * 2.2));
    const t = now / 1000 + seed;
    // Je aufgeheizter die Stimmung, desto stärker schwankt sie.
    const amp = 1.5 + 3 * (base / 100);
    const wobble = amp * (Math.sin(t * 1.3) * 0.5 + Math.sin(t * 3.7 + 1.1) * 0.3 + Math.sin(t * 0.45 + 2.3) * 0.6);
    const value = Math.max(0, Math.min(100, base + wobble));
    fill.style.width = `${value}%`;
    needle.style.left = `${value}%`;
    fill.dataset.mood = base >= 65 ? 'high' : base <= 35 ? 'low' : 'mid';
    requestAnimationFrame(animate);
  }

  timer = setInterval(draw, 250);
  draw();
  if (crowd) requestAnimationFrame(animate);
}
