import { instrumentName, levelUpBanners } from './cards.js';
import { bandById, cat, mutate } from './core.js';
import { createMinigame } from './minigame.js';
import { createCoverMachine } from './cover-machine.js';
import { coverSvg } from './cover.js';
import { bar, esc, fmt0, fmt1, fmt2, fmtGain, money, openModal, percent } from './ui.js';

/** Solo-Probe: ein Musiker übt sein Instrument. */
export function startPractice(musicianId, slot = 1) {
  return runMinigame({ kind: 'practice', musicianId: Number(musicianId), slot: Number(slot) });
}

/**
 * Studio, letzter Schritt: der Cover-Automat. Jedes angehaltene Band speichert der Server sofort;
 * nach dem letzten ist die Platte fertig und das Ergebnis erscheint mit dem Cover.
 */
export async function startCover(bandId) {
  const res = await mutate('coverStart', { bandId: Number(bandId) });
  if (!res) return;
  const cover = res.cover;
  const band = bandById(Number(bandId));
  const total = band?.record.open?.songsTotal;
  const modal = openModal({ wide: true, dismissable: false, className: 'cover-modal', html: `
    <div class="eyebrow">${esc(band?.name ?? '')} · Aufnahme ${total}/${total}</div>
    <h2>Cover-Automat: „${esc(cover.title)}“</h2>
    <p class="muted small">Tippe, wenn das Feld im weißen Rahmen ist, das du willst. <b style="color:#3ddc97">Grün</b>, <b style="color:#4f8bff">blau</b> und
      <b style="color:#ffc23d">gold</b> umrandete Felder machen das Cover wertvoller – es verkauft sich dann besser.</p>
    <div class="cover-machine"></div>` });
  let completed = null;
  const machine = createCoverMachine(modal.body.querySelector('.cover-machine'), {
    reels: cover.reels,
    speeds: cover.speeds,
    picks: cover.picks,
    onPick: async (reel, index) => {
      const r = await mutate('coverPick', { bandId: Number(bandId), reel, index });
      if (r?.completed) completed = r.completed;
      return !!r;
    },
    onDone: () => {
      machine.destroy();
      modal.body.innerHTML = completed ? `${releaseHtml(completed, band)}<div class="dialog-actions"><button class="btn primary" data-close>Super!</button></div>`
        : '<p>Fertig!</p><div class="dialog-actions"><button class="btn primary" data-close>OK</button></div>';
      modal.body.querySelector('[data-close]').addEventListener('click', () => modal.close());
    },
  });
}

/** Studio: das nächste Lied der offenen Platte einspielen. */
export function startRecordSong(bandId) {
  // Die letzte Aufnahme ist der Cover-Automat.
  if (bandById(Number(bandId))?.record.open?.coverNext) return startCover(bandId);
  return runMinigame({ kind: 'record', bandId: Number(bandId) });
}

async function runMinigame(request) {
  const res = await mutate('minigameStart', request);
  if (!res) return;
  const game = res.game;
  const modal = openModal({ wide: true, dismissable: false, className: 'mg-modal' });
  const mg = createMinigame(modal.body, game);
  const close = () => {
    mg.destroy();
    modal.close();
  };

  const outcome = await mg.play();
  if (outcome.aborted) {
    close();
    await mutate('minigameAbort', { sessionId: game.sessionId });
    return;
  }

  // Der Server erwartet, dass das Lied wirklich gespielt wurde. Klappt das Senden nicht (Verbindung weg), kann man es
  // nochmal versuchen, statt das Ergebnis zu verlieren.
  const fin = await new Promise((resolve) => {
    let sending = false;
    const send = async () => {
      if (sending) return;
      sending = true;
      const res = await mutate('minigameFinish', { sessionId: game.sessionId, ...outcome });
      sending = false;
      if (res) return resolve(res);
      mg.showResult('<p class="muted">Das Ergebnis konnte nicht gespeichert werden. Ist das WLAN da?</p>', [
        { label: 'Schließen', onClick: () => { close(); resolve(null); } },
        { label: 'Nochmal senden', cls: 'primary', onClick: send },
      ]);
    };
    send();
  });
  if (!fin) return;
  const result = fin.result;
  if (result.kind === 'practice') {
    mg.showResult(practiceSummary(result), [
      { label: 'Schließen', onClick: close },
      { label: 'Nochmal proben', cls: 'primary', onClick: () => { close(); startPractice(request.musicianId, request.slot); } },
    ]);
    return;
  }
  const band = bandById(request.bandId);
  const more = !result.completed && band?.record.open;
  mg.showResult(recordSummary(result), [
    { label: 'Schließen', onClick: close },
    ...(more ? [{ label: 'Nächste Aufnahme', cls: 'primary', onClick: () => { close(); startRecordSong(request.bandId); } }] : []),
  ]);
}

