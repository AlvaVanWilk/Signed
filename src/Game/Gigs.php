<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\Rng;

/**
 * Auftritt buchen: Die Orte, die zur Band passen (nach Ruhm, plus ein, zwei Stufen darüber), jeweils mit festen Kosten,
 * einer Gewinn-Schätzung (aus ein paar durchgespielten Auftritten), dem erwarteten Ruhm und ob der Sound die Erwartung erfüllt.
 * Ein Ort unter dem Anspruch der Band ärgert sie, einer darüber freut sie.
 */
final class Gigs
{
    /** Wie viele Lieder die Band spielen kann: aufgenommene und geschriebene. */
    public static function songsAvailable(array $band): int
    {
        return (int) $band['songs_recorded'] + (int) floor((float) $band['songs_written']);
    }

    /** Index des Ortes, den die Band nach ihrem Ruhm erwartet (ihr Anspruch). */
    public static function claim(float $fame): int
    {
        $index = 0;
        foreach (Balance::VENUES as $i => $venue) {
            if ($fame >= $venue['minFame']) {
                $index = $i;
            }
        }
        return $index;
    }

    /** Kosten eines Ortes für diese Band (Solokünstler buchen Studiomusiker dazu). */
    public static function cost(string $key, array $members, array $band): int
    {
        $c = Balance::GIG_BOOKING;
        $factor = 1.0;
        if (count($members) === 1) {
            $factor = $band['genre'] === 'pop' || in_array('pop', $members[0]['genres'], true) ? $c['soloCostPop'] : $c['soloCost'];
        }
        return (int) round($c['cost'][$key] * $factor);
    }

    /** Wie voll der Ort wird: Sound gegen Erwartung und Bekanntheit gegen die Größe des Ortes (eine unbekannte Band füllt keinen Saal). */
    public static function fill(float $sound, array $venue, float $fame): float
    {
        $c = Balance::GIG_BOOKING;
        $soundFill = min(1.0, $sound / max(1.0, (float) $venue['expect']));
        $fameFill = min(1.0, ($fame + $c['fameSoft']) / ($venue['minFame'] + $c['fameSoft']));
        return max($c['fillMin'], $soundFill * $fameFill);
    }

    /** Gage bei gegebener Leistung: Grundgage × Füllgrad × Stimmung im Saal. */
    public static function gage(int $base, float $fill, float $perf): int
    {
        $c = Balance::GIG_BOOKING;
        return (int) round($base * $fill * ($c['perfBase'] + $c['perfWeight'] * $perf));
    }

    /** @return list<array<string, mixed>> */
    public static function options(Repo $repo, array $band, array $members): array
    {
        $c = Balance::GIG_BOOKING;
        $gear = $repo->gearByMusician();
        $sound = BandCalc::bandSound($band, $members, $gear);
        $fame = BandCalc::bandFame($band, $members);
        $claim = self::claim($fame);
        [$manager, $level] = $repo->bandManager($band);
        $negotiator = BandCalc::managerBonus($manager, $level, 'verhandler', Balance::NEGOTIATOR_BONUS);
        $draw = BandCalc::freshness($band['gigs_since_record']);
        $available = self::songsAvailable($band);
        $players = array_map(static fn ($m) => Activities::player($m, $gear), $members);
        $out = [];
        foreach (Balance::VENUES as $i => $venue) {
            if ($i > $claim + $c['reach']) {
                break;
            }
            $key = $venue['key'];
            $songs = $c['songs'][$key];
            $base = (int) round(BandCalc::gagePerSong($venue + ['progress' => 0.5], $negotiator, count($members)) * $songs * $draw);
            // Schätzung: ein paar Auftritte durchspielen (der echte hängt vom Abend ab)
            $gages = [];
            $fames = [];
            for ($k = 0; $k < $c['estimateRuns']; $k++) {
                $sim = Performance::simulate($players, $sound, (float) $venue['expect'], $songs);
                $gages[] = self::gage($base, self::fill($sound, $venue, $fame), $sim['perf']);
                $fames[] = $venue['fame'] * $draw * BandCalc::soundFactor($sound) * $sim['perf'] * $songs;
            }
            sort($gages);
            $cost = self::cost($key, $members, $band);
            $ratio = $sound / max(1.0, (float) $venue['expect']);
            $fill = self::fill($sound, $venue, $fame);
            $out[] = [
                'key' => $key,
                'name' => $venue['name'],
                'place' => Catalog::VENUE_NAMES[$key][abs(crc32($band['id'] . '|' . $key . '|' . intdiv(\Signed\Clock::now(), 86400))) % count(Catalog::VENUE_NAMES[$key])],
                'songs' => $songs,
                'minutes' => (int) round($songs * Balance::SONG_SECONDS / 60),
                'cost' => $cost,
                'gage' => [$gages[0], $gages[count($gages) - 1]],
                'profit' => [$gages[0] - $cost, $gages[count($gages) - 1] - $cost],
                'fame' => round(array_sum($fames) / count($fames), 1),
                'expect' => $venue['expect'],
                'fill' => round($fill, 2),
                'fit' => $ratio >= 1.0 ? 'green' : ($ratio >= 0.75 ? 'yellow' : 'red'),
                'known' => $fame + Balance::GIG_BOOKING['fameSoft'] >= $venue['minFame'] + Balance::GIG_BOOKING['fameSoft'] ? 'yes' : (($fame + Balance::GIG_BOOKING['fameSoft']) / ($venue['minFame'] + Balance::GIG_BOOKING['fameSoft']) >= 0.5 ? 'some' : 'no'),
                'claim' => $i < $claim ? 'below' : ($i > $claim ? 'above' : 'fit'),
                'blocked' => $available < $songs ? sprintf('Dafür braucht %s %d Songs (hat %d).', Bands::who($members, true), $songs, $available) : null,
            ];
        }
        return ['sound' => $sound, 'songs' => $available, 'options' => $out];
    }

    /** Wie die Band die Buchung aufnimmt: unter ihrem Anspruch ärgert es, darüber freut es (je nach Charakter stärker). */
    public static function moodOnBooking(Repo $repo, array $members, string $claim): void
    {
        $c = Balance::GIG_BOOKING;
        $delta = $claim === 'below' ? $c['below'] : ($claim === 'above' ? $c['above'] : 0.0);
        if ($delta == 0.0) {
            return;
        }
        foreach ($members as $m) {
            // Gemütliche ärgern sich nicht über eine kleine Bühne
            if ($claim === 'below' && in_array(Balance::TRAITS['calm']['trait'], $m['personality'], true)) {
                continue;
            }
            $strong = array_intersect($m['personality'], $c['strongTraits']) ? $c['strong'] : 1.0;
            $value = max(0.0, min(100.0, $m['wellbeing'] + $delta * $strong));
            $repo->db->update('musicians', ['wellbeing' => round($value, 3)], ['id' => $m['id']]);
        }
    }
}
