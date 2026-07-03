<?php

namespace App\Services\Support\Concerns;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Standard exception / HTTP error handling for marketplace API services (Shopify reference pattern).
 */
trait HandlesMarketplaceApiExceptions
{
    /**
     * @return array{success: bool, message: string}
     */
    protected function marketplaceApiFailure(string $operation, string $identifier, string $message, array $context = []): array
    {
        Log::error("❌ {$operation} failed", array_merge([
            'identifier' => $identifier,
            'message' => $message,
        ], $context));

        return ['success' => false, 'message' => $message];
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected function marketplaceApiSuccess(string $operation, string $identifier, string $message = 'OK', array $context = []): array
    {
        Log::info("✅ {$operation} succeeded", array_merge([
            'identifier' => $identifier,
            'message' => $message,
        ], $context));

        return ['success' => true, 'message' => $message];
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected function handleMarketplaceThrowable(string $operation, string $identifier, Throwable $e, array $context = []): array
    {
        Log::error("❌ {$operation} exception", array_merge([
            'identifier' => $identifier,
            'error' => $e->getMessage(),
        ], $context));

        return ['success' => false, 'message' => $e->getMessage()];
    }

    protected function httpErrorMessage(Response $response, string $fallback = 'HTTP request failed'): string
    {
        $status = $response->status();
        $body = trim($response->body());

        if ($status === 401 || $status === 403) {
            return "Authentication failed (HTTP {$status}). Check API credentials.";
        }

        if ($status === 429) {
            return 'Rate limit exceeded (HTTP 429). Retry later.';
        }

        if ($status >= 500) {
            return "Marketplace server error (HTTP {$status}).";
        }

        if ($body !== '') {
            return mb_substr($body, 0, 500);
        }

        return "{$fallback} (HTTP {$status})";
    }
}
