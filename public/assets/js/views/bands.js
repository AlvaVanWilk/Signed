import { api } from '../api.js';
import { emptySlot, face, fullName, gearCard, gearModel, genreName, instrumentIcon, instrumentName, managerCard, musicianCard } from '../cards.js';
import { app, bandById, cat, defineActions, isBusy, managerById, musicianById, mutate, navigate, rerender, runningFor } from '../core.js';
import { openLive } from '../live.js';
import { startPractice, startRecordSong } from '../play.js';
import { bar, confirmDialog, duration, esc, fmt0, fmt1, money, openModal, percent, promptDialog, timeAgo, toast } from '../ui.js';

/** Untermenü einer Band: [Pfad, Icon, Bezeichnung] */
export const BAND_SECTIONS = [
  ['', '🎸', 'Band'],
  ['uebung', '🥁', 'Übung'],
  ['promo', '📣', 'Promo'],
  ['auftritte', '🎤', 'Auftritte'],
  ['studio', '💿', 'Studio'],
  ['chronik', '📜', 'Chronik'],
  ['auszeichnungen', '🏆', 'Auszeichnungen'],
];

export function renderList() {
  const s = app.state;
  return `
    <div class="section-head"><h1>Bands</h1><button class="btn primary" data-act="newBand">+ Neue Band</button></div>
    ${s.bands.length ? `<div class="tile-grid">${s.bands.map((b) => `<a class="panel band-link" href="#/band/${b.id}"><b>${esc(b.name)}</b><span class="chip genre genre-${b.genre}">${esc(genreName(b.genre))}</span></a>`).join('')}</div>`
      : '<div class="empty">Noch keine Band.</div>'}`;
}

export function renderBand(route) {
  const b = bandById(route.id);
  if (!b) return '<div class="empty">Diese Band gibt es nicht mehr.</div>';
  const members = b.memberIds.map(musicianById).filter(Boolean);
  const section = BAND_SECTIONS.some(([key]) => key === route.section) ? route.section : '';
  const render = { '': overview, uebung: practice, promo, auftritte: gigs, studio, chronik: chronicle, auszeichnungen: awards }[section];
  return render(b, members);
}

function head(title, b) {
  return `<div class="page-head"><div><div class="eyebrow">${esc(b.name)}</div><h1>${esc(title)}</h1></div></div>`;
}

/** Was gerade läuft oder warum nichts geht. */
function blocker(b, members) {
  const running = runningFor(b.id);
  if (running) return { text: running.title, running };
  if (isBusy(b.busyUntil)) return { text: 'Die Band ist gerade beschäftigt.' };
  const away = members.find((m) => isBusy(m.busyUntil));
  if (away) return { text: `${away.firstName} ist gerade unterwegs.` };
  if (members.length < cat().rules.minMembers) return { text: 'Die Band braucht mindestens zwei Musiker.' };
  return null;
}

function blockerNotice(b, members) {
  const block = blocker(b, members);
  if (!block) return '';
  return `
    <div class="notice">
      <span class="pulse"></span><span>${esc(block.text)}</span>
      ${block.running ? `<span class="countdown" data-until="${block.running.endsAt}"></span>${block.running.type !== 'bandpromo' ? `<button class="btn small" data-act="watch" data-id="${block.running.id}">Zuschauen</button>` : ''}` : ''}
    </div>`;
}

/** Kachel für eine Aktion: Icon, Titel, eine Zeile Info, optional Zusatz, Knopf. */
function actionTile({ icon, title, meta = '', body = '', button = '', locked = false }) {
  return `
    <div class="action-tile ${locked ? 'locked' : ''}">
      <div class="action-icon">${icon}</div>
      <div class="action-main"><b>${esc(title)}</b>${meta ? `<span class="muted small">${meta}</span>` : ''}${body}</div>
      ${locked ? '<span class="chip">bald</span>' : button}
    </div>`;
}

const songsMeta = (songs) => `${songs} ${songs === 1 ? 'Lied' : 'Lieder'} · ${duration(songs * cat().rules.songSeconds)}`;

// ---------- Band ----------

