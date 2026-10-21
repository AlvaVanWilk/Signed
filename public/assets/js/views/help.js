import { app, cat, defineActions } from '../core.js';
import { esc, fmt0, openModal } from '../ui.js';

/**
 * Einführung für alle, auch für Kinder: wenige Schritte, ein Satz pro Schritt, große Bilder.
 * Wer mehr wissen will, findet alles in der Hilfe (#/hilfe).
 */
const TOUR = [
  ['🎤', 'Du hast ein Plattenlabel!', 'Sammle Musiker – beim Talent-Scout und in den Läden.'],
  ['🎸', 'Gründe eine Band', 'Gib jedem ein eigenes Instrument. Dann klingt die Band besser.'],
  ['🥁', 'Übe!', 'Spiel im Takt mit. So werden deine Musiker besser.'],
  ['🎪', 'Tritt auf', 'Auftritte bringen Geld und Ruhm.'],
  ['💿', 'Nimm Platten auf', 'Kommst du auf Platz 1 der Charts?'],
];

const tourKey = () => `signed.tour.${app.label?.id ?? 0}`;

/** Tour als gesehen merken; liefert true, wenn sie auf diesem Gerät noch nicht lief. */
export function claimTour() {
  try {
    if (localStorage.getItem(tourKey())) return false;
    localStorage.setItem(tourKey(), '1');
    return true;
  } catch {
    return false;
  }
}

/** Beim ersten Mal auf diesem Gerät die Tour zeigen. */
export function maybeStartTour() {
  if (claimTour()) openTour();
}

/** onDone läuft nach dem letzten Schritt (z. B. die Begrüßung eines neuen Labels). */
export function openTour(onDone = null) {
  let step = 0;
  const modal = openModal({
    className: 'tour-modal',
    render: () => {
      const [icon, title, text] = TOUR[step];
      const last = step === TOUR.length - 1;
      return `
        <div class="tour">
          <div class="tour-icon">${icon}</div>
          <h2>${esc(title)}</h2>
          <p>${esc(text)}</p>
          <div class="tour-dots">${TOUR.map((_, i) => `<i class="${i === step ? 'on' : ''}"></i>`).join('')}</div>
          <button class="btn primary big" data-tour-next>${last ? 'Los geht’s!' : 'Weiter'}</button>
          ${last ? `
            <p class="small muted">Mehr wissen? <a href="#/hilfe" data-tour-close>Zur Hilfe</a>
              · Bluetooth-Kopfhörer? <button class="link" data-act="calibrate">Einstellen</button></p>` : ''}
        </div>`;
    },
  });
  modal.body.addEventListener('click', (e) => {
    if (e.target.closest('[data-tour-close]')) modal.close();
    if (!e.target.closest('[data-tour-next]')) return;
    if (step === TOUR.length - 1) {
      modal.close();
      onDone?.();
      return;
    }
    step++;
    modal.refresh();
  });
}

/** Hilfe für alle, die tiefer einsteigen wollen. Kurz pro Thema; die genauen Zahlen stehen im Spiel an den Werten. */
export function render() {
  const r = cat().rules;
  const topics = [
    ['🎵', 'Musiker', `Jeder Musiker spielt ein oder zwei Instrumente. <b>Können</b> (Kästchen) steigt durch Üben, Proben und Platten – grün ab 35, blau ab 60, lila ab 85. Mit jeder Stufe lernen Musiker eine Fähigkeit. <b>Bekanntheit</b> (Sterne) kommt von Auftritten und Promo.`],
    ['🎸', 'Instrumente', 'Ohne eigenes Instrument zählt ein Musiker nur halb. Ein gutes Instrument entfaltet seinen vollen Klang erst ab einem bestimmten Können – für Anfänger ist ein einfaches oft besser. Der grüne Pfeil zeigt, wem ein Instrument im Lager helfen würde.'],
    ['👥', 'Band und Genre', `Mögen alle Mitglieder dasselbe Genre und passt die Besetzung, wird die Band eine Genre-Band (+${fmt0((r.genreBonus - 1) * 100)} % Sound). Mögen alle noch ein zweites, wird sie hybrid. Weitere Bands gibt es mit mehr Label-Ruhm. <b>Zusammenspiel</b> wächst durch Proben, <b>Chemie</b> hängt vom Charakter ab.`],
    ['🎪', 'Auftritte', 'Straßenmusik und Open Stage gehen immer, richtige Gigs erst mit einer Platte. Je öfter ihr seit der letzten Platte spielt, desto mehr kennt das Publikum eure Lieder – eine neue Platte macht die Gigs wieder voll. Ab genug Ruhm spielt ihr in größeren Läden.'],
    ['💿', 'Platten und Charts', `Im Studio gibt es so viele Aufnahmen wie Lieder: Instrumente einspielen, am Mischpult mischen und zum Schluss der Master-Mix. Danach steigt die Platte in die Charts ein (Top ${fmt0(r.chartSize)}). Gigs und Promo schieben sie an. Je höher der Platz, desto mehr Tantiemen und Ruhm – und volle Gigs. Vor der nächsten Platte müsst ihr die letzte live spielen.`],
    ['💰', 'Geld', 'Geld kommt von Gigs, vom Verkauf neuer Platten und laufend als Tantiemen, solange eine Platte sich verkauft. Ausgeben kannst du es in den Läden, im Studio und an der Wechselbörse. Ein Klick aufs Geld zeigt das Konto.'],
    ['🔁', 'Wechselbörse und Jam-Café', 'Musiker ohne Band kannst du an der Börse anbieten oder dort kaufen. Im Jam-Café lernt ein Musiker ohne Band andere kennen und gründet mit einem davon eine Band.'],
    ['🎧', 'Kopfhörer', 'Hörst du die Töne später, als du sie siehst (oft bei Bluetooth)? Dann stell deine Kopfhörer einmal ein – das gilt danach für dieses Gerät.'],
  ];
  return `
    <div class="section-head">
      <div><h1>Hilfe</h1><p class="muted">Alles Wichtige zum Nachlesen. Die genauen Zahlen stehen im Spiel direkt an den Werten.</p></div>
      <div class="tile-buttons">
        <button class="btn" data-act="startTour">Einführung noch mal</button>
        <button class="btn" data-act="calibrate">Kopfhörer einstellen</button>
      </div>
    </div>
    <div class="help-grid">${topics.map(([icon, title, text]) => `
      <section class="panel help-topic"><h2>${icon} ${esc(title)}</h2><p>${text}</p></section>`).join('')}
    </div>`;
}

defineActions({
  startTour() {
    openTour();
  },
});
