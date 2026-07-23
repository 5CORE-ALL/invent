<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Channels\ChannelMasterController;
use App\Models\ChannelMasterSummary;
use App\Support\Badges\AllMarketplaceMasterBadgeCalculator;
use App\Support\Marketplace\ListingChannelCounts;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Dashboard "Listing Catalogue" block — Missing L / N Map / Variations Mismatch
 * score badges + rolling history charts (California dates).
 */
class ListingCatalogueController extends Controller
{
    private const TZ = 'America/Los_Angeles';

    public const VARIATIONS_CHANNEL_KEY = 'variationsverify';

    /**
     * Rolling history for Listing Catalogue badges.
     * metric: missing_l | nmap | variations_mismatch
     */
    public function chartData(Request $request)
    {
        $metric = strtolower(trim((string) $request->input('metric', 'missing_l')));

        try {
            // Keep today's snapshot fresh whenever a chart is opened
            self::persistTodaySnapshot();

            if ($metric === 'missing_l') {
                $sub = Request::create('/missing-listing/chart-data', 'GET', [
                    'channel' => 'all',
                    'days' => $request->input('days', 32),
                    'badge_value' => $request->input('badge_value'),
                ]);

                return app(MissingListingController::class)->chartData($sub);
            }

            if ($metric === 'nmap') {
                $sub = Request::create('/channel-metric-chart-data', 'GET', [
                    'channel' => 'all',
                    'metric' => 'nmap',
                    'days' => $request->input('days', 32),
                    'badge_value' => $request->input('badge_value'),
                ]);

                return app(ChannelMasterController::class)->getChannelMetricChartData($sub);
            }

            if ($metric === 'variations_mismatch') {
                return response()->json([
                    'success' => true,
                    'data' => $this->variationsMismatchHistory($request),
                ]);
            }

            return response()->json(['success' => false, 'message' => 'Unknown metric', 'data' => []], 400);
        } catch (\Throwable $e) {
            Log::error('ListingCatalogue chartData failed: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    /**
     * Persist today's California catalogue scores (Missing L, N Map, Variations Mismatch).
     */
    public static function persistTodaySnapshot(?int $variationsMismatch = null): void
    {
        if (! Schema::hasTable('channel_master_daily_data')) {
            return;
        }

        try {
            $today = now(self::TZ)->toDateString();
            $missingL = ListingChannelCounts::totalMissingL(true);
            $nmap = AllMarketplaceMasterBadgeCalculator::nmapCountForSidebar();
            $vMismatch = $variationsMismatch;
            if ($vMismatch === null) {
                $vMismatch = VariationsVerifyMasterController::totalMismatchCountForSidebar();
            }

            // Unified catalogue row
            $existing = ChannelMasterSummary::where('channel', 'listingcatalogue')
                ->whereDate('snapshot_date', $today)
                ->first();
            $sd = is_array($existing?->summary_data) ? $existing->summary_data : [];
            $sd['listing_miss_count'] = $missingL;
            $sd['miss_count'] = $missingL;
            $sd['nmap_count'] = $nmap;
            $sd['variations_mismatch'] = (int) $vMismatch;
            $sd['listing_catalogue_captured_at'] = now(self::TZ)->toDateTimeString();

            ChannelMasterSummary::updateOrCreate(
                ['channel' => 'listingcatalogue', 'snapshot_date' => $today],
                ['summary_data' => $sd, 'notes' => 'Listing Catalogue dashboard snapshot (California)']
            );

            // Variations-specific row for mismatch history
            $vExisting = ChannelMasterSummary::where('channel', self::VARIATIONS_CHANNEL_KEY)
                ->whereDate('snapshot_date', $today)
                ->first();
            $vsd = is_array($vExisting?->summary_data) ? $vExisting->summary_data : [];
            $vsd['variations_mismatch'] = (int) $vMismatch;
            $vsd['captured_at'] = now(self::TZ)->toDateTimeString();

            ChannelMasterSummary::updateOrCreate(
                ['channel' => self::VARIATIONS_CHANNEL_KEY, 'snapshot_date' => $today],
                ['summary_data' => $vsd, 'notes' => 'Variations Verify Mismatch snapshot (California)']
            );
        } catch (\Throwable $e) {
            Log::warning('ListingCatalogue persistTodaySnapshot failed: '.$e->getMessage());
        }
    }

    /**
     * @return list<array{date: string, value: float}>
     */
    private function variationsMismatchHistory(Request $request): array
    {
        $days = (int) $request->input('days', 32);
        $badgeValue = $request->input('badge_value');
        $live = ($badgeValue !== null && $badgeValue !== '' && is_numeric($badgeValue))
            ? (float) $badgeValue
            : (float) VariationsVerifyMasterController::totalMismatchCountForSidebar();

        if (! Schema::hasTable('channel_master_daily_data')) {
            return [[
                'date' => now(self::TZ)->format('M d'),
                'value' => round($live, 2),
            ]];
        }

        $query = ChannelMasterSummary::query()
            ->where('channel', self::VARIATIONS_CHANNEL_KEY)
            ->orderBy('snapshot_date', 'asc');

        if ($days > 0) {
            $start = now(self::TZ)->subDays(max($days - 1, 0))->toDateString();
            $query->whereDate('snapshot_date', '>=', $start);
        }

        $rows = $query->get(['snapshot_date', 'summary_data']);
        $chartData = [];
        foreach ($rows as $row) {
            $dateKey = Carbon::parse($row->snapshot_date)->timezone(self::TZ)->toDateString();
            $sd = is_array($row->summary_data) ? $row->summary_data : [];
            $chartData[] = [
                'date' => Carbon::parse($dateKey, self::TZ)->format('M d'),
                'date_key' => $dateKey,
                'value' => round((float) ($sd['variations_mismatch'] ?? 0), 2),
            ];
        }

        $todayKey = now(self::TZ)->toDateString();
        $todayLabel = now(self::TZ)->format('M d');
        $replaced = false;
        foreach ($chartData as &$point) {
            if (($point['date_key'] ?? '') === $todayKey) {
                $point['value'] = round($live, 2);
                $replaced = true;
            }
        }
        unset($point);

        if (! $replaced) {
            $chartData[] = [
                'date' => $todayLabel,
                'date_key' => $todayKey,
                'value' => round($live, 2),
            ];
        }

        return array_values(array_map(fn ($p) => [
            'date' => $p['date'],
            'value' => $p['value'],
        ], $chartData));
    }
}
