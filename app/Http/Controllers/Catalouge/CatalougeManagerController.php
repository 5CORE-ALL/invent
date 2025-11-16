<?php

namespace App\Http\Controllers\Catalouge;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class CatalougeManagerController extends Controller
{

    /**
     * Handle dynamic route parameters and return a view.
     */
    public function catalouge_manager_index(Request $request, $first = null, $second = null)
    {
        $mode = $request->query('mode');
        $demo = $request->query('demo');
    
        if ($first === "assets") {
            return redirect('home');
        }
    
        // Fetch paginated products for display
        $products = DB::table('product_master')->paginate(20);
    
        // Fetch all products separately for correct counting
        $allProducts = DB::table('product_master')->get();
    
        // Count SKUs, AMZ FBM LIST, and AMZ FBM NR
        $totalSKUs = $allProducts->whereNotNull('sku')->count();
        $totalFbmList = $allProducts->where('amz_fbm_list', 1)->count();
        $totalFbmNr = $allProducts->where('amz_fbm_nr', 1)->count();
    
        return view($first, compact('mode', 'demo', 'second', 'products', 'totalSKUs', 'totalFbmList', 'totalFbmNr'));
    }
    

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request)
    {
        Log::info('Request Data:', $request->all());

        // Validate Request Data
        $validatedData = $request->validate([
            'category' => 'required|string',
            'parent' => 'nullable|string',
            'sku' => 'required|string|unique:product_master,sku',
            'unit' => 'required|in:pcs,pair',
            'lp' => 'nullable|numeric',
            'cp' => 'nullable|numeric',
            'frght' => 'nullable|numeric',
            'ship' => 'nullable|numeric',
            'label_qty' => 'nullable|integer',
            'lps' => 'nullable|integer',
            'wt_act' => 'nullable|numeric',
            'wt_decl' => 'nullable|numeric',
            'l1' => 'nullable|numeric',
            'w1' => 'nullable|numeric',
            'h1' => 'nullable|numeric',
            'l2' => 'nullable|numeric',
            'w2' => 'nullable|numeric',
            'h2' => 'nullable|numeric',
            'cbm_item' => 'nullable|numeric',
            'cbm_carton' => 'nullable|numeric',
            'weight' => 'nullable|numeric',
            'pcs_per_box' => 'nullable|integer',
            'status' => 'required|in:Active,DC,2BDC,Sourcing,In Transit,To Order,MFRG',
            'item_link' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048', // ✅ Improved image validation
        ]);

        // Handle Image Upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');

            // ✅ Ensure directory exists
            $uploadPath = public_path('images/products');
            if (!file_exists($uploadPath)) {
                mkdir($uploadPath, 0775, true);
            }

            // ✅ Generate a unique filename
            $imageName = uniqid('product_') . '.' . $image->getClientOriginalExtension();

            // ✅ Move image to public/images/products
            $image->move($uploadPath, $imageName);

            // ✅ Save image path in database
            $validatedData['image'] = 'images/products/' . $imageName;
        }

        // Save Data to Database
        try {
            $product = ProductMaster::create($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Product saved successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            Log::error('Error saving product: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to save product. Please try again.'
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

                // Hard delete the product
                $product->delete();
            }

            return response()->json([
                'success' => true,
                'message' => count($productIds) . ' product(s) deleted successfully.',
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting product(s): ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product(s). Please try again.',
            ], 500);
        }
    }

}
