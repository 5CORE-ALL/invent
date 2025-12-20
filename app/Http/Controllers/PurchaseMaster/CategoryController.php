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
use PhpOffice\PhpSpreadsheet\IOFactory;

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

    public function getSkusForDimWtDropdown(Request $request)
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
            Log::error('Error fetching SKUs for Dim Wt: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching SKUs: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeDimWtMaster(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'wt_act' => 'nullable|numeric',
                'wt_decl' => 'nullable|numeric',
                'l' => 'nullable|numeric',
                'w' => 'nullable|numeric',
                'h' => 'nullable|numeric',
                'ctn_l' => 'nullable|numeric',
                'ctn_w' => 'nullable|numeric',
                'ctn_h' => 'nullable|numeric',
                'ctn_cbm' => 'nullable|numeric',
                'ctn_qty' => 'nullable|numeric',
                'ctn_cbm_each' => 'nullable|numeric',
                'cbm_e' => 'nullable|numeric',
                'ctn_gwt' => 'nullable|numeric',
            ]);

            // Get the product by SKU to retrieve parent
            $existingProduct = ProductMaster::where('sku', $validated['sku'])
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();

            $parent = null;
            if ($existingProduct) {
                $parent = $existingProduct->parent;
            }

            // Prepare Values array with dim-wt fields
            $values = [];
            
            if (isset($validated['wt_act']) && $validated['wt_act'] !== null) {
                $values['wt_act'] = $validated['wt_act'];
            }
            if (isset($validated['wt_decl']) && $validated['wt_decl'] !== null) {
                $values['wt_decl'] = $validated['wt_decl'];
            }
            if (isset($validated['l']) && $validated['l'] !== null) {
                $values['l'] = $validated['l'];
            }
            if (isset($validated['w']) && $validated['w'] !== null) {
                $values['w'] = $validated['w'];
            }
            if (isset($validated['h']) && $validated['h'] !== null) {
                $values['h'] = $validated['h'];
            }
            if (isset($validated['ctn_l']) && $validated['ctn_l'] !== null) {
                $values['ctn_l'] = $validated['ctn_l'];
            }
            if (isset($validated['ctn_w']) && $validated['ctn_w'] !== null) {
                $values['ctn_w'] = $validated['ctn_w'];
            }
            if (isset($validated['ctn_h']) && $validated['ctn_h'] !== null) {
                $values['ctn_h'] = $validated['ctn_h'];
            }
            if (isset($validated['ctn_cbm']) && $validated['ctn_cbm'] !== null) {
                $values['ctn_cbm'] = $validated['ctn_cbm'];
            }
            if (isset($validated['ctn_qty']) && $validated['ctn_qty'] !== null) {
                $values['ctn_qty'] = $validated['ctn_qty'];
            }
            if (isset($validated['ctn_cbm_each']) && $validated['ctn_cbm_each'] !== null) {
                $values['ctn_cbm_each'] = $validated['ctn_cbm_each'];
            }
            if (isset($validated['cbm_e']) && $validated['cbm_e'] !== null) {
                $values['cbm_e'] = $validated['cbm_e'];
            }
            if (isset($validated['ctn_gwt']) && $validated['ctn_gwt'] !== null) {
                $values['ctn_gwt'] = $validated['ctn_gwt'];
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

            return response()->json([
                'success' => true,
                'message' => 'Dim & Wt Master stored successfully',
                'data' => $product
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error storing Dim & Wt Master: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error saving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateDimWtMaster(Request $request)
    {
        try {
            $validated = $request->validate([
                'product_id' => 'required|integer',
                'sku' => 'required|string',
                'parent' => 'nullable|string',
                'wt_act' => 'nullable|numeric',
                'wt_decl' => 'nullable|numeric',
                'l' => 'nullable|numeric',
                'w' => 'nullable|numeric',
                'h' => 'nullable|numeric',
                'ctn_l' => 'nullable|numeric',
                'ctn_w' => 'nullable|numeric',
                'ctn_h' => 'nullable|numeric',
                'ctn_cbm' => 'nullable|numeric',
                'ctn_qty' => 'nullable|numeric',
                'ctn_cbm_each' => 'nullable|numeric',
                'cbm_e' => 'nullable|numeric',
                'ctn_gwt' => 'nullable|numeric',
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
            if (isset($validated['wt_act'])) {
                $values['wt_act'] = $validated['wt_act'];
            }
            if (isset($validated['wt_decl'])) {
                $values['wt_decl'] = $validated['wt_decl'];
            }
            if (isset($validated['l'])) {
                $values['l'] = $validated['l'];
            }
            if (isset($validated['w'])) {
                $values['w'] = $validated['w'];
            }
            if (isset($validated['h'])) {
                $values['h'] = $validated['h'];
            }
            if (isset($validated['ctn_l'])) {
                $values['ctn_l'] = $validated['ctn_l'];
            }
            if (isset($validated['ctn_w'])) {
                $values['ctn_w'] = $validated['ctn_w'];
            }
            if (isset($validated['ctn_h'])) {
                $values['ctn_h'] = $validated['ctn_h'];
            }
            if (isset($validated['ctn_cbm'])) {
                $values['ctn_cbm'] = $validated['ctn_cbm'];
            }
            if (isset($validated['ctn_qty'])) {
                $values['ctn_qty'] = $validated['ctn_qty'];
            }
            if (isset($validated['ctn_cbm_each'])) {
                $values['ctn_cbm_each'] = $validated['ctn_cbm_each'];
            }
            if (isset($validated['cbm_e'])) {
                $values['cbm_e'] = $validated['cbm_e'];
            }
            if (isset($validated['ctn_gwt'])) {
                $values['ctn_gwt'] = $validated['ctn_gwt'];
            }

            // Save the updated Values
            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Dim & Wt Master updated successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating Dim & Wt Master: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating data: ' . $e->getMessage()
            ], 500);
        }
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

    public function importShippingMaster(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file = $request->file('excel_file');
            $extension = strtolower($file->getClientOriginalExtension());
            
            // Load spreadsheet - PhpSpreadsheet handles both Excel and CSV
            $spreadsheet = IOFactory::load($file->getPathName());
            
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows'
                ], 422);
            }

            // Clean headers - normalize to lowercase and replace spaces/special chars
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            // Remove header row
            unset($rows[0]);

            // Expected column mappings
            $columnMap = [
                'sku' => 'sku',
                'ship' => 'ship',
                'temu_ship' => 'temu_ship',
                'ebay2_ship' => 'ebay2_ship'
            ];

            // Find column indices
            $columnIndices = [];
            foreach ($columnMap as $key => $value) {
                $index = array_search($key, $headers);
                if ($index !== false) {
                    $columnIndices[$value] = $index;
                }
            }

            if (!isset($columnIndices['sku'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU column not found in the Excel file'
                ], 422);
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed
                
                // Skip empty rows
                if (empty($row[$columnIndices['sku']])) {
                    continue;
                }

                $sku = trim($row[$columnIndices['sku']]);
                
                // Find product by SKU
                $product = ProductMaster::where('sku', $sku)->first();
                
                if (!$product) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                // Get existing Values
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                if (!is_array($values)) {
                    $values = [];
                }

                $hasChanges = false;

                // Update SHIP if column exists and has value
                if (isset($columnIndices['ship']) && isset($row[$columnIndices['ship']])) {
                    $shipValue = trim($row[$columnIndices['ship']]);
                    if ($shipValue !== '') {
                        $values['ship'] = $shipValue;
                        $hasChanges = true;
                    }
                }

                // Update TEMU SHIP if column exists and has value
                if (isset($columnIndices['temu_ship']) && isset($row[$columnIndices['temu_ship']])) {
                    $temuShipValue = trim($row[$columnIndices['temu_ship']]);
                    if ($temuShipValue !== '') {
                        $values['temu_ship'] = $temuShipValue;
                        $hasChanges = true;
                    }
                }

                // Update EBAY2 SHIP if column exists and has value
                if (isset($columnIndices['ebay2_ship']) && isset($row[$columnIndices['ebay2_ship']])) {
                    $ebay2ShipValue = trim($row[$columnIndices['ebay2_ship']]);
                    if ($ebay2ShipValue !== '') {
                        $values['ebay2_ship'] = $ebay2ShipValue;
                        $hasChanges = true;
                    }
                }

                // Update product if there are changes
                if ($hasChanges) {
                    $product->Values = $values;
                    $product->save();
                    $updated++;
                    $imported++;
                }
            }

            $message = "Successfully imported {$imported} record(s). Updated {$updated} record(s).";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Shipping Master Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
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

    public function importGeneralSpecificMaster(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file = $request->file('excel_file');
            
            // Load spreadsheet - PhpSpreadsheet handles both Excel and CSV
            $spreadsheet = IOFactory::load($file->getPathName());
            
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows'
                ], 422);
            }

            // Clean headers - normalize to lowercase and replace spaces/special chars
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            // Remove header row
            unset($rows[0]);

            // Expected column mappings
            $columnMap = [
                'sku' => 'sku',
                'brand' => 'brand',
                'handling_time' => 'handling_time',
                'country_of_origin' => 'country_of_origin',
                'warranty' => 'warranty',
                'prop_warning' => 'prop_warning',
                'condition' => 'condition'
            ];

            // Find column indices
            $columnIndices = [];
            foreach ($columnMap as $key => $value) {
                $index = array_search($key, $headers);
                if ($index !== false) {
                    $columnIndices[$value] = $index;
                }
            }

            if (!isset($columnIndices['sku'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU column not found in the Excel file'
                ], 422);
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed
                
                // Skip empty rows
                if (empty($row[$columnIndices['sku']])) {
                    continue;
                }

                $sku = trim($row[$columnIndices['sku']]);
                
                // Find product by SKU
                $product = ProductMaster::where('sku', $sku)
                    ->where('sku', 'NOT LIKE', 'PARENT %')
                    ->first();
                
                if (!$product) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                // Get existing Values
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                if (!is_array($values)) {
                    $values = [];
                }

                $hasChanges = false;

                // Update Brand if column exists and has value
                if (isset($columnIndices['brand']) && isset($row[$columnIndices['brand']])) {
                    $brandValue = trim($row[$columnIndices['brand']]);
                    if ($brandValue !== '') {
                        $values['brand'] = $brandValue;
                        $hasChanges = true;
                    }
                }

                // Update Handling Time if column exists and has value
                if (isset($columnIndices['handling_time']) && isset($row[$columnIndices['handling_time']])) {
                    $handlingTimeValue = trim($row[$columnIndices['handling_time']]);
                    if ($handlingTimeValue !== '') {
                        $values['handling_time'] = $handlingTimeValue;
                        $hasChanges = true;
                    }
                }

                // Update Country of Origin if column exists and has value
                if (isset($columnIndices['country_of_origin']) && isset($row[$columnIndices['country_of_origin']])) {
                    $countryOfOriginValue = trim($row[$columnIndices['country_of_origin']]);
                    if ($countryOfOriginValue !== '') {
                        $values['country_of_origin'] = $countryOfOriginValue;
                        $hasChanges = true;
                    }
                }

                // Update Warranty if column exists and has value
                if (isset($columnIndices['warranty']) && isset($row[$columnIndices['warranty']])) {
                    $warrantyValue = trim($row[$columnIndices['warranty']]);
                    if ($warrantyValue !== '') {
                        $values['warranty'] = $warrantyValue;
                        $hasChanges = true;
                    }
                }

                // Update Prop Warning if column exists and has value
                if (isset($columnIndices['prop_warning']) && isset($row[$columnIndices['prop_warning']])) {
                    $propWarningValue = trim($row[$columnIndices['prop_warning']]);
                    if ($propWarningValue !== '') {
                        $values['prop_warning'] = $propWarningValue;
                        $hasChanges = true;
                    }
                }

                // Update Condition if column exists and has value
                if (isset($columnIndices['condition']) && isset($row[$columnIndices['condition']])) {
                    $conditionValue = trim($row[$columnIndices['condition']]);
                    if ($conditionValue !== '') {
                        $values['condition'] = $conditionValue;
                        $values['Condition'] = $conditionValue; // Also update capitalized version
                        $hasChanges = true;
                    }
                }

                // Update product if there are changes
                if ($hasChanges) {
                    $product->Values = $values;
                    $product->save();
                    $updated++;
                    $imported++;
                }
            }

            $message = "Successfully imported {$imported} record(s). Updated {$updated} record(s).";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('General Specific Master Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
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

    public function importComplianceMaster(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file = $request->file('excel_file');
            
            // Load spreadsheet - PhpSpreadsheet handles both Excel and CSV
            $spreadsheet = IOFactory::load($file->getPathName());
            
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows'
                ], 422);
            }

            // Clean headers - normalize to lowercase and replace spaces/special chars
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            // Remove header row
            unset($rows[0]);

            // Expected column mappings
            $columnMap = [
                'sku' => 'sku',
                'battery' => 'battery',
                'wireless' => 'wireless',
                'electric' => 'electric',
                'graph' => 'graph'
            ];

            // Find column indices
            $columnIndices = [];
            foreach ($columnMap as $key => $value) {
                $index = array_search($key, $headers);
                if ($index !== false) {
                    $columnIndices[$value] = $index;
                }
            }

            if (!isset($columnIndices['sku'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU column not found in the Excel file'
                ], 422);
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed
                
                // Skip empty rows
                if (empty($row[$columnIndices['sku']])) {
                    continue;
                }

                $sku = trim($row[$columnIndices['sku']]);
                
                // Find product by SKU
                $product = ProductMaster::where('sku', $sku)
                    ->where('sku', 'NOT LIKE', 'PARENT %')
                    ->first();
                
                if (!$product) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                // Get existing Values
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                if (!is_array($values)) {
                    $values = [];
                }

                $hasChanges = false;

                // Update Battery if column exists and has value
                if (isset($columnIndices['battery']) && isset($row[$columnIndices['battery']])) {
                    $batteryValue = trim($row[$columnIndices['battery']]);
                    if ($batteryValue !== '') {
                        $values['battery'] = $batteryValue;
                        $hasChanges = true;
                    }
                }

                // Update Wireless if column exists and has value
                if (isset($columnIndices['wireless']) && isset($row[$columnIndices['wireless']])) {
                    $wirelessValue = trim($row[$columnIndices['wireless']]);
                    if ($wirelessValue !== '') {
                        $values['wireless'] = $wirelessValue;
                        $hasChanges = true;
                    }
                }

                // Update Electric if column exists and has value
                if (isset($columnIndices['electric']) && isset($row[$columnIndices['electric']])) {
                    $electricValue = trim($row[$columnIndices['electric']]);
                    if ($electricValue !== '') {
                        $values['electric'] = $electricValue;
                        $hasChanges = true;
                    }
                }

                // Update Graph if column exists and has value
                if (isset($columnIndices['graph']) && isset($row[$columnIndices['graph']])) {
                    $graphValue = trim($row[$columnIndices['graph']]);
                    if ($graphValue !== '') {
                        $values['graph'] = $graphValue;
                        $hasChanges = true;
                    }
                }

                // Update product if there are changes
                if ($hasChanges) {
                    $product->Values = $values;
                    $product->save();
                    $updated++;
                    $imported++;
                }
            }

            $message = "Successfully imported {$imported} record(s). Updated {$updated} record(s).";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Compliance Master Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
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

    public function importExtraFeaturesMaster(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file = $request->file('excel_file');
            
            // Load spreadsheet - PhpSpreadsheet handles both Excel and CSV
            $spreadsheet = IOFactory::load($file->getPathName());
            
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows'
                ], 422);
            }

            // Clean headers - normalize to lowercase and replace spaces/special chars
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            // Remove header row
            unset($rows[0]);

            // Expected column mappings
            $columnMap = [
                'sku' => 'sku',
                'ex_feature_1' => 'ex_feature_1',
                'ex_feature_2' => 'ex_feature_2',
                'ex_feature_3' => 'ex_feature_3',
                'ex_feature_4' => 'ex_feature_4'
            ];

            // Find column indices
            $columnIndices = [];
            foreach ($columnMap as $key => $value) {
                $index = array_search($key, $headers);
                if ($index !== false) {
                    $columnIndices[$value] = $index;
                }
            }

            if (!isset($columnIndices['sku'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU column not found in the Excel file'
                ], 422);
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed
                
                // Skip empty rows
                if (empty($row[$columnIndices['sku']])) {
                    continue;
                }

                $sku = trim($row[$columnIndices['sku']]);
                
                // Find product by SKU
                $product = ProductMaster::where('sku', $sku)
                    ->where('sku', 'NOT LIKE', 'PARENT %')
                    ->first();
                
                if (!$product) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                // Get existing Values
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                if (!is_array($values)) {
                    $values = [];
                }

                $hasChanges = false;

                // Update Ex.Feature 1 if column exists and has value
                if (isset($columnIndices['ex_feature_1']) && isset($row[$columnIndices['ex_feature_1']])) {
                    $exFeature1Value = trim($row[$columnIndices['ex_feature_1']]);
                    if ($exFeature1Value !== '') {
                        $values['ex_feature_1'] = $exFeature1Value;
                        $hasChanges = true;
                    }
                }

                // Update Ex.Feature 2 if column exists and has value
                if (isset($columnIndices['ex_feature_2']) && isset($row[$columnIndices['ex_feature_2']])) {
                    $exFeature2Value = trim($row[$columnIndices['ex_feature_2']]);
                    if ($exFeature2Value !== '') {
                        $values['ex_feature_2'] = $exFeature2Value;
                        $hasChanges = true;
                    }
                }

                // Update Ex.Feature 3 if column exists and has value
                if (isset($columnIndices['ex_feature_3']) && isset($row[$columnIndices['ex_feature_3']])) {
                    $exFeature3Value = trim($row[$columnIndices['ex_feature_3']]);
                    if ($exFeature3Value !== '') {
                        $values['ex_feature_3'] = $exFeature3Value;
                        $hasChanges = true;
                    }
                }

                // Update Ex.Feature 4 if column exists and has value
                if (isset($columnIndices['ex_feature_4']) && isset($row[$columnIndices['ex_feature_4']])) {
                    $exFeature4Value = trim($row[$columnIndices['ex_feature_4']]);
                    if ($exFeature4Value !== '') {
                        $values['ex_feature_4'] = $exFeature4Value;
                        $hasChanges = true;
                    }
                }

                // Update product if there are changes
                if ($hasChanges) {
                    $product->Values = $values;
                    $product->save();
                    $updated++;
                    $imported++;
                }
            }

            $message = "Successfully imported {$imported} record(s). Updated {$updated} record(s).";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Extra Features Master Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
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

    public function importAPlusImagesMaster(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file = $request->file('excel_file');
            
            // Load spreadsheet - PhpSpreadsheet handles both Excel and CSV
            $spreadsheet = IOFactory::load($file->getPathName());
            
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows'
                ], 422);
            }

            // Clean headers - normalize to lowercase and replace spaces/special chars
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            // Remove header row
            unset($rows[0]);

            // Expected column mappings (try multiple variations)
            $columnIndices = [];
            
            // Find SKU column
            foreach ($headers as $index => $header) {
                if ($header === 'sku') {
                    $columnIndices['sku'] = $index;
                    break;
                }
            }
            
            // Find Standard A+ column (try multiple variations)
            foreach ($headers as $index => $header) {
                if (in_array($header, ['standard_a_plus', 'standard_a', 'standardaplus', 'standard_a'])) {
                    $columnIndices['standard_a_plus'] = $index;
                    break;
                }
            }
            
            // If not found, try partial match
            if (!isset($columnIndices['standard_a_plus'])) {
                foreach ($headers as $index => $header) {
                    if (strpos($header, 'standard') !== false && (strpos($header, 'a') !== false || strpos($header, 'plus') !== false)) {
                        $columnIndices['standard_a_plus'] = $index;
                        break;
                    }
                }
            }
            
            // Find Premium A+ column (try multiple variations)
            foreach ($headers as $index => $header) {
                if (in_array($header, ['premium_a_plus', 'premium_a', 'premiumaplus', 'premium_a'])) {
                    $columnIndices['premium_a_plus'] = $index;
                    break;
                }
            }
            
            // If not found, try partial match
            if (!isset($columnIndices['premium_a_plus'])) {
                foreach ($headers as $index => $header) {
                    if (strpos($header, 'premium') !== false && (strpos($header, 'a') !== false || strpos($header, 'plus') !== false)) {
                        $columnIndices['premium_a_plus'] = $index;
                        break;
                    }
                }
            }

            if (!isset($columnIndices['sku'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU column not found in the Excel file'
                ], 422);
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed
                
                // Skip empty rows
                if (empty($row[$columnIndices['sku']])) {
                    continue;
                }

                $sku = trim($row[$columnIndices['sku']]);
                
                // Find product by SKU
                $product = ProductMaster::where('sku', $sku)
                    ->where('sku', 'NOT LIKE', 'PARENT %')
                    ->first();
                
                if (!$product) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                // Get existing Values
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                if (!is_array($values)) {
                    $values = [];
                }

                $hasChanges = false;

                // Update Standard A+ if column exists and has value
                if (isset($columnIndices['standard_a_plus']) && isset($row[$columnIndices['standard_a_plus']])) {
                    $standardAPlusValue = trim($row[$columnIndices['standard_a_plus']]);
                    if ($standardAPlusValue !== '') {
                        $values['standard_a_plus'] = $standardAPlusValue;
                        $hasChanges = true;
                    }
                }

                // Update Premium A+ if column exists and has value
                if (isset($columnIndices['premium_a_plus']) && isset($row[$columnIndices['premium_a_plus']])) {
                    $premiumAPlusValue = trim($row[$columnIndices['premium_a_plus']]);
                    if ($premiumAPlusValue !== '') {
                        $values['premium_a_plus'] = $premiumAPlusValue;
                        $hasChanges = true;
                    }
                }

                // Update product if there are changes
                if ($hasChanges) {
                    $product->Values = $values;
                    $product->save();
                    $updated++;
                    $imported++;
                }
            }

            $message = "Successfully imported {$imported} record(s). Updated {$updated} record(s).";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('A+ Images Master Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
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

    public function importKeywordsMaster(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file = $request->file('excel_file');
            
            // Load spreadsheet - PhpSpreadsheet handles both Excel and CSV
            $spreadsheet = IOFactory::load($file->getPathName());
            
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows'
                ], 422);
            }

            // Clean headers - normalize to lowercase and replace spaces/special chars
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            // Remove header row
            unset($rows[0]);

            // Expected column mappings (try multiple variations)
            $columnIndices = [];
            
            // Find SKU column
            foreach ($headers as $index => $header) {
                if ($header === 'sku') {
                    $columnIndices['sku'] = $index;
                    break;
                }
            }
            
            // Find Backend Keywords column (try multiple variations)
            foreach ($headers as $index => $header) {
                if (in_array($header, ['backend_keywords', 'backendkeywords', 'backend_keyword'])) {
                    $columnIndices['backend_keywords'] = $index;
                    break;
                }
            }
            
            // If not found, try partial match
            if (!isset($columnIndices['backend_keywords'])) {
                foreach ($headers as $index => $header) {
                    if (strpos($header, 'backend') !== false && (strpos($header, 'keyword') !== false || strpos($header, 'keywords') !== false)) {
                        $columnIndices['backend_keywords'] = $index;
                        break;
                    }
                }
            }
            
            // Find Generic Keyword column (try multiple variations)
            foreach ($headers as $index => $header) {
                if (in_array($header, ['generic_keyword', 'generickeyword', 'generic_keywords'])) {
                    $columnIndices['generic_keyword'] = $index;
                    break;
                }
            }
            
            // If not found, try partial match
            if (!isset($columnIndices['generic_keyword'])) {
                foreach ($headers as $index => $header) {
                    if (strpos($header, 'generic') !== false && (strpos($header, 'keyword') !== false || strpos($header, 'keywords') !== false)) {
                        $columnIndices['generic_keyword'] = $index;
                        break;
                    }
                }
            }

            if (!isset($columnIndices['sku'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU column not found in the Excel file'
                ], 422);
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed
                
                // Skip empty rows
                if (empty($row[$columnIndices['sku']])) {
                    continue;
                }

                $sku = trim($row[$columnIndices['sku']]);
                
                // Find product by SKU
                $product = ProductMaster::where('sku', $sku)
                    ->where('sku', 'NOT LIKE', 'PARENT %')
                    ->first();
                
                if (!$product) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                // Get existing Values
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                if (!is_array($values)) {
                    $values = [];
                }

                $hasChanges = false;

                // Update Backend Keywords if column exists and has value
                if (isset($columnIndices['backend_keywords']) && isset($row[$columnIndices['backend_keywords']])) {
                    $backendKeywordsValue = trim($row[$columnIndices['backend_keywords']]);
                    if ($backendKeywordsValue !== '') {
                        $values['backend_keywords'] = $backendKeywordsValue;
                        $hasChanges = true;
                    }
                }

                // Update Generic Keyword if column exists and has value
                if (isset($columnIndices['generic_keyword']) && isset($row[$columnIndices['generic_keyword']])) {
                    $genericKeywordValue = trim($row[$columnIndices['generic_keyword']]);
                    if ($genericKeywordValue !== '') {
                        $values['generic_keyword'] = $genericKeywordValue;
                        $hasChanges = true;
                    }
                }

                // Update product if there are changes
                if ($hasChanges) {
                    $product->Values = $values;
                    $product->save();
                    $updated++;
                    $imported++;
                }
            }

            $message = "Successfully imported {$imported} record(s). Updated {$updated} record(s).";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Keywords Master Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
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

    public function importCompetitorsMaster(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file = $request->file('excel_file');
            
            // Load spreadsheet - PhpSpreadsheet handles both Excel and CSV
            $spreadsheet = IOFactory::load($file->getPathName());
            
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows'
                ], 422);
            }

            // Clean headers - normalize to lowercase and replace spaces/special chars
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            // Remove header row
            unset($rows[0]);

            // Expected column mappings
            $columnIndices = [];
            
            // Find SKU column
            foreach ($headers as $index => $header) {
                if ($header === 'sku') {
                    $columnIndices['sku'] = $index;
                    break;
                }
            }
            
            // Find Brand and Link columns (Brand 1 through Brand 5, Link 1 through Link 5)
            for ($i = 1; $i <= 5; $i++) {
                $brandKey = "brand_{$i}";
                $linkKey = "link_{$i}";
                
                // Find Brand column
                foreach ($headers as $index => $header) {
                    if (in_array($header, [$brandKey, "brand{$i}", "brand_{$i}"])) {
                        $columnIndices[$brandKey] = $index;
                        break;
                    }
                }
                
                // If not found, try partial match
                if (!isset($columnIndices[$brandKey])) {
                    foreach ($headers as $index => $header) {
                        if (strpos($header, 'brand') !== false && (strpos($header, (string)$i) !== false || preg_match('/brand\s*' . $i . '/i', $header))) {
                            $columnIndices[$brandKey] = $index;
                            break;
                        }
                    }
                }
                
                // Find Link column
                foreach ($headers as $index => $header) {
                    if (in_array($header, [$linkKey, "link{$i}", "link_{$i}"])) {
                        $columnIndices[$linkKey] = $index;
                        break;
                    }
                }
                
                // If not found, try partial match
                if (!isset($columnIndices[$linkKey])) {
                    foreach ($headers as $index => $header) {
                        if (strpos($header, 'link') !== false && (strpos($header, (string)$i) !== false || preg_match('/link\s*' . $i . '/i', $header))) {
                            $columnIndices[$linkKey] = $index;
                            break;
                        }
                    }
                }
            }

            if (!isset($columnIndices['sku'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU column not found in the Excel file'
                ], 422);
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed
                
                // Skip empty rows
                if (empty($row[$columnIndices['sku']])) {
                    continue;
                }

                $sku = trim($row[$columnIndices['sku']]);
                
                // Find product by SKU
                $product = ProductMaster::where('sku', $sku)
                    ->where('sku', 'NOT LIKE', 'PARENT %')
                    ->first();
                
                if (!$product) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                // Get existing Values
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                if (!is_array($values)) {
                    $values = [];
                }

                $hasChanges = false;

                // Update Brand and Link columns (Brand 1 through Brand 5, Link 1 through Link 5)
                for ($i = 1; $i <= 5; $i++) {
                    $brandKey = "brand_{$i}";
                    $linkKey = "link_{$i}";
                    
                    // Update Brand if column exists and has value
                    if (isset($columnIndices[$brandKey]) && isset($row[$columnIndices[$brandKey]])) {
                        $brandValue = trim($row[$columnIndices[$brandKey]]);
                        if ($brandValue !== '') {
                            $values[$brandKey] = $brandValue;
                            $hasChanges = true;
                        }
                    }
                    
                    // Update Link if column exists and has value
                    if (isset($columnIndices[$linkKey]) && isset($row[$columnIndices[$linkKey]])) {
                        $linkValue = trim($row[$columnIndices[$linkKey]]);
                        if ($linkValue !== '') {
                            $values[$linkKey] = $linkValue;
                            $hasChanges = true;
                        }
                    }
                }

                // Update product if there are changes
                if ($hasChanges) {
                    $product->Values = $values;
                    $product->save();
                    $updated++;
                    $imported++;
                }
            }

            $message = "Successfully imported {$imported} record(s). Updated {$updated} record(s).";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Competitors Master Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
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

    public function importDimWtMaster(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file = $request->file('excel_file');
            
            // Load spreadsheet - PhpSpreadsheet handles both Excel and CSV
            $spreadsheet = IOFactory::load($file->getPathName());
            
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows'
                ], 422);
            }

            // Clean headers - normalize to lowercase and replace spaces/special chars
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            // Remove header row
            unset($rows[0]);

            // Expected column mappings
            $columnMap = [
                'sku' => 'sku',
                'wt_act' => 'wt_act',
                'wt_decl' => 'wt_decl',
                'l' => 'l',
                'w' => 'w',
                'h' => 'h',
                'ctn_l' => 'ctn_l',
                'ctn_w' => 'ctn_w',
                'ctn_h' => 'ctn_h',
                'ctn_cbm' => 'ctn_cbm',
                'ctn_qty' => 'ctn_qty',
                'ctn_cbm_each' => 'ctn_cbm_each',
                'cbm_e' => 'cbm_e',
                'ctn_gwt' => 'ctn_gwt'
            ];

            // Find column indices
            $columnIndices = [];
            foreach ($columnMap as $key => $value) {
                $index = array_search($key, $headers);
                if ($index !== false) {
                    $columnIndices[$value] = $index;
                }
            }

            if (!isset($columnIndices['sku'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU column not found in the Excel file'
                ], 422);
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed
                
                // Skip empty rows
                if (empty($row[$columnIndices['sku']])) {
                    continue;
                }

                $sku = trim($row[$columnIndices['sku']]);
                
                // Find product by SKU
                $product = ProductMaster::where('sku', $sku)
                    ->where('sku', 'NOT LIKE', 'PARENT %')
                    ->first();
                
                if (!$product) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                // Get existing Values
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                if (!is_array($values)) {
                    $values = [];
                }

                // Process each column
                foreach ($columnIndices as $field => $colIndex) {
                    if ($field === 'sku') continue; // Skip SKU field
                    
                    if (isset($row[$colIndex]) && $row[$colIndex] !== '' && $row[$colIndex] !== null) {
                        $value = trim($row[$colIndex]);
                        if ($value !== '') {
                            // Convert to float for numeric fields
                            if (in_array($field, ['wt_act', 'wt_decl', 'l', 'w', 'h', 'ctn_l', 'ctn_w', 'ctn_h', 'ctn_cbm', 'ctn_qty', 'ctn_cbm_each', 'cbm_e', 'ctn_gwt'])) {
                                $value = is_numeric($value) ? (float)$value : null;
                            }
                            if ($value !== null) {
                                $values[$field] = $value;
                            }
                        }
                    }
                }

                // Calculate CTN CBM if CTN L, W, H are provided but CTN CBM is not
                if (!isset($values['ctn_cbm']) || $values['ctn_cbm'] == 0) {
                    if (isset($values['ctn_l']) && isset($values['ctn_w']) && isset($values['ctn_h'])) {
                        $values['ctn_cbm'] = ($values['ctn_l'] * $values['ctn_w'] * $values['ctn_h']) / 1000000;
                    }
                }

                // Calculate CTN CBM/Each if CTN CBM and CTN QTY are provided but CTN CBM/Each is not
                if (!isset($values['ctn_cbm_each']) || $values['ctn_cbm_each'] == 0) {
                    if (isset($values['ctn_cbm']) && isset($values['ctn_qty']) && $values['ctn_qty'] > 0) {
                        $values['ctn_cbm_each'] = $values['ctn_cbm'] / $values['ctn_qty'];
                    }
                }

                // Save the updated Values
                $product->Values = $values;
                $product->save();
                $updated++;
                $imported++;
            }

            $message = "Successfully imported {$imported} records.";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            Log::error('Dim Wt Master Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function pushDimWtDataToPlatforms(Request $request)
    {
        try {
            $request->validate([
                'skus' => 'required|array',
                'skus.*.sku' => 'required|string',
                'skus.*.id' => 'required|integer',
            ]);

            $skus = $request->input('skus');
            $platforms = [
                'amazon', 'ebay', 'ebay2', 'ebay3', 'shopify', 'walmart', 
                'doba', 'temu', 'shein', 'bestbuy', 'tiendamia', 'macy', 
                'reverb', 'wayfair', 'faire', 'aliexpress', 'mercari', 
                'fb_marketplace', 'tiktok_shop', 'fb_shop', 'insta_shop', 'pls'
            ];

            $results = [];
            $totalSuccess = 0;
            $totalFailed = 0;
            $errors = [];

            // Initialize results for each platform
            foreach ($platforms as $platform) {
                $results[$platform] = [
                    'success' => 0,
                    'failed' => 0,
                    'errors' => []
                ];
            }

            // Process each SKU
            foreach ($skus as $skuData) {
                $sku = trim($skuData['sku']);
                $productId = $skuData['id'];
                
                // Get product from database
                $product = ProductMaster::find($productId);
                
                if (!$product || $product->sku !== $sku) {
                    $errors[] = "SKU {$sku}: Product not found";
                    $totalFailed++;
                    continue;
                }

                // Get existing Values
                $values = is_array($product->Values) ? $product->Values : 
                         (is_string($product->Values) ? json_decode($product->Values, true) : []);
                
                if (!is_array($values)) {
                    $values = [];
                }

                // Update dimensions and weight in Values
                $hasChanges = false;
                if (isset($skuData['wt_act']) && $skuData['wt_act'] !== null) {
                    $values['wt_act'] = (float)$skuData['wt_act'];
                    $hasChanges = true;
                }
                if (isset($skuData['wt_decl']) && $skuData['wt_decl'] !== null) {
                    $values['wt_decl'] = (float)$skuData['wt_decl'];
                    $hasChanges = true;
                }
                if (isset($skuData['l']) && $skuData['l'] !== null) {
                    $values['l'] = (float)$skuData['l'];
                    $hasChanges = true;
                }
                if (isset($skuData['w']) && $skuData['w'] !== null) {
                    $values['w'] = (float)$skuData['w'];
                    $hasChanges = true;
                }
                if (isset($skuData['h']) && $skuData['h'] !== null) {
                    $values['h'] = (float)$skuData['h'];
                    $hasChanges = true;
                }

                // Save to ProductMaster if there are changes
                if ($hasChanges) {
                    $product->Values = $values;
                    $product->save();
                }

                // Push to each platform
                foreach ($platforms as $platform) {
                    try {
                        $success = $this->pushToPlatform($platform, $sku, $skuData);
                        
                        if ($success) {
                            $results[$platform]['success']++;
                            $totalSuccess++;
                        } else {
                            $results[$platform]['failed']++;
                            $totalFailed++;
                            $results[$platform]['errors'][] = "SKU {$sku}: Update failed";
                        }
                    } catch (\Exception $e) {
                        $results[$platform]['failed']++;
                        $totalFailed++;
                        $errorMsg = "SKU {$sku}: " . $e->getMessage();
                        $results[$platform]['errors'][] = $errorMsg;
                        $errors[] = ucfirst($platform) . " - " . $errorMsg;
                        Log::error("Push Dim/Wt to {$platform} failed for SKU {$sku}: " . $e->getMessage());
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Push operation completed',
                'results' => $results,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'errors' => array_slice($errors, 0, 50) // Limit errors to first 50
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Push Dim/Wt Data Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error pushing data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Push dimensions and weight to a specific platform
     * 
     * @param string $platform Platform name
     * @param string $sku Product SKU
     * @param array $data Dimension and weight data
     * @return bool Success status
     */
    private function pushToPlatform($platform, $sku, $data)
    {
        try {
            switch (strtolower($platform)) {
                case 'amazon':
                    return $this->pushToAmazon($sku, $data);
                
                case 'ebay':
                case 'ebay2':
                case 'ebay3':
                    return $this->pushToEbay($platform, $sku, $data);
                
                case 'shopify':
                    return $this->pushToShopify($sku, $data);
                
                case 'walmart':
                    return $this->pushToWalmart($sku, $data);
                
                case 'doba':
                    return $this->pushToDoba($sku, $data);
                
                case 'temu':
                    return $this->pushToTemu($sku, $data);
                
                case 'shein':
                    return $this->pushToShein($sku, $data);
                
                case 'bestbuy':
                    return $this->pushToBestBuy($sku, $data);
                
                case 'tiendamia':
                    return $this->pushToTiendamia($sku, $data);
                
                case 'macy':
                    return $this->pushToMacy($sku, $data);
                
                case 'reverb':
                    return $this->pushToReverb($sku, $data);
                
                case 'wayfair':
                    return $this->pushToWayfair($sku, $data);
                
                case 'faire':
                    return $this->pushToFaire($sku, $data);
                
                case 'aliexpress':
                    return $this->pushToAliExpress($sku, $data);
                
                case 'mercari':
                    return $this->pushToMercari($sku, $data);
                
                case 'fb_marketplace':
                    return $this->pushToFbMarketplace($sku, $data);
                
                case 'tiktok_shop':
                    return $this->pushToTikTokShop($sku, $data);
                
                case 'fb_shop':
                    return $this->pushToFbShop($sku, $data);
                
                case 'insta_shop':
                    return $this->pushToInstaShop($sku, $data);
                
                case 'pls':
                    return $this->pushToPls($sku, $data);
                
                default:
                    Log::warning("Unknown platform: {$platform} for SKU: {$sku}");
                    return false;
            }
        } catch (\Exception $e) {
            Log::error("Error pushing to {$platform} for SKU {$sku}: " . $e->getMessage());
            return false;
        }
    }

    // Platform-specific push methods
    // These methods can be implemented to call the respective API services
    
    private function pushToAmazon($sku, $data)
    {
        // TODO: Implement Amazon API call to update dimensions/weight
        // Example: Use AmazonSpApiService to update product dimensions
        Log::info("Pushing dim/wt to Amazon for SKU: {$sku}");
        return true; // Placeholder - implement actual API call
    }

    private function pushToEbay($platform, $sku, $data)
    {
        // TODO: Implement eBay API call based on platform (ebay, ebay2, ebay3)
        // Example: Use EbayApiService, EbayTwoApiService, or EbayThreeApiService
        Log::info("Pushing dim/wt to {$platform} for SKU: {$sku}");
        return true; // Placeholder - implement actual API call
    }

    private function pushToShopify($sku, $data)
    {
        // TODO: Implement Shopify API call to update variant dimensions/weight
        // Example: Use ShopifyApiService to update product variant
        Log::info("Pushing dim/wt to Shopify for SKU: {$sku}");
        return true; // Placeholder - implement actual API call
    }

    private function pushToWalmart($sku, $data)
    {
        // TODO: Implement Walmart API call
        Log::info("Pushing dim/wt to Walmart for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToDoba($sku, $data)
    {
        // TODO: Implement Doba API call
        Log::info("Pushing dim/wt to Doba for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToTemu($sku, $data)
    {
        // TODO: Implement Temu API call
        Log::info("Pushing dim/wt to Temu for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToShein($sku, $data)
    {
        // TODO: Implement Shein API call
        Log::info("Pushing dim/wt to Shein for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToBestBuy($sku, $data)
    {
        // TODO: Implement BestBuy API call
        Log::info("Pushing dim/wt to BestBuy for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToTiendamia($sku, $data)
    {
        // TODO: Implement Tiendamia API call
        Log::info("Pushing dim/wt to Tiendamia for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToMacy($sku, $data)
    {
        // TODO: Implement Macy's API call
        Log::info("Pushing dim/wt to Macy for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToReverb($sku, $data)
    {
        // TODO: Implement Reverb API call
        Log::info("Pushing dim/wt to Reverb for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToWayfair($sku, $data)
    {
        // TODO: Implement Wayfair API call
        Log::info("Pushing dim/wt to Wayfair for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToFaire($sku, $data)
    {
        // TODO: Implement Faire API call
        Log::info("Pushing dim/wt to Faire for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToAliExpress($sku, $data)
    {
        // TODO: Implement AliExpress API call
        Log::info("Pushing dim/wt to AliExpress for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToMercari($sku, $data)
    {
        // TODO: Implement Mercari API call
        Log::info("Pushing dim/wt to Mercari for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToFbMarketplace($sku, $data)
    {
        // TODO: Implement Facebook Marketplace API call
        Log::info("Pushing dim/wt to FB Marketplace for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToTikTokShop($sku, $data)
    {
        // TODO: Implement TikTok Shop API call
        Log::info("Pushing dim/wt to TikTok Shop for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToFbShop($sku, $data)
    {
        // TODO: Implement Facebook Shop API call
        Log::info("Pushing dim/wt to FB Shop for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToInstaShop($sku, $data)
    {
        // TODO: Implement Instagram Shop API call
        Log::info("Pushing dim/wt to Instagram Shop for SKU: {$sku}");
        return true; // Placeholder
    }

    private function pushToPls($sku, $data)
    {
        // TODO: Implement PLS API call
        Log::info("Pushing dim/wt to PLS for SKU: {$sku}");
        return true; // Placeholder
    }

    public function packageIncludesMaster()
    {
        return view('package-includes-master');
    }

    public function getPackageIncludesMasterData(Request $request)
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

    public function storePackageIncludesMaster(Request $request)
    {
        try {
            // Build validation rules for all items
            $rules = ['sku' => 'required|string'];
            for ($i = 1; $i <= 10; $i++) {
                $rules["item{$i}"] = 'nullable|string';
            }
            
            $validated = $request->validate($rules);

            // Get the product by SKU to retrieve parent
            $existingProduct = ProductMaster::where('sku', $validated['sku'])
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();

            $parent = null;
            if ($existingProduct) {
                $parent = $existingProduct->parent;
            }

            // Check if product with same SKU already has package includes data
            if ($existingProduct) {
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                // Check if any item fields already exist
                $hasPackageIncludesData = false;
                for ($i = 1; $i <= 10; $i++) {
                    if (!empty($existingValues["item{$i}"])) {
                        $hasPackageIncludesData = true;
                        break;
                    }
                }
                
                if ($hasPackageIncludesData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This SKU already has package includes data. Please use edit instead.'
                    ], 409);
                }
            }

            // Prepare Values array with item fields
            $values = [];
            
            for ($i = 1; $i <= 10; $i++) {
                if (!empty($validated["item{$i}"])) {
                    $values["item{$i}"] = $validated["item{$i}"];
                }
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
                'message' => 'Package includes data saved successfully',
                'data' => $product
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function importPackageIncludesMaster(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file = $request->file('excel_file');
            
            // Load spreadsheet - PhpSpreadsheet handles both Excel and CSV
            $spreadsheet = IOFactory::load($file->getPathName());
            
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows'
                ], 422);
            }

            // Clean headers - normalize to lowercase and replace spaces/special chars
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            // Remove header row
            unset($rows[0]);

            // Expected column mappings
            $columnIndices = [];
            
            // Find SKU column
            foreach ($headers as $index => $header) {
                if ($header === 'sku') {
                    $columnIndices['sku'] = $index;
                    break;
                }
            }
            
            // Find Item columns (Item1 through Item10)
            for ($i = 1; $i <= 10; $i++) {
                $itemKey = "item{$i}";
                foreach ($headers as $index => $header) {
                    if (in_array($header, [$itemKey, "item_{$i}", "item{$i}"])) {
                        $columnIndices[$itemKey] = $index;
                        break;
                    }
                }
                
                // If not found, try partial match
                if (!isset($columnIndices[$itemKey])) {
                    foreach ($headers as $index => $header) {
                        if (strpos($header, 'item') !== false && (strpos($header, (string)$i) !== false || preg_match('/item\s*' . $i . '/i', $header))) {
                            $columnIndices[$itemKey] = $index;
                            break;
                        }
                    }
                }
            }

            if (!isset($columnIndices['sku'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU column not found in the Excel file'
                ], 422);
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed
                
                // Skip empty rows
                if (empty($row[$columnIndices['sku']])) {
                    continue;
                }

                $sku = trim($row[$columnIndices['sku']]);
                
                // Find product by SKU
                $product = ProductMaster::where('sku', $sku)
                    ->where('sku', 'NOT LIKE', 'PARENT %')
                    ->first();
                
                if (!$product) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                // Get existing Values
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                if (!is_array($values)) {
                    $values = [];
                }

                $hasChanges = false;

                // Update Item columns (Item1 through Item10) if they exist and have values
                for ($i = 1; $i <= 10; $i++) {
                    $itemKey = "item{$i}";
                    if (isset($columnIndices[$itemKey]) && isset($row[$columnIndices[$itemKey]])) {
                        $itemValue = trim($row[$columnIndices[$itemKey]]);
                        if ($itemValue !== '') {
                            $values[$itemKey] = $itemValue;
                            $hasChanges = true;
                        }
                    }
                }

                // Update product if there are changes
                if ($hasChanges) {
                    $product->Values = $values;
                    $product->save();
                    $updated++;
                    $imported++;
                }
            }

            $message = "Successfully imported {$imported} record(s). Updated {$updated} record(s).";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Package Includes Master Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function qaMaster()
    {
        return view('qa-master');
    }

    public function getQAMasterData(Request $request)
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

    public function storeQAMaster(Request $request)
    {
        try {
            // Build validation rules for all questions and answers
            $rules = ['sku' => 'required|string'];
            for ($i = 1; $i <= 10; $i++) {
                $rules["question{$i}"] = 'nullable|string';
                $rules["answer{$i}"] = 'nullable|string';
            }
            
            $validated = $request->validate($rules);

            // Get the product by SKU to retrieve parent
            $existingProduct = ProductMaster::where('sku', $validated['sku'])
                ->where('sku', 'NOT LIKE', 'PARENT %')
                ->first();

            $parent = null;
            if ($existingProduct) {
                $parent = $existingProduct->parent;
            }

            // Check if product with same SKU already has Q&A data
            if ($existingProduct) {
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                // Check if any Q&A fields already exist
                $hasQAData = false;
                for ($i = 1; $i <= 10; $i++) {
                    if (!empty($existingValues["question{$i}"]) || !empty($existingValues["answer{$i}"])) {
                        $hasQAData = true;
                        break;
                    }
                }
                
                if ($hasQAData) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This SKU already has Q&A data. Please use edit instead.'
                    ], 409);
                }
            }

            // Prepare Values array with Q&A fields
            $values = [];
            
            for ($i = 1; $i <= 10; $i++) {
                if (!empty($validated["question{$i}"])) {
                    $values["question{$i}"] = $validated["question{$i}"];
                }
                if (!empty($validated["answer{$i}"])) {
                    $values["answer{$i}"] = $validated["answer{$i}"];
                }
            }

            // Update existing product or create new one
            if ($existingProduct) {
                // Merge with existing Values
                $existingValues = is_array($existingProduct->Values) ? $existingProduct->Values : 
                                 (is_string($existingProduct->Values) ? json_decode($existingProduct->Values, true) : []);
                
                $mergedValues = array_merge($existingValues ?? [], $values);
                
                $existingProduct->update([
                    'Values' => $mergedValues
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Q&A data updated successfully',
                    'data' => $existingProduct
                ]);
            } else {
                // Create new product
                $newProduct = ProductMaster::create([
                    'sku' => $validated['sku'],
                    'parent' => $parent,
                    'Values' => $values
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Q&A data created successfully',
                    'data' => $newProduct
                ]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error saving data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function importQAMaster(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $file = $request->file('excel_file');
            
            // Load spreadsheet - PhpSpreadsheet handles both Excel and CSV
            $spreadsheet = IOFactory::load($file->getPathName());
            
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Excel file is empty or has no data rows'
                ], 422);
            }

            // Clean headers - normalize to lowercase and replace spaces/special chars
            $headers = array_map(function ($header) {
                return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '_', $header)));
            }, $rows[0]);

            // Remove header row
            unset($rows[0]);

            // Expected column mappings
            $columnIndices = [];
            
            // Find SKU column
            foreach ($headers as $index => $header) {
                if ($header === 'sku') {
                    $columnIndices['sku'] = $index;
                    break;
                }
            }
            
            // Find Question and Answer columns (Question1 through Question10, Answer1 through Answer10)
            for ($i = 1; $i <= 10; $i++) {
                $questionKey = "question{$i}";
                $answerKey = "answer{$i}";
                
                // Find Question column
                foreach ($headers as $index => $header) {
                    if (in_array($header, [$questionKey, "question_{$i}", "question{$i}"])) {
                        $columnIndices[$questionKey] = $index;
                        break;
                    }
                }
                
                // If not found, try partial match
                if (!isset($columnIndices[$questionKey])) {
                    foreach ($headers as $index => $header) {
                        if (strpos($header, 'question') !== false && (strpos($header, (string)$i) !== false || preg_match('/question\s*' . $i . '/i', $header))) {
                            $columnIndices[$questionKey] = $index;
                            break;
                        }
                    }
                }
                
                // Find Answer column
                foreach ($headers as $index => $header) {
                    if (in_array($header, [$answerKey, "answer_{$i}", "answer{$i}"])) {
                        $columnIndices[$answerKey] = $index;
                        break;
                    }
                }
                
                // If not found, try partial match
                if (!isset($columnIndices[$answerKey])) {
                    foreach ($headers as $index => $header) {
                        if (strpos($header, 'answer') !== false && (strpos($header, (string)$i) !== false || preg_match('/answer\s*' . $i . '/i', $header))) {
                            $columnIndices[$answerKey] = $index;
                            break;
                        }
                    }
                }
            }

            if (!isset($columnIndices['sku'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'SKU column not found in the Excel file'
                ], 422);
            }

            $imported = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we removed header and arrays are 0-indexed
                
                // Skip empty rows
                if (empty($row[$columnIndices['sku']])) {
                    continue;
                }

                $sku = trim($row[$columnIndices['sku']]);
                
                // Find product by SKU
                $product = ProductMaster::where('sku', $sku)
                    ->where('sku', 'NOT LIKE', 'PARENT %')
                    ->first();
                
                if (!$product) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                // Get existing Values
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                if (!is_array($values)) {
                    $values = [];
                }

                $hasChanges = false;

                // Update Question and Answer columns (Question1 through Question10, Answer1 through Answer10)
                for ($i = 1; $i <= 10; $i++) {
                    $questionKey = "question{$i}";
                    $answerKey = "answer{$i}";
                    
                    // Update Question if column exists and has value
                    if (isset($columnIndices[$questionKey]) && isset($row[$columnIndices[$questionKey]])) {
                        $questionValue = trim($row[$columnIndices[$questionKey]]);
                        if ($questionValue !== '') {
                            $values[$questionKey] = $questionValue;
                            $hasChanges = true;
                        }
                    }
                    
                    // Update Answer if column exists and has value
                    if (isset($columnIndices[$answerKey]) && isset($row[$columnIndices[$answerKey]])) {
                        $answerValue = trim($row[$columnIndices[$answerKey]]);
                        if ($answerValue !== '') {
                            $values[$answerKey] = $answerValue;
                            $hasChanges = true;
                        }
                    }
                }

                // Update product if there are changes
                if ($hasChanges) {
                    $product->Values = $values;
                    $product->save();
                    $updated++;
                    $imported++;
                }
            }

            $message = "Successfully imported {$imported} record(s). Updated {$updated} record(s).";
            if (count($errors) > 0) {
                $message .= " " . count($errors) . " error(s) occurred.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Q&A Master Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

}
