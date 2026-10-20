# Der Kern von Signed

Stand: erster spielbarer Kern. Grundlage ist das Claude-Doc „Signed – Spielkonzept“.
Alle Zahlen stehen in [`src/Game/Balance.php`](../src/Game/Balance.php) und sind Startwerte zum Nachjustieren.

## Was spielbar ist

Der Kernkreislauf: **Packs kaufen → Bands zusammenstellen → auftreten und Platten produzieren → Geld + Ruhm → neue Packs.**

| Bereich | Umfang im Kern |
|---|---|
| Label | Konto mit Labelname, 2.000 € Startkapital und ein Scout-Gutschein (keine Musiker zu Beginn) |
| Packs | Talent-Scout (5 gute Karten, eine wählen), Demo (150 €), Studio (450 €), Equipment (300 €), Gold (1.500 €, mindestens eine seltene Karte) |
| Musiker | Einmalig, zufällig erzeugt: 1–2 Instrumente, Können, Präsenz, Bekanntheit, Genre-Symbole, Eigenschaften, Spezialtechniken, Charakterzüge, „Ungewöhnlich“, Profilbild |
| Instrumente | 44 Modelle (11 Arten × 4 Seltenheiten) mit Klang, Spielbarkeit, Design; frei tauschbar |
| Manager | Stufe 1–5, 1–2 Fähigkeiten, bis zu 3 Bands mit geteilter Leistung |
| Bands | Besetzungsregeln, Sound, Eingespieltheit, Chemie, Ruhm nach dem Ehe-Modell, Bandhistorie |
| Aktivitäten | Straßenmusik, Open Stage, Gig (erst mit Platte), Solo-Probe (Rhythmus- oder Tonhöhen-Spiel), Promo-Mission (offline), Platte produzieren (Mischpult pro Lied) |
| Live | Live-Ticker mit Publikumsleiste für alle Auftritte |
| Charts | Gemeinsame Album-Charts aller Spieler mit täglichen Tantiemen, dazu eine Label-Gesamtliste |
| Namen | Zufallsnamen für Bands und Platten; Bandnamen sind im ganzen Spiel eindeutig |

### Bewusst noch nicht drin

Battle of the Bands, Tournee, Festival, ausgeschriebene Gigs, Jam-Café, Bandprobe, Handel und
Transfermarkt, Roadies, Gastmusiker, Contest im ESC-Stil, die echte Musik aus Logic Pro.
Die Manager-Fähigkeiten **Tourplaner** und **Netzwerker** werden schon verteilt, wirken aber erst mit
Tournee bzw. ausgeschriebenen Gigs (im Spiel ausgegraut markiert).

## Regeln und Formeln

### Musiker

- Startwerte je Seltenheit (Können / Präsenz): gewöhnlich 8–28 / 5–25, selten 22–42 / 12–38,
  episch 36–58 / 22–52, legendär 52–75 / 35–70.
- 20 % spielen zwei Instrumente. Dann gilt: Hauptinstrument × 0,85, Zweitinstrument × 0,55–0,8.
  Jede Probe verbessert nur das geübte Instrument (geteilter Können-Topf).
- „Ungewöhnlich“ (2 %): Das Lieblingsgenre passt nicht zum Klischee des Instruments. Bringt mehr Ruhm.
- Eigenschaften: Talent (Können +50 % schneller), Attraktiv (Präsenz +50 % schneller, etwas mehr Ruhm),
  Charisma (mehr Ruhm), Nervenstark (verspielt sich live seltener), Absolutes Gehör (großzügigeres Timing).

### Sound

```
Beitrag    = Können × (1 + Instrumentbonus) × Genre-Passung
Instrument = Klang/100 × 0,5 × min(1, Können / (100 − Spielbarkeit))
Genre      = 1, außerhalb der eigenen Genres 0,75
Sound      = (0,75 × Durchschnitt + 0,25 × schwächster Beitrag)
             × (1 + 0,05 × weitere Instrumentenarten, höchstens +20 %)
             × (0,7 + 0,3 × Eingespieltheit/100)
```

Ein schwacher Musiker zieht den Sound runter, und vier Musiker sind höchstens 20 % stärker als zwei.

### Eingespieltheit und Chemie

