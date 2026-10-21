import { bandById, defineActions, gearById, musicianById, mutate } from './core.js';
import { fullName, gearCard, gearModel } from './cards.js';
import { esc, fmt0, fmt1, openModal, toast } from './ui.js';

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
      return `
        <div class="gear-detail">
          ${gearCard(g)}
          <div>
            <h2>${esc(model.name)}</h2>
            ${holder ? `
              <p>Gespielt von <b>${esc(fullName(holder))}</b>${holderBand ? ` in ${esc(holderBand.name)}` : ''}.</p>
              <button class="btn small" data-unassign>Ins Lager legen</button>` : `
              <h3>Wer damit besser klingt</h3>
              ${rows ? `<ul class="gear-upgrades">${rows}</ul>` : '<p class="muted">Im Moment würde niemand damit besser klingen.</p>'}`}
            <p class="muted small">Klang: wie viel das Instrument zum Sound beiträgt. Spielbarkeit: ab welchem Können der volle Klang kommt
              (grün = erreicht, rot = noch nicht). Design: mehr Ruhm bei Auftritten.</p>
          </div>
        </div>`;
    },
  });
  modal.body.addEventListener('click', async (e) => {
    const assign = e.target.closest('[data-assign]');
    if (assign && await assignGear(gearId, Number(assign.dataset.assign))) modal.close();
    if (e.target.closest('[data-unassign]') && await assignGear(gearId, null)) modal.close();
  });
}

defineActions({
  openGear({ id }) {
    openGear(Number(id));
  },
});

