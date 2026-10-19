# Signed

Ein Musik-Sammelkartenspiel im Browser: Du bist ein Label, öffnest Packs mit einmaligen Musikern,
gründest Bands, produzierst Platten, spielst Gigs und kämpfst mit anderen Labels um die Charts.

Das Spielkonzept steht im Claude-Doc „Signed – Spielkonzept“. Was davon schon gebaut ist und nach
welchen Formeln das Spiel rechnet, steht in [docs/kern.md](docs/kern.md).

## Technik

- **Server:** PHP 8.1 oder neuer, ohne Framework und ohne Composer-Pakete.
- **Datenbank:** SQLite (Standard, eine Datei) oder MySQL/MariaDB.
- **Browser:** HTML, CSS und JavaScript-Module ohne Build-Schritt. Die Musik im Minispiel ist vorerst
  ein Platzhalter aus dem Browser-Synthesizer.

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

## Auf den Webspace bringen

1. Den ganzen Ordner hochladen (per FTP oder Git), **ohne** `data/signed.sqlite` und `tests/`.
2. Die Domain bzw. Subdomain auf den Ordner `public/` zeigen lassen. So liegen `src/`, `data/` und
   `config.php` außerhalb des Webroots.
   - Geht das beim Anbieter nicht, schützen die mitgelieferten `.htaccess`-Dateien `src/`, `data/`,
     `docs/` und `tests/` (nur Apache). Das Spiel ist dann unter `…/public/` erreichbar.
3. Der Ordner `data/` muss für PHP beschreibbar sein.
4. Optional `config.sample.php` nach `config.php` kopieren, z. B. für MySQL oder einen anderen
   Speicherort der SQLite-Datei.
5. HTTPS einschalten (bei fast allen Anbietern ein Klick), damit Passwörter verschlüsselt übertragen werden.

Benötigte PHP-Erweiterungen: `pdo_sqlite` (oder `pdo_mysql`), `mbstring`, `json`, `session`.

## Aufbau

```
public/            Webroot: index.html, api.php, assets/ (CSS, JS, Bilder)
src/               PHP: API, Datenbank, Sitzung
src/Game/          Spielregeln: Balance (alle Zahlen), Catalog (feste Daten), Generator, BandCalc, …
tests/             Test-Runner und Tests
docs/kern.md       Umfang des Kerns, Formeln und vorläufige Entscheidungen
```
