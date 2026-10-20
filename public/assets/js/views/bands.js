import { api } from '../api.js';
import { emptySlot, face, fullName, gearCard, gearModel, genreName, instrumentIcon, instrumentName, managerCard, musicianCard } from '../cards.js';
import { app, bandById, cat, defineActions, isBusy, managerById, musicianById, mutate, navigate, rerender, runningFor } from '../core.js';
import { openLive } from '../live.js';
import { startRecordSong } from '../play.js';
import { bar, confirmDialog, duration, esc, fmt0, fmt1, money, openModal, percent, promptDialog, timeAgo, toast } from '../ui.js';
import { bandTile } from './overview.js';

export function renderList() {
  const s = app.state;
  const rules = cat().rules;
  return `
    <div class="section-head">
      <div><h1>Bands</h1><p class="muted">Pro Genre eine Band, ${rules.minMembers} bis ${rules.maxMembers} Musiker. Geld gehört dem Label, Ruhm den Musikern.</p></div>
      <button class="btn primary" data-act="newBand">+ Neue Band</button>
    </div>
    ${s.bands.length ? `<div class="band-grid">${s.bands.map(bandTile).join('')}</div>` : `
      <div class="empty">Noch keine Band. Mit mindestens zwei Musikern kannst du <button class="link" data-act="newBand">jetzt eine gründen</button>.</div>`}`;
}

export function renderDetail(id) {
  const b = bandById(id);
  if (!b) return '<div class="empty">Diese Band gibt es nicht mehr. <a href="#/bands">Zu den Bands</a></div>';
  const members = b.memberIds.map(musicianById).filter(Boolean);
  const history = app.state.history.filter((h) => h.bandId === b.id);

  return `
    <a class="back" href="#/bands">← Alle Bands</a>
    <section class="band-head">
      <div>
        <div class="eyebrow">${esc(genreName(b.genre))}</div>
        <h1>${esc(b.name)} <button class="icon-btn" data-act="bandRename" data-id="${b.id}" title="Umbenennen">✎</button></h1>
        <div class="faces">${members.map((m) => face(m, 'md')).join('')}</div>
      </div>
      <div class="stat-tiles">
        <div class="tile" title="Kritikerwertung: Können, Instrumente, Genre-Passung und Eingespieltheit"><span>Sound</span><b>${fmt1(b.sound)}</b></div>
        <div class="tile" title="Summe der Bekanntheit aller Mitglieder"><span>Ruhm</span><b>${fmt1(b.fame)}</b>
          ${b.options.gig.nextVenue ? `<small>${esc(b.options.gig.nextVenue)} ab ${fmt0(b.options.gig.nextFame)}</small>${bar(b.options.gig.progress, 1, 'thin')}` : '<small>Höchste Stufe</small>'}
        </div>
        <div class="tile" title="Wächst durch gemeinsame Auftritte und Aufnahmen. Die Chemie bestimmt das Maximum."><span>Eingespieltheit</span><b>${fmt0(b.cohesion)}<small> / ${fmt0(b.maxCohesion)}</small></b>${bar(b.cohesion, 100, 'thin')}</div>
        <div class="tile" title="Wie gut die Charaktere zueinander passen"><span>Chemie</span><b>${chemistryLabel(b.chemistry)}</b><small>× ${fmt1(b.chemistry)}</small></div>
        <div class="tile"><span>Diskografie</span><b>${fmt0(b.records)} ${b.records === 1 ? 'Platte' : 'Platten'}</b><small>${fmt0(b.songsRecorded)} Lieder${b.perfectRecords ? ` · ${b.perfectRecords}× perfekt` : ''}</small></div>
      </div>
    </section>

    <section class="panel">
      <div class="section-head">
        <h2>Aufstellung</h2>
        <span class="muted small">${b.released ? `Freigegeben: Jeder Wechsel kostet Eingespieltheit, ein Rauswurf ${cat().rules.cohesionRemovePenalty} Punkte.` : 'Bis zum ersten Auftritt kannst du die Besetzung frei ändern.'}</span>
      </div>
      <div class="lineup-board">
        <div class="lineup">
          ${members.map((m) => memberUnit(b, m)).join('')}
          ${members.length < cat().rules.maxMembers ? `<div class="lineup-unit"><div class="lineup-cards">${emptySlot('Musiker aufnehmen', { act: 'addMember', data: { id: b.id } })}</div></div>` : ''}
        </div>
        <div class="lineup-side">
          <div><span class="slot-label">Manager</span>${managerUnit(b)}</div>
        </div>
      </div>
    </section>

    <div class="columns">
      <div class="stack">
        <section class="panel">
          <h2>Auftritte</h2>
          ${blockerNotice(b, members)}
          ${performances(b, members)}
        </section>
        <section class="panel">
          <h2>Studio</h2>
          ${studio(b, members)}
        </section>
      </div>
      <div class="stack">
        <section class="panel">
          <h2>Diskografie</h2>
          ${discography(b)}
        </section>
        <section class="panel">
          <h2>Bandhistorie</h2>
          ${history.length ? `<ul class="history">${history.map((h) => `<li><span>${esc(h.text)}</span><time>${timeAgo(h.createdAt, app.state.now)}</time></li>`).join('')}</ul>` : '<p class="muted">Die Geschichte dieser Band beginnt gerade.</p>'}
          <div class="danger-zone"><button class="btn danger ghost small" data-act="bandDisband" data-id="${b.id}">Band auflösen</button></div>
        </section>
      </div>
    </div>`;
}

