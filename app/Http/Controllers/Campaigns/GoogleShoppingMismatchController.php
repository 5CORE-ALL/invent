<?php

namespace App\Http\Controllers\Campaigns;

use App\Http\Controllers\Controller;
use App\Services\GoogleShoppingLiveMatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * /google/shopping/google-shopping/mismatches — the page behind the "SBGT/SBID not matching" alert.
 * Lists every ENABLED Shopping campaign whose SBGT/SBID differs from the live LBgt/LBid, lets the user
 * tick campaigns and push them to Google Ads (same Google Ads calls as the grid's Push SBGT / Push SBID).
 */
class GoogleShoppingMismatchController extends Controller
{
    /** Campaigns per push request: each one makes Google Ads calls, so the page sends them in small batches. */
    private const MAX_IDS_PER_PUSH = 25;

    public function index()
    {
        return view('campaign.google-shopping-mismatches');
    }

    public function data(Request $request, GoogleShoppingLiveMatchService $match): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);

        $out = $match->mismatchRows($request->boolean('refresh'));

        return response()->json([
            'ok' => $out['error'] === null,
            'message' => $out['error'],
            'checked' => $out['checked'],
            'data' => $out['rows'],
        ])->header('Cache-Control', 'no-store');
    }

    public function push(Request $request, GoogleShoppingLiveMatchService $match): JsonResponse
    {
        @ini_set('memory_limit', '512M');
        @set_time_limit(0);

        $ids = collect((array) $request->input('campaign_ids', []))
            ->map(fn ($id) => preg_replace('/\D+/', '', (string) $id) ?? '')
            ->filter()
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json(['ok' => false, 'message' => 'Select at least one campaign.'], 422);
        }
        if ($ids->count() > self::MAX_IDS_PER_PUSH) {
            return response()->json([
                'ok' => false,
                'message' => 'Too many campaigns in one request (max '.self::MAX_IDS_PER_PUSH.'). Send in batches.',
            ], 422);
        }

        $out = $match->pushSelected($ids->all());
        $matched = collect($out['results'])->where('status', 'matched')->count();
        $problems = collect($out['results'])->whereIn('status', ['not_matched', 'blocked', 'skipped'])->count();

        return response()->json([
            'ok' => $out['push_errors'] === [] && $problems === 0,
            'message' => $matched.' matched, '.$problems.' not pushed / not matching.',
            'results' => $out['results'],
            'push_errors' => $out['push_errors'],
        ]);
    }
}
