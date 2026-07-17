<?php

namespace App\Notifications\CronMonitor;

use App\Models\CronMonitorAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CronHealthAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CronMonitorAlert $alert) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('[Cron Monitor] ' . $this->alert->title)
            ->line($this->alert->title)
            ->line($this->alert->message ?? '')
            ->action('Open Dashboard', url('/cron-monitor'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'job_name' => $this->alert->job_name,
            'alert_type' => $this->alert->alert_type,
            'severity' => $this->alert->severity,
            'title' => $this->alert->title,
            'message' => $this->alert->message,
        ];
    }
}
