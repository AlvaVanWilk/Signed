# Der Kern von Signed

Stand: vierte Runde (Läden mit Auswahl, Konto, neue Karten). Währung: Groove Coins (₲). Grundlage ist das Claude-Doc „Signed – Spielkonzept“.
Alle Zahlen stehen in [`src/Game/Balance.php`](../src/Game/Balance.php) und sind Startwerte zum Nachjustieren.

## Was spielbar ist

Der Kernkreislauf: **Musiker und Instrumente finden → Bands zusammenstellen → auftreten und Platten produzieren → Geld + Ruhm → stöbern.**

| Bereich | Umfang im Kern |
|---|---|
| Label | Konto mit Labelname, 2.000 ₲ Startkapital und ein Scout-Gutschein (keine Musiker zu Beginn) |
| Läden | Talent-Scout (5 Musiker mit Können-Stufe 1, einer unterschreibt) und sechs Läden, in denen man mehrere Karten sieht und eine mitnimmt (siehe unten) |
| Konto | Klick aufs Geld: Tantiemen pro Tag, Platten in den Charts, Kontoauszug |
| Musiker | Einmalig, zufällig erzeugt: 1–2 Instrumente, Können, Bekanntheit, Genre-Symbole, Fähigkeiten (an die Können-Stufe gebunden), Charakterzüge, „Ungewöhnlich“, Profilbild. Favoriten ohne Band stehen in der zweiten Menüzeile |
| Instrumente | 44 Modelle (11 Arten × 4 Seltenheiten); jedes Exemplar weicht pro Wert um bis zu ±12 vom Modell ab; frei tauschbar |
| Manager | Stufe 1–5, 1–2 Fähigkeiten, bis zu 3 Bands mit geteilter Leistung |
| Bands | Bis zu 8 Bands (freigeschaltet über Label-Ruhm), Genre ergibt sich aus der Besetzung, Sound, Zusammenspiel, Chemie, Ruhm |
| Aktivitäten | Band: Bandprobe, Band-Promo, Straßenmusik, Open Stage, Gig (erst mit Platte), Platte (gemischte Minispiele, eins pro Lied). Einzeln: Solo-Probe (Rhythmus oder Tonhöhe), Promo-Mission. Ohne Band: Jam-Café (Mitmusiker für die erste Band), Straßenmusik |
| Live | Live-Ticker mit Publikumsleiste für alle Auftritte |
| Charts | Gemeinsame Album-Charts aller Spieler mit täglichen Tantiemen, dazu eine Label-Gesamtliste |
| Einführung und Hilfe | Kurze Tour beim ersten Mal (5 Schritte, ein Satz je Schritt, auch für Kinder), danach die Hilfeseite (?) für alle, die tiefer einsteigen wollen |
| Wechselbörse | Musiker ohne Band für 2 Tage anbieten; verbindliche Gebote, Mindestpreis aus den Werten |
| Namen | Zufallsnamen für Bands und Platten; Bandnamen sind im ganzen Spiel eindeutig. Musiker: 236 Vornamen, 253 Nachnamen, ein Nachname kommt im eigenen Label nur einmal vor (solange noch freie da sind) |

### Bewusst noch nicht drin

Battle of the Bands, Tournee, Festival, Sonderevents (Interview, Charity), Remaster, Produzenten, ausgeschriebene Gigs, Roadies, Gastmusiker, Contest im ESC-Stil, Aufnahmen für alle Genres und Stufen.
Die Manager-Fähigkeiten **Tourplaner** und **Netzwerker** werden schon verteilt, wirken aber erst mit
Tournee bzw. ausgeschriebenen Gigs (im Spiel ausgegraut markiert).

## Regeln und Formeln

### Musiker

- Start-Können je Seltenheit: gewöhnlich 5–30, selten 15–42, episch 25–52, legendär 35–58. Aus Läden kommt also höchstens Können-Stufe 1 und keine Bekanntheit.
- 20 % spielen zwei Instrumente. Dann gilt: Hauptinstrument × 0,85, Zweitinstrument × 0,55–0,8.
  Jede Probe verbessert nur das geübte Instrument (geteilter Können-Topf).
- „Ungewöhnlich“ (2 %): Das Lieblingsgenre passt nicht zum Klischee des Instruments. Bringt mehr Ruhm.
- Zwei Leitern, unabhängig voneinander:
  - **Können-Stufe** 0–3 nach dem besten Instrument: Stufe 1 ab 35, 2 ab 60, 3 ab 85. Keine Erfahrungspunkte –
    wer übt und spielt, steigt auf. Die Stufe bestimmt Rahmenfarbe der Karte (grau, bronze, silber, gold).
  - **Bekanntheits-Stufe** 0–3 nach Ruhm: ab 20, 120, 600. Sie lässt die Karte stärker leuchten (ohne Animation).
  - Beide auf 3: **Legende**.
  - Namen der Können-Stufen: Anfänger, Fortgeschritten, Profi, Virtuose.
    Bekanntheits-Stufen: Unbekannt, Lokal bekannt, Szenegröße, Star.
  - Auf der Karte: Kartenfarbe = Können-Stufe (grau, grün, blau, lila; Legende in Regenbogenfarben), Funkeln und
    Schein = Bekanntheits-Stufe (statisch, ohne Animation). Darunter die beiden wichtigen Werte in gleicher Form:
    Können pro Instrument als 10 Kästchen und Bekanntheit als 10 Sternchen – jeweils der Weg zur nächsten Stufe,
    in der Farbe der aktuellen Stufe, dahinter die Stufe als Zahl. In Texten steht der Titel („ist an der Gitarre jetzt Profi“). Lieblingsgenres als farbige Abzeichen oben rechts; der Bildhintergrund
    ist schräg in die Farben aller Lieblingsgenres geteilt.
- Jedes Genre hat eine feste Farbe und ein Symbol – auf Karten, an Bands (Menü, Seitenleiste, Bandseite) und in
  der Genre-Auswahl.
- Fähigkeiten: eine pro Können-Stufe. Bei Stufe 1 und 2 kommt eine zufällige dazu, bei Stufe 3 wählt man aus drei.
  Talent (Können +50 % schneller), Ausstrahlung (Promo bringt 50 % mehr Bekanntheit, etwas mehr Ruhm), Charisma (mehr Ruhm),
  Nervenstark (verspielt sich live seltener), Absolutes Gehör (großzügigeres Timing), Bühnentier,
  Studiofuchs (grüner Bereich am Mischpult +4 %) und die Spezialtechniken passend zum Instrument.

