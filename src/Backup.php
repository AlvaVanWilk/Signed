<?php
declare(strict_types=1);

namespace Signed;

/**
 * Tägliche Sicherung des Spielstands nach data/backups/ (vom Web aus gesperrt, siehe data/.htaccess).
 * Läuft beim ersten Aufruf eines Tages mit; behalten werden die letzten KEEP Tage.
 * SQLite: konsistente Kopie per VACUUM INTO. MySQL: alle Tabellen als JSON (gzip).
 * Der Workflow „Backup abholen“ lädt die Dateien zusätzlich täglich herunter (GitHub-Artefakt).
 */
final class Backup
{
    private const KEEP = 14;

    public static function daily(Db $db, ?string $dir = null): ?string
    {
        $dir ??= dirname(__DIR__) . '/data/backups';
        $day = gmdate('Y-m-d', Clock::now());
        $last = $db->value("SELECT v FROM meta WHERE k = 'backup_day'");
        if ($last === $day) {
            return null;
        }
        // Nur ein Aufruf sichert: wer den Tag zuerst einträgt.
        if ($last === null) {
            $db->exec("INSERT INTO meta (k, v) VALUES ('backup_day', ?)", [$day]);
        } elseif ($db->exec("UPDATE meta SET v = ? WHERE k = 'backup_day' AND v = ?", [$day, $last]) === 0) {
            return null;
        }
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return null;
        }
        try {
            $file = $db->driver() === 'sqlite' ? self::sqlite($db, $dir, $day) : self::mysql($db, $dir, $day);
        } catch (\Throwable) {
            // Eine fehlgeschlagene Sicherung darf das Spiel nie aufhalten.
            return null;
        }
        $files = glob("$dir/signed-*") ?: [];
        sort($files);
        foreach (array_slice($files, 0, max(0, count($files) - self::KEEP)) as $old) {
            @unlink($old);
        }
        return $file;
    }

    private static function sqlite(Db $db, string $dir, string $day): string
    {
        $file = "$dir/signed-$day.sqlite";
        @unlink($file);
        $db->raw('VACUUM INTO ' . $db->quote($file));
        return $file;
    }

    private static function mysql(Db $db, string $dir, string $day): string
    {
        $file = "$dir/signed-$day.json.gz";
        $out = gzopen($file, 'w6');
        gzwrite($out, '{');
        foreach ($db->all('SHOW TABLES') as $i => $row) {
            $table = (string) reset($row);
            gzwrite($out, ($i ? ',' : '') . json_encode($table) . ':' . json_encode($db->all("SELECT * FROM `$table`"), JSON_UNESCAPED_UNICODE));
        }
        gzwrite($out, '}');
        gzclose($out);
        return $file;
    }
}
