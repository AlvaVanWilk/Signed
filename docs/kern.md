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
| Bands | Bis zu 3 Bands (freigeschaltet über Label-Ruhm), Genre ergibt sich aus der Besetzung, Sound, Zusammenspiel, Chemie, Ruhm |
| Aktivitäten | Band: Bandprobe, Band-Promo, Straßenmusik, Open Stage, Gig (erst mit Platte), Platte (Mischpult pro Lied). Einzeln: Solo-Probe (Rhythmus oder Tonhöhe), Promo-Mission. Ohne Band: Jam-Café (Mitmusiker für die erste Band), Straßenmusik |
| Live | Live-Ticker mit Publikumsleiste für alle Auftritte |
| Charts | Gemeinsame Album-Charts aller Spieler mit täglichen Tantiemen, dazu eine Label-Gesamtliste |
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
    Können pro Instrument als 10 Kästchen (je 10 Punkte bis 100) und Bekanntheit als 10 Sternchen (jede
    Bekanntheits-Stufe füllt ein Drittel). Lieblingsgenres als farbige Abzeichen oben rechts; der Bildhintergrund
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
Instrument = Klang/100 × 0,5 × min(1, Können / (100 − Spielbarkeit))
Sound      = (0,75 × Durchschnitt + 0,25 × schwächster Beitrag) × Genre-Bonus (1 / 1,15 / 1,25)
             × (1 + 0,05 × weitere Instrumentenarten, höchstens +20 %)
             × (0,7 + 0,3 × Zusammenspiel/100)
