import { api } from '../api.js';
import { bandGenreTags, bandIdentity, emptySlot, genreRuleText, face, fullName, gearCard, gearModel, genreIcon, genreTag, instrumentEmoji, instrumentIcon, instrumentName, ownGear, managerCard, musicianCard, sortedGenres } from '../cards.js';
import { app, bandById, cat, defineActions, isBusy, managerBusy, managerById, musicianById, mutate, navigate, rerender, runningFor } from '../core.js';
import { openLive } from '../live.js';
import { coverHtml, startPractice } from '../play.js';
import { assignGear, soundChange } from '../gear.js';
import { trendBadge } from './charts.js';
import { awardList } from '../awards.js';
import { playedInstrument, stage } from '../figure.js';
import { bandStems, stemUrl } from '../music.js';
import { bar, confirmDialog, duration, esc, fmt0, fmt1, fmt2, fmtGain, money, openModal, percent, promptDialog, timeAgo, toast } from '../ui.js';
import { coreOn } from '../core-ui.js';

/** Untermenü einer Band: [Pfad, Icon, Bezeichnung] */
export const BAND_SECTIONS = [
  ['', '🎸', 'Band'],
  ['uebung', '🥁', 'Übung'],
  ['promo', '📣', 'Promo'],
  ['auftritte', '🎤', 'Auftritte'],
  ['studio', '💿', 'Studio'],
  ['chronik', '📜', 'Chronik'],
  ['auszeichnungen', '🏆', 'Auszeichnungen'],
];

export function renderList() {
  const s = app.state;
  return `
    <div class="section-head"><h1>Bands</h1><button class="btn primary" data-act="newBand">+ Neue Band</button></div>
    ${s.bands.length ? `<div class="tile-grid">${s.bands.map((b) => `<a class="panel band-link" href="#/band/${b.id}"><b>${esc(b.name)}</b><span class="chips">${bandGenreTags(b)}</span></a>`).join('')}</div>`
      : '<div class="empty">Noch keine Band.</div>'}`;
}

export function renderBand(route) {
  const b = bandById(route.id);
  if (!b) return '<div class="empty">Diese Band gibt es nicht mehr.</div>';
  const members = b.memberIds.map(musicianById).filter(Boolean);
  const section = BAND_SECTIONS.some(([key]) => key === route.section) ? route.section : '';
  const render = { '': overview, uebung: practice, promo, auftritte: gigs, studio, chronik: chronicle, auszeichnungen: awards }[section];
  return render(b, members);
}

function head(title, b) {
  return `<div class="page-head"><div><a class="lb-back" href="#/band/${b.id}">‹ ${esc(b.name)}</a><h1>${esc(title)}</h1></div></div>`;
}

/** Was gerade läuft oder warum nichts geht. */
export function blocker(b, members) {
  const running = runningFor(b.id);
  if (running) return { text: running.title, running };
  if (isBusy(b.busyUntil)) return { text: 'Die Band ist gerade beschäftigt.' };
  const away = members.find((m) => isBusy(m.busyUntil));
  if (away) {
    const what = promoOf(away);
    const text = { practice: `${away.firstName} übt gerade allein.`, course: `${away.firstName} ist im Kurs.` }[what?.type] ?? `${away.firstName} ist gerade unterwegs.`;
    return { text, promo: what };
  }
  if (members.length < cat().rules.minMembers) return { text: 'Die Band braucht mindestens zwei Musiker.' };
  return null;
}

export function blockerNotice(b, members) {
  const block = blocker(b, members);
  if (!block) return '';
  return `
    <div class="notice">
      <span class="pulse"></span><span>${esc(block.text)}</span>
      ${block.running ? `<span class="countdown" data-until="${block.running.endsAt}"></span>${block.running.type !== 'bandpromo' ? `<button class="btn small" data-act="watch" data-id="${block.running.id}">Zuschauen</button>` : ''}` : ''}
      ${cancelButton(block.running?.type === 'bandpromo' ? block.running : block.promo)}
    </div>`;
}

/** Laufende Promo-Mission eines Musikers, falls er gerade auf einer ist. */
export const promoOf = (m) => app.state.running.find((a) => (['promo', 'practice', 'course', 'interview'].includes(a.type) && a.musicianId === m.id)
  || (a.type === 'bandpromo' && m.bandId && a.bandId === m.bandId)) ?? null;

/** Knopf zum Abbrechen einer Promo (aus Versehen gestartet); null ergibt nichts. */
export function cancelButton(activity) {
  return activity ? `<button class="btn small ghost" data-act="promoCancel" data-id="${activity.id}">Zurückholen</button>` : '';
}

/** Kachel für eine Aktion: Icon, Titel, eine Zeile Info, optional Zusatz, Knopf. */
function actionTile({ icon, title, meta = '', body = '', button = '', locked = false }) {
  return `
    <div class="action-tile ${locked ? 'locked' : ''}">
      <div class="action-icon">${icon}</div>
      <div class="action-main"><b>${esc(title)}</b>${meta ? `<span class="muted small">${meta}</span>` : ''}${body}</div>
      ${locked ? '<span class="chip">bald</span>' : button}
    </div>`;
}

const songsMeta = (songs) => `${songs} ${songs === 1 ? 'Lied' : 'Lieder'} · ${duration(songs * cat().rules.songSeconds)}`;

// ---------- Band ----------

/** Allein unterwegs (Üben, Promo, Kurs), während die Band nicht gemeinsam unterwegs ist: wann zurück? */
function awayOf(b, m) {
  if (isBusy(b.busyUntil) || !isBusy(m.busyUntil)) return null;
  const a = app.state.running.find((x) => x.musicianId === m.id && ['promo', 'practice', 'course', 'interview'].includes(x.type));
  return { until: a?.endsAt ?? m.busyUntil, label: { promo: 'Promo-Tour', practice: 'Übt allein', course: 'Im Kurs', interview: 'Beim Interview' }[a?.type] ?? 'Unterwegs' };
}

/** Spielt ein Mitglied eine E-Aufnahme? Danach richtet sich, ob seine Figur eine E-Gitarre hält. */
function electricByStem(members, b) {
  const stems = bandStems(members, b);
  const map = new Map(members.map((m, i) => [m.id, /elektrisch/.test(stemUrl(stems[i]) ?? '')]));
  return (m) => map.get(m.id);
}

