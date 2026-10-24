# Signed auf Label-Ebene (Umbau-Konzept)

Stand: Entwurf, noch nichts gebaut. Wird in kleinen Schritten in der Testversion umgesetzt; das echte Spiel bleibt, wie es ist.
Was dafür herausgenommen wird, steht in [`ausgelagert-musikerspiel.md`](ausgelagert-musikerspiel.md). Bis zum Umbau gilt für das
echte Spiel weiter [`kern.md`](kern.md). Alle Zahlen hier sind **Startwerte zum Nachjustieren**.

## Leitidee

Du bist das **Label**, nicht die Band. Die Bands haben ein Eigenleben: Sie schreiben von selbst Songs, haben Bedürfnisse und
Probleme und äußern sie. Das Label gibt **Anordnungen** (Proben, aufnehmen, auftreten, fördern, ausstatten), und jede
Anordnung wirkt auf die **Zufriedenheit** der Band. Eine zufriedene Band schreibt schneller Songs, macht gute Auftritte und
Platten. Wer sie vernachlässigt oder ihr Unzumutbares abverlangt, riskiert, dass sie geht.

Zwei Grundsätze, die für alles gelten:

1. **Gezielt ausgeben, nicht würfeln.** Kein Geld für Pakete, bei denen man schaut, was herauskommt. Bei allem, was Geld kostet, sieht man vorher, was man bekommt.
2. **Ohne Lesen verständlich.** Warnungen, Wünsche und Folgen werden mit Farben, Gesichtern, Symbolen und kurzen Sätzen gezeigt.

## Die Ebenen und ihre Werte

### Musiker

| Wert | Was es ist | Wächst durch | Wirkt auf |
|---|---|---|---|
| **Können** | wie gut am Instrument (je Instrument, Stufen 0–3 bei 35 / 60 / 85) | Bandproben (Hauptquelle), Kurse | Sound, Wohlbefinden (Unterschied zu den anderen) |
| **Bekanntheit** | 3 Stufen in der Anzeige (Unbekannt, Lokal bekannt, Szenegröße, Star) | Auftritte, Interviews (Promo) | Ruhm der Band |
| **Charakter** | 1–2 Eigenschaften, von Anfang an | – | Beziehung (Passung), Wohlbefinden (Bedürfnisse), teils Wachstum von Können/Bekanntheit |
| **Genre** | Lieblingsgenres, von Anfang an | – | Sound (Reinheit, Hybrid, Crossover), Beziehung (gleiche Genres verstehen sich) |
| **Beziehung** | je Mitmusiker ein Wert, −100 bis +100 | gemeinsame Proben, Auftritte, Aufnahmen, je nach Passung | Wohlbefinden |
| **Wohlbefinden** | versteckter Wert 0–100, sichtbar nur als Gesicht und Farbe | alles Mögliche (siehe unten) | Zufriedenheit der Band; bei Rot droht der Weggang |

### Band

| Wert | Was es ist | Hängt ab von | Wirkt auf |
|---|---|---|---|
| **Sound** | wie gut die Band klingt | Können aller Mitglieder, Genre-Stufe, Besetzung, Instrumente | Erfolg der Platten (Qualität, Chartpunkte), Auftritte |
| **Ruhm** | wie berühmt die Band ist | Bekanntheit der Mitglieder, erfolgreiche Auftritte, Platten, Interviews | Anspruch der Band, Label-Erfolg, größere Bühnen |
| **Zusammenhalt** | Durchschnitt und schwächste Beziehung der Band (kein eigener Speicherwert) | Beziehungen | Anzeige; über die Beziehungen indirekt Wohlbefinden |
| **Zufriedenheit** | Ampel (grün / gelb / rot) | Wohlbefinden aller Mitglieder, vor allem des unzufriedensten | Songschreiben, erlaubte Anordnungen, Weggang |
| **Songs geschrieben** | fertige Lieder, die produziert werden könnten | Zeit × Zufriedenheit | Voraussetzung für Platten; ein Stau macht unzufrieden |

