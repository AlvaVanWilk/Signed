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
| **Label-Ruhm** | aus dem Ruhm aller Bands, große zählen stärker: Wurzel aus der Summe der Quadrate (`BandCalc::labelFame`). Eine Band allein zählt mit ihrem Ruhm; 500 und 490 ergeben 700, fünfzig Bands mit je 10 nur 71, neue Unbekannte ziehen nichts herunter. Bestimmt, wie viel Post kommt; steht auf der Übersicht und in der WikiSignia |

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
- **Nur mit Manager** (gebaut): Ohne Manager sieht man die Beziehungen und den Zusammenhalt nicht. Nur großer Streit (−7 oder schlechter,
  `Balance::MANAGER_STAFF['notes']['fight']`) ist laut: Er steht dann als Kreis auf der Bandseite und auf der Übersicht („… haben richtig Streit.“).

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
| Instrument | (gebaut) keins: −6; eins unter dem eigenen Können: −3 je Stufe Abstand; eins, das passt: +2; Diven nehmen es doppelt (`Balance::MOOD['gear']`) |
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
Wünsche ergeben sich aus der Lage: **Wer kein Instrument hat, wünscht sich eins; wer mit besserem Können sein Instrument überholt hat, wünscht sich ein besseres** (gebaut: „Ich will ein besseres Instrument.“).
Je länger ein Wunsch offen bleibt, desto mehr drückt er das Wohlbefinden.

### Charakterzüge: Stärke und Schwäche (gebaut)

Jeder Zug hat eine Stärke und eine Schwäche; welcher hilft, hängt von Band und Anordnungen ab. Namen sind Eigenschaftswörter (Menschen sind nicht ihre Eigenschaft);
die gespeicherten Schlüssel bleiben die alten. Zahlen: `Balance::TRAITS`, Ausdauer beim Proben `Catalog::LUST`, Texte `Catalog::PERSONALITY_INFO`.

| Zug (Schlüssel) | Stärke | Schwäche |
|---|---|---|
| Ehrgeizig (ehrgeizig) | lange Proben (×1,5) | −6 Wohlbefinden-Ziel, wenn 48 h keine neue Platte |
| Gemütlich (gelassen) | sinkt langsamer (×1,8), Songstau zählt halb, kleine Bühnen ärgern nicht | kurze Proben, mag keine Auftritte |
| Perfektionistisch (perfektionist) | lange Proben, Platte +0,04 Qualität | schreibt langsamer (×0,6) |
| Impulsiv (chaot) | schreibt schneller (×1,6), live +4 % Glanz | kurze Proben, live +4 % Patzer |
| Extrovertiert (rampensau) | Ruhm-Anteil bei Auftritten +0,2 | −6, wenn 8 h kein Auftritt; zwei reiben sich; solo unglücklich |
| Egozentrisch (diva) | Erstverkauf +10 %, solo glücklich | will gute Bühnen; zwei reiben sich |
| Kooperativ (teamplayer) | bessere Chemie | leidet mit Unzufriedenen ×1,5 mit, solo sehr unglücklich |
| Ausgeglichen (ruhepol) | halbiert Reibung | live −3 % Glanz |
| Gesellig (partytier) | Ruhm-Anteil bei Auftritten +0,15, zwei verstehen sich | −5, wenn 8 h kein Auftritt; kurze Proben |
| Kreativ (kreativ) | schreibt viel schneller (×3) | kurze Proben (×0,7), lernt beim Proben langsamer (×0,85) |
| Technisch (technisch) | übt wie verrückt (×1,6), lernt schneller (×1,25), live −3 % Patzer | schreibt keine Songs |

Kreativ und Technisch schließen sich aus. Frühere Fähigkeiten „Songwriter“ und „Interpret“ werden beim Lesen zu Kreativ und Technisch (`Repo::legacyTraits`).

**Versteckter Wert** (`Balance::VALUE`, nie angezeigt): Züge meist 0, Ehrgeizig, Kooperativ, Ausgeglichen, Kreativ +1, Egozentrisch und Technisch −1.
Die Unterschriftsprämie wächst um 12 % je Punkt (60 % bis 160 %): Glückstreffer kosten mehr, Pechvögel sind billig.

**Spieltechnik** (gebaut): Jeder Musiker hat genau eine, von Anfang an – man hört sie in der Einsendung (später eigene Spuren je Technik).
84 Techniken (`Catalog::ABILITIES`), jede gehört zu einem Instrument und zwei Genres; je Instrument und Genre gibt es mindestens drei.
Gelost wird aus denen, die zu Instrument und gemochten Genres passen (mag er nur untypische Genres: aus allen des Instruments).
Im passenden Genre der Band (bei Hybrid und Crossover alle ihre Genres, solo die eigenen) bringt sie +4 % Sound (`Balance::TECHNIQUE_SOUND`), live etwas mehr Glanz.
**Fähigkeiten** (Talent, Charisma, Nervenstark, Absolutes Gehör, Entertainer, Studiofuchs, Ausstrahlung) gibt es nicht mehr; stattdessen schaltet jede
Können-Stufe eine bessere Klasse von Instrumenten frei (Zahlen in docs/kern.md, Instrumente).

**Verborgene Charakterzüge und Bedürfnisse** (gebaut, `Traits`, `Managers`): Mit dem Label redet niemand persönlich. Wer neu ins Label
kommt (Einsendung, Pack, Jam, Scout …), zeigt seine Züge nicht; unbekannte stehen als „?“ da, auch schon in der Einsendung. Gewirkt wird
trotzdem von Anfang an. **Ohne Manager** sieht man nur die Stimmung (die Farbe); „Warum?“ verrät nichts, und Wünsche hört man keine.
Erst wer **rot** ist, meckert laut: Er sagt konkret, was er will (steht bei den Wünschen und auf der Übersicht; eine Anordnung, die er
ablehnt, nennt ihn), und man erfährt den Zug hinter seinem Wunsch („… sagt laut, was fehlt. Jetzt weißt du: Lea ist gesellig.“).
**Mit Manager** sieht das Label alle Wünsche, die Gründe der Stimmung („Warum?“) und seine Notizen. Die Züge merkt der Manager, sobald einer
zum ersten Mal etwas bewirkt, und gibt es weiter („Mira erzählt: Lea hatte richtig Lust auf den Auftritt. Jetzt weißt du: …“):
- eine Anordnung gemocht oder nicht gemocht (Probe, Auftritt, Aufnahme, Straßenmusik, offene Bühne, Interview),
- Unruhe ohne Auftritt (extrovertiert, gesellig) oder ohne neue Platte (ehrgeizig), Ruhe beim Songstau (gemütlich),
- ein neuer Song (kreativ; technisch in einer Band, weil er nicht mitschreibt), der Wunsch nach einem Künstlernamen,
- unzufrieden (schon bei Gelb; der erste Zug, nach dem sich auch der Wunsch richtet), bei der Probe als Erster die Lust verloren,
- nach jeder Probe (ab einer Minute) mit 35 % noch ein Zug.
Nach einem Tag mit dem Manager kennt er alle (`Balance::TRAIT_REVEAL`, `bands.manager_since`). Was bekannt ist, bleibt bekannt, auch wenn er
geht. Vorlieben an den Anordnungen zeigen sich nur für bekannte Züge. Wer schon vor dem Umbau da war, ist bekannt.

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
- **Jedes Mitglied trägt bei** (`Balance::SONGWRITING`): je Mitglied 0,25 mal dem Faktor seines Charakters (Kreativ ×3, Impulsiv ×1,6, Perfektionistisch ×0,6,
  Technisch 0); Tempo = Summe hoch 1,5. Zusammen sprudeln die Ideen, deshalb wächst es stärker als die Zahl der Mitglieder.
  Vierer-Band ohne solche Züge: ×1 (das Tempo oben), Zweier-Band ×0,35, allein ×0,125 (ein Song in acht Stunden), allein und kreativ ×0,65,
  Vierer-Band mit einem Kreativen ×1,84; allein und technisch: gar keine Songs.
  Gegenstück **Interpret** (schreibt nicht) (12 % der neuen Musiker, nur von Anfang an, nie lernbar): trägt 0 bei. Solo entstehen so keine Songs, in einer Vierer-Band sinkt das Tempo auf ×0,65.
- **Obergrenze 24 Songs.** Ab 12 liegenden Songs sinkt die Stimmung der Band langsam („Wir wollen aufnehmen!“).
- Eine Platte braucht mindestens 3 Songs (EP) bzw. 7 (LP) aus diesem Vorrat; beim Aufnehmen wählt das Label, wie viele.

## Anordnungen des Labels

| Anordnung | Wirkung | Bedingungen |
|---|---|---|
| **Proben** | **Hauptquelle für Können** und Beziehung; Wohlbefinden je nach Charakter | Band nicht rot, nicht beschäftigt, alle haben ein Instrument |
| **Aufnehmen** | Songs → Platte. Label wählt **Studio** und **Produzent** (verschiedene Preise); Qualität = Sound × Produktion | Songs vorhanden, Band nicht rot; Produktion unter dem Anspruch ärgert |
| **Auftritt buchen** | Gage, Ruhm, Bekanntheit; Ort mit festen Kosten und Gewinn-Schätzung | Band nicht rot; Ort unter der Würde ärgert |
| **Interview/Promo-Anfrage** | Bekanntheit und Ruhm | Eingang von Anfragen, passend zur Bekanntheitsstufe |
| **Einkaufen** | Instrumente, Kurse, Proberaum, Auszeit … (siehe unten) | Geld; Kurse brauchen einen freien Musiker |
| **Manager einsetzen** | siehe unten | einmalig bezahlt |
| **Cover wählen** | aus Entwürfen für die Platte | bei der Aufnahme |

