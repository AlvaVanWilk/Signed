import { gearCard, gearHue, instrumentIcon, instrumentName, rarityName } from '../cards.js';
import { playedInstrument } from '../figure.js';
import { app, cat, defineActions, mutate, refresh, serverNow } from '../core.js';
import { confirmDialog, duration, esc, money, openModal, toast } from '../ui.js';

/**
 * Music Store: ein Regal mit 5 bis 10 Instrumenten (je Art höchstens eins), für alle Labels gleich, das alle paar Stunden wechselt.
 * Ein Instrument lässt sich einen Tag lang zurücklegen. Beim Betreten gehen die Türen auf (Übergang).
 */

const SIGN = `
  <svg class="ms-signsvg" viewBox="0 0 360 96" aria-hidden="true">
    <path d="M70 0 L78 22 M290 0 L282 22" stroke="#3a2a1c" stroke-width="3" fill="none" stroke-linecap="round"/>
    <rect x="22" y="20" width="316" height="68" rx="10" fill="#6a4529" stroke="#2c1d12" stroke-width="3"/>
    <rect x="30" y="28" width="300" height="52" rx="6" fill="#7b5233" stroke="#4b3019" stroke-width="1.5"/>
    <path d="M36 40 H324 M36 68 H324" stroke="#5b3b22" stroke-width="1" opacity=".5"/>
    <text x="180" y="62" text-anchor="middle" font-family="Georgia, 'Times New Roman', serif" font-size="34" font-weight="700" letter-spacing="3" fill="#f3e3bd" stroke="#2c1d12" stroke-width="1.2" paint-order="stroke">MUSIC STORE</text>
    <circle cx="44" cy="54" r="3" fill="#2c1d12"/><circle cx="316" cy="54" r="3" fill="#2c1d12"/>
  </svg>`;

const playedType = (m) => playedInstrument(m);
const hasGear = (m) => app.state.gear.some((g) => g.musicianId === m.id && cat().gear[g.model]?.type === playedType(m));
/** Wer in einer Band spielt und für dieses Instrument noch keins hat: für die ist es gedacht. */
const needing = (item) => app.state.musicians.filter((m) => playedType(m) === item.type && !hasGear(m));

function icon(item) {
  const model = cat().gear[item.model];
  return instrumentIcon(item.type, !!model.electric, gearHue(item));
}

function cell(item) {
  const state = item.bought ? 'sold' : item.held ? 'held' : '';
  const need = needing(item);
  return `
    <button class="ms-cell r-${item.rarity} ${state}" data-act="storeItem" data-slot="${item.slot}" data-idx="${item.idx}" aria-label="${esc(cat().gear[item.model].name)}">
      <span class="ms-ico">${icon(item)}</span>
      ${need.length && !item.bought ? '<i class="ms-need" title="Dafür gibt es jemanden ohne Instrument"></i>' : ''}
      ${item.held ? '<b class="ms-ribbon">zurückgelegt</b>' : item.bought ? '<b class="ms-ribbon sold">gekauft</b>' : ''}
      <span class="ms-tag"><small>${esc(cat().gear[item.model].name)}</small>${item.bought ? '–' : money(item.price)}</span>
    </button>`;
}

function locker() {
  const hold = app.state.store.hold;
  if (!hold) {
    return `<div class="ms-locker empty"><b>Zurückgelegt</b><span>Tipp ein Instrument an und lass es einen Tag für dich zurücklegen. Eines je Label.</span></div>`;
  }
  const item = hold.item;
  return `
    <div class="ms-locker">
      <b>Für dich zurückgelegt</b>
      <button class="ms-cell small r-${item.rarity}" data-act="storeItem" data-slot="${item.slot}" data-idx="${item.idx}">
        <span class="ms-ico">${icon(item)}</span><span class="ms-tag"><small>${esc(cat().gear[item.model].name)}</small>${money(item.price)}</span>
      </button>
      <small class="muted">noch <span data-store-hold="${hold.expiresAt}">${duration(Math.max(0, hold.expiresAt - serverNow()))}</span></small>
    </div>`;
}

export function render() {
  const store = app.state.store;
  return `
    <div class="ms-page">
      <div class="ms-top">
        <div class="ms-sign">${SIGN}</div>
        <div class="ms-info">
          <div class="money-pill">${money(app.state.label.money)}</div>
          <small class="muted">Neues Angebot in <b data-store-timer>${duration(Math.max(0, store.endsAt - serverNow()))}</b></small>
        </div>
      </div>
      <div class="ms-shop">
        <div class="ms-shelf">${store.items.map(cell).join('')}</div>
        ${locker()}
      </div>
      <p class="muted ms-note">Das Regal ist für alle Labels gleich und wechselt alle paar Stunden. Von jeder Art steht höchstens ein Instrument da; gute Stücke sind selten.</p>
    </div>`;
}

