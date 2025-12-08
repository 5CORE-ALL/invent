<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Controller;
use App\Models\LinkedProductData;
use App\Models\Permission;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ProductMasterController extends Controller
{
    protected $apiController;

    public function __construct(ApiController $apiController)
    {
        $this->apiController = $apiController;
    }

    /**
     * Handle dynamic route parameters and return a view.
     */

    public function product_master_index(Request $request)
    {
        // Fetch all products sorted by ID first
        $allProducts = DB::table('product_master')->orderBy('id', 'asc')->get();

        // Group products by parent while maintaining original order for non-parent items
        $sortedProducts = [];
        $processedParents = [];

        foreach ($allProducts as $product) {
            // If this product has a parent and we haven't processed this parent group yet
            if ($product->parent && !in_array($product->parent, $processedParents)) {
                // Get all products with this parent
                $grouped = $allProducts->where('parent', $product->parent);
                foreach ($grouped as $item) {
                    $sortedProducts[] = (array) $item; // Convert to array
                }
                $processedParents[] = $product->parent;
            }
            // If this product doesn't have a parent and hasn't been added yet
            elseif (!$product->parent && !in_array($product->id, array_column($sortedProducts, 'id'))) {
                $sortedProducts[] = (array) $product; // Convert to array
            }
        }

        // Calculate statistics
        $totalSKUs = count($sortedProducts);
        $parentCount = collect($sortedProducts)->filter(function ($product) {
            return strpos($product['sku'], 'PARENT') !== false;
        })->count();
        // Calculate total LP and CP
        $totalLP = collect($sortedProducts)->sum('lp');
        $totalCP = collect($sortedProducts)->sum('cp');

        $emails = User::pluck('email')->toArray();

        // Fetch all role-based permissions
        $rolePermissions = Permission::all()->keyBy('role');

        // Build a map: email => columns based on user role
        $emailColumnMap = [];
        foreach ($emails as $email) {
            $user = User::where('email', $email)->first();
            $columns = [];
            if ($user && isset($rolePermissions[$user->role])) {
                $columns = $rolePermissions[$user->role]->permissions['product_lists'] ?? [];
            }
            $emailColumnMap[$email] = $columns;
        }

        // Get current user permissions based on role
        $userPermissions = [];
        if (auth()->check()) {
            $userRole = auth()->user()->role;
            $rolePermission = Permission::where('role', $userRole)->first();
            $userPermissions = $rolePermission ? $rolePermission->permissions : [];
        }

        return view('productmaster', [
            'products' => $sortedProducts,
            'totalSKUs' => $totalSKUs,
            'parentCount' => $parentCount,
            'totalLP' => number_format($totalLP, 2),
            'totalCP' => number_format($totalCP, 2),
            'emails' => $emails,
            'emailColumnMap' => $emailColumnMap, // Pass this to Blade
            'permissions' => $userPermissions, // Pass user permissions to view
        ]);
    }


    public function getViewProductData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END") 
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and key by SKU for fast lookup
        $shopifySkus = ShopifySku::all()->keyBy('sku');

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
            // $row['shopify_inv'] = $shopifySkus[$product->sku]->inv ?? null;
            // $row['shopify_quantity'] = $shopifySkus[$product->sku]->quantity ?? null;

            if (isset($shopifySkus[$product->sku])) {
                $shopifyData = $shopifySkus[$product->sku];
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float)$shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float)$shopifyData->quantity : 0;
                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $shopifyImage = null;
            }


            $shopifyImage = $shopifySkus[$product->sku]->image_src ?? null;
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


    public function getProductBySku(Request $request)
{
    // Get SKU from query param (with spaces)
    $sku = $request->query('sku');

    if (!$sku) {
        return response()->json([
            'message' => 'SKU is required',
            'status' => 400
        ], 400);
    }

    // Normalize spaces (remove extra, keep inside)
    $sku = preg_replace('/\s+/', ' ', trim($sku));

    // Fetch product
    $product = ProductMaster::where('sku', $sku)->first();

    if (!$product) {
        return response()->json([
            'message' => "Product not found for SKU: {$sku}",
            'status' => 404
        ], 404);
    }

    // Shopify data
    $shopifySku = ShopifySku::where('sku', $sku)->first();

    // Build response
    $row = [
        'id'     => $product->id,
        'Parent' => $product->parent,
        'SKU'    => $product->sku,
    ];

    // Merge values JSON
    $values = $product->Values;
    if (is_array($values)) {
        $row = array_merge($row, $values);
    } elseif (is_string($values)) {
        $decoded = json_decode($values, true);
        if (is_array($decoded)) {
            $row = array_merge($row, $decoded);
        }
    }

    // Shopify fields
    $row['shopify_inv'] = $shopifySku->inv ?? null;
    $row['shopify_quantity'] = $shopifySku->quantity ?? null;

    // Image
    $shopifyImage = $shopifySku->image_src ?? null;
    $localImage = $row['image_path'] ?? null;
    if ($shopifyImage) {
        $row['image_path'] = $shopifyImage;
    } elseif ($localImage) {
        $row['image_path'] = '/' . ltrim($localImage, '/');
    } else {
        $row['image_path'] = null;
    }

    return response()->json([
        'message' => 'Product loaded successfully',
        'data' => $row,
        'status' => 200
    ]);
}



    
    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $validated = $request->validate([
            'parent' => 'nullable|string',
            'sku' => 'required|string',
            'Values' => 'required',
            'unit' => 'required|string',
            'image' => 'nullable|file|image|max:5120', // 5MB max
        ]);

        $operation = $request->input('operation', 'create');
        $originalSku = $request->input('original_sku');
        $originalParent = $request->input('original_parent');

        try {
            // Prepare Values as array
            $values = is_array($validated['Values']) ? $validated['Values'] : json_decode($validated['Values'], true);

            // Handle image upload if present
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imagePath = $image->store('product_images', 'public');
                $values['image_path'] = 'storage/' . $imagePath;
            }

            if ($operation === 'update' && $originalSku) {
                // Find the product by original SKU and parent
                $query = ProductMaster::where('sku', $originalSku);
                if ($originalParent !== null) {
                    $query->where('parent', $originalParent);
                }
                $product = $query->first();

                if (!$product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product not found for update.'
                    ], 404);
                }

                // Prepare for possible image deletion
                $oldValues = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                $oldImagePath = !empty($oldValues['image_path']) ? public_path($oldValues['image_path']) : null;
                $newImageUploaded = false;

                // Handle image upload if present
                if ($request->hasFile('image')) {
                    $image = $request->file('image');
                    $imagePath = $image->store('product_images', 'public');
                    $values['image_path'] = 'storage/' . $imagePath;
                    $newImageUploaded = true;
                } else {
                    // Keep old image_path if not uploading a new image
                    if (!empty($oldValues['image_path'])) {
                        $values['image_path'] = $oldValues['image_path'];
                    }
                }

                // Check if changing SKU/parent would create a duplicate
                if ($validated['sku'] !== $originalSku || $validated['parent'] !== $originalParent) {
                    $duplicate = ProductMaster::where('sku', $validated['sku'])
                        ->where('parent', $validated['parent'])
                        ->where('id', '!=', $product->id)
                        ->first();
                    
                    if ($duplicate) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Another product with this SKU already exists.',
                            'data' => $duplicate
                        ], 409); // 409 Conflict status code
                    }
                }
                
                $product->sku = $validated['sku'];
                $product->parent = $validated['parent'];
                $product->Values = $values;
                $product->save();

                // Only delete old image after successful update and if a new image was uploaded
                if ($newImageUploaded && $oldImagePath && file_exists($oldImagePath)) {
                    @unlink($oldImagePath);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Product updated successfully',
                    'data' => $product
                ]);
            } else {
                // Check for soft-deleted product with same SKU and Parent
                $existing = ProductMaster::withTrashed()
                    ->where('sku', $validated['sku'])
                    ->where('parent', $validated['parent'])
                    ->first();

                if ($existing && !$existing->trashed()) {
                    // Product already exists and is not deleted
                    return response()->json([
                        'success' => false,
                        'message' => 'A product with this SKU already exists in the database.',
                        'data' => $existing
                    ], 409); // 409 Conflict status code
                } else if ($existing && $existing->trashed()) {
                    // Restore and update
                    $existing->restore();
                    $existing->Values = $values;
                    $existing->save();
                    $product = $existing;
                } else {
                    // Create new product
                    $product = ProductMaster::create([
                        'sku' => $validated['sku'],
                        'parent' => $validated['parent'],
                        'Values' => $values,
                    ]);
                }

                // 2. Also create a row with parent = original parent, sku = 'PARENT {parent}', Values = null
                if (!empty($validated['parent'])) {
                    $parentSku = 'PARENT ' . $validated['parent'];
                    // Check for both existing and soft-deleted rows
                    $parentRow = ProductMaster::withTrashed()
                        ->where('sku', $parentSku)
                        ->where('parent', $validated['parent'])
                        ->first();

                    if ($parentRow) {
                        if ($parentRow->trashed()) {
                            $parentRow->restore();
                        }
                    } elseif (!$parentRow) {
                        // Only create if it doesn't exist at all
                        ProductMaster::create([
                            'sku' => $parentSku,
                            'parent' => $validated['parent'],
                            'Values' => null,
                        ]);
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Product saved successfully',
                    'data' => $product
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error saving product: ' . $e->getMessage());
            
            // Check for duplicate entry error (integrity constraint violation)
            if (strpos($e->getMessage(), 'Integrity constraint violation') !== false && 
                strpos($e->getMessage(), 'Duplicate entry') !== false) {
                
                // Extract the duplicate SKU from the error message
                preg_match("/Duplicate entry '(.+)' for/", $e->getMessage(), $matches);
                $duplicateSku = isset($matches[1]) ? $matches[1] : 'this SKU';
                
                return response()->json([
                    'success' => false,
                    'message' => "Product with SKU '{$duplicateSku}' already exists in the database.",
                    'error_type' => 'duplicate_entry'
                ], 409); // Conflict status code
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Error saving product: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            // Get product IDs (single or multiple)
            $productIds = $request->input('ids');

            if (empty($productIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No products selected for deletion.',
                ], 400);
            }

            // Convert to array if single ID is provided
            if (!is_array($productIds)) {
                $productIds = [$productIds];
            }

            // Fetch products to delete
            $products = ProductMaster::whereIn('id', $productIds)->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No matching products found.',
                ], 404);
            }

            foreach ($products as $product) {

                // Delete image if exists
                if ($product->image && File::exists(public_path($product->image))) {
                    File::delete(public_path($product->image));
                }
                
                //Log the user who is deleting
                $product->deleted_by = auth()->id();
                $product->save();

                // Hard delete the product
                $product->delete();
            }

            return response()->json([
                'success' => true,
                'message' => count($productIds) . ' product(s) archived successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting product(s): ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product(s). Please try again.'
            ], 500);
        }
    }

    public function importFromSheet(Request $request)
    {
        $sheetData = $request->input('data');
        $imported = 0;
        $errors = [];

        // Define your mapping here (sheet column => db key)
        $keyMap = [
            'LP' => 'lp',
            'CP' => 'cp',
            'MOQ' => 'moq',
            'FRGHT' => 'frght',
            'SHIP' => 'ship',
            'SHIP FBA' => 'ship_fba',
            'SHIP Temu' => 'ship_temu',
            'MOQ' => 'moq',
            'SHIP eBay2' => 'ship_ebay2',
            'Label QTY' => 'label_qty',
            'WT ACT' => 'wt_act',
            'WT DECL' => 'wt_decl',
            'L' => 'l',
            'W' => 'w',
            'H' => 'h',
            'CBM' => 'cbm',
            'DC' => 'dc',
            '5C' => 'l2_url',
            'Pcs/Box' => 'pcs_per_box',
            'L1' => 'l1',
            'B' => 'b',
            'H1' => 'h1',
            'Weight' => 'weight',
            'MSRP' => 'msrp',
            'MAP' => 'map',
            'STATUS' => 'status',
            'UPC' => 'upc',
            'Initial Quantity' => 'initial_quantity'
        ];

        foreach ($sheetData as $row) {
            try {
                $parent = $row['Parent'] ?? null;
                $sku = $row['SKU'] ?? null;

                if (!$sku) {
                    $errors[] = 'SKU missing in row: ' . json_encode($row);
                    continue;
                }

                // Build the transformed array with all keys, set to null if missing
                $transformed = [];
                foreach ($keyMap as $sheetKey => $dbKey) {
                    $transformed[$dbKey] = array_key_exists($sheetKey, $row) && $row[$sheetKey] !== '' ? $row[$sheetKey] : null;
                }

                ProductMaster::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'parent' => $parent,
                        'Values' => $transformed,
                    ]
                );
                $imported++;
            } catch (\Exception $e) {
                $errors[] = 'Error for SKU ' . ($row['SKU'] ?? 'N/A') . ': ' . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'errors' => $errors,
        ]);
    }

    public function batchUpdate(Request $request)
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:product_master,id',
            'operations' => 'required|array',
            'operations.*.field' => 'required|string',
            'operations.*.operation' => 'required|string|in:set,add,subtract,multiply,divide',
            'operations.*.value' => 'required',
        ]);

        DB::beginTransaction();

        try {
            foreach ($data['items'] as $item) {
                $product = ProductMaster::find($item['id']);

                if (!$product) {
                    continue;
                }

                // Decode the existing Values JSON into an associative array
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);

                foreach ($data['operations'] as $operation) {
                    $field = $operation['field'];
                    $value = $operation['value'];

                    if (!isset($values[$field])) {
                        $values[$field] = 0; // Initialize if not present, this assumes numerical value for arithmetic operations
                    }

                    switch ($operation['operation']) {
                        case 'set':
                            $values[$field] = $value;
                            break;
                        case 'add':
                            $values[$field] += $value;
                            break;
                        case 'subtract':
                            $values[$field] -= $value;
                            break;
                        case 'multiply':
                            $values[$field] *= $value;
                            break;
                        case 'divide':
                            if ($value != 0) {
                                $values[$field] /= $value;
                            }
                            break;
                    }
                }

                // Encode the updated values back to JSON and save
                $product->Values = $values;
                $product->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Products updated successfully.'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Batch update failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update products. Please try again.'
            ], 500);
        }
    }

    public function linkedProductsView(){

        $warehouses = Warehouse::select('id', 'name')->get();

        $skus = ProductMaster::select('product_master.id', 'product_master.parent', 'product_master.sku', 'shopify_skus.inv as available_quantity', 'shopify_skus.quantity as l30')
            ->leftJoin('shopify_skus', 'product_master.sku', '=', 'shopify_skus.sku')
            ->get()
            ->map(function ($item) {
            $inv = $item->available_quantity ?? 0;
            $l30 = $item->l30 ?? 0;
            $item->dil = $inv != 0 ? round(($l30 / $inv) * 100) : 0;
            return $item;
        });

        return view('inventory-management.linked-products-view', compact('warehouses', 'skus'));
    }

    public function linkedProductStore(Request $request)
    {
        $request->validate([
            'group_id' => 'required|numeric',
            'from_sku' => 'required|string|different:to_sku',
            'to_sku' => 'required|string|different:from_sku',
        ]);

        // Update both SKUs with given group_id
        ProductMaster::whereIn('sku', [$request->from_sku, $request->to_sku])
            ->update(['group_id' => $request->group_id]);

        return response()->json([
            'success' => true,
            'message' => 'Products linked successfully',
            'group_id' => $request->group_id,
        ]);
    }

    public function linkedProductsList()
    {
         $data = ProductMaster::whereNotNull('group_id')
        ->orderBy('group_id')
        ->get()
        ->groupBy('group_id') // group all SKUs under the same group_id
        ->map(function ($group, $groupId) {
            return [
                'group_id' => $groupId,
                'skus'     => $group->pluck('sku')->toArray(),
                'parents'  => $group->pluck('parent')->unique()->toArray(),
            ];
        })
        ->values(); // reset keys for JSON

        return response()->json(['data' => $data]);
    }

    public function showUpdatedQty()
    {
        return view('inventory-management.auto-updated-qty');
    }

    // Data for DataTable / AJAX
    public function showUpdatedQtyList(Request $request)
    {
        $data = LinkedProductData::latest()->get(['group_id', 'sku', 'old_qty', 'new_qty']);

        return response()->json($data);
    }


    public function getArchived()
    {
        try {
            $archived = ProductMaster::onlyTrashed()
            ->leftJoin('users', 'product_master.deleted_by', '=', 'users.id') 
            ->select('product_master.*','users.name as deleted_by_name')
            ->orderBy('product_master.deleted_at', 'desc')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $archived
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching archived products: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch archived products.'
            ], 500);
        }
    }

    public function restore(Request $request)
    {
        try {
            $ids = $request->input('ids');

            if (empty($ids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No products selected for restoration.'
                ], 400);
            }

            if (!is_array($ids)) {
                $ids = [$ids];
            }

            ProductMaster::withTrashed()
            ->whereIn('id', $ids)
            ->update(['deleted_at' => null]);

            return response()->json([
                'success' => true,
                'message' => count($ids) . ' product(s) restored successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error restoring products: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore products.'
            ], 500);
        }
    }

    public function saveTitleData(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'title150' => 'nullable|string',
                'title100' => 'nullable|string',
                'title80' => 'nullable|string',
                'title60' => 'nullable|string',
            ]);

            // Try both uppercase SKU and lowercase sku columns
            $product = ProductMaster::where('SKU', $validated['sku'])
                ->orWhere('sku', $validated['sku'])
                ->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product with SKU "' . $validated['sku'] . '" not found in database.'
                ], 404);
            }

            // Update only the 4 title columns in product_master table
            $product->title150 = $validated['title150'] ?? null;
            $product->title100 = $validated['title100'] ?? null;
            $product->title80 = $validated['title80'] ?? null;
            $product->title60 = $validated['title60'] ?? null;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Title data saved successfully for SKU: ' . $validated['sku']
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving title data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save title data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveBulletData(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'bullet1' => 'nullable|string|max:200',
                'bullet2' => 'nullable|string|max:200',
                'bullet3' => 'nullable|string|max:200',
                'bullet4' => 'nullable|string|max:200',
                'bullet5' => 'nullable|string|max:200',
            ]);

            $product = ProductMaster::where('sku', $validated['sku'])->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.'
                ], 404);
            }

            $product->bullet1 = $validated['bullet1'];
            $product->bullet2 = $validated['bullet2'];
            $product->bullet3 = $validated['bullet3'];
            $product->bullet4 = $validated['bullet4'];
            $product->bullet5 = $validated['bullet5'];
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Bullet points saved successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving bullet data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save bullet data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveDescriptionData(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'product_description' => 'nullable|string|max:1500',
            ]);

            $product = ProductMaster::where('sku', $validated['sku'])->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.'
                ], 404);
            }

            $product->product_description = $validated['product_description'];
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Product description saved successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving description data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save description data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveFeaturesData(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'feature1' => 'nullable|string|max:100',
                'feature2' => 'nullable|string|max:100',
                'feature3' => 'nullable|string|max:100',
                'feature4' => 'nullable|string|max:100',
            ]);

            $product = ProductMaster::where('sku', $validated['sku'])->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.'
                ], 404);
            }

            $product->feature1 = $validated['feature1'];
            $product->feature2 = $validated['feature2'];
            $product->feature3 = $validated['feature3'];
            $product->feature4 = $validated['feature4'];
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Features saved successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving features data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save features data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveImagesData(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'main_image' => 'nullable|string',
                'main_image_brand' => 'nullable|string',
                'image1' => 'nullable|string',
                'image2' => 'nullable|string',
                'image3' => 'nullable|string',
                'image4' => 'nullable|string',
                'image5' => 'nullable|string',
                'image6' => 'nullable|string',
                'image7' => 'nullable|string',
                'image8' => 'nullable|string',
                'image9' => 'nullable|string',
                'image10' => 'nullable|string',
                'image11' => 'nullable|string',
                'image12' => 'nullable|string',
            ]);

            $product = ProductMaster::where('sku', $validated['sku'])->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found.'
                ], 404);
            }

            $product->main_image = $validated['main_image'];
            $product->main_image_brand = $validated['main_image_brand'];
            $product->image1 = $validated['image1'];
            $product->image2 = $validated['image2'];
            $product->image3 = $validated['image3'];
            $product->image4 = $validated['image4'];
            $product->image5 = $validated['image5'];
            $product->image6 = $validated['image6'];
            $product->image7 = $validated['image7'];
            $product->image8 = $validated['image8'];
            $product->image9 = $validated['image9'];
            $product->image10 = $validated['image10'];
            $product->image11 = $validated['image11'];
            $product->image12 = $validated['image12'];
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Images saved successfully.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving images data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save images data: ' . $e->getMessage()
            ], 500);
        }
    }


}
