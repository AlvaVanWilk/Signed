# Der Kern von Signed – Regeln und Formeln

Stand: Label-Spiel in der Testversion. Währung: Groove Coins (₲). Alle Zahlen stehen in [`src/Game/Balance.php`](../src/Game/Balance.php),
feste Daten in [`src/Game/Catalog.php`](../src/Game/Catalog.php); sie sind Startwerte zum Nachjustieren. Das Konzept dazu (was und warum) steht in
[`label-konzept.md`](label-konzept.md), was aus dem alten Spiel herausgenommen wurde, in [`ausgelagert-musikerspiel.md`](ausgelagert-musikerspiel.md).
Ändern sich Formeln, wird diese Datei mitgepflegt.

## Umfang

| Bereich | Umfang |
|---|---|
| Label | Name, 2.000 ₲ Startkapital, keine Musiker zu Beginn; Konto mit Kontoauszug (Klick aufs Geld), Tantiemen laufen sichtbar mit |
| Posteingang | Kassetten (Musiker und Bands), Briefe (Interviews, Scout-Berichte), Bewerbungen (Praktikum, Assistenz, Scout, Manager, Lehrkraft, Coach) |
| Musiker | zufällig erzeugt: 1–2 Instrumente, Können, Bekanntheit, Genres, Spieltechnik, Charakterzüge (erst verborgen), Wohlbefinden, Aussehen |
| Bands | ohne Obergrenze; jeder unter Vertrag ist in einer Band oder Solokünstler; Genre aus der Besetzung; Proberaum je Band |
| Anordnungen | Proben, Auftritt buchen, Aufnehmen, Promo-Tour, Auszeit, Tournee; Interviews einzelner Musiker |
| Fördern | Kurs des Tages, Privatlehrer, Songwriting-Coach, Instrumente (Music Store), Proberaum |
| Jede Woche | Festival (Mittwoch), Battle of the Bands (Samstag) |
| Erfolg | Album-Charts aller Labels, Rangliste, Auszeichnungen, WikiSignia |
| Einführung und Hilfe | sechs Schritte beim ersten Mal, Hilfe nach Themen (?); alte Labels sehen einmal „Was ist neu?“ |
| Namen | Zufallsnamen für Bands und Platten; Bandnamen sind im ganzen Spiel eindeutig (geschützt, solange sie Ruhm haben) |

## Regeln und Formeln

### Musiker

- Start-Können je Seltenheit: gewöhnlich 5–30, selten 15–42, episch 25–52, legendär 35–58.
- 20 % spielen zwei Instrumente: Hauptinstrument × 0,85, Zweitinstrument × 0,55–0,8. Proben verbessern das gespielte Instrument.
- „Ungewöhnlich“ (2 %): Das Lieblingsgenre passt nicht zum Klischee des Instruments; bringt mehr Bekanntheit (+0,3 Anteil).
- **Können-Stufe** 0–3 nach dem besten Instrument: ab 35, 60, 85 (Anfänger, Fortgeschritten, Profi, Virtuose). Sie bestimmt, welche
  Instrumente er spielen darf (grau, grün, blau, gold).
- **Bekanntheits-Stufe** 0–3 nach Bekanntheit: ab 20, 120, 600 (Unbekannt, Lokal bekannt, Szenegröße, Star).
- **Lernkurve** für alle Können-Gewinne außer Kursen und Privatlehrern: (1 − Können/110)^2,5.
- **Spieltechnik:** genau eine (84, `Catalog::ABILITIES`), passend zu Instrument und gemochten Genres.
- **Charakterzüge** und ihre Zahlen: `Balance::TRAITS`, Tabelle in `label-konzept.md`.
- Jedes Genre hat eine feste Farbe und ein Symbol.

### Sound

```
Beitrag  = Können × (1 + Klang-Bonus des Instruments)        ohne eigenes Instrument × 0,5
Sound    = (0,75 × Durchschnitt + 0,25 × schwächster Beitrag)
           × Genre (gemischt 0,8 / Genre-Band 1,15 / Hybrid 1,25 / Crossover 1,3; Genre geteilt, Besetzung fehlt: 1,0)
           × (1 + 0,04 × Spieltechniken im Genre der Band, höchstens zwei je Musiker)
           × (1 + 0,05 × weitere Instrumentenarten, höchstens +20 %)
           × (1 + 0,05 × Mitglieder über 2)
           × (0,7 + 0,3 × Zusammenhalt/100)
```

Ein schwacher Musiker zieht den Sound runter; große Bands klingen voller (sieben Musiker mit fünf Instrumentenarten bis zu 50 % mehr als ein Duo).
Solokünstler: kein Genre-Bonus außer halb für Pop und Elektro, Zusammenhalt 100.

### Genre einer Band

- Man wählt kein Genre; es ergibt sich aus Mitgliedern und Besetzung (gezählt wird das Instrument, das jemand in der Band spielt).
- **Genre-Band** (z. B. „Rockband“): Alle mögen das Genre **und** die Besetzung passt. **Hybrid** (z. B. „Punk-Rock-Band“): dasselbe für ein
  zweites Genre. **Crossover**: sogar für ein drittes (selten, ein Sammelziel).
- Besetzung je Genre:

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
- Ohne gemeinsames Genre (**gemischte Band**): Sound × 0,8. Kein eigener Verkaufs- oder Ruhm-Bonus fürs Genre.
- Wechsel kommen in die Chronik; beim Zusammentun zeigt das Spiel vorher, was herauskommt.
- **Namen:** Umbenennen kostet nie Geld; mit Laufbahn geht der Ruhm am alten Namen verloren. Verbundener Name („und die“) behält 50 % des Ruhms
  beider Namen. Ein Name bleibt geschützt, wenn er am Ende mindestens 20 Ruhm hatte.

