# Signed – das Label-Spiel (Konzept)

**Stand:** in der Testversion weitgehend gebaut. Das echte Spiel (`main`) bleibt, wie es ist, bis Sandra „veröffentlichen“ sagt.
Formeln und genaue Zahlen stehen in [`kern.md`](kern.md) (und im Code in `Balance.php` und `Catalog.php`), was ins Musikerspiel
ausgelagert wurde, in [`ausgelagert-musikerspiel.md`](ausgelagert-musikerspiel.md). Was noch offen oder geparkt ist, steht ganz unten.
Alle Zahlen sind Startwerte zum Nachjustieren; die Durchspiel-Prüfung kommt am Ende.

## Leitidee

Du bist das **Label**, nicht die Band. Die Bands haben ein Eigenleben: Sie schreiben von selbst Songs, haben Bedürfnisse und
Probleme und äußern sie. Das Label gibt **Anordnungen** (proben, aufnehmen, auftreten, fördern, ausstatten), und jede Anordnung wirkt auf
die **Stimmung** der Band. Eine zufriedene Band schreibt schneller Songs, macht gute Auftritte und Platten. Wer sie vernachlässigt oder ihr
Unzumutbares abverlangt, riskiert, dass sie geht.

Das Label will vor allem **Geld verdienen**; alles andere ist Mittel zum Zweck. Bei jeder Entscheidung stehen die Interessen des Labels
gegen die der Band. Oft decken sie sich von selbst, manchmal nicht, und genau dann muss man abwägen.

Drei Grundsätze, die für alles gelten:

1. **Gezielt ausgeben, nicht würfeln.** Kein Geld für Pakete, bei denen man schaut, was herauskommt. Bei allem, was Geld kostet, sieht man vorher, was man bekommt.
2. **Ohne Lesen verständlich.** Warnungen, Wünsche und Folgen werden mit Farben, Figuren, Symbolen und kurzen Sätzen gezeigt. Mitspieler sind auch Kinder (7 und 10).
3. **Die Welt lebt weiter, auch offline.** Songs, Tantiemen, Stimmung: alles läuft in Echtzeit. Nur eine **rote** Band friert ein.

Dazu aus `kern.md`: **Keine künstliche Bremse.** Das Tempo ergibt sich aus Gewinnen, Kosten, Schwellen und der Lernkurve; Ruhm darf nie den
eigenen Zuwachs verstärken.

## Spielablauf in Kürze

- **Start:** 2.000 ₲. Im Posteingang liegen die ersten Kassetten (drei brauchbare, zwei Mist), ein Praktikum, ein Scout und ein Manager bewerben
  sich. Die Einführung zeigt in sechs Schritten die Grundidee, die „Erste Schritte“ auf der Übersicht führen weiter (Kassette, Büro, Manager,
  Instrument, Probe, Auftritt, Platte, Scout).
- **Jeden Tag:** Post sortieren und Musiker unter Vertrag nehmen, Bands zusammenstellen und ausstatten, proben, auftreten, aufnehmen, auf
  Wünsche reagieren. Neu im Music Store und auf dem Schwarzen Brett (Kurs des Tages).
- **Jede Woche:** Mittwoch Festival, Samstag Battle of the Bands. Dazu jederzeit Tourneen.
- **Ziel:** Geld, Ruhm, Charts und Auszeichnungen; in der Rangliste der Labels nach oben.

## Werte

### Musiker

| Wert | Was es ist | Wächst durch | Wirkt auf |
|---|---|---|---|
| **Können** | je Instrument 0–100, Stufen bei 35 / 60 / 85 | Bandproben (Hauptquelle), Kurse, Privatlehrer | Sound; welche Instrumente er spielen darf; Wohlbefinden (Abstand zu den anderen) |
| **Bekanntheit** | Stufen bei 20 / 120 / 600 (Unbekannt, Lokal bekannt, Szenegröße, Star) | Auftritte, Festivals, Interviews, Promo, Charts | Ruhm der Band, Interview-Anfragen, Anspruch |
| **Charakter** | ein bis zwei Züge, von Anfang an, erst verborgen | – | Beziehungen, Wohlbefinden, Ausdauer, Songschreiben, Wünsche |
| **Genres** | Lieblingsgenres, von Anfang an | – | Sound (Genre-Band, Hybrid), Beziehungen |
| **Spieltechnik** | genau eine, passend zu Instrument und Genre | – | Sound im passenden Genre, Glanz bei Auftritten |
| **Beziehung** | je Mitmusiker ein Wert, −10 bis +10 | gemeinsame Proben, Auftritte, Aufnahmen, Tourneen, je nach Passung | Zusammenhalt, Wohlbefinden |
| **Wohlbefinden** | versteckt 0–100, sichtbar nur als Farbe und Wort (gut, angespannt, unzufrieden) | siehe unten | Stimmung der Band; bei Rot droht der Weggang |

### Band

