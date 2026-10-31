import { instrumentIcon, instrumentName, photo } from '../cards.js';
import { app, bandById, cat, defineActions, isBusy, liveMoney, managerById, musicianById, mutate, serverNow } from '../core.js';
import { coverHtml } from '../play.js';
import { INTERN_JOBS } from '../office.js';
import { esc, fmt0, fmt1, fmtGain, money, timeAgo, toast } from '../ui.js';

/**
 * Übersicht = das Büro des Labels. Oben die Zahlen, darunter (am Anfang) die ersten Schritte, dann was gerade läuft und was das Büro notiert hat,
 * das Plattenregal mit allen Platten und ihrem Chartplatz, der Zettel vom Schwarzen Brett und die Chronik.
 * Die Notizen zur Post schreibt nur, wer im Büro arbeitet (Praktikum oder Assistenz), und jede Assistenz über ihr Gebiet: Post (was bald
 * abläuft), Kassetten (was sie angehört und gefunden hat), Büro (Bewerbungen, Interview-Anfragen, Scout-Berichte). Über die Bands schreiben ihre Manager; ohne Manager
 * erfährt man nur, wer richtig unzufrieden ist.
 */

const left = (t) => {
  const sec = t - serverNow();
  return sec >= 86400 ? `noch ${Math.ceil(sec / 86400)} ${Math.ceil(sec / 86400) === 1 ? 'Tag' : 'Tage'}` : sec >= 3600 ? `noch ${Math.ceil(sec / 3600)} Std.` : `noch ${Math.max(1, Math.ceil(sec / 60))} Min.`;
};
const who = (b) => {
  const m = b.memberIds.length === 1 ? musicianById(b.memberIds[0]) : null;
  return m ? (m.stageName ?? m.firstName) : b.name;
};
const go = (key, text) => `<button class="link" data-act="inboxGo" data-key="${key}">${text}</button>`;
const bandLink = (b, text = esc(b.name)) => `<a href="#/band/${b.id}">${text}</a>`;

export function render() {
  const s = app.state;
  return `
    ${head(s)}
    ${prizes(s)}
    ${firstSteps(s)}
    <div class="ov-grid">
      <div class="ov-col">
        ${managerNotes(s)}
        ${office(s)}
        ${now(s)}
      </div>
      <div class="ov-col">
        ${boardNote(s)}
        ${chronicle(s)}
      </div>
    </div>
    ${shelf(s)}`;
}

// ---------- Preise vom Battle (neuer Kern) ----------

/** Offene Wahl des Siegers (Preisgeld oder Wochenpreis) und Gutscheine daraus. */
function prizes(s) {
  const list = s.prizes ?? [];
  if (!list.length) return '';
  const info = (p) => cat().battlePrizes?.[p.prize] ?? { title: p.prize, desc: '' };
  const bandName = (p) => esc(p.bandId ? bandById(p.bandId)?.name ?? 'Eine frühere Band' : '');
  const row = (p) => {
    const n = info(p);
    if (p.kind === 'choice') {
      return `<li><b>${bandName(p)}</b> hat das Battle of the Bands gewonnen. Ihr dürft wählen:
        <span class="ov-prize-pick"><button class="btn small primary" data-act="prizeChoose" data-id="${p.id}" data-pick="money">${money(p.money)}</button>
        <button class="btn small" data-act="prizeChoose" data-id="${p.id}" data-pick="weekly" title="${esc(n.desc)}">${esc(n.title)}</button></span>
        <small class="muted">${esc(n.desc)}</small></li>`;
    }
    if (p.prize === 'blue_instrument') {
      return `<li>Gutschein: <b>${esc(n.title)}</b>. Es kommt ins Lager, sobald ihr es aussucht.
        <select data-change="prizeRedeem" data-id="${p.id}"><option value="">Instrument aussuchen …</option>${Object.keys(cat().instruments).map((t) => `<option value="${t}">${esc(instrumentName(t))}</option>`).join('')}</select></li>`;
    }
    return `<li>Gutschein für <b>${bandName(p)}</b>: ${esc(n.desc)}</li>`;
  };
  return `<section class="ov-panel ov-prizes"><h2>Preise vom Battle</h2><ul class="ov-notes">${list.map(row).join('')}</ul></section>`;
}

