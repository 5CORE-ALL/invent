<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Summary by SKU" / "Summary by Parent" pages. Open QC / Packaging issues are grouped per supplier,
 * so SKUs that share a parent (siblings) only roll up together when they come from the same supplier.
 */
class QcIssuesSummaryController extends Controller
{
    public function page(Request $request, string $mode)
    {
        return view('customer-care.qc_issues_summary', [
            'mode' => $mode,
            'period' => QcIssuesSupplierWiseController::periodKey($request),
            'supplier' => trim((string) $request->query('supplier', '')) ?: null,
        ]);
    }

    public function data(Request $request, string $mode): JsonResponse
    {
        $period = QcIssuesSupplierWiseController::periodKey($request);
        // ?supplier=NAME limits the page to one supplier (opened from that supplier's detail tab).
        $only = mb_strtoupper(trim((string) $request->query('supplier', '')));

        $days = QcIssuesSupplierWiseController::PERIODS[$period];
        $sold = $this->soldBySku($days);
        $parentSkus = $mode === 'parent' ? $this->skusByParent() : [];

        // Common issues added by hand (the + button), per row.
        $manual = [];
        if (Schema::hasTable('qc_summary_common_issues')) {
            foreach (DB::table('qc_summary_common_issues')->where('mode', $mode)->orderBy('id')->get(['id', 'row_key', 'text']) as $m) {
                $manual[$m->row_key][] = ['id' => (int) $m->id, 'text' => $m->text];
            }
        }

        $data = [];
        foreach ($this->rows($mode, $days) as $key => $row) {
            if ($only !== '' && ! str_starts_with($key, $only.'||')) {
                continue;
            }
            $issues = $row['issues'];
            $isos = array_values(array_filter(array_column($issues, 'date_iso')));
            sort($isos);
            $counts = ['QC' => 0, 'Packaging' => 0];
            $kinds = [];
            foreach ($issues as $i) {
                foreach (array_keys($counts) as $dept) {
                    if (str_contains($i['department'], $dept)) {
                        $counts[$dept]++;
                    }
                }
                $kind = trim($i['what_happened'].' '.$i['issue']);
                if ($kind !== '') {
                    $kinds[$kind] = ($kinds[$kind] ?? 0) + 1;
                }
            }
            arsort($kinds);

            $totalSold = $this->totalSold($mode, $row['label'], $sold, $parentSkus);
            $data[] = [
                'key' => $key,
                'label' => $row['label'],
                'parent' => $row['parent'],
                'supplier' => $row['supplier'],
                'skus' => $row['skus'],
                'sku_count' => count($row['skus']),
                'total_sold' => $totalSold,
                'issue_count' => count($issues),
                // issue count / total sold, as a percentage (null when nothing sold in the period)
                'issue_pct' => $totalSold > 0 ? round(count($issues) / $totalSold * 100, 2) : null,
                'qc_count' => $counts['QC'],
                'pkg_count' => $counts['Packaging'],
                'first_date' => $isos ? Carbon::parse($isos[0])->format('d-m-Y') : '',
                'last_date' => $isos ? Carbon::parse(end($isos))->format('d-m-Y') : '',
                'last_iso' => $isos ? end($isos) : '',
                'top_issues' => array_slice(array_keys($kinds), 0, 3),
                'manual_issues' => $manual[$key] ?? [],
                // The row's issues, for the per-issue Action buttons (IG / edit / archive).
                'issues' => array_map(static fn ($i) => [
                    'id' => $i['id'], 'sku' => $i['sku'], 'date' => $i['date'], 'department' => $i['department'],
                    'what_happened' => $i['what_happened'], 'issue' => $i['issue'], 'issue_remark' => $i['issue_remark'],
                    'action' => $i['action'], 'action_remark' => $i['action_remark'],
                    'root_cause_fixed' => $i['root_cause_fixed'], 'root_cause_fixed_remark' => $i['root_cause_fixed_remark'],
                ], $issues),
            ];
        }

        usort($data, fn ($a, $b) => [$b['issue_count'], $a['label']] <=> [$a['issue_count'], $b['label']]);

        return response()->json(['data' => $data]);
    }

