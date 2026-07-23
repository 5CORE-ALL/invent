<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\ChannelMaster;
use App\Models\ChannelMasterSummary;
use App\Models\MissingListingDar;
use App\Support\Marketplace\CpMasterCounts;
use App\Support\Marketplace\ListingChannelCounts;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Missing Listing page — Tabulator view.
 *
 * Metrics from each channel's /listing-* page (live, no cache).
 * History chart from daily listing_miss_count snapshots (California dates).
 */
class MissingListingController extends Controller
{
    private const TZ = 'America/Los_Angeles';

    public function index()
    {
        return view('market-places.Missing_listing');
    }

    public function getData(Request $request)
    {
        try {
            $hasLogo = Schema::hasTable('channel_master')
                && Schema::hasColumn('channel_master', 'logo');
            $hasSellerLink = Schema::hasTable('channel_master')
                && Schema::hasColumn('channel_master', 'seller_link');

            $masterColumns = ['id', 'channel'];
            if ($hasLogo) {
                $masterColumns[] = 'logo';
            }
            if ($hasSellerLink) {
                $masterColumns[] = 'seller_link';
            }

            $masterRows = Schema::hasTable('channel_master')
                ? ChannelMaster::whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                    ->whereNotNull('channel')
                    ->where('channel', '!=', '')
                    ->orderBy('channel')
                    ->get($masterColumns)
                : collect();

            $cpMasterCounts = CpMasterCounts::counts(false);
            $cpSkuCount = (int) ($cpMasterCounts['SKU'] ?? 0);
            $cpZeroInv = (int) ($cpMasterCounts['ZeroInv'] ?? 0);

            $data = $masterRows
                ->filter(fn ($master) => ListingChannelCounts::hasListingSource((string) $master->channel))
                ->map(function ($master) use ($hasLogo, $hasSellerLink, $cpSkuCount, $cpZeroInv) {
                    $channel = (string) $master->channel;
                    $listingCounts = ListingChannelCounts::forChannel($channel, false);

                    return [
                        'id' => $master->id,
                        'image' => $hasLogo ? ($master->logo ?? null) : null,
                        'channel' => $channel,
                        'listing_url' => ListingChannelCounts::listingUrl($channel),
                        'sku' => $cpSkuCount,
                        'zero_inv' => $cpZeroInv,
                        'req' => (int) ($listingCounts['REQ'] ?? 0),
                        'nrl' => (int) ($listingCounts['NRL'] ?? 0),
                        'listed' => (int) ($listingCounts['Listed'] ?? 0),
                        'missing_listing' => (int) ($listingCounts['Pending'] ?? 0),
                        'seller_portal' => $hasSellerLink ? ($master->seller_link ?? null) : null,
                    ];
                })
                ->values();

            // Persist today's California listing Missing L for history charts
            $this->persistListingMissingHistory($data);

            $totalMissingL = (int) $data->sum('missing_listing');

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
                'total_missing_l' => $totalMissingL,
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing getData failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Missing L history chart — listing-page snapshots, California calendar dates.
     */
    public function chartData(Request $request)
    {
        try {
            $rawChannel = trim((string) $request->input('channel', 'all'));
            $channelKey = ListingChannelCounts::normalize($rawChannel);
            $isAll = $channelKey === '' || $channelKey === 'all';
            $days = (int) $request->input('days', 32);
            $badgeValue = $request->input('badge_value');

            if (! Schema::hasTable('channel_master_daily_data')) {
                return response()->json(['success' => true, 'data' => $this->chartWithLiveOnly($isAll, $channelKey, $badgeValue)]);
            }

            $query = ChannelMasterSummary::query()->orderBy('snapshot_date', 'asc');
            if ($days > 0) {
                $startDate = now(self::TZ)->subDays(max($days - 1, 0))->toDateString();
                $query->whereDate('snapshot_date', '>=', $startDate);
            }

            if (! $isAll) {
                $aliases = $this->channelAliases($channelKey);
                $query->whereIn('channel', $aliases);
            }

            $history = $query->get(['channel', 'snapshot_date', 'summary_data']);

            // Group by California snapshot_date (same-day listing capture — no −1 shift)
            $grouped = $history->groupBy(function ($row) {
                return Carbon::parse($row->snapshot_date)->timezone(self::TZ)->toDateString();
            })->sortKeys();

            $chartData = [];
            foreach ($grouped as $dateKey => $rows) {
                $label = Carbon::parse($dateKey, self::TZ)->format('M d');
                $value = 0.0;

                if ($isAll) {
                    // One value per channel key (dedupe aliases), prefer listing_miss_count
                    $byChannel = [];
                    foreach ($rows as $row) {
                        $ck = ListingChannelCounts::normalize((string) $row->channel);
                        $sd = is_array($row->summary_data) ? $row->summary_data : [];
                        $miss = array_key_exists('listing_miss_count', $sd)
                            ? (float) $sd['listing_miss_count']
                            : (float) ($sd['miss_count'] ?? 0);
                        // Prefer listing_miss when present; otherwise keep first seen
                        if (! isset($byChannel[$ck]) || array_key_exists('listing_miss_count', $sd)) {
                            $byChannel[$ck] = $miss;
                        }
                    }
                    $value = array_sum($byChannel);
                } else {
                    $best = null;
                    foreach ($rows as $row) {
                        $sd = is_array($row->summary_data) ? $row->summary_data : [];
                        if (array_key_exists('listing_miss_count', $sd)) {
                            $best = (float) $sd['listing_miss_count'];
                            break;
                        }
                        if ($best === null) {
                            $best = (float) ($sd['miss_count'] ?? 0);
                        }
                    }
                    $value = (float) ($best ?? 0);
                }

                $chartData[] = [
                    'date' => $label,
                    'date_key' => $dateKey,
                    'value' => round($value, 2),
                ];
            }

            // Ensure today's California point matches live listing page Missing L
            $todayKey = now(self::TZ)->toDateString();
            $todayLabel = now(self::TZ)->format('M d');
            $live = $this->liveMissingL($isAll, $channelKey);
            if ($badgeValue !== null && $badgeValue !== '' && is_numeric($badgeValue)) {
                $live = (float) $badgeValue;
            }

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

            // Drop helper key from payload
            $chartData = array_map(function ($p) {
                return ['date' => $p['date'], 'value' => $p['value']];
            }, $chartData);

            return response()->json(['success' => true, 'data' => array_values($chartData)]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing chartData failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => []], 500);
        }
    }

    public function updateSellerPortal(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:channel_master,id',
            'seller_portal' => 'nullable|string|max:1000|url',
        ]);

