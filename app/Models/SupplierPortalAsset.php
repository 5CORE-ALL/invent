<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SupplierPortalAsset extends Model
{
    public const CATEGORIES = [
        'logos' => 'Brand Assets / Logos',
        'packaging' => 'Packaging Designs',
        'marketing' => 'Marketing Materials',
        'documents' => 'Guidelines & Documents',
    ];

    protected $table = 'supplier_portal_assets';

    protected $fillable = [
        'category',
        'title',
        'file_name',
        'file_path',
        'mime',
        'file_size',
        'sort_order',
    ];

    public function publicUrl(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }

    public function isImage(): bool
    {
        $mime = strtolower((string) $this->mime);

        return str_starts_with($mime, 'image/') && ! str_contains($mime, 'svg');
    }

    public function extensionLabel(): string
    {
        $ext = strtoupper(pathinfo((string) $this->file_name, PATHINFO_EXTENSION));

        return $ext !== '' ? $ext : 'FILE';
    }

    public function sizeLabel(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '—';
        }
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
