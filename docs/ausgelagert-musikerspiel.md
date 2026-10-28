# Für das Musikerspiel ausgelagert

**Signed** bleibt auf der Ebene des Labels (siehe [`label-konzept.md`](label-konzept.md), Formeln in [`kern.md`](kern.md)).
Alles, was einen Musiker selbst betrifft – üben, spielen, eine Band gründen –, wandert in ein eigenes Spiel, in dem man
selbst der Musiker ist. Diese Datei sammelt, was dafür aus Signed herausgenommen wurde, mit den Zahlen und Erfahrungen,
damit nichts verloren geht. Dazu unten, was ganz aus Signed verschwindet (ohne Platz im Musikerspiel). Der Code ist in der
Testversion nicht mehr erreichbar und wird am Ende des Umbaus entfernt (Stand vor dem Umbau: `main` vom 24.10.2026, Commit `4aaa316`).

## Was herausgenommen wird

| Bereich | Wo im Code | Was es war |
|---|---|---|
| **Etüden** | `src/Game/Etude.php`, `src/Game/Styles.php`, `public/probe-etuede.html` | Das Spiel erzeugt aus typischen Genre-Bausteinen eine Übung (Tonart, Tempo, Akkordfolge, Rhythmus-Muster), Schwierigkeit nur nach Können. Mit Synthesizer-Begleitung. Alles hängt an einer Nummer (Seed): dieselbe Etüde wird mit mehr Können dichter, bleibt aber dieselbe. Grundlage für ein späteres Notizbuch („Etüde merken“) und Songwriting. |
| **Tonhöhen-Spiel** (Gesang, Geige) | `public/assets/js/pitch.js` | Halten = Ton steigt, Loslassen = sinkt. Steuerung bewusst unverändert gelassen (siehe „Erfahrungen“). |
| **Rhythmus-Spiel** (alle anderen) | `public/assets/js/minigame.js`, `src/Game/Chart.php`, `src/Game/Minigames.php` | Vier Spuren, D F J K oder Tippen; Haltenoten, Akkorde als zwei Noten. Wertung Perfekt/Gut/Daneben. |
| **Solo-Probe, „Allein üben“ (10 Min.)** | `Minigames.php`, `BandCalc::practiceGain`, `Balance::AUTO_PRACTICE` | Können + 0,8 × Trefferquote^1,5 × Talent × Lernkurve; auf Stufe 0 × 3. Allein üben läuft offline, bringt ¼. |
| **Jam-Café** | `src/Game/Jam.php`, `musicians.jam_at` | Musiker ohne Band jammen, danach drei Mitmusiker zur Auswahl, einer gründet mit. Mit Markierung gegen Missbrauch (Gründen/Auflösen/Wiederholen). |
| **Straßenmusik, Open Stage** | `src/Game/Activities.php` | Kleine Auftritte ohne Platte; Notgroschen, wenn das Geld nicht für eine EP reicht. |
| **Fan-Musiker** | `src/Game/FanMusician.php` | Nach Gigs in Halle/Arena bietet sich selten ein Fan als Musiker an. |
| **Einzel-Promo** | `Activities.php` | Promo-Mission eines Musikers. Kommt als Interview-Anfrage zurück (siehe Label-Konzept). |
| **Mischpult und Master-Mix** | `public/assets/js/mixer.js`, `master.js` | Pegel/Klangregler-Minispiele beim Plattenaufnehmen. Sind keine Instrument-Minispiele; vorerst raus, weil die Qualität einer Platte künftig vom Label (Studio, Produzent) kommt. |
| **Cover-Automat** | `public/assets/js/cover-machine.js`, `src/Game/Cover.php` | Vier Bänder (Motiv, Farben, Stil, Anordnung), Seltenheits-Rahmen. Wurde nicht gut angenommen. Das Cover-Zeichnen (`cover.js`) bleibt, das Label wählt künftig aus Entwürfen. |
| **Fähigkeiten rund ums Spielen** | `Levels.php` | Absolutes Gehör (großzügigeres Timing), Nervenstark (verspielt sich live seltener), Studiofuchs (grüner Bereich am Mischpult), Spezialtechniken je Instrument, Talent (+50 % Können). Talent bleibt als Wachstumsfaktor; die übrigen gehören ins Musikerspiel. |

**Ganz aus Signed entfernt** (kein Teil des Musikerspiels; beim Übergang alter Labels abgelöst, siehe `kern.md`):

