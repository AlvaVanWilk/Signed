import { bonusList, gearIcon, gearTier, instrumentName, rarityName, whoCanPlay, withBand } from '../cards.js';
import { playedInstrument } from '../figure.js';
import { app, cat, defineActions, mutate, onStateChange, refresh, serverNow } from '../core.js';
import { duration, esc, money, off, openModal, toast } from '../ui.js';

/**
 * Music Store: oben das Angebot des Tages (für alle Labels gleich, meist seltene Instrumente, ab und zu ein episches, ganz selten ein legendäres,
 * das groß beworben wird), unten die Grundausstattung – jedes graue Modell, immer da. Gekaufte Angebote verschwinden aus dem Regal.
 * Beim ersten Betreten gehen die Türen auf (Übergang).
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
  // Gekauft: Das Fach bleibt leer stehen.
  if (item.bought) return '<span class="ms-cell empty" aria-label="Gekauft"><b class="ms-soldcard">Verkauft</b></span>';
  const need = needing(item);
  const star = item.tier === 3;
  return `
    <button class="ms-cell r-${item.rarity} ${star ? 'star' : ''}" data-act="storeItem" data-idx="${item.idx}" aria-label="${esc(cat().gear[item.model].name)}">
      ${star ? '<span class="ms-rays" aria-hidden="true"></span>' : ''}
      <span class="ms-ico">${icon(item)}</span>
      ${need.length ? '<i class="ms-need" title="Dafür gibt es jemanden ohne Instrument"></i>' : ''}
      ${item.tier === 2 ? '<b class="ms-sticker blue">Top-Angebot</b>' : ''}
      <span class="ms-tag"><small>${esc(cat().gear[item.model].name)}</small>${money(item.price)}</span>
    </button>`;
}

/** Wimpelkette über dem Schaufenster. */
function garland() {
  const colors = ['#f2c14e', '#efe4c6', '#b8432f', '#2f6f6a'];
  const n = 16;
  const w = 1000 / n;
  const sag = (x) => 6 + 10 * Math.sin((x / 1000) * Math.PI);
  const flags = Array.from({ length: n }, (_, i) => {
    const x = i * w + 6;
    const y1 = sag(x);
    const y2 = sag(x + w - 12);
    return `<path d="M${x.toFixed(1)} ${y1.toFixed(1)} L${(x + w - 12).toFixed(1)} ${y2.toFixed(1)} L${(x + (w - 12) / 2).toFixed(1)} ${((y1 + y2) / 2 + 26).toFixed(1)} Z" fill="${colors[i % 4]}"/>`;
  }).join('');
  return `<svg class="ms-garland" viewBox="0 0 1000 44" preserveAspectRatio="none" aria-hidden="true"><path d="M0 6 Q500 26 1000 6" fill="none" stroke="#2a1d12" stroke-width="2"/>${flags}</svg>`;
}

/** Schaufenster fürs legendäre Instrument: über dem Regal, mit Wimpeln, Aufsteller und Stickern. Gekauft: leere Vitrine. */
function showcase(star) {
  const kind = (star.bonuses ?? []).find((b) => b.slot === 'gold')?.kind;
  const info = cat().gearBonusInfo[kind] ?? { name: 'Einzelstück', ad: '' };
  return `
    <div class="ms-window ${star.bought ? 'sold' : ''}">
      ${garland()}
      <div class="ms-window-row">
        <div class="ms-rollup"><small>Einzelstück</small><b>${esc(info.name)}</b><span>${esc(info.ad ?? '')}</span></div>
        <div class="ms-vitrine"><span class="ms-glass" aria-hidden="true"></span>${star.bought ? '<span class="ms-cell empty"><b class="ms-soldcard">Verkauft – an dich!</b></span>' : cell(star)}<span class="ms-plinth" aria-hidden="true"></span></div>
        <div class="ms-stickers">${star.bought ? '' : '<span class="ms-burst">Nur<br>heute!</span><span class="ms-sticker gold">Legendär</span>'}</div>
      </div>
    </div>`;
}

/** Grundausstattung: klein an der Wand, immer da. */
function basic(item) {
  const need = needing(item);
  return `
    <button class="ms-bcell" data-act="storeBasic" data-model="${item.model}" aria-label="${esc(cat().gear[item.model].name)}">
      <span class="ms-bico">${icon(item)}</span>
      ${need.length ? '<i class="ms-need" title="Dafür gibt es jemanden ohne Instrument"></i>' : ''}
      <span class="ms-btag"><small>${esc(cat().gear[item.model].name)}</small>${money(item.price)}</span>
    </button>`;
}

