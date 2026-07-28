<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransitDropdownOption extends Model
{
    protected $table = 'transit_dropdown_options';

    protected $fillable = [
        'field',
        'value',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public const FIELD_IMP = 'imp_name';
    public const FIELD_HSN = 'hsn_code';

    public static function optionsFor(string $field): array
    {
        return static::query()
            ->where('field', $field)
            ->orderByDesc('last_used_at')
            ->orderBy('value')
            ->pluck('value')
            ->map(fn ($v) => (string) $v)
            ->values()
            ->all();
    }

    public static function lastUsed(string $field): ?string
    {
        $row = static::query()
            ->where('field', $field)
            ->whereNotNull('last_used_at')
            ->orderByDesc('last_used_at')
            ->first();

        return $row ? (string) $row->value : null;
    }

    public static function addOption(string $field, string $value): self
    {
        $value = trim($value);
        $row = static::firstOrCreate(
            ['field' => $field, 'value' => $value],
            ['last_used_at' => null]
        );

        return $row;
    }

    public static function rememberSelection(string $field, ?string $value): void
    {
        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        $row = static::addOption($field, $value);
        $row->last_used_at = now();
        $row->save();
    }
}