function overview(b, members) {
  const gig = b.options.gig;
  return `
    <div class="page-head">
      <div><div class="eyebrow">${esc(genreName(b.genre))}</div><h1>${esc(b.name)} <button class="icon-btn" data-act="bandRename" data-id="${b.id}" title="Umbenennen">✎</button></h1></div>
    </div>
    <div class="stat-grid">
      <div class="tile"><span>Sound</span><b>${fmt1(b.sound)}</b></div>
      <div class="tile"><span>Ruhm</span><b>${fmt1(b.fame)}</b>${gig.nextVenue ? `<small>${esc(gig.nextVenue)} ab ${fmt0(gig.nextFame)}</small>${bar(gig.progress, 1, 'thin')}` : ''}</div>
      <div class="tile"><span>Eingespieltheit</span><b>${fmt0(b.cohesion)}<small> / ${fmt0(b.maxCohesion)}</small></b>${bar(b.cohesion, 100, 'thin')}</div>
      <div class="tile"><span>Chemie</span><b>${chemistryLabel(b.chemistry)}</b></div>
      <div class="tile"><span>Platten</span><b>${fmt0(b.records)}</b><small>${fmt0(b.songsRecorded)} Lieder</small></div>
    </div>
    <div class="lineup-grid">
      ${members.map((m) => memberTile(b, m)).join('')}
      ${members.length < cat().rules.maxMembers ? `<div class="lineup-tile">${emptySlot('Musiker aufnehmen', { act: 'addMember', data: { id: b.id } })}<span></span></div>` : ''}
      <div class="lineup-tile manager-tile">${managerSlot(b)}<span class="slot-label">Manager${b.managerId ? ` · Stufe ${fmt1(b.managerLevel)}` : ''}</span></div>
    </div>
    <div class="danger-zone"><button class="btn danger ghost small" data-act="bandDisband" data-id="${b.id}">Band auflösen</button></div>`;
}

function chemistryLabel(chemistry) {
  if (chemistry >= 1.15) return 'Super';
  if (chemistry >= 1.0) return 'Gut';
  if (chemistry >= 0.85) return 'Angespannt';
  return 'Zoff';
}

function memberTile(b, m) {
  const slotIndex = m.instruments.length > 1 ? m.bandSlot - 1 : 0;
  const played = m.instruments[slotIndex];
  const gear = app.state.gear.find((g) => g.musicianId === m.id && gearModel(g.model).type === played.type);
  const gearData = { musician: m.id, type: played.type };
  return `
    <div class="lineup-tile">
      <div class="lineup-pair">
        ${musicianCard(m, { size: 'sm' })}
        ${gear ? gearCard(gear, { act: 'pickGear', data: gearData, size: 'sm' }) : emptySlot(instrumentName(played.type), { act: 'pickGear', data: gearData, icon: instrumentIcon(played.type) })}
      </div>
      <div class="lineup-controls">
        ${m.instruments.length > 1 ? `
          <select data-change="bandSetSlot" data-band="${b.id}" data-musician="${m.id}">
            ${m.instruments.map((i, idx) => `<option value="${idx + 1}" ${idx === slotIndex ? 'selected' : ''}>${instrumentIcon(i.type)} ${esc(instrumentName(i.type))}</option>`).join('')}
          </select>` : `<span>${instrumentIcon(played.type)} ${esc(instrumentName(played.type))}</span>`}
        ${m.genres.includes(b.genre) ? '' : '<span class="warn small">fremdes Genre</span>'}
        <button class="icon-btn" data-act="removeMember" data-band="${b.id}" data-musician="${m.id}" title="Aus der Band nehmen">✕</button>
      </div>
    </div>`;
}

function managerSlot(b) {
  const manager = b.managerId ? managerById(b.managerId) : null;
  if (!manager) return emptySlot('Manager', { act: 'pickManager', data: { id: b.id }, icon: '💼' });
  return managerCard(manager, { act: 'pickManager', data: { id: b.id }, size: 'sm' });
}

// ---------- Übung ----------

function practice(b, members) {
  const blocked = !!blocker(b, members);
  return `
    ${head('Übung', b)}
    ${blockerNotice(b, members)}
    <div class="action-grid">
      ${actionTile({
        icon: '🥁', title: 'Bandprobe', meta: `${songsMeta(b.options.rehearsal.songs)} · Eingespieltheit, Können`,
        button: `<button class="btn primary" data-act="startActivity" data-id="${b.id}" data-type="rehearsal" ${blocked ? 'disabled' : ''}>Proben</button>`,
      })}
      ${members.map((m) => practiceTile(m, isBusy(m.busyUntil) || isBusy(b.busyUntil))).join('')}
    </div>`;
}

