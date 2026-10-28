<?php
declare(strict_types=1);

namespace Signed\Game;

use DateTimeImmutable;
use DateTimeZone;
use Signed\ApiError;
use Signed\Db;

/**
 * Veranstaltungen für alle Labels, einmal je Woche (Battle of the Bands am Samstag, später das Festival am Mittwoch).
 * Je Art und Woche gibt es eine Zeile in `events`; Bands melden sich in `event_entries` an. Begonnen und abgeschlossen wird
 * nebenbei bei der nächsten Anfrage (tick). Was dabei herauskommt, steht dann fest und wird nach der Uhr gezeigt.
 */
final class Events
{
    private const ZONE = 'Europe/Berlin';
    private const DAYS = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    /** Wie die Veranstaltung in Sätzen heißt („für … angemeldet“). */
    private const NAMES = ['battle' => 'das Battle of the Bands', 'festival' => 'ein Festival'];

    /** Woche in deutscher Zeit, z. B. „2026-W44“. */
    public static function week(int $t): string
    {
        return (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone(self::ZONE))->format('o-\WW');
    }

    /** Beginn in einer Woche: Wochentag (ISO, 1 = Montag) und Uhrzeit [Stunde, Minute] in deutscher Zeit. */
    public static function startOf(string $week, int $weekday, array $time): int
    {
        [$year, $number] = array_map('intval', explode('-W', $week));
        return (new DateTimeImmutable('now', new DateTimeZone(self::ZONE)))
            ->setISODate($year, $number, $weekday)->setTime((int) $time[0], (int) $time[1])->getTimestamp();
    }

    /** „Samstag, 31.10., 16 Uhr“ */
    public static function when(int $t): string
    {
        $d = (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone(self::ZONE));
        $clock = $d->format('i') === '00' ? $d->format('G') . ' Uhr' : $d->format('G:i') . ' Uhr';
        return sprintf('%s, %s, %s', self::DAYS[(int) $d->format('N') - 1], $d->format('j.n.'), $clock);
    }

    /**
     * Die nächste Veranstaltung dieser Art, für die man sich noch anmelden kann (legt sie an, wenn es sie noch nicht gibt).
     * Mit `close` in $cfg schließt die Anmeldung am selben Tag schon früher (Festival).
     * @param array{weekday: int, time: array, close?: array} $cfg
     */
    public static function next(Db $db, string $kind, array $cfg, int $now): array
    {
        $week = self::week($now);
        $close = self::startOf($week, $cfg['weekday'], $cfg['close'] ?? $cfg['time']);
        $row = $db->one('SELECT * FROM events WHERE kind = ? AND week = ?', [$kind, $week]);
        // Anmeldung schon zu (auch wenn die Uhrzeit inzwischen anders eingestellt ist): dann die nächste Woche.
        if ($close <= $now || ($row !== null && ($row['state'] !== 'open' || self::closesAt($row) <= $now))) {
            $week = self::week($close + 7 * 86400);
            $row = $db->one('SELECT * FROM events WHERE kind = ? AND week = ?', [$kind, $week]);
        }
        if ($row === null) {
            $start = self::startOf($week, $cfg['weekday'], $cfg['time']);
            try {
                $db->insert('events', [
                    'kind' => $kind, 'week' => $week, 'starts_at' => $start, 'state' => 'open',
                    'closes_at' => isset($cfg['close']) ? self::startOf($week, $cfg['weekday'], $cfg['close']) : 0,
                ]);
            } catch (\PDOException) {
                // Gleichzeitig von einer anderen Anfrage angelegt.
            }
            $row = $db->one('SELECT * FROM events WHERE kind = ? AND week = ?', [$kind, $week]);
        }
        return $row;
    }

    /** Anmeldeschluss: beim Battle der Beginn, beim Festival früher am Tag. */
    public static function closesAt(array $event): int
    {
        return (int) ($event['closes_at'] ?? 0) ?: (int) $event['starts_at'];
    }

    /** Die zuletzt begonnene Veranstaltung dieser Art (läuft, ist vorbei oder fiel aus), sonst null. */
    public static function latest(Db $db, string $kind, int $now): ?array
    {
        return $db->one("SELECT * FROM events WHERE kind = ? AND starts_at <= ? AND state <> 'open' ORDER BY starts_at DESC LIMIT 1", [$kind, $now]);
    }

    /** Anmeldungen mit Band- und Labelnamen, in der Reihenfolge des Eingangs. */
    public static function entries(Db $db, int $eventId): array
    {
        return $db->all(
            "SELECT x.*, b.name AS band_name, l.name AS label_name FROM event_entries x
             JOIN bands b ON b.id = x.band_id JOIN labels l ON l.id = x.label_id
             WHERE x.event_id = ? AND x.status = 'in' ORDER BY x.created_at, x.id",
            [$eventId]
        );
    }

    /** Bei jeder Anfrage: fällige Veranstaltungen beginnen und abschließen. */
    public static function tick(Db $db, int $now): void
    {
        $due = $db->all(
            "SELECT id, kind FROM events WHERE (state = 'open' AND (starts_at <= ? OR (closes_at > 0 AND closes_at <= ?)))
             OR (state = 'lineup' AND starts_at <= ?) OR (state = 'running' AND ends_at <= ?) ORDER BY starts_at",
            [$now, $now, $now, $now]
        );
        foreach ($due as $row) {
            match ($row['kind']) {
                Battle::KIND => Battle::advance($db, (int) $row['id'], $now),
                Festival::KIND => Festival::advance($db, (int) $row['id'], $now),
                default => null,
            };
        }
    }

    /**
     * Angemeldete Bands müssen pünktlich da sein: Was über den Beginn hinaus dauern würde, geht nicht.
     * Für Bands und für Musiker einer Band (deren Band-ID).
     */
    public static function assertFree(Db $db, ?int $bandId, int $endsAt): void
    {
        if ($bandId === null) {
            return;
        }
        $row = $db->one(
            "SELECT e.kind, e.starts_at FROM event_entries x JOIN events e ON e.id = x.event_id
             WHERE x.band_id = ? AND x.status IN ('in', 'accepted') AND e.state IN ('open', 'lineup') AND e.starts_at < ? ORDER BY e.starts_at LIMIT 1",
            [$bandId, $endsAt]
        );
        if ($row !== null) {
            throw new ApiError(sprintf(
                'Die Band ist für %s angemeldet (%s) und muss bis dahin zurück sein. Das hier würde zu lange dauern.',
                self::NAMES[$row['kind']] ?? 'eine Veranstaltung',
                self::when((int) $row['starts_at'])
            ));
        }
    }

    /** Für welche Veranstaltungen die Bands eines Labels angemeldet oder schon genommen sind (Band-ID => Liste [kind, startsAt, accepted]). */
    public static function registrations(Db $db, int $labelId): array
    {
        $out = [];
        foreach ($db->all(
            "SELECT x.band_id, x.status, e.kind, e.starts_at FROM event_entries x JOIN events e ON e.id = x.event_id
             WHERE x.label_id = ? AND x.status IN ('in', 'accepted') AND e.state IN ('open', 'lineup') ORDER BY e.starts_at",
            [$labelId]
        ) as $row) {
            $out[(int) $row['band_id']][] = ['kind' => $row['kind'], 'startsAt' => (int) $row['starts_at'], 'accepted' => $row['status'] === 'accepted'];
        }
        return $out;
    }
}
