import { api } from '../api.js';
import { abilityInfo, genreGlyph, genreName, gearModel, instrumentIcon, instrumentName, ownGear, photo, sortedGenres } from '../cards.js';
import { actions, app, bandById, cat, defineActions, isBusy, mutate, musicianById, runningFor, serverNow } from '../core.js';
import { closePopover, confirmDialog, duration, esc, fmt0, fmt1, fmtGain, money, off, openModal, popover, toast } from '../ui.js';
import { getAudio } from '../audio.js';
import { playedInstrument, stage } from '../figure.js';
import { bandStems, loadStems, playStems, stemUrl } from '../music.js';
import { blocker, blockerNotice, courseTiles, gainRange, statTile } from './bands.js';
import { roomSvg } from '../room.js';
import { crowdMeter, venueArt } from '../venues.js';
import { crowdSvg, stageSvg } from '../stage.js';

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

const MOOD_TIP = {
  green: 'Gut drauf',
  yellow: 'Angespannt',
  red: 'Unzufrieden · macht nichts mehr',
};

/** Was ein Genre der Band gerade bringt, so kurz wie möglich: Bonus, was fehlt, oder wer es mag. */
function genreTip(b, members, g) {
  const name = genreName(g);
  const needs = cat().genreLineup[g].map(([options, need]) => `${need > 1 ? `${need} × ` : ''}${options.map(instrumentName).join(' oder ')}`).join(', ');
  if (members.length === 1) {
    const r = cat().rules;
    if (b.genres.includes(g)) return `${name} · Sound +${Math.round((b.genreBonus - 1) * 100)} %`;
    if (r.soloGenres.includes(g)) return `${name} · Bonus mit ${needs}`;
    return `${name} · solo kein Bonus`;
  }
  if (b.genres.includes(g)) return `${name} · Sound +${Math.round((b.genreBonus - 1) * 100)} %`;
  const check = b.genreChecks?.[g];
  const fans = members.filter((m) => m.genres.includes(g)).map((m) => m.stageName ?? m.firstName);
  if (check?.allFans) {
    const missing = check.requirements.filter((r) => !r.met).map((r) => `${r.need - r.have > 1 ? `${r.need - r.have} × ` : ''}${r.instruments.map(instrumentName).join(' oder ')}`);
    return `${name} · fehlt: ${missing.join(', ')}`;
  }
  return `${name} · nur ${fans.join(', ')}`;
}

/**
 * Knopf einer Band in der Künstlerleiste. Links die Plakette: leer ohne Genre-Bonus, mit Bonus im Material des Genres (Hybrid: zwei, Crossover: drei).
 * Oben rechts die Studiolampe (Stimmung), Name mit Ruhm-Sternen, je Mitglied das Instrument (blass, wer keins hat), unten alle Genres der Band
 * (deckend, was alle mögen; gestrichelt, was nur einzelne mitbringen). Ein Wunsch sitzt als Sprechblase oben rechts auf der Ecke. Alles darauf erklärt sich beim Antippen;
 * die übrige Fläche öffnet die Band.
 */
