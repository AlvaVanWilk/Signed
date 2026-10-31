import { app, mutate, musicianById } from './core.js';
import { esc, fmt0, money, openModal } from './ui.js';
import { crowdMeter, venueArt } from './venues.js';

/**
 * Neuer Kern im Browser (Fans und Hype statt Ruhm, Gage und Tantiemen). Der Server meldet mit state.core, ob er schon so rechnet;
 * bis dahin bleibt alles wie bisher.
 */
export const coreOn = () => app.state?.core === true;

const STAGE_NAMES = ['Unbekannt', 'Lokal bekannt', 'Berühmt', 'Superstar'];
const FLAME_WORDS = { blaze: 'lodert', red: 'rote Flamme', weak: 'schwache Flamme', ember: 'kleine Glut', none: 'kein Hype' };

/** Flamme für den Hype (Charts, Bandseite). */
export function flame(kind, title = '') {
  if (!kind || kind === 'none') return `<span class="cf-flame none" title="${esc(title || FLAME_WORDS.none)}"></span>`;
  return `<span class="cf-flame ${kind}" title="${esc(title || FLAME_WORDS[kind])}"><svg viewBox="0 0 16 20" aria-hidden="true"><path d="M8 1 C9 6 14 8 13 13 C12.5 17 10 19 8 19 C5 19 3 17 3 13.5 C3 10.5 5 9 6 7 C6.5 9 7.5 10 8 10 C8 7 7 4 8 1 Z"/></svg></span>`;
}

/** Sterne der Ruhmstufe (0–3). */
export const coreStars = (b) => Math.min(3, b.core?.stage ?? 0);

/** Kurzer Text am Knopf „Auftreten“. */
export function gigHint(b) {
  const g = b.core?.gig;
  return g ? `etwa ${fmt0(g.tickets)} Leute · rund ${money(g.earnings)}` : 'Auftreten';
}

/** Fans und Hype als Kacheln unter „Werte“ (statt Ruhm). */
export function valueTiles(b) {
  const c = b.core;
  if (!c) return '';
  const next = c.next ? ` · ${STAGE_NAMES[c.stage + 1]} ab ${fmt0(c.next)}` : '';
  return `
    <div data-act="coreInfo" data-key="fans" data-id="${b.id}"><small>Fans</small><b>${fmt0(c.fans)}</b><em class="cf-sub">${esc(c.stageName)}${esc(next)}</em></div>
    <div data-act="coreInfo" data-key="hype" data-id="${b.id}"><small>Hype</small><b>${flame(c.flame)} ${fmt0(c.hype)}</b></div>`;
}

const WRITE_MODES = [
  ['fast', 'schnell', 'Mehr Songs, dafür etwas schwächere.'],
  ['normal', 'normal', 'So viele Songs, wie die Band schafft, in ihrer gewohnten Qualität.'],
  ['careful', 'sorgfältig', 'Weniger Songs, dafür bessere.'],
];

/** Regler auf der Bandseite: Wie die Band Songs schreibt. */
export function writeModeRow(b) {
  const mode = b.core?.writeMode ?? 'normal';
  return `<div class="cf-write"><small class="lb-cap">Songs schreiben</small>
    <div class="tabs">${WRITE_MODES.map(([key, label, text]) => `<button class="tab ${key === mode ? 'active' : ''}" data-act="bandWriteMode" data-id="${b.id}" data-mode="${key}" title="${esc(text)}">${label}</button>`).join('')}</div>
    <small class="muted">${esc(WRITE_MODES.find(([key]) => key === mode)?.[2] ?? '')}</small></div>`;
}

