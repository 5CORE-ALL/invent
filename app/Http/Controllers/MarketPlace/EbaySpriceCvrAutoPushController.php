<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\CronExecutionLog;
use App\Services\EbaySpriceCvrAutoPushService;
use App\Support\SpriceCvrMultRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Symfony\Component\Process\Process;
use Throwable;

class EbaySpriceCvrAutoPushController extends Controller
{
    public function index(): View
    {
        $rule = SpriceCvrMultRule::settings();
        $lastRuns = CronExecutionLog::query()
            ->where('job_name', 'eBay Sprice×CVR Auto Push')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('market-places.ebay_sprice_cvr_auto_push', [
            'rule' => $rule,
            'lastRuns' => $lastRuns,
            'scheduleLabel' => 'Daily at 2:00 PM IST (Asia/Kolkata)',
            'command' => 'ebay:sprice-cvr-auto-push',
        ]);
    }

    /**
     * Start the pipeline in the background (or dry-run sync for a quick preview).
     */
    public function run(Request $request): JsonResponse
    {
        $channels = $request->input('channels', ['ebay1', 'ebay2', 'ebay3']);
        if (! is_array($channels)) {
            $channels = array_filter(array_map('trim', explode(',', (string) $channels)));
        }
        $channels = array_values(array_intersect(
            EbaySpriceCvrAutoPushService::CHANNELS,
            array_map('strtolower', $channels)
        ));
        if ($channels === []) {
            $channels = EbaySpriceCvrAutoPushService::CHANNELS;
        }

        $dryRun = (bool) $request->boolean('dry_run');
        $skipPush = (bool) $request->boolean('skip_push');
        $skipClear = (bool) $request->boolean('skip_clear');
        $limit = $request->filled('limit') ? max(1, (int) $request->input('limit')) : null;

        $options = [
            '--channels' => implode(',', $channels),
        ];
        if ($dryRun) {
            $options['--dry-run'] = true;
        }
        if ($skipPush) {
            $options['--skip-push'] = true;
        }
        if ($skipClear) {
            $options['--skip-clear'] = true;
        }
        if ($limit !== null) {
            $options['--limit'] = $limit;
        }

        // Small/limited dry-run: run sync so the UI can show apply counts quickly.
        // Full dry-run still Clear+Apply in DB (no eBay push) — run in background when large.
        if ($dryRun && $limit !== null && $limit <= 50) {
            $exitCode = Artisan::call('ebay:sprice-cvr-auto-push', $options);
            $output = trim(Artisan::output());

            return response()->json([
                'success' => $exitCode === 0,
                'mode' => 'sync',
                'exit_code' => $exitCode,
                'output' => $output,
                'message' => $exitCode === 0
                    ? 'Dry-run finished: SPRICE cleared + applied in DB (not pushed to eBay). Refresh tabulator.'
                    : 'Command finished with errors.',
            ]);
        }

        $php = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/ebay-sprice-cvr-auto-push.log');

        $args = [$php, $artisan, 'ebay:sprice-cvr-auto-push', '--channels='.implode(',', $channels)];
        if ($skipPush) {
            $args[] = '--skip-push';
        }
        if ($skipClear) {
            $args[] = '--skip-clear';
        }
        if ($limit !== null) {
            $args[] = '--limit='.$limit;
        }

        try {
            $process = new Process($args);
            $process->setWorkingDirectory(base_path());
            $process->setTimeout(null);
            $process->disableOutput();
            // Append stdout/stderr to log via shell redirection when possible
            if (DIRECTORY_SEPARATOR === '\\') {
                $process->start();
            } else {
                $cmd = implode(' ', array_map('escapeshellarg', $args))
                    .' >> '.escapeshellarg($logFile).' 2>&1 &';
                exec($cmd);
            }

            return response()->json([
                'success' => true,
                'mode' => 'background',
                'message' => 'Pipeline started in background. Watch Cron Monitor or '
                    .basename($logFile).' for progress.',
                'log_file' => $logFile,
                'channels' => $channels,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start command: '.$e->getMessage(),
            ], 500);
        }
    }
}
