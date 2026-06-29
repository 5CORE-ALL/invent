<?php

namespace App\Services\Support;

use App\Http\Controllers\ProductMaster\VideoMasterController;
use Illuminate\Support\Facades\Log;

class VideoMasterPushRunner
{
    public function __construct(
        private readonly VideoMasterPushJobStore $store,
    ) {}

    public function run(): int
    {
        @set_time_limit(0);

        while (true) {
            $state = $this->store->load();
            if (($state['status'] ?? 'idle') !== 'running') {
                return 0;
            }

            $tasks = array_values($state['tasks'] ?? []);
            $index = (int) ($state['current_index'] ?? 0);
            $total = count($tasks);

            if ($index >= $total) {
                $this->store->update(function (array $state) {
                    $state['status'] = 'completed';
                    $state['current_marketplace'] = null;
                    $state['finished_at'] = now()->toDateTimeString();
                    $state['last_message'] = "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.";

                    return $state;
                });
                $state = $this->store->load();
                $this->store->appendMessage(
                    "Completed: {$state['ok_count']} ok, {$state['fail_count']} failed.",
                    ((int) ($state['fail_count'] ?? 0)) === 0
                );

                return 0;
            }

            $task = $tasks[$index];
            $mp = (string) ($task['marketplace'] ?? '');
            $sku = (string) ($state['sku'] ?? '');
            $mode = (string) ($state['mode'] ?? 'replace');
            $mainMap = is_array($state['main_by_marketplace'] ?? null) ? $state['main_by_marketplace'] : [];

            $this->store->update(function (array $state) use ($mp, $index, $total, $sku) {
                $state['current_marketplace'] = $mp;
                $state['last_message'] = 'Pushing '.($index + 1)."/{$total}: {$sku} → {$mp}";

                return $state;
            });

            $ok = false;
            $message = "{$mp}: failed";
            $result = ['success' => false, 'message' => $message, 'metrics_saved' => false];

            try {
                $result = app(VideoMasterController::class)->runQueuedMarketplacePush(
                    $sku,
                    $mp,
                    is_array($task['videos'] ?? null) ? $task['videos'] : [],
                    $mode,
                    $mainMap
                );
                $ok = (bool) ($result['success'] ?? false);
                $message = (string) ($result['message'] ?? ($ok ? 'OK' : 'Failed'));
            } catch (\Throwable $e) {
                $message = "{$mp}: ".$e->getMessage();
                $result = ['success' => false, 'message' => $message, 'metrics_saved' => false];
                Log::warning('VideoMasterPushRunner task failed', [
                    'sku' => $sku,
                    'marketplace' => $mp,
                    'error' => $e->getMessage(),
                ]);
            }

            $metricsFailed = $ok && ! ($result['metrics_saved'] ?? false);
            $this->store->update(function (array $state) use ($index, $mp, $ok, $message, $result, $metricsFailed) {
                $state['current_index'] = $index + 1;
                if ($ok) {
                    $state['ok_count'] = ((int) ($state['ok_count'] ?? 0)) + 1;
                } else {
                    $state['fail_count'] = ((int) ($state['fail_count'] ?? 0)) + 1;
                }
                if ($metricsFailed) {
                    $state['metrics_fail_count'] = ((int) ($state['metrics_fail_count'] ?? 0)) + 1;
                }
                $state['results'][$mp] = $result;
                if (isset($state['tasks'][$index]) && is_array($state['tasks'][$index])) {
                    $state['tasks'][$index]['status'] = $ok ? 'done' : 'failed';
                    $state['tasks'][$index]['result'] = $result;
                }
                $state['last_message'] = $message;

                return $state;
            });
            $this->store->appendMessage($message, $ok);
        }
    }
}
