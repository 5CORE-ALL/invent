<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\PricePushPending;
use App\Services\PricePushPendingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PricePushPendingController extends Controller
{
    /**
     * Same as POST /cvr-master-push-price, but remembers the outcome: success → pushed (green),
     * failure → pending (red, retried hourly, e-mail alert to software5@5core.com).
     */
    public function push(Request $request, PricePushPendingService $svc)
    {
        [$ok, $message, , $resp] = $svc->runPush($request->all(), $request);

        $sku = trim((string) $request->input('sku'));
        $mp = trim((string) $request->input('marketplace'));
        $price = round((float) $request->input('price'), 2);
        if ($sku !== '' && $mp !== '' && $price > 0) {
            $extras = [];
            foreach (['self_pick_price', 'goods_id', 'sku_id', 'product_id'] as $k) {
                if ($request->filled($k)) {
                    $extras[$k] = $request->input($k);
                }
            }
            $row = $svc->record($sku, $mp, (string) $request->input('channel', ''), $price, $extras, $ok, $message, optional(Auth::user())->name);
            // First failure of this price → alert right away
            if ($row && ! $ok && $row->alerted_at === null) {
                if ($svc->sendAlert([$row], 'A channel price could not be pushed. It will be retried every hour.')) {
                    $row->forceFill(['alerted_at' => now()])->save();
                }
            }
        }

        return $resp ?: response()->json(['success' => false, 'message' => $message], 500);
    }

    /** Outstanding (pending / gave up) pushes — drives the red alert on the pricing page. */
    public function index(PricePushPendingService $svc)
    {
        if (! $svc->available()) {
            return response()->json(['count' => 0, 'skus' => 0, 'items' => []]);
        }
        $rows = PricePushPending::outstanding()->orderByDesc('last_attempt_at')->limit(300)->get();

        return response()->json([
            'count' => $rows->count(),
            'skus' => $rows->pluck('sku')->unique()->count(),
            'items' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'sku' => $r->sku,
                'channel' => $r->channel ?: $r->marketplace,
                'marketplace' => $r->marketplace,
                'price' => $r->price,
                'status' => $r->status,
                'attempts' => $r->attempts,
                'error' => $r->last_error,
                'last_attempt_at' => optional($r->last_attempt_at)->format('Y-m-d H:i'),
                'requested_by' => $r->requested_by,
            ])->values(),
        ]);
    }

    /** Retry now: one row (id) or the oldest 12 outstanding rows. */
    public function retry(Request $request, PricePushPendingService $svc)
    {
        if (! $svc->available()) {
            return response()->json(['success' => false, 'message' => 'Not available'], 404);
        }
        $rows = $request->filled('id')
            ? PricePushPending::outstanding()->where('id', (int) $request->input('id'))->get()
            : PricePushPending::outstanding()->orderBy('last_attempt_at')->limit(12)->get();
        $ok = 0;
        foreach ($rows as $row) {
            $row->status = 'pending'; // allow a manual retry of a "failed" row
            $row->save();
            if ($svc->retryRow($row)) {
                $ok++;
            }
        }

        return response()->json(['success' => true, 'tried' => $rows->count(), 'ok' => $ok]);
    }
}
