<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MipPreChecklist extends Model
{
    protected $table = 'mip_pre_checklists';

    protected $fillable = [
        'source_table',
        'source_id',
        'sku',
        'items',
        'status',
        'escalation_note',
        'updated_by',
        'escalated_by',
        'escalated_at',
    ];

    protected $casts = [
        'items' => 'array',
        'escalated_at' => 'datetime',
    ];

    public static function defaultItems(): array
    {
        return [
            ['id' => 'qc', 'label' => 'QC', 'checked' => false],
            ['id' => 'printing_approvals', 'label' => 'Printing approvals', 'checked' => false],
            ['id' => 'compliance', 'label' => 'Compliance', 'checked' => false],
            ['id' => 'instructions_followed', 'label' => 'Instructions followed', 'checked' => false],
            ['id' => 'timely_delivery', 'label' => 'Timely Delivery', 'checked' => false],
        ];
    }

    public static function mergeWithDefaults(?array $items): array
    {
        $saved = collect($items ?? [])->keyBy('id');
        $merged = [];

        foreach (self::defaultItems() as $default) {
            $hit = $saved->get($default['id']);
            $merged[] = [
                'id' => $default['id'],
                'label' => $hit['label'] ?? $default['label'],
                'checked' => (bool) ($hit['checked'] ?? false),
            ];
        }

        foreach ($saved as $id => $item) {
            if (collect($merged)->contains(fn ($m) => $m['id'] === $id)) {
                continue;
            }
            $merged[] = [
                'id' => (string) $id,
                'label' => (string) ($item['label'] ?? $id),
                'checked' => (bool) ($item['checked'] ?? false),
            ];
        }

        return $merged;
    }

    public static function allItemsChecked(array $items): bool
    {
        if ($items === []) {
            return false;
        }

        foreach ($items as $item) {
            if (empty($item['checked'])) {
                return false;
            }
        }

        return true;
    }
}