### Sound

```
Beitrag    = Können × (1 + Instrumentbonus), ohne eigenes Instrument × 0,5
Instrument = Klang/100 × 0,5 × min(1, Können / (100 − Spielbarkeit))²   (darunter entfaltet es sich deutlich weniger,
             ein einfaches Instrument ist für Anfänger oft besser als ein gutes)
Sound      = (0,75 × Durchschnitt + 0,25 × schwächster Beitrag) × Genre (gemischt 0,8 / Genre-Band 1,15 / Hybrid 1,25 / Crossover 1,3)
             × (1 + 0,05 × weitere Instrumentenarten, höchstens +20 %)
             × (1 + 0,05 × Mitglieder über 2)            (volle Bühne: 7 Mitglieder × 1,25)
             × (0,7 + 0,3 × Zusammenspiel/100)             (Hybrid bis 120: × 1,06)
```

Ein schwacher Musiker zieht den Sound runter. Große Bands klingen dafür voller: Sieben Musiker mit fünf Instrumentenarten
sind bis zu 50 % stärker als ein Duo (`MEMBER_SOUND_BONUS`). Dazu bekommen sie mehr Gage (siehe Auftritte).

### Genre einer Band

- Beim Gründen wählt man kein Genre. Eine Band ist erst einmal einfach eine Band.
- **Genre-Band** (z. B. „Rockband“): Alle Mitglieder mögen das Genre **und** die Besetzung passt. Sound × 1,15.
- **Hybrid** (z. B. „Punk-Rock-Band“): Genre-Band, und für ein zweites Genre gilt dasselbe – alle mögen es **und**
  seine Besetzung passt auch. Sound × 1,25. Name: zweites Genre vor dem Bandgenre. Mehr Genres brauchen meist mehr
  Instrumente, also größere Bands (früher reichte für das zweite Genre, dass alle es mögen).
- **Crossover** (z. B. „Rock-Crossover-Band“): Hybrid, und dasselbe gilt sogar für ein drittes Genre (samt Besetzung). Sound × 1,3,
  Zusammenspiel wie Hybrid (bis 120). Sehr selten (nur rund 3 % der Musiker haben drei Genres) – ein Sammelziel.
- Besetzungs-Bedingungen (gezählt wird das Instrument, das jemand in der Band spielt):

| Genre | braucht |
|---|---|
| Pop | Gesang |
| Rock | Gitarre, Schlagzeug |
| Metal | 2 Gitarren, Schlagzeug |
| Punk | Gitarre, Bass, Schlagzeug |
| Jazz | Bass, Saxophon oder Trompete |
| Elektro | Keyboard oder Turntables |
| Hip-Hop | Gesang, Turntables |
| Folk | Gitarre und Geige oder Flöte |

- Kommen mehrere Genres in Frage, bleibt die Band bei ihrem bisherigen; sonst gilt die Reihenfolge oben.
- Ohne Genre-Einheit (**gemischte Band**) spielt die Musik im Genre, das die meisten Mitglieder mögen – und keiner
  versteht so recht, was der andere macht: Sound × 0,8, Zusammenspiel wächst halb so schnell und höchstens bis 70.
- Zusammenspiel je nach Linie (`COHESION_BY_GENRE`): gemischt höchstens 70 (halb so schnell), Genre-Band 100,
  Hybrid 120. Verliert eine Band ihre Linie, sinkt das Zusammenspiel auf den neuen Höchstwert.
- Bewusst kein eigener Verkaufs- oder Ruhm-Bonus fürs Genre: Der bessere Sound wirkt schon auf Ruhm, Platten und Charts.
- Wechsel kommen in die Chronik; beim Aufnehmen zeigt das Spiel vorher, ob die Band ihr Genre verliert oder gewinnt.
- **Band-Plätze:** bis zu 8 Bands – die ersten beiden sofort, weitere ab 50, 150, 300, 500, 750 und 1.050 Label-Ruhm. Genres dürfen sich wiederholen.
- **Namensänderung:** vor dem ersten Auftritt frei; danach kostet sie 300 ₲ und der Ruhm am alten Namen geht verloren.

### Zusammenspiel und Chemie

- Wächst pro Lied auf der Bühne (1,2 × Chemie) und pro Studio-Lied (2 × Trefferquote × Chemie).
- Chemie 0,5–1,5: Reibung (Diva+Diva, Rampensau+Rampensau, Perfektionist+Chaot, Ehrgeizig+Gelassen) −0,15,
  ein Ruhepol halbiert das; Teamplayer +0,04 pro Paar, Partytier+Partytier +0,06.
- Höchstwert des Zusammenspiels ist immer 100; Chemie bremst nur das Wachstum (× Chemie). Die Chemie-Kachel nennt, wer sich reibt und wer hilft.
- Nach der Freigabe (erster Auftritt) kostet: neues Mitglied −5, Instrumentwechsel −5, Rauswurf −30.
  Wer aufs Instrument von vorher zurückwechselt, ist wieder eingespielt: Der Wechsel-Abzug kommt zurück (`bands.slot_return`).
  Vor dem Wechsel zeigt das Spiel den Sound danach.

### Ruhm

- Begriffe: Ein Musiker hat **Bekanntheit**, eine Band hat **Ruhm**.
- Bandruhm = Ruhm des Bandnamens + Summe der Bekanntheit aller Mitglieder. Von jedem Ruhmgewinn der Band
  bleiben 25 % am Bandnamen hängen, 75 % werden Bekanntheit der Mitglieder. Wer geht, nimmt seine Bekanntheit
  mit; der Name behält seinen Teil. Eine Rückwirkung (Ruhm erhöht zusätzlich Bekanntheit) gibt es nicht.
- Ruhmgewinn eines Auftritts = Grund-Ruhm des Ortes pro Lied × Soundfaktor × Publikum × Zufall (0,9–1,1).
  - Soundfaktor = Sound / 30 (zwischen 0,2 und 3).
  - Publikum = 0,4 + 1,2 × Publikumsleiste/100.