Rot sperrt alles, was die Band nicht „brennend“ will; erlaubt bleiben die Anordnungen, die helfen (Pause, Wunsch erfüllen, etwas Besseres).

### Proben (Hauptquelle für Können)

Die alte Probe war „Wasser auf den heißen Stein“. Jetzt ist sie der Weg nach oben, **ohne Stufen-Bremse**: Die Lernkurve `(1 − Können/110)^2,5`
bremst von selbst. Gebaut (Testversion): Knopf „Proben“ direkt auf der Bandseite, ohne Unterseite, mit Nachfrage. Die Probe ist **offen**: Die Band probt, bis ein Mitglied
keine Lust mehr hat (`Balance::REHEARSAL['lust']` = 60 Minuten Grundwert, nach Charakter `Catalog::LUST`: Ehrgeizige und Perfektionisten halten länger, Chaoten und Partytiere kürzer)
oder bis jemand, der Proben nicht mag, zu unzufrieden wird. Alles wird **laufend** gutgeschrieben (Können mit Stufenaufstieg im selben Moment, Zusammenspiel, Beziehungen, Wohlbefinden), „Probe beenden“ behält den bisherigen Fortschritt.
Gewinne sind je 15 Minuten angegeben (Bezugszeit). Ziel für Startwerte (Spielzeit, ca. 15 Minuten je Probe; gebaut sind etwa 2,5 / 9 / 36 Stunden, siehe `kern.md`):
Stufe 1 in etwa einer Stunde, Stufe 2 in etwa 15 Stunden, Stufe 3 in etwa 80 Stunden. Je Musiker verschieden (Talent; Chaot/Ehrgeizig wirken
auf Stimmung statt auf Können). Ein bisschen mehr Können gewinnt, wer unter Besseren spielt.

### Aufnehmen: Studio und Produzent (gebaut)

Zahlen in docs/kern.md (Platte). Vier Studios, vier feste Produzenten (immer dieselben Personen, gezeichnet in `producers.js`):
Mo Beats (Nachwuchs), Roxy Reverb (etabliert), Diamond Dee (Hitproduzent, viel Gold) und Ruben Stille (die Legende: barfuß, langer Bart,
rote Sonnenbrille). Ohne Produzent nur die Garage; jeder Produzent bringt die Band ein Studio weiter. Keine Minispiele; während der
Aufnahme zeigt die Bandseite das Studio (`studio.js`) mit dem Produzenten hinter der Scheibe. Cover: drei Entwürfe, nur Optik.

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
- **Applaus** (gebaut, `audio.js`): Nach dem Auftritt klatscht eine echte Aufnahme je nach Ort (`public/assets/sfx/`, frei verwendbare
  Aufnahmen, mit ffmpeg gekürzt, ausgeblendet und gleich laut gemacht): wenig Publikum (Kneipe, Straßenmusik, Offene Bühne), Club (auch
  Konzertsaal), große Halle (auch Arena, von 26 auf 11 Sekunden gekürzt). Je begeisterter, desto lauter und länger; wer kaum jemanden
  begeistert, hört auch in der Halle nur ein paar Hände. Lädt die Aufnahme nicht, klatscht wie früher der Synthesizer.

### Schwarzes Brett (gebaut)

Eigener Menüpunkt für alles, was **für alle Spieler gleich** ist: heute die Kurse des Tages (mit „hinschicken“), dazu die Kurse der nächsten
sechs Tage. Später kommen hier **Veranstaltungen** hin (Festivals, Battle of the Bands), zu denen man sich anmeldet; sie landen nicht im Posteingang.

### Promo-Tour (gebaut)

Die einzelne Promo-Mission ist weg (dafür gibt es Interview-Anfragen). Die **Promo-Tour** der ganzen Band (oder des Solokünstlers) ist eine
Anordnung auf der Bandseite, jederzeit ohne Anfrage, mit Nachfrage wie bei Proben und Auszeit (Dauer, was es bringt, wer es mag): 10 Minuten, etwas Ruhm, schiebt die Platte in den Charts an und zählt für die nächste Platte.

### Interviews und Promo-Anfragen (gebaut als Interview-Anfragen im Posteingang)

- Anfragen kommen als **Mitteilung** (Zeitschrift fragt nach Interview mit X). Annehmen schickt den Musiker für kurze Zeit weg.
- **Reichweite der Anfrage** hängt an der **Bekanntheitsstufe** des Musikers: Stufe 0 bekommt Schülerzeitung und Stadtblog,
  Stufe 1 Stadtmagazin, Stufe 2 Musikzeitschrift und Radio, Stufe 3 große Magazine und Fernsehen. Zufall gibt es nur darin, welches Medium.
- **Gewinn = Anteil des Weges zur nächsten Stufe** (zum Beispiel 10–15 %). So ist jede Stufe gleich gut erreichbar, ohne Bremse; für Bekannte
  bringt ein kleines Blatt nichts, weil die Anfragen ihrer Stufe entsprechen.

### Einkaufen

Aus den „Läden“ wird **Einkaufen**, mit Reitern nach Art. **Der Shop ist für alle Spieler gleich**: Das Angebot wechselt täglich (aus dem Datum berechnet,
nicht pro Spieler gewürfelt), wie bei den Tageskursen. Man sieht vorher Preis und Werte und entscheidet gezielt. Das „Zufällige“ ist nur,
**was heute im Regal steht** (Art und Güte), nicht, was man bekommt. Es gibt nur **einmalige Ausgaben**, keine laufenden Kosten.

| Reiter | Was | Wirkung | Wann |
|---|---|---|---|
| **Instrumente** | gezielt für einen Musiker, in verschiedener Güte | Klang, Sound, Wohlbefinden (Wünsche), nötig zum Proben | von Anfang an |
| **Kurse** | einzelne Musiker fördern; mehrere Kurse, aus denen man wählt (Instrument, Dauer, Preis), täglich gleich für alle (gebaut: einer am Tag, `Balance::COURSE['perDay']`; die Woche steht auf dem Schwarzen Brett) | Können (ohne Bremse), Wohlbefinden je Charakter (Ehrgeizige mögen Kurse) | von Anfang an |
| **Proberaum** | Ausstattung **einer Band**, in Stufen | Proben bringen mehr Können und stärken die Beziehung | früh, teure Stufen später |
| **Auszeit** | Wochenende, Urlaub, Ausflug für **eine Band** | hebt die Stimmung (Gelassene und Ruhepole freuen sich besonders), Band ist dabei für eine Zeit nicht verfügbar | mittel; Preis steigt mit der Bandgröße. **Gebaut** als Anordnung auf der Bandseite: Ausflug 1 Std. (+8, 20 ₲ je Mitglied), Wochenende 4 Std. (+16, 60 ₲), Urlaub 12 Std. (+28, 150 ₲); Gelassene und Ruhepole ×1,5, Ehrgeizige ×0,5; keine Songs in der Zeit, die Beziehungen wachsen etwas; geht auch bei Rot (`Balance::RETREAT`) |
| **Bühnentechnik** | Licht, Ton, Show, Stufen | größere Orte füllen sich leichter (Erwartung sinkt), Diva und Rampensau freuen sich | mit größeren Bühnen |
| **Fahrzeuge** | **kleiner Tourbus**, **großer Tourbus**, **Jet**, jeweils in verschiedener Güte | Komfort auf Tour (Stimmung), später Voraussetzung für Tourneen mit mehreren Auftritten | **nur im späten Spiel**, erst mit hohem Label-Erfolg freigeschaltet |
| **Stellenbörse** | Manager und Assistenz, täglich 1–3, teuer | siehe Manager und Einsendungen | früh |

**Was ich noch gut fände** (für später, nicht alles auf einmal): ein **Songschreiber/Texter** für eine Band (die Band schreibt schneller Songs),
ein **Medienpaket** (bessere Interview-Anfragen), **Teambuilding** (Beziehungen wachsen schneller), **Outfits/Styling** (Bekanntheit der Mitglieder wächst schneller).
Jede neue Art braucht einen eigenen Zweck und Wunsch (Perfektionist → Proberaum, Diva → Bühnentechnik und Fahrzeug, Gelassen → Auszeit), sonst ist sie nur Beiwerk.

**Warnung zur Größe:** Mehr Reiter bedeuten mehr Klicken. Ich würde mit **Instrumente, Kurse, Proberaum, Auszeit und Stellenbörse** beginnen und
**Bühnentechnik und Fahrzeuge zusammen mit Tourneen** nachlegen. Eine Stufen-Bremse gibt es nicht (mehr); das Tempo ergibt sich aus den Gewinnen selbst (`kern.md`, Tempo).

### Woher neue Musiker kommen

