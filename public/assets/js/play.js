import { instrumentName, levelUpBanners } from './cards.js';
import { bandById, cat, mutate } from './core.js';
import { createMinigame } from './minigame.js';
import { bar, esc, fmt0, fmt1, fmt2, fmtGain, money, openModal, percent } from './ui.js';

/** Solo-Probe: ein Musiker übt sein Instrument. */
export function startPractice(musicianId, slot = 1) {
  return runMinigame({ kind: 'practice', musicianId: Number(musicianId), slot: Number(slot) });
}

/** Studio: das nächste Lied der offenen Platte einspielen. */
export function startRecordSong(bandId) {
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

  // Der Server erwartet, dass das Lied wirklich gespielt wurde.
  const fin = await mutate('minigameFinish', { sessionId: game.sessionId, ...outcome });
  if (!fin) {
    mg.showResult('<p class="muted">Das Ergebnis konnte nicht gespeichert werden.</p>', [{ label: 'Schließen', onClick: close }]);
    return;
  }
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
    ...(more ? [{ label: 'Nächstes Lied', cls: 'primary', onClick: () => { close(); startRecordSong(request.bandId); } }] : []),
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
const fmtSkill = (value, gain) => (gain < 0.1 ? fmt2(value) : fmt1(value));

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
    <h3>Lied ${r.songsDone} von ${r.songsTotal} im Kasten – Trefferquote ${percent(r.accuracy)}</h3>
    ${counts(r)}
    <p class="muted">Zusammenspiel +${fmt1(r.cohesion)}</p>
    ${levelUpBanners(r.levelUps)}`;
  if (!r.completed) return head;
  const c = r.completed;
  return `${head}
    <div class="release ${c.perfect ? 'perfect' : ''}">
      <div class="release-title">„${esc(c.title)}“ ist fertig!</div>
      <div class="muted">${c.format} · ${c.songs} Lieder · Qualität ${percent(c.quality)}${c.perfect ? ' · <b>perfekte Platte!</b>' : ` · ${c.misses} Fehler`}</div>
      <div class="reward-grid">
        <div class="reward"><span>Verkäufe</span><b>+${money(c.money)}</b></div>
        <div class="reward"><span>Ruhm</span><b>+${fmtGain(c.fame)}</b></div>
      </div>
      <p>Neu in den Album-Charts mit <b>${fmt1(c.chartPoints)} Punkten</b>.${c.vouchers ? ` Dazu ${c.vouchers === 1 ? 'ein Scout-Gutschein' : `${c.vouchers} Scout-Gutscheine`}!` : ''}</p>
    </div>`;
}
