<?php

namespace App\Http\Controllers\AdvertisementMaster;

use App\Http\Controllers\Controller;
use App\Models\VariationAdsFlag;
use App\Models\VariationAdsFlagDaily;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class VariationsAdsController extends Controller
{
    /** Channel checkbox columns. Absence of a stored row means the default (green / checked). */
    private const COLS = ['amz_kw', 'amz_pt', 'ebay2', 'google_shop'];

    public function index()
    {
        return view('advertisement-master.variations_ads');
    }

    /**
     * One row per distinct parent, each carrying its child SKUs as Tabulator data-tree
     * children (_children). Every row also carries the on/off state of the four channel
     * checkboxes (default = green / true when no state is stored yet).
     */
    public function data(): JsonResponse
    {
        if (! Schema::hasTable('product_master')) {
            return response()->json(['data' => []]);
        }

        $rows = DB::table('product_master')
            ->select('parent', 'sku')
            ->orderBy('parent')
            ->orderBy('sku')
            ->get();

        $childrenByParent = [];
        $parentsDisplay = [];
        foreach ($rows as $r) {
            $sku = trim((string) ($r->sku ?? ''));
            if ($sku === '' || Str::startsWith(strtoupper($sku), 'PARENT')) {
                continue;
            }
            $parentDisplay = preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? '')));
            if ($parentDisplay === '') {
                continue;
            }
            $pKey = strtoupper($parentDisplay);
            $parentsDisplay[$pKey] = $parentDisplay;
            $childrenByParent[$pKey][] = $sku;
        }
        ksort($parentsDisplay);

        $flagsBySku = $this->flagsBySku();

        $data = [];
        foreach ($parentsDisplay as $pKey => $parentDisplay) {
            $children = [];
            foreach ($childrenByParent[$pKey] ?? [] as $sku) {
                $children[] = array_merge([
                    'name' => $sku,
                    'sku' => $sku,
                    'is_parent' => false,
                ], $this->flagsForSku($sku, $flagsBySku));
            }

            $parentSku = 'PARENT '.$parentDisplay;
            $data[] = array_merge([
                'name' => $parentDisplay,
                'sku' => $parentSku,
                'is_parent' => true,
                '_children' => $children,
            ], $this->flagsForSku($parentSku, $flagsBySku));
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Toggle / set one channel checkbox for a SKU, then refresh today's date-wise snapshot.
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255'],
            'col_key' => ['required', 'string', 'in:'.implode(',', self::COLS)],
            'checked' => ['required', 'boolean'],
        ]);

        VariationAdsFlag::updateOrCreate(
            ['sku' => $validated['sku'], 'col_key' => $validated['col_key']],
            [
                'checked' => (bool) $validated['checked'],
                'user_id' => Auth::id(),
                'updated_at' => Carbon::now(),
            ]
        );

        $this->snapshotToday();

        return response()->json(['ok' => true]);
    }

    /**
     * Date-wise green counts per column for the trend graph.
     * Returns { labels: [...dates], series: { amz_kw: [...], ... } }.
     */
    public function history(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $days = max(1, min($days, 365));

        // Make sure today's point reflects the current state even if nothing changed today.
        $this->snapshotToday();

        $since = Carbon::now()->subDays($days - 1)->startOfDay();

        $rows = VariationAdsFlagDaily::where('snapshot_date', '>=', $since->toDateString())
            ->orderBy('snapshot_date')
            ->get();

        // Build the ordered list of dates in the window.
        $labels = [];
        $cursor = $since->copy();
        $today = Carbon::now()->startOfDay();
        while ($cursor->lte($today)) {
            $labels[] = $cursor->toDateString();
            $cursor->addDay();
        }

        // Index snapshots by date+col for quick fill, carrying the last known value forward.
        $greenByDateCol = [];
        $totalByDateCol = [];
        foreach ($rows as $row) {
            $d = $row->snapshot_date instanceof Carbon ? $row->snapshot_date->toDateString() : (string) $row->snapshot_date;
            $greenByDateCol[$d][$row->col_key] = (int) $row->green_count;
            $totalByDateCol[$d][$row->col_key] = (int) $row->total_count;
        }

        $series = [];
        $seriesUnchecked = [];
        foreach (self::COLS as $col) {
            $green = [];
            $unchecked = [];
            $lastGreen = null;
            $lastTotal = null;
            foreach ($labels as $d) {
                if (isset($greenByDateCol[$d][$col])) {
                    $lastGreen = $greenByDateCol[$d][$col];
                    $lastTotal = $totalByDateCol[$d][$col] ?? $lastTotal;
                }
                $green[] = $lastGreen; // null until the first snapshot for this column
                $unchecked[] = ($lastGreen === null || $lastTotal === null) ? null : max(0, $lastTotal - $lastGreen);
            }
            $series[$col] = $green;
            $seriesUnchecked[$col] = $unchecked;
        }

        return response()->json([
            'labels' => $labels,
            'series' => $series,
            'series_unchecked' => $seriesUnchecked,
        ]);
    }

    /**
     * Current stored flag state keyed by sku => [col_key => bool].
     *
     * @return array<string, array<string, bool>>
     */
    private function flagsBySku(): array
    {
        $out = [];
        VariationAdsFlag::all()->each(function ($f) use (&$out) {
            $out[(string) $f->sku][(string) $f->col_key] = (bool) $f->checked;
        });

        return $out;
    }

    /**
     * Flag values for one SKU with defaults applied (missing = false / red cross).
     *
     * @param  array<string, array<string, bool>>  $flagsBySku
     * @return array<string, bool>
     */
    private function flagsForSku(string $sku, array $flagsBySku): array
    {
        $stored = $flagsBySku[$sku] ?? [];
        $out = [];
        foreach (self::COLS as $col) {
            $out[$col] = array_key_exists($col, $stored) ? (bool) $stored[$col] : false;
        }

        return $out;
    }

    /**
     * Recompute and upsert today's green (checked) counts per column. Default is off/cross, so
     * green = number of stored rows explicitly checked; unchecked = universe − green.
     */
    private function snapshotToday(): void
    {
        $total = $this->universeSkuCount();
        $today = Carbon::now()->toDateString();

        $checkedByCol = VariationAdsFlag::where('checked', true)
            ->selectRaw('col_key, COUNT(*) AS c')
            ->groupBy('col_key')
            ->pluck('c', 'col_key')
            ->all();

        foreach (self::COLS as $col) {
            $green = min($total, (int) ($checkedByCol[$col] ?? 0));
            VariationAdsFlagDaily::updateOrCreate(
                ['snapshot_date' => $today, 'col_key' => $col],
                ['green_count' => $green, 'total_count' => $total, 'created_at' => Carbon::now()]
            );
        }
    }

    /**
     * Number of rows that render checkboxes: distinct parents + their child SKUs.
     */
    private function universeSkuCount(): int
    {
        if (! Schema::hasTable('product_master')) {
            return 0;
        }

        $rows = DB::table('product_master')->select('parent', 'sku')->get();

        $parents = [];
        $children = 0;
        foreach ($rows as $r) {
            $sku = trim((string) ($r->sku ?? ''));
            if ($sku === '' || Str::startsWith(strtoupper($sku), 'PARENT')) {
                continue;
            }
            $parentDisplay = strtoupper(preg_replace('/\s+/', ' ', trim((string) ($r->parent ?? ''))));
            if ($parentDisplay === '') {
                continue;
            }
            $parents[$parentDisplay] = true;
            $children++;
        }

        return count($parents) + $children;
    }
}
