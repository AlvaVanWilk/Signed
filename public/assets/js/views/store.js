import { bonusList, gearIcon, gearTier, instrumentName, rarityName, whoCanPlay } from '../cards.js';
import { playedInstrument } from '../figure.js';
import { app, cat, defineActions, mutate, refresh, serverNow } from '../core.js';
import { confirmDialog, duration, esc, fmt0, money, off, openModal, percent, toast } from '../ui.js';

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
  return gearIcon(item);
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
    return `<div class="ms-locker empty" data-store-drop><b>Zurückgelegt</b><span class="ms-drophint">Instrument hierher ziehen</span><span>Es bleibt einen Tag für dich da, auch wenn das Regal wechselt. Eines je Label.</span></div>`;
  }
  const item = hold.item;
  return `
    <div class="ms-locker" data-store-drop>
      <b>Für dich zurückgelegt</b>
      <button class="ms-cell small r-${item.rarity}" data-act="storeItem" data-slot="${item.slot}" data-idx="${item.idx}">
        <span class="ms-ico">${icon(item)}</span><span class="ms-tag"><small>${esc(cat().gear[item.model].name)}</small>${money(item.price)}</span>
      </button>
      <small class="muted">noch <span data-store-hold="${hold.expiresAt}">${duration(Math.max(0, hold.expiresAt - serverNow()))}</span></small>
      <small class="ms-drophint">Ein anderes hierher ziehen ersetzt es</small>
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
          <small class="muted">${store.restock?.own ? 'Deine Lieferung bleibt' : 'Neues Angebot in'} <b data-store-timer>${duration(Math.max(0, store.endsAt - serverNow()))}</b>${store.restock?.own ? ', dann kommt wieder das Regal für alle' : ''}</small>
          ${store.restock ? `<button class="btn small ghost ms-restock" data-act="storeRestock" ${off(app.state.label.money < store.restock.price ? `Dafür fehlen noch ${money(store.restock.price - app.state.label.money)}.` : '')}>Neue Lieferung bestellen · ${money(store.restock.price)}</button>` : ''}
        </div>
      </div>
      <div class="ms-shop">
        <div class="ms-shelf">${store.items.map(cell).join('')}</div>
        ${locker()}
      </div>
      <p class="muted ms-note">Das Regal ist für alle Labels gleich und wechselt alle paar Stunden. Von jeder Art steht höchstens ein Instrument da; gute Stücke sind selten. Mit einer neuen Lieferung bekommst du sofort ein anderes Regal nur für dich, bis zum nächsten Wechsel; jede weitere kostet das Doppelte.</p>
    </div>`;
}


/** Ein Instrument im Detail, im Stil des Ladens (kein Sammelkarten-Look): Bild auf Filz, Werte, Preisschild, Kaufen und Zurücklegen. */
function openItem(slot, idx) {
  const store = app.state.store;
  const item = [...store.items, ...(store.hold ? [store.hold.item] : [])].find((i) => i.slot === slot && i.idx === idx);
  if (!item) return;
  const model = cat().gear[item.model];
  const need = needing(item);
  const isHold = !!store.hold && store.hold.item.slot === slot && store.hold.item.idx === idx;
  const canHold = !item.bought && !isHold && item.slot === store.slot;
  const modal = openModal({
    className: 'ms-detail',
    html: `
      <div class="ms-sheet r-${item.rarity} t-${gearTier(item)}">
        <div class="ms-sheet-art"><span class="ms-ico big">${icon(item)}</span><b class="ms-rar">${esc(rarityName(item.rarity))}</b></div>
        <div class="ms-sheet-body">
          <h2>${esc(model.name)}</h2>
          <small class="muted">${esc(instrumentName(item.type))}</small>
          ${bonusList(item.bonuses ?? [])}
          <p class="gt-reach">${whoCanPlay(item)}</p>
          <p class="ms-need-line">${need.length ? `Fehlt gerade: <b>${esc(need.map((m) => m.firstName).join(', '))}</b>` : `Im Label fehlt gerade niemandem ein Instrument dieser Art.`}</p>
          <div class="ms-buy">
            <span class="ms-price">${item.bought ? 'gekauft' : money(item.price)}</span>
            ${isHold ? '<button class="btn ghost small" data-release>Zurückgeben</button>' : `<button class="btn ghost small" data-hold ${off(canHold ? '' : item.bought ? 'Das hast du schon gekauft.' : 'Zurücklegen geht nur mit Instrumenten aus dem Regal, das gerade dasteht.')}>${store.hold ? 'Stattdessen zurücklegen' : 'Zurücklegen'}</button>`}
            <button class="btn primary" data-buy ${off(item.bought ? 'Das hast du schon gekauft.' : app.state.label.money < item.price ? `Dafür fehlen noch ${money(item.price - app.state.label.money)}.` : '')}>${item.bought ? 'Schon gekauft' : 'Kaufen'}</button>
          </div>
        </div>
      </div>`,
  });
  const done = (res) => {
    if (res) modal.close();
    return res;
  };
  modal.body.querySelector('[data-buy]')?.addEventListener('click', async () => {
    const res = done(await mutate('storeBuy', { slot, idx }));
    if (res) toast(`${model.name} liegt jetzt im Lager.`, 'ok');
  });
  modal.body.querySelector('[data-hold]')?.addEventListener('click', async () => {
    if (done(await mutate('storeHold', { idx }))) toast('Zurückgelegt: Das Instrument bleibt einen Tag für dich da.', 'ok');
  });
  modal.body.querySelector('[data-release]')?.addEventListener('click', async () => {
    if (await confirmDialog('Das Instrument wieder ins Regal stellen? Es kann dann verschwinden, wenn das Angebot wechselt.', { ok: 'Zurückgeben' })) done(await mutate('storeRelease'));
  });
}