function overview(b, members) {
  const gig = b.options.gig;
  return `
    <div class="page-head">
      <div><div class="eyebrow">${b.title ? esc(b.title) : 'Band'}</div><h1>${esc(b.name)} <button class="icon-btn" data-act="bandRename" data-id="${b.id}" title="${b.released ? 'Namensänderung beantragen' : 'Umbenennen'}">✎</button></h1></div>
    </div>
    ${members.length ? stage(members, { className: runningFor(b.id) ? '' : 'idle', electricOf: electricByStem(members, b), finishOf: (m) => ownGear(m, playedInstrument(m)).finish, awayOf: (m) => awayOf(b, m) }) : ''}
    <div class="stat-grid">
      ${statTile('sound', b, fmt1(b.sound), { flash: `sound-${b.id}` })}
      ${statTile('fame', b, fmt1(b.fame), { extra: gig.nextVenue ? bar(gig.progress, 1, 'thin') : '' })}
      ${statTile('cohesion', b, `${fmt0(b.cohesion)}<small> / ${fmt0(b.maxCohesion)}</small>`, { extra: bar(b.cohesion, b.maxCohesion, 'thin') })}
      ${statTile('chemistry', b, chemistryLabel(b.chemistry))}
      ${statTile('records', b, fmt0(b.records))}
    </div>
    <div class="lineup-grid">
      ${members.map((m, i) => memberTile(b, m, i + 1)).join('')}
      ${Array.from({ length: cat().rules.maxMembers - members.length }, (_, i) => `
        <button class="lineup-tile free" data-act="addMember" data-id="${b.id}">
          <span class="lineup-head"><span class="slot-label">Platz ${members.length + i + 1}</span></span>
          <span class="lineup-free"><span class="tcg-slot-icon">+</span>Musiker aufnehmen</span>
        </button>`).join('')}
      <div class="lineup-tile manager-tile">
        <div class="lineup-head"><span class="slot-label">Manager</span>${b.managerId ? `<span class="small">Stufe ${fmt1(b.managerLevel)}</span>` : ''}</div>
        <div class="lineup-manager">${managerSlot(b)}</div>
      </div>
    </div>
    ${genrePanel(b, members)}
    <div class="danger-zone"><button class="btn danger ghost small" data-act="bandDisband" data-id="${b.id}">Band auflösen</button></div>`;
}

/**
 * Genre-Übersicht: Welche Genres die Mitglieder mögen und was zur Genre-Band fehlt.
 * Ein zweites Genre, das alle mögen, macht eine Genre-Band zum Hybrid.
 */
function genrePanel(b, members) {
  const rows = Object.entries(b.genreChecks)
    .filter(([, check]) => check.fans > 0)
    .sort((x, y) => y[1].fans - x[1].fans);
  const need = ([options, count]) => `${count > 1 ? `${count} × ` : ''}${options.map((t) => `${instrumentIcon(t)} ${esc(instrumentName(t))}`).join(' oder ')}`;
  return `
    <section class="panel genre-panel">
      <div class="section-head"><h2>Genre</h2>
        <span>${b.title ? `${bandGenreTags(b)} <b>${esc(b.title)}</b> · +${percent(b.genreBonus - 1)} Sound` : `<span class="bad">Gemischte Band: −${percent(1 - b.genreBonus)} Sound</span>`}</span>
      </div>
      <p class="muted small">${genreRuleText()}</p>
      <div class="genre-rows">${rows.map(([genre, check]) => `
        <div class="genre-row ${b.genres.includes(genre) ? 'reached' : ''}">
          ${genreTag(genre)}
          <span class="${check.allFans ? 'ok' : 'muted'}">${check.fans} / ${members.length} mögen es</span>
          <span class="genre-needs">${cat().genreLineup[genre].map((req, i) => `<span class="need ${check.requirements[i].met ? 'ok' : 'warn'}">${check.requirements[i].met ? '✓' : '✗'} ${need(req)}</span>`).join('')}</span>
        </div>`).join('')}
      </div>
    </section>`;
}

