# Einbau des neuen Kerns – Checkliste

Stand: eingebaut und in der Testversion eingeschaltet (Sandra, Frage 17). `public/api.php` setzt `BandCore::$live = true`; Schema-Schritt 58
hat die Test-Spielstände einmal umgestellt (`CoreTransition::run`). `main` bleibt beim alten Spiel, bis Sandra „veröffentlichen“ sagt.
Die Regeln stehen in `docs/kern.md` (Abschnitt „Neuer Kern: Fans und Hype“), die Herleitung im Dokument „Signed – Rechenwege“.

Das Rechenwerk liegt in `src/Game/Core` (Fans, Hype, Sales, Concert, Events, Career, Quality, Songs, Needs, Skill), die Zahlen in
`Balance::CORE_*`, die Tests in `tests/CoreTest.php` (Rechenwerk) und `tests/BandCoreTest.php` (im Spiel, Schalter an). Die übrigen Tests
laufen mit Schalter aus (`tests/support.php` setzt ihn vor jedem Test zurück) und prüfen weiter das alte Spiel.

## Grundsätze (umgesetzt)

- `Career` gibt die Reihenfolge vor: vor jeder Handlung alles bis jetzt weiterrechnen (`BandCore::settle`), alle Platten einer Band im
  Gleichschritt. Gespeichert werden dieselben Werte: `bands` (fans, fresh, last_gig_at, core_at, money_carry), `records.core` (JSON aus
  `Core\Sales`, solange die Platte verkauft) und `records.core_active`.
- Kein Cron: Weitergerechnet wird je Anfrage in `Api::handle` (`BandCore::settleLabel`). Für die Charts rechnet `Charts::coreAlbums` einmal
  je voller Stunde alle Bands mit laufenden Platten bis zur vollen Stunde (`chart_hours`, nach 48 Stunden aufgeräumt); sortiert wird jede
  Minute und nach jeder neuen Platte aus dem Gespeicherten (`Charts::coreRerank`). Alle Bands jede Minute weiterzurechnen kostet bei
  30 Labels etwa 0,4 Sekunden, bei 100 Labels über eine Sekunde – darum nur stündlich.
- Alle Werte als Kommazahlen speichern, erst in der Anzeige runden. SQL für SQLite und MySQL.

## Erledigt

- **Datenbank:** Schritt 55 (Fans, Frische, Zustand der Platten, `songs`, `chart_hours`), 56 (Regler fürs Songschreiben), 57 (`prizes`:
  Battle-Preise), 58 (Umstellung der Spielstände).
- **Auftreten:** ein Knopf, Ort wählt das Spiel, keine Kosten, Verdienst je Zuschauer, Frische; Manager (Verhandler, PR-Profi).
- **Aufnehmen:** 12 Songs aus dem Vorrat, vier Produktionsstufen, Qualität aus Sound, Songs, Produktion und Stimmung; kein Erstverkauf,
  kein Album-Zyklus.
- **Songs:** einzeln mit Qualität (`SongStock`), Tempo nach `Core\Songs`, Regler schnell / normal / sorgfältig auf der Bandseite.
- **Charts:** Verkäufe der letzten 24 Stunden, jede Minute neu, neue Platten sofort; Label-Bestenliste (Fans), Fan-Lieblinge, Sound-Monster; Flammen.
- **Geld:** Plattenverkäufe und Katalog laufend (`earnDaily`), Werbung, Konzerte; Startgeld 5.000 ₲.
- **Auszeichnungen:** Gold, Platin, Diamant nach 5.000 / 20.000 / 50.000; Platz 1; Halle und Arena (auf Tournee).
- **Ereignisse:** Festival (Bühnen nach Fans, Zuschauer, Schub), Battle (Zuschauer, Schub, Preise, Wochenpreis mit Gutscheinen).
- **Anfragen:** statt Interviews und Promo-Tour, nur mit Platte; dazu Anfragen nach Ereignissen mit Bezug im Brief.
- **Band-Änderungen:** Austritt, Zusammentun, Umbenennen, Auflösen nach den Fan-Regeln; Musiker-Bekanntheit = Ruhm-Gegenwert der Fans.
- **Bedürfnisse:** wachsen nur alle 24 Stunden, Auftauchen kostet nichts.
- **Können:** neues Maß (100 / 400 / 1.600, nach oben offen), Proben 50 je Stunde; gespeichert bleibt die alte Skala (`Levels::shown`).
- **Tournee:** Europa ab 5.000 Fans, Kontinente ab 50.000, Welttournee geparkt; Auftritte wie zu Hause mit Halle und Arena.
- **Übergang:** 0 Fans, 5.000 ₲, Instrumente in der Farbe der Können-Stufe, Lager und Fan-Karten verfallen, Tourneen enden, alter Ruhm als
  Erinnerung, Songs mit Qualität, „Was ist neu?“.
- **Oberfläche, Hilfe, Doku:** Bandseite, Übersicht, Charts, Aufnahme, Ergebnisse, Hilfe und Einführung, WikiSignia, Festival, Battle,
  Zusammentun, Umbenennen, Musikerkarten; `docs/kern.md`.

## Noch offen

- **Durchspielen:** Die Zahlen sind gerechnet und getestet, aber noch nicht über Wochen gespielt. Neue Leute auf Tournee sind nicht eingerechnet.
- **Welttournee** (Stadion, Globus über die Welttournee): geparkt.
- **Alte Wege im Code:** Sie laufen nur noch mit Schalter aus (Tests). Beim Veröffentlichen aufräumen: alte Zweige in `Activities`, `Studio`,
  `Charts`, `Inbox`, `Festival`, `Battle`, `Bands`, `Tours`, `Gigs`, `BandCalc` (Ruhm, Gage, Tantiemen, Promo), die alten Tests in
  `tests/FlowTest.php` und `tests/RulesTest.php`, `Balance`-Werte des alten Spiels.
- **Alte Seiten:** Die Unterseiten einer Band (`#/band/…/auftritte`, `/promo`, `/studio`) und die Musikerseite (`#/musician/…`) sind nicht
  mehr verlinkt, aber per Adresse noch erreichbar und zeigen teils alte Texte; was dort nicht mehr geht, lehnt der Server ab.
- **Aufräumen:** `records.money_reward`, `records.fame_reward`, `records.rank_hour` und `bands.promo_record_id` werden nicht mehr gebraucht.