**Posteingang** (gebaut, ersetzt die Seite „Einsendungen“): Drei Arten Post kommen an: **Demos** (Kassetten, wie unten), **Anfragen**
(Interviews für die eigenen Musiker, `Inbox`) und **Bewerbungen** (Leute fürs Label). **Alles kommt in Umschlägen** (`Post`): Man sieht nicht,
was drin ist, und muss erst auspacken (2 Sekunden, einer nach dem anderen; die Leiste ist so lang wie beim Kassettenhören). Alles liegt
**vier Tage** (`Balance::POST['ttl']`, auch Interviews und Bewerbungen), lässt sich merken und ungeöffnet wegwerfen. Post kommt **zufällig
verteilt** (man weiß nie genau, wann); am Menüpunkt steht **+N**, was seit dem letzten Blick in den Posteingang neu ist, und im Posteingang trägt
alles Neue ein **NEU** (bis man das nächste Mal hereinkommt). Alles landet im **Eingang**; in die Fächer **Demos, Anfragen, Bewerbungen** kommt es,
wenn jemand sortiert. Alles liegt in **einer Reihe**: Neues vorn (neueste zuerst), danach die eigene Anordnung (`labels.sub_order`, Kassetten
mit ihrer Nummer, Briefe mit minus ihrer Nummer). Ein ausgepackter Umschlag behält seinen Platz und seine Schräglage, sein Inhalt liegt also genau dort,
wo der Umschlag lag. Auch Briefe und Umschläge lassen sich in der Reihe verschieben.
**Von Hand** lässt sich alles festhalten und auf einen Reiter ziehen (auch zurück in den Eingang oder zu „Gespeichert“);
das überstimmt die Sortierung (`Post::move`, Spalte `box`).
- **Wie viel kommt**: Alles kommt zufällig, im Schnitt so viel wie die Rate je Tag. Jede Rate ist eine fließende Formel ohne Stufen und
  ohne Obergrenze: **Start + Zuwachs × √Label-Ruhm** (siehe oben, wie er sich berechnet; steht auf der Übersicht). Steigt er, gilt
  die neue Rate sofort (die Wartezeit bis zur nächsten Ankunft wird dann neu gewürfelt, `Post::nextArrival`).

  | je Tag            | Start | Zuwachs | bei Label-Ruhm 100 | 1.000 | 10.000 |
  |-------------------|------:|--------:|------:|------:|-------:|
  | Kassetten (Demos) |     8 |     1,8 |    26 |    65 |    188 |
  | Praktikum         |   0,8 |   0,033 |   1,1 |   1,8 |    4,1 |
  | Manager           |   0,3 |   0,019 |   0,5 |   0,9 |    2,2 |
  | Scout             |   0,2 |   0,014 |   0,3 |   0,6 |    1,6 |
  | Assistenz         |  0,12 |   0,011 |  0,23 |  0,47 |    1,2 |
  | Lehrkraft         |  0,08 |    0,01 |  0,18 |  0,40 |    1,1 |
  | Coach             |  0,05 |   0,009 |  0,14 |  0,33 |    0,95 |

  Zum Vergleich: Eine Band im Club hat etwa 30–150 Ruhm, im Konzertsaal 150–600, in der Halle 600–2.500, in der Arena mehr.
  **Interview-Anfragen** richten sich nur nach dem einzelnen Musiker: je Tag 0,1 + 0,05 × √Bekanntheit (unbekannt: alle zehn Tage eine;
  Bekanntheit 120: etwa 0,65; 600: etwa 1,3; 2.000: etwa 2,3). Offen sind für ihn höchstens 1 + Bekanntheitsstufe zugleich, jede von einem
  anderen Medium seiner Stufe (Unbekannte: Schülerzeitung, Blog; Bekannte: Musikzeitschriften, Sender; Stars: Fernsehshows). Wie viele andere
  Bands das Label hat, spielt keine Rolle. Ein Praktikum liegt immer da; ganz zu Beginn bewerben sich dazu ein Scout und ein Manager. Wie gut
  die Bewerber sind, folgt dem Label-Ruhm gesättigt: Güte = Ruhm / (Ruhm + 300) (`Balance::JOBS`, `Balance::SUBMISSIONS['perDay']`, `Balance::INTERVIEW`).
- **Praktikum** (gebaut): 50 ₲, 7 Tage, nur ein Vorname, sieht jünger aus (kleinerer Kopf). Immer nur **eins zur gleichen Zeit** (solange eins da ist oder auf eine Antwort wartet, ist „Einstellen“ bei weiteren Praktikums-Bewerbungen ausgegraut, mit Grund). Es arbeitet
  **nur auf Zuruf** und immer nur eine Sache: **Briefe öffnen**, **Demos anhören** (ohne wegzuwerfen) oder **Post sortieren** (was im Eingang
  liegt, kommt ins passende Fach). Es macht das für alles, was in dem Moment da liegt, **das Neueste zuerst**; was danach kommt, bleibt liegen.
  Man kann es jederzeit **abbrechen**: Fertiges bleibt fertig, der Rest liegt wieder da wie vorher. Es ist nicht schneller als man selbst,
  aber man kann derweil etwas anderes tun; auch offline. Ist es fertig, kommt eine Meldung.
  **Übernehmen geht erst nach den 7 Tagen:** Dann hört es auf zu arbeiten (ein laufender Auftrag endet, der Rest liegt wieder da), meldet sich
  einmal („möchte gern bleiben“) und wartet einen Tag (`Balance::INTERN['decide']`) auf eine Antwort: für 400 ₲ als Assistenz **übernehmen**
  (bekommt einen Nachnamen und sieht erwachsen aus) oder **verabschieden** (dann kann ein neues Praktikum kommen). Ohne Antwort geht es.
- **Assistenz** (gebaut, `Assistant`): einmalig bezahlt (400 ₲). Jede hat **ein Arbeitsgebiet** (jederzeit umstellbar, neue fangen ohne an) und
  arbeitet darin **laufend von selbst**, aber **Stück für Stück und so schnell wie man selbst** (Umschlag 2 s, einsortieren 1 s, Kassette 5 s,
  Brief lesen 5 s; `Balance::ASSISTANT`), das Älteste zuerst, auch offline (höchstens vier Tage). Nichts passiert auf einen Schlag. Gleichwertig
  sind die Gebiete über den Nutzen, nicht über die Menge (Kassetten sind die meiste Arbeit):
  - **Post**: packt Umschläge aus und legt alles in den richtigen Reiter (Demos, Anfragen, Bewerbungen); was man von Hand in den Eingang
    zurücklegt, bleibt dort. Schreibt auf die Übersicht, was in den nächsten 12 Stunden abläuft.
  - **Kassetten**: hört ausgepackte Kassetten an (Umschläge packt sie nicht aus: ohne Post-Assistenz wartet sie, „Ich warte auf ausgepackte
    Kassetten.“) und legt sie in **Genre-Reiter**; selbst Angehörtes legt sie dort auch hinein (1 s). Wirft nach Regel weg (Standard: nichts).
    **Suchauftrag** („Suchen …“: Instrument, Genre, ab Stufe; bei Bands reicht ein Mitglied): Passt eine Kassette, merkt sie sie (dann wirft sie
    niemand weg), markiert sie „gefunden“ und meldet sich in der Chronik und auf der Übersicht.
  - **Büro**: liest Bewerbungen, Interview-Anfragen und Scout-Berichte und erzählt auf der Übersicht davon (Lehrkräfte einzeln, mit wem es
    passt). Auf Wunsch („Interviews selbst zusagen“) sagt sie gelesene Interview-Anfragen zu, sobald der Musiker frei ist.
  Mehrere im selben Gebiet teilen sich die Arbeit. Alte Aufgaben: „Presse“ wurde Büro, „Kassetten anhören“ Kassetten.
  **Wegwerfen ist eine Wahl** („Wegwerfen …“ in der Sprechblase): Standard „Nichts wegwerfen“; nur wer „Unpassendes gleich wegwerfen“ wählt,
  stellt Stufe, Genres und Einzeln/Band ein. Es betrifft nur, was sie neu anhört; Angehörtes und Gemerktes bleibt. Die Sprechblase sagt, was gilt.
  **Genre-Reiter**: Mit einer Kassetten-Assistenz hat das Fach „Demos“ Unterreiter: Alle, Ungehört und je Genre (nur, was sie angehört und
  einsortiert hat; wer mehrere Genres hat, liegt in mehreren). In jedem lässt sich sortieren (eigene Reihenfolge, im Genre-Reiter nach den
  übrigen Genres gruppiert; Neueste, Stufe, Können). Solange ein Unterreiter oder eine Sortierung gilt, lässt sich nur auf Reiter ziehen.
  Ohne Kassetten-Assistenz gibt es dort den Filter nach Stufen, der Knopf „Genre-Reiter und Sortieren“ ist grau, mit Grund.
- **Poststelle** (gebaut, `office.js`): Oben im Posteingang eine Szene statt einer Kachel: Wand mit Fenster, Postfächern, Uhr und Lampe, vorn
  der Schreibtisch mit Namensschildern; Praktikum und Assistenzen stehen als Figuren dahinter. Ein Praktikum ohne Auftrag sagt „Ich habe
  nichts zu tun.“, eine Assistenz ohne Arbeitsgebiet „Ich habe keine feste Aufgabe.“ (beide schauen gelangweilt); beim Praktikum steht während der Arbeit die Restzeit über dem Kopf, und es hält
  Brief, Kassette oder Stapel in der Hand; wer Demos hört (Praktikum oder Assistenz), trägt dabei Kopfhörer. Eine Assistenz mit Arbeitsgebiet hält,
  woran sie gerade sitzt (Umschlag, Stapel, Kassette, Brief) und sagt nichts; gibt es gerade nichts dafür, trinkt sie Kaffee. Auf dem Namensschild
  steht ihr Gebiet. Ihre Sprechblase ist breiter, öffnet rechts nach links und steht auf dem Handy als Zettel unten am Bildschirm.
  Im Posteingang fragt das Spiel alle paar Sekunden nach, damit man sie arbeiten sieht (anderswo nicht).
  Antippen öffnet die Sprechblase mit Zurufen, Arbeitsgebiet, Regeln und Abbrechen; die Restzeit des Praktikums steht auf einem Zettel am Tisch.
  Ist das Praktikum vorbei, fragt es „Darf ich bleiben?“; in der Sprechblase stehen dann „Übernehmen“ und „Verabschieden“.
