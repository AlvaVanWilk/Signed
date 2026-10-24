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
| **Erfolg** | Summe des Ruhms aller Bands; steht in der Rangliste; bestimmt, wie viele Einsendungen kommen |

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
| **Einkaufen** | Instrumente, Kurse, Proberaum, Auszeit … (siehe unten) | Geld; Kurse brauchen einen freien Musiker |
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

### Einkaufen

Aus den „Läden“ wird **Einkaufen**, mit Reitern nach Art. **Der Shop ist für alle Spieler gleich**: Das Angebot wechselt täglich (aus dem Datum berechnet,
nicht pro Spieler gewürfelt), wie bei den Tageskursen. Man sieht vorher Preis und Werte und entscheidet gezielt. Das „Zufällige“ ist nur,
**was heute im Regal steht** (Art und Güte), nicht, was man bekommt. Es gibt nur **einmalige Ausgaben**, keine laufenden Kosten.

| Reiter | Was | Wirkung | Wann |
|---|---|---|---|
| **Instrumente** | gezielt für einen Musiker, in verschiedener Güte | Klang, Sound, Wohlbefinden (Wünsche), nötig zum Proben | von Anfang an |
| **Kurse** | einzelne Musiker fördern; mehrere Kurse, aus denen man wählt (Instrument, Dauer, Preis), täglich gleich für alle | Können (ohne Bremse), Wohlbefinden je Charakter (Ehrgeizige mögen Kurse) | von Anfang an |
| **Proberaum** | Ausstattung **einer Band**, in Stufen | Proben bringen mehr Können und stärken die Beziehung | früh, teure Stufen später |
| **Auszeit** | Wochenende, Urlaub, Ausflug für **eine Band** | hebt die Stimmung (Gelassene und Ruhepole freuen sich besonders), Band ist dabei für eine Zeit nicht verfügbar | mittel; Preis steigt mit der Bandgröße |
| **Bühnentechnik** | Licht, Ton, Show, Stufen | größere Orte füllen sich leichter (Erwartung sinkt), Diva und Rampensau freuen sich | mit größeren Bühnen |
| **Fahrzeuge** | **kleiner Tourbus**, **großer Tourbus**, **Jet**, jeweils in verschiedener Güte | Komfort auf Tour (Stimmung), später Voraussetzung für Tourneen mit mehreren Auftritten | **nur im späten Spiel**, erst mit hohem Label-Erfolg freigeschaltet |
| **Stellenbörse** | Manager und Assistenz, täglich 1–3, teuer | siehe Manager und Einsendungen | früh |

**Was ich noch gut fände** (für später, nicht alles auf einmal): ein **Songschreiber/Texter** für eine Band (die Band schreibt schneller Songs),
ein **Medienpaket** (bessere Interview-Anfragen), **Teambuilding** (Beziehungen wachsen schneller), **Outfits/Styling** (Bekanntheit der Mitglieder wächst schneller).
Jede neue Art braucht einen eigenen Zweck und Wunsch (Perfektionist → Proberaum, Diva → Bühnentechnik und Fahrzeug, Gelassen → Auszeit), sonst ist sie nur Beiwerk.

**Warnung zur Größe:** Mehr Reiter bedeuten mehr Klicken. Ich würde mit **Instrumente, Kurse, Proberaum, Auszeit und Stellenbörse** beginnen und
**Bühnentechnik und Fahrzeuge zusammen mit Tourneen** nachlegen. Der Weg zu den hohen Stufen (Konzept „Tempo“) läuft dann über diese Bereiche, nicht über eine Bremse.

### Woher neue Musiker kommen

In der Wirklichkeit schicken Künstler Demos an Labels, und Talentsucher hören sich um. Ein Label sucht nicht aus einem Katalog aller Menschen aus. Zwei Wege, die nichts miteinander zu tun haben:

**1. Einsendungen** kommen von selbst ins Haus.
- Jeden Tag kommen **Einsendungen** an, **für jedes Label eine eigene Auswahl** (ein Künstler bewirbt sich nur bei einem Label). Sie liegen als **neutrale Umschläge/Tapes** im Briefkasten.
  Außen steht **nichts** (weder Solo noch Band). Anklicken **öffnet** („entblößen“, etwa 5 Sekunden) und zeigt, was drin ist: meist **Unnützes** (sehr schwach, kein erkennbares Genre),
  manchmal **Gutes**. Das Öffnen hat etwas von einem Pack, soll aber **Arbeit** sein.
- **Laufend statt einmal am Tag:** Die Einsendungen **trudeln über den Tag verteilt ein** (Zufallszeiten, nicht alle auf einmal); bei einem kleinen Label etwa alle drei Stunden eine, bei einem großen alle paar Minuten.
  Wer wartet, bekommt mehr zu tun, und offline sammeln sich Einsendungen bis zum Verfallsdatum.
