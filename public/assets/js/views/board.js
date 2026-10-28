import { instrumentIcon } from '../cards.js';
import { app, bandById, isBusy, musicianById } from '../core.js';
import { duration, esc, fmt0, money } from '../ui.js';
import { trophy } from './battle.js';

/**
 * Schwarzes Brett: was für alle gilt, nicht nur für dein Label. Heute die Kurse des Tages (mit „hinschicken“), dazu die Vorschau auf die Woche.
 * Unten hängen die Veranstaltungen für alle (Battle of the Bands), zu denen man Bands anmeldet.
 */

const DAY = new Intl.DateTimeFormat('de-DE', { weekday: 'long', day: 'numeric', month: 'numeric', timeZone: 'Europe/Berlin' });
const dayName = (ymd, i) => (i === 0 ? 'Morgen' : DAY.format(new Date(`${ymd}T12:00:00`)));
const tilt = (n) => `--r:${[-1.6, 1.2, -0.6, 1.8, -1.2, 0.8][n % 6]}deg`;

/** Ein Kurszettel von heute: wer aus dem Label hin kann. */
function todayNote(c, n) {
  const list = c.candidates.map((x) => ({ ...x, m: musicianById(x.musicianId) })).filter((x) => x.m);
  return `<article class="bb-note" style="${tilt(n)}">
    <span class="bb-pin"></span>
    <div class="bb-head"><span class="bb-ico">${instrumentIcon(c.instrument)}</span><div><b>${esc(c.prefix)}-${esc(c.name)}</b><small>bei ${esc(c.teacher)} · ${duration(c.seconds)}</small></div></div>
    <p class="bb-gain">+${fmt0(c.gain)} Können</p>
    <ul class="bb-prices">${c.levels.map((l) => `<li>${esc(l.name)} <b>${money(l.price)}</b></li>`).join('')}</ul>
    ${list.length ? `<div class="bb-who">${list.map((x) => (x.visited
      ? `<span class="bb-done">${esc(x.m.firstName)}: heute schon im Kurs</span>`
      : `<button class="btn small primary" data-act="courseAttend" data-id="${x.m.id}" data-course="${c.index}" ${isBusy(x.m.busyUntil) || (x.m.bandId && isBusy(bandById(x.m.bandId)?.busyUntil)) ? 'disabled' : ''}>${esc(x.m.firstName)} hinschicken · ${money(c.levels[x.level].price)}</button>`)).join('')}</div>`
      : '<p class="bb-none">Bei dir spielt das niemand.</p>'}
  </article>`;
}

function weekDay(d, i) {
  return `<div class="bb-day"><h3>${esc(dayName(d.day, i))}</h3>${d.courses.map((c) => `
    <div class="bb-mini"><span class="bb-ico sm">${instrumentIcon(c.instrument)}</span><span><b>${esc(c.prefix)}-${esc(c.name)}</b><small>+${fmt0(c.gain)} Können · ab ${money(c.prices[0])}</small></span></div>`).join('')}</div>`;
}

/** Zettel zum Battle of the Bands: wann, wer dabei ist, und ob es gerade läuft. */
function battleNote(n) {
  const bt = app.state.battle;
  if (!bt) return '';
  const cur = bt.current;
  const running = cur?.state === 'running';
  const mine = bt.next.entries.filter((e) => e.mine).map((e) => e.name);
  const winner = cur?.state === 'done' ? cur.places.find((p) => p.place === 1) : null;
  const count = bt.next.entries.length;
  return `<article class="bb-note bb-event" style="${tilt(n)}">
    <span class="bb-pin"></span>
    <div class="bb-head"><span class="bb-ico">${trophy(1)}</span><div><b>Battle of the Bands</b><small>${running ? 'Läuft gerade!' : `${esc(bt.next.when)}`}</small></div></div>
    ${running ? '<p class="bb-gain">Jetzt zuschauen, wie es steht.</p>' : `<p class="bb-done">${count ? `${count} ${count === 1 ? 'Band' : 'Bands'} angemeldet` : 'Noch keine Anmeldung'}${mine.length ? `, dabei: ${esc(mine.join(', '))}` : ''}</p>`}
    ${winner ? `<p class="bb-done">Letzter Sieger: <b>${esc(cur.teams[winner.id]?.name ?? '')}</b></p>` : ''}
    <a class="btn small primary" href="#/battle">${running ? 'Zuschauen' : 'Ansehen und anmelden'}</a>
  </article>`;
}

export function render() {
  const s = app.state;
  return `
    <div class="section-head"><h1>Schwarzes Brett</h1></div>
    <p class="muted">Hier hängt, was für alle gilt, nicht nur für dein Label. Jeden Tag gibt es neue Kurse. Jeder Musiker darf an einem Tag in einen Kurs.</p>
    <section class="bb-board">
      <h2 class="bb-cap">Heute</h2>
      <div class="bb-notes">${(s.courses ?? []).map(todayNote).join('')}</div>
      <h2 class="bb-cap">Die nächsten Tage</h2>
      <div class="bb-week">${(s.board?.week ?? []).map(weekDay).join('')}</div>
      <h2 class="bb-cap">Veranstaltungen</h2>
      <div class="bb-notes">${battleNote(1)}</div>
    </section>`;
}