defineActions({
  async prizeRedeem(value, el) {
    if (!value) return;
    if (await mutate('prizeRedeem', { prizeId: Number(el.dataset.id), type: value })) toast(`Das blaue Instrument (${instrumentName(value)}) liegt jetzt im Lager.`, 'ok');
  },
});

// ---------- Kopf: Name und Zahlen ----------

function head(s) {
  const fame = s.bands.reduce((sum, b) => sum + b.fame, 0);
  const charting = (s.finance.records ?? []).filter((r) => r.rank);
  const best = charting.length ? Math.min(...charting.map((r) => r.rank)) : null;
  const founded = s.label.createdAt ? new Date(s.label.createdAt * 1000).toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric' }) : '';
  return `
    <section class="ov-head">
      <div><div class="eyebrow">Dein Label</div><h1>${esc(s.label.name)}</h1>${founded ? `<small class="muted">gegründet am ${founded} · <a href="#/wiki/label/${s.label.id}">in der WikiSignia</a></small>` : ''}</div>
      <div class="ov-stats">
        ${s.core
    // Neuer Kern: Geld aus Platten je Stunde, Fans aller Bands statt Label-Ruhm
    ? stat('Geld', `<span data-live-money>${money(liveMoney())}</span>`, s.finance.moneyPerHour ? `+${fmt0(s.finance.moneyPerHour)} ₲ pro Stunde aus Platten` : 'Platten und Auftritte bringen Geld')
      + stat('Fans', fmt0(s.label.fans ?? 0), 'alle Bands zusammen')
    : stat('Geld', `<span data-live-money>${money(liveMoney())}</span>`, s.finance.moneyPerHour ? `+${fmtGain(s.finance.moneyPerHour / 60)} ₲ pro Minute aus Platten` : 'Platten bringen laufend Geld')
      + stat('Label-Ruhm', fmt1(s.label.fame ?? fame), s.finance.famePerHour ? `+${fmtGain(s.finance.famePerHour)} pro Std. aus den Charts` : 'große Bands zählen mehr')}
        ${stat('In den Charts', charting.length ? fmt0(charting.length) : '–', best ? `bester Platz: ${best}` : 'noch keine Platte drin')}
        ${stat('Künstler', fmt0(s.musicians.length), s.bands.length ? `in ${s.bands.length} ${s.bands.length === 1 ? 'Band' : 'Bands'}` : 'noch niemand unter Vertrag')}
      </div>
    </section>`;
}

const stat = (label, value, note) => `<div class="ov-stat"><span>${esc(label)}</span><b>${value}</b><small>${note}</small></div>`;

// ---------- Erste Schritte (verschwinden, sobald alles einmal gemacht ist) ----------

