<?php

namespace App\Http\Controllers\CustomerCare;

use App\Http\Controllers\CustomerCare\Concerns\HasOptionalOrderNumberField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LabelIssuesController extends IssueBoardControllerBase
{
    use HasOptionalOrderNumberField;

    protected function viewName(): string
    {
        return 'customer-care.label_issues';
    }

    protected function issuesTable(): string
    {
        return 'label_issue_issues';
    }

    protected function historyTable(): string
    {
        return 'label_issue_issue_histories';
    }

    protected function moduleKey(): string
    {
        return 'label_issues';
    }

    public function issuesIndex(): JsonResponse
    {
        $body = parent::issuesIndex()->getData(true);
        $body['data'] = $this->attachSkuQcFields($body['data'] ?? []);

        return response()->json($body);
    }

    public function historyIndex(): JsonResponse
    {
        $body = parent::historyIndex()->getData(true);
        $body['data'] = $this->attachSkuQcFields($body['data'] ?? []);

        return response()->json($body);
    }

    public function skuDetails(Request $request): JsonResponse
    {
        $response = parent::skuDetails($request);
        $body = $response->getData(true);
        if (empty($body['found'])) {
            return $response;
        }

        $enriched = $this->attachSkuQcFields([['sku' => $body['sku'] ?? '']]);
        $extra = $enriched[0] ?? [];

        return response()->json(array_merge($body, [
            'product_master_id' => $extra['product_master_id'] ?? null,
            'ctn_instructions' => $extra['ctn_instructions'] ?? '',
            'instructions_item_pkg' => $extra['instructions_item_pkg'] ?? '',
            'qc_enhance_issue' => $extra['qc_enhance_issue'] ?? '',
            'qc_enhance_action_req' => $extra['qc_enhance_action_req'] ?? '',
            'qc_enhance_status_remark' => $extra['qc_enhance_status_remark'] ?? '',
        ]));
    }

    /**
     * CTN Pkg, item pkg, and QC Enhance are SKU lookups (same sources as the
     * QC & Packing board), not columns stored on label_issue_issues.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function attachSkuQcFields(array $rows): array
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
        $skus = array_values($skus);

        $productMap = [];
        if ($skus !== [] && Schema::hasTable('product_master')) {
            $placeholders = implode(',', array_fill(0, count($skus), '?'));
            $products = DB::table('product_master')
                ->select('id', 'sku', 'Values')
                ->whereRaw("UPPER(TRIM(sku)) IN ({$placeholders})", $skus)
                ->get();
            foreach ($products as $product) {
                $productMap[strtoupper(trim((string) $product->sku))] = $product;
            }
        }

        $instrPkgByProductId = [];
        if ($productMap !== [] && Schema::hasTable('instructions_item_pkg')) {
            $productIds = [];
            foreach ($productMap as $product) {
                $productIds[] = (int) $product->id;
            }
            $pkgRows = DB::table('instructions_item_pkg')
                ->whereIn('product_master_id', $productIds)
                ->get(['product_master_id', 'instructions']);
            foreach ($pkgRows as $pkgRow) {
                $instrPkgByProductId[(int) $pkgRow->product_master_id] = mb_substr((string) ($pkgRow->instructions ?? ''), 0, 2000);
            }
        }

        $qcEnhanceBySku = [];
        if ($skus !== [] && Schema::hasTable('quality_enhance')) {
            $placeholders = implode(',', array_fill(0, count($skus), '?'));
            $qeRows = DB::table('quality_enhance')
                ->select('sku', 'values')
                ->whereRaw("UPPER(TRIM(sku)) IN ({$placeholders})", $skus)
                ->get();
            foreach ($qeRows as $qeRow) {
                $decoded = json_decode($qeRow->values ?? '', true);
                if (! is_array($decoded)) {
                    $decoded = [];
                }
                $qcEnhanceBySku[strtoupper(trim((string) $qeRow->sku))] = [
                    'issue' => (string) ($decoded['issue'] ?? ''),
                    'action_req' => (string) ($decoded['action_req'] ?? ''),
                    'status_remark' => (string) ($decoded['status_remark'] ?? ''),
                ];
            }
        }

        foreach ($rows as $i => $row) {
            $key = strtoupper(trim((string) ($row['sku'] ?? '')));
            $productMasterId = null;
            $ctnInstructions = '';
            $instructionsItemPkg = '';
            if (isset($productMap[$key])) {
                $product = $productMap[$key];
                $productMasterId = (int) $product->id;
                $values = json_decode($product->Values ?? '', true);
                if (is_array($values) && isset($values['ctn_instructions'])) {
                    $ctnInstructions = mb_substr((string) $values['ctn_instructions'], 0, 100);
                }
                $instructionsItemPkg = $instrPkgByProductId[$productMasterId] ?? '';
            }
            $qe = $qcEnhanceBySku[$key] ?? ['issue' => '', 'action_req' => '', 'status_remark' => ''];
            $rows[$i]['product_master_id'] = $productMasterId;
            $rows[$i]['ctn_instructions'] = $ctnInstructions;
            $rows[$i]['instructions_item_pkg'] = $instructionsItemPkg;
            $rows[$i]['qc_enhance_issue'] = $qe['issue'];
            $rows[$i]['qc_enhance_action_req'] = $qe['action_req'];
            $rows[$i]['qc_enhance_status_remark'] = $qe['status_remark'];
        }

        return $rows;
    }
}
