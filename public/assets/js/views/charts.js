import { api } from '../api.js';
import { app, cat, defineActions, rerender } from '../core.js';
import { esc, fmt0, fmt1, money, toast } from '../ui.js';

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
          ? `Die Top ${rules.chartSize} der Platten aller Labels, jede Stunde neu. Eine Platte steigt ein, erreicht nach etwa ${fmt0(rules.chartPeakHours)} Stunden ihren Höhepunkt und fällt dann. Gigs und Promo der Band schieben die neueste Platte nach oben (der Schub halbiert sich alle ${fmt0(rules.chartBoostHalfHours)} Stunden). Solange eine Platte chartet, laufen Tantiemen. Platz 1 gibt einen Scout-Gutschein.`
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

function albums(list) {
  if (!list.length) return '<div class="empty">Noch keine Platte in den Charts. Die erste fertige Platte steigt hier ein.</div>';
  return `
    <table class="charts">
      <thead><tr><th>#</th><th title="Platz in der letzten Chartstunde">Vorher</th><th>Platte</th><th>Band · Label</th><th title="Stunden in den Charts">Std.</th><th title="Bester Platz">Best</th><th>Punkte</th></tr></thead>
      <tbody>${list.map((e) => `
        <tr class="${e.own ? 'own' : ''}">
          <td class="rank">${e.rank}</td>
          <td>${trendBadge(e)}</td>
          <td><b>${esc(e.title)}</b> <span class="chip">${e.format}</span>${e.perfect ? ' <span class="chip special" title="Ohne einen einzigen Fehler">perfekt</span>' : ''}</td>
          <td>${esc(e.band)}<br><span class="muted small">${esc(e.label)}</span></td>
          <td>${fmt0(e.hours)}</td>
          <td>${fmt0(e.best)}</td>
          <td><b>${fmt1(e.points)}</b>${e.own ? `<br><span class="muted small">${money(e.perHour)} / Std.</span>` : ''}</td>
        </tr>`).join('')}
      </tbody>
    </table>`;
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

defineActions({
  chartsTab({ tab }) {
    app.ui.chartsTab = tab;
    rerender();
  },
  chartsReload() {
    return loadCharts();
  },
});
