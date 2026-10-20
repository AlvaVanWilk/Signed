import { chip, face, fullName, gearModel, genreChips, instrumentIcon, instrumentName, musicianCard, musicianStatus, rarityName } from '../cards.js';
import { app, bandById, cat, defineActions, isBusy, musicianById, mutate, rerender } from '../core.js';
import { openLive } from '../live.js';
import { startPractice } from '../play.js';
import { bar, duration, esc, fmt1, openModal, toast } from '../ui.js';

const RARITY_RANK = { legendary: 0, epic: 1, rare: 2, common: 3 };
const SORTS = {
  rarity: (a, b) => RARITY_RANK[a.rarity] - RARITY_RANK[b.rarity] || maxSkill(b) - maxSkill(a),
  skill: (a, b) => maxSkill(b) - maxSkill(a),
  presence: (a, b) => b.presence - a.presence,
  fame: (a, b) => b.fame - a.fame,
  newest: (a, b) => b.id - a.id,
};

const maxSkill = (m) => Math.max(...m.instruments.map((i) => i.skill));

export function render() {
  const f = app.ui.catalog;
  const c = cat();
  const list = app.state.musicians
    .filter((m) => !f.instrument || m.instruments.some((i) => i.type === f.instrument))
    .filter((m) => !f.genre || m.genres.includes(f.genre))
    .filter((m) => !f.status || (f.status === 'free' ? !m.bandId : !!m.bandId))
    .sort(SORTS[f.sort] || SORTS.rarity);

  return `
    <div class="section-head">
      <div><h1>Katalog</h1><p class="muted">${app.state.musicians.length} Musiker unter Vertrag. Tippe einen an für Details, Proben und Promo.</p></div>
    </div>
    <div class="filters">
      ${select('instrument', f.instrument, 'Alle Instrumente', Object.entries(c.instruments).map(([k, v]) => [k, `${v.icon} ${v.name}`]))}
      ${select('genre', f.genre, 'Alle Genres', Object.entries(c.genres).map(([k, v]) => [k, v.name]))}
      ${select('status', f.status, 'Alle', [['free', 'Ohne Band'], ['band', 'In einer Band']])}
      ${select('sort', f.sort, null, [['rarity', 'Seltenheit'], ['skill', 'Können'], ['presence', 'Präsenz'], ['fame', 'Bekanntheit'], ['newest', 'Neueste']])}
    </div>
    ${list.length ? `<div class="tcg-grid">${list.map((m) => musicianCard(m)).join('')}</div>` : '<div class="empty">Keine Musiker für diesen Filter.</div>'}`;
}

function select(key, value, emptyLabel, options) {
  return `
    <select data-change="catalogFilter" data-key="${key}">
      ${emptyLabel !== null ? `<option value="">${esc(emptyLabel)}</option>` : ''}
      ${options.map(([k, label]) => `<option value="${k}" ${k === value ? 'selected' : ''}>${esc(label)}</option>`).join('')}
    </select>`;
}