export function practiceTile(m, busy) {
  return actionTile({
    icon: face(m, 'md'),
    title: fullName(m),
    meta: 'Solo-Probe',
    body: `<span class="tile-buttons">${m.instruments.map((i, idx) => `<button class="btn small" data-act="practice" data-id="${m.id}" data-slot="${idx + 1}" ${busy ? 'disabled' : ''}>${instrumentIcon(i.type)} ${esc(instrumentName(i.type))} ${fmt0(i.skill)}</button>`).join('')}</span>`,
  });
}

// ---------- Promo ----------

function promo(b, members) {
  const blocked = !!blocker(b, members);
  return `
    ${head('Promo', b)}
    ${blockerNotice(b, members)}
    <div class="action-grid">
      ${actionTile({
        icon: '📣', title: 'Band-Promo', meta: `${duration(cat().rules.bandPromoSeconds)} · Präsenz, Ruhm`,
        button: `<button class="btn primary" data-act="bandPromo" data-id="${b.id}" ${blocked ? 'disabled' : ''}>Losschicken</button>`,
      })}
      ${members.map((m) => promoTile(m, isBusy(m.busyUntil) || isBusy(b.busyUntil))).join('')}
    </div>`;
}

export function promoTile(m, busy) {
  return actionTile({
    icon: face(m, 'md'),
    title: fullName(m),
    meta: `Promo-Mission · ${duration(cat().rules.promoSeconds)} · Präsenz ${fmt0(m.presence)}`,
    button: `<button class="btn" data-act="promo" data-id="${m.id}" ${busy ? 'disabled' : ''}>Los</button>`,
  });
}

// ---------- Auftritte ----------

function gigs(b, members) {
  const disabled = blocker(b, members) ? 'disabled' : '';
  const o = b.options;
  const gig = o.gig;
  const songs = Math.max(1, Math.min(app.ui.gigSongs[b.id] || gig.maxSongs, gig.maxSongs));
  return `
    ${head('Auftritte', b)}
    ${blockerNotice(b, members)}
    <div class="action-grid">
      ${actionTile({
        icon: '🎤', title: `Gig · ${gig.venue}`,
        meta: gig.available ? `${money(gig.gagePerSong)} pro Lied` : 'Erst mit der ersten Platte',
        body: gig.available ? `
          <span class="small"><label class="inline-select">Lieder
            <select data-change="gigSongs" data-id="${b.id}">${Array.from({ length: gig.maxSongs }, (_, i) => i + 1).map((n) => `<option value="${n}" ${n === songs ? 'selected' : ''}>${n}</option>`).join('')}</select>
          </label> · ${duration(songs * cat().rules.songSeconds)} · <b>${money(gig.gagePerSong * songs)}</b></span>` : '',
        button: `<button class="btn primary" data-act="startActivity" data-id="${b.id}" data-type="gig" ${gig.available ? disabled : 'disabled'}>Spielen</button>`,
      })}
      ${actionTile({
        icon: '🎙️', title: 'Open Stage', meta: `${songsMeta(o.openstage.songs)} · Ruhm, Chance auf eine Karte`,
        button: `<button class="btn" data-act="startActivity" data-id="${b.id}" data-type="openstage" ${disabled}>Spielen</button>`,
      })}
      ${actionTile({
        icon: '🪙', title: 'Straßenmusik', meta: `${songsMeta(o.busking.songs)} · Präsenz`,
        button: `<button class="btn" data-act="startActivity" data-id="${b.id}" data-type="busking" ${disabled}>Spielen</button>`,
      })}
      ${actionTile({ icon: '🚌', title: 'Tournee', meta: 'Land · Kontinent · Welt', locked: true })}
    </div>`;
}

// ---------- Studio ----------