### Beziehungen und Zusammenhalt

- Beziehung je Paar −10 bis +10, Start 0 (als Band unterschrieben: 4). Gemeinsame Erlebnisse ziehen sie ein Stück zum **Zielwert**
  `10 × Passung + 3` (`Mood::fit`, `Balance::RELATION`): Probe 0,12, Auftritt 0,10, Aufnahme 0,08, Promo-Tour 0,05, Auszeit 0,08, Battle 0,10,
  Festival 0,12, Tournee-Auftritt 0,12, ganze Tournee 0,35 (Anteil des Weges). Getrennt driften sie mit 0,2 je Stunde auf 0.
- **Passung:** je Reibungspaar −0,5 (Ausgeglichene halbieren), Teamplayer +0,25, zwei Gesellige +0,3, gemeinsame Genres −0,3 / +0,1 / +0,3 / +0,4,
  Können-Abstand über 30 −0,2, über 50 −0,4; zwischen −1 und +1.
- **Zusammenhalt** = 0,5 × Durchschnitt + 0,5 × schwächste Beziehung; als Wert 0–100: `50 + 5 × Zusammenhalt` (Solokünstler 100).
  Neue Bands starten bei 50 (Sound × 0,85).

### Wohlbefinden und Bedürfnisse

- Wohlbefinden (Start 60) läuft auf ein Ziel zu (Halbwert 12 Stunden, `Balance::MOOD`); grün ab 60, gelb ab 35, sonst rot.
  Die Band ist so gestimmt wie ihr Unzufriedenster; fürs Songschreiben zählen 70 % Unzufriedenster, 30 % Durchschnitt.
- Ziel = 60 + Beziehungen (×3 je Punkt Durchschnitt) + Können-Abstand + Genre der Band (passt +6, passt nicht −8, gemischt −4) + Instrument
  (keins −6, zu schwach −3 je Stufe, passend +2; Diven doppelt) − Songstau (ab 12 Songs 1,5 je Song, höchstens 18) − Miesepeter − Unruhe + Proberaum.
- **Reaktionen:** Was der Charakter mag, hebt sofort, was er nicht mag, drückt (`Catalog::LIKES`, `Balance::MOOD['reactStep']`).
- **Unerfüllte Bedürfnisse wachsen** (`Balance::NEEDS`): Wer nicht grün ist, wartet auf seinen Wunsch; nach 2 Std. Schonzeit −0,4 je Stunde,
  bis die Band etwas macht, das er mag. Auf ein passendes Instrument warten: nach 4 Std. −0,3 je Stunde (Diven ×2). Ohne Bühne (Extrovertierte 6,
  Gesellige 5, ab 8 Std.) und ohne Platte (Ehrgeizige 6, ab 48 Std.) wächst der Abzug je Stunde.
- **Offline** rechnet die Stimmung bis zum ersten Rot und bleibt dann stehen, auch die Wartezeiten. **Geduld** (nur online im Roten): 30 Minuten,
  dann verlässt die Band das Label.

### Songs

- Tempo = Grundtempo nach Stimmung (grün 1 Song je Stunde, gelb 0,5, rot 0) × (Σ 0,25 × Charakter-Faktor je Mitglied)^1,5
  (Kreativ ×3, Impulsiv ×1,6, Perfektionistisch ×0,6, Technisch 0; `Balance::SONGWRITING`).
- Höchstens 24 Songs; ab 12 Stau (siehe oben). Songwriting-Coach: ×1,5 / ×1,75 / ×2 für einen Tag.

### Ruhm

- Ein Musiker hat **Bekanntheit**, eine Band hat **Ruhm** = Ruhm am Namen + Bekanntheit aller Mitglieder.
- Von jedem Ruhmgewinn der Band bleiben 25 % am Namen, 75 % werden Bekanntheit der Mitglieder: Einzelanteil = Gewinn / Mitglieder ×
  (0,8 + 0,3 Ungewöhnlich + bei Auftritten Charakter (Extrovertiert +0,2, Gesellig +0,15) und Publikumsmagnet); die Summe wird gleich verteilt.
- Keine Bremse und keine Rückwirkung (Ruhm erhöht nicht zusätzlich Bekanntheit).
- **Label-Ruhm** = √(Σ Bandruhm²) (`BandCalc::labelFame`).
- Jede Bekanntheit wird je Band und Tag mitgeschrieben (`fame_days`, für die Festival-Bühnen).

### Proben

- Offen, bis einer keine Lust mehr hat: Grundwert 60 Minuten × Charakter (`Catalog::LUST`) × Proberaum; Mindestdauer 1 Minute.
  Gewinne je 15 Minuten (`Balance::REHEARSAL`): Können 4,0 × Lernkurve × Talent (Technisch ×1,25, Kreativ ×0,85) × Proberaum
  (×1,08 / 1,16 / 1,25), etwas mehr unter Besseren; Beziehungen wie oben; Wohlbefinden je Charakter, Proberaum +1 / +2,5 / +4.
- Alles wird laufend gutgeschrieben; „Probe beenden“ behält den Fortschritt.

### Auftritte

- **Orte** nach Bandruhm (Anspruch): Kneipe (ab 0), Club (30), Konzertsaal (150), Halle (600), Arena (2.500). Buchbar vom kleinsten bis zwei
  Stufen über dem Anspruch. Grund-Gage je Lied 30 / 70 / 160 / 380 / 900 ₲, Grund-Ruhm je Lied 0,25 / 0,5 / 1 / 2 / 4, Erwartung 15 / 28 / 42 / 58 / 75.
