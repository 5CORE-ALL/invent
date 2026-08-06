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
            'apply_siblings' => 'nullable|boolean',
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
        $applySiblingsPref = ! empty($validated['apply_siblings']);
        $text = $ignored
            ? ''
            : (isset($validated['qc_improvement_req']) ? trim((string) $validated['qc_improvement_req']) : '');

        // Always remember Siblings checkbox for future opens.
        $this->persistSpecialQc($product, $text, $ignored, $applySiblingsPref);

        $siblingSkus = [];
        $siblingMessage = '';
        if ($applySiblingsPref) {
            $applied = $this->applySpecialQcToSiblings($product, $text, $ignored);
            $siblingSkus = $applied['siblings'];
            $siblingMessage = $applied['message'];
        }

        $message = $text === ''
            ? ($ignored ? 'Ignored.' : 'Cleared.')
            : 'Saved.';
        if ($siblingMessage !== '') {
            $message .= ' '.$siblingMessage;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'qc_improvement_req' => $text,
            'ignore' => $ignored,
            'apply_siblings' => $applySiblingsPref,
            'siblings' => $siblingSkus,
            'siblings_updated' => count($siblingSkus),
        ]);
    }

    /**
     * Persist Special Instruction QC text + ignore + siblings preference for one product_master row.
     */
    protected function persistSpecialQc(ProductMaster $product, string $text, bool $ignored, bool $applySiblings = false): void
    {
        $values = is_array($product->Values) ? $product->Values : [];
        if ($ignored) {
            $values['special_qc_ignore'] = true;
        } else {
            unset($values['special_qc_ignore']);
        }
        if ($applySiblings) {
            $values['special_qc_apply_siblings'] = true;
        } else {
            unset($values['special_qc_apply_siblings']);
        }
        $product->Values = $values;
        $product->save();

        if ($text === '') {
            QcImprovementReqBeforeItemPkg::where('product_master_id', $product->id)->delete();

            return;
        }

        QcImprovementReqBeforeItemPkg::updateOrCreate(
            ['product_master_id' => $product->id],
            ['qc_improvement_req' => $text]
        );
    }

    /**
     * Copy Special Instruction QC (+ Ignore) to sibling SKUs sharing product_master.parent.
     *
     * @return array{siblings: list<string>, message: string}
     */
    protected function applySpecialQcToSiblings(ProductMaster $source, string $text, bool $ignored): array
    {
        $parent = trim((string) ($source->parent ?? ''));
        if ($parent === '') {
            return [
                'siblings' => [],
                'message' => 'No parent set — nothing to copy to siblings.',
            ];
        }

        $siblings = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereRaw('TRIM(parent) = ?', [$parent])
            ->where('sku', 'NOT LIKE', 'PARENT %')
            ->where('id', '!=', $source->id)
            ->orderBy('sku')
            ->get();

        if ($siblings->isEmpty()) {
            return [
                'siblings' => [],
                'message' => 'No sibling SKUs found.',
            ];
        }

        $updatedSkus = [];
        foreach ($siblings as $sib) {
            // Keep Siblings checked on siblings for future edits too.
            $this->persistSpecialQc($sib, $text, $ignored, true);
            $updatedSkus[] = trim((string) $sib->sku);
        }

        return [
            'siblings' => $updatedSkus,
            'message' => count($updatedSkus).' sibling SKU(s) updated.',
        ];
    }
}
