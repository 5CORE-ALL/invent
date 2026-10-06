<?php

namespace App\Services\Wayfair;

use App\Services\Wayfair\Upload\WayfairUploadResult;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class WayfairBrowserUploadService
{
    public function upload(string $absolutePath, int $uploadId): WayfairUploadResult
    {
        $uploadUrl = trim((string) config('wayfair_upload.upload_url', ''));
        if ($uploadUrl === '') {
            return WayfairUploadResult::failed(
                'WAYFAIR_UPLOAD_URL is empty. Partner Home file upload needs the exact Wayfair upload page URL. It is not stored in this project.',
                false
            );
        }

        $username = (string) config('wayfair_upload.username', '');
        $password = (string) config('wayfair_upload.password', '');
        if ($username === '' || $password === '') {
            return WayfairUploadResult::authRequired('WAYFAIR_USERNAME and WAYFAIR_PASSWORD are required for Partner Home upload.');
        }

        $script = (string) config('wayfair_upload.browser_script');
        if (! is_file($script)) {
            return WayfairUploadResult::failed('Playwright upload script is missing at scripts/wayfair/upload-price-file.js.', false);
        }

        $errorDir = storage_path('app/'.trim((string) config('wayfair_upload.error_directory', 'wayfair/errors'), '/'));
        File::ensureDirectoryExists($errorDir);
        $payloadPath = storage_path('app/wayfair/tmp/upload-'.$uploadId.'-'.bin2hex(random_bytes(4)).'.json');
        File::ensureDirectoryExists(dirname($payloadPath));

        $payload = [
            'portalUrl' => (string) config('wayfair_upload.portal_url'),
            'uploadUrl' => $uploadUrl,
            'username' => $username,
            'password' => $password,
            'filePath' => $absolutePath,
            'screenshotDir' => $errorDir,
            'timeoutMs' => max(10, (int) config('wayfair_upload.upload_timeout', 120)) * 1000,
            'selectors' => config('wayfair_upload.selectors', []),
            'confirmationText' => (string) config('wayfair_upload.confirmation_text', ''),
            'uploadId' => $uploadId,
        ];
        file_put_contents($payloadPath, json_encode($payload));
        chmod($payloadPath, 0600);

        try {
            $process = new Process([
                (string) config('wayfair_upload.node_binary', 'node'),
                $script,
                $payloadPath,
            ]);
            $process->setTimeout((int) config('wayfair_upload.upload_timeout', 120) + 30);
            $process->run();
            $output = trim($process->getOutput()."\n".$process->getErrorOutput());
        } catch (\Throwable $e) {
            @unlink($payloadPath);

            return WayfairUploadResult::failed($e->getMessage(), $this->retryable($e->getMessage()));
        }

        @unlink($payloadPath);

        $decoded = $this->lastJson($output);
        if ($decoded === null) {
            return WayfairUploadResult::failed(
                'Browser worker did not return a result. '.mb_substr($output, 0, 500),
                $this->retryable($output)
            );
        }

        $state = (string) ($decoded['state'] ?? 'failed');
        $message = (string) ($decoded['message'] ?? 'Browser upload failed');
        $reference = isset($decoded['reference']) ? (string) $decoded['reference'] : null;
        $screenshot = isset($decoded['screenshot']) ? (string) $decoded['screenshot'] : null;

        if ($state === 'auth_required') {
            return WayfairUploadResult::authRequired($message, $screenshot);
        }
        if ($state === 'success') {
            return WayfairUploadResult::success($reference, $message, $decoded);
        }
        if ($state === 'processing') {
            return WayfairUploadResult::processing($reference, $message, $decoded);
        }

        return WayfairUploadResult::failed($message, (bool) ($decoded['retryable'] ?? $this->retryable($message)), $decoded, $screenshot);
    }

    public function checkStatus(?string $reference): WayfairUploadResult
    {
        $statusUrl = trim((string) config('wayfair_upload.status_url', ''));
        if ($statusUrl === '') {
            if ((bool) config('wayfair_upload.treat_acceptance_as_success', false) && $reference) {
                return WayfairUploadResult::success($reference, 'Acceptance treated as success by configuration.');
            }

            return WayfairUploadResult::processing(
                $reference,
                'Wayfair processing status page is not configured (WAYFAIR_STATUS_URL). Upload confirmation is not the same as processed prices.'
            );
        }

        return WayfairUploadResult::processing($reference, 'Browser status checks use WAYFAIR_STATUS_URL only when an adapter that can read it is selected. No status page result was captured.');
    }

    private function lastJson(string $output): ?array
    {
        $lines = preg_split('/\R/', $output) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim($lines[$i]);
            if ($line === '' || $line[0] !== '{') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function retryable(string $message): bool
    {
        return (bool) preg_match('/timeout|timed out|crashed|ECONN|net::|browser closed|temporarily/i', $message);
    }
}