function counts(r) {
  return `<div class="counts"><span class="ok">Perfekt ${r.perfect}</span><span class="good">Gut ${r.good}</span><span class="bad">Daneben ${r.miss}</span></div>`;
}

function practiceSummary(r) {
  return `
    <h3>Trefferquote ${percent(r.accuracy)}</h3>
    ${counts(r)}
    <p class="big-number">${esc(instrumentName(r.instrument))}: ${fmtSkill(r.skillBefore, r.gain)} → <b>${fmtSkill(r.skillAfter, r.gain)}</b> <span class="ok">+${fmtSkill(r.gain, r.gain)}</span></p>
    ${levelProgress(r.skillAfter)}
    ${levelUpBanners(r.levelUps)}`;
}

/** Kleine Zuwächse mit zwei Nachkommastellen, damit nicht „+0,0“ dasteht. */
/** Abgerundet: 34,96 zeigt 34,9 – nicht 35,0, solange die Stufe noch nicht erreicht ist. */
const fmtSkill = (value, gain) => (gain < 0.1 ? fmt2(Math.floor(value * 100 + 1e-6) / 100) : fmt1(Math.floor(value * 10 + 1e-6) / 10));

/** Wie weit es bis zur nächsten Stufe ist – so sieht man auch kleine Schritte. */
function levelProgress(skill) {
  const levels = cat().rules.skillLevels;
  const level = levels.filter((l) => skill >= l).length;
  if (level >= levels.length) return '';
  const from = [0, ...levels][level];
  const share = (skill - from) / (levels[level] - from);
  return `
    <p class="small muted">Weg zu „${esc(cat().levelNames[level + 1])}“ (ab ${fmt0(levels[level])}): <b>${percent(share)}</b>${level >= 1 ? ' · ab hier geht es langsamer voran' : ''}</p>
    ${bar(share, 1, 'thin')}`;
}

function recordSummary(r) {
  const head = `
    <h3>Aufnahme ${r.songsDone}/${r.songsTotal} im Kasten – Trefferquote ${percent(r.accuracy)}</h3>
    ${counts(r)}
    <p class="muted">Zusammenspiel +${fmt1(r.cohesion)}</p>
    ${levelUpBanners(r.levelUps)}`;
  return head;
}

/** Rahmenfarbe eines Covers: grün, blau oder gold je nach Wertigkeit. */
export function coverTier(spec) {
  if (!spec) return 0;
  return spec.rarity.includes(3) ? 3 : spec.quality >= 0.3 ? 2 : spec.quality > 0 ? 1 : 0;
}

/** Cover mit Rahmen (Diskografie, Charts, Ergebnis). */
export function coverHtml(spec, { band = '', title = '' } = {}) {
  if (!spec) return '<div class="cover-frame cover-none"><span>💿</span></div>';
  return `<div class="cover-frame q${coverTier(spec)}">${coverSvg(spec, { band, title })}</div>`;
}

/** Die fertige Platte, mit Cover. */
function releaseHtml(c, band) {
  return `
    <div class="release ${c.perfect ? 'perfect' : ''}">
      <div class="release-title">„${esc(c.title)}“ ist fertig!</div>
      ${c.cover ? `<div class="release-cover">${coverHtml(c.cover, { band: band?.name ?? '', title: c.title })}</div>` : ''}
      <div class="muted">${c.format} · ${c.songs} Lieder · Qualität ${percent(c.quality)}${c.perfect ? ' · <b>perfekte Platte!</b>' : ` · ${c.misses} Fehler`}</div>
      <div class="reward-grid">
        <div class="reward"><span>Verkäufe</span><b>+${money(c.money)}</b></div>
        <div class="reward"><span>Ruhm</span><b>+${fmtGain(c.fame)}</b></div>
        ${c.cover ? `<div class="reward"><span>Cover</span><b>${percent(c.cover.quality)}</b><small>Verkauf +${percent(c.coverBonus)}</small></div>` : ''}
      </div>
      <p>Neu in den Album-Charts mit <b>${fmt1(c.chartPoints)} Punkten</b>.${c.vouchers ? ` Dazu ${c.vouchers === 1 ? 'ein Scout-Gutschein' : `${c.vouchers} Scout-Gutscheine`}!` : ''}</p>
    </div>`;
}
