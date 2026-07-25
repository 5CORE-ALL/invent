<?php

namespace App\Http\Controllers\ProductMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Product Masters → Reverb Listing Master
 * Stores Reverb-specific listing attributes (make/model/finish/year/condition/shipping profile).
 */
class ReverbListingMasterController extends Controller
{
    public function index(): View
    {
        return view('reverb-listing-master', [
            'title' => 'Reverb Listing Master',
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        if (! Schema::hasTable('product_master')) {
            return response()->json(['success' => false, 'data' => [], 'message' => 'product_master missing.']);
        }

        $search = trim((string) $request->query('search', ''));
        $cols = ['id', 'sku', 'parent', 'title150', 'Values'];
        foreach ([
            'reverb_make', 'reverb_model', 'reverb_finish', 'reverb_year',
            'reverb_condition', 'reverb_shipping_profile_id',
        ] as $col) {
            if (Schema::hasColumn('product_master', $col)) {
                $cols[] = $col;
            }
        }
        $q = ProductMaster::query()->select($cols)->orderBy('sku');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $q->where(function ($w) use ($like) {
                $w->where('sku', 'like', $like)
                    ->orWhere('reverb_make', 'like', $like)
                    ->orWhere('reverb_model', 'like', $like)
                    ->orWhere('title150', 'like', $like);
            });
        }

        $rows = $q->limit(2000)->get()->map(function (ProductMaster $pm) {
            $values = is_array($pm->Values) ? $pm->Values : (json_decode((string) $pm->Values, true) ?: []);

            return [
                'id' => $pm->id,
                'sku' => $pm->sku,
                'parent' => $pm->parent,
                'title' => $pm->title150,
                'reverb_make' => $pm->reverb_make ?: ($values['brand'] ?? ''),
                'reverb_model' => $pm->reverb_model,
                'reverb_finish' => $pm->reverb_finish,
                'reverb_year' => $pm->reverb_year,
                'reverb_condition' => $pm->reverb_condition ?: ($values['condition'] ?? ''),
                'reverb_shipping_profile_id' => $pm->reverb_shipping_profile_id,
            ];
        });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function update(Request $request): JsonResponse
    {
        $id = (int) $request->input('id');
        $field = trim((string) $request->input('field'));
        $value = trim((string) $request->input('value', ''));

        $allowed = [
            'reverb_make',
            'reverb_model',
            'reverb_finish',
            'reverb_year',
            'reverb_condition',
            'reverb_shipping_profile_id',
        ];
        if ($id < 1 || ! in_array($field, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Invalid field or id.'], 422);
        }

        if (! Schema::hasColumn('product_master', $field)) {
            return response()->json([
                'success' => false,
                'message' => 'Column '.$field.' missing. Run: php artisan migrate',
            ], 422);
        }

        $pm = ProductMaster::query()->find($id);
        if (! $pm) {
            return response()->json(['success' => false, 'message' => 'SKU row not found.'], 404);
        }

        $pm->{$field} = $value !== '' ? $value : null;
        $pm->save();

        return response()->json([
            'success' => true,
            'message' => 'Saved '.$field.' for '.$pm->sku,
            'sku' => $pm->sku,
            'field' => $field,
            'value' => $pm->{$field},
        ]);
    }
}
