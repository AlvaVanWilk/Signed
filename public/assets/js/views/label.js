import { genreGlyph, genreName, gearModel, instrumentIcon, instrumentName, ownGear, photo, sortedGenres } from '../cards.js';
import { actions, app, bandById, cat, defineActions, isBusy, mutate, musicianById, runningFor, serverNow } from '../core.js';
import { closePopover, duration, esc, fmt0, fmt1, fmtGain, openModal, popover } from '../ui.js';
import { getAudio } from '../audio.js';
import { playedInstrument, stage } from '../figure.js';
import { bandStems, loadStems, playStems, stemUrl } from '../music.js';
import { blocker, blockerNotice, courseTiles, gainRange, statTile } from './bands.js';

/** Label-Ebene: Band-Leiste links und die ruhige Bandseite. Stimmung als Punkt und Wort (grün, gelb, rot). */

/** Wie viele Können-Stufen eine eigene Farbe haben (lv-0 … lv-N in label.css); weitere Stufen übernehmen die letzte, bis eine dazukommt. */
const MAX_LV_STYLE = 3;
const MOOD_WORD = { green: 'gut', yellow: 'angespannt', red: 'unzufrieden' };
const LAST_KEY = 'signed.lastBand';

export const moodWord = (level) => MOOD_WORD[level] ?? MOOD_WORD.green;

const moodTag = (level) => `<span class="lb-mood ${level}"><i></i>${moodWord(level)}</span>`;
/** Weg zur nächsten Können-Stufe in Prozent (nach der letzten: Rest bis 100). Dieselbe Leiste in den Zeilen, in der Probe und bei den Einsendungen. */
export function levelProgress(skill) {
  const marks = [0, ...cat().rules.skillLevels, 100];
  const hi = marks.findIndex((x) => skill < x);
  const lo = marks[Math.max(0, hi - 1)];
  return Math.min(100, ((skill - lo) / ((marks[hi] ?? 100) - lo)) * 100);
}

export const levelClass = (skill) => `lv-${Math.min(steps(skill, cat().rules.skillLevels), MAX_LV_STYLE)}`;
const steps = (value, thresholds) => thresholds.filter((t) => value >= t).length;
/** Dauer in Minuten (bzw. Stunden), lesbar ohne mm:ss. */
const mins = (s) => (s >= 5400 ? `${(s / 3600).toFixed(1).replace('.', ',')} Std.` : `${Math.max(1, Math.round(s / 60))} Min.`);
const signed1 = (v) => `${v > 0 ? '+' : v < 0 ? '−' : ''}${fmt1(Math.abs(v)).replace('.', ',')}`;
const signed = (v) => `${v > 0 ? '+' : v < 0 ? '−' : ''}${fmt0(Math.abs(v))}`;

/** Genre-Marke: Farbe des Genres als Rand und zarte Fläche, das Symbol darin. */
function genreTag(key) {
  return `<button class="lb-gt g-${key}" data-act="tip" data-tip="${esc(genreName(key))}" aria-label="${esc(genreName(key))}">${genreGlyph(key)}</button>`;
}

/** Genres der Band; ein Solokünstler hat keine Band-Genres, dort zählen seine eigenen. */
const genresOf = (b) => (b.genres.length || b.memberIds.length > 1 ? b.genres : (musicianById(b.memberIds[0])?.genres ?? []));

/** Zuletzt angesehene Band merken (für „Bands“ im Hauptmenü). */
export function rememberBand(id) {
  try { localStorage.setItem(LAST_KEY, String(id)); } catch { /* ohne Speicher egal */ }
}

export function lastBandId() {
  const bands = app.state.bands;
  let id = null;
  try { id = Number(localStorage.getItem(LAST_KEY)); } catch { /* ohne Speicher egal */ }
  return bands.some((b) => b.id === id) ? id : (bands[0]?.id ?? null);
}

// ---------- Band-Leiste ----------