/** Ein Instrument im Detail: Karte, Preis, wer es braucht, Kaufen und Zurücklegen. */
function openItem(slot, idx) {
  const store = app.state.store;
  const item = [...store.items, ...(store.hold ? [store.hold.item] : [])].find((i) => i.slot === slot && i.idx === idx);
  if (!item) return;
  const need = needing(item);
  const isHold = !!store.hold && store.hold.item.slot === slot && store.hold.item.idx === idx;
  const holdBusy = !!store.hold && !isHold;
  const modal = openModal({
    className: 'ms-detail',
    html: `
      <div class="ms-detail-card">${gearCard({ ...item, musicianId: null })}</div>
      <p class="ms-need-line">${need.length ? `Dafür gibt es jemanden ohne ${esc(instrumentName(item.type))}: <b>${esc(need.map((m) => m.firstName).join(', '))}</b>.` : `Im Label hat gerade niemand eine Lücke bei „${esc(instrumentName(item.type))}“.`}</p>
      <div class="dialog-actions">
        ${isHold ? '<button class="btn ghost" data-release>Zurückgeben</button>' : `<button class="btn ghost" data-hold ${item.bought || holdBusy ? 'disabled' : ''} title="${holdBusy ? 'Du hast schon ein Instrument zurücklegen lassen' : 'Einen Tag lang für dich aufbewahren'}">Zurücklegen</button>`}
        <button class="btn primary" data-buy ${item.bought || app.state.label.money < item.price ? 'disabled' : ''}>${item.bought ? 'Schon gekauft' : `Kaufen · ${money(item.price)}`}</button>
      </div>`,
  });
  const done = (res) => {
    if (res) modal.close();
    return res;
  };
  modal.body.querySelector('[data-buy]')?.addEventListener('click', async () => {
    const res = done(await mutate('storeBuy', { slot, idx }));
    if (res) toast(`${cat().gear[item.model].name} liegt jetzt im Lager.`, 'ok');
  });
  modal.body.querySelector('[data-hold]')?.addEventListener('click', async () => {
    if (done(await mutate('storeHold', { idx }))) toast('Zurückgelegt: Das Instrument bleibt einen Tag für dich da.', 'ok');
  });
  modal.body.querySelector('[data-release]')?.addEventListener('click', async () => {
    if (await confirmDialog('Das Instrument wieder ins Regal stellen? Es kann dann verschwinden, wenn das Angebot wechselt.', { ok: 'Zurückgeben' })) done(await mutate('storeRelease'));
  });
}

defineActions({
  storeItem({ slot, idx }) {
    openItem(Number(slot), Number(idx));
  },
});

// ---------- Türen ----------

let doorsPending = location.hash.startsWith('#/store');
window.addEventListener('hashchange', () => {
  if (location.hash.startsWith('#/store')) doorsPending = true;
});

const DOOR = `
  <span class="panel top"></span><span class="panel bottom"></span>
  <span class="glass"></span><span class="handle"></span>`;

/** Beim Betreten aus einer anderen Seite: Doppeltür geht auf und gibt das Regal frei. */
export function enter() {
  if (!doorsPending || document.querySelector('.ms-doors')) return;
  doorsPending = false;
  if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  const doors = document.createElement('div');
  doors.className = 'ms-doors';
  doors.innerHTML = `<div class="ms-door l">${DOOR}<b>MUSIC</b></div><div class="ms-door r">${DOOR}<b>STORE</b></div><span class="ms-glow"></span>`;
  document.body.appendChild(doors);
  requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(() => doors.classList.add('open'), 260)));
  setTimeout(() => doors.remove(), 2300);
}

// Zähler: sekündlich; läuft das Regal ab, kommt der neue Stand vom Server.
setInterval(() => {
  const timer = document.querySelector('[data-store-timer]');
  const store = app.state?.store;
  if (timer && store) {
    const left = store.endsAt - serverNow();
    timer.textContent = duration(Math.max(0, left));
    if (left <= -2) refresh({ quiet: true });
  }
  const hold = document.querySelector('[data-store-hold]');
  if (hold) hold.textContent = duration(Math.max(0, Number(hold.dataset.storeHold) - serverNow()));
}, 1000);