/** Kleines i an den Werten der Bandseite: Woraus sich der Wert zusammensetzt und was er bewirkt. */
const STAT_INFO = {
  sound: {
    title: 'Sound',
    text: (b) => {
      const r = cat().rules;
      const fx = b.effects;
      return `<p>Wie gut die Band zusammen klingt.</p>
        <h3>Woraus er sich zusammensetzt</h3>
        <ul>
          <li>Jeder Musiker: sein <b>Können</b> × sein <b>Instrument</b>. Ab einem blauen Instrument kommt Klang dazu, ohne eigenes Instrument zählt nur ${Math.round(r.noGearFactor * 100)} %.</li>
          <li>Der Durchschnitt zählt ${Math.round(r.soundMeanWeight * 100)} %, der <b>Schwächste</b> ${Math.round((1 - r.soundMeanWeight) * 100)} %: Eine schwache Stelle zieht runter.</li>
          <li>Verschiedene Instrumente: +${Math.round(r.lineupBonus * 100)} % je weiterem, höchstens +${Math.round(r.lineupBonus * r.lineupBonusMaxTypes * 100)} %.</li>
          <li><b>Volle Bühne</b>: +${Math.round(r.memberSoundBonus * 100)} % für jedes Mitglied über ${r.minMembers}.</li>
          <li><b>Zusammenhalt</b>: × ${fmt2(r.cohesionFloor)} bei −10, × 1 bei +10 – gerade × ${fmt2(fx.cohesionSound)}.</li>
          <li><b>Genre</b>: gemischt −${Math.round((1 - r.mixedBonus) * 100)} %, Genre-Band +${Math.round((r.genreBonus - 1) * 100)} %, Hybrid +${Math.round((r.hybridBonus - 1) * 100)} % – gerade × ${fmt2(b.genreBonus)}.</li>
        </ul>
        <h3>Was er bewirkt</h3>
        <ul>
          <li>Ruhm bei jedem Auftritt: × ${fmt2(fx.soundFame)}</li>
          <li>Jede neue <b>Platte</b>: Ruhm und Chartpunkte × ${fmt2(fx.soundFame)} – besserer Sound, bessere Platte.</li>
          <li>Das Publikum vergleicht den Sound mit dem, was es erwartet: In der ${esc(fx.venue)} erwartet es <b>${fmt0(fx.expect)}</b>.
            ${b.sound >= fx.expect ? 'Ihr klingt besser – die Stimmung steigt.' : 'Ihr klingt schwächer – die Stimmung sinkt eher.'}</li>
        </ul>`;
    },
  },
  fame: {
    title: 'Ruhm',
    text: (b) => {
      const fx = b.effects;
      const gig = b.options.gig;
      return `<p>Wie bekannt die Band ist: die Bekanntheit der Mitglieder plus der Ruhm am Bandnamen.</p>
      <h3>Gerade</h3>
      <ul><li>Am Bandnamen <b>${fmt1(b.fameOwn)}</b>, bei den Musikern <b>${fmt1(b.fame - b.fameOwn)}</b>.</li>
        <li>Gigs in der <b>${esc(fx.venue)}</b> für <b>${money(fx.gage)}</b> pro Lied (+${Math.round(cat().rules.memberGageBonus * 100)} % für jedes Mitglied über ${cat().rules.minMembers}).${gig.nextVenue ? ` ${esc(gig.nextVenue)} ab ${fmt0(gig.nextFame)} Ruhm.` : ''}</li>
        <li>Platten verkaufen sich beim Erscheinen <b>+${percent(fx.sales)}</b> besser. Auf die Charts wirkt Ruhm nicht – dort zählt nur die Platte.</li></ul>
      <h3>Woher er kommt</h3>
      <ul><li>Auftritte, Platten, Charts und Promo.</li><li>Geht jemand, nimmt er seine Bekanntheit mit. Der Ruhm am Namen bleibt.</li></ul>`;
    },
  },
  together: {
    title: 'Zusammenhalt',
    text: (b) => {
      const t = b.mood.togetherness;
      const sign = (v) => `${v > 0 ? '+' : v < 0 ? '−' : ''}${fmt1(Math.abs(v)).replace('.', ',')}`;
      return `<p>Wie gut sich die Mitglieder verstehen: je zur Hälfte der <b>Durchschnitt</b> und die <b>schwächste Beziehung</b> der Band.</p>
      <h3>Gerade</h3>
      <ul>${t ? `<li>Durchschnitt <b>${sign(t.avg)}</b>, schwächste Beziehung <b>${sign(t.min)}</b>, zusammen <b>${sign(t.value)}</b>.</li>` : '<li>Ein Solokünstler hat niemanden, mit dem er sich streiten könnte.</li>'}</ul>
      <h3>Woher er kommt</h3>
      <ul><li>Die Beziehungen wachsen durch gemeinsames Proben, Auftreten und Aufnehmen – je nachdem, wie gut sich die Charaktere und Genres verstehen.</li>
      <li>Wer neu in die Band kommt, kennt die anderen noch nicht. Wer schlecht zu ihnen passt, kann nie ein Freund werden.</li></ul>
      <h3>Was er bewirkt</h3>
      <ul><li>Sound × ${fmt2(cat().rules.cohesionFloor)} bei −10, × 1 bei +10 – gerade × ${fmt2(b.effects.cohesionSound)}.</li></ul>`;
    },
  },
  records: {
    title: 'Platten',
    text: (b) => {
      const royalty = app.state.finance.bands[b.id];
      return `<p>Alle Platten der Band: <b>${fmt0(b.records)}</b> mit zusammen ${fmt0(b.songsRecorded)} Liedern.</p>
      <h3>Gerade</h3>
      <ul>${royalty ? `<li>Tantiemen <b>+${fmtGain(royalty.moneyPerHour / 60)} ₲</b> pro Minute</li>
        <li>Ruhm aus den Charts <b>+${fmtGain(royalty.famePerHour)}</b> pro Stunde</li>` : '<li>Gerade nicht in den Charts.</li>'}</ul>
      <h3>Was sie bringen</h3>
      <ul><li>Geld und Ruhm beim Erscheinen. Wie viel, hängt von den Minispielen im Studio, vom Sound und vom Ruhm ab.</li>
      <li>In den Charts: laufend Tantiemen und Ruhm. Jede Platte verkauft sich danach für immer ein wenig weiter.</li>
      <li>Neue Lieder für Auftritte.</li></ul>`;
    },
  },
};

/** Wert-Kachel der Bandseite: nur Name und Wert; antippen (oder das i) zeigt, woraus er kommt und was er bewirkt. */
export function statTile(key, b, value, { flash = '', extra = '' } = {}) {
  return `<div class="tile stat-tile" data-act="bandStatInfo" data-key="${key}" data-id="${b.id}"${flash ? ` data-flash="${flash}"` : ''}>
    <span>${STAT_INFO[key].title} ${infoBtn(key, b)}</span><b>${value}</b>${extra}</div>`;
}

function infoBtn(key, b) {
  return `<button class="info-btn" data-act="bandStatInfo" data-key="${key}" data-id="${b.id}" title="Was ist ${STAT_INFO[key].title}?" aria-label="Info">i</button>`;
}

export function chemistryLabel(chemistry) {
  if (chemistry >= 1.15) return 'Super';
  if (chemistry >= 1.0) return 'Gut';
  if (chemistry >= 0.85) return 'Angespannt';
  return 'Zoff';
}

function memberTile(b, m, place) {
  const slotIndex = m.instruments.length > 1 ? m.bandSlot - 1 : 0;
  const played = m.instruments[slotIndex];
  const gear = app.state.gear.find((g) => g.musicianId === m.id && gearModel(g.model).type === played.type);
  const gearData = { musician: m.id, type: played.type };
  const better = app.state.gear.some((g) => g.upgrades?.some((u) => u.musicianId === m.id));
  return `
    <div class="lineup-tile">
      <div class="lineup-head"><span class="slot-label">Platz ${place}</span><span class="small">${instrumentIcon(played.type)} ${esc(instrumentName(played.type))}</span></div>
      <div class="lineup-pair">
        ${musicianCard(m, { size: 'sm' })}
        ${gear ? gearCard(gear, { act: 'pickGear', data: gearData, size: 'sm', up: better }) : emptySlot(instrumentName(played.type), { act: 'pickGear', data: gearData, icon: instrumentIcon(played.type), up: better })}
      </div>
      <div class="lineup-controls">
        ${m.instruments.length > 1 ? `
          <select data-change="bandSetSlot" data-band="${b.id}" data-musician="${m.id}">
            ${m.instruments.map((i, idx) => `<option value="${idx + 1}" ${idx === slotIndex ? 'selected' : ''}>${instrumentEmoji(i.type)} ${esc(instrumentName(i.type))}</option>`).join('')}
          </select>` : '<span></span>'}
        ${gear ? '' : '<span class="bad small">ohne Instrument</span>'}
        <button class="icon-btn" data-act="removeMember" data-band="${b.id}" data-musician="${m.id}" title="Aus der Band nehmen">✕</button>
      </div>
    </div>`;
}

