import { bandGenreTags, face, photo } from '../cards.js';
import { app, bandById, cat, isBusy, liveMoney, musicianById, runningFor, serverNow } from '../core.js';
import { esc, fmt0, fmt1, fmtGain, money, timeAgo } from '../ui.js';

export function render() {
  const s = app.state;
  const fame = s.bands.reduce((sum, b) => sum + b.fame, 0);
  const steps = [
    { done: s.musicians.length > 0, text: 'Posteingang: Kassetten anhören und den ersten Musiker unterschreiben', href: '#/einsendungen' },
    { done: (s.scouts ?? []).length > 0, text: 'Den Scout einstellen (Bewerbung im Posteingang) und losschicken', href: '#/einsendungen' },
    { done: s.bands.length > 0, text: 'Band gründen', act: 'newBand' },
    { done: s.gear.some((g) => g.musicianId), text: 'Instrumente besorgen: ohne eigenes Instrument klingt es nach wenig', href: '#/store' },
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
        ${tile('Bands', fmt0(s.bands.length), 'Solokünstler zählen mit')}
        ${tile('Label-Ruhm', fmt1(fame),
          s.finance.famePerHour ? `aus den Charts <em>+${fmtGain(s.finance.famePerHour)}</em> pro Std.` : 'Summe des Ruhms aller Bands')}
      </div>
    </section>

    ${assistantNotes(s)}
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

    ${s.bands.length ? '' : '<section><div class="empty">Noch keine Band.</div></section>'}

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

/** Notizen der Assistenz: was gerade wichtig ist. Ohne Assistenz bleibt die Übersicht schlicht. */
function assistantNotes(s) {
  const a = s.assistant;
  if (!a) return '';
  const left = (t) => {
    const sec = t - serverNow();
    return sec >= 86400 ? `noch ${Math.ceil(sec / 86400)} Tage` : `noch ${Math.max(1, Math.ceil(sec / 3600))} Std.`;
  };
  const go = (key, text) => `<button class="link" data-act="inboxGo" data-key="${key}">${text}</button>`;
  const notes = [];
  for (const m of (s.mail ?? []).filter((x) => x.kind === 'interview').sort((x, y) => x.expiresAt - y.expiresAt)) {
    const who = musicianById(m.musicianId);
    if (who) notes.push(go('interview', `Interview-Anfrage für <b>${esc(who.firstName)}</b> (${esc(m.medium)}, ${left(m.expiresAt)})`));
  }
  for (const m of (s.mail ?? []).filter((x) => x.kind === 'job')) {
    notes.push(go('job', `Bewerbung: <b>${esc(m.firstName)} ${esc(m.lastName)}</b> als ${esc(cat().staffRoles[m.role]?.tiers[m.tier] ?? '')} (${left(m.expiresAt)})`));
  }
  for (const m of (s.mail ?? []).filter((x) => x.kind === 'report' && x.cards.some((c) => !c.signed))) {
    notes.push(go('demos', `<b>${esc(m.scoutName)}</b> ist zurück und stellt dir ${m.cards.length} Talente vor (${left(m.expiresAt)})`));
  }
  for (const x of (s.scouts ?? []).filter((y) => y.away)) {
    notes.push(`<b>${esc(x.firstName)}</b> ist als Scout unterwegs, zurück in <span data-until="${x.away.until}"></span>`);
  }
  for (const t of s.tutors ?? []) {
    const who = t.role === 'teacher' ? musicianById(t.musicianId) : bandById(t.bandId);
    if (who) notes.push(t.role === 'teacher' ? `Heute Unterricht: <b>${esc(who.firstName)}</b> lernt bei ${esc(t.firstName)} (noch <span data-until="${t.until}"></span>)` : `<b>${esc(who.name)}</b> schreibt mit ${esc(t.firstName)} Songs (noch <span data-until="${t.until}"></span>)`);
  }
  const good = (s.submissions ?? []).filter((x) => x.state === 'open' && !x.saved && Math.max(...(x.members ?? [x]).map((m) => m.level ?? 0)) >= 1);
  notes.push(`Heute ${a.today.opened} Demos angehört, ${a.today.dropped} weggeworfen.${good.length ? ` ${go('demos', `<b>${good.length}</b> ${good.length === 1 ? 'gute wartet' : 'gute warten'} auf dich`)}.` : ''}`);
  for (const c of s.courses ?? []) {
    const open = c.candidates.filter((x) => !x.visited).map((x) => s.musicians.find((m) => m.id === x.musicianId)).filter(Boolean);
    if (open.length) notes.push(`Heute im Kurs: <b>${esc(c.prefix)}-${esc(c.name)}</b> mit ${esc(c.teacher)}. Hin können: ${open.map((m) => `<a href="#/band/${m.bandId}">${esc(m.firstName)}</a>`).join(', ')}.`);
  }
  const names = a.staff.map((x) => esc(x.firstName)).join(' und ');
  return `<section class="panel lb-notes">
    <h2><span class="lb-notes-faces">${a.staff.slice(0, 3).map((x) => `<span class="lb-ph sm">${photo({ id: x.id, look: x.look })}</span>`).join('')}</span> Notizen von ${names}</h2>
    <ul>${notes.map((n) => `<li>${n}</li>`).join('')}</ul>
  </section>`;
}
