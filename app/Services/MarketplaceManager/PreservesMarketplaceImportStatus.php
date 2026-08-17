<?php

namespace App\Services\MarketplaceManager;

/**
 * Keep queued/imported Shopify state when a marketplace re-fetch upserts the same order.
 */
trait PreservesMarketplaceImportStatus
{
    /**
     * @param  object|null  $existing  Row with import_status / shopify_order_id
     * @return array<string, string>
     */
    protected function importStatusForUpsert(?object $existing): array
    {
        $shopifyId = trim((string) ($existing->shopify_order_id ?? ''));
        if ($shopifyId !== '') {
            return [];
        }

        $status = strtolower(trim((string) ($existing->import_status ?? '')));
        if (in_array($status, [
            'queued',
            'imported',
            'skipped_fba',
            'skipped_pre_cutoff',
            'skipped_cancelled',
            'skipped_closed',
            'skipped_unpaid',
            'skipped_pre_july7',
        ], true)) {
            return [];
        }

        return ['import_status' => 'ready'];
    }
}
