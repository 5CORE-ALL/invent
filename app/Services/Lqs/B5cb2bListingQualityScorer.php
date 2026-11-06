<?php

namespace App\Services\Lqs;

/**
 * Rule-based Business 5 Core (B2B store) Listing Quality Score (0-100) from the synced store listing
 * (b5c_b2b_products + payload from business5core.com/api/listings).
 *
 * The store API returns the name, short description, images, categories, brand, size, weight, price, special price,
 * stock and active flag, but not the long description, so "Description" scores the short description only.
 *
 * Weights (total 100):
 *   Title 25 · Images 25 · Short description 10 · Categorisation 10 · Pricing 10 · Listing health 15 · Catalog data 5
 */
class B5cb2bListingQualityScorer extends StoredListingScorer
{
    public function score(array $content): array
    {
        $facts = $this->facts($content);
        $checks = [];

        $checks[] = $this->titleCheck($facts['title'], 25, 60, 'Product name');

        $checks[] = $this->imageCheck(count($facts['images']), 25, 6);

        $checks[] = $this->textCheck(
            'Description', $facts['description_chars'], 10, 150,
            'No short description on the store product.',
            'Short description is only %d characters. Add a 150+ character summary of what it is and who it is for.'
        );

        // ── Categorisation (10) ───────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['category_count'] > 0) {
            $pts += 6;
        } else {
            $notes[] = 'Product is not in any store category.';
        }
        if ($facts['has_brand']) {
            $pts += 4;
        } else {
            $notes[] = 'No brand set on the store product.';
        }
        $checks[] = $this->check('Categorisation', $pts, 10, $notes);

        // ── Pricing (10) ──────────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['price'] > 0) {
            $pts += 5;
        } else {
            $notes[] = 'No valid list price.';
        }
        if ($facts['selling_price'] > 0 && ($facts['price'] <= 0 || $facts['selling_price'] <= $facts['price'])) {
            $pts += 5;
        } else {
            $notes[] = $facts['selling_price'] > 0
                ? 'Selling price is above the list price.'
                : 'No valid selling price.';
        }
        $checks[] = $this->check('Pricing', $pts, 10, $notes);

        // ── Listing health (15) ───────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['active']) {
            $pts += 5;
        } else {
            $notes[] = 'Product is inactive on the B2B store.';
        }
        if ($facts['in_stock']) {
            $pts += 4;
        } else {
            $notes[] = 'Store marks the product out of stock.';
        }
        if ($facts['stock'] > 0) {
            $pts += 6;
        } else {
            $notes[] = 'Store quantity is 0. Buyers cannot order it.';
        }
        $checks[] = $this->check('Listing health', $pts, 15, $notes);

        // ── Catalog data (5) ──────────────────────────────────────
        $notes = [];
        $pts = 0;
        if ($facts['has_weight']) {
            $pts += 2;
        } else {
            $notes[] = 'Weight is missing on the store product (needed for B2B freight quotes).';
        }
        if ($facts['has_dimensions']) {
            $pts += 3;
        } else {
            $notes[] = 'Dimensions (L x W x H) are missing on the store product.';
        }
        $checks[] = $this->check('Catalog data', $pts, 5, $notes);

        return ['facts' => $facts] + $this->finish($checks);
    }

    /**
     * Accepted keys: title, short_description, images, category_count, has_brand, price, selling_price, stock,
     * in_stock, active, length/width/height, weight.
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    public function facts(array $c): array
    {
        $title = trim((string) ($c['title'] ?? ''));
        $desc = $this->plainText((string) ($c['short_description'] ?? ''));
        if ($desc !== '' && strcasecmp($desc, $title) === 0) {
            $desc = '';
        }

        $dim = fn ($k) => (float) ($c[$k] ?? 0) > 0;

        return [
            'title' => $title,
            'description_chars' => mb_strlen($desc),
            'images' => $this->cleanUrls((array) ($c['images'] ?? [])),
            'category_count' => (int) ($c['category_count'] ?? 0),
            'has_brand' => (bool) ($c['has_brand'] ?? false),
            'price' => (float) ($c['price'] ?? 0),
            'selling_price' => (float) ($c['selling_price'] ?? 0),
            'stock' => (int) ($c['stock'] ?? 0),
            'in_stock' => (bool) ($c['in_stock'] ?? false),
            'active' => (bool) ($c['active'] ?? false),
            'has_dimensions' => $dim('length') && $dim('width') && $dim('height'),
            'has_weight' => $dim('weight'),
        ];
    }
}