- Einzelanteil = Gewinn / Mitglieder × (0,8 + Boni für Ausstrahlung, Charisma,
  Ungewöhnlich und Instrument-Design). Die Summe der Einzelanteile wird gleichmäßig auf alle verteilt.

### Auftritte

| | Lieder | Geld | Ruhm pro Lied | Besonderheit |
|---|---|---|---|---|
| Straßenmusik | 3 | Notgroschen: 20–45 ₲ in den Hut, nur solange das Label keine EP (390 ₲) bezahlen kann | 0,06 × (1 − Ruhm/150), mindestens 10 %; Erwartung 25 | auch einzeln ohne Band |
| Bandprobe | 2 | – | – | Zusammenspiel × 2,5; Können 0,05 pro Lied für jedes Mitglied (Lernkurve wie unten) – eine Solo-Probe bringt einem Musiker 5- bis 15-mal so viel; beides steht im Spiel an den Knöpfen |
| Open Stage | 2 | – | 0,18 × (1 − Ruhm/120) | 7 % Manager-Karte, sonst 15 % Fan-Karte (+1–2 Ruhm) |
| Gig | 1 bis Maximum | Gage des Ortes pro Lied, vorher bekannt | je nach Ort | erst mit Platte |

Orte nach Bandruhm: Kneipe (ab 0), Club (30), Konzertsaal (150), Halle (600), Arena (2.500).
Gage + 10 % für jedes Mitglied über 2 (`MEMBER_GAGE_BONUS`, 7 Mitglieder + 50 %): Große Bands zahlen so ihre Instrumente und Kurse.
Grund-Ruhm pro Lied: 0,25 / 0,5 / 1 / 2 / 4.
Das Stadion bleibt der Tournee vorbehalten.

- Ein Gig hat mindestens 3 Lieder, höchstens so viele, wie die Band produziert hat, und ohne Manager höchstens 3.
- Frisches Material: Jeder Gig seit der letzten Platte senkt Gage und Ruhm um 8 %, höchstens auf 45 %. Eine neue Platte
  macht die Gigs wieder voll. Eine Platte der Band in den Top 10 bringt + 40 % (Platz 1) bis + 4 % (Platz 10).
  Ein Manager erlaubt 3 + ⌈Stufe × 1,4⌉ Lieder (Stufe 5: 10 Lieder = 4 Minuten).
- Verhandler: +8 % Gage pro Stufe. PR-Profi: +8 % Ruhm pro Stufe. Scout: weniger gewöhnliche Karten in Läden.
  Managt einer mehrere Bands, wird seine Stufe durch die Zahl der Bands geteilt.
- Publikumsleiste: startet bei 0 – das Publikum muss erst überzeugt werden. Im ersten Lied rückt sie pro Abschnitt
  um 40 % Richtung 50 + 0,6 × (Gleichgewicht der Band − 50). Im Browser gleitet sie fließend und wackelt ständig
  ein wenig (rein optisch).
- Jedes Lied hat 4 Abschnitte; in jedem kann jeder Musiker patzen
  (Chance 24 % minus 0,26 % pro Können-Punkt) oder glänzen (steigt mit Können, Spezialtechnik und Bühnentier),
  jeweils ±7–13 Punkte. Dazu pro Abschnitt Grundstimmung, Vergleich Sound zu Erwartung des Ortes und Zufall;
  ab dem zweiten Lied rückt die Leiste jedes Mal 8 % Richtung Mitte. Schwache Bands landen im Schnitt bei etwa 30 %, starke bei etwa 85 %.
- **Fan will einsteigen** (`FanMusician`, `Balance::FAN_MUSICIAN`): Nach einem Gig mit Publikum im Schnitt ab 75 % wartet mit 1,5 %
  ein Fan am Bühnenausgang – höchstens einmal pro Woche und Label (Gigs gibt es viele). Er mag das Genre der Band, spielt ein
  Instrument, das ihr fehlt (passend zum Genre bevorzugt), verträgt sich mit allen und kann höchstens 90 % des Bandschnitts.
  Er kommt kostenlos ins Label; in die Band holt man ihn per Knopf (normale Regeln, Zusammenspiel −5).
  Seltenheit nach Bühne: Kneipe 90/10/0/0 % … Arena 30/40/22/8 % (gewöhnlich/selten/episch/legendär).

### Talent-Scout

- 5 Musiker (70 % episch, 30 % legendär), alle mit Können-Stufe 1. Einer unterschreibt, die anderen verfallen.
  Der erste Musiker eines Labels wird automatisch Favorit.
- Gutscheine: 1 zum Label-Start, 1 für die erste Platte des Labels und 1 für Platz 1 der Album-Charts –
  Letzteres erst, wenn mindestens 3 Labels Platten in den Charts haben (sonst wäre Platz 1 geschenkt).
- Ohne Gutschein: 3.000 ₲, höchstens einmal pro Woche.

### Minispiele

Ein Lied dauert 24 Sekunden: 12 Takte 4/4 (oder 16 Takte 3/4) bei 120 BPM – alle Genres im selben Tempo und derselben Tonart, damit sich alle Spuren mischen lassen. Bewertet wird in Perfekt / Gut / Daneben; Trefferquote = (Perfekt + 0,6 × Gut) / Anzahl.

**Etüden (Solo-Probe, alle Instrumente):** Statt der Aufnahme spielt man eine Übung, die das Spiel aus typischen
Genre-Bausteinen erzeugt (`Styles`: Akkordfolgen, Grooves, Bass-, Begleit- und Melodie-Rhythmen je Genre; `Etude`).
Eigenes Tempo, Taktart (Folk 3/4) und Tonart, Swing bei Jazz und Hip-Hop, Synthesizer-Begleitung (Schlagzeug und Bass ohne die eigene Rolle, Akkorde immer; Bass eine Oktave höher, damit Tablet-Lautsprecher ihn spielen).
Muster tragen Wichtigkeiten (9 = Gerüst … 1 = Verzierung, zusätzlich durch die Taktposition gedeckelt). Das Können legt
die Notendichte fest (`Balance::ETUDE_RATE`, Noten pro Sekunde je Rolle von Können 0 bis 100, dazwischen nach Können^1,5);
genommen werden die wichtigsten Noten. So ist jede Etüde bei gleichem Können gleich schwer, egal welches Muster, Tempo oder
Genre gezogen wurde (vorher schwankte die Dichte bis zum Vierfachen). Gehaltene Töne klingen auch so lange. Alles Zufällige hängt an einer Nummer: dieselbe Etüde wird mit mehr Können
voller, bleibt aber dieselbe (Grundlage fürs spätere Notizbuch und Songwriting). Probe: `probe-etuede.html`.
Gesang und Geige spielen die Etüde im Tonhöhen-Spiel (Steuerung unverändert), Rap im Hip-Hop im Rhythmus-Spiel. Vor großen
Sprüngen (ab 3 Stufen) endet der Ton früher (0,07 s pro Stufe), damit Zeit zum Hinübergleiten bleibt – in Etüden und in
den Gesangsspuren der Aufnahmen (`Music::melodyChart`, für Studio-Aufnahmen).

