import { api } from '../api.js';
import { abilityInfo, genreName, instrumentName, photo } from '../cards.js';
import { app, cat, defineActions, rerender } from '../core.js';
import { esc, fmt0, fmt1, toast } from '../ui.js';
import { awardList } from '../awards.js';
import { coreOn } from '../core-ui.js';

/**
 * WikiSignia: Artikel zu jeder Band und jedem Künstler („Signie“), die dem Label einmal angehörten, auch zu ehemaligen.
 * Die Artikel laden bei Bedarf vom Server; der Text wird hier aus den Angaben zusammengesetzt (ohne Pronomen: Namen statt „er/sie“).
 */

const REASON = { renamed: 'Umbenannt', merged: 'Zusammengegangen', disbanded: 'Aufgelöst', left: 'Hat das Label verlassen' };
const LEFT = { released: 'Hat das Label verlassen', sold: 'Zu einem anderen Label gewechselt' };

const day = (ts) => new Date(ts * 1000).toLocaleDateString('de-DE', { day: 'numeric', month: 'long', year: 'numeric' });
const list = (items) => (items.length > 1 ? `${items.slice(0, -1).join(', ')} und ${items.at(-1)}` : items[0] ?? '');
const name = (m) => `${m.firstName} ${m.lastName}`;

let loading = null;

