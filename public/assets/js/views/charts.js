import { api } from '../api.js';
import { genreTag, musicianCard } from '../cards.js';
import { app, cat, defineActions, navigate, rerender } from '../core.js';
import { loadStems, playStems } from '../music.js';
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
          ? `Die Top ${rules.chartSize} der Platten aller Labels. Die Plätze werden alle ${fmt0(rules.chartIntervalHours)} Stunden neu vergeben – sofort, wenn eine Platte einsteigt oder Gigs und Promo sie anschieben. Eine Platte erreicht nach etwa ${fmt0(rules.chartPeakHours)} Stunden ihren Höhepunkt und fällt dann. Je höher der Platz, desto mehr Tantiemen und Ruhm (Platz 1 doppelt), und Gigs der Band werden voller. Platz 1 gibt einen Scout-Gutschein.`
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
      <thead><tr><th>#</th><th title="Platz in der letzten Chartstunde">Vorher</th><th>Platte</th><th>Band · Label</th><th title="Stunden in den Charts">Std.</th><th title="Bester Platz">Best</th><th>Punkte</th></tr></thead>
      <tbody>${list.map((e) => `
        <tr class="${e.own ? 'own' : ''}">
          <td class="rank">${e.rank}</td>
          <td>${trendBadge(e)}</td>
          <td><b>${esc(e.title)}</b> <span class="chip">${e.format}</span>${e.perfect ? ' <span class="chip special" title="Ohne einen einzigen Fehler">perfekt</span>' : ''}</td>
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
      <thead><tr><th>#</th><th>Label</th><th>Bands</th><th>Ruhm</th></tr></thead>
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
      <div class="tcg-grid">${band.members.map((m) => musicianCard(m, { act: null, showStatus: false })).join('')}</div>
      ${band.records.length ? `<h3>Platten</h3>
        <ul class="history">${band.records.map((r) => `
          <li><span>„${esc(r.title)}“ · ${r.songs} Lieder${r.perfect ? ' · perfekt' : ''}</span><span class="muted">${r.best ? `bester Platz ${r.best}` : ''}</span></li>`).join('')}
        </ul>` : ''}`,
  });
  const button = modal.body.querySelector('[data-listen]');
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
    button.textContent = '■ Stopp';
    timer = setTimeout(() => {
      stop();
      button.textContent = '▶ Anhören';
    }, LISTEN_SECONDS * 1000);
  });
}

defineActions({
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
