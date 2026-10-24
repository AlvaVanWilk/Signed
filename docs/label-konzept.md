# Signed auf Label-Ebene (Umbau-Konzept)

Stand: Entwurf, noch nichts gebaut. Wird in kleinen Schritten in der Testversion umgesetzt; das echte Spiel bleibt, wie es ist.
Was dafür herausgenommen wird, steht in [`ausgelagert-musikerspiel.md`](ausgelagert-musikerspiel.md). Bis zum Umbau gilt für das
echte Spiel weiter [`kern.md`](kern.md). Alle Zahlen hier sind **Startwerte zum Nachjustieren**.

## Leitidee

Du bist das **Label**, nicht die Band. Die Bands haben ein Eigenleben: Sie schreiben von selbst Songs, haben Bedürfnisse und
Probleme und äußern sie. Das Label gibt **Anordnungen** (Proben, aufnehmen, auftreten, fördern, ausstatten), und jede
Anordnung wirkt auf die **Stimmung** der Band. Eine zufriedene Band schreibt schneller Songs, macht gute Auftritte und
Platten. Wer sie vernachlässigt oder ihr Unzumutbares abverlangt, riskiert, dass sie geht.

Das Label will vor allem **Geld verdienen**; alles andere ist Mittel zum Zweck. Bei jeder Entscheidung stehen die Interessen des Labels
gegen die der Band. Oft decken sie sich von selbst, manchmal nicht, und genau dann muss man abwägen.

Drei Grundsätze, die für alles gelten:

1. **Gezielt ausgeben, nicht würfeln.** Kein Geld für Pakete, bei denen man schaut, was herauskommt. Bei allem, was Geld kostet, sieht man vorher, was man bekommt.
2. **Ohne Lesen verständlich.** Warnungen, Wünsche und Folgen werden mit Farben, Gesichtern, Symbolen und kurzen Sätzen gezeigt.
3. **Die Welt lebt weiter, auch offline.** Songs, Tantiemen, Stimmung: alles läuft in Echtzeit. Nur eine **rote** Band friert ein.

## Die Ebenen und ihre Werte

### Musiker

| Wert | Was es ist | Wächst durch | Wirkt auf |
|---|---|---|---|
| **Können** | wie gut am Instrument (je Instrument, Stufen 0–3 bei 35 / 60 / 85) | Bandproben (Hauptquelle), Kurse | Sound, Wohlbefinden (Unterschied zu den anderen) |
| **Bekanntheit** | 3 Stufen in der Anzeige (Unbekannt, Lokal bekannt, Szenegröße, Star) | Auftritte, Interviews (Promo) | Ruhm der Band |
| **Charakter** | 1–2 Eigenschaften, von Anfang an | – | Beziehung (Passung), Wohlbefinden (Bedürfnisse), teils Wachstum von Können/Bekanntheit |
| **Genre** | Lieblingsgenres, von Anfang an | – | Sound (Reinheit, Hybrid, Crossover), Beziehung (gleiche Genres verstehen sich) |
| **Beziehung** | je Mitmusiker ein Wert, −100 bis +100 | gemeinsame Proben, Auftritte, Aufnahmen, je nach Passung | Wohlbefinden |
| **Wohlbefinden** | versteckter Wert 0–100, sichtbar nur als Gesicht und Farbe | alles Mögliche (siehe unten) | Stimmung der Band; bei Rot droht der Weggang |

### Band

| Wert | Was es ist | Hängt ab von | Wirkt auf |
|---|---|---|---|
| **Sound** | wie gut die Band klingt | Können aller Mitglieder, Genre-Stufe, Besetzung, Instrumente | Erfolg der Platten (Qualität, Chartpunkte), Auftritte |
| **Ruhm** | wie berühmt die Band ist | Bekanntheit der Mitglieder, erfolgreiche Auftritte, Platten, Interviews | Anspruch der Band, Label-Erfolg, größere Bühnen |
| **Zusammenhalt** | Durchschnitt und schwächste Beziehung der Band (kein eigener Speicherwert) | Beziehungen | Anzeige; über die Beziehungen indirekt Wohlbefinden |
| **Stimmung** | Ampel (grün / gelb / rot) | Wohlbefinden aller Mitglieder, vor allem des unzufriedensten | Songschreiben, erlaubte Anordnungen, Weggang |
| **Songs geschrieben** | fertige Lieder, die produziert werden könnten | Zeit × Stimmung | Voraussetzung für Platten; ein Stau macht unzufrieden |

