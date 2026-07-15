<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdsLinkSkuField extends Model
{
    protected $table = 'ads_link_sku_fields';

    protected $fillable = [
        'sku',
        'sku_norm',
        'plus_kw',
        'minus_kw',
        'plus_pt',
        'minus_pt',
        'plus_kw_spl',
        'pt_spl',
        'spl_minus_kw',
        'spl_minus_pt',
        'updated_by',
    ];

    protected $casts = [
        'plus_kw' => 'array',
        'minus_kw' => 'array',
        'plus_pt' => 'array',
        'minus_pt' => 'array',
        'plus_kw_spl' => 'array',
        'pt_spl' => 'array',
        'spl_minus_kw' => 'array',
        'spl_minus_pt' => 'array',
    ];

    public static function normalizeSku(string $sku): string
    {
        return strtoupper(trim($sku));
    }

    /**
     * @param  list<string>  $skus
     * @return \Illuminate\Support\Collection<string, self>
     */
    public static function mapBySkus(array $skus)
    {
        $norms = collect($skus)
            ->map(fn ($sku) => self::normalizeSku((string) $sku))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($norms === []) {
            return collect();
        }

        return self::query()
            ->whereIn('sku_norm', $norms)
            ->get()
            ->keyBy('sku_norm');
    }

    /**
     * @return array{plus_kw: list<string>, minus_kw: list<string>, plus_pt: list<string>, minus_pt: list<string>, plus_kw_spl: list<string>, pt_spl: list<string>, spl_minus_kw: list<string>, spl_minus_pt: list<string>}
     */
    public static function emptyPayload(): array
    {
        return [
            'plus_kw' => [],
            'minus_kw' => [],
            'plus_pt' => [],
            'minus_pt' => [],
            'plus_kw_spl' => [],
            'pt_spl' => [],
            'spl_minus_kw' => [],
            'spl_minus_pt' => [],
        ];
    }

    /**
     * @return array{plus_kw: list<string>, minus_kw: list<string>, plus_pt: list<string>, minus_pt: list<string>, plus_kw_spl: list<string>, pt_spl: list<string>, spl_minus_kw: list<string>, spl_minus_pt: list<string>}
     */
    public function toPayload(): array
    {
        return [
            'plus_kw' => $this->normalizeList($this->plus_kw),
            'minus_kw' => $this->normalizeList($this->minus_kw),
            'plus_pt' => $this->normalizeList($this->plus_pt),
            'minus_pt' => $this->normalizeList($this->minus_pt),
            'plus_kw_spl' => $this->normalizeList($this->plus_kw_spl),
            'pt_spl' => $this->normalizeList($this->pt_spl),
            'spl_minus_kw' => $this->normalizeList($this->spl_minus_kw),
            'spl_minus_pt' => $this->normalizeList($this->spl_minus_pt),
        ];
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    public static function normalizeList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                $parts = preg_split('/[\n,]+/', $value);
                $value = is_array($parts) ? $parts : [];
            }
        }

        if (! is_array($value)) {
            return [];
        }

        $seen = [];
        $out = [];
        foreach ($value as $item) {
            $text = trim((string) $item);
            if ($text === '') {
                continue;
            }
            $key = strtoupper($text);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $text;
        }

        return $out;
    }
}