| Wert | Was es ist | Hängt ab von | Wirkt auf |
|---|---|---|---|
| **Sound** | wie gut die Band klingt | Können, Genre-Stufe, Besetzung, Instrumente, Techniken, Zusammenhalt | Platten, Auftritte, Battle |
| **Ruhm** | wie berühmt die Band ist | Bekanntheit der Mitglieder und Ruhm am Namen | Anspruch, Orte, Label-Ruhm |
| **Zusammenhalt** | aus den Beziehungen der Mitglieder | Beziehungen | Sound, Anzeige |
| **Stimmung** | Ampel grün / gelb / rot | Wohlbefinden aller, vor allem des Unzufriedensten | Songschreiben, erlaubte Anordnungen, Weggang |
| **Songs** | geschriebene Lieder (höchstens 24) | Zeit × Stimmung × Charaktere | Auftritte, Platten; ein Stau ab 12 macht unzufrieden |

### Label

**Label-Ruhm** aus dem Ruhm aller Bands, große zählen stärker: Wurzel aus der Summe der Quadrate (`BandCalc::labelFame`). Eine Band allein
zählt mit ihrem Ruhm; 500 und 490 ergeben 700, fünfzig Bands mit je 10 nur 71, neue Unbekannte ziehen nichts herunter. Er bestimmt, wie viel
Post kommt und wie gut die Bewerber sind, und steht auf der Übersicht, in der Rangliste und in der WikiSignia.

## Charakter, Stimmung und Beziehungen

### Beziehung

- Skala **−10 bis +10, Start 0** (neue Mitglieder kennen sich nicht; wer als Band kommt, kennt sich schon).
- **Passung** zweier Musiker (−1 bis +1), nie angezeigt: Charakter-Paar (Reibung, Teamplayer, zwei Gesellige), gemeinsame Genres, großer Können-Abstand.
- Gemeinsame Erlebnisse (Probe, Auftritt, Aufnahme, Battle, Festival, Tournee, Auszeit) bewegen die Beziehung auf einen **Zielwert** zu:
  `10 × Passung + 3`. Wer sich gut passt, kommt hoch; wer sich stark reibt, **kann nicht gut werden** (Passung −0,6 → Ziel −3). Absichtlich möglich.
  Eine Tournee schiebt zusätzlich alle ein Stück nach oben.
- **Auseinander:** Die Beziehung bleibt und driftet langsam auf 0 zurück (0,2 je Stunde).
- **Anzeige nur mit Manager:** Ohne Manager sieht man Beziehungen und Zusammenhalt nicht. Nur großer Streit (−7 oder schlechter) ist laut und steht
  auf der Bandseite und der Übersicht.

### Wohlbefinden und Bedürfnisse

Wohlbefinden (Start 60) läuft langsam auf ein **Ziel** zu, das sich aus Einflüssen zusammensetzt:

| Einfluss | Wirkung |
|---|---|
| Beziehungen | gute heben, schlechte ziehen runter |
| Können-Abstand | zu schwach: überfordert; zu stark: gelangweilt; ein kleiner Abstand ist gut |
| Genre der Band | wer es nicht mag, leidet |
| Instrument | keins: −6; eins unter dem eigenen Können: −3 je Stufe; passend: +2; Diven doppelt |
| Songstau | ab 12 liegenden Songs sinkt die Stimmung aller |
| Anordnungen | was der Charakter mag, hebt sofort; was er nicht mag, drückt (`Catalog::LIKES`) |
| Bühne und Studio gegen Anspruch | darüber freut, darunter ärgert |
| Wünsche, die offen bleiben | drücken mit jeder Stunde mehr (`Balance::NEEDS`) |
| Miesepeter | wer rot ist, zieht die anderen mit runter |
| Proberaum, Auszeit | heben |

**Wünsche** entstehen aus dem Charakter und der Lage: Auftritt, Probe oder Platte (je nach Zug), ein eigenes oder besseres Instrument,
„Ich will in eine Band!“, ein Künstlername. Unerfüllte Bedürfnisse wachsen wie Hunger: nach einer Schonzeit sinkt das Ziel mit jeder Stunde weiter, bis die Band etwas
macht, das er mag. Gezählt wird nur gerechnete Zeit; offline im Roten steht alles still, ein Urlaub kostet also keine Band. Jeder Wunsch hat ein
Symbol, einen kurzen Satz und den passenden Knopf.

### Charakterzüge: Stärke und Schwäche

Jeder Zug hat eine Stärke und eine Schwäche; welcher hilft, hängt von Band und Anordnungen ab. Namen sind Eigenschaftswörter (Menschen sind
nicht ihre Eigenschaft); gespeichert werden die alten Schlüssel. Zahlen: `Balance::TRAITS`, Ausdauer beim Proben `Catalog::LUST`, Texte `Catalog::PERSONALITY_INFO`.

