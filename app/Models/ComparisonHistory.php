<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComparisonHistory extends Model
{
    protected $table = 'comparison_histories';

    public $timestamps = false;

    protected $fillable = [
        'sku',
        'parent',
        'field',
        'old_value',
        'new_value',
        'changes',
        'updated_by',
        'updated_at',
    ];

    protected $casts = [
        'updated_at' => 'datetime',
    ];

    public static function fieldLabels(): array
    {
        return [
            'clink' => 'Comparison Link',
            'lmp' => 'LMP',
            'notes' => 'Notes',
            'sheet_data' => 'Comparison Sheet',
            'google_import' => 'Google Sheet Import',
        ];
    }

    public static function logChange(
        string $sku,
        ?string $parent,
        string $field,
        $oldValue,
        $newValue,
        ?string $updatedBy = null
    ): void {
        $old = self::stringifyValue($oldValue);
        $new = self::stringifyValue($newValue);

        if ($old === $new) {
            return;
        }

        $label = self::fieldLabels()[$field] ?? $field;
        $changes = sprintf('%s changed from "%s" to "%s"', $label, $old ?: 'empty', $new ?: 'empty');

        self::create([
            'sku' => $sku,
            'parent' => $parent !== '' ? $parent : null,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'changes' => $changes,
            'updated_by' => $updatedBy ?: 'N/A',
            'updated_at' => now(),
        ]);
    }

    private static function stringifyValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return trim((string) $value);
    }
}
