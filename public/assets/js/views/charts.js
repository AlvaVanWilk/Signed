import { api } from '../api.js';
import { app, cat, defineActions, rerender } from '../core.js';
import { esc, fmt0, fmt1, toast } from '../ui.js';

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
  const tab = app.ui.chartsTab;
  const genres = cat().genres;
  const tabs = [['labels', 'Labels'], ...Object.entries(genres).map(([k, g]) => [k, g.name])];
  const charts = app.charts;
  const list = charts ? (tab === 'labels' ? charts.labels : charts.genres[tab] || []) : null;

  return `
    <div class="section-head">
      <div><h1>Charts</h1><p class="muted">Gemessen wird Ruhm – über alle Labels. Pro Genre hat jedes Label höchstens eine Band.</p></div>
      <button class="btn ghost small" data-act="chartsReload">Aktualisieren</button>
    </div>
    <div class="tabs scroll">${tabs.map(([key, name]) => `<button class="tab ${key === tab ? 'active' : ''}" data-act="chartsTab" data-tab="${key}">${esc(name)}</button>`).join('')}</div>
    ${list === null ? '<div class="empty">Charts werden geladen …</div>' : list.length ? table(list, tab === 'labels') : '<div class="empty">In diesem Genre gibt es noch keine Band.</div>'}`;
}

function table(list, labels) {
  return `
    <table class="charts">
      <thead><tr><th>#</th>${labels ? '<th>Label</th><th>Bands</th>' : '<th>Band</th><th>Label</th><th title="Kritikerwertung">Sound</th>'}<th>Ruhm</th></tr></thead>
      <tbody>${list.map((e) => `
        <tr class="${e.own ? 'own' : ''}">
          <td class="rank">${e.rank}</td>
          ${labels
            ? `<td>${esc(e.label)}</td><td>${fmt0(e.bands)}</td>`
            : `<td>${e.own ? `<a href="#/band/${e.bandId}">${esc(e.band)}</a>` : esc(e.band)}</td><td class="muted">${esc(e.label)}</td><td>${fmt1(e.sound)}</td>`}
          <td><b>${fmt1(e.fame)}</b></td>
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