| Zug (Schlüssel) | Stärke | Schwäche |
|---|---|---|
| Ehrgeizig (ehrgeizig) | lange Proben (×1,5) | −6 Wohlbefinden-Ziel, wenn 48 h keine neue Platte |
| Gemütlich (gelassen) | sinkt langsamer (×1,8), Songstau zählt halb, kleine Bühnen ärgern nicht | kurze Proben, mag keine Auftritte |
| Perfektionistisch (perfektionist) | lange Proben, Platte +0,04 Qualität | schreibt langsamer (×0,6) |
| Impulsiv (chaot) | schreibt schneller (×1,6), live +4 % Glanz | kurze Proben, live +4 % Patzer |
| Extrovertiert (rampensau) | Ruhm-Anteil bei Auftritten +0,2 | −6, wenn 8 h kein Auftritt; zwei reiben sich; solo unglücklich |
| Egozentrisch (diva) | Erstverkauf +10 %, solo glücklich | will gute Bühnen; zwei reiben sich |
| Kooperativ (teamplayer) | bessere Beziehungen | leidet ×1,5 mit Unzufriedenen mit, solo sehr unglücklich |
| Ausgeglichen (ruhepol) | halbiert Reibung | live −3 % Glanz |
| Gesellig (partytier) | Ruhm-Anteil bei Auftritten +0,15, zwei verstehen sich | −5, wenn 8 h kein Auftritt; kurze Proben |
| Kreativ (kreativ) | schreibt viel schneller (×3) | kurze Proben (×0,7), lernt beim Proben langsamer (×0,85) |
| Technisch (technisch) | lernt schneller (×1,25), lange Proben (×1,6), live −3 % Patzer | schreibt keine Songs |

Kreativ und Technisch schließen sich aus. **Versteckter Wert** (`Balance::VALUE`, nie angezeigt): Ehrgeizig, Kooperativ, Ausgeglichen, Kreativ +1,
Egozentrisch und Technisch −1; die Unterschriftsprämie wächst um 12 % je Punkt (Glückstreffer kosten mehr, Pechvögel sind billig).

### Verborgene Züge und was der Manager weiß

Mit dem Label redet niemand persönlich. Wer neu kommt, zeigt seine Züge nicht (sie stehen als „?“ da), gewirkt wird trotzdem von Anfang an.
**Ohne Manager** sieht man nur die Stimmung; erst wer **rot** ist, sagt laut, was er will, und verrät damit den Zug dahinter.
**Mit Manager** sieht das Label alle Wünsche, die Gründe der Stimmung („Warum?“) und seine Notizen. Züge zeigen sich, sobald sie zum ersten Mal
etwas bewirken (Anordnung gemocht oder nicht, Unruhe ohne Auftritt, neuer Song, Lust verloren, Battle, Festival, Tournee …); nach einem Tag mit
dem Manager kennt er alle (`Balance::TRAIT_REVEAL`). Was bekannt ist, bleibt bekannt.

### Spieltechnik

Jeder Musiker hat genau eine, von Anfang an (84 Techniken, `Catalog::ABILITIES`); jede gehört zu einem Instrument und zwei Genres. Im passenden
Genre der Band bringt sie +4 % Sound und live etwas mehr Glanz. Sie steht schon auf der Kassette.

### Anspruch

Der **Anspruch** der Band steigt mit dem Ruhm; Diven, Ehrgeizige und Rampensäue nehmen es genauer, Gemütliche lockerer. Er betrifft
**Bühne und Studio**: Ein Ort oder Studio darüber freut, darunter ärgert. Der Anspruch kann höher sein, als der Ruhm wirtschaftlich rechtfertigt;
dann muss das Label abwägen: Geld gegen Stimmung.

### Stimmung der Band

- **Ampel** nach dem unzufriedensten Mitglied: grün nur, wenn alle zufrieden sind; gelb, wenn jemand angespannt ist; rot, wenn jemand sehr unzufrieden ist.
- **Rot sperrt**, was ein roter Musiker nicht mag; jede Eigenschaft mag mindestens eine Anordnung, es gibt also immer einen Ausweg. Eine Auszeit geht immer.
- **Rot friert ein:** keine Songs, keine weitere Verschlechterung, solange man offline ist. Eine Band geht nie, während man schläft.
- **Geduld** (nur online): Bleibt eine Band online im Roten, läuft ihre Geduld ab (30 Minuten Spielzeit, Warnungen bei 2/3 und 1/3); danach
  verlässt sie das Label. Was die Stimmung hebt, hält die Geduld an.
- **Ein Unzufriedener zieht alle runter.** Das Label kann ihn aus der Band nehmen, bevor alle schlecht drauf sind.
- **Warnung vor Folgen:** Jede Anordnung, die die Stimmung ernsthaft drücken kann, sagt vorher, wen es trifft.

### Songs schreiben

- Die Band schreibt **von selbst**: grün etwa 1 Song pro Stunde, gelb halb so schnell, rot gar nicht.
- Jedes Mitglied trägt bei (`Balance::SONGWRITING`): 0,25 × Faktor seines Charakters (Kreativ ×3, Impulsiv ×1,6, Perfektionistisch ×0,6,
  Technisch 0), Tempo = Summe hoch 1,5. Vierer-Band ×1, Zweier-Band ×0,35, allein ×0,125 (ein Song in acht Stunden), allein und kreativ ×0,65.
- Höchstens 24 Songs; ab 12 liegenden Songs sinkt die Stimmung („Wir wollen aufnehmen!“). Ein Songwriting-Coach beschleunigt.

## Anordnungen

Auf der Bandseite als Schallplatten mit farbigem Etikett; jede fragt vorher nach (Dauer, Kosten, was es bringt, wer es mag).