function managerSlot(b) {
  const manager = b.managerId ? managerById(b.managerId) : null;
  if (!manager) return emptySlot('Manager', { act: 'pickManager', data: { id: b.id }, icon: '💼' });
  return managerCard(manager, { act: 'pickManager', data: { id: b.id }, size: 'sm' });
}

// ---------- Übung ----------

function practice(b, members) {
  const blocked = !!blocker(b, members);
  return `
    ${head('Übung', b)}
    ${blockerNotice(b, members)}
    ${courseTiles(members)}
    <div class="action-grid">
      ${actionTile({
        icon: '🥁', title: 'Bandprobe', meta: `bis zu ${duration(b.options.rehearsal.seconds)} · ${b.cohesion >= b.maxCohesion ? 'Zusammenspiel ist schon voll' : 'Zusammenspiel'} · Können für alle: <b>${gainRange(members.map((m) => m.gains?.rehearsal ?? 0))}</b> je Musiker`,
        body: b.cohesion >= b.maxCohesion ? '<span class="small muted">Die Band ist eingespielt. Für mehr Können lohnt sich jetzt Solo-Üben mehr.</span>' : '',
        button: `<button class="btn primary" data-act="startActivity" data-id="${b.id}" data-type="rehearsal" ${blocked ? 'disabled' : ''}>Proben</button>`,
      })}
      ${members.map((m) => practiceTile(m, isBusy(m.busyUntil) || isBusy(b.busyUntil))).join('')}
    </div>`;
}

/**
 * Kacheln für den Tageskurs: je Schwierigkeit (Anfänger, Fortgeschrittene, Profis) eine, sofern einer der Musiker
 * passt. Ohne passenden Musiker erscheint nichts.
 */
export function courseTiles(members) {
  const ids = new Set(members.map((m) => m.id));
  return (app.state.courses ?? []).map((c) => {
    const candidates = c.candidates.filter((x) => ids.has(x.musicianId));
    return c.levels.map((level, k) => {
      const list = candidates.filter((x) => x.level === k);
      if (!list.length) return '';
      return `
      <div class="action-tile course-tile">
        <div class="action-icon">${instrumentIcon(c.instrument)}</div>
        <div class="action-main">
          <b>${esc(c.prefix)}-${esc(c.name)} für ${esc(level.name)} bei ${esc(c.teacher)}</b>
          <span class="muted small">Nur heute · <em>+${fmt0(c.gain)} Können</em> · ${money(level.price)} je Musiker · ${duration(c.seconds)}</span>
          <span class="tile-buttons">${list.map((x) => {
            const m = musicianById(x.musicianId);
            return x.visited
              ? `<button class="btn small" disabled>${esc(m.firstName)} war heute schon im Kurs</button>`
              : `<button class="btn small primary" data-act="courseAttend" data-id="${m.id}" data-course="${c.index}" ${isBusy(m.busyUntil) || (m.bandId && isBusy(bandById(m.bandId)?.busyUntil)) ? 'disabled' : ''}>${esc(m.firstName)} hinschicken</button>`;
          }).join('')}</span>
        </div>
      </div>`;
    }).join('');
  }).join('');
}

/** Spanne der Zuwächse, z. B. „+0,04–0,10“ (wer schon gut ist, lernt langsamer). */
export function gainRange(values) {
  const min = Math.min(...values);
  const max = Math.max(...values);
  return Math.abs(max - min) < 0.005 ? `+${fmtGain(max)}` : `+${fmtGain(min)}–${fmtGain(max)}`;
}

export function practiceTile(m, busy) {
  return actionTile({
    icon: face(m, 'md'),
    title: fullName(m),
    meta: `Selbst spielen: Können je nach Treffern · oder allein üben lassen (${duration(cat().rules.autoPracticeSeconds)}, weniger)`,
    body: m.instruments.map((i, idx) => `<span class="tile-buttons">
      <button class="btn small primary" data-act="practice" data-id="${m.id}" data-slot="${idx + 1}" ${busy ? 'disabled' : ''}>${instrumentIcon(i.type)} ${esc(instrumentName(i.type))} ${fmt0(i.skill)} · <b>bis +${fmtGain(m.gains?.practice?.[idx] ?? 0)}</b></button>
      <button class="btn small ghost" data-act="practiceAuto" data-id="${m.id}" data-slot="${idx + 1}" ${busy ? 'disabled' : ''}>Allein üben lassen · +${fmtGain(m.gains?.auto?.[idx] ?? 0)}</button>
    </span>`).join(''),
  });
}

// ---------- Promo ----------

function promo(b, members) {
  const blocked = !!blocker(b, members);
  return `
    ${head('Promo', b)}
    ${blockerNotice(b, members)}
    <div class="action-grid">
      ${actionTile({
        icon: '📣', title: 'Band-Promo', meta: `${duration(cat().rules.bandPromoSeconds)} · Ruhm${chartPush(b)}${cycleChip(b, 'promos', 'Für die nächste Platte')}`,
        button: `<button class="btn primary" data-act="bandPromo" data-id="${b.id}" ${blocked ? 'disabled' : ''}>Losschicken</button>`,
      })}
    </div>`;
}

// ---------- Auftritte ----------

/** Gigs und Promo schieben die neueste Platte in den Charts an – sagen, welche. */
function chartPush(b) {
  const record = b?.discography.find((r) => r.promoted);
  return record ? ` · Schub in den Charts für „${esc(record.title)}“` : ' · Schub in den Charts, sobald die Band eine Platte hat';
}

/** Die günstigste Platte: drei Lieder in der Garage, ohne Produzenten. */
export const recordCost = (songs) => cat().rules.studios[0].perSong * songs;

/** Reicht das Geld nicht für die kleinste Platte, bringt Straßenmusik Geld in den Hut (Notgroschen). */
export const buskingHat = () => (app.state.label.money < recordCost(cat().rules.recordMinSongs)
  ? ` · <b>Hut: ${money(cat().rules.buskingHat[0])}–${money(cat().rules.buskingHat[1])}</b>` : '');

