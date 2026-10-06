<?php

namespace App\Services\Wayfair\Upload;

class WayfairUploadResult
{
    public function __construct(
        public bool $ok,
        public string $state,
        public bool $retryable,
        public ?string $reference = null,
        public ?string $message = null,
        public array $response = [],
        public ?string $screenshot = null,
    ) {}

    public static function success(?string $reference, string $message = 'Upload processed', array $response = []): self
    {
        return new self(true, 'success', false, $reference, $message, $response);
    }

    public static function processing(?string $reference, string $message, array $response = []): self
    {
        return new self(true, 'processing', false, $reference, $message, $response);
    }

    public static function failed(string $message, bool $retryable, array $response = [], ?string $screenshot = null): self
    {
        return new self(false, 'failed', $retryable, null, $message, $response, $screenshot);
    }

    public static function authRequired(string $message, ?string $screenshot = null): self
    {
        return new self(false, 'auth_required', false, null, $message, [], $screenshot);
    }
}
