import { app, bandById, cat, defineActions, mutate, serverNow } from '../core.js';
import { genreGlyph } from '../cards.js';
import { esc, fmt0, fmt1, money, off, percent } from '../ui.js';

/**
 * Festival (jeden Mittwoch): Plakat mit dem Line-up (Bühnen, Zeiten, Headliner) und die Bewerbung für das nächste.
 * Bis zum Anmeldeschluss bewerben sich Bands mit dem Genre des Festivals; dann hängt das Line-up aus.
 */

const CLOCK = new Intl.DateTimeFormat('de-DE', { hour: '2-digit', minute: '2-digit', timeZone: 'Europe/Berlin' });
const clock = (t) => `${CLOCK.format(new Date(t * 1000))} Uhr`;
const genreName = (g) => cat().genres[g]?.name ?? g;

/** Kleines Festival-Plakat (für den Proberaum und das Brett). */
export function poster() {
  return `<svg class="trophy poster" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="2.5" width="16" height="19" rx="1.5" fill="#efe3c8" stroke="#6b5238" stroke-width="1.2"/>
    <path d="M6.5 6 H17.5" stroke="#c0623a" stroke-width="2.2"/><path d="M7 10 H17 M8 13 H16 M9 16 H15" stroke="#6b5238" stroke-width="1.2"/><circle cx="12" cy="19" r="1" fill="#c0623a"/></svg>`;
}

/** Das Auftritts-Fenster der eigenen Band, wenn sie gerade auf dem Festival spielt. */
const liveAct = (bandId) => app.state.running.find((a) => a.type === 'festival' && a.bandId === bandId);

function act(a, stage) {
  const run = a.live && a.mine ? liveAct(a.bandId) : null;
  const state = a.missing ? '<small class="fe-state">nicht gekommen</small>'
    : a.live ? `<small class="fe-state live">spielt gerade</small>${run ? `<button class="btn small primary" data-act="watch" data-id="${run.id}">Zuschauen</button>` : ''}`
      : a.done ? `<small class="fe-state">fertig${a.meter !== null ? ` · Publikum ${percent(a.meter / 100)}` : ''}</small>` : '';
  return `<li class="fe-act ${a.headliner ? 'head' : ''} ${a.mine ? 'mine' : ''} ${a.live ? 'live' : ''}">
    <span class="fe-time">${clock(a.at)}</span>
    <span class="fe-band">${a.headliner ? '<small class="fe-tag">Headliner</small>' : ''}<b>${a.genres.map(genreGlyph).join('')}${esc(a.name)}</b><small>${esc(a.label)}</small></span>
    ${state}
  </li>`;
}

function lineup(cur) {
  if (cur.state === 'cancelled') {
    return `<section class="panel fe-panel"><div class="eyebrow">Das letzte Festival</div><h2>${esc(cur.name)}</h2><p class="muted">${esc(cur.when)}: Es hatte sich keine passende Band beworben, deshalb fiel es aus.</p></section>`;
  }
  const head = { lineup: 'Das Line-up steht', running: 'Läuft gerade', done: 'Das letzte Festival' }[cur.state];
  const now = serverNow();
  const mine = cur.stages.flatMap((s) => s.acts.filter((a) => a.mine && !a.done && a.at > now).map((a) => ({ ...a, stage: s.name })));
  return `<section class="fe-poster" style="--g: var(--c-${cur.genre})">
    <div class="eyebrow">${head}</div>
    <h2 class="fe-name">${esc(cur.name)}</h2>
    <p class="fe-sub">${esc(cur.when)} · ${genreGlyph(cur.genre)}${esc(genreName(cur.genre))}</p>
    ${mine.map((a) => `<p class="fe-mine">${esc(a.name)} spielt um ${clock(a.at)} auf der ${esc(a.stage)} (in <span data-until="${a.at}"></span>).</p>`).join('')}
    <div class="fe-stages">${cur.stages.map((s) => `
      <div class="fe-stage"><h3>${esc(s.name)}</h3><ol>${[...s.acts].reverse().map((a) => act(a, s)).join('')}</ol></div>`).join('')}
    </div>
  </section>`;
}