/**
 * Warnt, bevor das Geld unter die kleinste Platte fällt, solange noch keine Band mit Gigs Geld verdienen kann.
 * Liefert true, wenn ausgegeben werden darf.
 */
export async function confirmSpend(amount) {
  const floor = recordCost(cat().rules.recordMinSongs);
  const money0 = app.state.label.money;
  if (money0 < floor || money0 - amount >= floor || app.state.bands.some((b) => b.records > 0)) return true;
  return confirmDialog(`Danach reicht dein Geld nicht mehr für eine Platte (${money(floor)}). Trotzdem?`, { ok: 'Trotzdem', danger: true });
}

/** Warum ein Gig gerade mehr oder weniger bringt – kurz, als Hinweise unter der Gage. */
function gigDraw(gig) {
  const parts = [];
  if (gig.fresh < 1) parts.push(`<span class="chip bad">Lieder schon bekannt: −${percent(1 - gig.fresh)}</span>`);
  return parts.length ? `<span class="gig-notes">${parts.join('')}</span>` : '';
}

/** Fortschritt im Album-Zyklus als kleiner Hinweis (nur solange er noch nicht geschafft ist). */
function cycleChip(b, key, label) {
  const c = b.record.cycle;
  if (!c || c.done || c[key].have >= c[key].need) return '';
  return ` <span class="chip">${label}: ${fmt0(c[key].have)}/${fmt0(c[key].need)}</span>`;
}

/** Gig: breite Kachel – Gage, Hinweise, Liederzahl. Die Erklärung steckt im i. */
function gigTile(b, gig, disabled) {
  const songs = Math.max(gig.minSongs, Math.min(app.ui.gigSongs[b.id] || gig.maxSongs, gig.maxSongs));
  return `
    <div class="action-tile gig-tile">
      <div class="action-icon">🎤</div>
      <div class="action-main">
        <b>Gig in der ${esc(gig.venue)} <button class="info-btn" data-act="gigInfo" data-id="${b.id}" aria-label="Info">i</button></b>
        ${gig.available ? `
          <span class="gig-pay"><b>${money(gig.gagePerSong)}</b> pro Lied</span>
          ${gigDraw(gig)}${cycleChip(b, 'songs', 'Für die nächste Platte: Lieder live')}
          <span class="gig-controls"><label class="inline-select">Lieder
            <select data-change="gigSongs" data-id="${b.id}">${Array.from({ length: gig.maxSongs - gig.minSongs + 1 }, (_, i) => gig.minSongs + i).map((n) => `<option value="${n}" ${n === songs ? 'selected' : ''}>${n}</option>`).join('')}</select>
          </label><span>${duration(songs * cat().rules.songSeconds)}</span><span>zusammen <b>${money(gig.gagePerSong * songs)}</b></span></span>`
        : '<span class="muted small">Erst mit der ersten Platte.</span>'}
      </div>
      <button class="btn primary" data-act="startActivity" data-id="${b.id}" data-type="gig" ${gig.available ? disabled : 'disabled'}>Spielen</button>
    </div>`;
}

/** Was hinter dem Gig steckt (das i der Gig-Kachel). */
function openGigInfo(b) {
  const gig = b.options.gig;
  const record = b.discography.find((r) => r.promoted);
  const modal = openModal({ html: `<div class="eyebrow">Auftritte</div><h2>Gig in der ${esc(gig.venue)}</h2>
    <h3>Gage</h3>
    <ul>
      <li>Wo ihr spielt, hängt vom Ruhm der Band ab: Kneipe, Club, Konzertsaal, Halle, Arena. Größere Bühnen zahlen mehr.${gig.nextVenue ? ` Nächste Stufe: <b>${esc(gig.nextVenue)}</b> ab ${fmt0(gig.nextFame)} Ruhm.` : ''}</li>
      <li>Gerade <b>${money(gig.gagePerSong)}</b> pro Lied. Ein Manager mit „Verhandler“ bringt mehr.</li>
      <li>Ein Gig hat ${gig.minSongs} bis ${gig.maxSongs} Lieder. Mehr Lieder gibt es mit neuen Platten und einem Manager.</li>
    </ul>
    <h3>Frische Lieder</h3>
    <ul>
      <li>Nach jeder Platte sind die Lieder neu. Mit jedem Gig kennt das Publikum sie besser, es kommen weniger: Gage und Ruhm sinken bis auf ${percent(cat().rules.gigFreshMin)}.</li>
      <li>Gerade: <b>${gig.fresh < 1 ? `−${percent(1 - gig.fresh)}` : 'alles frisch'}</b>. Eine neue Platte macht die Lieder wieder frisch.</li>
    </ul>
    <h3>Charts</h3>
    <ul>
      <li>${record ? `Jeder Gig schiebt „${esc(record.title)}“ in den Charts an.` : 'Jeder Gig schiebt die Platte an, die ihr spielt – sobald eine chartet.'}</li>
    </ul>
    <h3>Ruhm</h3>
    <ul><li>Je größer die Bühne, je besser der Sound und je besser die Stimmung im Publikum, desto mehr Ruhm.</li></ul>
    <div class="dialog-actions"><button class="btn primary" data-close>OK</button></div>` });
  modal.body.querySelector('[data-close]').addEventListener('click', () => modal.close());
}

function gigs(b, members) {
  const disabled = blocker(b, members) ? 'disabled' : '';
  const o = b.options;
  const gig = o.gig;
  return `
    ${head('Auftritte', b)}
    ${blockerNotice(b, members)}
    ${gigTile(b, gig, disabled)}
    <div class="action-grid">
      ${actionTile({
        icon: '🎙️', title: 'Open Stage', meta: `${songsMeta(o.openstage.songs)} · Ruhm, Chance auf eine Karte`,
        button: `<button class="btn" data-act="startActivity" data-id="${b.id}" data-type="openstage" ${disabled}>Spielen</button>`,
      })}
      ${actionTile({
        icon: '🪙', title: 'Straßenmusik', meta: `${songsMeta(o.busking.songs)} · etwas Ruhm${buskingHat()}`,
        button: `<button class="btn" data-act="startActivity" data-id="${b.id}" data-type="busking" ${disabled}>Spielen</button>`,
      })}
      ${actionTile({ icon: '🚌', title: 'Tournee', meta: 'Land · Kontinent · Welt', locked: true })}
    </div>`;
}

// ---------- Studio ----------

