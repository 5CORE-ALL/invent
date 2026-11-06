<?php

namespace App\Services\Lqs;

/**
 * Rule-based Faire Listing Quality Score (0-100) from the live Faire product (GET /products/{id}).
 *
 * Faire has no quality-score API. Faire is a wholesale marketplace, so retailers decide on photos, a clear name and
 * description, a ~2x retail markup (keystone) and in-stock, published variants.
 *
 * Weights (total 100):
 *   Title 20 · Description 20 · Images 20 · Categorisation 10 · Pricing 10 · Listing health 15 · Catalog data 5
 */
class FaireListingQualityScorer
{
    /** Brand every 5 Core listing title should carry. */
    private const BRAND = '5 Core';

    private const PROMO_WORDS = [
        'free shipping', 'best price', 'best seller', 'bestseller', 'hot sale', 'on sale', 'wow', 'must see',
        'cheap', 'lowest price', 'limited time', 'buy now', '100%', 'guaranteed', '!!!',
    ];

    /**
     * @param  array<string, mixed>  $content  see facts() for the accepted keys
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function score(array $content): array
    {
        $facts = $this->facts($content);
        $checks = [];

        // ── Title (20) ────────────────────────────────────────────
        $title = $facts['title'];
        $len = mb_strlen($title);
        $pts = 0.0;
        $notes = [];
        if ($len === 0) {
            $notes[] = 'Product name is missing.';
        } else {
            $pts += match (true) {
                $len >= 45 => 10,
                $len >= 35 => 8,
                $len >= 25 => 5,
                $len >= 12 => 3,
                default => 1,
            };
            if ($len < 45) {
                $notes[] = "Product name is only {$len} characters. Aim for 45-100: product type plus the key attribute retailers search for (size, pack, material).";
            }
            if (stripos($title, self::BRAND) !== false || stripos($title, '5core') !== false) {
                $pts += 3;
            } else {
                $notes[] = 'Product name does not carry the brand ('.self::BRAND.').';
            }
            $letters = preg_replace('/[^A-Za-z]/', '', $title) ?? '';
            $upperRatio = strlen($letters) > 0 ? strlen(preg_replace('/[^A-Z]/', '', $letters) ?? '') / strlen($letters) : 0;
            $lower = mb_strtolower($title);
            $promoHit = null;
            foreach (self::PROMO_WORDS as $word) {
                if (str_contains($lower, $word)) {
                    $promoHit = $word;
                    break;
                }
            }
            if ($upperRatio > 0.7 && strlen($letters) > 12) {
                $notes[] = 'Product name is mostly ALL CAPS. Use normal title case.';
            } elseif ($promoHit !== null) {
                $notes[] = "Product name contains promotional filler (\"{$promoHit}\"). Faire does not allow price/promo claims in names.";
            } else {
                $pts += 4;
            }
            $words = preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/', '', $lower) ?? '') ?: [];
            $counts = array_count_values(array_filter($words, fn ($w) => strlen($w) > 3));
            $stuffed = array_filter($counts, fn ($c) => $c >= 3);
            if ($stuffed === []) {
                $pts += 3;
            } else {
                $notes[] = 'Product name repeats "'.array_key_first($stuffed).'" several times. Use each keyword once.';
            }
        }
        $checks[] = $this->check('Title', $pts, 20, $notes);

        // ── Description (20) ──────────────────────────────────────
        $chars = $facts['description_chars'];
        $notes = [];
        $pts = match (true) {
            $chars >= 600 => 20,
            $chars >= 400 => 16,
            $chars >= 250 => 11,
            $chars >= 100 => 6,
            $chars > 0 => 2,
            default => 0,
        };
        if ($chars < 600) {
            $notes[] = $chars === 0
                ? 'No product description.'
                : "Description is only {$chars} characters. Add specs, dimensions, materials, what is in the box and why retailers should stock it (600+).";
        }
        $checks[] = $this->check('Description', $pts, 20, $notes);

        // ── Images (20) ───────────────────────────────────────────
        $count = count($facts['images']);
        $notes = [];
        $pts = match (true) {
            $count >= 6 => 16,
            $count >= 4 => 12,
            $count >= 3 => 9,
            $count >= 2 => 6,
            $count === 1 => 3,
            default => 0,
        };
        if ($count < 6) {
            $notes[] = "Only {$count} image(s). Use 6+ (hero on white, angles, in-use, scale, what's included, packaging).";
        }
        $side = $facts['main_image_side'];
        if ($count > 0) {
            if ($side >= 1000) {
                $pts += 4;
            } elseif ($side >= 600) {
                $pts += 2;
                $notes[] = "Main image is {$side}px on its short side. Use 1000px+ so retailers can zoom.";
            } elseif ($side > 0) {
                $notes[] = "Main image is only {$side}px on its short side. Use 1000px+.";
            } else {
                $pts += 2; // size unknown, do not punish
            }
        }
        $checks[] = $this->check('Images', $pts, 20, $notes);

        // ── Categorisation (10) ───────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_taxonomy']) {
            $pts += 4;
        } else {
            $notes[] = 'No Faire category (taxonomy type) set.';
        }
        if ($facts['attribute_count'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'No product attributes (material, color, etc.) filled in.';
        }
        if ($facts['warnings'] === []) {
            $pts += 3;
        } else {
            $notes[] = 'Faire reports publication warnings: '.implode('; ', array_slice($facts['warnings'], 0, 3)).'.';
        }
        $checks[] = $this->check('Categorisation', $pts, 10, $notes);

        // ── Pricing (10) ──────────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['wholesale'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'No wholesale price on the variant.';
        }
        if ($facts['retail'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'No retail (MSRP) price on the variant.';
        }
        if ($facts['wholesale'] > 0 && $facts['retail'] > 0) {
            $ratio = $facts['retail'] / $facts['wholesale'];
            if ($ratio >= 2) {
                $pts += 4;
            } elseif ($ratio > 1) {
                $pts += 2;
                $notes[] = sprintf('Retail is only %.1fx wholesale. Faire retailers expect about 2x (keystone) markup.', $ratio);
            } else {
                $notes[] = 'Retail price is not above wholesale, so retailers see no margin.';
            }
        }
        $checks[] = $this->check('Pricing', $pts, 10, $notes);

        // ── Listing health (15) ───────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['published']) {
            $pts += 5;
        } else {
            $notes[] = 'Product is not published on Faire ('.($facts['lifecycle'] ?: 'unknown state').').';
        }
        if ($facts['for_sale']) {
            $pts += 4;
        } else {
            $notes[] = 'Variant is not set to FOR_SALE ('.($facts['sale_state'] ?: 'unknown').').';
        }
        if ($facts['stock'] > 0) {
            $pts += 6;
        } else {
            $notes[] = 'Faire available quantity is 0. Retailers cannot order it.';
        }
        $checks[] = $this->check('Listing health', $pts, 15, $notes);

        // ── Catalog data (5) ──────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_gtin']) {
            $pts += 2;
        } else {
            $notes[] = 'No GTIN/UPC on the variant.';
        }
        if ($facts['has_dimensions']) {
            $pts += 1;
        } else {
            $notes[] = 'Variant dimensions (L x W x H) are missing.';
        }
        if ($facts['has_weight']) {
            $pts += 1;
        } else {
            $notes[] = 'Variant weight is missing.';
        }
        if ($facts['has_tariff']) {
            $pts += 1;
        } else {
            $notes[] = 'No tariff (HS) code on the variant.';
        }
        $checks[] = $this->check('Catalog data', $pts, 5, $notes);

        $total = round(array_sum(array_column($checks, 'points')), 1);

        return [
            'score' => max(0.0, min(100.0, $total)),
            'facts' => $facts,
            'checks' => $checks,
            'findings' => $this->findings($total, $checks),
            'suggestions' => $this->suggestions($checks),
        ];
    }

    /**
     * Normalise the raw content the sync service collected.
     *
     * Accepted keys: title, description, images (URL strings or ['url','width','height']), lifecycle, sale_state,
     * stock, wholesale, retail, has_taxonomy, attribute_count, warnings (list), gtin, length/width/height, weight, tariff.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    public function facts(array $c): array
    {
        $title = trim((string) ($c['title'] ?? ''));

        $desc = html_entity_decode((string) ($c['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $desc = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $desc) ?? $desc;
        $desc = preg_replace('#<br\s*/?>|</(p|div|li|h[1-6])>#i', ' ', $desc) ?? $desc;
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($desc)) ?? '');
        if ($desc !== '' && strcasecmp($desc, $title) === 0) {
            $desc = '';
        }

        $images = [];
        $mainSide = 0;
        foreach ((array) ($c['images'] ?? []) as $img) {
            $url = trim((string) (is_array($img) ? ($img['url'] ?? '') : $img));
            if (! preg_match('#^https?://#i', $url) || isset($images[$url])) {
                continue;
            }
            if ($images === [] && is_array($img)) {
                $mainSide = (int) min((int) ($img['width'] ?? 0), (int) ($img['height'] ?? 0));
            }
            $images[$url] = true;
        }

        $dim = fn ($k) => (float) ($c[$k] ?? 0) > 0;

        return [
            'title' => $title,
            'description_chars' => mb_strlen($desc),
            'images' => array_keys($images),
            'main_image_side' => $mainSide,
            'has_taxonomy' => (bool) ($c['has_taxonomy'] ?? false),
            'attribute_count' => (int) ($c['attribute_count'] ?? 0),
            'warnings' => array_values(array_filter(array_map('strval', (array) ($c['warnings'] ?? [])))),
            'wholesale' => (float) ($c['wholesale'] ?? 0),
            'retail' => (float) ($c['retail'] ?? 0),
            'price' => (float) ($c['wholesale'] ?? 0),
            'lifecycle' => strtoupper(trim((string) ($c['lifecycle'] ?? ''))),
            'sale_state' => strtoupper(trim((string) ($c['sale_state'] ?? ''))),
            'published' => strtoupper(trim((string) ($c['lifecycle'] ?? ''))) === 'PUBLISHED',
            'for_sale' => strtoupper(trim((string) ($c['sale_state'] ?? ''))) === 'FOR_SALE',
            'stock' => (int) ($c['stock'] ?? 0),
            'has_gtin' => strlen(preg_replace('/\D+/', '', (string) ($c['gtin'] ?? '')) ?? '') >= 8,
            'has_dimensions' => $dim('length') && $dim('width') && $dim('height'),
            'has_weight' => $dim('weight'),
            'has_tariff' => trim((string) ($c['tariff'] ?? '')) !== '',
        ];
    }

    /** @param  list<string>  $notes */
    private function check(string $name, float|int $points, int $max, array $notes): array
    {
        return ['name' => $name, 'points' => round((float) $points, 1), 'max' => $max, 'notes' => $notes];
    }

    /** @param  list<array<string, mixed>>  $checks */
    private function findings(float $total, array $checks): string
    {
        $lines = ["API score {$total}/100."];
        foreach ($checks as $c) {
            $lines[] = sprintf('%s: %s/%d%s', $c['name'], $c['points'], $c['max'], $c['notes'] ? ' – '.implode(' ', $c['notes']) : ' – OK.');
        }

        return implode("\n", $lines);
    }

    /** @param  list<array<string, mixed>>  $checks */
    private function suggestions(array $checks): string
    {
        $gaps = array_filter($checks, fn ($c) => $c['points'] < $c['max'] && $c['notes']);
        usort($gaps, fn ($a, $b) => ($b['max'] - $b['points']) <=> ($a['max'] - $a['points']));

        $out = [];
        $i = 1;
        foreach ($gaps as $c) {
            foreach ($c['notes'] as $note) {
                $out[] = $i++.'. ['.$c['name'].'] '.$note;
            }
        }

        return $out === [] ? 'No gaps found by the API checks.' : implode("\n", $out);
    }
}
