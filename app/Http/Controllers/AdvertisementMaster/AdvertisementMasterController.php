<?php

namespace App\Http\Controllers\AdvertisementMaster;

use App\Http\Controllers\AmazonAdsController;
use App\Http\Controllers\AmazonAdsMissingController;
use App\Http\Controllers\Campaigns\Ebay2CampaignAdsController;
use App\Http\Controllers\Campaigns\Ebay3CampaignAdsController;
use App\Http\Controllers\Campaigns\EbayCampaignAdsController;
use App\Http\Controllers\Campaigns\GoogleSerpAdsMissingController;
use App\Http\Controllers\Campaigns\GoogleShoppingAdsMissingController;
use App\Http\Controllers\Campaigns\GoogleYoutubeAdsMissingController;
use App\Http\Controllers\Campaigns\Tiktok1AdsRawDataController;
use App\Http\Controllers\Campaigns\TiktokAdsMissingController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\MarketPlace\ShopifyAdsMasterController;
use App\Http\Controllers\Sales\AmazonSalesController;
use App\Models\AdvertisementMasterChannelLabel;
use App\Models\AdvertisementMasterCustomRow;
use App\Models\AdvertisementMasterHiddenRow;
use App\Models\AdvertisementMasterNrReq;
use App\Models\AmazonOrder;
use App\Models\ChannelMaster;
use App\Models\ChannelMasterCalculatedData;
use App\Support\Marketplace\MappingChannelCounts;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class AdvertisementMasterController extends Controller
{
    /**
     * Timezone the history snapshots are stamped in. Pacific
     * (America/Los_Angeles) auto-switches between PST and PDT, so "today"
     * always means the current Pacific business day.
     */
    public const SNAPSHOT_TIMEZONE = 'America/Los_Angeles';

    /**
     * Channel-name delimiter marking a nested "sub-row" (e.g. "Amazon · KW",
     * "Shopify · Facebook"). Top-level parents have no separator, so the
     * history total sums only those and never double-counts children.
     */
    public const SUBROW_SEPARATOR = ' · ';

    /** Pseudo-channel key used to store the combined S Sales snapshot. */
    private const SSALES_CHANNEL = '__ssales__';

    public function index(Request $request)
    {
        return view('advertisement-master.advertisement_master', [
            'mode' => $request->query('mode'),
            'demo' => $request->query('demo'),
        ]);
    }

    /**
     * Home Dashboard badges — same rollup as /advertisement-master header badges
     * (parent channels only; CVR / ACOS / TCOS / TOTAL SALES derived).
     * Reads the latest Pacific-day snapshot so the dashboard stays fast.
     *
     * @return array{
     *     active: int,
     *     spend: float,
     *     clicks: float,
     *     sold: float,
     *     sales: float,
     *     cvr: float,
     *     acos: int,
     *     tcos: int,
     *     ssales: float,
     *     snapshot_date: string|null,
     *     updated_at: \Carbon\Carbon|null
     * }
     */
    public static function dashboardBadgeTotals(): array
    {
        $empty = [
            'active' => 0,
            'spend' => 0.0,
            'clicks' => 0.0,
            'sold' => 0.0,
            'sales' => 0.0,
            'cvr' => 0.0,
            'acos' => 0,
            'tcos' => 0,
            'ssales' => 0.0,
            'snapshot_date' => null,
            'updated_at' => null,
        ];

        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('advertisement_master_metric_snapshots')) {
                return $empty;
            }

            $latestDate = DB::table('advertisement_master_metric_snapshots')->max('snapshot_date');
            if (! $latestDate) {
                return $empty;
            }

            $rows = DB::table('advertisement_master_metric_snapshots')
                ->where('snapshot_date', $latestDate)
                ->get(['channel', 'spend', 'clicks', 'sold', 'sales', 'active', 'updated_at']);

            $spend = 0.0;
            $clicks = 0.0;
            $sold = 0.0;
            $sales = 0.0;
            $active = 0.0;
            $ssales = 0.0;
            $updatedAt = null;

            foreach ($rows as $r) {
                $ch = (string) ($r->channel ?? '');
                if ($r->updated_at) {
                    $ts = Carbon::parse($r->updated_at);
                    if ($updatedAt === null || $ts->gt($updatedAt)) {
                        $updatedAt = $ts;
                    }
                }

                if ($ch === self::SSALES_CHANNEL) {
                    $ssales = (float) ($r->sales ?? 0);
                    continue;
                }

                // Sub-rows are slices of a parent — skip to avoid double-count.
                if ($ch === '' || str_contains($ch, self::SUBROW_SEPARATOR)) {
                    continue;
                }

                $spend += (float) ($r->spend ?? 0);
                $clicks += (float) ($r->clicks ?? 0);
                $sold += (float) ($r->sold ?? 0);
                $sales += (float) ($r->sales ?? 0);
                $active += (float) ($r->active ?? 0);
            }

            $cvr = $clicks > 0 ? ($sold / $clicks) * 100 : 0.0;
            $acos = $sales > 0
                ? (int) round(($spend / $sales) * 100)
                : ($spend > 0 ? 100 : 0);
            $tcos = $ssales > 0
                ? (int) round(($spend / $ssales) * 100)
                : ($spend > 0 ? 100 : 0);

            return [
                'active' => (int) round($active),
                'spend' => round($spend, 2),
                'clicks' => round($clicks, 0),
                'sold' => round($sold, 0),
                'sales' => round($sales, 2),
                'cvr' => round($cvr, 1),
                'acos' => $acos,
                'tcos' => $tcos,
                'ssales' => round($ssales, 2),
                'snapshot_date' => (string) $latestDate,
                'updated_at' => $updatedAt,
            ];
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master dashboard badges failed: '.$e->getMessage());

            return $empty;
        }
    }

    public function data(
        AmazonAdsController $amazonAds,
        EbayCampaignAdsController $ebayCampaignAds,
        Ebay2CampaignAdsController $ebay2CampaignAds,
        Ebay3CampaignAdsController $ebay3CampaignAds,
        ShopifyAdsMasterController $shopifyAdsMaster,
        Tiktok1AdsRawDataController $tiktok1Ads
    ) {
        try {
            $amazonNetSales = $this->amazonNetSales();
            $ebayNetSales = EbayCampaignAdsController::advertisementMasterNetSales();
            $ebay2NetSales = Ebay2CampaignAdsController::advertisementMasterNetSales();
            $ebay3NetSales = Ebay3CampaignAdsController::advertisementMasterNetSales();
            $shopifyNetSales = ShopifyAdsMasterController::advertisementMasterNetSales();
            $tiktokNetSales = Tiktok1AdsRawDataController::advertisementMasterNetSales();

            $rows = array_merge(
                $amazonAds->getAdvertisementMasterChannelRows(),
                $ebayCampaignAds->getAdvertisementMasterChannelRows(),
                $ebay2CampaignAds->getAdvertisementMasterChannelRows(),
                $ebay3CampaignAds->getAdvertisementMasterChannelRows(),
                $shopifyAdsMaster->getAdvertisementMasterChannelRows(),
                $tiktok1Ads->getAdvertisementMasterChannelRows()
            );

            $this->applyTcosToRows($rows, [
                'amazon' => $amazonNetSales,
                'ebay'   => $ebayNetSales,
                'ebay2'  => $ebay2NetSales,
                'ebay3'  => $ebay3NetSales,
                'shopify' => $shopifyNetSales,
                'tiktok' => $tiktokNetSales,
            ]);

            $totalNetSales = round(
                $amazonNetSales + $ebayNetSales + $ebay2NetSales + $ebay3NetSales + $shopifyNetSales + $tiktokNetSales,
                2
            );

            // Trend dots: compare each metric against the previous Pacific-day
            // snapshot (per channel). Read *before* today's snapshot write so
            // "previous" never means today. Spend + ACOS are inverted on the
            // frontend (a higher value is worse → red). New channels (e.g.
            // TikTok 1) get a seeded prior day so dots + history show on day one.
            $pacificToday  = Carbon::now(self::SNAPSHOT_TIMEZONE)->toDateString();
            $this->attachMissingAds($rows);
            $prevByChannel = $this->previousSnapshotByChannel($pacificToday);
            $this->seedMissingPriorSnapshots($rows, $pacificToday, $prevByChannel);
            $this->attachTrends($rows, $prevByChannel);

            // Persist today's snapshot so the trend dots + badge charts have
            // history. The snapshot table is flat (one row per channel), so
            // flatten the tree first, and store combined S Sales as its own
            // pseudo-channel so the TCOS / S SALES badges get a trend too. Never
            // let a snapshot write break the data feed.
            try {
                $this->snapshotChannels($this->flattenRows($rows), $totalNetSales);
            } catch (\Throwable $e) {
                \Log::warning('Advertisement Master snapshot failed: ' . $e->getMessage());
            }

            // Display-name overrides (Group / Channel) applied after snapshot so
            // history + links keep the original channel_key.
            $this->revertStandaloneTypeTotalLabels();
            $this->applyChannelLabels($rows);
            $this->wrapStandaloneChannelTotals($rows);
            $this->ensureSumRowTotalSuffix($rows);
            $this->attachCustomRows($rows);
            $this->attachMissingChannelMasterTotals($rows);
            $this->removeHiddenRows($rows);
            $this->attachChannelHrefs($rows);
            $this->attachNrReqs($rows);
            $this->attachViews($rows);
            $this->attachTSales($rows);
            $this->attachTotalRowAcos($rows, $prevByChannel);
            $this->attachMissingAds($rows);
            $this->attachMissingAdsTrends($rows, $prevByChannel);
            try {
                $this->snapshotMissingAds($this->flattenRows($rows));
            } catch (\Throwable $e) {
                \Log::warning('Advertisement Master missing-ads snapshot failed: '.$e->getMessage());
            }

            return response()->json([
                'status' => 200,
                'message' => 'Advertisement Master data fetched successfully',
                'data' => $rows,
                'amazon_net_sales' => $amazonNetSales,
                'ebay_net_sales' => $ebayNetSales,
                'ebay2_net_sales' => $ebay2NetSales,
                'ebay3_net_sales' => $ebay3NetSales,
                'shopify_net_sales' => $shopifyNetSales,
                'tiktok_net_sales' => $tiktokNetSales,
                'total_net_sales' => $totalNetSales,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Advertisement Master data failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'status' => 500,
                'message' => 'Failed to load Advertisement Master data',
                'data' => [],
                'amazon_net_sales' => 0,
                'ebay_net_sales' => 0,
                'ebay2_net_sales' => 0,
                'ebay3_net_sales' => 0,
                'shopify_net_sales' => 0,
                'tiktok_net_sales' => 0,
                'total_net_sales' => 0,
            ], 500);
        }
    }

    /**
     * Persist Channel + Type. Existing source rows store a display-name override
     * (channel_key stays the original name). "+" rows are stored as custom rows.
     */
    public function saveLabel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_key' => 'nullable|string|max:191',
            'group_name' => 'required|string|max:80',
            'channel_name' => 'required|string|max:191',
        ]);

        $key = trim((string) ($data['channel_key'] ?? ''));
        $group = trim($data['group_name']);
        $channel = trim($data['channel_name']);
        if ($group === '' || $channel === '') {
            return response()->json([
                'status' => 422,
                'message' => 'Channel and Type are required.',
            ], 422);
        }

        if ($key === '' || str_starts_with($key, 'custom:')) {
            return $this->saveCustomRow($key, $group, $channel);
        }

        if (! Schema::hasTable('advertisement_master_channel_labels')) {
            return response()->json([
                'status' => 503,
                'message' => 'Channel labels table is missing. Run migrations and try again.',
            ], 503);
        }

        AdvertisementMasterChannelLabel::query()->updateOrCreate(
            ['channel_key' => $key],
            [
                'group_name' => $group,
                'channel_name' => $channel,
            ]
        );

        return response()->json([
            'status' => 200,
            'message' => 'Saved.',
            'channel_key' => $key,
            'group_name' => $group,
            'channel_name' => $channel,
            'is_custom' => false,
        ]);
    }

    private function saveCustomRow(string $key, string $channelName, string $typeName): JsonResponse
    {
        if (! Schema::hasTable('advertisement_master_custom_rows')) {
            return response()->json([
                'status' => 503,
                'message' => 'Custom rows table is missing. Run migrations and try again.',
            ], 503);
        }

        $id = 0;
        if (str_starts_with($key, 'custom:')) {
            $id = (int) substr($key, 7);
        }

        $row = $id > 0
            ? AdvertisementMasterCustomRow::query()->find($id)
            : null;

        if ($row) {
            $row->fill([
                'channel_name' => $channelName,
                'type_name' => $typeName,
            ])->save();
        } else {
            $row = AdvertisementMasterCustomRow::query()->create([
                'channel_name' => $channelName,
                'type_name' => $typeName,
            ]);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Saved.',
            'channel_key' => 'custom:'.$row->id,
            'group_name' => $row->channel_name,
            'channel_name' => $row->type_name,
            'is_custom' => true,
        ]);
    }

    /**
     * Hide a source row (or delete a "+" custom row). Children are hoisted
     * so nested types stay visible.
     */
    public function deleteRow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_key' => 'required|string|max:191',
        ]);
        $key = trim($data['channel_key']);
        if ($key === '') {
            return response()->json([
                'status' => 422,
                'message' => 'Missing row.',
            ], 422);
        }

        if (str_starts_with($key, 'custom:')) {
            if (Schema::hasTable('advertisement_master_custom_rows')) {
                $id = (int) substr($key, 7);
                if ($id > 0) {
                    AdvertisementMasterCustomRow::query()->where('id', $id)->delete();
                }
            }

            return response()->json([
                'status' => 200,
                'message' => 'Deleted.',
                'channel_key' => $key,
            ]);
        }

        if (! Schema::hasTable('advertisement_master_hidden_rows')) {
            return response()->json([
                'status' => 503,
                'message' => 'Hidden rows table is missing. Run migrations and try again.',
            ], 503);
        }

        AdvertisementMasterHiddenRow::query()->updateOrCreate(
            ['channel_key' => $key],
            ['channel_key' => $key]
        );

        return response()->json([
            'status' => 200,
            'message' => 'Deleted.',
            'channel_key' => $key,
        ]);
    }

    /**
     * Persist R/N (REQ = green, NR = red) for a Type row.
     */
    public function saveNrReq(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel_key' => 'required|string|max:191',
            'nr_req' => 'required|string|in:REQ,NR',
        ]);
        $key = trim((string) $data['channel_key']);
        $nr = strtoupper(trim((string) $data['nr_req'])) === 'NR' ? 'NR' : 'REQ';
        if ($key === '') {
            return response()->json([
                'status' => 422,
                'message' => 'Missing row.',
            ], 422);
        }

        if (! Schema::hasTable('advertisement_master_nr_reqs')) {
            return response()->json([
                'status' => 503,
                'message' => 'R/N table is missing. Run migrations and try again.',
            ], 503);
        }

        AdvertisementMasterNrReq::query()->updateOrCreate(
            ['channel_key' => $key],
            ['nr_req' => $nr]
        );

        return response()->json([
            'status' => 200,
            'message' => 'Saved.',
            'channel_key' => $key,
            'nr_req' => $nr,
        ]);
    }

    /**
     * Overlay saved Group / Channel display names onto the live tree.
     * Leaves channel_key as the original name.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function applyChannelLabels(array &$rows): void
    {
        $labels = $this->channelLabelMap();
        $this->applyChannelLabelsWalk($rows, $labels);
    }

    /**
     * @return array<string, array{group_name: string, channel_name: string}>
     */
    private function channelLabelMap(): array
    {
        if (! Schema::hasTable('advertisement_master_channel_labels')) {
            return [];
        }

        $map = [];
        foreach (AdvertisementMasterChannelLabel::query()->get(['channel_key', 'group_name', 'channel_name']) as $row) {
            $key = trim((string) $row->channel_key);
            if ($key === '') {
                continue;
            }
            $map[$key] = [
                'group_name' => trim((string) $row->group_name),
                'channel_name' => trim((string) $row->channel_name),
            ];
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array{group_name: string, channel_name: string}>  $labels
     */
    private function applyChannelLabelsWalk(array &$rows, array $labels): void
    {
        foreach ($rows as &$row) {
            $key = (string) ($row['channel_key'] ?? $row['channel'] ?? '');
            $row['channel_key'] = $key;
            if ($key !== '' && isset($labels[$key])) {
                if ($labels[$key]['channel_name'] !== '') {
                    $row['channel'] = $labels[$key]['channel_name'];
                }
                if ($labels[$key]['group_name'] !== '') {
                    $row['channel_group'] = $labels[$key]['group_name'];
                }
            }
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->applyChannelLabelsWalk($row['_children'], $labels);
            }
        }
        unset($row);
    }

    /**
     * eBay / eBay 2 / eBay 3 / TikTok 1 were briefly saved as "* Total".
     * Restore their Type names so they stay regular rows under the new group totals.
     */
    private function revertStandaloneTypeTotalLabels(): void
    {
        if (! Schema::hasTable('advertisement_master_channel_labels')) {
            return;
        }

        foreach (['eBay', 'eBay 2', 'eBay 3', 'TikTok 1'] as $key) {
            $label = AdvertisementMasterChannelLabel::query()->where('channel_key', $key)->first();
            if (! $label) {
                continue;
            }
            $name = trim((string) $label->channel_name);
            if ($name !== '' && preg_match('/\s+Total$/i', $name)) {
                $label->channel_name = $key;
                $label->save();
            }
        }
    }

    /**
     * Fold standalone eBay and TikTok type rows under a yellow group-total parent.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function wrapStandaloneChannelTotals(array &$rows): void
    {
        $this->wrapRowsAsGroupTotal($rows, ['eBay', 'eBay 2', 'eBay 3'], [
            'channel' => 'eBay Total',
            'channel_key' => 'eBay Total',
            'channel_group' => 'eBay',
            'marketplace' => 'ebay',
            'source' => 'ebay_group_total',
        ]);
        $this->wrapRowsAsGroupTotal($rows, ['TikTok 1'], [
            'channel' => 'TikTok Total',
            'channel_key' => 'TikTok Total',
            'channel_group' => 'TikTok',
            'marketplace' => 'tiktok',
            'source' => 'tiktok_group_total',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $memberKeys
     * @param  array{channel: string, channel_key: string, channel_group: string, marketplace: string, source: string}  $parentMeta
     */
    private function wrapRowsAsGroupTotal(array &$rows, array $memberKeys, array $parentMeta): void
    {
        $members = [];
        $firstIndex = null;
        $kept = [];
        foreach ($rows as $row) {
            if ($this->rowMatchesChannelKeys($row, $memberKeys)) {
                if ($firstIndex === null) {
                    $firstIndex = count($kept);
                }
                $row['is_sub_row'] = true;
                $row['channel_group'] = $parentMeta['channel_group'];
                $key = trim((string) ($row['channel_key'] ?? ''));
                $name = trim((string) ($row['channel'] ?? ''));
                if (preg_match('/\s+Total$/i', $name)) {
                    $row['channel'] = $this->stripTotalSuffix($key !== '' ? $key : $name);
                }
                $members[] = $row;
            } else {
                $kept[] = $row;
            }
        }
        if ($members === []) {
            return;
        }

        $parent = $this->buildGroupTotalRow($members, $parentMeta);
        $parent['_children'] = $members;
        array_splice($kept, $firstIndex ?? count($kept), 0, [$parent]);
        $rows = $kept;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private function rowMatchesChannelKeys(array $row, array $keys): bool
    {
        foreach ([(string) ($row['channel_key'] ?? ''), (string) ($row['channel'] ?? '')] as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
            if (in_array($candidate, $keys, true) || in_array($this->stripTotalSuffix($candidate), $keys, true)) {
                return true;
            }
        }

        return false;
    }

    private function stripTotalSuffix(string $name): string
    {
        return trim((string) preg_replace('/\s+Total$/i', '', $name));
    }

    /**
     * @param  array<int, array<string, mixed>>  $members
     * @param  array{channel: string, channel_key: string, channel_group: string, marketplace: string, source: string}  $meta
     * @return array<string, mixed>
     */
    private function buildGroupTotalRow(array $members, array $meta): array
    {
        $spend = 0.0;
        $clicks = 0.0;
        $sold = 0.0;
        $sales = 0.0;
        $active = 0.0;
        foreach ($members as $member) {
            $spend += (float) ($member['spend'] ?? 0);
            $clicks += (float) ($member['clicks'] ?? 0);
            $sold += (float) ($member['sold'] ?? 0);
            $sales += (float) ($member['sales'] ?? 0);
            $active += (float) ($member['active'] ?? 0);
        }

        return [
            'channel' => $meta['channel'],
            'channel_key' => $meta['channel_key'],
            'channel_group' => $meta['channel_group'],
            'source' => $meta['source'],
            'marketplace' => $meta['marketplace'],
            'spend' => round($spend, 2),
            'clicks' => (int) round($clicks),
            'sold' => (int) round($sold),
            'sales' => round($sales, 2),
            'cvr' => $clicks > 0 ? round(($sold / $clicks) * 100, 1) : 0.0,
            'acos' => $sales > 0 ? (int) round(($spend / $sales) * 100) : ($spend > 0 ? 100 : 0),
            'tcos' => 0,
            'active' => (int) round($active),
            'views' => 0,
            'is_sub_row' => false,
            'is_group_total' => true,
        ];
    }

    /**
     * Yellow "{Channel} Total" rows for every Active Channel Master channel
     * that is not already represented on Advertisement Master.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachMissingChannelMasterTotals(array &$rows): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        $occupied = $this->collectOccupiedChannelKeys($rows);
        $seen = [];

        try {
            $channels = ChannelMaster::query()
                ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                ->orderBy('channel')
                ->get(['id', 'channel']);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master Channel Master lookup failed: '.$e->getMessage());

            return;
        }

        foreach ($channels as $channel) {
            $name = trim((string) ($channel->channel ?? ''));
            if ($name === '') {
                continue;
            }
            $norm = $this->normalizeChannelMatchKey($name);
            if ($norm === '' || isset($occupied[$norm]) || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;
            $rows[] = $this->emptyChannelTotalRow($name);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, true>
     */
    private function collectOccupiedChannelKeys(array $rows): array
    {
        $set = [];
        $walk = function (array $list) use (&$walk, &$set): void {
            foreach ($list as $row) {
                foreach (['channel_key', 'channel', 'channel_group'] as $field) {
                    $norm = $this->normalizeChannelMatchKey((string) ($row[$field] ?? ''));
                    if ($norm !== '') {
                        $set[$norm] = true;
                    }
                }
                if (! empty($row['_children']) && is_array($row['_children'])) {
                    $walk($row['_children']);
                }
            }
        };
        $walk($rows);

        return $set;
    }

    private function normalizeChannelMatchKey(string $name): string
    {
        $n = strtolower($this->stripTotalSuffix($name));
        $n = preg_replace('/[^a-z0-9]+/', '', $n) ?? '';
        if ($n === '') {
            return '';
        }

        $aliases = [
            'amz' => 'amazon',
            'ebay1' => 'ebay',
            'ebayone' => 'ebay',
            'ebaytwo' => 'ebay2',
            'ebaythree' => 'ebay3',
            'tiktokshop' => 'tiktok1',
            'tiktoks' => 'tiktok1',
            'tiktok' => 'tiktok1',
        ];

        return $aliases[$n] ?? $n;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyChannelTotalRow(string $channelName): array
    {
        $type = preg_match('/\bTotal$/i', $channelName)
            ? $channelName
            : $channelName.' Total';

        return [
            'channel' => $type,
            'channel_key' => $type,
            'channel_group' => $channelName,
            'source' => 'channel_master',
            'marketplace' => $this->normalizeChannelMatchKey($channelName),
            'spend' => 0.0,
            'clicks' => 0,
            'sold' => 0,
            'sales' => 0.0,
            'cvr' => 0.0,
            'acos' => 0,
            'tcos' => 0,
            'active' => 0,
            'views' => 0,
            'is_sub_row' => false,
            'is_group_total' => true,
            'is_sum_row' => true,
        ];
    }

    /**
     * Yellow total rows get a " Total" Type suffix and that name is saved.
     * Totals = top-level channel rows (eBay / eBay 2 / eBay 3 / TikTok 1)
     * plus any parent that has children (Amazon, Shopify, Facebook, …).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function ensureSumRowTotalSuffix(array &$rows): void
    {
        foreach ($rows as &$row) {
            $children = $row['_children'] ?? [];
            $hasChildren = is_array($children) && $children !== [];
            if ($hasChildren) {
                $key = trim((string) ($row['channel_key'] ?? $row['channel'] ?? ''));
                $name = trim((string) ($row['channel'] ?? ''));
                if ($key !== '' && $name !== '' && ! preg_match('/\bTotal$/i', $name)) {
                    $name .= ' Total';
                    $row['channel'] = $name;
                    $row['channel_key'] = $key;
                    $this->persistSumRowLabel($key, $name, $row);
                }
            }
            if ($hasChildren) {
                $this->ensureSumRowTotalSuffix($children);
                $row['_children'] = $children;
            }
        }
        unset($row);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function persistSumRowLabel(string $channelKey, string $typeName, array $row): void
    {
        if (! Schema::hasTable('advertisement_master_channel_labels')) {
            return;
        }

        try {
            $existing = AdvertisementMasterChannelLabel::query()
                ->where('channel_key', $channelKey)
                ->first();
            $group = trim((string) ($row['channel_group'] ?? ''));
            if ($group === '') {
                $group = trim((string) ($existing->group_name ?? ''));
            }
            if ($group === '') {
                $group = $this->inferChannelGroup($row, $channelKey);
            }

            AdvertisementMasterChannelLabel::query()->updateOrCreate(
                ['channel_key' => $channelKey],
                [
                    'group_name' => $group,
                    'channel_name' => $typeName,
                ]
            );
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master sum-row label save failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function inferChannelGroup(array $row, string $channelKey): string
    {
        $mp = strtolower((string) ($row['marketplace'] ?? ''));
        if ($mp === 'amazon') {
            return 'Amazon';
        }
        if (str_starts_with($mp, 'ebay')) {
            return 'eBay';
        }
        if ($mp === 'shopify') {
            return 'Shopify';
        }
        if ($mp === 'tiktok') {
            return 'TikTok';
        }

        $base = $channelKey;
        if (str_contains($base, self::SUBROW_SEPARATOR)) {
            $base = explode(self::SUBROW_SEPARATOR, $base, 2)[0];
        }

        return $base !== '' ? $base : 'Other';
    }

    /**
     * Append user-created Channel / Type rows. Nested under a matching parent
     * when one exists; otherwise they become their own top-level row.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachCustomRows(array &$rows): void
    {
        if (! Schema::hasTable('advertisement_master_custom_rows')) {
            return;
        }

        foreach (AdvertisementMasterCustomRow::query()->orderBy('id')->get() as $custom) {
            $channelName = trim((string) $custom->channel_name);
            $typeName = trim((string) $custom->type_name);
            if ($channelName === '' || $typeName === '') {
                continue;
            }

            $row = [
                'channel' => $typeName,
                'channel_key' => 'custom:'.$custom->id,
                'channel_group' => $channelName,
                'source' => 'custom',
                'is_custom' => true,
                'spend' => 0.0,
                'clicks' => 0,
                'sold' => 0,
                'sales' => 0.0,
                'cvr' => 0.0,
                'acos' => 0,
                'tcos' => 0,
                'active' => 0,
                'views' => 0,
                'is_sub_row' => true,
                'marketplace' => '',
            ];

            $attached = false;
            foreach ($rows as &$parent) {
                $parentGroup = trim((string) ($parent['channel_group'] ?? $parent['channel'] ?? ''));
                if (strcasecmp($parentGroup, $channelName) === 0) {
                    if (! isset($parent['_children']) || ! is_array($parent['_children'])) {
                        $parent['_children'] = [];
                    }
                    $parent['_children'][] = $row;
                    $attached = true;
                    break;
                }
            }
            unset($parent);

            if (! $attached) {
                $row['is_sub_row'] = false;
                $rows[] = $row;
            }
        }
    }

    /**
     * Drop hidden rows from the tree and hoist their children one level up.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function removeHiddenRows(array &$rows): void
    {
        $hidden = $this->hiddenChannelKeySet();
        if ($hidden === []) {
            return;
        }

        $rows = $this->filterHiddenRows($rows, $hidden);
    }

    /**
     * @return array<string, true>
     */
    private function hiddenChannelKeySet(): array
    {
        $hidden = [
            'Shopify · Facebook' => true,
            'Shopify · Instagram' => true,
        ];

        if (! Schema::hasTable('advertisement_master_hidden_rows')) {
            return $hidden;
        }

        foreach (AdvertisementMasterHiddenRow::query()->pluck('channel_key') as $key) {
            $key = trim((string) $key);
            if ($key !== '') {
                $hidden[$key] = true;
            }
        }

        return $hidden;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, true>  $hidden
     * @return array<int, array<string, mixed>>
     */
    private function filterHiddenRows(array $rows, array $hidden): array
    {
        $out = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['channel_key'] ?? $row['channel'] ?? ''));
            $children = [];
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $children = $this->filterHiddenRows($row['_children'], $hidden);
            }

            if ($key !== '' && isset($hidden[$key])) {
                foreach ($children as $child) {
                    $out[] = $child;
                }

                continue;
            }

            if ($children !== []) {
                $row['_children'] = $children;
            } else {
                unset($row['_children']);
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Amazon L30 store sales (Pacific rolling window) — same source as Channel Master
     * and the Amazon daily sales page. Used for the S Sales badge and TCOS.
     */
    private function amazonNetSales(): float
    {
        try {
            $yesterdayPacific = Carbon::yesterday('America/Los_Angeles');
            $endToday = $yesterdayPacific->copy()->endOfDay();
            $startAmazonWindow = $yesterdayPacific
                ->copy()
                ->subDays(AmazonSalesController::DAILY_SALES_WINDOW_DAYS - 1)
                ->startOfDay();

            return AmazonOrder::badgeTotalSalesByOrderDate($startAmazonWindow, $endToday);
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master Amazon net sales lookup failed: ' . $e->getMessage());

            return 0.0;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, float>  $netSalesByMarketplace
     */
    private function applyTcosToRows(array &$rows, array $netSalesByMarketplace): void
    {
        foreach ($rows as &$row) {
            $marketplace = (string) ($row['marketplace'] ?? 'amazon');
            $netSales = (float) ($netSalesByMarketplace[$marketplace] ?? 0);
            $spend = (float) ($row['spend'] ?? 0);
            $row['tcos'] = Ebay2CampaignAdsController::tcosPercent(
                $spend,
                $netSales,
                (float) ($row['sales'] ?? 0)
            );

            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->applyTcosToRows($row['_children'], $netSalesByMarketplace);
            }
        }
        unset($row);
    }

    /**
     * Flatten a nested `_children` tree into a single list (parents first,
     * then their children) so snapshotting can persist one row per channel.
     * The `_children` and `trend` keys are stripped from each emitted row.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function flattenRows(array $rows): array
    {
        $flat = [];
        foreach ($rows as $row) {
            $children = $row['_children'] ?? [];
            unset($row['_children'], $row['trend']);
            $flat[] = $row;
            if (! empty($children) && is_array($children)) {
                $flat = array_merge($flat, $this->flattenRows($children));
            }
        }

        return $flat;
    }

    /**
     * Save (upsert) every channel row into the history table for the current
     * Pacific (PDT/PST) business day. One row per (snapshot_date, channel),
     * so repeated page loads within the same Pacific day refresh the value
     * rather than piling up duplicates.
     *
     * @param  array<int, array<string, mixed>>  $rows  flattened channel rows
     */
    private function snapshotChannels(array $rows, float $netSales = 0.0): void
    {
        $now   = Carbon::now(self::SNAPSHOT_TIMEZONE);
        $today = $now->toDateString();

        foreach ($rows as $row) {
            $channel = (string) ($row['channel'] ?? '');
            if ($channel === '') {
                continue;
            }
            $this->saveSnapshotRow($today, $channel, $this->snapshotMeasures($row), $now);
        }

        // Combined S Sales kept as its own pseudo-channel row (the net-sales
        // figure lives in the `sales` column). Powers the TCOS / S SALES badge
        // trends; excluded from the channel totals in history().
        $this->saveSnapshotRow($today, self::SSALES_CHANNEL, ['sales' => $netSales], $now);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, float|int>
     */
    private function snapshotMeasures(array $row): array
    {
        $measures = [
            'spend' => (float) ($row['spend'] ?? 0),
            'clicks' => (float) ($row['clicks'] ?? 0),
            'sold' => (float) ($row['sold'] ?? 0),
            'sales' => (float) ($row['sales'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
        ];
        if ($this->snapshotsHaveMissingAdsColumn()) {
            $measures['missing_ads'] = (int) ($row['missing_ads'] ?? 0);
        }

        return $measures;
    }

    private function snapshotsHaveMissingAdsColumn(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $has = Schema::hasColumn('advertisement_master_metric_snapshots', 'missing_ads');
        } catch (\Throwable $e) {
            $has = false;
        }

        return $has;
    }

    /**
     * Write missing-ads onto today's snapshots after Channel Master rows
     * (Temu, etc.) are appended.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function snapshotMissingAds(array $rows): void
    {
        if (! $this->snapshotsHaveMissingAdsColumn()) {
            return;
        }

        $now = Carbon::now(self::SNAPSHOT_TIMEZONE);
        $today = $now->toDateString();
        foreach ($rows as $row) {
            if (empty($row['has_missing_ads'])) {
                continue;
            }
            $channel = trim((string) ($row['channel_key'] ?? ''));
            if ($channel === '') {
                $channel = $this->stripTotalSuffix((string) ($row['channel'] ?? ''));
            }
            if ($channel === '') {
                continue;
            }
            $this->saveSnapshotRow($today, $channel, [
                'missing_ads' => (int) ($row['missing_ads'] ?? 0),
            ], $now);
        }
    }

    /**
     * Upsert a single history row for (snapshot_date, channel). The original
     * `created_at` is preserved on the first insert; only `updated_at` moves
     * on subsequent saves the same Pacific day.
     *
     * @param  array<string, float>  $measures
     */
    private function saveSnapshotRow(string $date, string $channel, array $measures, Carbon $now): void
    {
        $query = DB::table('advertisement_master_metric_snapshots')
            ->where('snapshot_date', $date)
            ->where('channel', $channel);

        if ($query->exists()) {
            (clone $query)->update(array_merge($measures, ['updated_at' => $now]));

            return;
        }

        $payload = array_merge($measures, [
            'snapshot_date' => $date,
            'channel'       => $channel,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // Local/prod tables were created with a PK `id` and no AUTO_INCREMENT.
        // Assign the next id so the insert cannot fail with
        // "Field 'id' doesn't have a default value".
        $maxId = DB::table('advertisement_master_metric_snapshots')->max('id');
        $payload['id'] = ((int) $maxId) + 1;

        DB::table('advertisement_master_metric_snapshots')->insert($payload);
    }

    /**
     * First-day channels have no snapshot before today, so trend dots stay
     * hidden and the history chart is empty. Write yesterday from the current
     * measures (flat vs today) and add them to $prevByChannel so the UI
     * matches Amazon / eBay / Shopify immediately.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, float>>  $prevByChannel
     */
    private function seedMissingPriorSnapshots(array $rows, string $today, array &$prevByChannel): void
    {
        $yesterday = Carbon::parse($today, self::SNAPSHOT_TIMEZONE)->subDay()->toDateString();
        $now = Carbon::now(self::SNAPSHOT_TIMEZONE);

        foreach ($this->flattenRows($rows) as $row) {
            $channel = (string) ($row['channel'] ?? '');
            if ($channel === '' || isset($prevByChannel[$channel])) {
                continue;
            }

            $measures = $this->snapshotMeasures($row);

            try {
                $this->saveSnapshotRow($yesterday, $channel, $measures, $now);
                $prevByChannel[$channel] = $measures;
            } catch (\Throwable $e) {
                \Log::warning('Advertisement Master prior-day seed failed for '.$channel.': '.$e->getMessage());
            }
        }
    }

    /**
     * Most-recent snapshot strictly before $today (per channel), used to work
     * out each metric's day-over-day direction for the trend dots. Rows are
     * read ascending so the latest prior date wins per channel.
     *
     * @return array<string, array{spend: float, clicks: float, sold: float, sales: float}>
     */
    private function previousSnapshotByChannel(string $today): array
    {
        $cols = ['channel', 'spend', 'clicks', 'sold', 'sales', 'active'];
        if ($this->snapshotsHaveMissingAdsColumn()) {
            $cols[] = 'missing_ads';
        }
        $rows = DB::table('advertisement_master_metric_snapshots')
            ->where('snapshot_date', '<', $today)
            ->orderBy('snapshot_date')
            ->get($cols);

        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r->channel] = [
                'spend'  => (float) $r->spend,
                'clicks' => (float) $r->clicks,
                'sold'   => (float) $r->sold,
                'sales'  => (float) $r->sales,
                'active' => (float) ($r->active ?? 0),
                'missing_ads' => (float) ($r->missing_ads ?? 0),
            ];
        }

        return $map;
    }

    /**
     * Tag every row (and nested child) with a `trend` map — one direction per
     * metric ('up' | 'down' | 'flat') comparing the current value to the
     * previous Pacific-day snapshot for that channel. Channels with no prior
     * snapshot get an empty map (no dot shown).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, float>>  $prevByChannel
     */
    private function attachTrends(array &$rows, array $prevByChannel): void
    {
        foreach ($rows as &$row) {
            $channel = (string) ($row['channel'] ?? '');
            $row['trend'] = $this->computeTrend($row, $prevByChannel[$channel] ?? null);

            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->attachTrends($row['_children'], $prevByChannel);
            }
        }
        unset($row);
    }

    /**
     * Direction of each displayed metric vs the previous day. CVR / ACOS are
     * re-derived from the previous day's raw measures exactly like the current
     * row so the comparison is apples-to-apples. The colour meaning (which way
     * is "good") is applied on the frontend, where Spend + ACOS are inverted.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, float>|null  $prev
     * @return array<string, string>
     */
    private function computeTrend(array $row, ?array $prev): array
    {
        if ($prev === null) {
            return [];
        }

        $prevSpend  = (float) ($prev['spend'] ?? 0);
        $prevClicks = (float) ($prev['clicks'] ?? 0);
        $prevSold   = (float) ($prev['sold'] ?? 0);
        $prevSales  = (float) ($prev['sales'] ?? 0);
        $prevActive = (float) ($prev['active'] ?? 0);
        $prevCvr    = $prevClicks > 0 ? ($prevSold / $prevClicks) * 100 : 0;
        $prevAcos   = $prevSales > 0
            ? ($prevSpend / $prevSales) * 100
            : ($prevSpend > 0 ? 100 : 0);

        $dir = static fn (float $cur, float $was): string => $cur > $was
            ? 'up'
            : ($cur < $was ? 'down' : 'flat');

        return [
            'spend'  => $dir((float) ($row['spend'] ?? 0),  $prevSpend),
            'clicks' => $dir((float) ($row['clicks'] ?? 0), $prevClicks),
            'sold'   => $dir((float) ($row['sold'] ?? 0),   $prevSold),
            'sales'  => $dir((float) ($row['sales'] ?? 0),  $prevSales),
            'active' => $dir((float) ($row['active'] ?? 0),  $prevActive),
            'cvr'    => $dir((float) ($row['cvr'] ?? 0),     $prevCvr),
            'acos'   => $dir((float) ($row['acos'] ?? 0),    $prevAcos),
            'missing_ads' => $dir((float) ($row['missing_ads'] ?? 0), (float) ($prev['missing_ads'] ?? 0)),
        ];
    }

    /**
     * Re-apply missing-ads trend after late Channel Master rows are attached.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, float>>  $prevByChannel
     */
    private function attachMissingAdsTrends(array &$rows, array $prevByChannel): void
    {
        foreach ($rows as &$row) {
            $channel = (string) ($row['channel_key'] ?? $row['channel'] ?? '');
            $prev = $prevByChannel[$channel] ?? $prevByChannel[(string) ($row['channel'] ?? '')] ?? null;
            if ($prev !== null) {
                $dir = static fn (float $cur, float $was): string => $cur > $was
                    ? 'up'
                    : ($cur < $was ? 'down' : 'flat');
                $row['trend'] = array_merge($row['trend'] ?? [], [
                    'missing_ads' => $dir((float) ($row['missing_ads'] ?? 0), (float) ($prev['missing_ads'] ?? 0)),
                ]);
            }
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->attachMissingAdsTrends($row['_children'], $prevByChannel);
            }
        }
        unset($row);
    }

    /**
     * Badge/cell trend history. Returns a per-day time series for each metric
     * (spend / clicks / sold / sales / cvr / acos) rolled up across the
     * top-level channels, plus the same broken out per channel so the chart
     * can lens to the total or a single channel. Mirrors the
     * /shopify-ads-master history endpoint.
     *
     *   GET /advertisement-master/history?days=32
     */
    public function history(Request $request)
    {
        $days = max(1, min(365, (int) $request->query('days', 32)));
        // Anchor to the Pacific business day so it lines up with the snapshots.
        $from = Carbon::now(self::SNAPSHOT_TIMEZONE)->subDays($days - 1)->toDateString();

        $histCols = ['snapshot_date', 'channel', 'spend', 'clicks', 'sold', 'sales', 'active'];
        if ($this->snapshotsHaveMissingAdsColumn()) {
            $histCols[] = 'missing_ads';
        }
        $rows = DB::table('advertisement_master_metric_snapshots')
            ->where('snapshot_date', '>=', $from)
            ->orderBy('snapshot_date')
            ->get($histCols);

        $byDate       = [];   // date => rolled-up totals (top-level parents only)
        $byChannel    = [];   // channel => date => measures
        $ssalesByDate = [];   // date => combined S Sales (net sales)
        foreach ($rows as $r) {
            $d  = (string) $r->snapshot_date;
            $ch = (string) $r->channel;

            // Combined S Sales pseudo-channel: kept aside for the TCOS /
            // S SALES badges, never rolled into the channel totals.
            if ($ch === self::SSALES_CHANNEL) {
                $ssalesByDate[$d] = (float) $r->sales;
                continue;
            }

            $byChannel[$ch][$d] = [
                'spend'  => (float) $r->spend,
                'clicks' => (float) $r->clicks,
                'sold'   => (float) $r->sold,
                'sales'  => (float) $r->sales,
                'active' => (float) ($r->active ?? 0),
                'missing_ads' => (float) ($r->missing_ads ?? 0),
            ];

            // Sub-rows (contain the separator) are slices of a parent — skip
            // them from the grand total so parents aren't double-counted.
            if (str_contains($ch, self::SUBROW_SEPARATOR)) {
                continue;
            }

            $byDate[$d] ??= ['spend' => 0.0, 'clicks' => 0.0, 'sold' => 0.0, 'sales' => 0.0, 'active' => 0.0, 'missing_ads' => 0.0];
            $byDate[$d]['spend']  += (float) $r->spend;
            $byDate[$d]['clicks'] += (float) $r->clicks;
            $byDate[$d]['sold']   += (float) $r->sold;
            $byDate[$d]['sales']  += (float) $r->sales;
            $byDate[$d]['active'] += (float) ($r->active ?? 0);
            $byDate[$d]['missing_ads'] += (float) ($r->missing_ads ?? 0);
        }

        // Continuous calendar window (L30 when days=30) so missing snapshot
        // days still appear on the chart instead of jumping Aug 14 → Aug 24.
        $end = Carbon::now(self::SNAPSHOT_TIMEZONE)->toDateString();
        $labels = [];
        $cursor = Carbon::parse($from, self::SNAPSHOT_TIMEZONE)->startOfDay();
        $endC = Carbon::parse($end, self::SNAPSHOT_TIMEZONE)->startOfDay();
        while ($cursor->lte($endC)) {
            $labels[] = $cursor->toDateString();
            $cursor->addDay();
        }

        // Rolled-up "All channels" series carries tcos + ssales (both need the
        // store-level net sales). Per-channel series get tcos too, lensed to
        // that channel's spend against the same store S Sales.
        $metrics = $this->buildMetricSeries($byDate, $labels, $ssalesByDate);
        $metrics['ssales'] = array_map(
            fn ($d) => array_key_exists($d, $ssalesByDate) ? round($ssalesByDate[$d], 2) : null,
            $labels
        );

        return response()->json([
            'status'   => 200,
            'days'     => $days,
            'labels'   => array_map(fn ($d) => date('M d', strtotime($d)), $labels),
            'metrics'  => $metrics,
            'channels' => $this->buildChannelSeries($byChannel, $labels, $ssalesByDate),
        ]);
    }

    /**
     * @param  array<string, array<string, float>>  $byDate
     * @param  array<int, string>  $labels
     * @param  array<string, float>  $ssalesByDate  date => combined S Sales (for TCOS)
     * @return array<string, array<int, float|null>>
     */
    private function buildMetricSeries(array $byDate, array $labels, array $ssalesByDate = []): array
    {
        $series = ['spend' => [], 'clicks' => [], 'sold' => [], 'sales' => [], 'active' => [], 'cvr' => [], 'acos' => [], 'tcos' => [], 'missing_ads' => []];
        foreach ($labels as $d) {
            if (! isset($byDate[$d])) {
                $series['spend'][]  = null;
                $series['clicks'][] = null;
                $series['sold'][]   = null;
                $series['sales'][]  = null;
                $series['active'][] = null;
                $series['cvr'][]    = null;
                $series['acos'][]   = null;
                $series['tcos'][]   = null;
                $series['missing_ads'][] = null;
                continue;
            }

            $m = $byDate[$d];
            $series['spend'][]  = round($m['spend'], 2);
            $series['clicks'][] = (int) round($m['clicks']);
            $series['sold'][]   = (int) round($m['sold']);
            $series['sales'][]  = round($m['sales'], 2);
            $series['active'][] = (int) round($m['active'] ?? 0);
            $series['cvr'][]    = $m['clicks'] > 0 ? round(($m['sold'] / $m['clicks']) * 100, 1) : 0;
            $series['acos'][]   = $m['sales'] > 0
                ? round(($m['spend'] / $m['sales']) * 100, 0)
                : ($m['spend'] > 0 ? 100 : 0);
            // TCOS = Spend / S Sales (combined store net sales).
            $ss = $ssalesByDate[$d] ?? 0;
            $series['tcos'][]   = $ss > 0
                ? round(($m['spend'] / $ss) * 100, 0)
                : ($m['spend'] > 0 ? 100 : 0);
            $series['missing_ads'][] = (int) round($m['missing_ads'] ?? 0);
        }

        return $series;
    }

    /**
     * @param  array<string, array<string, array<string, float>>>  $byChannel
     * @param  array<int, string>  $labels
     * @param  array<string, float>  $ssalesByDate
     * @return array<string, array<string, array<int, float|null>>>
     */
    private function buildChannelSeries(array $byChannel, array $labels, array $ssalesByDate = []): array
    {
        $out = [];
        foreach ($byChannel as $channel => $perDay) {
            $bd = [];
            foreach ($labels as $d) {
                if (isset($perDay[$d])) {
                    $bd[$d] = $perDay[$d];
                }
            }
            $out[$channel] = $this->buildMetricSeries($bd, $labels, $ssalesByDate);
        }

        return $out;
    }

    /**
     * Type-column link: channel ads page when one exists, otherwise analytics.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachChannelHrefs(array &$rows): void
    {
        foreach ($rows as &$row) {
            $href = $this->resolveChannelHref($row);
            if ($href) {
                $row['href'] = $href;
            }
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->attachChannelHrefs($row['_children']);
            }
        }
        unset($row);
    }

    /**
     * Attach saved R/N flags. Missing keys default to REQ (green).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachNrReqs(array &$rows): void
    {
        $map = $this->nrReqMap();
        $this->attachNrReqsWalk($rows, $map);
    }

    /**
     * @return array<string, string>
     */
    private function nrReqMap(): array
    {
        if (! Schema::hasTable('advertisement_master_nr_reqs')) {
            return [];
        }

        $map = [];
        foreach (AdvertisementMasterNrReq::query()->get(['channel_key', 'nr_req']) as $row) {
            $key = trim((string) $row->channel_key);
            if ($key === '') {
                continue;
            }
            $map[$key] = strtoupper((string) $row->nr_req) === 'NR' ? 'NR' : 'REQ';
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $map
     */
    private function attachNrReqsWalk(array &$rows, array $map): void
    {
        foreach ($rows as &$row) {
            $key = trim((string) ($row['channel_key'] ?? $row['channel'] ?? ''));
            $row['nr_req'] = ($key !== '' && ($map[$key] ?? '') === 'NR') ? 'NR' : 'REQ';
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->attachNrReqsWalk($row['_children'], $map);
            }
        }
        unset($row);
    }

    /**
     * Listing views from Channel Master. Parent totals sum children when
     * those children have views; otherwise the parent uses its own channel views.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachViews(array &$rows): void
    {
        $this->attachViewsWalk($rows, $this->channelViewsMap());
    }

    /**
     * @return array<string, int>
     */
    private function channelViewsMap(): array
    {
        if (! Schema::hasTable('channel_master_calculated_data')) {
            return [];
        }

        $map = [];
        try {
            foreach (ChannelMasterCalculatedData::query()->get(['channel', 'total_views']) as $row) {
                $norm = $this->normalizeChannelMatchKey((string) ($row->channel ?? ''));
                if ($norm === '') {
                    continue;
                }
                $views = (float) ($row->total_views ?? 0);
                // Same as Channel Master / All Marketplace Master: Reverb views ÷ 100.
                if ($norm === 'reverb') {
                    $views = $views / 100;
                }
                $map[$norm] = $views > 0 ? (int) round($views) : 0;
            }
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master views lookup failed: '.$e->getMessage());
        }

        return $map;
    }

    /**
     * Channel store sales — same L30 Sales figure as All Marketplace Master
     * "Sales" (channel_master_calculated_data.l30_sales).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachTSales(array &$rows): void
    {
        $this->attachTSalesWalk($rows, $this->channelL30SalesMap());
    }

    /**
     * Yellow total rows: ACOS = Spend / Total Sales (T Sales / AMM Sales).
     * Type rows keep Spend / Ads Sales.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, float>>  $prevByChannel
     */
    private function attachTotalRowAcos(array &$rows, array $prevByChannel = []): void
    {
        foreach ($rows as &$row) {
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $this->attachTotalRowAcos($row['_children'], $prevByChannel);
            }

            $isTotal = ! empty($row['is_sum_row'])
                || ! empty($row['is_group_total'])
                || (! empty($row['_children']) && is_array($row['_children']));
            if (! $isTotal) {
                continue;
            }

            $spend = (float) ($row['spend'] ?? 0);
            $totalSales = (float) ($row['t_sales'] ?? 0);
            $row['acos'] = $totalSales > 0
                ? round(($spend / $totalSales) * 100, 1)
                : ($spend > 0 ? 100.0 : 0.0);

            $channel = (string) ($row['channel_key'] ?? $row['channel'] ?? '');
            $prev = $prevByChannel[$channel] ?? $prevByChannel[(string) ($row['channel'] ?? '')] ?? null;
            if ($prev !== null) {
                $prevAcos = (float) ($prev['acos'] ?? 0);
                $dir = static fn (float $cur, float $was): string => $cur > $was
                    ? 'up'
                    : ($cur < $was ? 'down' : 'flat');
                $row['trend'] = array_merge($row['trend'] ?? [], [
                    'acos' => $dir((float) $row['acos'], $prevAcos),
                ]);
            }
        }
        unset($row);
    }

    /**
     * @return array<string, float>
     */
    private function channelL30SalesMap(): array
    {
        if (! Schema::hasTable('channel_master_calculated_data')) {
            return [];
        }

        $map = [];
        try {
            foreach (ChannelMasterCalculatedData::query()->get(['channel', 'l30_sales']) as $row) {
                $norm = $this->normalizeChannelMatchKey((string) ($row->channel ?? ''));
                if ($norm === '') {
                    continue;
                }
                $map[$norm] = (float) ($row->l30_sales ?? 0);
            }
        } catch (\Throwable $e) {
            \Log::warning('Advertisement Master T Sales lookup failed: '.$e->getMessage());
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, float>  $map
     */
    private function attachTSalesWalk(array &$rows, array $map): float
    {
        $sum = 0.0;
        foreach ($rows as &$row) {
            $childSum = 0.0;
            $childHas = false;
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $childSum = $this->attachTSalesWalk($row['_children'], $map);
                foreach ($row['_children'] as $child) {
                    if (! empty($child['has_t_sales'])) {
                        $childHas = true;
                        break;
                    }
                }
            }

            $own = $this->lookupRowTSales($row, $map);
            if ($childHas) {
                $row['t_sales'] = $childSum;
                $row['has_t_sales'] = true;
            } elseif ($own !== null) {
                $row['t_sales'] = $own;
                $row['has_t_sales'] = true;
            } else {
                $row['t_sales'] = 0.0;
                $row['has_t_sales'] = false;
            }
            $sum += (float) $row['t_sales'];
        }
        unset($row);

        return $sum;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, float>  $map
     */
    private function lookupRowTSales(array $row, array $map): ?float
    {
        $fields = ['channel_key', 'channel'];
        $key = (string) ($row['channel_key'] ?? $row['channel'] ?? '');
        if (! str_contains($key, self::SUBROW_SEPARATOR)) {
            $fields[] = 'channel_group';
        }

        foreach ($fields as $field) {
            $norm = $this->normalizeChannelMatchKey((string) ($row[$field] ?? ''));
            if ($norm !== '' && array_key_exists($norm, $map)) {
                return (float) $map[$norm];
            }
        }

        return null;
    }

    /**
     * Missing-ad counts from the same pages as the sidebar:
     * Ads Missing Amz, Missing Mapping Temu, Missing Google Shopping / SERP,
     * YouTube Missing Ads, TikTok Missing Ads.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function attachMissingAds(array &$rows): void
    {
        $this->attachMissingAdsWalk($rows, $this->missingAdsSourceMap());
    }

    /**
     * @return array<string, array{count: int, href: ?string}>
     */
    private function missingAdsSourceMap(): array
    {
        $map = [];
        $put = function (array $keys, int $count, ?string $href) use (&$map): void {
            foreach ($keys as $key) {
                if ($key === '') {
                    continue;
                }
                $map[$key] = ['count' => $count, 'href' => $href];
            }
        };

        $safeCount = function (callable $fn): int {
            try {
                return (int) $fn();
            } catch (\Throwable $e) {
                \Log::warning('Advertisement Master missing-ads count failed: '.$e->getMessage());

                return 0;
            }
        };

        $put(
            ['amazon'],
            $safeCount(static fn () => AmazonAdsMissingController::missingTotalCount()),
            $this->namedHref('amazon.ads.missing')
        );
        $put(
            ['shopifygoogleshopping'],
            $safeCount(static fn () => GoogleShoppingAdsMissingController::missingTotalCount()),
            $this->namedHref('google.shopping.ads.missing')
        );
        $put(
            ['shopifygoogleserp'],
            $safeCount(static fn () => GoogleSerpAdsMissingController::missingTotalCount()),
            $this->namedHref('google.serp.ads.missing')
        );
        $put(
            ['shopifyyoutubeads'],
            $safeCount(static fn () => GoogleYoutubeAdsMissingController::missingTotalCount()),
            $this->namedHref('google.youtube.ads.missing')
        );
        $put(
            ['shopifytiktokvideoads'],
            $safeCount(static fn () => TiktokAdsMissingController::missingTotalCount()),
            $this->namedHref('tiktok.ads.missing')
        );
        $put(
            ['temu', 'temu1'],
            $safeCount(static fn () => MappingChannelCounts::countForSlug('temu')),
            $this->temuMissingMappingHref()
        );

        return $map;
    }

    private function temuMissingMappingHref(): ?string
    {
        try {
            if (! Route::has('map.issues.channel')) {
                return null;
            }

            return route('map.issues.channel', ['channel' => 'temu']);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array{count: int, href: ?string}>  $sources
     */
    private function attachMissingAdsWalk(array &$rows, array $sources): int
    {
        $sum = 0;
        foreach ($rows as &$row) {
            $childSum = 0;
            $childHas = false;
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $childSum = $this->attachMissingAdsWalk($row['_children'], $sources);
                foreach ($row['_children'] as $child) {
                    if (! empty($child['has_missing_ads'])) {
                        $childHas = true;
                        break;
                    }
                }
            }

            $source = $this->lookupMissingAdsSource($row, $sources);
            if ($source !== null) {
                $row['missing_ads'] = (int) $source['count'];
                $row['missing_ads_href'] = $source['href'];
                $row['has_missing_ads'] = true;
            } elseif ($childHas) {
                $row['missing_ads'] = $childSum;
                $row['missing_ads_href'] = null;
                $row['has_missing_ads'] = true;
            } else {
                $row['missing_ads'] = 0;
                $row['missing_ads_href'] = null;
                $row['has_missing_ads'] = false;
            }
            $sum += (int) $row['missing_ads'];
        }
        unset($row);

        return $sum;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, array{count: int, href: ?string}>  $sources
     * @return array{count: int, href: ?string}|null
     */
    private function lookupMissingAdsSource(array $row, array $sources): ?array
    {
        foreach (['channel_key', 'channel'] as $field) {
            $norm = $this->normalizeChannelMatchKey((string) ($row[$field] ?? ''));
            if ($norm !== '' && isset($sources[$norm])) {
                return $sources[$norm];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, int>  $map
     */
    private function attachViewsWalk(array &$rows, array $map): int
    {
        $sum = 0;
        foreach ($rows as &$row) {
            $childSum = 0;
            if (! empty($row['_children']) && is_array($row['_children'])) {
                $childSum = $this->attachViewsWalk($row['_children'], $map);
            }

            $key = (string) ($row['channel_key'] ?? $row['channel'] ?? '');
            $isAdType = str_contains($key, self::SUBROW_SEPARATOR);
            if ($childSum > 0) {
                $row['views'] = $childSum;
            } elseif ($isAdType && empty($row['is_group_total'])) {
                $row['views'] = 0;
            } else {
                $row['views'] = $this->lookupRowViews($row, $map);
            }
            $sum += (int) $row['views'];
        }
        unset($row);

        return $sum;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $map
     */
    private function lookupRowViews(array $row, array $map): int
    {
        foreach (['channel_key', 'channel', 'channel_group'] as $field) {
            $norm = $this->normalizeChannelMatchKey((string) ($row[$field] ?? ''));
            if ($norm !== '' && isset($map[$norm])) {
                return (int) $map[$norm];
            }
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveChannelHref(array $row): ?string
    {
        foreach (['channel_key', 'channel', 'channel_group'] as $field) {
            $name = trim((string) ($row[$field] ?? ''));
            if ($name === '' || str_starts_with($name, 'custom:')) {
                continue;
            }
            $href = $this->channelHrefForName($name);
            if ($href) {
                return $href;
            }
        }

        return null;
    }

    private function channelHrefForName(string $name): ?string
    {
        $norm = $this->normalizeChannelMatchKey($name);
        if ($norm === '') {
            return null;
        }

        $ads = $this->channelAdsHrefMap()[$norm] ?? null;
        if (is_string($ads) && $ads !== '') {
            return $ads;
        }

        $analytics = $this->channelAnalyticsHrefMap()[$norm] ?? null;

        return (is_string($analytics) && $analytics !== '') ? $analytics : null;
    }

    /**
     * @return array<string, string>
     */
    private function channelAdsHrefMap(): array
    {
        static $map = null;
        if (is_array($map)) {
            return $map;
        }

        $map = array_filter([
            'amazon' => $this->namedHref('amazon.ads.all'),
            'amazonkw' => $this->namedHref('amazon.ads.all', ['search' => 'KW']),
            'amazonpt' => $this->namedHref('amazon.ads.all', ['search' => 'PT']),
            'amazonhl' => $this->namedHref('amazon.ads.all', ['source' => 'sb_reports']),
            'ebay' => $this->namedHref('ebay.campaign.ads'),
            'ebay2' => $this->namedHref('ebay2.campaign.ads'),
            'ebay3' => $this->namedHref('ebay3.campaign.ads'),
            'shopify' => $this->namedHref('shopify.ads.master'),
            'shopifyb2c' => $this->namedHref('shopify.ads.master'),
            'shopifygoogleshopping' => $this->namedHref('google.shopping.campaigns'),
            'shopifygoogleserp' => $this->namedHref('google.serp.campaigns'),
            'shopifyyoutubeads' => $this->namedHref('google.youtube.ads.campaigns'),
            'shopifytiktokvideoads' => $this->namedHref('tiktok.ads.master') ?: $this->namedHref('tiktok.video.ads'),
            'shopifyfacebook' => $this->namedHref('facebook.ads.channel'),
            'shopifyfacebookgvideo' => $this->namedHref('facebook.ads.channel.group.video'),
            'shopifyfacebookgcarousal' => $this->namedHref('facebook.ads.channel.group.carousal'),
            'shopifyfacebookpvideo' => $this->namedHref('facebook.ads.channel.parent.video'),
            'shopifyfacebookpcarousal' => $this->namedHref('facebook.ads.channel.parent.carousal'),
            'shopifyinstagram' => $this->namedHref('instagram.ads.channel'),
            'shopifyinstagramgvideo' => $this->namedHref('instagram.ads.channel.group.video'),
            'shopifyinstagramgcarousal' => $this->namedHref('instagram.ads.channel.group.carousal'),
            'shopifyinstagrampvideo' => $this->namedHref('instagram.ads.channel.parent.video'),
            'shopifyinstagrampcarousal' => $this->namedHref('instagram.ads.channel.parent.carousal'),
            'tiktok1' => $this->namedHref('tiktok1.ads.raw'),
            'tiktok2' => $this->namedHref('tiktok.gmv.ads.raw'),
            'temu' => $this->namedHref('temu.ads'),
            'temu1' => $this->namedHref('temu.ads'),
            'temu2' => $this->namedHref('temu2.ads'),
            'walmart' => $this->namedHref('walmart.running.ads'),
        ], fn ($url) => is_string($url) && $url !== '');

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private function channelAnalyticsHrefMap(): array
    {
        static $map = null;
        if (is_array($map)) {
            return $map;
        }

        $map = array_filter([
            'amazon' => url('/amazon-tabulator-view'),
            'ebay' => url('/ebay-tabulator-view'),
            'ebay2' => url('/ebay2-tabulator-view'),
            'ebay3' => url('/ebay3-tabulator-view'),
            'shopify' => url('/shopify-b2c-pricing'),
            'shopifyb2c' => url('/shopify-b2c-pricing'),
            'b2b' => url('/shopify-b2b-pricing'),
            'shopifyb2b' => url('/shopify-b2b-pricing'),
            'tiktok1' => $this->namedHref('tiktok.pricing'),
            'tiktok2' => $this->namedHref('tiktok2.pricing'),
            'temu' => $this->namedHref('temu1.data'),
            'temu1' => $this->namedHref('temu1.data'),
            'temu2' => url('/temu2-decrease'),
            'temu3' => $this->namedHref('temu3.decrease'),
            'aliexpress' => $this->namedHref('aliexpress.pricing.view'),
            'bestbuyusa' => $this->namedHref('bestbuy.pricing'),
            'bestbuy' => $this->namedHref('bestbuy.pricing'),
            'depop' => $this->namedHref('depop.pricing'),
            'doba' => url('/doba-tabulator'),
            'faire' => $this->namedHref('faire.pricing.view'),
            'fbmarketplace' => $this->namedHref('fb.marketplace.tabulator.view'),
            'facebookmarketplace' => $this->namedHref('fb.marketplace.tabulator.view'),
            'instagramshop' => $this->namedHref('zero.instagramshop'),
            'macys' => $this->namedHref('macys.pricing'),
            'macy' => $this->namedHref('macys.pricing'),
            'mercariwship' => $this->namedHref('mercari.wship.tabulator.view'),
            'mercariwithship' => $this->namedHref('mercari.wship.tabulator.view'),
            'mercariwoship' => $this->namedHref('mercari.woship.tabulator.view'),
            'mercariwithoutship' => $this->namedHref('mercari.woship.tabulator.view'),
            'newegg' => $this->namedHref('newegg.pricing.view'),
            'purchasingpower' => $this->namedHref('purchasing.power.pricing'),
            'reverb' => $this->namedHref('reverb.pricing'),
            'shein' => $this->namedHref('shein.pricing.view'),
            'topdawg' => $this->namedHref('topdawg.pricing'),
            'vinted' => $this->namedHref('vinted.pricing'),
            'wayfair' => $this->namedHref('wayfair.pricing.view'),
            'pls' => $this->namedHref('pls.pricing'),
            'walmart' => $this->namedHref('walmart.sheet.upload'),
        ], fn ($url) => is_string($url) && $url !== '');

        return $map;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function namedHref(string $name, array $query = []): ?string
    {
        try {
            if (! Route::has($name)) {
                return null;
            }
            $url = route($name);
        } catch (\Throwable $e) {
            return null;
        }

        if ($query === []) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }
}