/** Studio-Seite: die Diskografie und der Weg ins Studio (Aufnehmen läuft über den Dialog). */
function studio(b, members) {
  return `
    ${head('Studio', b)}
    ${blockerNotice(b, members)}
    <p><button class="btn primary" data-act="recordAsk" data-id="${b.id}">Aufnehmen …</button></p>
    ${b.discography.length ? `
      <h2>Diskografie</h2>
      <div class="tile-grid">${b.discography.map((r) => `
        <div class="panel record-tile">
          ${coverHtml(r.cover, { band: b.name, title: r.title })}
          <b>„${esc(r.title)}“</b>
          <span class="muted small">${r.format} · ${r.songs} Lieder · ${percent(r.quality)}${r.perfect ? ' · perfekt' : ''}</span>
          ${r.charting ? `<span class="chip special">Charts · Platz ${r.rank}</span> ${trendBadge(r)}` : r.best ? `<span class="muted small">Bester Platz: ${r.best}</span>` : ''}
        </div>`).join('')}
      </div>` : '<p class="muted">Noch keine Platte.</p>'}`;
}

// ---------- Chronik & Auszeichnungen ----------

function chronicle(b) {
  const history = app.state.history.filter((h) => h.bandId === b.id);
  return `
    ${head('Chronik', b)}
    <section class="panel">
      ${history.length ? `<ul class="history">${history.map((h) => `<li><span>${esc(h.text)}</span><time>${timeAgo(h.createdAt, app.state.now)}</time></li>`).join('')}</ul>` : '<p class="muted">Noch nichts passiert.</p>'}
    </section>`;
}

function awards(b) {
  return `
    ${head('Auszeichnungen', b)}
    ${awardList(b.awards)}`;
}

// ---------- Auswahl-Dialoge ----------

function openGearPicker(musicianId, type) {
  const m = musicianById(musicianId);
  const modal = openModal({
    wide: true,
    render: () => {
      const options = app.state.gear.filter((g) => gearModel(g.model).type === type && (!g.musicianId || g.musicianId === m.id));
      const current = options.find((g) => g.musicianId === m.id);
      const played = m.instruments.find((i) => i.type === type)?.skill ?? 0;
      return `
        <h2>${esc(instrumentName(type))} für ${esc(m.firstName)}</h2>
        ${options.length ? `<div class="gt-list">${options.map((g) => {
          const upgrade = g.upgrades?.find((u) => u.musicianId === m.id);
          return gearCard(g, {
            skill: played,
            extra: g === current
              ? '<button class="btn small" data-gear-remove>Herausnehmen</button>'
              : `${upgrade ? soundChange(upgrade) : '<span class="small muted">Klingt damit nicht besser</span>'}
                 <button class="btn primary small" data-gear="${g.id}">Einlegen</button>`,
          });
        }).join('')}</div>` : '<div class="empty">Kein freies Instrument dieser Art im Lager.</div>'}`;
    },
  });
  modal.body.addEventListener('click', async (e) => {
    const add = e.target.closest('[data-gear]');
    const remove = e.target.closest('[data-gear-remove]');
    if (add && await assignGear(Number(add.dataset.gear), m.id)) modal.close();
    if (remove) {
      const current = app.state.gear.find((g) => g.musicianId === m.id && gearModel(g.model).type === type);
      if (current && await assignGear(current.id, null)) modal.close();
    }
  });
}

function openManagerPicker(bandId) {
  const b = bandById(bandId);
  if (!app.state.managers.length) {
    toast('Noch kein Manager unter Vertrag.', 'info');
    return;
  }
  const modal = openModal({
    wide: true,
    render: () => `
      <h2>Manager für ${esc(b.name)}</h2>
      <div class="tcg-grid">${app.state.managers.map((m) => {
        const mine = m.id === b.managerId;
        const full = !mine && !!managerBusy(m, b);
        return managerCard(m, {
          extra: mine
            ? '<button class="btn small" data-manager="">Abziehen</button>'
            : `<button class="btn primary small" data-manager="${m.id}" ${full ? 'disabled' : ''}>${full ? 'Ausgelastet' : 'Zuweisen'}</button>`,
        });
      }).join('')}</div>`,
  });
  modal.body.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-manager]');
    if (!button || button.disabled) return;
    if (await mutate('bandSetManager', { bandId: b.id, managerId: button.dataset.manager ? Number(button.dataset.manager) : null })) modal.close();
  });
}

function pickRow(m, { checkbox = true, button = '', note = '' } = {}) {
  return `
    <div class="pick-row" data-id="${m.id}">
      <label class="pick-main">
        ${checkbox ? `<input type="checkbox" value="${m.id}">` : ''}
        ${face(m, 'sm')}
        <span class="pick-name">${esc(fullName(m))}<small class="muted">${sortedGenres(m.genres).map(genreIcon).join('')}${note ? ` · ${note}` : ''}</small></span>
      </label>
      ${m.instruments.length > 1 ? `
        <select data-slot>
          ${m.instruments.map((i, idx) => `<option value="${idx + 1}">${instrumentEmoji(i.type)} ${esc(instrumentName(i.type))} ${fmt1(i.skill)}</option>`).join('')}
        </select>` : `<span class="pick-instr">${instrumentIcon(m.instruments[0].type)} ${esc(instrumentName(m.instruments[0].type))} <b>${fmt1(m.instruments[0].skill)}</b></span>`}
      ${button}
    </div>`;
}

function lineupProblems(picks) {
  const rules = cat().rules;
  const problems = [];
  if (picks.length < rules.minMembers) problems.push(`Mindestens ${rules.minMembers} Musiker auswählen.`);
  if (picks.length > rules.maxMembers) problems.push(`Höchstens ${rules.maxMembers} Musiker.`);
  const perType = {};
  for (const p of picks) perType[p.type] = (perType[p.type] || 0) + 1;
  for (const [type, max] of Object.entries(rules.maxPerInstrument)) {
    if ((perType[type] || 0) > max) problems.push(`Höchstens ${max} × ${instrumentName(type)}.`);
  }
  return problems;
}

/** Solokünstler ohne eigene Laufbahn (allein in einer Band ohne Auftritt und Platte) lassen sich in eine andere Band holen. */
export function isFree(m) {
  if (m.bandId === null) return true;
  const own = bandById(m.bandId);
  return !!own && own.memberIds.length === 1 && !own.released && own.records === 0;
}

