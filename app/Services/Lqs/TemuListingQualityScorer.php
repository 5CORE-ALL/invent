<?php

namespace App\Services\Lqs;

/**
 * Rule-based Temu Listing Quality Score (0-100) from a live `bg.local.goods.detail.query` result.
 *
 * Temu's Open API has no listing-quality endpoint, so this scores what Temu search/conversion rewards and
 * what the API exposes. It is deterministic (same listing, same score) and returns a per-check breakdown.
 *
 * Weights (total 100):
 *   Title 20 · Photos & video 25 · Bullet points 20 · Description 10 · Attributes 15 · SKU data 5 · Shipping promise 5
 */
class TemuListingQualityScorer
{
    private const PROMO_WORDS = [
        'free shipping', 'best price', 'best seller', 'bestseller', 'hot sale', 'on sale', 'wow', 'must see',
        'cheap', 'lowest price', 'limited time', 'buy now', '100%', 'guaranteed', '!!!',
    ];

    /**
     * @param  array<string, mixed>  $goods  "result" node of bg.local.goods.detail.query
     * @return array{score: float, facts: array<string, mixed>, checks: list<array<string, mixed>>, findings: string, suggestions: string}
     */
    public function score(array $goods): array
    {
        $facts = $this->facts($goods);
        $checks = [];

        // ── Title (20) ────────────────────────────────────────────
        $title = $facts['title'];
        $len = mb_strlen($title);
        $pts = 0.0;
        $notes = [];
        if ($len === 0) {
            $notes[] = 'Title is missing.';
        } else {
            // Temu does not cap titles at 80 chars; long, keyword-rich titles (about 80-150) search best.
            $pts += match (true) {
                $len >= 90 => 12,
                $len >= 70 => 10,
                $len >= 50 => 7,
                $len >= 30 => 4,
                default => 2,
            };
            if ($len < 70) {
                $notes[] = "Title is only {$len} characters. Aim for 80-150: brand, product type, key specs (size, power, material) and buyer search keywords.";
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
                $notes[] = "Title contains promotional filler (\"{$promoHit}\"). Temu can suppress listings with price/promo claims in the title.";
            } else {
                $pts += 4;
            }
            $words = preg_split('/\s+/', preg_replace('/[^a-z0-9\s]/', '', $lower) ?? '') ?: [];
            $counts = array_count_values(array_filter($words, fn ($w) => strlen($w) > 3));
            $stuffed = array_filter($counts, fn ($c) => $c >= 3);
            if ($stuffed === []) {
                $pts += 4;
            } else {
                $notes[] = 'Title repeats "'.array_key_first($stuffed).'" several times. Use each keyword once.';
            }
        }
        $checks[] = $this->check('Title', $pts, 20, $notes);

        // ── Photos & video (25) ───────────────────────────────────
        $photos = $facts['photo_count'];
        $notes = [];
        $pts = match (true) {
            $photos >= 8 => 18,
            $photos >= 6 => 15,
            $photos >= 4 => 11,
            $photos >= 2 => 6,
            $photos === 1 => 3,
            default => 0,
        };
        if ($photos < 8) {
            $notes[] = "Only {$photos} product photo(s). Use 8+ (main on white, angles, in-use, size/scale, what's included).";
        }
        if ($facts['detail_image_count'] > 0) {
            $pts += 3;
        } else {
            $notes[] = 'No detail/infographic images. Add feature or size-chart images to the description gallery.';
        }
        if ($facts['skus_missing_images'] > 0) {
            $notes[] = $facts['skus_missing_images'].' of '.$facts['sku_count'].' variation(s) have no photo of their own. Each variation needs its own image.';
        } else {
            $pts += 2;
        }
        if ($facts['has_video']) {
            $pts += 2;
        } else {
            $notes[] = 'No product video. Videos lift conversion on Temu.';
        }
        $checks[] = $this->check('Photos & video', min(25, $pts), 25, $notes);

        // ── Bullet points (20) ────────────────────────────────────
        $bullets = $facts['bullets'];
        $notes = [];
        $pts = min(5, count($bullets)) * 3;
        if (count($bullets) < 5) {
            $notes[] = count($bullets) === 0
                ? 'No bullet points. Add 5 benefit-led bullets (use, material, size, what is included, compatibility).'
                : 'Only '.count($bullets).' bullet point(s). Fill all 5.';
        }
        if ($bullets !== []) {
            $avg = array_sum(array_map('mb_strlen', $bullets)) / count($bullets);
            if ($avg >= 60) {
                $pts += 5;
            } elseif ($avg >= 30) {
                $pts += 3;
                $notes[] = 'Bullets are short (avg '.round($avg).' chars). Aim for 60+ with concrete specs and benefits.';
            } else {
                $pts += 1;
                $notes[] = 'Bullets are very short (avg '.round($avg).' chars). Write full benefit sentences.';
            }
        }
        $checks[] = $this->check('Bullet points', $pts, 20, $notes);

        // ── Description (10) ──────────────────────────────────────
        $chars = $facts['description_chars'];
        $notes = [];
        $pts = match (true) {
            $chars >= 600 => 10,
            $chars >= 300 => 7,
            $chars >= 100 => 4,
            $chars > 0 => 2,
            default => 0,
        };
        if ($chars < 600) {
            $notes[] = $chars === 0
                ? 'Description is empty.'
                : "Description is only {$chars} characters. Add specs, dimensions, compatibility and what is in the box (600+).";
        }
        $checks[] = $this->check('Description', $pts, 10, $notes);

        // ── Attributes (15) ───────────────────────────────────────
        $props = $facts['property_count'];
        $notes = [];
        $pts = min(15.0, round($props * 1.5, 1)); // 10+ attributes = full
        if ($props < 10) {
            $notes[] = "Only {$props} product attribute(s) filled. Complete every category attribute (brand, material, color, power, compatibility...); Temu filters and ranks on them.";
        }
        $checks[] = $this->check('Attributes', $pts, 15, $notes);

        // ── SKU data (5) ──────────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_barcode']) {
            $pts += 2;
        } else {
            $notes[] = 'No UPC/EAN barcode on the SKUs.';
        }
        if ($facts['skus_missing_package'] === 0 && $facts['sku_count'] > 0) {
            $pts += 3;
        } else {
            $notes[] = $facts['skus_missing_package'].' variation(s) are missing package weight/dimensions, which affects shipping cost and delivery estimates.';
        }
        $checks[] = $this->check('SKU data', $pts, 5, $notes);

