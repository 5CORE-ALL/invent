<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\PricePushPending;
use App\Services\PricePushPendingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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

    /**
     * Push several channel prices IN THE BACKGROUND: every row is stored as "Queued", the response goes back at once and
     * the pushes run on the server after the response (they keep going when the browser tab is refreshed or closed).
     * A push that never finishes stays "pending" and is picked up by the hourly retry. Progress: GET /price-push-status.
     *
     * Body (JSON): { sku, items: [ { sku?, marketplace, price, channel?, apply_siblings?, self_pick_price?, goods_id?, sku_id? } ] }
     */
    public function pushBackground(Request $request, PricePushPendingService $svc)
    {
        // The job must survive a closed / refreshed browser: never stop because the client went away
        @ignore_user_abort(true);
        @set_time_limit(0);
        $sku = trim((string) $request->input('sku'));
        $items = $request->input('items');
        if ($sku === '' || ! is_array($items) || ! $items) {
            return response()->json(['success' => false, 'message' => 'sku and items are required'], 422);
        }
        if (! $svc->available()) {
            return response()->json(['success' => false, 'message' => 'Background push is not available (price_push_pendings table missing)'], 503);
        }

        $user = Auth::user();
        $by = optional($user)->name;
        $jobs = [];
        foreach ($items as $it) {
            if (! is_array($it)) {
                continue;
            }
            $mp = trim((string) ($it['marketplace'] ?? ''));
            $price = round((float) ($it['price'] ?? 0), 2);
            if ($mp === '' || ! ($price > 0)) {
                continue;
            }
            $itemSku = trim((string) ($it['sku'] ?? '')) ?: $sku;
            $extras = [];
            foreach (['self_pick_price', 'goods_id', 'sku_id', 'product_id'] as $k) {
                if (isset($it[$k]) && $it[$k] !== '' && $it[$k] !== null) {
                    $extras[$k] = $it[$k];
                }
            }
            // Remembered so a takeover / hourly retry also pushes the siblings
            if (! empty($it['apply_siblings']) && filter_var($it['apply_siblings'], FILTER_VALIDATE_BOOLEAN)) {
                $extras['apply_siblings'] = 1;
            }
            $payload = array_merge($it, ['sku' => $itemSku, 'marketplace' => $mp, 'price' => $price]);
            $channel = (string) ($it['channel'] ?? '');
            // "Queued" rows are what the page polls; they also make the hourly retry take over if this process dies
            $svc->record($itemSku, $mp, $channel, $price, $extras, false, PricePushPending::QUEUED_PREFIX . ' — pushing in the background', $by);
            $jobs[] = ['sku' => $itemSku, 'marketplace' => $mp, 'channel' => $channel, 'price' => $price, 'extras' => $extras, 'payload' => $payload];
        }
        if (! $jobs) {
            return response()->json(['success' => false, 'message' => 'Nothing to push (price must be > 0)'], 422);
        }

        // SEQUENTIAL QUEUE: the rows above ("Queued") are the queue. Exactly one worker at a time drains it, oldest first
        // (first in, first out) — this request's worker, or whichever worker is already running, or the per-minute
        // price-push:run-queued if that one died. Runs after the response was sent, so a refresh / closed tab changes nothing.
        app()->terminating(function () use ($svc) {
            @ignore_user_abort(true);
            @set_time_limit(0);
            $svc->drainQueue();
        });

        return response()->json(['success' => true, 'queued' => count($jobs)]);
    }

    /** Progress of the background pushes of one SKU: marketplace key → { status, error, price, message, saved_only }. */
    public function status(Request $request, PricePushPendingService $svc)
    {
        $sku = strtoupper(trim((string) $request->query('sku', '')));
        if ($sku === '' || ! $svc->available()) {
            return response()->json(['rows' => new \stdClass()]);
        }
        $out = [];
        foreach (PricePushPending::where('sku', $sku)->get() as $r) {
            $cached = Cache::get('price_push_result:' . $r->sku . '|' . $r->marketplace) ?: [];
            $out[$r->marketplace] = [
                'status' => $r->status,
                'error' => $r->last_error,
                'price' => $r->price,
                'message' => $cached['message'] ?? null,
                'saved_only' => ! empty($cached['saved_only']),
                'at' => optional($r->last_attempt_at)->toIso8601String(), // lets the page tell whether the server got its job
            ];
        }

        return response()->json(['rows' => $out ?: new \stdClass()]);
    }

    /** Outstanding (pending / gave up) pushes — drives the red alert on the pricing page. */
    public function index(Request $request, PricePushPendingService $svc)
    {
        if (! $svc->available()) {
            return response()->json(['count' => 0, 'skus' => 0, 'items' => []]);
        }
        $q = PricePushPending::outstanding();
        // ?sku=A  or  ?skus[]=A&skus[]=B  → only those SKUs (Hist column: "channels not updated" of a SKU / parent)
        $only = array_filter(array_map(
            fn ($s) => strtoupper(trim((string) $s)),
            array_merge((array) $request->query('skus', []), $request->filled('sku') ? [$request->query('sku')] : [])
        ));
        if ($only) {
            $q->whereIn('sku', array_values(array_unique($only)));
        }
        $rows = $q->orderByDesc('last_attempt_at')->limit(300)->get();

        $actions = $svc->actionsFor($rows);

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
                'action' => $actions[$r->id]['action'] ?? 'awaited',
                'action_note' => $actions[$r->id]['note'] ?? '',
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