function studio(b, members) {
  const rules = cat().rules;
  const open = b.record.open;
  const blocked = !!blocker(b, members);
  let record;
  if (open) {
    record = actionTile({
      icon: '💿', title: `„${open.title}“`, meta: `${open.songsDone} von ${open.songsTotal} Liedern`,
      body: `<span class="song-dots">${Array.from({ length: open.songsTotal }, (_, i) => {
        const r = open.songResults[i];
        return `<span class="song-dot ${r ? (r.miss === 0 ? 'perfect' : r.accuracy >= 0.75 ? 'good' : 'weak') : ''}"></span>`;
      }).join('')}</span>`,
      button: `<button class="btn primary" data-act="recordSong" data-id="${b.id}" ${blocked ? 'disabled' : ''}>Lied ${open.songsDone + 1}</button>`,
    });
  } else if (b.record.blocker) {
    record = actionTile({ icon: '💿', title: 'Neue Platte', meta: esc(b.record.blocker) });
  } else {
    const form = app.ui.form;
    const titleKey = `recordTitle${b.id}`;
    const songsKey = `recordSongs${b.id}`;
    const songs = Number(form[songsKey] || rules.recordMinSongs);
    record = `
      <form class="action-tile record-form" data-form="recordStart" data-id="${b.id}">
        <div class="action-icon">💿</div>
        <div class="action-main">
          <b>Neue Platte</b>
          <span class="input-dice"><input name="title" maxlength="60" required data-ui="${titleKey}" value="${esc(form[titleKey] || '')}" placeholder="Titel"><button type="button" class="btn small" data-act="diceRecord" data-key="${titleKey}" title="Zufälliger Titel">🎲</button></span>
          <label class="small">Lieder: <b data-output="${songsKey}">${songs}</b>
            <input type="range" name="songs" min="${rules.recordMinSongs}" max="${rules.recordMaxSongs}" value="${songs}" data-ui="${songsKey}">
          </label>
        </div>
        <button class="btn primary" ${app.state.label.money < rules.recordCost ? 'disabled' : ''}>${money(rules.recordCost)}</button>
      </form>`;
  }
  return `
    ${head('Studio', b)}
    ${blockerNotice(b, members)}
    <div class="action-grid">
      ${record}
      ${actionTile({ icon: '🎛️', title: 'Remaster', meta: 'Ältere Platten neu herausbringen', locked: true })}
      ${actionTile({ icon: '🎧', title: 'Produzent', meta: 'Mit einem Produzenten aufnehmen', locked: true })}
    </div>
    ${b.discography.length ? `
      <h2>Diskografie</h2>
      <div class="tile-grid">${b.discography.map((r) => `
        <div class="panel record-tile">
          <b>„${esc(r.title)}“</b>
          <span class="muted small">${r.format} · ${r.songs} Lieder · ${percent(r.quality)}${r.perfect ? ' · perfekt' : ''}</span>
          ${r.charting ? `<span class="chip special">Charts · ${fmt1(r.points)}</span>` : ''}
        </div>`).join('')}
      </div>` : ''}`;
}

// ---------- Chronik & Auszeichnungen ----------

function chronicle(b) {
  const history = app.state.history.filter((h) => h.bandId === b.id);
  return `
    ${head('Chronik', b)}
    <section class="panel">
      ${history.length ? `<ul class="history">${history.map((h) => `<li><span>${esc(h.text)}</span><time>${timeAgo(h.createdAt, app.state.now)}</time></li>`).join('')}</ul>` : '<p class="muted">Noch nichts passiert.</p>'}
    </section>`;
}

/** Auszeichnungen ergeben sich aus dem, was die Band erreicht hat. */
function bandAwards(b) {
  const list = [];
  if (b.gigsPlayed > 0) list.push(['🎤', 'Erster Gig', `${b.gigsPlayed} Gigs gespielt`]);
  if (b.records > 0) list.push(['💿', 'Erste Platte', `${b.records} ${b.records === 1 ? 'Platte' : 'Platten'}`]);
  for (const r of b.discography) {
    if (r.perfect) list.push(['✨', 'Perfekte Platte', `„${r.title}“`]);
    if (r.peak >= 400) list.push(['💽', 'Platin-Schallplatte', `„${r.title}“`]);
    else if (r.peak >= 150) list.push(['🥇', 'Goldene Schallplatte', `„${r.title}“`]);
    if (r.topped) list.push(['🏆', 'Platz 1 der Charts', `„${r.title}“`]);
  }
  if (['halle', 'arena'].includes(b.options.gig.venueKey)) list.push(['🏟️', 'Große Bühne', b.options.gig.venue]);
  return list;
}

