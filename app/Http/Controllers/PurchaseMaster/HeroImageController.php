<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HeroImageController extends Controller
{
    public function heroImagesMaster()
    {
        return view('hero-images-master');
    }

    public function getHeroImagesMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function ($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Fetch amazon data view with buyer and seller links
        $amazonDataViews = \App\Models\AmazonDataView::all()->keyBy(function ($item) {
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Fetch junglescout data for LQS (Listing Quality Score)
        $junglescoutData = \DB::table('junglescout_product_data')
            ->get()
            ->keyBy(function ($item) {
                return str_replace("\u{00a0}", ' ', $item->sku);
            });

        // Prepare data in the same format as A+ Images Master
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
            ];

            // Merge the Values array (if not null)
            if (is_array($product->Values)) {
                $row = array_merge($row, $product->Values);
            } elseif (is_string($product->Values)) {
                $values = json_decode($product->Values, true);
                if (is_array($values)) {
                    $row = array_merge($row, $values);
                }
            }

            // Add Shopify inv and quantity if available
            // Normalize the product SKU for lookup
            $normalizedSku = str_replace("\u{00a0}", ' ', $product->sku);

            if (isset($shopifySkus[$normalizedSku])) {
                $shopifyData = $shopifySkus[$normalizedSku];
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float) $shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float) $shopifyData->quantity : 0;

                // Ovl30 is shopify_quantity (same as product-master page)
                $row['ovl30'] = $row['shopify_quantity'];

                // Calculate Dil (Days in Inventory) = (shopify_quantity / shopify_inv) * 100
                $inv = $row['shopify_inv'];
                $ovl30 = $row['shopify_quantity'];
                $dil = ($inv > 0) ? ($ovl30 / $inv) * 100 : 0;
                $row['dil'] = round($dil, 2);

                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $row['ovl30'] = 0;
                $row['dil'] = 0;
                $shopifyImage = null;
            }

            $shopifyImage = $shopifySkus[$normalizedSku]->image_src ?? null;
            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/'.ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            // Add Amazon buyer and seller links from amazon_data_view
            if (isset($amazonDataViews[$normalizedSku])) {
                $amazonData = $amazonDataViews[$normalizedSku];
                $amazonValue = is_array($amazonData->value) ? $amazonData->value : json_decode($amazonData->value, true);
                $row['buyer_link'] = $amazonValue['buyer_link'] ?? null;
                $row['seller_link'] = $amazonValue['seller_link'] ?? null;
            } else {
                $row['buyer_link'] = null;
                $row['seller_link'] = null;
            }

            // Add Junglescout LQS data
            if (isset($junglescoutData[$normalizedSku])) {
                $jsData = $junglescoutData[$normalizedSku];
                $jsDataValue = is_array($jsData->data) ? $jsData->data : json_decode($jsData->data, true);
                $row['listing_quality_score'] = $jsDataValue['listing_quality_score'] ?? null;
                $row['lqs'] = $jsDataValue['listing_quality_score'] ?? null;
            } else {
                $row['listing_quality_score'] = null;
                $row['lqs'] = null;
            }

            // Surface DB link and hero image from Values JSON (shared with A+ Images Master).
            $row['db'] = $row['db'] ?? ($row['DB'] ?? null);
            $row['hero_image'] = $row['hero_image'] ?? null;

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200,
        ]);
    }

    /**
     * Update the DB link stored in product_masters.Values for a given SKU.
     * The DB column is shared with the A+ Images Master view.
     */
    public function updateDBLink(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'db' => 'nullable|string|url',
            ]);

            $product = $this->findProductBySku($validated['sku']);

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $values = $this->getValuesArray($product);
            $values['db'] = $validated['db'];
            $values['DB'] = $validated['db'];

            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'DB link updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Hero Update DB Link Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update DB link: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload (or replace) the hero image for a SKU. Stores the file under
     * storage/app/public/hero_images and saves the relative path in
     * product_masters.Values.hero_image.
     */
    public function uploadHeroImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'image_file' => 'required|file|mimes:jpeg,jpg,png,gif,bmp,webp,svg|max:10240',
            ]);

            $product = $this->findProductBySku($validated['sku']);

            if (! $product) {
                Log::error('Hero upload — product not found for SKU: '.$validated['sku']);

                return response()->json([
                    'success' => false,
                    'message' => 'Product not found for SKU: '.$validated['sku'],
                ], 404);
            }

            $values = $this->getValuesArray($product);

            $imageFile = $request->file('image_file');
            $safeSku = preg_replace('/[^A-Za-z0-9_\-]/', '_', $validated['sku']);
            $imageName = 'hero_'.$safeSku.'_'.time().'.'.$imageFile->getClientOriginalExtension();

            $directory = 'hero_images';
            if (! Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $imagePath = $imageFile->storeAs($directory, $imageName, 'public');

            $values['hero_image'] = $imagePath;
            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Hero image uploaded successfully',
                'image_path' => $imagePath,
            ]);
        } catch (\Exception $e) {
            Log::error('Hero Image Upload Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload hero image: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find a ProductMaster row by SKU, tolerating non-breaking spaces that can
     * sneak in from spreadsheet imports.
     */
    private function findProductBySku(string $sku): ?ProductMaster
    {
        $normalizedSku = str_replace("\u{00a0}", ' ', $sku);

        $product = ProductMaster::where('sku', $normalizedSku)->first();

        if (! $product) {
            $product = ProductMaster::where('sku', $sku)->first();
        }

        return $product;
    }

    /**
     * Decode the Values JSON column into an array regardless of how Eloquent
     * cast it.
     */
    private function getValuesArray(ProductMaster $product): array
    {
        $values = is_array($product->Values)
            ? $product->Values
            : json_decode((string) $product->Values, true);

        return is_array($values) ? $values : [];
    }
}