### Label

| Wert | Was es ist |
|---|---|
| **Erfolg** | Summe des Ruhms aller Bands; steht in der Rangliste |

## Beziehung

- Skala **−100 bis +100, Start immer 0** (neue Mitglieder kennen sich nicht). Unter 0 ist schlechter als gar nichts.
- **Passung** zweier Musiker (−1 bis +1), aus: Charakter-Paar (Reibung, Teamplayer, Partytier + Partytier, Ruhepol), Genre
  (gleiches Hauptgenre +, zwei gemeinsame ++, keins −), Können-Abstand (sehr großer Abstand −). Die Passung braucht keine Anzeige.
- Gemeinsame Erlebnisse (Probe, Auftritt, Aufnahme) bewegen die Beziehung auf einen **Zielwert** zu: ca. `100 × Passung + 30`.
  Wer sich gut passt, kommt hoch; Neutrale wachsen langsam auf ein mittleres Niveau; wer sich stark reibt, **kann nicht gut werden**
  (zum Beispiel Passung −0,6 → Ziel −30: Das belastet die Band dauerhaft). Solche Konstellationen sind absichtlich möglich.
- **Auseinander:** Auch wer nicht mehr in derselben Band ist, behält die Beziehung (in der Musikerkartei in den Details zu sehen).
  Sie **driftet langsam auf 0 zurück** (positive wie negative), etwa 2 Punkte je Spielstunde. Gemeinsames Spielen läuft wieder auf den Zielwert zu.
- **Anzeige:** Auf der Bandseite hat jedes Profil unter sich einen **Kreis pro Mitmusiker**: grün (gut, ab etwa +20), grau (noch fremd, −20 bis +20),
  gelb (angespannt), rot (zerrüttet, unter etwa −50). Tippen auf einen Kreis zeigt, welche zwei Musiker es sind, und warum.
  Bei 7 Mitgliedern sind das 6 Kreise je Profil. Grau statt Gelb für „noch fremd“, damit Neue nicht wie eine Warnung aussehen.

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

### Anspruch

Der **Anspruch** der Band steigt mit dem Ruhm: Wer noch nichts erreicht hat, freut sich über alles, was das Label ermöglicht.
Diva und Perfektionist erhöhen ihn, Gelassene senken ihn. Anspruch betrifft **Studio, Bühne und Produktion**. Eine Anordnung, die den
Anspruch übertrifft, macht glücklich (auch eine unzufriedene Band lässt sich so aufhellen); eine darunter ärgert.

## Zufriedenheit der Band

- **Ampel** nach dem unzufriedensten Mitglied: grün nur, wenn alle glücklich sind; gelb, wenn jemand angespannt ist; rot, wenn jemand sehr unzufrieden ist.
  Für die Geschwindigkeit des Songschreibens zählt ein fließender Wert (70 % unzufriedenster, 30 % Durchschnitt).
- **Sprechblase** auf dem Band-Knopf: nur ein Hinweis, dass die Band einen Wunsch geäußert hat (kleine Blase, kein Text).
- **Rot friert die Band ein.** Eine rote Band schreibt keine Songs mehr, ihre Zufriedenheit tickt nur, solange du **online** bist,
  und man kann ihr nichts abverlangen, worauf sie keine Lust hat: keine Auftritte unter ihrer Würde, keine Promo, nichts, was ein Mitglied
  stark verärgert. Offline passiert **nichts Schlechtes**, die Band bleibt auf Rot stehen.
- **Geduld:** Bleibt eine Band online im Roten, läuft eine **Geduld** ab (Start: 30 Minuten Spielzeit; Warnungen bei 2/3 und 1/3).
  Danach verlässt sie das Label. Jede Anordnung, die die Stimmung hebt, hält die Geduld an. Man hat also immer Mittel und Wege.