function tile(b, selected) {
  const away = isBusy(b.busyUntil) || !!runningFor(b.id);
  const solo = b.memberIds.length === 1;
  const bonusGenres = b.genreBonus > 1 ? sortedGenres(b.genres) : [];
  const members = b.memberIds.map(musicianById).filter(Boolean);
  const stars = Math.min(3, steps(b.fame, cat().rules.fameLevels));
  const tip = (text) => `data-act="tip" data-tip="${esc(text)}" title="${esc(text)}"`;
  const ins = members.map((m) => {
    const type = playedInstrument(m);
    const on = hasGear(m);
    return `<i class="ins ${on ? '' : 'off'}" ${tip(`${m.stageName ?? m.firstName} · ${instrumentName(type)}${on ? '' : ' fehlt'}`)}>${instrumentIcon(type)}</i>`;
  }).join('');
  const present = sortedGenres([...new Set(members.flatMap((m) => m.genres))]);
  const shared = (g) => members.every((m) => m.genres.includes(g));
  const genres = present.map((g) => `<i class="gen g-${g} ${shared(g) ? '' : 'part'}" ${tip(genreTip(b, members, g))}>${genreGlyph(g)}</i>`).join('');
  const plaqueTip = bonusGenres.length
    ? `${b.title ?? genreName(bonusGenres[0])} · Sound +${Math.round((b.genreBonus - 1) * 100)} %`
    : solo ? 'Kein Genre-Bonus · solo nur Pop, Elektro' : 'Kein Genre-Bonus';
  const plaque = `<span class="plq ${bonusGenres.length ? 'on' : ''}" ${tip(plaqueTip)}>${bonusGenres.map((g) => `<i class="tex t-${g}"></i>`).join('')}</span>`;
  const songsText = b.mood.songRate > 0 ? `~${String(Math.round(b.mood.songRate * 10) / 10).replace('.', ',')} Songs / Stunde` : 'Schreibt gerade nicht';
  const wish = b.mood.wishes?.[0];
  return `
    <a href="#/band/${b.id}" data-nav="b${b.id}" draggable="false" class="lb-bt ${selected ? 'sel' : ''} ${away ? 'busy' : ''} ${b.mood.level === 'red' ? 'frozen' : ''}">
      ${plaque}
      <span class="lamp ${b.mood.level}" ${tip(MOOD_TIP[b.mood.level] ?? '')}><i></i></span>
      <span class="nm"><span class="nm-t">${esc(b.name)}</span><span class="stars" ${tip(`Ruhm: ${cat().fameNames[stars] ?? ''}`)}>${[0, 1, 2].map((i) => `<i class="${i < stars ? 'on' : ''}">★</i>`).join('')}</span></span>
      <span class="gl"><span class="inss">${ins}</span>${b.mood.level === 'red' ? '<small class="waits">wartet auf dich</small>' : ''}</span>
      <span class="gens">${genres}</span>
      <span class="songs ${b.mood.songRate > 0 ? 'writing' : ''}" style="--cycle:${b.mood.songRate > 0 ? Math.min(6, Math.max(0.9, 1.6 / b.mood.songRate)).toFixed(2) : 0}s" ${tip(songsText)}><svg viewBox="0 0 24 24" aria-hidden="true"><path class="ghost" d="M9 18a3 3 0 11-6 0 3 3 0 016 0 L9 5 L20 3 L20 16 a3 3 0 11-6 0 3 3 0 016 0"/><path class="draw" pathLength="100" d="M9 18a3 3 0 11-6 0 3 3 0 016 0 L9 5 L20 3 L20 16 a3 3 0 11-6 0 3 3 0 016 0"/></svg><b>${b.mood.songs}</b></span>
      ${wish ? `<span class="wishb ${wish.level}" style="--off:-${((b.id * 0.61) % 1.5).toFixed(2)}s" ${tip(`„${wish.text}“`)}><svg viewBox="0 0 30 28" aria-hidden="true"><path d="M15 2C22.2 2 28 6.9 28 13s-5.8 11-13 11c-1.6 0-3.1-.2-4.5-.7L3 26.5l3.1-5.8C3.6 18.7 2 16 2 13 2 6.9 7.8 2 15 2Z"/><circle class="d1" cx="9.6" cy="13" r="1.3"/><circle class="d2" cx="15" cy="13" r="1.3"/><circle class="d3" cx="20.4" cy="13" r="1.3"/></svg></span>` : ''}
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
        <a class="lb-add" href="#/einsendungen" title="Neue Künstler kommen über die Einsendungen. Bands bildest du, indem du Kacheln aufeinanderziehst.">+ Neue Künstler</a>
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
      <a class="lb-ph ${mood}" data-act="musicianInfo" data-id="${m.id}" style="--split:linear-gradient(120deg, ${split})" title="${esc(m.firstName)} ansehen">${photo(m)}</a>
      <div class="lb-info">
        <div class="lb-nm"><a data-act="musicianInfo" data-id="${m.id}">${m.stageName ? `${esc(m.stageName)} <small class="muted">${esc(m.firstName)} ${esc(m.lastName)}</small>` : `${esc(m.firstName)} ${esc(m.lastName)}`}</a>${moodTag(mood)}
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
      ${members.length > 1 ? `<button class="icon-btn lb-x" data-act="removeMember" data-band="${b.id}" data-musician="${m.id}" title="Aus der Band nehmen">✕</button>` : ''}
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
    <section class="lb-wishes"><h2 class="lb-cap">${members.length === 1 ? 'Wünsche' : 'Wünsche der Band'}</h2>
      ${wishes.map((w) => {
        const m = musicianById(w.musicianId);
        const [label, section] = WISH_BUTTON[w.action] ?? ['Music Store', null];
        const target = section ? `#/band/${b.id}/${section}` : '#/store';
        // Proben startet gleich, die übrigen führen zur passenden Seite.
        const go = w.action === 'stageName'
          ? `<button class="lb-pill" data-act="stageNameAsk" data-id="${m.id}">Namen aussuchen</button>`
          : w.action === 'rehearsal' || w.action === 'gig'
          ? `<button class="lb-pill" data-act="${w.action === 'gig' ? 'gigAsk' : 'rehearseAsk'}" data-id="${b.id}" ${off(blocker(b, members)?.text)}>${label}</button>`
          : `<a class="lb-pill" href="${target}">${label}</a>`;
        return `<div class="lb-wish"><a class="lb-ph sm ${w.level}" data-act="musicianInfo" data-id="${m.id}">${photo(m)}</a><span class="say">${esc(w.text)}</span>${go}</div>`;
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
    return `<div class="lb-act lock" data-act="tip" data-tip="${esc(names(red))}: keine Lust · erst Stimmung heben">${icon}<b>${title}</b><small>${esc(names(red))} ${plural(red, 'hat', 'haben')} keine Lust darauf.</small></div>`;
  }
  const hint = r.dislike.length ? `<small class="no">${esc(names(r.dislike))} ${plural(r.dislike, 'mag', 'mögen')} das nicht.</small>` : `<small>${text}</small>`;
  // Ohne Unterseite: Die Anordnung startet gleich (Proben).
  if (!section) {
    const why = busy ? (blocker(b, members)?.text ?? (members.length === 1 ? 'Gerade beschäftigt.' : 'Die Band ist gerade beschäftigt.')) : '';
    return `<button class="lb-act ${busy ? 'idle' : ''}" data-act="${type === 'gig' ? 'gigAsk' : 'rehearseAsk'}" data-id="${b.id}" ${off(why)}>${icon}<b>${title}</b>${busy ? `<small>${members.length === 1 ? 'Gerade beschäftigt.' : 'Die Band ist gerade beschäftigt.'}</small>` : hint}</button>`;
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
        <div><h2 class="lb-cap">${members.length === 1 ? 'Probt' : 'Die Band probt'}</h2>
          <small class="muted">Seit <span data-live-since="${act.startedAt}"></span> · spätestens zu Ende in <b data-live-left="${act.endsAt}">${duration(left)}</b> (${esc(who?.firstName ?? '')}${act.rehearsal.limit.why === 'red' ? ' wird genervt' : ' hat dann keine Lust mehr'})</small></div>
        <span class="lb-live-btns"><button class="btn small ghost" data-act="liveSound" data-id="${act.id}">${music?.actId === act.id ? 'Ton aus' : 'Ton an'}</button>
          <button class="btn small primary" data-act="rehearsalStop" data-id="${act.id}">Probe beenden</button></span>
      </div>
      <div class="lb-room">${roomSvg(b.room?.level ?? 0, b.id, b.name)}${stage(members, {
    className: 'in-room',
    act: 'musicianInfo',
    bpm: cat().genres[sortedGenres(genresOf(b))[0]]?.bpm ?? 120,
    electricOf: (m) => electric.get(m.id),
    hueOf: (m) => ownGear(m, playedInstrument(m)).hue,
    instrumentOf: (m) => (hasGear(m) ? playedInstrument(m) : 'none'),
    nameOf: (m) => `<i class="lb-glow ${m.wellbeing?.level ?? 'green'}" data-live-glow="${m.id}"></i>${esc(m.stageName ?? m.firstName)}`,
    below: (m) => (!hasGear(m)
      ? `<span class="lb-lv lb-nogear" title="Ohne Instrument kann ${esc(m.firstName)} nicht mitspielen und lernt nichts"><span class="lb-prog" style="visibility:hidden"><i></i></span><em>${esc(instrumentName(playedOf(m).type))} fehlt</em></span>`
      : `<span class="lb-lv lv-${Math.min(steps(playedOf(m).skill, cat().rules.skillLevels), MAX_LV_STYLE)}"><span class="lb-prog" title="Weg zur nächsten Stufe"><i data-live-bar="${m.id}"></i></span><em data-live-gain="${m.id}">+0,00</em></span>`),
  })}</div>
    </section>`;
}

/** Läuft ein Auftritt, sieht man die Band auf der Bühne des Orts; Zuschauen öffnet das Live-Fenster mit Ton. */
function gigBlock(b, members, act) {
  const stems = bandStems(members, b, act.id);
  const electric = new Map(members.map((m, i) => [m.id, /elektrisch/.test(stemUrl(stems[i]) ?? '')]));
  const venue = act.venue ?? 'kneipe';
  return `
    <section class="lb-live">
      <div class="lb-live-head">
        <div><h2 class="lb-cap">${members.length === 1 ? 'Steht auf der Bühne' : 'Die Band spielt'}</h2>
          <small class="muted">${esc(act.title)} · noch <span class="countdown" data-until="${act.endsAt}"></span></small></div>
        <span class="lb-live-btns"><button class="btn small primary" data-act="watch" data-id="${act.id}">Zuschauen</button></span>
      </div>
      ${venueStage(venue, b.id, members, {
    act: 'musicianInfo',
    bpm: cat().genres[sortedGenres(genresOf(b))[0]]?.bpm ?? 120,
    electricOf: (m) => electric.get(m.id),
    hueOf: (m) => ownGear(m, playedInstrument(m)).hue,
    instrumentOf: (m) => (hasGear(m) ? playedInstrument(m) : 'none'),
  })}
    </section>`;
}

/** Bühne eines Orts mit Band und Publikum davor. */
export function venueStage(venue, seed, members, opts) {
  return `<div class="lb-room lb-venue v-${venue}">${stageSvg(venue, seed)}${stage(members, { ...opts, className: `in-room ${opts.className ?? ''}` })}${crowdSvg(venue, seed)}</div>`;
}

/** Genre-Bonus auf der Bandseite: was die Band hat, und je Genre der Mitglieder, was zum Bonus fehlt. */
function genreSection(b, members) {
  const pc = (x) => `${x >= 1 ? '+' : '−'}${Math.round(Math.abs(x - 1) * 100)} %`;
  const need = ([options, n]) => `${n > 1 ? `${n} × ` : ''}${options.map((t) => `${instrumentIcon(t)} ${esc(instrumentName(t))}`).join(' oder ')}`;
  const genres = sortedGenres([...new Set(members.flatMap((m) => m.genres))]);
  const head = members.length === 1
    ? (b.title ? `<b>${esc(b.title)}</b>: Sound ${pc(b.genreBonus)} (solo gibt es den halben Bonus, und nur für ${cat().rules.soloGenres.map(genreName).join(' und ')}).` : `Solo gibt es den Genre-Bonus nur für ${cat().rules.soloGenres.map(genreName).join(' und ')}, halb so groß wie in einer Band. Ab zwei Mitgliedern zählt: Mögen alle dasselbe Genre und ist die Besetzung dafür da, klingt die Band besser.`)
    : b.title ? `<b>${esc(b.title)}</b>: Sound ${pc(b.genreBonus)}.`
      : b.genreBonus < 1 ? `Gemischte Band: Niemand teilt ein Genre mit allen anderen, Sound ${pc(b.genreBonus)}.` : 'Alle teilen ein Genre, aber die Besetzung fehlt noch: kein Bonus, kein Abzug.';
  const rows = genres.map((g) => {
    const check = b.genreChecks?.[g];
    const fans = members.filter((m) => m.genres.includes(g));
    const reached = b.genres.includes(g);
    return `<div class="lb-gb-row ${reached ? 'on' : ''}">${genreTag(g)}<b class="lb-gb-name">${esc(genreName(g))}</b>
      <span class="${fans.length === members.length ? 'ok' : 'muted'}">${members.length === 1 ? 'mag es' : `${fans.length} von ${members.length} mögen es`}</span>
      <span class="lb-gb-needs">${cat().genreLineup[g].map((req, i) => {
        const met = check?.requirements?.[i]?.met;
        return `<span class="${met ? 'ok' : 'warn'}">${met ? '✓' : '✗'} ${need(req)}</span>`;
      }).join('')}</span>
      ${reached ? '<em class="ok">Bonus</em>' : ''}</div>`;
  }).join('');
  return `
    <h2 class="lb-cap lb-sec">Genre-Bonus <button class="lb-linkbtn" data-act="genreOverview">Welche Band braucht was?</button></h2>
    <section class="lb-gb"><p class="muted small">${head}</p>${rows}</section>`;
}

/** Übersicht aller Genre-Bands: Besetzung und Bonus. */
function openGenreOverview() {
  const r = cat().rules;
  const pc = (x) => `${x >= 1 ? '+' : '−'}${Math.round(Math.abs(x - 1) * 100)} %`;
  const need = ([options, n]) => `${n > 1 ? `${n} × ` : ''}${options.map((t) => `${instrumentIcon(t)} ${esc(instrumentName(t))}`).join(' oder ')}`;
  const modal = openModal({
    wide: true,
    className: 'lb-gb-modal',
    html: `
      <div class="lb-cap">Genre-Bonus</div>
      <h2>Welche Band braucht was?</h2>
      <p class="muted">Eine Band bekommt den Bonus, wenn <b>alle</b> Mitglieder das Genre mögen <b>und</b> die Besetzung dafür da ist. Solokünstler bekommen ihn nur für ${cat().rules.soloGenres.map(genreName).join(' und ')}, und nur halb (Sound ${pc(1 + (r.genreBonus - 1) * r.soloGenreShare)}).</p>
      <ul class="lb-gb-steps">
        <li><b>Genre-Band</b>: Sound ${pc(r.genreBonus)}</li>
        <li><b>Hybrid</b> (dasselbe auch für ein zweites Genre): ${pc(r.hybridBonus)}</li>
        <li><b>Crossover</b> (sogar ein drittes): ${pc(r.crossoverBonus)}</li>
        <li><b>Gemischt</b> (niemand teilt ein Genre mit allen): ${pc(r.mixedBonus)}</li>
      </ul>
      <div class="lb-gb-table">${Object.entries(cat().genreLineup).map(([g, reqs]) => `<div class="lb-gb-row">${genreTag(g)}<b class="lb-gb-name">${esc(genreName(g))}</b><span class="lb-gb-needs">${reqs.map((q) => `<span>${need(q)}</span>`).join('')}</span></div>`).join('')}</div>
      <div class="dialog-actions"><button class="btn primary" data-close>OK</button></div>`,
  });
  modal.body.querySelector('[data-close]').addEventListener('click', () => modal.close());
}

const pct = (x) => `${Math.round((x - 1) * 100)} %`;

/** Proberaum: die Band in ihrem Raum (ohne laufende Probe), Stufe, Wirkung und der nächste Ausbau. */
function roomBlock(b, members) {
  const room = b.room;
  if (!room) return '';
  const effect = room.level ? `Proben bringen ${pct(room.skill)} mehr Können, die Band hält ${pct(room.lust)} länger durch${room.mood ? ', alle fühlen sich wohler' : ''}.` : 'Dunkel, feucht, kaputt. Hier probt niemand gern lange.';
  return `
    <section class="lb-roomcard">
      <div class="lb-room idle">${roomSvg(room.level, b.id, b.name)}${stage(members, {
    className: 'idle in-room',
    act: 'musicianInfo',
    instrumentOf: (m) => (hasGear(m) ? playedInstrument(m) : 'none'),
    hueOf: (m) => ownGear(m, playedInstrument(m)).hue,
    nameOf: (m) => esc(m.stageName ?? m.firstName),
  })}</div>
      <div class="lb-roominfo">
        <div><h2 class="lb-cap">${room.solo ? 'Eigene Übungsecke' : 'Proberaum'}</h2><b>${esc(room.stage)}</b> <small class="muted">Stufe ${room.level} von 3</small>
          <p class="muted small">${effect}</p></div>
        ${room.next ? `<button class="btn small" data-act="roomUpgrade" data-id="${b.id}" ${off(app.state.label.money < room.next.cost ? `Dafür fehlen noch ${fmt0(room.next.cost - app.state.label.money)} ₲.` : '')}>Ausbauen: ${esc(room.next.name)} · ${fmt0(room.next.cost)} ₲</button>` : '<span class="muted small">voll ausgebaut</span>'}
      </div>
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
  // Wer kein Instrument hat, spielt nichts: seine Spur bleibt stumm.
  const players = band.memberIds.map(musicianById).filter(Boolean).filter(hasGear);
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
  const notice = running?.type === 'rehearsal' || running?.type === 'gig' ? '' : blockerNotice(b, members);
  const free = cat().rules.maxMembers - members.length;
  const base = `#/band/${b.id}`;
  const courses = courseTiles(members);
  const course = courses ? `<details class="lb-more"><summary>Kurs des Tages</summary><div class="lb-course">${courses}</div></details>` : '';
  return `
    <header class="lb-hd ${genresOf(b).length ? '' : 'mixed'}" ${genresOf(b).length ? `style="--g:var(--lb-c-${sortedGenres(genresOf(b))[0]})"` : ''}>
      <div>
        <h1>${esc(b.name)} ${solo ? '' : `<button class="icon-btn" data-act="bandRename" data-id="${b.id}" title="Umbenennen">✎</button>`}</h1>
        <small>${solo ? 'Solokünstler' : esc(b.title ?? 'Band')} · ${genresOf(b).length ? sortedGenres(genresOf(b)).map((g) => esc(genreName(g))).join(' · ') : 'gemischt'}</small>
      </div>
      <div class="lb-hd-mood"><span class="lb-cap">Stimmung</span>${moodTag(b.mood.level).replace('class="lb-mood', 'class="lb-mood big')}</div>
    </header>
    ${notice}
    ${running?.type === 'rehearsal' ? liveBlock(b, members, running) : running?.type === 'gig' ? gigBlock(b, members, running) : ''}
    ${wishBlock(b, members)}
    <h2 class="lb-cap lb-sec">Anordnungen</h2>
    <div class="lb-acts">
      ${act(b, members, 'rehearsal', null, 'Proben', `bis zu ${mins(b.options.rehearsal.seconds)} · Können ${gainRange(members.map((m) => m.gains?.rehearsal ?? 0))} je ${mins(cat().rules.rehearsalSeconds)}`, ICON.rehearse, false, !!blocker(b, members))}
      ${act(b, members, 'record', 'studio', 'Aufnehmen', `${b.mood.songs} ${b.mood.songs === 1 ? 'Song wartet' : 'Songs warten'}`, ICON.record, b.mood.songs >= 12)}
      ${act(b, members, 'gig', null, 'Auftritt buchen', 'Orte, Kosten und Gewinn sehen', ICON.gig, false, !!blocker(b, members))}
    </div>
    ${course}
    ${running ? '' : roomBlock(b, members)}
    <h2 class="lb-cap lb-sec">Musiker</h2>
    <div class="lb-members">
      ${members.map((m) => memberRow(b, m, members, genreSlots(members))).join('')}
      ${free > 0 ? `<button class="lb-addm" data-act="together" data-id="${b.id}">${solo ? '+ mit anderen zusammentun' : '+ mit Musiker(n) zusammentun'}</button>` : ''}
    </div>
    ${genreSection(b, members)}
    <h2 class="lb-cap lb-sec">Werte</h2>
    <div class="lb-sum">
      <div data-act="bandStatInfo" data-key="sound" data-id="${b.id}" data-flash="sound-${b.id}"><small>Sound</small><b>${fmt1(b.sound)}</b></div>
      <div data-act="bandStatInfo" data-key="fame" data-id="${b.id}"><small>Ruhm</small><b>${esc(cat().fameNames[steps(b.fame, cat().rules.fameLevels)])}</b></div>
      ${solo || !b.mood.togetherness ? '' : `<div data-act="bandStatInfo" data-key="together" data-id="${b.id}"><small>Zusammenhalt</small><b>${signed1(b.mood.togetherness.value)}</b></div>`}
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
      <a class="btn ghost small" href="#/wiki/band/${b.id}">WikiSignia</a>
      ${solo
    ? `<button class="btn danger ghost small" data-act="musicianRelease" data-id="${members[0].id}">Künstler entlassen</button>`
    : `<button class="btn danger ghost small" data-act="bandDisband" data-id="${b.id}">Band auflösen</button>`}
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

/** Kleines Fenster zum Musiker: was die Bandseite nicht zeigt (Bekanntheit, Wert, Fähigkeiten, Auskommen mit den anderen). */
function musicianWindow(m) {
  const rules = cat().rules;
  const band = m.bandId ? bandById(m.bandId) : null;
  const nextFame = rules.fameLevels[m.fameLevel];
  const notes = band ? band.chemistryNotes.filter((n) => n.ids.includes(m.id)) : [];
  const ins = m.instruments.map((i) => {
    const lv = steps(i.skill, rules.skillLevels);
    const next = rules.skillLevels[lv];
    return `<li><span class="lb-ins lv-${Math.min(lv, MAX_LV_STYLE)}"><span class="ico">${instrumentIcon(i.type)}</span><span class="sk"><i style="width:${levelProgress(i.skill)}%"></i></span><b class="lv">${lv}</b></span>
      <span>${esc(instrumentName(i.type))}: ${esc(cat().levelNames[lv])} <small class="muted">(${fmt0(i.skill)}${next ? `, nächste Stufe ab ${next}` : ''})</small></span></li>`;
  }).join('');
  const abilities = m.abilities.map((a) => {
    const info = abilityInfo(a);
    return `<li><b>${info.icon} ${esc(info.name)}</b> <small class="muted">${esc(info.desc)}</small></li>`;
  }).join('');
  const modal = openModal({
    className: 'lb-ask lb-mw',
    html: `
      <div class="lb-mw-head"><span class="lb-ph ${m.wellbeing?.level ?? 'green'}">${photo(m)}</span>
        <div><h2>${esc(m.stageName ?? `${m.firstName} ${m.lastName}`)}</h2>${m.stageName ? `<small class="muted">${esc(m.firstName)} ${esc(m.lastName)} · </small>` : ''}<small class="muted">${band && band.memberIds.length > 1 ? esc(band.name) : 'Solokünstler'}</small></div></div>
      ${m.abilityChoice ? `<p class="lb-mw-note">${esc(m.firstName)} ist jetzt ${esc(cat().levelWords[3])} und darf eine dritte Fähigkeit wählen. <button class="btn small primary" data-choose>Wählen</button></p>` : ''}
      <div class="lb-sum">
        <div><small>Bekanntheit</small><b>${esc(cat().fameNames[m.fameLevel])}</b><small class="muted">${fmt1(m.fame)}${nextFame ? ` · nächste Stufe ab ${nextFame}` : ''}</small></div>
        <div><small>Marktwert</small><b>${money(m.value)}</b></div>
      </div>
      <h3 class="lb-cap">Können</h3><ul class="lb-mw-list">${ins}</ul>
      ${abilities ? `<h3 class="lb-cap">Fähigkeiten</h3><ul class="lb-mw-list plain">${abilities}</ul>` : ''}
      ${notes.length ? `<h3 class="lb-cap">Mit den anderen</h3><ul class="lb-mw-list plain">${notes.map((n) => `<li class="${n.good ? 'good' : 'bad'}">${n.good ? '+' : '–'} ${esc(n.text)}</li>`).join('')}</ul>` : ''}
      <div class="dialog-actions"><a class="btn ghost" href="#/wiki/signie/${m.id}" data-close>Artikel in der WikiSignia</a><button class="btn primary" data-close>Schließen</button></div>`,
  });
  modal.body.querySelectorAll('[data-close]').forEach((el) => el.addEventListener('click', () => modal.close()));
  modal.body.querySelector('[data-choose]')?.addEventListener('click', () => {
    modal.close();
    actions.chooseAbility({ id: String(m.id) });
  });
}

/** Wie voll der Ort voraussichtlich wird, in Worten. */
const fillWord = (f) => (f >= 0.9 ? 'voll' : f >= 0.6 ? 'gut besucht' : f >= 0.3 ? 'halb leer' : 'fast leer');
const KNOWN = { some: '<li class="warn">Erst wenige kennen die Band: nicht alle Plätze werden verkauft.</li>', no: '<li class="bad">Kaum jemand kennt die Band: der Ort bleibt fast leer.</li>' };
const FIT = { green: ['ok', 'Sound passt'], yellow: ['knapp', 'Sound knapp: der Saal wird nicht ganz voll'], red: ['bad', 'Sound zu schwach: der Saal bleibt halb leer'] };

/** Auftritt buchen: alle passenden Orte mit Kosten, Gewinn-Schätzung, Ruhm, ob der Sound reicht und wie die Band den Ort findet. */
async function openGigs(b) {
  let data;
  try {
    data = await api('gigOptions', { bandId: b.id });
  } catch (e) {
    toast(e.message, 'error');
    return;
  }
  const members = b.memberIds.map(musicianById).filter(Boolean);
  const r = b.mood.reactions?.gig ?? { like: [], dislike: [] };
  const names = (ids) => ids.map((id) => musicianById(id)?.firstName).filter(Boolean).join(', ');
  const playable = data.options.filter((o) => !o.blocked);
  const best = playable.reduce((m, o) => (!m || o.profit[0] + o.profit[1] > m.profit[0] + m.profit[1] ? o : m), null);
  const strong = cat().rules;
  const range = ([a, c]) => (a === c ? signedMoney(a) : `${signedMoney(a)} bis ${signedMoney(c)}`);
  const row = (o) => {
    const [cls, fitText] = FIT[o.fit];
    const claim = o.claim === 'below' ? `<li class="bad">Unter ihrem Anspruch: die Band ärgert sich.</li>`
      : o.claim === 'above' ? `<li class="good">Eine größere Bühne: die Band freut sich.</li>` : '';
    const tooPoor = app.state.label.money < o.cost;
    return `
      <div class="gg-row ${o.blocked ? 'blocked' : ''} ${o === best ? 'best' : ''}">
        <div class="gg-art">${venueArt(o.key)}${o === best ? '<em class="gg-best">bester Gewinn</em>' : ''}</div>
        <div class="gg-main">
          <div class="gg-head"><b>${esc(o.place)}</b><small>${esc(o.name)} · ${o.songs} Songs · etwa ${o.minutes} Min.</small></div>
          <div class="gg-crowd">${crowdMeter(o.fill ?? 0)}<span>Publikum: <b>${fillWord(o.fill ?? 0)}</b></span></div>
          <ul class="gg-notes"><li class="${cls === 'ok' ? 'good' : cls === 'bad' ? 'bad' : 'warn'}">${fitText}</li>${KNOWN[o.known] ?? ''}${claim}${o.blocked ? `<li class="bad">${esc(o.blocked)}</li>` : ''}</ul>
        </div>
        <div class="gg-side">
          <div class="gg-money">
            <span><small>Kosten</small><b>${money(o.cost)}</b></span>
            <span><small>Gage</small><b>${o.gage[0] === o.gage[1] ? money(o.gage[0]) : `${money(o.gage[0])} – ${money(o.gage[1])}`}</b></span>
            <span class="${o.profit[0] >= 0 ? 'good' : o.profit[1] < 0 ? 'bad' : 'warn'}"><small>Gewinn</small><b>${range(o.profit)}</b></span>
            <span><small>Ruhm</small><b>+${fmt1(o.fame)}</b></span>
          </div>
          <button class="btn ${o === best ? 'primary' : ''} small" data-book="${o.key}" ${off(o.blocked || (tooPoor ? `Dafür fehlen noch ${money(o.cost - app.state.label.money)}.` : ''))}>${tooPoor ? 'Zu teuer' : 'Buchen'}</button>
        </div>
      </div>`;
  };
  const modal = openModal({
    wide: true,
    className: 'gg-modal',
    html: `
      <div class="lb-cap">Anordnung</div>
      <h2>Auftritt buchen für ${esc(b.name)}</h2>
      <p class="muted small">Sound der Band ${fmt1(data.sound)} · ${data.songs} ${data.songs === 1 ? 'Song' : 'Songs'} spielbar (aufgenommen und geschrieben).
        Die Kosten sind fest, der Gewinn hängt vom Abend ab.${r.like.length ? ` ${esc(names(r.like))} ${r.like.length > 1 ? 'freuen' : 'freut'} sich auf die Bühne.` : ''}${r.dislike.length ? ` ${esc(names(r.dislike))} ${r.dislike.length > 1 ? 'mögen' : 'mag'} keine Auftritte.` : ''}</p>
      <div class="gg-list">${data.options.map(row).join('')}</div>`,
  });
  modal.body.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-book]');
    if (!btn) return;
    btn.disabled = true;
    const res = await mutate('activityStart', { bandId: b.id, type: 'gig', venue: btn.dataset.book });
    if (res) {
      modal.close();
      toast(`${b.name} ist auf dem Weg zum Auftritt.`, 'ok');
    } else btn.disabled = false;
  });
}

const signedMoney = (v) => `${v < 0 ? '−' : '+'}${money(Math.abs(v))}`;

defineActions({
  genreOverview() {
    openGenreOverview();
  },
  /** Künstlername: Der Musiker hat sich einen gewünscht – drei Vorschläge, einer wird es. */
  async stageNameAsk({ id }) {
    const m = musicianById(id);
    if (!m) return;
    let options;
    try {
      options = (await api('stageNameOptions', { musicianId: m.id })).options;
    } catch (e) {
      toast(e.message, 'error');
      return;
    }
    const modal = openModal({
      className: 'lb-ask lb-stagename',
      html: `
        <div class="lb-cap">Künstlername</div>
        <h2>${esc(m.firstName)} ${esc(m.lastName)} hätte gern einen Künstlernamen</h2>
        <p class="muted">Unter ihm tritt ${esc(m.firstName)} künftig auf, solo und in jeder Band. Welcher soll es sein? ${esc(m.firstName)} freut sich darüber.</p>
        <div class="lb-stage-opts">${options.map((o) => `<button class="lb-stage-opt" data-name="${esc(o)}">${esc(o)}</button>`).join('')}</div>
        <div class="dialog-actions"><button class="btn ghost" data-close>Später</button></div>`,
    });
    modal.body.querySelector('[data-close]').addEventListener('click', () => modal.close());
    modal.body.querySelectorAll('[data-name]').forEach((btn) => btn.addEventListener('click', async () => {
      btn.disabled = true;
      if (await mutate('stageNameGive', { musicianId: m.id, name: btn.dataset.name })) {
        modal.close();
        toast(`${m.firstName} tritt jetzt als „${btn.dataset.name}“ auf.`, 'ok');
      } else btn.disabled = false;
    }));
  },
  gigAsk({ id }) {
    const b = bandById(id);
    if (b) openGigs(b);
  },
  async roomUpgrade({ id }) {
    const b = bandById(id);
    const next = b.room?.next;
    if (!next) return;
    const ok = await confirmDialog(`${b.room.solo ? 'Übungsecke' : 'Proberaum'} ausbauen zu „${next.name}“ für ${fmt0(next.cost)} ₲? Danach bringen Proben ${pct(next.skill)} mehr Können, die Band hält ${pct(next.lust)} länger durch${next.mood ? ' und fühlt sich beim Proben wohler' : ''}.`, { ok: 'Ausbauen' });
    if (ok) await mutate('roomUpgrade', { bandId: b.id });
  },
  musicianInfo({ id }) {
    musicianById(id) && musicianWindow(musicianById(id));
  },
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
        <p class="muted">${members.length === 1 ? `${esc(members[0].firstName)} geht in den Proberaum und übt` : 'Die Band geht in den Proberaum und probt'}, bis ${members.length === 1 ? 'die Lust vorbei ist' : 'jemand keine Lust mehr hat'} – hier nach etwa <b>${mins(b.options.rehearsal.seconds)}</b> (${why}). Du kannst jederzeit beenden und behältst alles, was bis dahin gelernt wurde.</p>
        <h3>Was es bringt</h3>
        <ul class="lb-ask-list">${skill}${members.length > 1 ? '<li>Die Beziehungen untereinander wachsen.</li>' : ''}${reactionLines(b, 'rehearsal')}</ul>
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
  /** Instrument wechseln: Unter dem Knopf klappen die anderen Instrumente als Symbole mit Können-Leiste auf. */
  slotCycle({ band, musician }, el) {
    const open = document.querySelector('.lb-slotmenu');
    const same = open?.dataset.musician === musician;
    open?.remove();
    if (same) return;
    const m = musicianById(musician);
    const rules = cat().rules;
    const menu = document.createElement('div');
    menu.className = 'lb-slotmenu';
    menu.dataset.musician = musician;
    menu.innerHTML = m.instruments.map((i, idx) => ({ i, idx })).filter(({ idx }) => idx + 1 !== m.bandSlot).map(({ i, idx }) => {
      const lv = steps(i.skill, rules.skillLevels);
      const own = ownGear(m, i.type);
      return `<button class="lb-ins lv-${Math.min(lv, MAX_LV_STYLE)}" data-slot="${idx + 1}" title="${esc(instrumentName(i.type))} (Können ${fmt0(i.skill)})" aria-label="${esc(instrumentName(i.type))}">
        <span class="ico">${instrumentIcon(i.type, own.electric, own.hue)}</span><span class="sk"><i style="width:${levelProgress(i.skill)}%"></i></span><b class="lv">${lv}</b></button>`;
    }).join('');
    document.body.appendChild(menu);
    const r = el.getBoundingClientRect();
    menu.style.left = `${Math.max(8, Math.min(window.innerWidth - menu.offsetWidth - 8, r.left)) + window.scrollX}px`;
    menu.style.top = `${r.bottom + 6 + window.scrollY}px`;
    const close = (e) => {
      if (e && menu.contains(e.target)) return;
      menu.remove();
      document.removeEventListener('pointerdown', close, true);
    };
    setTimeout(() => document.addEventListener('pointerdown', close, true));
    menu.addEventListener('click', (e) => {
      const pick = e.target.closest('[data-slot]');
      if (!pick) return;
      close();
      actions.bandSetSlot(pick.dataset.slot, { dataset: { band, musician }, value: '' });
    });
  },
});