        // ── Shipping promise (5) ──────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_cost_template']) {
            $pts += 2;
        } else {
            $notes[] = 'No shipping cost template on the listing.';
        }
        $days = $facts['shipment_limit_days'];
        if ($days !== null && $days > 0 && $days <= 2) {
            $pts += 3;
        } elseif ($days !== null && $days > 0 && $days <= 3) {
            $pts += 2;
            $notes[] = "Ships within {$days} days. 1-2 day handling ranks and converts better.";
        } else {
            $notes[] = $days ? "Handling time is {$days} days. Reduce to 2 or fewer." : 'Handling time is not set.';
        }
        $checks[] = $this->check('Shipping promise', $pts, 5, $notes);

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
     * @param  array<string, mixed>  $goods
     * @return array<string, mixed>
     */
    public function facts(array $goods): array
    {
        $gallery = is_array($goods['goodsGallery'] ?? null) ? $goods['goodsGallery'] : [];
        $carousel = $this->strings($gallery['goodsCarouselImage'] ?? []);
        $detailImages = $this->strings($gallery['detailImage'] ?? []);

        $hasVideo = false;
        foreach ($gallery as $key => $value) {
            if (stripos((string) $key, 'video') !== false && $value !== null && $value !== '' && $value !== []) {
                $hasVideo = true;
            }
        }

        $skus = array_values(array_filter((array) ($goods['skuList'] ?? []), 'is_array'));
        $maxSkuImages = 0;
        $missingImages = 0;
        $missingPackage = 0;
        $hasBarcode = false;
        foreach ($skus as $sku) {
            $images = $this->strings($sku['images'] ?? []);
            $maxSkuImages = max($maxSkuImages, count($images));
            if ($images === []) {
                $missingImages++;
            }
            $express = is_array($sku['productExpressInfo'] ?? null) ? $sku['productExpressInfo'] : [];
            $weight = (float) ($express['weightInfo']['weight'] ?? 0);
            $dims = $express['volumeInfo'] ?? [];
            $hasDims = (float) ($dims['length'] ?? 0) > 0 && (float) ($dims['width'] ?? 0) > 0 && (float) ($dims['height'] ?? 0) > 0;
            if ($weight <= 0 || ! $hasDims) {
                $missingPackage++;
            }
            if ($this->strings($sku['barCodeList'] ?? []) !== []) {
                $hasBarcode = true;
            }
        }

        $properties = 0;
        foreach ((array) ($goods['goodsProperties'] ?? []) as $prop) {
            if (! is_array($prop)) {
                continue;
            }
            $value = $prop['value'] ?? $prop['vid'] ?? $prop['numberInputValue'] ?? null;
            if ($value !== null && $value !== '' && $value !== 0) {
                $properties++;
            }
        }

        $description = html_entity_decode((string) ($goods['goodsDesc'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $description) ?? $description;
        $descText = trim(preg_replace('/\s+/', ' ', strip_tags($description)) ?? '');

        $promise = is_array($goods['goodsServicePromise'] ?? null) ? $goods['goodsServicePromise'] : [];
        $limit = (int) ($promise['shipmentLimitDay'] ?? 0);

        return [
            'goods_id' => (string) ($goods['goodsId'] ?? ''),
            'title' => trim((string) ($goods['goodsName'] ?? '')),
            'category' => trim((string) ($goods['catName'] ?? '')),
            // Carousel is the main gallery; fall back to the richest variation gallery when it is empty.
            'photo_count' => max(count($carousel), $maxSkuImages),
            'detail_image_count' => count($detailImages),
            'has_video' => $hasVideo,
            'bullets' => array_values(array_filter($this->strings($goods['bulletPoints'] ?? []), fn ($b) => mb_strlen($b) >= 5)),
            'description_chars' => mb_strlen($descText),
            'property_count' => $properties,
            'sku_count' => count($skus),
            'skus_missing_images' => $missingImages,
            'skus_missing_package' => $missingPackage,
            'has_barcode' => $hasBarcode,
            'has_cost_template' => trim((string) ($promise['costTemplateId'] ?? '')) !== '',
            'shipment_limit_days' => $limit > 0 ? $limit : null,
        ];
    }

    /** @return list<string> */
    private function strings(mixed $value): array
    {
        if (! is_array($value)) {
            $value = $value === null || $value === '' ? [] : [$value];
        }

        return array_values(array_filter(array_map(
            fn ($v) => is_scalar($v) ? trim((string) $v) : '',
            $value
        ), fn ($v) => $v !== ''));
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