### Label

| Wert | Was es ist |
|---|---|
| **Erfolg** | Summe des Ruhms aller Bands; steht in der Rangliste |

## Beziehung

- Skala **−10 bis +10, Start immer 0** (neue Mitglieder kennen sich nicht). Unter 0 ist schlechter als gar nichts; +10 sind beste Freunde.
- **Passung** zweier Musiker (−1 bis +1), aus: Charakter-Paar (Reibung, Teamplayer, Partytier + Partytier, Ruhepol), Genre
  (gleiches Hauptgenre +, zwei gemeinsame ++, keins −), Können-Abstand (sehr großer Abstand −). Die Passung braucht keine Anzeige.
- Gemeinsame Erlebnisse (Probe, Auftritt, Aufnahme) bewegen die Beziehung auf einen **Zielwert** zu: ca. `10 × Passung + 3`.
  Wer sich gut passt, kommt hoch; Neutrale wachsen langsam auf ein mittleres Niveau; wer sich stark reibt, **kann nicht gut werden**
  (zum Beispiel Passung −0,6 → Ziel −3: Das belastet die Band dauerhaft). Solche Konstellationen sind absichtlich möglich.
- **Auseinander:** Auch wer nicht mehr in derselben Band ist, behält die Beziehung (in der Musikerkartei in den Details zu sehen).
  Sie **driftet langsam auf 0 zurück** (positive wie negative), etwa 0,2 Punkte je Stunde. Gemeinsames Spielen läuft wieder auf den Zielwert zu.
- **Anzeige:** Auf der Bandseite hat jedes Profil unter sich einen **Kreis pro Mitmusiker** (bei 7 Mitgliedern 6 Kreise). Die Farbe läuft **stufenlos**:
  Mitte **grau**, nach plus immer kräftigeres **Grün**, nach minus immer kräftigeres **Rot**. Eine enge Freundschaft ist ein strahlend grüner Kreis,
  „noch fremd“ ein grauer. Tippen auf einen Kreis zeigt, welche zwei Musiker es sind, und warum.

## Wohlbefinden und Bedürfnisse

Wohlbefinden ist ein versteckter Wert 0–100 (Start ca. 60). Er setzt sich aus Einflüssen zusammen, die kommen und gehen:

| Einfluss | Wirkung |
|---|---|
| Durchschnitt der Beziehungen | gute Beziehungen heben, schlechte ziehen runter |
| Können-Unterschied | zu schwach: überfordert; zu stark: gelangweilt. Kleiner Unterschied ist gut (der Schwächere lernt schneller) |
| Genre-Passung zur Band | wer das Genre der Band nicht mag, leidet |
| Anordnungen des Labels | je nach Charakter + oder − (siehe Tabelle) |
| Produktion gegen Anspruch | Studio besser als der Anspruch: Freude; schlechter: Ärger |
| Songstau (Band-Ebene) | liegen viele Songs ohne Produktion, sinkt die Stimmung aller |
| Instrument | ein gutes Instrument hebt, vor allem bei Diva; ein schlechtes drückt |
| Wünsche, die lange offen bleiben | ziehen langsam nach unten |
| **Miesepeter** | Wer selbst rot ist, zieht die anderen mit runter: mit jeder schlechten Beziehung mehr, aber auch Freunde leiden mit |

Sichtbar: **Gesicht** (😀 grün ab etwa 60, 😐 gelb 35–59, 😠 rot unter 35). Keine Zahl, kein langer Text.

### Charakter → was sie mögen

Jede Anordnung ist beliebt (+), unbeliebt (−) oder egal. Daraus entstehen die **Wünsche** („Bedürfnisse“), die eine Band äußert.

