<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingErrorsFixCalculatedData extends Model
{
    protected $table = 'pricing_errors_fix_calculated_data';

    protected $fillable = [
        'sku', 'marketplace', 'pull_key', 'channel_label', 'parent', 'image_path',
        'inv', 'ov_l30', 'dil', 'price', 'groi', 'nroi', 'gpft', 'npft',
        'sprice', 'sroi', 'sgpft', 'snroi', 'snpft', 'success',
        'lp', 'ship', 'margin', 'ads_pct', 'calculated_at',
    ];

    protected $casts = [
        'inv' => 'float',
        'ov_l30' => 'float',
        'dil' => 'float',
        'price' => 'float',
        'groi' => 'float',
        'nroi' => 'float',
        'gpft' => 'float',
        'npft' => 'float',
        'sprice' => 'float',
        'sroi' => 'float',
        'sgpft' => 'float',
        'snroi' => 'float',
        'snpft' => 'float',
        'lp' => 'float',
        'ship' => 'float',
        'margin' => 'float',
        'ads_pct' => 'float',
        'calculated_at' => 'datetime',
    ];

    public static function lastCalculatedAt(): ?string
    {
        $v = static::query()->max('calculated_at');

        return $v ? (string) $v : null;
    }

    public static function hasData(): bool
    {
        return static::query()->exists();
    }

    /**
     * Tabulator row shape used by pricing_errors_fix_view.
     *
     * @return array<string, mixed>
     */
    public function toTabulatorRow(): array
    {
        return [
            'id' => $this->marketplace.'|'.$this->sku,
            'channel' => $this->channel_label,
            'channel_key' => $this->marketplace,
            'pull_key' => $this->pull_key ?: $this->marketplace,
            'marketplace' => $this->marketplace,
            'image_path' => $this->image_path,
            'parent' => $this->parent,
            'sku' => $this->sku,
            'inv' => $this->inv,
            'ov_l30' => $this->ov_l30,
            'dil' => $this->dil,
            'price' => $this->price !== null && (float) $this->price > 0 ? round((float) $this->price, 2) : null,
            'groi' => $this->groi,
            'nroi' => $this->nroi,
            'gpft' => $this->gpft,
            'npft' => $this->npft,
            'sprice' => $this->sprice !== null && (float) $this->sprice > 0 ? round((float) $this->sprice, 2) : null,
            'sroi' => $this->sroi,
            'sgpft' => $this->sgpft,
            'snroi' => $this->snroi,
            'snpft' => $this->snpft,
            'success' => $this->success,
            'lp' => $this->lp,
            'ship' => $this->ship,
            'margin' => $this->margin,
            'ads_pct' => $this->ads_pct,
            '_selected' => false,
        ];
    }
}
