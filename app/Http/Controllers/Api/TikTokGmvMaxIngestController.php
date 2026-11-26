<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TikTokGmvAdsSyncService;
use App\Services\TikTokGmvMaxCampaignStore;
use App\Services\TikTokGmvMaxReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives GMV Max spend per TikTok product from a job that runs outside India
 * (TikTok blocks this server's IP). Protected by a shared secret header.
 */
class TikTokGmvMaxIngestController extends Controller
{
    public function ingest(Request $request, TikTokGmvAdsSyncService $sync): JsonResponse
    {
        $expected = trim((string) config('services.tiktok_ads.gmv_ingest_key'));
        $given = (string) $request->header('X-Ingest-Key', '');
        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'range' => ['required', 'in:L30,L1'],
            'rows' => ['present', 'array', 'max:50000'],
        ]);

        $rows = [];
        foreach ($data['rows'] as $key => $value) {
            // Accept {"itemId": spend} or [{"item_id": "...", "spend": 1.2}, ...]
            if (is_array($value)) {
                $itemId = (string) ($value['item_id'] ?? '');
                $spend = (float) ($value['spend'] ?? 0);
            } else {
                $itemId = (string) $key;
                $spend = (float) $value;
            }
            if ($itemId === '' || ! preg_match('/^\d{5,25}$/', $itemId) || $spend <= 0) {
                continue;
            }
            $rows[$itemId] = ($rows[$itemId] ?? 0) + $spend;
        }

        TikTokGmvMaxReportService::storeUploadedSpend($data['range'], $rows);
        $applied = $sync->applyUploadedGmvMaxSpend($data['range']);

        return response()->json([
            'ok' => true,
            'range' => $data['range'],
            'received_items' => count($rows),
            'applied_to_table' => $applied,
        ]);
    }

    /**
     * Campaign-level totals (cost, SKU orders, gross revenue, ROI) like the Ads Manager export.
     */
    public function campaigns(Request $request): JsonResponse
    {
        $expected = trim((string) config('services.tiktok_ads.gmv_ingest_key'));
        $given = (string) $request->header('X-Ingest-Key', '');
        if ($expected === '' || $given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $data = $request->validate([
            'range' => ['required', 'in:'.implode(',', TikTokGmvMaxCampaignStore::RANGES)],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
            'campaigns' => ['present', 'array', 'max:5000'],
        ]);

        $saved = TikTokGmvMaxCampaignStore::replaceRange(
            $data['range'],
            $data['start_date'] ?? null,
            $data['end_date'] ?? null,
            $data['campaigns']
        );

        return response()->json(['ok' => true, 'range' => $data['range'], 'campaigns_saved' => $saved]);
    }
}
