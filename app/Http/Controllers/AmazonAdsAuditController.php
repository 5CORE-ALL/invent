<?php

namespace App\Http\Controllers;

use App\Models\AmazonAdsAuditHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AmazonAdsAuditController extends Controller
{
    /** SP campaign reports table — same source as /amazon-ads/all default view. */
    private const SP_TABLE = 'amazon_sp_campaign_reports';

    /** A campaign is "green" while its latest audit entry is newer than this many days. */
    private const GREEN_WINDOW_DAYS = 30;

    public function index()
    {
        return view('amazon_ads.amz_ads_audit');
    }

    /**
     * One row per distinct SP campaign with L30 Cvr / CPC (same overlays as /amazon-ads/all)
     * plus its audit history and current dot status. Sorted oldest-audited (or never audited) first.
     */
    public function data(): JsonResponse
    {
        if (! Schema::hasTable(self::SP_TABLE)) {
            return response()->json(['data' => []]);
        }

        $hasAdType = Schema::hasColumn(self::SP_TABLE, 'ad_type');
        $hasCost = Schema::hasColumn(self::SP_TABLE, 'cost');
        $hasSpend = Schema::hasColumn(self::SP_TABLE, 'spend');
        $hasPurch30 = Schema::hasColumn(self::SP_TABLE, 'purchases30d');
        $hasPurch = Schema::hasColumn(self::SP_TABLE, 'purchases');

        // Latest L30 row id per campaign (+ ad_type).
        $sub = DB::table(self::SP_TABLE)
            ->whereRaw("UPPER(TRIM(report_date_range)) = 'L30'");
        if ($hasAdType) {
            $sub->selectRaw('MAX(id) AS max_id')->groupBy('campaign_id', 'ad_type');
        } else {
            $sub->selectRaw('MAX(id) AS max_id')->groupBy('campaign_id');
        }
        $ids = $sub->pluck('max_id')->filter()->map(fn ($v) => (int) $v)->all();

        $rows = $ids === [] ? collect() : DB::table(self::SP_TABLE)->whereIn('id', $ids)->get();

        // Audit history grouped by campaign_id (oldest first).
        $historyByCampaign = AmazonAdsAuditHistory::orderBy('created_at')
            ->get()
            ->groupBy(fn ($h) => (string) $h->campaign_id);

        $now = Carbon::now();
        $data = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            $campaignId = isset($r['campaign_id']) ? trim((string) $r['campaign_id']) : '';
            $campaignName = isset($r['campaignName']) ? (string) $r['campaignName'] : '';

            $clicks = isset($r['clicks']) && is_numeric($r['clicks']) ? (float) $r['clicks'] : null;
            $spend = null;
            if ($hasCost && isset($r['cost']) && is_numeric($r['cost'])) {
                $spend = (float) $r['cost'];
            } elseif ($hasSpend && isset($r['spend']) && is_numeric($r['spend'])) {
                $spend = (float) $r['spend'];
            }
            $sold = null;
            if ($hasPurch30 && isset($r['purchases30d']) && is_numeric($r['purchases30d'])) {
                $sold = (float) $r['purchases30d'];
            } elseif ($hasPurch && isset($r['purchases']) && is_numeric($r['purchases'])) {
                $sold = (float) $r['purchases'];
            }

            $cvr = ($sold !== null && $clicks !== null && $clicks > 0) ? round(($sold / $clicks) * 100, 2) : null;
            $cpc = ($spend !== null && $clicks !== null && $clicks > 0) ? round($spend / $clicks, 2) : null;

            $history = $historyByCampaign->get($campaignId, collect());
            $historyArr = [];
            foreach ($history as $h) {
                $historyArr[] = [
                    'fixed' => (bool) $h->fixed,
                    'details' => (string) $h->details,
                    'created_at' => optional($h->created_at)->format('Y-m-d H:i'),
                ];
            }
            $latest = $history->last();
            $latestAt = $latest && $latest->created_at ? $latest->created_at : null;
            $isGreen = $latestAt !== null && $latestAt->gt($now->copy()->subDays(self::GREEN_WINDOW_DAYS));

            $data[] = [
                'campaign_id' => $campaignId,
                'campaign_name' => $campaignName,
                'cvr' => $cvr,
                'cpc' => $cpc,
                'link' => route('amazon.ads.all').'?search='.rawurlencode($campaignName),
                'dot' => $isGreen ? 'green' : 'red',
                'latest_audit_at' => $latestAt ? $latestAt->format('Y-m-d H:i') : null,
                'latest_audit_ts' => $latestAt ? $latestAt->getTimestamp() : 0,
                'history' => $historyArr,
            ];
        }

        // Oldest audited (or never audited, ts = 0) on top.
        usort($data, fn ($a, $b) => $a['latest_audit_ts'] <=> $b['latest_audit_ts']);

        return response()->json(['data' => $data]);
    }

    /**
     * Record an audit submission for a campaign. Both "fixed" and "details" are mandatory.
     * Returns the refreshed history + dot state for the row.
     */
    public function save(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => ['required', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'fixed' => ['required', 'boolean'],
            'details' => ['required', 'string'],
        ]);

        AmazonAdsAuditHistory::create([
            'campaign_id' => $validated['campaign_id'],
            'campaign_name' => $validated['campaign_name'] ?? null,
            'fixed' => (bool) $validated['fixed'],
            'details' => $validated['details'],
            'user_id' => Auth::id(),
            'created_at' => Carbon::now(),
        ]);

        $history = AmazonAdsAuditHistory::where('campaign_id', $validated['campaign_id'])
            ->orderBy('created_at')
            ->get();

        $historyArr = $history->map(fn ($h) => [
            'fixed' => (bool) $h->fixed,
            'details' => (string) $h->details,
            'created_at' => optional($h->created_at)->format('Y-m-d H:i'),
        ])->all();

        $latest = $history->last();
        $latestAt = $latest && $latest->created_at ? $latest->created_at : null;
        $isGreen = $latestAt !== null && $latestAt->gt(Carbon::now()->subDays(self::GREEN_WINDOW_DAYS));

        return response()->json([
            'ok' => true,
            'dot' => $isGreen ? 'green' : 'red',
            'latest_audit_at' => $latestAt ? $latestAt->format('Y-m-d H:i') : null,
            'history' => $historyArr,
        ]);
    }
}