- Wächst pro Lied auf der Bühne (1,2 × Chemie) und pro Studio-Lied (2 × Trefferquote × Chemie).
- Chemie 0,5–1,5: Reibung (Diva+Diva, Rampensau+Rampensau, Perfektionist+Chaot, Ehrgeizig+Gelassen) −0,15,
  ein Ruhepol halbiert das; Teamplayer +0,04 pro Paar, Partytier+Partytier +0,06.
- Höchstwert der Eingespieltheit = 70 + (Chemie − 1) × 60, zwischen 40 und 100.
- Nach der Freigabe (erster Auftritt) kostet: neues Mitglied −5, Instrumentwechsel −5, Rauswurf −30.

### Ruhm

- Bandruhm = Summe der Bekanntheit aller Mitglieder. Wer geht, nimmt seine Bekanntheit mit.
- Ruhmgewinn eines Auftritts = Grund-Ruhm des Ortes pro Lied × Soundfaktor × Publikum × Zufall (0,9–1,1).
  - Soundfaktor = Sound / 30 (zwischen 0,2 und 3).
  - Publikum = 0,4 + 1,2 × Publikumsleiste/100.
- Einzelanteil = Gewinn / Mitglieder × (0,6 + 0,008 × Präsenz + Boni für Attraktiv, Charisma,
  Ungewöhnlich und Instrument-Design). Die Summe der Einzelanteile wird gleichmäßig auf alle verteilt.

### Auftritte

| | Lieder | Geld | Ruhm pro Lied | Besonderheit |
|---|---|---|---|---|
| Straßenmusik | 3 | 14 € pro Lied × (1 − Ruhm/150), mindestens 10 % | 0,3 × gleicher Abfall | keine Platte nötig |
| Open Stage | 2 | – | 1,4 × (1 − Ruhm/120) | 7 % Manager-Karte, sonst 15 % Fan-Karte (+3–10 Ruhm) |
| Gig | 1 bis Maximum | Gage des Ortes pro Lied, vorher bekannt | je nach Ort | erst mit Platte |

Orte nach Bandruhm: Kneipe (ab 0), Club (30), Konzertsaal (150), Halle (600), Arena (2.500).
Das Stadion bleibt der Tournee vorbehalten.

- Ein Gig hat höchstens so viele Lieder, wie die Band produziert hat, und ohne Manager höchstens 3.
  Ein Manager erlaubt 3 + ⌈Stufe × 1,4⌉ Lieder (Stufe 5: 10 Lieder = 5 Minuten).
- Verhandler: +8 % Gage pro Stufe. PR-Profi: +8 % Ruhm pro Stufe. Scout: weniger gewöhnliche Karten in Packs.
  Managt einer mehrere Bands, wird seine Stufe durch die Zahl der Bands geteilt.
- Publikumsleiste: startet bei 50. Jedes Lied hat 4 Abschnitte; in jedem kann jeder Musiker patzen
  (Chance 24 % minus 0,26 % pro Können-Punkt) oder glänzen (steigt mit Können, Präsenz und Spezialtechnik),
  jeweils ±7–13 Punkte. Dazu pro Abschnitt Grundstimmung, Vergleich Sound zu Erwartung des Ortes und Zufall;
  die Leiste rückt jedes Mal 8 % Richtung Mitte. Schwache Bands landen im Schnitt bei etwa 30 %, starke bei etwa 85 %.

### Talent-Scout

- 5 Musiker, mindestens selten (60 % selten, 32 % episch, 8 % legendär). Einer unterschreibt, die anderen verfallen.
- Gutscheine: 1 zum Label-Start, je 1 für die erste Platte des Labels, für jede perfekte Platte und für Platz 1 der Album-Charts.
- Ohne Gutschein: 3.000 €, höchstens einmal pro Woche.

### Minispiele

Ein Lied dauert 30 Sekunden. Bewertet wird in Perfekt / Gut / Daneben; Trefferquote = (Perfekt + 0,6 × Gut) / Anzahl.

- **Rhythmus** (Solo-Probe für alle außer Gesang und Geige): vier Spuren, Tasten D F J K oder Tippen.
  Dichte 0,25–0,55 je nach Können. Zeitfenster Perfekt 60–75 ms, Gut 135–185 ms, Absolutes Gehör +20 ms.
