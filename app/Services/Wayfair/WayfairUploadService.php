<?php

namespace App\Services\Wayfair;

use App\Services\Wayfair\Upload\ApiUploadAdapter;
use App\Services\Wayfair\Upload\BrowserUploadAdapter;
use App\Services\Wayfair\Upload\MockUploadAdapter;
use App\Services\Wayfair\Upload\SftpUploadAdapter;
use App\Services\Wayfair\Upload\WayfairUploadAdapter;
use App\Services\Wayfair\Upload\WayfairUploadResult;

class WayfairUploadService
{
    public function adapter(): WayfairUploadAdapter
    {
        return match (strtolower((string) config('wayfair_upload.mode', 'browser'))) {
            'api' => app(ApiUploadAdapter::class),
            'sftp' => app(SftpUploadAdapter::class),
            'mock' => app(MockUploadAdapter::class),
            default => app(BrowserUploadAdapter::class),
        };
    }

    public function upload(string $absolutePath, int $uploadId): WayfairUploadResult
    {
        return $this->adapter()->upload($absolutePath, $uploadId);
    }

    public function checkStatus(?string $reference, array $context = []): WayfairUploadResult
    {
        return $this->adapter()->checkStatus($reference, $context);
    }
}