- **Scout-Karte** (gebaut): Der eingestellte Scout steht unter der Künstlerleiste: losschicken, Countdown, danach „Ansehen“ für den Bericht.
- Die **Sprechblasen** mit Wünschen auf den Band-Kacheln sind vorerst geparkt.
- **Interviews** siehe „Interviews und Promo-Anfragen“; Zahlen in `kern.md` (Promo, Tempo).
- **Scout** (gebaut, `Scouting`): Ein einfacher Scout bewirbt sich gleich zu Beginn (400 ₲); bessere kommen mit dem Erfolg
  (`Balance::SCOUT_STAFF`). Losschicken mit Genre und/oder Instrument („Egal“ geht), dann liegt nach 6 / 5 / 4 Stunden ein **Bericht** im
  Posteingang (bei Assistenz unter „Demos“) mit 2 / 3 / 3 Talenten, Können 15–34 / 22–44 / 32–54, alle unbekannt. Im Bericht blättert man mit
  Pfeilen durch die Talente (eins nach dem anderen) und unterschreibt beliebig viele, jedes für seine Prämie. Der Bericht liegt 2 Tage.
  Die alten Scout-Gutscheine (Kartenauswahl) sind nicht mehr im Menü.
- **Privatunterricht** (gebaut, `Tutors`): Lehrkräfte bewerben sich für ein Instrument (meist eins, das im Label gespielt wird), manchmal mit
  Lieblingsgenre. Gebucht wird für **2 Tage und genau einen Musiker** (einmal bezahlt: 300 / 900 / 2.500 ₲), danach geht die Lehrkraft wieder.
  In der Zeit wächst das Können gleichmäßig um +8 / +12 / +16, **ohne Stufen-Bremse** (mit passendem Genre ×1,3); unterrichtet wird nur bis
  Können 55 / 80 / 100. Der Unterricht läuft **nebenbei**: Der Musiker bleibt frei für Proben, Auftritte und Aufnahmen. Ein Musiker hat höchstens eine Lehrkraft zugleich.
  Auf jeder Bewerbung steht, ob jemand **fest** bleibt (Assistenz, Scout, Manager) oder nur **für eine Zeit** kommt (Lehrkraft, Coach). So kostet jeder Schüler einzeln, und der Gewinn lohnt sich vor allem
  für Bessere, bei denen die Proben langsam werden (`Balance::TEACHER`).
- **Songwriting-Coach** (gebaut): gebucht für **1 Tag und eine Band** (oder einen Solokünstler; 400 / 1.200 / 3.000 ₲). In der Zeit entstehen
  Songs 1,5- / 1,75- / 2-mal so schnell, und die Songs aus dieser Zeit machen die nächste Platte besser (+4 / +8 / +12 % Qualität, anteilig nach
  gecoachten Songs; `bands.coached_songs`). Eine Band hat höchstens einen Coach zugleich (`Balance::COACH`).
- **Manager** (gebaut, `Managers`): Ohne ihn erfährt man fast nichts über seine Leute (siehe oben). Einer bewirbt sich gleich zu Beginn, dann
  kommen sie häufiger als alle außer Praktika. Einmalig bezahlt, die einfachen sind **günstig** (300 / 3.000 / 10.000 ₲, Stärke 1 / 3 / 5).
  Ein Manager betreut **eine Band oder bis zu vier Solokünstler** (`Balance::MANAGER_STAFF['solos']`), dafür mit voller Stärke.
  Er schreibt **Notizen auf die Übersicht**: Wünsche seiner Leute (rot, gelb, sonst), wer besser spielt, als sein Instrument hergibt, wer sich
  nicht versteht (Beziehung ab −3), wer seit über einem Tag nicht auf der Bühne stand, wer bereit fürs Studio ist.
  Jeder hat ein bis zwei **Schwerpunkt-Genres** (oft eins, das im Label gespielt wird), einen **Stil** (Charakterzug) und ein bis zwei Wirkungen
  als Zugabe: **Verhandler** (Gage), **PR-Profi** (Ruhm bei Auftritten), **Netzwerker** (Interviews bringen +10 % je Stärke), **Vermittler** (was
  der Band nicht gefällt, ärgert sie 15 % je Stärke weniger, höchstens 80 %). „Gespür“ gibt es nicht mehr, weil jeder Manager seine Leute kennenlernt.
  **Passung** zur Band = Faktor auf die Stärke: gemeinsame Genres 0 / 1 / 2 → ×0,6 / ×1 / ×1,25, dazu +0,2 × Anteil der Mitglieder mit seinem
  Stil (widersprüchlicher Zug zählt minus), zwischen 0,4 und 1,45. Eingesetzt wird er auf der Bandseite („Manager wählen“); die Bewerbung zeigt
  vorher, zu welchen Bands er passt.

In der Wirklichkeit schicken Künstler Demos an Labels, und Talentsucher hören sich um. Ein Label sucht nicht aus einem Katalog aller Menschen aus. Zwei Wege, die nichts miteinander zu tun haben:

**1. Einsendungen** kommen von selbst ins Haus.
- Jeden Tag kommen **Einsendungen** an, **für jedes Label eine eigene Auswahl** (ein Künstler bewirbt sich nur bei einem Label). Sie liegen als **neutrale Umschläge/Tapes** im Briefkasten.
  Außen steht **nichts** (weder Solo noch Band). Gebaut (Testversion) als **Kassette**: Antippen hört sie gleich an, ein kleines × wirft sie ungehört weg (früher: zwei Wahlmöglichkeiten, **Sichten** (5 Sekunden, serverseitig, `Balance::SUBMISSIONS['listen']`), danach wird aus der Kassette an derselben Stelle eine Visitenkarte in fast gleicher Größe und Schräglage) oder **Wegwerfen**; der Server verrät vorher nichts. Einzelne Musiker und Bands; Assistenz und Scout fehlen noch. Anklicken **öffnet** („entblößen“, etwa 5 Sekunden) und zeigt, was drin ist: meist **Unnützes** (sehr schwach, kein erkennbares Genre),
  manchmal **Gutes**. Das Öffnen hat etwas von einem Pack, soll aber **Arbeit** sein.
- **Laufend statt einmal am Tag:** Die Einsendungen **trudeln über den Tag verteilt ein** (Zufallszeiten, nicht alle auf einmal); bei einem kleinen Label etwa alle drei Stunden eine, bei einem großen alle paar Minuten.
  Wer wartet, bekommt mehr zu tun, und offline sammeln sich Einsendungen bis zum Verfallsdatum.
- **Keine Mengenbegrenzung** im Briefkasten; nur ein Verfallsdatum (etwa 4 Tage), das **auf jeder Einsendung steht** („läuft ab in …“). Wegwerfen kann man jede Einsendung jederzeit. Am Anfang kommen **wenige** (grob 8 pro Tag), mit wachsendem **Label-Ruhm** immer mehr
  (8 + 1,8 × √Label-Ruhm: 26 bei 100, 65 bei 1.000, 188 bei 10.000) und ein größerer Anteil Guter (von etwa 5 % auf höchstens 25 %). Die schlechten hören **nie auf**, sich zu bewerben:
  Große Labels haben viel Arbeit, können sich aber am ehesten eine Assistenz leisten.
- **Genres folgen dem Erfolg:** Hat eine Band des Labels Erfolg, bewerben sich vermehrt Musiker mit **gleichen oder ähnlichen Genres**, fremde Genres seltener (außer ein Musiker hat mehrere Genres,
  dann kann auch ein fremdes dabei sein).
- **Aufnehmen kostet eine einmalige Unterschriftsprämie**, die alle Gagen des Künstlers zusammenfasst, fürs ganze Leben: groß = teurer als klein, bekannt = teurer als unbekannt.
  Grundform je Musiker: `40 + 0,05 × Können² + 1,5 × Bekanntheit` (Können 50 ≈ 165 ₲, Können 80 ≈ 360 ₲). Ablehnen ist kostenlos. Von einer **Band** kann man alle aufnehmen oder **einzelne Mitglieder**:
  Auf der Karte öffnet jedes Gesicht die Detailansicht wie bei Einzelnen; gewählt wird erst im Fenster „Unterschreiben“ (alle vorgewählt).
  Wer nicht mitgenommen wird, **zieht enttäuscht weiter** (die Einsendung ist danach weg), und wer kommt, ist **gekränkt**: Wohlbefinden startet um 20 niedriger
  (Teamplayer ×1,6, Ehrgeizige ×0,5; `Balance::SUBMISSIONS['splitHurt']`) und erholt sich danach wie sonst. Das Fenster sagt das vor dem Unterschreiben.
  Mitglieder einer Band **kennen sich schon**, ihre Beziehung startet nicht bei 0.
- **Entlassen** bringt **20 %** der **bezahlten** Prämie zurück (nicht des aktuellen Werts, sonst könnte man trainieren und mit Gewinn entlassen).

**Assistenz** (in der Wirklichkeit „A&R-Assistenz“; in Signed *Assistenz*). Wird in der **Stellenbörse** wie ein Manager **einmalig bezahlt**.
- Jede Assistenz bearbeitet **täglich eine feste Zahl Einsendungen** (nach Wertigkeit): Praktikum 20, Assistenz 60, Senior 150, Leitung 400 (Startwerte). Mehr Eingang heißt **mehr oder bessere Assistenz**; mehrere lassen sich anstellen.
- **Bearbeiten heißt:** öffnen, und je nach Stufe nach **Regeln** aussortieren. Die Regeln stellt man ein: zum Beispiel nur bestimmte **Genres**, ab **Können-Stufe**, nur Solo oder nur Bands, nur Gutes.
  Wertlosere Assistenzen können nur öffnen; **die Sortier-Optionen schalten erst höhere Stufen frei**. So liegt im Briefkasten nur, was man brauchen kann.
- Ohne Assistenz sortiert man selbst: Umschlag für Umschlag öffnen. Es gibt **keine Sammelknöpfe** wie „Alles unter Stufe X wegwerfen“ ohne Assistenz; sie sind ein Teil dessen, was eine Assistenz mitbringt.

