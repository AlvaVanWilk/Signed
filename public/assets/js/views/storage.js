import { fullName, gearCard, gearModel, instrumentName, managerCard } from '../cards.js';
import { app, bandById, cat, defineActions, mutate } from '../core.js';
import { esc, fmt0, toast } from '../ui.js';

export function render() {
  const s = app.state;
  const byType = {};
  for (const g of s.gear) {
    const type = gearModel(g.model).type;
    (byType[type] ||= []).push(g);
  }
  return `
    <div class="section-head">
      <div><h1>Lager</h1><p class="muted">Instrumente, Manager und weitere Karten deines Labels.</p></div>
    </div>

    <section>
      <h2>Instrumente</h2>
      <p class="muted">Instrumente sind nicht einmalig und lassen sich jederzeit tauschen. Ein gutes Instrument entfaltet sich nur in fähigen Händen.</p>
      ${s.gear.length ? Object.entries(byType).map(([type, items]) => `
        <h3>${esc(instrumentName(type))}</h3>
        <div class="card-grid">${items.map((g) => gearCard(g, assignSelect(g, type))).join('')}</div>`).join('') : '<div class="empty">Noch keine Instrumente. Sie stecken in Studio- und Equipment-Packs.</div>'}
    </section>

    <section>
      <h2>Manager</h2>
      <p class="muted">Ein Manager betreut bis zu ${cat().rules.managerMaxBands} Bands, teilt seine Leistung dann aber auf. Zuweisen kannst du ihn auf der Seite der Band.</p>
      ${s.managers.length ? `<div class="card-grid">${s.managers.map((m) => managerCard(m, managedBands(m))).join('')}</div>` : '<div class="empty">Noch kein Manager. Manager gibt es in Packs und manchmal nach einer Open Stage.</div>'}
    </section>

    <section>
      <h2>Fan-Karten</h2>
      ${s.items.length ? `<div class="card-grid">${s.items.map(itemCard).join('')}</div>` : '<div class="empty">Keine Fan-Karten. Die gibt es mit etwas Glück nach einer Open Stage.</div>'}
    </section>`;
}

function assignSelect(g, type) {
  const players = app.state.musicians.filter((m) => m.instruments.some((i) => i.type === type));
  return `
    <select class="card-select" data-change="assignGearFromStorage" data-gear="${g.id}">
      <option value="">Im Lager</option>
      ${players.map((m) => `<option value="${m.id}" ${g.musicianId === m.id ? 'selected' : ''}>${esc(fullName(m))}</option>`).join('')}
    </select>`;
}

function managedBands(m) {
  const names = m.bandIds.map((id) => bandById(id)?.name).filter(Boolean);
  return `<span class="small ${names.length ? '' : 'muted'}">${names.length ? `Managt: ${names.map(esc).join(', ')}` : 'Managt noch keine Band'}</span>`;
}

function itemCard(item) {
  return `
    <div class="card card-item">
      <span class="gear-icon">💌</span>
      <span class="card-title">Fan-Karte</span>
      <span class="muted small">+${fmt0(item.value)} Ruhm für eine Band, verteilt auf alle Mitglieder.</span>
      ${app.state.bands.length ? `
        <select class="card-select" data-change="useItem" data-item="${item.id}">
          <option value="">Einsetzen bei …</option>
          ${app.state.bands.map((b) => `<option value="${b.id}">${esc(b.name)}</option>`).join('')}
        </select>` : '<span class="muted small">Gründe zuerst eine Band.</span>'}
    </div>`;
}

defineActions({
  async assignGearFromStorage(value, el) {
    await mutate('gearAssign', { gearId: Number(el.dataset.gear), musicianId: value ? Number(value) : null });
  },
  async useItem(value, el) {
    if (!value) return;
    const res = await mutate('itemUse', { itemId: Number(el.dataset.item), bandId: Number(value) });
    if (res) toast(`+${fmt0(res.value)} Ruhm für ${bandById(value)?.name ?? 'die Band'}!`, 'ok');
  },
});

