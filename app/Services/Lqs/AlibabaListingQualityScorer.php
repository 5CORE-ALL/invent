<?php

namespace App\Services\Lqs;

/**
 * Rule-based Alibaba.com Listing Quality Score (0-100) from the live ICBU product (/icbu/product/get).
 *
 * Alibaba has no quality-score API. Buyers on Alibaba.com are businesses searching by product name and attributes,
 * so the checks reward a clear subject, a full description, a complete photo set, filled-in attributes, and an
 * approved, displayed, in-stock listing.
 *
 * Weights (total 100):
 *   Title 20 · Description 20 · Images 20 · Attributes 10 · Pricing 5 · Listing health 20 · Catalog data 5
 */
class AlibabaListingQualityScorer
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
            $notes[] = 'Product subject (title) is missing.';
        } else {
            $pts += match (true) {
                $len >= 60 => 10,
                $len >= 45 => 8,
                $len >= 30 => 5,
                $len >= 15 => 3,
                default => 1,
            };
            if ($len < 60) {
                $notes[] = "Subject is only {$len} characters. Aim for 60-128: product type, key specs (size, pack, material) and the words buyers search for.";
            }
            if (stripos($title, self::BRAND) !== false || stripos($title, '5core') !== false) {
                $pts += 3;
            } else {
                $notes[] = 'Subject does not carry the brand ('.self::BRAND.').';
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
                $notes[] = 'Subject is mostly ALL CAPS. Use normal title case.';
            } elseif ($promoHit !== null) {
                $notes[] = "Subject contains promotional filler (\"{$promoHit}\"). Alibaba flags price/promo claims in subjects.";
            } else {
                $pts += 4;
            }
            $words = preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/', '', $lower) ?? '') ?: [];
            $counts = array_count_values(array_filter($words, fn ($w) => strlen($w) > 3));
            $stuffed = array_filter($counts, fn ($c) => $c >= 3);
            if ($stuffed === []) {
                $pts += 3;
            } else {
                $notes[] = 'Subject repeats "'.array_key_first($stuffed).'" several times. Use each keyword once.';
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
                : "Description is only {$chars} characters. Add specs, materials, sizes, packaging, certifications and uses (600+).";
        }
        $checks[] = $this->check('Description', $pts, 20, $notes);

        // ── Images (20) ───────────────────────────────────────────
        $count = count($facts['images']);
        $notes = [];
        $pts = match (true) {
            $count >= 6 => 20,
            $count === 5 => 17,
            $count === 4 => 14,
            $count === 3 => 10,
            $count === 2 => 6,
            $count === 1 => 3,
            default => 0,
        };
        if ($count < 6) {
            $notes[] = "Only {$count} main image(s). Alibaba allows 6: hero on white, angles, in-use, details, packaging, scale.";
        }
        $checks[] = $this->check('Images', $pts, 20, $notes);

        // ── Attributes (10) ───────────────────────────────────────
        $attrs = $facts['attribute_count'];
        $notes = [];
        $pts = match (true) {
            $attrs >= 8 => 10,
            $attrs >= 5 => 7,
            $attrs >= 3 => 4,
            $attrs >= 1 => 2,
            default => 0,
        };
        if ($attrs < 8) {
            $notes[] = $attrs === 0
                ? 'No product attributes filled in. Attributes drive Alibaba search filters.'
                : "Only {$attrs} attribute(s) filled in. Complete 8+ (brand, material, size, color, model, place of origin, certification...).";
        }
        $checks[] = $this->check('Attributes', $pts, 10, $notes);

        // ── Pricing (5) ───────────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['price'] > 0) {
            $pts += 5;
        } else {
            $notes[] = 'No valid price on the SKU.';
        }
        $checks[] = $this->check('Pricing', $pts, 5, $notes);

        // ── Listing health (20) ───────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['published']) {
            $pts += 8;
        } else {
            $notes[] = 'Product is not approved/online on Alibaba ('.($facts['status'] ?: 'offline').').';
        }
        if ($facts['displayed']) {
            $pts += 4;
        } else {
            $notes[] = 'Product is hidden (display is off), so buyers cannot find it.';
        }
        if ($facts['stock'] > 0) {
            $pts += 8;
        } else {
            $notes[] = 'Alibaba stock is 0. Buyers cannot order it.';
        }
        $checks[] = $this->check('Listing health', $pts, 20, $notes);

        // ── Catalog data (5, from Product Master) ─────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_weight']) {
            $pts += 2;
        } else {
            $notes[] = 'Product weight is missing.';
        }
        if ($facts['has_dimensions']) {
            $pts += 2;
        } else {
            $notes[] = 'Product dimensions (L x W x H) are missing.';
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
     * Accepted keys: title, description (HTML), images (URL list), attribute_count, price, stock, status, published,
     * displayed, length/width/height, weight, origin.
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
        foreach ((array) ($c['images'] ?? []) as $url) {
            $url = is_string($url) ? trim($url) : '';
            if (preg_match('#^(https?:)?//#i', $url)) {
                $images[$url] = true;
            }
        }

        $dim = fn ($k) => (float) ($c[$k] ?? 0) > 0;

        return [
            'title' => $title,
            'description_chars' => mb_strlen($desc),
            'images' => array_keys($images),
            'attribute_count' => (int) ($c['attribute_count'] ?? 0),
            'price' => (float) ($c['price'] ?? 0),
            'stock' => (int) ($c['stock'] ?? 0),
            'status' => trim((string) ($c['status'] ?? '')),
            'published' => (bool) ($c['published'] ?? false),
            'displayed' => (bool) ($c['displayed'] ?? false),
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