function parse() {
  const [, kind = '', id = ''] = location.hash.replace(/^#\/?/, '').split('/');
  return ['band', 'signie', 'label'].includes(kind) ? { kind, id: Number(id) } : { kind: '', id: 0 };
}

/** Lädt, was die aktuelle Adresse braucht (Verzeichnis immer, dazu den Artikel). */
export async function ensure() {
  const { kind, id } = parse();
  const key = kind ? `${kind}:${id}` : 'index';
  if (loading === key) return;
  const wiki = app.wiki ?? (app.wiki = { index: null, article: null, key: '', at: 0 });
  // Bei erneutem Aufruf nach ein paar Sekunden frisch laden (neue Bands, neue Einträge).
  if (wiki.key === key && Date.now() - wiki.at < 15000) return;
  loading = key;
  try {
    if (!wiki.index || !kind) wiki.index = await api('wikiIndex', {});
    if (!kind) {
      const own = wiki.index.labels.find((l) => l.own);
      wiki.own = own ? await api('wikiLabel', { id: own.id }) : null;
    }
    wiki.article = kind ? await api(kind === 'band' ? 'wikiBand' : kind === 'signie' ? 'wikiSignie' : 'wikiLabel', { id }) : null;
    wiki.key = key;
    wiki.at = Date.now();
  } catch (e) {
    toast(e.message, 'error');
    wiki.key = key;
    wiki.article = null;
    wiki.missing = true;
  } finally {
    loading = null;
  }
  rerender();
}

const link = (kind, id, text, former = false) => `<a class="wk-link ${former ? 'former' : ''}" href="#/wiki/${kind}/${id}">${esc(text)}</a>`;

function searchBox() {
  return `<div class="wk-search"><input type="search" data-wiki-search placeholder="WikiSignia durchsuchen" autocomplete="off" aria-label="WikiSignia durchsuchen"></div>`;
}

const entry = (kind, e) => `<li>${link(kind, e.id, e.name, e.status === 'former')}${e.status === 'former' ? ' <small class="muted">ehemalig</small>' : ''}</li>`;

function lists(data) {
  return `<div class="wk-index">
    <section><h2>Bands</h2>${data.bands.length ? `<ul>${data.bands.map((b) => entry('band', b)).join('')}</ul>` : '<p class="muted">Noch keine Band.</p>'}</section>
    <section><h2>Signies</h2>${data.signies.length ? `<ul>${data.signies.map((s) => entry('signie', s)).join('')}</ul>` : '<p class="muted">Noch niemand.</p>'}</section>
  </div>`;
}

function indexPage(index, own) {
  const others = index.labels.filter((l) => !l.own);
  return `
    <div class="wk-head"><h1>WikiSignia</h1><p class="muted">Alle Labels, ihre Bands und Signies, aktuelle und ehemalige.</p></div>
    ${searchBox()}
    <div class="wk-results" hidden></div>
    ${own ? `<h2 class="wk-cat">${esc(own.name)} <small class="muted">dein Label</small></h2>${lists(own)}` : ''}
    ${others.length ? `<h2 class="wk-cat">Weitere Labels</h2><ul class="wk-list">${others.slice(0, 20).map((l) => `<li>${link('label', l.id, l.name)} <small class="muted">${l.bandCount} Bands · ${l.signieCount} Signies</small></li>`).join('')}</ul>${others.length > 20 ? `<p class="muted">… und ${others.length - 20} weitere Labels. Die Suche findet auch sie.</p>` : ''}` : ''}`;
}

function labelPage(l) {
  const active = l.bands.filter((b) => b.status !== 'former').length;
  const signed = l.signies.filter((s) => s.status !== 'former').length;
  const lead = `<b>${esc(l.name)}</b> ist ein Plattenlabel, gegründet am ${day(l.foundedAt)}. ${signed
    ? `Unter Vertrag ${signed === 1 ? 'steht ein Signie' : `stehen ${signed} Signies`} in ${active === 1 ? 'einer Band' : `${active} Bands`}${l.recordCount ? `; erschienen ${l.recordCount === 1 ? 'ist eine Platte' : `sind ${l.recordCount} Platten`}` : ''}.`
    : 'Noch steht niemand unter Vertrag.'}`;
  const top = l.topRecords.map((r) => `<li><b>„${esc(r.title)}“</b> von ${link('band', r.bandId, r.band)} <small class="muted">${r.topped ? 'Platz 1' : `bester Platz ${r.best}`}${r.perfect ? ' · perfekt' : ''}</small></li>`).join('');
  return `
    <div class="wk-article">
      <aside class="wk-box"><h3>${esc(l.name)}</h3><table>
        ${row('Gegründet', day(l.foundedAt))}
        ${l.fans != null ? row('Fans', fmt0(l.fans)) : row('Label-Ruhm', fmt1(l.success))}
        ${row('Platten', l.recordCount || '')}
        ${row('Nummer-1-Hits', l.numberOnes || '')}
        ${row('Perfekte Platten', l.perfectCount || '')}
        ${row('Auftritte', l.gigs || '')}
      </table></aside>
      <h1>${esc(l.name)}</h1>
      <p class="muted">Label${l.own ? ' (deins)' : ''}</p>
      <p class="wk-lead">${lead}</p>
      ${top ? `<h2>Größte Erfolge</h2><ul class="wk-list">${top}</ul>` : ''}
      ${l.topBands.length ? `<h2>Bekannteste Bands</h2><ul class="wk-list">${l.topBands.map((b) => `<li>${link('band', b.id, b.name)} <small class="muted">${b.fans != null ? `${fmt0(b.fans)} Fans` : `Ruhm ${fmt1(b.fame)}`}</small></li>`).join('')}</ul>` : ''}
      ${l.milestones.length ? `<h2>Geschichte</h2><ul class="wk-events">${l.milestones.map((m) => `<li><time>${day(m.at)}</time> ${esc(m.text)}</li>`).join('')}</ul>` : ''}
    </div>
    ${lists(l)}`;
}

const row = (label, value) => (value ? `<tr><th>${label}</th><td>${value}</td></tr>` : '');

function timeline(events) {
  return events.length ? `<ul class="wk-events">${events.map((e) => `<li><time>${day(e.createdAt)}</time> ${esc(e.text)}</li>`).join('')}</ul>` : '';
}

function bandPage(b) {
  const former = b.status === 'former';
  const genres = b.genres.map(genreName);
  const lead = former
    ? `<b>${esc(b.name)}</b> war eine Band des Labels und bestand vom ${day(b.foundedAt)} bis zum ${day(b.endedAt)}. ${esc(b.name)} ${esc(b.note ?? 'wurde beendet')}.`
    : `<b>${esc(b.name)}</b> ist ${b.title ? `eine ${esc(b.title)}` : 'eine Band'} mit ${b.members.length} ${b.members.length === 1 ? 'Mitglied' : 'Mitgliedern'}, gegründet am ${day(b.foundedAt)}.`;
  const records = b.records.map((r) => `<li><b>„${esc(r.title)}“</b> <small class="muted">${r.format}${r.perfect ? ' · perfekt' : ''} · ${r.best ? `bester Platz ${r.best}` : 'nie in den Charts'}${r.sold ? ` · ${fmt0(r.sold)} verkauft` : ''}</small></li>`).join('');
  return `
    <div class="wk-article">
      <aside class="wk-box"><h3>${esc(b.name)}</h3><table>
        ${row('Label', link('label', b.label.id, b.label.name))}
        ${row('Status', former ? (REASON[b.reason] ?? 'Ehemalig') : 'Aktiv')}
        ${row('Gegründet', day(b.foundedAt))}
        ${row('Beendet', b.endedAt ? day(b.endedAt) : '')}
        ${row('Genre', esc(list(genres)))}
        ${b.fans != null ? row('Fans', fmt0(b.fans)) : coreOn() ? '' : row(former ? 'Ruhm am Namen zuletzt' : 'Ruhm', fmt1(b.fame))}
        ${row('Platten', b.recordCount || '')}
        ${row('Auftritte', b.gigsPlayed || '')}
      </table></aside>
      <h1>${esc(b.name)}</h1>
      <p class="wk-lead">${lead}</p>
      ${b.members.length ? `<h2>Mitglieder</h2><ul class="wk-people">${b.members.map(person).join('')}</ul>` : ''}
      ${b.formerMembers.length ? `<h2>${former ? 'Mitglieder' : 'Ehemalige Mitglieder'}</h2><ul class="wk-people">${b.formerMembers.map(person).join('')}</ul>` : ''}
      ${records ? `<h2>Diskografie</h2><ul class="wk-list">${records}</ul>` : ''}
      ${b.awards?.length ? `<h2>Auszeichnungen</h2>${awardList(b.awards)}` : ''}
      ${b.events.length ? `<h2>Geschichte</h2>${timeline(b.events)}` : ''}
    </div>`;
}

function person(m) {
  return `<li><a class="wk-ph" href="#/wiki/signie/${m.id}">${photo(m)}</a><span>${link('signie', m.id, name(m))}<small class="muted">${esc(list(m.instruments.map((i) => instrumentName(i.type))))}</small></span></li>`;
}

function signiePage(m) {
  const rules = cat();
  const genres = m.genres.map(genreName);
  const instruments = m.instruments.map((i) => instrumentName(i.type));
  const stay = m.status === 'current'
    ? `Seit dem ${day(m.joinedAt)} beim Label.`
    : `Gehörte vom ${day(m.joinedAt)} bis zum ${day(m.leftAt)} zum Label (${esc((LEFT[m.reason] ?? 'ausgeschieden').toLowerCase())}).`;
  const bands = m.bands.map((b) => `<li>${link('band', b.id, b.name, b.status === 'former')} <small class="muted">${esc(b.label)}</small>${b.now ? ' <small class="muted">aktuell</small>' : ''}${b.status === 'former' ? ' <small class="muted">ehemalig</small>' : ''}</li>`).join('');
  const abilities = m.abilities.map((a) => {
    const info = abilityInfo(a);
    return `<li><b>${info.icon} ${esc(info.name)}</b> <small class="muted">${esc(info.desc)}</small></li>`;
  }).join('');
  return `
    <div class="wk-article">
      <aside class="wk-box"><div class="wk-box-photo">${photo(m)}</div><h3>${esc(name(m))}</h3><table>
        ${row('Label', m.label ? link('label', m.label.id, m.label.name) : '')}
        ${row('Frühere Labels', m.formerLabels.length ? m.formerLabels.map((l) => link('label', l.id, l.name, true)).join(', ') : '')}
        ${row('Status', m.status === 'current' ? 'Beim Label' : esc(LEFT[m.reason] ?? 'Ausgeschieden'))}
        ${row('Instrumente', esc(m.instruments.map((i) => `${instrumentName(i.type)} (${rules.levelNames[Math.min(rules.rules.skillLevels.filter((t) => i.skill >= t).length, 3)]})`).join(', ')))}
        ${row('Genres', esc(list(genres)))}
        ${row('Charakter', esc(list([...m.personality.map((p) => rules.personalities[p]), ...Array(m.hiddenTraits ?? 0).fill('?')])))}
        ${row('Bekanntheit', coreOn() ? esc(rules.fameNames[m.fameLevel]) : `${esc(rules.fameNames[m.fameLevel])} (${fmt1(m.fame)})`)}
        ${row('Beim Label seit', day(m.joinedAt))}
        ${row('Ausgeschieden', m.leftAt ? day(m.leftAt) : '')}
      </table></aside>
      <h1>${esc(name(m))}</h1>
      <p class="wk-lead"><b>${esc(name(m))}</b> ist Signie und spielt ${esc(list(instruments))}${genres.length ? `, am liebsten ${esc(list(genres))}` : ''}. ${stay}</p>
      ${bands ? `<h2>Bands</h2><ul class="wk-list">${bands}</ul>` : ''}
      ${abilities ? `<h2>Spieltechnik</h2><ul class="wk-list">${abilities}</ul>` : ''}
      ${m.events.length ? `<h2>Laufbahn</h2>${timeline(m.events)}` : ''}
    </div>`;
}

export function render() {
  const wiki = app.wiki;
  const { kind, id } = parse();
  const back = kind ? '<a class="btn ghost small wk-back" href="#/wiki">← WikiSignia</a>' : '';
  if (!wiki || wiki.key !== (kind ? `${kind}:${id}` : 'index')) return '<div class="empty">Die WikiSignia lädt …</div>';
  if (kind && !wiki.article) return `${back}<div class="empty">Diesen Artikel gibt es nicht.</div>`;
  if (!kind) return indexPage(wiki.index, wiki.own);
  const body = kind === 'band' ? bandPage(wiki.article) : kind === 'signie' ? signiePage(wiki.article) : labelPage(wiki.article);
  return `${back}${searchBox()}<div class="wk-results" hidden></div>${body}`;
}

/** Suche über alle Labels: tippt man, kommen nach einer kurzen Pause die Treffer vom Server; Enter öffnet den ersten. */
let searchTimer = null;
let hits = [];
document.addEventListener('input', (e) => {
  const field = e.target.closest?.('[data-wiki-search]');
  if (!field) return;
  clearTimeout(searchTimer);
  const box = document.querySelector('.wk-results');
  const q = field.value.trim();
  if (q.length < 2) {
    hits = [];
    if (box) box.hidden = true;
    return;
  }
  searchTimer = setTimeout(async () => {
    try {
      const res = await api('wikiSearch', { q });
      if (field.value.trim() !== q || !box) return;
      hits = [...res.bands.map((b) => ['band', b]), ...res.signies.map((s) => ['signie', s])];
      box.hidden = false;
      box.innerHTML = hits.length
        ? `<ul>${hits.map(([k, x]) => `<li>${link(k, x.id, x.name, x.status === 'former')} <small class="muted">${k === 'band' ? 'Band' : 'Signie'}${x.label ? ` · ${esc(x.label)}` : ''}</small></li>`).join('')}</ul>`
        : '<p class="muted">Dazu gibt es keinen Artikel.</p>';
    } catch (err) {
      toast(err.message, 'error');
    }
  }, 250);
});
document.addEventListener('keydown', (e) => {
  if (e.key !== 'Enter' || !e.target.closest?.('[data-wiki-search]') || !hits.length) return;
  location.hash = `#/wiki/${hits[0][0]}/${hits[0][1].id}`;
});

defineActions({});
