import { app, bandById, cat, defineActions, mutate, serverNow } from '../core.js';
import { genreGlyph } from '../cards.js';
import { esc, fmt0, fmt1, money, off } from '../ui.js';

/**
 * Battle of the Bands (jeden Samstag): Anmeldung, und wenn es läuft wie bei einer WM die Gruppen, die K.-o.-Runden
 * und das Spiel, das gerade auf der Bühne ist. Der Server schickt nur, was nach der Uhr schon passiert ist.
 */

const CLOCK = new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Berlin' });
const clock = (t) => `${CLOCK.format(new Date(t * 1000))} Uhr`;
const pct = (x) => `${x >= 0 ? '+' : '−'}${Math.round(Math.abs(x) * 100)} %`;
const METAL = { 1: ['#e8c35a', '#a8801f'], 2: ['#d5d9e0', '#8b93a1'], 3: ['#d79a62', '#8f5a2c'] };

/** Pokal in Gold, Silber oder Bronze (für die Bandseite und das Brett). */
export function trophy(place = 1) {
  const [light, dark] = METAL[place] ?? METAL[1];
  return `<svg class="trophy" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3 H17 V9 C17 12 15 14 12 14 C9 14 7 12 7 9 Z" fill="${light}" stroke="${dark}" stroke-width="1.2"/>
    <path d="M7 5 H4 C4 8 5 10 7.5 10.5 M17 5 H20 C20 8 19 10 16.5 10.5" fill="none" stroke="${dark}" stroke-width="1.4"/>
    <path d="M12 14 V18 M8.5 21 H15.5 L14.5 18 H9.5 Z" fill="${light}" stroke="${dark}" stroke-width="1.2"/></svg>`;
}

function team(cur, id) {
  const t = id ? cur.teams[id] : null;
  if (!t) return '<span class="bt-team open">noch offen</span>';
  return `<span class="bt-team ${t.mine ? 'mine' : ''}" title="${esc(t.label)}">${t.genres.map(genreGlyph).join('')}${esc(t.name)}</span>`;
}

const mineIn = (cur, m) => !!(cur.teams[m.a]?.mine || cur.teams[m.b]?.mine);

/** Ein Spiel: beide Bands, dazwischen das Ergebnis (oder „läuft“, oder die Uhrzeit). */
function matchRow(cur, m, round) {
  const done = m.pa !== undefined;
  const mid = done ? `<b class="bt-score">${m.pa} : ${m.pb}</b>` : m.live ? '<span class="bt-live">läuft</span>' : `<span class="bt-at">${clock(round.at)}</span>`;
  const side = (id) => (done ? (m.w === id ? 'won' : 'lost') : '');
  return `<div class="bt-match ${m.live ? 'live' : ''} ${mineIn(cur, m) ? 'mine' : ''}">
    <span class="bt-side ${side(m.a)}">${team(cur, m.a)}</span>${mid}<span class="bt-side right ${side(m.b)}">${team(cur, m.b)}</span></div>`;
}

function groupCard(cur, g, gi) {
  // Weiter kommen die ersten zwei (bei nur einer Gruppe: ins Finale)
  const rows = g.table.map((r, i) => `<tr class="${cur.teams[r.id]?.mine ? 'mine' : ''} ${i < 2 ? 'through' : ''}">
      <td>${i + 1}</td><td>${team(cur, r.id)}</td><td>${r.played}</td><td>${r.wins}</td><td>${r.points}:${r.against}</td></tr>`).join('');
  const games = cur.rounds.flatMap((round) => round.matches.filter((m) => m.kind === 'group' && m.group === gi).map((m) => matchRow(cur, m, round))).join('');
  return `<article class="bt-group">
    <h3>${esc(g.name)}</h3>
    <table class="bt-table"><thead><tr><th></th><th>Band</th><th title="Spiele">Sp.</th><th title="Siege">Siege</th><th title="Punkte der Jury">Punkte</th></tr></thead><tbody>${rows}</tbody></table>
    <div class="bt-games">${games}</div>
  </article>`;
}

/** K.-o.-Runden nebeneinander, das Spiel um Platz 3 beim Finale. */
function bracket(cur) {
  const ko = cur.rounds.filter((r) => r.kind !== 'group');
  if (!ko.length) return '';
  return `<h2 class="bt-cap">K.-o.-Runden</h2>
    <div class="bt-bracket" style="--cols:${ko.length}">${ko.map((round) => `
      <div class="bt-col"><h3>${esc(round.name)} <small>${clock(round.at)}</small></h3>
        ${round.matches.map((m) => `${m.kind === 'third' ? '<p class="bt-third">Spiel um Platz 3</p>' : ''}${matchRow(cur, m, round)}`).join('')}
      </div>`).join('')}
    </div>`;
}

