import { gearCard, managerCard, musicianCard, rarityName } from '../cards.js';
import { app, cat, defineActions, mutate, serverNow } from '../core.js';
import { esc, money, openModal, toast } from '../ui.js';

const RARITY_ORDER = ['common', 'rare', 'epic', 'legendary'];

export function render() {
  const packs = cat().packs;
  return `
    <div class="section-head">
      <h1>Läden</h1>
      <div class="money-pill">${money(app.state.label.money)}</div>
    </div>
    ${openOfferTile()}
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
      <p>${rules.scoutCards} Musiker mit Können-Stufe 1 – einer unterschreibt.</p>
      ${scout.offer ? '<button class="btn primary" data-act="scoutShow">Auswahl ansehen</button>' : `
        <button class="btn primary" data-act="scoutOpen" data-voucher="1" ${scout.vouchers ? '' : 'disabled'}>Gutschein einlösen (${scout.vouchers})</button>
        <button class="btn" data-act="scoutOpen" data-voucher="" ${canBuy && app.state.label.money >= scout.price ? '' : 'disabled'}>Ohne Gutschein · ${money(scout.price)}</button>
        ${canBuy ? '' : `<span class="muted small">Ohne Gutschein wieder ab ${esc(next)}</span>`}`}
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

const KIND_NAMES = { musician: 'Musiker', gear: 'Instrumente', manager: 'Manager' };

function packTile(key, pack) {
  const affordable = app.state.label.money >= pack.price;
  const busy = !!app.state.packOffer;
  const total = Object.values(pack.odds).reduce((a, b) => a + b, 0);
  const kinds = [...new Set(pack.kinds)].map((k) => KIND_NAMES[k]).join(', ');
  return `
    <div class="pack pack-${key}">
      <div class="pack-art"><span>${esc(pack.name)}</span></div>
      <p>${esc(pack.desc)}</p>
      <p class="muted small">${pack.choices} Karten, eine nimmst du mit · ${esc(kinds)}</p>
      <div class="odds" title="Chancen je Karte">
        ${RARITY_ORDER.filter((r) => pack.odds[r] > 0).map((r) => `
          <span class="odds-seg rarity-bg-${r}" style="flex:${pack.odds[r]}" title="${esc(rarityName(r))}: ${Math.round((pack.odds[r] / total) * 100)} %"></span>`).join('')}
      </div>
      <div class="odds-legend small">${RARITY_ORDER.filter((r) => pack.odds[r] > 0).map((r) => `<span><i class="dot rarity-bg-${r}"></i>${esc(rarityName(r))} ${Math.round((pack.odds[r] / total) * 100)} %</span>`).join('')}</div>
      <button class="btn primary" data-act="buyPack" data-pack="${key}" ${affordable && !busy ? '' : 'disabled'}>Stöbern · ${money(pack.price)}</button>
    </div>`;
}

function openOfferTile() {
  const offer = app.state.packOffer;
  if (!offer) return '';
  return `
    <div class="notice choice">
      <span>Im ${esc(cat().packs[offer.pack].name)} liegt noch deine Auswahl bereit.</span>
      <button class="btn primary small" data-act="packShow">Ansehen</button>
    </div>`;
}

function revealFace(card) {
  if (card.kind === 'musician') return musicianCard(card.musician, { act: null, showStatus: false });
  if (card.kind === 'gear') return gearCard(card.gear);
  return managerCard(card.manager);
}

function cardRarity(card) {
  if (card.kind === 'gear') return cat().gear[card.gear.model].rarity;
  return (card.musician || card.manager).rarity;
}

/** Verdeckte Karten aufdecken, dann eine mitnehmen. */
export function openPackOffer() {
  const offer = app.state.packOffer;
  if (!offer) return;
  const modal = openModal({
    wide: true,
    className: 'reveal-modal',
    html: `
      <h2>${esc(cat().packs[offer.pack].name)}</h2>
      <p class="muted">Deck die Karten auf und nimm eine mit.</p>
      <div class="reveal-grid">
        ${offer.cards.map((card, i) => `
          <div class="reveal-slot">
            <div class="flip rarity-${cardRarity(card)}" data-index="${i}" style="--delay:${i * 80}ms">
              <div class="flip-inner">
                <div class="flip-back"><span class="logo">Signed</span><small>Tippen zum Aufdecken</small></div>
                <div class="flip-front">${revealFace(card)}</div>
              </div>
            </div>
            ${card.tip ? `<div class="pack-tip small">Tipp vom Verkäufer: passt zu ${esc(card.tip)}</div>` : ''}
            <button class="btn primary small" data-pick="${i}" hidden>Mitnehmen</button>
          </div>`).join('')}
      </div>
      <div class="dialog-actions">
        <button class="btn ghost" data-reveal-all>Alle aufdecken</button>
      </div>`,
  });
  const open = (flip) => {
    flip.classList.add('open');
    flip.parentElement.querySelector('[data-pick]').hidden = false;
  };
  const flips = [...modal.body.querySelectorAll('.flip')];
  modal.body.addEventListener('click', async (e) => {
    const flip = e.target.closest('.flip');
    if (flip) open(flip);
    if (e.target.closest('[data-reveal-all]')) {
      flips.forEach((f, i) => setTimeout(() => open(f), i * 150));
    }
    const pick = e.target.closest('[data-pick]');
    if (pick) {
      const res = await mutate('packPick', { offerId: offer.id, index: Number(pick.dataset.pick) });
      if (!res) return;
      modal.close();
      const c = res.card;
      const name = c.kind === 'gear' ? cat().gear[c.gear.model].name : `${c[c.kind].firstName} ${c[c.kind].lastName}`;
      toast(`${name} gehört jetzt zu deinem Label.`, 'ok');
    }
  });
}

/** Meisterwerkstatt: zuerst das Instrument wählen. Vorgeschlagen wird eins, für das noch ein Musiker kein Instrument hat. */
function askInstrument() {
  return new Promise((resolve) => {
    let answer = null;
    const needed = new Set();
    for (const m of app.state.musicians) {
      for (const i of m.instruments) {
        if (!app.state.gear.some((g) => g.musicianId === m.id && cat().gear[g.model].type === i.type)) needed.add(i.type);
      }
    }
    const modal = openModal({
      html: `
        <h2>Was soll angefertigt werden?</h2>
        <form class="stack">
          <select name="type">${Object.entries(cat().instruments).map(([k, v]) => `<option value="${k}" ${needed.has(k) ? '' : ''}>${v.icon} ${esc(v.name)}${needed.has(k) ? ' – fehlt noch' : ''}</option>`).join('')}</select>
          <div class="dialog-actions">
            <button type="button" class="btn ghost" data-cancel>Abbrechen</button>
            <button class="btn primary">Anfertigen lassen</button>
          </div>
        </form>`,
      onClose: () => resolve(answer),
    });
    const form = modal.body.querySelector('form');
    if (needed.size) form.type.value = [...needed][0];
    form.querySelector('[data-cancel]').addEventListener('click', () => modal.close());
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      answer = form.type.value;
      modal.close();
    });
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
      toast('Mitmusiker findest du im Jam-Café oder am Schwarzen Brett.', 'info');
    }
  },
  async buyPack({ pack }) {
    const body = { pack };
    if (cat().packs[pack].chooseType) {
      body.type = await askInstrument();
      if (!body.type) return;
    }
    if (await mutate('buyPack', body)) openPackOffer();
  },
  packShow() {
    openPackOffer();
  },
  noop() {},
});
