<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\AmazonDatasheet;
use App\Models\AmazonDataView;
use App\Models\AmazonProductReview;
use App\Models\AmazonSkuCompetitor;
use App\Models\AmzCvrAuditHistory;
use App\Models\AmzCvrIssueType;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AmzCvrIssuesController extends Controller
{
    /** @var list<string> */
    private const RESERVED_ISSUE_KEYS = [
        'pricing',
        'compliance',
        'missing_listing',
        'advertisement',
        'other',
    ];

    public function index(): View
    {
        return view('market-places.amz_cvr_issues', [
            'customIssueTypes' => $this->customIssueTypesPayload(),
        ]);
    }

    public function issueTypes(): JsonResponse
    {
        return response()->json([
            'data' => $this->customIssueTypesPayload(),
        ]);
    }

    public function storeIssueType(Request $request): JsonResponse
    {
        if (! Schema::hasTable('amz_cvr_issue_types')) {
            return response()->json([
                'success' => false,
                'message' => 'Issue types table is missing. Run migrations first.',
            ], 500);
        }

        $validated = $request->validate([
            'label' => 'required|string|max:200',
            'assignee_user_id' => 'required|exists:users,id',
        ]);

        $label = AmzCvrIssueType::normalizeLabel($validated['label']);
        if ($label === '') {
            return response()->json([
                'success' => false,
                'message' => 'Issue name is required.',
            ], 422);
        }

        $user = User::query()->find($validated['assignee_user_id']);
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Assignee user not found.',
            ], 422);
        }

        $dup = AmzCvrIssueType::query()
            ->whereRaw('LOWER(label) = ?', [strtolower($label)])
            ->exists();
        if ($dup) {
            return response()->json([
                'success' => false,
                'message' => 'An issue with this name already exists.',
            ], 422);
        }

        $issueKey = AmzCvrIssueType::makeIssueKey($label);
        if (in_array($issueKey, self::RESERVED_ISSUE_KEYS, true)
            || in_array(preg_replace('/^custom_/', '', $issueKey), self::RESERVED_ISSUE_KEYS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'This issue name conflicts with a built-in option.',
            ], 422);
        }

        $maxOrder = (int) AmzCvrIssueType::query()->max('sort_order');
        $row = AmzCvrIssueType::create([
            'issue_key' => $issueKey,
            'label' => $label,
            'assignee_user_id' => (int) $user->id,
            'assignee_email' => (string) $user->email,
            'is_active' => true,
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Issue added for future task allotment.',
            'issue' => $this->formatIssueType($row, $user),
        ]);
    }

    public function destroyIssueType(int $id): JsonResponse
    {
        if (! Schema::hasTable('amz_cvr_issue_types')) {
            return response()->json(['success' => false, 'message' => 'Issue types table is missing.'], 500);
        }

        $row = AmzCvrIssueType::query()->find($id);
        if (! $row) {
            return response()->json(['success' => false, 'message' => 'Issue not found.'], 404);
        }

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Custom issue removed.',
            'issue_key' => $row->issue_key,
        ]);
    }

    public function storeAuditHistory(Request $request): JsonResponse
    {
        if (! Schema::hasTable('amz_cvr_audit_histories')) {
            return response()->json([
                'success' => false,
                'message' => 'Audit history table is missing. Run migrations first.',
            ], 500);
        }

        $validated = $request->validate([
            'sku' => 'required|string|max:255',
            'task_count' => 'required|integer|min:1|max:50',
            'cvr_l30' => 'nullable|numeric|min:0|max:1000',
        ]);

        $user = Auth::user();
        $row = AmzCvrAuditHistory::create([
            'sku' => trim($validated['sku']),
            'user_id' => $user?->id,
            'user_name' => $user?->name ?: ($user?->email ?: 'Unknown'),
            'task_count' => (int) $validated['task_count'],
            'cvr_l30' => array_key_exists('cvr_l30', $validated) && $validated['cvr_l30'] !== null
                ? round((float) $validated['cvr_l30'], 2)
                : null,
        ]);

        return response()->json([
            'success' => true,
            'history' => $this->formatAuditHistory($row),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customIssueTypesPayload(): array
    {
        if (! Schema::hasTable('amz_cvr_issue_types')) {
            return [];
        }

        try {
            return AmzCvrIssueType::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->with(['assignee:id,name,email'])
                ->get()
                ->map(fn (AmzCvrIssueType $row) => $this->formatIssueType($row, $row->assignee))
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('AmzCvrIssues: failed loading custom issue types', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatIssueType(AmzCvrIssueType $row, ?User $user = null): array
    {
        $user = $user ?: $row->assignee;

        return [
            'id' => (int) $row->id,
            'key' => (string) $row->issue_key,
            'label' => (string) $row->label,
            'email' => (string) ($row->assignee_email ?: ($user->email ?? '')),
            'user_id' => $user
                ? (int) $user->id
                : ($row->assignee_user_id ? (int) $row->assignee_user_id : null),
            'name' => $user ? (string) $user->name : null,
            'custom' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAuditHistory(AmzCvrAuditHistory $row): array
    {
        $dt = $row->created_at;

        return [
            'id' => (int) $row->id,
            'sku' => (string) $row->sku,
            'user' => (string) ($row->user_name ?: 'Unknown'),
            'user_id' => $row->user_id ? (int) $row->user_id : null,
            'task_count' => (int) $row->task_count,
            'cvr_l30' => $row->cvr_l30 !== null ? round((float) $row->cvr_l30, 2) : null,
            'date_key' => $dt ? $dt->format('Y-m-d') : '',
            'date_label' => $dt ? strtoupper($dt->format('j M')) : '',
            'created_at' => $dt ? $dt->toIso8601String() : null,
            'sort_ts' => $dt ? $dt->getTimestamp() : 0,
        ];
    }

    /**
     * Latest audit history rows keyed by SKU.
     *
     * @param  list<string>  $skus
     * @return array<string, list<array<string, mixed>>>
     */
    private function auditHistoryBySku(array $skus): array
    {
        if ($skus === [] || ! Schema::hasTable('amz_cvr_audit_histories')) {
            return [];
        }

        try {
            $grouped = [];
            AmzCvrAuditHistory::query()
                ->whereIn('sku', $skus)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->each(function (AmzCvrAuditHistory $row) use (&$grouped) {
                    $sku = trim((string) $row->sku);
                    if ($sku === '') {
                        return;
                    }
                    if (! isset($grouped[$sku])) {
                        $grouped[$sku] = [];
                    }
                    // Keep recent entries; always latest on top.
                    if (count($grouped[$sku]) >= 10) {
                        return;
                    }
                    $grouped[$sku][] = $this->formatAuditHistory($row);
                });

            // Ensure each SKU list is latest-first even if DB collation differs.
            foreach ($grouped as $sku => $rows) {
                usort($grouped[$sku], static function (array $a, array $b): int {
                    return ((int) ($b['sort_ts'] ?? 0)) <=> ((int) ($a['sort_ts'] ?? 0))
                        ?: ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
                });
            }

            return $grouped;
        } catch (\Throwable $e) {
            Log::warning('AmzCvrIssues: failed loading audit history', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Tabulator JSON for #amz_cvr_issues.
     */
    public function data(): JsonResponse
    {
        $productMasters = ProductMaster::query()
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(TRIM(sku)) NOT LIKE ?', ['PARENT%'])
            ->orderBy('parent')
            ->orderBy('sku')
            ->get(['id', 'parent', 'sku', 'main_image', 'Values']);

        $skus = $productMasters->pluck('sku')->filter()->unique()->values()->all();
        $shopifyBySku = ShopifySku::mapByProductSkus($skus);
        $amazonDatasheetsBySku = AmazonDatasheet::groupedByNormalizedSku();
        $auditHistoryBySku = $this->auditHistoryBySku($skus);

        // NR/REQ from amazon_data_view (NRL field) — used for Missing L (ML) badge
        $nrBySku = [];
        if ($skus !== [] && Schema::hasTable('amazon_data_view')) {
            try {
                AmazonDataView::query()
                    ->whereIn('sku', $skus)
                    ->get(['sku', 'value'])
                    ->each(function ($row) use (&$nrBySku) {
                        $skuKey = trim((string) ($row->sku ?? ''));
                        if ($skuKey === '') {
                            return;
                        }
                        $raw = is_array($row->value)
                            ? $row->value
                            : (is_string($row->value) ? json_decode($row->value, true) : null);
                        $nrl = is_array($raw) ? strtoupper(trim((string) ($raw['NRL'] ?? $raw['NR'] ?? ''))) : '';
                        if ($nrl === 'NRL' || $nrl === 'NR') {
                            $nrBySku[$skuKey] = 'NRL';
                        } else {
                            $nrBySku[$skuKey] = 'REQ';
                        }
                    });
            } catch (\Throwable $e) {
                Log::warning('AmzCvrIssues: failed loading amazon_data_view NR', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $shipBySkuKey = [];
        foreach ($productMasters as $pmShipRow) {
            $shipVals = $this->decodeValues($pmShipRow->Values);
            $ownShip = isset($shipVals['ship']) ? (float) $shipVals['ship'] : 0.0;
            $shipKey = $this->normalizeSkuKeyForShipLookup($pmShipRow->sku);
            if ($shipKey !== '') {
                $shipBySkuKey[$shipKey] = $ownShip;
            }
        }

        $lmpLowestLookup = collect();
        $lmpDetailsLookup = collect();
        try {
            $lmpLookups = AmazonSkuCompetitor::buildGroupedLookup('amazon');
            $lmpLowestLookup = $lmpLookups['lowest'];
            $lmpDetailsLookup = $lmpLookups['details'];
        } catch (\Throwable $e) {
            Log::warning('AmzCvrIssues: failed loading LMP data', [
                'error' => $e->getMessage(),
            ]);
        }

        $amzReviewsBySku = [];
        if (Schema::hasTable('amazon_product_reviews')) {
            try {
                AmazonProductReview::query()
                    ->where(function ($q) {
                        $q->where('channel', 'Amazon')->orWhereNull('channel')->orWhere('channel', '');
                    })
                    ->whereNotNull('sku')
                    ->get(['sku', 'product_rating', 'review_count', 'source'])
                    ->each(function ($rr) use (&$amzReviewsBySku) {
                        $k = strtoupper(trim(str_replace("\xC2\xA0", ' ', (string) $rr->sku)));
                        if ($k === '') {
                            return;
                        }
                        $amzReviewsBySku[$k] = $rr;
                        $compact = AmazonDatasheet::normalizeSkuForLookup($k);
                        if ($compact !== '' && $compact !== $k) {
                            $amzReviewsBySku[$compact] = $rr;
                        }
                    });
            } catch (\Throwable $e) {
                Log::warning('AmzCvrIssues: failed loading amazon_product_reviews', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $data = [];
        foreach ($productMasters as $pm) {
            $sku = trim((string) ($pm->sku ?? ''));
            if ($sku === '') {
                continue;
            }

            $skuClean = strtoupper(str_replace("\xC2\xA0", ' ', $sku));
            $skuLookupKey = AmazonDatasheet::normalizeSkuForLookup($sku);
            $amazonSheetKey = $skuLookupKey;

            $amazonSheet = AmazonDatasheet::pickBestForProductSku(
                $sku,
                $amazonDatasheetsBySku->get($amazonSheetKey)
                    ?? $amazonDatasheetsBySku->get($skuLookupKey)
                    ?? $amazonDatasheetsBySku->get($skuClean)
                    ?? $amazonDatasheetsBySku->get($sku)
            );

            $amzRev = $amzReviewsBySku[$sku]
                ?? $amzReviewsBySku[$skuClean]
                ?? $amzReviewsBySku[$amazonSheetKey]
                ?? $amzReviewsBySku[$skuLookupKey]
                ?? null;

            $aL30 = $amazonSheet ? (float) ($amazonSheet->units_ordered_l30 ?? 0) : 0;
            $sess30 = $amazonSheet ? (float) ($amazonSheet->sessions_l30 ?? 0) : 0;
            $sess7 = $amazonSheet ? (float) ($amazonSheet->sessions_l7 ?? 0) : 0;
            $cvrL30 = $sess30 > 0 ? round(($aL30 / $sess30) * 100, 2) : 0;
            $price = $amazonSheet ? (float) ($amazonSheet->price ?? 0) : 0;

            $shopify = $shopifyBySku[$sku] ?? null;
            $inv = (float) ($shopify->inv ?? 0);
            if ($inv < 1) {
                continue;
            }
            $ovL30 = (float) ($shopify->quantity ?? 0);
            $dilPct = $inv > 0 ? round(($ovL30 / $inv) * 100, 2) : 0;

            $values = $this->decodeValues($pm->Values);
            $lp = 0.0;
            foreach ($values as $k => $v) {
                if (strtolower((string) $k) === 'lp') {
                    $lp = (float) $v;
                    break;
                }
            }
            $ownShip = isset($values['ship']) ? (float) $values['ship'] : 0.0;
            $labelQty = $this->extractLabelQtyFromValues($values);
            $ship = $this->resolveMultiPackageShipCost($sku, $ownShip, $labelQty, $shipBySkuKey);

            // GROI% = ((price × 0.80 - ship - lp) / lp) × 100 — same as Analytics Amz
            $groi = $lp > 0
                ? round((($price * 0.80 - $ship - $lp) / $lp) * 100, 2)
                : 0;

            $lmpKey = AmazonSkuCompetitor::normalizeSkuKey($sku);
            $lowestLmp = $lmpLowestLookup->get($lmpKey);
            $lmpPrice = ($lowestLmp && isset($lowestLmp->price) && is_numeric($lowestLmp->price))
                ? (float) $lowestLmp->price
                : null;
            $lmpEntries = $lmpDetailsLookup->get($lmpKey);
            $lmpEntriesTotal = $lmpEntries instanceof \Illuminate\Support\Collection
                ? $lmpEntries->count()
                : 0;

            $nr = $nrBySku[$sku] ?? 'REQ';
            $history = $auditHistoryBySku[$sku] ?? [];

            $data[] = [
                'Parent' => trim((string) ($pm->parent ?? '')),
                'image_path' => $pm->main_image ?: null,
                '(Child) sku' => $sku,
                'price' => $price,
                'lmp_price' => $lmpPrice,
                'lmp_entries_total' => $lmpEntriesTotal,
                'A_L30' => $aL30,
                'Sess30' => $sess30,
                'Sess7' => $sess7,
                'CVR_L30' => $cvrL30,
                'INV' => $inv,
                'L30' => $ovL30,
                'E Dil%' => $dilPct,
                'GROI%' => $groi,
                'NR' => $nr,
                'is_missing_amazon' => $amazonSheet ? false : true,
                'amz_avg_rating' => $amzRev && $amzRev->product_rating !== null
                    ? (float) $amzRev->product_rating
                    : null,
                'amz_review_count' => $amzRev ? (int) ($amzRev->review_count ?? 0) : null,
                'amz_reviews_source' => $amzRev->source ?? null,
                'audit_history' => $history,
                'audit_history_latest' => $history[0] ?? null,
                'audit_history_ts' => isset($history[0]['sort_ts']) ? (int) $history[0]['sort_ts'] : 0,
                'audit_history_dates' => array_values(array_unique(array_filter(array_map(
                    static fn ($h) => (string) ($h['date_key'] ?? ''),
                    $history
                )))),
            ];
        }

        return response()->json(['data' => $data]);
    }

    private function decodeValues($values): array
    {
        if (is_array($values)) {
            return $values;
        }
        if (is_string($values) && $values !== '') {
            $decoded = json_decode($values, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function normalizeSkuKeyForShipLookup(?string $sku): string
    {
        if ($sku === null) {
            return '';
        }

        return strtoupper(str_replace(' ', '', str_replace("\xC2\xA0", ' ', trim($sku))));
    }

    private function extractLabelQtyFromValues(array $values): int
    {
        $raw = $values['label_qty'] ?? $values['Label QTY'] ?? $values['Label_QTY'] ?? null;
        if ($raw === null || $raw === '') {
            return 0;
        }

        return is_numeric($raw) ? (int) $raw : 0;
    }

    /**
     * @param  array<string, float>  $shipBySkuKey
     */
    private function resolveMultiPackageShipCost(string $sku, float $ownShip, int $labelQty, array $shipBySkuKey): float
    {
        if ($labelQty < 2) {
            return round($ownShip, 2);
        }

        $components = preg_split('/\s*\+\s*/', trim(str_replace("\xC2\xA0", ' ', $sku))) ?: [];
        $components = array_values(array_filter(array_map(static function ($part) {
            return trim((string) $part);
        }, $components)));

        if (count($components) >= 2) {
            $parts = array_slice($components, 0, $labelQty);
            $sum = 0.0;
            $found = 0;
            foreach ($parts as $comp) {
                $key = $this->normalizeSkuKeyForShipLookup($comp);
                if ($key !== '' && array_key_exists($key, $shipBySkuKey)) {
                    $sum += (float) $shipBySkuKey[$key];
                    $found++;
                }
            }
            if ($found > 0) {
                return round($sum, 2);
            }
        }

        return round($ownShip * $labelQty, 2);
    }
}
