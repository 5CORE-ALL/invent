<?php

namespace App\Http\Controllers\AmazonAds;

use App\Http\Controllers\Controller;
use App\Models\AmazonAdsCampaignAudit;
use App\Services\AmazonAdsCampaignAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Audit column on /amazon-ads/all: run the AI audit for one campaign, list dot statuses, open a stored report.
 */
class AmazonAdsCampaignAuditController extends Controller
{
    /**
     * One campaign per request. The grid loops over the selected rows, so a bulk run shows progress
     * and one slow or failed campaign never blocks the rest.
     */
    public function run(Request $request, AmazonAdsCampaignAuditService $service): JsonResponse
    {
        $data = $request->validate([
            'campaign_id' => ['required', 'string', 'max:64'],
            'channel' => ['nullable', 'in:sp,sb'],
            'campaign_name' => ['nullable', 'string', 'max:512'],
        ]);
        @set_time_limit(180);

        $audit = $service->audit(
            (string) ($data['channel'] ?? 'sp'),
            trim($data['campaign_id']),
            trim((string) ($data['campaign_name'] ?? '')),
            optional($request->user())->name
        );

        return response()->json([
            'ok' => $audit->status === AmazonAdsCampaignAudit::STATUS_DONE,
            'campaign_id' => (string) $audit->campaign_id,
            'status' => $service->statusPayload($audit),
            'message' => $audit->status === AmazonAdsCampaignAudit::STATUS_DONE ? 'Audit finished.' : (string) $audit->error,
        ]);
    }

    /**
     * Dot state for the campaigns currently on the grid.
     */
    public function statuses(Request $request, AmazonAdsCampaignAuditService $service): JsonResponse
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn ($v): string => trim((string) $v), (array) $request->input('ids', [])),
            static fn (string $v): bool => $v !== ''
        )));

        return response()->json(['ok' => true, 'statuses' => (object) $service->statuses(array_slice($ids, 0, 1000))]);
    }

    /**
     * The stored report behind the dot.
     */
    public function report(Request $request, AmazonAdsCampaignAuditService $service): JsonResponse
    {
        $cid = trim((string) $request->query('campaign_id', ''));
        $channel = $request->query('channel') === 'sb' ? 'sb' : 'sp';
        if ($cid === '' || ! Schema::hasTable(AmazonAdsCampaignAudit::TABLE)) {
            return response()->json(['ok' => false, 'message' => 'No audit yet.'], 404);
        }
        $audit = AmazonAdsCampaignAudit::query()->where('channel', $channel)->where('campaign_id', $cid)->first();
        if ($audit === null) {
            return response()->json(['ok' => false, 'message' => 'No audit yet.'], 404);
        }

        return response()->json([
            'ok' => true,
            'campaign_id' => $cid,
            'campaign_name' => $audit->campaign_name,
            'status' => $service->statusPayload($audit),
            'report' => $audit->report,
            'signals' => $audit->facts['signals'] ?? [],
            'provider' => $audit->provider,
            'requested_by' => $audit->requested_by,
        ]);
    }
}
