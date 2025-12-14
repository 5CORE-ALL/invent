<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function categoryList()
    {
        $categories = Category::paginate(20);

        foreach ($categories as $category) {
            $category->supplier_count = DB::table('suppliers')
                ->whereRaw("FIND_IN_SET(?, category_id)", [$category->id])
                ->count();
        }
        return view('purchase-master.category.category_list', compact('categories'));
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        return response()->json([
            'success' => true,
            'category' => $category
        ]);
    }

    public function postCategory(Request $request)
    {
        $data = $request->except('_token');

        $rule = [
            'category_name' => 'required'
        ];

        $validator = Validator::make($data, $rule);

        if ($validator->fails()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $inputs = $request->all();
        if (!empty($inputs['category_id'])) {
            $category_obj = Category::findOrFail($inputs['category_id']);
        } else {
            $category_obj = new Category;
        }

        $category_obj->name = trim($inputs['category_name']);
        $category_obj->status = !empty($inputs['status']) ? $inputs['status'] : 'inactive';

        // Generate code if not exists
        if (empty($category_obj->code)) {
            $category_obj->code = strtoupper(Str::slug($category_obj->name, '_'));
        }
        
        $category_obj->save();

        $message = !empty($inputs['category_id']) 
            ? 'Successfully updated category.' 
            : 'Successfully created category.';

        // Return JSON response for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'category' => $category_obj
            ]);
        }

        return redirect()->back()->with('flash_message', $message);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        return redirect()->back()->with('flash_message', 'Category deleted successfully.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids');
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['success' => false, 'message' => 'No categories selected.']);
        }

        Category::whereIn('id', $ids)->delete();

        return response()->json(['success' => true, 'message' => 'Categories deleted.']);
    }

    public function categoryMaster()
    {
        return view('category-master');
    }

    public function getCategoryMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            $shopifyImage = $shopifySkus[$normalizedSku]->image_src ?? null;
            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function idMaster()
    {
        return view('id-master');
    }

    public function getIdMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            $shopifyImage = $shopifySkus[$normalizedSku]->image_src ?? null;
            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function dimWtMaster()
    {
        return view('dim-wt-master');
    }

    public function getDimWtMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            $shopifyImage = $shopifySkus[$normalizedSku]->image_src ?? null;
            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function shippingMaster()
    {
        return view('shipping-master');
    }

    public function getShippingMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            $shopifyImage = $shopifySkus[$normalizedSku]->image_src ?? null;
            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function generalSpecificMaster()
    {
        return view('general-specific-master');
    }

    public function getGeneralSpecificMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            $shopifyImage = $shopifySkus[$normalizedSku]->image_src ?? null;
            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function updateGeneralSpecificMaster(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|integer',
                'sku' => 'required|string',
                'parent' => 'nullable|string',
                'brand' => 'nullable|string',
                'handling_time' => 'nullable|string',
                'country_of_origin' => 'nullable|string',
                'warranty' => 'nullable|string',
                'prop_warning' => 'nullable|string',
                'condition' => 'nullable|string',
            ]);

            // Find the product
            $product = ProductMaster::find($validated['product_id']);
            
            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.'
                ], 404);
            }

            // Get existing Values or create new array
            $values = is_array($product->Values) ? $product->Values : 
                     (is_string($product->Values) ? json_decode($product->Values, true) : []);
            
            if (!is_array($values)) {
                $values = [];
            }

            // Update the specific fields in Values
            if (isset($validated['brand'])) {
                $values['brand'] = $validated['brand'];
            }
            if (isset($validated['handling_time'])) {
                $values['handling_time'] = $validated['handling_time'];
            }
            if (isset($validated['country_of_origin'])) {
                $values['country_of_origin'] = $validated['country_of_origin'];
            }
            if (isset($validated['warranty'])) {
                $values['warranty'] = $validated['warranty'];
            }
            if (isset($validated['prop_warning'])) {
                $values['prop_warning'] = $validated['prop_warning'];
            }
            if (isset($validated['condition'])) {
                $values['condition'] = $validated['condition'];
                $values['Condition'] = $validated['condition']; // Also update capitalized version
            }

            // Save the updated Values
            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'General Specific Master updated successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating General Specific Master: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeGeneralSpecificMaster(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'brand' => 'nullable|string',
                'handling_time' => 'nullable|string',
                'country_of_origin' => 'nullable|string',
                'warranty' => 'nullable|string',
                'prop_warning' => 'nullable|string',
                'condition' => 'nullable|string',
            ]);

            // Get the product by SKU to retrieve parent
            $existingProduct = ProductMaster::where('sku', $validated['sku'])
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();

            $parent = null;
            if ($existingProduct) {
                $parent = $existingProduct->parent;
            }

            // Check if product with same SKU already has general specific data
            if ($existingProduct) {
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                // Check if any general specific fields already exist
                $hasGeneralSpecificData = !empty($existingValues['brand']) || 
                                         !empty($existingValues['handling_time']) || 
                                         !empty($existingValues['country_of_origin']) || 
                                         !empty($existingValues['warranty']) || 
                                         !empty($existingValues['prop_warning']) || 
                                         !empty($existingValues['condition']);
                
                if ($hasGeneralSpecificData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This SKU already has general specific data. Please use edit instead.'
                    ], 409);
                }
            }

            // Prepare Values array with general specific fields
            $values = [];
            
            if (!empty($validated['brand'])) {
                $values['brand'] = $validated['brand'];
            }
            if (!empty($validated['handling_time'])) {
                $values['handling_time'] = $validated['handling_time'];
            }
            if (!empty($validated['country_of_origin'])) {
                $values['country_of_origin'] = $validated['country_of_origin'];
            }
            if (!empty($validated['warranty'])) {
                $values['warranty'] = $validated['warranty'];
            }
            if (!empty($validated['prop_warning'])) {
                $values['prop_warning'] = $validated['prop_warning'];
            }
            if (!empty($validated['condition'])) {
                $values['condition'] = $validated['condition'];
                $values['Condition'] = $validated['condition']; // Also set capitalized version
            }

            // Update existing product or create new one
            if ($existingProduct) {
                // Merge with existing Values
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                if (!is_array($existingValues)) {
                    $existingValues = [];
                }
                
                $mergedValues = array_merge($existingValues, $values);
                $existingProduct->Values = $mergedValues;
                $existingProduct->save();
                $product = $existingProduct;
            } else {
                // Create new product (shouldn't happen if SKU dropdown is working correctly)
                $product = ProductMaster::create([
                    'sku' => $validated['sku'],
                    'parent' => $parent,
                    'Values' => !empty($values) ? $values : null,
                ]);
            }

            // If parent exists, also create/update the parent row
            if ($parent) {
                $parentSku = 'PARENT ' . $parent;
                $parentRow = ProductMaster::where('sku', $parentSku)
                    ->where('parent', $parent)
                    ->first();

                if (!$parentRow) {
                    ProductMaster::create([
                        'sku' => $parentSku,
                        'parent' => $parent,
                        'Values' => null,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'General Specific Data added successfully',
                'data' => $product
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error storing General Specific Master: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error saving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getSkusForDropdown(Request $request)
    {
        try {
            // Fetch distinct SKUs from ProductMaster, excluding PARENT rows
            $skus = ProductMaster::where('sku', 'NOT LIKE', 'PARENT %')
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->select('sku', 'parent')
                ->orderBy('sku', 'asc')
                ->get()
                ->unique('sku')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $skus
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching SKUs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching SKUs: ' . $e->getMessage()
            ], 500);
        }
    }

    public function complianceMaster()
    {
        return view('compliance-master');
    }

    public function getComplianceMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            $shopifyImage = $shopifySkus[$normalizedSku]->image_src ?? null;
            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function storeComplianceMaster(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'battery' => 'nullable|string',
                'wireless' => 'nullable|string',
                'electric' => 'nullable|string',
                'graph' => 'nullable|string',
            ]);

            // Get the product by SKU to retrieve parent
            $existingProduct = ProductMaster::where('sku', $validated['sku'])
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();

            $parent = null;
            if ($existingProduct) {
                $parent = $existingProduct->parent;
            }

            // Check if product with same SKU already has compliance data
            if ($existingProduct) {
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                // Check if any compliance fields already exist
                $hasComplianceData = !empty($existingValues['battery']) || 
                                    !empty($existingValues['wireless']) || 
                                    !empty($existingValues['electric']) || 
                                    !empty($existingValues['graph']);
                
                if ($hasComplianceData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This SKU already has compliance data. Please use edit instead.'
                    ], 409);
                }
            }

            // Prepare Values array with compliance fields
            $values = [];
            
            if (!empty($validated['battery'])) {
                $values['battery'] = $validated['battery'];
            }
            if (!empty($validated['wireless'])) {
                $values['wireless'] = $validated['wireless'];
            }
            if (!empty($validated['electric'])) {
                $values['electric'] = $validated['electric'];
            }
            if (!empty($validated['graph'])) {
                $values['graph'] = $validated['graph'];
            }

            // Update existing product or create new one
            if ($existingProduct) {
                // Merge with existing Values
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                if (!is_array($existingValues)) {
                    $existingValues = [];
                }
                
                $mergedValues = array_merge($existingValues, $values);
                $existingProduct->Values = $mergedValues;
                $existingProduct->save();
                $product = $existingProduct;
            } else {
                // Create new product (shouldn't happen if SKU dropdown is working correctly)
                $product = ProductMaster::create([
                    'sku' => $validated['sku'],
                    'parent' => $parent,
                    'Values' => !empty($values) ? $values : null,
                ]);
            }

            // If parent exists, also create/update the parent row
            if ($parent) {
                $parentSku = 'PARENT ' . $parent;
                $parentRow = ProductMaster::where('sku', $parentSku)
                    ->where('parent', $parent)
                    ->first();

                if (!$parentRow) {
                    ProductMaster::create([
                        'sku' => $parentSku,
                        'parent' => $parent,
                        'Values' => null,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Compliance Data added successfully',
                'data' => $product
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error storing Compliance Master: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error saving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function extraFeaturesMaster()
    {
        return view('extra-features-master');
    }

    public function getExtraFeaturesMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            $shopifyImage = $shopifySkus[$normalizedSku]->image_src ?? null;
            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function storeExtraFeaturesMaster(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'ex_feature_1' => 'nullable|string',
                'ex_feature_2' => 'nullable|string',
                'ex_feature_3' => 'nullable|string',
                'ex_feature_4' => 'nullable|string',
            ]);

            // Get the product by SKU to retrieve parent
            $existingProduct = ProductMaster::where('sku', $validated['sku'])
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();

            $parent = null;
            if ($existingProduct) {
                $parent = $existingProduct->parent;
            }

            // Check if product with same SKU already has extra features data
            if ($existingProduct) {
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                // Check if any extra features fields already exist
                $hasExtraFeaturesData = !empty($existingValues['ex_feature_1']) || 
                                       !empty($existingValues['ex_feature_2']) || 
                                       !empty($existingValues['ex_feature_3']) || 
                                       !empty($existingValues['ex_feature_4']);
                
                if ($hasExtraFeaturesData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This SKU already has extra features data. Please use edit instead.'
                    ], 409);
                }
            }

            // Prepare Values array with extra features fields
            $values = [];
            
            if (!empty($validated['ex_feature_1'])) {
                $values['ex_feature_1'] = $validated['ex_feature_1'];
            }
            if (!empty($validated['ex_feature_2'])) {
                $values['ex_feature_2'] = $validated['ex_feature_2'];
            }
            if (!empty($validated['ex_feature_3'])) {
                $values['ex_feature_3'] = $validated['ex_feature_3'];
            }
            if (!empty($validated['ex_feature_4'])) {
                $values['ex_feature_4'] = $validated['ex_feature_4'];
            }

            // Update existing product or create new one
            if ($existingProduct) {
                // Merge with existing Values
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                if (!is_array($existingValues)) {
                    $existingValues = [];
                }
                
                $mergedValues = array_merge($existingValues, $values);
                $existingProduct->Values = $mergedValues;
                $existingProduct->save();
                $product = $existingProduct;
            } else {
                // Create new product (shouldn't happen if SKU dropdown is working correctly)
                $product = ProductMaster::create([
                    'sku' => $validated['sku'],
                    'parent' => $parent,
                    'Values' => !empty($values) ? $values : null,
                ]);
            }

            // If parent exists, also create/update the parent row
            if ($parent) {
                $parentSku = 'PARENT ' . $parent;
                $parentRow = ProductMaster::where('sku', $parentSku)
                    ->where('parent', $parent)
                    ->first();

                if (!$parentRow) {
                    ProductMaster::create([
                        'sku' => $parentSku,
                        'parent' => $parent,
                        'Values' => null,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Extra Features Data added successfully',
                'data' => $product
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error storing Extra Features Master: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error saving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function aPlusImagesMaster()
    {
        return view('a-plus-images-master');
    }

    public function getAPlusImagesMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            $shopifyImage = $shopifySkus[$normalizedSku]->image_src ?? null;
            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function storeAPlusImagesMaster(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'standard_a_plus' => 'nullable|string',
                'premium_a_plus' => 'nullable|string',
            ]);

            // Get the product by SKU to retrieve parent
            $existingProduct = ProductMaster::where('sku', $validated['sku'])
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();

            $parent = null;
            if ($existingProduct) {
                $parent = $existingProduct->parent;
            }

            // Check if product with same SKU already has A+ images data
            if ($existingProduct) {
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                // Check if any A+ images fields already exist
                $hasAPlusImagesData = !empty($existingValues['standard_a_plus']) || 
                                     !empty($existingValues['premium_a_plus']);
                
                if ($hasAPlusImagesData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This SKU already has A+ images data. Please use edit instead.'
                    ], 409);
                }
            }

            // Prepare Values array with A+ images fields
            $values = [];
            
            if (!empty($validated['standard_a_plus'])) {
                $values['standard_a_plus'] = $validated['standard_a_plus'];
            }
            if (!empty($validated['premium_a_plus'])) {
                $values['premium_a_plus'] = $validated['premium_a_plus'];
            }

            // Update existing product or create new one
            if ($existingProduct) {
                // Merge with existing Values
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                if (!is_array($existingValues)) {
                    $existingValues = [];
                }
                
                $mergedValues = array_merge($existingValues, $values);
                $existingProduct->Values = $mergedValues;
                $existingProduct->save();
                $product = $existingProduct;
            } else {
                // Create new product (shouldn't happen if SKU dropdown is working correctly)
                $product = ProductMaster::create([
                    'sku' => $validated['sku'],
                    'parent' => $parent,
                    'Values' => !empty($values) ? $values : null,
                ]);
            }

            // If parent exists, also create/update the parent row
            if ($parent) {
                $parentSku = 'PARENT ' . $parent;
                $parentRow = ProductMaster::where('sku', $parentSku)
                    ->where('parent', $parent)
                    ->first();

                if (!$parentRow) {
                    ProductMaster::create([
                        'sku' => $parentSku,
                        'parent' => $parent,
                        'Values' => null,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'A+ Images Data added successfully',
                'data' => $product
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error storing A+ Images Master: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error saving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function keywordsMaster()
    {
        return view('keywords-master');
    }

    public function getKeywordsMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            $shopifyImage = $shopifySkus[$normalizedSku]->image_src ?? null;
            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function storeKeywordsMaster(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'backend_keywords' => 'nullable|string',
                'generic_keyword' => 'nullable|string',
            ]);

            // Get the product by SKU to retrieve parent
            $existingProduct = ProductMaster::where('sku', $validated['sku'])
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();

            $parent = null;
            if ($existingProduct) {
                $parent = $existingProduct->parent;
            }

            // Check if product with same SKU already has keywords data
            if ($existingProduct) {
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                // Check if any keywords fields already exist
                $hasKeywordsData = !empty($existingValues['backend_keywords']) || 
                                 !empty($existingValues['generic_keyword']);
                
                if ($hasKeywordsData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This SKU already has keywords data. Please use edit instead.'
                    ], 409);
                }
            }

            // Prepare Values array with keywords fields
            $values = [];
            
            if (!empty($validated['backend_keywords'])) {
                $values['backend_keywords'] = $validated['backend_keywords'];
            }
            if (!empty($validated['generic_keyword'])) {
                $values['generic_keyword'] = $validated['generic_keyword'];
            }

            // Update existing product or create new one
            if ($existingProduct) {
                // Merge with existing Values
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                if (!is_array($existingValues)) {
                    $existingValues = [];
                }
                
                $mergedValues = array_merge($existingValues, $values);
                $existingProduct->Values = $mergedValues;
                $existingProduct->save();
                $product = $existingProduct;
            } else {
                // Create new product (shouldn't happen if SKU dropdown is working correctly)
                $product = ProductMaster::create([
                    'sku' => $validated['sku'],
                    'parent' => $parent,
                    'Values' => !empty($values) ? $values : null,
                ]);
            }

            // If parent exists, also create/update the parent row
            if ($parent) {
                $parentSku = 'PARENT ' . $parent;
                $parentRow = ProductMaster::where('sku', $parentSku)
                    ->where('parent', $parent)
                    ->first();

                if (!$parentRow) {
                    ProductMaster::create([
                        'sku' => $parentSku,
                        'parent' => $parent,
                        'Values' => null,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Keywords Data added successfully',
                'data' => $product
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error storing Keywords Master: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error saving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function competitorsMaster()
    {
        return view('competitors-master');
    }

    public function getCompetitorsMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function storeCompetitorsMaster(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'brand_1' => 'nullable|string',
                'link_1' => 'nullable|string',
                'brand_2' => 'nullable|string',
                'link_2' => 'nullable|string',
                'brand_3' => 'nullable|string',
                'link_3' => 'nullable|string',
                'brand_4' => 'nullable|string',
                'link_4' => 'nullable|string',
                'brand_5' => 'nullable|string',
                'link_5' => 'nullable|string',
            ]);

            // Get the product by SKU to retrieve parent
            $existingProduct = ProductMaster::where('sku', $validated['sku'])
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();

            $parent = null;
            if ($existingProduct) {
                $parent = $existingProduct->parent;
            }

            // Check if product with same SKU already has competitors data
            if ($existingProduct) {
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                // Check if any competitors fields already exist
                $hasCompetitorsData = !empty($existingValues['brand_1']) || 
                                     !empty($existingValues['link_1']) ||
                                     !empty($existingValues['brand_2']) || 
                                     !empty($existingValues['link_2']) ||
                                     !empty($existingValues['brand_3']) || 
                                     !empty($existingValues['link_3']) ||
                                     !empty($existingValues['brand_4']) || 
                                     !empty($existingValues['link_4']) ||
                                     !empty($existingValues['brand_5']) || 
                                     !empty($existingValues['link_5']);
                
                if ($hasCompetitorsData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This SKU already has competitors data. Please use edit instead.'
                    ], 409);
                }
            }

            // Prepare Values array with competitors fields
            $values = [];
            
            if (!empty($validated['brand_1'])) {
                $values['brand_1'] = $validated['brand_1'];
            }
            if (!empty($validated['link_1'])) {
                $values['link_1'] = $validated['link_1'];
            }
            if (!empty($validated['brand_2'])) {
                $values['brand_2'] = $validated['brand_2'];
            }
            if (!empty($validated['link_2'])) {
                $values['link_2'] = $validated['link_2'];
            }
            if (!empty($validated['brand_3'])) {
                $values['brand_3'] = $validated['brand_3'];
            }
            if (!empty($validated['link_3'])) {
                $values['link_3'] = $validated['link_3'];
            }
            if (!empty($validated['brand_4'])) {
                $values['brand_4'] = $validated['brand_4'];
            }
            if (!empty($validated['link_4'])) {
                $values['link_4'] = $validated['link_4'];
            }
            if (!empty($validated['brand_5'])) {
                $values['brand_5'] = $validated['brand_5'];
            }
            if (!empty($validated['link_5'])) {
                $values['link_5'] = $validated['link_5'];
            }

            // Update existing product or create new one
            if ($existingProduct) {
                // Merge with existing Values
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                if (!is_array($existingValues)) {
                    $existingValues = [];
                }
                
                $mergedValues = array_merge($existingValues, $values);
                $existingProduct->Values = $mergedValues;
                $existingProduct->save();
                $product = $existingProduct;
            } else {
                // Create new product (shouldn't happen if SKU dropdown is working correctly)
                $product = ProductMaster::create([
                    'sku' => $validated['sku'],
                    'parent' => $parent,
                    'Values' => !empty($values) ? $values : null,
                ]);
            }

            // If parent exists, also create/update the parent row
            if ($parent) {
                $parentSku = 'PARENT ' . $parent;
                $parentRow = ProductMaster::where('sku', $parentSku)
                    ->where('parent', $parent)
                    ->first();

                if (!$parentRow) {
                    ProductMaster::create([
                        'sku' => $parentSku,
                        'parent' => $parent,
                        'Values' => null,
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Competitors Data added successfully',
                'data' => $product
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error storing Competitors Master: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error saving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function targetKeywordsMaster()
    {
        return view('target-keywords-master');
    }

    public function getTargetKeywordsMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function targetProductsMaster()
    {
        return view('target-products-master');
    }

    public function getTargetProductsMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function tagLinesMaster()
    {
        return view('tag-lines-master');
    }

    public function getTagLinesMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function groupMaster()
    {
        return view('group-master');
    }

    public function getGroupMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

    public function seoKeywordsMaster()
    {
        return view('seo-keywords-master');
    }

    public function getSeoKeywordsMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Prepare data in the same format as your sheet (flatten Values)
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
                'title150' => $product->title150,
                'title100' => $product->title100,
                'title80' => $product->title80,
                'title60' => $product->title60,
                'bullet1' => $product->bullet1,
                'bullet2' => $product->bullet2,
                'bullet3' => $product->bullet3,
                'bullet4' => $product->bullet4,
                'bullet5' => $product->bullet5,
                'product_description' => $product->product_description,
                'feature1' => $product->feature1,
                'feature2' => $product->feature2,
                'feature3' => $product->feature3,
                'feature4' => $product->feature4,
                'main_image' => $product->main_image,
                'main_image_brand' => $product->main_image_brand,
                'image1' => $product->image1,
                'image2' => $product->image2,
                'image3' => $product->image3,
                'image4' => $product->image4,
                'image5' => $product->image5,
                'image6' => $product->image6,
                'image7' => $product->image7,
                'image8' => $product->image8,
                'image9' => $product->image9,
                'image10' => $product->image10,
                'image11' => $product->image11,
                'image12' => $product->image12,
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
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }

            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/' . ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200
        ]);
    }

}
