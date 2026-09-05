<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ProductMaster extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_master';

    protected $fillable = [
        'parent',
        'is_spare_part',
        'min_stock_level',
        'reorder_level',
        'max_stock_level',
        'lead_time_days',
        'parent_id',
        'sku',
        'barcode',
        'group_id',
        'category_id',
        'mfr_category_ids',
        'reverb_make',
        'reverb_model',
        'reverb_finish',
        'reverb_year',
        'reverb_condition',
        'reverb_shipping_profile_id',
        'group',
        'category',
        'Values',
        'remark',
        'sales',
        'views',
        'deleted_by',
        'title150',
        'title100',
        'title80',
        'title75',
        'title60',
        'amazon_last_sync',
        'amazon_sync_status',
        'amazon_sync_error',
        'bullet1',
        'bullet2',
        'bullet3',
        'bullet4',
        'bullet5',
        'product_description',
        'description_1500',
        'description_1000',
        'description_800',
        'description_600',
        'description_html',
        'description_v2_bullets',
        'description_v2_description',
        'description_v2_images',
        'description_v2_features',
        'description_v2_specifications',
        'description_v2_package',
        'description_v2_brand',
        'feature1',
        'feature2',
        'feature3',
        'feature4',
        'main_image',
        'main_image_brand',
        'image1',
        'image2',
        'image3',
        'image4',
        'image5',
        'image6',
        'image7',
        'image8',
        'image9',
        'image10',
        'image11',
        'image12',
        'image13',
        'image14',
        'image15',
        'image16',
        'image17',
        'image18',
        'image19',
        'image20',
        'image_main_by_marketplace_json',
        'video_product_overview',
        'video_product_overview_status',
        'video_unboxing',
        'video_unboxing_status',
        'video_how_to',
        'video_how_to_status',
        'video_setup',
        'video_setup_status',
        'video_troubleshooting',
        'video_troubleshooting_status',
        'video_brand_story',
        'video_brand_story_status',
        'video_product_benefits',
        'video_product_benefits_status',
    ];

    public function setTemuShipAttribute($value)
    {
        $values = is_array($this->Values)
            ? $this->Values
            : (is_string($this->Values) ? (json_decode($this->Values, true) ?: []) : []);
        $values['temu_ship'] = $value;
        $this->attributes['Values'] = json_encode($values);
    }

    public function getTemuShipAttribute()
    {
        $values = is_array($this->Values)
            ? $this->Values
            : (is_string($this->Values) ? (json_decode($this->Values, true) ?: []) : []);

        return $values['temu_ship'] ?? null;
    }

    /**
     * Product Master STATUS "Coming" is stored as upcoming (aliases accepted).
     */
    public static function isComingStatus(mixed $statusOrValues): bool
    {
        $status = $statusOrValues;
        if (is_object($statusOrValues)) {
            $raw = $statusOrValues->Values ?? null;
            if (is_string($raw)) {
                $raw = json_decode($raw, true) ?: [];
            }
            $status = is_array($raw) ? ($raw['status'] ?? '') : '';
        } elseif (is_array($statusOrValues)) {
            $status = $statusOrValues['status'] ?? '';
        }

        $norm = strtolower(trim((string) $status));

        return in_array($norm, ['upcoming', 'coming', 'comming'], true);
    }

    public function isComing(): bool
    {
        return self::isComingStatus($this->Values ?? []);
    }

    public static function statusValueFromValues(mixed $values): string
    {
        if (is_object($values)) {
            $values = $values->Values ?? null;
        }
        if (is_string($values)) {
            $values = json_decode($values, true) ?: [];
        }
        if (! is_array($values)) {
            return '';
        }

        return trim((string) ($values['status'] ?? ''));
    }

    public static function statusDisplayLabel(string $status): string
    {
        $s = trim($status);
        if ($s === '') {
            return '';
        }

        $lower = strtolower($s);
        $upper = strtoupper($s);

        if ($lower === 'active') {
            return 'Active';
        }
        if ($lower === 'inactive') {
            return 'Inactive';
        }
        if (in_array($lower, ['upcoming', 'coming', 'comming'], true)) {
            return 'Coming';
        }
        if ($upper === 'DC') {
            return 'DC';
        }
        if ($upper === '2BDC') {
            return '2BDC';
        }

        return $s;
    }

    public function statusValue(): string
    {
        return self::statusValueFromValues($this->Values ?? []);
    }

    public function statusLabel(): string
    {
        return self::statusDisplayLabel($this->statusValue());
    }

    /**
     * Auto-recalculate LP, CBM and FRGHT in the Values JSON whenever the model is saved.
     * Source of truth formula:
     *   CBM   = (L * 2.54) * (W * 2.54) * (H * 2.54) / 1,000,000   (L/W/H in inches -> m^3)
     *   FRGHT = CBM * 200
     *   LP    = CP + FRGHT
     *
     * SKUs listed in CustomLpMappingService keep their custom LP so the existing
     * manual overrides continue to work end-to-end.
     */
    protected static function booted()
    {
        static::saving(function (self $product) {
            $raw = $product->Values;
            if ($raw === null) {
                return;
            }
            $values = is_array($raw) ? $raw : json_decode($raw, true);
            if (! is_array($values)) {
                return;
            }

            $sku = (string) ($product->sku ?? '');
            $recalculated = self::recalcDerivedValues($values, $sku);

            if ($recalculated !== $values) {
                $product->Values = $recalculated;
            }
        });
    }

    /**
     * Recalculate LP, CBM and FRGHT for a given Values array.
     * Uses own L/W/H when present; combo SKUs fall back to component package dims.
     * Parent SKUs (containing "PARENT") are returned unchanged.
     */
    public static function recalcDerivedValues(array $values, string $sku = ''): array
    {
        if ($sku !== '' && stripos($sku, 'PARENT') !== false) {
            return $values;
        }

        $customMapping = \App\Services\CustomLpMappingService::getCustomLpMapping();
        $hasCustomLp = $sku !== '' && array_key_exists($sku, $customMapping);

        $cp = self::numericFromValues($values, 'cp');
        $l = self::numericFromValues($values, 'l');
        $w = self::numericFromValues($values, 'w');
        $h = self::numericFromValues($values, 'h');

        $cbm = null;
        $frght = null;
        if ($l > 0 && $w > 0 && $h > 0) {
            $cbm = ($l * 2.54) * ($w * 2.54) * ($h * 2.54) / 1000000;
            $frght = $cbm * 200;
        } elseif ($sku !== '' && self::isComboSku($sku, (string) ($values['Parent'] ?? $values['parent'] ?? ''))) {
            try {
                $combo = self::lookupComboFreightFromDatabase($sku, (string) ($values['Parent'] ?? $values['parent'] ?? ''));
            } catch (\Throwable $e) {
                $combo = null;
            }
            if ($combo !== null) {
                $cbm = $combo['cbm'];
                $frght = $combo['frght'];
            }
        }

        if ($cbm !== null) {
            $values['cbm'] = round($cbm, 4);
        }
        if ($frght !== null) {
            $values['frght'] = round($frght, 2);
        }

        if ($hasCustomLp) {
            $values['lp'] = round((float) $customMapping[$sku], 2);
        } elseif ($cp > 0 || ($cbm !== null && $cbm > 0)) {
            $values['lp'] = round($cp + ($frght ?? 0.0), 2);
        }

        return $values;
    }

    /**
     * Safely read a numeric value from a Values JSON array.
     * Accepts strings with stray spaces/commas and returns 0.0 if not parseable.
     */
    private static function numericFromValues(array $values, string $key): float
    {
        if (! array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
            return 0.0;
        }
        $raw = $values[$key];
        if (is_string($raw)) {
            $raw = trim(str_replace(',', '', $raw));
        }

        return is_numeric($raw) ? (float) $raw : 0.0;
    }

    public static function skuKey(string $sku): string
    {
        $sku = str_replace("\u{00a0}", ' ', $sku);
        $sku = preg_replace('/\s+/', ' ', trim($sku)) ?? '';

        return strtoupper($sku);
    }

    public static function skuCompact(string $sku): string
    {
        return str_replace(' ', '', self::skuKey($sku));
    }

    public static function isComboSku(string $sku, string $parent = ''): bool
    {
        return str_contains($sku, '+')
            || stripos($sku, 'combo') !== false
            || stripos($parent, 'combo') !== false;
    }

    public static function parseComboComponentSkus(string $sku): array
    {
        if (trim($sku) === '') {
            return [];
        }

        $parts = preg_split('/\+/', $sku) ?: [];

        return array_values(array_filter(array_map(static function ($part) {
            return trim(str_replace("\u{00a0}", ' ', (string) $part));
        }, $parts), static fn ($part) => $part !== ''));
    }

    public static function cbmFromRowDims(array $row): ?float
    {
        $l = self::numericFromValues($row, 'l');
        $w = self::numericFromValues($row, 'w');
        $h = self::numericFromValues($row, 'h');
        if ($l <= 0 || $w <= 0 || $h <= 0) {
            return null;
        }

        return ($l * 2.54) * ($w * 2.54) * ($h * 2.54) / 1000000;
    }

    /**
     * Sum CBM/FRGHT from combo component rows when the combo itself has no L/W/H.
     *
     * @param  callable(string): (?array)  $findComponent
     */
    public static function applyComboFreightToRow(array $row, callable $findComponent): array
    {
        $sku = (string) ($row['SKU'] ?? $row['sku'] ?? '');
        $parent = (string) ($row['Parent'] ?? $row['parent'] ?? '');
        if ($sku === '' || stripos($sku, 'PARENT') !== false) {
            return $row;
        }
        if (self::cbmFromRowDims($row) !== null) {
            return $row;
        }
        if (! self::isComboSku($sku, $parent)) {
            return $row;
        }

        $parts = self::parseComboComponentSkus($sku);
        if (count($parts) < 2) {
            return $row;
        }

        $totalCbm = 0.0;
        $found = 0;
        foreach ($parts as $part) {
            $component = $findComponent($part);
            if (! is_array($component)) {
                continue;
            }
            $cbm = self::cbmFromRowDims($component);
            if ($cbm === null) {
                continue;
            }
            $totalCbm += $cbm;
            $found++;
        }

        if ($found < 2 || $totalCbm <= 0) {
            return $row;
        }

        $frght = $totalCbm * 200;
        $row['cbm'] = round($totalCbm, 4);
        $row['frght'] = round($frght, 2);

        $customMapping = \App\Services\CustomLpMappingService::getCustomLpMapping();
        if (array_key_exists($sku, $customMapping)) {
            $row['lp'] = round((float) $customMapping[$sku], 2);
        } else {
            $cp = self::numericFromValues($row, 'cp');
            $storedLp = self::numericFromValues($row, 'lp');
            if ($cp > 0 && ($storedLp <= 0 || abs($storedLp - $cp) < 0.009)) {
                $row['lp'] = round($cp + $frght, 2);
            }
        }

        return $row;
    }

    public static function applyComboFreightToRows(array $rows): array
    {
        $byCompact = [];
        foreach ($rows as $i => $row) {
            $compact = self::skuCompact((string) ($row['SKU'] ?? $row['sku'] ?? ''));
            if ($compact !== '') {
                $byCompact[$compact] = $i;
            }
        }

        foreach ($rows as $i => $row) {
            $rows[$i] = self::applyComboFreightToRow($row, static function (string $componentSku) use ($rows, $byCompact) {
                $idx = $byCompact[self::skuCompact($componentSku)] ?? null;

                return $idx === null ? null : $rows[$idx];
            });
        }

        return $rows;
    }

    public static function findByCompactSku(string $sku): ?self
    {
        $compact = self::skuCompact($sku);
        if ($compact === '') {
            return null;
        }

        $direct = self::query()->where('sku', $sku)->first();
        if ($direct) {
            return $direct;
        }

        $candidates = self::query()
            ->whereRaw("REPLACE(UPPER(sku), ' ', '') = ?", [$compact])
            ->get();

        return $candidates->first(static fn (self $product) => self::skuCompact((string) $product->sku) === $compact);
    }

    /**
     * @return array{cbm: float, frght: float}|null
     */
    public static function lookupComboFreightFromDatabase(string $sku, string $parent = ''): ?array
    {
        if (! self::isComboSku($sku, $parent)) {
            return null;
        }

        $parts = self::parseComboComponentSkus($sku);
        if (count($parts) < 2) {
            return null;
        }

        $dummy = ['SKU' => $sku, 'Parent' => $parent];
        $applied = self::applyComboFreightToRow($dummy, static function (string $componentSku) {
            $product = self::findByCompactSku($componentSku);
            if (! $product) {
                return null;
            }
            $values = is_array($product->Values) ? $product->Values : [];

            return array_merge(['SKU' => $product->sku], $values);
        });

        if (! isset($applied['cbm'], $applied['frght'])) {
            return null;
        }

        return [
            'cbm' => (float) $applied['cbm'],
            'frght' => (float) $applied['frght'],
        ];
    }

    protected $casts = [
        'description_v2_images' => 'array',
        'description_v2_features' => 'array',
        'description_v2_specifications' => 'array',
        'Values' => 'array',
        'sales' => 'array',
        'views' => 'array',
        'mfr_category_ids' => 'array',
        'is_spare_part' => 'boolean',
        'min_stock_level' => 'integer',
        'reorder_level' => 'integer',
        'max_stock_level' => 'integer',
        'lead_time_days' => 'integer',
    ];

    protected $guarded = ['group_id'];

    /**
     * Get the group that the product belongs to
     */
    public function productGroup()
    {
        return $this->belongsTo(ProductGroup::class, 'group_id');
    }

    /**
     * Get the category that the product belongs to
     */
    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function parentPart()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function childParts()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function sparePartDetail()
    {
        return $this->hasOne(SparePartDetail::class, 'product_master_id');
    }

    public function scopeSpareParts($query)
    {
        return $query->where('is_spare_part', true);
    }

    public function stockMovements()
    {
        return $this->hasMany(\App\Models\Wms\StockMovement::class, 'product_id');
    }

    /**
     * Resolve a product from WMS/camera/USB scan input (SKU, internal barcode, UPC/GTIN in columns or Values JSON).
     * Also resolves via shopify_skus.sku when the scan matches Shopify but PM sku differs only by spaces/case/NBSP.
     */
    public static function findByWmsScanCode(string $code): ?self
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $collapsed = preg_replace('/\s+/u', ' ', $code) ?? $code;
        $candidates = array_values(array_unique(array_merge(
            self::wmsScanCodeCandidates($code),
            self::wmsScanCodeCandidates($collapsed),
            [
                $code,
                $collapsed,
                str_replace(' ', "\xc2\xa0", $collapsed),
                str_replace("\xc2\xa0", ' ', $code),
            ],
        )));

        $normLower = self::normalizeScanKey($code);
        $driver = Schema::getConnection()->getDriverName();

        $product = static::query()
            ->where(function ($q) use ($candidates, $normLower, $driver) {
                $q->whereIn('barcode', $candidates)
                    ->orWhereIn('sku', $candidates);

                if (Schema::hasColumn((new self)->getTable(), 'upc')) {
                    $q->orWhereIn('upc', $candidates);
                }

                if ($driver === 'mysql') {
                    $q->orWhereRaw('LOWER(TRIM(REPLACE(sku, UNHEX("C2A0"), " "))) = ?', [$normLower]);
                    $q->orWhereRaw('(barcode IS NOT NULL AND LOWER(TRIM(barcode)) = ?)', [$normLower]);
                    if (Schema::hasColumn((new self)->getTable(), 'upc')) {
                        $q->orWhereRaw('(upc IS NOT NULL AND LOWER(TRIM(CAST(upc AS CHAR))) = ?)', [$normLower]);
                    }
                } else {
                    $q->orWhereRaw('LOWER(TRIM(sku)) = ?', [$normLower]);
                    $q->orWhereRaw('(barcode IS NOT NULL AND LOWER(TRIM(barcode)) = ?)', [$normLower]);
                }

                if (Schema::hasColumn((new self)->getTable(), 'Values')) {
                    $jsonKeys = ['upc', 'gtin', 'ean', 'barcode'];
                    $q->orWhere(function ($jsonQ) use ($candidates, $jsonKeys) {
                        foreach ($candidates as $c) {
                            if ($c === '') {
                                continue;
                            }
                            foreach ($jsonKeys as $key) {
                                $jsonQ->orWhere('Values->'.$key, $c);
                                if (preg_match('/^\d{1,15}$/', (string) $c) === 1) {
                                    $jsonQ->orWhere('Values->'.$key, (int) $c);
                                }
                            }
                        }
                    });
                }
            })
            ->first();

        if ($product) {
            return $product;
        }

        return self::findViaShopifySkuScan($candidates, $normLower, $driver);
    }

    private static function normalizeScanKey(string $code): string
    {
        $s = str_replace("\xc2\xa0", ' ', trim($code));
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return Str::lower($s);
    }

    /**
     * @param  list<string>  $candidates
     */
    private static function findViaShopifySkuScan(array $candidates, string $normLower, string $driver): ?self
    {
        $shopify = ShopifySku::query()->whereIn('sku', $candidates)->first();
        if (! $shopify && $driver === 'mysql') {
            $shopify = ShopifySku::query()
                ->whereRaw('LOWER(TRIM(REPLACE(sku, UNHEX("C2A0"), " "))) = ?', [$normLower])
                ->first();
        }
        if (! $shopify) {
            $shopify = ShopifySku::query()->whereRaw('LOWER(TRIM(sku)) = ?', [$normLower])->first();
        }

        if (! $shopify) {
            return null;
        }

        $sku = (string) $shopify->sku;
        $alt = str_replace("\xc2\xa0", ' ', $sku);
        $altNbsp = str_replace(' ', "\xc2\xa0", $alt);

        return static::query()
            ->where(function ($q) use ($sku, $alt, $altNbsp, $driver) {
                $q->whereIn('sku', array_values(array_unique(array_filter([$sku, $alt, $altNbsp]))));
                if ($driver === 'mysql') {
                    $q->orWhereRaw('LOWER(TRIM(REPLACE(sku, UNHEX("C2A0"), " "))) = ?', [self::normalizeScanKey($alt)]);
                } else {
                    $q->orWhereRaw('LOWER(TRIM(sku)) = ?', [self::normalizeScanKey($alt)]);
                }
            })
            ->first();
    }

    /**
     * @return list<string>
     */
    private static function wmsScanCodeCandidates(string $code): array
    {
        $candidates = [$code];

        if (preg_match('/^\d+$/', $code) === 1) {
            $trimmed = ltrim($code, '0');
            if ($trimmed !== '' && $trimmed !== $code) {
                $candidates[] = $trimmed;
            }

            if (strlen($code) === 12) {
                $candidates[] = '0'.$code;
            }
            if ($trimmed !== '' && strlen($trimmed) === 12) {
                $candidates[] = '0'.$trimmed;
            }
        }

        return array_values(array_unique($candidates));
    }
}

