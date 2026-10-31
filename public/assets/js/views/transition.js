import { app, cat, mutate } from '../core.js';
import { esc, money, openModal, topModal } from '../ui.js';
import { fullName, genreName, instrumentIcon, instrumentName } from '../cards.js';
import { portrait } from '../figure.js';
import { claimTour, openSlides } from './help.js';

/**
 * Einmal nach dem Umbau (State.transition): „Was ist neu?“ für Labels aus dem alten Spiel, danach die Wahl aus der Kartei
 * (bis zu Balance::TRANSITION['keep'] Musiker bleiben). Erst mit transitionDone ist der Übergang abgeschlossen.
 */

const NEWS = [
  ['update', 'Signed ist umgebaut', 'Dein Label, deine Bands, dein Geld und deine Platten sind alle noch da. Neu ist: Deine Musiker haben jetzt ein eigenes Leben.'],
  ['mood', 'Stimmung und Wünsche', 'Jeder Musiker ist grün, gelb oder rot. Jeder Charakter mag andere Dinge. Wer unzufrieden ist, wünscht sich etwas, und ein Manager sagt dir, was.'],
  ['orders', 'Proben statt Üben', 'Besser werden deine Musiker jetzt beim Proben mit ihrer Band. Die Minispiele zum Üben gibt es nicht mehr.'],
  ['post', 'Post statt Packs', 'Neue Musiker schicken Kassetten in den Posteingang. Scouts, Manager und Lehrer bewerben sich dort. Packs, Karten und die Wechselbörse gibt es nicht mehr.'],
  ['week', 'Jede Woche ist was los', 'Mittwochs gibt es ein Festival, samstags das Battle of the Bands. Wer bekannt genug ist, geht mit Bus oder Flugzeug auf Tournee. Dazu Auszeichnungen im Proberaum.'],
];

/** Übergang ins neue Spiel mit Fans und Hype (State.transition.core). */
const CORE_NEWS = [
  ['band', 'Fans statt Ruhm', 'Der Erfolg einer Band sind jetzt ihre Fans. Fans kaufen Platten, kommen zu Auftritten und erzählen es weiter. Alle Bands fangen bei 0 Fans an, so erleben alle das neue Spiel von vorn.'],
  ['charts', 'Hype', 'Jede neue Platte bringt Hype: Je besser sie ist, desto mehr. Mit viel Hype kaufen viele die Platte, und viele kommen zu Auftritten. Jeden Tag ist der Hype halb so groß. Anfragen, Festival und Battle geben einen Schub.'],
  ['gig', 'Auftreten', 'Es gibt nur noch einen Knopf: Auftreten. Das Spiel nimmt den passenden Ort, Kosten gibt es keine, das Label verdient an jedem Zuschauer. Gleich noch einmal auftreten bringt weniger Leute; nach einem Tag Pause kommen wieder alle.'],
  ['record', 'Platten', 'Ein Album hat 12 Songs, jeder Song hat seine eigene Qualität. Die Qualität der Platte kommt aus Sound, Songs, Produktion und Stimmung. Die Charts zählen die Verkäufe der letzten 24 Stunden.'],
  ['course', 'Können', 'Können wird jetzt größer gezählt: Fortgeschritten ab 100, Profi ab 400, Virtuose ab 1.600. Wer vorher Profi war, ist es auch jetzt. Eine Probe am Tag hält den Sound auf der Höhe.'],
];

let open = false;
let waiting = null;

/** Beim Rendern aufrufen: zeigt den Übergang, solange er nicht abgeschlossen ist (einmal je Seitenaufruf, erst wenn kein anderes Fenster offen ist). */
export function maybeShowTransition() {
  const t = app.state?.transition;
  if (!t || open) return;
  if (topModal()) {
    // Erst die Ergebnisse von früher wegklicken lassen, dann geht es los.
    waiting ??= setTimeout(() => {
      waiting = null;
      maybeShowTransition();
    }, 800);
    return;
  }
  open = true;
  // Wer aus dem alten Spiel kommt, braucht die Einführung für Neue nicht mehr.
  claimTour();
  const news = t.core ? [...(t.old ? NEWS : []), ...CORE_NEWS] : NEWS;
  openSlides([...news, ['gift', 'Dein Umbau', summary(t)]], {
    dismissable: false,
    onDone: () => (t.pool.length ? choosePool(t) : finish([])),
  });
}