function awards(b) {
  const list = bandAwards(b);
  return `
    ${head('Auszeichnungen', b)}
    ${list.length ? `<div class="award-grid">${list.map(([icon, title, sub]) => `
      <div class="award"><span class="award-icon">${icon}</span><b>${esc(title)}</b><span class="muted small">${esc(sub)}</span></div>`).join('')}</div>`
      : '<div class="empty">Noch keine Auszeichnungen.</div>'}`;
}

// ---------- Auswahl-Dialoge ----------

function openGearPicker(musicianId, type) {
  const m = musicianById(musicianId);
  const modal = openModal({
    wide: true,
    render: () => {
      const options = app.state.gear.filter((g) => gearModel(g.model).type === type && (!g.musicianId || g.musicianId === m.id));
      const current = options.find((g) => g.musicianId === m.id);
      return `
        <h2>${esc(instrumentName(type))} für ${esc(m.firstName)}</h2>
        ${options.length ? `<div class="tcg-grid">${options.map((g) => gearCard(g, {
          extra: g === current
            ? '<button class="btn small" data-gear-remove>Herausnehmen</button>'
            : `<button class="btn primary small" data-gear="${g.id}">Einlegen</button>`,
        })).join('')}</div>` : '<div class="empty">Kein freies Instrument dieser Art im Lager.</div>'}`;
    },
  });
  modal.body.addEventListener('click', async (e) => {
    const add = e.target.closest('[data-gear]');
    const remove = e.target.closest('[data-gear-remove]');
    if (add && await mutate('gearAssign', { gearId: Number(add.dataset.gear), musicianId: m.id })) modal.close();
    if (remove) {
      const current = app.state.gear.find((g) => g.musicianId === m.id && gearModel(g.model).type === type);
      if (current && await mutate('gearAssign', { gearId: current.id, musicianId: null })) modal.close();
    }
  });
}

function openManagerPicker(bandId) {
  const b = bandById(bandId);
  const max = cat().rules.managerMaxBands;
  if (!app.state.managers.length) {
    toast('Noch kein Manager unter Vertrag.', 'info');
    return;
  }
  const modal = openModal({
    wide: true,
    render: () => `
      <h2>Manager für ${esc(b.name)}</h2>
      <div class="tcg-grid">${app.state.managers.map((m) => {
        const mine = m.id === b.managerId;
        const full = !mine && m.bandIds.length >= max;
        return managerCard(m, {
          extra: mine
            ? '<button class="btn small" data-manager="">Abziehen</button>'
            : `<button class="btn primary small" data-manager="${m.id}" ${full ? 'disabled' : ''}>${full ? 'Ausgelastet' : 'Zuweisen'}</button>`,
        });
      }).join('')}</div>`,
  });
  modal.body.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-manager]');
    if (!button || button.disabled) return;
    if (await mutate('bandSetManager', { bandId: b.id, managerId: button.dataset.manager ? Number(button.dataset.manager) : null })) modal.close();
  });
}

function pickRow(m, genre, { checkbox = true, button = '' } = {}) {
  const fits = m.genres.includes(genre);
  return `
    <div class="pick-row" data-id="${m.id}">
      <label class="pick-main">
        ${checkbox ? `<input type="checkbox" value="${m.id}">` : ''}
        ${face(m, 'sm')}
        <span class="pick-name">${esc(fullName(m))}<small class="muted">${m.genres.map(genreName).map(esc).join(', ')}${fits ? '' : ' · <span class="warn">fremdes Genre</span>'}</small></span>
      </label>
      ${m.instruments.length > 1 ? `
        <select data-slot>
          ${m.instruments.map((i, idx) => `<option value="${idx + 1}">${instrumentIcon(i.type)} ${esc(instrumentName(i.type))} ${fmt1(i.skill)}</option>`).join('')}
        </select>` : `<span class="pick-instr">${instrumentIcon(m.instruments[0].type)} ${esc(instrumentName(m.instruments[0].type))} <b>${fmt1(m.instruments[0].skill)}</b></span>`}
      ${button}
    </div>`;
}

