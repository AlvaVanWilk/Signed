import { fullName, gearHue, gearModel, instrumentIcon, instrumentName, managerCard } from '../cards.js';
import { app, bandById, cat, defineActions, musicianById, mutate } from '../core.js';
import { assignGear } from '../gear.js';
import { esc, fmt0, toast } from '../ui.js';

/** Ein Instrument im Flightcase: Schaumstoff innen, Klebeband mit dem Namen dessen, der es spielt. */
function flightcase(g) {
  const model = gearModel(g.model);
  const holder = g.musicianId ? musicianById(g.musicianId) : null;
  const finish = g.design >= 60 && g.design < 85 ? gearHue(g) : null;
  return `
    <button class="lg-case r-${model.rarity} ${holder ? 'used' : 'free'}" data-act="openGear" data-id="${g.id}" title="${esc(model.name)}">
      <span class="lg-lid"></span>
      <span class="lg-foam"><span class="lg-ico">${instrumentIcon(model.type, !!model.electric, gearHue(g) ?? finish)}</span></span>
      <span class="lg-tape">${holder ? esc(holder.firstName) : 'frei'}</span>
      <small class="lg-name">${esc(model.name)}</small>
    </button>`;
}

export function render() {
  const s = app.state;
  const free = s.gear.filter((g) => !g.musicianId);
  const used = s.gear.filter((g) => g.musicianId);
  return `
    <div class="lg-page">
      <div class="lg-wall">
        <h1 class="lg-sign">LAGER</h1>
        <p class="lg-sub">Was frei ist, wartet auf jemanden. Ein Tipp aufs Case zeigt, wem es am meisten bringt.</p>
        <h2 class="lg-cap">Frei <small>${free.length}</small></h2>
        ${free.length ? `<div class="lg-rack">${free.map(flightcase).join('')}</div>` : '<p class="lg-empty">Nichts frei. Neues gibt es im Music Store.</p>'}
        <h2 class="lg-cap">Im Einsatz <small>${used.length}</small></h2>
        ${used.length ? `<div class="lg-rack">${used.map(flightcase).join('')}</div>` : '<p class="lg-empty">Noch spielt niemand ein eigenes Instrument.</p>'}
      </div>
    </div>

    ${s.managers.length ? `<section>
      <h2>Manager</h2>
      <div class="tcg-grid">${s.managers.map((m) => managerCard(m, { extra: managedBands(m) })).join('')}</div>
    </section>` : ''}

    ${s.items.length ? `<section>
      <h2>Fan-Karten</h2>
      <div class="card-grid">${s.items.map(itemCard).join('')}</div>
    </section>` : ''}`;
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
      <span class="muted small">+${fmt0(item.value)} Ruhm für eine Band</span>
      ${app.state.bands.length ? `
        <select class="card-select" data-change="useItem" data-item="${item.id}">
          <option value="">Einsetzen bei …</option>
          ${app.state.bands.map((b) => `<option value="${b.id}">${esc(b.name)}</option>`).join('')}
        </select>` : '<span class="muted small">Gründe zuerst eine Band.</span>'}
    </div>`;
}

defineActions({
  async assignGearFromStorage(value, el) {
    await assignGear(Number(el.dataset.gear), value ? Number(value) : null);
  },
  async useItem(value, el) {
    if (!value) return;
    const res = await mutate('itemUse', { itemId: Number(el.dataset.item), bandId: Number(value) });
    if (res) toast(`+${fmt0(res.value)} Ruhm für ${bandById(value)?.name ?? 'die Band'}!`, 'ok');
  },
});

