<?php

use Illuminate\Support\Facades\Route;

// Test route to get Shein L30 sales data from Shein API
Route::get('/test-shein-api', function () {
    try {
        $sheinService = new \App\Services\SheinApiService();
        $products = $sheinService->listAllProducts();
        
        return response()->json([
            'success' => true,
            'total_products' => count($products),
            'message' => 'Shein API data fetched successfully (inventory + stock updates)',
            'data' => $products
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ], 500);
    }
});
