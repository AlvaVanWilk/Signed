import { genreGlyph, genreName, gearModel, instrumentIcon, instrumentName, ownGear, photo, sortedGenres } from '../cards.js';
import { app, bandById, cat, isBusy, musicianById, runningFor } from '../core.js';
import { esc, fmt0, fmt1 } from '../ui.js';
import { blockerNotice, chemistryLabel, statTile } from './bands.js';

/** Label-Ebene: Band-Leiste links und die ruhige Bandseite. Stimmung als Punkt und Wort (grün, gelb, rot). */

const MOOD_WORD = { green: 'gut', yellow: 'angespannt', red: 'unzufrieden' };
const LAST_KEY = 'signed.lastBand';

export const moodWord = (level) => MOOD_WORD[level] ?? MOOD_WORD.green;

const moodTag = (level) => `<span class="lb-mood ${level}"><i></i>${moodWord(level)}</span>`;
const steps = (value, thresholds) => thresholds.filter((t) => value >= t).length;
const signed = (v) => `${v > 0 ? '+' : v < 0 ? '−' : ''}${fmt0(Math.abs(v))}`;

/** Genre-Marke: Farbe des Genres als Rand und zarte Fläche, das Symbol darin. */
function genreTag(key) {
  return `<span class="lb-gt g-${key}" title="${esc(genreName(key))}">${genreGlyph(key)}</span>`;
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
      <span class="fame" title="Ruhm: ${esc(cat().fameNames[Math.min(pips, 3)] ?? '')}">${[0, 1, 2].map((i) => `<i class="${i < pips ? 'on' : ''}"></i>`).join('')}${b.mood.level === 'red' ? '<small>wartet auf dich</small>' : ''}</span>
      <span class="songs" title="Geschriebene Songs, die auf eine Aufnahme warten"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18V5l11-2v13 M9 18a3 3 0 11-6 0 3 3 0 016 0z M20 16a3 3 0 11-6 0 3 3 0 016 0z"/></svg><b>${b.mood.songs}</b></span>
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

/** Beziehung −10…+10 als Farbe: Grau in der Mitte, zu Grün (gut) oder Rot (schlecht) hin stufenlos. */
function relColor(v) {
  const mix = (a, b, t) => a.map((x, i) => Math.round(x + (b[i] - x) * t));
  return `rgb(${mix([96, 92, 108], v >= 0 ? [92, 170, 130] : [196, 100, 108], Math.min(1, Math.abs(v) / 10)).join(',')})`;
}

function relationsOf(b, m, members) {
  const rel = b.mood.relations ?? {};
  return members.filter((x) => x.id !== m.id).map((x) => {
    const key = m.id < x.id ? `${m.id}-${x.id}` : `${x.id}-${m.id}`;
    const v = Math.round(rel[key] ?? 0);
    return `<span class="lb-rel" style="--rc:${relColor(rel[key] ?? 0)}" title="${esc(m.firstName)} und ${esc(x.firstName)}: ${signed(v)}">${photo(x)}</span>`;
  }).join('');
}

/** Das Instrument, das der Musiker in dieser Band spielt, und sein Platz dafür (Instrument-Wahl, falls er mehrere kann). */
function playedOf(m) {
  const index = m.instruments.length > 1 ? m.bandSlot - 1 : 0;
  return m.instruments[index] ?? m.instruments[0];
}

function memberRow(b, m, members) {
  const played = playedOf(m);
  const gear = app.state.gear.find((g) => g.musicianId === m.id && gearModel(g.model).type === played.type);
  const level = steps(played.skill, cat().rules.skillLevels);
  const mood = m.wellbeing?.level ?? 'green';
  const own = ownGear(m, played.type);
  const better = app.state.gear.some((g) => g.upgrades?.some((u) => u.musicianId === m.id));
  const split = sortedGenres(m.genres).map((g, i, all) => `var(--lb-c-${g}) ${(i * 100) / all.length}% ${((i + 1) * 100) / all.length}%`).join(', ');
  return `
    <div class="lb-line">
    <div class="lb-mr">
      <a class="lb-ph ${mood}" href="#/musician/${m.id}" style="--split:linear-gradient(120deg, ${split})" title="${esc(m.firstName)} ansehen">${photo(m)}</a>
      <div class="lb-info">
        <div class="lb-nm"><a href="#/musician/${m.id}">${esc(m.firstName)} ${esc(m.lastName)}</a>${moodTag(mood)}</div>
        <div class="lb-ins">
          <span class="ico" title="${esc(instrumentName(played.type))}">${instrumentIcon(played.type, own.electric, own.hue)}</span>
          <span class="sk" title="Können ${fmt0(played.skill)}"><i style="width:${Math.min(100, played.skill)}%"></i></span>
          <b class="lv" title="${esc(cat().levelWords[level])}">${level}</b>
          ${m.instruments.length > 1 ? `<select data-change="bandSetSlot" data-band="${b.id}" data-musician="${m.id}" title="Instrument in der Band">${m.instruments.map((i, idx) => `<option value="${idx + 1}" ${i === played ? 'selected' : ''}>${esc(instrumentName(i.type))}</option>`).join('')}</select>` : ''}
        </div>
      </div>
      <div class="lb-genres">${sortedGenres(m.genres).map(genreTag).join('')}</div>
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

export function page(route) {
  const b = bandById(route.id);
  if (!b) return '<div class="empty">Diese Band gibt es nicht mehr.</div>';
  rememberBand(b.id);
  const members = b.memberIds.map(musicianById).filter(Boolean);
  const rel = Object.values(b.mood.relations ?? {});
  const avgRel = rel.length ? rel.reduce((a, v) => a + v, 0) / rel.length : 0;
  const solo = members.length === 1;
  const notice = blockerNotice(b, members);
  const free = cat().rules.maxMembers - members.length;
  const base = `#/band/${b.id}`;
  return `
    <header class="lb-hd" style="--g:var(--lb-c-${sortedGenres(genresOf(b))[0] ?? 'pop'})">
      <div>
        <h1>${esc(b.name)} <button class="icon-btn" data-act="bandRename" data-id="${b.id}" title="${b.released ? 'Namensänderung beantragen' : 'Umbenennen'}">✎</button></h1>
        <small>${solo ? 'Solokünstler' : esc(b.title ?? 'Band')} · ${genresOf(b).length ? sortedGenres(genresOf(b)).map((g) => esc(genreName(g))).join(' · ') : 'gemischt'}</small>
      </div>
      <div class="lb-hd-mood"><span class="lb-cap">Stimmung</span>${moodTag(b.mood.level).replace('class="lb-mood', 'class="lb-mood big')}</div>
    </header>
    ${notice}
    <h2 class="lb-cap lb-sec">Anordnungen</h2>
    <div class="lb-acts">
      <a class="lb-act" href="${base}/uebung">${ICON.rehearse}<b>Proben</b><small>Können und Beziehungen wachsen</small></a>
      <a class="lb-act ${b.mood.songs >= 12 ? 'warn' : ''}" href="${base}/studio">${ICON.record}<b>Aufnehmen</b><small>${b.mood.songs} ${b.mood.songs === 1 ? 'Song wartet' : 'Songs warten'}</small></a>
      <a class="lb-act" href="${base}/auftritte">${ICON.gig}<b>Auftritt buchen</b><small>Kosten und Gewinn sehen</small></a>
    </div>
    <h2 class="lb-cap lb-sec">Musiker</h2>
    <div class="lb-members">
      ${members.map((m) => memberRow(b, m, members)).join('')}
      ${free > 0 ? `<button class="lb-addm" data-act="addMember" data-id="${b.id}">+ Musiker aufnehmen</button>` : ''}
    </div>
    <h2 class="lb-cap lb-sec">Werte</h2>
    <div class="lb-sum">
      <div data-act="bandStatInfo" data-key="sound" data-id="${b.id}" data-flash="sound-${b.id}"><small>Sound</small><b>${fmt1(b.sound)}</b></div>
      <div data-act="bandStatInfo" data-key="fame" data-id="${b.id}"><small>Ruhm</small><b>${esc(cat().fameNames[steps(b.fame, cat().rules.fameLevels)])}</b></div>
      <div><small>Beziehungen Ø</small><b>${solo ? '–' : `${avgRel > 0 ? '+' : avgRel < 0 ? '−' : ''}${fmt1(Math.abs(avgRel)).replace('.', ',')}`}</b></div>
      <div><small>Stimmung</small><b>${fmt0(b.mood.value)}</b></div>
    </div>
    <details class="lb-more"><summary>Weitere Werte</summary>
      <div class="stat-grid">
        ${statTile('cohesion', b, `${fmt0(b.cohesion)}<small> / ${fmt0(b.maxCohesion)}</small>`)}
        ${statTile('chemistry', b, chemistryLabel(b.chemistry))}
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

