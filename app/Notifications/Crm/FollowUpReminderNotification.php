<?php

namespace App\Notifications\Crm;

use App\Models\Crm\FollowUp;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FollowUpReminderNotification extends Notification
{
    public function __construct(
        public FollowUp $followUp
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $followUp = $this->followUp;
        $customerName = $followUp->customer?->name ?? 'Customer';

        $mail = (new MailMessage)
            ->subject('Follow-up reminder: '.$followUp->title)
            ->greeting('Hello '.($notifiable->name ?: 'there').',')
            ->line('This is a reminder for your pending follow-up **'.$followUp->title.'** (customer: '.$customerName.').')
            ->line('Reminder time: '.$followUp->reminder_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'));

        if ($followUp->scheduled_at) {
            $mail->line('Scheduled for: '.$followUp->scheduled_at->timezone(config('app.timezone'))->format('Y-m-d H:i'));
        }

        $mail->action('Open follow-up', route('crm.follow-ups.show', $followUp));

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $followUp = $this->followUp;

        return [
            'follow_up_id' => $followUp->id,
            'title' => $followUp->title,
            'customer_id' => $followUp->customer_id,
            'customer_name' => $followUp->customer?->name,
            'reminder_at' => $followUp->reminder_at?->toIso8601String(),
            'scheduled_at' => $followUp->scheduled_at?->toIso8601String(),
            'url' => route('crm.follow-ups.show', $followUp),
        ];
    }
}