function memberUnit(b, m) {
  const slotIndex = m.instruments.length > 1 ? m.bandSlot - 1 : 0;
  const played = m.instruments[slotIndex];
  const gear = app.state.gear.find((g) => g.musicianId === m.id && gearModel(g.model).type === played.type);
  const gearData = { musician: m.id, type: played.type };
  return `
    <div class="lineup-unit">
      <div class="lineup-cards">
        ${musicianCard(m, { size: 'sm' })}
        ${gear
          ? gearCard(gear, { act: 'pickGear', data: gearData, size: 'sm' })
          : emptySlot(`${instrumentName(played.type)} einlegen`, { act: 'pickGear', data: gearData, icon: instrumentIcon(played.type) })}
      </div>
      <div class="lineup-controls">
        ${m.instruments.length > 1 ? `
          <select data-change="bandSetSlot" data-band="${b.id}" data-musician="${m.id}" title="Welches Instrument spielt ${esc(m.firstName)} in der Band?">
            ${m.instruments.map((i, idx) => `<option value="${idx + 1}" ${idx === slotIndex ? 'selected' : ''}>${instrumentIcon(i.type)} ${esc(instrumentName(i.type))}</option>`).join('')}
          </select>` : `<span>${instrumentIcon(played.type)} ${esc(instrumentName(played.type))}</span>`}
        ${m.genres.includes(b.genre) ? '' : '<span class="warn">fremdes Genre</span>'}
        <button class="icon-btn" data-act="removeMember" data-band="${b.id}" data-musician="${m.id}" title="Aus der Band nehmen">✕</button>
      </div>
    </div>`;
}

function managerUnit(b) {
  const manager = b.managerId ? managerById(b.managerId) : null;
  if (!manager) {
    return emptySlot(app.state.managers.length ? 'Manager zuweisen' : 'Noch kein Manager', { act: 'pickManager', data: { id: b.id }, icon: '💼' });
  }
  return `
    <div class="tcg-wrap">
      ${managerCard(manager, { act: 'pickManager', data: { id: b.id }, size: 'sm' })}
      <span class="muted small">Für diese Band Stufe ${fmt1(b.managerLevel)}${manager.bandIds.length > 1 ? ` (geteilt auf ${manager.bandIds.length} Bands)` : ''}</span>
    </div>`;
}

function discography(b) {
  if (!b.discography.length) return '<p class="muted">Noch keine Platte. Erst mit einer Platte gibt es richtige Gigs und einen Platz in den Charts.</p>';
  return `
    <ul class="discography">${b.discography.map((r) => `
      <li>
        <div><b>„${esc(r.title)}“</b><span class="muted small">${r.format} · ${r.songs} Lieder · Qualität ${percent(r.quality)}${r.perfect ? ' · perfekt' : ''}</span></div>
        ${r.charting ? `<span class="chip special" title="Aktuelle Chartpunkte">Charts · ${fmt1(r.points)}</span>` : '<span class="muted small">aus den Charts</span>'}
      </li>`).join('')}
    </ul>`;
}

