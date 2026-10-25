import { api } from './api.js';
import { actions, app } from './core.js';

/**
 * Band- und Favoritenknöpfe verschieben: mit der Maus einfach ziehen, mit dem Finger kurz gedrückt halten und dann ziehen
 * (sonst scrollt die Leiste wie gewohnt). Die neue Reihenfolge merkt sich das Label (navOrder).
 * In der Künstlerleiste lässt sich eine Band auch mitten auf eine andere legen: Dann fragt das Zusammentun-Fenster nach.
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

/** Liegt der Zeiger in der Mitte einer anderen Band (nicht am Rand), ist das ein Zusammentun statt Umsortieren. */
function mergeTarget(x, y) {
  const { btn } = press;
  if (!btn.parentElement.classList.contains('lb-bar')) return null;
  for (const el of btn.parentElement.querySelectorAll('[data-nav]')) {
    if (el === btn) continue;
    const r = el.getBoundingClientRect();
    const fx = (x - r.left) / r.width;
    const fy = (y - r.top) / r.height;
    const inner = press.vert ? fy > 0.28 && fy < 0.72 && fx > 0 && fx < 1 : fx > 0.28 && fx < 0.72 && fy > 0 && fy < 1;
    if (inner) return el;
  }
  return null;
}

function move(x, y) {
  const { btn, vert } = press;
  const nav = btn.parentElement;
  const target = mergeTarget(x, y);
  if (target !== press.target) {
    press.target?.classList.remove('drop-target');
    target?.classList.add('drop-target');
    press.target = target;
  }
  if (target) {
    const moved = pos0(btn, vert) - press.startLeft;
    btn.style.transform = `translate(${x - press.x - (vert ? 0 : moved)}px, ${y - press.y - (vert ? moved : 0)}px) scale(0.96)`;
    btn.classList.add('merging');
    return;
  }
  btn.classList.remove('merging');
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

const pos0 = (el, vert) => (vert ? el.offsetTop : el.offsetLeft);

function finish(cancelled) {
  const p = press;
  press = null;
  if (!p) return;
  clearTimeout(p.timer);
  if (!p.active) return;
  p.btn.classList.remove('dragging', 'merging');
  p.btn.style.transform = '';
  p.target?.classList.remove('drop-target');
  swallowClickUntil = Date.now() + 400;
  if (cancelled || !p.btn.isConnected) return;
  if (p.target) {
    const id = (el) => el.dataset.nav.slice(1);
    actions.togetherWith?.({ id: id(p.btn), other: id(p.target) });
    return;
  }
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