| Charakter | mag | mag nicht | äußert zum Beispiel |
|---|---|---|---|
| Ehrgeizig | Proben, Aufnahmen, größere Bühnen, Kurse | lange Pausen, Auftritte unter Würde | „Wir wollen weiter!“ |
| Gelassen | Pausen, Ruhe | viele Auftritte hintereinander, Druck | „Wir brauchen mal Ruhe.“ |
| Perfektionist | Proben, gutes Studio | schlechtes Studio, Hast | „So können wir nicht aufnehmen.“ |
| Chaot | Auftritte, Spontanes | viele Proben | „Nicht schon wieder proben!“ |
| Rampensau | Auftritte, Interviews | lange ohne Bühne | „Ab auf die Bühne!“ |
| Diva | Rampenlicht, bessere Bühne und Studio, gutes Instrument | gemeinsames Hintenanstehen, billige Produktion | „Wir haben was Besseres verdient.“ |
| Teamplayer | gemeinsame Proben und Aufnahmen | Interviews einzeln | – (stabilisiert die Band) |
| Ruhepol | Ruhe | – | dämpft Reibung der anderen |
| Partytier | Auftritte, Feiern | Proben | „Wann ist der nächste Gig?“ |

Typische **Wünsche** (acht Symbole): Auftritt, Probe, Aufnahme, Ruhe, Kurs/Förderung, besseres Instrument, bessere Bühne/Studio, Songs produzieren.
Jeder Wunsch hat ein Symbol und einen Satz und ist mit einer passenden Anordnung verknüpft (ein Tipp darauf führt dorthin oder erfüllt ihn direkt).
Wünsche ergeben sich aus der Lage: **Wer kein Instrument hat, wünscht sich eins; wer mit besserem Können sein Instrument überholt hat, wünscht sich ein besseres.**
Je länger ein Wunsch offen bleibt, desto mehr drückt er das Wohlbefinden.

### Anspruch

Der **Anspruch** der Band steigt mit dem Ruhm: Wer noch nichts erreicht hat, freut sich über alles, was das Label ermöglicht.
Diva, Ehrgeizige und Perfektionisten erhöhen ihn, Gelassene senken ihn; auch ein bekannter Musiker in einer sonst unbekannten Band hebt ihn.
Der Anspruch kann also **höher sein, als der Ruhm wirtschaftlich rechtfertigt**: Die Band wünscht sich dann etwas, das das Label Geld kostet statt einzubringen.
Das ist der Moment, in dem das Label abwägen muss. Anspruch betrifft **Studio, Bühne und Produktion**. Eine Anordnung, die den
Anspruch übertrifft, macht glücklich (auch eine unzufriedene Band lässt sich so aufhellen); eine darunter ärgert.

## Stimmung der Band

- **Ampel** nach dem unzufriedensten Mitglied: grün nur, wenn alle glücklich sind; gelb, wenn jemand angespannt ist; rot, wenn jemand sehr unzufrieden ist.
  Für die Geschwindigkeit des Songschreibens zählt ein fließender Wert (70 % unzufriedenster, 30 % Durchschnitt).
- **Sprechblase** auf dem Band-Knopf: nur ein Hinweis, dass die Band einen Wunsch geäußert hat (kleine Blase, kein Text).
- **Rot friert die Band ein.** Eine rote Band schreibt keine Songs mehr, ihre Stimmung tickt nur, solange du **online** bist,
  und man kann ihr nichts abverlangen, worauf sie keine Lust hat: keine Auftritte unter ihrer Würde, keine Promo, nichts, was ein Mitglied
  stark verärgert. Offline passiert **nichts Schlechtes**, die Band bleibt auf Rot stehen.
- **Geduld:** Bleibt eine Band online im Roten, läuft eine **Geduld** ab (Start: 30 Minuten Spielzeit; Warnungen bei 2/3 und 1/3).
  Danach verlässt sie das Label. Jede Anordnung, die die Stimmung hebt, hält die Geduld an. Man hat also immer Mittel und Wege.
