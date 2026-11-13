<?php

namespace App\Services\Meetings;

/**
 * Anything that goes wrong talking to Google. getMessage() is always safe to show to the user
 * (it never contains tokens or raw API payloads); technical detail goes to the log.
 */
class GoogleMeetException extends \RuntimeException
{
    /** Machine readable reason: not_configured | auth | scope | api_disabled | quota | not_found | conference_failed | network | api */
    public string $reason;

    public bool $retryable;

    public function __construct(string $message, string $reason = 'api', bool $retryable = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->reason = $reason;
        $this->retryable = $retryable;
    }

    public static function notConfigured(string $message): self
    {
        return new self($message, 'not_configured');
    }
}
