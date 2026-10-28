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
    /** Von wo aus ein Musiker geöffnet wurde: Liste zum Durchblättern und Rückweg. */
    musicianNav: null,
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

const stateListeners = [];

/** fn(vorher, nachher) läuft bei jedem neuen Spielstand, z. B. für Feiern nach einer Änderung. Beim ersten ist vorher null. */
export function onStateChange(fn) {
  stateListeners.push(fn);
}

export function setState(state) {
  const previous = app.state;
  app.state = state;
  app.label = state.label;
  app.offset = state.now - Date.now() / 1000;
  stateListeners.forEach((fn) => fn(previous, state));
}

/** Serverzeit in Sekunden. */
export function serverNow() {
  return Date.now() / 1000 + app.offset;
}

/** Geld mit den seit dem letzten Abruf angefallenen Tantiemen (der Server rechnet beim nächsten Abruf genauso ab). */
export function liveMoney() {
  const s = app.state;
  return Math.floor(s.label.money + (s.finance.moneyPerHour / 3600) * Math.max(0, serverNow() - s.now));
}

/** Wechselt am Spielstand etwas, das die Seite neu aufbauen muss (statt nur Zahlen mitlaufen zu lassen)? */
function structuralChange(before, after) {
  if (!before) return true;
  const key = (s) => [
    s.running.map((a) => `${a.id}`).join(','),
    s.bands.map((b) => `${b.id}:${b.memberIds.join('.')}:${b.mood.level}:${b.mood.wishes.length}`).join(','),
    s.musicians.map((m) => `${m.id}:${m.level}:${m.fameLevel}:${m.bandId}:${m.wellbeing?.level}`).join(','),
    s.results.map((r) => r.id).join(','),
  ].join('|');
  return key(before) !== key(after);
}

/** quiet: Der Spielstand wird übernommen, die Seite aber nur neu gezeichnet, wenn sich etwas Grundlegendes ändert (laufende Probe). */
export async function refresh({ quiet = false } = {}) {
  const before = app.state;
  try {
    setState(await api('state'));
  } catch (e) {
    handleError(e);
    return;
  }
  if (!quiet || structuralChange(before, app.state)) rerender();
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

/** Kann dieser Manager die Band übernehmen? Eine Band braucht ihn für sich allein, Solokünstler teilen sich einen (bis zu vier). Sonst der Grund. */
export function managerBusy(m, b) {
  const max = cat().rules.managerSolos;
  const others = m.bandIds.filter((id) => id !== b.id).map(bandById).filter(Boolean);
  const bands = others.filter((x) => x.memberIds.length > 1).length;
  const solos = others.length - bands;
  if (bands) return `${m.firstName} kümmert sich schon um eine Band.`;
  if (solos && b.memberIds.length > 1) return `${m.firstName} betreut schon Solokünstler. Eine Band braucht einen Manager für sich allein.`;
  if (solos >= max) return `${m.firstName} betreut schon ${max} Solokünstler, mehr schafft niemand.`;
  return '';
}
export const gearById = (id) => app.state.gear.find((g) => g.id === Number(id));
/** Was die Band gerade macht. Reisen auf Tournee zählen nicht: Unterwegs ist die Band frei (sie probt im Bus, gibt Interviews). */
export const runningFor = (bandId) => app.state.running.find((a) => a.bandId === Number(bandId) && a.type !== 'tourtravel' && a.type !== 'tour');

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
