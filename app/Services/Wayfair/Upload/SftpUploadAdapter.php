<?php

namespace App\Services\Wayfair\Upload;

use Illuminate\Support\Facades\Storage;

class SftpUploadAdapter implements WayfairUploadAdapter
{
    public function upload(string $absolutePath, int $uploadId): WayfairUploadResult
    {
        $host = trim((string) config('wayfair_upload.sftp.host', ''));
        $username = trim((string) config('wayfair_upload.sftp.username', ''));
        if ($host === '' || $username === '') {
            return WayfairUploadResult::failed(
                'SFTP upload is not configured. Set WAYFAIR_SFTP_HOST and WAYFAIR_SFTP_USERNAME, and install league/flysystem-sftp-v3.',
                false
            );
        }

        try {
            $disk = Storage::build([
                'driver' => 'sftp',
                'host' => $host,
                'username' => $username,
                'password' => (string) config('wayfair_upload.sftp.password', ''),
                'port' => (int) config('wayfair_upload.sftp.port', 22),
                'root' => (string) config('wayfair_upload.sftp.root', '/'),
                'timeout' => (int) config('wayfair_upload.upload_timeout', 120),
            ]);
            $remote = basename($absolutePath);
            $disk->put($remote, file_get_contents($absolutePath));
        } catch (\Throwable $e) {
            $retryable = (bool) preg_match('/timeout|timed out|connection|temporarily/i', $e->getMessage());

            return WayfairUploadResult::failed($e->getMessage(), $retryable);
        }

        return WayfairUploadResult::processing(null, 'File stored on the configured SFTP server. Processing status is not available from SFTP.');
    }

    public function checkStatus(?string $reference, array $context = []): WayfairUploadResult
    {
        return WayfairUploadResult::processing($reference, 'SFTP has no Wayfair processing-status check.');
    }
}
