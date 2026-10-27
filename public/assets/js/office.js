import { figure } from './figure.js';
import { app, defineActions, rerender } from './core.js';
import { esc, money, off } from './ui.js';

/**
 * Die Poststelle des Labels als kleine Szene statt einer Kachel: Wand mit Fenster und Postfächern, vorn der Schreibtisch, dahinter stehen
 * Praktikum und Assistenz als Figuren. Man redet mit ihnen über ihre Sprechblase (Zurufe, Aufgabe, Abbrechen); was nebenher gilt
 * (wie lange das Praktikum noch bleibt, Übernehmen), steht auf einem Zettel an der Wand. Gezeichnet im Stil der Signies und des Proberaums.
 */

/** Was das Praktikum tun kann, und wie es das sagt. */
export const INTERN_JOBS = { unpack: ['Briefe öffnen', 'öffnet Briefe', 'Ich öffne gerade die Briefe.'], listen: ['Demos anhören', 'hört Demos an', 'Ich höre gerade die Demos an.'], sort: ['Post sortieren', 'sortiert die Post', 'Ich sortiere gerade die Post in die Fächer.'] };

/**
 * Die große Sprechblase erscheint erst, wenn man jemanden antippt (eine zur Zeit, bleibt beim Neuzeichnen offen). Wer keinen Auftrag
 * hat, sagt von sich aus kurz „Ich habe nichts zu tun.“; beim Praktikum steht während der Arbeit die Restzeit über dem Kopf.
 */
defineActions({
  psTalk({ id }) {
    app.ui.psOpen = app.ui.psOpen === Number(id) ? null : Number(id);
    rerender();
  },
});
document.addEventListener('click', (e) => {
  if (app.ui?.psOpen == null || e.target.closest?.('.ps-bubble, [data-act=psTalk]')) return;
  app.ui.psOpen = null;
  rerender();
});
/** Nach einem Zuruf oder Abbrechen schließt sich die Blase wieder. */
export const closeTalk = () => { if (app.ui) app.ui.psOpen = null; };

