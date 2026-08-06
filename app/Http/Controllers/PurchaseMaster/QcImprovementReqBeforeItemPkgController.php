<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\QcImprovementReqBeforeItemPkg;
use Illuminate\Http\Request;

class QcImprovementReqBeforeItemPkgController extends Controller
{
    /**
     * Create or update QC Improvement Req for one product_master row.
     * Optional ignore=true clears text and hides Missing on PO proforma.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|integer',
            'sku' => 'nullable|string|max:255',
            'qc_improvement_req' => 'nullable|string',
            'ignore' => 'nullable|boolean',
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

        $ignored = ! empty($validated['ignore']);
        $text = $ignored
            ? ''
            : (isset($validated['qc_improvement_req']) ? trim((string) $validated['qc_improvement_req']) : '');

        $values = is_array($product->Values) ? $product->Values : [];
        if ($ignored) {
            $values['special_qc_ignore'] = true;
        } else {
            unset($values['special_qc_ignore']);
        }
        $product->Values = $values;
        $product->save();

        if ($text === '') {
            QcImprovementReqBeforeItemPkg::where('product_master_id', $product->id)->delete();

            return response()->json([
                'success' => true,
                'message' => $ignored ? 'Ignored.' : 'Cleared.',
                'qc_improvement_req' => '',
                'ignore' => $ignored,
            ]);
        }

        $row = QcImprovementReqBeforeItemPkg::updateOrCreate(
            ['product_master_id' => $product->id],
            ['qc_improvement_req' => $text]
        );

        return response()->json([
            'success' => true,
            'message' => 'Saved.',
            'qc_improvement_req' => $row->qc_improvement_req,
            'ignore' => false,
        ]);
    }
}
