<?php

namespace App\Services\ResourcesMaster;

use App\Models\ResourceMaster;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class ResourceMasterStorageService
{
    /** @var array<string, string> extension => file_type */
    protected array $extensionMap = [
        'pdf' => 'pdf',
        'doc' => 'doc',
        'docx' => 'doc',
        'xls' => 'spreadsheet',
        'xlsx' => 'spreadsheet',
        'csv' => 'spreadsheet',
        'ppt' => 'presentation',
        'pptx' => 'presentation',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'webp' => 'image',
        'mp4' => 'video',
        'zip' => 'archive',
    ];

    /** @var array<string, true> */
    protected array $allowedMime = [
        'application/pdf' => true,
        'application/msword' => true,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => true,
        'application/vnd.ms-excel' => true,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => true,
        'text/csv' => true,
        'text/plain' => true,
        'application/csv' => true,
        'application/vnd.ms-powerpoint' => true,
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => true,
        'image/jpeg' => true,
        'image/png' => true,
        'image/webp' => true,
        'video/mp4' => true,
        'application/zip' => true,
    ];

    public function disk(): string
    {
        return config('resources_master.disk', 'resources_master');
    }

    public function maxBytes(): int
    {
        return (int) config('resources_master.max_upload_kb', 102400) * 1024;
    }

    public function validateUploadedFile(UploadedFile $file): void
    {
        if ($file->getSize() > $this->maxBytes()) {
            throw new FileException('File exceeds maximum allowed size.');
        }

        $ext = strtolower($file->getClientOriginalExtension());
        $blocked = config('resources_master.blocked_extensions', []);
        if ($ext !== '' && in_array($ext, $blocked, true)) {
            throw new FileException('This file extension is not allowed.');
        }

        $mime = $file->getMimeType() ?? '';
        if ($mime !== '' && ! isset($this->allowedMime[$mime])) {
            throw new FileException('This file type is not allowed.');
        }

        if ($ext === '' || ! isset($this->extensionMap[$ext])) {
            throw new FileException('Unsupported file extension.');
        }

        if (str_contains($file->getClientOriginalName(), '..')) {
            throw new FileException('Invalid file name.');
        }
    }

    public function detectFileType(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return $this->extensionMap[$ext] ?? 'document';
    }

    /**
     * @return array{path: string, file_type: string, mime: string|null, size: int, original: string}
     */
    public function store(UploadedFile $file, string $category): array
    {
        $this->validateUploadedFile($file);

        $safe = ResourceMaster::sanitizeFilename($file->getClientOriginalName());
        $dir = $category.'/'.now()->format('Y/m');
        $name = Str::uuid().'_'.$safe;
        $path = $file->storeAs($dir, $name, $this->disk());

        return [
            'path' => $path,
            'file_type' => $this->detectFileType($file),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'original' => $safe,
        ];
    }

    public function storeThumbnail(UploadedFile $file, string $category): ?string
    {
        try {
            $this->validateUploadedFile($file);
        } catch (FileException) {
            return null;
        }
        if ($this->detectFileType($file) !== 'image') {
            return null;
        }
        $dir = $category.'/thumbnails/'.now()->format('Y/m');
        $name = Str::uuid().'_'.ResourceMaster::sanitizeFilename($file->getClientOriginalName());

        return $file->storeAs($dir, $name, $this->disk());
    }

    public function deleteIfExists(?string $path): void
    {
        if ($path && Storage::disk($this->disk())->exists($path)) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    public function isAllowedExternalUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! $host) {
            return false;
        }
        $lower = strtolower($host);

        return str_contains($lower, 'youtube.com')
            || str_contains($lower, 'youtu.be')
            || str_contains($lower, 'drive.google.com')
            || str_contains($lower, 'docs.google.com');
    }
}