- **Ein Unzufriedener zieht alle runter.** Wer rot ist und schlechte Beziehungen hat, drückt die Stimmung der ganzen Band. Das Label steht so vor der
  Entscheidung, ihn **schnell aus der Band zu nehmen**, bevor alle schlecht drauf sind. Das Gehen eines Musikers mit schlechten Beziehungen tut der Band gut;
  geht jemand mit guten Beziehungen, stürzt das die anderen in Unzufriedenheit (egal aus welchen Gründen).
- **Wer geht zuerst:** Ein Musiker mit Rot **und** schlechten Beziehungen verlässt die Band von selbst (er landet im Katalog). Sind die Beziehungen gut,
  geht eher die **ganze Band zusammen** vom Label.
- **Warnung vor Folgen:** Jede Anordnung, die die Stimmung ernsthaft drücken kann, zeigt vorher eine deutliche Warnung (Farbe, Gesicht, wer betroffen ist).
  Es darf nie aus Versehen passieren.

### Offline und Einfrieren

- **Alles läuft in Echtzeit, auch offline:** Songs werden geschrieben, die Tantiemen laufen (und mit ihnen Ruhm aus den Charts), Wünsche bleiben offen,
  Stimmung und Beziehungen verändern sich. So hat man beim Einloggen immer etwas zu tun, und Wenigspieler haben keinen Vorteil.
- **Rot friert ein.** Sobald eine Band rot ist, bleibt sie so stehen, **bis du wieder da bist**: keine Songs mehr, keine weitere Verschlechterung,
  kein Weggang. Eine Band geht also nie, während du schläfst.
- **Geduld** (nur online): Erst wenn du eingeloggt bist und eine Band im Roten lässt, läuft ihre Geduld ab (Startwert 30 Minuten, mit Warnungen bei 2/3 und 1/3).
  Danach verlässt sie das Label. Du hast also immer die Gelegenheit und die Mittel, es zu verhindern.
- Wie schnell eine Band offline von grün nach rot sinkt, stellen wir so ein, dass es bei unerfüllten Wünschen etwa **ein bis zwei Tage** dauert.

## Songs schreiben

- Die Band schreibt **von selbst**, im Tempo ihrer Stimmung: grün etwa 1 Song pro Stunde, gelb halb so schnell, rot gar nicht.
- **Obergrenze 24 Songs.** Ab 12 liegenden Songs sinkt die Stimmung der Band langsam („Wir wollen aufnehmen!“).
- Eine Platte braucht mindestens 3 Songs (EP) bzw. 7 (LP) aus diesem Vorrat; beim Aufnehmen wählt das Label, wie viele.

## Anordnungen des Labels

| Anordnung | Wirkung | Bedingungen |
|---|---|---|
| **Proben** | **Hauptquelle für Können** und Beziehung; Wohlbefinden je nach Charakter | Band nicht rot, nicht beschäftigt, alle haben ein Instrument |
| **Aufnehmen** | Songs → Platte. Label wählt **Studio** und **Produzent** (verschiedene Preise); Qualität = Sound × Produktion | Songs vorhanden, Band nicht rot; Produktion unter dem Anspruch ärgert |
| **Auftritt buchen** | Gage, Ruhm, Bekanntheit; Ort mit festen Kosten und Gewinn-Schätzung | Band nicht rot; Ort unter der Würde ärgert |
| **Interview/Promo-Anfrage** | Bekanntheit und Ruhm | Eingang von Anfragen, passend zur Bekanntheitsstufe |
| **Kurs** | einzelner Musiker fördern (Können) | Musiker frei; wie bisher Tageskurse |
| **Instrument kaufen** | gezielt für einen Musiker, Preis und Wert sichtbar | Geld |
| **Manager einsetzen** | siehe unten | einmalig bezahlt |
| **Cover wählen** | aus Entwürfen für die Platte | bei der Aufnahme |

Rot sperrt alles, was die Band nicht „brennend“ will; erlaubt bleiben die Anordnungen, die helfen (Pause, Wunsch erfüllen, etwas Besseres).

### Proben (Hauptquelle für Können)