function lineupProblems(picks) {
  const rules = cat().rules;
  const problems = [];
  if (picks.length < rules.minMembers) problems.push(`Mindestens ${rules.minMembers} Musiker auswählen.`);
  if (picks.length > rules.maxMembers) problems.push(`Höchstens ${rules.maxMembers} Musiker.`);
  const perType = {};
  for (const p of picks) perType[p.type] = (perType[p.type] || 0) + 1;
  for (const [type, max] of Object.entries(rules.maxPerInstrument)) {
    if ((perType[type] || 0) > max) problems.push(`Höchstens ${max} × ${instrumentName(type)}.`);
  }
  return problems;
}

function openCreateBand() {
  const s = app.state;
  const free = s.musicians.filter((m) => !m.bandId);
  const used = new Set(s.bands.map((b) => b.genre));
  const genres = Object.entries(cat().genres).filter(([key]) => !used.has(key));
  if (free.length < cat().rules.minMembers) {
    toast('Du brauchst mindestens zwei Musiker ohne Band.', 'error');
    return;
  }
  if (!genres.length) {
    toast('Dein Label hat schon in jedem Genre eine Band.', 'error');
    return;
  }
  const counts = {};
  for (const m of free) for (const g of m.genres) if (!used.has(g)) counts[g] = (counts[g] || 0) + 1;
  const suggested = Object.entries(counts).sort((a, b) => b[1] - a[1])[0]?.[0] ?? genres[0][0];

  const modal = openModal({
    wide: true,
    html: `
      <h2>Neue Band gründen</h2>
      <form class="stack create-band">
        <div class="form-row">
          <label class="field"><span>Bandname</span>
            <span class="input-dice"><input name="name" maxlength="40" required placeholder="z. B. Die Kellerkinder"><button type="button" class="btn small" data-dice title="Zufälliger Name">🎲</button></span>
          </label>
          <label class="field"><span>Genre</span>
            <select name="genre">${genres.map(([key, g]) => `<option value="${key}" ${key === suggested ? 'selected' : ''}>${esc(g.name)}</option>`).join('')}</select>
          </label>
        </div>
        <div class="pick-list"></div>
        <p class="create-summary small"></p>
        <div class="dialog-actions">
          <button type="button" class="btn ghost" data-cancel>Abbrechen</button>
          <button class="btn primary">Band gründen</button>
        </div>
      </form>`,
  });
  const form = modal.body.querySelector('form');
  const list = form.querySelector('.pick-list');
  const summary = form.querySelector('.create-summary');
  const selected = new Set();

  const picks = () => [...list.querySelectorAll('.pick-row')]
    .filter((row) => row.querySelector('input').checked)
    .map((row) => {
      const m = musicianById(row.dataset.id);
      const slot = Number(row.querySelector('[data-slot]')?.value || 1);
      return { id: m.id, slot, type: m.instruments[slot - 1].type };
    });

  const drawList = () => {
    list.innerHTML = free.map((m) => pickRow(m, form.genre.value)).join('');
    list.querySelectorAll('input[type=checkbox]').forEach((cb) => { cb.checked = selected.has(Number(cb.value)); });
  };
  const update = () => {
    selected.clear();
    for (const p of picks()) selected.add(p.id);
    const problems = lineupProblems(picks());
    summary.innerHTML = problems.length ? `<span class="warn">${problems.map(esc).join(' ')}</span>` : `<span class="ok">${selected.size} Musiker – passt!</span>`;
  };
  drawList();
  update();
  form.genre.addEventListener('change', () => {
    drawList();
    update();
  });
  list.addEventListener('change', update);
  form.querySelector('[data-cancel]').addEventListener('click', () => modal.close());
  form.querySelector('[data-dice]').addEventListener('click', async () => {
    form.name.value = await suggestName('band');
  });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const members = picks().map(({ id, slot }) => ({ id, slot }));
    const problems = lineupProblems(picks());
    if (problems.length) {
      toast(problems[0], 'error');
      return;
    }
    const res = await mutate('bandCreate', { name: form.name.value, genre: form.genre.value, members });
    if (res) {
      modal.close();
      navigate(`#/band/${res.bandId}`);
      toast('Die Band ist gegründet!', 'ok');
    }
  });
}

