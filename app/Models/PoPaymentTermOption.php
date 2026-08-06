<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PoPaymentTermOption extends Model
{
    protected $table = 'po_payment_term_options';

    protected $fillable = [
        'value',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    /**
     * @return list<string>
     */
    public static function optionsList(): array
    {
        return static::query()
            ->orderByDesc('last_used_at')
            ->orderBy('id')
            ->pluck('value')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    public static function addOption(string $value): self
    {
        $value = trim($value);

        return static::firstOrCreate(
            ['value' => $value],
            ['last_used_at' => null]
        );
    }

    public static function rememberSelection(?string $value): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        $row = static::addOption($value);
        $row->last_used_at = now();
        $row->save();
    }
}