- **Rhythmus** (Solo-Probe für alle außer Gesang und Geige): vier Spuren, Tasten D F J K oder Tippen.
  Die Noten kommen aus der Aufnahme (noten.json) der eigenen Spur: Schlagzeug nur Bassdrum (links) und Snare (rechts),
  die Hi-Hat bleibt im Klang; andere Instrumente Spur nach Tonhöhe (tief links), Akkorde als zwei Noten
  zugleich (tiefster und höchster Ton), Töne ab 1,5 Schlägen werden gehalten (bis kurz vor dem nächsten Anschlag). Haltenote: ab 85 % gehalten
  zählt die Wertung des Anschlags, ab 40 % „Gut“, sonst „Daneben“.
  Die Schwierigkeit steckt in der Spur der Stufe. Bei Treffern hört man die eigene Spur, bei Fehlern wird sie leise.
  Man übt allein: Dazu tickt nur ein Metronom. Ohne Aufnahme: Zufallsnoten, Dichte 0,25–0,55 je nach Können. Zeitfenster Perfekt 60–75 ms, Gut 135–185 ms, Absolutes Gehör +20 ms.
- **Tonhöhe** (Solo-Probe für Gesang und Geige): Gedrückt halten lässt den Ton steigen, Loslassen sinken.
  Melodie aus der Aufnahme, Tonleiter ab ihrem tiefsten Ton. Gesteuert wird mit Halten, nicht mit der Stimme.
  Die echte Spur ist zu hören, solange man den Ton trifft; daneben wird sie leise und man hört den eigenen, falschen Ton.
  Dazu tickt ein Metronom.
  Wie schnell der Ton steigt, richtet sich nach den Sprüngen der Melodie (0,5–1 Tonleiter pro Sekunde).
  Jeder Ton der Melodie zählt als Perfekt ab 70 % der Zeit im richtigen Bereich, als Gut ab 40 %.
  Der Bereich ist ±7–13 % der Tonleiter, mit Absolutem Gehör +3 %.
- **Mischpult** (Studio): Die Band spielt, die Pegel gleiten ständig – ein Zufallspfad, der zur Mitte zurückstrebt.
  Neuer Zielpunkt alle 0,7–1,4 Sekunden, Schritt 0,05–0,18; je schwächer der Musiker, desto öfter und weiter.
  Bis zu 4 Musiker haben eigene Spuren; größere Bands werden zu Gruppen (Rhythmus, Harmonie, Melodie) mit
  dem durchschnittlichen Können zusammengefasst. Jede Spur hat 6 Abschnitte à 4 Sekunden (2 Takte), bewertet wie oben.
  Der grüne Bereich ist ±11–19 %, je nach Sound der Band.
  Ein Regler folgt der Bewegung des Fingers, nicht seiner Position: Festhalten allein hält die Spur nicht im Bereich.
- **Master-Mix** (letzte Aufnahme einer Platte): Die ganze Band läuft durch einen Klangregler mit Bässen (250 Hz),
  Mitten (1 kHz) und Höhen (4 kHz). Jeder Regler hat eine richtige Stellung (15–85 %); Abweichung klingt mit 24 dB
  pro voller Reglerlänge daneben. Eine Lampe pro Regler: grün im Bereich (±11–19 % wie am Mischpult), gelb bis zum
  2,2-fachen, sonst rot – ohne Richtung. 4 Abschnitte à 6 Sekunden; nach der Hälfte verschiebt sich jede Stellung
  um 15–35 %. Bewertet pro Regler und Abschnitt wie am Mischpult (12 Wertungen).
- **Tageskurse:** Jeden Tag ein Instrument und eine Kursart, für alle Spieler gleich (Instrument reihum, nie zweimal
  hintereinander; mit Gastdozent). Kursart zufällig: Kurs (50 %, 10 Min., +3 Können), Workshop (35 %, 30 Min., +5),
  Meisterklasse (15 %, 60 Min., +8). Angeboten in drei Schwierigkeiten – Anfänger, Fortgeschrittene, Profis (Stufe 0,
  1, 2+) –, jeder Musiker nur in seiner. Gleicher Zuwachs für alle, ohne Lernkurve und ohne Stufen-Bremse; Preis =
  Grundpreis (80 / 150 / 300 ₲) × 1 / 3 / 8 je Schwierigkeit. Jeder Musiker einmal pro Tag, auch ohne Band; läuft
  offline; Zurückholen gibt das Geld zurück. Ein Musiker hat etwa jeden zehnten Tag sein Instrument: im Schnitt +4,5
  Können, eine Stufe allein durch Kurse in rund zwei Monaten.
- Allein üben lassen (statt Minispiel): 10 Minuten, läuft auch offline, bringt ¼ einer perfekten Solo-Probe. Wer selbst
  spielt, bekommt je nach Treffern bis zum Vierfachen. Beim Platte-Aufnehmen gibt es diese Wahl nicht.
- Solo-Probe: Können + 0,8 × Trefferquote^1,5 × Talent × Lernkurve; auf Stufe 0 (unter 35) × 3 –
  eine perfekte Probe bringt dann +1,7 (bei Können 15) bis +0,95 (bei 34). Von 15 auf 35 sind es bei 90 % Treffern
  rund 19 Proben (~13 Minuten), danach normales Tempo (Stufen-Bremse).
