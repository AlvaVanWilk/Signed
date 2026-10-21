import { api } from '../api.js';
import { genreTag, musicianCard } from '../cards.js';
import { app, cat, defineActions, navigate, rerender } from '../core.js';
import { stage } from '../figure.js';
import { coverHtml } from '../play.js';
import { loadStems, playStems, stemUrl } from '../music.js';
import { esc, fmt0, fmt1, fmtGain, openModal, toast } from '../ui.js';

let loading = false;

export async function loadCharts() {
  if (loading) return;
  loading = true;
  try {
    app.charts = await api('charts');
  } catch (e) {
    toast(e.message, 'error');
  } finally {
    loading = false;
  }
  rerender();
}

export function render() {
  const tab = app.ui.chartsTab === 'labels' ? 'labels' : 'albums';
  const charts = app.charts;
  const rules = cat().rules;

  return `
    <div class="section-head">
      <div>
        <h1>Charts</h1>
        <p class="muted">${tab === 'albums'
          ? `Die Top ${rules.chartSize} der Platten aller Labels. Die Plätze werden alle ${fmt0(rules.chartIntervalHours)} Stunden neu vergeben – sofort, wenn eine Platte einsteigt oder Gigs und Promo sie anschieben. Je höher der Platz, desto mehr Tantiemen und Ruhm (Platz 1 doppelt), und Gigs der Band werden voller. Platz 1 gibt einen Scout-Gutschein.`
          : 'Die erfolgreichsten Labels nach dem Ruhm all ihrer Bands.'}</p>
      </div>
      <button class="btn ghost small" data-act="chartsReload">Aktualisieren</button>
    </div>
    <div class="tabs">
      <button class="tab ${tab === 'albums' ? 'active' : ''}" data-act="chartsTab" data-tab="albums">Album-Charts</button>
      <button class="tab ${tab === 'labels' ? 'active' : ''}" data-act="chartsTab" data-tab="labels">Labels</button>
    </div>
    ${!charts ? '<div class="empty">Charts werden geladen …</div>' : tab === 'albums' ? albums(charts.albums) : labels(charts.labels)}`;
}

function albums(all) {
  const list = all.filter((e) => e.placed);
  const outside = all.filter((e) => !e.placed);
  if (!list.length) return '<div class="empty">Noch keine Platte in den Charts. Die erste fertige Platte steigt hier ein.</div>';
  return `
    <table class="charts">
      <thead><tr>${['rank', 'prev', 'record', 'band', 'hours', 'best', 'points'].map(headCell).join('')}</tr></thead>
      <tbody>${list.map((e) => `
        <tr class="${e.own ? 'own' : ''}">
          <td class="rank">${e.rank}</td>
          <td>${trendBadge(e)}</td>
          <td><span class="chart-record"><span class="cover-thumb">${coverHtml(e.cover)}</span><span><b>${esc(e.title)}</b> <span class="chip">${e.format}</span>${e.perfect ? ' <span class="chip special" title="Ohne einen einzigen Fehler">perfekt</span>' : ''}</span></span></td>
          <td>${e.bandId ? `<button class="link" data-act="viewBand" data-id="${e.bandId}">${esc(e.band)}</button>` : esc(e.band)}<br><span class="muted small">${esc(e.label)}</span></td>
          <td>${fmt0(e.hours)}</td>
          <td>${fmt0(e.best)}</td>
          <td><b>${fmt1(e.points)}</b>${e.own ? `<br><span class="muted small">${fmtGain(e.perHour / 60)} ₲ / Min.</span>` : ''}</td>
        </tr>`).join('')}
      </tbody>
    </table>
    ${outside.length ? `<h3>Deine Platten außerhalb der Top ${cat().rules.chartSize}</h3>
      <ul class="history">${outside.map((e) => `<li><span>„${esc(e.title)}“ · ${esc(e.band)}</span><span class="muted">Platz ${e.rank} · ${fmt1(e.points)} Punkte</span></li>`).join('')}</ul>` : ''}`;
}

