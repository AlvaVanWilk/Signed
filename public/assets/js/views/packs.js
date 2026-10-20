import { gearCard, managerCard, musicianCard, rarityName } from '../cards.js';
import { app, cat, defineActions, mutate, serverNow } from '../core.js';
import { esc, money, openModal, toast } from '../ui.js';

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
      ${scoutTile()}
      ${Object.entries(packs).map(([key, pack]) => packTile(key, pack)).join('')}
    </div>`;
}

function scoutTile() {
  const scout = app.state.scout;
  const rules = cat().rules;
  const canBuy = serverNow() >= scout.nextPurchase;
  const next = new Date(scout.nextPurchase * 1000).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' });
  return `
    <div class="pack pack-scout">
      <div class="pack-art"><span>Talent-Scout</span></div>
      <p>Der Scout stellt dir ${rules.scoutCards} vielversprechende Musiker vor, alle mindestens selten. Einer unterschreibt bei dir, die anderen ziehen weiter.</p>
      <p class="muted small">Gutscheine gibt es zum Label-Start, für die erste Platte, für perfekte Platten und für Platz 1 der Album-Charts.</p>
      ${scout.offer ? '<button class="btn primary" data-act="scoutShow">Auswahl ansehen</button>' : `
        <button class="btn primary" data-act="scoutOpen" data-voucher="1" ${scout.vouchers ? '' : 'disabled'}>Gutschein einlösen (${scout.vouchers})</button>
        <button class="btn" data-act="scoutOpen" data-voucher="" ${canBuy && app.state.label.money >= scout.price ? '' : 'disabled'}>Ohne Gutschein · ${money(scout.price)}</button>
        <span class="muted small">${canBuy ? 'Ohne Gutschein höchstens einmal pro Woche.' : `Ohne Gutschein wieder ab ${esc(next)}.`}</span>`}
    </div>`;
}

/** Die Auswahl des Scouts. Schließt sich von selbst, sobald jemand unterschrieben hat. */
export function openScoutOffer() {
  openModal({
    wide: true,
    className: 'scout-modal',
    render: () => {
      const offer = app.state.scout.offer;
      if (!offer) return null;
      return `
        <div class="eyebrow">Talent-Scout</div>
        <h2>Wen nimmst du unter Vertrag?</h2>
        <p class="muted">Nur einer unterschreibt, die anderen sind danach weg. Du kannst das Fenster schließen und später weiter überlegen.</p>
        <div class="tcg-grid scout-grid">
          ${offer.cards.map((m, index) => musicianCard(m, {
            act: null,
            showStatus: false,
            extra: `<button class="btn primary small" data-act="scoutPick" data-offer="${offer.id}" data-index="${index}">Unter Vertrag nehmen</button>`,
          })).join('')}
        </div>`;
    },
  });
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
  if (card.kind === 'musician') return musicianCard(card.musician, { showStatus: false });
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
  async scoutOpen({ voucher }) {
    const res = await mutate('scoutOpen', { voucher: !!voucher });
    if (res) openScoutOffer();
  },
  scoutShow() {
    openScoutOffer();
  },
  async scoutPick({ offer, index }) {
    const res = await mutate('scoutPick', { offerId: Number(offer), index: Number(index) });
    if (!res) return;
    toast(`${res.musician.firstName} ${res.musician.lastName} hat unterschrieben!`, 'ok');
    if (app.state.musicians.length < cat().rules.minMembers) {
      toast('Für eine Band brauchst du mindestens zwei Musiker – ein Demo-Pack hilft.', 'info');
    }
  },
  async buyPack({ pack }) {
    const res = await mutate('buyPack', { pack });
    if (res) openPackReveal(res.cards, cat().packs[pack].name);
  },
  noop() {},
});