```

Ein schwacher Musiker zieht den Sound runter, und vier Musiker sind höchstens 20 % stärker als zwei.

### Genre einer Band

- Beim Gründen wählt man kein Genre. Eine Band ist erst einmal einfach eine Band.
- **Genre-Band** (z. B. „Rockband“): Alle Mitglieder mögen das Genre **und** die Besetzung passt. Sound × 1,15.
- **Hybrid** (z. B. „Punk-Rock-Band“): Genre-Band, und alle mögen zusätzlich ein zweites Genre (ohne weitere
  Besetzungs-Bedingung). Sound × 1,25. Name: zweites Genre vor dem Bandgenre.
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
| Folk | Geige, Flöte oder Gitarre |

- Kommen mehrere Genres in Frage, bleibt die Band bei ihrem bisherigen; sonst gilt die Reihenfolge oben.
- Ohne Genre-Einheit spielt die Musik im Genre, das die meisten Mitglieder mögen (ohne Bonus).
- Wechsel kommen in die Chronik; beim Aufnehmen zeigt das Spiel vorher, ob die Band ihr Genre verliert oder gewinnt.
- **Band-Plätze:** die erste Band sofort, die zweite ab 150, die dritte ab 800 Label-Ruhm. Genres dürfen sich wiederholen.

### Zusammenspiel und Chemie

- Wächst pro Lied auf der Bühne (1,2 × Chemie) und pro Studio-Lied (2 × Trefferquote × Chemie).
- Chemie 0,5–1,5: Reibung (Diva+Diva, Rampensau+Rampensau, Perfektionist+Chaot, Ehrgeizig+Gelassen) −0,15,
  ein Ruhepol halbiert das; Teamplayer +0,04 pro Paar, Partytier+Partytier +0,06.
- Höchstwert des Zusammenspiels = 70 + (Chemie − 1) × 60, zwischen 40 und 100.
- Nach der Freigabe (erster Auftritt) kostet: neues Mitglied −5, Instrumentwechsel −5, Rauswurf −30.

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
| Straßenmusik | 3 | – | 0,3 × (1 − Ruhm/150), mindestens 10 %; Erwartung 25 | auch einzeln ohne Band |
| Bandprobe | 2 | – | – | Zusammenspiel × 2,5, etwas Können für alle |
| Open Stage | 2 | – | 1,4 × (1 − Ruhm/120) | 7 % Manager-Karte, sonst 15 % Fan-Karte (+3–10 Ruhm) |
| Gig | 1 bis Maximum | Gage des Ortes pro Lied, vorher bekannt | je nach Ort | erst mit Platte |

Orte nach Bandruhm: Kneipe (ab 0), Club (30), Konzertsaal (150), Halle (600), Arena (2.500).
Das Stadion bleibt der Tournee vorbehalten.

- Ein Gig hat mindestens 3 Lieder, höchstens so viele, wie die Band produziert hat, und ohne Manager höchstens 3.
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

### Talent-Scout

- 5 Musiker (70 % episch, 30 % legendär), alle mit Können-Stufe 1. Einer unterschreibt, die anderen verfallen.
  Der erste Musiker eines Labels wird automatisch Favorit.
- Gutscheine: 1 zum Label-Start, 1 für die erste Platte des Labels und 1 für Platz 1 der Album-Charts –
  Letzteres erst, wenn mindestens 3 Labels Platten in den Charts haben (sonst wäre Platz 1 geschenkt).
- Ohne Gutschein: 3.000 ₲, höchstens einmal pro Woche.

### Minispiele

Ein Lied dauert 24 Sekunden: 12 Takte 4/4 (oder 16 Takte 3/4) bei 120 BPM – alle Genres im selben Tempo und derselben Tonart, damit sich alle Spuren mischen lassen. Bewertet wird in Perfekt / Gut / Daneben; Trefferquote = (Perfekt + 0,6 × Gut) / Anzahl.

- **Rhythmus** (Solo-Probe für alle außer Gesang und Geige): vier Spuren, Tasten D F J K oder Tippen.
  Dichte 0,25–0,55 je nach Können. Zeitfenster Perfekt 60–75 ms, Gut 135–185 ms, Absolutes Gehör +20 ms.
- **Tonhöhe** (Solo-Probe für Gesang und Geige): Gedrückt halten lässt den Ton steigen, Loslassen sinken.
  Jeder Ton der Melodie zählt als Perfekt ab 70 % der Zeit im richtigen Bereich, als Gut ab 40 %.
  Der Bereich ist ±7–13 % der Tonleiter, mit Absolutem Gehör +3 %.
- **Mischpult** (Studio): Die Band spielt, die Pegel gleiten ständig – ein Zufallspfad, der zur Mitte zurückstrebt.
  Neuer Zielpunkt alle 0,7–1,4 Sekunden, Schritt 0,05–0,18; je schwächer der Musiker, desto öfter und weiter.
  Bis zu 4 Musiker haben eigene Spuren; größere Bands werden zu Gruppen (Rhythmus, Harmonie, Melodie) mit
  dem durchschnittlichen Können zusammengefasst. Jede Spur hat 6 Abschnitte à 4 Sekunden (2 Takte), bewertet wie oben.
  Der grüne Bereich ist ±11–19 %, je nach Sound der Band.
- Solo-Probe: Können + 4 × Trefferquote^1,5 × Talent × max(0,08; 1 − Können/110).

### Platte

- 3 (EP) bis 12 Lieder (LP), Pauschalpreis 500 ₲. Pro Lied ein Minispiel, in Etappen spielbar.
- Jedes Lied: etwas Können für alle Mitglieder, Zusammenspiel.
- Fertig: Erstverkauf = Lieder × 60 ₲ × (0,3 + Qualität²) × LP-Bonus × Perfekt-Bonus × Verkäufe,
  Ruhm = Lieder × 5 × Soundfaktor × (0,3 + Qualität^1,5) × LP-Bonus × Perfekt-Bonus.
  - LP-Bonus: +4 % pro Lied über 3. Perfekt (kein einziger Fehler): × 1,5. Verkäufe: 1 + Bandruhm/400, höchstens × 5.
- Vor der nächsten Platte spielt die Band die letzte live: 2 Lieder pro Lied der Platte auf Gigs
  (EP mit 3 Liedern: 6 Lieder, also 2 Gigs; LP mit 10 Liedern: 20 Lieder).

### Album-Charts

- Chartpunkte = 100 × Qualität² × Soundfaktor × Format × Perfekt (× 1,3) × (1 + Bandruhm/500, höchstens × 3).
  Format: EP × 0,6, LP (ab 7 Liedern) × 1 + 4 % pro Lied über 7.
- Die Punkte schrumpfen jeden Tag auf 88 %. Unter 1 Punkt fällt die Platte heraus. Gezeigt werden die Top 50.
- Tantiemen pro Tag in den Charts: 2 ₲ und 0,05 Ruhm pro Punkt, Ruhm verteilt auf die aktuellen Mitglieder.

### Promo

- Promo-Mission (ein Musiker): 10 Minuten, läuft offline. Bekanntheit + 1–2,5 × Ausstrahlung-Faktor / (1 + Bekanntheit/40).
- Band-Promo: 10 Minuten, jedes Mitglied bringt 0,8–2 nach derselben Formel; verteilt wie Auftrittsruhm.

### Jam-Café

Nur für Musiker ohne Band, 2 Lieder, beliebig oft. Im Publikum sitzen Musiker: Erwartung 35 (Straßenmusik 25,
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
- Fehlt eine Spur, nimmt das Spiel die nächste Stufe, dann die andere Variante, dann Pop. Gibt es gar keine, bleibt der Synthesizer.
- Zu hören am Mischpult (jeder Regler steuert seine Spuren) und in der Live-Ansicht von Auftritten.

## Vorläufige Entscheidungen zu offenen Fragen

Diese Punkte sind im Konzept offen. Für den Kern habe ich sie so festgelegt; alles lässt sich ändern.

- **Instrumente:** Keyboard und Synthesizer sind ein Instrument (Keyboard). Gitarren, Schlagzeuge und Keyboards gibt es akustisch und elektrisch; das merkt sich nur das Spiel, um später die passende Spur zu spielen.
- **Besetzung:** Jedes Mitglied spielt in der Band genau ein Instrument. Ein Sänger mit Gitarre zählt
  als Gitarrist, wenn er in der Band Gitarre spielt. Höchstens 6 Mitglieder.
- **Straßenmusik:** vorläufig ohne Geld fürs Label, dafür etwas Ruhm. Noch offen, ob sie bleibt.
- **Open Stage:** lohnt sich ab 120 Ruhm nicht mehr.
- **Platte:** Die letzte Platte erst live spielen (2 Lieder pro Plattenlied), dann die nächste. Ja, der Sound bestimmt, wie fehlerverzeihend das Minispiel ist.
- **Charts:** statt einer Liste pro Genre gibt es gemeinsame Album-Charts; die Label-Gesamtliste zählt den Ruhm aller eigenen Bands.
- **Ruhm verfällt nicht** (vorerst).
- **Präsenz** ist vorerst raus, um es einfach zu halten (der Wert steht noch in der Datenbank, zählt aber nicht).
- **Ob man bei Gigs mitspielt:** vorerst nicht, man schaut im Live-Ticker zu.

## Bekannte Grenzen

- Das Ergebnis des Minispiels kommt aus dem Browser. Der Server prüft nur, ob es plausibel ist
  (Notenzahl, Mindestdauer). Für ein Hobbyspiel unter Bekannten reicht das; gegen gezieltes Schummeln nicht.
- Bisher gibt es nur Pop Stufe 0 als Aufnahme. Die Minispiele Rhythmus und Tonhöhe nutzen noch den Synthesizer;
  ihre Noten kommen später aus `noten.json`.
- Bei sehr verzögerten Kopfhörern (Bluetooth) hört man die Treffer später, als man sie sieht. Gewertet wird
  nach dem Bild. Eine Latenz-Kalibrierung fehlt noch.
- Noch keine Bremse gegen Passwort-Raten beim Login.
