import { api } from '../api.js';
import { app, bandById, cat, defineActions, mutate, serverNow } from '../core.js';
import { coverHtml } from '../play.js';
import { playedInstrument, stage } from '../figure.js';
import { producerFigure } from '../producers.js';
import { studioSvg } from '../studio.js';
import { duration, esc, fmt0, money, off, openModal, percent, popover, toast } from '../ui.js';
import { suggestName } from './bands.js';

/**
 * Aufnehmen: Dialog mit Songzahl, Produzent, Studio, Titel und Cover; die Schätzung zeigt, was es kostet und was es bringt.
 * Während der Aufnahme steht die Band im Studio, hinter der Scheibe sitzt der Produzent.
 */
const PRODUCER_KEYS = ['none', 'mo', 'roxy', 'diamond', 'legend'];
const PRODUCER_LINE = {
  none: 'Ihr macht alles selbst.',
  mo: 'Junger Beatbastler, bringt euch ins Tonstudio.',
  roxy: 'Rockt jedes Pult, bringt euch ins Profi-Studio.',
  diamond: 'Macht Hits mit Glanz – auch im legendären Studio.',
  legend: 'Holt aus jeder Band das Echte heraus. Überall. Nimmt aber nicht jede.',
};
/** Wer „die Band“ ist: bei Solokünstlern der Name. */
const who = (b, lower = false) => {
  const members = b.memberIds.map((id) => app.state.musicians.find((m) => m.id === id)).filter(Boolean);
  return members.length === 1 ? (members[0].stageName ?? members[0].firstName) : lower ? 'die Band' : 'Die Band';
};
const solo = (b) => b.memberIds.length === 1;
const CLAIM = (gap, b) => (gap < 0 ? ['no', `Das ist unter ${solo(b) ? 'der' : 'ihrer'} Würde – ${who(b, true)} ärgert sich.`] : gap > 0 ? ['ok', `${who(b)} freut sich über so ein Studio.`] : ['', solo(b) ? 'Passt gut.' : 'Passt zur Band.']);

/** Was im Dialog gewählt ist (bleibt beim Neuzeichnen erhalten). */
let pick = null;

function optionFor(data, producer, studio) {
  return data.options.find((o) => o.producer === producer && o.studio === studio);
}

