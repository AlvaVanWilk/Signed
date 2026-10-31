# Signed – Hinweise für Claude Code

Musik-Spiel auf Label-Ebene (Web-App): Bands mit Eigenleben, Stimmung, Beziehungen. Konzept, Offenes und Geparktes: `docs/label-konzept.md`.
Regeln und Formeln: `docs/kern.md`. Was herausgenommen wurde (Minispiele, Packs, Börse …): `docs/ausgelagert-musikerspiel.md`.
Neuer Kern (Fans und Hype statt Ruhm): Rechenwerk in `src/Game/Core`, getestet, noch nicht eingebaut; Checkliste `docs/kern-einbau.md`.
**Im Umbau:** Es wird nur in der Testversion gebaut; `main` (das echte Spiel) bleibt unverändert, bis der Umbau fertig ist und Sandra
„veröffentlichen“ sagt. Alte Labels bekommen beim Umbau einen einmaligen Übergang (`Transition`, Schema-Schritt 53).

## Rahmen

- Läuft auf einem gewöhnlichen Webspace: PHP 8.1+, SQLite oder MySQL, kein Composer, kein Build-Schritt.
  Keine Abhängigkeiten einführen, die das ändern (kein Node auf dem Server, keine CDNs, keine Google Fonts).
- Spieltexte und Kommentare auf Deutsch, Bezeichner im Code auf Englisch.
- Alle Spielzahlen gehören nach `src/Game/Balance.php`, feste Daten nach `src/Game/Catalog.php`.
  Wenn sich Formeln ändern, `docs/kern.md` mitpflegen.
- Zeit: Tag, Stunde, Minute und Woche heißen immer echte Zeit. Zeit im Spiel heißt Signie-Day, Signie-Hour, Signie-Minute,
  Signie-Month und Signie-Year (1 Signie-Day = 1 echte Stunde, 1 Signie-Year = 1 echte Woche) und kommt in keiner Rechnung vor.
  Der neue Kern rechnet Hype und Plattenverkäufe je echtem Tag, Konzerte einzeln je Auftritt. Jede Dauer gegenüber Sandra
  in echter Zeit nennen.

## Architektur

- `public/api.php` → `src/Api.php`: eine Aktion pro Anfrage (`api.php?a=…`). POST braucht den Header
  `X-Signed: 1` (CSRF-Schutz). Verändernde Aktionen liefern den neuen Spielstand (`State::build`) mit.
- Der Server ist maßgeblich: Zufall, Geld, Ruhm und Auftrittsergebnisse werden dort berechnet.
  Automatische Auftritte werden beim Start simuliert und nach Ablauf gutgeschrieben (`Activities::settleDue`).
- SQL muss auf SQLite und MySQL laufen. Schemaänderungen nur als neuer Eintrag am Ende von
  `Schema::migrations()`, bestehende Einträge nie ändern.
- Browser: `public/assets/js/` mit ES-Modulen. `core.js` hält den Zustand, Aktionen laufen über
  `data-act` / `data-form` / `data-change` und `defineActions`.

## Spielbalance – unbedingt beachten

- **Keine künstliche Bremse.** Die alte Stufen-Bremse (`LEVEL_PACE`) ist im Umbau entfernt. Das Tempo soll sich aus gut
  abgestimmten Gewinnen, Kosten, Schwellen und der Lernkurve ergeben. Schaukelt sich etwas auf oder geht es zu schnell,
  ist das ein Fehler in den Formeln und wird dort behoben, nicht mit einer Bremse. Grundsatz: Ruhm darf nie den eigenen
  Zuwachs verstärken. Neue Inhalte und Zahlen so wählen, dass alle Stufen mit Einsatz erreichbar sind, und das in
  `docs/kern.md` (Abschnitt Tempo) festhalten.

## Musik

- Aufnahmen liegen in `public/assets/music/<genre>-<stufe>/` und werden lokal mit `tools/musik-import.php` (ffmpeg)
  aus Logic-WAVs und MIDI erzeugt. Zuordnung der Spuren: `src/Game/Music.php`, Abspielen: `public/assets/js/music.js`.

## Prüfen

- `php tests/run.php` – muss grün sein.
- Gegen MySQL/MariaDB: `SIGNED_TEST_MYSQL="mysql:host=127.0.0.1;;benutzer;;passwort" php tests/run.php` (legt die Datenbank `signed_test` jedes Mal neu an).
- `php -S localhost:8000 -t public` zum Ausprobieren.