function openCreateBand() {
  const s = app.state;
  const free = s.musicians.filter(isFree);
  const slots = s.label.bandSlots;
  if (s.bands.length >= slots.open) {
    toast(slots.next === null ? 'Mehr Bands kann dein Label nicht führen.' : `Deine nächste Band kannst du ab ${fmt0(slots.next)} Label-Ruhm gründen.`, 'error');
    return;
  }
  if (free.length < cat().rules.minMembers) {
    toast('Du brauchst mindestens einen Musiker ohne Band.', 'error');
    return;
  }

  const modal = openModal({
    wide: true,
    html: `
      <h2>Neue Band gründen</h2>
      <form class="stack create-band">
        <div class="form-row">
          <label class="field"><span>Bandname</span>
            <span class="input-dice"><input name="name" maxlength="40" required placeholder="z. B. Die Kellerkinder"><button type="button" class="btn small" data-dice title="Zufälliger Name">🎲</button></span>
          </label>
        </div>
        <div class="pick-list"></div>
        <p class="create-summary small"></p>
        <div class="dialog-actions">
          <button type="button" class="btn ghost" data-cancel>Abbrechen</button>
          <button class="btn primary">Band gründen</button>
        </div>
      </form>`,
  });
  const form = modal.body.querySelector('form');
  const list = form.querySelector('.pick-list');
  const summary = form.querySelector('.create-summary');
  const selected = new Set();

  const picks = () => [...list.querySelectorAll('.pick-row')]
    .filter((row) => row.querySelector('input').checked)
    .map((row) => {
      const m = musicianById(row.dataset.id);
      const slot = Number(row.querySelector('[data-slot]')?.value || 1);
      return { id: m.id, slot, type: m.instruments[slot - 1].type };
    });

  const drawList = () => {
    list.innerHTML = free.map((m) => pickRow(m)).join('');
    list.querySelectorAll('input[type=checkbox]').forEach((cb) => { cb.checked = selected.has(Number(cb.value)); });
  };
  const update = () => {
    selected.clear();
    for (const p of picks()) selected.add(p.id);
    const problems = lineupProblems(picks());
    const identity = bandIdentity(picks().map((p) => ({ type: p.type, genres: musicianById(p.id).genres })));
    summary.innerHTML = problems.length
      ? `<span class="warn">${problems.map(esc).join(' ')}</span>`
      : `<span class="ok">${selected.size} Musiker – ${identity.title ? `das wird eine ${esc(identity.title)} (+${percent(identity.bonus - 1)} Sound)` : `noch ohne gemeinsames Genre (−${percent(1 - identity.bonus)} Sound)`}.</span>`;
  };
  drawList();
  update();
  list.addEventListener('change', update);
  form.querySelector('[data-cancel]').addEventListener('click', () => modal.close());
  form.querySelector('[data-dice]').addEventListener('click', async () => {
    form.name.value = await suggestName('band');
  });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const members = picks().map(({ id, slot }) => ({ id, slot }));
    const problems = lineupProblems(picks());
    if (problems.length) {
      toast(problems[0], 'error');
      return;
    }
    const res = await mutate('bandCreate', { name: form.name.value, members });
    if (res) {
      modal.close();
      navigate(`#/band/${res.bandId}`);
      toast('Die Band ist gegründet!', 'ok');
    }
  });
}

/** Was mit dem Genre der Band passiert, wenn dieser Musiker dazukommt (mit seinem Hauptinstrument). */
function joinNote(band, m) {
  const current = band.memberIds.map((id) => {
    const x = musicianById(id);
    return { genres: x.genres, type: x.instruments[x.bandSlot - 1]?.type ?? x.instruments[0].type };
  });
  const before = bandIdentity(current, band.genres[0] ?? '', band.genres[1] ?? null);
  const after = bandIdentity([...current, { genres: m.genres, type: m.instruments[0].type }], band.genres[0] ?? '', band.genres[1] ?? null);
  if (after.title === before.title) return '';
  if (!after.title) return `<span class="warn">${esc(before.title)} fällt weg</span>`;
  return `<span class="ok">wird ${esc(after.title)}</span>`;
}

function openAddMember(bandId) {
  const band = bandById(bandId);
  const free = app.state.musicians.filter((m) => m.bandId !== bandId && isFree(m));
  if (!free.length) {
    toast('Es gibt gerade niemanden, den du in diese Band holen kannst: Alle spielen schon in einer Band oder haben mit ihrer Solo-Band schon etwas aufgebaut.', 'error');
    return;
  }
  const modal = openModal({
    wide: true,
    html: `
      <h2>Musiker für ${esc(band.name)}</h2>
      <div class="pick-list">${free.map((m) => pickRow(m, {
        checkbox: false,
        note: joinNote(band, m),
        button: `<button class="btn small" data-add="${m.id}">Aufnehmen</button>`,
      })).join('')}
      </div>`,
  });
  modal.body.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-add]');
    if (!button) return;
    const row = button.closest('.pick-row');
    const slot = Number(row.querySelector('[data-slot]')?.value || 1);
    const res = await mutate('bandAddMember', { bandId: band.id, musicianId: Number(button.dataset.add), slot });
    if (res) modal.close();
  });
}

export async function suggestName(kind) {
  try {
    return (await api('nameSuggest', { kind })).name;
  } catch (e) {
    toast(e.message, 'error');
    return '';
  }
}

