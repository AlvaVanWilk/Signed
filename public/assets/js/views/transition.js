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
  openSlides([...NEWS, ['gift', 'Dein Umbau', summary(t)]], {
    dismissable: false,
    onDone: () => (t.pool.length ? choosePool(t) : finish([])),
  });
}

function summary(t) {
  const lines = [];
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