| Bereich | Wo im Code | Was es war |
|---|---|---|
| **Läden / Packs** | `src/Game/Packs.php`, `views/packs.js` | Bezahlen, verdeckte Karten aufdecken, eine mitnehmen: Trödelmarkt 120 ₲, Schwarzes Brett 200 ₲, Second-Hand-Laden 350 ₲, Music Store 800 ₲ (mit Tipp), Backstage-Pass 1.500 ₲, Meisterwerkstatt 2.400 ₲. Ersetzt durch Kassetten, Bewerbungen und den Music Store mit festen Preisen. |
| **Talent-Scout mit Gutscheinen** | `src/Game/Scout.php`, `labels.scout_vouchers` | 5 Karten (70 % episch, 30 % legendär, Stufe 1), eine unterschreibt; Gutscheine zum Start, für die erste Platte und Platz 1; sonst 3.000 ₲ einmal die Woche. Ersetzt durch den Scout als Bewerbung mit Bericht. |
| **Wechselbörse** | `src/Game/Transfers.php`, `views/market.js` | Musiker ohne Band 2 Tage anbieten, Mindestpreis aus den Werten, verbindliche Gebote (Geld zurückgelegt), +5 % zum Überbieten, Verlängerung bei späten Geboten. |
| **Fan-Karten** | `items`, `Activities` (Open Stage) | +1–2 Ruhm für eine Band. |
| **Kartei und Bandplätze** | `views/bands.js` (alte Bandseiten) | Musiker ohne Band als Sammlung; bis zu 8 Bands, freigeschaltet über Label-Ruhm. Heute: jeder ist Band oder Solokünstler, keine Obergrenze. |
| **Zusammenspiel und Chemie** | `Balance::COHESION_BY_GENRE` | Zusammenspiel 0–100 (wuchs durch Lieder, Höchstwert nach Genre-Linie), Chemie 0,5–1,5 als Wachstumsfaktor, Abzüge für Ein-/Austritt und Rollenwechsel. Ersetzt durch den Zusammenhalt aus den Beziehungen. |
| **Stufen-Bremse** | `LEVEL_PACE` | Ab Stufe 1 wirkten Können- und Bekanntheitsgewinne nur noch zu 25 %, ab Stufe 2 zu 3 %. Ganz entfernt. |
| **Kopfhörer-Ausgleich** | `settings.js`, `mg-shell.js` | Bluetooth-Verzögerung je Gerät: 12 Klicks mitklopfen, Mittel ohne die ersten 3. Nur für die Minispiele nötig. |

Gemeinsam bleiben (beide Spiele): Musik-Werkzeuge (`tools/musik-import.php`, `src/Game/Music.php`, `audio.js`, `music.js`),
Figuren und Köpfe (`figure.js`, `avatar.js`), Namen und Aussehen der Musiker (`Generator.php`, `Names.php`).

## Zahlen und Regeln zum Mitnehmen

- **Ein Lied** dauert 24 Sekunden: 12 Takte 4/4 (oder 16 Takte 3/4) bei 120 BPM. Wertung: Trefferquote = (Perfekt + 0,6 × Gut) / Anzahl.
- **Zeitfenster** Rhythmus: Perfekt 60–75 ms, Gut 135–185 ms (Absolutes Gehör +20 ms).
- **Tonhöhe:** Bereich ±7–13 % der Tonleiter, Perfekt ab 70 % der Zeit im Bereich, Gut ab 40 %.
- **Etüden-Dichte** (`Balance::ETUDE_RATE`, Noten pro Sekunde bei Können 0 / 100, dazwischen Können^1,5): Schlagzeug 1,4 / 5,8; Bass 0,55 / 2,1; Begleitung 0,55 / 3,4; Melodie 0,55 / 3,0.
- **Lernkurve** für alle Können-Gewinne: (1 − Können/110)^2,5.
- **Können-Stufen:** 35 / 60 / 85 (Anfänger, Fortgeschritten, Profi, Virtuose). **Bekanntheits-Stufen:** 20 / 120 / 600 (Unbekannt, Lokal bekannt, Szenegröße, Star).
- **Tageskurse** bleiben im Label-Spiel (einzelne Musiker fördern) und sind hier nur der Vollständigkeit halber erwähnt.
- **Rhythmus-Spiel aus der Aufnahme:** Noten aus `noten.json` der eigenen Spur; Schlagzeug nur Bassdrum (links) und Snare (rechts), die Hi-Hat
  bleibt im Klang; andere Instrumente Spur nach Tonhöhe (tief links), Akkorde als zwei Noten (tiefster und höchster Ton), Töne ab 1,5 Schlägen
  gehalten. Haltenote: ab 85 % gehalten zählt die Wertung des Anschlags, ab 40 % „Gut“. Bei Treffern hört man die eigene Spur, sonst wird sie
  leise; dazu tickt ein Metronom. Ohne Aufnahme: Zufallsnoten, Dichte 0,25–0,55 je nach Können. Spuren ohne MIDI (Turntables): Anschläge aus der
  Aufnahme erkannt (Spektral-Fluss), aufs Achtel gerundet. Rap im Hip-Hop: Silben im Takt tippen.
