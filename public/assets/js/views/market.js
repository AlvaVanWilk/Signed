import { musicianCard } from '../cards.js';
import { app, defineActions, mutate } from '../core.js';
import { confirmDialog, esc, money, toast } from '../ui.js';

export function render() {
  const { offers, mine } = app.state.market;
  return `
    <div class="section-head"><h1>Wechselbörse</h1></div>
    ${mine.length ? `
      <h2>Deine Angebote</h2>
      <div class="tcg-grid">${mine.map(ownTile).join('')}</div>` : ''}
    <h2>Angebote anderer Labels</h2>
    ${offers.length ? `<div class="tcg-grid">${offers.map(offerTile).join('')}</div>` : '<div class="empty">Gerade bietet niemand einen Musiker an.</div>'}`;
}

function priceLine(t) {
  return t.bestAmount
    ? `<span>Gebot: <b>${money(t.bestAmount)}</b>${t.bidder ? ` · ${esc(t.bidder)}` : ''}</span>`
    : `<span>Mindestpreis: <b>${money(t.minPrice)}</b></span>`;
}

function info(t, body) {
  return `
    <div class="market-info">
      ${priceLine(t)}
      <span class="muted small">Noch <span data-until="${t.endsAt}"></span>${t.bids ? ` · ${t.bids} ${t.bids === 1 ? 'Gebot' : 'Gebote'}` : ''}</span>
      ${body}
    </div>`;
}

function ownTile(t) {
  const button = t.bestAmount
    ? `<button class="btn primary small" data-act="transferAccept" data-id="${t.id}">Zuschlagen · ${money(t.bestAmount)}</button>`
    : `<button class="btn ghost small" data-act="transferWithdraw" data-id="${t.id}">Zurückziehen</button>`;
  return musicianCard(t.musician, { act: null, showStatus: false, extra: info(t, button) });
}

function offerTile(t) {
  const form = `
    ${t.leading ? '<span class="chip special">Du liegst vorn</span>' : ''}
    <form class="market-bid" data-form="transferBid" data-id="${t.id}">
      <input type="number" name="amount" min="${t.minBid}" step="10" value="${t.minBid}" required>
      <button class="btn primary small" ${app.state.label.money < (t.leading ? t.minBid - t.bestAmount : t.minBid) ? 'disabled' : ''}>Bieten</button>
    </form>`;
  return musicianCard(t.musician, { act: null, showStatus: false, extra: info(t, form) });
}

defineActions({
  async transferList({ id }) {
    const m = app.state.musicians.find((x) => x.id === Number(id));
    const ok = await confirmDialog(
      `${m.firstName} ${m.lastName} für 2 Tage an die Wechselbörse stellen? Mindestpreis: ${money(m.value)}. Sobald jemand bietet, ist der Verkauf verbindlich.`,
      { ok: 'Anbieten' },
    );
    if (ok && await mutate('transferList', { musicianId: m.id })) toast(`${m.firstName} steht jetzt auf der Wechselbörse.`, 'ok');
  },
  async transferWithdraw({ id }) {
    await mutate('transferWithdraw', { transferId: Number(id) });
  },
  async transferAccept({ id }) {
    const t = app.state.market.mine.find((x) => x.id === Number(id));
    const ok = await confirmDialog(`${t.musician.firstName} für ${money(t.bestAmount)} an ${t.bidder} verkaufen?`, { ok: 'Verkaufen' });
    if (ok && await mutate('transferAccept', { transferId: t.id })) toast('Verkauft!', 'ok');
  },
  async transferBid(values, form) {
    if (await mutate('transferBid', { transferId: Number(form.dataset.id), amount: Number(values.amount) })) toast('Gebot abgegeben. Das Geld ist zurückgelegt.', 'ok');
  },
});
