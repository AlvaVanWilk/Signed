import { api } from './api.js';
import { actions, app, bandById, cat, defineActions, isBusy, liveMoney, musicianById, mutate, navigate, onRender, refresh, rerender, serverNow } from './core.js';
import { bandFill, genreGlyph, levelUpBanners, musicianCard } from './cards.js';
import { closeAllModals, duration, esc, fmt0, fmt1, fmtGain, money, openModal, percent, timeAgo, toast, topModal } from './ui.js';
import './celebrate.js';
import './settings.js';
import './genre-info.js';
import './navdrag.js';
import './sidefit.js';
import './glitter.js';
import * as auth from './views/auth.js';
import * as board from './views/board.js';
import * as battle from './views/battle.js';
import * as festival from './views/festival.js';
import * as tour from './tour.js';
import { celebrateAwards } from './awards.js';
import * as bands from './views/bands.js';
import * as label from './views/label.js';
import * as charts from './views/charts.js';
import * as help from './views/help.js';
import { maybeShowTransition } from './views/transition.js';
import { trendBadge } from './views/charts.js';
import { flame } from './core-ui.js';
import { playApplause, playCoin } from './audio.js';
import * as market from './views/market.js';
import * as musician from './views/musician.js';
import * as overview from './views/overview.js';
import * as packs from './views/packs.js';
import * as storage from './views/storage.js';
import * as store from './views/store.js';
import * as submissions from './views/submissions.js';
import './views/together.js';
import * as wiki from './views/wiki.js';
import { recordResultHtml } from './views/recording.js';

const NAV = [
  ['', 'Übersicht'],
  ['einsendungen', 'Posteingang'],
  ['brett', 'Schwarzes Brett'],
  ['store', 'Music Store'],
  ['lager', 'Lager'],
  ['charts', 'Charts'],
  ['wiki', 'WikiSignia'],
];

const VIEWS = {
  '': () => overview.render(),
  einsendungen: () => submissions.render(),
  brett: () => board.render(),
  battle: () => battle.render(),
  festival: () => festival.render(),
  packs: () => packs.render(),
  store: () => store.render(),
  lager: () => storage.render(),
  bands: (route) => viewBand(route),
  band: (route) => viewBand(route),
  musician: (route) => musician.renderMusician(route),
  charts: () => charts.render(),
  wiki: () => wiki.render(),
  boerse: () => market.render(),
  hilfe: () => help.render(),
};

