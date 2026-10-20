import { api } from './api.js';
import { refreshModals, toast } from './ui.js';

/** Gemeinsamer Zustand der App. */
export const app = {
  label: null,
  catalog: null,
  state: null,
  charts: null,
  offset: 0,
  ui: {
    catalog: { instrument: '', genre: '', status: '', sort: 'level' },
    chartsTab: 'albums',
    gigSongs: {},
    form: {},
  },
};

/** Aktionen, die per data-act / data-form / data-change ausgelöst werden. */
export const actions = {};

export function defineActions(map) {
  Object.assign(actions, map);
}

let renderFn = () => {};

export function onRender(fn) {
  renderFn = fn;
}

export function rerender() {
  renderFn();
  refreshModals();
}

export function setState(state) {
  app.state = state;
  app.label = state.label;
  app.offset = state.now - Date.now() / 1000;
}

/** Serverzeit in Sekunden. */
export function serverNow() {
  return Date.now() / 1000 + app.offset;
}

export async function refresh() {
  try {
    setState(await api('state'));
  } catch (e) {
    handleError(e);
    return;
  }
  rerender();
}

/** Führt eine Aktion aus, übernimmt den neuen Spielstand und zeigt Fehler als Hinweis. Liefert null bei Fehlern. */
export async function mutate(action, body = {}) {
  try {
    const res = await api(action, body);
    if (res.state) {
      setState(res.state);
    }
    rerender();
    return res;
  } catch (e) {
    handleError(e);
    return null;
  }
}

function handleError(e) {
  if (e.status === 401) {
    app.label = null;
    app.state = null;
    rerender();
  }
  toast(e.message, 'error');
}

export const cat = () => app.catalog;
export const musicianById = (id) => app.state.musicians.find((m) => m.id === Number(id));
export const bandById = (id) => app.state.bands.find((b) => b.id === Number(id));
export const managerById = (id) => app.state.managers.find((m) => m.id === Number(id));
export const gearById = (id) => app.state.gear.find((g) => g.id === Number(id));
export const runningFor = (bandId) => app.state.running.find((a) => a.bandId === Number(bandId));

export function isBusy(until) {
  return until > serverNow();
}

export function navigate(hash) {
  if (location.hash === hash) {
    rerender();
  } else {
    location.hash = hash;
  }
}
