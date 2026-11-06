<?php

namespace App\Services\Lqs;

/**
 * Rule-based Wayfair Listing Quality Score (0-100).
 *
 * Wayfair does not expose a quality score, and its Product Catalog read API is permission-gated for this supplier
 * account, so the score is built from what this app holds for the listing: the content it pushes to Wayfair
 * (Product Master + wayfair_metrics), the Partner Home listing state (wayfair_listing_statuses) and the uploaded
 * price/stock (wayfair_pricing_prices). Every finding is therefore labelled "app records".
 *
 * Weights (total 100):
 *   Title 20 · Description 20 · Key features 20 · Images 15 · Listing health 15 · Catalog data 10
 */
class WayfairListingQualityScorer
{
    /** Brand every 5 Core listing title should carry (Wayfair shows brand + product name together). */
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
            $notes[] = 'No title on record.';
        } else {
            $pts += match (true) {
                $len >= 60 => 10,
                $len >= 45 => 8,
                $len >= 30 => 5,
                $len >= 15 => 2,
                default => 1,
            };
            if ($len < 60) {
                $notes[] = "Title is only {$len} characters. Aim for 60-100: brand, product type, key attribute (size, color, pack) and what shoppers search for (app records).";
            } elseif ($len > 150) {
                $pts -= 2;
                $notes[] = "Title is {$len} characters. Trim it to 150 or less (app records).";
            }
            if (stripos($title, self::BRAND) !== false || stripos($title, '5core') !== false) {
                $pts += 3;
            } else {
                $notes[] = 'Title does not carry the brand ('.self::BRAND.'). Wayfair shoppers see brand and product name together.';
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
                $notes[] = "Title contains promotional filler (\"{$promoHit}\"). Wayfair rejects price/promo claims in titles.";
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
        $checks[] = $this->check('Title', max(0.0, $pts), 20, $notes);

        // ── Description / marketing copy (20) ─────────────────────
        $chars = $facts['description_chars'];
        $notes = [];
        $pts = match (true) {
            $chars >= 500 => 20,
            $chars >= 350 => 16,
            $chars >= 200 => 11,
            $chars >= 100 => 6,
            $chars > 0 => 2,
            default => 0,
        };
        if ($chars < 500) {
            $notes[] = $chars === 0
                ? 'No marketing description on record. Wayfair shows it on the product page.'
                : "Description is only {$chars} characters. Add specs, dimensions, materials, use cases and what is in the box (500+) (app records).";
        }
        $checks[] = $this->check('Description', $pts, 20, $notes);

        // ── Key features / bullets (20) ───────────────────────────
        $bullets = $facts['bullets'];
        $notes = [];
        $pts = min(5, count($bullets)) * 3;
        if (count($bullets) < 5) {
            $notes[] = (count($bullets) === 0 ? 'No key features on record.' : 'Only '.count($bullets).' key feature(s) on record.').' Wayfair shows up to 5-8 feature bullets. Add benefit-led bullets (app records).';
        }
        if ($bullets !== []) {
            $avg = array_sum(array_map('mb_strlen', $bullets)) / count($bullets);
            if ($avg >= 60) {
                $pts += 5;
            } elseif ($avg >= 30) {
                $pts += 3;
                $notes[] = 'Key features are short (avg '.round($avg).' chars). Aim for 60+ with concrete specs (app records).';
            } else {
                $pts += 1;
                $notes[] = 'Key features are very short (avg '.round($avg).' chars). Write full benefit sentences (app records).';
            }
        }
        $checks[] = $this->check('Key features', $pts, 20, $notes);

        // ── Images (15) ───────────────────────────────────────────
        $images = count($facts['images']);
        $notes = [];
        $pts = match (true) {
            $images >= 8 => 15,
            $images >= 6 => 13,
            $images >= 4 => 9,
            $images >= 2 => 5,
            $images === 1 => 2,
            default => 0,
        };
        if ($images < 8) {
            $notes[] = "Only {$images} image(s) on record. Use 8+ (main on white, angles, in-use/room scene, scale, what's included) (app records).";
        }
        $checks[] = $this->check('Images', $pts, 15, $notes);

        // ── Listing health (15) ───────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['live']) {
            $pts += 6;
        } else {
            $notes[] = 'Listing is marked Inactive on Wayfair Partner Home. It cannot sell until reactivated.';
        }
        if ($facts['has_buyer_link']) {
            $pts += 2;
        } else {
            $notes[] = 'No shopper-facing Wayfair product page link on record yet.';
        }
        if ($facts['price'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'No uploaded Wayfair price.';
        }
        if ($facts['stock'] > 0) {
            $pts += 4;
        } else {
            $notes[] = 'Wayfair stock is 0. The listing shows unavailable to shoppers.';
        }
        $checks[] = $this->check('Listing health', $pts, 15, $notes);

        // ── Catalog data (10) ─────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_upc']) {
            $pts += 3;
        } else {
            $notes[] = 'No UPC/GTIN. Identifiers improve Wayfair catalog matching.';
        }
        if ($facts['has_dimensions']) {
            $pts += 2;
        } else {
            $notes[] = 'Product dimensions (L x W x H) are missing.';
        }
        if ($facts['has_weight']) {
            $pts += 2;
        } else {
            $notes[] = 'Product weight is missing.';
        }
        if ($facts['has_origin']) {
            $pts += 1;
        } else {
            $notes[] = 'Country of origin is missing.';
        }
        if ($facts['has_video']) {
            $pts += 2;
        } else {
            $notes[] = 'No product video. Wayfair shows video on the product page.';
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
     * Accepted keys: title, description (plain or HTML), bullets (list), images (list of URLs), live (bool, default true),
     * buyer_link, price, stock, upc, length/width/height, weight, origin, has_video.
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
        // A description that only repeats the title is a placeholder, not marketing copy.
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
            'live' => (bool) ($c['live'] ?? true),
            'has_buyer_link' => trim((string) ($c['buyer_link'] ?? '')) !== '',
            'price' => (float) ($c['price'] ?? 0),
            'stock' => (int) ($c['stock'] ?? 0),
            'has_upc' => strlen(preg_replace('/\D+/', '', (string) ($c['upc'] ?? '')) ?? '') >= 8,
            'has_dimensions' => $dim('length') && $dim('width') && $dim('height'),
            'has_weight' => $dim('weight'),
            'has_origin' => trim((string) ($c['origin'] ?? '')) !== '',
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
        $lines = ["API score {$total}/100 (app records)."];
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