function dialogHtml(b, data) {
  const studios = cat().rules.studios;
  const producers = cat().rules.producers;
  const songsMax = Math.min(cat().rules.recordMaxSongs, data.songsWritten);
  if (data.blocker) {
    const c = b.record.cycle;
    const step = (label, { have, need }) => `<li class="${have >= need ? 'ok' : ''}">${have >= need ? '✓' : '○'} ${label} <b>${fmt0(have)}/${fmt0(need)}</b></li>`;
    return `
      <div class="lb-cap">Aufnehmen</div><h2>${esc(b.name)}</h2>
      <p>${esc(data.blocker)}</p>
      ${c && !c.done ? `<ul class="rc-cycle">${step(`Lieder der letzten Platte live gespielt (${c.plays}× das Album)`, c.songs)}${step(solo(b) ? 'Promo' : 'Band-Promo', c.promos)}</ul>` : ''}
      <div class="dialog-actions"><button class="btn primary" data-close>Okay</button></div>`;
  }
  if (data.songsWritten < cat().rules.recordMinSongs) {
    return `
      <div class="lb-cap">Aufnehmen</div><h2>${esc(b.name)}</h2>
      <p>Für eine Platte braucht ${esc(who(b, true))} mindestens ${cat().rules.recordMinSongs} fertige Songs. Geschrieben sind erst ${data.songsWritten}.</p>
      <p class="muted">Songs entstehen von selbst, solange ${esc(who(b, true))} gut gelaunt ist.</p>
      <div class="dialog-actions"><button class="btn primary" data-close>Okay</button></div>`;
  }
  const cur = optionFor(data, pick.producer, pick.studio);
  const can = !cur.refusal && app.state.label.money >= cur.cost && pick.cover !== null && pick.title.trim() !== '';
  const why = cur.refusal ?? (app.state.label.money < cur.cost ? `Dafür fehlen noch ${money(cur.cost - app.state.label.money)}.` : pick.title.trim() === '' ? 'Die Platte braucht einen Titel.' : '');
  const [claimCls, claimText] = CLAIM(cur.claim, b);
  const total = (x) => cur.money + x;
  const low = total(cur.royalties[0]) - cur.cost;
  const high = total(cur.royalties[1]) - cur.cost;
  return `
    <div class="lb-cap">Aufnehmen</div><h2>${esc(b.name)} ${solo(b) ? 'geht' : 'geht'} ins Studio</h2>
    <div class="rc-row"><span class="rc-label">Lieder</span>
      <span class="rc-stepper"><button class="btn small ghost" data-r="songs" data-v="-1" ${off(pick.songs <= cat().rules.recordMinSongs ? 'Weniger geht nicht.' : '')}>−</button>
      <b>${pick.songs}</b><button class="btn small ghost" data-r="songs" data-v="1" ${off(pick.songs >= songsMax ? (songsMax < cat().rules.recordMaxSongs ? `Mehr Songs hat ${who(b, true)} noch nicht geschrieben.` : 'Mehr passt nicht auf eine Platte.') : '')}>+</button></span>
      <small class="muted">${pick.songs >= 7 ? 'Album (LP)' : 'EP'} · ${data.songsWritten} Songs fertig · dauert ${duration(data.seconds)}</small></div>

    <h3 class="rc-h">Produzent</h3>
    <div class="rc-cards">${PRODUCER_KEYS.map((k) => {
    const o = optionFor(data, k, Math.min(pick.studio, producers[k].unlocks));
    const refused = k === 'legend' && o.refusal;
    return `<button class="rc-card rc-prod ${pick.producer === k ? 'on' : ''}" data-r="producer" data-v="${k}" ${off(refused ? o.refusal : '')}>
        <span class="rc-pic">${k === 'none' ? '<span class="rc-none">–</span>' : producerFigure(k)}</span>
        <b>${esc(producers[k].name)}</b><small>${producers[k].fee ? money(producers[k].fee) : 'kostenlos'}</small></button>`;
  }).join('')}</div>
    <p class="rc-say muted">${esc(PRODUCER_LINE[pick.producer])}</p>

    <h3 class="rc-h">Studio</h3>
    <div class="rc-cards">${studios.map((s, i) => {
    const o = optionFor(data, pick.producer, i);
    return `<button class="rc-card rc-studio ${pick.studio === i ? 'on' : ''}" data-r="studio" data-v="${i}" ${off(o.refusal ?? '')}>
        <span class="rc-thumb">${studioSvg(i, 'none')}</span><b>${esc(s.name)}</b><small>${money(s.perSong)} je Lied</small></button>`;
  }).join('')}</div>

    <div class="rc-sum">
      <div><span>Kosten</span><b>${money(cur.cost)}</b></div>
      <div><span>Qualität</span><b>${percent(cur.quality)}${cur.perfect ? ' · perfekt' : ''}</b><i class="rc-bar"><i style="width:${Math.round(cur.quality * 100)}%"></i></i></div>
      <div><span>Bringt</span><b>${money(total(cur.royalties[0]))} – ${money(total(cur.royalties[1]))}</b><small>Verkauf und Tantiemen, solange sie chartet</small></div>
      <div><span>Unterm Strich</span><b class="${high < 0 ? 'neg' : low >= 0 ? 'pos' : ''}">${low >= 0 ? '+' : ''}${money(low)} bis ${high >= 0 ? '+' : ''}${money(high)}</b></div>
    </div>
    <p class="rc-claim ${claimCls}">${esc(claimText)}${pick.producer === 'legend' ? ' Mit Ruben Stille sind alle ganz entspannt.' : ''}</p>

    <h3 class="rc-h">Titel</h3>
    <span class="input-dice"><input data-r="title" maxlength="60" value="${esc(pick.title)}" placeholder="Titel der Platte"><button class="btn small" data-r="dice" title="Zufälliger Titel">🎲</button></span>

    <h3 class="rc-h">Cover <small class="muted">Entwürfe vom Grafikbüro</small></h3>
    <div class="rc-covers">${pick.covers.map((c, i) => `<button class="rc-cover ${pick.cover === i ? 'on' : ''}" data-r="cover" data-v="${i}">${coverHtml(c, { band: b.name, title: pick.title || ' ' })}</button>`).join('')}</div>
    <button class="btn small ghost" data-r="redraw" ${off(app.state.label.money < cat().rules.coverRedraw ? 'Dafür reicht das Geld nicht.' : '')}>Neue Entwürfe · ${money(cat().rules.coverRedraw)}</button>

    <div class="dialog-actions"><button class="btn ghost" data-close>Abbrechen</button>
      <button class="btn primary" data-r="start" ${off(can ? '' : why || 'Bitte wähle ein Cover.')}>Ins Studio · ${money(cur.cost)}</button></div>`;
}