- **Kosten** 20 / 90 / 350 / 1.100 / 3.200 ₲ (Solokünstler ×1,5, Pop ×1,2), **Länge** 3 / 4 / 5 / 7 / 9 Songs (aufgenommene und geschriebene zählen).
- **Gage** = Grund-Gage × Songs × Frische × (1 + 10 % je Mitglied über 2) × (1 + Verhandler) × **Füllgrad** × (0,7 + 0,3 × Leistung)
  (beim Buchen mit der Mitte des Ortes gerechnet).
  Füllgrad = Sound gegen Erwartung × Bekanntheit gegen Ortsgröße ((Ruhm + 10) / (Mindestruhm + 10)), mindestens 5 %.
- **Frische:** Jeder Auftritt seit der letzten Platte senkt Gage und Ruhm um 8 %, höchstens auf 45 %; eine neue Platte macht wieder voll.
- **Bezahlt wird hinterher** aus der Gage; vorher nur, was selbst der schlechteste Abend nicht einspielt (`Gigs::deposit`).
- **Ruhm** = Σ Lieder (Grund-Ruhm × Soundfaktor × Publikum × 0,9–1,1) × Frische × (1 + PR-Profi); Soundfaktor = Sound / 30 (0,2 bis 3),
  Publikum = 0,4 + 1,2 × Publikumsleiste/100.
- **Anspruch:** Ort darunter −6 Wohlbefinden, darüber +4 (Ehrgeizige, Diven, Extrovertierte ×1,7).
- **Publikumsleiste:** startet bei 0; im ersten Lied rückt sie je Abschnitt 40 % Richtung 50 + 0,6 × (Gleichgewicht − 50). Jedes Lied hat
  4 Abschnitte; jeder Musiker kann patzen (24 % minus 0,26 % je Können-Punkt) oder glänzen (mit Können, Technik, Charakter, Showstück),
  je ±7–13 Punkte. Ab dem zweiten Lied rückt sie jedes Mal 8 % Richtung Mitte. Schwache Bands landen bei etwa 30 %, starke bei etwa 85 %.
- **Applaus:** echte Aufnahme je Ortsgröße, lauter und länger, je begeisterter.
- Manager: Verhandler +8 % Gage, PR-Profi +8 % Ruhm je Stufe (× Passung).

### Platte

- 3 (EP) bis 12 Lieder (LP) **aus dem Song-Vorrat**; die Band ist 5 Minuten je Lied im Studio, dann erscheint die Platte.
- **Studio** und **Produzent** (`Balance::STUDIOS`, `Balance::PRODUCERS`):

  | Studio | Qualität | Preis je Lied |
  |---|---|---|
  | Garage | +0 | 20 ₲ |
  | Tonstudio | +0,12 | 60 ₲ |
  | Profi-Studio | +0,24 | 150 ₲ |
  | Legendäres Studio | +0,36 | 350 ₲ |

  | Produzent | Qualität | Honorar | bringt die Band bis ins |
  |---|---|---|---|
  | ohne | +0 | – | Garage |
  | Mo Beats | +0,08 | 150 ₲ | Tonstudio |
  | Roxy Reverb | +0,16 | 500 ₲ | Profi-Studio |
  | Diamond Dee | +0,24 | 1.500 ₲ | Legendäre Studio |
  | Ruben Stille (Legende) | +0,30 | 2.500 ₲ | jedes Studio |

  Qualität = 0,35 + Studio + Produzent + 0,04 je Perfektionist + Studio-Legende (Instrument) + Coach, höchstens 1; ab 0,95 **perfekt** (× 1,5).
  Ruben Stille nimmt nur Bands ab Sound 45, alle sind mit ihm entspannt (+6 Wohlbefinden).
- **Anspruch:** Ruhm-Stufe gegen Produktionsniveau; je Stufe darunter −6 (Egozentrische und Perfektionistische × 1,5), je Stufe darüber +3.
- **Cover:** drei Entwürfe, neue kosten 25 ₲. Gestaltung: Kunststudentin 20 ₲ (+0 %), Agentur 150 ₲ (+3 %), Star-Designerin 600 ₲ (+6 %);
  Bandlogo ab der zweiten Platte +3 %. Erstverkauf × (1 + Gestaltung + Logo).
- **Fertig:** Können 0,12 × Lieder × Qualität für alle, Beziehungen wie nach einer Anordnung.
  Erstverkauf = Lieder × 60 ₲ × (0,3 + Qualität²) × LP-Bonus × Perfekt × (1 + Bandruhm/400, höchstens × 5) × Egozentrische/Kultobjekt;
  Ruhm = Lieder × 0,6 × Soundfaktor × (0,3 + Qualität^1,5) × LP-Bonus × Perfekt. LP-Bonus +4 % je Lied über 3.
- **Album-Zyklus** (`Balance::RECORD_CYCLE`): Vor der nächsten Platte spielt die Band die letzte 3-mal komplett live (gezählte Lieder) und
  macht 3 Promo-Touren. Der Knopf „Aufnehmen“ zeigt, was noch fehlt.

### Album-Charts und Tantiemen

- Chartpunkte = 100 × Qualität² × Soundfaktor × Format × Perfekt (× 1,3) × Hitmaschine – nur die Platte selbst, **ohne Bandruhm**.
  Format: EP × 0,6, LP (ab 7 Liedern) × 1 + 4 % je Lied über 7.
- Punkte jetzt = Chartpunkte × Verlauf + Schub. Verlauf: −3 % je Stunde (nach einem Tag 48 %, nach drei 11 %). Schub für die neueste Platte
  der Band: Auftritt +2,5 % je Lied, Promo-Tour +10 %, Interview +4 %; höchstens +60 %, halbiert sich alle 3 Stunden.