function parseRoute() {
  const [name = '', id, section = ''] = location.hash.replace(/^#\/?/, '').split('/');
  // „Bands“ im Hauptmenü öffnet die zuletzt angesehene Band.
  if (name === 'bands') return { name: 'band', id: app.state ? label.lastBandId() : null, section: '' };
  return { name: VIEWS[name] ? name : '', id: id ? Number(id) : null, section };
}

/** Bandseite: ohne Unterseite die ruhige Label-Ansicht, sonst die Übung, das Studio usw. */
function viewBand(route) {
  if (!app.state.bands.length) return `<div class="empty">Noch keine Band. <a class="btn primary" href="#/einsendungen">Zum Posteingang</a></div>`;
  // Die alte Übungsseite gibt es nicht mehr: Proben starten direkt auf der Bandseite.
  return route.section && route.section !== 'uebung' ? bands.renderBand(route) : label.page(route);
}

/** Zweite Menüzeile: die Bands des Labels und favorisierte Musiker ohne Band. */
function subnav(route) {
  const s = app.state;
  const favorites = s.musicians.filter((m) => m.favorite && !m.bandId);
  const active = (kind, id) => route.name === kind && route.id === id;
  // Eigene Reihenfolge (verschiebbar, navdrag.js); Neues hängt hinten an.
  const order = s.label.navOrder ?? [];
  if (!favorites.length) return '';
  const items = [
    ...favorites.map((m) => ({ key: `m${m.id}`, html: `<a href="#/musician/${m.id}" data-nav="m${m.id}" draggable="false" class="subnav-btn fav ${active('musician', m.id) ? 'active' : ''}">★ ${esc(m.firstName)} ${esc(m.lastName)}</a>` })),
  ];
  const rank = (key, i) => (order.includes(key) ? order.indexOf(key) : order.length + i);
  const sorted = items.map((it, i) => ({ ...it, rank: rank(it.key, i) })).sort((a, b) => a.rank - b.rank);
  return `
    <nav class="subnav">
      ${sorted.map((it) => it.html).join('')}
    </nav>`;

  function bandButton(b) {
    const fill = bandFill(b);
    // Beschäftigt: laufende Streifen – kräftig, wenn die ganze Band weg ist, zart, wenn nur ein Teil fehlt.
    const members = s.musicians.filter((m) => m.bandId === b.id);
    const away = members.filter((m) => isBusy(m.busyUntil)).length;
    const busy = isBusy(b.busyUntil) || (away && away === members.length) ? 'busy-all' : away ? 'busy-some' : '';
    const hint = busy === 'busy-all' ? ' title="Die Band ist beschäftigt"' : busy ? ` title="${away} ${away === 1 ? 'Mitglied ist' : 'Mitglieder sind'} unterwegs"` : '';
    return `<a href="#/band/${b.id}" data-nav="b${b.id}" draggable="false" class="subnav-btn ${fill.cls} ${busy} ${active('band', b.id) ? 'active' : ''}" style="${fill.style}"${hint}>${b.genres.map(genreGlyph).join('')}${esc(b.name)}</a>`;
  }
}

let lastSide = '';

/** Seitenleiste für Band- und Musikerseiten: die gewählte Band rutscht nach links, darunter ihr Menü. */
function sidebar(route) {
  const key = `${route.name}/${route.id}`;
  const enter = key !== lastSide;
  lastSide = key;
  let title;
  let base;
  let sections;
  let theme;
  let icon = '';
  let themeStyle = '';
  if (route.name === 'band') {
    return '';
  } else if (route.name === 'musician') {
    const m = musicianById(route.id);
    if (!m) return '';
    title = `${m.firstName} ${m.lastName}`;
    base = `#/musician/${m.id}`;
    sections = musician.sectionsFor(m);
    theme = 'fav';
  } else {
    return '';
  }
  return `
    <aside class="side ${enter ? 'enter' : ''}">
      <a class="side-entity subnav-btn ${theme}" style="${themeStyle}" href="${base}">${icon}${esc(title)}</a>
      <nav class="side-menu">${sections.map(([key, icon, label]) => `
        <a href="${base}${key ? `/${key}` : ''}" class="${route.section === key ? 'active' : ''}" title="${esc(label)}"><span>${icon}</span><small>${esc(label)}</small></a>`).join('')}
      </nav>
    </aside>`;
}

/**
 * Zahl am Menüpunkt Börse: wie viele Angebote anderer Labels drin sind. In Signalfarbe, solange eins dabei ist,
 * das man noch nicht gesehen hat (gemerkt im Browser: höchste gesehene Angebots-Nummer je Label).
 */
/** Zahl am Posteingang: was nicht gemerkt ist (Demos und Briefe). */
/** Zahl am Posteingang: was seit dem letzten Blick hineingekommen ist (+1, +2 …). */
function inboxBadge(s) {
  const n = s.inboxNew ?? 0;
  return n ? ` <span class="nav-badge">+${n}</span>` : '';
}

function marketBadge(visiting) {
  const offers = app.state.market?.offers ?? [];
  if (!offers.length) return '';
  const key = `signed.marketSeen.${app.state.label.id ?? app.state.label.name}`;
  const newest = Math.max(...offers.map((o) => o.id));
  let seen = 0;
  try {
    seen = Number(localStorage.getItem(key) || 0);
    if (visiting && newest > seen) {
      localStorage.setItem(key, String(newest));
      seen = newest;
    }
  } catch { /* ohne Speicher: alles gilt als neu */ }
  return ` <span class="nav-badge ${newest > seen ? 'new' : ''}" title="${offers.length} Angebote${newest > seen ? ', neue dabei' : ''}">${offers.length}</span>`;
}

function layout(content, route) {
  const s = app.state;
  const active = route.name;
  const side = sidebar(route);
  // Die Band-Leiste ist auf jeder Seite da; auf der Bandseite zeigt sie die gewählte Band.
  const lbar = app.state.bands.length ? label.bar(route.name === 'band' ? route.id : null) : '';
  const bandPage = route.name === 'band' && !!lbar;
  return `
    <header class="topbar">
      <a class="logo" href="#/">Signed</a>
      <nav class="nav">${NAV.map(([key, label]) => `<a href="#/${key}" class="${key === active || (key === 'brett' && ['battle', 'festival'].includes(active)) ? 'active' : ''}">${label}${key === 'brett' && (s.battle?.current?.state === 'running' || s.festival?.current?.state === 'running') ? ' <span class="nav-badge new" title="Gerade läuft eine Veranstaltung">live</span>' : ''}${key === 'boerse' ? marketBadge(active === 'boerse') : ''}${key === 'einsendungen' ? inboxBadge(s) : ''}${key === 'store' && s.store?.gold ? ' <span class="nav-gold" title="Heute ein legendäres Instrument im Angebot">★</span>' : ''}</a>`).join('')}</nav>
      <div class="label-info">
        <span class="label-name" title="Dein Label">${esc(s.label.name)}</span>
        <button class="money-pill" data-act="openFinance" data-live-money title="Konto und Einnahmen">${money(liveMoney())}</button>
        <a class="btn ghost small help-btn ${active === 'hilfe' ? 'active' : ''}" href="#/hilfe" title="Hilfe">?</a>
        <button class="btn ghost small" data-act="logout">Abmelden</button>
      </div>
      ${subnav(route)}
    </header>
    <div class="shell ${side && !lbar ? 'with-side' : ''} ${lbar ? 'with-bar' : ''}">${lbar}<main class="page ${bandPage ? 'lb-main' : ''}">${lbar ? side.replace('class="side ', 'class="side inline ') : ''}${content}</main></div>`;
}

function render() {
  const root = document.getElementById('app');
  if (!app.catalog) return;
  if (!app.label) {
    root.innerHTML = auth.render();
    return;
  }
  if (!app.state) {
    root.innerHTML = '<div class="boot"><span class="logo">Signed</span></div>';
    return;
  }
  const route = parseRoute();
  // Die Künstlerleiste bleibt, wo man hingescrollt hat (breit: senkrecht, schmal: waagerecht), auch wenn man eine Band anklickt.
  const was = ['.lb-side', '.lb-bar'].map((sel) => root.querySelector(sel)).map((el) => (el ? [el.scrollTop, el.scrollLeft] : null));
  root.innerHTML = layout(VIEWS[route.name](route), route);
  ['.lb-side', '.lb-bar'].forEach((sel, i) => {
    const el = was[i] && root.querySelector(sel);
    if (el) [el.scrollTop, el.scrollLeft] = was[i];
  });
  updateCountdowns();
  showResults();
  // Neue Auszeichnungen feiern, wenn keine Ergebnisse mehr offen sind.
  if (!app.state?.results.length) celebrateAwards();
  // Labels aus dem alten Spiel sehen einmal „Was ist neu?“ statt der Einführung.
  if (app.state.transition) maybeShowTransition();
  else help.maybeStartTour();
  if (route.name === 'charts' && (!app.charts || Date.now() - (app.chartsAt ?? 0) > 60000)) charts.loadCharts();
  if (route.name === 'wiki') wiki.ensure();
  if (route.name === 'store') store.enter();
}

function updateCountdowns() {
  const now = serverNow();
  for (const el of document.querySelectorAll('[data-until]')) {
    el.textContent = duration(Number(el.dataset.until) - now);
  }
  // Tantiemen laufen ständig: das Geld zählt sichtbar mit.
  const cash = money(liveMoney());
  for (const el of document.querySelectorAll('[data-live-money]')) {
    if (el.textContent !== cash) el.textContent = cash;
  }
}

// ---------- Ergebnisse abgeschlossener Aktionen ----------

let resultModal = null;

function moodText(meter) {
  if (meter >= 75) return 'Das Publikum war begeistert!';
  if (meter >= 55) return 'Ein gelungener Auftritt.';
  if (meter >= 35) return 'Ganz okay – da geht noch mehr.';
  return 'Das lief nicht gut.';
}

/** count: {to, kind} lässt den Wert beim Öffnen hochzählen (kind 'money' klimpert dabei). */
function reward(label, value, count = null) {
  const attrs = count ? ` data-count="${count.to}" data-kind="${count.kind}"` : '';
  return `<div class="reward${count?.kind === 'money' ? ' reward-money' : ''}"><span>${esc(label)}</span><b${attrs}>${value}</b></div>`;
}

/**
 * Nach einem Auftritt: Gage und Ruhm zählen hoch, das Geld klimpert, ein paar Münzen springen,
 * und das Publikum klatscht – so begeistert, wie es war.
 */
function celebrateResult(body, res, audience) {
  const els = [...body.querySelectorAll('[data-count]')];
  const start = performance.now();
  const duration = 1300;
  let coins = 0;
  const moneyTile = body.querySelector('.reward-money');
  if (moneyTile) {
    const burst = document.createElement('div');
    burst.className = 'coin-burst';
    burst.innerHTML = Array.from({ length: 8 }, (_, i) => `<i style="--a:${-80 + i * 22}deg;--d:${i * 60}ms">🪙</i>`).join('');
    moneyTile.append(burst);
  }
  function tick(now) {
    const p = Math.min(1, (now - start) / duration);
    const eased = 1 - (1 - p) ** 3;
    for (const el of els) {
      const value = Number(el.dataset.count) * eased;
      el.textContent = el.dataset.kind === 'money' ? `+${money(Math.round(value))}` : `+${fmtGain(value)}`;
    }
    if (moneyTile && coins < Math.floor(p * 9)) {
      coins++;
      playCoin(1 + coins * 0.03);
    }
    if (p < 1) requestAnimationFrame(tick);
  }
  if (els.length) requestAnimationFrame(tick);
  // Erst klimpert das Geld, dann klatscht das Publikum – gleichzeitig klingt beides wie ein Münzregen.
  if (audience && res.meter !== null && res.meter !== undefined) playApplause((res.meter - 35) / 60, moneyTile ? duration / 1000 : 0, res.venue ?? null);
}

/** „ · Bandname“ für Ergebnisse einzelner Musiker, damit man weiß, wer gemeint ist. */
function bandOf(m) {
  const band = m?.bandId ? bandById(m.bandId) : null;
  return band ? ` · ${esc(band.name)}` : '';
}

/** „+0,05“ oder „+0,01–0,07“ – wer schon gut ist, lernt langsamer. */
function skillSpan(values) {
  const min = Math.min(...values);
  const max = Math.max(...values);
  return max - min < 0.005 ? `+${fmtGain(max)}` : `+${fmtGain(min)}–${fmtGain(max)}`;
}

const REHEARSAL_END = {
  lust: (n) => `${esc(n)} hat keine Lust mehr.`,
  red: (n) => `${esc(n)} ist zu genervt.`,
  stopped: () => 'Du hast die Probe beendet.',
  ended: () => 'Die Probe ist zu Ende.',
};

function resultHtml(r) {
  const res = r.result;
  if (r.type === 'record') return recordResultHtml(r, { reward, levelUpBanners });
  if (r.type === 'rehearsal') {
    const band = bandById(r.bandId);
    const members = band ? band.memberIds.map(musicianById).filter(Boolean) : [];
    const skills = res.skillBy ?? {};
    return `
      <div class="eyebrow">${esc(band?.name ?? '')}</div>
      <h2>Die Probe ist vorbei</h2>
      <p class="muted">${(REHEARSAL_END[res.ended] ?? REHEARSAL_END.ended)(res.endedBy ?? '')} Sie dauerte ${duration(res.seconds ?? 0)}.</p>
      <div class="reward-grid">
        ${members.map((m) => (skills[m.id] === undefined ? '' : reward(`Können ${esc(m.firstName)}`, `+${fmtGain(skills[m.id])}`))).join('')}
      </div>
      ${levelUpBanners(res.levelUps)}`;
  }
  if (r.type === 'practice' || r.type === 'course') {
    const m = musicianById(r.musicianId);
    return `
      <div class="eyebrow">${r.type === 'course' ? 'Kurs geschafft' : 'Allein geübt'}${bandOf(m)}</div>
      <h2>${esc(m ? `${m.firstName} ${m.lastName}` : 'Übung')} ist fertig</h2>
      <div class="reward-grid">${reward(`Können ${esc(cat().instruments[res.instrument]?.name ?? '')}`, `+${fmtGain(res.skill)}`)}${reward('Jetzt', fmt1(res.skillAfter))}</div>
      ${levelUpBanners(res.levelUps)}
      ${r.type === 'practice' ? '<p class="muted small">Selbst mitspielen bringt bis zum Vierfachen.</p>' : ''}`;
  }
  if (r.type === 'battle') return battleResultHtml(r);
  if (r.type === 'tourtravel') return tour.arrivalHtml(r);
  if (r.type === 'tour') return tour.homeHtml(r);
  if (r.type === 'interview' && res.request) {
    // Neuer Kern: Anfrage erledigt – Hype-Schub für die neueste Platte, Werbung bringt Geld
    const m = musicianById(r.musicianId);
    return `
      <div class="eyebrow">${esc(cat().requests?.[res.request]?.name ?? 'Anfrage')}${bandOf(m)}</div>
      <h2>${esc(m ? `${m.firstName} ${m.lastName}` : 'Anfrage')} ist zurück</h2>
      <p class="muted">${esc(res.medium ?? '')}</p>
      <div class="reward-grid">${reward('Hype-Schub', `+${fmt0(res.boost)}`)}${res.money ? reward('Werbung', `+${money(0)}`, { to: res.money, kind: 'money' }) : ''}</div>
      <p class="muted small">Ein Schub hebt den Hype der neuesten Platte; jeder weitere für dieselbe Platte zählt nur halb so viel.</p>`;
  }
  if (r.type === 'interview') {
    const m = musicianById(r.musicianId);
    return `
      <div class="eyebrow">Interview${bandOf(m)}</div>
      <h2>${esc(m ? `${m.firstName} ${m.lastName}` : 'Interview')} ist zurück</h2>
      <p class="muted">${esc(res.medium ?? '')}</p>
      <div class="reward-grid">${reward('Bekanntheit', `+${fmtGain(res.fame)}`)}${reward('Bekanntheit jetzt', fmt1(res.total))}</div>`;
  }
  if (r.type === 'promo') {
    const m = musicianById(r.musicianId);
    return `
      <div class="eyebrow">Promo-Mission${bandOf(m)}</div>
      <h2>${esc(m ? `${m.firstName} ${m.lastName}` : 'Promo')} ist zurück</h2>
      <div class="reward-grid">${reward('Bekanntheit', `+${fmtGain(res.fame)}`)}${reward('Bekanntheit jetzt', fmt1(res.total))}</div>`;
  }
  if (r.bandId === null) {
    const m = musicianById(r.musicianId);
    // Immer die aktuelle Auswahl aus dem Spielstand zeigen (ältere Ergebnisse sind anders gespeichert).
    const jamOffer = res.jamOffer ? app.state.jamOffers.find((o) => o.id === res.jamOffer.id) : null;
    return `
      <div class="eyebrow">${esc(m ? `${m.firstName} ${m.lastName}` : '')}</div>
      <h2>${esc(r.title)}</h2>
      ${res.meter ? `<p class="muted">${moodText(res.meter)}</p>` : ''}
      <div class="reward-grid">${reward('Bekanntheit', `+${fmtGain(res.fame)}`)}${res.meter ? reward('Publikum', percent(res.meter / 100)) : ''}</div>
      ${jamOffer ? `<p>${jamOffer.cards.length} Musiker würden mit ${esc(m?.firstName ?? '')} eine Band gründen.</p>
        ${musician.jamOfferHtml(jamOffer, true)}` : ''}`;
  }
  const band = bandById(r.bandId);
  const drops = (res.drops || []).map((d) => {
    if (d.kind === 'manager') return `<div class="banner">🎴 Neue Karte: <b>${esc(d.name)}</b> will dein Manager werden (Stufe ${d.level}). Du findest ihn im Lager.</div>`;
    if (d.kind === 'musician') return fanDrop(d, band);
    return `<div class="banner">💌 Fan-Karte erhalten: +${fmt1(d.value)} Ruhm, einsetzbar im Lager.</div>`;
  }).join('');
  return `
    <div class="eyebrow">${esc(band?.name ?? '')}</div>
    <h2>${esc(r.title)}</h2>
    ${res.meter ? `<p class="muted">${moodText(res.meter)}</p>` : ''}
    <div class="reward-grid">
      ${res.tickets !== undefined ? reward('Zuschauer', `${fmt0(res.tickets)}${res.size ? ` von ${fmt0(res.size)}` : ''}`) : ''}
      ${res.money ? reward(r.type === 'busking' ? 'Im Hut' : res.tickets !== undefined ? 'Verdient' : res.bill ? 'Gage nach Rechnung' : 'Gage', `+${money(0)}`, { to: res.money, kind: 'money' }) : ''}
      ${res.fans ? reward('Neue Fans', `+${fmt0(res.fans)}`) : ''}
      ${res.mood ? reward('Stimmung', `+${fmt0(res.mood)}`) : ''}
      ${res.fame ? reward('Ruhm', `+${fmtGain(0)}`, { to: res.fame, kind: 'fame' }) : ''}
      ${res.skill?.length ? reward('Können je Musiker', skillSpan(res.skill)) : ''}
      ${res.meter ? reward('Publikum', percent(res.meter / 100)) : ''}
    </div>
    ${levelUpBanners(res.levelUps)}
    ${res.venueUp ? `<div class="banner">⭐ Neue Stufe! Ab jetzt spielt die Band Gigs in: <b>${esc(res.venueUp)}</b>.</div>` : ''}
    ${res.stageUp ? `<div class="banner">⭐ Neue Ruhmstufe: <b>${esc(res.stageUp)}</b>! Ab jetzt passen mehr Leute in die Orte, und das Label verdient mehr je Zuschauer.</div>` : ''}
    ${res.request ? `<div class="banner">Nach dem Auftritt hat ${esc(res.request.medium)} angefragt, bei ${esc(res.request.who)}. Die Anfrage liegt im Posteingang.</div>` : ''}
    ${res.festival ? festivalExtras(res.festival) : ''}
    ${res.tour ? tour.gigExtras(res) : ''}
    ${drops}`;
}

/** Nach dem Festival: Headliner-Plakat und die Interview-Anfrage. */
function festivalExtras(f) {
  return `${f.headliner ? `<div class="banner">Ihr wart der Headliner von „${esc(f.name)}“! Das Plakat hängt jetzt im Proberaum.</div>` : ''}
    ${f.interview ? `<div class="banner">Nach dem Auftritt hat ${esc(f.interview.medium)} angefragt: ein Interview mit ${esc(f.interview.who)}. Die Anfrage liegt im Posteingang.</div>` : ''}`;
}

/** Battle of the Bands vorbei: Platz, Pokal, Preisgeld, Ruhm. */
function battleResultHtml(r) {
  const res = r.result;
  const info = res.battle ?? {};
  const band = bandById(r.bandId);
  const name = esc(band?.name ?? '');
  const head = info.place === 1 ? 'Gewonnen!' : info.place ? `Platz ${info.place}` : 'Ausgeschieden';
  const text = info.place === 1
    ? `${name} hat das Battle of the Bands gewonnen. Der goldene Pokal steht jetzt im Proberaum.`
    : info.place && info.place <= 3
      ? `${name} ist auf Platz ${info.place} von ${info.of} Bands gekommen. Dafür gibt es einen Pokal in ${info.place === 2 ? 'Silber' : 'Bronze'}.`
      : info.place
        ? `${name} ist auf Platz ${info.place} von ${info.of} Bands gekommen.`
        : `${name} ist ${esc(BATTLE_OUT[info.reached] ?? BATTLE_OUT.group)}. Dabei waren ${info.of} Bands.`;
  return `
    <div class="eyebrow">Battle of the Bands · ${name}</div>
    <h2>${head}</h2>
    <p class="muted">${text}</p>
    <div class="reward-grid">
      ${reward('Spiele gewonnen', `${info.wins ?? 0} von ${info.played ?? 0}`)}
      ${res.money ? reward('Preisgeld', `+${money(0)}`, { to: res.money, kind: 'money' }) : ''}
      ${res.fans ? reward('Neue Fans', `+${fmt0(res.fans)}`) : ''}
      ${res.fame ? reward('Ruhm', `+${fmtGain(0)}`, { to: res.fame, kind: 'fame' }) : ''}
      ${res.mood ? reward('Stimmung', `+${fmt0(res.mood)}`) : ''}
    </div>
    ${res.prize && app.state.prizes?.some((p) => p.id === res.prize.id) ? prizeChoice(res.prize) : ''}
    ${levelUpBanners(res.levelUps)}
    ${res.venueUp ? `<div class="banner">Neue Stufe! Ab jetzt spielt die Band Gigs in: <b>${esc(res.venueUp)}</b>.</div>` : ''}`;
}

/** Neuer Kern: Der Sieger wählt Preisgeld oder Wochenpreis (geht auch später auf der Übersicht). */
function prizeChoice(p) {
  const n = cat().battlePrizes?.[p.weekly] ?? { title: p.weekly, desc: '' };
  return `<div class="banner bt-prize"><b>Euer Preis:</b> ${money(p.money)} oder ${esc(n.name ?? n.title)}. ${esc(n.desc)}
    <span class="ov-prize-pick"><button class="btn small primary" data-act="prizeChoose" data-id="${p.id}" data-pick="money">${money(p.money)} nehmen</button>
    <button class="btn small" data-act="prizeChoose" data-id="${p.id}" data-pick="weekly">${esc(n.title)} nehmen</button></span>
    <small class="muted">Ihr könnt auch später wählen, auf der Übersicht.</small></div>`;
}

const BATTLE_OUT = {
  group: 'in der Gruppe ausgeschieden',
  r16: 'im Achtelfinale ausgeschieden',
  qf: 'im Viertelfinale ausgeschieden',
  sf: 'im Halbfinale ausgeschieden',
};

/** Fan will einsteigen: Er ist schon im Label; in die Band holt man ihn mit einem Tipp, solange Platz ist. */
function fanDrop(d, band) {
  const m = musicianById(d.id);
  if (!m) return '';
  const inBand = m.bandId !== null;
  return `<div class="banner fan-drop">
    <p>💖 Nach dem Gig wartet <b>${esc(m.firstName)}</b> am Bühnenausgang: großer Fan von ${esc(band?.name ?? 'euch')} und will unbedingt mitspielen!
      ${esc(m.firstName)} ist jetzt in deinem Label.</p>
    <div class="fan-drop-card">${musicianCard(m, { size: 'sm' })}</div>
    ${inBand ? '' : d.canJoin && band
      ? `<button class="btn primary" data-act="fanJoinBand" data-band="${band.id}" data-musician="${m.id}">In die Band holen</button>`
      : '<p class="muted small">In der Band ist gerade kein Platz – vielleicht für eine neue Band?</p>'}
  </div>`;
}

/** Ein kaputtes Ergebnis darf nie das ganze Spiel blockieren. */
function safeResultHtml(r) {
  try {
    return resultHtml(r);
  } catch (e) {
    console.error(e);
    return `<h2>${esc(r.title ?? 'Ergebnis')}</h2>`;
  }
}

function showResults() {
  if (resultModal || !app.state?.results.length) return;
  // Nicht über ein laufendes Minispiel legen.
  if (topModal() && !topModal().dismissable) return;
  const r = app.state.results[0];
  const jamOffer = r.result.jamOffer && app.state.jamOffers.find((o) => o.id === r.result.jamOffer.id);
  resultModal = openModal({
    className: 'result-modal',
    wide: !!jamOffer,
    html: `${safeResultHtml(r)}<div class="dialog-actions">${jamOffer
      ? `<button class="btn ghost" data-act="jamDecline" data-offer="${jamOffer.id}">Alle ablehnen</button><button class="btn" data-close>Später</button>`
      : r.type === 'tourtravel' ? tour.arrivalActions(r)
        : r.type === 'tourgig' && tour.gigActions(r) ? tour.gigActions(r)
          : `${r.type === 'practice' && musicianById(r.musicianId) ? '<button class="btn" data-again>Nochmal üben</button>' : ''}<button class="btn primary" data-close>Weiter</button>`}</div>`,
    onClose: () => {
      resultModal = null;
      app.state.results = app.state.results.filter((x) => x.id !== r.id);
      api('activityAck', { activityId: r.id }).catch(() => {});
      setTimeout(() => (app.state.results.length ? showResults() : celebrateAwards()), 250);
      // Kommt eine Band zurück (Auftritt, Auszeit, Promo), läuft sie jetzt sichtbar in den Raum.
      if (Object.keys(app.ui.awayFrom ?? {}).length) rerender();
    },
  });
  resultModal.body.addEventListener('click', (e) => {
    if (e.target.closest('[data-close]')) resultModal?.close();
  });
  // Allein geübt: gleich noch eine Runde mit demselben Instrument.
  resultModal.body.querySelector('[data-again]')?.addEventListener('click', async () => {
    const m = musicianById(r.musicianId);
    const slot = Math.max(1, m.instruments.findIndex((i) => i.type === r.result.instrument) + 1);
    resultModal?.close();
    if (await mutate('practiceAuto', { musicianId: m.id, slot })) toast(`${m.firstName} übt noch eine Runde.`, 'ok');
  });
  if (r.type !== 'promo') celebrateResult(resultModal.body, r.result, r.type !== 'rehearsal');
}

// ---------- Konto ----------

/** Konto in Groove Coins: regelmäßige Einnahmen aus den Charts und der Kontoauszug. */
/** Neuer Kern: was die Platten gerade einbringen (Verkäufe des Tages und Katalog), ohne Tantiemen und Ruhm. */
function coreFinanceHtml(f, now) {
  return `
    <div class="eyebrow">Groove Coins</div>
    <h2 data-live-money>${money(liveMoney())}</h2>
    <div class="reward-grid">
      ${reward('Platten pro Stunde', `${fmt0(f.moneyPerHour)} ₲`)}
      ${reward('Platten in den Charts', String(f.records.filter((r) => r.placed).length))}
    </div>
    ${f.catalogPerHour ? `<p class="muted small">Davon Katalog: <b>${fmt0(f.catalogPerHour)} ₲</b> pro Stunde. Jede Platte verkauft sich noch eine Weile weiter, jede Woche halb so viel.</p>` : ''}
    ${f.records.length ? `
      <p class="muted small">Jede Platte bringt 10 ₲ je verkauftem Stück. Was ein Tag bringt, steht zu Beginn des Tages fest und kommt gleichmäßig über den Tag.</p>
      <table class="charts finance-table">
        <thead><tr><th>Platte</th><th>Band</th><th>Hype</th><th>Platz</th><th>heute verkauft</th><th>pro Stunde</th></tr></thead>
        <tbody>${f.records.map((r) => `<tr><td>„${esc(r.title)}“</td><td>${esc(r.band)}</td><td>${flame(r.flame)}</td><td>${r.placed ? `${r.rank} ${trendBadge(r)}` : `<span class="muted">${r.rank ?? (r.ended ? 'nur Katalog' : '–')}</span>`}</td><td>${fmt0(r.soldToday)}</td><td>${fmt0(r.perHour)} ₲</td></tr>`).join('')}</tbody>
      </table>` : '<p class="muted">Gerade verkauft keine Platte. Eine neue Platte bringt Geld und Fans.</p>'}
    <h3>Kontoauszug</h3>
    ${f.ledger.length ? `<ul class="history ledger">${f.ledger.map((l) => `
      <li><span>${esc(l.text)}</span><span class="ledger-right"><b class="${l.amount < 0 ? 'bad' : 'ok'}">${l.amount > 0 ? '+' : '−'}${money(Math.abs(l.amount))}</b><time>${timeAgo(l.at, now)}</time></span></li>`).join('')}</ul>`
    : '<p class="muted">Noch keine Buchungen.</p>'}`;
}

function financeHtml() {
  const f = app.state.finance;
  const now = app.state.now;
  if (f.core) return coreFinanceHtml(f, now);
  return `
    <div class="eyebrow">Groove Coins</div>
    <h2 data-live-money>${money(liveMoney())}</h2>
    <div class="reward-grid">
      ${reward('Tantiemen pro Minute', `${fmtGain(f.moneyPerHour / 60)} ₲`)}
      ${reward('Ruhm aus den Charts pro Stunde', `+${fmtGain(f.famePerHour)}`)}
      ${reward('Platten in den Charts', String(f.records.filter((r) => r.placed).length))}
    </div>
    ${f.catalogPerHour ? `<p class="muted small">Davon Katalog: <b>${fmtGain(f.catalogPerHour / 60)} ₲</b> pro Minute – jede Platte verkauft sich für immer ein wenig.</p>` : ''}
    ${f.records.length ? `
      <p class="muted small">Neue Platten verkaufen sich eine Woche lang, mehr Punkte bringen mehr Geld. Gigs und Promo schieben die neueste Platte der Band an.</p>
      <table class="charts finance-table">
        <thead><tr><th>Platte</th><th>Band</th><th>Platz</th><th>Punkte</th><th>pro Minute</th></tr></thead>
        <tbody>${f.records.map((r) => `<tr><td>„${esc(r.title)}“</td><td>${esc(r.band)}</td><td>${r.placed ? `${r.rank} ${trendBadge(r)}` : `<span class="muted">${r.rank ? `${r.rank} (nicht in den Top ${cat().rules.chartSize})` : 'verkauft sich'}</span>`}</td><td>${fmt1(r.points)}</td><td>${fmtGain(r.perHour / 60)} ₲</td></tr>`).join('')}</tbody>
      </table>` : '<p class="muted">Gerade keine neue Platte im Verkauf – regelmäßige Einnahmen kommen über Tantiemen.</p>'}
    <h3>Kontoauszug</h3>
    ${f.ledger.length ? `<ul class="history ledger">${f.ledger.map((l) => `
      <li><span>${esc(l.text)}</span><span class="ledger-right"><b class="${l.amount < 0 ? 'bad' : 'ok'}">${l.amount > 0 ? '+' : '−'}${money(Math.abs(l.amount))}</b><time>${timeAgo(l.at, now)}</time></span></li>`).join('')}</ul>`
    : '<p class="muted">Noch keine Buchungen.</p>'}`;
}

// ---------- Ereignisse ----------

defineActions({
  async fanJoinBand({ band, musician: id }, el) {
    if (await mutate('bandAddMember', { bandId: Number(band), musicianId: Number(id), slot: 1 })) {
      el.outerHTML = `<p class="ok"><b>${esc(musicianById(Number(id))?.firstName ?? '')}</b> spielt jetzt in der Band!</p>`;
    }
  },
  async logout() {
    await api('logout', {}).catch(() => {});
    closeAllModals();
    app.label = null;
    app.state = null;
    app.charts = null;
    rerender();
  },
  async prizeChoose({ id, pick }) {
    if (await mutate('prizeChoose', { prizeId: Number(id), pick })) {
      toast(pick === 'money' ? 'Das Preisgeld ist auf dem Konto.' : 'Der Wochenpreis gehört euch.', 'ok');
      resultModal?.close();
    }
  },
  openFinance() {
    openModal({ wide: true, className: 'finance-modal', render: financeHtml });
  },
  closeModalAndGo({ href }) {
    closeAllModals();
    navigate(href);
  },
});

document.addEventListener('click', async (e) => {
  const el = e.target.closest('[data-act]');
  if (!el || el.disabled || el.dataset.running) return;
  const handler = actions[el.dataset.act];
  if (!handler) return;
  e.preventDefault();
  el.dataset.running = '1';
  try {
    await handler({ ...el.dataset }, el);
  } finally {
    delete el.dataset.running;
  }
});

document.addEventListener('submit', async (e) => {
  const form = e.target.closest('form[data-form]');
  if (!form) return;
  e.preventDefault();
  const button = form.querySelector('button:not([type=button])');
  if (button) button.disabled = true;
  try {
    await actions[form.dataset.form]?.(Object.fromEntries(new FormData(form)), form);
  } finally {
    if (button) button.disabled = false;
  }
});

document.addEventListener('change', (e) => {
  const el = e.target.closest('[data-change]');
  if (el) actions[el.dataset.change]?.(el.value, el);
});

/** Eingaben mit data-ui überstehen das Neuzeichnen. */
document.addEventListener('input', (e) => {
  const key = e.target.dataset?.ui;
  if (!key) return;
  app.ui.form[key] = e.target.value;
  for (const out of document.querySelectorAll(`[data-output="${key}"]`)) {
    out.textContent = 'recordCost' in out.dataset ? money(bands.recordCost(Number(e.target.value))) : e.target.value;
  }
});

window.addEventListener('hashchange', () => {
  if (parseRoute().name === 'charts') app.charts = null;
  // NEU im Posteingang gilt bis zum nächsten Besuch.
  if (parseRoute().name !== 'einsendungen') app.ui.postSeenAt = undefined;
  render();
  window.scrollTo(0, 0);
});

document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible' && app.state) refresh();
});

