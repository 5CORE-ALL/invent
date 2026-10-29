<?php

namespace App\Http\Controllers\MarketPlace\ListingMarketPlace;

use App\Http\Controllers\Controller;
use App\Services\MarketplaceManager\EbayListingPublishService;
use App\Services\MarketplaceManager\ListingVariationPreviewService;
use App\Services\ProductMaster\BackstageSpecificationStore;
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

    /** Search the parent (variation) listings that already exist on a marketplace. */
    public function existingParents(Request $request)
    {
        $channel = strtolower(trim((string) $request->input('channel', '')));
        if ($channel === '') {
            return response()->json(['success' => false, 'message' => 'Marketplace channel is missing.', 'parents' => []], 422);
        }

        try {
            $parents = \App\Support\Marketplace\ListingExistingParents::search(
                $channel,
                (string) $request->input('q', ''),
                (string) $request->input('parent', '')
            );
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'parents' => []], 500);
        }

        return response()->json([
            'success' => true,
            'attach_supported' => \App\Support\Marketplace\ListingExistingParents::supports($channel),
            'parents' => $parents,
        ]);
    }

    public function publish(Request $request, ListingVariationPreviewService $preview)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(300);
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
        $categoryUuid = trim((string) $request->input('category_uuid', ''));
        $categoryName = trim((string) $request->input('category_name', ''));
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $categoryUuid)) {
            $categoryUuid = '';
        }
        $weightLb = $this->positiveFloatFromRequest($request, 'weight_lb', 'package_weight_lb');
        $weightKg = $this->positiveFloatFromRequest($request, 'weight_kg', 'package_weight_kg');
        $itemSpecifics = $this->itemSpecificsFromRequest($request);
        $overrides = $itemSpecifics !== [] ? ['item_specifics' => $itemSpecifics] : [];
        if (str_starts_with(str_replace(['-', '_', ' '], '', $channel), 'newegg')) {
            $overrides['follow_feed'] = true;
        }

        // Variation listing: add to an existing parent listing, or force a brand-new parent.
        $attach = strtolower(trim((string) $request->input('attach', '')));
        if ($mode === 'variation' && in_array($attach, ['existing', 'new'], true)) {
            if (! \App\Support\Marketplace\ListingExistingParents::supports($channel)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Adding to an existing parent listing is not supported on this marketplace yet.',
                ], 422);
            }
            $existingId = trim((string) $request->input('existing_listing_id', ''));
            if ($attach === 'existing' && ! preg_match('/^[A-Za-z0-9_\-]{3,64}$/', $existingId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Select the existing parent listing to add this variation to.',
                ], 422);
            }
            $overrides['attach_mode'] = $attach;
            $overrides['existing_listing_id'] = $attach === 'existing' ? $existingId : '';
        }

        try {
            $result = $preview->publishSkus(
                $skus,
                $channel,
                ! $request->boolean('confirmed'),
                $mode,
                $parentHint,
                $categoryId > 0 ? $categoryId : null,
                $categoryUuid !== '' ? $categoryUuid : null,
                $categoryName !== '' ? $categoryName : null,
                $weightLb,
                $weightKg,
                $overrides
            );
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Publish failed: '.$e->getMessage(),
            ], 500);
        }

        $this->rememberBackstage($result, $channel, $skus, $itemSpecifics);

        return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Keep the specifications that went out with this push for other marketplaces.
     *
     * @param  array<string, mixed>  $result
     * @param  list<string>  $requestedSkus
     * @param  array<string, string>  $itemSpecifics
     */
    private function rememberBackstage(array $result, string $channel, array $requestedSkus, array $itemSpecifics): void
    {
        $listed = [];
        foreach ((array) ($result['skus'] ?? []) as $sku) {
            $sku = trim((string) $sku);
            if ($sku !== '') {
                $listed[] = $sku;
            }
        }
        if ($listed === [] && ($result['success'] ?? false)) {
            $listed = $requestedSkus;
        }
        if ($listed === []) {
            return;
        }

        app(BackstageSpecificationStore::class)->recordProducts($listed, $channel, $itemSpecifics);
    }

    /**
     * eBay: required item specifics still missing before publishing (filled in the publish window).
     */
    public function ebayRequiredSpecifics(Request $request, EbayListingPublishService $ebay)
    {
        $skus = $this->skusFromRequest($request);
        $channel = strtolower(trim((string) $request->input('channel', '')));
        $categoryId = (int) preg_replace('/\D+/', '', (string) $request->input('category_id', ''));
        $categoryName = trim((string) $request->input('category_name', ''));

        try {
            return response()->json($ebay->requiredSpecifics(
                $skus,
                $channel,
                (string) $request->input('mode', 'variation'),
                trim((string) $request->input('parent', '')),
                $categoryId > 0 ? $categoryId : null,
                $categoryName !== '' ? $categoryName : null,
                $this->itemSpecificsFromRequest($request)
            ));
        } catch (\Throwable $e) {
            return response()->json(['success' => true, 'missing' => [], 'aspects_loaded' => false, 'message' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function itemSpecificsFromRequest(Request $request): array
    {
        $raw = $request->input('item_specifics', []);
        if (is_string($raw) && $raw !== '') {
            $raw = json_decode($raw, true);
        }
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $name => $value) {
            $name = trim((string) $name);
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($name !== '' && $value !== '') {
                $out[$name] = $value;
            }
        }

        return $out;
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

    private function positiveFloatFromRequest(Request $request, string ...$keys): ?float
    {
        foreach ($keys as $key) {
            $raw = $request->input($key);
            if ($raw === null || $raw === '') {
                continue;
            }
            if (is_string($raw)) {
                $raw = trim(str_replace(',', '', $raw));
            }
            if (! is_numeric($raw)) {
                continue;
            }
            $n = (float) $raw;
            if ($n > 0) {
                return $n;
            }
        }

        return null;
    }
}
