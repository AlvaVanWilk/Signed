import { api } from './api.js';
import { actions, app, bandById, defineActions, musicianById, navigate, onRender, refresh, rerender, serverNow } from './core.js';
import { bandFill, genreGlyph, levelUpBanners, musicianCard } from './cards.js';
import { closeAllModals, duration, esc, fmt1, money, openModal, percent, timeAgo, topModal } from './ui.js';
import './celebrate.js';
import * as auth from './views/auth.js';
import * as bands from './views/bands.js';
import * as catalog from './views/catalog.js';
import * as charts from './views/charts.js';
import * as market from './views/market.js';
import * as musician from './views/musician.js';
import * as overview from './views/overview.js';
import * as packs from './views/packs.js';
import * as storage from './views/storage.js';

const NAV = [
  ['', 'Übersicht'],
  ['packs', 'Läden'],
  ['katalog', 'Katalog'],
  ['lager', 'Lager'],
  ['boerse', 'Börse'],
  ['charts', 'Charts'],
];

const VIEWS = {
  '': () => overview.render(),
  packs: () => packs.render(),
  katalog: () => catalog.render(),
  lager: () => storage.render(),
  bands: () => bands.renderList(),
  band: (route) => bands.renderBand(route),
  musician: (route) => musician.renderMusician(route),
  charts: () => charts.render(),
  boerse: () => market.render(),
};

