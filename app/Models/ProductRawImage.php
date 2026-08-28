<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductRawImage extends Model
{
    public const KIND_RAW = 'raw';

    public const KIND_RAW_AI = 'raw_ai';

    public const KIND_BATCH_COO = 'batch_coo';

    public const KIND_BATCH_COO_AI = 'batch_coo_ai';

    public const KIND_HERO_2 = 'hero_2';

    public const KIND_HERO_2_AI = 'hero_2_ai';

    public const KIND_PKG = 'pkg';

    public const KIND_PKG_AI = 'pkg_ai';

    public static function aiKindFor(string $kind): string
    {
        return match ($kind) {
            self::KIND_BATCH_COO, self::KIND_BATCH_COO_AI => self::KIND_BATCH_COO_AI,
            self::KIND_HERO_2, self::KIND_HERO_2_AI => self::KIND_HERO_2_AI,
            self::KIND_PKG, self::KIND_PKG_AI => self::KIND_PKG_AI,
            default => self::KIND_RAW_AI,
        };
    }

    public static function pageKindFor(string $kind): string
    {
        return match ($kind) {
            self::KIND_BATCH_COO, self::KIND_BATCH_COO_AI => self::KIND_BATCH_COO,
            self::KIND_HERO_2, self::KIND_HERO_2_AI => self::KIND_HERO_2,
            self::KIND_PKG, self::KIND_PKG_AI => self::KIND_PKG,
            default => self::KIND_RAW,
        };
    }

    public static function isAiKind(string $kind): bool
    {
        return in_array($kind, [self::KIND_RAW_AI, self::KIND_BATCH_COO_AI, self::KIND_HERO_2_AI, self::KIND_PKG_AI], true);
    }

    public function isAiGenerated(): bool
    {
        if (self::isAiKind((string) $this->kind)) {
            return true;
        }

        $hay = strtolower((string) $this->image_path.' '.(string) $this->original_name);

        return str_contains($hay, 'ai_raw');
    }

    public function isBatchKind(): bool
    {
        return in_array((string) $this->kind, [self::KIND_BATCH_COO, self::KIND_BATCH_COO_AI], true);
    }

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
        return '/storage/'.ltrim((string) $this->image_path, '/');
    }

    public function thumbUrl(): ?string
    {
        $path = $this->thumbPath();
        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return '/storage/'.ltrim($path, '/');
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

    public function thumbPath(): string
    {
        return 'image-cache/thumbs/'.$this->id.'.jpg';
    }

    /**
     * @return array{id:int,url:string,thumb_url:string,name:string,previewable:bool,size:int|null}
     */
    public function toUiArray(): array
    {
        $url = $this->url;

        return [
            'id' => (int) $this->id,
            'url' => $url,
            'thumb_url' => $this->thumbUrl() ?: $url,
            'name' => $this->original_name ?: basename((string) $this->image_path),
            'previewable' => $this->isPreviewable(),
            'size' => $this->file_size,
        ];
    }
}