export function musicianDetail(id) {
  const m = musicianById(id);
  if (!m) return null;
  const c = cat();
  const band = m.bandId ? bandById(m.bandId) : null;
  const busy = isBusy(m.busyUntil) || (band && isBusy(band.busyUntil));
  const status = musicianStatus(m);

  return `
    <div class="detail rarity-${m.rarity}">
      <div class="detail-head">
        ${face(m, 'xl')}
        <div>
          <div class="eyebrow">${esc(rarityName(m.rarity))}${m.unusual ? ' · Ungewöhnlich' : ''}</div>
          <h2>${esc(fullName(m))}</h2>
          <div class="chips">${genreChips(m.genres)}</div>
          ${status ? `<p class="status-line"><span class="pulse"></span> ${status}</p>` : ''}
        </div>
      </div>
      <div class="detail-grid">
        <section>
          <h3>Können</h3>
          ${m.instruments.map((i, index) => `
            <div class="skill-row">
              <span>${instrumentIcon(i.type)} ${esc(instrumentName(i.type))}</span>
              ${bar(i.skill)}
              <b>${fmt1(i.skill)}</b>
              <button class="btn small" data-act="practice" data-id="${m.id}" data-slot="${index + 1}" ${busy ? 'disabled' : ''}>Proben</button>
            </div>`).join('')}
          ${m.instruments.length > 1 ? '<p class="muted small">Zwei Instrumente teilen sich das Können: Jede Probe verbessert nur das geübte.</p>' : ''}
          <h3>Präsenz</h3>
          <div class="skill-row">
            <span>✨ Bühnenwirkung</span>
            ${bar(m.presence)}
            <b>${fmt1(m.presence)}</b>
            <button class="btn small" data-act="promo" data-id="${m.id}" ${busy ? 'disabled' : ''}>Promo · ${duration(c.rules.promoSeconds)}</button>
          </div>
          <p class="muted small">Auf Promo-Mission ist ${esc(m.firstName)} unterwegs und kann so lange nicht auftreten. Läuft auch, wenn du offline bist.</p>
          <h3>Bekanntheit</h3>
          <p class="big-number">⭐ ${fmt1(m.fame)}</p>
          <p class="muted small">Gehört ${esc(m.firstName)} persönlich und kommt bei jedem Bandwechsel mit.</p>
        </section>
        <section>
          <h3>Besonderheiten</h3>
          ${m.traits.length || m.techniques.length ? `<ul class="traits">
            ${m.traits.map((t) => `<li><b>${esc(c.traits[t].name)}</b> <span class="muted">${esc(c.traits[t].desc)}</span></li>`).join('')}
            ${m.techniques.map((t) => `<li><b>${esc(c.techniques[t])}</b> <span class="muted">Spezialtechnik</span></li>`).join('')}
          </ul>` : '<p class="muted">Keine.</p>'}
          <h3>Charakter</h3>
          <div class="chips">${m.personality.map((p) => chip(c.personalities[p], 'trait')).join('')}</div>
          <h3>Band</h3>
          <p>${band ? `<a href="#/band/${band.id}" data-act="closeModalAndGo" data-href="#/band/${band.id}">${esc(band.name)}</a> · spielt ${esc(instrumentName(m.instruments[m.bandSlot - 1]?.type ?? m.instruments[0].type))}` : '<span class="muted">Ohne Band</span>'}</p>
          <h3>Instrumente</h3>
          ${m.instruments.map((i) => gearSelect(m, i.type)).join('')}
        </section>
      </div>
    </div>`;
}

function gearSelect(m, type) {
  const options = app.state.gear.filter((g) => gearModel(g.model).type === type && (!g.musicianId || g.musicianId === m.id));
  const current = options.find((g) => g.musicianId === m.id);
  return `
    <label class="field inline">
      <span>${instrumentIcon(type)} ${esc(instrumentName(type))}</span>
      <select data-change="assignGear" data-musician="${m.id}" data-type="${type}">
        <option value="">Eigenes Standardinstrument</option>
        ${options.map((g) => {
          const model = gearModel(g.model);
          return `<option value="${g.id}" ${g === current ? 'selected' : ''}>${esc(model.name)} (Klang ${model.klang})</option>`;
        }).join('')}
      </select>
    </label>`;
}

export function openMusician(id) {
  openModal({ wide: true, render: () => musicianDetail(id) });
}

defineActions({
  catalogFilter(value, el) {
    app.ui.catalog[el.dataset.key] = value;
    rerender();
  },
  openMusician({ id }) {
    openMusician(id);
  },
  practice({ id, slot }) {
    startPractice(id, slot);
  },
  async promo({ id }) {
    const res = await mutate('promoStart', { musicianId: Number(id) });
    if (res) toast(`${musicianById(id).firstName} ist auf Promo-Tour.`, 'ok');
  },
  async assignGear(value, el) {
    const musicianId = Number(el.dataset.musician);
    if (value) {
      await mutate('gearAssign', { gearId: Number(value), musicianId });
      return;
    }
    const current = app.state.gear.find((g) => g.musicianId === musicianId && gearModel(g.model).type === el.dataset.type);
    if (current) await mutate('gearAssign', { gearId: current.id, musicianId: null });
  },
  watch({ id }) {
    openLive(id);
  },
});
