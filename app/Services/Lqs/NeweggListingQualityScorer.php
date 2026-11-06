<?php

namespace App\Services\Lqs;

/**
 * Rule-based Newegg Listing Quality Score (0-100) from the synced Newegg item (newegg_items.raw_json: website title,
 * bullet description, images, size, weight, UPC, status) and Newegg price/inventory (newegg_pricing).
 *
 * Weights (total 100):
 *   Title 20 · Key features 25 · Images 20 · Pricing 10 · Listing health 15 · Catalog data 10
 */
class NeweggListingQualityScorer extends StoredListingScorer
{
    public function score(array $content): array
    {
        $facts = $this->facts($content);
        $checks = [];

        $checks[] = $this->titleCheck($facts['title'], 20, 60, 'Website title');

        // ── Key features (25) ─────────────────────────────────────
        $bullets = $facts['bullets'];
        $notes = [];
        $pts = min(5, count($bullets)) * 3.0;
        if (count($bullets) < 5) {
            $notes[] = (count($bullets) === 0 ? 'No bullet description.' : 'Only '.count($bullets).' bullet point(s).').' Add 5 benefit-led bullets.';
        }
        if ($bullets !== []) {
            $avg = array_sum(array_map('mb_strlen', $bullets)) / count($bullets);
            if ($avg >= 80) {
                $pts += 5;
            } elseif ($avg >= 40) {
                $pts += 3;
                $notes[] = 'Bullets are short (avg '.round($avg).' chars). Aim for 80+ with concrete specs.';
            } else {
                $notes[] = 'Bullets are very short (avg '.round($avg).' chars). Write full benefit sentences.';
            }
            $total = array_sum(array_map('mb_strlen', $bullets));
            if ($total >= 600) {
                $pts += 5;
            } elseif ($total >= 300) {
                $pts += 3;
                $notes[] = "Bullet text totals {$total} characters. Aim for 600+ so shoppers get the full spec.";
            } else {
                $notes[] = "Bullet text totals only {$total} characters. Aim for 600+.";
            }
        }
        $checks[] = $this->check('Key features', $pts, 25, $notes);

        $checks[] = $this->imageCheck(count($facts['images']), 20, 6);

        // ── Pricing (10) ──────────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['price'] > 0) {
            $pts += 5;
        } else {
            $notes[] = 'No Newegg selling price.';
        }
        if ($facts['msrp'] > 0) {
            $pts += 3;
            if ($facts['price'] > 0 && $facts['msrp'] >= $facts['price']) {
                $pts += 2;
            } elseif ($facts['price'] > 0) {
                $notes[] = 'MSRP is below the selling price. Shoppers see no savings; set MSRP above price.';
            }
        } else {
            $notes[] = 'No MSRP set. Newegg shows a savings badge when MSRP is above price.';
        }
        $checks[] = $this->check('Pricing', $pts, 10, $notes);

        // ── Listing health (15) ───────────────────────────────────
        $notes = [];
        $pts = 0;
        if (strcasecmp($facts['status'], 'Active') === 0) {
            $pts += 6;
        } else {
            $notes[] = 'Newegg item status is "'.($facts['status'] ?: 'unknown').'", not Active.';
        }
        if ($facts['active']) {
            $pts += 3;
        } else {
            $notes[] = 'Newegg price/inventory record is inactive or missing.';
        }
        if ($facts['stock'] > 0) {
            $pts += 6;
        } else {
            $notes[] = 'Newegg stock is 0. The listing cannot sell.';
        }
        $checks[] = $this->check('Listing health', $pts, 15, $notes);

        // ── Catalog data (10) ─────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_upc']) {
            $pts += 4;
        } else {
            $notes[] = 'No UPC/GTIN on the item.';
        }
        if ($facts['has_dimensions']) {
            $pts += 2;
        } else {
            $notes[] = 'Item dimensions (L x W x H) are missing.';
        }
        if ($facts['has_weight']) {
            $pts += 2;
        } else {
            $notes[] = 'Item weight is missing.';
        }
        if ($facts['has_category']) {
            $pts += 2;
        } else {
            $notes[] = 'No Newegg subcategory set.';
        }
        $checks[] = $this->check('Catalog data', $pts, 10, $notes);

        return ['facts' => $facts] + $this->finish($checks);
    }

    /**
     * Accepted keys: title, bullets (list or newline text), images (list or comma text), price, msrp, stock, status,
     * active, upc, length/width/height, weight, category.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    public function facts(array $c): array
    {
        $title = trim((string) ($c['title'] ?? ''));

        $bullets = $c['bullets'] ?? [];
        if (is_string($bullets)) {
            $bullets = preg_split('/\r\n|\r|\n/', $bullets) ?: [];
        }
        $bullets = array_values(array_filter(array_map(fn ($b) => trim(strip_tags((string) $b)), (array) $bullets),
            fn ($b) => mb_strlen($b) >= 5 && strcasecmp($b, $title) !== 0));

        $images = $c['images'] ?? [];
        if (is_string($images)) {
            $images = preg_split('/[,\s]+/', $images) ?: [];
        }

        $dim = fn ($k) => (float) ($c[$k] ?? 0) > 0;

        return [
            'title' => $title,
            'bullets' => $bullets,
            'images' => $this->cleanUrls((array) $images),
            'price' => (float) ($c['price'] ?? 0),
            'msrp' => (float) ($c['msrp'] ?? 0),
            'stock' => (int) ($c['stock'] ?? 0),
            'status' => trim((string) ($c['status'] ?? '')),
            'active' => (bool) ($c['active'] ?? false),
            'has_upc' => strlen(preg_replace('/\D+/', '', (string) ($c['upc'] ?? '')) ?? '') >= 8,
            'has_dimensions' => $dim('length') && $dim('width') && $dim('height'),
            'has_weight' => $dim('weight'),
            'has_category' => trim((string) ($c['category'] ?? '')) !== '',
        ];
    }
}
