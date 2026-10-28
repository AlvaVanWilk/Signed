# Signed

Ein Musik-Spiel im Browser: Du führst ein Plattenlabel. Musiker schicken Kassetten, du nimmst sie unter Vertrag,
stellst Bands zusammen und sagst ihnen, was sie tun: proben, auftreten, Platten aufnehmen, auf Tournee gehen.
Die Bands haben ein Eigenleben, mit Stimmung, Wünschen und Beziehungen. Jede Woche gibt es ein Festival und das
Battle of the Bands, und alle Labels spielen um dieselben Charts.

Das Konzept steht in [docs/label-konzept.md](docs/label-konzept.md), die Regeln und Formeln in
[docs/kern.md](docs/kern.md). Ursprung ist das Claude-Doc „Signed – Spielkonzept“.

## Technik

- **Server:** PHP 8.1 oder neuer, ohne Framework und ohne Composer-Pakete.
- **Datenbank:** SQLite (Standard, eine Datei) oder MySQL/MariaDB.
- **Browser:** HTML, CSS und JavaScript-Module ohne Build-Schritt. Musik: echte Aufnahmen je Genre und Stufe
  (`public/assets/music/`), wo noch keine da sind, der Browser-Synthesizer.

Damit läuft das Spiel auf praktisch jedem gewöhnlichen Webspace.

## Lokal starten

```bash
php -S localhost:8000 -t public
```

Dann <http://localhost:8000> öffnen. Die Datenbank `data/signed.sqlite` entsteht beim ersten Aufruf von selbst.

## Tests

```bash
php tests/run.php
```

Die Tests prüfen die Spielregeln und spielen den kompletten Kernkreislauf über die API durch
(mit vorgespulter Uhr, also in wenigen Sekunden).

## Auf den Webspace bringen (IONOS, automatisch)

Bei jeder Änderung prüft GitHub den Code und lädt ihn per SFTP hoch
(`.github/workflows/pruefen-und-hochladen.yml`):

- `main` → der echte Ordner (`IONOS_SFTP_PATH`, z. B. `/signed`)
- jeder andere Zweig → der Testordner mit „-test“ (z. B. `/signed-test`, eigene Datenbank)

Einmalig einrichten:

1. Unter **Settings → Secrets and variables → Actions** die Geheimnisse `IONOS_SFTP_HOST`,
   `IONOS_SFTP_USER`, `IONOS_SFTP_PASSWORD` und `IONOS_SFTP_PATH` anlegen.
2. Bei IONOS PHP 8.1 oder neuer einstellen.
3. Eine Adresse (Domain, Subdomain oder Unterordner) auf den Ordner zeigen lassen. Die `.htaccess` im
   Projektordner leitet alles nach `public/` weiter; `src/`, `data/` und `config.php` bleiben unerreichbar.

Die Datenbank `data/signed.sqlite` entsteht beim ersten Aufruf und wird beim Hochladen nie überschrieben.
Anderer Server: Ordner ohne `tests/`, `docs/`, `.git` hochladen, `data/` beschreibbar machen, optional
`config.sample.php` nach `config.php` kopieren. PHP-Erweiterungen: `pdo_sqlite` (oder `pdo_mysql`), `mbstring`.

## Aufbau

```
public/            Webroot: index.html, api.php, assets/ (CSS, JS, Bilder)
src/               PHP: API, Datenbank, Sitzung
src/Game/          Spielregeln: Balance (alle Zahlen), Catalog (feste Daten), Generator, BandCalc, …
tests/             Test-Runner und Tests
docs/kern.md       Umfang, Regeln und Formeln
docs/label-konzept.md        Konzept des Label-Spiels, Offenes und Geparktes
docs/ausgelagert-musikerspiel.md   was aus Signed herausgenommen wurde
```