let lastAutoRefresh = 0;
let lastWake = 0;
let lastTapeRefresh = 0;
setInterval(() => {
  if (!app.state) return;
  updateCountdowns();
  // Eine laufende Probe wird alle paar Sekunden gutgeschrieben: So steigen Können und Stufen wirklich in dem Moment, in dem es passiert.
  const live = app.state.running.some((a) => a.type === 'rehearsal');
  label.tickLive();
  battle.tick();
  tour.tick();
  // Battle und Festival: zur nächsten Runde, zum nächsten Ergebnis, zum Anmeldeschluss und zum Beginn neu fragen.
  const wake = Math.min(app.state.battle?.wakeAt ?? Infinity, app.state.festival?.wakeAt ?? Infinity);
  if (Number.isFinite(wake) && wake + 0.5 <= serverNow() && Date.now() - lastWake > 2000) {
    lastWake = Date.now();
    refresh({ quiet: true });
  }
  const due = app.state.running.some((a) => a.endsAt <= serverNow());
  // Eine gesichtete Kassette soll sich sofort öffnen: kurz nach dem Ablauf (mit Reserve für die Uhr) schnell und wiederholt nachfragen.
  const now0 = serverNow();
  // Eigene Kassetten und Umschläge zeigen sich sofort. Was Praktikum und Assistenz tun, zeigt sich laufend nur im Posteingang und
  // höchstens alle paar Sekunden (sonst würde ständig neu gezeichnet).
  const inbox = location.hash.startsWith('#/einsendungen');
  const post = [...(app.state.submissions ?? []), ...(app.state.mail ?? [])];
  const done = (x) => (x.state === 'listening' && x.opensAt + 0.4 <= now0) || (x.state === 'unpacking' && x.unpacksAt + 0.4 <= now0);
  const ownDone = post.some((x) => done(x) && !x.byIntern)
    // Ende des Praktikums (dann fragt es, ob es bleiben darf) und Ende der Bedenkzeit
    || (app.state.assistant?.staff ?? []).some((x) => x.intern && ((!x.ended && x.until + 0.4 <= now0) || x.leaves + 0.4 <= now0 || (x.job && x.job.until + 0.4 <= now0)));
  const staffDone = post.some((x) => done(x) && x.byIntern)
    || (app.state.assistant?.staff ?? []).some((x) => (x.job && (x.job.next || x.job.until) + 0.4 <= now0) || (x.doingUntil && x.doingUntil + 0.4 <= now0));
  if ((ownDone && Date.now() - lastTapeRefresh > 700) || (staffDone && inbox && Date.now() - lastTapeRefresh > 3000)) {
    lastTapeRefresh = Date.now();
    refresh();
  }
  // Charts: jede Minute neu sortiert – solange die Seite offen ist, ebenso oft nachladen.
  if (location.hash.startsWith('#/charts') && app.charts?.core && Date.now() - (app.chartsAt ?? 0) > 60000) charts.loadCharts();
  // Post kommt zufällig: ab und zu nachsehen, damit die Zahl am Posteingang hochzählt.
  const slow = Date.now() - lastAutoRefresh > (cat().rules.postRefresh ?? 60) * 1000;
  if ((due && Date.now() - lastAutoRefresh > 3000) || (live && Date.now() - lastAutoRefresh > 5000) || slow) {
    lastAutoRefresh = Date.now();
    refresh({ quiet: !due });
  }
}, 1000);

onRender(render);

async function boot() {
  try {
    const session = await api('session');
    app.catalog = session.catalog;
    app.label = session.label;
    if (app.label) {
      await refresh();
    } else {
      rerender();
    }
  } catch (e) {
    document.getElementById('app').innerHTML = `<div class="boot error"><span class="logo">Signed</span><p>Das Spiel konnte nicht geladen werden: ${esc(e.message)}</p></div>`;
  }
}

boot();
