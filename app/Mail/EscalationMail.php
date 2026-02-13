<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EscalationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $juniorName,
        public string $juniorEmail,
        public string $question,
        public int $escalationId,
        public string $domain,
        public string $replyLink
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '5Core AI Support: Team member needs your assistance - ' . $this->domain,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.escalation',
        );
    }
}
