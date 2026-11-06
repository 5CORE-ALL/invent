<?php

namespace App\Services\Lqs;

/**
 * Rule-based PLS (Pro Light Sounds Shopify store) Listing Quality Score (0-100) from the synced Shopify product
 * (shopify_catalog_products / _variants, store = pls): title, description HTML, images, status, vendor, type,
 * price and stock.
 *
 * Weights (total 100):
 *   Title 20 · Description 25 · Images 25 · Pricing 5 · Listing health 15 · Catalog data 10
 */
class PlsListingQualityScorer extends StoredListingScorer
{
    public function score(array $content): array
    {
        $facts = $this->facts($content);
        $checks = [];

        $checks[] = $this->titleCheck($facts['title'], 20, 60);

        $checks[] = $this->textCheck(
            'Description', $facts['description_chars'], 25, 600,
            'No product description.',
            'Description is only %d characters. Add specs, dimensions, materials, what is in the box and use cases (600+).'
        );

        // Structure bonus is folded into the description check note only; weights stay fixed.
        $checks[] = $this->imageCheck(count($facts['images']), 25, 6);

        // ── Pricing (5) ───────────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['price'] > 0) {
            $pts += 5;
        } else {
            $notes[] = 'No valid price on the variant.';
        }
        $checks[] = $this->check('Pricing', $pts, 5, $notes);

        // ── Listing health (15) ───────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['status'] === 'active') {
            $pts += 8;
        } else {
            $notes[] = 'Shopify product status is "'.($facts['status'] ?: 'unknown').'", not active, so it is not on the storefront.';
        }
        if ($facts['stock'] > 0) {
            $pts += 7;
        } else {
            $notes[] = 'PLS stock is 0. The listing cannot sell.';
        }
        $checks[] = $this->check('Listing health', $pts, 15, $notes);

        // ── Catalog data (10) ─────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_vendor']) {
            $pts += 4;
        } else {
            $notes[] = 'No vendor/brand on the product.';
        }
        if ($facts['has_type']) {
            $pts += 6;
        } else {
            $notes[] = 'No product type set. It drives collections and search filters.';
        }
        $checks[] = $this->check('Catalog data', $pts, 10, $notes);

        return ['facts' => $facts] + $this->finish($checks);
    }

    /**
     * Accepted keys: title, description (HTML), images (list or JSON), price, stock, status, vendor, product_type.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    public function facts(array $c): array
    {
        $title = trim((string) ($c['title'] ?? ''));
        $desc = $this->plainText((string) ($c['description'] ?? ''));
        if ($desc !== '' && strcasecmp($desc, $title) === 0) {
            $desc = '';
        }

        $images = $c['images'] ?? [];
        if (is_string($images)) {
            $decoded = json_decode($images, true);
            $images = is_array($decoded) ? $decoded : (preg_split('/[,\s]+/', $images) ?: []);
        }

        return [
            'title' => $title,
            'description_chars' => mb_strlen($desc),
            'images' => $this->cleanUrls((array) $images),
            'price' => (float) ($c['price'] ?? 0),
            'stock' => (int) ($c['stock'] ?? 0),
            'status' => strtolower(trim((string) ($c['status'] ?? ''))),
            'has_vendor' => trim((string) ($c['vendor'] ?? '')) !== '',
            'has_type' => trim((string) ($c['product_type'] ?? '')) !== '',
        ];
    }
}