- **Tonhöhe** (Solo-Probe für Gesang und Geige): Gedrückt halten lässt den Ton steigen, Loslassen sinken.
  Jeder Ton der Melodie zählt als Perfekt ab 70 % der Zeit im richtigen Bereich, als Gut ab 40 %.
  Der Bereich ist ±7–13 % der Tonleiter, mit Absolutem Gehör +3 %.
- **Mischpult** (Studio): Die Band spielt, die Pegel der Spuren schwanken – pro Spur 3–11 Ausschläge,
  je schwächer der Musiker, desto öfter und stärker. Jede Spur hat 6 Abschnitte à 5 Sekunden, bewertet wie oben.
  Der grüne Bereich ist ±11–19 %, je nach Sound der Band.
- Solo-Probe: Können + 4 × Trefferquote^1,5 × Talent × (1 − Können/110).

### Platte

- 3 (EP) bis 12 Lieder (LP), Pauschalpreis 500 €. Pro Lied ein Minispiel, in Etappen spielbar.
- Jedes Lied: etwas Können für alle Mitglieder, Eingespieltheit.
- Fertig: Geld = Lieder × 150 € × (0,3 + Qualität²) × LP-Bonus × Perfekt-Bonus × Verkäufe,
  Ruhm = Lieder × 5 × Soundfaktor × (0,3 + Qualität^1,5) × LP-Bonus × Perfekt-Bonus.
  - LP-Bonus: +4 % pro Lied über 3. Perfekt (kein einziger Fehler): × 1,5. Verkäufe: 1 + Bandruhm/400, höchstens × 5.
- Danach braucht die Band 3 Gigs, bevor sie die nächste Platte beginnen darf.

### Album-Charts

- Chartpunkte = 100 × Qualität² × Soundfaktor × Format × Perfekt (× 1,3) × (1 + Bandruhm/500, höchstens × 3).
  Format: EP × 0,6, LP (ab 7 Liedern) × 1 + 4 % pro Lied über 7.
- Die Punkte schrumpfen jeden Tag auf 88 %. Unter 1 Punkt fällt die Platte heraus. Gezeigt werden die Top 50.
- Tantiemen pro Tag in den Charts: 2 € und 0,05 Ruhm pro Punkt, Ruhm verteilt auf die aktuellen Mitglieder.

### Promo-Mission

10 Minuten, läuft offline. Präsenz + 2–4 × Attraktiv-Faktor × (1 − Präsenz/110).

## Vorläufige Entscheidungen zu offenen Fragen

Diese Punkte sind im Konzept offen. Für den Kern habe ich sie so festgelegt; alles lässt sich ändern.

- **Besetzung:** Jedes Mitglied spielt in der Band genau ein Instrument. Ein Sänger mit Gitarre zählt
  als Gitarrist, wenn er in der Band Gitarre spielt. Höchstens 6 Mitglieder.
- **Straßenmusik:** 14 € pro Lied, ab 150 Ruhm nur noch 10 % davon.
- **Open Stage:** lohnt sich ab 120 Ruhm nicht mehr.
- **Platte:** 3 Gigs bis zur nächsten Platte. Ja, der Sound bestimmt, wie fehlerverzeihend das Minispiel ist.
- **Charts:** statt einer Liste pro Genre gibt es gemeinsame Album-Charts; die Label-Gesamtliste zählt den Ruhm aller eigenen Bands.
- **Ruhm verfällt nicht** (vorerst).
- **Präsenz:** Einzelanteil wie oben unter „Ruhm“.
- **Ob man bei Gigs mitspielt:** vorerst nicht, man schaut im Live-Ticker zu.

## Bekannte Grenzen

- Das Ergebnis des Minispiels kommt aus dem Browser. Der Server prüft nur, ob es plausibel ist
  (Notenzahl, Mindestdauer). Für ein Hobbyspiel unter Bekannten reicht das; gegen gezieltes Schummeln nicht.
- Die Musik ist ein Synthesizer-Platzhalter. Mit den Logic-Pro-Aufnahmen kommen Noten (Rhythmus) und
  Melodien (Tonhöhe) später direkt aus den MIDI-Dateien.
- Bei sehr verzögerten Kopfhörern (Bluetooth) hört man die Treffer später, als man sie sieht. Gewertet wird
  nach dem Bild. Eine Latenz-Kalibrierung fehlt noch.
- Noch keine Bremse gegen Passwort-Raten beim Login.
