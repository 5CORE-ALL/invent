<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceManager\ListingVariationPreviewService;
use Illuminate\Http\Request;

class ListingPublishCommonController extends Controller
{
    public function preview(Request $request, ListingVariationPreviewService $preview)
    {
        $channel = strtolower(trim((string) $request->input('channel', '')));
        $skus = $this->skusFromRequest($request);
        if ($channel === '' || $skus === []) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least one SKU.',
                'groups' => [],
            ], 422);
        }

        return response()->json($preview->previewFromSkus($skus, $channel));
    }

    public function publish(Request $request, ListingVariationPreviewService $preview)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $channel = strtolower(trim((string) $request->input('channel', '')));
        $skus = $this->skusFromRequest($request);
        if ($channel === '' || $skus === []) {
            return response()->json([
                'success' => false,
                'message' => 'SKU is required.',
            ], 422);
        }

        try {
            $result = $preview->publishSkus($skus, $channel, ! $request->boolean('confirmed'));
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Publish failed: '.$e->getMessage(),
            ], 500);
        }

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * @return list<string>
     */
    private function skusFromRequest(Request $request): array
    {
        $skus = $request->input('skus');
        if (! is_array($skus) || $skus === []) {
            $single = trim((string) $request->input('sku', ''));
            $skus = $single !== '' ? [$single] : [];
        }

        $out = [];
        foreach ($skus as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $out[] = $sku;
            }
        }

        return $out;
    }
}