- Plätze werden alle 3 Stunden neu vergeben (sofort bei neuer Platte oder Schub); Trend, Stunden in den Charts, bester Platz.
  Top 10 groß, darunter bis Platz 100. Unter 1 Punkt oder nach 7 Tagen zählt eine Platte nicht mehr.
- **Tantiemen** laufen ständig, solange die Platte höchstens auf Platz 50 steht: je Punkt und Stunde 0,6 ₲ und 0,0125 Ruhm (an die aktuellen
  Mitglieder). Eine Platte mit 60 Punkten bringt über ihre Chartzeit etwa 1.200 ₲. Dazu verkauft jede Platte für immer ein wenig (Katalog:
  0,01 ₲ je Stunde und Chartpunkt).
- **Verkaufte Stück** = Chartpunkt-Stunden × 100 (für Gold, Platin, Diamant).
- Rangliste: Labels nach Label-Ruhm, dazu Bands.

### Promo-Tour und Interviews

- **Promo-Tour** (Band oder Solokünstler): 10 Minuten, jedes Mitglied bringt 0,1–0,25 Bekanntheit / (1 + Bekanntheit/40); Chart-Schub +10 %;
  zählt für den Album-Zyklus. Abbrechen geht, bringt dann nichts.
- **Interview** (Anfrage im Posteingang): Annehmen schickt den Musiker weg (15 / 20 / 30 / 45 Minuten je Bekanntheitsstufe).
  Gewinn = Anteil des Mediums (8–14 %) × Weg zur nächsten Stufe (Stufe 0: 20, 1: 100, 2: 480, 3: 600), ohne Bremse; Netzwerker +10 % je Stufe.
  Anfragen je Tag: 0,1 + 0,05 × √Bekanntheit, höchstens 1 + Stufe zugleich offen, sie liegen vier Tage.

### Kurse, Privatlehrer, Coach

- **Kurs des Tages:** jeden Tag ein Instrument (reihum, nie zweimal hintereinander) und eine Kursart, für alle gleich: Kurs (50 %, 10 Min.,
  +3 Können), Workshop (35 %, 30 Min., +5), Meisterklasse (15 %, 60 Min., +8). Drei Schwierigkeiten (Stufe 0, 1, 2+), jeder nur in seiner;
  Preis = Grundpreis (80 / 150 / 300 ₲) × 1 / 3 / 8. Ohne Lernkurve. Jeder Musiker einmal am Tag; Zurückholen gibt das Geld zurück.
- **Privatlehrer:** 300 / 900 / 2.500 ₲, 2 Tage, +8 / +12 / +16 Können ohne Lernkurve (passendes Genre ×1,3), nur bis 55 / 80 / 100.
- **Songwriting-Coach:** 400 / 1.200 / 3.000 ₲, 1 Tag für eine Band, Songs ×1,5 / ×1,75 / ×2, Plattenqualität +4 / +8 / +12 % (anteilig).

### Auszeit

Ausflug 1 Std. (+8, 20 ₲ je Mitglied), Wochenende 4 Std. (+16, 60 ₲), Urlaub 12 Std. (+28, 150 ₲); Gemütliche und Ausgeglichene ×1,5,
Ehrgeizige ×0,5 (`Balance::RETREAT`). Keine Songs in der Zeit; geht auch bei Rot.

### Battle of the Bands

Jeden **Samstag um 16 Uhr** (deutsche Zeit) für alle Labels (`Balance::BATTLE`, `Battle`, `Events`). Anmeldung die ganze Woche, höchstens
**2 Bands je Label** und 32 insgesamt; nur Bands mit mindestens 2 Musikern (keine Solokünstler), keine Computer-Bands, unter 2 Anmeldungen
fällt es aus. **Online sein muss niemand:** Der Server spielt das Battle aus, das Ergebnis kommt beim nächsten Öffnen. Eine angemeldete Band
kann vorher nichts anfangen, was über den Beginn hinaus dauern würde (Auftritt, Probe, Promo, Auszeit, Studio, Tournee, Kurs, Interview
eines Mitglieds); das wird mit Hinweis abgelehnt.

- **Ablauf wie bei einer WM:** 2 Bands spielen nur das Finale, 3–5 eine Gruppe (die ersten zwei ins Finale, 3. und 4. um Platz 3),
  6–11 zwei Gruppen, 12–23 vier, 24–32 acht. In der Gruppe jeder gegen jeden, die ersten zwei kommen weiter (Erster gegen Zweiten der
  Nachbargruppe), dann K.-o. bis zum Finale; die Verlierer des Halbfinales spielen zugleich um Platz 3. Tabelle: Siege, dann Punkte-Unterschied, dann Punkte.
- **Bands eines Labels** kommen in verschiedene Gruppen; treffen sie in der ersten K.-o.-Runde aufeinander, wird mit einem anderen Spiel getauscht,
  wenn das geht (nur bei einer einzigen Gruppe geht es nicht anders).
- **Ein Spiel:** jede Band 2 Lieder (je 24 s), dann 12 s Jury; alle 5 Minuten eine Runde (8 Bands: 5 Runden, gut 20 Minuten; höchstens 9 Runden).
  Punkte = Sound × Laune × Tagesform, gerundet. **Laune** = 1 + (Stimmung der Band − 60) × 0,0025 (Stimmung 100: +10 %, 20: −10 %).
  **Tagesform** zufällig 0,8–1,2 je Spiel. Ruhm, Proben und Klassen zählen nicht. Doppelter Sound gewinnt immer, 10 % mehr Sound meistens.
