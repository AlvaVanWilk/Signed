# Signed – Hinweise für Claude Code

Musik-Spiel auf Label-Ebene (Web-App). Konzept: Claude-Doc „Signed – Spielkonzept“. Umfang und Formeln des heutigen Spiels: `docs/kern.md`.
**Im Umbau:** Neues Konzept in `docs/label-konzept.md` (Bands mit Eigenleben, Stimmung, Beziehungen), Ausgelagertes in `docs/ausgelagert-musikerspiel.md`.
Es wird nur in der Testversion gebaut; `main` (das echte Spiel) bleibt unverändert, bis der Umbau fertig ist und Sandra „veröffentlichen“ sagt.

## Rahmen

- Läuft auf einem gewöhnlichen Webspace: PHP 8.1+, SQLite oder MySQL, kein Composer, kein Build-Schritt.
  Keine Abhängigkeiten einführen, die das ändern (kein Node auf dem Server, keine CDNs, keine Google Fonts).
- Spieltexte und Kommentare auf Deutsch, Bezeichner im Code auf Englisch.
- Alle Spielzahlen gehören nach `src/Game/Balance.php`, feste Daten nach `src/Game/Catalog.php`.
  Wenn sich Formeln ändern, `docs/kern.md` mitpflegen.

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
