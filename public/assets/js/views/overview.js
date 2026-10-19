import { face, genreName } from '../cards.js';
import { app, bandById, isBusy, musicianById, runningFor, serverNow } from '../core.js';
import { esc, fmt0, fmt1, money, timeAgo } from '../ui.js';

export function render() {
  const s = app.state;
  const fame = s.bands.reduce((sum, b) => sum + b.fame, 0);
  const steps = [
    { done: s.musicians.length > 0, text: 'Starter-Pack öffnen' },
    { done: s.bands.length > 0, text: 'Erste Band gründen', href: '#/bands' },
    { done: s.bands.some((b) => b.released), text: 'Erster Auftritt: Straßenmusik oder Open Stage', href: '#/bands' },
    { done: s.bands.some((b) => b.records > 0), text: 'Erste Platte produzieren – erst dann gibt es richtige Gigs', href: '#/bands' },
    { done: s.bands.some((b) => b.gigsPlayed > 0), text: 'Ersten richtigen Gig spielen', href: '#/bands' },
  ];
  const nextStep = steps.find((step) => !step.done);

  return `
    <section class="hero">
      <div>
        <div class="eyebrow">Dein Label</div>
        <h1>${esc(s.label.name)}</h1>
      </div>
      <div class="stat-tiles">
        ${tile('Geld', money(s.label.money))}
        ${tile('Musiker', fmt0(s.musicians.length))}
        ${tile('Bands', fmt0(s.bands.length))}
        ${tile('Label-Ruhm', fmt1(fame))}
      </div>
    </section>

    ${nextStep ? `
      <section class="panel">
        <h2>Erste Schritte</h2>
        <ol class="steps">${steps.map((step) => `
          <li class="${step.done ? 'done' : step === nextStep ? 'next' : ''}">
            ${step.href && step === nextStep ? `<a href="${step.href}">${esc(step.text)}</a>` : esc(step.text)}
          </li>`).join('')}
        </ol>
      </section>` : ''}

    ${s.running.length ? `
      <section class="panel">
        <h2>Gerade unterwegs</h2>
        <div class="running-list">${s.running.map(runningItem).join('')}</div>
      </section>` : ''}

    <section>
      <div class="section-head"><h2>Deine Bands</h2><a class="btn ghost small" href="#/bands">Alle Bands</a></div>
      ${s.bands.length ? `<div class="band-grid">${s.bands.map(bandTile).join('')}</div>` : `
        <div class="empty">Noch keine Band. <a href="#/bands">Gründe jetzt deine erste Band</a> – mindestens zwei Musiker.</div>`}
    </section>

    <section class="panel">
      <h2>Chronik</h2>
      ${s.history.length ? `<ul class="history">${s.history.slice(0, 15).map((h) => `
        <li><span>${esc(h.text)}</span><time>${timeAgo(h.createdAt, serverNow())}</time></li>`).join('')}</ul>` : '<p class="muted">Noch nichts passiert.</p>'}
    </section>`;
}

function tile(label, value) {
  return `<div class="tile"><span>${esc(label)}</span><b>${value}</b></div>`;
}

export function runningItem(a) {
  const who = a.bandId ? bandById(a.bandId)?.name : musicianById(a.musicianId)?.firstName;
  const canWatch = a.type !== 'promo';
  return `
    <div class="running">
      <span class="pulse"></span>
      <div class="running-text"><b>${esc(a.title)}</b><span class="muted">${esc(who ?? '')}</span></div>
      <span class="countdown" data-until="${a.endsAt}"></span>
      ${canWatch ? `<button class="btn small" data-act="watch" data-id="${a.id}">Zuschauen</button>` : ''}
    </div>`;
}

export function bandTile(b) {
  const members = b.memberIds.map(musicianById).filter(Boolean);
  const running = runningFor(b.id);
  return `
    <a class="band-tile" href="#/band/${b.id}">
      <div class="band-tile-head">
        <b>${esc(b.name)}</b>
        <span class="chip genre genre-${b.genre}">${esc(genreName(b.genre))}</span>
      </div>
      <div class="faces">${members.map((m) => face(m, 'sm')).join('')}</div>
      <div class="band-tile-stats">
        <span title="Sound (Kritikerwertung)">🎚️ ${fmt1(b.sound)}</span>
        <span title="Ruhm">⭐ ${fmt1(b.fame)}</span>
        <span title="Platten">💿 ${fmt0(b.records)}</span>
      </div>
      <div class="band-tile-status">${running ? `<span class="pulse"></span> ${esc(running.title)}` : isBusy(b.busyUntil) ? 'Im Studio' : '<span class="muted">bereit</span>'}</div>
    </a>`;
}