/** Was gerade auf der Bühne passiert: das Spiel der eigenen Band, sonst das erste. */
function stage(cur) {
  if (!cur.live?.length) return '';
  const main = cur.live.find((l) => mineIn(cur, l)) ?? cur.live[0];
  const round = cur.rounds.find((r) => r.matches.some((m) => m.i === main.i));
  const others = cur.live.filter((l) => l !== main);
  const now = serverNow();
  return `<section class="bt-stage">
    <div class="eyebrow">Jetzt auf der Bühne · ${esc(main.kind === 'third' ? 'Spiel um Platz 3' : round?.name ?? '')}${main.group !== null ? ` · ${esc(cur.groups[main.group]?.name ?? '')}` : ''}</div>
    <h2>${team(cur, main.a)} <span class="muted">gegen</span> ${team(cur, main.b)}</h2>
    <ol class="bt-lines">${main.lines.map((l) => `<li class="bt-line ${l.kind} side-${l.side}" data-at="${l.at}" ${l.at > now ? 'hidden' : ''}>${esc(l.text)}</li>`).join('')}</ol>
    <p class="muted small">Die Jury sagt ihr Ergebnis in <span data-until="${main.revealAt}"></span>.</p>
    ${others.length ? `<p class="muted small">Gleichzeitig auf den anderen Bühnen: ${others.map((l) => `${team(cur, l.a)} – ${team(cur, l.b)}`).join(' · ')}</p>` : ''}
  </section>`;
}

/** Wann die eigene Band als Nächstes spielt (wenn das schon feststeht). */
function upNext(cur) {
  const now = serverNow();
  for (const round of cur.rounds) {
    if (round.at <= now) continue;
    const m = round.matches.find((x) => x.a && mineIn(cur, x));
    if (m) return `<p class="bt-next">Als Nächstes: ${team(cur, m.a)} gegen ${team(cur, m.b)} um ${clock(round.at)} (in <span data-until="${round.at}"></span>).</p>`;
  }
  return '';
}

function podium(cur) {
  const top = cur.places.filter((p) => p.place && p.place <= 3);
  return `<div class="bt-podium">${top.map((p) => `
    <div class="bt-place p${p.place} ${cur.teams[p.id]?.mine ? 'mine' : ''}">${trophy(p.place)}<span><small>${p.place === 1 ? 'Sieger' : `Platz ${p.place}`}</small><b>${esc(cur.teams[p.id]?.name ?? '')}</b><small>${esc(cur.teams[p.id]?.label ?? '')}</small></span></div>`).join('')}
  </div>`;
}

function current(cur) {
  if (cur.state === 'cancelled') {
    return `<section class="panel bt-panel"><h2>Das letzte Battle fiel aus</h2><p class="muted">${esc(cur.when)}: Es hatten sich zu wenige Bands angemeldet.</p></section>`;
  }
  const running = cur.state === 'running';
  return `<section class="panel bt-panel">
    <div class="section-head"><div><div class="eyebrow">${running ? 'Läuft gerade' : 'Das letzte Battle'}</div><h2>${esc(cur.when)}</h2></div>
      ${running ? `<span class="muted small">Ende gegen ${clock(cur.endsAt)}</span>` : ''}</div>
    ${running ? '' : podium(cur)}
    ${stage(cur)}
    ${running ? upNext(cur) : ''}
    ${cur.groups.length ? `<h2 class="bt-cap">${cur.groups.length === 1 ? 'Gruppe' : 'Gruppen'}</h2><div class="bt-groups">${cur.groups.map((g, i) => groupCard(cur, g, i)).join('')}</div>` : ''}
    ${bracket(cur)}
  </section>`;
}

