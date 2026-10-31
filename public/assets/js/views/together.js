import { api } from '../api.js';
import { face, fullName, genreTag, instrumentIcon, instrumentName } from '../cards.js';
import { app, bandById, cat, defineActions, musicianById, mutate, navigate } from '../core.js';
import { esc, fmt0, fmt1, openModal, toast } from '../ui.js';
import { coreOn } from '../core-ui.js';
import { suggestName } from './bands.js';
import { getAudio } from '../audio.js';
import { bandStems, loadStems, playStems } from '../music.js';

/**
 * „Mit Musiker(n) zusammentun“: Wer passt zu dieser Band? Der Server ordnet die Möglichkeiten nach Nutzen:
 * zuerst echte Vorteile (Genre-Bonus, neues Instrument), dann gute Passung der Charaktere, dann der Rest, Unmögliches zuletzt.
 * Hier kommen nur Suche, Gliederung und Anzeige dazu.
 */

const SECTIONS = [
  ['better', 'Verbessert den Sound oder das Verständnis', ''],
  ['neutral', 'Neutral', ''],
  ['worse', 'Verschlechtert', ''],
  ['blocked', 'Gerade nicht möglich', ''],
];

const sectionOf = (c) => (c.blocked ? 'blocked' : c.rating);

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
      <div class="tg-go">${slot}${c.blocked ? '' : `<button class="btn small ghost" data-hear="${c.bandId}" title="So würdet ihr zusammen klingen">▶ Reinhören</button>
        <button class="btn small primary" data-join="${c.bandId}" data-kind="${c.kind}" data-musician="${c.musicianId ?? ''}">Zusammentun</button>`}</div>
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

/** only: nur dieser eine Kandidat (beim Aufeinanderschieben in der Künstlerleiste) – dasselbe Fenster als Kontrollfrage. */
async function open(bandId, only = null) {
  const band = bandById(bandId);
  let candidates;
  try {
    candidates = (await api('bandJoinCandidates', { bandId })).candidates;
    if (only !== null) candidates = candidates.filter((c) => c.bandId === only);
  } catch (e) {
    toast(e.message, 'error');
    return;
  }
  const solo = band.memberIds.length === 1;
  // Hörprobe: So würdet ihr zusammen klingen (eine zur Zeit, ein Durchgang; endet mit dem Fenster)
  let hearing = null;
  const stopHearing = () => {
    if (!hearing) return;
    clearTimeout(hearing.timer);
    hearing.player?.stop();
    hearing.btn.textContent = '▶ Reinhören';
    hearing = null;
  };
  const hear = async (cand, slot, btn) => {
    stopHearing();
    const mine = { btn, player: null, timer: 0 };
    hearing = mine;
    btn.textContent = '■ Stopp';
    // Alle zusammen, der Neue mit dem gewählten Instrument; mögen alle ein Genre, spielen alle darin, sonst jeder seins
    const theirs = cand.memberIds.map(musicianById).filter(Boolean).map((m) => (cand.kind === 'musician' ? { ...m, bandSlot: slot } : m));
    const all = [...band.memberIds.map(musicianById).filter(Boolean), ...theirs];
    const shared = all.reduce((acc, m) => acc.filter((g) => m.genres.includes(g)), all[0]?.genres ?? []);
    const buffers = await loadStems(bandStems(all, shared.length ? { genres: shared.slice(0, 2) } : null, bandId).map((x) => [x]));
    const ctx = getAudio();
    if (hearing !== mine) return;
    if (!buffers || !ctx) {
      stopHearing();
      toast('Dafür gibt es noch keine Aufnahme.', 'error');
      return;
    }
    mine.player = playStems(buffers, ctx.currentTime + 0.05, { volume: 0.85 });
    mine.timer = setTimeout(() => {
      if (hearing === mine) stopHearing();
    }, (cat().rules.songSeconds ?? 24) * 1000);
  };
  const modal = openModal({
    wide: true,
    className: 'tg-modal',
    onClose: stopHearing,
    html: `
      <h2>${only !== null && candidates[0] ? `${esc(band.name)} und ${esc(candidates[0].name)} zusammentun?` : solo ? `${esc(band.name)} mit anderen zusammentun` : `${esc(band.name)}: mit Musiker(n) zusammentun`}</h2>
      ${only !== null ? '<p class="muted">So würde sich das Zusammentun auswirken. Erst mit „Zusammentun“ und der Namenswahl passiert es wirklich.</p>' : '<input class="tg-search" type="search" placeholder="Name suchen" autocomplete="off" aria-label="Name suchen">'}
      <div class="tg-list">${list(candidates)}</div>
      <p class="tg-none muted" hidden>Niemand mit diesem Namen.</p>`,
  });
  const search = modal.body.querySelector('.tg-search');
  search?.addEventListener('input', () => {
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
    const listen = e.target.closest('[data-hear]');
    if (listen) {
      getAudio();
      if (hearing?.btn === listen) stopHearing();
      else hear(candidates.find((c) => c.bandId === Number(listen.dataset.hear)), Number(listen.closest('.tg-row').querySelector('[data-slot]')?.value || 1), listen);
      return;
    }
    const btn = e.target.closest('[data-join]');
    if (!btn) return;
    stopHearing();
    const cand = candidates.find((c) => c.bandId === Number(btn.dataset.join));
    const slot = Number(btn.closest('.tg-row').querySelector('[data-slot]')?.value || 1);
    const choice = await chooseName(band, cand);
    if (!choice) return;
    const res = await mutate('bandMerge', { bandId, otherId: cand.bandId, keepName: choice.keepName, newName: choice.newName, slot });
    if (res) {
      modal.close();
      // Besteht die andere Zeile weiter (ihr Name bleibt), geht es dorthin.
      if (res.bandId && res.bandId !== bandId) navigate(`#/band/${res.bandId}`);
    }
  });
}