- Lernkurve (alle Können-Gewinne): (1 − Können/110)^2,5. Je besser jemand ist, desto langsamer geht es.

### Tempo

Ziel: Ein Anfänger (Können 15) ist schnell fortgeschritten – mit gezieltem Üben (Solo-Probe × 3 auf Stufe 0) in rund 13 Minuten. Darüber hinaus geht es mit den
heutigen Mitteln kaum weiter: Ab Stufe 1 wirken alle Können- und Bekanntheitsgewinne nur noch zu 25 %, ab Stufe 2
nur noch zu 3 %. Profi bzw. Szenegröße dauert so rund 40 Stunden Spielzeit, Virtuose bzw. Star weit über 1.000 –
praktisch unerreichbar, bis Tourneen und Manager dazukommen.
**Tageskurse** öffnen diesen Weg als erste: ohne Bremse, aber selten und teuer (eine Stufe allein durch Kurse in rund zwei Monaten).
**Große Bands** sind ein bewusster Weg nach oben: mehr Sound (+5 % je Mitglied über 2) und mehr Gage (+10 % je Mitglied über 2) – sie kosten mehr Zeit und Geld, dafür kommen sie schneller zu größeren Bühnen.
**Fans** großer Bühnen sind ein weiterer, kleiner Weg: In Halle und Arena kommen auch epische und legendäre Musiker, so gut wie fast die Band.
**Für neue Inhalte:** Tourneen, Manager und Co. sollen genau diese Stufen erreichbar machen (eigene, ungebremste
Gewinne oder eine gelockerte Bremse) – nicht einfach dem heutigen Tempo unterliegen. Lokal bekannt ist man nach einigen Stunden Auftritten.
Nur die Gewinne sind klein; wie oft man etwas hintereinander spielt, ist nicht begrenzt.

### Platte

- 3 (EP) bis 12 Lieder (LP), Studiozeit 150 ₲ + 80 ₲ pro Lied. So viele Minispiele (Aufnahmen) wie Lieder, in Etappen spielbar.
  Angezeigt als „Aufnahme y/x“ – eine Aufnahme ist nicht ein bestimmtes Lied.
- Reihenfolge wird beim Start festgelegt (`Studio::plan`). Im Topf: pro Instrument der Band einmal **Einspielen**
  (das Spiel der Solo-Probe für einen Musiker mit diesem Instrument) und **Mischpult**-Runden mit 2–3 zufälligen
  Mitgliedern (eine bei 2 Musikern, sonst mindestens 2, etwa halb so viele wie Instrumente). Gezogen wird gemischt;
  ist der Topf leer, wird er neu gefüllt. Nie zweimal dasselbe direkt hintereinander. Die vorletzte Aufnahme ist immer der
  **Master-Mix**, die letzte der **Cover-Automat** (bei 3 Liedern also: eine Aufnahme, Master-Mix, Cover).
- Jede Aufnahme: Können 0,12 × Trefferquote für alle Mitglieder (Lernkurve), Zusammenspiel – egal, welches Minispiel.
- **Cover-Automat** (`Cover`, `Balance::COVER`): die letzte Aufnahme; danach ist die Platte fertig. Er bringt kein Können und
  kein Zusammenspiel, die Qualität der Platte kommt nur aus den Musik-Aufnahmen.
  Vier Bänder (Motiv, Farben, Stil, Anordnung) öffnen sich nacheinander, nur eins läuft (6 / 7,5 / 9 / 10,5 Felder pro Sekunde).
  Tippen hält an – das Feld im Rahmen gilt, kein Nachrutschen. Je Band 14 Felder: 8 gewöhnlich, 3 grün, 2 blau, 1 gold
  (0/1/2/4 Punkte). Güte = Punkte / 16; sie bringt beim Erstverkauf bis +10 % Geld, Chartpunkte bleiben bei Musik und Sound.
  Lauter gewöhnliche Felder geben ein blasses, verwaschenes Cover, Gold eine Folie. Jedes angehaltene Band speichert der Server
  sofort – Neuladen bringt keinen zweiten Versuch. Das Bild entsteht im Browser aus den gespeicherten Zutaten (`cover.js`).
- Fertig: Erstverkauf = Lieder × 60 ₲ × (0,3 + Qualität²) × LP-Bonus × Perfekt-Bonus × Verkäufe,
  Ruhm = Lieder × 0,6 × Soundfaktor × (0,3 + Qualität^1,5) × LP-Bonus × Perfekt-Bonus.
  - LP-Bonus: +4 % pro Lied über 3. Perfekt (kein einziger Fehler): × 1,5. Verkäufe: 1 + Bandruhm/400, höchstens × 5.
- **Album-Zyklus** (`Balance::RECORD_CYCLE`, `Studio::cycle`): Vor der nächsten Platte spielt die Band die letzte
  **3-mal komplett live** (3 × Lieder der Platte, gezählt werden Lieder auf Gigs) und macht **3 Band-Promos** (je 10 Minuten,
  laufen auch offline; abgebrochene zählen nicht). Die Promos kosten jede Platte gleich viel Zeit, damit kurze EPs nicht viel
  schneller sind. Die Studio-Kachel zeigt beides als Checkliste, Gig- und Promo-Kachel den Stand.
  (Früher: 5 Gigs; davor 2 Lieder live pro Plattenlied – kurze EPs fluteten die Charts.)

### Album-Charts

- Chartpunkte = 100 × Qualität² × Soundfaktor × Format × Perfekt (× 1,3) – nur die Platte selbst, **ohne Bandruhm**.
  (Früher × bis zu 3 durch Bandruhm: Ruhm brachte Plätze, Plätze brachten Ruhm – wer oben war, blieb oben.)
  Format: EP × 0,6, LP (ab 7 Liedern) × 1 + 4 % pro Lied über 7.
- Die Charts rechnen in Stunden. Punkte jetzt = Chartpunkte × Verlauf + Schub. Verlauf: Einstieg mit den vollen Punkten
  (so viele, wie beim Fertigstellen angezeigt), dann verliert jede Platte jede Stunde 3 % (`CHART_DECAY`):
  nach einem Tag 48 %, nach zwei 23 %, nach drei 11 %. Für jede Platte gleich; von selbst steigt keine, nur durch Schub.
  (Früher: Einstieg mit 65 %, Höhepunkt nach 8 Stunden, dann fallend – wirkte wie verlorene Punkte.)
  Eine starke Platte kann gegen schwache Konkurrenz direkt auf Platz 1 einsteigen.
  Weil jede Platte ihren eigenen Verlauf hat, überholen sich Platten; es gibt Bewegung.