**2. Der Scout** ist eine **Aktion**; mit den Einsendungen hat er nichts zu tun.
- **Ohne Scout gibt es sie nicht.** Den Scout stellt man **einmalig bezahlt** ein, einen einfachen schon früh in der **Stellenbörse**. Erst dann lässt sich die Aktion nutzen.
- Man **schickt ihn los** (mit einem Suchauftrag: Genre und/oder Instrument oder „egal“). Er ist eine **Zeit weg** (läuft auch offline) und stellt bei der Rückkehr **zwei bis drei Künstler vor**, die er für besonders
  talentiert oder passend hält. Es gibt kein „Öffnen“: Man sieht sie **als ganze Person** (Figur, Charakter, Genres, Instrumente, Können, Prämie) und **blättert mit einem Pfeil** durch, **nicht nebeneinander**.
  Man darf **beliebig viele** davon unterzeichnen; **jeder kostet** seine Prämie.
- **Wen er findet:** Leute, die **noch bei keinem Label sind**: **unbekannt** (Bekanntheit 0) und **gut, aber keine Profis**: Können nur bis in die Stufe 1 (Startwert: Einfach bis 34, besser bis 44, am besten bis 54).
  Wer bekannt oder Profi ist, findet eher selbst zu großen Labels (Einsendungen).
- Der Scout ist **unterschiedlich gut**: schneller, treffsicherer (Können), mit mehr Kandidaten. Später lassen sich **mehrere** anstellen und **parallel** losschicken.
- **Dauer** (Startwerte, vorher zu lang gedacht): Stufe 1 **6 Stunden** für 2 Kandidaten, Stufe 2 **5 Stunden** für 3, Stufe 3 **4 Stunden** für 3 mit höherem Können. Eine Tour am Abend ist am Morgen fertig,
  und über den Tag reichen zwei, drei Aufbrüche. Das reicht, ein kleines Label gut zu bestücken; die Kosten sind die Prämien und, dass jeder Neue betreut werden will.

Die **Wechselbörse entfällt** erstmal; ebenso die Zufallspakete (Talent-Scout-Karten, Packs) und der Fan-Musiker.

### Spielbeginn und Startkapital

Wer neu anfängt, soll **sofort etwas tun** können:
- **Startkapital 2.000 ₲** (wie bisher). Reicht für: einen einfachen **Scout** (einmalig, Startwert 400 ₲), **mehrere Künstler** auf Einsendungen (je Unterschriftsprämie, bei Können um 25 etwa 70 ₲), je ein **einfaches Instrument**
  (Startwert gewöhnlich 60 ₲, selten 200, episch 700, legendär 2.000) und noch eine erste Platte (150 ₲ + 80 ₲ je Lied, also eine EP mit vier Liedern rund 470 ₲).
  Beispiel für vier Künstler: Scout 400 + 4 Prämien 280 + 4 Instrumente 240 + EP 470 = rund 1.400 ₲, es bleiben 600 ₲ Puffer.
- Ein **Willkommens-Stapel** liegt zu Beginn im Briefkasten: fünf Einsendungen, **drei brauchbar** (Können 15–34, verschiedene Instrumente, die sich zu einer Band ergänzen können) und zwei Mist, damit man das Öffnen gleich lernt.
- Der einfache **Scout** steht von Anfang an in der **Stellenbörse**, die **Assistenz** erst später (eine Assistenz lohnt sich erst, wenn viele Einsendungen kommen).
- Die kurze Tour führt durch: Briefkasten öffnen, Künstler unterzeichnen, Instrument kaufen, Band bilden, Proben.

### Kartei und Besetzungsplaner

Jeder Künstler unter Vertrag ist entweder **in einer Band** oder **Solokünstler** (eine Band mit einem Mitglied, kein extra Zustand „frei“). Solokünstler haben **einen eigenen Knopf** in der Band-Leiste, wie Bands.
Nicht jeder **wünscht sich einen Künstlernamen**; das hängt am Charakter (`Balance::STAGE_NAME`): Diven ab Ruhm 20, Rampensäue ab 40, Ehrgeizige ab 120, alle anderen nie.
Das Label sucht einen von drei Vorschlägen aus (fest je Person, „DJ“ und „MC“ nur, wo es passt); das freut ihn (Wohlbefinden +12). Der Name bleibt in jeder Band
und steht vor dem echten Namen; tritt er allein auf, heißt seine Solo-Band so. Solo-Bands lassen sich sonst nicht umbenennen.
Die **Künstlerleiste** zeigt je Knopf: Name, je Mitglied das Instrument (blass, wer keins hat), alle vertretenen Genres (voll, was alle teilen;
gestrichelt, was nur einzelne mitbringen), Stimmung, Ruhm als Sterne und geschriebene Songs. Knöpfe lassen sich umsortieren; legt man einen
**mitten auf einen anderen**, öffnet sich das Zusammentun-Fenster mit genau diesem Paar als Kontrollfrage.
Oben rechts sitzt die **Studiolampe** (Stimmung, ohne Wort); alles auf der Kachel lässt sich antippen und erklärt sich. Genres: voll = bringt den Genre-Bonus,
gestrichelt = keiner (auch bei Solokünstlern). Auf der Bandseite zeigt „Genre-Bonus“, was je Genre fehlt, „Welche Band braucht was?“ die Übersicht aller Genre-Bands.
- **Solokünstler** sollen sich **kaum lohnen**: ohne Beziehungen, **kein Genre-, Hybrid- oder Besetzungs-Bonus** (aber auch kein Malus für „gemischte Band“), und Auftritte und Aufnahmen kosten mehr, weil
  **Studiomusiker** zugebucht werden (Aufschlag zum Beispiel +50 %; bei **Pop** nur +20 %, denn Pop-Sänger treten oft allein auf). Gage und Besetzungs-Bonus sind niedriger als bei einer Band, die Aufmerksamkeit gleich.
  Rentabel wird es **nur in Sonderfällen**: **Pop**, **Diva** (steht gern allein im Rampenlicht, bekommt eine Stimmungsprämie). Allein ist es **einsam** (Ziel −4 für alle);
  **Teamplayer** (−12), **Rampensau** (−8) und **Partytier** (−6) leiden mehr, die **Diva** freut sich (+8). Solo schreibt man viel langsamer (siehe Songs schreiben), außer man ist kreativ.
  Genre-Bonus solo nur für **Pop** (Gesang) und **Elektro** (Keyboard oder Turntables), und nur halb. Kein täglicher Kostenpunkt je Künstler. Wer sich eigentlich eine Band wünscht, wird **unzufrieden**: **Teamplayer** und **Partytier**
  äußern den Wunsch „Ich will in eine Band!“, der offen bleibt, solange sie solo sind. Nützlich am Anfang (ein Künstler genügt) und für Musiker, die in keine Band passen.
- **Die Kartei** zeigt alle Künstler unter Vertrag (Passfotos, Liste) und ist der **Besetzungsplaner**: Dort steckt man Musiker zu **Entwürfen** zusammen und sieht, was dabei herauskäme (Genre-Titel, Sound,
  fehlende Instrumente, bekannte Reibung der Charaktere). Entwürfe lassen sich **merken**, ohne sie auszuführen; aus einem wird mit einem Tippen eine Band.
- **Bands zusammenstellen („Casting“)** bleibt, ebenso **einen Musiker zu einer Band hinzufügen** oder **herausnehmen** (er ist dann Solokünstler). Das tun Labels in Wirklichkeit selten, aber manchmal.

### Keine Bandplätze

Bands lassen sich **ohne Obergrenze** führen. Der **Label-Ruhm** zählt große Bands stärker (siehe oben), viele kleine verzerren ihn also nicht. Es bremst von selbst: Jede Band **kostet Geld** (Prämien, Instrumente, Manager) und
**Aufmerksamkeit** (Wünsche, Stimmung; rote Bands laufen **online** ihrer Geduld nach, auch wenn viele gleichzeitig rot sind). Ein Manager betreut nur wenige Bands. Irgendwann ist eine Grenze erreicht, an der man nicht
mehr alle glücklich halten kann; wer es weiter schafft, soll auch erfolgreicher sein. **Beobachten:** ob viele kleine Bands die Rangliste verzerren. Bisher ist das nicht erkennbar; gäbe es ein Problem, wäre das ein späterer Eingriff.

### Manager

- Gehören zum Label und werden **einmalig bezahlt** (keine laufenden Kosten). Ein Manager betreut **eine Band oder bis zu vier Solokünstler**.
  Nur über ihn erfährt man Bedürfnisse und Charakterzüge (gebaut, siehe „Verborgene Charakterzüge und Bedürfnisse“).
- **Stellenbörse** statt Kartenladen: **täglich wechselnd** werden **ein bis drei** Manager angeboten, mit sichtbaren Werten, Schwerpunkt-Genres und Stil, und
  oft **sehr teuer**. Den passenden zu finden ist Teil der Aufgabe.
- Wie im echten Label der Betreuer („A&R“): Jeder hat **ein bis zwei Schwerpunkt-Genres** und einen **Stil** (Charakterzug). Die **Passung** zu jeder Band
  ergibt sich aus Genre-Schnittmenge und Charakter. Gute Passung verstärkt, schlechte schwächt.
- **Wirkung:** vor allem Wissen (Wünsche, Gründe, Charakterzüge, Notizen), dazu als Zugabe Vermittler (dämpft die Stimmungsfolgen schwieriger
  Anordnungen), Verhandler (bessere Gage), PR-Profi und Netzwerker (bessere Anfragen).

### Cover

Das Label wählt aus **drei Entwürfen** (Designer günstig / solide / aufwendig), erzeugt aus den bisherigen Bausteinen (Motiv, Farben, Stil, Anordnung).
Ein stimmiger Stil zum Genre zählt. Kein Automat.

