<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class JuniorNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $juniorName,
        public string $originalQuestion,
        public string $seniorReply,
        public string $dashboardLink
    ) {}

    public function envelope(): Envelope
    {
        $seniorEmail = config('services.5core.senior_email', 'president@5core.com');
        $fromName = config('mail.from.name', '5Core AI Assistant');

        return new Envelope(
            from: new Address($seniorEmail, $fromName),
            replyTo: [new Address($seniorEmail, $fromName)],
            subject: '5Core AI: Senior replied to your question',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.junior-notification',
        );
    }
}