    /** Add a common issue by hand to one SKU / parent row. */
    public function addCommonIssue(Request $request, string $mode): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:1000',
            'text' => 'required|string|max:255',
        ]);
        $text = trim($validated['text']);
        if ($text === '') {
            return response()->json(['message' => 'Enter the common issue.'], 422);
        }
        if (! Schema::hasTable('qc_summary_common_issues')) {
            return response()->json(['message' => 'Run php artisan migrate first.'], 500);
        }
        if (! isset($this->rows($mode)[$validated['key']])) {
            return response()->json(['message' => 'Row not found.'], 404);
        }

        $hash = md5($mode.'|'.$validated['key']);
        $dupe = DB::table('qc_summary_common_issues')->where('key_hash', $hash)->whereRaw('LOWER(text) = ?', [mb_strtolower($text)])->exists();
        if ($dupe) {
            return response()->json(['message' => 'That common issue is already added.'], 422);
        }

        $user = $request->user();
        $id = DB::table('qc_summary_common_issues')->insertGetId([
            'mode' => $mode,
            'key_hash' => $hash,
            'row_key' => $validated['key'],
            'text' => $text,
            'created_by' => trim((string) ($user?->name ?? '')) ?: 'System',
            'created_by_user_id' => $user?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'id' => $id, 'text' => $text]);
    }

    /** Remove a common issue that was added by hand. */
    public function removeCommonIssue(string $mode, int $id): JsonResponse
    {
        if (Schema::hasTable('qc_summary_common_issues')) {
            DB::table('qc_summary_common_issues')->where('id', $id)->where('mode', $mode)->delete();
        }

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, array{label: string, parent: string, supplier: string, skus: list<string>, issues: list<array<string, mixed>>}>
     */
    private function rows(string $mode, ?int $days = null): array
    {
        $rows = [];
        foreach (app(QcIssuesSupplierWiseController::class)->groupsBySupplier($days) as $supplierKey => $group) {
            foreach ($group['issues'] as $i) {
                if (! empty($i['ignored'])) {
                    continue; // "Ignore - Not supplier fault" issues are left out of supplier summaries
                }
                $parent = $i['parent'] !== '' ? $i['parent'] : $i['sku'];
                $label = $mode === 'sku' ? $i['sku'] : $parent;
                $key = $supplierKey.'||'.mb_strtoupper($label);
                if (! isset($rows[$key])) {
                    $rows[$key] = [
                        'label' => $label,
                        'parent' => $mode === 'sku' ? $i['parent'] : $parent,
                        'supplier' => $group['supplier'],
                        'skus' => [],
                        'issues' => [],
                    ];
                }
                if (! in_array($i['sku'], $rows[$key]['skus'], true)) {
                    $rows[$key]['skus'][] = $i['sku'];
                }
                $rows[$key]['issues'][] = $i;
            }
        }

        return $rows;
    }

    /** SKU with every non A-Z0-9 character removed, so "AB-1" and "AB 1" match across tables. */
    private function canon(string $sku): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper($sku)) ?? '';
    }

    /**
     * Units sold: by SKU the SKU itself; by parent every SKU under that parent in Product Master.
     *
     * @param  array<string, int>  $sold  canon SKU => units
     * @param  array<string, list<string>>  $parentSkus  UPPER(parent) => canon SKUs
     */
    private function totalSold(string $mode, string $label, array $sold, array $parentSkus): int
    {
        if ($mode === 'parent' && isset($parentSkus[mb_strtoupper($label)])) {
            $total = 0;
            foreach ($parentSkus[mb_strtoupper($label)] as $canon) {
                $total += $sold[$canon] ?? 0;
            }

            return $total;
        }

        return $sold[$this->canon($label)] ?? 0;
    }

    /** @return array<string, list<string>> UPPER(parent) => unique canon SKUs under it */
    private function skusByParent(): array
    {
        $out = [];
        $q = DB::table('product_master')->select('sku', 'parent');
        if (Schema::hasColumn('product_master', 'deleted_at')) {
            $q->whereNull('deleted_at');
        }
        foreach ($q->get() as $p) {
            $sku = strtoupper(trim((string) ($p->sku ?? '')));
            $parent = strtoupper(trim((string) ($p->parent ?? '')));
            if ($sku === '' || $parent === '' || str_starts_with($sku, 'PARENT')) {
                continue;
            }
            $out[$parent][$this->canon($sku)] = $this->canon($sku);
        }

        return array_map('array_values', $out);
    }

    /**
     * Units sold per canon SKU over the period (null = lifetime). Same sources as the QC / Packaging
     * reports: shopify_raw_orders for the dates it holds, sku_monthly_orders (whole months) for older ones.
     *
     * @return array<string, int>
     */
    private function soldBySku(?int $days): array
    {
        return Cache::remember('qc_summary_sold_v1:'.($days ?? 'life').':'.now('America/Los_Angeles')->format('Y-m-d-H'), 1800, function () use ($days) {
            $start = $days !== null ? Carbon::now('America/Los_Angeles')->subDays($days)->startOfDay() : null;
            $out = [];

            $rawMin = null;
            if (Schema::hasTable('shopify_raw_orders')) {
                $min = DB::table('shopify_raw_orders')->min('order_date');
                $rawMin = $min ? Carbon::parse($min)->startOfMonth() : null;
                $q = DB::table('shopify_raw_orders')->selectRaw('sku, SUM(quantity) as qty')->whereNotNull('sku')->where('sku', '!=', '');
                if ($start) {
                    $q->where('order_date', '>=', $start->toDateString());
                }
                foreach ($q->groupBy('sku')->get() as $r) {
                    $k = $this->canon((string) $r->sku);
                    if ($k !== '') {
                        $out[$k] = ($out[$k] ?? 0) + (int) $r->qty;
                    }
                }
            }

            if (Schema::hasTable('sku_monthly_orders')) {
                $monthStart = "STR_TO_DATE(CONCAT(year,'-',month,'-01'), '%Y-%c-%d')";
                $q = DB::table('sku_monthly_orders')->selectRaw('sku, SUM(order_count) as qty')->whereNotNull('sku');
                if ($rawMin) {
                    $q->whereRaw("{$monthStart} < ?", [$rawMin->toDateString()]); // months raw orders do not cover
                }
                if ($start) {
                    $q->whereRaw("{$monthStart} >= ?", [$start->copy()->startOfMonth()->toDateString()]);
                }
                foreach ($q->groupBy('sku')->get() as $r) {
                    $k = $this->canon((string) $r->sku);
                    if ($k !== '') {
                        $out[$k] = ($out[$k] ?? 0) + (int) $r->qty;
                    }
                }
            }

            return $out;
        });
    }
}