defineActions({
  async diceRecord({ key }) {
    const name = await suggestName('record');
    if (!name) return;
    app.ui.form[key] = name;
    const input = document.querySelector(`[data-ui="${key}"]`);
    if (input) input.value = name;
  },
  pickGear({ musician, type }) {
    openGearPicker(Number(musician), type);
  },
  pickManager({ id }) {
    openManagerPicker(Number(id));
  },
  newBand() {
    openCreateBand();
  },
  addMember({ id }) {
    openAddMember(Number(id));
  },
  async removeMember({ band, musician }) {
    const b = bandById(band);
    const m = musicianById(musician);
    if (b.released) {
      const ok = await confirmDialog(
        `${fullName(m)} verlässt ${b.name}: Bandruhm −${fmt1(m.fame)}, und der Zusammenhalt ändert sich.`,
        { ok: 'Rausnehmen', danger: true },
      );
      if (!ok) return;
    }
    await mutate('bandRemoveMember', { bandId: b.id, musicianId: m.id });
  },
  gigInfo({ id }) {
    openGigInfo(bandById(Number(id)));
  },
  bandStatInfo({ key, id }) {
    const info = STAT_INFO[key];
    const modal = openModal({ html: `<div class="eyebrow">Band</div><h2>${info.title}</h2>${info.text(bandById(Number(id)))}
      <div class="dialog-actions"><button class="btn primary" data-close>OK</button></div>` });
    modal.body.querySelector('[data-close]').addEventListener('click', () => modal.close());
  },
  async bandSetSlot(value, el) {
    const b = bandById(Number(el.dataset.band));
    const m = musicianById(Number(el.dataset.musician));
    const after = b.slotPreview?.[m.id];
    const back = b.slotReturn?.includes(String(m.id));
    const penalty = '';
    const ok = await confirmDialog(
      `${m.firstName} spielt ${instrumentName(m.instruments[Number(value) - 1].type)}: Sound ${fmt1(b.sound)} → ${after == null ? '?' : fmt1(after)}.${penalty}`,
      { ok: 'Wechseln', danger: !!b.released && !back },
    );
    if (!ok) {
      el.value = String(m.bandSlot);
      return;
    }
    await mutate('bandSetSlot', { bandId: b.id, musicianId: m.id, slot: Number(value) });
  },
  async bandRename({ id }) {
    const b = bandById(id);
    if (coreOn() && b.core?.fans >= 1) {
      // Neuer Kern: Ein Teil der Fans mag keinen neuen Namen (Bands::rename)
      const lost = b.core.fans * (cat().rules.coreFanMoves?.rename ?? 0.1);
      const ok = await confirmDialog(
        `Unter „${b.name}“ kennen die Fans die Band: Ein Zehntel mag keinen neuen Namen und hört auf (etwa ${fmt0(lost)} von ${fmt0(b.core.fans)} Fans). Die Platten behalten die Band und verkaufen sich weiter.`,
        { ok: 'Umbenennen', danger: true },
      );
      if (!ok) return;
    } else if (!coreOn() && (b.released || b.records > 0)) {
      const ok = await confirmDialog(
        `Umbenennen kostet nichts, aber unter „${b.name}“ kennt man die Band schon: Der Ruhm am Namen (${fmt1(b.fameOwn)}) geht verloren, `
        + `die Band hat danach ${fmt1(b.fame - b.fameOwn)} statt ${fmt1(b.fame)} Ruhm. Die Platten bleiben bei „${b.name}“ (Tantiemen laufen weiter) und stehen in der Chronik.`,
        { ok: 'Umbenennen', danger: true },
      );
      if (!ok) return;
    }
    const name = await promptDialog('Neuer Bandname', b.name);
    if (name && name !== b.name) await mutate('bandRename', { bandId: b.id, name });
  },
  async bandDisband({ id }) {
    const b = bandById(id);
    const members = b.memberIds.map(musicianById).filter(Boolean);
    // Besteht die Band nur aus Jam-Café-Musikern, sollen die nicht glauben, sie könnten gleich wieder jammen.
    const jamOnly = members.length > 0 && members.every((m) => m.jammed);
    const after = coreOn()
      ? `${b.core?.fans >= 1 ? ' Jeder macht allein weiter und nimmt einen Teil der Fans mit.' : ''}${b.records > 0 ? ' Die Band bleibt mit ihren Platten in der Chronik; die Platten verkaufen sich weiter.' : ''}`
      : (b.released || b.records > 0 ? ' Die Band bleibt mit ihren Platten in der Chronik; die Tantiemen laufen weiter.' : '');
    const ok = await confirmDialog(`${b.name} auflösen? Eine laufende Produktion geht verloren.${after}${jamOnly
      ? ` ${members.map((m) => m.firstName).join(' und ')} haben sich im Jam-Café gefunden – ins Jam-Café können sie danach nicht noch einmal.` : ''}`, { ok: 'Auflösen', danger: true });
    if (!ok) return;
    if (await mutate('bandDisband', { bandId: b.id })) navigate('#/');
  },
  gigSongs(value, el) {
    app.ui.gigSongs[el.dataset.id] = Number(value);
    rerender();
  },
  async startActivity({ id, type }) {
    const b = bandById(id);
    const songs = type === 'gig' ? Math.max(b.options.gig.minSongs, Math.min(app.ui.gigSongs[b.id] || b.options.gig.maxSongs, b.options.gig.maxSongs)) : 0;
    const res = await mutate('activityStart', { bandId: b.id, type, songs });
    // Die Probe läuft offen auf der Bandseite mit; der Probenraum ist nur auf Wunsch offen („Zuschauen“).
    if (res && type !== 'rehearsal') openLive(res.activity.id);
  },
  async bandPromo({ id }) {
    const b = bandById(id);
    const one = b?.memberIds.length === 1 ? musicianById(b.memberIds[0]) : null;
    if (await mutate('bandPromo', { bandId: Number(id) })) toast(one ? `${one.stageName ?? one.firstName} ist auf Promo-Tour.` : 'Die Band ist auf Promo-Tour.', 'ok');
  },
  practice({ id, slot }) {
    startPractice(id, slot);
  },
  async courseAttend({ id, course }) {
    const c = app.state.courses.find((y) => y.index === Number(course ?? 0));
    const x = c?.candidates.find((y) => y.musicianId === Number(id));
    if (!x || !(await confirmSpend(c.levels[x.level].price))) return;
    if (await mutate('courseAttend', { musicianId: Number(id), course: c.index })) toast(`${musicianById(id).firstName} ist im Kurs.`, 'ok');
  },
  async practiceAuto({ id, slot }) {
    if (await mutate('practiceAuto', { musicianId: Number(id), slot: Number(slot) })) toast(`${musicianById(id).firstName} übt jetzt allein.`, 'ok');
  },
  async promoCancel({ id }) {
    const ok = await confirmDialog('Jetzt zurückholen? Dann gibt es dafür nichts.', { ok: 'Zurückholen', danger: true });
    if (ok && await mutate('promoCancel', { activityId: Number(id) })) toast('Alle sind wieder da.', 'ok');
  },
  watch({ id }) {
    // Die Probe sieht man auf der Bandseite, mit ihren Werten; Auftritte laufen im Live-Fenster.
    const act = app.state.running.find((a) => a.id === Number(id));
    if (act?.type === 'rehearsal') {
      navigate(`#/band/${act.bandId}`);
      return;
    }
    openLive(id);
  },
});
