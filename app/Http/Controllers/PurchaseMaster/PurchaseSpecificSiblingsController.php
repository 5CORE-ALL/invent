<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase Specific Siblings page: Tabulator whose rows come from the CP Master data feed
 * (/product-master-data-view?lite=1). Each SKU can be given a "purchase sibling" name;
 * SKUs sharing the same name are siblings.
 */
class PurchaseSpecificSiblingsController extends Controller
{
    private const MAX = 120;

    private const TABLE = 'purchase_specific_siblings';

    public function __construct(private ComparisonController $comparison)
    {
    }

    public function index()
    {
        return view('purchase-master.purchase-specific-siblings.index', ['max' => self::MAX]);
    }

    /** Every saved sibling name, keyed by normalized SKU. */
    public function data()
    {
        if (! Schema::hasTable(self::TABLE)) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase siblings table is missing — run php artisan migrate on the server.',
            ], 500);
        }

        $map = [];
        foreach (DB::table(self::TABLE)->get(['sku', 'name']) as $row) {
            $map[self::normalizeSku((string) $row->sku)] = (string) $row->name;
        }

        return response()->json(['success' => true, 'max' => self::MAX, 'siblings' => $map]);
    }

    /**
     * Set the purchase sibling name for one or many SKUs.
     * Body: { skus: string[], name: string (<= 120 chars, blank clears) }
     */
    public function save(Request $request)
    {
        $validated = $request->validate([
            'skus' => 'required|array|min:1|max:5000',
            'skus.*' => 'required|string|max:191',
            'name' => 'nullable|string|max:'.self::MAX,
        ], [
            'name.max' => 'Purchase sibling name can be at most '.self::MAX.' characters.',
        ]);

        if (! Schema::hasTable(self::TABLE)) {
            return response()->json([
                'success' => false,
                'message' => 'Purchase siblings table is missing — run php artisan migrate on the server.',
            ], 500);
        }

        $name = trim((string) preg_replace('/\s+/u', ' ', (string) ($validated['name'] ?? '')));
        $user = Auth::user()?->name ?? 'N/A';

        // De-duplicate SKUs by normalized key (first spelling wins).
        $skus = [];
        foreach ($validated['skus'] as $sku) {
            $sku = trim($sku);
            if ($sku !== '') {
                $skus[self::normalizeSku($sku)] ??= $sku;
            }
        }

        $existing = [];
        foreach (DB::table(self::TABLE)->get() as $row) {
            $key = self::normalizeSku((string) $row->sku);
            if (isset($skus[$key])) {
                $existing[$key] = $row;
            }
        }

        // Reuse the spelling of an existing sibling name that only differs by case.
        if ($name !== '') {
            $match = DB::table(self::TABLE)->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('name');
            if ($match) {
                $name = (string) $match;
            }
        }

        $updated = 0;
        DB::transaction(function () use ($skus, $existing, $name, $user, &$updated) {
            foreach ($skus as $key => $sku) {
                $row = $existing[$key] ?? null;

                if ($name === '') {
                    if ($row) {
                        DB::table(self::TABLE)->where('id', $row->id)->delete();
                        $updated++;
                    }
                    continue;
                }

                if ($row) {
                    if ((string) $row->name === $name) {
                        continue;
                    }
                    DB::table(self::TABLE)->where('id', $row->id)->update([
                        'name' => $name,
                        'updated_by' => $user,
                        'updated_at' => now(),
                    ]);
                } else {
                    DB::table(self::TABLE)->insert([
                        'sku' => $sku,
                        'name' => $name,
                        'updated_by' => $user,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $updated++;
            }
        });

        return response()->json([
            'success' => true,
            'message' => $updated.' SKU(s) updated.',
            'updated' => $updated,
            'skus' => array_values($skus),
            'name' => $name,
        ]);
    }

    /**
     * Copy the Critical specs of `from_sku` onto `to_skus` (this SKU and/or its siblings).
     * Specs a destination already has are skipped, never overwritten.
     * Body: { from_sku: string, to_skus: string[] }
     */
    public function copySpecs(Request $request)
    {
        $validated = $request->validate([
            'from_sku' => 'required|string|max:191',
            'to_skus' => 'required|array|min:1|max:500',
            'to_skus.*' => 'required|string|max:191',
        ]);

        @set_time_limit(300);
        $user = Auth::user()?->name ?? 'N/A';
        $out = $this->comparison->copyCriticalSpecs($validated['from_sku'], $validated['to_skus'], $user);

        if (! $out['ok']) {
            return response()->json(['success' => false, 'message' => $out['message']], 422);
        }

        $copiedSkus = collect($out['results'])->where('status', 'copied');
        $skippedSkus = collect($out['results'])->where('status', 'skipped');
        $failedSkus = collect($out['results'])->where('status', 'failed');

        $message = $copiedSkus->count().' SKU(s) updated ('.$copiedSkus->sum('copied').' spec(s) copied)';
        if ($skippedSkus->count()) {
            $message .= ', '.$skippedSkus->count().' already had every spec';
        }
        if ($failedSkus->count()) {
            $message .= ', '.$failedSkus->count().' failed';
        }

        return response()->json([
            'success' => true,
            'message' => $message.'.',
            'source_specs' => $out['source_specs'],
            'results' => $out['results'],
        ]);
    }

    private static function normalizeSku(string $sku): string
    {
        return strtoupper(trim(str_replace("\u{00a0}", ' ', $sku)));
    }
}
