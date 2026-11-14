<?php

namespace App\Support;

use App\Models\AmazonDataView;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The Push column on /pls-pricing reads PLS_STATUS from amazon_data_views (✓✓ pushed, ✗ error).
 * The shared S PRC runner (ChannelPushSpriceRunner) calls this after every PLS push, the same way the old
 * PLS-only push did, so the column keeps following the real result.
 */
class PlsPushStatus
{
    public static function set(string $sku, string $status): void
    {
        $sku = strtoupper(trim($sku));
        if ($sku === '') {
            return;
        }
        try {
            // One indexed lookup per pushed SKU. amazon_data_views is large, so it is never loaded whole.
            $view = AmazonDataView::query()->whereRaw('UPPER(TRIM(sku)) = ?', [$sku])->first()
                ?? new AmazonDataView(['sku' => $sku]);
            $existing = is_array($view->value)
                ? $view->value
                : (is_string($view->value) && $view->value !== '' ? (json_decode($view->value, true) ?: []) : []);
            $existing['PLS_STATUS'] = $status;
            $existing['PLS_STATUS_UPDATED_AT'] = now()->toDateTimeString();
            $view->value = $existing;
            $view->save();
        } catch (Throwable $e) {
            Log::error('[PlsPushStatus] PLS_STATUS save failed', ['sku' => $sku, 'error' => $e->getMessage()]);
        }
    }
}