export function render() {
  const store = app.state.store;
  // Das legendäre steht extra im Schaufenster über dem Regal; im Regal immer gleich viele Fächer (gekaufte bleiben leer).
  const star = store.items.find((i) => i.tier === 3) ?? null;
  const shelf = store.items.filter((i) => i !== star);
  return `
    <div class="ms-page">
      <div class="ms-top">
        <div class="ms-sign">${SIGN}</div>
        <div class="ms-info">
          <div class="money-pill">${money(app.state.label.money)}</div>
          <small class="muted">Neues Angebot in <b data-store-timer>${duration(Math.max(0, store.endsAt - serverNow()))}</b></small>
        </div>
      </div>
      <div class="ms-shop ${store.gold ? 'gold' : ''}">
        ${star ? `<div class="ms-promo ${star.bought ? '' : 'gold'}">${star.bought ? '<b>Im Schaufenster</b><span>Das Einzelstück von heute gehört jetzt dir.</span>' : '<b>Heute ganz besonders:</b> ein legendäres Instrument!'}</div>${showcase(star)}` : ''}
        <div class="ms-promo"><b>Angebot des Tages</b><span>Besondere Stücke, nur heute</span></div>
        <div class="ms-shelf">${shelf.map(cell).join('<i class="ms-post" aria-hidden="true"></i>')}</div>
      </div>
      <div class="ms-basics">
        <div class="ms-basics-head"><b>Grundausstattung</b><span>Jedes Instrument, immer da</span></div>
        <div class="ms-wall">${store.basics.map(basic).join('')}</div>
      </div>
      <p class="muted ms-note">Das Angebot ist für alle Labels gleich und wechselt jeden Morgen. Meist sind es seltene Instrumente, ab und zu ein episches – und ganz selten ein legendäres. Die Grundausstattung gibt es immer, so oft du willst.</p>
    </div>`;
}

/** Ein Instrument im Detail, im Stil des Ladens (kein Sammelkarten-Look): Bild auf Filz, Boni, Preisschild, Kaufen. */
function openItem(item, basic = false) {
  const model = cat().gear[item.model];
  const need = needing(item);
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
          <p class="ms-need-line">${need.length ? `Fehlt gerade: <b>${esc(need.map((m) => withBand(m)).join(', '))}</b>` : 'Im Label fehlt gerade niemandem ein Instrument dieser Art.'}</p>
          <div class="ms-buy">
            <span class="ms-price">${money(item.price)}</span>
            ${basic ? '<small class="muted">Immer vorrätig</small>' : '<small class="muted">Nur heute</small>'}
            <button class="btn primary" data-buy ${off(app.state.label.money < item.price ? `Dafür fehlen noch ${money(item.price - app.state.label.money)}.` : '')}>Kaufen</button>
          </div>
        </div>
      </div>`,
  });
  modal.body.querySelector('[data-buy]')?.addEventListener('click', async () => {
    const res = await (basic ? mutate('storeBuyBasic', { model: item.model }) : mutate('storeBuy', { slot: item.slot, idx: item.idx }));
    if (res) {
      modal.close();
      toast(`${model.name} liegt jetzt im Lager.`, 'ok');
    }
  });
}

defineActions({
  storeItem({ idx }) {
    const item = app.state.store.items.find((i) => i.idx === Number(idx));
    if (item) openItem(item);
  },
  storeBasic({ model }) {
    const item = app.state.store.basics.find((b) => b.model === model);
    if (item) openItem(item, true);
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
}, 1000);

// Ein legendäres Instrument im Angebot: einmal je Angebot ein Hinweis, wo immer man gerade ist.
const GOLD_SEEN = 'signed.storeGoldSeen';
onStateChange((before, after) => {
  if (!after.store?.gold) return;
  let seen = 0;
  try { seen = Number(localStorage.getItem(GOLD_SEEN) ?? 0); } catch { /* ohne Speicher: jedes Mal beim Laden */ }
  if (seen === after.store.slot) return;
  try { localStorage.setItem(GOLD_SEEN, String(after.store.slot)); } catch { /* egal */ }
  toast('Im Music Store gibt es heute ein legendäres Instrument!', 'gold');
});
