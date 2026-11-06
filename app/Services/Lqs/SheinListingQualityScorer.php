<?php

namespace App\Services\Lqs;

/**
 * Rule-based Shein Listing Quality Score (0-100).
 *
 * Shein has no quality-score API. The seller product query (synced into shein_metrics.raw_data) gives the live
 * images, product attributes, shelf state, stock, price and dimensions, but NOT the title, description or bullets
 * (they come back empty), so those use what this app pushes to Shein (Product Master / shein_metrics) and are
 * labelled "app records" in the findings.
 *
 * Weights (total 100):
 *   Title 20 · Description 15 · Key features 10 · Images 20 · Product attributes 10 · Listing health 15 · Catalog data 10
 */
class SheinListingQualityScorer
{
    /** Brand every 5 Core listing title should carry. */
    private const BRAND = '5 Core';

    /** Shein cuts titles at this length when this app pushes them (services.shein.title_max_length). */
    public const TITLE_MAX = 80;

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
            $notes[] = 'No title on record.';
        } else {
            $pts += match (true) {
                $len >= 50 => 10,
                $len >= 40 => 8,
                $len >= 30 => 6,
                $len >= 20 => 3,
                default => 1,
            };
            if ($len < 50) {
                $notes[] = "Title is only {$len} characters. Use 50-".self::TITLE_MAX.': product type, key attribute (size, color, pack) and what shoppers search for (app records).';
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
                $notes[] = "Title contains promotional filler (\"{$promoHit}\"). Shein rejects price/promo claims in titles.";
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
        $checks[] = $this->check('Title', $pts, 20, $notes);

        // ── Description (15) ──────────────────────────────────────
        $chars = $facts['description_chars'];
        $notes = [];
        $pts = match (true) {
            $chars >= 300 => 15,
            $chars >= 200 => 12,
            $chars >= 100 => 8,
            $chars > 0 => 3,
            default => 0,
        };
        if ($chars < 300) {
            $notes[] = $chars === 0
                ? 'No product description on record.'
                : "Description is only {$chars} characters. Add specs, materials, size and what is in the package (300+) (app records).";
        }
        $checks[] = $this->check('Description', $pts, 15, $notes);

        // ── Key features / bullets (10) ───────────────────────────
        $bullets = $facts['bullets'];
        $notes = [];
        $pts = min(5, count($bullets)) * 1.6;
        if (count($bullets) < 5) {
            $notes[] = (count($bullets) === 0 ? 'No key features on record.' : 'Only '.count($bullets).' key feature(s) on record.').' Add 5 benefit-led bullets (app records).';
        }
        if ($bullets !== []) {
            $avg = array_sum(array_map('mb_strlen', $bullets)) / count($bullets);
            if ($avg >= 50) {
                $pts += 2;
            } elseif ($avg >= 25) {
                $pts += 1;
                $notes[] = 'Key features are short (avg '.round($avg).' chars). Aim for 50+ with concrete specs (app records).';
            } else {
                $notes[] = 'Key features are very short (avg '.round($avg).' chars). Write full benefit sentences (app records).';
            }
        }
        $checks[] = $this->check('Key features', $pts, 10, $notes);

        // ── Images (20, live from Shein) ──────────────────────────
        $images = count($facts['images']);
        $notes = [];
        $pts = match (true) {
            $images >= 8 => 20,
            $images >= 6 => 16,
            $images >= 4 => 11,
            $images >= 2 => 6,
            $images === 1 => 2,
            default => 0,
        };
        if ($images < 8) {
            $notes[] = "Only {$images} image(s) on Shein. Use 8+ (square main on white, angles, in-use, scale, detail close-ups).";
        }
        $checks[] = $this->check('Images', $pts, 20, $notes);

        // ── Product attributes (10, live from Shein) ──────────────
        $attrs = $facts['attributes'];
        $notes = [];
        $pts = match (true) {
            $attrs >= 8 => 10,
            $attrs >= 5 => 8,
            $attrs >= 3 => 5,
            $attrs >= 1 => 2,
            default => 0,
        };
        if ($attrs < 8) {
            $notes[] = "Only {$attrs} product attribute(s) filled on Shein. Fill the category attributes (material, power, size, compatibility) so the listing shows in filtered search.";
        }
        $checks[] = $this->check('Product attributes', $pts, 10, $notes);

        // ── Listing health (15) ───────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['active']) {
            $pts += 6;
        } else {
            $notes[] = 'Listing is not active/on shelf on Shein. It cannot sell until fixed.';
        }
        if ($facts['price'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'No valid Shein price.';
        }
        if ($facts['stock'] > 0) {
            $pts += 4;
        } else {
            $notes[] = 'Shein stock is 0. The listing shows sold out.';
        }
        if ($facts['listed']) {
            $pts += 2;
        } else {
            $notes[] = 'Listing status is still Pending in Seller Hub.';
        }
        $checks[] = $this->check('Listing health', $pts, 15, $notes);

        // ── Catalog data (10) ─────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_dimensions']) {
            $pts += 3;
        } else {
            $notes[] = 'Package dimensions (L x W x H) are missing.';
        }
        if ($facts['has_weight']) {
            $pts += 3;
        } else {
            $notes[] = 'Package weight is missing.';
        }
        if ($facts['has_brand']) {
            $pts += 2;
        } else {
            $notes[] = 'No brand set on the Shein product.';
        }
        if ($facts['has_video']) {
            $pts += 2;
        } else {
            $notes[] = 'No product video.';
        }
        $checks[] = $this->check('Catalog data', $pts, 10, $notes);

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
     * Accepted keys: title, description (plain or HTML), bullets (list), images (list of URLs), attributes (int),
     * active, listed, price, stock, length/width/height, weight, has_brand, has_video.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    public function facts(array $c): array
    {
        $title = mb_substr(trim((string) ($c['title'] ?? '')), 0, self::TITLE_MAX);

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
            'attributes' => max(0, (int) ($c['attributes'] ?? 0)),
            'active' => (bool) ($c['active'] ?? false),
            'listed' => (bool) ($c['listed'] ?? false),
            'price' => (float) ($c['price'] ?? 0),
            'stock' => (int) ($c['stock'] ?? 0),
            'has_dimensions' => $dim('length') && $dim('width') && $dim('height'),
            'has_weight' => $dim('weight'),
            'has_brand' => (bool) ($c['has_brand'] ?? false),
            'has_video' => (bool) ($c['has_video'] ?? false),
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