- Ausgelost und ausgespielt wird beim Beginn (bei der ersten Anfrage danach); gezeigt wird nach der Uhr, Runde für Runde. Die Ansage
  (Lieder, Glanzmomente, Patzer, Publikum) folgt der Tagesform. Alle Bands bleiben bis zum Schluss dort, damit die Uhr nicht verrät, wie weit sie kommen.
- **Belohnung** (am Ende): Ruhm 1,5 je Spiel + 1,5 je Sieg + nach Platz 20 / 12 / 7 / 4 (fest, nicht nach Ruhm), verteilt wie bei Auftritten;
  Preisgeld 600 / 300 / 150 / 80 ₲; Stimmung +12 / +6 / +4 für die ersten drei; Pokal in Gold, Silber, Bronze (steht im Proberaum);
  Chart-Schub wie bei Auftritten je gespieltes Lied; Beziehungen wie bei einem Auftritt.
  Ehrgeizige, Extrovertierte und Egozentrische mögen das Battle, Gemütliche nicht. Alle Labels bekommen die Nachricht, wer gewonnen hat.
- Zum Tempo: Der Sieger einer Achterrunde bekommt gut 30 Ruhm (etwa acht Club-Auftritte), einmal je Woche; für kleine Bands ein großer Schritt,
  für große vor allem Pokal und Preisgeld. Weil der Gewinn fest ist, verstärkt Ruhm ihn nicht.

### Festival

Jeden **Mittwoch um 17 Uhr**, Bewerbung bis **Mittwoch 12 Uhr** (`Balance::FESTIVAL`, `Festival`). Jedes Festival sucht ein **Genre**
(aus der Woche berechnet, nie zweimal hintereinander dasselbe; Name nach `Catalog::FESTIVALS`). Bewerben können sich nur Bands mit diesem
Genre (auch als zweites oder drittes Genre), jedes Label mit so vielen, wie es will. Bis zum Beginn muss die Band zurück sein (wie beim Battle).

- **Plätze:** Hauptbühne 2, Nebenbühne 3, Zeltbühne 3. Sind es mehr Bewerbungen, entscheidet das Los (gezeigt als „der Veranstalter hat sich
  für andere Bands entschieden“). Wer nicht mehr passt (Genre gewechselt, Band aufgelöst), fällt raus.
- **Bühnen nach der Bekanntheit der letzten 7 Tage** (`fame_days`, auch die vom Festival selbst): die meiste spielt als **Headliner** zuletzt auf
  der Hauptbühne, dann der Reihe nach Hauptbühne, Nebenbühne, Zeltbühne (auf jeder Bühne die Bessere später). Das letzte Festival liegt schon
  außerhalb der 7 Tage, und das Genre wechselt jede Woche.
- Auftritte alle 10 Minuten, auf allen Bühnen zugleich, alle enden zur selben Zeit; mit wenigen Bands geht es gleich zu Beginn los.
  Das Line-up hängt vom Anmeldeschluss bis zum Beginn aus; alle Labels bekommen die Nachricht, wer Headliner ist.
- **Ein Auftritt** wird beim Beginn wie ein Auftritt im Club berechnet (Publikumsleiste, Ticker, „Zuschauen“) und zu seiner Zeit gezeigt.
  Die Band ist vom Beginn des Festivals bis zum Ende ihres Auftritts dort.

| Bühne | Lieder | Ruhm je Lied | Gage | Erwartung |
|---|---|---|---|---|
| Hauptbühne | 6 | 5,0 | 300 ₲ | 45 |
| Nebenbühne | 5 | 3,0 | 150 ₲ | 35 |
| Zeltbühne | 4 | 1,8 | 80 ₲ | 25 |

  Ruhm = Σ Lieder (Ruhm je Lied × Soundfaktor × Publikum × 0,9–1,1) × (1 + PR-Profi). Bei Sound 30 und mittlerem Publikum bringt die
  Hauptbühne rund 30 Ruhm (etwa acht Club-Auftritte), die Zeltbühne rund 7. Dazu Chart-Schub je Lied, Beziehungen, die Lieder zählen für den
  Album-Zyklus.
- **Danach:** Ein Medium eine Stufe größer als sonst fragt nach einem Interview. Der **Headliner** bekommt ein Plakat für den Proberaum.
  Ehrgeizige, Impulsive, Extrovertierte, Gesellige und Egozentrische mögen Festivals, Gemütliche nicht.

### Tournee

Anordnung auf der Bandseite (`Balance::TOUR`, `Tours`), erst mit einer Platte. Man wählt nur die Länge (3, 5 oder 7 Stationen);
**jedes Ziel wird ausgewürfelt** (echte Länder, `Catalog::COUNTRIES`, keins doppelt) und erst bekannt, wenn es weitergeht.

- **Ablauf, alles per Klick:** Flug zum Ziel (Reisezeit 30 Min. + 0,6 s je km: London etwa 40 Min., Tokio 2 Std., Sydney 3 Std.) →
  Meldung „angekommen“ → **Auftritt starten** → danach **Weiterreisen** oder **Nach Hause**. Nach der letzten Station bleibt nur der Heimflug.
  Wer zwei Tage nicht weitermacht, fliegt von selbst heim. Die Band kann während der Tournee nichts anderes machen; wer für Battle oder
  Festival angemeldet ist, kann nicht los.
- **Kosten:** je Strecke Mitglieder × (15 + 0,01 ₲ je km), bei vier Musikern etwa 80 ₲ (Amsterdam) bis 700 ₲ (Sydney); der Heimflug ist frei.
  Reicht das Geld nicht, bleibt das ausgewürfelte Ziel stehen.