function firstSteps(s) {
  const firstBand = s.bands[0];
  const steps = [
    { done: s.musicians.length > 0, text: 'Umschläge auspacken, eine Kassette anhören und den ersten Künstler unterschreiben', href: '#/einsendungen' },
    { done: !!s.assistant, text: 'Jemanden fürs Büro einstellen: Praktikum oder Assistenz (Bewerbungen im Posteingang)', href: '#/einsendungen' },
    { done: s.bands.some((b) => b.managed), text: 'Einen Manager einstellen: Er sagt dir, was deine Künstler brauchen', href: '#/einsendungen' },
    { done: s.gear.some((g) => g.musicianId), text: 'Im Music Store ein Instrument kaufen', href: '#/store' },
    { done: s.bands.some((b) => b.cohesion > 0 || b.gigsPlayed > 0 || b.records > 0), text: 'Zum ersten Mal proben', href: firstBand ? `#/band/${firstBand.id}` : '#/einsendungen' },
    { done: s.bands.some((b) => b.gigsPlayed > 0), text: s.core ? 'Zum ersten Mal auftreten' : 'Den ersten Auftritt buchen', href: firstBand ? `#/band/${firstBand.id}` : '' },
    { done: s.bands.some((b) => b.records > 0), text: 'Die erste Platte aufnehmen', href: firstBand ? `#/band/${firstBand.id}` : '' },
    { done: (s.scouts ?? []).length > 0, text: 'Einen Scout einstellen und auf Talentsuche schicken', href: '#/einsendungen' },
  ];
  const done = steps.filter((x) => x.done).length;
  if (done === steps.length) return '';
  const next = steps.find((x) => !x.done);
  return `
    <section class="ov-panel ov-steps">
      <h2>Erste Schritte <small class="muted">${done} von ${steps.length}</small></h2>
      <div class="ov-progress"><i style="width:${Math.round((done / steps.length) * 100)}%"></i></div>
      <ol>${steps.map((x) => `<li class="${x.done ? 'done' : x === next ? 'next' : ''}">${x === next && x.href ? `<a href="${x.href}">${esc(x.text)}</a>` : esc(x.text)}</li>`).join('')}</ol>
    </section>`;
}

// ---------- Das Büro: Notizen von Praktikum und Assistenz ----------

/**
 * Was das Büro aufschreibt: wichtigste zuerst (rot), dann was wartet, dann Hinweise. Von Briefen erzählt nur das Büro (und nur, was es
 * schon gelesen hat), von bald Ablaufendem nur die Post, vom Kassettenhören und vom Suchauftrag nur die Kassetten-Assistenz.
 */
