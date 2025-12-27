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
use PhpOffice\PhpSpreadsheet\IOFactory;

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
            'parent' => 'nullable|string',
            'sku' => 'required|string',
            'Values' => 'nullable',
            'unit' => 'required|string',
            'image' => 'nullable|file|image|max:5120', // 5MB max
        ]);

        // Skip validation for PARENT SKUs
        $sku = $validated['sku'];
        $isParentSku = stripos($sku, 'PARENT') !== false;

        // Validate Values JSON (optional field)
        $values = [];
        if (isset($validated['Values']) && $validated['Values'] !== null) {
            $values = is_array($validated['Values']) ? $validated['Values'] : json_decode($validated['Values'], true);
            
            if (!is_array($values)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid Values data format'
                ], 422);
            }
        }

        // Skip required field validation for PARENT SKUs
        // Only unit and sku are required, all other fields are optional
        if (!$isParentSku) {
            // Validate numeric fields only if they are provided (optional validation)
            $numericFields = ['label_qty', 'cp', 'wt_act', 'wt_decl', 'w', 'l', 'h'];
            $invalidNumeric = [];
            
            foreach ($numericFields as $field) {
                if (isset($values[$field]) && $values[$field] !== '' && $values[$field] !== null) {
                    // Normalize value for validation: trim whitespace and remove commas if it's a string
                    $valueToCheck = $values[$field];
                    if (is_string($valueToCheck)) {
                        $valueToCheck = trim(str_replace(',', '', $valueToCheck));
                    }
                    
                    // Check if value is numeric and non-negative (>= 0)
                    if (!is_numeric($valueToCheck) || floatval($valueToCheck) < 0) {
                        $invalidNumeric[] = $field;
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
            
            // Normalize numeric fields for storage (remove commas, trim whitespace)
            foreach ($numericFields as $field) {
                if (isset($values[$field]) && $values[$field] !== '' && $values[$field] !== null && is_string($values[$field])) {
                    $values[$field] = trim(str_replace(',', '', $values[$field]));
                }
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

    public function updateField(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        // Validate based on whether it's an image upload or regular field
        $isImageField = $request->hasFile('image');
        
        if ($isImageField) {
            $validated = $request->validate([
                'sku' => 'required|string',
                'field' => 'required|string',
                'image' => 'required|file|image|max:5120', // 5MB max
            ]);
        } else {
            $validated = $request->validate([
                'sku' => 'required|string',
                'field' => 'required|string',
                'value' => 'required'
            ]);
        }

        try {
            $product = ProductMaster::where('sku', $validated['sku'])->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Get current Values
            $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
            if (!is_array($values)) {
                $values = [];
            }

            $field = $validated['field'];
            $value = null;

            // Handle image upload
            if ($isImageField && $field === 'image_path') {
                // Delete old image if exists
                $oldImagePath = !empty($values['image_path']) ? public_path($values['image_path']) : null;
                
                // Store new image
                $image = $request->file('image');
                $imagePath = $image->store('product_images', 'public');
                $value = 'storage/' . $imagePath;
                
                // Delete old image after successful upload
                if ($oldImagePath && file_exists($oldImagePath)) {
                    @unlink($oldImagePath);
                }
            } else {
                // Handle regular field updates
                $value = $validated['value'];

                // Handle numeric fields
                $numericFields = ['lp', 'cp', 'frght', 'wt_act', 'wt_decl', 'l', 'w', 'h', 'cbm', 'upc', 'label_qty', 'moq'];
                if (in_array($field, $numericFields)) {
                    $value = is_numeric($value) ? (float)$value : null;
                }
            }

            // Update the field in Values array
            $values[$field] = $value;

            // Save updated Values
            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Field updated successfully',
                'data' => [
                    'sku' => $product->sku,
                    'field' => $field,
                    'value' => $value
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating field: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating field: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update verified data status for a product
     */
    public function updateVerified(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        $validated = $request->validate([
            'sku' => 'required|string',
            'verified_data' => 'required|integer|in:0,1'
        ]);

        try {
            $product = ProductMaster::where('sku', $validated['sku'])->first();

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found'
                ], 404);
            }

            // Get current Values
            $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
            if (!is_array($values)) {
                $values = [];
            }

            // Update verified_data in Values array
            $values['verified_data'] = $validated['verified_data'];

            // Save updated Values
            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Verified status updated successfully',
                'data' => [
                    'sku' => $product->sku,
                    'verified_data' => $validated['verified_data']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating verified data: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating verified status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a value is considered missing
     */
    private function isDataMissing($value, $isNumeric = false, $field = null)
    {
        if ($value === null || $value === '') {
            return true;
        }
        
        $strValue = trim((string)$value);
        
        // Empty string or dash means missing
        if ($strValue === '' || $strValue === '-' || $strValue === 'null' || $strValue === 'undefined') {
            return true;
        }
        
        // For numeric fields
        if ($isNumeric) {
            $numValue = is_numeric($strValue) ? (float)$strValue : null;
            if ($numValue === null || is_nan($numValue)) {
                return true;
            }
            // For dimensions and weights, treat 0 as missing
            if (in_array($field, ['l', 'w', 'h', 'wt_act', 'wt_decl', 'label_qty', 'moq']) && ($numValue == 0 || $numValue == '0')) {
                return true;
            }
        }
        
        return false;
    }

    public function import(Request $request)
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

            // Expected column mappings (Excel column names to database field names)
            $columnMap = [
                'sku' => 'sku',
                'parent' => 'parent',
                'cp' => 'cp',
                'lp' => 'lp',
                'frght' => 'frght',
                'ship' => 'ship',
                'temu_ship' => 'temu_ship',
                'moq' => 'moq',
                'ebay2_ship' => 'ebay2_ship',
                'label_qty' => 'label_qty',
                'wt_act' => 'wt_act',
                'wt_decl' => 'wt_decl',
                'l' => 'l',
                'w' => 'w',
                'h' => 'h',
                'cbm' => 'cbm',
                'image' => 'image_path',
                'image_path' => 'image_path',
                'unit' => 'unit',
                'status' => 'status',
                'upc' => 'upc',
                'initial_quantity' => 'initial_quantity',
                'shopify_inv' => 'shopify_inv',
                'shopify_quantity' => 'shopify_quantity'
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
                
                // Find or create product by SKU
                $product = ProductMaster::where('sku', $sku)->first();
                
                if (!$product) {
                    $errors[] = "Row {$rowNumber}: SKU '{$sku}' not found in database";
                    continue;
                }

                // Get existing Values - preserve all existing data
                $values = is_array($product->Values) ? $product->Values : json_decode($product->Values, true);
                if (!is_array($values)) {
                    $values = [];
                }

                // Update parent only if provided, not empty, AND currently missing
                if (isset($columnIndices['parent']) && isset($row[$columnIndices['parent']])) {
                    $parentValue = trim($row[$columnIndices['parent']]);
                    if ($parentValue !== '') {
                        // Only update if parent is currently missing
                        $currentParent = $product->parent ?? null;
                        if ($this->isDataMissing($currentParent, false)) {
                            $product->parent = $parentValue;
                        }
                        // If parent already has a value, don't update it
                    }
                    // If empty, keep original value (don't update)
                }

                // Process each column - only update fields that are currently MISSING in database
                foreach ($columnIndices as $field => $colIndex) {
                    if ($field === 'sku' || $field === 'parent') continue; // Skip SKU and parent (handled separately)
                    
                    // Check if the cell has a value (not empty, not null, not just whitespace)
                    $cellValue = isset($row[$colIndex]) ? $row[$colIndex] : null;
                    
                    // Skip if cell is empty, null, or only whitespace
                    if ($cellValue === null || $cellValue === '' || trim($cellValue) === '') {
                        continue; // Don't update this field - keep existing value
                    }
                    
                    $value = trim($cellValue);
                    
                    // Skip if value is still empty after trim
                    if ($value === '') {
                        continue; // Don't update this field - keep existing value
                    }
                    
                    // Check if this field is currently MISSING in the database
                    $isCurrentlyMissing = false;
                    $currentValue = null;
                    $isNumeric = in_array($field, ['cp', 'lp', 'frght', 'ship', 'temu_ship', 'moq', 'ebay2_ship', 'label_qty', 'wt_act', 'wt_decl', 'l', 'w', 'h', 'cbm', 'upc', 'initial_quantity', 'shopify_inv', 'shopify_quantity']);
                    
                    // Get current value from database
                    if (in_array($field, ['cp', 'lp', 'frght', 'ship', 'temu_ship', 'moq', 'ebay2_ship', 'label_qty', 'wt_act', 'wt_decl', 'l', 'w', 'h', 'cbm', 'upc', 'initial_quantity', 'image_path', 'status', 'unit'])) {
                        // All these fields are stored in Values JSON
                        $currentValue = $values[$field] ?? null;
                    } elseif ($field === 'shopify_inv') {
                        $currentValue = $product->shopify_inv;
                    } elseif ($field === 'shopify_quantity') {
                        $currentValue = $product->shopify_quantity;
                    }
                    
                    // Check if current value is missing
                    $isCurrentlyMissing = $this->isDataMissing($currentValue, $isNumeric, $field);
                    
                    // Only update if the field is currently MISSING in the database
                    if (!$isCurrentlyMissing) {
                        continue; // Field already has a value - don't update it
                    }
                    
                    // Convert to float for numeric fields
                    if ($isNumeric) {
                        // Only update if it's a valid number
                        if (is_numeric($value)) {
                            $value = (float)$value;
                        } else {
                            // Invalid numeric value - skip this field
                            continue;
                        }
                    }
                    
                    // Only update fields that are currently missing and have values in Excel
                    // Store in Values array for most fields (including status and unit)
                    if (in_array($field, ['cp', 'lp', 'frght', 'ship', 'temu_ship', 'moq', 'ebay2_ship', 'label_qty', 'wt_act', 'wt_decl', 'l', 'w', 'h', 'cbm', 'upc', 'initial_quantity', 'image_path', 'status', 'unit'])) {
                        $values[$field] = $value;
                    } 
                    // Store directly on product for shopify fields
                    elseif ($field === 'shopify_inv') {
                        $product->shopify_inv = $value;
                    } elseif ($field === 'shopify_quantity') {
                        $product->shopify_quantity = $value;
                    }
                }

                // Save the updated Values
                $product->Values = $values;
                $product->save();
                $updated++;
                $imported++;
            }

            $message = "Successfully imported {$imported} records. Only missing data fields were updated - existing values were preserved.";
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
            Log::error('Product Master Import Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
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

        DB::beginTransaction();
        try {
            // Get the current products
            $fromProduct = ProductMaster::where('sku', $request->from_sku)->first();
            $toProduct = ProductMaster::where('sku', $request->to_sku)->first();

            if (!$fromProduct || !$toProduct) {
                return response()->json([
                    'success' => false,
                    'message' => 'One or both SKUs not found',
                ], 404);
            }

            $fromGroupId = $fromProduct->group_id;
            $toGroupId = $toProduct->group_id;

            // Only assign group_id to SKUs that don't have one (don't overwrite existing)
            $updatedCount = 0;
            
            if (!$fromGroupId) {
                $fromProduct->group_id = $request->group_id;
                $fromProduct->save();
                $updatedCount++;
            }
            
            if (!$toGroupId) {
                $toProduct->group_id = $request->group_id;
                $toProduct->save();
                $updatedCount++;
            }

            DB::commit();

            $message = $updatedCount > 0 
                ? "Products linked successfully. {$updatedCount} SKU(s) assigned to group {$request->group_id}."
                : "Both SKUs already have group IDs assigned. No changes made.";

            return response()->json([
                'success' => true,
                'message' => $message,
                'group_id' => $request->group_id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Link products failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to link products: ' . $e->getMessage(),
            ], 500);
        }
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

    public function linkedProductDelink(Request $request)
    {
        $request->validate([
            'group_id' => 'required|numeric',
        ]);

        try {
            $count = ProductMaster::where('group_id', $request->group_id)->count();
            
            if ($count === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No products found with this group ID.',
                ], 404);
            }

            ProductMaster::where('group_id', $request->group_id)->update(['group_id' => null]);

            return response()->json([
                'success' => true,
                'message' => "Successfully delinked {$count} products from group {$request->group_id}.",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('Delink products failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delink products: ' . $e->getMessage(),
            ], 500);
        }
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
                'shein' => 'title150',
                'wayfair' => 'title150',
                'reverb' => 'title150',
                'faire' => 'title150',
                'aliexpress' => 'title150',
                'tiktok' => 'title150',
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
                
                case 'shein':
                    return $this->updateSheinTitle($sku, $title);
                
                case 'wayfair':
                    return $this->updateWayfairTitle($sku, $title);
                
                case 'reverb':
                    return $this->updateReverbTitle($sku, $title);
                
                case 'faire':
                    return $this->updateFaireTitle($sku, $title);
                
                case 'aliexpress':
                    return $this->updateAliexpressTitle($sku, $title);
                
                case 'tiktok':
                    return $this->updateTiktokTitle($sku, $title);
                
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

    private function updateSheinTitle($sku, $title)
    {
        try {
            Log::info("Starting Shein title update for SKU: {$sku}, Title: {$title}");
            
            // Try to get credentials - check both possible variable names
            // First try OPEN_KEY_ID and SECRET_KEY (for signature generation)
            // If not found, try APP_ID and APP_SECRET/APP_S
            $openKeyId = env('SHEIN_OPEN_KEY_ID') ?: env('SHEIN_APP_ID');
            $secretKey = env('SHEIN_SECRET_KEY') ?: env('SHEIN_APP_SECRET') ?: env('SHEIN_APP_S');

            if (!$openKeyId || !$secretKey) {
                $missing = [];
                if (!$openKeyId) {
                    $missing[] = 'SHEIN_OPEN_KEY_ID or SHEIN_APP_ID';
                }
                if (!$secretKey) {
                    $missing[] = 'SHEIN_SECRET_KEY or SHEIN_APP_SECRET or SHEIN_APP_S';
                }
                
                $errorMsg = "Shein credentials not configured. Missing: " . implode(', ', $missing);
                Log::error($errorMsg);
                Log::error("Please add the following to your .env file:");
                Log::error("SHEIN_APP_ID=your_app_id");
                Log::error("SHEIN_APP_SECRET=your_app_secret");
                Log::error("OR use:");
                Log::error("SHEIN_OPEN_KEY_ID=your_open_key_id");
                Log::error("SHEIN_SECRET_KEY=your_secret_key");
                
                // Also log to platform_debug.log for easier debugging
                file_put_contents(storage_path('logs/platform_debug.log'), 
                    date('Y-m-d H:i:s') . " - SHEIN ERROR: {$errorMsg}\n", FILE_APPEND);
                
                return false;
            }

            Log::info("✓ Shein credentials found - Using: " . (env('SHEIN_OPEN_KEY_ID') ? 'SHEIN_OPEN_KEY_ID' : 'SHEIN_APP_ID'));

            // Check if product exists in your local DB/view
            $sheinProduct = \App\Models\SheinDataView::where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first();
            
            if (!$sheinProduct) {
                Log::error("SKU {$sku} not found in SheinDataView. Product may not be listed on Shein.");
                return false;
            }
            
            Log::info("Found Shein product for SKU: {$sku}");

            // Generate signature for the update endpoint
            $endpoint = "/open-api/openapi-business-backend/product/update";
            $timestamp = round(microtime(true) * 1000);
            $random = \Illuminate\Support\Str::random(5);
            
            // IMPORTANT: Shein API signature requires SHEIN_OPEN_KEY_ID and SHEIN_SECRET_KEY
            // These are DIFFERENT from SHEIN_APP_ID and SHEIN_APP_SECRET
            // Check if we have the correct credentials for signature
            $signatureOpenKeyId = env('SHEIN_OPEN_KEY_ID');
            $signatureSecretKey = env('SHEIN_SECRET_KEY');
            
            // If we don't have OPEN_KEY_ID, try using APP_ID (they might be the same value)
            // But this will likely fail - user needs to add SHEIN_OPEN_KEY_ID to .env
            if (!$signatureOpenKeyId) {
                $signatureOpenKeyId = $openKeyId; // Use APP_ID as fallback
                Log::warning("⚠️ SHEIN_OPEN_KEY_ID not found, using SHEIN_APP_ID for signature.");
                Log::warning("⚠️ This will likely cause signature errors. Please add SHEIN_OPEN_KEY_ID to .env file.");
            }
            
            if (!$signatureSecretKey) {
                $signatureSecretKey = $secretKey; // Use APP_SECRET as fallback
                Log::warning("⚠️ SHEIN_SECRET_KEY not found, using SHEIN_APP_SECRET for signature.");
                Log::warning("⚠️ This will likely cause signature errors. Please add SHEIN_SECRET_KEY to .env file.");
            }
            
            // Generate signature using the credentials
            // Signature format: randomKey + base64(hmac_sha256(openKeyId & timestamp & path, secretKey + randomKey))
            // Note: The path should be the endpoint path without query parameters
            $value = $signatureOpenKeyId . "&" . $timestamp . "&" . $endpoint;
            $key = $signatureSecretKey . $random;
            $hmacResult = hash_hmac('sha256', $value, $key, false); // false means return hexadecimal
            $base64Signature = base64_encode($hmacResult);
            $signature = $random . $base64Signature;
            
            Log::info("Generated Shein signature - OpenKeyId: " . substr($signatureOpenKeyId, 0, 10) . "... (Using: " . (env('SHEIN_OPEN_KEY_ID') ? 'OPEN_KEY_ID' : 'APP_ID fallback') . ")");
            
            $baseUrl = env('SHEIN_BASE_URL', 'https://openapi.sheincorp.com');
            $url = $baseUrl . $endpoint;

            // Log all available fields from SheinDataView for debugging
            $sheinProductArray = $sheinProduct->toArray();
            Log::info("SheinDataView fields for SKU {$sku}: " . json_encode($sheinProductArray));
            
            // Check if value field contains product data
            $valueData = $sheinProduct->value ?? [];
            if (is_string($valueData)) {
                $valueData = json_decode($valueData, true) ?? [];
            }
            Log::info("SheinDataView value data: " . json_encode($valueData));
            
            // Get product identifier - Shein uses SKU code (sellerSku) for updates
            // Try multiple possible field names
            $skuCode = $sheinProduct->sku_code 
                ?? $sheinProduct->seller_sku 
                ?? $sheinProduct->sellerSku 
                ?? $valueData['skuCode'] 
                ?? $valueData['sellerSku'] 
                ?? $valueData['sku']
                ?? $sku;
            
            $spuCode = $sheinProduct->spu_code 
                ?? $sheinProduct->spu_id 
                ?? $sheinProduct->spuCode 
                ?? $valueData['spuCode'] 
                ?? $valueData['spu_id'] 
                ?? $valueData['spu']
                ?? null;
            
            Log::info("Using SKU Code: {$skuCode}, SPU Code: " . ($spuCode ?? 'null'));

            // Shein API payload structure - try different formats
            // Format 1: Using skuCode (most common)
            $payload = [
                "skuCode" => $skuCode,
                "productName" => $title,
            ];
            
            // If we have SPU code, also include it (some endpoints require both)
            if ($spuCode) {
                $payload["spuCode"] = $spuCode;
            }

            Log::info("Shein API call - Endpoint: {$endpoint}, SKU: {$sku}, SKU Code: {$skuCode}");
            Log::info("Shein API payload: " . json_encode($payload));

            sleep(2); // Gentle rate limiting

            // Use the signature OpenKeyId in the header (must match what was used for signature)
            $headerOpenKeyId = $signatureOpenKeyId ?? $openKeyId;
            
            Log::info("Sending request with headers - OpenKeyId: " . substr($headerOpenKeyId, 0, 10) . "...");
            
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders([
                    "Language" => "en-us",
                    "x-lt-openKeyId" => $headerOpenKeyId,
                    "x-lt-timestamp" => $timestamp,
                    "x-lt-signature" => $signature,
                    "Content-Type" => "application/json",
                ])
                ->timeout(30)
                ->post($url, $payload);

            $status = $response->status();
            $body = $response->body();
            
            Log::info("Shein API response status: {$status}");
            Log::info("Shein API response body: {$body}");

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info("Shein API response data: " . json_encode($responseData));

                // Check for success indicators in Shein API response
                // Shein API typically returns: { "code": 0, "message": "success", "data": {...} }
                $code = $responseData['code'] ?? $responseData['errorCode'] ?? $responseData['status'] ?? null;
                $message = $responseData['message'] ?? $responseData['errorMsg'] ?? $responseData['msg'] ?? '';
                
                // Check if there's an info object with status
                if (isset($responseData['info'])) {
                    $infoCode = $responseData['info']['code'] ?? null;
                    $infoMsg = $responseData['info']['message'] ?? '';
                    if ($infoCode !== null) {
                        $code = $infoCode;
                        $message = $infoMsg;
                    }
                }

                // Success codes: 0, 200, or success status
                if ($code === 0 || $code === 200 || $code === '0' || 
                    (isset($responseData['success']) && $responseData['success']) ||
                    (isset($responseData['info']['success']) && $responseData['info']['success'])) {
                    Log::info("✓ Successfully updated Shein title for SKU: {$sku}");
                    return true;
                } else {
                    Log::error("✗ Shein update failed - Code: {$code}, Message: {$message}");
                    Log::error("Full response: " . json_encode($responseData, JSON_PRETTY_PRINT));
                    return false;
                }
            } else {
                Log::error("✗ HTTP failure updating Shein title for SKU {$sku}. Status: {$status}, Body: {$body}");

                if ($status == 401) {
                    $errorMsg = "Authentication failed (401). This usually means:";
                    $errorMsg .= "\n1. Signature verification failed - The signature was generated incorrectly";
                    $errorMsg .= "\n2. Wrong credentials - SHEIN_OPEN_KEY_ID and SHEIN_SECRET_KEY are required for API calls";
                    $errorMsg .= "\n3. SHEIN_APP_ID and SHEIN_APP_SECRET are DIFFERENT from SHEIN_OPEN_KEY_ID and SHEIN_SECRET_KEY";
                    $errorMsg .= "\n\nPlease check:";
                    $errorMsg .= "\n- Do you have SHEIN_OPEN_KEY_ID in .env? (Required for signature)";
                    $errorMsg .= "\n- Do you have SHEIN_SECRET_KEY in .env? (Required for signature)";
                    $errorMsg .= "\n- These are different from SHEIN_APP_ID and SHEIN_APP_SECRET";
                    Log::error($errorMsg);
                } elseif ($status == 403) {
                    Log::error("Missing permissions - check API permissions in Shein Seller Center");
                } elseif ($status == 429) {
                    Log::error("Rate limited - wait and retry");
                }

                return false;
            }
        } catch (\Exception $e) {
            Log::error("✗ Exception updating Shein title for SKU {$sku}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function updateWayfairTitle($sku, $title)
    {
        try {
            Log::info("Starting Wayfair title update for SKU: {$sku}, Title: {$title}");
            
            // Get Wayfair credentials from env (similar to Amazon approach)
            $clientId = env('WAYFAIR_CLIENT_ID');
            $clientSecret = env('WAYFAIR_CLIENT_SECRET');
            
            if (!$clientId || !$clientSecret) {
                Log::warning("Wayfair credentials not configured");
                return false;
            }
            
            // Authenticate and get access token (similar to Amazon)
            $authResponse = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->asForm()
                ->post('https://sso.auth.wayfair.com/oauth/token', [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);
            
            if (!$authResponse->successful()) {
                Log::error("Failed to authenticate with Wayfair API. Status: " . $authResponse->status());
                return false;
            }
            
            $accessToken = $authResponse->json('access_token');
            if (!$accessToken) {
                Log::error("Failed to get Wayfair access token");
                return false;
            }
            
            // Get supplier ID from env (if available)
            $supplierId = env('WAYFAIR_SUPPLIER_ID');
            
            // Use GraphQL mutation to update product name (Wayfair's direct API method)
            $graphqlMutation = <<<'GRAPHQL'
mutation UpdateProductName($inventory: [inventoryInput!]!) {
  inventory {
    save(
      inventory: $inventory,
      feed_kind: DIFFERENTIAL
    ) {
      handle
      submittedAt
      errors {
        key
        message
      }
    }
  }
}
GRAPHQL;
            
            // Build inventory input
            $inventoryInput = [
                [
                    'supplierPartNumber' => $sku,
                    'productNameAndOptions' => $title,
                ]
            ];
            
            // Add supplierId if available
            if ($supplierId) {
                $inventoryInput[0]['supplierId'] = $supplierId;
            }
            
            // Make GraphQL request (similar to Amazon's direct PATCH)
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post('https://api.wayfair.com/v1/graphql', [
                    'query' => $graphqlMutation,
                    'variables' => [
                        'inventory' => $inventoryInput
                    ],
                ]);
            
            // Check response (similar to Amazon's success check)
            if ($response->successful()) {
                $responseData = $response->json();
                
                // Check for GraphQL errors
                if (isset($responseData['errors']) && !empty($responseData['errors'])) {
                    $errorMsg = $responseData['errors'][0]['message'] ?? 'Unknown error';
                    Log::error("✗ Failed to update Wayfair title for SKU {$sku}: " . $errorMsg);
                    
                    // Provide helpful message for verification errors
                    if (strpos($errorMsg, 'not been verified') !== false || strpos($errorMsg, 'verification') !== false) {
                        Log::error("⚠️ Wayfair API Verification Required. Contact Wayfair support to enable product update permissions.");
                    }
                    
                    return false;
                }
                
                // Check inventory save result
                $inventoryResult = $responseData['data']['inventory']['save'] ?? null;
                
                if ($inventoryResult) {
                    // Check for errors in the save result
                    $saveErrors = $inventoryResult['errors'] ?? [];
                    if (!empty($saveErrors)) {
                        $errorMsg = $saveErrors[0]['message'] ?? 'Unknown error';
                        Log::error("✗ Failed to update Wayfair title for SKU {$sku}: " . $errorMsg);
                        return false;
                    }
                    
                    // Success - similar to Amazon's success check
                    if (isset($inventoryResult['handle']) || isset($inventoryResult['submittedAt'])) {
                        Log::info("✓ Successfully updated Wayfair title for SKU: {$sku}");
                        return true;
                    }
                }
                
                Log::error("✗ Failed to update Wayfair title for SKU {$sku}: Unexpected response structure");
                return false;
            } else {
                Log::error("✗ Failed to update Wayfair title for SKU {$sku}: " . $response->body());
                return false;
            }
        } catch (\Exception $e) {
            Log::error("✗ Exception updating Wayfair title for SKU {$sku}: " . $e->getMessage());
            return false;
        }
    }

    private function updateReverbTitle($sku, $title)
    {
        try {
            Log::info("Starting Reverb title update for SKU: {$sku}, Title: {$title}");
            
            // Get Reverb API token from env or config
            $reverbToken = env('REVERB_TOKEN') ?? config('services.reverb.token');
            
            if (!$reverbToken) {
                Log::warning("Reverb token not configured in .env file");
                Log::warning("Required: REVERB_TOKEN or set in config/services.php");
                return false;
            }
            
            Log::info("✓ Reverb token found");
            
            // Step 1: Get listing by SKU to find the listing ID
            $listingsUrl = 'https://api.reverb.com/api/my/listings';
            $listingsResponse = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $reverbToken,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                    'Content-Type' => 'application/hal+json',
                ])
                ->timeout(30)
                ->get($listingsUrl, [
                    'sku' => $sku,
                    'state' => 'all', // Get all states (active, draft, etc.)
                ]);
            
            if (!$listingsResponse->successful()) {
                Log::error("Failed to fetch Reverb listings for SKU {$sku}. Status: " . $listingsResponse->status() . ", Body: " . $listingsResponse->body());
                return false;
            }
            
            $listingsData = $listingsResponse->json();
            $listings = $listingsData['listings'] ?? [];
            
            if (empty($listings)) {
                Log::warning("No Reverb listing found for SKU: {$sku}");
                return false;
            }
            
            // Find the listing with matching SKU (in case there are multiple)
            $listing = null;
            foreach ($listings as $item) {
                if (isset($item['sku']) && strtoupper(trim($item['sku'])) === strtoupper(trim($sku))) {
                    $listing = $item;
                    break;
                }
            }
            
            if (!$listing) {
                Log::warning("No matching Reverb listing found for SKU: {$sku}");
                return false;
            }
            
            $listingId = $listing['id'] ?? null;
            
            if (!$listingId) {
                Log::error("Reverb listing found but no ID available for SKU: {$sku}");
                return false;
            }
            
            Log::info("Found Reverb listing ID: {$listingId} for SKU: {$sku}");
            
            // Step 2: Update the listing title using PUT request
            $updateUrl = "https://api.reverb.com/api/listings/{$listingId}";
            
            // Rate limiting delay
            sleep(1);
            
            $updateResponse = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $reverbToken,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                    'Content-Type' => 'application/hal+json',
                ])
                ->timeout(30)
                ->put($updateUrl, [
                    'title' => $title,
                ]);
            
            $status = $updateResponse->status();
            $body = $updateResponse->body();
            
            Log::info("Reverb API update response status: {$status}");
            Log::info("Reverb API update response body: {$body}");
            
            if ($updateResponse->successful()) {
                $responseData = $updateResponse->json();
                
                // Reverb API returns listing data nested under 'listing' key
                $listing = $responseData['listing'] ?? $responseData;
                
                // Check if title was updated successfully
                if (isset($listing['title'])) {
                    // Verify the title matches (or at least we got a listing back)
                    if ($listing['title'] === $title || isset($listing['id'])) {
                        Log::info("✓ Successfully updated Reverb title for SKU: {$sku}, Listing ID: {$listingId}");
                        Log::info("Updated title: " . $listing['title']);
                        return true;
                    }
                } elseif (isset($responseData['id'])) {
                    // If we got a listing object back directly, assume success
                    Log::info("✓ Successfully updated Reverb title for SKU: {$sku}, Listing ID: {$listingId}");
                    return true;
                } elseif (isset($responseData['message'])) {
                    // Sometimes Reverb returns a message indicating success
                    // Check if it's a success message (not an error)
                    $message = $responseData['message'] ?? '';
                    if (stripos($message, 'sold out') !== false || stripos($message, 'updated') !== false || stripos($message, 'success') !== false) {
                        // Even if message says "sold out", if we got 200 and listing data, it's a success
                        if (isset($responseData['listing']['id'])) {
                            Log::info("✓ Successfully updated Reverb title for SKU: {$sku}, Listing ID: {$listingId} (Message: {$message})");
                            return true;
                        }
                    }
                }
                
                Log::warning("Reverb update response structure unexpected: " . json_encode($responseData));
                return false;
            } else {
                Log::error("✗ HTTP failure updating Reverb title for SKU {$sku}. Status: {$status}, Body: {$body}");
                
                if ($status == 401) {
                    Log::error("Authentication failed (401). Check REVERB_TOKEN in .env file");
                } elseif ($status == 403) {
                    Log::error("Permission denied (403). Check API permissions in Reverb account");
                } elseif ($status == 404) {
                    Log::error("Listing not found (404). Listing ID: {$listingId}");
                } elseif ($status == 429) {
                    Log::error("Rate limited (429) - wait and retry");
                }
                
                return false;
            }
        } catch (\Exception $e) {
            Log::error("✗ Exception updating Reverb title for SKU {$sku}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function updateFaireTitle($sku, $newTitle)
    {
        try {
            Log::info("Starting Faire title update for SKU: {$sku}, New Title: {$newTitle}");
            
            // Get Faire credentials from .env
            $appId = env('FAIRE_APP_ID');
            $appSecret = env('FAIRE_APP_SECRET');
            $redirectUrl = env('FAIRE_REDIRECT_URL');
            
            // Try to get access token - first check for direct token, then try OAuth
            $token = env('FAIRE_BEARER_TOKEN') 
                ?? env('FAIRE_ACCESS_TOKEN') 
                ?? env('FAIRE_TOKEN');
            
            // If no direct token and we have OAuth credentials, try to get access token
            if (!$token && $appId && $appSecret) {
                Log::info("No direct token found, attempting to get access token via OAuth...");
                
                // Check if we have a refresh token or authorization code
                $refreshToken = env('FAIRE_REFRESH_TOKEN');
                $authCode = env('FAIRE_AUTH_CODE') ?? env('code'); // Also check 'code' variable from .env
                
                if ($refreshToken) {
                    // Use refresh token to get new access token
                    try {
                        $tokenResponse = \Illuminate\Support\Facades\Http::withoutVerifying()
                            ->post('https://www.faire.com/api/external-api-oauth2/token', [
                                'applicationId' => $appId,
                                'applicationSecret' => $appSecret,
                                'grantType' => 'REFRESH_TOKEN',
                                'refreshToken' => $refreshToken,
                            ]);
                        
                        if ($tokenResponse->successful()) {
                            $token = $tokenResponse->json('access_token');
                            if ($token) {
                                Log::info("✓ Successfully obtained Faire access token via OAuth refresh");
                            }
                        } else {
                            Log::warning("OAuth token refresh failed. Status: " . $tokenResponse->status() . ", Body: " . $tokenResponse->body());
                        }
                    } catch (\Exception $e) {
                        Log::warning("OAuth token refresh exception: " . $e->getMessage());
                    }
                } elseif ($authCode) {
                    // Use authorization code to get access token
                    try {
                        // Ensure redirectUrl has proper format
                        if ($redirectUrl && !preg_match('/^https?:\/\//', $redirectUrl)) {
                            $redirectUrl = 'http://' . $redirectUrl;
                        }
                        
                        $oauthPayload = [
                            'applicationId' => $appId,
                            'applicationSecret' => $appSecret,
                            'redirectUrl' => $redirectUrl,
                            'scope' => ['READ_PRODUCTS', 'WRITE_PRODUCTS'], // Required scope for product updates
                            'grantType' => 'AUTHORIZATION_CODE',
                            'authorizationCode' => $authCode,
                        ];
                        
                        Log::info("Attempting OAuth token exchange with authorization code (code length: " . strlen($authCode) . ")");
                        
                        $tokenResponse = \Illuminate\Support\Facades\Http::withoutVerifying()
                            ->post('https://www.faire.com/api/external-api-oauth2/token', $oauthPayload);
                        
                        if ($tokenResponse->successful()) {
                            $token = $tokenResponse->json('access_token');
                            if ($token) {
                                Log::info("✓ Successfully obtained Faire access token via OAuth authorization code");
                            } else {
                                Log::warning("OAuth response successful but no access_token in response: " . $tokenResponse->body());
                            }
                        } else {
                            Log::warning("OAuth authorization code exchange failed. Status: " . $tokenResponse->status() . ", Body: " . $tokenResponse->body());
                            Log::warning("OAuth payload sent: " . json_encode(array_merge($oauthPayload, ['applicationSecret' => '***HIDDEN***', 'authorizationCode' => '***HIDDEN***'])));
                        }
                    } catch (\Exception $e) {
                        Log::warning("OAuth authorization code exchange exception: " . $e->getMessage());
                        Log::warning("Exception trace: " . $e->getTraceAsString());
                    }
                }
            }
            
            if (!$token) {
                Log::warning("Faire access token not available");
                Log::warning("Configured credentials: FAIRE_APP_ID=" . ($appId ? "✓" : "✗") . ", FAIRE_APP_SECRET=" . ($appSecret ? "✓" : "✗"));
                Log::warning("Please ensure you have either:");
                Log::warning("  - FAIRE_BEARER_TOKEN or FAIRE_ACCESS_TOKEN (direct token)");
                Log::warning("  - FAIRE_REFRESH_TOKEN (for OAuth refresh)");
                Log::warning("  - FAIRE_AUTH_CODE (for OAuth authorization code exchange)");
                return false;
            }

            Log::info("✓ Faire token found (length: " . strlen($token) . " characters)");

            $baseUrl = 'https://www.faire.com/external-api/v2';
            
            // Try both header formats - Faire API may accept either
            $headerFormats = [
                [
                    'X-FAIRE-ACCESS-TOKEN' => $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ];

            // Step 1: Try to get product ID from FaireDataView first (faster)
            $productId = null;
            $faireDataView = \App\Models\FaireDataView::where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first();
            
            if ($faireDataView && $faireDataView->value) {
                $value = is_array($faireDataView->value) ? $faireDataView->value : json_decode($faireDataView->value, true);
                if (is_array($value)) {
                    // Try common product ID field names
                    $productId = $value['id'] ?? $value['product_id'] ?? $value['productId'] ?? $value['product']['id'] ?? null;
                    if ($productId) {
                        Log::info("Found Faire product ID from FaireDataView: {$productId} for SKU: {$sku}");
                    }
                }
            }

            // Step 2: If no product ID from database, fetch from API with pagination
            if (!$productId) {
                Log::info("Product ID not found in FaireDataView, fetching from Faire API...");
                
                $limit = 50;
                $cursor = null;
                $found = false;
                $listSuccess = false;

                // Try both header formats for product listing
                foreach ($headerFormats as $headerIndex => $testHeaders) {
                    Log::info("Trying header format " . ($headerIndex + 1) . " for product list...");
                    
                    do {
                        $query = ['limit' => $limit];
                        if ($cursor) $query['cursor'] = $cursor;

                        $listResponse = \Illuminate\Support\Facades\Http::withoutVerifying()
                            ->withHeaders($testHeaders)
                            ->timeout(30)
                            ->get("{$baseUrl}/products", $query);

                        if (!$listResponse->successful()) {
                            $status = $listResponse->status();
                            $body = $listResponse->body();
                            
                            if ($status == 401 && $headerIndex == 0) {
                                // Try next header format if first one fails with 401
                                Log::warning("Product list failed with 401 using header format 1, trying format 2...");
                                break; // Break out of do-while, try next header format
                            }
                            
                            Log::error("Product list failed. Status: {$status}, Body: {$body}");
                            
                            if ($status == 401) {
                                Log::error("Authentication failed (401). Check Faire token in .env file - token may be invalid or expired");
                                Log::error("Verify token is valid in Faire Brand Portal > Settings > API");
                            } elseif ($status == 403) {
                                Log::error("Permission denied (403). Check API permissions in Faire Brand Portal");
                            }
                            
                            if ($headerIndex == count($headerFormats) - 1) {
                                // Last header format failed, return false
                                return false;
                            }
                            continue; // Try next header format
                        }

                        $listSuccess = true;
                        $data = $listResponse->json();

                        foreach ($data['products'] ?? [] as $product) {
                            // Check product SKU
                            if (isset($product['sku']) && strtoupper(trim($product['sku'])) === strtoupper(trim($sku))) {
                                $productId = $product['id'];
                                $found = true;
                                break 3; // Found - break out of do-while, foreach, and header format loop
                            }
                            
                            // Check variants for matching SKU
                            foreach ($product['variants'] ?? [] as $variant) {
                                if (($variant['sku'] ?? '') === $sku || strtoupper(trim($variant['sku'] ?? '')) === strtoupper(trim($sku))) {
                                    $productId = $product['id'];
                                    $found = true;
                                    break 4; // Found - break out of all loops
                                }
                            }
                        }

                        $cursor = $data['pagination']['next_cursor'] ?? null;
                    } while ($cursor && !$found && $listSuccess);
                    
                    if ($found || $listSuccess) {
                        break; // Found product or got successful response, stop trying header formats
                    }
                }

                if (!$productId) {
                    Log::error("No product found for SKU: {$sku} in Faire API");
                    return false;
                }

                Log::info("Found Faire Product ID from API: {$productId}");
            }

            // Step 3: First, get the full product to see its structure
            Log::info("Fetching Faire product details for ID: {$productId}");
            $currentProduct = null;
            $workingHeaders = null;
            
            // Try both header formats to get product details
            foreach ($headerFormats as $headerIndex => $testHeaders) {
                $getProductResponse = \Illuminate\Support\Facades\Http::withoutVerifying()
                    ->withHeaders($testHeaders)
                    ->timeout(30)
                    ->get("{$baseUrl}/products/{$productId}");

                if ($getProductResponse->successful()) {
                    $currentProduct = $getProductResponse->json();
                    $workingHeaders = $testHeaders; // Remember which header format worked
                    Log::info("Faire product structure: " . json_encode(array_keys($currentProduct ?? [])));
                    break; // Found working header format
                } else {
                    Log::warning("Could not fetch product details with header format " . ($headerIndex + 1) . ", trying next...");
                }
            }
            
            if (!$currentProduct) {
                Log::warning("Could not fetch product details with any header format, proceeding with update anyway");
                $workingHeaders = $headerFormats[0]; // Use first header format as default
            }

            // Step 4: Update product name (title) - try multiple payload formats
            // Rate limiting delay
            sleep(1);

            Log::info("Updating Faire product ID: {$productId} with title: {$newTitle}");

            // Try different payload formats and methods
            $updateAttempts = [
                [
                    'method' => 'PATCH',
                    'payload' => ['name' => $newTitle],
                    'description' => 'PATCH with name only'
                ],
                [
                    'method' => 'PATCH',
                    'payload' => [
                        'name' => $newTitle,
                        'short_description' => $newTitle
                    ],
                    'description' => 'PATCH with name and short_description'
                ],
                [
                    'method' => 'PUT',
                    'payload' => ['name' => $newTitle],
                    'description' => 'PUT with name only'
                ],
            ];

            // If we have the current product and it has variants, try updating variants too
            if ($currentProduct && isset($currentProduct['variants']) && is_array($currentProduct['variants'])) {
                $variantUpdates = [];
                foreach ($currentProduct['variants'] as $variant) {
                    if (isset($variant['id'])) {
                        $variantUpdates[] = [
                            'id' => $variant['id'],
                            'name' => $newTitle
                        ];
                    }
                }
                if (!empty($variantUpdates)) {
                    $updateAttempts[] = [
                        'method' => 'PATCH',
                        'payload' => [
                            'name' => $newTitle,
                            'variants' => $variantUpdates
                        ],
                        'description' => 'PATCH with name and variant updates'
                    ];
                }
            }

            // Also try updating variants directly if product has variants
            if ($currentProduct && isset($currentProduct['variants']) && is_array($currentProduct['variants'])) {
                foreach ($currentProduct['variants'] as $variant) {
                    if (isset($variant['id']) && isset($variant['sku']) && 
                        (strtoupper(trim($variant['sku'])) === strtoupper(trim($sku)))) {
                        // Found matching variant, try updating it directly
                        $variantId = $variant['id'];
                        $updateAttempts[] = [
                            'method' => 'PATCH',
                            'payload' => ['name' => $newTitle],
                            'description' => "PATCH variant {$variantId} name directly",
                            'is_variant' => true,
                            'variant_id' => $variantId
                        ];
                        Log::info("Found matching variant ID: {$variantId} for SKU: {$sku}");
                    }
                }
            }

            $lastError = null;
            foreach ($updateAttempts as $attempt) {
                Log::info("Trying Faire update: {$attempt['description']}");
                
                // Try both header formats for each update attempt
                $updateSuccess = false;
                foreach ($headerFormats as $headerIndex => $testHeaders) {
                    $httpClient = \Illuminate\Support\Facades\Http::withoutVerifying()
                        ->withHeaders($testHeaders)
                        ->timeout(30);
                    
                    // Determine endpoint - variant or product
                    if (isset($attempt['is_variant']) && $attempt['is_variant']) {
                        $endpoint = "{$baseUrl}/products/{$productId}/variants/{$attempt['variant_id']}";
                    } else {
                        $endpoint = "{$baseUrl}/products/{$productId}";
                    }

                    if ($attempt['method'] === 'PUT') {
                        $updateResponse = $httpClient->put($endpoint, $attempt['payload']);
                    } else {
                        $updateResponse = $httpClient->patch($endpoint, $attempt['payload']);
                    }

                    $status = $updateResponse->status();
                    $body = $updateResponse->body();

                    Log::info("Faire update status: {$status} (method: {$attempt['method']}, header format: " . ($headerIndex + 1) . ", endpoint: {$endpoint})");
                    Log::info("Faire update body: {$body}");

                    if ($updateResponse->successful()) {
                        $responseData = $updateResponse->json();
                        
                        // Check if title was updated successfully
                        if (isset($responseData['name']) && $responseData['name'] === $newTitle) {
                            Log::info("✓ Successfully updated Faire title for SKU: {$sku}, Product ID: {$productId} using {$attempt['description']} with header format " . ($headerIndex + 1));
                            return true;
                        } elseif (isset($responseData['id'])) {
                            // If we got a product/variant object back, assume success
                            Log::info("✓ Successfully updated Faire title for SKU: {$sku}, Product ID: {$productId} using {$attempt['description']} with header format " . ($headerIndex + 1));
                            return true;
                        } else {
                            // Still consider it successful if status was 200/204
                            if ($status == 200 || $status == 204) {
                                Log::info("✓ Faire update returned {$status} - assuming success with header format " . ($headerIndex + 1));
                                return true;
                            }
                        }
                        $updateSuccess = true; // Got successful response, stop trying other header formats
                        break;
                    } else {
                        $lastError = [
                            'status' => $status,
                            'body' => $body,
                            'method' => $attempt['method'],
                            'description' => $attempt['description'],
                            'header_format' => $headerIndex + 1,
                            'endpoint' => $endpoint
                        ];
                        
                        // If 401 with first header format, try second
                        if ($status == 401 && $headerIndex == 0) {
                            Log::warning("Faire update failed with 401 using header format 1, trying format 2...");
                            continue; // Try next header format
                        }
                        
                        // If it's a 400 error, try next method/header combination
                        if ($status == 400) {
                            Log::warning("Faire update failed with 400 using {$attempt['description']} with header format " . ($headerIndex + 1));
                            if ($headerIndex == count($headerFormats) - 1) {
                                // Last header format, try next method
                                break; // Break out of header format loop, continue to next attempt
                            }
                            continue; // Try next header format
                        }
                        
                        // For other errors, log but continue
                        Log::warning("Faire update failed with {$status} using {$attempt['description']} with header format " . ($headerIndex + 1));
                        
                        if ($headerIndex == count($headerFormats) - 1) {
                            // Last header format failed, move to next attempt
                            break;
                        }
                    }
                }
                
                if ($updateSuccess) {
                    break; // Success, stop trying other attempts
                }
                
                // Small delay between attempts
                sleep(1);
            }

            // If we get here, all attempts failed
            if ($lastError) {
                Log::error("✗ All Faire update attempts failed. Last error - Status: {$lastError['status']}, Method: {$lastError['method']}, Body: {$lastError['body']}");
                
                if ($lastError['status'] == 401) {
                    Log::error("Authentication failed (401). Check Faire token in .env file - token may be invalid or expired");
                    Log::error("Try regenerating token in Faire Brand Portal > Settings > API");
                } elseif ($lastError['status'] == 403) {
                    Log::error("Permission denied (403). Check API permissions in Faire Brand Portal - ensure product update permissions are enabled");
                } elseif ($lastError['status'] == 404) {
                    Log::error("Product not found (404). Product ID: {$productId} may not exist or be accessible");
                } elseif ($lastError['status'] == 400) {
                    Log::error("Bad request (400). The API may require additional fields or a different payload format");
                    Log::error("Response: {$lastError['body']}");
                } elseif ($lastError['status'] == 429) {
                    Log::error("Rate limited (429) - wait and retry");
                }
            }
            
            return false;

        } catch (\Exception $e) {
            Log::error("✗ Exception updating Faire title for SKU {$sku}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function updateAliexpressTitle($sku, $newTitle, $language = 'en')
    {
        try {
            Log::info("Starting AliExpress title update for SKU: {$sku}, Title: {$newTitle}");
            
            $appKey = env('ALIEXPRESS_APP_KEY');
            $appSecret = env('ALIEXPRESS_APP_SECRET');
            $accessToken = env('ALIEXPRESS_ACCESS_TOKEN');
            
            if (!$appKey || !$appSecret || !$accessToken) {
                Log::warning("AliExpress credentials missing in .env");
                Log::warning("Required: ALIEXPRESS_APP_KEY, ALIEXPRESS_APP_SECRET, and ALIEXPRESS_ACCESS_TOKEN");
                return false;
            }

            Log::info("✓ AliExpress credentials found (access token length: " . strlen($accessToken) . " characters)");

            // Step 1: Try to get product_id from database first (faster)
            $productId = null;
            
            $aliexpressDataView = \App\Models\AliexpressDataView::where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first();
            
            if ($aliexpressDataView && $aliexpressDataView->value) {
                $value = is_array($aliexpressDataView->value) ? $aliexpressDataView->value : json_decode($aliexpressDataView->value, true);
                if (is_array($value)) {
                    $productId = $value['product_id'] ?? $value['productId'] ?? $value['id'] ?? null;
                    if ($productId) {
                        Log::info("Found AliExpress product ID from AliexpressDataView: {$productId} for SKU: {$sku}");
                    }
                }
            }

            // Step 2: If no product ID from database, we need it to update
            if (!$productId) {
                Log::error("Product ID not found for SKU: {$sku} in AliexpressDataView");
                Log::error("AliExpress API requires product_id to update title. Please ensure product is synced to AliexpressDataView.");
                return false;
            }

            Log::info("Found AliExpress product_id: {$productId}");

            // Step 3: Update product title using Aliexpress Open Platform API
            // Using aliexpress.solution.product.edit method
            $baseUrl = 'https://api-sg.aliexpress.com/sync';
            $timestamp = round(microtime(true) * 1000); // milliseconds

            $updateParams = [
                'app_key' => $appKey,
                'timestamp' => $timestamp,
                'access_token' => $accessToken,
                'sign_method' => 'sha256',
                'method' => 'aliexpress.solution.product.edit',
                'product_id' => $productId,
                'subject' => $newTitle, // Product title field in Aliexpress
            ];

            // Generate signature (Aliexpress requires signature)
            $paramsForSign = $updateParams;
            unset($paramsForSign['sign']); // Remove sign if exists
            ksort($paramsForSign);

            $signStr = $appSecret;
            foreach ($paramsForSign as $key => $val) {
                if ($val !== null && $val !== '') {
                    $signStr .= $key . $val;
                }
            }
            $signStr .= $appSecret;

            $signature = strtoupper(hash_hmac('sha256', $signStr, $appSecret));
            $updateParams['sign'] = $signature;

            // Rate limiting delay
            sleep(1);

            Log::info("Updating AliExpress product ID: {$productId} with title: {$newTitle}");

            $updateResponse = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->asForm()
                ->timeout(30)
                ->post($baseUrl, $updateParams);

            $status = $updateResponse->status();
            $body = $updateResponse->body();

            Log::info("AliExpress update status: {$status}");
            Log::info("AliExpress update body: {$body}");

            if ($updateResponse->successful()) {
                $responseData = $updateResponse->json();
                
                // Check response structure (Aliexpress API response format)
                if (isset($responseData['aliexpress_solution_product_edit_response'])) {
                    $result = $responseData['aliexpress_solution_product_edit_response'];
                    if (isset($result['result']) && isset($result['result']['success']) && $result['result']['success'] == true) {
                        Log::info("✓ Successfully updated AliExpress title for SKU: {$sku}, Product ID: {$productId}");
                        return true;
                    } else {
                        $errorMsg = $result['result']['error_message'] ?? $result['result']['error_msg'] ?? 'Unknown error';
                        Log::error("✗ AliExpress update failed: {$errorMsg}");
                        return false;
                    }
                } elseif (isset($responseData['error_response'])) {
                    $error = $responseData['error_response'];
                    $errorMsg = $error['msg'] ?? $error['message'] ?? 'Unknown error';
                    $errorCode = $error['code'] ?? 'N/A';
                    Log::error("✗ AliExpress API error [{$errorCode}]: {$errorMsg}");
                    return false;
                } elseif (strpos($body, '"success":true') !== false || strpos($body, '"success": true') !== false) {
                    Log::info("✓ Successfully updated AliExpress title for SKU: {$sku}, Product ID: {$productId}");
                    return true;
                } else {
                    Log::error("✗ Update failed - unexpected response structure. Response: " . json_encode($responseData));
                    return false;
                }
            } else {
                Log::error("✗ AliExpress update failed. Status: {$status}, Body: {$body}");
                
                if ($status == 401) {
                    Log::error("Authentication failed (401). Check ALIEXPRESS_ACCESS_TOKEN - may be invalid or expired");
                } elseif ($status == 403) {
                    Log::error("Permission denied (403). Check API permissions in AliExpress Open Platform");
                } elseif ($status == 400) {
                    Log::error("Bad request (400). Check request parameters and signature");
                }
                
                return false;
            }

        } catch (\Exception $e) {
            Log::error("✗ Exception updating AliExpress title for SKU {$sku}: " . $e->getMessage());
            Log::error("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    private function updateTiktokTitle($sku, $newTitle)
    {
        try {
            Log::info("Starting TikTok title update for SKU: {$sku}, Title: {$newTitle}");
            
            // Get TikTok Shop API credentials from .env - try multiple variable name formats
            $appKey = env('TIKTOK_APP_KEY') 
                ?? env('TIKTOK_CLIENT_KEY') 
                ?? env('TIKTOK_APP_ID')
                ?? env('TIKTOK_KEY');
            $appSecret = env('TIKTOK_APP_SECRET') 
                ?? env('TIKTOK_CLIENT_SECRET') 
                ?? env('TIKTOK_SECRET');
            $accessToken = env('TIKTOK_ACCESS_TOKEN')
                ?? env('TIKTOK_TOKEN')
                ?? env('TIKTOK_BEARER_TOKEN');
            
            if (!$appKey || !$appSecret || !$accessToken) {
                Log::warning("TikTok credentials missing in .env");
                Log::warning("Required: TIKTOK_APP_KEY (or TIKTOK_CLIENT_KEY/TIKTOK_APP_ID), TIKTOK_APP_SECRET (or TIKTOK_CLIENT_SECRET), and TIKTOK_ACCESS_TOKEN (or TIKTOK_TOKEN)");
                Log::warning("Please check your .env file and ensure TikTok credentials are configured");
                return false;
            }

            Log::info("✓ TikTok credentials found (access token length: " . strlen($accessToken) . " characters)");

            // Step 1: Try to get product_id from database first (faster)
            $productId = null;
            
            $tiktokDataView = \App\Models\TiktokShopDataView::where('sku', $sku)
                ->orWhere('sku', strtoupper($sku))
                ->orWhere('sku', strtolower($sku))
                ->first();
            
            if ($tiktokDataView && $tiktokDataView->value) {
                $value = is_array($tiktokDataView->value) ? $tiktokDataView->value : json_decode($tiktokDataView->value, true);
                if (is_array($value)) {
                    $productId = $value['product_id'] ?? $value['productId'] ?? $value['id'] ?? null;
                    if ($productId) {
                        Log::info("Found TikTok product ID from TiktokShopDataView: {$productId} for SKU: {$sku}");
                    }
                }
            }

            // Step 2: If no product ID from database, we need it to update
            if (!$productId) {
                Log::error("Product ID not found for SKU: {$sku} in TiktokShopDataView");
                Log::error("TikTok API requires product_id to update title. Please ensure product is synced to TiktokShopDataView.");
                return false;
            }

            Log::info("Found TikTok product_id: {$productId}");

            // Step 3: Update product title using TikTok Shop API
            // TikTok Shop API endpoint for updating product
            $baseUrl = 'https://open-api.tiktokglobalshop.com';
            $shopId = env('TIKTOK_SHOP_ID'); // Optional, may be required for some endpoints
            
            // TikTok Shop API uses Bearer token authentication
            $headers = [
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ];

            // TikTok Shop API endpoint for updating product title
            // Using /product/202309/products/{product_id} endpoint
            $updatePayload = [
                'title' => $newTitle,
            ];

            // Rate limiting delay
            sleep(1);

            Log::info("Updating TikTok product ID: {$productId} with title: {$newTitle}");

            // Try different TikTok API endpoints
            $endpoints = [
                "/product/202309/products/{$productId}",
                "/api/products/{$productId}",
                "/open_api/v1/product/update",
            ];

            $success = false;
            foreach ($endpoints as $endpoint) {
                $url = $baseUrl . $endpoint;
                
                Log::info("Trying TikTok endpoint: {$url}");

                $updateResponse = \Illuminate\Support\Facades\Http::withoutVerifying()
                    ->withHeaders($headers)
                    ->timeout(30)
                    ->patch($url, $updatePayload);

                $status = $updateResponse->status();
                $body = $updateResponse->body();

                Log::info("TikTok update status: {$status}");
                Log::info("TikTok update body: {$body}");

                if ($updateResponse->successful()) {
                    $responseData = $updateResponse->json();
                    
                    // Check for success indicators
                    if (isset($responseData['data']) || isset($responseData['success']) || isset($responseData['message'])) {
                        Log::info("✓ Successfully updated TikTok title for SKU: {$sku}, Product ID: {$productId}");
                        $success = true;
                        break;
                    }
                } elseif ($status == 404) {
                    // Endpoint not found, try next one
                    continue;
                } else {
                    // Log error but continue to next endpoint
                    Log::warning("TikTok endpoint {$endpoint} failed. Status: {$status}");
                }
            }

            if (!$success) {
                Log::error("✗ TikTok update failed for all endpoints. Last status: {$status}, Body: {$body}");
                
                if ($status == 401) {
                    Log::error("Authentication failed (401). Check TIKTOK_ACCESS_TOKEN - may be invalid or expired");
                } elseif ($status == 403) {
                    Log::error("Permission denied (403). Check API permissions in TikTok Shop Developer Portal");
                } elseif ($status == 400) {
                    Log::error("Bad request (400). Check request parameters and payload format");
                }
                
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error("✗ Exception updating TikTok title for SKU {$sku}: " . $e->getMessage());
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
