<?php

namespace App\Services\Lqs;

/**
 * Rule-based TopDawg Listing Quality Score (0-100).
 *
 * TopDawg has no quality-score API, and its supplier catalog list (SupplierProduct/list) is capped at 25,000 calls a
 * day that the app's other jobs already use up. The scheduled product sync stores each live listing in
 * topdawg_products (title, price, MSRP, stock, TDID, state); descriptions, bullets and the photo set are not kept
 * there, so those use what this app pushes to TopDawg (topdawg_metrics, then Product Master) and are labelled
 * "app records" in the findings.
 *
 * Weights (total 100):
 *   Title 25 · Description 20 · Key features 10 · Images 15 · Pricing 10 · Listing health 15 · Catalog data 5
 */
class TopdawgListingQualityScorer
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

        // ── Title (25, live from TopDawg) ─────────────────────────
        $title = $facts['title'];
        $len = mb_strlen($title);
        $pts = 0.0;
        $notes = [];
        if ($len === 0) {
            $notes[] = 'Title is missing.';
        } else {
            $pts += match (true) {
                $len >= 60 => 12,
                $len >= 45 => 9,
                $len >= 30 => 6,
                $len >= 15 => 3,
                default => 1,
            };
            if ($len < 60) {
                $notes[] = "Title is only {$len} characters. Aim for 60-120: product type, key attribute (size, color, pack) and what shoppers search for.";
            }
            if (stripos($title, self::BRAND) !== false || stripos($title, '5core') !== false) {
                $pts += 3;
            } else {
                $notes[] = 'Title does not carry the brand ('.self::BRAND.').';
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
                $pts += 5;
            }
            $words = preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/', '', $lower) ?? '') ?: [];
            $counts = array_count_values(array_filter($words, fn ($w) => strlen($w) > 3));
            $stuffed = array_filter($counts, fn ($c) => $c >= 3);
            if ($stuffed === []) {
                $pts += 5;
            } else {
                $notes[] = 'Title repeats "'.array_key_first($stuffed).'" several times. Use each keyword once.';
            }
        }
        $checks[] = $this->check('Title', $pts, 25, $notes);

        // ── Description (20, app records) ─────────────────────────
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
                ? 'No product description on record.'
                : "Description is only {$chars} characters. Add specs, dimensions, materials and what is in the box (600+) (app records).";
        }
        $checks[] = $this->check('Description', $pts, 20, $notes);

        // ── Key features / bullets (10, app records) ──────────────
        $bullets = $facts['bullets'];
        $notes = [];
        $pts = min(5, count($bullets)) * 1.6;
        if (count($bullets) < 5) {
            $notes[] = (count($bullets) === 0 ? 'No bullet points on record.' : 'Only '.count($bullets).' bullet point(s) on record.').' Add 5 benefit-led bullets (app records).';
        }
        if ($bullets !== []) {
            $avg = array_sum(array_map('mb_strlen', $bullets)) / count($bullets);
            if ($avg >= 60) {
                $pts += 2;
            } elseif ($avg >= 30) {
                $pts += 1;
                $notes[] = 'Bullets are short (avg '.round($avg).' chars). Aim for 60+ with concrete specs (app records).';
            } else {
                $notes[] = 'Bullets are very short (avg '.round($avg).' chars). Write full benefit sentences (app records).';
            }
        }
        $checks[] = $this->check('Key features', $pts, 10, $notes);

        // ── Images (15, app records + live main image) ────────────
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

        // ── Pricing (10, live from TopDawg) ───────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['price'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'No valid TopDawg price.';
        }
        if ($facts['msrp'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'No MSRP set.';
        }
        if ($facts['msrp'] > 0 && $facts['price'] > 0) {
            if ($facts['msrp'] >= $facts['price']) {
                $pts += 4;
            } else {
                $notes[] = 'MSRP is below the selling price. Shoppers see no savings; set MSRP above price.';
            }
        }
        $checks[] = $this->check('Pricing', $pts, 10, $notes);

        // ── Listing health (15, live from TopDawg) ────────────────
        $notes = [];
        $pts = 0;
        if ($facts['published']) {
            $pts += 5;
        } else {
            $notes[] = 'Listing is not published on TopDawg (pending, review or inactive).';
        }
        if ($facts['has_live_id']) {
            $pts += 4;
        } else {
            $notes[] = 'No live TopDawg product ID (TDID) on the listing.';
        }
        if ($facts['stock'] > 0) {
            $pts += 6;
        } else {
            $notes[] = 'TopDawg stock is 0. The listing cannot sell.';
        }
        $checks[] = $this->check('Listing health', $pts, 15, $notes);

        // ── Catalog data (5) ──────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_upc']) {
            $pts += 2;
        } else {
            $notes[] = 'No UPC/GTIN on the product.';
        }
        if ($facts['has_dimensions']) {
            $pts += 1;
        } else {
            $notes[] = 'Product dimensions (L x W x H) are missing.';
        }
        if ($facts['has_weight']) {
            $pts += 1;
        } else {
            $notes[] = 'Product weight is missing.';
        }
        if ($facts['has_origin']) {
            $pts += 1;
        } else {
            $notes[] = 'Country of origin is missing.';
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
     * Accepted keys: title, description (plain or HTML), bullets (list), images (list of URLs), price, msrp, stock,
     * published, has_live_id, upc, length/width/height, weight, origin.
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

        $bullets = array_values(array_filter(array_map(
            fn ($b) => trim(strip_tags((string) $b)),
            (array) ($c['bullets'] ?? [])
        ), fn ($b) => mb_strlen($b) >= 5 && strcasecmp($b, $title) !== 0));

        $images = [];
        foreach ((array) ($c['images'] ?? []) as $url) {
            $url = is_string($url) ? trim($url) : '';
            if (preg_match('#^https?://#i', $url)) {
                $images[] = $url;
            }
        }

        $dim = fn ($k) => (float) ($c[$k] ?? 0) > 0;

        return [
            'title' => $title,
            'description_chars' => mb_strlen($desc),
            'bullets' => $bullets,
            'images' => array_values(array_unique($images)),
            'price' => (float) ($c['price'] ?? 0),
            'msrp' => (float) ($c['msrp'] ?? 0),
            'stock' => (int) ($c['stock'] ?? 0),
            'published' => (bool) ($c['published'] ?? false),
            'has_live_id' => (bool) ($c['has_live_id'] ?? false),
            'has_upc' => strlen(preg_replace('/\D+/', '', (string) ($c['upc'] ?? '')) ?? '') >= 8,
            'has_dimensions' => $dim('length') && $dim('width') && $dim('height'),
            'has_weight' => $dim('weight'),
            'has_origin' => trim((string) ($c['origin'] ?? '')) !== '',
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
