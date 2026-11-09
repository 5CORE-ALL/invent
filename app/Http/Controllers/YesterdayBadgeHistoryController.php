<?php

namespace App\Http\Controllers;

use App\Models\BadgeDataHistory;
use App\Support\Badges\YesterdayBadgeRegistry;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Shared history backend for the "Yesterday" badges registered in {@see YesterdayBadgeRegistry}.
 * Used by partials/y-badge-history (through partials/channel-badge-history) on every page that has such a badge.
 *
 *   GET  /y-badge/chart-data?page=…&metric=y_sales&days=30
 *   GET  /y-badge/prev-day?page=…
 *   POST /y-badge/snapshot?page=…   body: {y_sales: 1234}
 */
class YesterdayBadgeHistoryController extends Controller
{
    private const TZ = BadgeDataHistory::TZ;

    public function chart(Request $request): JsonResponse
    {
        $page = (string) $request->query('page', '');
        $metric = (string) $request->query('metric', '');
        if (! YesterdayBadgeRegistry::has($page) || ! in_array($metric, YesterdayBadgeRegistry::fieldsFor($page), true)) {
            return response()->json(['success' => false, 'message' => 'Unknown page or metric'], 422);
        }
        if (! Schema::hasTable('badges_data_histories')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $days = (int) $request->query('days', 30); // 0 = lifetime
        $q = BadgeDataHistory::query()
            ->where('page_name', YesterdayBadgeRegistry::storageName($page))
            ->where('field', $metric)
            ->whereNotNull('value')
            ->orderBy('snapshot_date');
        if ($days > 0) {
            $q->whereDate('snapshot_date', '>=', now(self::TZ)->subDays($days - 1)->toDateString());
        }

        $data = [];
        foreach ($q->get(['snapshot_date', 'value']) as $row) {
            $ymd = Carbon::parse($row->snapshot_date)->toDateString();
            $data[] = [
                'date' => Carbon::parse($ymd)->format('M d'),
                'full_date' => $ymd,
                'value' => (float) $row->value,
                'source' => 'snapshot',
            ];
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function prevDay(Request $request): JsonResponse
    {
        $page = (string) $request->query('page', '');
        if (! YesterdayBadgeRegistry::has($page)) {
            return response()->json(['success' => false, 'message' => 'Unknown page'], 422);
        }
        if (! Schema::hasTable('badges_data_histories')) {
            return response()->json(['success' => true, 'date' => null, 'metrics' => null]);
        }

        $today = now(self::TZ)->toDateString();
        $metrics = [];
        $date = null;
        foreach (YesterdayBadgeRegistry::fieldsFor($page) as $field) {
            // Latest earlier day that actually saved this field.
            $row = BadgeDataHistory::query()
                ->where('page_name', YesterdayBadgeRegistry::storageName($page))
                ->where('field', $field)
                ->whereNotNull('value')
                ->whereDate('snapshot_date', '<', $today)
                ->orderByDesc('snapshot_date')
                ->first(['snapshot_date', 'value']);
            if ($row) {
                $metrics[$field] = (float) $row->value;
                $date = Carbon::parse($row->snapshot_date)->toDateString();
            }
        }

        return response()->json(['success' => true, 'date' => $date, 'metrics' => $metrics ?: null]);
    }

    /** The page reports the value it is showing right now (today's Pacific snapshot). */
    public function snapshot(Request $request): JsonResponse
    {
        $page = (string) $request->query('page', '');
        if (! YesterdayBadgeRegistry::has($page)) {
            return response()->json(['success' => false, 'message' => 'Unknown page'], 422);
        }

        try {
            $name = YesterdayBadgeRegistry::storageName($page);
            $today = now(self::TZ)->toDateString();
            $values = [];
            foreach (YesterdayBadgeRegistry::fieldsFor($page) as $field) {
                $raw = $request->input($field);
                if (! is_numeric($raw)) {
                    continue;
                }
                // The page sends whole numbers; do not overwrite a precise value the nightly job already saved.
                $existing = BadgeDataHistory::query()
                    ->where('page_name', $name)->where('field', $field)->whereDate('snapshot_date', $today)
                    ->value('value');
                if ($existing !== null && abs((float) $existing - (float) $raw) < 1.0) {
                    continue;
                }
                $values[$field] = (float) $raw;
            }
            if ($values !== []) {
                BadgeDataHistory::recordPage($name, $values);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::warning('Yesterday badge snapshot failed: ' . $e->getMessage());

            return response()->json(['success' => false], 500);
        }
    }
}