- **Ein Auftritt** wie im Ort nach Ruhm mit ganzer Setlist, aber ohne Abnutzung (im Ausland kennt niemand die Lieder): Ruhm × 2, Gage × 1,25.
  **Lieblings-Genre** des Landes (England Rock, Irland Folk, Frankreich Pop, Spanien Folk, Niederlande Elektro, Schweden Elektro, Norwegen Metal,
  Finnland Metal, USA Hip-Hop, Kanada Punk, Brasilien Jazz, Südafrika Jazz, Japan Pop, Australien Rock): Spielt die Band es, noch einmal
  Ruhm × 1,5 und Gage × 1,5, und das Publikum ist leichter zu begeistern (Erwartung × 0,85).
- **Bühne des Landes** (`stage-world.js`, als `tour-fr` …): klischeehaft; Größe und Gage richten sich trotzdem nach dem Ort nach Ruhm.
- **Weltenbummler:** Hat eine Band (über alle Tourneen) in allen 14 Ländern gespielt, steht ein goldener Globus in ihrem Proberaum.
- **Beziehungen:** Jeder Auftritt wie ein Auftritt, am Ende die ganze Tournee und zusätzlich ein fester Schub von +0,6 je gespielte Station
  (höchstens +3) auf jede Beziehung, auch über den Zielwert hinaus. Ehrgeizige, Impulsive, Extrovertierte und Gesellige mögen Tourneen,
  Gemütliche und Ausgeglichene nicht.

### Auszeichnungen

Nur echte Pokale (`Awards`, `Balance::AWARDS`), alle im Proberaum der Band (Schallplatten, Pässe und Plakate an der Wand, Pokale und Globus
auf dem Boden) und als Liste auf der Bandseite und in WikiSignia. Neue werden einmal mit einem Fenster gefeiert.
- **Goldene Schallplatte** ab 100.000 verkauften Stück, **Platin** ab 250.000, **Diamant** ab 1 Million (je Platte einmal). Eine Platte mit
  30 Punkten schafft etwa Gold, mit 75 Platin, Diamant nur die besten (um 300 Punkte). Beim Umbau werden die bisherigen Verkäufe geschätzt
  (ohne Schub) und die Auszeichnungen ohne Feier vergeben.
- **Platz 1 der Charts** (je Platte, nur mit Konkurrenz aus mindestens 3 Labels), **Große Halle** und **Arena** (erster Auftritt dort, je Band;
  auf Tournee zählt die Größe des Ortes).
- Dazu: Battle-Pokale (Platz 1 bis 3), Festival-Plakat (Headliner), Weltenbummler-Globus (alle Länder).

### Posteingang und Leute

- Raten je Tag = Start + Zuwachs × √Label-Ruhm (Tabelle in `label-konzept.md`), zufällig verteilt (`Post::nextArrival`); alles liegt vier Tage.
  Anteil guter Kassetten 5 % bis 25 % (bis Label-Ruhm 5.000); Güte der Bewerber = Ruhm / (Ruhm + 300).
- **Unterschriftsprämie** = (40 + 0,05 × Können² + 1,5 × Bekanntheit) × (1 + 12 % × versteckter Wert); wer bei einer Band zurückbleibt,
  kränkt die anderen (Wohlbefinden −20, Teamplayer ×1,6, Ehrgeizige ×0,5).
- Arbeit je Stück (gleich für einen selbst, Praktikum und Assistenz): Umschlag 2 s, Kassette anhören 5 s, Brief lesen 5 s, einsortieren 1 s.
  Assistenz holt offline höchstens vier Tage nach.
- Praktikum 50 ₲ für 7 Tage, übernehmen 400 ₲; Assistenz 400 ₲; Scout 400 / 1.200 / 3.000 ₲ (6 / 5 / 4 Std., 2 / 3 / 3 Talente, Können bis
  34 / 44 / 54); Manager 300 / 3.000 / 10.000 ₲ (Stärke 1 / 3 / 5, Passung ×0,4 bis ×1,45).

### Instrumente und Music Store

- **Güteklassen** (`Gear`): gewöhnlich (grau), selten (grün), episch (blau), legendär (gold) – dieselben Farben wie die Können-Stufen.
  Spielen darf ein Musiker nur Instrumente bis zu seiner Können-Stufe am jeweiligen Instrument. Kaufen darf das Label alles; beim Anlegen steht,
  wie viel Können noch fehlt.
- Ohne eigenes Instrument zählt ein Musiker im Sound nur halb (× 0,5); ein graues Instrument hebt das auf, mehr nicht.
- **Boni** je Klasse (`Balance::GEAR_BONUS`), innerhalb ihrer Spanne gewürfelt:
  grün einer von Ausdauer beim Proben (+10–45 %) oder Lernhilfe (+5–24 % Können je Probe und Platte);
  blau Klang: Sound des Musikers +20–35 % (episch) bzw. +35–50 % (legendär);
  gold eins von: Publikumsmagnet (Ruhm-Anteil bei Auftritten +0,15–0,35), Kultobjekt (Erstverkauf +10–25 %),
  Studio-Legende (Plattenqualität +0,03–0,06), Showstück (live +3–6 % Glanzchance), Hitmaschine (Chartpunkte +10–25 %).
- Preis und Verkaufswert × 0,8 bis × 1,3 nach Güte innerhalb der Klasse. Verkauf: 25 / 80 / 260 / 750 ₲ je Klasse.
- Ältere Instrumente bekommen ihre Boni aus ihren alten Werten (`Gear::legacy`).
- **Music Store:** Grundausstattung (jedes graue Modell, immer, 40 ₲); Angebot des Tages für alle gleich, wechselt um 4 Uhr UTC: drei Fächer,
  meist grün, mit 35 % eins blau, mit 13 % zusätzlich ein goldenes im Schaufenster; Preise 320 / 1.500 / 5.200 ₲ × Güte; jedes Angebot einmal je Label.
