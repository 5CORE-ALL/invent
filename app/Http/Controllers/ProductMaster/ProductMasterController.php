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

    // Shopify data - normalize SKU for lookup
    $normalizedSku = str_replace("\u{00a0}", ' ', $sku);
    $shopifySku = ShopifySku::where('sku', $normalizedSku)
        ->orWhere('sku', str_replace(' ', "\u{00a0}", $sku))
        ->first();

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
            'parent' => 'required|string',
            'sku' => 'required|string',
            'Values' => 'required',
            'unit' => 'required|string',
            'image' => 'nullable|file|image|max:5120', // 5MB max
        ]);

        // Skip validation for PARENT SKUs
        $sku = $validated['sku'];
        $isParentSku = stripos($sku, 'PARENT') !== false;

        // Validate Values JSON contains required fields (skip for PARENT SKUs)
        $values = is_array($validated['Values']) ? $validated['Values'] : json_decode($validated['Values'], true);
        
        if (!is_array($values)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Values data format'
            ], 422);
        }

        // Skip required field validation for PARENT SKUs
        if (!$isParentSku) {
            $requiredFields = [
                'label_qty' => 'Label QTY',
                'status' => 'Status',
                'cp' => 'CP',
                'wt_act' => 'WT ACT',
                'wt_decl' => 'WT DECL',
                'ship' => 'SHIP',
                'upc' => 'UPC',
                'w' => 'W',
                'l' => 'L',
                'h' => 'H'
            ];

            $missingFields = [];
            foreach ($requiredFields as $field => $label) {
                if (!isset($values[$field]) || $values[$field] === '' || $values[$field] === null) {
                    $missingFields[] = $label;
                }
            }

            if (!empty($missingFields)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The following fields are required: ' . implode(', ', $missingFields),
                    'errors' => $missingFields
                ], 422);
            }

            // Validate numeric fields
            $numericFields = ['label_qty', 'cp', 'wt_act', 'wt_decl', 'w', 'l', 'h'];
            $invalidNumeric = [];
            foreach ($numericFields as $field) {
                if (isset($values[$field]) && $values[$field] !== '' && $values[$field] !== null) {
                    if (!is_numeric($values[$field]) || floatval($values[$field]) < 0) {
                        $invalidNumeric[] = $requiredFields[$field] ?? $field;
                    }
                }
            }

            if (!empty($invalidNumeric)) {
                return response()->json([
                    'success' => false,
                    'message' => 'The following fields must be valid positive numbers: ' . implode(', ', $invalidNumeric),
                    'errors' => $invalidNumeric
                ], 422);
            }
        }

        $operation = $request->input('operation', 'create');
        $originalSku = $request->input('original_sku');
        $originalParent = $request->input('original_parent');

        try {
            // Values array is already prepared above during validation

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

    public function saveVideosData(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'video_product_overview' => 'nullable|string|max:500',
                'video_unboxing' => 'nullable|string|max:500',
                'video_how_to' => 'nullable|string|max:500',
                'video_setup' => 'nullable|string|max:500',
                'video_troubleshooting' => 'nullable|string|max:500',
                'video_brand_story' => 'nullable|string|max:500',
                'video_product_benefits' => 'nullable|string|max:500',
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

            // Update video columns in product_master table
            $product->video_product_overview = $validated['video_product_overview'] ?? null;
            $product->video_unboxing = $validated['video_unboxing'] ?? null;
            $product->video_how_to = $validated['video_how_to'] ?? null;
            $product->video_setup = $validated['video_setup'] ?? null;
            $product->video_troubleshooting = $validated['video_troubleshooting'] ?? null;
            $product->video_brand_story = $validated['video_brand_story'] ?? null;
            $product->video_product_benefits = $validated['video_product_benefits'] ?? null;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Video data saved successfully for SKU: ' . $validated['sku']
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving video data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save video data: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateTitlesToAmazon(Request $request)
    {
        try {
            $validated = $request->validate([
                'skus' => 'required|array',
                'skus.*' => 'required|string',
            ]);

            $skus = $validated['skus'];

            // Get Amazon API configuration
            $clientId = env('SPAPI_CLIENT_ID');
            $clientSecret = env('SPAPI_CLIENT_SECRET');
            $refreshToken = env('SPAPI_REFRESH_TOKEN');
            $sellerId = env('AMAZON_SELLER_ID');
            $marketplaceId = env('SPAPI_MARKETPLACE_ID', 'ATVPDKIKX0DER');

            if (!$clientId || !$clientSecret || !$refreshToken || !$sellerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amazon SP-API credentials not configured properly in .env file'
                ], 500);
            }

            // Get LWA access token
            $lwaToken = $this->getAmazonAccessToken($clientId, $clientSecret, $refreshToken);
            
            if (!$lwaToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to obtain Amazon LWA access token'
                ], 500);
            }

            $successCount = 0;
            $errorCount = 0;
            $errors = [];

            foreach ($skus as $sku) {
                // Get product from database
                $product = ProductMaster::where('SKU', $sku)
                    ->orWhere('sku', $sku)
                    ->first();

                if (!$product) {
                    $errorCount++;
                    $errors[] = "SKU {$sku}: Product not found";
                    continue;
                }

                $amazonSuccess = false;
                $shopifySuccess = false;

                // Update Amazon if title150 exists
                if ($product->title150) {
                    try {
                        // Use Listings Items API PATCH method
                        $endpoint = "https://sellingpartnerapi-na.amazon.com";
                        $path = "/listings/2021-08-01/items/{$sellerId}/{$sku}";
                        $url = $endpoint . $path . "?marketplaceIds={$marketplaceId}";

                        $payload = [
                            'productType' => 'PRODUCT',
                            'patches' => [
                                [
                                    'op' => 'replace',
                                    'path' => '/attributes/item_name',
                                    'value' => [
                                        [
                                            'value' => $product->title150,
                                            'language_tag' => 'en_US'
                                        ]
                                    ]
                                ]
                            ]
                        ];

                        $response = \Illuminate\Support\Facades\Http::withHeaders([
                            'x-amz-access-token' => $lwaToken,
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ])
                        ->timeout(30)
                        ->patch($url, $payload);

                        $statusCode = $response->status();

                        if ($statusCode == 200 || $statusCode == 202) {
                            $amazonSuccess = true;
                            
                            // Update sync status
                            $product->amazon_last_sync = now();
                            $product->amazon_sync_status = 'success';
                            $product->amazon_sync_error = null;
                            $product->save();
                        } else {
                            $errorMsg = $response->body();
                            $errors[] = "Amazon - SKU {$sku}: {$statusCode} - " . substr($errorMsg, 0, 100);
                            
                            // Update error status
                            $product->amazon_sync_status = 'failed';
                            $product->amazon_sync_error = substr($errorMsg, 0, 500);
                            $product->save();
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Amazon - SKU {$sku}: " . $e->getMessage();
                        
                        $product->amazon_sync_status = 'failed';
                        $product->amazon_sync_error = $e->getMessage();
                        $product->save();
                    }
                }

                // Update Shopify if title100 exists
                if ($product->title100) {
                    // Add delay before Shopify API call to avoid rate limiting
                    usleep(500000); // 500ms delay
                    
                    try {
                        $shopifySuccess = $this->updateShopifyTitle($sku, $product->title100);
                        if (!$shopifySuccess) {
                            $errors[] = "Shopify - SKU {$sku}: Update failed (check logs)";
                        }
                    } catch (\Exception $e) {
                        $errors[] = "Shopify - SKU {$sku}: " . $e->getMessage();
                        Log::error("Shopify update exception for SKU {$sku}: " . $e->getMessage());
                    }
                }

                // Count success only if at least one platform succeeded
                if ($amazonSuccess || $shopifySuccess) {
                    $successCount++;
                } else {
                    $errorCount++;
                }

                // Rate limiting: 500ms delay between products (2 requests per second)
                usleep(500000);
            }

            $message = count($errors) > 0 ? implode("\n", array_slice($errors, 0, 5)) : '';
            
            return response()->json([
                'success' => true,
                'success_count' => $successCount,
                'error_count' => $errorCount,
                'message' => $message,
                'total' => count($skus)
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating titles to Amazon: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update titles: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getAmazonAccessToken($clientId, $clientSecret, $refreshToken)
    {
        try {
            $response = \Illuminate\Support\Facades\Http::asForm()->post('https://api.amazon.com/auth/o2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            Log::error('Failed to get Amazon access token: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('Error getting Amazon access token: ' . $e->getMessage());
            return null;
        }
    }

    private function updateShopifyTitle($sku, $title)
    {
        try {
            Log::info("Starting Shopify title update for SKU: {$sku}, Title: {$title}");
            
            // Get Shopify credentials from env - try multiple variable names
            $shopifyDomain = env('SHOPIFY_DOMAIN') ?? env('SHOPIFY_STORE_URL') ?? env('SHOPIFY_5CORE_DOMAIN');
            $shopifyToken = env('SHOPIFY_ACCESS_TOKEN') ?? env('SHOPIFY_PASSWORD');
            
            // Clean up domain (remove https://, trailing slashes)
            if ($shopifyDomain) {
                $shopifyDomain = preg_replace('#^https?://#', '', $shopifyDomain);
                $shopifyDomain = rtrim($shopifyDomain, '/');
            }
            
            if (!$shopifyDomain || !$shopifyToken) {
                Log::warning("Shopify credentials not configured. SHOPIFY_DOMAIN: " . ($shopifyDomain ? 'set' : 'missing') . ", SHOPIFY_ACCESS_TOKEN: " . ($shopifyToken ? 'set' : 'missing'));
                return false;
            }

            Log::info("Shopify credentials found. Domain: {$shopifyDomain}");

            // Find Shopify product by SKU - try both uppercase and lowercase
            $shopifySku = ShopifySku::where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first();
            
            if (!$shopifySku || !$shopifySku->variant_id) {
                Log::warning("No Shopify variant found for SKU: {$sku}");
                return false;
            }

            $variantId = $shopifySku->variant_id;
            Log::info("Found Shopify variant ID: {$variantId} for SKU: {$sku}");
            
            // Add delay before API call (3 seconds to safely respect Shopify's 2 calls/second limit)
            sleep(3);
            
            // First, get the product ID from the variant with retry logic
            $variantUrl = "https://{$shopifyDomain}/admin/api/2024-01/variants/{$variantId}.json";
            
            Log::info("Fetching variant from: {$variantUrl}");
            
            $maxRetries = 3;
            $retryDelay = 2; // Start with 2 seconds
            $variantResponse = null;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $variantResponse = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-Shopify-Access-Token' => $shopifyToken,
                ])->timeout(30)->get($variantUrl);
                
                if ($variantResponse->successful()) {
                    break; // Success, exit retry loop
                }
                
                if ($variantResponse->status() == 429 && $attempt < $maxRetries) {
                    Log::warning("Rate limited on attempt {$attempt} for SKU {$sku}, waiting {$retryDelay} seconds...");
                    sleep($retryDelay);
                    $retryDelay *= 2; // Exponential backoff
                    continue;
                }
                
                // Not rate limited or final attempt failed
                break;
            }
            
            if (!$variantResponse->successful()) {
                Log::error("Failed to fetch variant for SKU {$sku} after {$maxRetries} attempts. Status: " . $variantResponse->status() . ", Body: " . $variantResponse->body());
                return false;
            }
            
            $variantData = $variantResponse->json();
            $productId = $variantData['variant']['product_id'] ?? null;
            
            if (!$productId) {
                Log::error("No product ID found in variant response for SKU: {$sku}. Response: " . json_encode($variantData));
                return false;
            }

            Log::info("Found product ID: {$productId} for SKU: {$sku}");

            // Add another delay before product update (3 seconds)
            sleep(3);

            // Now update the product title with retry logic
            $productUrl = "https://{$shopifyDomain}/admin/api/2024-01/products/{$productId}.json";
            
            Log::info("Updating product at: {$productUrl}");

            $maxRetries = 3;
            $retryDelay = 2;
            $response = null;
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'X-Shopify-Access-Token' => $shopifyToken,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->put($productUrl, [
                    'product' => [
                        'id' => $productId,
                        'title' => $title
                    ]
                ]);
                
                if ($response->successful()) {
                    break; // Success, exit retry loop
                }
                
                if ($response->status() == 429 && $attempt < $maxRetries) {
                    Log::warning("Rate limited on product update attempt {$attempt} for SKU {$sku}, waiting {$retryDelay} seconds...");
                    sleep($retryDelay);
                    $retryDelay *= 2; // Exponential backoff
                    continue;
                }
                
                // Not rate limited or final attempt failed
                break;
            }

            if ($response->successful()) {
                Log::info("✓ Successfully updated Shopify title for SKU: {$sku} (Product ID: {$productId})");
                return true;
            } else {
                Log::error("✗ Failed to update Shopify title for SKU {$sku} after {$maxRetries} attempts. Status: " . $response->status() . ", Body: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("✗ Exception updating Shopify title for SKU {$sku}: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return false;
        }
    }

    public function updateTitlesToPlatforms(Request $request)
    {
        try {
            Log::info("=== updateTitlesToPlatforms called ===", $request->all());
            
            $validated = $request->validate([
                'skus' => 'required|array',
                'skus.*' => 'required|string',
                'platforms' => 'required|array',
            ]);

            $skus = $validated['skus'];
            $platforms = $validated['platforms'];

            // Platform to title field mapping
            $platformTitleMap = [
                'amazon' => 'title150',
                'shopify' => 'title100',
                'ebay1' => 'title80',
                'ebay2' => 'title80',
                'ebay3' => 'title80',
                'walmart' => 'title80',
                'temu' => 'title150',
                'doba' => 'title100',
            ];

            $results = [];
            foreach ($platforms as $platform) {
                $results[$platform] = ['success' => 0, 'failed' => 0];
            }

            $errors = [];

            foreach ($skus as $sku) {
                // Get product from database
                $product = ProductMaster::where('SKU', $sku)
                    ->orWhere('sku', $sku)
                    ->first();

                if (!$product) {
                    $errors[] = "SKU {$sku}: Product not found";
                    foreach ($platforms as $platform) {
                        $results[$platform]['failed']++;
                    }
                    continue;
                }

                // Update each selected platform
                foreach ($platforms as $platform) {
                    $titleField = $platformTitleMap[$platform] ?? null;
                    
                    if (!$titleField) {
                        $errors[] = ucfirst($platform) . " - SKU {$sku}: Unknown platform";
                        $results[$platform]['failed']++;
                        continue;
                    }

                    $title = $product->{$titleField};
                    
                    if (!$title) {
                        $errors[] = ucfirst($platform) . " - SKU {$sku}: No title available for {$titleField}";
                        $results[$platform]['failed']++;
                        continue;
                    }

                    // Call platform-specific update method
                    $success = $this->updatePlatformTitle($platform, $sku, $title);
                    
                    if ($success) {
                        $results[$platform]['success']++;
                    } else {
                        $results[$platform]['failed']++;
                        $errors[] = ucfirst($platform) . " - SKU {$sku}: Update failed";
                    }
                    
                    // Rate limiting delay - Shopify needs more time (2 calls per product)
                    if ($platform === 'shopify') {
                        sleep(5); // 5 seconds for Shopify to safely clear rate limit
                    } else {
                        sleep(1); // 1 second for other platforms
                    }
                }
            }

            // Calculate totals
            $totalSuccess = array_sum(array_column($results, 'success'));
            $totalFailed = array_sum(array_column($results, 'failed'));

            $message = count($errors) > 0 ? implode("\n", array_slice($errors, 0, 10)) : '';
            
            return response()->json([
                'success' => true,
                'results' => $results,
                'total_success' => $totalSuccess,
                'total_failed' => $totalFailed,
                'message' => $message,
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating titles to platforms: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update titles: ' . $e->getMessage()
            ], 500);
        }
    }

    private function updatePlatformTitle($platform, $sku, $title)
    {
        try {
            file_put_contents(storage_path('logs/platform_debug.log'), date('Y-m-d H:i:s') . " - Platform: {$platform}, SKU: {$sku}\n", FILE_APPEND);
            Log::info("Updating {$platform} title for SKU: {$sku}");

            switch ($platform) {
                case 'amazon':
                    return $this->updateAmazonTitle($sku, $title);
                
                case 'shopify':
                    return $this->updateShopifyTitle($sku, $title);
                
                case 'ebay1':
                case 'ebay2':
                case 'ebay3':
                    return $this->updateEbayTitle($platform, $sku, $title);
                
                case 'walmart':
                    Log::info("Updating walmart title for SKU: {$sku}");
                    return $this->updateWalmartTitle($sku, $title);
                
                case 'temu':
                    return $this->updateTemuTitle($sku, $title);
                
                case 'doba':
                    return $this->updateDobaTitle($sku, $title);
                
                default:
                    Log::warning("Unknown platform: {$platform}");
                    return false;
            }
        } catch (\Exception $e) {
            $error = $e->getMessage();
            file_put_contents(storage_path('logs/platform_debug.log'), date('Y-m-d H:i:s') . " - ERROR in {$platform}: {$error}\n", FILE_APPEND);
            Log::error("Error updating {$platform} title for SKU {$sku}: " . $error);
            return false;
        }
    }

    private function updateAmazonTitle($sku, $title)
    {
        try {
            // Get credentials from environment
            $clientId = env('SPAPI_CLIENT_ID');
            $clientSecret = env('SPAPI_CLIENT_SECRET');
            $refreshToken = env('SPAPI_REFRESH_TOKEN');
            $sellerId = env('AMAZON_SELLER_ID');
            $marketplaceId = env('SPAPI_MARKETPLACE_ID', 'ATVPDKIKX0DER');

            if (!$clientId || !$clientSecret || !$refreshToken || !$sellerId) {
                Log::warning("Amazon credentials not configured");
                return false;
            }

            // Get access token
            $accessToken = $this->getAmazonAccessToken($clientId, $clientSecret, $refreshToken);
            if (!$accessToken) {
                Log::error("Failed to get Amazon access token");
                return false;
            }

            // Update listing via SP-API
            $url = "https://sellingpartnerapi-na.amazon.com/listings/2021-08-01/items/{$sellerId}/{$sku}?marketplaceIds={$marketplaceId}";

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->patch($url, [
                'productType' => 'PRODUCT',
                'patches' => [
                    [
                        'op' => 'replace',
                        'path' => '/attributes/item_name',
                        'value' => [
                            [
                                'value' => $title,
                                'marketplace_id' => $marketplaceId
                            ]
                        ]
                    ]
                ]
            ]);

            if ($response->successful()) {
                Log::info("✓ Successfully updated Amazon title for SKU: {$sku}");
                return true;
            } else {
                Log::error("✗ Failed to update Amazon title for SKU {$sku}: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("✗ Exception updating Amazon title for SKU {$sku}: " . $e->getMessage());
            return false;
        }
    }

    private function updateEbayTitle($account, $sku, $title)
    {
        try {
            Log::info("Starting eBay {$account} title update for SKU: {$sku}, Title: {$title}");
            
            // Get eBay credentials based on account
            $credentials = $this->getEbayCredentials($account);
            
            if (!$credentials) {
                Log::warning("{$account} credentials not configured");
                return false;
            }

            // Get OAuth access token from refresh token
            $accessToken = $this->getEbayAccessToken($credentials);
            
            if (!$accessToken) {
                Log::warning("Failed to get access token for {$account}");
                return false;
            }

            // Find eBay listing by SKU using existing metrics tables
            $ebayMetricModel = null;
            
            if ($account === 'ebay1') {
                $ebayMetricModel = \App\Models\EbayMetric::class;
            } elseif ($account === 'ebay2') {
                $ebayMetricModel = \App\Models\Ebay2Metric::class;
            } elseif ($account === 'ebay3') {
                $ebayMetricModel = \App\Models\Ebay3Metric::class;
            }
            
            if (!$ebayMetricModel) {
                Log::warning("Unknown eBay account: {$account}");
                return false;
            }
            
            // Get item ID from metrics table
            $ebayListing = $ebayMetricModel::where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first();
                
            if (!$ebayListing || !$ebayListing->item_id) {
                Log::info("No eBay listing found for SKU: {$sku} in {$account}");
                return false;
            }
            
            $itemId = $ebayListing->item_id;
            Log::info("Found eBay item ID: {$itemId} for SKU: {$sku} in {$account}");

            // Add delay to respect eBay rate limits
            sleep(1);

            // eBay Trading API endpoint
            $apiUrl = 'https://api.ebay.com/ws/api.dll';
            
            // Build XML request for ReviseItem
            $xmlRequest = '<?xml version="1.0" encoding="utf-8"?>
<ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
    <RequesterCredentials>
        <eBayAuthToken>' . $accessToken . '</eBayAuthToken>
    </RequesterCredentials>
    <Item>
        <ItemID>' . $itemId . '</ItemID>
        <Title>' . htmlspecialchars($title, ENT_XML1, 'UTF-8') . '</Title>
    </Item>
</ReviseItemRequest>';

            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
                'X-EBAY-API-DEV-NAME' => $credentials['dev_id'],
                'X-EBAY-API-APP-NAME' => $credentials['app_id'],
                'X-EBAY-API-CERT-NAME' => $credentials['cert_id'],
                'X-EBAY-API-CALL-NAME' => 'ReviseItem',
                'X-EBAY-API-SITEID' => '0', // 0 = US
                'Content-Type' => 'text/xml',
            ])
            ->timeout(30)
            ->send('POST', $apiUrl, ['body' => $xmlRequest]);

            if ($response->successful()) {
                $responseBody = $response->body();
                
                // Parse XML response to check for success
                $xml = simplexml_load_string($responseBody);
                $ack = (string)$xml->Ack;
                
                if ($ack === 'Success' || $ack === 'Warning') {
                    Log::info("✓ Successfully updated {$account} title for SKU: {$sku}, Item ID: {$itemId}");
                    return true;
                } else {
                    $errors = [];
                    $errorCodes = [];
                    if (isset($xml->Errors)) {
                        foreach ($xml->Errors as $error) {
                            $errorCode = (string)$error->ErrorCode;
                            $errorMsg = (string)$error->LongMessage;
                            $errors[] = $errorMsg;
                            $errorCodes[] = $errorCode;
                            
                            // Special handling for common errors
                            if (strpos($errorMsg, 'ended listing') !== false) {
                                Log::warning("✗ {$account} SKU {$sku} (Item {$itemId}): Listing has ended and cannot be revised");
                            } elseif (strpos($errorMsg, 'not found') !== false) {
                                Log::warning("✗ {$account} SKU {$sku} (Item {$itemId}): Listing not found");
                            }
                        }
                    }
                    Log::error("✗ eBay API error for {$account} SKU {$sku}: " . implode(', ', $errors));
                    return false;
                }
            } else {
                Log::error("✗ Failed to update {$account} title for SKU {$sku}: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("✗ Exception updating {$account} title for SKU {$sku}: " . $e->getMessage());
            return false;
        }
    }

    private function getEbayCredentials($account)
    {
        // Map account names to env variable prefixes
        $envPrefix = match($account) {
            'ebay1' => 'EBAY',
            'ebay2' => 'EBAY2',
            'ebay3' => 'EBAY_3',
            default => null
        };

        if (!$envPrefix) {
            Log::warning("Invalid eBay account: {$account}");
            return null;
        }

        $appId = env("{$envPrefix}_APP_ID");
        $certId = env("{$envPrefix}_CERT_ID");
        $devId = env("{$envPrefix}_DEV_ID");
        $refreshToken = env("{$envPrefix}_REFRESH_TOKEN");

        Log::info("eBay credentials check for {$account}: APP_ID=" . ($appId ? 'SET' : 'MISSING') . 
                  ", CERT_ID=" . ($certId ? 'SET' : 'MISSING') . 
                  ", DEV_ID=" . ($devId ? 'SET' : 'MISSING') . 
                  ", REFRESH_TOKEN=" . ($refreshToken ? 'SET' : 'MISSING'));

        if (!$appId || !$certId || !$devId || !$refreshToken) {
            Log::warning("Missing credentials for {$account}. Env prefix: {$envPrefix}");
            return null;
        }

        return [
            'app_id' => $appId,
            'cert_id' => $certId,
            'dev_id' => $devId,
            'refresh_token' => trim($refreshToken, '"')
        ];
    }

    private function getEbayAccessToken($credentials)
    {
        try {
            $authString = base64_encode($credentials['app_id'] . ':' . $credentials['cert_id']);
            
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . $authString,
            ])
            ->asForm()
            ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $credentials['refresh_token'],
                'scope' => 'https://api.ebay.com/oauth/api_scope'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            }

            Log::error("Failed to get eBay access token: " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("Exception getting eBay access token: " . $e->getMessage());
            return null;
        }
    }

    private function updateWalmartTitle($sku, $title)
    {
        try {
            Log::info("Starting Walmart title update for SKU: {$sku}, Title: {$title}");
            
            $clientId = env('WALMART_CLIENT_ID');
            $clientSecret = env('WALMART_CLIENT_SECRET');
            $channelType = env('WALMART_CHANNEL_TYPE', '0f3e4dd4-0514-4346-b39d-af0e00ea066d');
            $baseUrl = env('WALMART_API_ENDPOINT', 'https://marketplace.walmartapis.com');

            if (!$clientId || !$clientSecret) {
                Log::warning("Walmart credentials not configured in .env file");
                return false;
            }

            // Get OAuth access token
            $accessToken = $this->getWalmartAccessToken($clientId, $clientSecret);
            
            if (!$accessToken) {
                Log::error("✗ Failed to get Walmart access token for SKU {$sku}");
                return false;
            }

            Log::info("✓ Successfully obtained Walmart access token");

            // Build MP_ITEM feed XML for title update with processMode=UPDATE
            $feedXml = '<?xml version="1.0" encoding="UTF-8"?>
<MPItemFeed xmlns="http://walmart.com/">
    <MPItemFeedHeader>
        <version>1.4</version>
        <requestId>' . uniqid() . '</requestId>
        <requestBatchId>' . uniqid() . '</requestBatchId>
    </MPItemFeedHeader>
    <MPItem>
        <processMode>UPDATE</processMode>
        <Item>
            <sku>' . htmlspecialchars($sku, ENT_XML1) . '</sku>
            <productName>' . htmlspecialchars($title, ENT_XML1) . '</productName>
        </Item>
    </MPItem>
</MPItemFeed>';

            Log::info("Walmart MP_ITEM Feed XML: " . $feedXml);

            sleep(2);
            
            // Submit feed using MP_ITEM feedType for content updates
            $feedsEndpoint = $baseUrl . '/v3/feeds?feedType=MP_ITEM';
            
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders([
                    'WM_SEC.ACCESS_TOKEN' => $accessToken,
                    'WM_QOS.CORRELATION_ID' => uniqid(),
                    'WM_SVC.NAME' => 'Walmart Marketplace',
                    'WM_CONSUMER.CHANNEL.TYPE' => $channelType,
                    'Content-Type' => 'multipart/form-data',
                    'Accept' => 'application/xml',
                ])
                ->attach('file', $feedXml, 'feed.xml')
                ->timeout(60)
                ->post($feedsEndpoint);

            Log::info("Walmart MP_ITEM Feed response status: " . $response->status());
            Log::info("Walmart MP_ITEM Feed response: " . $response->body());

            // Walmart Feeds API returns 202 Accepted for async processing
            if ($response->status() === 202) {
                $responseBody = $response->body();
                
                // Parse feedId from XML response
                preg_match('/<feedId>(.*?)<\/feedId>/', $responseBody, $matches);
                $feedId = $matches[1] ?? null;
                
                if ($feedId) {
                    Log::info("✓ Walmart feed submitted successfully. FeedId: {$feedId}");
                    Log::info("⏳ Feed is processing asynchronously (15-60 min). Poll status: GET /v3/feeds/{$feedId}");
                    
                    // Optionally poll feed status after a delay
                    // You can implement polling logic here or return success
                    return true;
                } else {
                    Log::warning("⚠ Feed submitted (202) but no feedId found in response");
                    return true; // Still consider it successful since it was accepted
                }
            } elseif ($response->successful()) {
                Log::info("✓ Walmart title feed submitted successfully");
                return true;
            } else {
                Log::error("✗ Failed to submit Walmart MP_ITEM feed for SKU {$sku}. Status: {$response->status()}, Error: {$response->body()}");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("✗ Exception updating Walmart title for SKU {$sku}: " . $e->getMessage());
            return false;
        }
    }

    private function getWalmartAccessToken($clientId, $clientSecret)
    {
        try {
            Log::info("Attempting to get Walmart access token...");
            
            $authorization = base64_encode("{$clientId}:{$clientSecret}");

            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => "Basic {$authorization}",
                    'WM_QOS.CORRELATION_ID' => uniqid(),
                    'WM_SVC.NAME' => 'Walmart Marketplace',
                    'Accept' => 'application/json',
                ])
                ->asForm()
                ->post('https://marketplace.walmartapis.com/v3/token', [
                    'grant_type' => 'client_credentials',
                ]);

            Log::info("Walmart token response status: " . $response->status());

            if ($response->successful()) {
                $tokenData = $response->json();
                $accessToken = $tokenData['access_token'] ?? null;
                
                if ($accessToken) {
                    Log::info("✓ Successfully obtained Walmart access token");
                    return $accessToken;
                } else {
                    Log::error("✗ Token response successful but no access_token found: " . json_encode($tokenData));
                    return null;
                }
            }

            $errorBody = $response->json();
            Log::error("✗ Failed to get Walmart access token. Status: {$response->status()}");
            Log::error("Error response: " . json_encode($errorBody));
            
            if (isset($errorBody['error'])) {
                $error = $errorBody['error'];
                $errorDesc = $errorBody['error_description'] ?? 'No description';
                
                if ($error === 'invalid_client') {
                    Log::error("INVALID CREDENTIALS: The WALMART_CLIENT_ID or WALMART_CLIENT_SECRET is incorrect.");
                    Log::error("Please verify credentials at: https://seller.walmart.com > Settings > API Keys");
                } elseif ($error === 'unauthorized_client') {
                    Log::error("UNAUTHORIZED: Client is not authorized for this grant type.");
                } else {
                    Log::error("Error type: {$error}, Description: {$errorDesc}");
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("✗ Exception getting Walmart access token: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return null;
        }
    }

    private function checkWalmartFeedStatus($feedId, $accessToken)
    {
        try {
            $baseUrl = env('WALMART_API_ENDPOINT', 'https://marketplace.walmartapis.com');
            $channelType = env('WALMART_CHANNEL_TYPE', '0f3e4dd4-0514-4346-b39d-af0e00ea066d');
            
            Log::info("Checking Walmart feed status for feedId: {$feedId}");
            
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders([
                    'WM_SEC.ACCESS_TOKEN' => $accessToken,
                    'WM_QOS.CORRELATION_ID' => uniqid(),
                    'WM_SVC.NAME' => 'Walmart Marketplace',
                    'WM_CONSUMER.CHANNEL.TYPE' => $channelType,
                    'Accept' => 'application/xml',
                ])
                ->timeout(30)
                ->get($baseUrl . "/v3/feeds/{$feedId}");
            
            Log::info("Feed status response: " . $response->body());
            
            if ($response->successful()) {
                $body = $response->body();
                
                // Parse feed status from XML
                preg_match('/<feedStatus>(.*?)<\/feedStatus>/', $body, $matches);
                $status = $matches[1] ?? 'UNKNOWN';
                
                Log::info("Feed Status: {$status}");
                
                return [
                    'status' => $status,
                    'response' => $body
                ];
            } else {
                Log::error("Failed to get feed status. Status: {$response->status()}");
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Exception checking Walmart feed status: " . $e->getMessage());
            return null;
        }
    }

    private function updateTemuTitle($sku, $title)
    {
        try {
            Log::info("Starting Temu title update for SKU: {$sku}, Title: {$title}");
            
            $appKey = env('TEMU_APP_KEY');
            $appSecret = env('TEMU_SECRET_KEY');
            $accessToken = env('TEMU_ACCESS_TOKEN');

            if (!$appKey || !$appSecret || !$accessToken) {
                Log::warning("Temu credentials not configured in .env file");
                return false;
            }

            Log::info("✓ Temu credentials found");

            // Check if product exists in your local DB/view
            $temuProduct = \App\Models\TemuDataView::where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first();
            
            if (!$temuProduct) {
                Log::error("SKU {$sku} not found in TemuDataView. Product may not be listed on Temu.");
                return false;
            }
            
            Log::info("Found Temu product for SKU: {$sku}");

            $timestamp = time();

            // Correct method type and fields for local goods update (US sellers)
            $method = "bg.local.goods.update";
            $data = [
                "goodsExternalId" => $sku,      // Your external SKU
                "goodsName" => $title,         // Title field (max 200 chars)
                // Add more fields if needed, e.g., "goodsDesc" => "Updated description"
            ];

            $dataJson = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            // Parameters for signature (must include these exact keys)
            $params = [
                'app_key' => $appKey,
                'access_token' => $accessToken,
                'timestamp' => (string)$timestamp,
                'method' => $method,
                'data' => $dataJson,
            ];

            // Sort keys alphabetically
            ksort($params);

            // Concatenate key + value without separators
            $signString = '';
            foreach ($params as $key => $value) {
                $signString .= $key . $value;
            }

            // Full sign: secret + concatenated + secret
            $signStr = $appSecret . $signString . $appSecret;
            $sign = strtoupper(md5($signStr));

            // Add sign to params for sending
            $params['sign'] = $sign;

            Log::info("Temu API call - Method: {$method}, SKU: {$sku}, Sign: {$sign}");

            sleep(2); // Gentle rate limiting

            // Send as form-data (multipart/form-data) - this is crucial for Temu router
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->timeout(30)
                ->post('https://openapi-b-us.temu.com/openapi/router', $params);

            $status = $response->status();
            $body = $response->body();
            
            Log::info("Temu API response status: {$status}");
            Log::info("Temu API response body: {$body}");

            if ($response->successful()) {
                $responseData = $response->json();

                // Success indicators: errorCode == 0 (or sometimes 1000000) and success == true
                $errorCode = $responseData['errorCode'] ?? null;
                $success = $responseData['success'] ?? false;

                if (($errorCode === 0 || $errorCode === 1000000) && $success) {
                    Log::info("✓ Successfully updated Temu title for SKU: {$sku}");
                    return true;
                } else {
                    $errorMsg = $responseData['errorMsg'] ?? $responseData['message'] ?? 'Unknown error';
                    Log::error("✗ Temu update failed - Code: {$errorCode}, Message: {$errorMsg}");
                    Log::error("Full response: " . json_encode($responseData));
                    return false;
                }
            } else {
                Log::error("✗ HTTP failure updating Temu title for SKU {$sku}. Status: {$status}, Body: {$body}");

                if ($status == 401) {
                    Log::error("Invalid access_token - regenerate in Seller Center > Apps and Services");
                } elseif ($status == 403) {
                    Log::error("Missing permissions - edit app, enable 'Local Product Management', regenerate token");
                } elseif ($status == 429) {
                    Log::error("Rate limited - wait and retry");
                }

                return false;
            }
        } catch (\Exception $e) {
            Log::error("✗ Exception updating Temu title for SKU {$sku}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function updateDobaTitle($sku, $title)
    {
        try {
            Log::info("Starting Doba title update for SKU: {$sku}, Title: {$title}");
            
            $publicKey = env('DOBA_PUBLIC_KEY');
            $privateKey = env('DOBA_PRIVATE_KEY');
            $appKey = env('DOBA_APP_KEY');
            
            if (!$publicKey || !$privateKey || !$appKey) {
                Log::warning("Doba credentials not configured in .env file");
                return false;
            }

            Log::info("✓ Doba credentials found");

            // Try to find Doba product by SKU
            $dobaProduct = \App\Models\DobaDataView::where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first();
            
            // Use doba_product_id if found, otherwise use SKU as fallback
            $dobaProductId = $dobaProduct && isset($dobaProduct->doba_product_id)
                ? $dobaProduct->doba_product_id 
                : $sku;
            
            if ($dobaProduct) {
                Log::info("Found Doba Product ID: {$dobaProductId} for SKU: {$sku}");
            } else {
                Log::info("SKU not found in DobaDataView, using SKU as product identifier: {$dobaProductId}");
            }
            
            // Doba API uses RSA signature authentication
            $timestamp = time();
            $nonce = uniqid();
            
            // Format private key for OpenSSL (convert from string to PEM format if needed)
            $privateKeyFormatted = $privateKey;
            if (strpos($privateKey, '-----BEGIN') === false) {
                // If key doesn't have PEM headers, add them
                $privateKeyFormatted = "-----BEGIN PRIVATE KEY-----\n" . 
                    chunk_split($privateKey, 64, "\n") . 
                    "-----END PRIVATE KEY-----";
            }
            
            // Get the key resource
            $keyResource = openssl_pkey_get_private($privateKeyFormatted);
            
            if (!$keyResource) {
                Log::error("Failed to load Doba private key. OpenSSL error: " . openssl_error_string());
                return false;
            }
            
            // Build request data
            $requestData = [
                'product_id' => $dobaProductId,
                'title' => $title,
                'app_key' => $appKey,
                'timestamp' => $timestamp,
                'nonce' => $nonce,
            ];
            
            // Sort parameters for signature
            ksort($requestData);
            $signString = http_build_query($requestData);
            
            // Generate RSA signature
            $signature = '';
            $signResult = openssl_sign($signString, $signature, $keyResource, OPENSSL_ALGO_SHA256);
            
            if (!$signResult) {
                Log::error("Failed to sign Doba request. OpenSSL error: " . openssl_error_string());
                openssl_free_key($keyResource);
                return false;
            }
            
            $signBase64 = base64_encode($signature);
            openssl_free_key($keyResource);
            
            Log::info("Doba API call - Product ID: {$dobaProductId}");
            
            sleep(2); // Rate limiting
            
            // Doba API endpoint
            $apiUrl = 'https://api.doba.com/v1/products/update';
            
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Doba-App-Key' => $appKey,
                    'X-Doba-Timestamp' => $timestamp,
                    'X-Doba-Nonce' => $nonce,
                    'X-Doba-Signature' => $signBase64,
                ])
                ->timeout(30)
                ->post($apiUrl, [
                    'product_id' => $dobaProductId,
                    'title' => $title,
                ]);

            $status = $response->status();
            $body = $response->body();
            
            Log::info("Doba API response status: " . $status);
            Log::info("Doba API response body: " . $body);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData['success']) && $responseData['success'] === true) {
                    Log::info("✓ Successfully updated Doba title for SKU: {$sku}");
                    return true;
                } elseif (isset($responseData['status']) && $responseData['status'] === 'success') {
                    Log::info("✓ Successfully updated Doba title for SKU: {$sku}");
                    return true;
                } else {
                    $errorMsg = $responseData['message'] ?? $responseData['error'] ?? 'Unknown error';
                    Log::error("✗ Doba API returned error: {$errorMsg}");
                    Log::error("Full response: " . json_encode($responseData));
                    return false;
                }
            } else {
                Log::error("✗ Failed to update Doba title for SKU {$sku}. Status: {$status}, Error: {$body}");
                
                if ($status == 401) {
                    Log::error("Authentication failed. Check Doba credentials in .env");
                } elseif ($status == 403) {
                    Log::error("Permission denied. Verify your Doba account has product update permissions");
                } elseif ($status == 404) {
                    Log::error("Product not found or API endpoint incorrect");
                    Log::error("Current endpoint: {$apiUrl}");
                }
                
                return false;
            }
        } catch (\Exception $e) {
            Log::error("✗ Exception updating Doba title for SKU {$sku}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return false;
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
