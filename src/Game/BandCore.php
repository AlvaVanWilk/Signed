<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Clock;
use Signed\Db;
use Signed\Game\Core\Career;
use Signed\Game\Core\Fans;
use Signed\Game\Core\Sales;

/**
 * Bindeglied zwischen dem neuen Kern (src/Game/Core) und der Datenbank: lädt den Stand einer Band (Fans, Frische, laufende Platten),
 * rechnet ihn bis jetzt weiter, bucht Geld, Verkäufe und Chart-Stunden und speichert ihn wieder.
 * Läuft nur, wenn der Schalter an ist; bis der Umbau fertig ist, rechnet das Spiel wie bisher (siehe docs/kern-einbau.md).
 */
final class BandCore
{
    /** Schalter für den Umbau. Aus: das bisherige Spiel (Testversion unverändert). An: Fans und Hype. */
    public static bool $live = false;

    /** Art der Band fürs Dach: gemischt, Genre, Hybrid, Crossover; Solokünstler mit Pop oder Elektro wie Genre, mit beidem wie Hybrid. */
    public static function kind(array $band, array $members): string
    {
        if (!$members) {
            return 'mixed';
        }
        $identity = BandCalc::identity($members, $band['genre'], $band['genre2'] ?? null);
        if (count($members) === 1) {
            $solo = array_filter(Balance::SOLO_GENRES, static fn ($g) => $identity['checks'][$g]['fans'] === 1 && $identity['checks'][$g]['lineup']);
            return ['mixed', 'genre', 'hybrid'][min(2, count($solo))];
        }
        return match (true) {
            $identity['third'] !== null => 'crossover',
            $identity['second'] !== null => 'hybrid',
            $identity['primary'] !== null => 'genre',
            default => 'mixed',
        };
    }

    /** Stand einer Band aus der Datenbank: Fans, Frische und die Platten, die noch verkaufen (neueste zuletzt). */
    public static function load(Db $db, array $band, string $kind): array
    {
        $row = $db->one('SELECT fans, fresh, last_gig_at, core_at FROM bands WHERE id = ?', [$band['id']]);
        $career = Career::start((float) $row['fans'], $kind, (int) $row['core_at']);
        $career['fresh'] = (float) $row['fresh'];
        $career['lastGig'] = $row['last_gig_at'] === null ? null : (int) $row['last_gig_at'];
        foreach ($db->all('SELECT id, core FROM records WHERE band_id = ? AND core_active = 1 ORDER BY finished_at, id', [$band['id']]) as $r) {
            $career['records'][] = ['id' => (int) $r['id']] + (json_decode((string) $r['core'], true) ?: []);
        }
        return $career;
    }

    /**
     * Rechnet eine Band bis $until weiter und speichert sie: Stunde für Stunde, damit die Charts die Verkäufe je Stunde kennen.
     * Liefert den neuen Stand (für weitere Handlungen im selben Aufruf).
     */
    public static function settle(Repo $repo, array $band, int $until, ?array $members = null): array
    {
        $members ??= $repo->members($band['id']);
        $career = self::load($repo->db, $band, self::kind($band, $members));
        return self::advance($repo, $band, $career, $until);
    }

    /** Alle Bands des Labels bis jetzt (bei jedem Aufruf des Spiels, statt der alten Tantiemen). */
    public static function settleLabel(Repo $repo, ?int $until = null): void
    {
        $until ??= Clock::now();
        foreach ($repo->bands() as $band) {
            self::settle($repo, $band, $until);
        }
    }

    /** Alle Bands mit laufenden Platten, über alle Labels (vor den Charts, damit die Verkäufe der letzten Stunden stimmen). */
    public static function settleAll(Db $db, ?int $until = null): void
    {
        $until ??= Clock::now();
        foreach ($db->all('SELECT DISTINCT label_id FROM records WHERE core_active = 1') as $row) {
            self::settleLabel(new Repo($db, (int) $row['label_id']), $until);
        }
    }