function notes(s) {
  const out = [];
  const add = (level, html) => out.push({ level, html });
  const mail = s.mail ?? [];
  const areas = s.assistant?.areas ?? {};
  const subs = s.submissions ?? [];
  if (areas.office) {
    for (const m of mail.filter((x) => x.kind === 'interview' && x.read).sort((x, y) => x.expiresAt - y.expiresAt)) {
      const w = musicianById(m.musicianId);
      if (w) add(m.expiresAt - serverNow() < 2 * 3600 ? 0 : 1, go('interview', `Interview-Anfrage für <b>${esc(w.firstName)}</b> (${esc(m.medium)}, ${left(m.expiresAt)})`));
    }
    for (const m of mail.filter((x) => x.kind === 'report' && x.read && x.cards.some((c) => !c.signed))) {
      add(1, go('demos', `<b>${esc(m.scoutName)}</b> ist zurück und stellt dir ${m.cards.length} Talente vor (${left(m.expiresAt)})`));
    }
    // Lehrer: wer sich beworben hat und bei wem das Instrument passt; wer zu niemandem passt, steht zusammen in einer Zeile.
    const unfit = [];
    for (const m of mail.filter((x) => x.kind === 'job' && x.read && x.role === 'teacher')) {
      const fits = s.musicians.filter((x) => x.instruments.some((i) => i.type === m.instrument && i.skill < m.maxSkill));
      if (fits.length) add(1, go('job', `<b>${esc(m.firstName)}</b> möchte ${esc(instrumentName(m.instrument))} unterrichten (+${m.gain} Können), passt zu ${fits.slice(0, 3).map((x) => esc(x.firstName)).join(', ')}.`));
      else unfit.push(m);
    }
    if (unfit.length) add(3, go('job', `${unfit.length === 1 ? 'Eine Lehrkraft möchte' : `${unfit.length} Lehrkräfte möchten`} ${[...new Set(unfit.map((m) => instrumentName(m.instrument)))].map(esc).join(', ')} unterrichten, aber das spielt bei dir gerade niemand.`));
    const jobs = mail.filter((x) => x.kind === 'job' && x.read && x.role !== 'teacher');
    if (jobs.length) {
      const names = jobs.slice(0, 3).map((m) => `${esc(m.firstName)} (${esc(cat().staffRoles[m.role]?.tiers[m.tier] ?? '')})`).join(', ');
      add(2, go('job', `${jobs.length === 1 ? 'Eine Bewerbung' : `${jobs.length} Bewerbungen`}: ${names}${jobs.length > 3 ? ' …' : ''}`));
    }
  }
  if (areas.post) {
    const soon = [...subs, ...mail].filter((x) => x.expiresAt - serverNow() < (cat().rules.assistSoon ?? 43200)).length;
    if (soon) add(1, go('inbox', `${soon === 1 ? 'Eine Sache läuft' : `${soon} Sachen laufen`} bald ab (in den nächsten ${Math.round((cat().rules.assistSoon ?? 43200) / 3600)} Stunden).`));
  }
  const unheard = subs.filter((x) => x.state === 'closed' && !x.saved).length;
  const envelopes = [...subs, ...mail].filter((x) => x.state === 'envelope' && !x.saved).length;
  if (envelopes && !areas.post) add(2, go('inbox', `${envelopes === 1 ? 'Ein Umschlag liegt' : `${envelopes} Umschläge liegen`} noch ungeöffnet im Eingang.`));
  for (const x of (s.assistant?.staff ?? []).filter((y) => y.intern && !y.ended)) {
    const todo = s.assistant.todo;
    add(2, x.job ? `<b>${esc(x.firstName)}</b> ${esc(INTERN_JOBS[x.job.kind]?.[1] ?? 'arbeitet')}, fertig in <span data-until="${x.job.until}"></span>.`
      : (todo.unpack || todo.listen || todo.sort ? go('inbox', `<b>${esc(x.firstName)}</b> langweilt sich und wartet auf einen Auftrag.`) : `<b>${esc(x.firstName)}</b> hat gerade nichts zu tun.`));
  }
  const found = subs.filter((x) => x.found).length;
  if (found && areas.demos) add(0, go('saved', `${found === 1 ? 'Eine Kassette passt zu deiner Suche. Sie liegt' : `${found} Kassetten passen zu deiner Suche. Sie liegen`} bei den gemerkten.`));
  const good = subs.filter((x) => x.state === 'open' && !x.saved && Math.max(...(x.members ?? [x]).map((m) => m.level ?? 0)) >= 1).length;
  if (good) add(1, go('demos', `${good === 1 ? 'Eine gute Kassette wartet' : `${good} gute Kassetten warten`} auf dich.`));
  if (unheard && !areas.demos) add(2, go('demos', `${unheard === 1 ? 'Eine Kassette ist' : `${unheard} Kassetten sind`} noch nicht angehört.`));
  if (areas.demos) add(3, `Heute ${s.assistant.today.opened} Kassetten angehört, ${s.assistant.today.dropped} weggeworfen.`);
  for (const x of (s.scouts ?? []).filter((y) => !y.away) ) {
    if (!mail.some((m) => m.kind === 'report' && m.scoutName === x.firstName)) add(2, `<b>${esc(x.firstName)}</b> hat gerade nichts zu tun. Unter der Künstlerleiste kannst du den Scout losschicken.`);
  }
  for (const x of (s.assistant?.staff ?? []).filter((y) => y.intern && y.until - serverNow() < 2 * 86400)) {
    add(1, x.ended ? go('inbox', `Das Praktikum von <b>${esc(x.firstName)}</b> ist vorbei. ${esc(x.firstName)} möchte gern bleiben: übernehmen oder verabschieden?`)
      : go('inbox', `Das Praktikum von <b>${esc(x.firstName)}</b> endet bald (${left(x.until)}). Danach kannst du ${esc(x.firstName)} übernehmen.`));
  }
  if (!areas.office && mail.some((x) => ['job', 'interview', 'report'].includes(x.kind))) add(3, 'Von Bewerbungen und Interview-Anfragen erzählt dir eine Assistenz im Büro.');
  const c = (s.courses ?? [])[0];
  if (c) {
    const fits = c.candidates.filter((x) => !x.visited).map((x) => musicianById(x.musicianId)).filter(Boolean);
    if (fits.length) add(2, `<a href="#/brett">Heute im Kurs: ${esc(c.prefix)}-${esc(c.name)}</a>, passt zu ${fits.map((m) => esc(m.firstName)).join(', ')}.`);
  }
  return out.sort((a, b) => a.level - b.level);
}