// ---------- Ziehen aufs Zurücklegen ----------
// Mit der Maus einfach ziehen, mit dem Finger kurz halten und dann ziehen. Ein neues Instrument ersetzt das zurückgelegte.

let drag = null;
let swallowUntil = 0;

function lift() {
  if (!drag) return;
  drag.active = true;
  const ghost = drag.cell.querySelector('.ms-ico').cloneNode(true);
  ghost.className = 'ms-ghost';
  document.body.appendChild(ghost);
  drag.ghost = ghost;
  drag.cell.classList.add('lifting');
  navigator.vibrate?.(12);
  place(drag.x, drag.y);
}

/** Folgt dem Finger: höchstens einmal je Bild, ohne Übergänge (sonst ruckelt es). */
let frame = 0;
function place(x, y) {
  if (!drag) return;
  drag.px = x;
  drag.py = y;
  if (frame) return;
  frame = requestAnimationFrame(() => {
    frame = 0;
    if (!drag?.ghost) return;
    drag.ghost.style.transform = `translate3d(${drag.px - 56}px, ${drag.py - 70}px, 0) rotate(-6deg)`;
    const over = document.elementFromPoint(drag.px, drag.py)?.closest('[data-store-drop]') ?? null;
    if (over !== drag.over) {
      drag.over?.classList.remove('over');
      over?.classList.add('over');
      drag.over = over;
    }
  });
}

async function drop(cancelled) {
  const d = drag;
  drag = null;
  if (!d) return;
  clearTimeout(d.timer);
  if (!d.active) return;
  swallowUntil = Date.now() + 400;
  d.ghost.remove();
  d.cell.classList.remove('lifting');
  document.querySelectorAll('[data-store-drop]').forEach((el) => el.classList.remove('over'));
  if (cancelled || !d.over) return;
  const slot = Number(d.cell.dataset.slot);
  if (slot !== app.state.store.slot) return;
  if (await mutate('storeHold', { idx: Number(d.cell.dataset.idx) })) toast('Zurückgelegt: Das Instrument bleibt einen Tag für dich da.', 'ok');
}

document.addEventListener('pointerdown', (e) => {
  const cell = e.target.closest('.ms-shelf .ms-cell');
  if (!cell || e.button > 0 || cell.classList.contains('sold') || cell.classList.contains('held')) return;
  drag = { cell, x: e.clientX, y: e.clientY, active: false, touch: e.pointerType !== 'mouse' };
  if (drag.touch) drag.timer = setTimeout(lift, 300);
});
document.addEventListener('pointermove', (e) => {
  if (!drag) return;
  drag.x = e.clientX;
  drag.y = e.clientY;
  if (!drag.active) {
    if (Math.hypot(e.clientX - drag.x0, e.clientY - drag.y0) < 7) return;
    if (drag.touch) {
      clearTimeout(drag.timer);
      drag = null;
      return;
    }
    lift();
  }
  if (drag?.active) place(e.clientX, e.clientY);
});
document.addEventListener('pointerdown', (e) => { if (drag) { drag.x0 = e.clientX; drag.y0 = e.clientY; } });
document.addEventListener('pointerup', () => drop(false));
document.addEventListener('pointercancel', () => drop(true));
document.addEventListener('touchmove', (e) => { if (drag?.active) e.preventDefault(); }, { passive: false });
document.addEventListener('click', (e) => {
  if (Date.now() < swallowUntil && e.target.closest('.ms-cell')) {
    e.preventDefault();
    e.stopPropagation();
  }
}, true);
document.addEventListener('dragstart', (e) => { if (e.target.closest?.('.ms-cell')) e.preventDefault(); });

defineActions({
  /** Neue Lieferung: kostet Geld, das jetzige Regal ist danach weg (Zurückgelegtes bleibt). */
  async storeRestock() {
    const price = app.state.store.restock.price;
    if (!await confirmDialog(`Neue Lieferung für ${money(price)} bestellen? Das jetzige Regal ist dann weg, ein zurückgelegtes Instrument bleibt.`, { ok: 'Bestellen' })) return;
    if (await mutate('storeRestock')) {
      doorsPending = true;
      enter(true);
      toast('Die neue Lieferung steht im Regal.', 'ok');
    }
  },
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

const DOORS_SEEN = 'signed.storeDoorsSeen';
const doorsSeen = () => { try { return localStorage.getItem(DOORS_SEEN) === '1'; } catch { return false; } };

/** Beim allerersten Betreten: Doppeltür geht auf und gibt das Regal frei. Danach geht es ohne Tür hinein. */
/** force: auch nach dem ersten Besuch (neue Lieferung – die Türen gehen auf und zeigen das frische Regal). */
export function enter(force = false) {
  if (!doorsPending || document.querySelector('.ms-doors')) return;
  doorsPending = false;
  if ((doorsSeen() && !force) || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  try { localStorage.setItem(DOORS_SEEN, '1'); } catch { /* ohne Speicher eben jedes Mal */ }
  const doors = document.createElement('div');
  doors.className = 'ms-doors';
  doors.innerHTML = `<div class="ms-door l">${DOOR}<b>MUSIC</b></div><div class="ms-door r">${DOOR}<b>STORE</b></div><span class="ms-glow"></span>`;
  document.body.appendChild(doors);
  requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(() => doors.classList.add('open'), 260)));
  setTimeout(() => doors.remove(), 2500);
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
