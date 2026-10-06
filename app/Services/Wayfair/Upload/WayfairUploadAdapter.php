<?php

namespace App\Services\Wayfair\Upload;

interface WayfairUploadAdapter
{
    public function upload(string $absolutePath, int $uploadId): WayfairUploadResult;

    public function checkStatus(?string $reference, array $context = []): WayfairUploadResult;
}
