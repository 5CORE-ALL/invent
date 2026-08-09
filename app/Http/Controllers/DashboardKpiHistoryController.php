<?php

namespace App\Http\Controllers;

use App\Models\BadgeData;
use App\Models\BadgeDataHistory;
use App\Support\Badges\BadgeDataCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardKpiHistoryController extends Controller
{
    /**
     * Rolling history + current tone for one badges_data KPI key.
     */
    public function show(Request $request): JsonResponse
    {
        $key = trim((string) $request->query('key', ''));
        $parsed = BadgeDataCatalog::parseKey($key);
        if (! $parsed) {
            return response()->json(['success' => false, 'message' => 'Invalid KPI key.'], 422);
        }

        $page = $parsed['page'];
        $field = $parsed['field'];
        $days = (int) $request->query('days', 30);
        $badgeValue = $request->query('badge_value');
        $live = ($badgeValue !== null && $badgeValue !== '' && is_numeric($badgeValue))
            ? (float) $badgeValue
            : (isset(BadgeData::dataForPage($page)[$field]) && is_numeric(BadgeData::dataForPage($page)[$field])
                ? (float) BadgeData::dataForPage($page)[$field]
                : null);

        // Keep today's point fresh whenever the chart is opened.
        if ($live !== null) {
            BadgeDataHistory::recordPage($page, [$field => $live]);
        }

        $series = BadgeDataHistory::series($page, $field, $days, $live);
        $tone = BadgeDataHistory::toneFor($page, $field, $live);

        return response()->json([
            'success' => true,
            'key' => $key,
            'page' => $page,
            'field' => $field,
            'label' => BadgeDataCatalog::labelFor($page, $field),
            'tone' => $tone,
            'lower_better' => BadgeDataCatalog::isLowerBetter($page, $field),
            'current' => $live,
            'current_display' => BadgeDataCatalog::formatDisplay($page, $field, $live),
            'data' => $series,
        ]);
    }

    /**
     * Batch tones for many KPI keys (dashboard dots).
     */
    public function tones(Request $request): JsonResponse
    {
        $keys = $request->input('keys', []);
        if (! is_array($keys)) {
            $keys = [];
        }

        $out = [];
        foreach ($keys as $key) {
            $key = trim((string) $key);
            $parsed = BadgeDataCatalog::parseKey($key);
            if (! $parsed) {
                continue;
            }
            $page = $parsed['page'];
            $field = $parsed['field'];
            $live = BadgeData::dataForPage($page)[$field] ?? null;
            $out[$key] = [
                'tone' => BadgeDataHistory::toneFor($page, $field, $live),
                'value' => is_numeric($live) ? (float) $live : null,
                'label' => BadgeDataCatalog::labelFor($page, $field),
                'lower_better' => BadgeDataCatalog::isLowerBetter($page, $field),
            ];
        }

        return response()->json(['success' => true, 'tones' => $out]);
    }
}
