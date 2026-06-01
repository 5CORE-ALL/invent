<?php

namespace App\Http\Controllers\CustomerCare\Concerns;

/** Optional order_number on issue rows (label, carrier, listing, etc.). */
trait HasOptionalOrderNumberField
{
    protected function extraValidationRules(): array
    {
        return [
            'order_number' => 'nullable|string|max:255',
        ];
    }

    protected function buildExtraPayload(array $validated): array
    {
        return [
            'order_number' => isset($validated['order_number']) ? trim((string) $validated['order_number']) : null,
        ];
    }

    protected function extraRowFields(object $row): array
    {
        return [
            'order_number' => $row->order_number ?? null,
        ];
    }

    protected function extraHistoryRowFields(object $row): array
    {
        return [
            'order_number' => $row->order_number ?? null,
        ];
    }

    protected function csvImportExtraPayload(callable $get): array
    {
        $v = $get('order_number');

        return [
            'order_number' => $v !== null && $v !== '' ? $v : null,
        ];
    }
}
