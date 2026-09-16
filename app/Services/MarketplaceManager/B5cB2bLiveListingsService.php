<?php

namespace App\Services\MarketplaceManager;

use App\Models\B5cB2bProduct;
use App\Services\Business5CoreB2bApiService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class B5cB2bLiveListingsService
{
    public function __construct(protected Business5CoreB2bApiService $api)
    {
    }

    /**
     * @return array{success: bool, message: string, stored: int}
     */
    public function refresh(): array
    {
        if (! $this->api->isConfigured()) {
            return ['success' => false, 'message' => 'Business 5 Core B2B API is not configured.', 'stored' => 0];
        }
        if (! Schema::hasTable('b5c_b2b_products')) {
            return ['success' => false, 'message' => 'Run migrations for b5c_b2b_products.', 'stored' => 0];
        }

        try {
            $listings = $this->api->fetchAllListings();
            $inventory = $this->api->fetchAllInventoryRows();
        } catch (\Throwable $e) {
            Log::warning('B5C B2B listings refresh failed', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage(), 'stored' => 0];
        }

        $invBySku = [];
        foreach ($inventory as $row) {
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($sku !== '') {
                $invBySku[strtoupper($sku)] = $row;
            }
            foreach (is_array($row['variants'] ?? null) ? $row['variants'] : [] as $variant) {
                $vSku = trim((string) ($variant['sku'] ?? ''));
                if ($vSku !== '') {
                    $invBySku[strtoupper($vSku)] = array_merge($row, $variant);
                }
            }
        }

        $stored = 0;
        foreach ($listings as $listing) {
            $sku = trim((string) ($listing['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $inv = $invBySku[strtoupper($sku)] ?? [];
            B5cB2bProduct::query()->updateOrCreate(
                ['sku' => $sku],
                [
                    'listing_id' => $listing['id'] ?? $inv['id'] ?? null,
                    'slug' => $listing['slug'] ?? $inv['slug'] ?? null,
                    'title' => $listing['name'] ?? $inv['name'] ?? $sku,
                    'qty' => $inv['qty'] ?? $listing['qty'] ?? null,
                    'in_stock' => $inv['in_stock'] ?? $listing['in_stock'] ?? null,
                    'is_active' => $inv['is_active'] ?? $listing['is_active'] ?? true,
                    'payload' => $listing,
                ]
            );
            $stored++;
        }

        return [
            'success' => true,
            'message' => 'Stored '.$stored.' Business 5 Core B2B listing(s).',
            'stored' => $stored,
        ];
    }
}