    /** Neue Platte erscheint (beim Fertigwerden im Studio); die vorige läuft von selbst aus. */
    public static function release(Repo $repo, array $band, int $recordId, float $quality, ?int $at = null): array
    {
        $at ??= Clock::now();
        $career = self::settle($repo, $band, $at);
        $career = Career::release($career, $at, $quality);
        $career['records'][count($career['records']) - 1]['id'] = $recordId;
        return self::save($repo, $band, $career, []);
    }

    /**
     * Auftritt beginnt: Zuschauer, Verdienst und neue Fans stehen fest, die Frische ist verbraucht ($live = Live-Qualität 0–100).
     * Geld und Fans bucht finishGig, wenn der Auftritt vorbei ist.
     */
    public static function startGig(Repo $repo, array $band, float $live, bool $tour = false, ?int $at = null): array
    {
        $at ??= Clock::now();
        $career = self::settle($repo, $band, $at);
        [$career, $result] = Career::gigStart($career, $at, $live, $tour);
        self::save($repo, $band, $career, []);
        return $result;
    }

    /** Auftritt vorbei: neue Fans gutschreiben (das Geld bucht der Aufrufer mit seinem Text). */
    public static function finishGig(Repo $repo, array $band, float $newFans, ?int $at = null): array
    {
        $at ??= Clock::now();
        return self::save($repo, $band, Career::addFans(self::settle($repo, $band, $at), $at, $newFans), []);
    }

    /** Was ein Auftritt jetzt bringen würde (für den Knopf), ohne etwas zu ändern. */
    public static function preview(Repo $repo, array $band, bool $tour = false, ?int $at = null): array
    {
        $at ??= Clock::now();
        $career = self::settle($repo, $band, $at);
        return Career::preview($career, $at, $tour);
    }

    /** Hype-Schub für die neueste Platte (Anfragen, Battle-Platz). */
    public static function boost(Repo $repo, array $band, float $amount, ?int $at = null): array
    {
        $at ??= Clock::now();
        return self::save($repo, $band, Career::boost(self::settle($repo, $band, $at), $at, $amount), []);
    }

    /**
     * Ereignis mit Publikum (Festival-Bühne, ein Battle-Spiel): neue Fans aus den Zuschauern, danach der Schub.
     * @return float neue Fans
     */
    public static function event(Repo $repo, array $band, float $audience, float $live, float $boost = 0.0, ?int $at = null): float
    {
        $at ??= Clock::now();
        [$career, $gained] = Career::event(self::settle($repo, $band, $at), $at, $audience, $live, $boost);
        self::save($repo, $band, $career, []);
        return $gained;
    }

    /** Werbung: Schub wie jede Anfrage; liefert das Geld (so viele ₲, wie die Band Fans hat), gebucht vom Aufrufer. */
    public static function ad(Repo $repo, array $band, ?int $at = null): int
    {
        $at ??= Clock::now();
        $career = Career::ad(self::settle($repo, $band, $at), $at);
        $money = (int) round($career['earned']['ads']);
        self::save($repo, $band, $career, []);
        return $money;
    }

    /** Fans einer Band (aus der Datenbank, ohne weiterzurechnen). */
    public static function fans(Db $db, int $bandId): float
    {
        return (float) ($db->value('SELECT fans FROM bands WHERE id = ?', [$bandId]) ?? 0);
    }

    /** Ruhmstufe einer Band nach ihren Fans. */
    public static function stage(Db $db, int $bandId): int
    {
        return Fans::stage(self::fans($db, $bandId));
    }