function openGearPicker(musicianId, type) {
  const m = musicianById(musicianId);
  const modal = openModal({
    wide: true,
    render: () => {
      const options = app.state.gear.filter((g) => gearModel(g.model).type === type && (!g.musicianId || g.musicianId === m.id));
      const current = options.find((g) => g.musicianId === m.id);
      return `
        <h2>${esc(instrumentName(type))} für ${esc(m.firstName)}</h2>
        <p class="muted">Ein gutes Instrument entfaltet sich nur in fähigen Händen: ${esc(m.firstName)} hat Können ${fmt0(m.instruments.find((i) => i.type === type)?.skill ?? 0)}.</p>
        ${options.length ? `<div class="tcg-grid">${options.map((g) => gearCard(g, {
          extra: g === current
            ? '<button class="btn small" data-gear-remove>Herausnehmen</button>'
            : `<button class="btn primary small" data-gear="${g.id}">Einlegen</button>`,
        })).join('')}</div>` : `<div class="empty">Kein freies Instrument dieser Art im Lager. Instrumente gibt es im Equipment-Pack.</div>`}`;
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
    toast('Noch kein Manager unter Vertrag. Manager gibt es in Packs und manchmal nach einer Open Stage.', 'info');
    return;
  }
  const modal = openModal({
    wide: true,
    render: () => `
      <h2>Manager für ${esc(b.name)}</h2>
      <p class="muted">Ein Manager betreut bis zu ${max} Bands, teilt seine Leistung dann aber auf. Er ermöglicht längere Gigs.</p>
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

function chemistryLabel(chemistry) {
  if (chemistry >= 1.15) return 'Super';
  if (chemistry >= 1.0) return 'Gut';
  if (chemistry >= 0.85) return 'Angespannt';
  return 'Zoff';
}

/** Warum die Band gerade nichts starten kann, sonst null. */
function blocker(b, members) {
  const running = runningFor(b.id);
  if (running) return { text: `Live: ${running.title}`, running };
  if (isBusy(b.busyUntil)) return { text: 'Die Band ist gerade im Studio.' };
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
      ${block.running ? `<span class="countdown" data-until="${block.running.endsAt}"></span><button class="btn small" data-act="watch" data-id="${block.running.id}">Zuschauen</button>` : ''}
    </div>`;
}

function performances(b, members) {
  const disabled = blocker(b, members) ? 'disabled' : '';
  const songSeconds = cat().rules.songSeconds;
  const o = b.options;
  const gig = o.gig;
  const songs = Math.max(1, Math.min(app.ui.gigSongs[b.id] || gig.maxSongs, gig.maxSongs));

  return `
    <div class="action-row">
      <div>
        <b>Straßenmusik</b>
        <span class="muted small">${o.busking.songs} Lieder · ${duration(o.busking.songs * songSeconds)} · ca. ${money(o.busking.gage)} · kaum Ruhm · keine Platte nötig</span>
      </div>
      <button class="btn" data-act="startActivity" data-id="${b.id}" data-type="busking" ${disabled}>Spielen</button>
    </div>
    <div class="action-row">
      <div>
        <b>Open Stage</b>
        <span class="muted small">${o.openstage.songs} Lieder · ${duration(o.openstage.songs * songSeconds)} · kein Geld · etwas Ruhm · kleine Chance auf eine Karte</span>
        ${o.openstage.worthIt ? '' : '<span class="note small">Für eine so bekannte Band bringt die Open Stage keinen Ruhm mehr.</span>'}
      </div>
      <button class="btn" data-act="startActivity" data-id="${b.id}" data-type="openstage" ${disabled}>Spielen</button>
    </div>
    <div class="action-row ${gig.available ? '' : 'locked'}">
      <div>
        <b>Gig · ${esc(gig.venue)}</b>
        ${gig.available ? `
          <span class="muted small">Gage ${money(gig.gagePerSong)} pro Lied · Ruhm hängt vom Auftritt ab</span>
          <span class="small">
            <label class="inline-select">Lieder
              <select data-change="gigSongs" data-id="${b.id}">
                ${Array.from({ length: gig.maxSongs }, (_, i) => i + 1).map((n) => `<option value="${n}" ${n === songs ? 'selected' : ''}>${n}</option>`).join('')}
              </select>
            </label>
            = ${duration(songs * songSeconds)} · <b>${money(gig.gagePerSong * songs)}</b>
          </span>
          ${gig.maxSongs < gig.songCap ? `<span class="note small">Länger spielen? Dafür braucht die Band mehr produzierte Lieder (bis ${gig.songCap} mit diesem Management).</span>` : ''}
          ${gig.songCap < 10 ? `<span class="note small">Ein ${b.managerId ? 'besserer ' : ''}Manager ermöglicht längere Gigs (jetzt höchstens ${gig.songCap} Lieder).</span>` : ''}`
          : '<span class="muted small">Richtige Gigs gibt es erst, wenn die Band eine Platte produziert hat (eine EP reicht).</span>'}
      </div>
      <button class="btn primary" data-act="startActivity" data-id="${b.id}" data-type="gig" ${gig.available ? disabled : 'disabled'}>Gig spielen</button>
    </div>`;
}

function studio(b, members) {
  const rules = cat().rules;
  const open = b.record.open;
  const blocked = !!blocker(b, members);
  if (open) {
    return `
      <div class="record-open">
        <div class="record-title"><span>💿</span><div><b>„${esc(open.title)}“</b><span class="muted small">${open.songsDone} von ${open.songsTotal} Liedern eingespielt</span></div></div>
        ${bar(open.songsDone, open.songsTotal)}
        <div class="song-dots">${Array.from({ length: open.songsTotal }, (_, i) => {
          const r = open.songResults[i];
          return `<span class="song-dot ${r ? (r.miss === 0 ? 'perfect' : r.accuracy >= 0.75 ? 'good' : 'weak') : ''}" title="${r ? `Lied ${i + 1}: ${percent(r.accuracy)}` : `Lied ${i + 1}`}"></span>`;
        }).join('')}</div>
        <p class="muted small">Pro Lied ein Minispiel. Jedes Lied bringt Können, Geld und Ruhm gibt es, wenn die Platte fertig ist. Ohne Fehler wird sie perfekt.</p>
        <button class="btn primary" data-act="recordSong" data-id="${b.id}" ${blocked ? 'disabled' : ''}>Lied ${open.songsDone + 1} einspielen</button>
      </div>`;
  }
  if (b.record.blocker) {
    return `<p class="muted">${esc(b.record.blocker)}</p>`;
  }
  const form = app.ui.form;
  const titleKey = `recordTitle${b.id}`;
  const songsKey = `recordSongs${b.id}`;
  const songs = Number(form[songsKey] || rules.recordMinSongs);
  return `
    <form data-form="recordStart" data-id="${b.id}" class="stack">
      <p class="muted small">Eine Platte kostet pauschal ${money(rules.recordCost)}, egal wie viele Lieder. Mehr Lieder bringen mehr Geld und Ruhm – aber auch mehr Chancen auf Fehler.</p>
      <label class="field"><span>Titel</span>
        <span class="input-dice"><input name="title" maxlength="60" required data-ui="${titleKey}" value="${esc(form[titleKey] || '')}" placeholder="z. B. ${esc(b.name)} EP"><button type="button" class="btn small" data-act="diceRecord" data-key="${titleKey}" title="Zufälliger Titel">🎲</button></span>
      </label>
      <label class="field"><span>Lieder: <b data-output="${songsKey}">${songs}</b> <small class="muted">(${rules.recordMinSongs} = EP, bis ${rules.recordMaxSongs} = LP)</small></span>
        <input type="range" name="songs" min="${rules.recordMinSongs}" max="${rules.recordMaxSongs}" value="${songs}" data-ui="${songsKey}">
      </label>
      <button class="btn primary" ${app.state.label.money < rules.recordCost ? 'disabled' : ''}>Produktion starten · ${money(rules.recordCost)}</button>
    </form>`;
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
    toast('Du brauchst mindestens zwei Musiker ohne Band. Neue Musiker gibt es bei den Packs.', 'error');
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
        <p class="muted small">Musiker außerhalb ihres Genres dürfen mitspielen, bringen aber weniger. Höchstens 1 Schlagzeug und 2 Gitarren pro Band.</p>
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
    toast('Alle Musiker spielen schon in einer Band. Neue gibt es bei den Packs.', 'error');
    return;
  }
  const modal = openModal({
    wide: true,
    html: `
      <h2>Musiker für ${esc(band.name)}</h2>
      ${band.released ? '<p class="muted small">Ein neues Mitglied senkt die Eingespieltheit leicht und startet mit seiner eigenen Bekanntheit.</p>' : ''}
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

async function suggestName(kind) {
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
        `${fullName(m)} verlässt ${b.name}. Die Eingespieltheit sinkt um ${cat().rules.cohesionRemovePenalty} Punkte, und der Bandruhm sinkt um ${fmt1(m.fame)} – so viel Bekanntheit nimmt ${m.firstName} mit.`,
        { ok: 'Rausnehmen', danger: true },
      );
      if (!ok) return;
    }
    await mutate('bandRemoveMember', { bandId: b.id, musicianId: m.id });
  },
  async bandSetSlot(value, el) {
    await mutate('bandSetSlot', { bandId: Number(el.dataset.band), musicianId: Number(el.dataset.musician), slot: Number(value) });
  },
  async bandSetManager(value, el) {
    await mutate('bandSetManager', { bandId: Number(el.dataset.band), managerId: value ? Number(value) : null });
  },
  async bandRename({ id }) {
    const b = bandById(id);
    const name = await promptDialog('Neuer Bandname', b.name);
    if (name && name !== b.name) await mutate('bandRename', { bandId: b.id, name });
  },
  async bandDisband({ id }) {
    const b = bandById(id);
    const ok = await confirmDialog(`${b.name} auflösen? Die Musiker behalten ihre Bekanntheit und kommen zurück in den Katalog. Eine laufende Produktion geht verloren.`, { ok: 'Auflösen', danger: true });
    if (!ok) return;
    if (await mutate('bandDisband', { bandId: b.id })) navigate('#/bands');
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
  async recordStart(values, form) {
    const bandId = Number(form.dataset.id);
    const res = await mutate('recordStart', { bandId, title: values.title, songs: Number(values.songs) });
    if (res) {
      delete app.ui.form[`recordTitle${bandId}`];
      toast('Ab ins Studio! Spiel jetzt das erste Lied ein.', 'ok');
      rerender();
    }
  },
  recordSong({ id }) {
    startRecordSong(id);
  },
});
