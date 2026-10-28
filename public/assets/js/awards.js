import { api } from './api.js';
import { app, bandById, cat } from './core.js';
import { esc, openModal, topModal } from './ui.js';
import { trophy } from './views/battle.js';
import { poster } from './views/festival.js';
import { globe } from './tour.js';

/**
 * Auszeichnungen der Bands: wie sie aussehen (Schallplatte im Rahmen, Platz-1-Stern, Bühnenpass, Pokal, Plakat, Globus),
 * wo sie im Proberaum stehen (an der Wand oder auf dem Boden) und das Fenster, das eine neue einmal feiert.
 */

const DISC = { gold: ['#e8c35a', '#a8801f'], platinum: ['#e4e8ef', '#8b93a1'], diamond: ['#d8f3ff', '#6fb3d6'] };

/** Schallplatte im Rahmen: Gold, Platin oder Diamant. */
function disc(kind) {
  const [light, dark] = DISC[kind];
  const sparkle = kind === 'diamond' ? '<path d="M17.5 5 l.8 1.6 1.6.8 -1.6.8 -.8 1.6 -.8-1.6 -1.6-.8 1.6-.8 Z M6.5 15 l.6 1.2 1.2.6 -1.2.6 -.6 1.2 -.6-1.2 -1.2-.6 1.2-.6 Z" fill="#fff"/>' : '';
  return `<svg class="trophy award-disc" viewBox="0 0 24 24" aria-hidden="true"><rect x="1.5" y="1.5" width="21" height="21" rx="1.5" fill="#3a2a1e" stroke="#1d1a2a" stroke-width="1"/>
    <rect x="3.5" y="3.5" width="17" height="17" fill="#f1e9da"/><circle cx="12" cy="11" r="6.4" fill="${light}" stroke="${dark}" stroke-width="1"/>
    <circle cx="12" cy="11" r="4.2" fill="none" stroke="${dark}" stroke-width=".4" opacity=".7"/><circle cx="12" cy="11" r="1.6" fill="#c8323a"/>
    <rect x="8" y="18.2" width="8" height="1.6" fill="${dark}"/>${sparkle}</svg>`;
}

/** Goldener Stern mit einer 1 (Platz 1 der Charts). */
function numberOne() {
  return `<svg class="trophy award-one" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 1.8 l2.9 6.1 6.6.8 -4.9 4.6 1.3 6.6 -5.9-3.3 -5.9 3.3 1.3-6.6 -4.9-4.6 6.6-.8 Z" fill="#e8c35a" stroke="#a8801f" stroke-width="1.1" stroke-linejoin="round"/>
    <path d="M10.6 9.6 L12.6 8.4 V15.4 M10.8 15.4 H14.4" fill="none" stroke="#7a5a12" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>`;
}

/** Bühnenpass am Band (erster Auftritt in einer Halle oder Arena). */
function pass(kind) {
  const color = kind === 'arena' ? '#e8c35a' : '#6f9bd6';
  return `<svg class="trophy award-pass" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 1 L12 8 L16 1" fill="none" stroke="#c8323a" stroke-width="1.6"/>
    <rect x="5.5" y="7" width="13" height="16" rx="1.6" fill="#f4f1ea" stroke="#1d1a2a" stroke-width="1"/><rect x="5.5" y="7" width="13" height="5" rx="1.2" fill="${color}"/>
    <rect x="10" y="8.2" width="4" height="1.4" rx=".7" fill="#1d1a2a" opacity=".5"/><path d="M12 13.6 l1.2 2.4 2.6.4 -1.9 1.8 .5 2.6 -2.4-1.3 -2.4 1.3 .5-2.6 -1.9-1.8 2.6-.4 Z" fill="${color}" stroke="#1d1a2a" stroke-width=".6"/></svg>`;
}

/** Bild einer Auszeichnung. */
export function awardIcon(a) {
  if (DISC[a.kind]) return disc(a.kind);
  if (a.kind === 'number1') return numberOne();
  if (a.kind === 'hall' || a.kind === 'arena') return pass(a.kind);
  if (a.kind === 'festival') return poster();
  if (a.kind === 'world') return globe();
  return trophy(a.place);
}