    /** Stunde für Stunde weiterrechnen, Verkäufe je Platte und Stunde mitschreiben, dann alles speichern. */
    private static function advance(Repo $repo, array $band, array $career, int $until): array
    {
        if (!$career['records'] || $career['settledAt'] <= 0) {
            $career['settledAt'] = max($career['settledAt'], $until);
            return self::save($repo, $band, $career, []);
        }
        $hours = [];
        while ($career['settledAt'] < $until) {
            $next = min($until, intdiv($career['settledAt'], 3600) * 3600 + 3600);
            $before = self::soldByRecord($career);
            $career = Career::settle($career, $next);
            $hour = intdiv($next - 1, 3600);
            foreach (self::soldByRecord($career) as $id => $sold) {
                $delta = $sold - ($before[$id] ?? $sold);
                if ($delta > 0) {
                    $hours[$id][$hour] = ($hours[$id][$hour] ?? 0) + $delta;
                }
            }
        }
        return self::save($repo, $band, $career, $hours);
    }

    /** Durch den Hype verkaufte Stück je Platte (auch der eben ganz fertig gewordenen). */
    private static function soldByRecord(array $career): array
    {
        $out = [];
        foreach ([...$career['records'], ...$career['retired']] as $r) {
            if (isset($r['id'])) {
                $out[$r['id']] = $r['sold'];
            }
        }
        return $out;
    }

    /**
     * Speichern: Band (Fans, Frische, bis wann gerechnet), Platten (Zustand, Verkäufe, Auszeichnungen), Chart-Stunden und Geld.
     * Das Geld kommt aus dem, was seit dem letzten Speichern verdient wurde; Bruchteile bleiben für das nächste Mal liegen.
     * Liefert den Stand zurück, mit geleertem Geld und ohne die fertigen Platten (damit nichts doppelt gebucht wird).
     * @param array<int, array<int, float>> $hours Platte => Stunde => verkauft durch den Hype
     */
    private static function save(Repo $repo, array $band, array $career, array $hours): array
    {
        $db = $repo->db;
        $carry = (float) $db->value('SELECT money_carry FROM bands WHERE id = ?', [$band['id']]);
        $money = $carry + $career['earned']['records'] + $career['earned']['catalog'];
        $whole = (int) floor($money);
        $db->update('bands', [
            'fans' => round($career['fans'], 4),
            'fresh' => round($career['fresh'], 6),
            'last_gig_at' => $career['lastGig'],
            'core_at' => $career['settledAt'],
            'money_carry' => round($money - $whole, 6),
        ], ['id' => $band['id']]);
        if ($whole > 0) {
            $repo->earnDaily($whole, sprintf('Plattenverkäufe: %s', $band['name']));
        }
        foreach ([[$career['records'], 1], [$career['retired'], 0]] as [$records, $active]) {
            foreach ($records as $record) {
                if (!isset($record['id'])) {
                    continue;
                }
                self::saveRecord($repo, $band, $record, $active);
            }
        }
        foreach ($hours as $recordId => $byHour) {
            foreach ($byHour as $hour => $sold) {
                if ($db->exec('UPDATE chart_hours SET sold = sold + ? WHERE record_id = ? AND hour = ?', [$sold, $recordId, $hour]) === 0) {
                    $db->insert('chart_hours', ['record_id' => $recordId, 'hour' => $hour, 'sold' => $sold]);
                }
            }
        }
        $career['earned'] = array_fill_keys(array_keys($career['earned']), 0.0);
        $career['retired'] = [];
        return $career;
    }

    /** Eine Platte speichern; verkaufte Stück (Hype und Katalog) zählen für Gold, Platin und Diamant. */
    private static function saveRecord(Repo $repo, array $band, array $record, int $active): void
    {
        $db = $repo->db;
        $row = $db->one('SELECT title, sold FROM records WHERE id = ?', [$record['id']]);
        if ($row === null) {
            return;
        }
        $before = (float) $row['sold'];
        $after = $record['sold'] + $record['catalogSold'];
        $core = $record;
        unset($core['id']);
        $db->update('records', ['core' => json_encode($core), 'core_active' => $active, 'sold' => round($after, 4)], ['id' => $record['id']]);
        if ($after > $before) {
            Awards::sales($repo, $band['id'], $record['id'], (string) $row['title'], $before, $after);
        }
    }
}