function tile(b, selected) {
  const away = isBusy(b.busyUntil) || !!runningFor(b.id);
  const solo = b.memberIds.length === 1;
  const genreList = genresOf(b);
  const genres = genreList.length ? sortedGenres(genreList).map(genreName).join(' · ') : 'gemischt';
  const pips = steps(b.fame, cat().rules.fameLevels);
  const split = genreList.length ? `--g:var(--lb-c-${sortedGenres(genreList)[0]})` : '';
  return `
    <a href="#/band/${b.id}" class="lb-bt ${selected ? 'sel' : ''} ${away ? 'busy' : ''} ${b.mood.level === 'red' ? 'frozen' : ''}" style="${split}" title="${esc(b.name)}">
      <span class="nm">${esc(b.name)}</span>
      <span class="gl">${solo ? 'Solo · ' : ''}${esc(genres)}</span>
      <span class="mood">${moodTag(b.mood.level)}</span>
      <span class="fame" title="Ruhm: ${esc(cat().fameNames[Math.min(pips, 3)] ?? '')}">${[0, 1, 2].map((i) => `<i class="${i < pips ? 'on' : ''}"></i>`).join('')}${b.mood.level === 'red' ? '<small>wartet auf dich</small>' : ''}${b.mood.wishes?.length ? `<svg class="wish" viewBox="0 0 24 20" title="Die Band hat einen Wunsch"><path d="M3 2h18a2 2 0 012 2v9a2 2 0 01-2 2h-9l-5 4v-4H3a2 2 0 01-2-2V4a2 2 0 012-2z"/></svg>` : ''}</span>
      <span class="songs ${b.mood.songRate > 0 ? 'writing' : ''}" style="--cycle:${b.mood.songRate > 0 ? Math.min(6, Math.max(0.9, 1.6 / b.mood.songRate)).toFixed(2) : 0}s" title="Geschriebene Songs, die auf eine Aufnahme warten${b.mood.songRate > 0 ? ` · schreibt gerade etwa ${String(Math.round(b.mood.songRate * 10) / 10).replace('.', ',')} pro Std.` : ' · schreibt gerade nicht'}"><svg viewBox="0 0 24 24" aria-hidden="true"><path pathLength="100" d="M9 18a3 3 0 11-6 0 3 3 0 016 0 L9 5 L20 3 L20 16 a3 3 0 11-6 0 3 3 0 016 0"/></svg><b>${b.mood.songs}</b></span>
    </a>`;
}

export function bar(selectedId) {
  const bands = app.state.bands;
  const order = app.state.label.navOrder ?? [];
  const rank = (b, i) => (order.includes(`b${b.id}`) ? order.indexOf(`b${b.id}`) : order.length + i);
  const sorted = bands.map((b, i) => ({ b, r: rank(b, i) })).sort((x, y) => x.r - y.r).map((x) => x.b);
  return `
    <aside class="lb-side">
      <h2 class="lb-cap">Deine Bands</h2>
      <nav class="lb-bar">
        ${sorted.map((b) => tile(b, b.id === selectedId)).join('')}
        <button class="lb-add" data-act="newBand">+ Band oder Solokünstler</button>
      </nav>
      <p class="lb-legend"><span class="lb-mood green"><i></i>gut</span><span class="lb-mood yellow"><i></i>angespannt</span><span class="lb-mood red"><i></i>unzufrieden</span></p>
    </aside>`;
}

// ---------- Bandseite ----------

const ICON = {
  rehearse: '<svg viewBox="0 0 32 32"><path d="M10 28 L14 5 H18 L22 28 Z"/><path d="M16 22 L23 9"/><circle cx="23" cy="9" r="1.6"/></svg>',
  record: '<svg viewBox="0 0 32 32"><circle cx="16" cy="16" r="12"/><circle cx="16" cy="16" r="4.5"/><path d="M16 4 V9"/></svg>',
  gig: '<svg viewBox="0 0 32 32"><rect x="12" y="4" width="8" height="15" rx="4"/><path d="M8 15 a8 8 0 0 0 16 0 M16 23 V28 M11 28 H21"/></svg>',
};

const relWord = (v) => (v >= 7 ? 'verstehen sich sehr gut' : v >= 3 ? 'verstehen sich gut' : v > -3 ? 'kennen sich kaum' : v > -7 ? 'sind angespannt' : 'sind zerstritten');

