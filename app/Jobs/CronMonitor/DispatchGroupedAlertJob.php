<?php

namespace App\Jobs\CronMonitor;

use App\Models\CronAlertBatch;
use App\Models\CronMonitorAlert;
use App\Models\User;
use App\Services\CronMonitor\TaskManagerStatusReporter;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class DispatchGroupedAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $batchId) {}

    public function handle(TaskManagerStatusReporter $taskManager, WhatsAppService $whatsApp): void
    {
        $batch = CronAlertBatch::find($this->batchId);
        if (! $batch || $batch->notified) {
            return;
        }

        $alerts = $batch->payload['alerts'] ?? [];
        if ($alerts === []) {
            $batch->update(['notified' => true, 'notified_at' => now()]);

            return;
        }

        $grouped = [
            'failed' => [],
            'missed' => [],
            'partial' => [],
            'stuck' => [],
            'recovered' => [],
            'other' => [],
        ];

        foreach ($alerts as $a) {
            $type = $a['alert_type'] ?? 'other';
            $line = ($a['job_name'] ?? 'unknown') . ($a['root_cause'] ? ' — ' . $a['root_cause'] : '');
            match (true) {
                in_array($type, ['failed', 'validation_failed', 'no_updates', 'timed_out'], true) => $grouped['failed'][] = $line,
                $type === 'cron_missed' => $grouped['missed'][] = $line,
                $type === 'partial_success' => $grouped['partial'][] = $line,
                $type === 'stuck' => $grouped['stuck'][] = $line,
                ($a['status'] ?? '') === 'recovered' => $grouped['recovered'][] = $line,
                default => $grouped['other'][] = $line,
            };
        }

        $body = "Cron Health Warning\n\n";
        foreach (['failed' => 'Failed', 'missed' => 'Missed', 'partial' => 'Partial', 'stuck' => 'Stuck', 'recovered' => 'Recovered', 'other' => 'Other'] as $key => $label) {
            if ($grouped[$key] === []) {
                continue;
            }
            $body .= $label . "\n";
            foreach (array_unique($grouped[$key]) as $line) {
                $body .= '  • ' . $line . "\n";
            }
            $body .= "\n";
        }
        $body .= 'Dashboard: ' . url('/cron-monitor');

        $batch->update([
            'summary' => 'Cron Health Warning (' . count($alerts) . ' issues)',
            'window_ended_at' => now(),
            'payload' => array_merge($batch->payload ?? [], ['grouped' => $grouped, 'body' => $body]),
        ]);

        $channels = config('cron-monitor.notifications.channels', ['taskmanager']);
        foreach ($channels as $channel) {
            match ($channel) {
                'taskmanager' => $taskManager->post([
                    'source' => 'cron-monitor',
                    'command' => 'cron-monitor:grouped-alert',
                    'status' => 'failed',
                    'title' => $batch->summary,
                    'error' => $body,
                    'meta' => ['batch_id' => $batch->id, 'count' => count($alerts)],
                ]),
                'mail' => $this->sendMail($batch->summary, $body),
                'whatsapp' => $this->sendWhatsApp($whatsApp, $body),
                'database' => null,
                default => null,
            };
        }

        // Mark individual alerts notified
        $ids = collect($alerts)->pluck('id')->filter()->all();
        if ($ids) {
            CronMonitorAlert::query()->whereIn('id', $ids)->update([
                'notified' => true,
                'notified_at' => now(),
            ]);
        }

        $batch->update(['notified' => true, 'notified_at' => now()]);
        Cache::forget('cron-monitor:alert-batch:open');
        Cache::forget('cron-monitor:alert-batch:flush:' . $batch->id);
    }

    protected function sendMail(string $subject, string $body): void
    {
        $recipients = config('cron-monitor.notifications.mail.to', []);
        if ($recipients === []) {
            $admin = config('services.admin.email');
            $recipients = $admin ? [$admin] : [];
        }
        if ($recipients === []) {
            return;
        }

        try {
            Mail::raw($body, function ($message) use ($subject, $recipients) {
                $message->to($recipients)->subject('[Cron Monitor] ' . $subject);
            });
        } catch (\Throwable $e) {
            Log::error('[CronMonitor] Grouped mail failed: ' . $e->getMessage());
        }
    }

    protected function sendWhatsApp(WhatsAppService $whatsApp, string $body): void
    {
        foreach (config('cron-monitor.notifications.whatsapp.to', []) as $phone) {
            $clean = WhatsAppService::cleanPhone($phone);
            if ($clean) {
                $whatsApp->sendText($clean, $body);
            }
        }
    }
}