// ---------- Manager: was sie über ihre Leute wissen ----------

/** Jeder Manager notiert, was seine Band (oder seine Solokünstler) braucht. Ohne Manager hört man nur, wer richtig unzufrieden ist. */
function managerNotes(s) {
  if (!s.bands.length) return '';
  const groups = new Map();
  for (const b of s.bands.filter((x) => x.managed)) {
    const m = managerById(b.managerId);
    if (!m) continue;
    if (!groups.has(m.id)) groups.set(m.id, { m, notes: [] });
    groups.get(m.id).notes.push(...b.managerNotes.map((n) => ({ ...n, b })));
  }
  const loud = s.bands.filter((b) => !b.managed && b.mood.wishes.length);
  // Großer Streit ist laut, den bekommt man auch ohne Manager mit.
  const fights = s.bands.filter((b) => !b.managed).flatMap((b) => Object.keys(b.mood.relations ?? {}).map((pair) => ({ b, ids: pair.split('-').map(Number) })));
  const without = s.bands.filter((b) => !b.managed);
  const line = (n) => `<li class="l${n.level}">${bandLink(n.b, esc(n.text))}</li>`;
  const block = ({ m, notes }) => `
    <div class="ov-mgr"><h3><span class="lb-ph sm">${photo({ id: m.id, look: m.look })}</span>${esc(m.firstName)} <small class="muted">für ${m.bandIds.map((id) => esc(who(bandById(id) ?? { memberIds: [], name: '' }))).join(', ')}</small></h3>
      ${notes.length ? `<ul class="ov-notes">${notes.sort((a, b) => a.level - b.level).slice(0, 6).map(line).join('')}</ul>` : '<p class="muted small">Alles gut, gerade fehlt nichts.</p>'}</div>`;
  const job = (s.mail ?? []).find((x) => x.kind === 'job' && x.role === 'manager');
  const hasJob = !!job;
  return `
    <section class="ov-panel ov-office ov-c-mgr">
      <h2>Notizen der Manager</h2>
      ${[...groups.values()].map(block).join('')}
      ${loud.length ? `<ul class="ov-notes">${loud.map((b) => {
        const m = musicianById(b.mood.wishes[0].musicianId);
        const name = m ? (m.stageName ?? m.firstName) : who(b);
        return `<li class="l0">${bandLink(b, `<b>${esc(name)}</b>${b.memberIds.length > 1 ? ` (${esc(b.name)})` : ''}`)} ist richtig unzufrieden: „${esc(b.mood.wishes[0].text)}“</li>`;
      }).join('')}</ul>` : ''}
      ${fights.length ? `<ul class="ov-notes">${fights.map(({ b, ids }) => `<li class="l0">${bandLink(b, `<b>${ids.map((id) => esc(musicianById(id)?.firstName ?? '')).join(' und ')}</b> haben richtig Streit.`)}</li>`).join('')}</ul>` : ''}
      ${without.length ? `<p class="muted small">${without.map((b) => esc(who(b))).join(', ')} ${without.length === 1 ? 'hat' : 'haben'} keinen Manager. Ohne Manager siehst du nur, wie gut jemand drauf ist, und erfährst erst etwas, wenn es richtig schlecht läuft.
        ${hasJob ? go(job.box ?? (job.sorted ? 'job' : 'inbox'), 'Ein Manager hat sich beworben.') : ''}</p>` : ''}
    </section>`;
}