- **Tonhöhen-Spiel:** Melodie aus der Aufnahme, Tonleiter ab ihrem tiefsten Ton; Steigen 0,5–1 Tonleiter je Sekunde nach den Sprüngen der
  Melodie. Nach einem Sprung zählt der Anfang des neuen Tons erst, wenn man ihn bei normalem Tempo erreichen kann (Weg ÷ Tempo minus Pause,
  plus 0,12 s, höchstens der halbe Ton).
- **Solo-Probe:** Können + 0,8 × Trefferquote^1,5 × Talent × Lernkurve, auf Stufe 0 × 3 (eine perfekte Probe bei Können 15: +1,7). Allein üben
  lassen: 10 Minuten, ¼ einer perfekten Probe; ab etwa 40 % Treffern lohnt sich Mitspielen.
- **Mischpult:** Pegel gleiten als Zufallspfad zur Mitte (neuer Zielpunkt alle 0,7–1,4 s, Schritt 0,05–0,18, je schwächer der Musiker, desto
  öfter und weiter); bis 4 eigene Spuren, größere Bands in Gruppen; 6 Abschnitte à 4 s; grüner Bereich ±11–19 % nach Sound; der Regler folgt
  der Bewegung des Fingers, nicht seiner Position.
- **Master-Mix:** Bässe (250 Hz), Mitten (1 kHz), Höhen (4 kHz); richtige Stellung 15–85 %, Abweichung 24 dB je Reglerlänge; Lampe grün/gelb/rot
  ohne Richtung; 4 Abschnitte à 6 s, nach der Hälfte verschiebt sich jede Stellung um 15–35 %.
- **Jam-Café:** Erwartung 35; danach 3 Musiker, die zum Gastgeber passen (anderes Instrument, gemeinsames Genre, keine Reibung), Stufe 0;
  je besser die Session, desto seltener gewöhnlich (85/14/1 % bis 35/45/20 %) und desto mehr Mindest-Können (5 bis 26).
- **Straßenmusik / Open Stage:** 3 bzw. 2 Lieder; Hut 20–45 ₲ nur, solange das Geld nicht für eine EP reicht; Ruhm 0,06 × (1 − Ruhm/150) bzw.
  0,18 × (1 − Ruhm/120).

## Erfahrungen aus dem Feinschliff (wichtig fürs neue Spiel)

- **Schwierigkeit ist heikel.** Bei der Tonhöhen-Steuerung hat jede „Verbesserung“ (Schonzeit, Magnet zum Zielton, Finger-Ziehen,
  zwei Knöpfe) das Gefühl schlechter gemacht. Am besten war die einfachste Steuerung: **Halten = steigen, Loslassen = sinken**.
  Gemessene Trefferquoten der Spieler damit: Können 35 ≈ 85 %, 60 ≈ 50–63 %, 100 ≈ 47–53 %.
- **Jede Etüde bei gleichem Können gleich schwer:** Erst als die Notendichte direkt am Können hing (nicht an Muster und Tempo),
  fühlte sich das Üben stimmig an. Davor sprang es zwischen leicht und schwer.
- **Versionsnummer** auf der Probe-Seite und ein Eingabefeld für das Können machten das Abstimmen erst möglich.
  Beim Ändern des Könnens muss die Etüde neu geladen werden (die Probe tut das jetzt automatisch).
- **Töne vor großen Sprüngen kürzen** (ab 3 Stufen, 0,07 s pro Stufe) gibt Zeit zum Hinübergleiten.
- **Langer Ton = langer Klang:** gehaltene Töne müssen auch wirklich so lange klingen (Gitarre/Keyboard/Synth hatten eine feste Ausklingzeit).
- **Begleitband** muss man hören: Bass eine Oktave höher, Akkorde unter jedem Instrument, sonst verschluckt der Tablet-Lautsprecher sie.
- **Sprechende Gesichter:** Mimik nach Charakter, aber nur zeitweise und weich überblendet (sonst wirkt es unheimlich).
- Kinder spielen mit (7 und 10): kurze Texte, leichte Animationen, alles ohne Lesen verständlich.

## Ideen, die liegen geblieben sind

- **Etüden-Notizbuch:** gelungene Etüden merken und später wieder spielen (Seed + Genre + Instrument reichen).
- **Eigene Songs:** Spieler schreiben Lieder (MIDI, Samples). Etüden und Styles sind die Grundlage.
- **Samples:** Aufnahmen echter Instrumente einbinden, sobald welche da sind.
- **Remaster:** ältere Platten wieder spielen.