| Anordnung | Wirkung | Bedingungen |
|---|---|---|
| **Proben** | **Hauptquelle für Können**, Beziehungen, Wohlbefinden je Charakter | alle haben ein Instrument, niemand Rotes mag es nicht |
| **Auftritt buchen** | Gage, Ruhm, Bekanntheit; Ort mit Kosten und Gewinn-Schätzung | genug Songs; Ort unter dem Anspruch ärgert |
| **Aufnehmen** | Songs → Platte; Studio, Produzent, Cover | genug Songs; Studio unter dem Anspruch ärgert |
| **Promo-Tour** | etwas Ruhm, schiebt die neueste Platte an | 10 Minuten, jederzeit |
| **Auszeit** | hebt die Stimmung, Beziehungen wachsen etwas | Ausflug / Wochenende / Urlaub, geht auch bei Rot |
| **Tournee** | Auftritte in echten Ländern, stärkt die Beziehungen | siehe Tournee |

### Proben

Der Weg nach oben, **ohne Stufen-Bremse**: Die Lernkurve `(1 − Können/110)^2,5` bremst von selbst. Die Probe ist **offen**: Die Band probt,
bis ein Mitglied keine Lust mehr hat (Grundwert 60 Minuten, nach Charakter und Proberaum länger oder kürzer) oder bis jemand, der Proben nicht
mag, zu unzufrieden wird. Alles wird **laufend** gutgeschrieben (Können mit Stufenaufstieg im selben Moment, Beziehungen, Wohlbefinden);
„Probe beenden“ behält den Fortschritt. Wer unter Besseren spielt, lernt etwas schneller. Tempo der Stufen: siehe `kern.md` (Tempo).

### Auftritt buchen

- Das Fenster zeigt die Orte vom kleinsten bis zwei Stufen über dem Anspruch, jeden von außen (Fachwerk-Kneipe, Club, Konzertsaal, Halle, Arena)
  mit **festen Kosten**, Länge in Songs, **Gage-Spanne und Gewinn** (aus sechs durchgespielten Abenden), erwartetem Ruhm, einer Ampel, ob der
  Sound die Erwartung erfüllt, und wie die Band den Ort findet.
- Gespielt werden aufgenommene und geschriebene Songs; eine Platte ist keine Voraussetzung. Wer oft dieselben Lieder spielt, zieht weniger
  Leute; eine neue Platte macht die Auftritte wieder voll.
- **Bezahlt wird hinterher** aus der Gage; vorher nur, was selbst der schlechteste Abend nicht einspielt. So rutscht das Label nie ins Minus.
- Beim Auftritt steht die Band auf der Bühne des Ortes (je größer, desto prächtiger) mit wippendem Publikum, Live-Ticker und echtem Applaus.
- Solokünstler buchen Studiomusiker dazu (Kosten ×1,5, Pop ×1,2).

### Aufnehmen und Cover

- **Studio** (Garage, Standard, Profi, legendär) und **Produzent** (keiner, Mo Beats, Roxy Reverb, Diamond Dee, Ruben Stille); ohne Produzent
  nur die Garage, jeder Produzent bringt die Band ein Studio weiter. Qualität = Sound × Produktionsniveau, keine Minispiele. Während der
  Aufnahme zeigt die Bandseite das Studio mit dem Produzenten hinter der Scheibe.
- **Cover:** drei Entwürfe; wer gestaltet (Kunststudentin, Agentur, Star-Designerin) bestimmt Glanz und etwas Erstverkauf. Das **Bandlogo**
  wählt man bei der ersten Platte, ab der zweiten verkauft es 3 % mehr. Ein passendes Wort im Titel („Herz“, „Feuer“ …) wird zum Motiv.
- Danach steigt die Platte in die Charts ein und bringt Tantiemen, solange sie sich verkauft.

### Interviews

Interview-Anfragen kommen als Briefe in den Posteingang, je Musiker nach seiner Bekanntheit: Unbekannte bekommen Schülerzeitung und Stadtblog,
Stars Fernsehshows. Annehmen schickt den Musiker kurz weg; der Gewinn ist ein Anteil des Weges zur nächsten Bekanntheitsstufe. Nach einem
Festival meldet sich ein größeres Medium.

### Auszeit

Ausflug 1 Std. (+8, 20 ₲ je Mitglied), Wochenende 4 Std. (+16, 60 ₲), Urlaub 12 Std. (+28, 150 ₲); Gemütliche und Ausgeglichene ×1,5,
Ehrgeizige ×0,5. Keine Songs in der Zeit, die Beziehungen wachsen etwas. Die Band holt sich Koffer und geht; ein Zettel sagt, wann sie zurück ist.

### Fördern: Kurse, Privatlehrer, Songwriting-Coach

- **Kurs des Tages** (Schwarzes Brett): jeden Tag ein Instrument und eine Kursart, für alle gleich, in drei Schwierigkeiten (Anfänger,
  Fortgeschrittene, Profis), jeder nur in seiner. Der Zuwachs ist für jeden Teilnehmer gleich, ohne Lernkurve.
- **Privatlehrer** (Bewerbung): 2 Tage für genau einen Musiker, +8 / +12 / +16 Können ohne Bremse (mit passendem Genre ×1,3), nur bis Können
  55 / 80 / 100. Läuft nebenbei.
