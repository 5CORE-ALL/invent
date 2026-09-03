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
        if ($skus === []) {
            return response()->json([
                'success' => false,
                'message' => 'Select at least one SKU.',
                'groups' => [],
            ], 422);
        }
        if ($channel === '') {
            return response()->json([
                'success' => false,
                'message' => 'Marketplace channel is missing. Refresh the page and try Publish again.',
                'groups' => [],
            ], 422);
        }

        $mode = strtolower(trim((string) $request->input('mode', 'variation'))) === 'single'
            ? 'single'
            : 'variation';

        return response()->json($preview->previewFromSkus($skus, $channel, $this->skuParentsFromRequest($request), $mode));
    }

    public function publish(Request $request, ListingVariationPreviewService $preview)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }

        $channel = strtolower(trim((string) $request->input('channel', '')));
        $skus = $this->skusFromRequest($request);
        if ($skus === []) {
            return response()->json([
                'success' => false,
                'message' => 'SKU is required.',
            ], 422);
        }
        if ($channel === '') {
            return response()->json([
                'success' => false,
                'message' => 'Marketplace channel is missing. Refresh the page and try Publish again.',
            ], 422);
        }

        $mode = strtolower(trim((string) $request->input('mode', 'variation'))) === 'single'
            ? 'single'
            : 'variation';
        $parentHint = trim((string) $request->input('parent', $request->input('parent_hint', '')));
        $categoryId = (int) preg_replace('/\D+/', '', (string) $request->input('category_id', ''));

        try {
            $result = $preview->publishSkus(
                $skus,
                $channel,
                ! $request->boolean('confirmed'),
                $mode,
                $parentHint,
                $categoryId > 0 ? $categoryId : null
            );
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

    /**
     * @return array<string, string>
     */
    private function skuParentsFromRequest(Request $request): array
    {
        $raw = $request->input('sku_parents', $request->input('skuParents', []));
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $sku => $parent) {
            if (is_array($parent)) {
                $sku = $parent['sku'] ?? $sku;
                $parent = $parent['parent'] ?? '';
            }
            $sku = trim((string) $sku);
            $parent = trim((string) $parent);
            if ($sku !== '') {
                $out[$sku] = $parent;
            }
        }

        return $out;
    }
}
