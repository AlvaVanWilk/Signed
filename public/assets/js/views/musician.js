import { abilityInfo, bandIdentity, emptySlot, fullName, gearCard, gearModel, genreName, genreTag, instrumentIcon, instrumentName, musicianCard, musicianStatus } from '../cards.js';
import { app, bandById, cat, defineActions, isBusy, musicianById, mutate, navigate } from '../core.js';
import { openLive } from '../live.js';
import { chip } from '../cards.js';
import { closeAllModals, duration, esc, fmt0, money, openModal, timeAgo, toast } from '../ui.js';
import { chemistryLabel, practiceTile, promoTile, suggestName } from './bands.js';
import { catalogList } from './catalog.js';

/** Untermenü eines Musikers. solo = nur ohne Band. */
export const MUSICIAN_SECTIONS = [
  ['', '👤', 'Profil'],
  ['uebung', '🎯', 'Übung'],
  ['promo', '📣', 'Promo'],
  ['jam', '☕', 'Jam-Café', 'solo'],
  ['strasse', '🪙', 'Straßenmusik', 'solo'],
  ['chronik', '📜', 'Chronik'],
];

export function sectionsFor(m) {
  return MUSICIAN_SECTIONS.filter(([, , , only]) => only !== 'solo' || !m.bandId);
}

export function renderMusician(route) {
  const m = musicianById(route.id);
  if (!m) return '<div class="empty">Diesen Musiker gibt es nicht mehr.</div>';
  const section = sectionsFor(m).some(([key]) => key === route.section) ? route.section : '';
  return { '': profile, uebung: practice, promo, jam, strasse: street, chronik: chronicle }[section](m);
}

function head(title, m) {
  return `${pager(m)}<div class="page-head"><div><div class="eyebrow">${esc(fullName(m))}</div><h1>${esc(title)}</h1></div></div>`;
}

/** Rückweg und Blättern durch die Liste, aus der der Musiker geöffnet wurde. */
function pager(m) {
  const nav = app.ui.musicianNav;
  const ids = (nav?.ids ?? app.state.musicians.map((x) => x.id)).filter((id) => musicianById(id));
  const index = ids.indexOf(m.id);
  const section = location.hash.split('/')[3] ?? '';
  const link = (id, label, cls) => `<a class="btn small ${cls}" href="#/musician/${id}${section ? `/${section}` : ''}">${label}</a>`;
  return `
    <div class="pager">
      ${nav?.back ? `<a class="btn ghost small" href="${nav.back.href}">← ${esc(nav.back.label)}</a>` : '<span></span>'}
      ${index >= 0 && ids.length > 1 ? `<span class="pager-steps">
        ${link(ids[(index - 1 + ids.length) % ids.length], '‹', 'ghost')}
        <span class="muted small">${index + 1} / ${ids.length}</span>
        ${link(ids[(index + 1) % ids.length], '›', 'ghost')}
      </span>` : ''}
    </div>`;
}

const busyOf = (m) => isBusy(m.busyUntil) || (m.bandId && isBusy(bandById(m.bandId)?.busyUntil));

function statusNotice(m) {
  const status = musicianStatus(m);
  return status ? `<div class="notice"><span class="pulse"></span><span>${status}</span></div>` : '';
}

function profile(m) {
  const rules = cat().rules;
  const band = m.bandId ? bandById(m.bandId) : null;
  const nextSkill = rules.skillLevels[m.level];
  const nextFame = rules.fameLevels[m.fameLevel];
  return `
    ${pager(m)}
    <div class="profile">
      <div class="profile-card">${musicianCard(m, { act: null })}</div>
      <div class="profile-side">
        ${statusNotice(m)}
        ${m.abilityChoice ? `<div class="notice choice"><span>⭐ ${esc(m.firstName)} ist jetzt Experte und darf eine dritte Fähigkeit wählen.</span><button class="btn primary small" data-act="chooseAbility" data-id="${m.id}">Wählen</button></div>` : ''}
        <div class="stat-grid">
          <div class="tile"><span>Können-Stufe</span><b>${m.level}</b><small>${nextSkill ? `Stufe ${m.level + 1} ab ${nextSkill}` : 'Höchste Stufe'}</small></div>
          <div class="tile"><span>Bekanntheit</span><b>${m.fameLevel}</b><small>${nextFame ? `Stufe ${m.fameLevel + 1} ab ${nextFame}` : 'Höchste Stufe'}</small></div>
          <div class="tile"><span>Band</span><b class="small-b">${band ? `<a href="#/band/${band.id}">${esc(band.name)}</a>` : '–'}</b>
            ${band ? '' : `<button class="btn small ${m.favorite ? 'primary' : ''}" data-act="toggleFavorite" data-id="${m.id}">${m.favorite ? '★ Favorit' : '☆ Favorit'}</button>`}
          </div>
          <div class="tile"><span>Marktwert</span><b>${money(m.value)}</b>
            ${band || busyOf(m) ? '' : `<button class="btn small" data-act="transferList" data-id="${m.id}">An die Börse</button>`}
          </div>
        </div>
        <div class="tile-grid compact">
          ${m.instruments.map((i) => gearSlot(m, i.type)).join('')}
        </div>
        ${m.abilities.length ? `<div class="ability-list">${m.abilities.map((a) => {
          const info = abilityInfo(a);
          return `<div class="ability"><span class="ability-icon">${info.icon}</span><b>${esc(info.name)}</b><span class="muted small">${esc(info.desc)}</span></div>`;
        }).join('')}</div>` : ''}
        <div class="chips">${m.genres.map(genreTag).join('')}${m.personality.map((p) => chip(cat().personalities[p], 'trait')).join('')}</div>
      </div>
    </div>`;
}

