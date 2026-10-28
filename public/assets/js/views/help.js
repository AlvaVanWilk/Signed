import { app, cat, defineActions } from '../core.js';
import { esc, fmt0, openModal } from '../ui.js';

/**
 * Einführung und Hilfe, für alle verständlich (auch für Kinder): kurze Sätze, ruhige Bilder.
 * Die Einführung zeigt beim ersten Mal auf einem Gerät die Grundidee in sechs Schritten; die Hilfe (#/hilfe) erklärt jedes Thema.
 */

/** Schlichte Bilder (Linien), passend zum übrigen Spiel. */
const ICON = {
  label: '<path d="M12 3a9 9 0 1 0 0 18a9 9 0 0 0 0-18Z"/><circle cx="12" cy="12" r="3"/><path d="M12 6.5a5.5 5.5 0 0 1 5.5 5.5"/>',
  post: '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 8 L12 14 L21 8"/>',
  band: '<circle cx="7" cy="8" r="2.6"/><circle cx="17" cy="8" r="2.6"/><circle cx="12" cy="6" r="2.6"/><path d="M2.5 19c.5-3.5 2.2-5.5 4.5-5.5s4 2 4.5 5.5M12.5 19c.5-3.5 2.2-5.5 4.5-5.5s4 2 4.5 5.5"/>',
  orders: '<path d="M5 20 L9 4 H12 L16 20 Z"/><path d="M12 14 L18 6"/><circle cx="18" cy="6" r="1.3"/>',
  mood: '<rect x="8" y="2.5" width="8" height="19" rx="3"/><circle cx="12" cy="7" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="17" r="1.7"/>',
  week: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10 H21 M8 3 V7 M16 3 V7"/><path d="M8 14.5 l2.4 2.3 L16 13"/>',
  musician: '<circle cx="12" cy="7.5" r="3.5"/><path d="M5 21c.6-4.6 3.3-7 7-7s6.4 2.4 7 7"/>',
  manager: '<rect x="5" y="3.5" width="14" height="17" rx="2"/><path d="M9 3.5 V5.5 H15 V3.5"/><path d="M8.5 10 H15.5 M8.5 13.5 H15.5 M8.5 17 H12.5"/>',
  gig: '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M6 11 a6 6 0 0 0 12 0 M12 17 V21 M8.5 21 H15.5"/>',
  record: '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3.4"/><path d="M12 3 V6.6"/>',
  promo: '<path d="M4 10 V14 H7.5 L16 19 V5 L7.5 10 Z"/><path d="M19 9 a4.5 4.5 0 0 1 0 6"/>',
  store: '<path d="M4 9 L5.5 4 H18.5 L20 9"/><path d="M4 9 H20 V20 H4 Z"/><path d="M10 20 V14 H14 V20"/>',
  course: '<path d="M2.5 9 L12 4.5 L21.5 9 L12 13.5 Z"/><path d="M6.5 11 V16 c3.5 2.5 7.5 2.5 11 0 V11"/>',
  battle: '<path d="M7 3 H17 V9 C17 12 15 14 12 14 C9 14 7 12 7 9 Z"/><path d="M7 5 H4 C4 8 5 10 7.5 10.5 M17 5 H20 C20 8 19 10 16.5 10.5 M12 14 V18 M8.5 21 H15.5 L14.5 18 H9.5 Z"/>',
  festival: '<path d="M3 20 L12 5 L21 20 Z"/><path d="M12 5 V2.5 M12 20 V14 M9.5 20 L12 14 L14.5 20"/>',
  tour: '<circle cx="12" cy="12" r="9"/><path d="M3 12 H21 M12 3 C8 7 8 17 12 21 M12 3 C16 7 16 17 12 21"/>',
  award: '<circle cx="12" cy="9" r="6"/><path d="M8.5 13.8 L7 21.5 L12 19 L17 21.5 L15.5 13.8"/><path d="M12 6 l1 2 2.2.3 -1.6 1.5 .4 2.2 -2-1.1 -2 1.1 .4-2.2 -1.6-1.5 2.2-.3 Z"/>',
  charts: '<path d="M4 20 V12 M10 20 V6 M16 20 V9 M3 20.5 H21"/><path d="M16 9 l2.5-4.5"/>',
};
export const icon = (key, cls = 'help-ico') => `<svg class="${cls}" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">${ICON[key]}</svg>`;