/** Kurzer Name (für Listen): „Goldene Schallplatte“, „Battle of the Bands: Platz 2“ … */
export function awardName(a) {
  return {
    gold: 'Goldene Schallplatte', platinum: 'Platin-Schallplatte', diamond: 'Diamant-Schallplatte', number1: 'Platz 1 der Charts',
    hall: 'Große Halle', arena: 'Arena', festival: 'Festival-Headliner', world: 'Weltenbummler',
  }[a.kind] ?? (a.place === 1 ? 'Battle of the Bands: Sieger' : `Battle of the Bands: Platz ${a.place}`);
}

/** Was hängt an der Wand und was steht auf dem Boden? */
const WALL = ['gold', 'platinum', 'diamond', 'number1', 'hall', 'arena', 'festival'];

/** Im Proberaum: Schallplatten, Pässe und Plakate an der Wand, Pokale und Globus auf dem Boden (die neuesten, mit „+N“ für den Rest). */
export function roomAwards(b) {
  const all = b.awards ?? [];
  const wall = all.filter((a) => WALL.includes(a.kind));
  const floor = all.filter((a) => !WALL.includes(a.kind));
  const show = (list, max, cls) => {
    if (!list.length) return '';
    const items = list.slice(-max);
    const more = list.length - items.length;
    return `<div class="${cls}">${items.map((a) => `<span title="${esc(a.title)}">${awardIcon(a)}</span>`).join('')}${more ? `<small class="lb-award-more">+${more}</small>` : ''}</div>`;
  };
  return show(wall, 6, 'lb-wall') + show(floor, 5, 'lb-trophies');
}

/** Liste aller Auszeichnungen einer Band (Bandseite, WikiSignia). */
export function awardList(awards) {
  if (!awards?.length) return '<p class="muted small">Noch keine Auszeichnungen. Goldene Schallplatte, Platz 1, große Bühnen, Battle-Pokale und mehr: alles landet hier und im Proberaum.</p>';
  // Unterzeile ohne den Namen davor: „Goldene Schallplatte: „Titel““ → „Titel“
  const detail = (a) => (a.title.includes(': ') ? a.title.slice(a.title.indexOf(': ') + 2) : a.title);
  return `<div class="aw-list">${[...awards].reverse().map((a) => `<div class="aw-item"><span class="aw-icon">${awardIcon(a)}</span><span><b>${esc(awardName(a))}</b><small>${esc(detail(a))}</small></span></div>`).join('')}</div>`;
}

const COPIES = new Intl.NumberFormat('de-DE');
/** „hat sich 100.000 Mal verkauft.“ (Schwellen aus Balance::AWARDS) */
const sold = (kind) => `hat sich ${COPIES.format(cat().awards.records[kind])} Mal verkauft${kind === 'diamond' ? '!' : '.'}`;
const TEXTS = { gold: true, platinum: true, diamond: true };

/** Neue Auszeichnung: einmal groß feiern (eine nach der anderen), dann hängt sie im Proberaum. */
let open = false;
export function celebrateAwards() {
  const list = app.state?.awardsNew ?? [];
  if (open || !list.length || topModal()) return;
  const a = list[0];
  const band = bandById(a.bandId);
  const record = a.title.includes('„') ? a.title.slice(a.title.indexOf('„')) : '';
  const text = TEXTS[a.kind] ? `${record} ${sold(a.kind)}`
    : a.kind === 'number1' ? `${record} steht auf Platz 1 der Charts!`
      : a.kind === 'hall' ? 'Zum ersten Mal in einer großen Halle gespielt. Der Bühnenpass hängt jetzt im Proberaum.'
        : a.kind === 'arena' ? 'Zum ersten Mal in einer Arena gespielt. Der goldene Bühnenpass hängt jetzt im Proberaum.'
          : a.title;
  open = true;
  openModal({
    className: 'aw-modal',
    html: `<div class="aw-big">${awardIcon(a)}</div>
      <div class="eyebrow">Auszeichnung · ${esc(band?.name ?? '')}</div>
      <h2>${esc(awardName(a))}!</h2>
      <p class="muted">${esc(text)}${TEXTS[a.kind] || a.kind === 'number1' ? ' Sie hängt jetzt im Proberaum.' : ''}</p>
      <div class="dialog-actions"><button class="btn primary" data-close>Toll!</button></div>`,
    onClose: () => {
      open = false;
      app.state.awardsNew = (app.state.awardsNew ?? []).filter((x) => x.id !== a.id);
      api('awardSeen', { awardId: a.id }).catch(() => {});
      setTimeout(celebrateAwards, 300);
    },
  }).body.addEventListener('click', (e) => {
    if (e.target.closest('[data-close]')) topModal()?.close();
  });
}