/** Warum heute so viele oder so wenige kommen (das i neben dem Knopf). */
function why(b) {
  const c = b.core;
  const lines = [];
  if (!c.record) lines.push('Noch keine Platte: Es kommen nur ein paar Neugierige. Mit einer neuen Platte kommen viel mehr.');
  else if (c.hype >= 25) lines.push(`Die neueste Platte ist frisch (Hype ${fmt0(c.hype)}): Viele wollen ${b.memberIds.length === 1 ? 'den Auftritt' : 'die Band'} sehen.`);
  else if (c.hype >= 5) lines.push(`Der Hype der neuesten Platte lässt nach (${fmt0(c.hype)}). Eine neue Platte oder eine Anfrage bringt wieder mehr Leute.`);
  else lines.push('Die neueste Platte ist schon älter: ohne Hype kommen vor allem die treuen Fans.');
  if (c.fresh < 0.99) lines.push(`Heute schon aufgetreten: Es kommen nur ${fmt0(c.fresh * 100)} % so viele. Nach einem Tag Pause sind wieder alle da.`);
  lines.push(`${fmt0(c.fans)} Fans; je mehr Fans, desto mehr kommen. Das Label verdient ${money(c.gig.perVisitor)} je Zuschauer.`);
  return lines;
}

/** Auftreten: ein Fenster mit dem, was kommt, und einem Knopf. Den Ort wählt das Spiel. */
export function openGig(b) {
  const c = b.core;
  if (!c) return;
  const g = c.gig;
  const r = b.mood.reactions?.gig ?? { like: [], dislike: [] };
  const names = (ids) => ids.map((id) => musicianById(id)?.firstName).filter(Boolean).join(', ');
  const full = g.demand > g.size;
  const modal = openModal({
    wide: true,
    html: `
      <h2>Auftreten</h2>
      <div class="gg-row cf-gig">
        <div class="gg-art">${venueArt(g.venue)}</div>
        <div class="gg-main">
          <div class="gg-head"><b>${esc(g.venueName)} mit ${fmt0(g.size)} Plätzen</b><small>${full ? 'ausverkauft' : 'das Spiel nimmt die kleinste Größe, in die alle passen'}</small></div>
          <div class="gg-crowd">${crowdMeter(Math.min(1, g.tickets / g.size))}<span>etwa <b>${fmt0(g.tickets)}</b> Leute</span></div>
          <ul class="gg-notes">${why(b).map((l) => `<li>${esc(l)}</li>`).join('')}
            ${r.like.length ? `<li class="good">${esc(names(r.like))} ${r.like.length > 1 ? 'freuen' : 'freut'} sich darauf.</li>` : ''}
            ${r.dislike.length ? `<li class="bad">${esc(names(r.dislike))} ${r.dislike.length > 1 ? 'mögen' : 'mag'} das nicht.</li>` : ''}</ul>
        </div>
        <div class="gg-side"><small>rund</small><b>${money(g.earnings)}</b><small>wie viele neue Fans es werden, hängt von der Show ab</small></div>
      </div>
      <div class="dialog-actions"><button class="btn ghost" data-close>Lieber nicht</button><button class="btn primary" data-go>Auftreten</button></div>`,
  });
  modal.body.querySelector('[data-close]').addEventListener('click', () => modal.close());
  modal.body.querySelector('[data-go]').addEventListener('click', async (e) => {
    e.currentTarget.disabled = true;
    if (await mutate('activityStart', { bandId: b.id, type: 'gig' })) modal.close();
    else e.currentTarget.disabled = false;
  });
}

/** Erklärung zu Fans und Hype (Antippen der Kachel). */
export function infoText(b, key) {
  const c = b.core;
  if (key === 'hype') {
    return `Hype der neuesten Platte: Beim Erscheinen so hoch wie die Qualität es hergibt, danach jeden Tag halb so viel (eine perfekte Platte etwas langsamer). Anfragen, Festival und Battle geben einen Schub; jeder weitere zählt nur halb so viel. Mit viel Hype kaufen mehr Leute die Platte und kommen mehr zu Auftritten.`;
  }
  const roof = c.roof < 50000 ? ` Diese Band kommt höchstens auf ${fmt0(c.roof)} Fans.` : '';
  return `${fmt0(c.fans)} Fans: Sie kaufen Platten, kommen zu Auftritten und ziehen andere mit. Neue Fans kommen durch Platten, Auftritte, Festival und Battle. Ruhmstufe: ${c.stageName} (Lokal bekannt ab 500, Berühmt ab 5.000, Superstar ab 50.000).${roof}`;
}