Die alte Probe war „Wasser auf den heißen Stein“. Jetzt ist sie der Weg nach oben, **ohne Stufen-Bremse**: Die Lernkurve `(1 − Können/110)^2,5`
bremst von selbst. Ziel für Startwerte (Spielzeit, ca. 15 Minuten je Probe):
Stufe 1 in etwa einer Stunde, Stufe 2 in etwa 15 Stunden, Stufe 3 in etwa 80 Stunden. Je Musiker verschieden (Talent; Chaot/Ehrgeizig wirken
auf Stimmung statt auf Können). Ein bisschen mehr Können gewinnt, wer unter Besseren spielt.

### Aufnehmen: Studio und Produzent

- **Studio:** Garage / Standard / Profi / legendär (steigende Preise, Qualität und Ansprüche).
- **Produzent:** keiner / Nachwuchs / etabliert / Star (steigende Preise und Qualität).
- **Produktionsniveau** (0–3) = Studio + Produzent. **Qualität** der Platte = Sound der Band × Produktionsniveau (die bisherige
  Qualitäts-Formel, nur ohne Minispiel). Chartpunkte bleiben wie in `kern.md` (nur die Platte zählt, kein Ruhm-Bonus).
- **Anspruch** der Band gegen Produktionsniveau: darunter = ärgert, gleich = zufrieden, darüber = freut sich.
  Eine mäßige Band freut sich über ein gutes Studio, eine berühmte findet ein einfaches unter ihrer Würde.

### Auftritt buchen

- Das Label sieht die **Orte, die zur Band passen** (nach Ruhm, plus ein oder zwei Stufen darüber). Zu jedem: **Kosten** (feststehend, vorher bekannt) und eine
  **Gewinn-Schätzung** (eine Spanne; der echte Gewinn hängt vom Auftritt ab). Dazu eine kleine Ampel, ob der Sound der Band die **Erwartung** des Ortes erfüllt
  (wie bisher `expect`; sonst bleibt der Saal halb leer und der Auftritt wird eine Enttäuschung).
- Meist ist die Wahl einfach: **der Ort mit dem größten erwarteten Gewinn.** Es wird nur schwierig, wenn die Band **etwas anderes will** als das Wirtschaftliche:
  ein Wunsch nach der größeren Bühne (zum Beispiel Stadion) kostet das Label womöglich mehr, als er einbringt. Dann muss man abwägen:
  Geld gegen Stimmung. Ein Ort **unter** dem Anspruch der Band ärgert sie, auch wenn er sicher Gewinn bringt.

### Interviews und Promo-Anfragen

- Anfragen kommen als **Mitteilung** (Zeitschrift fragt nach Interview mit X). Annehmen schickt den Musiker für kurze Zeit weg.
- **Reichweite der Anfrage** hängt an der **Bekanntheitsstufe** des Musikers: Stufe 0 bekommt Schülerzeitung und Stadtblog,
  Stufe 1 Stadtmagazin, Stufe 2 Musikzeitschrift und Radio, Stufe 3 große Magazine und Fernsehen. Zufall gibt es nur darin, welches Medium.
- **Gewinn = Anteil des Weges zur nächsten Stufe** (zum Beispiel 10–15 %). So ist jede Stufe gleich gut erreichbar, ohne Bremse; für Bekannte
  bringt ein kleines Blatt nichts, weil die Anfragen ihrer Stufe entsprechen.

### Instrumente, Läden, Talente

- **Instrumente** werden gezielt gekauft: Auswahl nach Art, Werte und Preis sichtbar, keine Pakete. Musiker äußern Wünsche danach (Diva zuerst).
  Ohne Instrument kann eine Band nicht proben.
- **Musiker aufnehmen („Vorspielen“):** Das Label wählt Genre, Instrument und Budget und sieht dann eine kurze Liste von Kandidaten (Können,
  Charakter, Genres sichtbar), aus der es einen unter Vertrag nimmt. Statt Zufallspaketen.
- **Läden** werden dafür überarbeitet: Instrumente gezielt, Manager in der Stellenbörse, Musiker über das Vorspielen. Keine Kartenpakete mehr.
- Fortlaufende Kosten gibt es nicht, nur einmalige Ausgaben.

### Manager