/** Beziehung −10…+10 als Farbe: Grau in der Mitte, zu Grün (gut) oder Rot (schlecht) hin stufenlos. */
export function relColor(v) {
  const mix = (a, b, t) => a.map((x, i) => Math.round(x + (b[i] - x) * t));
  return `rgb(${mix([96, 92, 108], v >= 0 ? [92, 170, 130] : [196, 100, 108], Math.min(1, Math.abs(v) / 10)).join(',')})`;
}

function relationsOf(b, m, members) {
  const rel = b.mood.relations ?? {};
  return members.filter((x) => x.id !== m.id).map((x) => {
    const key = m.id < x.id ? `${m.id}-${x.id}` : `${x.id}-${m.id}`;
    const v = Math.round(rel[key] ?? 0);
    return `<button class="lb-rel" style="--rc:${relColor(rel[key] ?? 0)}" data-act="relInfo" data-a="${m.id}" data-b="${x.id}" data-band="${b.id}" title="${esc(m.firstName)} und ${esc(x.firstName)}: ${relWord(v)}">${photo(x)}</button>`;
  }).join('');
}

/** Das Instrument, das der Musiker in dieser Band spielt, und sein Platz dafür (Instrument-Wahl, falls er mehrere kann). */
function playedOf(m) {
  const index = m.instruments.length > 1 ? m.bandSlot - 1 : 0;
  return m.instruments[index] ?? m.instruments[0];
}

/**
 * Feste Plätze für die Genre-Marken der Band: Alle Genres, die ein Mitglied mag, je ein Platz.
 * Das von den meisten geteilte steht ganz rechts, das am zweitmeisten geteilte links daneben – so stehen gleiche Marken in allen Zeilen untereinander.
 */
function genreSlots(members) {
  const count = new Map();
  for (const m of members) for (const g of m.genres) count.set(g, (count.get(g) ?? 0) + 1);
  const order = sortedGenres([...count.keys()]);
  return order.sort((a, b) => count.get(a) - count.get(b) || order.indexOf(b) - order.indexOf(a));
}

/** Hat der Musiker ein Instrument für das, was er in der Band spielt? Ohne steht er nur dabei. */
export function hasGear(m) {
  const type = playedOf(m).type;
  return app.state.gear.some((g) => g.musicianId === m.id && gearModel(g.model).type === type);
}

