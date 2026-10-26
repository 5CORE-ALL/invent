<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QcAndPackingController extends Controller
{
    /**
     * Add image_url and supplier onto QC rows.
     * Image: Shopify, then product_master image_path, main_image, and image1.
     * Supplier: latest to-order name, then manufacturing, ready-to-ship, and transit.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    /**
     * "Issue?" values per SKU for the QC / Packaging board only.
     *
     * Same rows the /customer-care/qc-packing page shows: not archived and department
     * includes QC or Packaging (other departments share the table but are not on that page).
     * Returns { SKU_UPPER: [ { value, count } ... ] } newest value first.
     */
    public function whatHappenedBySku()
    {
        $boardDepartments = ['QC', 'Packaging'];

        $rows = DB::table('qc_and_packing_issues')
            ->where(function ($q) {
                $q->whereNull('is_archived')->orWhere('is_archived', false);
            })
            ->whereNotNull('what_happened')
            ->where('what_happened', '!=', '')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get(['sku', 'what_happened', 'department']);

        $bySku = [];
        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) ($row->sku ?? '')));
            $value = trim((string) $row->what_happened);
            if ($sku === '' || $value === '') {
                continue;
            }
            if (array_intersect($this->departmentValues($row->department), $boardDepartments) === []) {
                continue;
            }
            $key = mb_strtolower($value);
            if (! isset($bySku[$sku][$key])) {
                $bySku[$sku][$key] = ['value' => $value, 'count' => 0];
            }
            $bySku[$sku][$key]['count']++;
        }

        return response()->json([
            'success' => true,
            'data' => array_map(fn ($items) => array_values($items), $bySku),
        ]);
    }

    /**
     * OV (overall Shopify) units sold over the trailing 12 months, same source and month
     * window as the Forecast Analysis page: shopify_raw_orders for months it still holds,
     * sku_monthly_orders for older months.
     *
     * Returns { from, to, label, by_sku: {CANON_SKU: units}, by_parent: {PARENT_UPPER: units} }
     * where CANON_SKU is the SKU upper-cased with every non A-Z0-9 character removed.
     */
    public function sales12Months()
    {
        $data = Cache::remember('qc_pkg_sales_12m_v1:'.now('America/Los_Angeles')->format('Y-m-d-H'), 1800, function () {
            return $this->computeSales12Months();
        });

        return response()->json(array_merge(['success' => true], $data));
    }

    private function computeSales12Months(): array
    {
        $canon = fn ($sku) => preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $sku)) ?? '';

        $current = Carbon::now('America/Los_Angeles')->startOfMonth();
        $slots = [];
        for ($i = 11; $i >= 0; $i--) {
            $d = $current->copy()->subMonths($i);
            $slots[] = ['slot' => $d->year.'-'.$d->month, 'start' => $d->toDateString()];
        }
        $from = $slots[0]['start'];

        $rawMin = null;
        $raw = [];
        if (Schema::hasTable('shopify_raw_orders')) {
            $min = DB::table('shopify_raw_orders')->min('order_date');
            $rawMin = $min ? Carbon::parse($min)->startOfMonth()->toDateString() : null;
            $rows = DB::table('shopify_raw_orders')
                ->selectRaw('sku, YEAR(order_date) as y, MONTH(order_date) as m, SUM(quantity) as qty')
                ->where('order_date', '>=', $from)
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->groupByRaw('sku, YEAR(order_date), MONTH(order_date)')
                ->get();
            foreach ($rows as $r) {
                $k = $canon($r->sku);
                if ($k === '') {
                    continue;
                }
                $slot = ((int) $r->y).'-'.((int) $r->m);
                $raw[$k][$slot] = ($raw[$k][$slot] ?? 0) + (int) $r->qty;
            }
        }

        $hist = [];
        if (Schema::hasTable('sku_monthly_orders')) {
            $rows = DB::table('sku_monthly_orders')
                ->whereRaw("STR_TO_DATE(CONCAT(year,'-',month,'-01'), '%Y-%c-%d') >= ?", [$from])
                ->get(['sku', 'year', 'month', 'order_count']);
            foreach ($rows as $r) {
                $k = $canon($r->sku);
                if ($k === '') {
                    continue;
                }
                $slot = ((int) $r->year).'-'.((int) $r->month);
                $hist[$k][$slot] = ($hist[$k][$slot] ?? 0) + (int) $r->order_count;
            }
        }

        $bySku = [];
        foreach (array_unique(array_merge(array_keys($raw), array_keys($hist))) as $k) {
            $total = 0;
            foreach ($slots as $s) {
                $coveredByRaw = $rawMin !== null && $s['start'] >= $rawMin;
                $total += $coveredByRaw ? ($raw[$k][$s['slot']] ?? 0) : ($hist[$k][$s['slot']] ?? 0);
            }
            if ($total > 0) {
                $bySku[$k] = $total;
            }
        }

        $byParent = [];
        $seen = [];
        foreach (DB::table('product_master')->get(['sku', 'parent']) as $p) {
            $sku = strtoupper(trim((string) ($p->sku ?? '')));
            $parent = strtoupper(trim((string) ($p->parent ?? '')));
            if ($sku === '' || $parent === '' || str_starts_with($sku, 'PARENT')) {
                continue;
            }
            $k = $canon($sku);
            if (isset($seen[$parent][$k])) {
                continue;
            }
            $seen[$parent][$k] = true;
            $byParent[$parent] = ($byParent[$parent] ?? 0) + ($bySku[$k] ?? 0);
        }

        $last = $current->copy();

        return [
            'from' => $from,
            'to' => $last->copy()->endOfMonth()->toDateString(),
            'label' => Carbon::parse($from)->format('M Y').' – '.$last->format('M Y'),
            'by_sku' => (object) $bySku,
            'by_parent' => (object) $byParent,
        ];
    }

    /** Department column is a JSON array or a "|" / "," separated list. */
    private function departmentValues(mixed $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(fn ($x) => trim((string) $x), $decoded)));
        }

        return array_values(array_filter(array_map('trim', preg_split('/[|,]/', $raw) ?: [])));
    }

    /** Adds the latest supplier per SKU as `supplier` (same lookup the QC board uses). */
    public function attachSuppliers(array $rows): array
    {
        $skus = [];
        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku !== '') {
                $skus[$sku] = $sku;
            }
        }
        $suppliers = $this->supplierBySku(array_values($skus));
        foreach ($rows as $i => $row) {
            $rows[$i]['supplier'] = $suppliers[strtoupper(trim((string) ($row['sku'] ?? '')))] ?? '';
        }

        return $rows;
    }

    public function attachImages(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $skus = [];
        foreach ($rows as $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            if ($sku !== '') {
                $skus[$sku] = $sku;
            }
        }
        $urls = $this->urlsBySku(array_values($skus));
        $suppliers = $this->supplierBySku(array_values($skus));

        foreach ($rows as $i => $row) {
            $key = strtoupper(trim((string) ($row['sku'] ?? '')));
            $rows[$i]['image_url'] = $urls[$key] ?? null;
            $rows[$i]['supplier'] = $suppliers[$key] ?? '';
        }

        return $rows;
    }

    /**
     * Latest supplier name for each SKU: to-order, then manufacturing, ready-to-ship, and transit.
     *
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    private function supplierBySku(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $out = array_fill_keys($skus, '');
        $this->fillSupplier($out, $skus, 'to_order_analysis', 'sku', 'supplier_name');
        $this->fillSupplier($out, $skus, 'mfrg_progress', 'sku', 'supplier');
        $this->fillSupplier($out, $skus, 'ready_to_ship', 'sku', 'supplier');
        $this->fillSupplier($out, $skus, 'transit_container_details', 'our_sku', 'supplier_name');

        return $out;
    }

    /**
     * @param  array<string, string>  $out
     * @param  list<string>  $skus
     */
    private function fillSupplier(array &$out, array $skus, string $table, string $skuColumn, string $nameColumn): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $skuColumn) || ! Schema::hasColumn($table, $nameColumn)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $query = DB::table($table)->select($skuColumn, $nameColumn);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }
        $query->whereNotNull($nameColumn)
            ->whereRaw('TRIM('.$nameColumn.") != ''")
            ->whereRaw('UPPER(TRIM('.$skuColumn.")) IN ({$placeholders})", $skus);
        if (Schema::hasColumn($table, 'updated_at')) {
            $query->orderByDesc('updated_at');
        }
        if (Schema::hasColumn($table, 'id')) {
            $query->orderByDesc('id');
        }

        foreach ($query->get() as $row) {
            $key = strtoupper(trim((string) ($row->{$skuColumn} ?? '')));
            $name = trim((string) ($row->{$nameColumn} ?? ''));
            if ($key !== '' && $name !== '' && isset($out[$key]) && $out[$key] === '') {
                $out[$key] = $name;
            }
        }
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    private function urlsBySku(array $skus): array
    {
        if ($skus === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($skus), '?'));
        $shopifyBySku = [];
        if (Schema::hasTable('shopify_skus')) {
            $shopRows = DB::table('shopify_skus')
                ->select('sku', 'image_src')
                ->whereRaw("UPPER(TRIM(sku)) IN ({$placeholders})", $skus)
                ->get();
            foreach ($shopRows as $shopRow) {
                $key = strtoupper(trim((string) $shopRow->sku));
                if ($key !== '' && ! isset($shopifyBySku[$key])) {
                    $shopifyBySku[$key] = $shopRow->image_src;
                }
            }
        }

        $productsBySku = [];
        if (Schema::hasTable('product_master')) {
            $products = DB::table('product_master')
                ->select('sku', 'Values', 'main_image', 'image1')
                ->whereRaw("UPPER(TRIM(sku)) IN ({$placeholders})", $skus)
                ->get();
            foreach ($products as $product) {
                $productsBySku[strtoupper(trim((string) $product->sku))] = $product;
            }
        }

        $urls = [];
        foreach ($skus as $sku) {
            $product = $productsBySku[$sku] ?? null;
            $values = [];
            if ($product && is_string($product->Values ?? null) && trim($product->Values) !== '') {
                $decoded = json_decode($product->Values, true);
                if (is_array($decoded)) {
                    $values = $decoded;
                }
            }
            $url = $this->normalizeImage($shopifyBySku[$sku] ?? null)
                ?? $this->normalizeImage($values['image_path'] ?? null)
                ?? $this->normalizeImage($product?->main_image ?? null)
                ?? $this->normalizeImage($product?->image1 ?? null);
            if ($url !== null) {
                $urls[$sku] = $url;
            }
        }

        return $urls;
    }

    private function normalizeImage(mixed $path): ?string
    {
        $value = trim((string) ($path ?? ''));
        if ($value === '') {
            return null;
        }
        if (preg_match('/^(https?:)?\/\//i', $value) || str_starts_with($value, 'data:')) {
            return $value;
        }

        return '/'.ltrim($value, '/');
    }
}