- Gehören zum Label und werden **einmalig bezahlt** (keine laufenden Kosten). Ein Manager betreut **mehrere Bands** und **teilt seine Leistung** je nach Zahl
  der Bands (wie heute: bis zu 3). Eine sehr gute, anspruchsvolle Band bekommt sinnvollerweise einen eigenen, mehrere kleine teilen sich einen.
- **Stellenbörse** statt Kartenladen: **täglich wechselnd** werden **ein bis drei** Manager angeboten, mit sichtbaren Werten, Schwerpunkt-Genres und Stil, und
  oft **sehr teuer**. Den passenden zu finden ist Teil der Aufgabe.
- Wie im echten Label der Betreuer („A&R“): Jeder hat **ein bis zwei Schwerpunkt-Genres** und einen **Stil** (Charakterzug). Die **Passung** zu jeder Band
  ergibt sich aus Genre-Schnittmenge und Charakter. Gute Passung verstärkt, schlechte schwächt.
- **Wirkung:** Gespür (Wünsche früher und klarer erkennen), Vermittler (dämpft die Stimmungsfolgen schwieriger Anordnungen), dazu Verhandler (bessere Gage) und
  Netzwerker (bessere Anfragen).

### Cover

Das Label wählt aus **drei Entwürfen** (Designer günstig / solide / aufwendig), erzeugt aus den bisherigen Bausteinen (Motiv, Farben, Stil, Anordnung).
Ein stimmiger Stil zum Genre zählt. Kein Automat.

### Klang der Band („Profil“) – später

Idee: Die Band spielt manchmal **zwei Versionen** eines Songs vor, das Label wählt eine. Aus den Wahlen entsteht ein Klangprofil. Machbar ohne neue
Aufnahmen aus den vorhandenen Bausteinen (`Styles`: Akkordfolgen, Grooves, Muster je Genre) mit Synthesizer-Klang; die echten Aufnahmen bleiben
als Grundlage. Einzelne Wahlen verändern zum Beispiel Tempo-Gefühl, Akkordfolge, Groove. Das ist eine **spätere Ausbaustufe**.

## Oberfläche

- **Hauptmenü** (oben): Übersicht, Katalog, Läden, Lager, Börse, Charts.
- **Band-Leiste** (links, immer sichtbar, bleibt verschiebbar): große Knöpfe, je Band mit
  **Stimmung sehr deutlich** (großes Gesicht in Ampelfarbe), **Songs geschrieben** (Zahl mit Icon), **Ruhm als Stufe/Sterne**, kleiner Wunsch-Hinweis (leere Blase), Streifen wenn beschäftigt.
- **Bandseite** (Klick auf die Band, kein Untermenü): Kopf mit Name und Stimmung; **Anordnungen** als Knöpfe (Proben, Aufnehmen, Auftritt);
  Musikerliste als **Zeilen** (Passfoto, Name, Instrument, Können, Gesicht, Beziehungskreise); darunter **Werte mit Aufschlüsselung** (Sound, Ruhm, Zusammenhalt,
  Stimmung mit den Einflüssen, die gerade wirken); Wünsche der Band oben; **Auszeichnungen** als kleine Symbole. Die **Chronik** ist zugeklappt und lässt sich per Tippen öffnen. Keine Bühne.
- **Übersicht des Labels:** alle geäußerten Wünsche aller Bands als **Mitteilungen** untereinander; Tippen öffnet die Band, ein Knopf erfüllt direkt.
  Dazu Geld, Charts, Rangliste.
- **Katalog** bleibt die Künstlerkartei (Passfotos), mit Beziehungen zu früheren Mitmusikern in den Details.
- **Aufnahme-Dialog** (Studio, Produzent, Songs, Cover) mit Preisen.

### Auszeichnungen

Deutlich weniger als bisher, nur **echte Pokale**: zum Beispiel Goldene Schallplatte, Platin-Schallplatte, Platz 1 der Charts, Große Bühne (Halle/Arena),
später „Battle of the Bands“ gewonnen. Kleinkram wie „Erster Gig“ fällt weg und steht höchstens in der Chronik.

## Bleibt und geht

