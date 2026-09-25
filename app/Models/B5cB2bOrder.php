<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class B5cB2bOrder extends Model
{
    protected $table = 'b5c_b2b_orders';

    protected $fillable = [
        'store_order_id',
        'status',
        'customer_email',
        'customer_name',
        'currency',
        'total',
        'tracking_reference',
        'ordered_at',
        'shopify_order_id',
        'shopify_imported_at',
        'payload',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'ordered_at' => 'datetime',
        'shopify_imported_at' => 'datetime',
        'payload' => 'array',
    ];

    /**
     * Public Business 5 Core order code (B5-0009), not the numeric store id.
     */
    public function channelOrderNumber(): string
    {
        $payload = is_array($this->payload) ? $this->payload : [];
        foreach (['order_number', 'number', 'code', 'reference'] as $key) {
            $value = strtoupper(trim((string) ($payload[$key] ?? '')));
            if (preg_match('/^B5-\d+$/', $value) === 1) {
                return $value;
            }
        }

        $id = max(0, (int) $this->store_order_id);

        return 'B5-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * B5-0009 and #9 both resolve to store order id 9.
     */
    public static function storeIdFromSearch(string $search): ?int
    {
        $search = trim($search);
        if (preg_match('/^#?B5-0*(\d+)$/i', $search, $match) === 1) {
            return (int) $match[1];
        }
        if ($search !== '' && ctype_digit($search)) {
            return (int) $search;
        }

        return null;
    }

    /**
     * Order lines with a SKU taken from the store payload, including nested product fields.
     *
     * @return list<array{sku: string, name: string, qty: int, price: float, product_id: int}>
     */
    public function normalizedLines(): array
    {
        $raw = self::rawLines(is_array($this->payload) ? $this->payload : []);
        $lines = [];
        foreach ($raw as $line) {
            if (! is_array($line)) {
                continue;
            }
            $sku = self::skuFromLine($line);
            $name = self::lineName($line);
            $qty = (int) ($line['qty'] ?? $line['quantity'] ?? $line['qty_ordered'] ?? $line['ordered_qty'] ?? 0);
            $price = (float) ($line['unit_price'] ?? $line['price'] ?? $line['selling_price'] ?? $line['unitPrice'] ?? 0);
            $productId = (int) ($line['product_id'] ?? $line['listing_id'] ?? 0);
            if ($productId <= 0 && is_array($line['product'] ?? null)) {
                $productId = (int) ($line['product']['id'] ?? $line['product']['listing_id'] ?? 0);
            }
            if ($sku === '' && $name === '' && $qty <= 0) {
                continue;
            }
            $lines[] = [
                'sku' => $sku,
                'name' => $name,
                'qty' => $qty,
                'price' => $price,
                'product_id' => $productId,
            ];
        }

        return $lines;
    }

    /**
     * Same as normalizedLines(), filling a blank SKU from the local Business 5 Core catalog.
     *
     * @return list<array{sku: string, name: string, qty: int, price: float, product_id: int}>
     */
    public function displayLines(): array
    {
        $lines = $this->normalizedLines();
        foreach ($lines as $index => $line) {
            if ($line['sku'] !== '') {
                continue;
            }
            $lines[$index]['sku'] = self::skuFromCatalog($line);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function skuFromLine(array $line): string
    {
        $candidates = [];
        foreach (['sku', 'seller_sku', 'product_sku', 'variant_sku', 'item_sku', 'sku_code'] as $key) {
            $candidates[] = $line[$key] ?? null;
        }
        foreach (['product', 'variant', 'item'] as $nest) {
            if (! is_array($line[$nest] ?? null)) {
                continue;
            }
            foreach (['sku', 'seller_sku', 'product_sku', 'sku_code', 'code'] as $key) {
                $candidates[] = $line[$nest][$key] ?? null;
            }
        }

        foreach ($candidates as $value) {
            if (is_array($value)) {
                $value = $value['sku'] ?? $value['code'] ?? '';
            }
            $sku = trim((string) $value);
            if ($sku !== '' && stripos($sku, 'PARENT') === false) {
                return $sku;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<mixed>
     */
    public static function rawLines(array $payload): array
    {
        foreach (['products', 'line_items', 'items', 'lines', 'order_items', 'order_products'] as $key) {
            if (is_array($payload[$key] ?? null) && $payload[$key] !== []) {
                return array_values($payload[$key]);
            }
        }
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        foreach (['products', 'line_items', 'items', 'lines', 'order_items'] as $key) {
            if (is_array($data[$key] ?? null) && $data[$key] !== []) {
                return array_values($data[$key]);
            }
        }

        return [];
    }

    /**
     * @param  array{name?: string, product_id?: int}  $line
     */
    public static function skuFromCatalog(array $line): string
    {
        try {
            if (! \Illuminate\Support\Facades\Schema::hasTable('b5c_b2b_products')) {
                return '';
            }
        } catch (\Throwable) {
            return '';
        }

        $productId = (int) ($line['product_id'] ?? 0);
        if ($productId > 0) {
            $hit = B5cB2bProduct::query()->where('listing_id', $productId)->value('sku');
            $sku = trim((string) $hit);
            if ($sku !== '') {
                return $sku;
            }
        }

        $title = trim((string) ($line['name'] ?? ''));
        if ($title === '') {
            return '';
        }

        $hit = B5cB2bProduct::query()->where('title', $title)->value('sku');

        return trim((string) $hit);
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected static function lineName(array $line): string
    {
        foreach (['name', 'title', 'product_name', 'product_title'] as $key) {
            $value = trim((string) ($line[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        if (is_array($line['product'] ?? null)) {
            foreach (['name', 'title'] as $key) {
                $value = trim((string) ($line['product'][$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }
}