- **Songwriting-Coach** (Bewerbung): 1 Tag für eine Band, Songs 1,5- bis 2-mal so schnell, und die nächste Platte wird besser.

## Jede Woche

### Schwarzes Brett

Eigener Menüpunkt für alles, was **für alle gleich** ist: der Kurs des Tages (mit „hinschicken“) und die Kurse der nächsten Tage, darunter
Festival und Battle. Sie landen nicht im Posteingang.

### Festival (Mittwoch)

Jeden Mittwoch um 17 Uhr, Bewerbung bis 12 Uhr. Jedes Festival hat ein Lieblings-Genre (wechselt jede Woche, nie zweimal hintereinander) und
nimmt nur Bands dieses Genres (auch Hybrid-Bands). Gibt es mehr Bewerbungen als Plätze, wird insgeheim gelost, offiziell entscheidet der
Veranstalter. Die Bühne (Haupt-, Neben-, Zeltbühne) richtet sich nach der Bekanntheit, die die Band in der letzten Woche gesammelt hat; wer am
meisten hat, spielt als Headliner zuletzt auf der Hauptbühne. Die Seite zeigt das Plakat mit Bühnen und Zeiten und, wer gerade spielt
(„Zuschauen“, eigene Bühnenbilder für Open Air und Zelt). Belohnung: ein großer Bekanntheits-Schub, eher kleine Gage, danach eine
Interview-Anfrage von einem größeren Medium, für den Headliner ein Plakat im Proberaum. Online sein muss niemand.

### Battle of the Bands (Samstag)

Jeden Samstag um 16 Uhr, für alle Labels. Anmeldung die ganze Woche, bis zu zwei Bands je Label, **nur Bands** (keine Solokünstler), keine
Computer-Bands. Ablauf wie bei einer WM: Gruppen, dann K.-o. bis zum Finale; Bands eines Labels werden möglichst getrennt. Es entscheiden
**Sound und Laune**, der Zufall nur in der Spanne der Tagesform; Ruhm und Proben zählen nicht. Die Seite zeigt Gruppentabellen, alle Spiele mit
Uhrzeit, den K.-o.-Baum und das Spiel, das gerade auf der Bühne ist. Während des Battles ist die Band weg (Zettel im Proberaum mit
„Zuschauen“). Danach: Platz, Preisgeld, Ruhm und für die ersten drei ein Pokal im Proberaum. Online sein muss niemand.

### Tournee (jederzeit)

Eine Band geht auf Tournee durch echte Länder (14, von Frankreich bis Japan). Man wählt nur die Länge (3, 5 oder 7 Stationen); wohin es geht,
wird ausgewürfelt, das nächste Ziel erst beim Weiterreisen. Die Reise dauert nach der echten Entfernung; bei der Ankunft kommt eine Meldung,
den Auftritt startet man selbst, danach „Weiterreisen“ oder „Nach Hause“ (wer zu lange wartet, fliegt nach zwei Tagen von selbst heim, der
Heimflug ist frei). Jedes Land hat ein Lieblings-Genre; passt es zur Band, wird der Abend besonders gut. Im Ausland gibt es mehr Ruhm und
etwas mehr Gage. Die Bandseite zeigt statt des Proberaums eine Weltkarte mit der Route und dem Flugzeug. Jedes Land hat eine eigene,
klischeehafte Bühne (Frankreich: Bistro mit Baguettes und Eiffelturm, Japan: Fuji, Torii und Kirschblüten …). Eine Tournee schweißt zusammen.
Wer mit einer Band in allen Ländern gespielt hat, bekommt den goldenen Globus.

## Das Label

### Posteingang

Drei Arten Post: **Kassetten** (Musiker und Bands, die zum Label wollen), **Briefe** (Interview-Anfragen, Scout-Berichte) und **Bewerbungen**
(Praktikum, Assistenz, Scout, Manager, Lehrkraft, Coach). Alles kommt in **Umschlägen** (erst auspacken, 2 Sekunden), **zufällig verteilt**
(man weiß nie genau, wann), liegt **vier Tage**, lässt sich merken und ungeöffnet wegwerfen. Am Menüpunkt steht **+N**, was neu ist.
Alles liegt im **Eingang**; in die Fächer kommt es, wenn jemand sortiert. Von Hand lässt sich alles in ein Fach ziehen und umsortieren.

**Wie viel kommt:** je Art eine fließende Rate **Start + Zuwachs × √Label-Ruhm** ohne Obergrenze:

| je Tag            | Start | Zuwachs | bei Label-Ruhm 100 | 1.000 | 10.000 |
|-------------------|------:|--------:|------:|------:|-------:|
| Kassetten         |     8 |     1,8 |    26 |    65 |    188 |
| Praktikum         |   0,8 |   0,033 |   1,1 |   1,8 |    4,1 |
| Manager           |   0,3 |   0,019 |   0,5 |   0,9 |    2,2 |
| Scout             |   0,2 |   0,014 |   0,3 |   0,6 |    1,6 |
| Assistenz         |  0,12 |   0,011 |  0,23 |  0,47 |    1,2 |
| Lehrkraft         |  0,08 |    0,01 |  0,18 |  0,40 |    1,1 |
| Coach             |  0,05 |   0,009 |  0,14 |  0,33 |    0,95 |

