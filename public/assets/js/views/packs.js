import { gearCard, managerCard, musicianCard, rarityName } from '../cards.js';
import { app, cat, defineActions, mutate } from '../core.js';
import { esc, money, openModal } from '../ui.js';

const SLOT_NAMES = { musician: 'Musiker', gear: 'Instrument', manager: 'Manager', any: 'Überraschung' };
const RARITY_ORDER = ['common', 'rare', 'epic', 'legendary'];

export function render() {
  const packs = cat().packs;
  return `
    <div class="section-head">
      <div><h1>Packs</h1><p class="muted">Jeder Musiker existiert genau einmal im ganzen Spiel – wer ihn zieht, hat ihn.</p></div>
      <div class="money-pill">${money(app.state.label.money)}</div>
    </div>
    <div class="pack-grid">
      ${Object.entries(packs).map(([key, pack]) => packTile(key, pack)).join('')}
    </div>`;
}

function packTile(key, pack) {
  const affordable = app.state.label.money >= pack.price;
  const slotCounts = {};
  for (const slot of pack.slots) slotCounts[slot] = (slotCounts[slot] || 0) + 1;
  const total = Object.values(pack.odds).reduce((a, b) => a + b, 0);
  return `
    <div class="pack pack-${key}">
      <div class="pack-art"><span>${esc(pack.name)}</span></div>
      <p>${esc(pack.desc)}</p>
      <p class="muted small">${Object.entries(slotCounts).map(([slot, n]) => `${n} × ${SLOT_NAMES[slot]}`).join(' · ')}</p>
      <div class="odds" title="Chancen je Karte">
        ${RARITY_ORDER.filter((r) => pack.odds[r] > 0).map((r) => `
          <span class="odds-seg rarity-bg-${r}" style="flex:${pack.odds[r]}" title="${esc(rarityName(r))}: ${Math.round((pack.odds[r] / total) * 100)} %"></span>`).join('')}
      </div>
      <div class="odds-legend small">${RARITY_ORDER.filter((r) => pack.odds[r] > 0).map((r) => `<span><i class="dot rarity-bg-${r}"></i>${esc(rarityName(r))} ${Math.round((pack.odds[r] / total) * 100)} %</span>`).join('')}</div>
      <button class="btn primary" data-act="buyPack" data-pack="${key}" ${affordable ? '' : 'disabled'}>Kaufen · ${money(pack.price)}</button>
    </div>`;
}

function revealFace(card) {
  if (card.kind === 'musician') return musicianCard(card.musician, { act: 'noop' });
  if (card.kind === 'gear') return gearCard(card.gear);
  return managerCard(card.manager);
}

function cardRarity(card) {
  if (card.kind === 'gear') return cat().gear[card.gear.model].rarity;
  return (card.musician || card.manager).rarity;
}

/** Karten verdeckt zeigen, Klick deckt auf. */
export function openPackReveal(cards, title, intro = '') {
  const modal = openModal({
    wide: true,
    className: 'reveal-modal',
    html: `
      <h2>${esc(title)}</h2>
      ${intro ? `<p class="muted">${esc(intro)}</p>` : ''}
      <div class="reveal-grid">
        ${cards.map((card, i) => `
          <div class="flip rarity-${cardRarity(card)}" data-index="${i}" style="--delay:${i * 80}ms">
            <div class="flip-inner">
              <div class="flip-back"><span class="logo">Signed</span><small>Tippen zum Aufdecken</small></div>
              <div class="flip-front">${revealFace(card)}</div>
            </div>
          </div>`).join('')}
      </div>
      <div class="dialog-actions">
        <button class="btn ghost" data-reveal-all>Alle aufdecken</button>
        <button class="btn primary" data-reveal-done>Weiter</button>
      </div>`,
  });
  const flips = [...modal.body.querySelectorAll('.flip')];
  modal.body.addEventListener('click', (e) => {
    const flip = e.target.closest('.flip');
    if (flip) flip.classList.add('open');
    if (e.target.closest('[data-reveal-all]')) {
      flips.forEach((f, i) => setTimeout(() => f.classList.add('open'), i * 150));
    }
    if (e.target.closest('[data-reveal-done]')) modal.close();
  });
}

defineActions({
  async buyPack({ pack }) {
    const res = await mutate('buyPack', { pack });
    if (res) openPackReveal(res.cards, cat().packs[pack].name);
  },
  noop() {},
});
