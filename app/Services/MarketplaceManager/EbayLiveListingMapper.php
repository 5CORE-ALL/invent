<?php

namespace App\Services\MarketplaceManager;

/**
 * Map ebay_*_metrics rows to MM live listing state from seller listing_status only.
 */
final class EbayLiveListingMapper
{
    /**
     * @return array{product_id: string, sku: string, state: string, inventory: int|null, title: ?string, price: ?float, inactive_reason?: ?string}|null
     */
    public static function mapMetricRow(object $row, bool $hasListingStatus, bool $hasInactiveReason = false): ?array
    {
        $sku = trim((string) ($row->sku ?? ''));
        if ($sku === '') {
            return null;
        }

        $itemId = trim((string) ($row->item_id ?? ''));
        $productId = $itemId !== '' ? $itemId : $sku;
        $inv = isset($row->ebay_stock) && $row->ebay_stock !== null ? (int) $row->ebay_stock : null;

        $raw = $hasListingStatus ? strtoupper(trim((string) ($row->listing_status ?? ''))) : '';
        if (in_array($raw, ['MISSING', 'NOT_LISTED'], true)) {
            return null;
        }

        $state = 'other';
        if ($raw === 'ACTIVE' || $raw === 'LIVE') {
            $state = 'active';
        } elseif (in_array($raw, ['INACTIVE', 'UNSOLD', 'ENDED', 'SOLD'], true)) {
            $state = 'inactive';
        } elseif ($raw !== '') {
            $state = MarketplacePortalStatusTabs::bucket($raw);
        }

        $reason = null;
        if ($state === 'inactive') {
            $stored = $hasInactiveReason ? trim((string) ($row->inactive_reason ?? '')) : '';
            $reason = $stored !== '' ? $stored : match ($raw) {
                'SOLD' => 'Sold / ended',
                'UNSOLD', 'ENDED' => 'Unsold / ended',
                default => 'Inactive on eBay',
            };
        }

        return [
            'product_id' => $productId,
            'sku' => $sku,
            'state' => $state,
            'inventory' => $inv,
            'title' => isset($row->ebay_title) && $row->ebay_title !== null ? (string) $row->ebay_title : null,
            'price' => isset($row->ebay_price) && $row->ebay_price !== null ? (float) $row->ebay_price : null,
            'inactive_reason' => $reason,
        ];
    }
}
