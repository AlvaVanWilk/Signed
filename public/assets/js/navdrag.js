import { api } from './api.js';
import { app } from './core.js';

/**
 * Band- und Favoritenknöpfe verschieben: mit der Maus einfach ziehen, mit dem Finger kurz gedrückt halten und dann ziehen
 * (sonst scrollt die Leiste wie gewohnt). Die neue Reihenfolge merkt sich das Label (navOrder).
 */

const HOLD_MS = 350;
const SLOP = 8;
let press = null;
let swallowClickUntil = 0;

const SEL = '.subnav [data-nav], .lb-bar [data-nav]';
/** Die Band-Leiste steht senkrecht (am Handy waagerecht); die Favoriten-Leiste immer waagerecht. */
const vertical = (btn) => btn.parentElement.classList.contains('lb-bar') && getComputedStyle(btn.parentElement).flexDirection === 'column';

function begin() {
  if (!press || !press.btn.isConnected) return;
  press.active = true;
  press.vert = vertical(press.btn);
  press.startLeft = press.vert ? press.btn.offsetTop : press.btn.offsetLeft;
  press.btn.classList.add('dragging');
  navigator.vibrate?.(15);
}

function move(x, y) {
  const { btn, vert } = press;
  const nav = btn.parentElement;
  const pos = (el) => (vert ? el.offsetTop : el.offsetLeft);
  const size = (el) => (vert ? el.offsetHeight : el.offsetWidth);
  const shift = () => ((vert ? y - press.y : x - press.x)) - (pos(btn) - press.startLeft);
  const center = () => pos(btn) + shift() + size(btn) / 2;
  const mid = (el) => pos(el) + size(el) / 2;
  const prev = btn.previousElementSibling;
  const next = btn.nextElementSibling;
  if (prev?.dataset.nav && center() < mid(prev)) nav.insertBefore(btn, prev);
  else if (next?.dataset.nav && center() > mid(next)) nav.insertBefore(next, btn);
  btn.style.transform = `${vert ? 'translateY' : 'translateX'}(${shift()}px) scale(1.03)`;
}

function finish(cancelled) {
  const p = press;
  press = null;
  if (!p) return;
  clearTimeout(p.timer);
  if (!p.active) return;
  p.btn.classList.remove('dragging');
  p.btn.style.transform = '';
  swallowClickUntil = Date.now() + 400;
  if (cancelled || !p.btn.isConnected) return;
  const order = [...p.btn.parentElement.querySelectorAll('[data-nav]')].map((el) => el.dataset.nav);
  if (order.join() === p.before) return;
  // Die Leiste kennt nur Bands, die Favoriten-Leiste nur Musiker: Der jeweils andere Teil der Reihenfolge bleibt erhalten.
  const own = (k) => (p.btn.dataset.nav.startsWith('b') ? k.startsWith('b') : !k.startsWith('b'));
  const rest = (app.state.label.navOrder ?? []).filter((k) => !own(k));
  const merged = p.btn.dataset.nav.startsWith('b') ? [...order, ...rest] : [...rest, ...order];
  app.state.label.navOrder = merged;
  api('navOrder', { order: merged }).catch(() => {});
}

document.addEventListener('pointerdown', (e) => {
  const btn = e.target.closest(SEL);
  if (!btn || e.button > 0) return;
  const before = [...btn.parentElement.querySelectorAll('[data-nav]')].map((el) => el.dataset.nav).join();
  press = { btn, x: e.clientX, y: e.clientY, active: false, touch: e.pointerType !== 'mouse', before };
  if (press.touch) press.timer = setTimeout(begin, HOLD_MS);
});

document.addEventListener('pointermove', (e) => {
  if (!press) return;
  if (!press.active) {
    const moved = Math.hypot(e.clientX - press.x, e.clientY - press.y) > SLOP;
    if (!moved) return;
    if (press.touch) {
      // Bewegt vor dem Festhalten: Das ist Scrollen, kein Verschieben.
      clearTimeout(press.timer);
      press = null;
      return;
    }
    begin();
  }
  if (press?.active) move(e.clientX, e.clientY);
});

document.addEventListener('pointerup', () => finish(false));
document.addEventListener('pointercancel', () => finish(true));
// Beim Ziehen mit dem Finger nicht die Leiste scrollen.
document.addEventListener('touchmove', (e) => {
  if (press?.active) e.preventDefault();
}, { passive: false });
// Nach dem Loslassen nicht noch die Band öffnen; langes Drücken öffnet kein Menü.
document.addEventListener('click', (e) => {
  if (Date.now() < swallowClickUntil && e.target.closest(SEL)) {
    e.preventDefault();
    e.stopPropagation();
  }
}, true);
document.addEventListener('contextmenu', (e) => {
  if (e.target.closest(SEL)) e.preventDefault();
});