        if (! Schema::hasColumn('channel_master', 'seller_link')) {
            return response()->json([
                'success' => false,
                'message' => 'channel_master.seller_link column is not available.',
            ], 500);
        }

        try {
            $channel = ChannelMaster::find($request->integer('id'));
            if (! $channel) {
                return response()->json(['success' => false, 'message' => 'Channel not found.'], 404);
            }

            $value = trim((string) $request->input('seller_portal', ''));
            $channel->seller_link = $value === '' ? null : $value;
            $channel->save();

            return response()->json([
                'success' => true,
                'message' => 'Seller Portal updated.',
                'data' => [
                    'id' => $channel->id,
                    'seller_portal' => $channel->seller_link,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing updateSellerPortal failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function submitDar(Request $request)
    {
        $request->validate([
            'report' => 'required|string|max:5000',
        ]);

        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'You must be logged in to submit a DAR.',
            ], 401);
        }

        try {
            $dar = MissingListingDar::create([
                'user_id' => $user->id,
                'report' => trim((string) $request->input('report')),
                'submitted_at' => now(self::TZ),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'DAR submitted successfully.',
                'data' => [
                    'id' => $dar->id,
                    'user_name' => $user->name,
                    'report' => $dar->report,
                    'submitted_at' => $dar->submitted_at?->timezone(self::TZ)->toIso8601String(),
                    'submitted_at_california' => $dar->submitted_at
                        ? $dar->submitted_at->timezone(self::TZ)->format('M j, Y g:i A T')
                        : null,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing submitDar failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function darHistory(Request $request)
    {
        try {
            $rows = MissingListingDar::with('user:id,name')
                ->orderByDesc('submitted_at')
                ->orderByDesc('id')
                ->get(['id', 'user_id', 'report', 'submitted_at']);

            $data = $rows->map(function ($r) {
                $ca = $r->submitted_at?->timezone(self::TZ);

                return [
                    'id' => $r->id,
                    'user_name' => $r->user->name ?? 'Unknown',
                    'report' => $r->report,
                    'submitted_at' => $ca?->toIso8601String(),
                    // Pre-formatted California / Pacific display
                    'submitted_at_california' => $ca ? $ca->format('M j, Y g:i A T') : '-',
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'count' => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Missing Listing darHistory failed: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function persistListingMissingHistory(Collection $rows): void
    {
        if (! Schema::hasTable('channel_master_daily_data') || $rows->isEmpty()) {
            return;
        }

        try {
            $today = now(self::TZ)->toDateString();

            foreach ($rows as $row) {
                $channelKey = ListingChannelCounts::normalize((string) ($row['channel'] ?? ''));
                if ($channelKey === '') {
                    continue;
                }

                $existing = ChannelMasterSummary::where('channel', $channelKey)
                    ->whereDate('snapshot_date', $today)
                    ->first();

                $sd = is_array($existing?->summary_data) ? $existing->summary_data : [];
                $sd['listing_miss_count'] = (int) ($row['missing_listing'] ?? 0);
                $sd['listing_req'] = (int) ($row['req'] ?? 0);
                $sd['listing_nrl'] = (int) ($row['nrl'] ?? 0);
                $sd['listing_listed'] = (int) ($row['listed'] ?? 0);
                $sd['listing_captured_at'] = now(self::TZ)->toDateTimeString();

                ChannelMasterSummary::updateOrCreate(
                    [
                        'channel' => $channelKey,
                        'snapshot_date' => $today,
                    ],
                    [
                        'summary_data' => $sd,
                        'notes' => $existing?->notes ?: 'Listing Missing L snapshot (California)',
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Missing Listing persistListingMissingHistory failed: ' . $e->getMessage());
        }
    }

    private function liveMissingL(bool $isAll, string $channelKey): float
    {
        if ($isAll) {
            $total = 0;
            $seen = [];
            $masters = Schema::hasTable('channel_master')
                ? ChannelMaster::whereRaw('LOWER(TRIM(status)) = ?', ['active'])
                    ->whereNotNull('channel')
                    ->pluck('channel')
                : collect();

            foreach ($masters as $name) {
                if (! ListingChannelCounts::hasListingSource((string) $name)) {
                    continue;
                }
                $key = ListingChannelCounts::normalize((string) $name);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $c = ListingChannelCounts::forChannel((string) $name, false);
                $total += (int) ($c['Pending'] ?? 0);
            }

            return (float) $total;
        }

        $c = ListingChannelCounts::forChannel($channelKey, false);

        return (float) ($c['Pending'] ?? 0);
    }

    /**
     * @return list<array{date: string, value: float}>
     */
    private function chartWithLiveOnly(bool $isAll, string $channelKey, mixed $badgeValue): array
    {
        $live = ($badgeValue !== null && $badgeValue !== '' && is_numeric($badgeValue))
            ? (float) $badgeValue
            : $this->liveMissingL($isAll, $channelKey);

        return [[
            'date' => now(self::TZ)->format('M d'),
            'value' => round($live, 2),
        ]];
    }

    /**
     * @return list<string>
     */
    private function channelAliases(string $normalizedKey): array
    {
        $aliases = [$normalizedKey];

        $map = [
            'ebaytwo' => ['ebay2', 'ebaytwo'],
            'ebay2' => ['ebay2', 'ebaytwo'],
            'ebaythree' => ['ebay3', 'ebaythree'],
            'ebay3' => ['ebay3', 'ebaythree'],
            'ebay' => ['ebay', 'ebay1', 'ebayone'],
            'tiktokshop' => ['tiktokshop', 'tiktok'],
            'tiktok' => ['tiktokshop', 'tiktok'],
            'bestbuyusa' => ['bestbuyusa', 'bestbuy'],
            'bestbuy' => ['bestbuyusa', 'bestbuy'],
            'fbmarketplace' => ['fbmarketplace', 'facebookmarketplace'],
            'shopifyb2c' => ['shopifyb2c', 'shopify'],
        ];

        foreach ($map[$normalizedKey] ?? [] as $a) {
            $aliases[] = $a;
        }

        return array_values(array_unique($aliases));
    }
}
