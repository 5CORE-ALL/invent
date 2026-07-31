<?php

namespace App\Http\Controllers\MarketPlace;

use App\Http\Controllers\Controller;
use App\Models\CronExecutionLog;
use App\Support\SpriceCvrMultRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Symfony\Component\Process\Process;
use Throwable;

class AmazonSpriceCvrAutoPushController extends Controller
{
    public function index(): View
    {
        $rule = SpriceCvrMultRule::settings();
        $lastRuns = CronExecutionLog::query()
            ->where('job_name', 'Amazon Sprice×CVR Auto Push')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return view('market-places.amazon_sprice_cvr_auto_push', [
            'rule' => $rule,
            'lastRuns' => $lastRuns,
            'scheduleLabel' => 'Daily at 2:00 PM IST (Asia/Kolkata)',
            'command' => 'amazon:sprice-cvr-auto-push',
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        $dryRun = (bool) $request->boolean('dry_run');
        $skipPush = (bool) $request->boolean('skip_push');
        $skipClear = (bool) $request->boolean('skip_clear');
        $limit = $request->filled('limit') ? max(1, (int) $request->input('limit')) : null;

        $options = [];
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

        if ($dryRun && $limit !== null && $limit <= 50) {
            $exitCode = Artisan::call('amazon:sprice-cvr-auto-push', $options);
            $output = trim(Artisan::output());

            return response()->json([
                'success' => $exitCode === 0,
                'mode' => 'sync',
                'exit_code' => $exitCode,
                'output' => $output,
                'message' => $exitCode === 0
                    ? 'Dry-run finished: SPRICE cleared + applied in DB (not pushed to Amazon). Refresh tabulator.'
                    : 'Command finished with errors.',
            ]);
        }

        $php = PHP_BINARY ?: 'php';
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/amazon-sprice-cvr-auto-push.log');

        $args = [$php, $artisan, 'amazon:sprice-cvr-auto-push'];
        if ($dryRun) {
            $args[] = '--dry-run';
        }
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
            if (DIRECTORY_SEPARATOR === '\\') {
                $process = new Process($args);
                $process->setWorkingDirectory(base_path());
                $process->setTimeout(null);
                $process->disableOutput();
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
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start command: '.$e->getMessage(),
            ], 500);
        }
    }
}
