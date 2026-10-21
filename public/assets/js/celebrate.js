import { cat, onStateChange } from './core.js';
import { genreIcon } from './cards.js';
import { avatar } from './avatar.js';
import { esc } from './ui.js';

/**
 * Feiern nach einem neuen Spielstand: Band wird Genre-Band oder Hybrid, Musiker steigt im Können
 * oder in der Bekanntheit auf. Mehrere kommen nacheinander. Nur CSS-Transform und Deckkraft (iPad).
 */
const SKILL_WORDS = ['', 'fortgeschritten', 'Profi', 'virtuos'];
const FAME_WORDS = ['', 'lokal bekannt', 'eine Szenegröße', 'ein Star'];

const queue = [];
let current = null;

onStateChange((before, after) => {
  const oldBands = new Map(before.bands.map((b) => [b.id, b.title]));
  for (const b of after.bands) {
    if (b.title && b.title !== oldBands.get(b.id)) queue.push(bandHtml(b));
  }
  const oldMusicians = new Map(before.musicians.map((m) => [m.id, m]));
  for (const m of after.musicians) {
    const old = oldMusicians.get(m.id);
    if (!old) continue;
    if (m.level > old.level) queue.push(skillHtml(m));
    if (m.fameLevel > old.fameLevel) queue.push(fameHtml(m));
  }
  if (!current) next();
});

function bandHtml(b) {
  return `
    <div class="celebrate-icons">${b.genres.map(genreIcon).join('')}</div>
    <div class="celebrate-text">${esc(b.name)} ist jetzt eine ${esc(b.title)}!</div>
    <div class="celebrate-sub">Sound + ${Math.round((b.genreBonus - 1) * 100)} %</div>`;
}

function skillHtml(m) {
  const best = m.instruments.reduce((a, b) => (b.skill > a.skill ? b : a));
  return `
    <div class="celebrate-face lvl-${m.level}">${avatar(m.look)}</div>
    <div class="celebrate-text">${esc(m.firstName)} ist ${esc(cat().instruments[best.type].at)} jetzt ${SKILL_WORDS[m.level]}!</div>
    <div class="celebrate-sub">Können-Stufe ${m.level} · ${esc(cat().levelNames[m.level])}</div>`;
}

function fameHtml(m) {
  return `
    <div class="celebrate-face fame">${avatar(m.look)}<span class="celebrate-stars">★ ★ ★</span></div>
    <div class="celebrate-text">${esc(m.firstName)} ist jetzt ${FAME_WORDS[m.fameLevel]}!</div>
    <div class="celebrate-sub">Bekanntheit ${m.fameLevel} · ${esc(cat().fameNames[m.fameLevel])}</div>`;
}

function next() {
  current?.remove();
  current = null;
  const html = queue.shift();
  if (!html) return;
  const el = document.createElement('div');
  el.className = 'celebrate';
  el.innerHTML = html;
  el.addEventListener('click', next);
  el.addEventListener('animationend', (e) => {
    if (e.animationName === 'celebrate-out' && e.target === el) next();
  });
  document.body.append(el);
  current = el;
}