- **Wer geht zuerst:** Ein Musiker mit Rot **und** schlechten Beziehungen verlässt die Band (er landet im Katalog). Sind die Beziehungen gut,
  geht eher die **ganze Band zusammen** vom Label. Geht jemand mit guten Beziehungen, stürzt das die anderen in Unzufriedenheit.
  Manchmal tut es einer Band aber auch gut, wenn jemand geht, mit dem die Beziehungen schlecht sind.
- **Warnung vor Folgen:** Jede Anordnung, die die Stimmung ernsthaft drücken kann, zeigt vorher eine deutliche Warnung (Farbe, Gesicht, wer betroffen ist).
  Es darf nie aus Versehen passieren.

### Spielzeit

Alle **Stimmungs- und Beziehungsänderungen** laufen nur in der **Spielzeit** (solange die App offen ist, grob nach einem Lebenszeichen alle
60 Sekunden, je Lücke höchstens 2 Minuten). **Aktionen** (Probe, Aufnahme, Auftritt, Kurs) laufen wie bisher in Echtzeit weiter, auch offline.
**Songs** werden auch offline geschrieben, mit dem eingefrorenen Tempo der letzten Zufriedenheit, bis zu einer Obergrenze.
So verliert niemand etwas im Urlaub, und niemand kann Stimmung „aussitzen“.

## Songs schreiben

- Die Band schreibt **von selbst**, im Tempo ihrer Zufriedenheit: grün etwa 1 Song pro Stunde, gelb halb so schnell, rot gar nicht.
- **Obergrenze 24 Songs.** Ab 12 liegenden Songs sinkt die Zufriedenheit der Band langsam („Wir wollen aufnehmen!“).
- Eine Platte braucht mindestens 3 Songs (EP) bzw. 7 (LP) aus diesem Vorrat; beim Aufnehmen wählt das Label, wie viele.

## Anordnungen des Labels

| Anordnung | Wirkung | Bedingungen |
|---|---|---|
| **Proben** | **Hauptquelle für Können** und Beziehung; Wohlbefinden je nach Charakter | Band nicht rot, nicht beschäftigt, alle haben ein Instrument |
| **Aufnehmen** | Songs → Platte. Label wählt **Studio** und **Produzent** (verschiedene Preise); Qualität = Sound × Produktion | Songs vorhanden, Band nicht rot; Produktion unter dem Anspruch ärgert |
| **Auftritt buchen** | Gage, Ruhm, Bekanntheit; Ort aus **3 Angeboten** | Band nicht rot; Ort unter der Würde ärgert |
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

- Drei Angebote: **darunter** (sicher, wenig Gage, ärgert Bands mit hohem Anspruch), **passend**, **Wagnis** (eine Stufe über dem Ruhm).
- Gegen die **Erwartung** des Ortes (wie bisher `expect`) zeigt eine kleine Ampel, ob der Sound der Band reicht. Reicht er nicht, bleibt der Saal
  halb leer, und der Auftritt wird eine Enttäuschung (Stimmung und Ruhm leiden). So ist das Wagnis eine echte Entscheidung.

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
- Fortlaufende Kosten gibt es nicht, nur einmalige Ausgaben.

### Manager

- Gehören zum Label, werden **einmalig bezahlt** (keine laufenden Kosten) und einer **Band zugewiesen**. Wie im echten Label der Betreuer („A&R“),
  der mit der Band arbeitet. Realistisch haben solche Leute Szenen, in denen sie sich auskennen: Jeder Manager hat daher **ein bis zwei Schwerpunkt-Genres**
  und einen **Stil** (Charakterzug). Die **Passung** zur Band entsteht aus Genre-Schnittmenge und Charakter.
- **Wirkung:** Gespür (Wünsche der Band früher und klarer erkennen), Vermittler (dämpft die Stimmungsfolgen von Anordnungen), dazu die bisherigen
  Fähigkeiten (Verhandler: bessere Gage; Netzwerker: bessere Anfragen). Gute Passung verstärkt, schlechte schwächt.

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
  **Zufriedenheit sehr deutlich** (großes Gesicht in Ampelfarbe), **Songs geschrieben** (Zahl mit Icon), **Ruhm als Stufe/Sterne**, kleiner Wunsch-Hinweis (leere Blase), Streifen wenn beschäftigt.