function summary(t) {
  const lines = [];
  if (t.core) {
    const c = t.core;
    const g = c.gear;
    lines.push(`Jedes Label startet mit ${money(c.start)}${c.money ? ` (vorher ${money(c.money)})` : ''}.`);
    if (g.swapped) lines.push(g.swapped === 1 ? 'Ein Instrument war besser, als der Musiker es spielen darf: Der Music Store hat es in seine Farbe umgetauscht.' : `${g.swapped} Instrumente waren besser, als ihre Musiker sie spielen dürfen: Der Music Store hat sie umgetauscht.`);
    if (g.expired) lines.push(`Was im Lager lag, ist weg (${g.expired === 1 ? 'ein Instrument' : `${g.expired} Instrumente`}). Jeder Musiker behält sein eigenes.`);
    if (g.given) lines.push(g.given === 1 ? 'Ein Musiker ohne Instrument hat eins bekommen.' : `${g.given} Musiker ohne Instrument haben eins bekommen.`);
    if (c.fame?.length) lines.push(`Der alte Ruhm steht als Erinnerung in der Chronik (${c.fame.map((f) => `${f.band}: ${f.fame}`).join(', ')}).`);
    lines.push('Können und Beziehungen sind geblieben.');
    if (!t.old) return lines;
  }
  if (t.money > 0) lines.push(`Für alte Karten und Gutscheine bekommst du ${money(t.money)}.`);
  if (t.market > 0) lines.push(t.market === 1 ? 'Ein Musiker von der Wechselbörse ist zurück bei dir.' : `${t.market} Musiker von der Wechselbörse sind zurück bei dir.`);
  lines.push('Wer schon lange zusammen spielt, kennt sich gut: Die Beziehungen in deinen Bands sind schon da.');
  if (t.pool.length) lines.push(`Die Kartei gibt es nicht mehr. Such dir gleich bis zu ${t.keep} Musiker aus, die bleiben dürfen.`);
  return lines;
}

const levelOf = (skill) => cat().rules.skillLevels.filter((x) => skill >= x).length;

function card(m, on) {
  const instruments = m.instruments.map((i) => `<span class="tr-ins">${instrumentIcon(i.type)}${esc(instrumentName(i.type))} <small>${esc(cat().levelNames[levelOf(i.skill)] ?? '')}</small></span>`).join('');
  return `<button class="tr-card ${on ? 'on' : ''}" data-pick="${m.id}" aria-pressed="${on}">
    <span class="tr-face">${portrait(m.look)}</span>
    <span class="tr-info"><b>${esc(m.stageName ?? fullName(m))}</b>${instruments}<small class="muted">${esc(m.genres.map(genreName).join(', '))}</small></span>
    <span class="tr-check" aria-hidden="true">${on ? '✓' : ''}</span>
  </button>`;
}

/** Kartei: bis zu t.keep Musiker auswählen; die übrigen verlassen das Label. */
function choosePool(t) {
  const picked = new Set();
  const modal = openModal({
    wide: true,
    className: 'tr-modal',
    dismissable: false,
    render: () => `
      <div class="eyebrow">Deine Kartei</div>
      <h2>Wer darf bleiben?</h2>
      <p class="muted">Such dir bis zu ${t.keep} Musiker aus. Sie bleiben als Solokünstler und können später in eine Band. Die anderen verlassen das Label.</p>
      <div class="tr-grid">${t.pool.map((m) => card(m, picked.has(m.id))).join('')}</div>
      <div class="dialog-actions">
        <span class="muted small">${picked.size} von ${t.keep} ausgewählt</span>
        <button class="btn primary" data-keep>${picked.size ? (picked.size === 1 ? 'Diesen Musiker behalten' : `Diese ${picked.size} behalten`) : 'Niemanden behalten'}</button>
      </div>`,
  });
  modal.body.addEventListener('click', async (e) => {
    const pick = e.target.closest('[data-pick]');
    if (pick) {
      const id = Number(pick.dataset.pick);
      if (picked.has(id)) picked.delete(id);
      else if (picked.size < t.keep) picked.add(id);
      modal.refresh();
      return;
    }
    if (!e.target.closest('[data-keep]')) return;
    if (await finish([...picked])) modal.close();
  });
}

async function finish(keep) {
  const res = await mutate('transitionDone', { keep });
  open = false;
  return res !== null;
}
