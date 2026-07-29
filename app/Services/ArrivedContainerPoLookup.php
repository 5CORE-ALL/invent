<?php

namespace App\Services;

use App\Models\PurchaseOrder;

/**
 * Shared PO Number / O link options for arrived_containers pages
 * (pricing, QC, arrived, inv-verify).
 */
class ArrivedContainerPoLookup
{
    /**
     * @return array{0: array<string, list<array<string, mixed>>>, 1: list<array<string, mixed>>, 2: array<string, array<string, array{price: ?float, currency: string}>>}
     */
    public static function build(): array
    {
        $poBySku = [];
        $allPoOptions = [];
        $poPriceLookup = [];

        $poRows = PurchaseOrder::query()
            ->where(function ($q) {
                $q->where('is_archived', false)->orWhereNull('is_archived');
            })
            ->orderByDesc('id')
            ->get(['id', 'po_number', 'items']);

        foreach ($poRows as $po) {
            $poNumber = trim((string) ($po->po_number ?? ''));
            if ($poNumber === '') {
                continue;
            }
            $pdfUrl = route('generate-pdf', ['id' => $po->id]);
            $baseOption = [
                'id' => (int) $po->id,
                'po_number' => $poNumber,
                'link' => $pdfUrl,
                'page_url' => route('list-all-purchase-orders').'?po='.urlencode($poNumber),
            ];
            $allPoOptions[] = $baseOption;

            $items = $po->items;
            if (is_string($items)) {
                $items = json_decode($items, true);
            }
            if (! is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                $itemSku = strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($item['sku'] ?? ''))));
                if ($itemSku === '') {
                    continue;
                }
                $price = is_numeric($item['price'] ?? null) ? (float) $item['price'] : null;
                $currency = strtoupper(trim((string) ($item['currency'] ?? 'USD')));
                if ($currency === '') {
                    $currency = 'USD';
                }

                $poPriceLookup[$poNumber][$itemSku] = [
                    'price' => $price,
                    'currency' => $currency,
                ];

                $option = array_merge($baseOption, [
                    'price' => $price,
                    'currency' => $currency,
                ]);

                $poBySku[$itemSku] ??= [];
                $seen = false;
                foreach ($poBySku[$itemSku] as $existing) {
                    if (($existing['po_number'] ?? '') === $poNumber) {
                        $seen = true;
                        break;
                    }
                }
                if (! $seen) {
                    $poBySku[$itemSku][] = $option;
                }
            }
        }

        return [$poBySku, $allPoOptions, $poPriceLookup];
    }

    /**
     * Attach po_options onto each arrived-container record (by SKU).
     *
     * @param  \Illuminate\Support\Collection<int, mixed>  $records
     * @param  array<string, list<array<string, mixed>>>  $poBySku
     */
    public static function attachPoOptions($records, array $poBySku): void
    {
        $normalizeSku = static function ($value) {
            return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $value)));
        };

        $records->transform(function ($record) use ($poBySku, $normalizeSku) {
            $skuKey = $normalizeSku($record->our_sku ?? '');
            $record->po_options = $poBySku[$skuKey] ?? [];

            return $record;
        });
    }
}