function memberRow(b, m, members, slots) {
  const played = playedOf(m);
  const gear = app.state.gear.find((g) => g.musicianId === m.id && gearModel(g.model).type === played.type);
  const level = steps(played.skill, cat().rules.skillLevels);
  const mood = m.wellbeing?.level ?? 'green';
  const own = ownGear(m, played.type);
  const better = app.state.gear.some((g) => g.upgrades?.some((u) => u.musicianId === m.id));
  const split = sortedGenres(m.genres).map((g, i, all) => `var(--lb-c-${g}) ${(i * 100) / all.length}% ${((i + 1) * 100) / all.length}%`).join(', ');
  return `
    <div class="lb-line">
    <div class="lb-mr" data-member-row="${m.id}">
      <a class="lb-ph ${mood}" href="#/musician/${m.id}" style="--split:linear-gradient(120deg, ${split})" title="${esc(m.firstName)} ansehen">${photo(m)}</a>
      <div class="lb-info">
        <div class="lb-nm"><a href="#/musician/${m.id}">${esc(m.firstName)} ${esc(m.lastName)}</a>${moodTag(mood)}
          <span class="lb-traitrow">${m.personality.map((p) => `<button class="lb-trait" data-act="tip" data-tip="${esc(cat().personalities[p])}: ${esc(cat().personalityInfo[p])}" title="${esc(cat().personalities[p])}: ${esc(cat().personalityInfo[p])}">${esc(cat().personalities[p])}</button>`).join('')}</span></div>
        <div class="lb-ins lv-${Math.min(level, MAX_LV_STYLE)}">
          ${m.instruments.length > 1
    ? `<button class="ico switch" data-act="slotCycle" data-band="${b.id}" data-musician="${m.id}" title="${esc(instrumentName(played.type))} – tippen, um ein anderes Instrument zu wählen">${instrumentIcon(played.type, own.electric, own.hue)}<i>⇄</i></button>`
    : `<span class="ico" data-act="tip" data-tip="${esc(instrumentName(played.type))}">${instrumentIcon(played.type, own.electric, own.hue)}</span>`}
          <span class="sk" title="Können ${fmt0(played.skill)}"><i style="width:${levelProgress(played.skill)}%"></i></span>
          <b class="lv" data-act="tip" data-tip="Stufe ${level}: ${esc(cat().levelWords[level])} (Können ${fmt0(played.skill)})">${level}</b>
        </div>
      </div>
      <div class="lb-genres" style="--slots:${slots.length}">${slots.map((g) => (m.genres.includes(g) ? genreTag(g) : '<span class="lb-gt none"></span>')).join('')}</div>
      <div class="lb-rels">${members.length > 1 ? relationsOf(b, m, members) : ''}</div>
      <button class="icon-btn lb-x" data-act="removeMember" data-band="${b.id}" data-musician="${m.id}" title="Aus der Band nehmen">✕</button>
    </div>
    <button class="lb-gear ${gear ? '' : 'empty'}" data-act="pickGear" data-musician="${m.id}" data-type="${played.type}" title="${gear ? esc(gearModel(gear.model).name) : `Kein ${esc(instrumentName(played.type))}`}">
      <span class="art">${gear ? instrumentIcon(played.type, gearModel(gear.model).electric, null) : '+'}</span>
      <small>${gear ? esc(gearModel(gear.model).name) : 'Instrument'}</small>${better ? '<em title="Im Lager liegt ein passendes Instrument">▲</em>' : ''}
    </button>
    </div>`;
}

function history(b) {
  const list = app.state.history.filter((h) => h.bandId === b.id).slice(0, 12);
  return list.length
    ? `<ul class="lb-hist">${list.map((h) => `<li><span>${esc(h.text)}</span></li>`).join('')}</ul>`
    : '<p class="muted small">Noch nichts passiert.</p>';
}

const WISH_BUTTON = { gig: ['Auftritt buchen', 'auftritte'], rehearsal: ['Proben', 'uebung'], record: ['Aufnehmen', 'studio'] };

