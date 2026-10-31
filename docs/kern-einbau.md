# Einbau des neuen Kerns – Checkliste

Stand: Plan, noch nichts eingebaut. Das Rechenwerk liegt in `src/Game/Core` (Fans, Hype, Sales, Concert, Events, Career, Quality,
Songs, Needs, Skill), die Zahlen in `Balance::CORE_*`, die Tests in `tests/CoreTest.php`. Herleitung und Beschlüsse stehen im
Dokument „Signed – Rechenwege“ (Abschnitt Einbau-Plan, Fragen 15–17). **Eingebaut wird erst, wenn Sandra Frage 17 bejaht hat**,
denn ab dem Einbau werden die Spielstände der Testversion umgestellt. Die Schritte 3 bis 6 gehen zusammen hoch.

## Grundsätze

- `Career` gibt die Reihenfolge vor: vor jeder Handlung alles bis jetzt weiterrechnen (`settle`), alle Platten einer Band im Gleichschritt.
  Gespeichert werden dieselben Werte wie im Career-Array (Band) und in `Sales::release` (Platte).
- Kein Cron: Weitergerechnet wird wie heute je Anfrage in `Api::handle` (eigenes Label). Für die Charts werden einmal je Stunde alle Bands
  mit laufenden Platten bis zur vollen Stunde gerechnet.
- Alle Werte als Kommazahlen speichern, erst in der Anzeige runden. SQL für SQLite und MySQL, Schema nur am Ende von `Schema::migrations()`.

## Schritt 3: Datenbank (neuer Eintrag #55)

- `bands`: `fans`, `fresh` (Start 1), `last_gig_at`, `core_at` (bis wann gerechnet).
- `records`: Zustand aus `Sales::release` – `q` (0–100; `records.quality` ist die alte 0–1-Skala), `f0`, `pool`, `hype`, `boosts`, `buyers`,
  `gained`, `willing`, `day`, `room`, `sold_rate`, `fan_rate`, `catalog_rate`, `day_sold`, `core_at`, `catalog_sold`, `ended`.
  `records.sold` gibt es schon (Auszeichnungen), `finished_at` dient als Erscheinen.
- `songs` (neu): einzelne Songs mit eigener Qualität (beschlossen: beim Aufnehmen 12 aus dem Vorrat wählen). Heute ist der Vorrat nur
  eine Zahl (`bands.songs_written`).
- `chart_hours` (`record_id`, `hour`, `sold`): Verkäufe je Stunde; Platz = Summe der letzten 24 Stunden.

## Schritt 4: Einbauen

- **Auftreten:** `Activities::start` (Zweig mit Ort und alter Zweig ohne Ort), `Gigs::options/claim/cost/deposit/gage`,
  `BandCalc::gagePerSong/freshness/venue` → ein Knopf „Auftreten“ mit `Career::preview`, dann `Career::gig`. Kosten, Anzahlung und Gage
  fallen weg. `Performance::simulate` bleibt für den Live-Ticker; L = Publikumsleiste am Ende (0–100).
  Vorschlag: Erwartung des Ortes = Sound-Erwartung der Ruhmstufe (`CORE_SOUND_EXPECT`).
- **Gutschreiben:** `Activities::applyBand` – Geld = `earnings`, keine Ruhm-Gewinne, kein `Charts::boost`; `gigs_since_record` entfällt.
- **Aufnehmen:** `Studio::quality/estimate/returns/complete` → `Core\Quality::album` (S, W, P, E) und `Career::release`; kein Erstverkauf,
  kein Ruhm, keine Chartpunkte. Der Album-Zyklus (`Studio::cycle/blocker`: Live-Songs und Promos) fällt weg.
- **Songs schreiben:** `Mood::songRate/crewFactor` und der Songvorrat in `Mood::run` → `Core\Songs::perDay`, jeder Song mit
  `Core\Quality::song` (kreative Kraft `Quality::creativeShare`).
- **Charts:** `Charts::albums/rerank/markTop` nach `chart_hours` (stündlich statt alle 3 Stunden), Flamme `Hype::flame`.
  `Charts::points/curve/push/payRoyalties/payCatalog/income` fallen weg; Geld kommt aus `Career::settle`.
- **Auszeichnungen:** `Awards::sales` mit `sold + catalog_sold` und neuen Grenzen (Frage 16), `Balance::AWARDS['copies']` entfällt.
- **Ereignisse:** `Festival::start` und `Battle::draw` → `Career::event` (Zuschauer, L, Schub nach Bühne bzw. Platz), Battle-Preise
  aus `CORE_BATTLE_PRIZES`. Festival-Bühnen nach Fans statt `fame_days`.
