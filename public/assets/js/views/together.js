import { api } from '../api.js';
import { face, fullName, genreTag, instrumentIcon, instrumentName } from '../cards.js';
import { app, bandById, defineActions, musicianById, mutate } from '../core.js';
import { esc, fmt1, openModal, toast } from '../ui.js';

/**
 * „Mit Musiker(n) zusammentun“: Wer passt zu dieser Band? Der Server ordnet die Möglichkeiten nach Nutzen:
 * zuerst echte Vorteile (Genre-Bonus, neues Instrument), dann gute Passung der Charaktere, dann der Rest, Unmögliches zuletzt.
 * Hier kommen nur Suche, Gliederung und Anzeige dazu.
 */

const SECTIONS = [
  ['green', 'Bringt etwas', 'Echte Vorteile für den Sound der Band'],
  ['fit', 'Passt gut', 'Die Charaktere verstehen sich'],
  ['rest', 'Weitere', ''],
  ['blocked', 'Gerade nicht möglich', ''],
];

const sectionOf = (c) => (c.blocked ? 'blocked' : c.green ? 'green' : c.compat >= 3.5 ? 'fit' : 'rest');

function who(c) {
  if (c.kind === 'musician') {
    const m = musicianById(c.musicianId);
    if (!m) return '';
    return `${face(m, 'sm')}
      <span class="tg-name"><b>${esc(fullName(m))}</b>
        <small>${m.genres.map((g) => genreTag(g)).join('')} ${m.instruments.map((i) => `<span class="tg-ins">${instrumentIcon(i.type)} ${esc(instrumentName(i.type))} ${fmt1(i.skill)}</span>`).join(' ')}</small></span>`;
  }
  const members = c.memberIds.map(musicianById).filter(Boolean);
  return `<span class="tg-faces">${members.map((m) => face(m, 'sm')).join('')}</span>
    <span class="tg-name"><b>${esc(c.name)}</b> <small class="muted">Band mit ${members.length}</small>
      <small>${members.map((m) => `<span class="tg-ins">${instrumentIcon(m.instruments[0].type)} ${esc(m.firstName)}</span>`).join(' ')}</small></span>`;
}

function row(c) {
  const m = c.kind === 'musician' ? musicianById(c.musicianId) : null;
  const slot = m && m.instruments.length > 1
    ? `<select data-slot>${m.instruments.map((i, idx) => `<option value="${idx + 1}">${esc(instrumentName(i.type))} ${fmt1(i.skill)}</option>`).join('')}</select>`
    : '';
  const effects = c.blocked
    ? `<li class="bad">${esc(c.blocked)}</li>`
    : c.effects.map((e) => `<li class="${e.good ? 'good' : 'bad'}">${esc(e.text)}</li>`).join('') || '<li class="muted">Ändert am Sound nichts Besonderes.</li>';
  return `
    <div class="tg-row ${sectionOf(c)}" data-band="${c.bandId}" data-search="${esc(searchText(c))}">
      <div class="tg-who">${who(c)}</div>
      <ul class="tg-fx">${effects}</ul>
      <div class="tg-go">${slot}${c.blocked ? '' : `<button class="btn small primary" data-join="${c.bandId}" data-kind="${c.kind}" data-musician="${c.musicianId ?? ''}">Zusammentun</button>`}</div>
    </div>`;
}

function searchText(c) {
  const names = c.memberIds.map((id) => fullName(musicianById(id) ?? { firstName: '', lastName: '' }));
  return [c.name, ...names].join(' ').toLowerCase();
}

function list(candidates) {
  if (!candidates.length) return '<p class="muted">Es gibt gerade niemanden, mit dem sich diese Band zusammentun könnte.</p>';
  return SECTIONS.map(([key, title, note]) => {
    const rows = candidates.filter((c) => sectionOf(c) === key);
    if (!rows.length) return '';
    return `<section class="tg-sec ${key}"><h3>${title}${note ? ` <small class="muted">${note}</small>` : ''}</h3>${rows.map(row).join('')}</section>`;
  }).join('');
}

async function open(bandId) {
  const band = bandById(bandId);
  let candidates;
  try {
    candidates = (await api('bandJoinCandidates', { bandId })).candidates;
  } catch (e) {
    toast(e.message, 'error');
    return;
  }
  const solo = band.memberIds.length === 1;
  const modal = openModal({
    wide: true,
    className: 'tg-modal',
    html: `
      <h2>${solo ? `${esc(band.name)} mit anderen zusammentun` : `${esc(band.name)}: mit Musiker(n) zusammentun`}</h2>
      <input class="tg-search" type="search" placeholder="Name suchen" autocomplete="off" aria-label="Name suchen">
      <div class="tg-list">${list(candidates)}</div>
      <p class="tg-none muted" hidden>Niemand mit diesem Namen.</p>`,
  });
  const search = modal.body.querySelector('.tg-search');
  search.addEventListener('input', () => {
    const q = search.value.trim().toLowerCase();
    let shown = 0;
    for (const r of modal.body.querySelectorAll('.tg-row')) {
      const hit = !q || r.dataset.search.includes(q);
      r.hidden = !hit;
      if (hit) shown++;
    }
    for (const s of modal.body.querySelectorAll('.tg-sec')) s.hidden = ![...s.querySelectorAll('.tg-row')].some((r) => !r.hidden);
    modal.body.querySelector('.tg-none').hidden = shown > 0;
  });
  modal.body.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-join]');
    if (!btn) return;
    const r = btn.closest('.tg-row');
    const res = btn.dataset.kind === 'musician'
      ? await mutate('bandAddMember', { bandId, musicianId: Number(btn.dataset.musician), slot: Number(r.querySelector('[data-slot]')?.value || 1) })
      : await mutate('bandMerge', { bandId, otherId: Number(btn.dataset.join) });
    if (res) modal.close();
  });
}

defineActions({
  together({ id }) {
    open(Number(id));
  },
});