- **Bandseite** (Klick auf die Band, kein Untermenü): Kopf mit Name und Zufriedenheit; **Anordnungen** als Knöpfe (Proben, Aufnehmen, Auftritt);
  Musikerliste als **Zeilen** (Passfoto, Name, Instrument, Können, Gesicht, Beziehungskreise); darunter **Werte mit Aufschlüsselung** (Sound, Ruhm, Zusammenhalt,
  Zufriedenheit mit den Einflüssen, die gerade wirken); Wünsche der Band oben; kleiner Verlauf (Chronik) unten. Keine Bühne.
- **Übersicht des Labels:** alle geäußerten Wünsche aller Bands als **Mitteilungen** untereinander; Tippen öffnet die Band, ein Knopf erfüllt direkt.
  Dazu Geld, Charts, Rangliste.
- **Katalog** bleibt die Künstlerkartei (Passfotos), mit Beziehungen zu früheren Mitmusikern in den Details.
- **Aufnahme-Dialog** (Studio, Produzent, Songs, Cover) mit Preisen.

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

Beim ersten Login nach dem Umbau ein Willkommens-Dialog (Saison 2):
1. **Neu starten:** Labelname und Konto bleiben, Bands und Katalog fallen weg, dazu ein Startbonus (Geld).
2. **Eine oder zwei Bands mitnehmen:** Sie starten wie neu: **kein Ruhm, keine Bekanntheit**, aber **Können der Mitglieder** und eine **Beziehung aus der alten Zeit**
   (berechnet nach dem neuen System). Ein besonderer Chronik-Eintrag erinnert daran.
3. Alte Platten kommen in eine **Ruhmeshalle der vorigen Saison**; die Charts beginnen neu.

Alles zu übernehmen wäre für die Balance fatal.

## Wer wirkt auf wen (nur stabilisierende Kreisläufe)

- **Ruhm → Anspruch → Zufriedenheit:** wer berühmt ist, verlangt mehr (kostet das Label mehr). Gewollt, bremst von selbst.
- **Zufriedenheit → Songs → Stau → Zufriedenheit:** Songs machen glücklich, bis sie liegenbleiben. Gewollt, bremst von selbst.
- Es gibt **keinen** Kreislauf, in dem sich ein Wert über einen anderen von selbst aufschaukelt (Grundsatz aus `kern.md`: Ruhm darf nie den eigenen Zuwachs verstärken).

## Umbau in Schritten (jeweils nur in der Testversion)

1. Konzept und Auslagerung dokumentieren (diese Dateien).
2. Datenmodell: Wohlbefinden, Beziehungen (Paare), Songs, Spielzeit, Zufriedenheit.
3. Band-Leiste und Bandseite (neue Oberfläche, ohne Untermenü).
4. Anordnungen: Proben (neue Können-Quelle), Auftritt buchen mit 3 Angeboten.
5. Aufnehmen mit Studio und Produzent, Cover-Wahl.
6. Wünsche, Mitteilungen, Charakter-Tabelle.
7. Interview-Anfragen, Kurse als Einzelförderung, Instrumente gezielt, Vorspielen.
8. Manager neu.
9. Übergang für bestehende Labels.
10. Entfernen der ausgelagerten Teile aus dem Code.

## Offene Fragen

- Genaue Zahlen (Songtempo, Geduld, Beziehungsraten, Studio-/Produzentenpreise) werden beim Bauen abgestimmt.
- Soll ein Manager mehrere Bands betreuen oder genau eine?
- Wie viele Bands dürfen beim Übergang mitgenommen werden, und kommt das Lager (Instrumente) mit?
- Klangprofil (A/B-Vorspielen): erst nach den ersten Schritten.
