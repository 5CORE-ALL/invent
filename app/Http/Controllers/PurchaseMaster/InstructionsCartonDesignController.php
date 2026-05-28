<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\InstructionsCartonDesign;
use App\Models\ProductMaster;
use Illuminate\Http\Request;

class InstructionsCartonDesignController extends Controller
{
    /**
     * Create or update carton design instructions for one product_master row.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'sku' => 'nullable|string|max:255',
            'instructions' => 'nullable|string|max:2000',
        ]);

        $product = ProductMaster::find($validated['product_id']);

        if (! $product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.',
            ], 404);
        }

        if (! empty($validated['sku']) && strcasecmp(trim((string) $product->sku), trim((string) $validated['sku'])) !== 0) {
            return response()->json([
                'success' => false,
                'message' => 'SKU mismatch.',
            ], 422);
        }

        $text = isset($validated['instructions']) ? trim((string) $validated['instructions']) : '';

        if ($text === '') {
            InstructionsCartonDesign::where('product_master_id', $product->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Cleared.',
                'instructions' => '',
            ]);
        }

        $stored = mb_substr($text, 0, 2000);

        $row = InstructionsCartonDesign::updateOrCreate(
            ['product_master_id' => $product->id],
            ['instructions' => $stored]
        );

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'instructions' => $row->instructions,
        ]);
    }
}