- Aussehen je Klasse (`gearart.js`): grau schlicht; grün natürliche Hölzer bzw. Metalle; blau farbig lackiert mit Gold; Gold sieht nach seiner
  Fähigkeit aus (Publikumsmagnet nachtblau mit goldenen Ranken, Showstück Galaxie mit Strass, Studio-Legende elfenbein mit Goldlinien,
  Kultobjekt geschnitztes Holz mit Perlen und Feder, Hitmaschine poliertes Gold mit Rillen).

### Proberaum

Bruchbude → Aufgeräumter Keller (400 ₲) → Gemütlicher Proberaum (1.200 ₲) → Profi-Proberaum (3.000 ₲) (`Balance::ROOM`): Können je Probe
×1,08 / 1,16 / 1,25, Ausdauer ×1,15 / 1,3 / 1,5, Wohlbefinden +1 / +2,5 / +4 je 15 Minuten Probe. Jeder Solokünstler hat seine eigene Übungsecke.

### Übergang alter Labels

Einmalig beim Umbau der Datenbank (Schritt 53, `Transition`, Zahlen `Balance::TRANSITION`). Label, Geld, Bands, Platten, Instrumente und Manager bleiben.
- **Wechselbörse** zu: Angebotene Musiker bleiben beim Label, Gebote gehen zurück aufs Konto.
- **Alte Karten und Gutscheine** werden zu Geld: Fan-Karte 100 ₲ je Ruhmpunkt, Scout-Gutschein und offenes Scout-Angebot je 1.200 ₲ (so viel wie ein
  guter Scout heute), ungeöffnete Packs zum Kaufpreis. Offene Jam-Angebote verfallen.
- **Laufende Aktionen** rechnet das neue Spiel ab, sobald sie fertig sind. Alte Proben und Jam-Treffen werden gleich mit ihrem schon berechneten
  Können-Gewinn abgerechnet; Karten-Funde fallen weg; alte Ergebnisse erscheinen nicht noch einmal.
- **Beziehungen** aus der gemeinsamen Bandzeit: Zielwert der Passung × (1 − e^(−Tage / 7)), gezählt ab Gründung oder späterer Unterschrift
  (nach einer Woche etwa zwei Drittel, nach zwei Wochen 86 %).
- **Kartei:** Sind es mehr als 5 Musiker ohne Band, kommen sie beiseite. Beim ersten Login sucht man bis zu 5 aus, die als Solokünstler bleiben;
  die übrigen verlassen das Label (wie Entlassene, sie bleiben in WikiSignia). Ihre Instrumente liegen im Lager.
- Beim ersten Login: „Was ist neu?“ in sechs Schritten mit der Übersicht, was der Umbau gebracht hat, statt der Einführung für Neue.
- In der Testversion (Datenbank schon im Umbau) gilt das nur für Labels mit altem Kram (Karten, Packs, Börse, Musiker alter Herkunft), und zur Wahl
  stehen nur Musiker alter Herkunft; wer aus dem Posteingang oder vom Scout kam, bleibt.

### Tempo

**Keine Stufen-Bremse.** Die alte Bremse (`LEVEL_PACE`) ist ganz entfernt. Das Tempo ergibt sich aus gut abgestimmten Gewinnen, Kosten,
steigenden Schwellen (Bekanntheit 20 / 120 / 600, Können 35 / 60 / 85), dem Anspruch der Bands und der Lernkurve. Was sich aufschaukelt,
ist ein Fehler in den Formeln und wird dort behoben. Neue Inhalte bekommen eigene, gut abgestimmte Gewinne; keine Bremse und keine Ausnahme.

- **Können:** Die Bandprobe ist die Hauptquelle. Ab Können 15 (ohne Talent), in Probenzeit: Stufe 1 nach rund 2,5 Stunden, Stufe 2 nach rund 9,
  Stufe 3 nach rund 36 Stunden. Kurse: teuer, je Musiker einmal am Tag, ohne Lernkurve; ein Musiker hat etwa jeden zehnten Tag sein Instrument
  (im Schnitt +4,5, eine Stufe allein durch Kurse in rund zwei Monaten). Privatlehrer lohnen sich vor allem für Bessere (bei Können 70 sind +16
  etwa zwölf Stunden Proben).
- **Bekanntheit:** Auftritte (größere Orte bringen mehr je Lied, werden aber erst mit Ruhm erreichbar und kosten mehr), Festivals (fest je Bühne),
  Battle (fest nach Platz), Tournee (Ausland ×2), Charts (nach Punkten), Interviews (Anteil des Weges zur nächsten Stufe: ab Stufe 1 schafft man
  mit allen Anfragen eine Stufe in grob zwei bis vier Wochen).
- **Songs:** Vierer-Band ein Song je Stunde, Solokünstler achtmal langsamer; Bands sind der schnellere Weg, Solo lohnt sich nur kreativ, mit Pop/Elektro
  oder egozentrisch.
- **Große Bands** sind ein bewusster Weg nach oben: mehr Sound (+5 % je Mitglied über 2) und mehr Gage (+10 %), dafür mehr Kosten und Aufmerksamkeit.
- **Noch zu prüfen** (Durchspiel-Prüfung am Ende): dass größere Orte, Tourneen und Festivals ohne Bremse nicht davonlaufen; dass alle Stufen mit
  Einsatz erreichbar sind; wie lange ein neues Label bis Halle und Arena braucht. Die alte Rechnung „Platten und Gigs im Gleichgewicht“ stammt
  aus dem alten Spiel und wird dabei neu gemacht.