const INK = '#1d1a2a';
const W = 960;
const H = 300;
const st = (w = 3) => `stroke="${INK}" stroke-width="${w}" stroke-linejoin="round" stroke-linecap="round"`;
const box = (x, y, w, h, fill, rx = 3, sw = 3) => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="${rx}" fill="${fill}" ${st(sw)}/>`;
const ENV_COLORS = ['#e9dcc0', '#d8c6a2', '#f0e6d2', '#c9b48c', '#e3cfa8'];

/** Wand, Fenster mit Abendlicht, Postfächer, Uhr, Lampe. */
function wall() {
  let s = `<defs><linearGradient id="psSky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b4f7a"/><stop offset="0.6" stop-color="#c9826a"/><stop offset="1" stop-color="#f2c27a"/></linearGradient>
    <radialGradient id="psLamp" cx="0.5" cy="0" r="1"><stop offset="0" stop-color="#ffe6a8" stop-opacity="0.55"/><stop offset="1" stop-color="#ffe6a8" stop-opacity="0"/></radialGradient>
    <radialGradient id="psWin" cx="0.5" cy="0.5" r="0.6"><stop offset="0" stop-color="#f2c27a" stop-opacity="0.22"/><stop offset="1" stop-color="#f2c27a" stop-opacity="0"/></radialGradient></defs>`;
  s += `<rect width="${W}" height="${H}" fill="#3f5160"/>`;
  for (let x = 0; x < W; x += 40) s += `<rect x="${x}" y="0" width="20" height="${H}" fill="#435667" opacity="0.6"/>`;
  // Holzvertäfelung unten
  s += `<rect y="196" width="${W}" height="${H - 196}" fill="#6b4a34"/>` + `<path d="M0 196 H${W}" ${st(3)}/>`;
  for (let x = 30; x < W; x += 80) s += `<path d="M${x} 204 V${H}" stroke="#5a3d2a" stroke-width="2"/>`;
  // Fenster links mit Abendhimmel und Lichtschein
  s += `<ellipse cx="120" cy="100" rx="150" ry="110" fill="url(#psWin)"/>`;
  s += box(48, 34, 150, 118, 'url(#psSky)', 4);
  s += `<circle cx="160" cy="118" r="16" fill="#ffd98a" opacity="0.9"/>`;
  s += `<path d="M48 128 Q90 112 120 126 T198 120 V152 H48 Z" fill="#2f3b5c" opacity="0.8"/>`;
  s += `<path d="M123 34 V152 M48 93 H198" ${st(3)}/>` + box(40, 150, 166, 10, '#8a6a4e', 3, 2.6);
  // Pflanze auf dem Fensterbrett
  s += `<path d="M76 150 l4 -12 h20 l4 12 Z" fill="#b5654a" ${st(2.4)}/>` + `<path d="M90 138 C84 120 70 118 66 108 M90 138 C92 118 104 112 110 100 M90 138 C88 126 92 116 88 104" stroke="#4f8a5a" stroke-width="5" fill="none" stroke-linecap="round"/>`;
  // Postfächer rechts, mit Umschlägen in den Fächern
  const sx = 700, sy = 30, cw = 52, ch = 38;
  s += box(sx - 8, sy - 8, cw * 4 + 16, ch * 4 + 16, '#7a5238', 4);
  for (let r = 0; r < 4; r++) for (let c = 0; c < 4; c++) {
    const x = sx + c * cw, y = sy + r * ch;
    s += box(x, y, cw - 4, ch - 4, '#3a2a20', 2, 2);
    if ((r * 7 + c * 3) % 5 !== 0) s += `<rect x="${x + 6}" y="${y + 10 + ((r + c) % 3)}" width="${cw - 16}" height="${ch - 18}" rx="1.5" fill="${ENV_COLORS[(r + c * 2) % ENV_COLORS.length]}" ${st(1.6)}/>`;
  }
  // Uhr
  s += `<circle cx="560" cy="62" r="24" fill="#efe6d2" ${st(3)}/><path d="M560 62 V46 M560 62 L571 68" ${st(2.4)}/>`;
  // Hängelampe mit Lichtkegel über dem Schreibtisch
  s += `<path d="M420 0 V40" ${st(2.4)}/><path d="M398 58 L442 58 L432 38 L408 38 Z" fill="#c9a86a" ${st(2.6)}/><ellipse cx="420" cy="60" rx="10" ry="4" fill="#fff3c8"/>`;
  s += `<path d="M404 60 L260 ${H} L580 ${H} L436 60 Z" fill="url(#psLamp)"/>`;
  return `<svg class="ps-wall" viewBox="0 0 ${W} ${H}" preserveAspectRatio="xMidYMid slice" aria-hidden="true">${s}</svg>`;
}

/** Der Schreibtisch vorn (liegt über den Figuren, sie stehen dahinter): Platte, Front, Poststapel, Kassettenkiste, Tasse. */
function desk(piles) {
  let s = `<rect x="0" y="18" width="${W}" height="72" fill="#8a5c3c" ${st(3)}/><rect x="0" y="6" width="${W}" height="16" rx="4" fill="#a8744c" ${st(3)}/>`;
  s += `<rect x="0" y="22" width="${W}" height="5" fill="#000" opacity="0.15"/>`;
  for (let x = 120; x < W; x += 240) s += box(x, 34, 180, 44, '#7a5034', 4, 2.4) + `<circle cx="${x + 90}" cy="56" r="4" fill="#c9a86a" ${st(1.6)}/>`;
  // Poststapel links (so hoch, wie Post da ist), Kassettenkiste, Tasse
  const n = Math.min(7, piles);
  for (let i = 0; i < n; i++) s += `<rect x="${60 + (i % 2) * 4}" y="${-4 - i * 6}" width="64" height="10" rx="1.5" fill="${ENV_COLORS[i % ENV_COLORS.length]}" ${st(1.8)} transform="rotate(${(i % 3) - 1} 92 ${-i * 6})"/>`;
  s += box(820, -12, 70, 22, '#3a3352', 3, 2.4) + `<rect x="828" y="-8" width="16" height="12" rx="2" fill="#2b2836" ${st(1.4)}/><rect x="848" y="-8" width="16" height="12" rx="2" fill="#2b2836" ${st(1.4)}/><rect x="868" y="-8" width="16" height="12" rx="2" fill="#2b2836" ${st(1.4)}/>`;
  s += `<path d="M740 -14 h22 v20 a4 4 0 0 1 -4 4 h-14 a4 4 0 0 1 -4 -4 Z" fill="#cf7f9b" ${st(2.2)}/><path d="M762 -8 a6 6 0 0 1 0 10" fill="none" ${st(2.2)}/>`;
  return `<svg class="ps-desk" viewBox="0 -40 ${W} 130" preserveAspectRatio="xMidYMax slice" aria-hidden="true">${s}</svg>`;
}

/** Was das Praktikum in der Hand hat, während es arbeitet. */
const HELD = {
  unpack: `<svg viewBox="0 0 40 28"><rect x="2" y="2" width="36" height="24" rx="2" fill="#e9dcc0" ${st(2)}/><path d="M2 3 L20 16 L38 3" fill="none" ${st(2)}/></svg>`,
  listen: `<svg viewBox="0 0 40 28"><rect x="2" y="2" width="36" height="24" rx="4" fill="#2b2836" ${st(2)}/><rect x="7" y="6" width="26" height="8" rx="1.5" fill="#d8d2c4"/><circle cx="13" cy="20" r="3" fill="#c9a86a"/><circle cx="27" cy="20" r="3" fill="#c9a86a"/></svg>`,
  sort: `<svg viewBox="0 0 40 28"><rect x="6" y="2" width="30" height="20" rx="2" fill="#d8c6a2" ${st(2)}/><rect x="2" y="6" width="30" height="20" rx="2" fill="#f0e6d2" ${st(2)}/></svg>`,
  coffee: `<svg viewBox="0 0 40 34"><path class="ps-steam" d="M14 9 C11 6 16 4 13 1 M22 9 C19 6 24 4 21 1" fill="none" stroke="#fff" stroke-opacity=".6" stroke-width="2" stroke-linecap="round"/><path d="M6 12 H30 V26 a6 6 0 0 1 -6 6 H12 a6 6 0 0 1 -6 -6 Z" fill="#cf7f9b" ${st(2)}/><path d="M30 15 a5 5 0 0 1 0 10" fill="none" ${st(2)}/></svg>`,
};

/**
 * Was jemand gerade in der Hand hat: Das Praktikum zeigt seinen Zuruf; eine Assistenz mit Arbeitsgebiet die Kassette oder den
 * Interview-Brief, wenn es dafür gerade etwas gibt, sonst trinkt sie Kaffee. Wer gar nichts zu tun hat, hat nichts in der Hand.
 */
function heldOf(x, a) {
  if (x.intern) return x.job ? x.job.kind : null;
  if (x.task === 'demos') return a.todo.listen ? 'listen' : 'coffee';
  if (x.task === 'presse') return (app.state.mail ?? []).some((m) => m.kind === 'interview') ? 'unpack' : 'coffee';
  return null;
}

const name = (x) => esc(x.firstName);

/** Sprechblase des Praktikums: Langeweile und Zurufe, oder was es gerade tut (mit Abbrechen). */
function internBubble(x, a) {
  if (x.job) {
    const say = INTERN_JOBS[x.job.kind]?.[2] ?? 'Ich bin beschäftigt.';
    return `<p>${esc(say)} <small>Fertig in <span data-until="${x.job.until}"></span>.</small></p>
      <div class="ps-say"><button class="ps-pill ghost" data-act="internCancel" data-id="${x.id}">Lass gut sein</button></div>`;
  }
  const t = a.todo;
  const any = t.unpack || t.listen || t.sort;
  const none = { unpack: 'Gerade liegt kein ungeöffneter Brief da.', listen: 'Gerade liegt keine ungehörte Kassette da.', sort: 'Im Eingang liegt nichts zum Sortieren.' };
  return `<p>${any ? 'Mir ist langweilig. Was soll ich machen?' : 'Gerade gibt es nichts zu tun.'}</p>
    <div class="ps-say">${Object.entries(INTERN_JOBS).map(([job, [label]]) => `<button class="ps-pill" data-act="internOrder" data-id="${x.id}" data-job="${job}" ${off(t[job] ? '' : none[job])}>${label}</button>`).join('')}</div>`;
}

/** Sprechblase einer Assistenz: was sie laufend tut, ihr Arbeitsgebiet zum Umstellen. */
function assistantBubble(x, a) {
  // Kurz, damit es in die Sprechblase passt (die langen Namen stehen in Catalog::STAFF_ROLES).
  const tasks = { none: 'Nur Post', demos: 'Kassetten', presse: 'Interviews' };
  const doing = { none: 'Was soll ich sonst machen?', demos: 'Dazu höre ich Kassetten an.', presse: 'Dazu sage ich Interviews zu.' }[x.task] ?? '';
  return `<p>Ich packe die Post aus und sortiere sie. ${esc(doing)}${x.task === 'demos' ? `<small>Heute ${a.today.opened} angehört, ${a.today.dropped} weggeworfen.</small>` : ''}</p>
    <div class="ps-say">${Object.entries(tasks).map(([k, label]) => `<button class="ps-pill ${x.task === k ? 'on' : ''}" data-act="assistTask" data-id="${x.id}" data-task="${k}">${esc(label)}</button>`).join('')}
    ${x.task === 'demos' ? '<button class="ps-pill ghost" data-act="assistRulesAsk">Regeln</button>' : ''}</div>`;
}

/** Die ganze Szene. a = app.state.assistant (oder null: niemand da). */
export function officeScene(a, posts = 0) {
  const staff = (a?.staff ?? []).slice(0, 3);
  // Drei Ebenen mit denselben Spalten: Figuren hinter dem Tisch, der Tisch, davor Sprechblasen und Namensschilder.
  const people = staff.map((x) => {
    const held = heldOf(x, a);
    return `
    <div class="ps-col ${held && held !== 'coffee' ? 'busy' : ''}">
      <div class="ps-fig fig-stage idle"><div class="fig-member" style="--d:${(-((x.id * 7919) % 4300) / 1000).toFixed(2)}s">${figure(x.look, 'none', { bored: !held })}</div>
        ${held ? `<span class="ps-held ${held === 'coffee' ? 'coffee' : ''}">${HELD[held]}</span>` : ''}</div>
    </div>`;
  }).join('');
  const open = staff.some((x) => x.id === app.ui.psOpen) ? app.ui.psOpen : null;
  // Ohne Auftrag (Praktikum) oder ohne Arbeitsgebiet (Assistenz) sagt man es; beim Praktikum steht während der Arbeit die Restzeit
  // über dem Kopf. Eine Assistenz mit Arbeitsgebiet sagt nichts, auch wenn sie gerade Kaffee trinkt.
  const hint = (x) => (x.intern ? (x.job ? `<span data-until="${x.job.until}"></span>` : 'Ich habe nichts zu tun.') : x.task === 'none' ? 'Ich habe nichts zu tun.' : '');
  const front = staff.map((x) => `
    <div class="ps-col">
      ${x.id === open ? `<div class="ps-bubble">${x.intern ? internBubble(x, a) : assistantBubble(x, a)}</div>`
    : hint(x) ? `<button class="ps-mini" data-act="psTalk" data-id="${x.id}" title="Mit ${name(x)} reden">${hint(x)}</button>` : ''}
      <button class="ps-hit" data-act="psTalk" data-id="${x.id}" title="Mit ${name(x)} reden" aria-label="Mit ${name(x)} reden"></button>
      <button class="ps-name" data-act="psTalk" data-id="${x.id}">${name(x)}<small>· ${x.intern ? 'Praktikum' : 'Assistenz'}</small></button>
    </div>`).join('');
  const intern = staff.find((x) => x.intern);
  const note = intern
    ? `<div class="ps-note"><b>${name(intern)}</b> bleibt noch <span data-until="${intern.until}"></span>
        <button class="ps-pill" data-act="assistAdopt" data-id="${intern.id}" ${off(app.state.label.money < a.adoptPrice ? `Dafür fehlen noch ${money(a.adoptPrice - app.state.label.money)}.` : '')}>Fest übernehmen · ${money(a.adoptPrice)}</button></div>`
    : !staff.length
      ? `<div class="ps-note ps-empty">Hier sitzt noch niemand. Ein <b>Praktikum</b> hilft dir auf Zuruf, eine <b>Assistenz</b> erledigt die Post von allein.<br>
        <button class="ps-pill" data-act="subBox" data-key="job">Zu den Bewerbungen</button></div>`
      : '';
  return `<section class="ps-scene ${staff.length > 1 ? 'many' : ''}">${wall()}<div class="ps-row ps-people">${people}</div>${desk(posts)}<div class="ps-row ps-front">${front}</div>${note}</section>`;
}
