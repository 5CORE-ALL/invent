<?php

namespace App\Http\Controllers\MarketPlace\Lqs\Concerns;

use App\Models\LqsMarketplaceScore;
use App\Services\Lqs\EbayLqsApiSyncService;
use App\Services\Lqs\LqsListingAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;

/**
 * AI audit + live-API LQS actions shared by the eBay, eBay 2 and eBay 3 LQS pages.
 * Uses $this->slug() ('ebay' | 'ebay2' | 'ebay3') so each account scores its own listings.
 */
trait HandlesEbayLqsApi
{
    public function auditPrompt(LqsListingAuditService $audit)
    {
        try {
            $saved = $audit->getOrCreatePrompt($this->slug());

            return response()->json([
                'success' => true,
                'prompt' => $saved['prompt'],
                'is_default' => $saved['is_default'],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to load audit prompt.'], 500);
        }
    }

    public function saveAuditPrompt(Request $request, LqsListingAuditService $audit)
    {
        $request->validate([
            'prompt' => 'required|string|min:20',
        ]);

        try {
            $prompt = $audit->savePrompt($this->slug(), $request->input('prompt'));

            return response()->json([
                'success' => true,
                'prompt' => $prompt,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to save prompt.'], 500);
        }
    }

    /** Score one SKU straight from live eBay API data (no AI, no sheet). */
    public function apiScore(Request $request, EbayLqsApiSyncService $sync)
    {
        $request->validate(['sku' => 'required|string']);

        try {
            $result = $sync->syncSku($request->input('sku'), $this->slug());

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
                'message' => $e->getMessage() ?: 'eBay API scoring failed.',
            ], 500);
        }
    }

    /** A run counts as active only while it keeps updating (a crashed run must not block the button forever). */
    private function activeSyncStatus(): ?array
    {
        $status = Cache::get(EbayLqsApiSyncService::statusKey($this->slug()));
        if (! is_array($status) || empty($status['running'])) {
            return null;
        }

        return (time() - (int) ($status['updated_at'] ?? 0)) <= 300 ? $status : null;
    }

    /** Page button: score every eBay listing from the live API in a background process. */
    public function startApiSync(EbayLqsApiSyncService $sync)
    {
        if (! $sync->isConfigured($this->slug())) {
            return response()->json(['success' => false, 'message' => EbayLqsApiSyncService::label($this->slug()).' API is not configured.'], 422);
        }
        if ($this->activeSyncStatus()) {
            return response()->json(['success' => true, 'already_running' => true]);
        }

        try {
            // Not PHP_BINARY: under php-fpm/Apache that is the web binary, not the CLI.
            $php = (new PhpExecutableFinder())->find(false) ?: 'php';
            $cmd = escapeshellarg($php).' '.escapeshellarg(base_path('artisan'))
                .' lqs:sync-ebay --channel='.escapeshellarg($this->slug()).' --limit=500 --stale-hours=24 --sleep-ms=250';

            Cache::put(EbayLqsApiSyncService::statusKey($this->slug()), [
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
            Cache::forget(EbayLqsApiSyncService::statusKey($this->slug()));
            Log::error($this->slug().' LQS API sync failed to start: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Could not start the sync: '.$e->getMessage()], 500);
        }
    }

    /** Progress for the page button (polled while a sync runs). */
    public function apiSyncStatus()
    {
        $status = Cache::get(EbayLqsApiSyncService::statusKey($this->slug()));
        $active = $this->activeSyncStatus();

        $metricModel = EbayLqsApiSyncService::metricsModel($this->slug());
        $skus = $metricModel::query()
            ->whereNotNull('item_id')->where('item_id', '!=', '')
            ->whereNotNull('sku')->where('sku', '!=', '')
            ->distinct()->count('sku');
        $scored = LqsMarketplaceScore::query()
            ->where('marketplace', $this->slug())
            ->whereNotNull('lqs')->where('lqs', '>', 0)
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

    public function runAudit(Request $request, LqsListingAuditService $audit)
    {
        $request->validate([
            'sku' => 'required|string',
            'prompt' => 'nullable|string',
        ]);

        try {
            $result = $audit->run($this->slug(), $request->input('sku'), $request->input('prompt'));

            return response()->json(['success' => true] + $result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Audit failed.',
            ], 500);
        }
    }
}
