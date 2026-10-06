<?php

namespace App\Services\Wayfair\Upload;

use App\Services\Wayfair\WayfairBrowserUploadService;

class BrowserUploadAdapter implements WayfairUploadAdapter
{
    public function __construct(private WayfairBrowserUploadService $browser) {}

    public function upload(string $absolutePath, int $uploadId): WayfairUploadResult
    {
        return $this->browser->upload($absolutePath, $uploadId);
    }

    public function checkStatus(?string $reference, array $context = []): WayfairUploadResult
    {
        return $this->browser->checkStatus($reference);
    }
}
