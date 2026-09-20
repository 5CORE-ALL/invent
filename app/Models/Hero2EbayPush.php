<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hero2EbayPush extends Model
{
    public const ACCOUNTS = [
        'ebay' => 'eBay 1',
        'ebay2' => 'eBay 2',
        'ebay3' => 'eBay 3',
    ];

    protected $table = 'hero2_ebay_pushes';

    protected $fillable = [
        'sku',
        'account',
        'image_url',
        'item_id',
        'variation_value',
        'is_variation',
        'pushed_at',
    ];

    protected $casts = [
        'is_variation' => 'boolean',
        'pushed_at' => 'datetime',
    ];

    public static function label(string $account): string
    {
        return self::ACCOUNTS[$account] ?? $account;
    }

    /**
     * @return array{account: string, label: string, image_url: string, item_id: string|null, variation_value: string|null, is_variation: bool, pushed_at: string|null, pushed_at_label: string|null}
     */
    public function toUiArray(): array
    {
        $pushedAt = $this->pushed_at;

        return [
            'account' => (string) $this->account,
            'label' => self::label((string) $this->account),
            'image_url' => (string) $this->image_url,
            'item_id' => $this->item_id !== null && $this->item_id !== '' ? (string) $this->item_id : null,
            'variation_value' => $this->variation_value !== null && $this->variation_value !== '' ? (string) $this->variation_value : null,
            'is_variation' => (bool) $this->is_variation,
            'pushed_at' => $pushedAt?->toIso8601String(),
            'pushed_at_label' => $pushedAt?->timezone(config('app.timezone'))->format('M j, Y g:i A'),
        ];
    }
}