/** Wünsche der Band: wer unzufrieden ist, sagt, was ihm gefällt; ein Tipp führt zur passenden Anordnung. */
function wishBlock(b, members) {
  const wishes = (b.mood.wishes ?? []).slice(0, 3);
  if (!wishes.length) return '';
  return `
    <section class="lb-wishes"><h2 class="lb-cap">Wünsche der Band</h2>
      ${wishes.map((w) => {
        const m = musicianById(w.musicianId);
        const [label, section] = WISH_BUTTON[w.action] ?? ['Einkaufen', null];
        const target = section ? `#/band/${b.id}/${section}` : '#/packs';
        // Proben startet gleich, die übrigen führen zur passenden Seite.
        const go = w.action === 'rehearsal'
          ? `<button class="lb-pill" data-act="rehearseAsk" data-id="${b.id}" ${blocker(b, members) ? 'disabled' : ''}>${label}</button>`
          : `<a class="lb-pill" href="${target}">${label}</a>`;
        return `<div class="lb-wish"><a class="lb-ph sm ${w.level}" href="#/musician/${m.id}">${photo(m)}</a><span class="say">${esc(w.text)}</span>${go}</div>`;
      }).join('')}
    </section>`;
}

/** Eine Anordnung: gesperrt, wenn ein roter Musiker sie ablehnt; sonst mit Hinweis, wem sie nicht gefällt. */
function act(b, members, type, section, title, text, icon, warn = false, busy = false) {
  const r = b.mood.reactions?.[type] ?? { like: [], dislike: [], locked: false };
  const names = (ids) => ids.map((id) => musicianById(id)?.firstName).filter(Boolean).join(', ');
  const plural = (ids, one, many) => (ids.length > 1 ? many : one);
  if (r.locked) {
    const red = r.dislike.filter((id) => musicianById(id)?.wellbeing?.level === 'red');
    return `<div class="lb-act lock" data-act="tip" data-tip="${esc(names(red))} ${plural(red, 'hat', 'haben')} darauf gerade keine Lust. Erst die Stimmung mit etwas heben, das der Band gefällt.">${icon}<b>${title}</b><small>${esc(names(red))} ${plural(red, 'hat', 'haben')} keine Lust darauf.</small></div>`;
  }
  const hint = r.dislike.length ? `<small class="no">${esc(names(r.dislike))} ${plural(r.dislike, 'mag', 'mögen')} das nicht.</small>` : `<small>${text}</small>`;
  // Ohne Unterseite: Die Anordnung startet gleich (Proben).
  if (!section) {
    return `<button class="lb-act ${busy ? 'idle' : ''}" data-act="rehearseAsk" data-id="${b.id}" ${busy ? 'disabled' : ''}>${icon}<b>${title}</b>${busy ? '<small>Die Band ist gerade beschäftigt.</small>' : hint}</button>`;
  }
  return `<a class="lb-act ${warn ? 'warn' : ''}" href="#/band/${b.id}/${section}">${icon}<b>${title}</b>${hint}</a>`;
}

/**
 * Läuft eine Probe, sieht man ihr zu: je Musiker, was schon gelernt wurde, und wie die Stimmung gerade ist.
 * Die Zahlen laufen sekündlich mit (tickLive); alle paar Sekunden kommt der echte Stand vom Server.
 */
function liveBlock(b, members, act) {
  const left = act.endsAt - serverNow();
  const who = musicianById(act.rehearsal.limit.id);
  const stems = bandStems(members, b, act.id);
  const electric = new Map(members.map((m, i) => [m.id, /elektrisch/.test(stemUrl(stems[i]) ?? '')]));
  return `
    <section class="lb-live" data-live-act="${act.id}">
      <div class="lb-live-head">
        <div><h2 class="lb-cap">Die Band probt</h2>
          <small class="muted">Seit <span data-live-since="${act.startedAt}"></span> · spätestens zu Ende in <b data-live-left="${act.endsAt}">${duration(left)}</b> (${esc(who?.firstName ?? '')}${act.rehearsal.limit.why === 'red' ? ' wird genervt' : ' hat dann keine Lust mehr'})</small></div>
        <span class="lb-live-btns"><button class="btn small ghost" data-act="liveSound" data-id="${act.id}">${music?.actId === act.id ? 'Ton aus' : 'Ton an'}</button>
          <button class="btn small primary" data-act="rehearsalStop" data-id="${act.id}">Probe beenden</button></span>
      </div>
      ${stage(members, {
    bpm: cat().genres[sortedGenres(genresOf(b))[0]]?.bpm ?? 120,
    electricOf: (m) => electric.get(m.id),
    hueOf: (m) => ownGear(m, playedInstrument(m)).hue,
    instrumentOf: (m) => (hasGear(m) ? playedInstrument(m) : 'none'),
    nameOf: (m) => `<i class="lb-glow ${m.wellbeing?.level ?? 'green'}" data-live-glow="${m.id}"></i>${esc(m.firstName)}`,
    below: (m) => `<span class="lb-lv lv-${Math.min(steps(playedOf(m).skill, cat().rules.skillLevels), MAX_LV_STYLE)}"><span class="lb-prog" title="Weg zur nächsten Stufe"><i data-live-bar="${m.id}"></i></span><em data-live-gain="${m.id}">+0,00</em></span>`,
  })}
    </section>`;
}

// ---------- Ton der Probe ----------

let music = null;

function stopMusic() {
  music?.player?.stop();
  music = null;
}

/** Die Aufnahmen der Band laufen in Schleife mit, passend zur Serverzeit. Braucht einen Tipp des Spielers (Browser-Regel). */
function startMusic(act) {
  stopMusic();
  const band = bandById(act.bandId);
  const players = band.memberIds.map(musicianById).filter(Boolean);
  const groups = bandStems(players, band, act.id).map((stem) => [stem]);
  const mine = { actId: act.id, player: null };
  music = mine;
  loadStems(groups).then((buffers) => {
    if (!buffers || music !== mine) return;
    const elapsed = serverNow() - act.startedAt;
    mine.player = playStems(buffers, getAudio().currentTime + 0.05, { offset: elapsed % cat().rules.songSeconds, loop: true, volume: 0.8 });
  });
}

/** Live-Anzeige der laufenden Probe aus dem letzten Spielstand und der Zeit seither. */
export function tickLive() {
  const root = document.querySelector('[data-live-act]');
  const act = root && app.state?.running.find((a) => a.id === Number(root.dataset.liveAct));
  if (!act) {
    if (music) stopMusic();
    return;
  }
  const rules = cat().rules;
  const unit = rules.rehearsalSeconds;
  const now = serverNow();
  const run = Math.max(0, Math.min(now, act.endsAt) - app.state.now);
  const band = bandById(act.bandId);
  const left = root.querySelector('[data-live-left]');
  if (left) left.textContent = duration(Math.max(0, act.endsAt - now));
  const sinceEl = root.querySelector('[data-live-since]');
  if (sinceEl) sinceEl.textContent = duration(Math.max(0, now - act.startedAt));
  for (const id of band.memberIds) {
    const m = musicianById(id);
    const credited = Number(act.rehearsal.credited[id] ?? 0);
    const gain = credited + ((m.gains?.rehearsal ?? 0) / unit) * run;
    const gainEl = root.querySelector(`[data-live-gain="${id}"]`);
    if (gainEl) gainEl.textContent = `+${fmtGain(gain)}`;
    const played = playedOf(m);
    const skillNow = played.skill + ((m.gains?.rehearsal ?? 0) / unit) * run;
    const lvl = Math.min(steps(skillNow, rules.skillLevels), MAX_LV_STYLE);
    for (const el of [root.querySelector(`[data-live-bar="${id}"]`)?.closest('.lb-lv'), document.querySelector(`[data-member-row="${id}"] .lb-ins`)]) {
      if (el) el.className = el.className.replace(/lv-\d/, `lv-${lvl}`);
    }
    const barEl = root.querySelector(`[data-live-bar="${id}"]`);
    if (barEl) barEl.style.width = `${levelProgress(skillNow)}%`;
    const bar = document.querySelector(`[data-member-row="${id}"] .sk i`);
    if (bar) bar.style.width = `${levelProgress(skillNow)}%`;
    const score = Math.max(-1, Math.min(1, (band.mood.reactions?.rehearsal.like.includes(id) ? 1 : 0) - (band.mood.reactions?.rehearsal.dislike.includes(id) ? 1 : 0)));
    const w = Math.max(0, Math.min(100, (m.wellbeing?.value ?? 60) + (rules.reactStep * score * run) / unit));
    const level = w >= rules.moodGreen ? 'green' : w >= rules.moodYellow ? 'yellow' : 'red';
    const glow = root.querySelector(`[data-live-glow="${id}"]`);
    if (glow) glow.className = `lb-glow ${level}`;
    const row = document.querySelector(`[data-member-row="${id}"]`);
    if (row) {
      const ph = row.querySelector('.lb-ph');
      if (ph) ph.className = `lb-ph ${level}`;
      const tag = row.querySelector('.lb-nm .lb-mood');
      if (tag && !tag.classList.contains(level)) tag.outerHTML = moodTag(level);
    }
  }
}

export function page(route) {
  const b = bandById(route.id);
  if (!b) return '<div class="empty">Diese Band gibt es nicht mehr.</div>';
  rememberBand(b.id);
  const members = b.memberIds.map(musicianById).filter(Boolean);
  const solo = members.length === 1;
  const running = runningFor(b.id);
  const notice = running?.type === 'rehearsal' ? '' : blockerNotice(b, members);
  const free = cat().rules.maxMembers - members.length;
  const base = `#/band/${b.id}`;
  const courses = courseTiles(members);
  const course = courses ? `<details class="lb-more"><summary>Kurs des Tages</summary><div class="lb-course">${courses}</div></details>` : '';
  return `
    <header class="lb-hd" style="--g:var(--lb-c-${sortedGenres(genresOf(b))[0] ?? 'pop'})">
      <div>
        <h1>${esc(b.name)} <button class="icon-btn" data-act="bandRename" data-id="${b.id}" title="${b.released ? 'Namensänderung beantragen' : 'Umbenennen'}">✎</button></h1>
        <small>${solo ? 'Solokünstler' : esc(b.title ?? 'Band')} · ${genresOf(b).length ? sortedGenres(genresOf(b)).map((g) => esc(genreName(g))).join(' · ') : 'gemischt'}</small>
      </div>
      <div class="lb-hd-mood"><span class="lb-cap">Stimmung</span>${moodTag(b.mood.level).replace('class="lb-mood', 'class="lb-mood big')}</div>
    </header>
    ${notice}
    ${running?.type === 'rehearsal' ? liveBlock(b, members, running) : ''}
    ${wishBlock(b, members)}
    <h2 class="lb-cap lb-sec">Anordnungen</h2>
    <div class="lb-acts">
      ${act(b, members, 'rehearsal', null, 'Proben', `bis zu ${mins(b.options.rehearsal.seconds)} · Können ${gainRange(members.map((m) => m.gains?.rehearsal ?? 0))} je ${mins(cat().rules.rehearsalSeconds)}`, ICON.rehearse, false, !!blocker(b, members))}
      ${act(b, members, 'record', 'studio', 'Aufnehmen', `${b.mood.songs} ${b.mood.songs === 1 ? 'Song wartet' : 'Songs warten'}`, ICON.record, b.mood.songs >= 12)}
      ${act(b, members, 'gig', 'auftritte', 'Auftritt buchen', 'Kosten und Gewinn sehen', ICON.gig)}
    </div>
    ${course}
    <h2 class="lb-cap lb-sec">Musiker</h2>
    <div class="lb-members">
      ${members.map((m) => memberRow(b, m, members, genreSlots(members))).join('')}
      ${free > 0 ? `<button class="lb-addm" data-act="addMember" data-id="${b.id}">+ Musiker aufnehmen</button>` : ''}
    </div>
    <h2 class="lb-cap lb-sec">Werte</h2>
    <div class="lb-sum">
      <div data-act="bandStatInfo" data-key="sound" data-id="${b.id}" data-flash="sound-${b.id}"><small>Sound</small><b>${fmt1(b.sound)}</b></div>
      <div data-act="bandStatInfo" data-key="fame" data-id="${b.id}"><small>Ruhm</small><b>${esc(cat().fameNames[steps(b.fame, cat().rules.fameLevels)])}</b></div>
      <div data-act="bandStatInfo" data-key="together" data-id="${b.id}"><small>Zusammenhalt</small><b>${solo || !b.mood.togetherness ? '–' : signed1(b.mood.togetherness.value)}</b></div>
      <div><small>Stimmung</small><b>${fmt0(b.mood.value)}</b></div>
    </div>
    <details class="lb-more"><summary>Weitere Werte</summary>
      <div class="stat-grid">
        ${statTile('records', b, fmt0(b.records))}
      </div>
    </details>
    <details class="lb-more"><summary>Chronik</summary>${history(b)}</details>
    <div class="lb-foot">
      <a class="btn ghost small" href="${base}/auszeichnungen">Auszeichnungen</a>
      <a class="btn ghost small" href="${base}/promo">Promo</a>
      <button class="btn danger ghost small" data-act="bandDisband" data-id="${b.id}">Band auflösen</button>
    </div>`;
}


