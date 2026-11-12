<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Support\Ads\MissingAdsCatalog;
use Illuminate\Http\JsonResponse;

/**
 * Shared base for the "<channel> Ads" Tabulator pages (Pinterest Ads, ChatGPT Ads).
 *
 * Every page lists the product master SKUs with the basic columns (image, parent, SKU, INV, OV L30, Dil, price ...).
 * Channel specific columns (campaigns, spend ...) are added in the child controller / blade when that data exists.
 */
abstract class AdsTabulatorController extends Controller
{
    /** Blade view of the page. */
    abstract protected function viewName(): string;

    /** Saved Columns-panel layout key (channel_tabulator_column_settings.channel_name). */
    abstract protected function channelKey(): string;

    abstract protected function pageTitle(): string;

    /** Route name of the JSON data endpoint. */
    abstract protected function dataRouteName(): string;

    public function index()
    {
        return view($this->viewName(), [
            'pageTitle' => $this->pageTitle(),
            'channelKey' => $this->channelKey(),
            'dataUrl' => route($this->dataRouteName()),
        ]);
    }

    public function data(): JsonResponse
    {
        $rows = $this->baseRows();

        return response()->json([
            'data' => $rows,
            'total' => count($rows),
        ]);
    }

    /**
     * One row per product master SKU (PARENT summary SKUs are left out).
     *
     * @return list<array<string, mixed>>
     */
    protected function baseRows(): array
    {
        $products = ProductMaster::query()
            ->select(['id', 'parent', 'sku', 'Values', 'main_image'])
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->where('sku', 'not like', 'PARENT %')
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        $skus = $products->pluck('sku')->map(fn ($sku) => (string) $sku)->unique()->values()->all();
        $shopifyByNorm = $skus === [] ? [] : ShopifySku::buildShopifySkuLookupByNormalizedSku($skus);

        $rows = [];
        foreach ($products as $pm) {
            $sku = (string) $pm->sku;
            $key = ShopifySku::normalizeSkuForShopifyLookup($sku);
            $shopify = $key !== '' ? ($shopifyByNorm[$key] ?? null) : null;
            $values = is_array($pm->Values) ? $pm->Values : [];

            $inv = $shopify ? (int) ($shopify->inv ?? 0) : 0;
            $ovL30 = $shopify ? (int) round((float) ($shopify->quantity ?? $shopify->shopify_l30 ?? 0)) : 0;
            $price = $shopify ? (float) ($shopify->price ?? $shopify->b2c_price ?? 0) : 0.0;

            $rows[] = [
                'id' => $pm->id,
                'parent' => trim((string) ($pm->parent ?? '')),
                'sku' => $sku,
                'image_path' => MissingAdsCatalog::imagePath($pm, $shopify),
                'inv' => $inv,
                'ov_l30' => $ovL30,
                'dil_percent' => $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0,
                'price' => $price > 0 ? round($price, 2) : null,
                'lp' => isset($values['lp']) && $values['lp'] !== '' ? round((float) $values['lp'], 2) : null,
                'ship' => isset($values['ship']) && $values['ship'] !== '' ? round((float) $values['ship'], 2) : null,
            ];
        }

        return $rows;
    }
}