function gearSlot(m, type) {
  const gear = app.state.gear.find((g) => g.musicianId === m.id && gearModel(g.model).type === type);
  const data = { musician: m.id, type };
  return `<div class="gear-slot">${gear ? gearCard(gear, { act: 'pickGear', data, size: 'sm' }) : emptySlot(instrumentName(type), { act: 'pickGear', data, icon: instrumentIcon(type) })}</div>`;
}

function practice(m) {
  return `${head('Übung', m)}${statusNotice(m)}<div class="action-grid">${practiceTile(m, busyOf(m))}</div>`;
}

function promo(m) {
  return `${head('Promo', m)}${statusNotice(m)}<div class="action-grid">${promoTile(m, busyOf(m))}</div>`;
}

function jam(m) {
  const offer = jamOfferFor(m);
  if (offer) {
    return `
      ${head('Jam-Café', m)}
      ${jamOfferHtml(offer, true)}
      <div><button class="btn ghost" data-act="jamDecline" data-offer="${offer.id}">Alle ablehnen</button></div>`;
  }
  const slots = app.state.label.bandSlots;
  const full = app.state.bands.length >= slots.open;
  const note = !full ? '2 Lieder · Musiker für eine Band finden'
    : slots.next === null ? 'Dein Label führt schon so viele Bands, wie es kann.'
    : `Erst ab ${fmt0(slots.next)} Label-Ruhm: Dann ist Platz für eine weitere Band.`;
  return `
    ${head('Jam-Café', m)}
    ${statusNotice(m)}
    <div class="action-grid">
      <div class="action-tile">
        <div class="action-icon">☕</div>
        <div class="action-main"><b>Jam-Session</b><span class="muted small">${esc(note)}</span></div>
        <button class="btn primary" data-act="solo" data-id="${m.id}" data-type="jam" ${busyOf(m) || full ? 'disabled' : ''}>Jammen</button>
      </div>
    </div>`;
}

/** Gastgeber und Angebote nebeneinander; was zum Gastgeber passt, ist hervorgehoben. */
export function jamOfferHtml(offer, withButtons) {
  const host = offer.host ?? musicianById(offer.musicianId);
  if (!host) return '';
  const hostTypes = host.instruments.map((i) => i.type);
  return `
    <div class="jam-board">
      <div class="jam-host">
        <span class="slot-label">${esc(host.firstName)}</span>
        ${musicianCard(host, { act: null, showStatus: false })}
      </div>
      <div class="jam-plus">+</div>
      <div class="tcg-grid jam-offers">${offer.cards.map((c, index) => {
        const type = c.instruments[0].type;
        const chem = c.match?.chemistry ?? 1;
        return musicianCard(c, {
          act: null,
          showStatus: false,
          extra: `
            <ul class="jam-match">
              <li class="good">${instrumentIcon(type)} ${esc(instrumentName(type))}${hostTypes.includes(type) ? '' : ' ergänzt'}</li>
              <li class="good">🎵 ${(c.match?.genres ?? c.genres.filter((g) => host.genres.includes(g))).map((g) => esc(genreName(g))).join(', ')}</li>
              <li class="${chem >= 1.0 ? 'good' : ''}">🤝 Chemie: ${chemistryLabel(chem)}</li>
            </ul>
            ${withButtons ? `<button class="btn primary small" data-act="jamChoose" data-offer="${offer.id}" data-index="${index}">Band gründen</button>` : ''}`,
        });
      }).join('')}</div>
    </div>`;
}

const jamOfferFor = (m) => app.state.jamOffers.find((o) => o.musicianId === m.id);

