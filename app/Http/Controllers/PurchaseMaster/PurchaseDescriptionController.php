<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ComparisonHistory;
use App\Models\ProductMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase Description page: lists/saves the per-SKU purchase description
 * (same `comparison_purchase_descriptions` table the Purchase Comparison form uses).
 */
class PurchaseDescriptionController extends Controller
{
    private const MAX = 180;

    private const TABLE = 'comparison_purchase_descriptions';

    public function index()
    {
        return view('purchase-master.purchase-description.index', ['max' => self::MAX]);
    }

    /**
     * Every saved purchase description, keyed by normalized SKU (trim + upper, NBSP -> space).
     */
    public function data()
    {
        if (! Schema::hasTable(self::TABLE)) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase description table is missing — run php artisan migrate on the server.',
            ], 500);
        }

        $map = [];
        foreach (DB::table(self::TABLE)->get(['sku', 'description']) as $row) {
            $map[self::normalizeSku((string) $row->sku)] = (string) $row->description;
        }

        return response()->json(['success' => true, 'max' => self::MAX, 'descriptions' => $map]);
    }

    /**
     * Save one description for one or many SKUs.
     * Body: { skus: string[], description: string (<= 180 chars, blank clears) }
     */
    public function saveBulk(Request $request)
    {
        $validated = $request->validate([
            'skus' => 'required|array|min:1|max:5000',
            'skus.*' => 'required|string|max:191',
            'description' => 'nullable|string|max:'.self::MAX,
        ], [
            'description.max' => 'Purchase description can be at most '.self::MAX.' characters.',
        ]);

        if (! Schema::hasTable(self::TABLE)) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase description table is missing — run php artisan migrate on the server.',
            ], 500);
        }

        $description = trim((string) ($validated['description'] ?? ''));
        $user = Auth::user()?->name ?? 'N/A';

        // De-duplicate SKUs by normalized key (first spelling wins).
        $skus = [];
        foreach ($validated['skus'] as $sku) {
            $sku = trim($sku);
            if ($sku === '') {
                continue;
            }
            $skus[self::normalizeSku($sku)] ??= $sku;
        }

        $existing = [];
        foreach (DB::table(self::TABLE)->get() as $row) {
            $key = self::normalizeSku((string) $row->sku);
            if (isset($skus[$key])) {
                $existing[$key] = $row;
            }
        }

        $parents = ProductMaster::query()
            ->whereIn('sku', array_values($skus))
            ->pluck('parent', 'sku')
            ->all();

        $updated = 0;
        DB::transaction(function () use ($skus, $existing, $description, $user, $parents, &$updated) {
            foreach ($skus as $key => $sku) {
                $row = $existing[$key] ?? null;
                $old = (string) ($row->description ?? '');

                if ($row) {
                    if ($old === $description) {
                        continue;
                    }
                    DB::table(self::TABLE)->where('id', $row->id)->update([
                        'description' => $description,
                        'updated_by' => $user,
                        'updated_at' => now(),
                    ]);
                } else {
                    if ($description === '') {
                        continue;
                    }
                    DB::table(self::TABLE)->insert([
                        'sku' => $sku,
                        'description' => $description,
                        'updated_by' => $user,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $updated++;

                try {
                    ComparisonHistory::logChange($sku, (string) ($parents[$sku] ?? ''), 'purchase_description', $old, $description, $user);
                } catch (\Throwable $e) {
                    // History is best-effort; never block the save.
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => $updated.' SKU(s) updated.',
            'updated' => $updated,
            'skus' => array_values($skus),
            'description' => $description,
        ]);
    }

    private static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }
}
