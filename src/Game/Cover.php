<?php
declare(strict_types=1);

namespace Signed\Game;

use Signed\ApiError;
use Signed\Rng;

/**
 * Plattencover: Das Grafikbüro legt drei Entwürfe vor (Motiv, Farben, Stil, Anordnung aus Catalog::COVER_PARTS), das Label wählt einen
 * und wer ihn gestaltet (Balance::COVER_DESIGNERS: teurer = klarer, mit Glanz, und die Platte verkauft sich etwas besser). Das Motiv folgt dem
 * Titel, wenn ein passendes Wort darin steckt (im Browser, Catalog::COVER_WORDS). Neue Entwürfe kosten ein kleines Honorar (Balance::COVER_REDRAW).
 * Gezeichnet wird es im Browser (cover.js), mit dem Bandlogo, sobald die Band eins hat.
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

    /** Ein gewähltes Cover prüfen: nur bekannte Zutaten; der Designer bestimmt, wie klar und glänzend es wird. */
    public static function validate(array $in, string $designer = 'student', ?array $logo = null): array
    {
        if (!isset(Balance::COVER_DESIGNERS[$designer])) {
            throw new ApiError('Wer soll das Cover gestalten?');
        }
        $spec = [];
        foreach (Catalog::COVER_PARTS as $key => $options) {
            $value = $in[$key] ?? null;
            if (!is_string($value) || !in_array($value, $options, true)) {
                throw new ApiError('Bitte wähle ein Cover.');
            }
            $spec[$key] = $value;
        }
        return self::spec($spec, Balance::COVER_DESIGNERS[$designer]['rarity']) + ['designer' => $designer] + ($logo !== null ? ['logo' => $logo] : []);
    }

    /** Bandlogo prüfen: bekannte Schrift und Farbe. */
    public static function logo(mixed $in): ?array
    {
        if (!is_array($in) || !isset($in['font'], $in['color'])) {
            return null;
        }
        if (!isset(Catalog::LOGO_FONTS[$in['font']]) || !isset(Catalog::LOGO_COLORS[$in['color']])) {
            throw new ApiError('Diese Schrift oder Farbe gibt es nicht.');
        }
        return ['font' => (string) $in['font'], 'color' => (string) $in['color']];
    }

    private static function spec(array $parts, int $rarity = 2): array
    {
        return $parts + ['rarity' => array_fill(0, 4, $rarity), 'quality' => 0.0];
    }
}
