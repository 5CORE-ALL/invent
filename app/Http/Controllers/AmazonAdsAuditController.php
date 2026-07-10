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

        $hasCost = Schema::hasColumn(self::SP_TABLE, 'cost');
        $hasSpend = Schema::hasColumn(self::SP_TABLE, 'spend');
        $hasPurch30 = Schema::hasColumn(self::SP_TABLE, 'purchases30d');
        $hasPurch = Schema::hasColumn(self::SP_TABLE, 'purchases');
        $hasSales30 = Schema::hasColumn(self::SP_TABLE, 'sales30d');
        $hasSales = Schema::hasColumn(self::SP_TABLE, 'sales');

        // Campaign universe = distinct campaigns that reported on the latest available daily date.
        // This mirrors the default /amazon-ads/all view (Calendar mode pinned to the latest day),
        // so the audit lists the same currently-active campaigns instead of every campaign that
        // ever had an L30 summary row.
        $universe = [];
        $latestDay = $this->latestDailyReportDay();
        if ($latestDay !== null) {
            $dailyRows = DB::table(self::SP_TABLE)
                ->whereRaw('CHAR_LENGTH(TRIM(report_date_range)) >= 10')
                ->whereRaw("LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
                ->whereRaw('LEFT(TRIM(report_date_range), 10) = ?', [$latestDay])
                ->get(['campaign_id', 'campaignName']);
            foreach ($dailyRows as $dr) {
                $cid = isset($dr->campaign_id) ? trim((string) $dr->campaign_id) : '';
                if ($cid === '') {
                    continue;
                }
                $name = isset($dr->campaignName) ? (string) $dr->campaignName : '';
                if (! array_key_exists($cid, $universe) || ($universe[$cid] === '' && $name !== '')) {
                    $universe[$cid] = $name;
                }
            }
        }

        // Latest L30 summary row per campaign — source for the Cvr / CPC / ACOS overlays (same slice as /amazon-ads/all).
        $l30Ids = DB::table(self::SP_TABLE)
            ->whereRaw("UPPER(TRIM(report_date_range)) = 'L30'")
            ->selectRaw('MAX(id) AS max_id')
            ->groupBy('campaign_id')
            ->pluck('max_id')->filter()->map(fn ($v) => (int) $v)->all();
        $l30ByCampaign = $l30Ids === [] ? collect() : DB::table(self::SP_TABLE)
            ->whereIn('id', $l30Ids)
            ->get()
            ->keyBy(fn ($row) => trim((string) ($row->campaign_id ?? '')));

        // Audit history grouped by campaign_id (oldest first).
        $historyByCampaign = AmazonAdsAuditHistory::orderBy('created_at')
            ->get()
            ->groupBy(fn ($h) => (string) $h->campaign_id);

        $now = Carbon::now();
        $data = [];
        foreach ($universe as $campaignId => $campaignName) {
            $metricRow = $l30ByCampaign->get($campaignId);
            $r = $metricRow ? (array) $metricRow : [];
            if ($campaignName === '' && isset($r['campaignName'])) {
                $campaignName = (string) $r['campaignName'];
            }

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

            $sales = null;
            if ($hasSales30 && isset($r['sales30d']) && is_numeric($r['sales30d'])) {
                $sales = (float) $r['sales30d'];
            } elseif ($hasSales && isset($r['sales']) && is_numeric($r['sales'])) {
                $sales = (float) $r['sales'];
            }
            // ACOS (%) = cost / sales * 100. When spend > 0 and sales = 0, ACOS is 100%.
            $acos = null;
            if ($spend !== null) {
                if ($sales !== null && $sales > 0) {
                    $acos = round(($spend / $sales) * 100, 2);
                } elseif ($spend > 0) {
                    $acos = 100.0;
                }
            }

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
                'spl30' => $spend !== null ? round($spend) : null,
                'cvr' => $cvr,
                'cpc' => $cpc,
                'acos' => $acos,
                'link' => $this->amazonAdsConsoleCampaignUrl($campaignId),
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
     * Deep-link to the Amazon Advertising console campaign editor for a Sponsored Products campaign.
     * The console addresses a campaign as /cm/sp/campaigns/{campaignId}; an account `entityId`
     * (config: services.amazon_ads.entity_id) is appended when configured so the link opens the
     * correct account directly instead of the account picker.
     */
    private function amazonAdsConsoleCampaignUrl(string $campaignId): string
    {
        $base = rtrim((string) config('services.amazon_ads.console_base', 'https://advertising.amazon.com'), '/');
        $url = $base.'/cm/sp/campaigns/'.rawurlencode($campaignId);

        $params = ['adProduct' => 'SPONSORED_PRODUCTS'];
        $entityId = trim((string) config('services.amazon_ads.entity_id', ''));
        if ($entityId !== '') {
            $params = ['entityId' => $entityId] + $params;
        }

        return $url.'?'.http_build_query($params);
    }

    /**
     * Latest calendar day stored in `report_date_range` (YYYY-MM-DD prefix rows only), capped at today.
     * Matches the default date the /amazon-ads/all grid pins its Calendar filter to.
     */
    private function latestDailyReportDay(): ?string
    {
        if (! Schema::hasColumn(self::SP_TABLE, 'report_date_range')) {
            return null;
        }

        $day = DB::table(self::SP_TABLE)
            ->whereRaw('CHAR_LENGTH(TRIM(report_date_range)) >= 10')
            ->whereRaw("LEFT(TRIM(report_date_range), 10) REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'")
            ->selectRaw('MAX(LEFT(TRIM(report_date_range), 10)) AS d')
            ->value('d');

        if (! is_string($day) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return null;
        }

        $today = Carbon::now(config('app.timezone'))->format('Y-m-d');

        return $day > $today ? $today : $day;
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