- Schub für die neueste Platte der Band (immer, auch wenn sie gerade nicht chartet), als Aufschlag auf ihre Punkte:
  Gig +2,5 % pro gespieltes Lied (egal wo, egal wie gut), Band-Promo +10 %, Promo-Mission eines Mitglieds +4 %.
  Höchstens +60 %, halbiert sich alle 3 Stunden. Prozent statt fester Punkte: Wer gleich viel spielt, bekommt denselben
  Aufschlag – das Verhältnis zweier Platten bleibt, der Schub belohnt nur Fleiß. Die Schere schließt die Zeit (3 % pro Stunde).
  (Früher hing der Gig-Schub an der Größe des Ortes – große Bands zogen davon.)
- Unter 1 Punkt verkauft sich eine Platte nicht mehr, nach 7 Tagen zählt sie nicht mehr. Die Charts zeigen die Top 10 groß und darunter kompakt bis Platz 100 (`CHART_LIST`); jede Platte zählt, ohne Deckel pro Band oder Label.
  Für den Platz selbst gibt es nur den Scout-Gutschein für Platz 1 und Nachrichten. Eine Band spielt und bewirbt immer ihre **neueste** Platte
  (`Charts::promoted`), ältere fallen so von selbst; sie wieder zu spielen kommt später mit dem Remaster.
- Die Plätze werden alle 3 Stunden neu vergeben (Chartwoche) – sofort, wenn eine Platte erscheint oder Schub bekommt.
  Dazwischen bleiben sie stehen. „Vorher“ ist der Platz zu Beginn der Chartwoche; dazu Trend (▲ ▼ – NEU),
  Stunden in den Charts und bester Platz. Nachrichten: Einstieg in die Top 10, Platz 1 (mit Feier), aus den Top 10 gefallen.
- Tantiemen laufen ständig (sekundengenau abgerechnet, Bruchteile werden aufgehoben): pro Punkt und Stunde 0,6 ₲ und
  0,0125 Ruhm – nur nach Punkten, ohne Platz-Bonus (die Punkte sind schon die Platzierung; früher × bis zu 2 für
  Platz 1, das war doppelt gezählt). Ruhm an die aktuellen Mitglieder.
  Eine Platte mit 60 Punkten bringt über ihre Chartzeit etwa 1.200 ₲.
  Das Geld zählt im Browser sichtbar mit; im Kontoauszug eine Zeile pro Platte und Tag.
- Platten ohne Chartplatz verkaufen sich in ihrer ersten Woche trotzdem: Tantiemen nach Punkten wie alle.
- Katalog: Jede Platte verkauft sich für immer ein wenig – 0,01 ₲ pro Stunde und Chartpunkt (Startwert), zusätzlich
  zu den Charts. 60 Punkte ≈ 14 ₲ am Tag. Klein gegen Gigs, aber es sammelt sich mit vielen Platten; das 5-Gig-Limit
  zwischen zwei Platten verhindert, dass man das mit EP-Fluten ausnutzt.

### Promo

- Promo-Mission (ein Musiker): 10 Minuten, läuft offline. Bekanntheit + 0,12–0,3 × Ausstrahlung-Faktor / (1 + Bekanntheit/40).
- Band-Promo: 10 Minuten, jedes Mitglied bringt 0,1–0,25 nach derselben Formel; verteilt wie Auftrittsruhm.
- Beide lassen sich jederzeit abbrechen („Zurückholen“): sofort wieder frei, keine Bekanntheit, kein Chart-Schub.

### Jam-Café

Nur für Musiker ohne Band, 2 Lieder, beliebig oft – bis jemand eine Band gründet: Gastgeber und neuer Musiker sind dann
markiert (`musicians.jam_at`) und können nie wieder ins Jam-Café. Sonst könnte man gründen, auflösen, wieder jammen und
so gratis Musiker sammeln. Löst man eine Band auf, die nur aus solchen Musikern besteht, warnt das Spiel. Im Publikum sitzen Musiker: Erwartung 35 (Straßenmusik 25,
Open Stage 12). Danach stellen sich 3 Musiker vor, die zum Gastgeber passen. Wie gut sie sind, hängt von der
Session ab (Publikumsleiste 0 → 100 %): Seltenheit von 85/14/1 % auf 35/45/20 % (gewöhnlich/selten/episch),
Mindest-Können von 5 auf 26.
- jeder spielt ein anderes Instrument, das der Gastgeber nicht spielt, und teilt ein Genre mit ihm,
- kein Charakterzug reibt sich mit dem Gastgeber; ist er Partytier, ist der neue es oft auch,
- alle haben Können-Stufe 0 (höhere Stufen gibt es nur über den Talent-Scout).

Einer wird ausgewählt und gründet mit dem Gastgeber eine Band in einem gemeinsamen Genre. Damit ist das
Jam-Café für den Gastgeber vorbei. Lehnt man alle ab, verfällt die Auswahl und man kann wieder jammen. Ins Jam-Café kann man nur, wenn ein Band-Platz frei ist.

### Wechselbörse

- Ein Musiker ohne Band kommt für 2 Tage an die Börse. Solange ist er gesperrt (keine Band, keine Aktivitäten),
  seine Instrumente bleiben beim Label.
- Mindestpreis = Marktwert = 170 + 0,8 × Können² + 5 × Bekanntheit + 300 × (Können-Stufe + Bekanntheits-Stufe)
  + 100 × Fähigkeiten, auf 10 gerundet. Beispiele: Anfänger (Können 15) etwa 300 ₲, Scout-Musiker (Können 35–45) etwa 1.600–2.300 ₲.
- Gebote sind verbindlich: Das Geld wird beim Bieten zurückgelegt, wer überboten wird, bekommt es sofort zurück.
  Überbieten um mindestens 5 % (mindestens 10 ₲). Wer vorn liegt und erhöht, legt nur die Differenz dazu.