/** Wer die Anordnung mag oder nicht mag, als Zeilen für den Dialog. */
function reactionLines(b, type) {
  const r = b.mood.reactions?.[type] ?? { like: [], dislike: [] };
  const names = (ids) => ids.map((id) => esc(musicianById(id)?.firstName ?? '')).join(', ');
  const lines = [];
  if (r.like.length) lines.push(`<li class="good">${names(r.like)} ${r.like.length > 1 ? 'freuen' : 'freut'} sich darüber.</li>`);
  if (r.dislike.length) lines.push(`<li class="bad">${names(r.dislike)} ${r.dislike.length > 1 ? 'mögen' : 'mag'} das nicht.</li>`);
  return lines.join('');
}

defineActions({
  /** Ton der laufenden Probe an oder aus. */
  liveSound({ id }, el) {
    if (music?.actId === Number(id)) {
      stopMusic();
      el.textContent = 'Ton an';
      return;
    }
    const act = app.state.running.find((a) => a.id === Number(id));
    if (act) {
      startMusic(act);
      el.textContent = 'Ton aus';
    }
  },
  /** Laufende Probe beenden: was bis jetzt gelernt wurde, bleibt. */
  async rehearsalStop({ id }) {
    stopMusic();
    await mutate('rehearsalStop', { activityId: Number(id) });
  },
  /** Kurzer Hinweis am Knopf (Genre, Stufe, Instrument, gesperrte Anordnung). */
  tip({ tip }, el) {
    popover(el, tip);
  },
  /** Probe: erst fragen, mit Dauer und Wirkung, dann starten. */
  rehearseAsk({ id }) {
    const b = bandById(id);
    const members = b.memberIds.map(musicianById).filter(Boolean);
    const unit = mins(cat().rules.rehearsalSeconds);
    const skill = members.map((m) => (hasGear(m)
      ? `<li><b>${esc(m.firstName)}</b>: Können <em>+${fmtGain(m.gains?.rehearsal ?? 0)}</em> je ${unit}</li>`
      : `<li class="bad"><b>${esc(m.firstName)}</b> hat kein Instrument, steht nur dabei und lernt nichts.</li>`)).join('');
    const who = musicianById(b.options.rehearsal.limit.id);
    const why = b.options.rehearsal.limit.why === 'red' ? `${esc(who?.firstName ?? '')} wird davon so genervt, dass die Stimmung kippt` : `${esc(who?.firstName ?? '')} hat dann keine Lust mehr`;
    const modal = openModal({
      className: 'lb-ask',
      html: `
        <div class="lb-cap">Anordnung</div>
        <h2>Proben mit ${esc(b.name)}</h2>
        <p class="muted">Die Band geht in den Proberaum und probt, bis jemand keine Lust mehr hat – hier nach etwa <b>${mins(b.options.rehearsal.seconds)}</b> (${why}). Du kannst jederzeit beenden und behältst alles, was bis dahin gelernt wurde.</p>
        <h3>Was es bringt</h3>
        <ul class="lb-ask-list">${skill}<li>Die Beziehungen untereinander wachsen.</li>${reactionLines(b, 'rehearsal')}</ul>
        <div class="dialog-actions">
          <button class="btn ghost" data-cancel>Abbrechen</button>
          <button class="btn primary" data-go>Proben starten</button>
        </div>`,
    });
    modal.body.querySelector('[data-cancel]').addEventListener('click', () => modal.close());
    modal.body.querySelector('[data-go]').addEventListener('click', async () => {
      modal.close();
      closePopover();
      await actions.startActivity({ id: String(b.id), type: 'rehearsal' });
      // Der Tipp aufs Starten erlaubt dem Browser den Ton.
      const act = runningFor(b.id);
      if (act?.type === 'rehearsal') startMusic(act);
    });
  },
  /** Beziehung antippen: wer mit wem, in Worten. */
  relInfo({ a, b, band }, el) {
    const x = musicianById(a);
    const y = musicianById(b);
    const v = Math.round(bandById(band)?.mood.relations?.[Number(a) < Number(b) ? `${a}-${b}` : `${b}-${a}`] ?? 0);
    popover(el, `${x.firstName} und ${y.firstName} ${relWord(v)} (${signed(v)}).`);
  },
  /** Nächstes Instrument des Musikers in der Band wählen (mit der gewohnten Rückfrage). */
  slotCycle({ band, musician }, el) {
    const m = musicianById(musician);
    const next = (m.bandSlot % m.instruments.length) + 1;
    return actions.bandSetSlot(String(next), el);
  },
});
