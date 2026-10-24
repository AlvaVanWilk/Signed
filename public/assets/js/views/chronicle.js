import { app } from '../core.js';
import { esc, fmt1 } from '../ui.js';
import { bandAwards } from './bands.js';

/**
 * Label-Chronik: Alle ehemaligen Bands mit dem, was von ihnen bleibt – Verlauf (Gründung, Wechsel, Ende), Platten, Auszeichnungen.
 * Eine Band wird „ehemalig“, wenn sie ihren Namen aufgibt: umbenannt, aufgelöst, in einer anderen aufgegangen oder mit dem Label fertig.
 */

const REASON = {
  renamed: 'Umbenannt',
  merged: 'Zusammengegangen',
  disbanded: 'Aufgelöst',
  left: 'Hat das Label verlassen',
};

const day = (ts) => new Date(ts * 1000).toLocaleDateString('de-DE', { day: 'numeric', month: 'short', year: 'numeric' });

function recordLine(r) {
  const chart = r.charting ? `in den Charts, Platz ${r.rank}` : r.best ? `bester Platz ${r.best}` : 'nie in den Charts';
  return `<li><b>„${esc(r.title)}“</b> <small class="muted">${r.format}${r.perfect ? ' · perfekt' : ''} · ${chart}${r.charting ? ' · Tantiemen laufen' : ''}</small></li>`;
}

function band(c) {
  const awards = bandAwards({ gigsPlayed: c.gigsPlayed, records: c.records, discography: c.discography, options: { gig: { venueKey: '' } } });
  return `
    <article class="ch-band">
      <header>
        <h2>${esc(c.name)}</h2>
        <small class="muted">${day(c.foundedAt)} bis ${day(c.endedAt)}</small>
      </header>
      <p class="ch-end"><span class="ch-tag">${REASON[c.reason] ?? 'Beendet'}</span> ${esc(c.note ?? '')}${c.fameFinal > 0 ? ` · Ruhm am Namen zuletzt ${fmt1(c.fameFinal)}` : ''}</p>
      ${c.events.length ? `<h3 class="lb-cap">Verlauf</h3><ul class="ch-events">${c.events.map((e) => `<li><time>${day(e.createdAt)}</time> ${esc(e.text)}</li>`).join('')}</ul>` : ''}
      ${c.discography.length ? `<h3 class="lb-cap">Platten</h3><ul class="ch-records">${c.discography.map(recordLine).join('')}</ul>` : ''}
      ${awards.length ? `<h3 class="lb-cap">Auszeichnungen</h3><div class="ch-awards">${awards.map(([icon, title, sub]) => `<span class="ch-award" title="${esc(sub)}">${icon} ${esc(title)}</span>`).join('')}</div>` : ''}
    </article>`;
}

export function render() {
  const list = app.state.chronicle ?? [];
  return `
    <div class="page-head"><div><h1>Chronik</h1><div class="muted">Ehemalige Bands deines Labels und was von ihnen bleibt</div></div></div>
    ${list.length ? `<div class="ch-list">${list.map(band).join('')}</div>`
      : '<div class="empty">Noch keine ehemalige Band. Hier erscheinen Bands, die ihren Namen aufgegeben haben: umbenannt, aufgelöst oder mit einer anderen zusammengegangen.</div>'}`;
}
