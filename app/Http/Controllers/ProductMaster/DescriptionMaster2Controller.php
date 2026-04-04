<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ProductMaster\Concerns\RetriesMarketplacePush;
use App\Models\ProductMaster;
use App\Services\EbayApiService;
use App\Services\ShopifyApiService;
use App\Services\Support\ProductDescriptionV2HtmlBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DescriptionMaster2Controller extends Controller
{
    use RetriesMarketplacePush;

    public function index(Request $request)
    {
        return view('product-description-2', [
            'mode' => $request->query('mode', ''),
            'demo' => $request->query('demo', ''),
        ]);
    }

    /**
     * GET /product-description-2/data?sku=
     */
    public function getData(Request $request)
    {
        $sku = $this->normalizeSku((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required.'], 422);
        }

        $pm = ProductMaster::query()->where('sku', $sku)->first();
        if (! $pm) {
            return response()->json(['success' => false, 'message' => 'Product not found for this SKU.'], 404);
        }

        $images = $pm->description_v2_images;
        if (! is_array($images) || $images === []) {
            $images = $this->defaultImagesFromProductMaster($pm);
        }

        return response()->json([
            'success' => true,
            'sku' => $pm->sku,
            'product_name' => (string) ($pm->title150 ?? ''),
            'description_v2_bullets' => (string) ($pm->description_v2_bullets ?? ''),
            'description_v2_description' => (string) ($pm->description_v2_description ?? ''),
            'description_v2_images' => array_values(array_pad(array_slice($images, 0, 12), 12, '')),
            'description_v2_features' => $this->normalizeFeatures($pm->description_v2_features),
            'description_v2_specifications' => $this->normalizeSpecs($pm->description_v2_specifications),
            'description_v2_package' => (string) ($pm->description_v2_package ?? ''),
            'description_v2_brand' => (string) ($pm->description_v2_brand ?? ''),
        ]);
    }

    /**
     * POST /product-description-2/save — persist structured fields to product_master.
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'description_v2_bullets' => 'nullable|string',
            'description_v2_description' => 'nullable|string',
            'description_v2_images' => 'nullable|array',
            'description_v2_images.*' => 'nullable|string|max:2048',
            'description_v2_features' => 'nullable|array',
            'description_v2_features.*.title' => 'nullable|string|max:500',
            'description_v2_features.*.body' => 'nullable|string|max:5000',
            'description_v2_specifications' => 'nullable|array',
            'description_v2_specifications.*.key' => 'nullable|string|max:255',
            'description_v2_specifications.*.value' => 'nullable|string|max:2000',
            'description_v2_package' => 'nullable|string',
            'description_v2_brand' => 'nullable|string',
        ]);

        $sku = $this->normalizeSku($validated['sku']);
        $pm = ProductMaster::query()->where('sku', $sku)->first();
        if (! $pm) {
            return response()->json(['success' => false, 'message' => 'Product not found for this SKU.'], 404);
        }

        if (! $this->productMasterHasV2Columns()) {
            return response()->json(['success' => false, 'message' => 'Description 2.0 columns missing. Run migrations.'], 503);
        }

        $images = array_slice(array_values(array_filter(
            array_map('trim', $validated['description_v2_images'] ?? []),
            fn ($u) => $u !== ''
        )), 0, 12);

        $pm->description_v2_bullets = (string) ($validated['description_v2_bullets'] ?? '');
        $pm->description_v2_description = (string) ($validated['description_v2_description'] ?? '');
        $pm->description_v2_images = $images === [] ? null : $images;
        $pm->description_v2_features = $this->normalizeFeatures($validated['description_v2_features'] ?? null);
        $pm->description_v2_specifications = $this->normalizeSpecs($validated['description_v2_specifications'] ?? null);
        $pm->description_v2_package = (string) ($validated['description_v2_package'] ?? '');
        $pm->description_v2_brand = (string) ($validated['description_v2_brand'] ?? '');
        $pm->save();

        Log::info('DescriptionMaster2: saved structured description', ['sku' => $sku]);

        return response()->json(['success' => true, 'message' => 'Saved.']);
    }

    /**
     * POST /product-description-2/push — build HTML and push to Shopify Main and/or eBay (eBay1).
     */
    public function push(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'push_shopify_main' => 'nullable|boolean',
            'push_ebay' => 'nullable|boolean',
            'description_v2_bullets' => 'nullable|string',
            'description_v2_description' => 'nullable|string',
            'description_v2_images' => 'nullable|array',
            'description_v2_images.*' => 'nullable|string|max:2048',
            'description_v2_features' => 'nullable|array',
            'description_v2_specifications' => 'nullable|array',
            'description_v2_package' => 'nullable|string',
            'description_v2_brand' => 'nullable|string',
            'spec_table_heading' => 'nullable|string|max:120',
        ]);

        $sku = $this->normalizeSku($validated['sku']);
        $pushShopify = (bool) ($validated['push_shopify_main'] ?? false);
        $pushEbay = (bool) ($validated['push_ebay'] ?? false);

        if (! $pushShopify && ! $pushEbay) {
            return response()->json(['success' => false, 'message' => 'Select at least one marketplace.'], 422);
        }

        $pm = ProductMaster::query()->where('sku', $sku)->first();
        if (! $pm) {
            return response()->json(['success' => false, 'message' => 'Product not found for this SKU.'], 404);
        }

        if (! $this->productMasterHasV2Columns()) {
            return response()->json(['success' => false, 'message' => 'Description 2.0 columns missing. Run migrations.'], 503);
        }

        $bulletsText = (string) ($validated['description_v2_bullets'] ?? $pm->description_v2_bullets ?? '');
        $lines = preg_split('/\r\n|\r|\n/', $bulletsText) ?: [];
        $bullets = array_slice(array_values(array_filter(array_map('trim', $lines), fn ($b) => $b !== '')), 0, 5);

        $images = $validated['description_v2_images'] ?? $pm->description_v2_images;
        if (! is_array($images)) {
            $images = [];
        }
        $images = array_slice(array_values(array_filter(array_map('trim', $images), fn ($u) => $u !== '')), 0, 12);

        $features = $this->normalizeFeatures($validated['description_v2_features'] ?? $pm->description_v2_features);
        $specs = $this->normalizeSpecs($validated['description_v2_specifications'] ?? $pm->description_v2_specifications);

        $productDescription = (string) ($validated['description_v2_description'] ?? $pm->description_v2_description ?? '');
        $package = (string) ($validated['description_v2_package'] ?? $pm->description_v2_package ?? '');
        $brand = (string) ($validated['description_v2_brand'] ?? $pm->description_v2_brand ?? '');
        $specHeading = trim((string) ($validated['spec_table_heading'] ?? ''));
        if ($specHeading === '') {
            $specHeading = 'Specification';
        }

        $productLabel = trim((string) ($pm->title150 ?? ''));
        if ($specHeading === '' || $specHeading === 'Specification') {
            $specHeading = $productLabel !== '' ? $productLabel.' Specification' : 'Specification';
        }

        $built = ProductDescriptionV2HtmlBuilder::build(
            $bullets,
            $images,
            $productDescription,
            $features,
            $specs,
            $package,
            $brand,
            $specHeading
        );
        $html = $built['html'];

        if ($html === '') {
            return response()->json(['success' => false, 'message' => 'Nothing to push — add at least one section (bullets, description, images, features, specs, package, or brand).'], 422);
        }

        $results = [];
        $anyOk = false;

        if ($pushShopify) {
            Log::info('DescriptionMaster2: pushing Shopify Main', ['sku' => $sku, 'html_len' => strlen($html)]);
            $shopify = $this->invokeMarketplacePushWithCustomBackoff(
                fn () => app(ShopifyApiService::class)->updateBodyHtml($sku, $html),
                'DescriptionMaster2:shopify_main',
                'shopify_main',
                $sku,
                [2, 4, 8]
            );
            $results['shopify_main'] = $shopify;
            if ($shopify['success'] ?? false) {
                $anyOk = true;
            }
        }

        if ($pushEbay) {
            Log::info('DescriptionMaster2: pushing eBay', ['sku' => $sku, 'html_len' => strlen($html)]);
            $ebay = $this->invokeMarketplacePushWithCustomBackoff(
                fn () => app(EbayApiService::class)->updateListingDescriptionRawHtml($sku, $html),
                'DescriptionMaster2:ebay',
                'ebay',
                $sku,
                [2, 4, 8]
            );
            $results['ebay'] = $ebay;
            if ($ebay['success'] ?? false) {
                $anyOk = true;
            }
        }

        $pm->description_v2_bullets = $bulletsText;
        $pm->description_v2_description = $productDescription;
        $pm->description_v2_images = $images === [] ? null : $images;
        $pm->description_v2_features = $features;
        $pm->description_v2_specifications = $specs;
        $pm->description_v2_package = $package;
        $pm->description_v2_brand = $brand;
        $pm->save();

        Log::info('DescriptionMaster2: push completed', [
            'sku' => $sku,
            'any_success' => $anyOk,
            'results' => array_map(fn ($r) => ['success' => $r['success'] ?? false, 'message' => $r['message'] ?? ''], $results),
        ]);

        return response()->json([
            'success' => $anyOk,
            'message' => $anyOk ? 'Push completed (see per-marketplace status).' : 'All selected pushes failed.',
            'html_length' => strlen($html),
            'results' => $results,
        ]);
    }

    private function productMasterHasV2Columns(): bool
    {
        if (! Schema::hasTable('product_master')) {
            return false;
        }

        return Schema::hasColumn('product_master', 'description_v2_bullets');
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    private function normalizeFeatures(mixed $raw): array
    {
        $out = [];
        if (is_array($raw)) {
            foreach (array_slice($raw, 0, 4) as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $out[] = [
                    'title' => trim((string) ($row['title'] ?? '')),
                    'body' => trim((string) ($row['body'] ?? '')),
                ];
            }
        }
        while (count($out) < 4) {
            $out[] = ['title' => '', 'body' => ''];
        }

        return array_slice($out, 0, 4);
    }

    /**
     * @return list<array{key: string, value: string}>
     */
    private function normalizeSpecs(mixed $raw): array
    {
        $out = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $k = trim((string) ($row['key'] ?? ''));
                $v = trim((string) ($row['value'] ?? ''));
                if ($k === '' && $v === '') {
                    continue;
                }
                $out[] = ['key' => $k, 'value' => $v];
            }
        }

        return $out;
    }

    private function defaultImagesFromProductMaster(ProductMaster $pm): array
    {
        $urls = [];
        foreach (['main_image', 'image1', 'image2', 'image3', 'image4', 'image5', 'image6', 'image7', 'image8', 'image9', 'image10', 'image11', 'image12'] as $col) {
            $u = trim((string) ($pm->{$col} ?? ''));
            if ($u !== '') {
                $urls[] = $u;
            }
        }

        return array_slice($urls, 0, 12);
    }

    private function normalizeSku(?string $sku): string
    {
        if (! $sku) {
            return '';
        }

        return str_replace("\u{00a0}", ' ', trim((string) $sku));
    }
}