function office(s) {
  const a = s.assistant;
  if (!a) {
    return `
      <section class="ov-panel ov-office ov-none ov-c-office">
        <h2>Das Büro</h2>
        <p class="muted">Noch arbeitet niemand im Büro. Ein Praktikum hilft dir auf Zuruf mit der Post. Eine Assistenz arbeitet von selbst in ihrem Gebiet (Post, Kassetten oder Büro) und schreibt dir hier auf, was wichtig ist.</p>
        ${go('job', 'Zu den Bewerbungen')}
      </section>`;
  }
  const list = notes(s);
  const names = a.staff.map((x) => esc(x.firstName));
  return `
    <section class="ov-panel ov-office ov-c-office">
      <h2><span class="ov-faces">${a.staff.slice(0, 4).map((x) => `<span class="lb-ph sm">${photo({ id: x.id, look: x.look })}</span>`).join('')}</span>
        Notizen von ${names.length > 1 ? `${names.slice(0, -1).join(', ')} und ${names.at(-1)}` : names[0]}</h2>
      ${list.length ? `<ul class="ov-notes">${list.map((n) => `<li class="l${n.level}">${n.html}</li>`).join('')}</ul>` : '<p class="muted">Alles erledigt. Gerade gibt es nichts Wichtiges.</p>'}
    </section>`;
}

// ---------- Was gerade läuft ----------

const ACT = { rehearsal: 'Probt', gig: 'Auftritt', record: 'Im Studio', bandpromo: 'Promo-Tour', retreat: 'Auszeit', interview: 'Interview', course: 'Im Kurs', practice: 'Übt', busking: 'Straßenmusik', openstage: 'Offene Bühne' };

function now(s) {
  const rows = [];
  for (const a of s.running) {
    const b = a.bandId ? bandById(a.bandId) : null;
    const m = a.musicianId ? musicianById(a.musicianId) : null;
    const face = m ? photo(m) : b ? photo(musicianById(b.memberIds[0]) ?? { id: b.id, look: {} }) : '';
    rows.push({
      until: a.endsAt,
      html: `<span class="lb-ph sm">${face}</span><span class="ov-now-txt"><b>${esc(b ? b.name : m ? m.firstName : '')}</b><small class="muted">${esc(ACT[a.type] ?? '')}${a.title && !['bandpromo', 'rehearsal'].includes(a.type) ? ` · ${esc(a.title)}` : ''}</small></span>
        <span class="ov-now-time" data-until="${a.endsAt}"></span>${['rehearsal', 'gig'].includes(a.type) ? `<button class="btn small" data-act="watch" data-id="${a.id}">Ansehen</button>` : ''}`,
    });
  }
  for (const x of (s.scouts ?? []).filter((y) => y.away)) {
    rows.push({ until: x.away.until, html: `<span class="lb-ph sm">${photo({ id: x.id, look: x.look })}</span><span class="ov-now-txt"><b>${esc(x.firstName)}</b><small class="muted">Scout auf Talentsuche</small></span><span class="ov-now-time" data-until="${x.away.until}"></span>` });
  }
  for (const t of s.tutors ?? []) {
    const target = t.role === 'teacher' ? musicianById(t.musicianId)?.firstName : bandById(t.bandId)?.name;
    rows.push({ until: t.until, html: `<span class="lb-ph sm">${photo({ id: t.id, look: t.look })}</span><span class="ov-now-txt"><b>${esc(t.firstName)}</b><small class="muted">${t.role === 'teacher' ? `unterrichtet ${esc(target ?? '')}` : `hilft ${esc(target ?? '')} beim Songschreiben`}</small></span><span class="ov-now-time" data-until="${t.until}"></span>` });
  }
  rows.sort((a, b) => a.until - b.until);
  return `
    <section class="ov-panel ov-c-now">
      <h2>Gerade los</h2>
      ${rows.length ? `<div class="ov-now">${rows.map((r) => `<div class="ov-now-row">${r.html}</div>`).join('')}</div>` : `<p class="muted">Gerade ist niemand unterwegs.${s.bands.length ? ' Auf der Seite einer Band kannst du proben, auftreten oder aufnehmen.' : ''}</p>`}
    </section>`;
}