function apply(f) {
  const n = f.next;
  const rules = f.rules;
  const own = n.options.map((o) => ({ ...o, b: bandById(o.bandId) })).filter((o) => o.b)
    .sort((x, y) => (y.entered || !y.blocked) - (x.entered || !x.blocked));
  const genre = genreName(n.genre);
  return `<section class="panel fe-panel">
    <div class="eyebrow">Bewerbung</div>
    <h2>${esc(n.name)} <small class="muted">${genreGlyph(n.genre)}${esc(genre)}</small></h2>
    <p class="muted">${esc(n.when)}. Bewerben bis ${esc(n.closeWhen)}. Gesucht sind ${esc(genre)}-Bands, auch gemischt mit ${esc(genre)}. Jedes Label darf alle passenden Bands schicken.</p>
    <p class="muted">Es gibt ${n.slots} Plätze: ${rules.stages.map((s) => `${esc(s.name)} ${s.slots}`).join(', ')}. Bewerben sich mehr, entscheidet der Veranstalter. ${rules.core ? 'Die Bands mit den meisten Fans spielen auf der Hauptbühne' : `Wer in den letzten ${rules.days} Tagen am meisten Bekanntheit gesammelt hat, spielt auf der Hauptbühne`}, zum Schluss als Headliner.</p>
    ${own.length ? `<div class="bt-entry-list">${own.map((o) => `
      <div class="bt-entry ${o.entered ? 'in' : ''}">
        <span><b>${o.b.genres.map(genreGlyph).join('')}${esc(o.b.name)}</b><small>${rules.core ? `${fmt0(o.gain)} Fans` : `In ${rules.days} Tagen +${fmt1(o.gain)} Bekanntheit`}</small></span>
        ${o.entered
    ? `<span class="bt-in">beworben</span><button class="btn small ghost" data-act="festivalWithdraw" data-id="${o.b.id}">Zurückziehen</button>`
    : `<button class="btn small ${o.blocked ? '' : 'primary'}" data-act="festivalEnter" data-id="${o.b.id}" ${off(o.blocked)}>Bewerben</button>`}
      </div>`).join('')}</div>` : '<p class="muted">Du hast noch keine Band.</p>'}
    <h3>Beworben: ${n.entries.length} ${n.entries.length === 1 ? 'Band' : 'Bands'}</h3>
    ${n.entries.length ? `<ul class="bt-entries">${n.entries.map((e) => `<li class="${e.mine ? 'mine' : ''}"><b>${esc(e.name)}</b> <small>${esc(e.label)}</small></li>`).join('')}</ul>` : '<p class="muted small">Noch niemand.</p>'}
    <details class="bt-rules"><summary>Was es gibt</summary>
      ${rules.stages.map((s) => (rules.core
    ? `<p><b>${esc(s.name)}</b>: ${s.songs} Lieder, ${money(s.money)} und rund ${fmt0(s.audience)} Zuschauer. Wer noch kein Fan ist, kann einer werden, umso mehr, je besser die Show. Dazu gibt die Bühne der neuesten Platte einen Schub Hype.</p>`
    : `<p><b>${esc(s.name)}</b>: ${s.songs} Lieder, ${money(s.money)} Gage und viel Bekanntheit (je größer die Bühne, desto mehr; dazu zählen Sound und Publikum wie bei einem Gig).</p>`)).join('')}
      <p>Nach dem Auftritt will ein größeres Magazin oder ein Sender ein Interview (im Posteingang). Der Headliner bekommt ein Plakat für den Proberaum. Und vielleicht wartet ein Fan, der mitspielen will.</p>
      <p>Die Band ist vom Beginn des Festivals bis zu ihrem Auftritt dort.</p>
    </details>
  </section>`;
}

export function render() {
  const f = app.state.festival;
  if (!f) return '<div class="empty">Gerade gibt es kein Festival.</div>';
  const cur = f.current;
  const fresh = cur && cur.state !== 'done' && cur.state !== 'cancelled';
  return `
    <div class="section-head"><div><a class="muted small" href="#/brett">← Schwarzes Brett</a><h1>Festival</h1></div></div>
    <p class="muted">Jeden Mittwoch spielt ein Festival unter freiem Himmel. Jedes sucht ein anderes Genre.</p>
    ${fresh ? lineup(cur) : ''}
    ${apply(f)}
    ${cur && !fresh ? lineup(cur) : ''}`;
}

defineActions({
  async festivalEnter({ id }) {
    await mutate('festivalEnter', { bandId: Number(id) });
  },
  async festivalWithdraw({ id }) {
    await mutate('festivalWithdraw', { bandId: Number(id) });
  },
});
