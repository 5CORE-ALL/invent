<?php

namespace App\Http\Controllers\PerformanceManagement;

use App\Http\Controllers\Controller;
use App\Models\ChecklistCategory;
use App\Models\ChecklistItem;
use App\Models\Designation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Response;

class ChecklistController extends Controller
{
    /**
     * Check if user is admin
     */
    private function checkAdmin()
    {
        if (Auth::user()->email !== 'president@5core.com') {
            abort(403, 'Unauthorized access. Only admin can manage checklists.');
        }
    }

    /**
     * Get checklist for a designation
     * Supports both designation ID (from table) and designation name (from users table)
     */
    public function getChecklist($designationIdOrName)
    {
        try {
            // URL decode the parameter in case it's encoded
            $designationIdOrName = urldecode($designationIdOrName);
            
            // Check if designations table exists
            $designationsTableExists = Schema::hasTable('designations');
            
            // Try to find by ID first (numeric) - only if table exists
            if ($designationsTableExists && is_numeric($designationIdOrName)) {
                try {
                    $designation = Designation::with([
                        'categories' => function($query) {
                            $query->where('is_active', true)->orderBy('order');
                        },
                        'categories.items' => function($query) {
                            $query->where('is_active', true)->orderBy('order');
                        }
                    ])->find($designationIdOrName);
                    
                    if ($designation) {
                        // Ensure categories is always an array
                        $designationData = $designation->toArray();
                        if (!isset($designationData['categories'])) {
                            $designationData['categories'] = [];
                        }
                        return response()->json($designationData);
                    }
                } catch (\Exception $e) {
                    Log::warning('Error querying designations table by ID: ' . $e->getMessage());
                }
            }

            // If not found by ID, treat as designation name (from users table)
            $designationName = $designationIdOrName;
            
            // Check if designation exists in designations table by name - only if table exists
            $designation = null;
            if ($designationsTableExists) {
                try {
                    $designation = Designation::where('name', $designationName)
                        ->with([
                            'categories' => function($query) {
                                $query->where('is_active', true)->orderBy('order');
                            },
                            'categories.items' => function($query) {
                                $query->where('is_active', true)->orderBy('order');
                            }
                        ])->first();
                } catch (\Exception $e) {
                    Log::warning('Error querying designations table by name: ' . $e->getMessage());
                }
            }

            // If not in table, create a virtual designation object from users
            if (!$designation) {
                // Check if any users have this designation
                $userCount = \App\Models\User::where('designation', $designationName)
                    ->where('is_active', true)
                    ->count();

                if ($userCount === 0) {
                    return response()->json([
                        'error' => 'Designation not found'
                    ], 404);
                }

                // Return virtual designation structure as array (not object) for proper JSON serialization
                $designationData = [
                    'id' => null,
                    'name' => $designationName,
                    'description' => null,
                    'is_active' => true,
                    'is_dynamic' => true,
                    'categories' => [], // Empty categories array - admin can create them
                ];
                
                return response()->json($designationData);
            }

            // Ensure categories is always an array
            $designationData = $designation->toArray();
            if (!isset($designationData['categories'])) {
                $designationData['categories'] = [];
            }
            
            return response()->json($designationData);
        } catch (\Exception $e) {
            Log::error('Error in ChecklistController@getChecklist: ' . $e->getMessage(), [
                'designationIdOrName' => $designationIdOrName,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Failed to load checklist',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a new category
     * Supports both designation ID and designation name
     */
    public function storeCategory(Request $request)
    {
        try {
            $this->checkAdmin();

            $validated = $request->validate([
                'designation_id' => 'nullable',
                'designation_name' => 'nullable|string',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'order' => 'nullable|integer|min:0',
                'is_active' => 'nullable|boolean',
            ]);

            // Handle designation - can be ID or name
            $designationId = $validated['designation_id'] ?? null;
            $designationName = $validated['designation_name'] ?? null;

            // If designation_id is not numeric and not empty, treat it as name
            if ($designationId && !is_numeric($designationId) && trim($designationId) !== '') {
                $designationName = $designationId;
                $designationId = null;
            }

            // Find or create designation
            if ($designationName && trim($designationName) !== '') {
                try {
                    $designation = Designation::firstOrCreate(
                        ['name' => trim($designationName)],
                        ['description' => null, 'is_active' => true]
                    );
                    $designationId = $designation->id;
                } catch (\Exception $e) {
                    Log::error('Error creating/finding designation: ' . $e->getMessage());
                    return response()->json([
                        'success' => false,
                        'message' => 'Error processing designation: ' . $e->getMessage()
                    ], 500);
                }
            } elseif (!$designationId || !is_numeric($designationId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Designation ID or name is required'
                ], 422);
            }

            // Verify designation exists
            if (!Designation::find($designationId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Designation not found'
                ], 404);
            }

            $category = ChecklistCategory::create([
                'designation_id' => $designationId,
                'name' => trim($validated['name']),
                'description' => $validated['description'] ?? null,
                'order' => $validated['order'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Category created successfully',
                'data' => $category->load('items')
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error in ChecklistController@storeCategory: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error saving category: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get category data for editing
     */
    public function getCategory(ChecklistCategory $category)
    {
        return response()->json($category);
    }

    /**
     * Get item data for editing
     */
    public function getItem(ChecklistItem $item)
    {
        return response()->json($item);
    }

    /**
     * Update a category
     */
    public function updateCategory(Request $request, ChecklistCategory $category)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category->fresh()->load('items')
        ]);
    }

    /**
     * Delete a category
     */
    public function destroyCategory(ChecklistCategory $category)
    {
        $this->checkAdmin();

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }

    /**
     * Store a new checklist item
     */
    public function storeItem(Request $request)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'category_id' => 'required|exists:checklist_categories,id',
            'question' => 'required|string',
            'weight' => 'required|numeric|min:0.1|max:10',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $item = ChecklistItem::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Checklist item created successfully',
            'data' => $item
        ], 201);
    }

    /**
     * Update a checklist item
     */
    public function updateItem(Request $request, ChecklistItem $item)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'question' => 'required|string',
            'weight' => 'required|numeric|min:0.1|max:10',
            'order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $item->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Checklist item updated successfully',
            'data' => $item->fresh()
        ]);
    }

    /**
     * Delete a checklist item
     */
    public function destroyItem(ChecklistItem $item)
    {
        $this->checkAdmin();

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Checklist item deleted successfully'
        ]);
    }

    /**
     * Reorder categories
     */
    public function reorderCategories(Request $request)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'designation_id' => 'required|exists:designations,id',
            'category_orders' => 'required|array',
            'category_orders.*.id' => 'required|exists:checklist_categories,id',
            'category_orders.*.order' => 'required|integer',
        ]);

        DB::transaction(function() use ($validated) {
            foreach ($validated['category_orders'] as $orderData) {
                ChecklistCategory::where('id', $orderData['id'])
                    ->update(['order' => $orderData['order']]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Categories reordered successfully'
        ]);
    }

    /**
     * Reorder items
     */
    public function reorderItems(Request $request)
    {
        $this->checkAdmin();

        $validated = $request->validate([
            'category_id' => 'required|exists:checklist_categories,id',
            'item_orders' => 'required|array',
            'item_orders.*.id' => 'required|exists:checklist_items,id',
            'item_orders.*.order' => 'required|integer',
        ]);

        DB::transaction(function() use ($validated) {
            foreach ($validated['item_orders'] as $orderData) {
                ChecklistItem::where('id', $orderData['id'])
                    ->update(['order' => $orderData['order']]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Items reordered successfully'
        ]);
    }

    /**
     * Export checklist items to CSV
     */
    public function exportCsv($designationIdOrName)
    {
        $this->checkAdmin();

        try {
            // URL decode the parameter
            $designationIdOrName = urldecode($designationIdOrName);
            
            // Get designation
            $designation = null;
            if (is_numeric($designationIdOrName)) {
                $designation = Designation::find($designationIdOrName);
            } else {
                $designation = Designation::where('name', $designationIdOrName)->first();
            }

            if (!$designation) {
                return response()->json(['error' => 'Designation not found'], 404);
            }

            // Get all categories and items
            $categories = ChecklistCategory::where('designation_id', $designation->id)
                ->with(['allItems'])
                ->orderBy('order')
                ->get();

            // Prepare CSV data
            $csvData = [];
            $csvData[] = ['Category Name', 'Category Description', 'Question', 'Weight', 'Order'];

            foreach ($categories as $category) {
                if ($category->allItems->count() > 0) {
                    foreach ($category->allItems as $item) {
                        $csvData[] = [
                            $category->name,
                            $category->description ?? '',
                            $item->question,
                            $item->weight,
                            $item->order
                        ];
                    }
                } else {
                    // Include category even if no items
                    $csvData[] = [
                        $category->name,
                        $category->description ?? '',
                        '',
                        '',
                        $category->order
                    ];
                }
            }

            // Generate CSV content
            $filename = 'checklist_' . str_replace(' ', '_', $designation->name) . '_' . date('Y-m-d') . '.csv';
            $handle = fopen('php://temp', 'r+');
            
            foreach ($csvData as $row) {
                fputcsv($handle, $row);
            }
            
            rewind($handle);
            $csvContent = stream_get_contents($handle);
            fclose($handle);

            return Response::make($csvContent, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Exception $e) {
            Log::error('Error exporting checklist CSV: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error exporting CSV: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import checklist items from CSV
     */
    public function importCsv(Request $request, $designationIdOrName)
    {
        $this->checkAdmin();

        try {
            // URL decode the parameter
            $designationIdOrName = urldecode($designationIdOrName);
            
            // Validate file
            $request->validate([
                'csv_file' => 'required|file|mimes:csv,txt|max:2048',
                'update_existing' => 'nullable|boolean'
            ]);

            // Get designation
            $designation = null;
            if (is_numeric($designationIdOrName)) {
                $designation = Designation::find($designationIdOrName);
            } else {
                // Try to find by name, or create if doesn't exist
                $designation = Designation::where('name', $designationIdOrName)->first();
                if (!$designation) {
                    $designation = Designation::create([
                        'name' => $designationIdOrName,
                        'description' => null,
                        'is_active' => true
                    ]);
                }
            }

            if (!$designation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Designation not found and could not be created'
                ], 404);
            }

            $file = $request->file('csv_file');
            $updateExisting = $request->input('update_existing', false);
            
            // Read CSV file
            $csvData = [];
            $handle = fopen($file->getRealPath(), 'r');
            $header = fgetcsv($handle); // Skip header row
            
            $imported = 0;
            $updated = 0;
            $errors = [];

            DB::beginTransaction();
            
            try {
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 3) continue; // Skip invalid rows
                    
                    $categoryName = trim($row[0] ?? '');
                    $categoryDescription = trim($row[1] ?? '');
                    $question = trim($row[2] ?? '');
                    $weight = isset($row[3]) && $row[3] !== '' ? floatval($row[3]) : 1.00;
                    $order = isset($row[4]) && $row[4] !== '' ? intval($row[4]) : 0;

                    if (empty($categoryName) || empty($question)) {
                        $errors[] = 'Skipped row: Missing category name or question';
                        continue;
                    }

                    // Find or create category
                    $category = ChecklistCategory::where('designation_id', $designation->id)
                        ->where('name', $categoryName)
                        ->first();

                    if (!$category) {
                        $category = ChecklistCategory::create([
                            'designation_id' => $designation->id,
                            'name' => $categoryName,
                            'description' => $categoryDescription ?: null,
                            'order' => $order,
                            'is_active' => true
                        ]);
                    } elseif ($updateExisting && $categoryDescription) {
                        $category->update(['description' => $categoryDescription]);
                    }

                    // Find or create item
                    $item = ChecklistItem::where('category_id', $category->id)
                        ->where('question', $question)
                        ->first();

                    if (!$item) {
                        ChecklistItem::create([
                            'category_id' => $category->id,
                            'question' => $question,
                            'weight' => $weight,
                            'order' => $order,
                            'is_active' => true
                        ]);
                        $imported++;
                    } elseif ($updateExisting) {
                        $item->update([
                            'weight' => $weight,
                            'order' => $order
                        ]);
                        $updated++;
                    }
                }

                fclose($handle);
                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => "Import completed successfully",
                    'data' => [
                        'imported' => $imported,
                        'updated' => $updated,
                        'errors' => count($errors),
                        'error_messages' => $errors
                    ]
                ]);
            } catch (\Exception $e) {
                DB::rollBack();
                fclose($handle);
                throw $e;
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error importing checklist CSV: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error importing CSV: ' . $e->getMessage()
            ], 500);
        }
    }
}