/** Spaltenköpfe der Charts: antippen erklärt, was in der Spalte steht. */
const HEADS = {
  rank: ['#', 'Platz', (r) => `<p>Der Platz in den Top ${r.chartSize}.</p>
    <ul><li>Je höher der Platz, desto mehr <b>Tantiemen</b> (Geld) und <b>Ruhm</b> pro Stunde – Platz 1 bekommt doppelt so viel wie ganz unten.</li>
    <li>Gigs der Band werden voller: bis <b>+${Math.round(r.chartGigBonus * 100)} %</b> auf Platz 1.</li>
    <li>Wer zum ersten Mal Platz 1 erreicht, bekommt einen <b>Scout-Gutschein</b> – wenn mindestens ${r.chartTopMinLabels} Labels in den Charts sind.</li></ul>`],
  prev: ['Vorher', 'Vorher', (r) => `<p>Der Platz zu Beginn der Chartwoche.</p>
    <ul><li>Die Plätze werden alle <b>${fmt0(r.chartIntervalHours)} Stunden</b> neu vergeben – sofort, wenn eine Platte einsteigt oder Gigs und Promo sie anschieben.</li>
    <li><span class="trend up">▲</span> aufgestiegen, <span class="trend down">▼</span> abgestiegen, <span class="trend new">NEU</span> gerade eingestiegen.</li></ul>`],
  record: ['Platte', 'Platte', (r) => `<ul><li><b>EP</b>: bis ${r.chartLpSongs - 1} Lieder, <b>LP</b>: ab ${r.chartLpSongs} Liedern – LPs bekommen mehr Punkte.</li>
    <li><b>perfekt</b>: ohne einen einzigen Fehler aufgenommen – Punkte × ${fmt1(r.chartPerfect)}.</li>
    <li>Das Bild ist das Cover aus dem Cover-Automaten.</li></ul>`],
  band: ['Band · Label', 'Band und Label', (r) => `<ul><li>Von jeder Band steht nur ihre <b>stärkste Platte</b> in den Charts, von jedem Label höchstens <b>${r.chartPerLabel}</b>.</li>
    <li>Tippe auf eine Band, um sie anzuschauen und anzuhören.</li></ul>`],
  hours: ['Std.', 'Std. – Stunden', (r) => `<p>Wie viele Stunden die Platte schon draußen ist.</p>
    <ul><li>Die Punkte steigen bis etwa <b>${fmt0(r.chartPeakHours)} Stunden</b> nach dem Erscheinen, danach sinken sie langsam.</li>
    <li>Nach <b>${fmt0(r.chartMaxAgeDays)} Tagen</b> ist eine Platte raus aus den Charts – verkauft sich aber für immer ein wenig weiter.</li></ul>`],
  best: ['Best', 'Best', () => '<p>Der beste Platz, den die Platte je erreicht hat.</p>'],
  points: ['Punkte', 'Punkte', (r) => `<p>Danach richtet sich der Platz.</p>
    <h3>Woraus sie kommen</h3>
    <ul><li><b>Qualität</b> der Aufnahmen im Studio – zählt am meisten.</li>
    <li><b>Sound</b> der Band und <b>Länge</b> (LP mehr als EP), <b>perfekt</b> × ${fmt1(r.chartPerfect)}.</li>
    <li><b>Ruhm</b> der Band: bis × ${fmt0(1 + r.chartFameMax)}.</li></ul>
    <h3>Wie sie sich ändern</h3>
    <ul><li>Erst steigen sie, nach etwa ${fmt0(r.chartPeakHours)} Stunden sinken sie.</li>
    <li>Gigs und Promo geben <b>Schub</b>: bis +${Math.round(r.chartBoostMax * 100)} %, er halbiert sich alle ${fmt0(r.chartBoostHalfHours)} Stunden.</li>
    <li>Bei deinen Platten steht darunter, wie viel <b>Geld pro Minute</b> sie gerade bringen.</li></ul>`],
  labelRank: ['#', 'Platz', () => '<p>Der Platz unter allen Labels – nach dem Ruhm all ihrer Bands.</p>'],
  label: ['Label', 'Label', () => '<p>Das Plattenlabel, also der Spieler.</p>'],
  bands: ['Bands', 'Bands', () => '<p>Wie viele Bands das Label gerade hat.</p>'],
  labelFame: ['Ruhm', 'Ruhm', () => `<p>Der Ruhm aller Bands des Labels zusammen.</p>
    <ul><li>Mehr Label-Ruhm schaltet <b>weitere Bands</b> frei.</li></ul>`],
};

function headCell(key) {
  return `<th><button class="th-info" data-act="chartHeadInfo" data-key="${key}">${HEADS[key][0]}</button></th>`;
}

/** Auf- oder Abstieg seit der letzten Chartstunde, wie in echten Charts. */
export function trendBadge(e) {
  if (e.trend === 'new') return '<span class="trend new">NEU</span>';
  if (e.trend === 'up') return `<span class="trend up" title="vorher Platz ${e.prev}">▲ ${e.prev}</span>`;
  if (e.trend === 'down') return `<span class="trend down" title="vorher Platz ${e.prev}">▼ ${e.prev}</span>`;
  return `<span class="trend same" title="vorher Platz ${e.prev}">– ${e.prev ?? ''}</span>`;
}

