<?php

namespace App\Services\Lqs;

/**
 * Rule-based Reverb Listing Quality Score (0-100) from a live /my/listings entry.
 *
 * Weights (total 100):
 *   Title 20 · Photos 25 · Description 20 · Item details 20 · Shipping, offers & UPC 10 · Listing health 5
 */
class ReverbListingQualityScorer
{
    private const PROMO_WORDS = [
        'free shipping', 'best price', 'best seller', 'bestseller', 'hot sale', 'on sale', 'wow', 'must see',
        'cheap', 'lowest price', 'limited time', 'buy now', '100%', 'guaranteed', '!!!',
    ];

    /**
     * @param  array<string, mixed>  $listing  one Reverb listing
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function score(array $listing): array
    {
        $facts = $this->facts($listing);
        $checks = [];

        // ── Title (20) ────────────────────────────────────────────
        $title = $facts['title'];
        $len = mb_strlen($title);
        $pts = 0.0;
        $notes = [];
        if ($len === 0) {
            $notes[] = 'Title is missing.';
        } else {
            $pts += match (true) {
                $len >= 60 => 10,
                $len >= 45 => 8,
                $len >= 30 => 5,
                $len >= 20 => 3,
                default => 1,
            };
            if ($len < 60) {
                $notes[] = "Title is only {$len} characters. Reverb buyers search brand + model + type: aim for 60-100.";
            }
            $lower = mb_strtolower($title);
            if ($facts['make'] !== '' && ! str_contains($lower, mb_strtolower($facts['make']))) {
                $notes[] = 'Title does not include the brand ('.$facts['make'].').';
            } else {
                $pts += 3;
            }
            $letters = preg_replace('/[^A-Za-z]/', '', $title) ?? '';
            $upperRatio = strlen($letters) > 0 ? strlen(preg_replace('/[^A-Z]/', '', $letters) ?? '') / strlen($letters) : 0;
            $promoHit = null;
            foreach (self::PROMO_WORDS as $word) {
                if (str_contains($lower, $word)) {
                    $promoHit = $word;
                    break;
                }
            }
            if ($upperRatio > 0.7 && strlen($letters) > 12) {
                $notes[] = 'Title is mostly ALL CAPS. Use normal title case.';
            } elseif ($promoHit !== null) {
                $notes[] = "Title contains promotional filler (\"{$promoHit}\"). Put offers in the description.";
            } else {
                $pts += 4;
            }
            $words = preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/', '', $lower) ?? '') ?: [];
            $counts = array_count_values(array_filter($words, fn ($w) => strlen($w) > 3));
            if (array_filter($counts, fn ($c) => $c >= 3) === []) {
                $pts += 3;
            } else {
                $notes[] = 'Title repeats a keyword several times. Use each keyword once.';
            }
        }
        $checks[] = $this->check('Title', $pts, 20, $notes);

        // ── Photos (25) ───────────────────────────────────────────
        $photos = $facts['photo_count'];
        $notes = [];
        $pts = match (true) {
            $photos >= 10 => 25,
            $photos >= 8 => 21,
            $photos >= 6 => 17,
            $photos >= 4 => 12,
            $photos >= 2 => 7,
            $photos === 1 => 3,
            default => 0,
        };
        if ($photos < 8) {
            $notes[] = "Only {$photos} photo(s). Reverb buyers expect 8+ (front, back, close-ups, in use, what's included).";
        }
        $checks[] = $this->check('Photos', $pts, 25, $notes);

        // ── Description (20) ──────────────────────────────────────
        $chars = $facts['description_chars'];
        $notes = [];
        $pts = match (true) {
            $chars >= 500 => 20,
            $chars >= 350 => 16,
            $chars >= 200 => 11,
            $chars >= 80 => 6,
            $chars > 0 => 2,
            default => 0,
        };
        if ($chars < 500) {
            $notes[] = $chars === 0
                ? 'Description is empty.'
                : "Description is only {$chars} characters. Add specs, condition notes, what is included and shipping details (500+).";
        }
        $checks[] = $this->check('Description', $pts, 20, $notes);

        // ── Item details (20) ─────────────────────────────────────
        $notes = [];
        $pts = 0;
        foreach ([
            ['make', 'Brand (make) is not set.', 5],
            ['model', 'Model is not set.', 5],
            ['finish', 'Finish/color is not set.', 3],
            ['condition', 'Condition is not set.', 4],
            ['category', 'No category.', 3],
        ] as [$key, $msg, $points]) {
            if ($facts[$key] !== '') {
                $pts += $points;
            } else {
                $notes[] = $msg;
            }
        }
        $checks[] = $this->check('Item details', $pts, 20, $notes);

        // ── Shipping, offers & UPC (10) ───────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_shipping']) {
            $pts += 6;
        } else {
            $notes[] = 'No shipping rates set. Listings without shipping are hidden or rank poorly.';
        }
        if ($facts['offers_enabled']) {
            $pts += 2;
        } else {
            $notes[] = 'Offers are off. Enabling offers lifts conversion and ranking.';
        }
        if ($facts['upc'] !== '') {
            $pts += 2;
        } else {
            $notes[] = 'No UPC. A UPC links the listing to Reverb\'s product catalog.';
        }
        $checks[] = $this->check('Shipping, offers & UPC', $pts, 10, $notes);

        // ── Listing health (5) ────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['state'] === 'live') {
            $pts += 3;
        } else {
            $notes[] = 'Listing state is "'.($facts['state'] ?: 'unknown').'", not live.';
        }
        if ($facts['inventory'] > 0) {
            $pts += 2;
        } else {
            $notes[] = 'Inventory is 0.';
        }
        $checks[] = $this->check('Listing health', $pts, 5, $notes);

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
     * @param  array<string, mixed>  $l
     * @return array<string, mixed>
     */
    public function facts(array $l): array
    {
        $desc = html_entity_decode((string) ($l['description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $desc = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $desc) ?? $desc;
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($desc)) ?? '');

        $condition = $l['condition'] ?? '';
        if (is_array($condition)) {
            $condition = $condition['display_name'] ?? $condition['slug'] ?? '';
        }

        $category = '';
        foreach ((array) ($l['categories'] ?? []) as $c) {
            $category = trim((string) (is_array($c) ? ($c['full_name'] ?? $c['name'] ?? '') : $c));
            if ($category !== '') {
                break;
            }
        }
        if ($category === '') {
            $category = trim((string) ($l['category_full_name'] ?? ''));
        }

        $rates = $l['shipping']['rates'] ?? [];
        $hasShipping = is_array($rates) && $rates !== [];
        if (! $hasShipping && ! empty($l['shipping']['local'])) {
            $hasShipping = true;
        }

        $state = $l['state'] ?? '';
        if (is_array($state)) {
            $state = $state['slug'] ?? $state['name'] ?? '';
        }

        return [
            'listing_id' => trim((string) ($l['id'] ?? '')),
            'sku' => trim((string) ($l['sku'] ?? '')),
            'title' => trim((string) ($l['title'] ?? $l['name'] ?? '')),
            'make' => trim((string) ($l['make'] ?? '')),
            'model' => trim((string) ($l['model'] ?? '')),
            'finish' => trim((string) ($l['finish'] ?? '')),
            'condition' => trim((string) $condition),
            'category' => $category,
            'photo_count' => count(array_filter((array) ($l['photos'] ?? []))),
            'description_chars' => mb_strlen($desc),
            'has_shipping' => $hasShipping,
            'offers_enabled' => (bool) ($l['offers_enabled'] ?? false),
            'upc' => trim((string) ($l['upc'] ?? '')),
            'state' => strtolower(trim((string) $state)),
            'inventory' => (int) ($l['inventory'] ?? 0),
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