/**
 * Welcher Name macht weiter? 'a' (diese Band), 'b' (die andere) oder ein neuer Name. Ein Einzelmusiker hat keinen Namen, der bleiben könnte.
 * Wer den Namen aufgibt, verliert den Ruhm daran; die Platten bleiben bei der ehemaligen Band. Der Ruhm danach lässt sich ausrechnen:
 * Bekanntheit aller Mitglieder plus Ruhm des Namens, der bleibt.
 */
function chooseName(a, cand) {
  const b = bandById(cand.bandId);
  const fame = (id) => musicianById(id)?.fame ?? 0;
  const members = [...a.memberIds, ...b.memberIds].reduce((sum, id) => sum + fame(id), 0);
  const hasValue = (x) => x.records > 0 || x.released || x.fameOwn > 0;
  // Neuer Kern: die größere Band plus die Hälfte der kleineren; ein neuer Name kostet einen Teil der Fans (wie Bands::merge)
  const core = coreOn() && a.core && b.core;
  const moves = cat().rules.coreFanMoves ?? { mergeSmall: 0.5, rename: 0.1 };
  const merged = core ? Math.max(a.core.fans, b.core.fans) + moves.mergeSmall * Math.min(a.core.fans, b.core.fans) : 0;
  const options = cand.nameOptions.map((key) => {
    const keptOwn = key === 'a' ? a.fameOwn : key === 'b' ? b.fameOwn : key === 'combo' ? cat().rules.nameComboKeep * (a.fameOwn + b.fameOwn) : 0;
    const gives = [key !== 'a' ? a : null, key !== 'b' ? b : null].filter((x) => x && hasValue(x));
    if (core) return { key, after: key === 'a' || key === 'b' ? merged : merged * (1 - moves.rename), gives };
    return { key, after: members + keptOwn, gives };
  });
  const before = core ? a.core.fans + b.core.fans : a.fame + b.fame;
  const best = options.reduce((m, o) => (o.after > m.after ? o : m), options[0]);
  return new Promise((resolve) => {
    const label = (o) => (o.key === 'a' ? `Weiter als „${esc(a.name)}“` : o.key === 'b' ? `Weiter als „${esc(b.name)}“` : o.key === 'combo' ? 'Beide Namen verbinden' : 'Ganz neuer Name');
    // Solokünstler zuerst („Linus Heller & The Foxes“); bei „and the“ und „und die“ entfällt ein Artikel vor dem Bandnamen.
    const [first, second] = a.memberIds.length === 1 || b.memberIds.length > 1 ? [a, b] : [b, a];
    const bare = (n) => n.replace(/^(the|die|der|das)\s+/i, '');
    const joined = (glue) => `${first.name}${glue}${glue.trim() === '&' ? second.name : bare(second.name)}`;
    const modal = openModal({
      className: 'lb-ask tg-name',
      html: `
        <div class="lb-cap">Zusammentun</div>
        <h2>${esc(a.name)} und ${esc(b.name)}</h2>
        <p class="muted">${core
    ? 'Welcher Name macht weiter? Die Band behält die Fans der größeren Band und die Hälfte der Fans der kleineren, denn viele sind schon Fans von beiden. Ein neuer Name kostet ein Zehntel der Fans. Wer seinen Namen aufgibt, behält seine Platten: Sie verkaufen sich weiter und stehen in der Chronik.'
    : 'Welcher Name macht weiter? Wer seinen Namen aufgibt, verliert den Ruhm daran. Seine Platten bleiben bei der ehemaligen Band (Tantiemen laufen weiter) und stehen in der Chronik.'}</p>
        <div class="tg-opts">${options.map((o) => `
          <label class="tg-opt ${o === best ? 'best' : ''}">
            <input type="radio" name="nm" value="${o.key}" ${o === best ? 'checked' : ''}>
            <span><b>${label(o)}</b>
              ${o.key === 'new' ? '<input type="text" class="tg-newname" maxlength="40" placeholder="Neuer Bandname"> <button type="button" class="btn small ghost" data-dice title="Namen vorschlagen">Würfeln</button>' : ''}
              ${o.key === 'combo' ? `<span class="tg-combos"><input type="text" class="tg-combo" maxlength="40" value="${esc(joined(' & '))}">
                ${[[' & ', '&'], [' and the ', 'and the'], [' und die ', 'und die']].map(([glue, text]) => `<button type="button" class="btn small ghost" data-glue="${esc(glue)}">${text}</button>`).join('')}</span>` : ''}
              ${core
    ? `<small>Fans danach <b>${fmt0(o.after)}</b> <span class="muted">(jetzt zusammen ${fmt0(before)})</span></small>`
    : `<small>Ruhm danach <b>${fmt1(o.after)}</b> <span class="${o.after >= before ? 'good' : 'bad'}">(jetzt zusammen ${fmt1(before)})</span></small>`}
              ${o.gives.map((g) => `<small class="muted">„${esc(g.name)}“ ist dann ehemalig${!core && g.fameOwn > 0 ? `, ${fmt1(g.fameOwn)} Ruhm am Namen weg` : ''}${g.records > 0 ? `, ${g.records} ${g.records === 1 ? 'Platte bleibt' : 'Platten bleiben'} dort` : ''}.</small>`).join('')}
            </span>
          </label>`).join('')}
        </div>
        <div class="dialog-actions"><button class="btn ghost" data-cancel>Abbrechen</button><button class="btn primary" data-go>Zusammentun</button></div>`,
    });
    const field = modal.body.querySelector('.tg-newname');
    const combo = modal.body.querySelector('.tg-combo');
    field?.addEventListener('focus', () => { modal.body.querySelector('input[value="new"]').checked = true; });
    combo?.addEventListener('focus', () => { modal.body.querySelector('input[value="combo"]').checked = true; });
    modal.body.querySelectorAll('[data-glue]').forEach((btn) => btn.addEventListener('click', () => {
      combo.value = joined(btn.dataset.glue);
      modal.body.querySelector('input[value="combo"]').checked = true;
    }));
    modal.body.querySelector('[data-dice]')?.addEventListener('click', async () => {
      const name = await suggestName('band');
      if (name) field.value = name;
    });
    modal.body.querySelector('[data-cancel]').addEventListener('click', () => { modal.close(); resolve(null); });
    modal.body.querySelector('[data-go]').addEventListener('click', () => {
      const keepName = modal.body.querySelector('input[name="nm"]:checked').value;
      const newName = (keepName === 'combo' ? combo?.value : field?.value)?.trim() ?? '';
      if ((keepName === 'new' || keepName === 'combo') && !newName) {
        toast('Bitte einen neuen Namen eintragen.', 'error');
        return;
      }
      modal.close();
      resolve({ keepName, newName });
    });
  });
}

defineActions({
  together({ id }) {
    open(Number(id));
  },
  /** In der Künstlerleiste aufeinandergeschoben: dasselbe Fenster, nur mit dem einen Paar. */
  togetherWith({ id, other }) {
    open(Number(id), Number(other));
  },
});
