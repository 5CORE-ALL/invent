<?php

namespace App\Services\Wayfair;

use Illuminate\Support\Facades\Log;

class WayfairUploadLogger
{
    public function info(string $message, array $context = []): void
    {
        Log::channel('wayfair-upload')->info($message, $this->scrub($context));
    }

    public function error(string $message, array $context = []): void
    {
        Log::channel('wayfair-upload')->error($message, $this->scrub($context));
    }

    public function scrub(array $context): array
    {
        $blocked = [
            'password', 'wayfair_password', 'client_secret', 'access_token',
            'token', 'cookie', 'cookies', 'authorization', 'secret',
        ];
        $clean = [];
        foreach ($context as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                continue;
            }
            if (is_string($value)) {
                $clean[$key] = self::redact($value);
            } elseif (is_array($value)) {
                $clean[$key] = $this->scrub($value);
            } else {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    public static function redact(string $message): string
    {
        $patterns = [
            '/(password|passwd|client_secret|access_token|authorization|cookie)\s*[=:]\s*\S+/i',
            '/Bearer\s+\S+/i',
        ];
        $clean = $message;
        foreach ($patterns as $pattern) {
            $clean = (string) preg_replace($pattern, '$1=[redacted]', $clean);
        }

        return $clean;
    }
}
