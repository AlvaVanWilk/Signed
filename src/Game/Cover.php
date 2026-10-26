<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Rng;

/**
 * Plattencover: Das Grafikbüro legt drei Entwürfe vor (Motiv, Farben, Stil, Anordnung aus Catalog::COVER_PARTS), das Label wählt einen.
 * Neue Entwürfe kosten ein kleines Honorar (Balance::COVER_REDRAW). Das Cover sieht nur schön aus und wirkt nicht aufs Spiel.
 * Gezeichnet wird es im Browser (cover.js); alle Zutaten erscheinen in klarer Fassung (Wertigkeit 2, ohne Folie).
 */
final class Cover
{
    public function __construct(private Repo $repo)
    {
    }

    /** Drei verschiedene Entwürfe. */
    public static function drafts(int $count = 3): array
    {
        $out = [];
        $motifs = Rng::sample(Catalog::COVER_PARTS['motif'], $count);
        $palettes = Rng::sample(Catalog::COVER_PARTS['palette'], $count);
        for ($i = 0; $i < $count; $i++) {
            $out[] = self::spec([
                'motif' => $motifs[$i],
                'palette' => $palettes[$i],
                'style' => Rng::pick(Catalog::COVER_PARTS['style']),
                'layout' => Rng::pick(Catalog::COVER_PARTS['layout']),
            ]);
        }
        return $out;
    }

    /** Neue Entwürfe gegen ein kleines Honorar. */
    public function redraw(): array
    {
        $this->repo->spend(Balance::COVER_REDRAW, 'Grafikbüro: neue Cover-Entwürfe');
        return self::drafts();
    }

    /** Ein gewähltes Cover prüfen: nur bekannte Zutaten. */
    public static function validate(array $in): array
    {
        $spec = [];
        foreach (Catalog::COVER_PARTS as $key => $options) {
            $value = $in[$key] ?? null;
            if (!is_string($value) || !in_array($value, $options, true)) {
                throw new ApiError('Bitte wähle ein Cover.');
            }
            $spec[$key] = $value;
        }
        return self::spec($spec);
    }

    private static function spec(array $parts): array
    {
        return $parts + ['rarity' => [2, 2, 2, 2], 'quality' => 0.0];
    }
}