function parseRoute() {
  const [name = '', id, section = ''] = location.hash.replace(/^#\/?/, '').split('/');
  return { name: VIEWS[name] ? name : '', id: id ? Number(id) : null, section };
}

/** Zweite Menüzeile: die Bands des Labels und favorisierte Musiker ohne Band. */
function subnav(route) {
  const s = app.state;
  const favorites = s.musicians.filter((m) => m.favorite && !m.bandId);
  const active = (kind, id) => route.name === kind && route.id === id;
  return `
    <nav class="subnav">
      ${s.bands.map((b) => {
        const fill = bandFill(b);
        return `<a href="#/band/${b.id}" class="subnav-btn ${fill.cls} ${active('band', b.id) ? 'active' : ''}" style="${fill.style}">${b.genres.map(genreGlyph).join('')}${esc(b.name)}</a>`;
      }).join('')}
      ${favorites.map((m) => `<a href="#/musician/${m.id}" class="subnav-btn fav ${active('musician', m.id) ? 'active' : ''}">★ ${esc(m.firstName)} ${esc(m.lastName)}</a>`).join('')}
      <button class="subnav-btn add" data-act="newBand">+ Band</button>
    </nav>`;
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
    const b = bandById(route.id);
    if (!b) return '';
    title = b.name;
    icon = b.genres.map(genreGlyph).join('');
    base = `#/band/${b.id}`;
    sections = bands.BAND_SECTIONS;
    theme = bandFill(b).cls;
    themeStyle = bandFill(b).style;
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

function layout(content, route) {
  const s = app.state;
  const active = route.name;
  const side = sidebar(route);
  return `
    <header class="topbar">
      <a class="logo" href="#/">Signed</a>
      <nav class="nav">${NAV.map(([key, label]) => `<a href="#/${key}" class="${key === active ? 'active' : ''}">${label}</a>`).join('')}</nav>
      <div class="label-info">
        <span class="label-name" title="Dein Label">${esc(s.label.name)}</span>
        <button class="money-pill" data-act="openFinance" title="Konto und Einnahmen">${money(s.label.money)}</button>
        <button class="btn ghost small" data-act="logout">Abmelden</button>
      </div>
      ${subnav(route)}
    </header>
    <div class="shell ${side ? 'with-side' : ''}">${side}<main class="page">${content}</main></div>`;
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
  root.innerHTML = layout(VIEWS[route.name](route), route);
  updateCountdowns();
  showResults();
  if (route.name === 'charts' && !app.charts) charts.loadCharts();
}

function updateCountdowns() {
  const now = serverNow();
  for (const el of document.querySelectorAll('[data-until]')) {
    el.textContent = duration(Number(el.dataset.until) - now);
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

function reward(label, value) {
  return `<div class="reward"><span>${esc(label)}</span><b>${value}</b></div>`;
}

function resultHtml(r) {
  const res = r.result;
  if (r.type === 'promo') {
    const m = musicianById(r.musicianId);
    return `
      <div class="eyebrow">Promo-Mission</div>
      <h2>${esc(m ? `${m.firstName} ${m.lastName}` : 'Promo')} ist zurück</h2>
      <div class="reward-grid">${reward('Bekanntheit', `+${fmt1(res.fame)}`)}${reward('Bekanntheit jetzt', fmt1(res.total))}</div>`;
  }
  if (r.bandId === null) {
    const m = musicianById(r.musicianId);
    // Immer die aktuelle Auswahl aus dem Spielstand zeigen (ältere Ergebnisse sind anders gespeichert).
    const jamOffer = res.jamOffer ? app.state.jamOffers.find((o) => o.id === res.jamOffer.id) : null;
    return `
      <div class="eyebrow">${esc(m ? `${m.firstName} ${m.lastName}` : '')}</div>
      <h2>${esc(r.title)}</h2>
      ${res.meter ? `<p class="muted">${moodText(res.meter)}</p>` : ''}
      <div class="reward-grid">${reward('Bekanntheit', `+${fmt1(res.fame)}`)}${res.meter ? reward('Publikum', percent(res.meter / 100)) : ''}</div>
      ${jamOffer ? `<p>${jamOffer.cards.length} Musiker würden mit ${esc(m?.firstName ?? '')} eine Band gründen.</p>
        ${musician.jamOfferHtml(jamOffer, true)}` : ''}`;
  }
  const band = bandById(r.bandId);
  const drops = (res.drops || []).map((d) => d.kind === 'manager'
    ? `<div class="banner">🎴 Neue Karte: <b>${esc(d.name)}</b> will dein Manager werden (Stufe ${d.level}). Du findest ihn im Lager.</div>`
    : `<div class="banner">💌 Fan-Karte erhalten: +${fmt1(d.value)} Ruhm, einsetzbar im Lager.</div>`).join('');
  return `
    <div class="eyebrow">${esc(band?.name ?? '')}</div>
    <h2>${esc(r.title)}</h2>
    ${res.meter ? `<p class="muted">${moodText(res.meter)}</p>` : ''}
    <div class="reward-grid">
      ${res.money ? reward('Gage', `+${money(res.money)}`) : ''}
      ${res.fame ? reward('Ruhm', `+${fmt1(res.fame)}`) : ''}
      ${res.cohesion ? reward('Zusammenspiel', `+${fmt1(res.cohesion)}`) : ''}
      ${res.meter ? reward('Publikum', percent(res.meter / 100)) : ''}
    </div>
    ${levelUpBanners(res.levelUps)}
    ${res.venueUp ? `<div class="banner">⭐ Neue Stufe! Ab jetzt spielt die Band Gigs in: <b>${esc(res.venueUp)}</b>.</div>` : ''}
    ${drops}`;
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
      : '<button class="btn primary" data-close>Weiter</button>'}</div>`,
    onClose: () => {
      resultModal = null;
      app.state.results = app.state.results.filter((x) => x.id !== r.id);
      api('activityAck', { activityId: r.id }).catch(() => {});
      setTimeout(showResults, 250);
    },
  });
  resultModal.body.querySelector('[data-close]').addEventListener('click', () => resultModal?.close());
}

// ---------- Konto ----------

/** Konto in Groove Coins: regelmäßige Einnahmen aus den Charts und der Kontoauszug. */
function financeHtml() {
  const f = app.state.finance;
  const now = app.state.now;
  return `
    <div class="eyebrow">Groove Coins</div>
    <h2>${money(app.state.label.money)}</h2>
    <div class="reward-grid">
      ${reward('Tantiemen pro Tag', money(f.perDay))}
      ${reward('Platten in den Charts', String(f.records.length))}
    </div>
    ${f.records.length ? `
      <p class="muted small">Die Chartpunkte – und damit die Tantiemen – sinken jeden Tag um ${percent(1 - f.decay)}.</p>
      <table class="charts finance-table">
        <thead><tr><th>Platte</th><th>Band</th><th>Punkte</th><th>pro Tag</th></tr></thead>
        <tbody>${f.records.map((r) => `<tr><td>„${esc(r.title)}“</td><td>${esc(r.band)}</td><td>${fmt1(r.points)}</td><td>${money(r.perDay)}</td></tr>`).join('')}</tbody>
      </table>` : '<p class="muted">Noch keine Platte in den Charts – regelmäßige Einnahmen kommen über Tantiemen.</p>'}
    <h3>Kontoauszug</h3>
    ${f.ledger.length ? `<ul class="history ledger">${f.ledger.map((l) => `
      <li><span>${esc(l.text)}</span><span class="ledger-right"><b class="${l.amount < 0 ? 'bad' : 'ok'}">${l.amount > 0 ? '+' : '−'}${money(Math.abs(l.amount))}</b><time>${timeAgo(l.at, now)}</time></span></li>`).join('')}</ul>`
    : '<p class="muted">Noch keine Buchungen.</p>'}`;
}

// ---------- Ereignisse ----------

defineActions({
  async logout() {
    await api('logout', {}).catch(() => {});
    closeAllModals();
    app.label = null;
    app.state = null;
    app.charts = null;
    rerender();
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
  for (const out of document.querySelectorAll(`[data-output="${key}"]`)) out.textContent = e.target.value;
});

window.addEventListener('hashchange', () => {
  if (parseRoute().name === 'charts') app.charts = null;
  render();
  window.scrollTo(0, 0);
});

document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible' && app.state) refresh();
});

let lastAutoRefresh = 0;
setInterval(() => {
  if (!app.state) return;
  updateCountdowns();
  const due = app.state.running.some((a) => a.endsAt <= serverNow());
  if (due && Date.now() - lastAutoRefresh > 3000) {
    lastAutoRefresh = Date.now();
    refresh();
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