- Ein Gebot in den letzten 10 Minuten verlängert die Frist auf 10 Minuten.
- Am Ende geht der Musiker an das höchste Gebot. Der Verkäufer kann vorher zuschlagen; zurückziehen kann er nur,
  solange niemand geboten hat. Ohne Gebot bleibt der Musiker beim Label.

### Instrumente

- Jedes Exemplar: Klang, Spielbarkeit und Design je Modellwert ± bis zu 12.
- Ohne eigenes Instrument zählt ein Musiker im Sound nur halb (× 0,5).
- Eigenschaften: epische Instrumente haben eine, legendäre zwei. Bühnenmagnet: Anteil am Auftrittsruhm +0,15.
  Lehrmeister: Können-Gewinn × 1,3. Studioklang: Chartpunkte einer Platte +6 % pro Mitglied mit so einem Instrument.
- Aussehen nach Design: ab 30 eigene Farbe, ab 60 Sonderlackierung, ab 85 Gold-Edition mit Glitzern.
- Verkauf an den Second-Hand-Laden: 25 / 80 / 260 / 750 ₲ je Seltenheit, +25 % pro Eigenschaft.
- Die Instrumentenarten kommen so oft wie die Musiker, die sie spielen (gleiche Gewichte).

### Läden

Man bezahlt, sieht mehrere verdeckte Karten, deckt sie auf und nimmt **eine** mit. Solange eine Auswahl offen ist,
gibt es keinen neuen Laden. Chancen: gewöhnlich / selten / episch / legendär.

| Laden | Preis | Karten | Was | Chancen |
|---|---|---|---|---|
| Trödelmarkt | 120 ₲ | 4 | 70 % Instrumente, 24 % Musiker, 6 % Manager | 86 / 11 / 2,5 / 0,5 |
| Schwarzes Brett | 200 ₲ | 4 | Musiker | 68 / 26 / 5 / 1 |
| Second-Hand-Laden | 350 ₲ | 4 | Instrumente | 50 / 38 / 10 / 2 |
| Music Store | 800 ₲ | 4 | Instrumente, manchmal mit Tipp | 10 / 55 / 30 / 5 |
| Backstage-Pass | 1.500 ₲ | 4 | 2 Musiker, 1 Manager, 1 Instrument | 0 / 55 / 35 / 10 |
| Meisterwerkstatt | 2.400 ₲ | 3 | Instrument nach Wahl | 0 / 0 / 75 / 25 |

Instrumente sind zufällig. Im Music Store hat der Verkäufer mit 25 % Chance einen Tipp: Eine der Karten ist ein Instrument für einen Musiker, der noch keins hat (Bandmitglieder zuerst), und ist so markiert.

## Musik

- Aufnahmen aus Logic Pro: je Genre und Stufe ein Ordner `public/assets/music/<genre>-<stufe>/` mit einer MP3 pro Spur
  (z. B. `gitarre-elektrisch.mp3`, `gesang.mp3`) und `noten.json` (Noten aus der MIDI-Datei).
  Alle Spuren: 120 BPM, 12 Takte = 24 Sekunden, gleiche Tonart.
- Einlesen lokal mit `php tools/musik-import.php pop-0 datei.mid spur.wav …` (braucht ffmpeg). Lautheit je Rolle:
  Gesang −17 LUFS, Bass und Schlagzeug −19, Keyboard und Turntables −21, Bläser/Geige/Flöte −22, Gitarre −23.
- Jeder Musiker spielt die Spur seines Instruments, seiner Stufe und eines Genres: das Genre der Band, sonst eines
  seiner Lieblingsgenres. Akustisch oder elektrisch richtet sich nach seinem Instrument.
- Gesang gibt es mit Frauen- und Männerstimme (`gesang-weiblich`, `gesang-maennlich`): Musikerinnen singen mit der
  Frauenstimme, Musiker mit der Männerstimme, diverse je nach Karte. Beide teilen sich die Noten der MIDI-Spur „gesang“.
  Die MIDI-Spur kann aus echtem Gesang kommen (Logic: Flex Pitch → MIDI-Spur); sehr kurze Töne (unter 0,15 s) zählen
  nicht, ineinander gebundene Töne enden, wo der nächste beginnt.
- Rap: In Hip-Hop wird gerappt (`Catalog::RAP_GENRES`). Gesang übt dort im Rhythmus-Spiel – man tippt die Silben
  im Takt (Noten aus derselben MIDI-Spur wie Pop). Vorhanden: Pop 0 komplett, Rock 0 (Schlagzeug, Bass, Keyboard),
  Hip-Hop 0 (Schlagzeug, Bass, Gesang), Elektro 0 (Schlagzeug, E-Piano, Turntables, Gesang; Gesang und E-Piano mit den Noten von Pop,
  Männerstimme aus Pop).
- Spur ohne MIDI (Elektro-Turntables): Die Noten fürs Rhythmus-Spiel kommen aus der Aufnahme selbst – Anschläge erkannt
  (Spektral-Fluss), aufs Achtel gerundet, die Spur im Spiel nach Klangfarbe (hell/dunkel) verteilt.
  Stufe 0 bekommt nur Einzeltöne (Doppeltöne im Abstand bis ½ Schlag zusammengefasst), Stufe 1 (`elektro-1`) die schwerere Fassung.
- Tonhöhen-Spiel (Gesang, Geige): Nach einem Sprung zählt der Anfang des neuen Tons erst, wenn man ihn bei normalem Tempo
  erreichen kann (Weg ÷ Tempo minus Pause, plus 0,12 s, höchstens der halbe Ton) – weite Sprünge sind so schaffbar.
- Fehlt eine Spur, nimmt das Spiel die nächste Stufe, dann die andere Variante, dann Pop. Gibt es gar keine, bleibt der Synthesizer.
- Elektrisch oder akustisch (`Catalog::soundsElectric`): Manche Genres verlangen es (Metal: Gitarre und Bass, Elektro: Keyboard,
  Schlagzeug und Bass immer elektrisch). Sonst zählt das eigene Instrument; ohne eigenes Instrument die Gewohnheit des Genres
  (Jazz und Folk akustisch, die übrigen elektrisch).