Gebaut: **Wer gestaltet** – Kunststudentin (20 ₲, blass), Agentur (150 ₲, klar mit Glanz, Erstverkauf +3 %), Star-Designerin (600 ₲, Gold mit
Folie, +6 %; `Balance::COVER_DESIGNERS`). Das **Bandlogo** (Schrift und Farbe) wählt man bei der ersten Platte; es steht danach auf jedem Cover,
und ab der zweiten Platte verkauft es 3 % mehr (Wiedererkennung, `bands.logo`). Steckt ein passendes Wort im **Titel** („Herz“, „Feuer“, „Mond“ …,
`Catalog::COVER_WORDS`), zeigen alle Entwürfe dieses Motiv. Die Schriften sind Systemschriften und sehen je nach Gerät etwas anders aus.

### Klang der Band („Profil“) – später

Idee: Die Band spielt manchmal **zwei Versionen** eines Songs vor, das Label wählt eine. Aus den Wahlen entsteht ein Klangprofil. Machbar ohne neue
Aufnahmen aus den vorhandenen Bausteinen (`Styles`: Akkordfolgen, Grooves, Muster je Genre) mit Synthesizer-Klang; die echten Aufnahmen bleiben
als Grundlage. Einzelne Wahlen verändern zum Beispiel Tempo-Gefühl, Akkordfolge, Groove. Das ist eine **spätere Ausbaustufe**.

## Oberfläche

**Übersicht = das Büro des Labels** (gebaut):
- Kopf mit Name, Gründungsdatum (Link zum WikiSignia-Artikel) und vier Zahlen: Geld (dazu Tantiemen je Minute), Label-Ruhm, Platten in den Charts
  (bester Platz), Künstler in wie vielen Bands.
- **Erste Schritte** mit Fortschritt (Kassette anhören, Büro besetzen, Instrument kaufen, proben, Auftritt, Platte, Scout); verschwindet, wenn alles einmal gemacht ist.
- **Notizen der Manager**: je Manager, was seine Leute brauchen (siehe Manager). Ohne Manager stehen dort nur die, die richtig unzufrieden
  sind, mit ihrem Wunsch, und wer noch keinen Manager hat.
- **Notizen vom Büro**, von der Assistenz (das Praktikum meldet nur, ob es beschäftigt ist oder sich langweilt), wichtigste zuerst (rot, gelb, Rest),
  je nach Gebiet: Büro (gelesene Interview-Anfragen, Scout-Berichte, Lehrkräfte einzeln mit passenden Musikern, übrige Bewerbungen), Post (was
  bald abläuft; ohne Post: ungeöffnete Umschläge), Kassetten (Funde des Suchauftrags, heute angehört und weggeworfen; ohne: ungehörte Kassetten);
  dazu gute Kassetten, ein Praktikum, das bald endet, der
  Kurs des Tages. Ohne Büro steht dort nur, dass niemand da ist.
- **Gerade los**: alles, was läuft, nach Ende sortiert (Proben, Auftritte, Studio, Promo, Interviews, Scout unterwegs, Lehrer und Coaches).
- **Schwarzes Brett** (der heutige Kurs als Zettel, mit „hinschicken“), **Chronik** (die letzten zehn Einträge) und das **Plattenregal**
  (alle Platten mit Cover und Chartplatz).
- Scout-Gutscheine gibt es nicht mehr (weder für die erste Platte noch für Platz 1); Platz 1 wird nur vermerkt.


- **Hauptmenü** (oben): Übersicht, Katalog, Einkaufen, Lager, Börse, Charts.
- **Band-Leiste** (links, immer sichtbar): gleich große Knöpfe ohne Köpfe, je Band (und je **Solokünstler**) mit
  Name, Genre, **Stimmung als Punkt und Wort** (gut / angespannt / unzufrieden, **kein Smiley**), **Ruhm als drei Rauten**, **Songs geschrieben rechts** (Notensymbol mit Zahl), kleiner Wunsch-Hinweis (leere Blase), Streifen wenn beschäftigt.
  Verschieben per Ziehen gibt es in der senkrechten Leiste noch nicht (die gespeicherte Reihenfolge gilt weiter).
- **Bandseite** (Klick auf die Band, kein Untermenü): Kopf mit Name und Stimmung; **Anordnungen** als Knöpfe (Proben, Aufnehmen, Auftritt);
  Musikerliste als **Zeilen**: eckiges Passfoto (Hintergrund in den Genre-Farben, Rahmen in der Stimmungsfarbe), Name mit **Stimmung als Wort direkt dahinter**,
  Instrument-Symbol, Können-Strich mit **Stufe 0–3**, **quadratischer Platz für das Instrument**, **Genre-Marken in Genre-Farbe**, Beziehungen (kleines Foto des anderen mit Zahl, farblos); darunter **Werte mit Aufschlüsselung** (Sound, Ruhm, Zusammenhalt,
  Stimmung mit den Einflüssen, die gerade wirken); Wünsche der Band oben; **Auszeichnungen** als kleine Symbole. Die **Chronik** ist zugeklappt und lässt sich per Tippen öffnen. Keine Bühne.
- **Bandseite als Szene** (gebaut, `label.js` `bandScene`, `room.js`): Oben steht der Proberaum statt eines Kopfes. Der Bandname hängt als Schild
  an der Wand, passend zum Raum: Holzbrett (Bruchbude), Pappe mit Klebeband (Keller), Metallschild (Proberaum), Leuchtschrift mittig auf der
  Lamellenwand (Profi-Raum). Das Schild ist Teil der Wand, alles im Raum liegt davor. Oben rechts klein „Band · Genre“ mit Umbenennen.
  **Mit Genre-Bonus ist der Raum im Stil des Genres eingerichtet** (`THEMES`): Tapete, Boden, Teppich (Farbe und Form), Lichterkette bzw.
  Laternen, Lamellen (Holz, Glas, Metall, Lack), Polster und Wandpaneele. Die Bruchbude bekommt nur eine Decke aufs Sofa.
  Der **Manager** steht hinten rechts mit Klemmbrett (antippen: Manager ändern). Der **dringendste Wunsch** steht als Blase mit drei Punkten
  über dem Kopf; angetippt zeigt sie den Wunsch und den passenden Knopf. Ist er erfüllt, kommt der nächste (Warteschlange).
  Direkt am Raum hängt eine Leiste mit dem Proberaum (Name, Stufe, Wirkung) und dem Knopf zum Ausbauen.
  **Aufstellung per Ziehen:** Einen Signie festhalten (Maus: drücken und ziehen; Finger: kurz halten) und an eine andere Stelle ziehen; die anderen
  rücken dabei zur Seite. Die Reihenfolge wird gespeichert (`bands.stage_order`, `bandStageOrder`) und gilt überall (Raum, Bühne, Musikerliste,
  Leiste). Neu Dazugekommene stehen hinten an. Kurz antippen öffnet weiter den Musiker.
  Darunter die **Anordnungen als Schallplatten** mit farbigem Etikett (Proben türkis, Aufnehmen gelb, Auftritt rosa, Promo blau, Auszeit grün).
  Läuft etwas (Probe, Auftritt, Aufnahme), steht dessen Szene oben, und die Wünsche stehen als Liste darunter.
  **Kommen und Gehen** (`leaveScene`): Zum Auftritt und zur Promo-Tour geht die Band nacheinander rechts aus dem Raum, erst dann wird die
  Anordnung abgeschickt. Zur Auszeit holen sie sich links Koffer und gehen dann rechts raus. Solange sie weg sind (Auszeit, Promo), ist der
  Raum leer und ein Zettel an der Wand sagt, wann sie zurück sind. Danach kommen sie von rechts wieder herein (nach der Auszeit mit Koffern),
  sobald das Ergebnis-Fenster zu ist. Mit „weniger Bewegung“ im Gerät entfällt das Laufen.
  Beim Gehen sind die Signies **von der Seite** gezeichnet (`figure.js` `walker`: Profil mit einem Auge, Nase und Ohr, alle Frisuren, Hüte,
  Brillen, Bärte und Schmuck; Beine und Arme schwingen), nach links gespiegelt. Gepäck: zur Auszeit der Koffer in der Hand, zum Auftritt das
  eigene Instrument (Gitarre, Bass, Geige in der Tasche auf dem Rücken, Sticks in der Stocktasche, sonst ein Koffer; Gesang und wer kein
  eigenes Instrument hat, nichts), zur Promo nichts. Angekommen stehen sie wieder von vorn da.
- **Übersicht des Labels:** alle geäußerten Wünsche aller Bands als **Mitteilungen** untereinander; Tippen öffnet die Band, ein Knopf erfüllt direkt.
  Dazu Geld, Charts, Rangliste.
- **Katalog** ist aufgelöst (Testversion): Jeder Künstler unter Vertrag steht sofort als Band mit einem Mitglied (Solokünstler) in der Band-Leiste, auch wer gerade unterschrieben hat oder aus einer Band genommen wurde. Die Kartei als Besetzungsplaner kommt später.
  Solokünstler ohne eigene Laufbahn (keine Platte, kein Auftritt) lassen sich in eine andere Band holen; ihre leere Solo-Band verschwindet dann.
- **Aufnahme-Dialog** (Studio, Produzent, Songs, Cover) mit Preisen.

**Gestaltung:** ruhig, dunkle Flächen in drei Stufen (Seite, Feld, Karte), ein warmer Akzent. **Farbe hat feste Bedeutungen:** Grün/Gelb/Rot nur für Stimmung,
die Genre-Farben nur für Genre. Keine Seltenheits-Farben, keine Smileys, Köpfe eckig mit abgerundeten Ecken.

### Auszeichnungen

Deutlich weniger als bisher, nur **echte Pokale**: zum Beispiel Goldene Schallplatte, Platin-Schallplatte, Platz 1 der Charts, Große Bühne (Halle/Arena),
später „Battle of the Bands“ gewonnen. Kleinkram wie „Erster Gig“ fällt weg und steht höchstens in der Chronik.