- **Anfragen:** Interviews in `Inbox` werden zu Anfragen (Interview, Fotoserie, Artikel, Reportage, Werbung, `CORE_EVENTS['requests']`),
  `Inbox::settle` → `Career::boost` bzw. `Career::ad`. Promo und Band-Promo (`Activities::promo/bandPromo/promoFame`) fallen weg.
- **Wer heute Ruhm liest, liest Fans bzw. die Ruhmstufe:** Studio-Laune (`Studio::claimGap`), Einsendungen und Preise
  (`Submissions::success/topUp/price`, `Scouting`), Bewerbungen (`Inbox::quality/jobRates`), Tournee-Freigaben (`Tours::options`),
  Künstlername und Charakterzüge (`Mood` 317, `Traits` 105), `Transfers::value`, `Present`, `State`, `Wiki`, `Names::bandTaken`
  (Namensschutz), Label-Bestenliste (`Charts::labels`: Summe der Fans), Fan-Lieblinge und Sound-Monster statt `hallOfFame`.
- **Band-Änderungen:** `Bands::removeMember/merge/rename/giveUpName` → `Fans::leave/merge/renamed`; Musiker-Bekanntheit = Fans der Band.
- **Bedürfnisse:** `Mood::waitWhy/idleWhy` → `Core\Needs::penalty/appears` (alle 24 Stunden, Auftauchen kostet nichts).

## Schritt 5: Können und Übergang (neuer Eintrag nach #55)

- Können neu: `SKILL_LEVELS` → `CORE_SKILL['levels']`; `Levels`, `Gear::playable/gap`, Probe-Zuwachs (50 je Stunde), Startwerte in
  `Generator`, `Submissions`, `Scouting`, `Scout`; Patzer-Chance in `Performance`; Noten in `Etude`/`Minigames`; `Courses`, `Tutors`,
  `Jam` (`maxSkill`); Sound-Erwartungen der Orte.
- Übergang für alle Labels (echtes Spiel und Testversion; anders als `Transition::FIRST_NEW` heute): 0 Fans, Können über
  `Core\Skill::fromLegacy`, 5.000 ₲, eigenes Instrument höchstens in der Farbe der neuen Stufe (`Skill::keptGear`), Lager verfällt,
  Gutscheine (`Skill::voucher`), alter Ruhm bleibt als Erinnerung (WikiSignia, Chronik), „Was ist neu?“ über `labels.transition`.

## Schritt 6: Oberfläche

`core.js` (Geld tickt aus dem neuen Fluss), `main.js` (Ergebnis- und Finanz-Fenster), `views/label.js` (Kacheln mit Sternen nach Fans,
Knopf „Auftreten: etwa … Leute, rund … ₲“ mit i, Buchungsfenster ersetzen), `views/bands.js` (alte Auftritt-Seite und Gage-Texte),
`views/charts.js` (Flammen, Countdown zur vollen Stunde, Label-Bestenliste, Fan-Lieblinge, Sound-Monster), `views/recording.js`
(Qualität in vier Teilen, Songs auswählen), `views/overview.js`, `cards.js`, `celebrate.js`, `awards.js`, `tour.js`, kleinere Stellen in
`views/together.js`, `views/musician.js`, `views/wiki.js`, `views/submissions.js`, `views/festival.js`, `views/battle.js`, `views/storage.js`.

## Schritt 7: Hilfe und Doku

`views/help.js` (Tour und Themen), `views/transition.js` („Was ist neu?“), Erklär-Fenster in den Ansichten, Texte in `Catalog`
(`FAME_NAMES`, `PERSONALITY_INFO`, `MANAGER_SKILLS`, `GEAR_BONUS_INFO`), `docs/kern.md`, `docs/label-konzept.md`.

## Schritt 8: Tournee

`Tours.php`: Freigaben nach Ruhmstufe, Orte über `Concert::venue(…, tour: true)`, Verdienst auf Tournee und neue Leute in jedem Land
(beides noch zu rechnen).

## Beim Durchsehen gefunden (Aufräumen beim Einbau)

- `records.money_reward`, `records.fame_reward`, `records.rank_hour` und `bands.promo_record_id` werden geschrieben, aber nie gelesen.
- `State.php` 82 zeigt die Qualität der Diskografie aus den alten `song_results` (bei neuen Platten 0).
- Veraltete Texte: `views/charts.js` 34 (Scout-Gutschein für Platz 1), `views/bands.js` 227 (Minispiele im Studio), tote Texte zu
  Band-Plätzen nach Label-Ruhm in `views/musician.js` 142/195 und `views/bands.js` 609; Kommentar in `Balance.php` 697 („× Ruhm“).
- Tests, die das alte System prüfen: `tests/FlowTest.php` (viel), `tests/RulesTest.php` (einiges) – beim Einbau mit umstellen.