Mit dem Erfolg steigt der Anteil guter Kassetten (5 % bis 25 %), und die Bewerber werden besser (Güte = Ruhm / (Ruhm + 300)). Hat eine Band
Erfolg, bewerben sich mehr Musiker mit ähnlichen Genres. Interview-Anfragen richten sich nur nach dem einzelnen Musiker.

### Leute im Büro

- **Praktikum** (50 ₲, 7 Tage, immer nur eins): arbeitet **nur auf Zuruf** (Briefe öffnen, Kassetten anhören, Post sortieren), alles, was gerade
  daliegt, das Neueste zuerst; abbrechen geht jederzeit. Nach den 7 Tagen fragt es „Darf ich bleiben?“: für 400 ₲ als Assistenz übernehmen
  oder verabschieden.
- **Assistenz** (400 ₲): hat **ein Arbeitsgebiet** und arbeitet darin laufend von selbst, Stück für Stück und so schnell wie man selbst, auch offline:
  **Post** (auspacken und in die Fächer legen, meldet, was bald abläuft), **Kassetten** (anhören, in Genre-Reiter legen, nach Regel wegwerfen,
  Suchauftrag: Instrument, Genre, ab Stufe) oder **Büro** (Bewerbungen, Anfragen und Berichte lesen, auf der Übersicht davon erzählen, auf Wunsch
  Interviews zusagen). Mit Assistenz gibt es Unterreiter und Sortieren.
- **Poststelle:** Oben im Posteingang eine Szene: Praktikum und Assistenzen stehen am Schreibtisch, halten, woran sie gerade sitzen, tragen
  beim Anhören Kopfhörer, langweilen sich ohne Aufgabe. Antippen öffnet ihre Sprechblase mit Zurufen und Einstellungen.

### Neue Musiker

- **Kassetten:** Antippen hört sie an (5 Sekunden), dann sieht man, wer drauf ist: meist Mittelmaß, manchmal Gutes. Unter Vertrag nehmen kostet
  eine **einmalige Unterschriftsprämie** fürs ganze Leben (`40 + 0,05 × Können² + 1,5 × Bekanntheit`, mal versteckter Wert). Von einer Band kann
  man alle oder **einzelne** nehmen; wer zurückbleibt, zieht enttäuscht weiter, und wer kommt, ist gekränkt (Wohlbefinden startet niedriger).
  Mitglieder einer Band kennen sich schon.
- **Scout** (Bewerbung, 400 / 1.200 / 3.000 ₲): losschicken mit Genre und/oder Instrument, nach 6 / 5 / 4 Stunden liegt ein **Bericht** mit 2 / 3 / 3
  Talenten im Posteingang (unbekannt, Können bis 34 / 44 / 54). Man blättert durch und unterschreibt beliebig viele. Der Scout steht unter der
  Künstlerleiste.
- **Entlassen** (Musikerseite): nur wer in keiner Band mit anderen spielt und gerade frei ist; er verlässt das Label für immer und bleibt in der
  WikiSignia. Seine Instrumente bleiben im Lager. Es gibt kein Geld zurück.

### Manager

Ohne Manager erfährt man fast nichts über seine Leute (siehe oben). Einer bewirbt sich gleich zu Beginn. Einmalig bezahlt (300 / 3.000 /
10.000 ₲, Stärke 1 / 3 / 5); er betreut **eine Band oder bis zu vier Solokünstler**. Er schreibt **Notizen auf die Übersicht**: Wünsche seiner
Leute, wer besser spielt, als sein Instrument hergibt, wer sich nicht versteht, wer lange nicht auf der Bühne stand, wer bereit fürs Studio ist.
Jeder hat Schwerpunkt-Genres, einen Stil (Charakterzug) und ein bis zwei Zugaben: **Verhandler** (Gage), **PR-Profi** (Ruhm bei Auftritten),
**Netzwerker** (Interviews bringen mehr), **Vermittler** (schlechte Laune nach Anordnungen fällt schwächer aus). Die **Passung** zur Band
(gemeinsame Genres, Stil) verstärkt oder schwächt ihn; die Bewerbung zeigt vorher, zu welchen Bands er passt.

### Music Store und Instrumente

- Ohne eigenes Instrument zählt ein Musiker nur halb. Instrumente gibt es in **Grau, Grün, Blau, Gold**; ein besseres darf erst spielen, wer die
  passende Können-Stufe hat. Farbige Instrumente haben Boni (Klang, Ausdauer, Lernhilfe …), Gold dazu eine besondere Fähigkeit.
- **Grundausstattung:** jedes graue Modell, immer da, 40 ₲.
- **Angebot des Tages:** für alle gleich, wechselt jeden Morgen; drei Fächer, meist grün, manchmal blau, etwa einmal die Woche ein goldenes im
  Schaufenster. Jedes Angebot kauft ein Label einmal. Man sieht vorher Preis und Werte, nichts ist Glück.
- Die Figur hält ihr eigenes Instrument, so wie es im Laden aussieht. Im **Lager** liegen freie Instrumente, mit Hinweis, wem sie helfen würden.

### Proberaum