## Bleibt und geht

| Bleibt | Geht (erstmal) |
|---|---|
| Kartei und Besetzungsplaner, Entlassen (mit Rückerstattung), Einsendungen, Assistenz, Scout, Solokünstler | Solo-Üben, Etüden, Instrument-Minispiele |
| Bandproben (als Hauptquelle für Können), Kurse | Jam-Café, Straßenmusik, Open Stage, Fan-Musiker, Wechselbörse, Zufallspakete |
| Aufnahmen (ohne Minispiele), Cover (als Wahl) | Einzel-Promo (kommt als Interview-Anfrage zurück) |
| Gigs mit Live-Ticker und Bühne, Charts, Tantiemen | Mischpult, Master-Mix, Cover-Automat |
| Instrumente (gezielt), Manager (einmalig) | |
| Bandseite ohne Bühne; Bühne bei Auftritten | |

Wir reduzieren stark und schauen dann, was wirklich fehlt.

## Übergang für bestehende Labels

Beim ersten Login nach dem Umbau (Saison 2) bleiben **Labelname und Konto**, alles andere fängt neu an (Ruhm, Platten, Charts, Lager, Katalog). Die alten Bands kommen **als Einsendungen zurück**:

- **Jede alte Band kommt als normale Einsendung** ins Haus (ein Umschlag wie alle anderen), mit **denselben Regeln**: sie läuft nach **4 Tagen** ab (steht darauf), man kann sie wegwerfen, aufnehmen,
  ganz oder einzelne Mitglieder. Die **Frist beginnt mit dem ersten Login nach dem Umbau**, nicht schon beim Umbau (damit niemand sie verpasst); ein kurzer Willkommenshinweis weist darauf hin.
  Es gibt keinen besonderen Aufbewahrungsort. Unterschied: Sie **unterschreiben kostenlos** (beim Öffnen ein Hinweis: „Diese Band würde gern bei dir bleiben und unterschreibt kostenlos“) und bekommen eine **besondere Animation**.
  Das kostenlose Unterschreiben ist der Bonus für alte Spieler und passt zum knappen Startkapital.
- Sie behalten **Namen, Mitglieder, Aussehen, Charakter und Können**, starten aber **ohne Ruhm, Bekanntheit und Platten** und mit einem einfachen Standard-Instrument je Musiker.
  Ein besonderer Chronik-Eintrag erinnert an die alte Zeit.
- **Beziehungen** werden nicht nur aus dem Zusammenspiel berechnet: Die **gemeinsame Bandzeit** zählt (aus der Chronik: wer ist wann beigetreten, Gründungsmitglieder seit der Gründung)
  und der **Charakter** (Passung, auch Genre und Können-Abstand). Startwert eines Paares = Zielwert der Passung × Anteil, der sich mit der gemeinsamen Zeit füllt.
- **Startkapital:** wie ein neues Label, bei früher Erfolgreichen etwas mehr (Vorschlag: nach Platz in der Bestenliste ein kleiner Aufschlag).
- Ohne Wechselbörse gibt es kein „Auflösen und teuer verkaufen“. Es gibt keine Ruhmeshalle der vorigen Saison und keine Bandplätze, die man freischalten müsste.

## Wer wirkt auf wen (nur stabilisierende Kreisläufe)

- **Ruhm → Anspruch → Stimmung:** wer berühmt ist, verlangt mehr (kostet das Label mehr). Gewollt, bremst von selbst.
- **Stimmung → Songs → Stau → Stimmung:** Songs machen glücklich, bis sie liegenbleiben. Gewollt, bremst von selbst.
- Es gibt **keinen** Kreislauf, in dem sich ein Wert über einen anderen von selbst aufschaukelt (Grundsatz aus `kern.md`: Ruhm darf nie den eigenen Zuwachs verstärken).

## Umbau in Schritten (jeweils nur in der Testversion)

1. Konzept und Auslagerung dokumentieren (diese Dateien).
2. Datenmodell: Wohlbefinden, Beziehungen (Paare), Songs, Spielzeit, Stimmung.
3. Band-Leiste und Bandseite (neue Oberfläche, ohne Untermenü). **Gebaut** (Testversion). **Zusammenspiel und Chemie sind ersetzt** durch den Zusammenhalt aus den Beziehungen (siehe `kern.md`). Noch offen: Verschieben der Knöpfe.
   Dazu gebaut (vorgezogen aus Schritt 6): **Charakter-Reaktionen** (`Catalog::LIKES`: Eine Anordnung, die der Charakter mag, hebt das Wohlbefinden sofort um `Balance::MOOD['reactStep']`, eine unbeliebte drückt es), **Sperre bei Rot** (ein roter Musiker, der die Anordnung nicht mag, sperrt sie; jede Eigenschaft mag mindestens eine Anordnung, damit es immer einen Ausweg gibt) und **Wünsche** (unzufriedene Musiker nennen ihren Charakter-Wunsch, `Catalog::WISHES`; fehlendes Instrument; Songstau). Offen: die Wirkung offener Wünsche auf das Wohlbefinden über die Zeit (bisher nur der Stoß beim Erfüllen), Anspruch und Studio.
4. Anordnungen: Proben (neue Können-Quelle), Auftritt buchen mit Kosten und Gewinn-Schätzung.
5. Aufnehmen mit Studio und Produzent, Cover-Wahl.
6. Wünsche, Mitteilungen, Charakter-Tabelle.
7. Interview-Anfragen, Kurse und Einkaufen, Einsendungen mit Assistenz und Scout, Kartei und Besetzungsplaner.
8. Manager neu.
9. Übergang für bestehende Labels.
10. Entfernen der ausgelagerten Teile aus dem Code.

## Offene Fragen

- Genaue Zahlen (Songtempo, Geduld, Beziehungsraten, Zeit bis Rot, Studio-/Produzentenpreise, Kosten und Gewinn der Orte) werden beim Bauen abgestimmt.
- Verfallszeit der Einsendungen (4 Tage?), Verteilung der Mengen und Preise der Assistenzen und Scouts (Scout-Startwerte: Stufe 1 400 ₲, Stufe 2 1.200 ₲, Stufe 3 3.000 ₲).
- Klangprofil (A/B-Vorspielen): erst nach den ersten Schritten.
- Auszeichnungen: endgültige kurze Liste.

## Zusammentun (Band erweitern)

- Statt „Musiker aufnehmen“: „mit Musiker(n) zusammentun“ (`bandJoinCandidates`, `bandMerge`). Kandidaten sind freie Solokünstler **und** Bands ohne eigene Laufbahn.
- Sortierung: erst echte Vorteile (Genre-Titel, Genre-Bonus, neues Instrument bei steigendem Sound), dann gute Passung der Charaktere (Beziehungsziel ≥ 4), dann Sound-Änderung; Unmögliches zuletzt mit Grund. Suchfeld nach Namen (auch Mitglieder).
- **Aufstellung im Raum** (Proberaum, Studio, Bühne; `figure.js` `stage`, `label.css` `.fig-stage.depth`): bis vier stehen nebeneinander,
  ab fünf jeder Zweite versetzt dahinter (etwas kleiner und weiter oben). So passen bis zu sieben in jede Breite; die Größe richtet sich nach Breite und Höhe des Raums.
  Auf den Bühnen der Auftrittsorte gibt es dafür eine **Bühnenfläche mit Tiefe** (`stage.js` `DECK`): Die vordere Reihe steht an der
  Vorderkante, die hintere hinten auf der Fläche, beide also auf der Bühne und nicht im Publikum oder in der Luft. Die Höhen rechnen in
  Punkten des Bühnenbilds (`--u` in `label.css`), damit sie bei jeder Größe passen; in der Kneipe (ohne Bühne) steht die Band auf dem Teppich.
- Band-Leiste links ist per Ziehen ordenbar (`navOrder`, Schlüssel `b<ID>`). Sie bleibt beim Anklicken einer Band an ihrer Scroll-Stelle (`main.js`, `render`). Gelangweilte Figuren ohne Instrument zucken ab und zu die Schultern.
- Solokünstler: keine „Band“-Wörter, „Künstler entlassen“ statt „Band auflösen“, kein ✕, kein Zusammenhalt-Feld.

## Zusammentun, Namen und ehemalige Bands

- **Zusammentun geht immer** (Bands wie Einzelmusiker, mit oder ohne Laufbahn). Die Liste bewertet für **beide** Seiten (Genre-Bonus, Charaktere), sortiert nach dem Mittel beider Bewertungen.
- **Namenswahl** beim Zusammentun: Name A, Name B oder neuer Name. Ein Einzelmusiker hat keinen Namen, der bleiben könnte (nur Name der Band oder neu). Der Dialog zeigt den Ruhm danach:
  Ruhm danach = Bekanntheit aller Mitglieder + Ruhm des Namens, der bleibt. Wer seinen Namen aufgibt, verliert den Ruhm daran.
- **Umbenennen** kostet kein Geld. Mit Laufbahn geht der Ruhm am alten Namen verloren (wie bei „neuer Name“).
- **Ehemalige Bands:** Gibt eine Band ihren Namen auf (umbenannt, aufgelöst, aufgegangen, Solokünstler verlässt das Label) und hat eine Laufbahn,
  bleibt sie als Zeile in `bands` mit `retired_at` erhalten (Grund, Notiz, Ruhm am Ende `fame_final`). Ihre Platten und ihr Verlauf (Chronik-Einträge) bleiben bei ihr, nicht bei der weiterspielenden Band;
  die Zähler der weiterspielenden Band (Platten, Auftritte, Songs) beginnen bei 0. Ohne Laufbahn gibt es nichts zu bewahren, die Band verschwindet.
