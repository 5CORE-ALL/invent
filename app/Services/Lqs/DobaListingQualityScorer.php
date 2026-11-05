<?php

namespace App\Services\Lqs;

/**
 * Rule-based Doba Listing Quality Score (0-100) from a live goods/detail product (SPU) and one of its SKUs.
 *
 * Weights (total 100):
 *   Title 25 · Description 20 · Highlights (bullets) 20 · Images 20 · Pricing & stock 10 · Catalog data 5
 */
class DobaListingQualityScorer
{
    private const PROMO_WORDS = [
        'free shipping', 'best price', 'best seller', 'bestseller', 'hot sale', 'on sale', 'wow', 'must see',
        'cheap', 'lowest price', 'limited time', 'buy now', '100%', 'guaranteed', '!!!',
    ];

    /**
     * @param  array<string, mixed>  $product  one goods/detail SPU
     * @param  array<string, mixed>  $sku  the SKU inside $product['skus'] being scored
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function score(array $product, array $sku): array
    {
        $facts = $this->facts($product, $sku);
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
                $notes[] = "Title is only {$len} characters. Aim for 80-150: brand, product type, model, key spec (size, color, pack) and what shoppers search for.";
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
                ? 'Product description is empty on Doba.'
                : "Description is only {$chars} characters. Add specs, dimensions, compatibility and what is in the box (600+).";
        }
        $checks[] = $this->check('Description', $pts, 20, $notes);

        // ── Highlights / bullets (20) ─────────────────────────────
        $bullets = $facts['highlights'];
        $notes = [];
        $pts = min(5, count($bullets)) * 3;
        if (count($bullets) < 5) {
            $notes[] = (count($bullets) === 0 ? 'No highlights (bullet points).' : 'Only '.count($bullets).' highlight(s).').' Doba shows up to 5. Add benefit-led bullets.';
        }
        if ($bullets !== []) {
            $avg = array_sum(array_map('mb_strlen', $bullets)) / count($bullets);
            if ($avg >= 60) {
                $pts += 5;
            } elseif ($avg >= 30) {
                $pts += 3;
                $notes[] = 'Highlights are short (avg '.round($avg).' chars). Aim for 60+ with concrete specs.';
            } else {
                $pts += 1;
                $notes[] = 'Highlights are very short (avg '.round($avg).' chars). Write full benefit sentences.';
            }
        }
        $checks[] = $this->check('Highlights', $pts, 20, $notes);

        // ── Images (20) ───────────────────────────────────────────
        $images = count($facts['images']);
        $notes = [];
        $pts = match (true) {
            $images >= 6 => 20,
            $images >= 4 => 15,
            $images >= 2 => 9,
            $images === 1 => 4,
            default => 0,
        };
        if ($images < 6) {
            $notes[] = "Only {$images} image(s) on this SKU. Use 6+ (main on white, angles, in-use, scale, what's included).";
        }
        $checks[] = $this->check('Images', $pts, 20, $notes);

        // ── Pricing & stock (10) ──────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['msrp'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'No MSRP set.';
        }
        if ($facts['price'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'No valid price (anticipated income) on the stock record.';
        }
        if ($facts['inventory'] > 0) {
            $pts += 4;
        } else {
            $notes[] = 'No available inventory on Doba. The listing cannot sell.';
        }
        $checks[] = $this->check('Pricing & stock', $pts, 10, $notes);

        // ── Catalog data (5) ──────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['category_id'] !== '') {
            $pts += 3;
        } else {
            $notes[] = 'No category on the product.';
        }
        if ($facts['item_no'] !== '' && $facts['sku_code'] !== '') {
            $pts += 2;
        } else {
            $notes[] = 'SKU code or item number is missing.';
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
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $sku
     * @return array<string, mixed>
     */
    public function facts(array $product, array $sku): array
    {
        $desc = html_entity_decode((string) ($product['productDetails'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $desc = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $desc) ?? $desc;
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags($desc)) ?? '');

        $stock = is_array($sku['stocks'][0] ?? null) ? $sku['stocks'][0] : [];

        $images = [];
        foreach ((array) ($sku['images'] ?? []) as $img) {
            $url = is_array($img) ? ($img['url'] ?? $img['imageUrl'] ?? '') : $img;
            if (is_string($url) && trim($url) !== '') {
                $images[] = trim($url);
            }
        }

        return [
            'sku_code' => trim((string) ($sku['skuCode'] ?? '')),
            'item_no' => trim((string) ($stock['itemNo'] ?? '')),
            'spu_id' => trim((string) ($product['spuId'] ?? $product['goodsId'] ?? '')),
            'title' => trim((string) ($product['productName'] ?? '')),
            'description_chars' => mb_strlen($desc),
            'highlights' => array_values(array_filter(array_map(
                fn ($b) => trim((string) (is_array($b) ? json_encode($b) : $b)),
                (array) ($product['sellingPoints'] ?? [])
            ), fn ($b) => mb_strlen($b) >= 5)),
            'images' => array_values(array_unique($images)),
            'msrp' => (float) ($sku['msrp'] ?? 0),
            'price' => (float) ($stock['anticipatedIncome'] ?? 0),
            'inventory' => (int) ($stock['availableInventory'] ?? 0),
            'category_id' => trim((string) ($product['catId'] ?? $product['categoryId'] ?? $product['goodsCatId'] ?? '')),
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
