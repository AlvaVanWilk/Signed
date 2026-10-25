import { app, bandById, cat, defineActions, gearById, musicianById, mutate } from './core.js';

/** Das eigene Instrument einer Art, das ein Musiker gerade hat (ID oder null). */
const app_gearOf = (m, type) => app.state.gear.find((x) => x.musicianId === m.id && cat().gear[x.model]?.type === type)?.id ?? null;
import { fullName, gearCard, gearModel } from './cards.js';
import { confirmDialog, esc, fmt0, fmt1, money, openModal, toast } from './ui.js';

/** Sound vorher → nachher, wie ihn der Server für ein freies Instrument ausgerechnet hat. */
export function soundChange(u) {
  return `<span class="sound-change">Sound ${fmt1(u.before)} → <b>${fmt1(u.after)}</b> (+${fmt1(u.after - u.before)})</span>`;
}

/** Instrument einlegen oder herausnehmen. Wird der Bandsound besser, glimmt er kurz auf. */
export async function assignGear(gearId, musicianId) {
  const g = gearById(gearId);
  const m = musicianById(musicianId ?? g?.musicianId);
  const band = m?.bandId ? bandById(m.bandId) : null;
  const before = band?.sound;
  const res = await mutate('gearAssign', { gearId, musicianId });
  if (!res || !band) return res;
  const after = bandById(band.id)?.sound ?? before;
  if (after > before + 0.05) {
    toast(`Sound von ${band.name}: ${fmt1(before)} → ${fmt1(after)}`, 'ok');
    requestAnimationFrame(() => {
      for (const el of document.querySelectorAll(`[data-flash="sound-${band.id}"]`)) {
        el.classList.remove('flash-up');
        void el.offsetWidth;
        el.classList.add('flash-up');
      }
    });
  }
  return res;
}

/** Große Ansicht: Werte des Instruments und wer damit wie viel besser klingen würde. */
export function openGear(gearId) {
  const modal = openModal({
    wide: true,
    render: () => {
      const g = gearById(gearId);
      if (!g) return '<div class="empty">Dieses Instrument gibt es nicht mehr.</div>';
      const model = gearModel(g.model);
      const needed = 100 - g.spielbarkeit;
      const holder = g.musicianId ? musicianById(g.musicianId) : null;
      const holderBand = holder?.bandId ? bandById(holder.bandId) : null;
      const rows = (g.upgrades ?? []).map((u) => {
        const m = musicianById(u.musicianId);
        if (!m) return '';
        const skill = m.instruments.find((i) => i.type === model.type)?.skill ?? 0;
        const band = u.bandId ? bandById(u.bandId) : null;
        return `
          <li>
            <span><b>${esc(fullName(m))}</b> <span class="muted">· ${band ? esc(band.name) : 'ohne Band'}</span></span>
            ${soundChange(u)}
            <span class="small">Können <b class="${skill >= needed ? 'ok' : 'bad'}">${fmt0(skill)}</b> / voll ab ${fmt0(needed)}</span>
            <button class="btn primary small" data-assign="${m.id}">Einlegen</button>
          </li>`;
      }).join('');
      // Alle anderen, die so ein Instrument spielen (auch wenn es ihren Sound nicht hebt)
      const better = new Set((g.upgrades ?? []).map((u) => u.musicianId));
      const others = holder ? [] : app.state.musicians.filter((m) => !better.has(m.id) && m.instruments.some((i) => i.type === model.type));
      return `
        <div class="gear-detail">
          ${gearCard(g)}
          <div>
            <h2>${esc(model.name)}</h2>
            ${holder ? `
              <p>Gespielt von <b>${esc(fullName(holder))}</b>${holderBand ? ` in ${esc(holderBand.name)}` : ''}.</p>
              <button class="btn small" data-unassign>Ins Lager legen</button>` : `
              <h3>Wer damit besser klingt</h3>
              ${rows ? `<ul class="gear-upgrades">${rows}</ul>` : '<p class="muted">Im Moment würde niemand damit besser klingen.</p>'}
              ${others.length ? `<h3>Geben an</h3><ul class="gear-upgrades">${others.map((m) => `<li><span><b>${esc(fullName(m))}</b>${m.bandId ? ` <span class="muted">· ${esc(bandById(m.bandId)?.name ?? '')}</span>` : ''}</span>
                <span class="small muted">${gearById(app_gearOf(m, model.type)) ? 'hat schon eins' : 'hat keins'}</span><button class="btn small" data-assign="${m.id}">Einlegen</button></li>`).join('')}</ul>` : ''}`}
            ${(g.perks ?? []).length ? `<h3>Eigenschaften</h3><ul class="perk-list">${g.perks.map((p) => cat().gearPerks[p]).filter(Boolean)
              .map((p) => `<li>${p.icon} <b>${esc(p.name)}</b> – ${esc(p.desc)}</li>`).join('')}</ul>` : ''}
            <p class="muted small">Klang: wie viel das Instrument zum Sound beiträgt. Spielbarkeit: ab welchem Können der volle Klang kommt
              (grün = erreicht, rot = noch nicht). Design: mehr Ruhm bei Auftritten und ein besonderes Aussehen.</p>
            <div class="dialog-actions"><button class="btn ghost small" data-sell>An den Second-Hand-Laden verkaufen · ${money(g.sellPrice)}</button></div>
          </div>
        </div>`;
    },
  });
  modal.body.addEventListener('click', async (e) => {
    const assign = e.target.closest('[data-assign]');
    if (assign && await assignGear(gearId, Number(assign.dataset.assign))) modal.close();
    if (e.target.closest('[data-unassign]') && await assignGear(gearId, null)) modal.close();
    if (e.target.closest('[data-sell]')) {
      const g = gearById(gearId);
      const holder = g.musicianId ? musicianById(g.musicianId) : null;
      const ok = await confirmDialog(`${gearModel(g.model).name} für ${money(g.sellPrice)} verkaufen?${holder ? ` ${holder.firstName} spielt danach ohne eigenes Instrument.` : ''}`, { ok: 'Verkaufen', danger: true });
      if (ok && await mutate('gearSell', { gearId })) {
        modal.close();
        toast(`Verkauft für ${money(g.sellPrice)}.`, 'ok');
      }
    }
  });
}

defineActions({
  upgradeInfo({ head, lines: json }) {
    const lines = JSON.parse(json || '[]');
    const modal = openModal({ html: `<div class="eyebrow">Instrument</div><h2>${esc(head)}</h2>
      ${lines.length ? `<ul class="history">${lines.map((l) => `<li><span>${esc(l)}</span></li>`).join('')}</ul>` : '<p class="muted">Gerade niemand.</p>'}
      <div class="dialog-actions"><button class="btn primary" data-close>OK</button></div>` });
    modal.body.querySelector('[data-close]').addEventListener('click', () => modal.close());
  },
  openGear({ id }) {
    openGear(Number(id));
  },
});

