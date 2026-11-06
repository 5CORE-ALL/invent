<?php

namespace App\Http\Controllers\MarketPlace\Lqs\Concerns;

use App\Models\LqsMarketplaceScore;
use App\Services\Lqs\FaireLqsApiSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;

/** Faire LQS actions (scored from the live Faire product API) for the /lqs-faire page. */
trait HandlesFaireLqsApi
{
    /** Score one SKU's Faire listing from the live Faire product (no AI, no sheet). */
    public function apiScore(Request $request, FaireLqsApiSyncService $sync)
    {
        $request->validate(['sku' => 'required|string']);

        try {
            $result = $sync->syncSku($request->input('sku'));

            return response()->json([
                'success' => true,
                'sku' => $result['sku'],
                'lqs' => $result['lqs'],
                'findings' => $result['findings'],
                'suggestions' => $result['suggestions'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Faire API scoring failed.',
            ], 500);
        }
    }

    /** A run counts as active only while it keeps updating (a crashed run must not block the button forever). */
    private function activeSyncStatus(): ?array
    {
        $status = Cache::get(FaireLqsApiSyncService::STATUS_KEY);
        if (! is_array($status) || empty($status['running'])) {
            return null;
        }

        return (time() - (int) ($status['updated_at'] ?? 0)) <= 300 ? $status : null;
    }

    /** Page button: score every Faire listing in a background process. */
    public function startApiSync(FaireLqsApiSyncService $sync)
    {
        if (! $sync->isConfigured()) {
            return response()->json(['success' => false, 'message' => 'Faire is not configured (FAIRE_ACCESS_TOKEN) or faire_metric / product_master tables are missing.'], 422);
        }
        if ($this->activeSyncStatus()) {
            return response()->json(['success' => true, 'already_running' => true]);
        }

        try {
            // Not PHP_BINARY: under php-fpm/Apache that is the web binary, not the CLI.
            $php = (new PhpExecutableFinder())->find(false) ?: 'php';
            $cmd = escapeshellarg($php).' '.escapeshellarg(base_path('artisan'))
                .' lqs:sync-faire';

            Cache::put(FaireLqsApiSyncService::STATUS_KEY, [
                'running' => true, 'total' => null, 'done' => 0, 'failed' => 0,
                'started_at' => now()->toDateTimeString(), 'finished_at' => null,
                'message' => 'Starting…', 'updated_at' => time(),
            ], 600);

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                pclose(popen('start /B '.$cmd, 'r'));
            } else {
                exec($cmd.' > /dev/null 2>&1 &');
            }

            return response()->json(['success' => true, 'already_running' => false]);
        } catch (\Throwable $e) {
            Cache::forget(FaireLqsApiSyncService::STATUS_KEY);
            Log::error('Faire LQS API sync failed to start: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not start the sync: '.$e->getMessage()], 500);
        }
    }

    /** Progress for the page button (polled while a sync runs). */
    public function apiSyncStatus(FaireLqsApiSyncService $sync)
    {
        $status = Cache::get(FaireLqsApiSyncService::STATUS_KEY);
        $active = $this->activeSyncStatus();

        $catalog = collect($sync->catalogSkus());
        $skus = $catalog->count();
        // Only count scores for SKUs this app lists on Faire.
        $scored = LqsMarketplaceScore::query()
            ->where('marketplace', FaireLqsApiSyncService::MARKETPLACE)
            ->whereNotNull('lqs')->where('lqs', '>', 0)
            ->pluck('sku')
            ->map(fn ($s) => FaireLqsApiSyncService::normalizeSku((string) $s))
            ->unique()
            ->intersect($catalog)
            ->count();

        return response()->json([
            'success' => true,
            'running' => (bool) $active,
            'total' => $active['total'] ?? ($status['total'] ?? null),
            'done' => $active['done'] ?? ($status['done'] ?? 0),
            'failed' => $active['failed'] ?? ($status['failed'] ?? 0),
            'message' => is_array($status) ? ($status['message'] ?? null) : null,
            'finished_at' => is_array($status) ? ($status['finished_at'] ?? null) : null,
            'skus_with_item_id' => $skus,
            'scored' => $scored,
        ]);
    }
}
