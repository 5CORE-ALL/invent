<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SupplierPortalAsset extends Model
{
    public const CATEGORIES = [
        'brand_assets' => 'Brand Assets',
        'inner_box_designs' => 'Inner Box Designs',
        'inner_box_cover' => 'Inner Box Cover',
        'master_carton_designs' => 'Carton Designs',
        'assembly_designs' => 'Assembly Designs',
        'operations_manual' => 'Operations Manual',
        'dos_and_donts' => "Do's & Don'ts",
    ];

    public const LEGACY_CATEGORY_SLUGS = [
        'logos' => 'brand_assets',
        'packaging' => 'inner_box_designs',
        'marketing' => 'brand_assets',
        'documents' => 'brand_assets',
    ];

    public const CATEGORY_ICONS = [
        'brand_assets' => 'ri-palette-line',
        'inner_box_designs' => 'ri-box-3-line',
        'inner_box_cover' => 'ri-inbox-archive-line',
        'master_carton_designs' => 'ri-stack-line',
        'assembly_designs' => 'ri-tools-line',
        'operations_manual' => 'ri-book-2-line',
        'dos_and_donts' => 'ri-error-warning-line',
    ];

    public const CATEGORY_PREFIXES = [
        'brand_assets' => 'BA',
        'inner_box_designs' => 'IBD',
        'inner_box_cover' => 'IBC',
        'master_carton_designs' => 'MCD',
        'assembly_designs' => 'AD',
        'operations_manual' => 'OM',
        'dos_and_donts' => 'DD',
    ];

    public const CATEGORY_HINTS = [
        'brand_assets' => 'Logos, icons, brand files',
        'inner_box_designs' => 'Inner box artwork and dielines',
        'inner_box_cover' => 'Inner box cover artwork',
        'master_carton_designs' => 'Carton artwork and dielines',
        'assembly_designs' => 'Assembly drawings and build files',
        'operations_manual' => 'User and operations manuals',
        'dos_and_donts' => 'Do and do not guidelines',
    ];

    public static function resolveCategoryKey(string $category): ?string
    {
        $category = strtolower(trim($category));
        if ($category === '') {
            return null;
        }
        if (isset(self::CATEGORIES[$category])) {
            return $category;
        }

        return self::LEGACY_CATEGORY_SLUGS[$category] ?? null;
    }

    public static function prefixFor(string $category): string
    {
        $key = self::resolveCategoryKey($category) ?? strtolower(trim($category));

        return self::CATEGORY_PREFIXES[$key] ?? 'SP';
    }

    public static function nextSortOrder(string $category): int
    {
        $key = self::resolveCategoryKey($category) ?? $category;
        $max = (int) self::query()
            ->where(function ($q) use ($key) {
                $q->where('category', $key);
                foreach (self::LEGACY_CATEGORY_SLUGS as $legacy => $mapped) {
                    if ($mapped === $key) {
                        $q->orWhere('category', $legacy);
                    }
                }
            })
            ->max('sort_order');

        return max(0, $max) + 1;
    }

    public function codeLabel(?int $number = null): string
    {
        $n = $number ?? max(1, (int) $this->sort_order);

        return self::prefixFor((string) $this->category).'-'.str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }

    protected $table = 'supplier_portal_assets';

    protected $fillable = [
        'category',
        'title',
        'sku',
        'parent',
        'file_name',
        'file_path',
        'mime',
        'file_size',
        'sort_order',
    ];

    public function productMeta(): string
    {
        $parts = [];
        $parent = trim((string) ($this->parent ?? ''));
        $sku = trim((string) ($this->sku ?? ''));
        if ($parent !== '') {
            $parts[] = $parent;
        }
        if ($sku !== '') {
            $parts[] = $sku;
        }

        return implode(' · ', $parts);
    }

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