/** Die Einführung: sechs Schritte, ein Bild, zwei kurze Sätze. */
const TOUR = [
  ['label', 'Du hast ein Plattenlabel!', 'Du bist der Chef. Deine Bands machen Musik, und du entscheidest, was sie als Nächstes tun.'],
  ['post', 'Post für dich', 'Im Posteingang kommen Kassetten von Musikern und Bands. Hör rein und nimm die Guten unter Vertrag.'],
  ['band', 'Gründe eine Band', 'Musiker, die dasselbe Genre mögen, klingen zusammen am besten. Und jeder braucht ein Instrument.'],
  ['orders', 'Proben, auftreten, aufnehmen', 'Proben macht die Musiker besser. Auftritte bringen Geld und Ruhm. Mit Platten kommst du in die Charts.'],
  ['mood', 'Achte auf die Stimmung', 'Grün heißt: gut drauf. Wird jemand gelb oder rot, wünscht er sich etwas. Ein Manager sagt dir, was.'],
  ['week', 'Jede Woche ist was los', 'Mittwochs gibt es ein Festival, samstags das Battle of the Bands. Und auf Tournee geht es um die ganze Welt.'],
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
      const [key, title, text] = TOUR[step];
      const last = step === TOUR.length - 1;
      return `
        <div class="tour">
          <div class="tour-icon">${icon(key, 'tour-svg')}</div>
          <h2>${esc(title)}</h2>
          <p>${esc(text)}</p>
          <div class="tour-dots">${TOUR.map((_, i) => `<i class="${i === step ? 'on' : ''}"></i>`).join('')}</div>
          <div class="tour-btns">${step ? '<button class="btn ghost" data-tour-back>Zurück</button>' : ''}<button class="btn primary big" data-tour-next>${last ? 'Los geht’s!' : 'Weiter'}</button></div>
          ${last ? '<p class="small muted">Mehr wissen? <a href="#/hilfe" data-tour-close>Zur Hilfe</a> (das Fragezeichen oben)</p>' : ''}
        </div>`;
    },
  });
  modal.body.addEventListener('click', (e) => {
    if (e.target.closest('[data-tour-close]')) modal.close();
    if (e.target.closest('[data-tour-back]') && step > 0) {
      step--;
      modal.refresh();
      return;
    }
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

/** Themen der Hilfe in Gruppen: [Bild, Titel, Text (HTML)]. Genaue Zahlen stehen im Spiel an den Werten. */
function topics() {
  const r = cat().rules;
  const aw = cat().awards?.records ?? {};
  const n = (x) => fmt0(x ?? 0);
  const battle = app.state?.battle?.next?.when;
  const festival = app.state?.festival?.next;
  return [
    ['Los geht’s', [
      ['label', 'Dein Label', `Du führst ein Plattenlabel. Mit Geld nimmst du Musiker unter Vertrag, kaufst Instrumente und bezahlst Aufnahmen. Geld kommt von Auftritten, Platten und Preisen. Ein Klick aufs Geld oben zeigt dein Konto. Der <b>Label-Ruhm</b> zeigt, wie bekannt deine Bands zusammen sind; große Bands zählen dabei stärker.`],
      ['post', 'Posteingang', `Hier kommt die Post an. <b>Kassetten</b> sind Musiker oder ganze Bands, die zu dir wollen: Hör rein (dann siehst du, wie gut sie sind) und nimm sie unter Vertrag, wenn sie dir gefallen. <b>Briefe</b> sind Anfragen, zum Beispiel für Interviews. <b>Bewerbungen</b> kommen von Scouts, Lehrern, Song-Coaches, Managern und Praktikanten. Post bleibt nur ein paar Tage liegen. Eine <b>Assistenz</b> kann sortieren, Kassetten anhören und Briefe lesen.`],
      ['band', 'Bands', `Jeder Musiker unter Vertrag steht in der Leiste links, allein als Solokünstler oder in einer Band. Mit „zusammentun“ werden aus Solokünstlern Bands. Mögen alle dasselbe <b>Genre</b> und passt die Besetzung (zum Beispiel Gitarre und Schlagzeug für Rock), wird es eine Genre-Band und klingt besser (+${n((r.genreBonus - 1) * 100)} %). Ein zweites gemeinsames Genre macht sie hybrid, dann klingt sie noch besser. Eine Band hat höchstens ${n(r.maxMembers)} Musiker.`],
    ]],
    ['Musiker und Stimmung', [
      ['musician', 'Musiker', 'Jeder Musiker spielt ein oder zwei Instrumente. <b>Können</b> wächst vor allem beim Proben; mit Stufen (grün, blau, gold) darf er bessere Instrumente spielen. <b>Bekanntheit</b> wächst durch Auftritte, Platten, Promo und Interviews. Jeder hat eine <b>Spieltechnik</b> und einen <b>Charakter</b>: Manche Züge kennst du erst, wenn sie sich zeigen.'],
      ['mood', 'Stimmung und Wünsche', 'Jeder Musiker ist <b>grün</b> (gut drauf), <b>gelb</b> (angespannt) oder <b>rot</b> (unzufrieden). Was ein Charakter mag, hebt die Stimmung sofort, was er nicht mag, drückt sie. Wer unzufrieden ist, wünscht sich etwas, zum Beispiel einen Auftritt, ein besseres Instrument oder eine Platte. Je länger er warten muss, desto unzufriedener wird er. Wer rot ist, macht nicht mehr alles mit. Eine <b>Auszeit</b> hilft fast immer.'],
      ['band', 'Beziehungen', 'Wer zusammen probt, auftritt und aufnimmt, lernt sich kennen. Ob sie sich mögen, hängt vom Charakter ab. Gute Beziehungen machen den <b>Zusammenhalt</b> der Band stark, und dann klingt sie besser. Eine Tournee schweißt besonders zusammen.'],
      ['manager', 'Manager', 'Ein Manager kennt die Wünsche und Charakterzüge der Band und schreibt dir Notizen auf die Übersicht. Manche können mehr: besser verhandeln (mehr Gage), Werbung machen (mehr Ruhm), schlechte Laune abfedern oder mehr aus Interviews holen. Manager bewerben sich im Posteingang.'],
    ]],
    ['Was die Band macht', [
      ['orders', 'Proben', `Proben ist der wichtigste Weg, besser zu werden. Die Probe läuft, bis jemand keine Lust mehr hat; das hängt vom Charakter ab. Ein besserer <b>Proberaum</b> bringt mehr und hält länger.`],
      ['gig', 'Auftritte', 'Unter „Auftritt buchen“ siehst du die Orte, die zur Bekanntheit der Band passen, mit Kosten und Gage. Größere Orte brauchen mehr Songs. <b>Straßenmusik</b> und <b>offene Bühne</b> gehen immer. Je besser der Sound und je besser das Publikum mitgeht, desto mehr Ruhm und Gage. Spielt ihr oft dieselben Lieder, lässt das nach; eine neue Platte macht die Auftritte wieder voll.'],
      ['record', 'Platten', `Die Band schreibt ganz von selbst laufend neue Songs, kreative Musiker schneller. Für eine Platte braucht die Band fertige Songs (mindestens ${n(r.recordMinSongs)}). Du wählst Studio, Produzent und Cover: Bessere kosten mehr und machen die Platte besser. Danach steigt sie in die Charts ein und bringt Geld, solange sie sich verkauft. Auftritte und Promo schieben sie an.`],
      ['promo', 'Promo, Interviews, Auszeit', 'Eine <b>Promo-Tour</b> macht die Band bekannter und schiebt die neueste Platte an. <b>Interviews</b> kommen als Anfrage in den Posteingang. Eine <b>Auszeit</b> (Ausflug, Wochenende, Urlaub) lässt die Band ausruhen, danach geht es allen besser.'],
      ['store', 'Instrumente', 'Ohne eigenes Instrument zählt ein Musiker nur halb. Im <b>Music Store</b> gibt es jeden Tag andere Instrumente in Grau, Grün, Blau und Gold; ein besseres darf erst spielen, wer das Können dafür hat. Freie Instrumente liegen im Lager, und dort steht auch, wem sie helfen würden.'],
      ['course', 'Kurse und Lehrer', 'Auf dem <b>Schwarzen Brett</b> hängen jeden Tag Kurse: Ein Musiker lernt dort schnell dazu, einmal am Tag. <b>Privatlehrer</b> und <b>Song-Coaches</b> bewerben sich im Posteingang.'],
    ]],
    ['Jede Woche', [
      ['battle', 'Battle of the Bands', `Jeden Samstag um 16 Uhr.${battle ? ` Das nächste: ${esc(battle)}.` : ''} Jedes Label meldet bis zu zwei Bands an, nur Bands, keine Solokünstler. Wie bei einer Weltmeisterschaft: erst Gruppen, dann K.-o.-Runden bis zum Finale. Es zählen Sound und Laune, und ein bisschen Glück. Online sein musst du dafür nicht, das Ergebnis kommt beim nächsten Öffnen. Die ersten drei bekommen Pokale.`],
      ['festival', 'Festival', `Jeden Mittwoch um 17 Uhr, bewerben bis 12 Uhr.${festival ? ` Das nächste: „${esc(festival.name)}“, gesucht ${esc(cat().genres[festival.genre]?.name ?? '')}.` : ''} Jedes Festival sucht ein Genre und nimmt nur Bands, die es spielen. Gibt es zu viele Bewerbungen, entscheidet der Veranstalter. Wer in der letzten Woche am meisten Bekanntheit gesammelt hat, spielt auf der Hauptbühne, zum Schluss als Headliner.`],
      ['tour', 'Tournee', 'Auf der Seite einer Band startest du eine Tournee mit 3, 5 oder 7 Stationen. Wohin es geht, wird ausgewürfelt. Ist die Band angekommen, startest du den Auftritt, danach geht es weiter oder nach Hause. Jedes Land liebt ein Genre; passt es zur Band, wird der Abend besonders gut. Wer mit einer Band in allen Ländern gespielt hat, bekommt einen goldenen Globus.'],
    ]],
    ['Erfolg', [
      ['award', 'Auszeichnungen', `Alles steht im Proberaum der Band: die <b>Goldene Schallplatte</b> (${n(aw.gold)} verkauft), <b>Platin</b> (${n(aw.platinum)}), <b>Diamant</b> (${n(aw.diamond)}), <b>Platz 1</b> der Charts, der Pass für den ersten Auftritt in einer <b>Halle</b> und einer <b>Arena</b>, Pokale vom Battle, das Plakat als Festival-Headliner und der Globus von der Tournee.`],
      ['charts', 'Charts und WikiSignia', 'In den <b>Charts</b> stehen die Platten aller Labels; je höher, desto mehr verkauft sich eine Platte. Dort findest du auch die Rangliste der Labels und Bands. In der <b>WikiSignia</b> hat jede Band und jeder Musiker einen eigenen Artikel, auch von anderen Labels.'],
    ]],
  ];
}

export function render() {
  return `
    <div class="section-head">
      <div><h1>Hilfe</h1><p class="muted">Alles Wichtige zum Nachlesen. Die genauen Zahlen stehen im Spiel direkt an den Werten.</p></div>
      <div class="tile-buttons"><button class="btn" data-act="startTour">Einführung noch mal</button></div>
    </div>
    ${topics().map(([group, list]) => `
      <h2 class="help-group">${esc(group)}</h2>
      <div class="help-grid">${list.map(([key, title, text]) => `
        <section class="panel help-topic"><h3>${icon(key)}${esc(title)}</h3><p>${text}</p></section>`).join('')}
      </div>`).join('')}`;
}

defineActions({
  startTour() {
    openTour();
  },
});
