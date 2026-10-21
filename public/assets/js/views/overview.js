import { bandGenreTags, face } from '../cards.js';
import { app, bandById, isBusy, liveMoney, musicianById, runningFor, serverNow } from '../core.js';
import { esc, fmt0, fmt1, fmtGain, money, timeAgo } from '../ui.js';

export function render() {
  const s = app.state;
  const fame = s.bands.reduce((sum, b) => sum + b.fame, 0);
  const slots = s.label.bandSlots;
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
        ${tile('Geld', `<span data-live-money>${money(liveMoney())}</span>`,
          s.finance.moneyPerHour ? `Tantiemen <em>+${fmtGain(s.finance.moneyPerHour / 60)} ₲</em> pro Minute` : 'Tantiemen gibt es, sobald eine Platte chartet')}
        ${tile('Musiker', fmt0(s.musicians.length), `davon <em>${fmt0(s.musicians.filter((m) => !m.bandId).length)}</em> ohne Band`)}
        ${tile('Bands', `${fmt0(s.bands.length)}<small> / ${fmt0(slots.open)}</small>`,
          slots.next === null ? 'Alle Band-Plätze freigeschaltet' : `Nächster Band-Platz ab <em>${fmt0(slots.next)}</em> Label-Ruhm`)}
        ${tile('Label-Ruhm', fmt1(fame),
          s.finance.famePerHour ? `aus den Charts <em>+${fmtGain(s.finance.famePerHour)}</em> pro Std.` : 'Summe des Ruhms aller Bands')}
      </div>
    </section>

    ${courseNotice(s)}
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

/** Kachel; note erklärt, was der Wert bewirkt (darf <em> für den veränderlichen Teil enthalten). */
function tile(label, value, note = '') {
  return `<div class="tile"><span>${esc(label)}</span><b>${value}</b>${note ? `<small>${note}</small>` : ''}</div>`;
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
        ${bandGenreTags(b)}
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

/** Startseite: Kurs des Tages, und wer aus dem Label hin kann. */
function courseNotice(s) {
  const c = s.course;
  if (!c) return '';
  const open = c.candidates.filter((x) => !x.visited).map((x) => s.musicians.find((m) => m.id === x.musicianId)).filter(Boolean);
  return `<div class="notice course-notice"><span>🎓 Heute: <b>${esc(c.prefix)}-${esc(c.name)}</b> mit ${esc(c.teacher)} (+${fmt1(c.gain)} Können) – für Anfänger, Fortgeschrittene und Profis.
    ${open.length ? `Hin können: ${open.map((m) => `<a href="#/musician/${m.id}/uebung">${esc(m.firstName)}</a>`).join(', ')}.` : 'Heute ist niemand von dir dabei.'}</span></div>`;
}
