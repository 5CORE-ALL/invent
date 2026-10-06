<?php

namespace App\Services\Wayfair\Upload;

use App\Services\WayfairApiService;
use Illuminate\Support\Facades\Http;

class ApiUploadAdapter implements WayfairUploadAdapter
{
    public function upload(string $absolutePath, int $uploadId): WayfairUploadResult
    {
        $url = trim((string) config('wayfair_upload.upload_url', ''));
        if ($url === '') {
            return WayfairUploadResult::failed(
                'WAYFAIR_UPLOAD_URL is not set. No Wayfair price-file API endpoint is configured. Use browser mode for Partner Home, or set the URL Wayfair gives you.',
                false
            );
        }

        try {
            $token = app(WayfairApiService::class)->getAccessTokenWithScope(null);
            $response = Http::withToken($token)
                ->connectTimeout((int) config('services.wayfair.connect_timeout', 30))
                ->timeout((int) config('wayfair_upload.upload_timeout', 120))
                ->attach('file', file_get_contents($absolutePath), basename($absolutePath))
                ->post($url);
        } catch (\Throwable $e) {
            return WayfairUploadResult::failed($e->getMessage(), $this->retryableMessage($e->getMessage()));
        }

        $body = $response->json();
        $payload = is_array($body) ? $body : ['body' => mb_substr($response->body(), 0, 2000)];
        if ($response->failed()) {
            return WayfairUploadResult::failed(
                'Wayfair API rejected the file (HTTP '.$response->status().').',
                $response->status() >= 500 || $response->status() === 429,
                $payload
            );
        }

        $reference = (string) ($payload['reference'] ?? $payload['id'] ?? $payload['feedId'] ?? '');

        return WayfairUploadResult::processing(
            $reference !== '' ? $reference : null,
            'File accepted by the configured Wayfair API. Processing is not confirmed yet.',
            $payload
        );
    }

    public function checkStatus(?string $reference, array $context = []): WayfairUploadResult
    {
        $url = trim((string) config('wayfair_upload.status_url', ''));
        if ($url === '') {
            return WayfairUploadResult::processing($reference, 'WAYFAIR_STATUS_URL is not set, so processing cannot be confirmed.');
        }

        try {
            $token = app(WayfairApiService::class)->getAccessTokenWithScope(null);
            $response = Http::withToken($token)
                ->timeout((int) config('wayfair_upload.upload_timeout', 120))
                ->get($url, array_filter(['reference' => $reference]));
        } catch (\Throwable $e) {
            return WayfairUploadResult::failed($e->getMessage(), true);
        }

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : ['body' => mb_substr($response->body(), 0, 2000)];
        $state = strtolower((string) ($payload['status'] ?? $payload['state'] ?? ''));
        if (in_array($state, ['success', 'succeeded', 'complete', 'completed'], true)) {
            return WayfairUploadResult::success($reference, 'Wayfair reported success.', $payload);
        }
        if (in_array($state, ['failed', 'error', 'rejected'], true)) {
            return WayfairUploadResult::failed('Wayfair processing failed.', false, $payload);
        }

        return WayfairUploadResult::processing($reference, 'Wayfair is still processing the file.', $payload);
    }

    private function retryableMessage(string $message): bool
    {
        return (bool) preg_match('/timeout|timed out|connection|temporarily|429|503/i', $message);
    }
}
