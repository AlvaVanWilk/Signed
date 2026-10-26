import { cat, onStateChange } from './core.js';
import { genreIcon } from './cards.js';
import { avatar } from './avatar.js';
import { esc, openModal, timeAgo } from './ui.js';

/**
 * Feiern nach einem neuen Spielstand: Band wird Genre-Band oder Hybrid, Musiker steigt im Können
 * oder in der Bekanntheit auf. Mehrere kommen nacheinander. Nur CSS-Transform und Deckkraft (iPad).
 */
const FAME_WORDS = ['', 'lokal bekannt', 'eine Szenegröße', 'ein Star'];

const queue = [];
let current = null;

onStateChange((before, after) => {
  // Backstage-News nach einer Pause; Platz 1 bekommt zusätzlich seinen großen Auftritt.
  if (after.news?.length) showNews(after);
  const fresh = before ? after.history.filter((h) => h.id > (before.history[0]?.id ?? 0)) : after.news ?? [];
  for (const h of fresh) {
    if (h.kind === 'chart1') queue.push(chartHtml(h.text));
  }
  if (!before) {
    if (!current) next();
    return;
  }
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
    <div class="celebrate-text">${esc(m.firstName)} ist ${esc(cat().instruments[best.type].at)} jetzt ${esc(cat().levelWords[m.level])}!</div>
    <div class="celebrate-sub">Kann jetzt ${['', 'seltene', 'epische', 'legendäre'][m.level] ?? ''} Instrumente spielen</div>`;
}

function fameHtml(m) {
  return `
    <div class="celebrate-face fame">${avatar(m.look)}<span class="celebrate-stars">★ ★ ★</span></div>
    <div class="celebrate-text">${esc(m.firstName)} ist jetzt ${FAME_WORDS[m.fameLevel]}!</div>
    <div class="celebrate-sub">Bekanntheit ${m.fameLevel} · ${esc(cat().fameNames[m.fameLevel])}</div>`;
}

function chartHtml(text) {
  return `
    <div class="celebrate-trophy">🏆</div>
    <div class="celebrate-text">${esc(text)}</div>
    <div class="celebrate-sub">Die beste Platte der Charts!</div>`;
}

const NEWS_ICONS = { chart1: '🏆', chart: '📈', transfer: '🔁', scout: '🎟️', level: '⭐', fame: '✨' };
let newsShownFor = 0;

function showNews(state) {
  const latest = state.news[0].id;
  if (latest <= newsShownFor) return;
  newsShownFor = latest;
  const modal = openModal({
    html: `
      <div class="eyebrow">Backstage-News</div>
      <h2>Das ist inzwischen passiert</h2>
      <ul class="history news">${state.news.map((n) => `
        <li class="${n.kind === 'chart1' ? 'news-top' : ''}"><span>${NEWS_ICONS[n.kind] ?? '•'} ${esc(n.text)}</span><time>${timeAgo(n.createdAt, state.now)}</time></li>`).join('')}
      </ul>
      <div class="dialog-actions"><button class="btn primary" data-close>Weiter</button></div>`,
  });
  modal.body.querySelector('[data-close]').addEventListener('click', () => modal.close());
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
