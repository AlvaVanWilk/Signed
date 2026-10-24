# Für das Musikerspiel ausgelagert

Stand: Beginn des Label-Umbaus. **Signed** bleibt auf der Ebene des Labels (siehe [`label-konzept.md`](label-konzept.md)).
Alles, was einen Musiker selbst betrifft – üben, spielen, eine Band gründen –, wandert in ein eigenes Spiel, in dem man
selbst der Musiker ist. Diese Datei sammelt, was dafür aus Signed herausgenommen wird, mit den Zahlen und Erfahrungen,
damit nichts verloren geht. Der Code bleibt bis zum Umbau im Repository (Git-Verlauf: Stand vor dem Umbau ist der
Tag/Commit `main` vom 24.10.2026, Commit `4aaa316`).

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