/** Mit dem gewählten Musiker aus dem Jam-Café eine Band gründen. */
function openJamBand(offerId, index) {
  const offer = app.state.jamOffers.find((o) => o.id === Number(offerId));
  const host = offer && musicianById(offer.musicianId);
  if (!host) return;
  const card = offer.cards[Number(index)];
  const slots = app.state.label.bandSlots;
  if (app.state.bands.length >= slots.open) {
    toast(slots.next === null ? 'Mehr Bands kann dein Label nicht führen.' : `Deine nächste Band kannst du ab ${fmt0(slots.next)} Label-Ruhm gründen.`, 'error');
    return;
  }
  const identity = bandIdentity([
    { genres: host.genres, type: host.instruments[0].type },
    { genres: card.genres, type: card.instruments[0].type },
  ]);
  const modal = openModal({
    html: `
      <h2>Band gründen</h2>
      <p>${esc(fullName(host))} und ${esc(fullName(card))}</p>
      <form class="stack">
        <label class="field"><span>Bandname</span>
          <span class="input-dice"><input name="name" maxlength="40" required><button type="button" class="btn small" data-dice title="Zufälliger Name">🎲</button></span>
        </label>
        <p class="small ${identity.title ? 'ok' : 'muted'}">${identity.title ? `Das wird eine ${esc(identity.title)}.` : 'Noch ohne gemeinsames Genre.'}</p>
        <div class="dialog-actions">
          <button type="button" class="btn ghost" data-cancel>Abbrechen</button>
          <button class="btn primary">Band gründen</button>
        </div>
      </form>`,
  });
  const form = modal.body.querySelector('form');
  form.querySelector('[data-cancel]').addEventListener('click', () => modal.close());
  form.querySelector('[data-dice]').addEventListener('click', async () => {
    form.name.value = await suggestName('band');
  });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const res = await mutate('jamPick', { offerId: offer.id, index: Number(index), name: form.name.value });
    if (!res) return;
    closeAllModals();
    toast('Die Band ist gegründet!', 'ok');
    navigate(`#/band/${res.bandId}`);
  });
}

function street(m) {
  return `
    ${head('Straßenmusik', m)}
    ${statusNotice(m)}
    <div class="action-grid">
      <div class="action-tile">
        <div class="action-icon">🪙</div>
        <div class="action-main"><b>${esc(cat().genres[m.genres[0]].name)} auf der Straße</b><span class="muted small">3 Lieder · ${duration(3 * cat().rules.songSeconds)} · etwas Bekanntheit</span></div>
        <button class="btn" data-act="solo" data-id="${m.id}" data-type="busking" ${busyOf(m) ? 'disabled' : ''}>Spielen</button>
      </div>
    </div>`;
}

function chronicle(m) {
  const history = app.state.history.filter((h) => h.musicianId === m.id);
  return `
    ${head('Chronik', m)}
    <section class="panel">
      ${history.length ? `<ul class="history">${history.map((h) => `<li><span>${esc(h.text)}</span><time>${timeAgo(h.createdAt, app.state.now)}</time></li>`).join('')}</ul>` : '<p class="muted">Noch nichts passiert.</p>'}
    </section>`;
}

/** Stufe 3: aus drei Fähigkeiten eine wählen. */
export function openAbilityChoice(id) {
  const m = musicianById(id);
  if (!m?.abilityChoice) return;
  const modal = openModal({
    html: `
      <div class="eyebrow">${esc(fullName(m))} · Können-Stufe 3</div>
      <h2>Welche Fähigkeit?</h2>
      <div class="ability-list">${m.abilityChoice.map((a) => {
        const info = abilityInfo(a);
        return `<button class="ability pick" data-ability="${a}"><span class="ability-icon">${info.icon}</span><b>${esc(info.name)}</b><span class="muted small">${esc(info.desc)}</span></button>`;
      }).join('')}</div>`,
  });
  modal.body.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-ability]');
    if (button && await mutate('abilityChoose', { musicianId: m.id, ability: button.dataset.ability })) modal.close();
  });
}

defineActions({
  openMusician({ id }) {
    // Merken, woher wir kommen: Katalog, Band oder sonstwo – dorthin führt der Zurück-Knopf, und darin wird geblättert.
    const [, view, viewId] = location.hash.split('/');
    if (view === 'katalog') {
      app.ui.musicianNav = { ids: catalogList().map((x) => x.id), back: { href: '#/katalog', label: 'Katalog' } };
    } else if (view === 'band' && bandById(Number(viewId))) {
      const band = bandById(Number(viewId));
      app.ui.musicianNav = { ids: band.memberIds, back: { href: location.hash, label: band.name } };
    } else if (view !== 'musician') {
      app.ui.musicianNav = { ids: null, back: { href: location.hash || '#/', label: 'Zurück' } };
    }
    navigate(`#/musician/${id}`);
  },
  async toggleFavorite({ id }) {
    const m = musicianById(id);
    await mutate('musicianFavorite', { musicianId: m.id, favorite: !m.favorite });
  },
  chooseAbility({ id }) {
    openAbilityChoice(id);
  },
  jamChoose({ offer, index }) {
    openJamBand(offer, index);
  },
  async jamDecline({ offer }, el) {
    if (await mutate('jamDecline', { offerId: Number(offer) }) && el.closest('.modal')) closeAllModals();
  },
  async solo({ id, type }) {
    const res = await mutate('soloStart', { musicianId: Number(id), type });
    if (res) openLive(res.activity.id);
  },
});

// Pfeiltasten blättern auf der Musikerseite.
document.addEventListener('keydown', (e) => {
  if (!location.hash.startsWith('#/musician/') || document.body.classList.contains('has-modal')) return;
  if (e.target.closest?.('input, select, textarea')) return;
  const button = document.querySelectorAll('.pager-steps .btn')[e.key === 'ArrowLeft' ? 0 : e.key === 'ArrowRight' ? 1 : -1];
  if (button) location.hash = button.getAttribute('href');
});