### Wer wirkt auf wen (ohne Kreisläufe)

Grundsatz: Ruhm darf nie den eigenen Zuwachs verstärken. Sonst schaukelt sich etwas auf.

- **Chartplatz** ← Chartpunkte (Qualität, Sound, Länge, perfekt) × Verlauf (−3 %/Std.) × Schub (Auftritte, Promo, Interviews). Ruhm wirkt hier nicht.
- **Chartplatz →** nur Nachrichten und die Auszeichnung „Platz 1“. Geld und Ruhm kommen aus den Punkten, nicht aus dem Platz.
- **Ruhm** ← Auftritte (je nach Ort), Platten, Charts (nach Punkten), Promo-Tour, Interviews, Battle und Festival (fest), Tournee.
- **Ruhm →** Auftrittsort (die Leiter nach oben), Erstverkauf einer Platte (Geld), Anspruch (kostet), Label-Ruhm (mehr Post, bessere Bewerber).
  Der Ort ist der einzige Ruhm-zu-Ruhm-Weg – gewollt, mit festen Stufen und steigenden Kosten.
- **Bekanntheit der Woche →** Festival-Bühne. Gedämpft, weil das Genre jede Woche wechselt.
- **Geld →** Instrumente, Kurse, Lehrer, Proberaum → Können und Sound → bessere Platten. Ein langsamer, gewollter Weg.
- **Bandgröße →** Sound und Gage; die Bekanntheit eines Auftritts wird geteilt (in großen Bands wird der Einzelne langsamer bekannt).

### Nachrichten

Wer mindestens 15 Minuten nicht im Spiel war, bekommt beim nächsten Aufruf die Neuigkeiten aus der Chronik: Charts, Aufstiege, Charakterzüge,
Battle, Festival, Tournee, Auszeichnungen (`Balance::NEWS_KINDS`).

## Musik

- Aufnahmen aus Logic Pro: je Genre und Stufe ein Ordner `public/assets/music/<genre>-<stufe>/` mit einer MP3 pro Spur
  (z. B. `gitarre-elektrisch.mp3`, `gesang.mp3`) und `noten.json` (Noten aus der MIDI-Datei).
  Alle Spuren: 120 BPM, 12 Takte = 24 Sekunden, gleiche Tonart, damit sich alles mischen lässt.
- Einlesen lokal mit `php tools/musik-import.php pop-0 datei.mid spur.wav …` (braucht ffmpeg). Lautheit je Rolle:
  Gesang −17 LUFS, Bass und Schlagzeug −19, Keyboard und Turntables −21, Bläser/Geige/Flöte −22, Gitarre −23.
- Jeder Musiker spielt die Spur seines Instruments, seiner Stufe und eines Genres: das Genre der Band, sonst eines seiner Lieblingsgenres.
  Akustisch oder elektrisch richtet sich nach seinem Instrument (`Catalog::soundsElectric`): Manche Genres verlangen es (Metal: Gitarre und Bass,
  Elektro: Keyboard, Schlagzeug und Bass immer elektrisch); ohne eigenes Instrument die Gewohnheit des Genres. Wer kein Instrument hat, bleibt stumm.
- Gesang mit Frauen- und Männerstimme (`gesang-weiblich`, `gesang-maennlich`), diverse je nach Figur; beide teilen sich die Noten der MIDI-Spur.
  In Hip-Hop wird gerappt (`Catalog::RAP_GENRES`). Vorhanden: Pop 0 komplett, Rock 0 (Schlagzeug, Bass, Keyboard), Hip-Hop 0 (Schlagzeug, Bass,
  Gesang), Elektro 0 (Schlagzeug, E-Piano, Turntables, Gesang).
- Fehlt eine Spur, nimmt das Spiel die nächste Stufe, dann die andere Variante, dann Pop. Gibt es gar keine, bleibt der Synthesizer.
- Spielen zwei Mitglieder dasselbe Instrument, nimmt der zweite die Aufnahme eines anderen Genres; übersprungen werden Aufnahmen mit (fast)
  denselben Noten (`Music::parts`) – man hört nie zweimal denselben Part, solange es einen anderen gibt.
- Zu hören bei Proben (Bandseite) und Auftritten (Live-Ansicht); dazu echter Applaus je Ortsgröße (`public/assets/sfx/`).

## Vorläufige Entscheidungen

- **Instrumente:** Keyboard und Synthesizer sind ein Instrument (Keyboard). Gitarren, Schlagzeuge und Keyboards gibt es akustisch und elektrisch.
- **Besetzung:** Jedes Mitglied spielt in der Band genau ein Instrument. Höchstens 7 Mitglieder (damit jede Genre-Kombination möglich ist;
  Metal + Jazz + Hip-Hop braucht 7).
- **Charts:** gemeinsame Album-Charts aller Labels statt einer Liste pro Genre; die Label-Rangliste zählt den Label-Ruhm.
- **Ruhm verfällt nicht** (vorerst). **Präsenz** zählt nicht (der Wert steht noch in der Datenbank).
- **Bei Auftritten spielt man nicht mit**, man schaut im Live-Ticker zu.

## Bekannte Grenzen

- Aufnahmen gibt es erst für wenige Genres und Stufen; die übrigen spielen die nächste passende mit.
- Noch keine Bremse gegen Passwort-Raten beim Login.
- Nicht getestet auf dem iPad (Berühren, Ziehen, Klang); das kommt vor dem Veröffentlichen.
