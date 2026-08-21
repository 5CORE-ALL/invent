<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductRawImage extends Model
{
    public const KIND_RAW = 'raw';

    public const KIND_BATCH_COO = 'batch_coo';

    protected $table = 'product_raw_images';

    protected $fillable = [
        'sku',
        'kind',
        'image_path',
        'original_name',
        'file_size',
        'mime_type',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function isPreviewable(): bool
    {
        $mime = strtolower((string) $this->mime_type);
        if (str_starts_with($mime, 'image/') && ! in_array($mime, [
            'image/x-canon-cr2',
            'image/x-canon-cr3',
            'image/x-nikon-nef',
            'image/x-sony-arw',
            'image/x-adobe-dng',
        ], true)) {
            return true;
        }

        $ext = strtolower(pathinfo((string) $this->image_path, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
    }

    /**
     * @return array{id:int,url:string,name:string,previewable:bool,size:int|null}
     */
    public function toUiArray(): array
    {
        return [
            'id' => (int) $this->id,
            'url' => $this->url,
            'name' => $this->original_name ?: basename((string) $this->image_path),
            'previewable' => $this->isPreviewable(),
            'size' => $this->file_size,
        ];
    }
}