- Zu hören am Mischpult (jeder Regler steuert seine Spuren), bei der Solo-Probe (nur die eigene Spur) und in der Live-Ansicht von Auftritten.
- Spielen zwei Mitglieder dasselbe Instrument (z. B. zwei Gitarren im Metal), nimmt der zweite die Aufnahme eines anderen
  Genres (erst Pop, dann Rock, dann die übrigen) in der Variante, die eigentlich klingen würde (Metal: Pop-E-Gitarre).
  Übersprungen werden Aufnahmen mit (fast) denselben Noten (`Music::parts`: Rhythmus ≥ 90 % und Töne ≥ 50 % gleich,
  z. B. Metal- und Rock-Gitarre) – man hört nie zweimal denselben Part, solange es einen anderen gibt. Gesang zählt je Stimme.

### Platten und Gigs im Gleichgewicht

Rechnung pro Stunde Spielzeit (Gig = 3 Lieder ≈ 1,5 Minuten, Plattenlied ≈ 1 Minute, Qualität 75 %, EP alle 4 Gigs):

| Band | nur Gigs | Platten + Gigs (sofort + Tantiemen) |
|---|---|---|
| Anfänger (Sound 12) | ~1.500 ₲ | ~850 + ~2.100 ₲ |
| Mittel (Sound 30, Ruhm 100) | ~3.600 ₲ | ~4.400 + ~6.200 ₲ |
| Gut (Sound 55, Ruhm 600) | ~19.500 ₲ | ~31.500 + ~20.800 ₲ |

Nur Gigs lohnt sich immer weniger (das Publikum kennt die Lieder), nur Platten geht nicht (Live-Pflicht vor der nächsten).
Platten bringen über die Charts mehr, Gigs schieben die Platte an.

### Wer wirkt auf wen (ohne Kreisläufe)

Grundsatz: Ruhm darf nie den eigenen Ruhm-Zuwachs über den Chartplatz verstärken. Sonst schaukelt sich Platz 1 auf.

- **Chartplatz** ← Chartpunkte (Qualität, Sound, Länge, perfekt) × Verlauf (−3 %/Std.) × Schub (Gigs, Promo).
  Ruhm wirkt hier nicht.
- **Chartplatz →** nur der Scout-Gutschein für Platz 1 und Nachrichten. Geld und Ruhm kommen aus den Punkten, nicht aus dem Platz.
- **Ruhm** ← Gigs (je nach Ort), Platten, Charts (nach Punkten), Promo, Straßenmusik/Open Stage (mit Ruhm abnehmend).
- **Ruhm →** Auftrittsort (die Leiter nach oben: mehr Gage und Ruhm pro Lied), Erstverkauf einer Platte (Geld),
  Bandplätze (Label-Ruhm), Marktwert. Der Ort ist der einzige Ruhm-zu-Ruhm-Weg – gewollt, mit festen Stufen.
- **Geld →** Instrumente, Kurse → Können und Sound → bessere Platten. Ein langsamer, gewollter Weg.
- **Bandgröße →** Sound (+5 % je Mitglied über 2) und Gage (+10 %). Die Bekanntheit eines Gigs wird geteilt:
  In großen Bands wird der Einzelne langsamer bekannt, die Band als Ganzes nicht.

### Nachrichten

Backstage-News: Wer mindestens 15 Minuten nicht im Spiel war, bekommt beim nächsten Aufruf die Neuigkeiten aus der
Chronik: Charts, Wechselbörse, Scout-Gutscheine, Aufstiege.

## Vorläufige Entscheidungen zu offenen Fragen

Diese Punkte sind im Konzept offen. Für den Kern habe ich sie so festgelegt; alles lässt sich ändern.

- **Instrumente:** Keyboard und Synthesizer sind ein Instrument (Keyboard). Gitarren, Schlagzeuge und Keyboards gibt es akustisch und elektrisch; das merkt sich nur das Spiel, um später die passende Spur zu spielen.
- **Besetzung:** Jedes Mitglied spielt in der Band genau ein Instrument. Ein Sänger mit Gitarre zählt
  als Gitarrist, wenn er in der Band Gitarre spielt. Höchstens 7 Mitglieder (damit jede Genre-Kombination möglich ist; Metal + Jazz + Hip-Hop braucht 7).
- **Straßenmusik:** etwas Ruhm; Geld nur als Notgroschen (Hut, 20–45 ₲ je nach Auftritt), solange das Geld nicht für die
  kleinste Platte reicht – so kommt niemand in eine Sackgasse (ca. 10 Auftritte bis zur EP). Läden, Scout, Börse und
  Umbenennen warnen, bevor das Geld unter die kleinste Platte fällt, solange noch keine Band Gigs spielen kann.
- **Open Stage:** lohnt sich ab 120 Ruhm nicht mehr.
- **Platte:** Die letzte Platte erst live spielen (2 Lieder pro Plattenlied), dann die nächste. Ja, der Sound bestimmt, wie fehlerverzeihend das Minispiel ist.
- **Charts:** statt einer Liste pro Genre gibt es gemeinsame Album-Charts; die Label-Gesamtliste zählt den Ruhm aller eigenen Bands.
- **Ruhm verfällt nicht** (vorerst).
- **Präsenz** ist vorerst raus, um es einfach zu halten (der Wert steht noch in der Datenbank, zählt aber nicht).
- **Ob man bei Gigs mitspielt:** vorerst nicht, man schaut im Live-Ticker zu.

## Bekannte Grenzen

- Das Ergebnis des Minispiels kommt aus dem Browser. Der Server prüft nur, ob es plausibel ist
  (Notenzahl, Mindestdauer). Für ein Hobbyspiel unter Bekannten reicht das; gegen gezieltes Schummeln nicht.
- Bisher gibt es nur Pop Stufe 0 als Aufnahme; alle anderen Genres und Stufen spielen sie mit.
- Bluetooth-Kopfhörer: Verzögerung pro Gerät einstellbar (im Minispiel „Ton kommt zu spät?“): Man tippt 12 Klicks
  im Sekundentakt mit, die mittlere Verspätung (ohne die ersten 3) wird gespeichert. Rhythmus- und Tonhöhen-Spiel
  verschieben Bild und Wertung darum. Ohne Einstellung gilt, was der Browser als Ausgabeverzögerung meldet.
- Noch keine Bremse gegen Passwort-Raten beim Login.