function signup(battle) {
  const n = battle.next;
  const r = battle.rules;
  const own = n.options.map((o) => ({ ...o, b: bandById(o.bandId) })).filter((o) => o.b);
  const mineCount = n.entries.filter((e) => e.mine).length;
  return `<section class="panel bt-panel">
    <div class="eyebrow">Anmeldung</div>
    <h2>${esc(n.when)}</h2>
    ${n.weekly ? `<p class="bt-weekly">Wochenpreis: <b>${esc(cat().battlePrizes[n.weekly]?.title ?? '')}</b> · Der Sieger wählt ihn oder ${money(battle.rules.prizes[1])}. <small class="muted">${esc(cat().battlePrizes[n.weekly]?.desc ?? '')}</small></p>` : ''}
    <p class="muted">Jedes Label darf bis zu ${n.perLabel} Bands anmelden, nur Bands (keine Solokünstler). Online sein muss dafür niemand: Das Battle läuft auch so, das Ergebnis kommt beim nächsten Öffnen. Nur kann eine angemeldete Band vorher nichts anfangen, was bis über den Beginn hinaus dauert.</p>
    ${own.length ? `<div class="bt-entry-list">${own.map((o) => `
      <div class="bt-entry ${o.entered ? 'in' : ''}">
        <span><b>${esc(o.b.name)}</b><small>Sound ${fmt1(o.b.sound)} · Laune ${pct(o.moodBonus)}</small></span>
        ${o.entered
    ? '<span class="bt-in">angemeldet</span><button class="btn small ghost" data-act="battleWithdraw" data-id="' + o.b.id + '">Abmelden</button>'
    : `<button class="btn small primary" data-act="battleEnter" data-id="${o.b.id}" ${off(o.blocked)}>Anmelden</button>`}
      </div>`).join('')}</div>` : '<p class="muted">Du hast noch keine Band mit mindestens zwei Musikern.</p>'}
    <h3>Angemeldet: ${n.entries.length} ${n.entries.length === 1 ? 'Band' : 'Bands'}${mineCount ? `, ${mineCount} von dir` : ''}</h3>
    ${n.entries.length ? `<ul class="bt-entries">${n.entries.map((e) => `<li class="${e.mine ? 'mine' : ''}"><b>${esc(e.name)}</b> <small>${esc(e.label)}</small></li>`).join('')}</ul>` : '<p class="muted small">Noch niemand. Wenn bis zum Beginn weniger als zwei Bands dabei sind, fällt das Battle aus.</p>'}
    <details class="bt-rules"><summary>So wird entschieden</summary>
      <p>Jede Band spielt ${r.songs} Lieder, dann gibt die Jury Punkte. Die Punkte kommen vom <b>Sound</b> der Band. Gute <b>Laune</b> gibt bis zu ${pct(r.moodMax)} dazu, schlechte Laune zieht ab. Dazu kommt die <b>Tagesform</b>: Mal klappt es besser, mal schlechter (bis zu ${Math.round(r.luck * 100)} %). Ruhm zählt nicht.</p>
      <p>Erst spielen alle in Gruppen jeder gegen jeden. Die zwei Besten jeder Gruppe kommen weiter, dann geht es im K.-o. bis zum Finale. Bands aus demselben Label kommen möglichst in verschiedene Gruppen. Alle ${Math.round(r.round / 60)} Minuten beginnt eine neue Runde.</p>
      ${r.core
    ? `<p>Preise: Platz 1 ${money(r.prizes[1])} und ein goldener Pokal, Platz 2 ${money(r.prizes[2])} und Silber, Platz 3 ${money(r.prizes[3])} und Bronze, Platz 4 ${money(r.prizes[4])}. Jedes Spiel hat rund ${fmt0(r.audience)} Zuschauer: Wer noch kein Fan ist, kann einer werden. Die ersten drei geben der neuesten Platte einen Schub Hype. Alle bleiben bis zum Schluss und feiern mit.</p>`
    : `<p>Preise: Platz 1 ${money(r.prizes[1])} und ein goldener Pokal, Platz 2 ${money(r.prizes[2])} und Silber, Platz 3 ${money(r.prizes[3])} und Bronze. Für jedes Spiel und jeden Sieg gibt es Ruhm, und alle bleiben bis zum Schluss und feiern mit.</p>`}
    </details>
  </section>`;
}

export function render() {
  const battle = app.state.battle;
  if (!battle) return '<div class="empty">Gerade gibt es kein Battle.</div>';
  const cur = battle.current;
  const running = cur?.state === 'running';
  return `
    <div class="section-head"><div><a class="muted small" href="#/brett">← Schwarzes Brett</a><h1>Battle of the Bands</h1></div></div>
    <p class="muted">Jeden Samstag treten Bands aus allen Labels gegeneinander an, wie bei einer Weltmeisterschaft.</p>
    ${running ? current(cur) : ''}
    ${signup(battle)}
    ${cur && !running ? current(cur) : ''}`;
}

/** Jede Sekunde: Zeilen der Ansage zeigen, sobald sie dran sind. */
export function tick() {
  const now = serverNow();
  for (const el of document.querySelectorAll('.bt-line[hidden][data-at]')) {
    if (Number(el.dataset.at) <= now) el.hidden = false;
  }
}

defineActions({
  async battleEnter({ id }) {
    await mutate('battleEnter', { bandId: Number(id) });
  },
  async battleWithdraw({ id }) {
    await mutate('battleWithdraw', { bandId: Number(id) });
  },
});
