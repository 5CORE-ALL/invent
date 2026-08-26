<?php

namespace App\Http\Controllers\CustomerCare\Concerns;

use Illuminate\Support\Facades\Schema;

/** Optional order_number on issue rows (label, carrier, listing, etc.). */
trait HasOptionalOrderNumberField
{
    protected function extraValidationRules(): array
    {
        return [
            'order_number' => 'nullable|string|max:255',
            'total_loss' => 'nullable|numeric',
        ];
    }

    protected function buildExtraPayload(array $validated): array
    {
        $payload = [
            'order_number' => isset($validated['order_number']) ? trim((string) $validated['order_number']) : null,
        ];
        if (Schema::hasColumn($this->issuesTable(), 'total_loss') && request()->exists('total_loss')) {
            $lossRaw = request()->input('total_loss');
            $payload['total_loss'] = ($lossRaw === null || $lossRaw === '') ? null : (float) $lossRaw;
        }

        return $payload;
    }

    protected function extraRowFields(object $row): array
    {
        return [
            'order_number' => $row->order_number ?? null,
            'total_loss' => isset($row->total_loss) && $row->total_loss !== null ? (float) $row->total_loss : null,
        ];
    }

    protected function extraHistoryRowFields(object $row): array
    {
        return [
            'order_number' => $row->order_number ?? null,
            'total_loss' => isset($row->total_loss) && $row->total_loss !== null ? (float) $row->total_loss : null,
        ];
    }

    protected function csvImportExtraPayload(callable $get): array
    {
        $v = $get('order_number');
        $loss = $get('total_loss');

        $payload = [
            'order_number' => $v !== null && $v !== '' ? $v : null,
        ];
        if (Schema::hasColumn($this->issuesTable(), 'total_loss')) {
            $payload['total_loss'] = ($loss !== null && $loss !== '' && is_numeric($loss)) ? (float) $loss : null;
        }

        return $payload;
    }
}