- **Label-Chronik** (Menü „Chronik“): alle ehemaligen Bands mit Gründung, Wechseln, Ende, Platten und Auszeichnungen.
- **Tantiemen** laufen, solange die Platte höchstens auf Platz 50 der Charts steht (`Balance::ROYALTY_RANKS`); die Punkte sinken stündlich (CHART_DECAY), nach 7 Tagen endet es ohnehin.
  Die Tantiemen gehen ans Label, der Ruhmanteil nur an aktuelle Mitglieder der Band (bei ehemaligen Bands niemand).

## WikiSignia

- Ersetzt die Label-Chronik. Menüpunkt „WikiSignia“: Verzeichnis (Suche) aller Bands und aller **Signies** (so heißen die Musiker-Figuren, vorerst nur hier) des Labels, auch ehemalige.
  Artikel `#/wiki/band/<id>` und `#/wiki/signie/<id>` im Wikipedia-Stil: Infobox rechts, Einleitung, Abschnitte (Mitglieder, Diskografie, Auszeichnungen, Geschichte; bei Signies Bands, Fähigkeiten, Laufbahn).
- Ehemalige Signies (entlassen oder verkauft) stehen in der Tabelle `alumni` und behalten ihren Artikel. Texte nennen Namen statt Pronomen.
- Wer zuletzt in einer Band war, die ihren Namen aufgibt, wird in der Geschichte der ehemaligen Band und im Artikel des Signies festgehalten; Ein- und Austritte werden immer protokolliert.
- Wer kein Instrument hat, spielt bei der Probe nichts: seine Spur bleibt stumm.

### WikiSignia über alle Labels

- Oberste Ebene ist das **Label**: Verzeichnis `#/wiki` (eigenes Label mit Bands und Signies, dazu die anderen), Labelseite `#/wiki/label/<id>`, Artikel zeigen ihr Label. Auch Künstler und Bands fremder Labels sind lesbar; von fremden Labels sieht man nur Einträge ohne Geld (lineup, band, record, level).
- **Labelartikel:** Gründung, Label-Ruhm, Zahl der Platten, Nummer-1-Hits, perfekte Platten und Auftritte, die größten Erfolge
  (beste Chartplätze), die bekanntesten Bands und Meilensteine (erste Band, erste Platte, erste Nummer 1, erste perfekte Platte).
- Protokolliert werden für jeden Künstler: Unterschrift, Bandwechsel, Stufenaufstiege (Können und Bekanntheit), gelernte Fähigkeiten, Platten, Ende.
- Suche über alle Labels (`wikiSearch`). Zugang über den Knopf „WikiSignia“ im Label-Menü oben rechts.
- **Namensschutz:** Eine ehemalige Band behält ihren Namen nur geschützt, wenn sie am Ende mindestens `Balance::NAME_PROTECT_FAME` (20, erste Bekanntheitsstufe) Ruhm am Namen hatte; sonst wird der Name frei. Wiederbeleben ist noch nicht möglich.
- **Verbundener Name** („Solo & Band“, „and the“, „und die“): behält `NAME_COMBO_KEEP` (50 %) des Ruhms beider Namen; ein ganz neuer Name behält nichts, ein vorhandener Name den vollen eigenen Ruhm, der andere Name verfällt.

### Music Store (erste Version, gebaut)

- Ersetzt „Einkaufen“ (Zufallspakete bleiben im Code, aber nicht mehr im Menü). Menü: Übersicht, Einsendungen, **Music Store**, Charts, WikiSignia; Lager und Börse sind nicht mehr im Menü (Seiten bestehen weiter unter `#/lager`, `#/boerse`).
- **Grundausstattung** (unten, eine Lochwand): jedes gewöhnliche Modell (15), immer da, beliebig oft, 40 ₲ (`Store::basics`, `storeBuyBasic`).
  Ein graues Instrument braucht jeder, und man kann es beliebig lange weiterspielen – deshalb gibt es keinen Druck mehr, das Regal neu zu füllen.
- **Angebot des Tages** (oben, ein Werbebereich wie im echten Laden): für alle Labels gleich, **wechselt jeden Morgen** (`Balance::STORE`: `hours` 24, `offset` 4 Uhr UTC);
  es ergibt sich aus der Tagesnummer (`Store::shelf($slot)`), nicht aus dem Spiel-Zufall. Je Art höchstens eins:
  Im Regal immer drei Fächer (`offer.shelf`), meist seltene (grün), mit 35 % ist eins davon episch (blau, mit Sticker „Top-Angebot“).
  Mit 13 % kommt ein legendäres dazu (gold, etwa einmal die Woche) – es steht extra im **Schaufenster** über dem Regal.
  Preise 320 / 1.500 / 5.200 ₲ × Güte. Jedes Angebot kauft ein Label einmal.
- **Gold wird beworben:** Schaufenster mit Wimpelkette, Vitrine mit Strahlen, Aufsteller „Einzelstück“ mit der goldenen Fähigkeit und einem Werbespruch
  (`Catalog::GEAR_BONUS_INFO[…]['ad']`), Sticker „Nur heute!“ und „Legendär“, goldenes Werbeschild und Rahmen, Stern am Menüpunkt und einmal je Angebot ein Hinweis.
- Gekauftes hinterlässt ein leeres Fach („Verkauft“), die Vitrine bleibt leer stehen. Zurücklegen gibt es nicht mehr.
- **Man sieht das Instrument an der Figur** (`figure.js`, `held`): Die Figur hält dieselbe Zeichnung wie im Laden (`gearart.js`, ohne
  Schlagzeugstöcke und Geigenbogen, die hält sie selbst), in Güteklasse und Farbe des eigenen Instruments; ohne eigenes in Grau.
  Gold funkelt dazu leise. Was der Laden von oben zeigt, hat für die Figur eine eigene Ansicht von vorn bzw. von der Seite (`gearart.js`, `SIDE`):
  Keyboard (immer als Synthesizer), E-Drums und Geige (schräg unterm Kinn, mit sichtbarer Zarge). Plattenspieler liegen flach auf dem Tisch.
  Auch die kleinen Instrument-Symbole auf den Musikerkarten zeigen das eigene Instrument.
- Die frühere „Neue Lieferung“ gegen Geld gibt es nicht mehr.
- Oberfläche: gezeichnetes Regal mit Preisschildern, Seltenheit als Lichtschein, Hinweispunkt bei Instrumenten, die jemandem im Label fehlen; Übergang: Doppeltür geht beim ersten Betreten auf.

### Proberaum (gebaut)

- Jede Band hat ihren eigenen Proberaum (`bands.room`), auch jeder Solokünstler (seine **eigene Übungsecke**, voller Preis). Die frühere gemeinsame Übungsecke (`labels.solo_room`) ist abgelöst; ihre Stufe haben die Solokünstler übernommen.
- Stufen (`Balance::ROOM`): Bruchbude → Aufgeräumter Keller (400 ₲) → Gemütlicher Proberaum (1.200 ₲) → Profi-Proberaum (3.000 ₲). Wirkung: Können je Probe ×1,08/1,16/1,25,
  die Band hält ×1,15/1,3/1,5 länger durch, Wohlbefinden +1/+2,5/+4 je 15 Minuten Probe für alle. Beim Zusammentun bleibt der bessere Raum.
- Auf der Bandseite steht die Band in ihrem Raum (gezeichnet in `room.js`, vier Stufen); während einer Probe spielt sie dort.

### Auftritt buchen (gebaut, erste Version)

- Knopf „Auftritt buchen“ auf der Bandseite (und beim Wunsch „Ab auf die Bühne!“) öffnet die Orte: vom kleinsten bis zwei Stufen über dem Anspruch der Band (Anspruch = Ort nach Bandruhm).
- Je Ort: **feste Kosten** (Kneipe 20, Club 90, Saal 350, Halle 1.100, Arena 3.200 ₲; Solokünstler ×1,5, Pop ×1,2 für Studiomusiker), Länge in Songs (3/4/5/7/9),
  **Gage-Spanne und Gewinn** (aus sechs durchgespielten Abenden), erwarteter Ruhm, Ampel „Sound passt / knapp / zu schwach“ und wie die Band den Ort findet.
- **Gage** = Grundgage × Füllgrad × Stimmung im Saal (0,7 + 0,3 × Leistung). Füllgrad = Sound gegen Erwartung × Bekanntheit gegen Ortsgröße ((Ruhm + 10) / (Mindestruhm + 10)), mindestens 5 %: Unbekannte füllen keinen Saal.
- **Bezahlt wird hinterher** (gebaut): Die Rechnung kommt nach dem Auftritt und wird von der Gage bezahlt („Rechnung: …“ im Kontoauszug).
  Vorher zahlt man nur den Teil, den selbst der schlechteste Abend (Leistung 0, also 70 % der Grundgage × Füllgrad) nicht einspielt; meist ist
  das nichts. So kann eine Band auch spielen, wenn das Label pleite ist, und das Label rutscht nie ins Minus (`Gigs::deposit`).
- Gespielt werden **aufgenommene und geschriebene Songs** (eine Platte ist keine Voraussetzung mehr); geschriebene bleiben für die Platte erhalten.
- **Anspruch:** Ort darunter → Wohlbefinden −6 (Ehrgeizige, Diven, Rampensäue stärker), darüber → +4. Zahlen in `Balance::GIG_BOOKING`.
- **Aussehen:** Im Buchungsfenster sieht man jeden Ort von außen (Fachwerk-Kneipe, Club mit Neonschild, Konzertsaal mit Säulen, Halle, Arena) und eine Reihe Köpfe, wie voll es wird.
  Die Bühne innen sieht man erst beim Auftritt: auf der Bandseite und im Live-Fenster, je Ort prächtiger (Kneipe mit Teppich und Tresen bis Arena mit Leinwänden und Lichtstrahlen), mit wippendem Publikum davor (`stage.js`).