function labels(list) {
  if (!list.length) return '<div class="empty">Noch kein Label mit Band.</div>';
  return `
    <table class="charts">
      <thead><tr>${['labelRank', 'label', 'bands', 'labelFame'].map(headCell).join('')}</tr></thead>
      <tbody>${list.map((e) => `
        <tr class="${e.own ? 'own' : ''}">
          <td class="rank">${e.rank}</td>
          <td>${esc(e.label)}</td>
          <td>${fmt0(e.bands)}</td>
          <td><b>${fmt1(e.points)}</b></td>
        </tr>`).join('')}
      </tbody>
    </table>`;
}

/** So lange läuft „Anhören“: ein Durchgang der Aufnahmen. */
const LISTEN_SECONDS = 24;

/** Eine Band ansehen – auch die anderer Labels – und reinhören, wie sie klingt. */
async function openBandView(id) {
  let band;
  try {
    band = await api('bandView', { bandId: Number(id) });
  } catch (e) {
    toast(e.message, 'error');
    return;
  }
  let player = null;
  let timer = 0;
  const stop = () => {
    clearTimeout(timer);
    player?.stop();
    player = null;
    modal?.body.querySelector('.fig-stage')?.classList.add('idle');
  };
  const modal = openModal({
    wide: true,
    onClose: stop,
    html: `
      <div class="eyebrow">${esc(band.label)}</div>
      <h2>${esc(band.name)}</h2>
      <p class="muted">${[...band.genres.map(genreTag), band.title ? `<span class="chip special">${esc(band.title)}</span>` : '', `Ruhm <b>${fmt1(band.fame)}</b>`].filter(Boolean).join(' ')}</p>
      <div class="dialog-actions" style="justify-content:flex-start;margin-bottom:14px">
        <button class="btn primary" data-listen>▶ Anhören</button>
        ${band.own ? `<button class="btn" data-goto>Zur Band</button>` : ''}
      </div>
      ${stage(band.members, { bpm: cat().genres[band.genres[0] ?? band.members[0]?.genres[0]]?.bpm ?? 120, className: 'idle', electricOf: (m) => /elektrisch/.test(stemUrl(m.stem) ?? '') })}
      <div class="tcg-grid">${band.members.map((m) => musicianCard(m, { act: null, showStatus: false })).join('')}</div>
      ${band.records.length ? `<h3>Platten</h3>
        <ul class="history">${band.records.map((r) => `
          <li><span class="chart-record"><span class="cover-thumb">${coverHtml(r.cover)}</span>„${esc(r.title)}“ · ${r.songs} Lieder${r.perfect ? ' · perfekt' : ''}</span><span class="muted">${r.best ? `bester Platz ${r.best}` : ''}</span></li>`).join('')}
        </ul>` : ''}`,
  });
  const button = modal.body.querySelector('[data-listen]');
  const stageEl = modal.body.querySelector('.fig-stage');
  modal.body.querySelector('[data-goto]')?.addEventListener('click', () => {
    modal.close();
    navigate(`#/band/${band.id}`);
  });
  button.addEventListener('click', async () => {
    if (player) {
      stop();
      button.textContent = '▶ Anhören';
      return;
    }
    button.disabled = true;
    button.textContent = 'Lädt …';
    const stems = await loadStems(band.members.map((m) => [m.stem]));
    button.disabled = false;
    if (!stems || modal.closed) {
      button.textContent = stems ? '▶ Anhören' : 'Noch keine Aufnahme';
      button.disabled = !stems;
      return;
    }
    player = playStems(stems, 0);
    stageEl.classList.remove('idle');
    button.textContent = '■ Stopp';
    timer = setTimeout(() => {
      stop();
      button.textContent = '▶ Anhören';
    }, LISTEN_SECONDS * 1000);
  });
}

defineActions({
  chartHeadInfo({ key }) {
    const [, title, text] = HEADS[key];
    const modal = openModal({ html: `<div class="eyebrow">Charts</div><h2>${title}</h2>${text(cat().rules)}
      <div class="dialog-actions"><button class="btn primary" data-close>OK</button></div>` });
    modal.body.querySelector('[data-close]').addEventListener('click', () => modal.close());
  },
  viewBand({ id }) {
    return openBandView(id);
  },
  chartsTab({ tab }) {
    app.ui.chartsTab = tab;
    rerender();
  },
  chartsReload() {
    return loadCharts();
  },
});
