import { face, genreName } from '../cards.js';
import { app, bandById, isBusy, musicianById, runningFor, serverNow } from '../core.js';
import { esc, fmt0, fmt1, money, timeAgo } from '../ui.js';

export function render() {
  const s = app.state;
  const fame = s.bands.reduce((sum, b) => sum + b.fame, 0);
  const firstFavorite = s.musicians.find((m) => m.favorite && !m.bandId);
  const choices = s.musicians.filter((m) => m.abilityChoice);
  const steps = [
    { done: s.musicians.length > 0, text: 'Talent-Scout: ersten Musiker unter Vertrag nehmen', href: '#/packs' },
    { done: s.musicians.length > 1, text: 'Mitmusiker finden: Jam-Café oder Schwarzes Brett', href: firstFavorite ? `#/musician/${firstFavorite.id}/jam` : '#/packs' },
    { done: s.bands.length > 0, text: 'Band gründen', act: 'newBand' },
    { done: s.gear.some((g) => g.musicianId), text: 'Instrumente besorgen: ohne eigenes Instrument klingt es nach wenig', href: '#/packs' },
    { done: s.bands.some((b) => b.released), text: 'Erster Auftritt' },
    { done: s.bands.some((b) => b.records > 0), text: 'Erste Platte aufnehmen' },
    { done: s.bands.some((b) => b.gigsPlayed > 0), text: 'Erster richtiger Gig' },
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

    ${choices.map((m) => `<div class="notice choice"><span>⭐ ${esc(m.firstName)} ${esc(m.lastName)} darf eine dritte Fähigkeit wählen.</span><button class="btn primary small" data-act="chooseAbility" data-id="${m.id}">Wählen</button></div>`).join('')}
    ${nextStep ? `
      <section class="panel">
        <h2>Erste Schritte</h2>
        <ol class="steps">${steps.map((step) => `
          <li class="${step.done ? 'done' : step === nextStep ? 'next' : ''}">
            ${step === nextStep && step.href ? `<a href="${step.href}">${esc(step.text)}</a>` : step === nextStep && step.act ? `<button class="link" data-act="${step.act}">${esc(step.text)}</button>` : esc(step.text)}
          </li>`).join('')}
        </ol>
      </section>` : ''}

    ${s.running.length ? `
      <section class="panel">
        <h2>Gerade unterwegs</h2>
        <div class="running-list">${s.running.map(runningItem).join('')}</div>
      </section>` : ''}

    <section>
      <div class="section-head"><h2>Deine Bands</h2></div>
      ${s.bands.length ? `<div class="band-grid">${s.bands.map(bandTile).join('')}</div>` : '<div class="empty">Noch keine Band.</div>'}
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
        <span title="Sound">🎚️ ${fmt1(b.sound)}</span>
        <span title="Ruhm">⭐ ${fmt1(b.fame)}</span>
        <span title="Platten">💿 ${fmt0(b.records)}</span>
      </div>
      <div class="band-tile-status">${running ? `<span class="pulse"></span> ${esc(running.title)}` : isBusy(b.busyUntil) ? 'Im Studio' : '<span class="muted">bereit</span>'}</div>
    </a>`;
}
