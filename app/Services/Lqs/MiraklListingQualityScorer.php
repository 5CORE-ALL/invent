<?php

namespace App\Services\Lqs;

/**
 * Rule-based Listing Quality Score (0-100) for Mirakl marketplaces (Best Buy, Purchasing Power).
 *
 * Mirakl has no quality-score API. The live offer feed (OF21) exposes the catalog title,
 * description, brand, category, price/MSRP, stock, status and lead time, but NOT bullets or images, so those two
 * checks use what this app last pushed (<marketplace>_metrics) and are labelled "app records" in the findings.
 *
 * Weights (total 100):
 *   Title 25 · Description 20 · Bullets 15 · Images 15 · Product identity 10 · Offer health 15
 */
class MiraklListingQualityScorer
{
    private const PROMO_WORDS = [
        'free shipping', 'best price', 'best seller', 'bestseller', 'hot sale', 'on sale', 'wow', 'must see',
        'cheap', 'lowest price', 'limited time', 'buy now', '100%', 'guaranteed', '!!!',
    ];

    /**
     * @param  array<string, mixed>  $offer  one OF21 offer
     * @param  array{bullets?: list<string>, images?: list<string>}  $local  content stored by this app (<marketplace>_metrics)
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function score(array $offer, array $local = [], string $label = 'the marketplace'): array
    {
        $facts = $this->facts($offer, $local);
        $checks = [];

        // ── Title (25) ────────────────────────────────────────────
        $title = $facts['title'];
        $len = mb_strlen($title);
        $pts = 0.0;
        $notes = [];
        if ($len === 0) {
            $notes[] = 'Title is missing.';
        } else {
            $pts += match (true) {
                $len >= 80 => 15,
                $len >= 60 => 12,
                $len >= 40 => 8,
                $len >= 25 => 4,
                default => 2,
            };
            if ($len < 80) {
                $notes[] = "Title is only {$len} characters. Aim for 80-150: brand, product type, model, key spec (size, power, color) and what shoppers search for.";
            }
            if ($facts['brand'] !== '' && ! str_contains(mb_strtolower($title), mb_strtolower($facts['brand']))) {
                $notes[] = 'Title does not start with the brand ('.$facts['brand'].'). '.$label.' titles lead with the brand.';
            } else {
                $pts += 3;
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
                $notes[] = 'Title is mostly ALL CAPS. Use normal title case.';
            } elseif ($promoHit !== null) {
                $notes[] = "Title contains promotional filler (\"{$promoHit}\"). Marketplaces reject price/promo claims in titles.";
            } else {
                $pts += 4;
            }
            $words = preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/', '', $lower) ?? '') ?: [];
            $counts = array_count_values(array_filter($words, fn ($w) => strlen($w) > 3));
            $stuffed = array_filter($counts, fn ($c) => $c >= 3);
            if ($stuffed === []) {
                $pts += 3;
            } else {
                $notes[] = 'Title repeats "'.array_key_first($stuffed).'" several times. Use each keyword once.';
            }
        }
        $checks[] = $this->check('Title', $pts, 25, $notes);

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
                ? 'Product description is empty on '.$label.'.'
                : "Description is only {$chars} characters. Add specs, dimensions, compatibility and what is in the box (600+).";
        }
        $checks[] = $this->check('Description', $pts, 20, $notes);

        // ── Bullets (15, app records) ─────────────────────────────
        $bullets = $facts['bullets'];
        $notes = [];
        $pts = min(5, count($bullets)) * 2;
        if (count($bullets) < 5) {
            $notes[] = (count($bullets) === 0
                ? 'No bullet points on record.'
                : 'Only '.count($bullets).' bullet point(s) on record.').' Add 5 benefit-led bullets (app records).';
        }
        if ($bullets !== []) {
            $avg = array_sum(array_map('mb_strlen', $bullets)) / count($bullets);
            if ($avg >= 60) {
                $pts += 5;
            } elseif ($avg >= 30) {
                $pts += 3;
                $notes[] = 'Bullets are short (avg '.round($avg).' chars). Aim for 60+ with concrete specs (app records).';
            } else {
                $pts += 1;
                $notes[] = 'Bullets are very short (avg '.round($avg).' chars). Write full benefit sentences (app records).';
            }
        }
        $checks[] = $this->check('Bullets', $pts, 15, $notes);

        // ── Images (15, app records) ──────────────────────────────
        $images = count($facts['images']);
        $notes = [];
        $pts = match (true) {
            $images >= 6 => 15,
            $images >= 4 => 11,
            $images >= 2 => 7,
            $images === 1 => 3,
            default => 0,
        };
        if ($images < 6) {
            $notes[] = "Only {$images} image(s) on record. Use 6+ (main on white, angles, in-use, scale, what's included) (app records).";
        }
        $checks[] = $this->check('Images', $pts, 15, $notes);

        // ── Product identity (10) ─────────────────────────────────
        $notes = [];
        $pts = 0;
        foreach ([
            ['brand', 'Brand is missing.', 3],
            ['category', 'No '.$label.' category on the offer.', 3],
        ] as [$key, $msg, $points]) {
            if ($facts[$key] !== '') {
                $pts += $points;
            } else {
                $notes[] = $msg;
            }
        }
        if ($facts['msrp'] > 0) {
            $pts += 2;
        } else {
            $notes[] = 'No MSRP set. Shoppers see a savings badge only when MSRP is above the price.';
        }
        if ($facts['has_reference']) {
            $pts += 2;
        } else {
            $notes[] = 'No UPC/GTIN product reference. Identifiers improve catalog matching.';
        }
        $checks[] = $this->check('Product identity', $pts, 10, $notes);

        // ── Offer health (15) ─────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['active']) {
            $pts += 6;
        } else {
            $why = $facts['inactivity_reasons'] !== [] ? ' ('.implode(', ', $facts['inactivity_reasons']).')' : '';
            $notes[] = 'Offer is inactive on '.$label.$why.'. It cannot sell until fixed.';
        }
        if ($facts['quantity'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'Offer quantity is 0.';
        }
        if ($facts['price'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'Offer has no valid price.';
        }
        $lead = $facts['leadtime_to_ship'];
        if ($lead !== null && $lead <= 2) {
            $pts += 3;
        } else {
            $notes[] = $lead === null ? 'Lead time to ship is not set.' : "Lead time is {$lead} days. 1-2 days converts best.";
        }
        $checks[] = $this->check('Offer health', $pts, 15, $notes);

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
     * @param  array<string, mixed>  $offer
     * @param  array{bullets?: list<string>, images?: list<string>}  $local
     * @return array<string, mixed>
     */
    public function facts(array $offer, array $local = []): array
    {
        $desc = html_entity_decode((string) ($offer['product_description'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $desc = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $desc) ?? $desc;
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($desc)) ?? '');
        // The feed prefixes copy with a field label; it is not shopper-facing text.
        $desc = trim(preg_replace('/^Product Long Description:\s*/i', '', $desc) ?? $desc);

        $refs = $offer['product_references'] ?? [];
        $hasReference = false;
        foreach ((array) $refs as $ref) {
            $value = is_array($ref) ? ($ref['reference'] ?? '') : $ref;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $hasReference = true;
            }
        }

