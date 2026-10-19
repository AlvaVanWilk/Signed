# Signed – Hinweise für Claude Code

Musik-Sammelkartenspiel (Web-App). Konzept: Claude-Doc „Signed – Spielkonzept“. Umfang und Formeln: `docs/kern.md`.

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

## Prüfen

- `php tests/run.php` – muss grün sein.
- `php -S localhost:8000 -t public` zum Ausprobieren.