// ---------- Schwarzes Brett (Auszug) ----------

function boardNote(s) {
  const c = (s.courses ?? [])[0];
  if (!c) return '';
  const fits = c.candidates.map((x) => ({ ...x, m: musicianById(x.musicianId) })).filter((x) => x.m);
  return `
    <section class="ov-panel ov-board">
      <h2><a href="#/brett">Schwarzes Brett</a></h2>
      <article class="bb-note ov-note" style="--r:-1deg">
        <span class="bb-pin"></span>
        <div class="bb-head"><span class="bb-ico">${instrumentIcon(c.instrument)}</span><div><b>Heute: ${esc(c.prefix)}-${esc(c.name)}</b><small>bei ${esc(c.teacher)} · +${fmt0(c.gain)} Können</small></div></div>
        ${fits.length ? `<div class="bb-who">${fits.map((x) => (x.visited ? `<span class="bb-done">${esc(x.m.firstName)} war schon da</span>`
          : `<button class="btn small primary" data-act="courseAttend" data-id="${x.m.id}" data-course="${c.index}" ${isBusy(x.m.busyUntil) ? 'disabled' : ''}>${esc(x.m.firstName)} hinschicken · ${money(c.levels[x.level].price)}</button>`)).join('')}</div>`
          : '<p class="bb-none">Bei dir spielt das heute niemand.</p>'}
        <a class="bb-more" href="#/brett">Die ganze Woche ansehen</a>
      </article>
    </section>`;
}

// ---------- Chronik ----------

function chronicle(s) {
  const list = s.history.slice(0, 10);
  return `
    <section class="ov-panel ov-c-chron">
      <h2>Chronik</h2>
      ${list.length ? `<ul class="ov-history">${list.map((h) => `<li><span>${esc(h.text)}</span><time>${timeAgo(h.createdAt, serverNow())}</time></li>`).join('')}</ul>` : '<p class="muted">Noch nichts passiert.</p>'}
    </section>`;
}

// ---------- Plattenregal ----------

/** „ · Gold“, wenn eine Platte so oft verkauft wurde (Schwellen aus Balance::AWARDS). */
function soldLevel(sold) {
  const t = cat().awards.records;
  return sold >= t.diamond ? ' · Diamant' : sold >= t.platinum ? ' · Platin' : sold >= t.gold ? ' · Gold' : '';
}

function shelf(s) {
  const records = s.bands.flatMap((b) => b.discography.map((r) => ({ ...r, band: b }))).sort((a, b) => b.id - a.id);
  const trend = (r) => (!r.rank ? '' : r.prev == null ? '<i class="up">neu</i>' : r.prev > r.rank ? '<i class="up">↑</i>' : r.prev < r.rank ? '<i class="down">↓</i>' : '<i>→</i>');
  return `
    <section class="ov-panel ov-shelf">
      <h2>Plattenregal <small class="muted">${records.length ? `${records.length} ${records.length === 1 ? 'Platte' : 'Platten'}` : ''}</small></h2>
      ${records.length ? `<div class="ov-records">${records.slice(0, 12).map((r) => `
        <a class="ov-record" href="#/band/${r.band.id}">
          ${coverHtml(r.cover, { band: r.band.name, title: r.title })}
          <b>„${esc(r.title)}“</b><small class="muted">${esc(r.band.name)} · ${r.format}</small>
          <span class="ov-rank ${r.rank ? 'on' : ''}">${r.rank ? `Platz ${r.rank} ${trend(r)}` : r.topped ? 'war auf Platz 1' : 'nicht in den Charts'}</span>
          ${r.sold ? `<small class="ov-sold">${fmt0(r.sold)} verkauft${soldLevel(r.sold)}</small>` : ''}
        </a>`).join('')}</div>`
        : '<p class="muted">Noch keine Platte. Die erste nimmst du auf der Seite einer Band auf, sobald genug Songs geschrieben sind.</p>'}
    </section>`;
}