Jede Band hat ihren eigenen Raum, jeder Solokünstler seine Übungsecke. Stufen: Bruchbude → Aufgeräumter Keller (400 ₲) → Gemütlicher
Proberaum (1.200 ₲) → Profi-Proberaum (3.000 ₲); bessere Räume bringen mehr Können je Probe, längere Proben und mehr Wohlbefinden. Mit
Genre-Bonus ist der Raum im Stil des Genres eingerichtet. Beim Zusammentun bleibt der bessere Raum. Im Raum stehen die Auszeichnungen.

## Bands zusammenstellen

- **Jeder unter Vertrag ist in einer Band oder Solokünstler** (eine Band mit einem Mitglied, eigener Knopf in der Leiste). Es gibt keine Kartei
  mehr und keine Bandplätze.
- **Solokünstler** lohnen sich kaum: kein Genre- oder Besetzungs-Bonus (nur Pop und Elektro halb), einsam (Teamplayer, Extrovertierte und
  Gesellige leiden mehr, Diven freuen sich), schreiben langsam, Auftritte kosten mehr. Nützlich am Anfang und für Musiker, die in keine Band passen.
- **Künstlername:** Diven ab Ruhm 20, Extrovertierte ab 40, Ehrgeizige ab 120 wünschen sich einen; das Label wählt einen von drei Vorschlägen.
- **Zusammentun** (Knopf oder Knopf auf einen anderen ziehen): Bands und Einzelne, mit oder ohne Laufbahn. Die Liste bewertet beide Seiten
  (Genre-Bonus, Charaktere). **Namenswahl:** Name A, Name B, verbunden („und die“) oder neu; wer seinen Namen aufgibt, verliert den Ruhm daran.
- **Ehemalige Bands:** Gibt eine Band mit Laufbahn ihren Namen auf, bleibt sie mit Platten und Geschichte erhalten (WikiSignia). Ein Name bleibt
  geschützt, wenn er genug Ruhm hatte.
- **Aufstellung:** Signies lassen sich im Raum umstellen (festhalten und ziehen); die Reihenfolge gilt überall.
- **Bands ohne Obergrenze:** Es bremst von selbst, weil jede Band Geld und Aufmerksamkeit kostet und ein Manager nur eine betreut.

## Erfolg

- **Charts und Tantiemen:** Platten aller Labels; je höher, desto mehr verkauft sich eine Platte. Tantiemen laufen laufend (sichtbar am Geld),
  solange die Platte höchstens auf Platz 50 steht, längstens 7 Tage. Auftritte, Promo und Festivals schieben an. Dazu Rangliste der Labels und Bands.
- **Auszeichnungen** (nur echte Pokale, alle im Proberaum): **Goldene Schallplatte** (100.000 verkauft), **Platin** (250.000), **Diamant** (1 Million),
  **Platz 1** der Charts, Bühnenpass für den ersten Auftritt in einer **Halle** und einer **Arena**, **Battle-Pokale** (Platz 1 bis 3),
  **Festival-Plakat** (Headliner), **goldener Globus** (alle Länder der Tournee). Neue werden einmal gefeiert.
- **WikiSignia:** Lexikon über alle Labels: Labels, Bands (auch ehemalige) und Signies (auch Entlassene) mit Infobox, Diskografie, Auszeichnungen
  und Geschichte. Von fremden Labels sieht man nichts über Geld.

## Oberfläche

- **Gestaltung:** ruhig, dunkle Flächen, ein warmer Akzent, nicht knallig und nicht kindisch, keine Smileys. **Farbe hat feste Bedeutungen:**
  Grün/Gelb/Rot für Stimmung, Genre-Farben für Genre, Grau/Grün/Blau/Gold für Instrumente und Können-Stufen.
- **Menü:** Übersicht, Posteingang (+N), Schwarzes Brett, Music Store, Lager, Charts, WikiSignia; Hilfe als Fragezeichen.
- **Übersicht = das Büro:** Geld (mit Tantiemen je Minute), Label-Ruhm, Charts, Künstler; Erste Schritte; Notizen der Manager und vom Büro;
  was gerade läuft; Schwarzes Brett; Chronik; Plattenregal.
- **Künstlerleiste** (links, auf dem Handy oben): je Band ein Knopf mit Name, Instrumenten, Genres (voll = Bonus), Stimmung, Ruhm als Sterne,
  Songs; per Ziehen sortierbar.
- **Bandseite als Szene:** Die Band steht in ihrem Proberaum (Schild mit dem Namen, Manager mit Klemmbrett, Auszeichnungen an der Wand und auf
  dem Boden, dringendster Wunsch als Blase). Darunter die Anordnungen als Schallplatten, die Musiker mit Werten und Beziehungen, Werte mit
  Aufschlüsselung („Warum?“), Auszeichnungen, Chronik. Läuft etwas, steht dessen Szene oben (Probe, Bühne, Studio, Weltkarte). Zum Auftritt
  geht die Band nacheinander aus dem Raum, zur Auszeit mit Koffern.
- **Ausgegraute Knöpfe** lassen sich antippen und sagen, warum es nicht geht.
- **Einführung und Hilfe:** sechs Schritte beim ersten Mal, die Hilfe nach Themen (Fragezeichen oben). Alte Labels sehen stattdessen einmal
  „Was ist neu?“.