| Bleibt | Geht (erstmal) |
|---|---|
| Katalog/Kartei, Wechselbörse, Entlassen, Vorspielen | Solo-Üben, Etüden, Instrument-Minispiele |
| Bandproben (als Hauptquelle für Können), Kurse | Jam-Café, Straßenmusik, Open Stage, Fan-Musiker |
| Aufnahmen (ohne Minispiele), Cover (als Wahl) | Einzel-Promo (kommt als Interview-Anfrage zurück) |
| Gigs mit Live-Ticker und Bühne, Charts, Tantiemen | Mischpult, Master-Mix, Cover-Automat |
| Instrumente (gezielt), Manager (einmalig) | |
| Bandseite ohne Bühne; Bühne bei Auftritten | |

Wir reduzieren stark und schauen dann, was wirklich fehlt.

## Übergang für bestehende Labels

Beim ersten Login nach dem Umbau ein Willkommens-Dialog (Saison 2). Labelname und Konto bleiben.
1. **Bis zu zwei Bands mitnehmen** (so viele Plätze hat ein Label am Anfang). Sie behalten **Namen, Mitglieder, Aussehen, Charakter und Können**,
   starten aber **ohne Ruhm und Bekanntheit** und ohne Platten; die **Beziehung** wird aus dem alten Zusammenspiel und der Chemie nach dem neuen System berechnet.
   Jeder Musiker bekommt ein einfaches Standard-Instrument (die guten Instrumente bleiben zurück). Ein besonderer Chronik-Eintrag erinnert an die alte Zeit.
2. **Oder neu starten** mit einem kleinen Startbonus (Geld).
3. Alle anderen Bands, der Katalog und das Lager entfallen. Alte Platten und Bands kommen in eine **Ruhmeshalle der vorigen Saison** (mit Bildern und Namen), damit
   die Geschichten nicht verloren gehen. Die Charts beginnen neu.

**Stolpersteine:** Mitgenommene Musiker haben Können und damit einen hohen Marktwert. Würde man sie auflösen und an der Börse verkaufen, brächte das
mehr Geld als der Bonus. Deshalb: **Mitgenommene Musiker lassen sich nicht an die Börse stellen** (sie dürfen nur entlassen werden). Mehr als zwei Bands gibt
es nicht, weil die Plätze auch bei Label-Ruhm 0 begrenzt sind.

## Wer wirkt auf wen (nur stabilisierende Kreisläufe)

- **Ruhm → Anspruch → Stimmung:** wer berühmt ist, verlangt mehr (kostet das Label mehr). Gewollt, bremst von selbst.
- **Stimmung → Songs → Stau → Stimmung:** Songs machen glücklich, bis sie liegenbleiben. Gewollt, bremst von selbst.
- Es gibt **keinen** Kreislauf, in dem sich ein Wert über einen anderen von selbst aufschaukelt (Grundsatz aus `kern.md`: Ruhm darf nie den eigenen Zuwachs verstärken).

## Umbau in Schritten (jeweils nur in der Testversion)

1. Konzept und Auslagerung dokumentieren (diese Dateien).
2. Datenmodell: Wohlbefinden, Beziehungen (Paare), Songs, Spielzeit, Stimmung.
3. Band-Leiste und Bandseite (neue Oberfläche, ohne Untermenü).
4. Anordnungen: Proben (neue Können-Quelle), Auftritt buchen mit Kosten und Gewinn-Schätzung.
5. Aufnehmen mit Studio und Produzent, Cover-Wahl.
6. Wünsche, Mitteilungen, Charakter-Tabelle.
7. Interview-Anfragen, Kurse als Einzelförderung, Instrumente gezielt, Vorspielen.
8. Manager neu.
9. Übergang für bestehende Labels.
10. Entfernen der ausgelagerten Teile aus dem Code.

## Offene Fragen

- Genaue Zahlen (Songtempo, Geduld, Beziehungsraten, Zeit bis Rot, Studio-/Produzentenpreise, Kosten und Gewinn der Orte) werden beim Bauen abgestimmt.
- Wie viele Bands haben eure Labels, und will jemand mehr als zwei mitnehmen? Was bekommen die übrigen Musiker?
- Klangprofil (A/B-Vorspielen): erst nach den ersten Schritten.
- Auszeichnungen: endgültige kurze Liste.