async function recordAsk(bandId) {
  const b = bandById(bandId);
  if (!b) return;
  const want = Math.max(cat().rules.recordMinSongs, Math.min(4, b.mood.songs));
  let data;
  try {
    data = await api('recordOptions', { bandId, songs: want });
  } catch (e) {
    toast(e.message, 'error');
    return;
  }
  pick = { bandId, songs: data.songs, producer: 'none', studio: 0, title: '', covers: data.covers ?? [], cover: 0 };
  const modal = openModal({ className: 'rc-modal', wide: true, render: () => dialogHtml(bandById(bandId) ?? b, data) });
  const reload = async () => {
    try {
      data = await api('recordOptions', { bandId, songs: pick.songs, noCovers: 1 });
      modal.refresh();
    } catch (e) {
      toast(e.message, 'error');
    }
  };
  modal.body.addEventListener('input', (e) => {
    if (e.target.dataset.r === 'title') pick.title = e.target.value;
  });
  modal.body.addEventListener('change', (e) => {
    if (e.target.dataset.r === 'title') modal.refresh();
  });
  modal.body.addEventListener('click', async (e) => {
    if (e.target.closest('[data-close]')) {
      modal.close();
      return;
    }
    const el = e.target.closest('[data-r]');
    if (!el || el.dataset.r === 'title') return;
    if (el.getAttribute('aria-disabled') === 'true') {
      popover(el, el.dataset.why);
      return;
    }
    const v = el.dataset.v;
    switch (el.dataset.r) {
      case 'songs':
        pick.songs += Number(v);
        await reload();
        return;
      case 'producer':
        pick.producer = v;
        // Höher als der Produzent es erlaubt? Dann in das beste Studio, in das er die Band bringt.
        pick.studio = Math.min(pick.studio, cat().rules.producers[v].unlocks);
        break;
      case 'studio':
        pick.studio = Number(v);
        break;
      case 'cover':
        pick.cover = Number(v);
        break;
      case 'dice':
        pick.title = await suggestName('record');
        break;
      case 'redraw': {
        const res = await mutate('coverRedraw');
        if (res) {
          pick.covers = res.covers;
          pick.cover = 0;
        }
        break;
      }
      case 'start': {
        const res = await mutate('recordStart', { bandId, title: pick.title, songs: pick.songs, studio: pick.studio, producer: pick.producer, cover: pick.covers[pick.cover] });
        if (res) {
          modal.close();
          toast(`${solo(b) ? who(b) : b.name} ist im Studio.`, 'ok');
        }
        return;
      }
      default:
        return;
    }
    modal.refresh();
  });
}

/** Bandseite während der Aufnahme: das Studio mit Produzent hinter der Scheibe, die Band davor. Mit Ton zum Zuhören. */
export function studioBlock(b, members, act, { soundOn = false } = {}) {
  const rec = act.record ?? { studio: 0, producer: 'none', quality: 0 };
  const studio = cat().rules.studios[rec.studio]?.name ?? 'Studio';
  const producer = rec.producer !== 'none' ? cat().rules.producers[rec.producer]?.name : null;
  const bpm = cat().genres[b.genres?.[0] ?? members[0]?.genres?.[0]]?.bpm ?? 110;
  // Klingt es richtig gut, gehen die Produzenten mehr mit (Ruben schließt die Augen).
  const hype = rec.quality >= 0.8;
  return `
    <section class="lb-live" data-sound-act="${act.id}">
      <div class="lb-live-head">
        <div><h2 class="lb-cap">${members.length === 1 ? 'Nimmt auf' : 'Die Band nimmt auf'}</h2>
          <small class="muted">${esc(act.title)} · ${esc(studio)}${producer ? ` mit ${esc(producer)}` : ''} · fertig in <span class="countdown" data-until="${act.endsAt}">${duration(Math.max(0, act.endsAt - serverNow()))}</span></small></div>
        <span class="lb-live-btns"><button class="btn small ghost" data-act="liveSound" data-id="${act.id}">${soundOn ? 'Ton aus' : 'Zuhören'}</button></span>
      </div>
      <div class="lb-room st-room" style="--beat:${(60 / bpm).toFixed(3)}s">${studioSvg(rec.studio, rec.producer, { live: true, hype })}${stage(members, {
    className: 'in-room',
    bpm,
    act: 'musicianInfo',
    instrumentOf: (m) => playedInstrument(m),
  })}</div>
    </section>`;
}

/** Ergebnis, wenn die Platte erschienen ist. */
export function recordResultHtml(r, { reward, levelUpBanners }) {
  const res = r.result;
  const band = bandById(r.bandId);
  const producer = res.producer && res.producer !== 'none' ? cat().rules.producers[res.producer]?.name : null;
  return `
    <div class="eyebrow">${esc(band?.name ?? '')}</div>
    <h2>„${esc(res.title)}“ ist erschienen!</h2>
    <div class="rc-release">${coverHtml(res.cover, { band: band?.name ?? '', title: res.title })}
      <p class="muted">${res.format}, ${res.songs} Lieder${producer ? `, produziert von ${esc(producer)}` : ''}.${res.perfect ? ' <b>Eine perfekte Platte!</b>' : ''}</p></div>
    <div class="reward-grid">
      ${reward('Erstverkauf', `+${money(0)}`, { to: res.money, kind: 'money' })}
      ${reward('Ruhm', `+${fmt0(0)}`, { to: res.fame, kind: 'fame' })}
      ${reward('Qualität', percent(res.quality))}
      ${reward('Chartpunkte', fmt0(res.chartPoints))}
    </div>
    ${levelUpBanners(res.levelUps ?? [])}
    <p class="muted small">Jetzt läuft sie in den Charts. Auftritte und Promo schieben sie an – und das Label gibt das nächste Album frei, wenn sie unter die Leute gebracht ist.</p>`;
}

defineActions({
  recordAsk({ id }) {
    recordAsk(Number(id));
  },
});