        $lead = $offer['leadtime_to_ship'] ?? null;

        return [
            'shop_sku' => trim((string) ($offer['shop_sku'] ?? '')),
            'product_sku' => trim((string) ($offer['product_sku'] ?? '')),
            'title' => trim((string) ($offer['product_title'] ?? '')),
            'brand' => trim((string) ($offer['product_brand'] ?? '')),
            'category' => trim((string) ($offer['category_label'] ?? $offer['category_code'] ?? '')),
            'description_chars' => mb_strlen($desc),
            'bullets' => array_values(array_filter((array) ($local['bullets'] ?? []), fn ($b) => mb_strlen((string) $b) >= 5)),
            'images' => array_values(array_filter((array) ($local['images'] ?? []), fn ($u) => trim((string) $u) !== '')),
            'msrp' => (float) ($offer['msrp'] ?? 0),
            'price' => (float) ($offer['price'] ?? 0),
            'quantity' => (int) ($offer['quantity'] ?? 0),
            'active' => (bool) ($offer['active'] ?? false),
            'inactivity_reasons' => array_values(array_filter(array_map('strval', (array) ($offer['inactivity_reasons'] ?? [])))),
            'leadtime_to_ship' => is_numeric($lead) ? (int) $lead : null,
            'has_reference' => $hasReference,
        ];
    }

    /** @param  list<string>  $notes */
    private function check(string $name, float|int $points, int $max, array $notes): array
    {
        return [
            'name' => $name,
            'points' => round((float) $points, 1),
            'max' => $max,
            'notes' => $notes,
        ];
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
