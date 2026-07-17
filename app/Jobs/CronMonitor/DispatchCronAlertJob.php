<?php

namespace App\Jobs\CronMonitor;

use App\Models\CronMonitorAlert;
use App\Models\User;
use App\Notifications\CronMonitor\CronHealthAlertNotification;
use App\Services\CronMonitor\TaskManagerStatusReporter;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Delivers cron-monitor alerts through the app's existing channels
 * (Task Manager API, Laravel notifications, mail, WhatsApp) — no webhooks.
 */
class DispatchCronAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $alertId) {}

    public function handle(TaskManagerStatusReporter $taskManager, WhatsAppService $whatsApp): void
    {
        $alert = CronMonitorAlert::with('executionLog')->find($this->alertId);
        if (! $alert || $alert->notified) {
            return;
        }

        $channels = config('cron-monitor.notifications.channels', ['taskmanager', 'database']);

        foreach ($channels as $channel) {
            match ($channel) {
                'taskmanager' => $taskManager->reportAlert($alert),
                'mail' => $this->sendMail($alert),
                'database' => $this->sendLaravelNotification($alert),
                'whatsapp' => $this->sendWhatsApp($alert, $whatsApp),
                default => Log::debug("[CronMonitor] Unknown or disabled channel: {$channel}"),
            };
        }

        $alert->update([
            'notified' => true,
            'notified_at' => now(),
        ]);
    }

    protected function sendMail(CronMonitorAlert $alert): void
    {
        $recipients = config('cron-monitor.notifications.mail.to', []);
        if ($recipients === []) {
            $admin = config('services.admin.email');
            $recipients = $admin ? [$admin] : [];
        }
        if ($recipients === []) {
            return;
        }

        $dashboard = $alert->execution_log_id
            ? url('/cron-monitor/' . $alert->execution_log_id)
            : url('/cron-monitor');

        $body = $alert->title . "\n\n" . ($alert->message ?? '') . "\n\nDashboard: {$dashboard}";

        try {
            Mail::raw($body, function ($message) use ($alert, $recipients) {
                $message->to($recipients)
                    ->subject('[Cron Monitor] ' . $alert->title);
            });
        } catch (\Throwable $e) {
            Log::error('[CronMonitor] Mail failed: ' . $e->getMessage());
        }
    }

    protected function sendLaravelNotification(CronMonitorAlert $alert): void
    {
        $emails = config('cron-monitor.notifications.mail.to', []);
        if ($emails === []) {
            $admin = config('services.admin.email');
            $emails = $admin ? [$admin] : [];
        }

        if ($emails === []) {
            Log::warning('[CronMonitor] No mail recipients configured for database notification (set CRON_MONITOR_MAIL_TO or ADMIN_EMAIL)');

            return;
        }

        $users = User::query()->whereIn('email', $emails)->get();
        if ($users->isEmpty()) {
            Log::warning('[CronMonitor] No matching users for database notification', ['emails' => $emails]);

            return;
        }

        Notification::send($users, new CronHealthAlertNotification($alert));
    }

    protected function sendWhatsApp(CronMonitorAlert $alert, WhatsAppService $whatsApp): void
    {
        $phones = config('cron-monitor.notifications.whatsapp.to', []);
        if ($phones === []) {
            return;
        }

        $text = "Cron Monitor Alert\n\n"
            . $alert->title . "\n\n"
            . ($alert->message ?? '') . "\n\n"
            . 'Open: ' . ($alert->execution_log_id
                ? url('/cron-monitor/' . $alert->execution_log_id)
                : url('/cron-monitor'));

        foreach ($phones as $phone) {
            $clean = WhatsAppService::cleanPhone($phone);
            if (! $clean) {
                continue;
            }
            try {
                $whatsApp->sendText($clean, $text);
            } catch (\Throwable $e) {
                Log::error('[CronMonitor] WhatsApp failed: ' . $e->getMessage(), ['phone' => $clean]);
            }
        }
    }
}