function openAddMember(bandId) {
  const band = bandById(bandId);
  const free = app.state.musicians.filter((m) => !m.bandId);
  if (!free.length) {
    toast('Alle Musiker spielen schon in einer Band.', 'error');
    return;
  }
  const modal = openModal({
    wide: true,
    html: `
      <h2>Musiker für ${esc(band.name)}</h2>
      <div class="pick-list">${free.map((m) => pickRow(m, band.genre, {
        checkbox: false,
        button: `<button class="btn small" data-add="${m.id}">Aufnehmen</button>`,
      })).join('')}
      </div>`,
  });
  modal.body.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-add]');
    if (!button) return;
    const row = button.closest('.pick-row');
    const slot = Number(row.querySelector('[data-slot]')?.value || 1);
    const res = await mutate('bandAddMember', { bandId: band.id, musicianId: Number(button.dataset.add), slot });
    if (res) modal.close();
  });
}

export async function suggestName(kind) {
  try {
    return (await api('nameSuggest', { kind })).name;
  } catch (e) {
    toast(e.message, 'error');
    return '';
  }
}

defineActions({
  async diceRecord({ key }) {
    const name = await suggestName('record');
    if (!name) return;
    app.ui.form[key] = name;
    const input = document.querySelector(`[data-ui="${key}"]`);
    if (input) input.value = name;
  },
  pickGear({ musician, type }) {
    openGearPicker(Number(musician), type);
  },
  pickManager({ id }) {
    openManagerPicker(Number(id));
  },
  newBand() {
    openCreateBand();
  },
  addMember({ id }) {
    openAddMember(Number(id));
  },
  async removeMember({ band, musician }) {
    const b = bandById(band);
    const m = musicianById(musician);
    if (b.released) {
      const ok = await confirmDialog(
        `${fullName(m)} verlässt ${b.name}: Eingespieltheit −${cat().rules.cohesionRemovePenalty}, Bandruhm −${fmt1(m.fame)}.`,
        { ok: 'Rausnehmen', danger: true },
      );
      if (!ok) return;
    }
    await mutate('bandRemoveMember', { bandId: b.id, musicianId: m.id });
  },
  async bandSetSlot(value, el) {
    await mutate('bandSetSlot', { bandId: Number(el.dataset.band), musicianId: Number(el.dataset.musician), slot: Number(value) });
  },
  async bandRename({ id }) {
    const b = bandById(id);
    const name = await promptDialog('Neuer Bandname', b.name);
    if (name && name !== b.name) await mutate('bandRename', { bandId: b.id, name });
  },
  async bandDisband({ id }) {
    const b = bandById(id);
    const ok = await confirmDialog(`${b.name} auflösen? Eine laufende Produktion geht verloren.`, { ok: 'Auflösen', danger: true });
    if (!ok) return;
    if (await mutate('bandDisband', { bandId: b.id })) navigate('#/');
  },
  gigSongs(value, el) {
    app.ui.gigSongs[el.dataset.id] = Number(value);
    rerender();
  },
  async startActivity({ id, type }) {
    const b = bandById(id);
    const songs = type === 'gig' ? Math.max(1, Math.min(app.ui.gigSongs[b.id] || b.options.gig.maxSongs, b.options.gig.maxSongs)) : 0;
    const res = await mutate('activityStart', { bandId: b.id, type, songs });
    if (res) openLive(res.activity.id);
  },
  async bandPromo({ id }) {
    if (await mutate('bandPromo', { bandId: Number(id) })) toast('Die Band ist auf Promo-Tour.', 'ok');
  },
  async recordStart(values, form) {
    const bandId = Number(form.dataset.id);
    const res = await mutate('recordStart', { bandId, title: values.title, songs: Number(values.songs) });
    if (res) {
      delete app.ui.form[`recordTitle${bandId}`];
      rerender();
    }
  },
  recordSong({ id }) {
    startRecordSong(id);
  },
  practice({ id, slot }) {
    startPractice(id, slot);
  },
  async promo({ id }) {
    if (await mutate('promoStart', { musicianId: Number(id) })) toast(`${musicianById(id).firstName} ist auf Promo-Tour.`, 'ok');
  },
  watch({ id }) {
    openLive(id);
  },
});
