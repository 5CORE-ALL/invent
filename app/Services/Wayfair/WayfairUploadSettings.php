<?php

namespace App\Services\Wayfair;

class WayfairUploadSettings
{
    public function enabled(): bool
    {
        $path = $this->path();
        if (is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json) && array_key_exists('enabled', $json)) {
                return (bool) $json['enabled'];
            }
        }

        return (bool) config('wayfair_upload.enabled', false);
    }

    public function setEnabled(bool $enabled): void
    {
        $dir = dirname($this->path());
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->path(), json_encode(['enabled' => $enabled], JSON_PRETTY_PRINT));
    }

    private function path(): string
    {
        return storage_path('app/wayfair/settings.json');
    }
}
