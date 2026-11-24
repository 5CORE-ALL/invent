<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\PriceIncreaseBoxSprice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Own data store of the "SPRICE $" box on /price-increase (SKU modal toolbar).
 * The box value is independent of the channel SPRICEs (e.g. Amazon analytics page): changing it here never
 * reads from, and a refresh never reloads it from, any channel page.
 */
class PriceIncreaseBoxSpriceController extends Controller
{
    private function key(string $sku): string
    {
        return strtoupper(trim(str_replace("\xc2\xa0", ' ', $sku)));
    }

    /** GET /price-increase-box-sprice?sku=... → { success, sprice|null } */
    public function show(Request $request): JsonResponse
    {
        $sku = $this->key((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }
        // Not migrated yet: behave as "no own value" so the page keeps working
        if (! Schema::hasTable('price_increase_box_sprices')) {
            return response()->json(['success' => true, 'sprice' => null, 'table_missing' => true]);
        }

        $row = PriceIncreaseBoxSprice::query()->where('sku', $sku)->first();

        return response()->json([
            'success' => true,
            'sprice' => $row && (float) $row->sprice > 0 ? round((float) $row->sprice, 2) : null,
        ]);
    }

    /** POST /price-increase-box-sprice { sku, sprice } (sprice empty / 0 clears the saved value) */
    public function save(Request $request): JsonResponse
    {
        $sku = $this->key((string) $request->input('sku', ''));
        if ($sku === '' || $sku === 'NOT LISTED') {
            return response()->json(['success' => false, 'message' => 'SKU is required'], 422);
        }
        if (! Schema::hasTable('price_increase_box_sprices')) {
            return response()->json([
                'success' => false,
                'message' => 'Table price_increase_box_sprices is missing — run php artisan migrate',
            ], 503);
        }

        $raw = $request->input('sprice');
        $price = is_numeric($raw) ? round((float) $raw, 2) : 0.0;

        if ($price > 0) {
            PriceIncreaseBoxSprice::query()->updateOrCreate(
                ['sku' => $sku],
                ['sprice' => $price, 'updated_by' => Auth::id()]
            );
        } else {
            PriceIncreaseBoxSprice::query()->where('sku', $sku)->delete();
        }

        return response()->json(['success' => true, 'sprice' => $price > 0 ? $price : null]);
    }
}