- **Keine Mengenbegrenzung** im Briefkasten; nur ein Verfallsdatum (etwa 4 Tage), das **auf jeder Einsendung steht** („läuft ab in …“). Wegwerfen kann man jede Einsendung jederzeit. Am Anfang kommen **wenige** (grob 8 pro Tag), mit wachsendem **Erfolg des Labels** immer mehr
  (grob 14 bei 50, 45 bei 450, 250 bei 5.000 Erfolg) und ein größerer Anteil Guter (von etwa 5 % auf höchstens 25 %). Die schlechten hören **nie auf**, sich zu bewerben:
  Große Labels haben viel Arbeit, können sich aber am ehesten eine Assistenz leisten.
- **Genres folgen dem Erfolg:** Hat eine Band des Labels Erfolg, bewerben sich vermehrt Musiker mit **gleichen oder ähnlichen Genres**, fremde Genres seltener (außer ein Musiker hat mehrere Genres,
  dann kann auch ein fremdes dabei sein).
- **Aufnehmen kostet eine einmalige Unterschriftsprämie**, die alle Gagen des Künstlers zusammenfasst, fürs ganze Leben: groß = teurer als klein, bekannt = teurer als unbekannt.
  Grundform je Musiker: `40 + 0,05 × Können² + 1,5 × Bekanntheit` (Können 50 ≈ 165 ₲, Können 80 ≈ 360 ₲). Ablehnen ist kostenlos. Von einer **Band** kann man alle aufnehmen oder **einzelne Mitglieder**;
  die anderen bleiben zurück. Mitglieder einer Band **kennen sich schon**, ihre Beziehung startet nicht bei 0.
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
- **Solokünstler** sollen sich **kaum lohnen**: ohne Beziehungen, **kein Genre-, Hybrid- oder Besetzungs-Bonus** (aber auch kein Malus für „gemischte Band“), und Auftritte und Aufnahmen kosten mehr, weil
  **Studiomusiker** zugebucht werden (Aufschlag zum Beispiel +50 %; bei **Pop** nur +20 %, denn Pop-Sänger treten oft allein auf). Gage und Besetzungs-Bonus sind niedriger als bei einer Band, die Aufmerksamkeit gleich.
  Rentabel wird es **nur in Sonderfällen**: **Pop**, **Diva** (steht gern allein im Rampenlicht, bekommt eine Stimmungsprämie). Wer sich eigentlich eine Band wünscht, wird **unzufrieden**: **Teamplayer** und **Partytier**
  äußern den Wunsch „Ich will in eine Band!“, der offen bleibt, solange sie solo sind. Nützlich am Anfang (ein Künstler genügt) und für Musiker, die in keine Band passen.
- **Die Kartei** zeigt alle Künstler unter Vertrag (Passfotos, Liste) und ist der **Besetzungsplaner**: Dort steckt man Musiker zu **Entwürfen** zusammen und sieht, was dabei herauskäme (Genre-Titel, Sound,
  fehlende Instrumente, bekannte Reibung der Charaktere). Entwürfe lassen sich **merken**, ohne sie auszuführen; aus einem wird mit einem Tippen eine Band.
- **Bands zusammenstellen („Casting“)** bleibt, ebenso **einen Musiker zu einer Band hinzufügen** oder **herausnehmen** (er ist dann Solokünstler). Das tun Labels in Wirklichkeit selten, aber manchmal.

### Keine Bandplätze

Bands lassen sich **ohne Obergrenze** führen, und der **Erfolg des Labels** ist einfach die Summe des Ruhms aller Bands. Es bremst von selbst: Jede Band **kostet Geld** (Prämien, Instrumente, Manager) und
**Aufmerksamkeit** (Wünsche, Stimmung; rote Bands laufen **online** ihrer Geduld nach, auch wenn viele gleichzeitig rot sind). Ein Manager betreut nur wenige Bands. Irgendwann ist eine Grenze erreicht, an der man nicht
mehr alle glücklich halten kann; wer es weiter schafft, soll auch erfolgreicher sein. **Beobachten:** ob viele kleine Bands die Rangliste verzerren. Bisher ist das nicht erkennbar; gäbe es ein Problem, wäre das ein späterer Eingriff.

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

- **Hauptmenü** (oben): Übersicht, Katalog, Einkaufen, Lager, Börse, Charts.
- **Band-Leiste** (links, immer sichtbar, bleibt verschiebbar): große Knöpfe, je Band (und je **Solokünstler**) mit
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
3. Band-Leiste und Bandseite (neue Oberfläche, ohne Untermenü).
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