## Wer wirkt auf wen (nur stabilisierende Kreisläufe)

- **Ruhm → Anspruch → Stimmung:** Wer berühmt ist, verlangt mehr (kostet das Label mehr). Gewollt, bremst von selbst.
- **Stimmung → Songs → Stau → Stimmung:** Songs machen glücklich, bis sie liegenbleiben. Gewollt, bremst von selbst.
- **Label-Ruhm → Post → Arbeit:** Mehr Erfolg bringt mehr und bessere Post, aber auch mehr zu sortieren; eine Assistenz kostet.
- Es gibt **keinen** Kreislauf, in dem sich ein Wert über einen anderen von selbst aufschaukelt (Ruhm darf nie den eigenen Zuwachs verstärken).
  Die Festival-Bühne hängt an der Bekanntheit der letzten Woche; weil das Genre jede Woche wechselt, spielt selten dieselbe Band zweimal hintereinander.

## Übergang für bestehende Labels

**Gebaut, entschieden:** Bestehende Labels spielen weiter, mit einem einmaligen Umbau (Einzelheiten und Zahlen in `kern.md`, Abschnitt
„Übergang alter Labels“): Wechselbörse zu (Musiker und Gebote zurück), alte Karten und Gutscheine in Geld, alte laufende Aktionen abgerechnet,
Beziehungen aus der gemeinsamen Bandzeit, Kartei beiseite mit der Wahl von bis zu fünf Musikern, die bleiben, und beim ersten Login
„Was ist neu?“. Geprüft mit einem Label, das mit dem Code von `main` durchgespielt wurde (zwei Bands, Platte, Auftritte, Karten, Instrumente,
Manager, Fan-Karte, Kartei, Börse mit Gebot, ungeöffnetes Pack, laufender Auftritt, laufende Probe, Üben).

Verworfen wurde der ursprüngliche Plan (Saison 2: alles fängt neu an, die alten Bands kommen als Einsendungen zurück): Technisch ist ein
Neuanfang nicht nötig, und Sandra hat sich für das Weiterspielen mit Umbau entschieden.

## Was rausgenommen wurde

| Bleibt (neu gedacht) | Ist raus |
|---|---|
| Bandproben als Hauptquelle für Können, Kurse, Privatlehrer | Solo-Üben, Etüden, Instrument-Minispiele (ins Musikerspiel ausgelagert) |
| Kassetten, Scout mit Bericht, Assistenz, Praktikum | Packs, Talent-Scout-Karten und -Gutscheine, Fan-Musiker, Fan-Karten |
| Auftritt buchen mit Kosten und Gewinn, Live-Ticker und Bühne | Straßenmusik, Offene Bühne, Jam-Café |
| Aufnehmen mit Studio und Produzent, Cover als Wahl | Aufnahme-Minispiele, Mischpult, Master-Mix, Cover-Automat |
| Interview-Anfragen, Promo-Tour | Einzel-Promo |
| Solokünstler, Zusammentun, Entlassen | Kartei, Wechselbörse, Bandplätze |
| Instrumente gezielt im Music Store, Manager einmalig | Zufallsläden, „Neue Lieferung“, Zurücklegen |

Der Code der rausgenommenen Teile ist noch da (nicht erreichbar) und wird am Ende entfernt.

## Offen und später

**Wartet auf Sandras Entscheidung**
- **Reisen auf Tournee:** Vorschlag, je Tournee das Verkehrsmittel zu wählen (Billigflieger / normal / Privatjet: Preis gegen Stimmung und
  Reisezeit), statt Fahrzeuge im Music Store zu kaufen. Noch nicht gebaut.
- **Saison-Rangliste**, die nur zählt, was nach dem Übergang dazukommt. Sandra ist noch nicht sicher.

**Geparkt (später vielleicht)**
- Ausgeschriebene Gigs (Veranstalter suchen eine Band für einen Abend) und Charity-Konzerte.
- Outfits/Styling (Bekanntheit wächst schneller), Songschreiber/Texter für eine Band, Medienpaket (bessere Interviews), Teambuilding
  (Beziehungen wachsen schneller), Bühnentechnik (größere Orte füllen sich leichter). Jede Art braucht einen eigenen Zweck und Wunsch.
- **Klangprofil:** Die Band spielt manchmal zwei Versionen vor, das Label wählt; daraus entsteht ihr Klang (aus den vorhandenen Bausteinen in `Styles`).
- Entlassen mit einem Teil der Prämie zurück; einen geschützten Bandnamen wiederbeleben.

**Am Ende, wenn alles fertig ist**
- **Fairness- und Durchspiel-Prüfung:** alle Stufen mit Einsatz erreichbar, kein Aufschaukeln; ob alter Ruhm, altes Geld und alte goldene
  Instrumente einen unfairen Vorsprung geben (dazu die echten Werte aus einer Sicherung, nur Ruhm, Geld und Instrumente, nur mit Sandras Erlaubnis).
- **Aufräumen:** den Code der rausgenommenen Teile entfernen (Packs, Börse, Jam-Café, Minispiele, alte Scout-Gutscheine, Fan-Karten, alte Seiten).
- Test auf dem iPad (Berühren, Ziehen, Klang), dann veröffentlichen.
