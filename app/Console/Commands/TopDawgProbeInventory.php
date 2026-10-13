<?php

namespace App\Console\Commands;

use App\Services\TopDawgApiService;
use Illuminate\Console\Command;

/**
 * Find which TopDawg supplier call actually changes a product's live qty.
 * SupplierProduct/update answers "submitted for review" to any field, so each
 * attempt is checked by re-reading SupplierProduct/list.
 *
 *   php artisan topdawg:probe-inventory "LS 100"            # read-only: show live row qty fields
 *   php artisan topdawg:probe-inventory "LS 100" 200 --apply
 */
class TopDawgProbeInventory extends Command
{
    protected $signature = 'topdawg:probe-inventory
        {sku : Seller SKU / product_code}
        {qty? : Qty to push (required with --apply)}
        {--apply : Send the candidate updates}
        {--deep : Control test for the access error, then alternate request shapes on SupplierProduct/update}';

    protected $description = 'Probe TopDawg endpoints/fields that update live inventory, verified by read-back.';

    public function handle(TopDawgApiService $api): int
    {
        $sku = trim((string) $this->argument('sku'));
        $row = $api->liveProductRow($sku);
        if ($row === null) {
            $this->error("SKU {$sku} not found in TopDawg SupplierProduct/list.");

            return self::FAILURE;
        }

        $this->info('Live row (qty / stock / id fields):');
        $this->printQtyFields($row);
        $startQty = TopDawgApiService::qtyFromLiveProductRow($row);
        $this->line('Current live qty: '.var_export($startQty, true));

        if (! $this->option('apply')) {
            $this->line('');
            $this->line('Full row keys: '.implode(', ', array_keys($row)));
            $this->warn('Read-only. Re-run with a qty and --apply to try the update calls.');

            return self::SUCCESS;
        }

        $qty = $this->argument('qty');
        if ($qty === null || ! is_numeric($qty)) {
            $this->error('qty is required with --apply.');

            return self::FAILURE;
        }
        $qty = max(0, (int) $qty);
        $code = trim((string) ($row['product_code'] ?? $sku));

        if ($this->option('deep')) {
            return $this->deep($api, $sku, $code, (string) ($row['tdid'] ?? ''), $qty, $row);
        }

        $this->line('');
        $this->info('Endpoint discovery (404 = endpoint does not exist):');
        foreach ([
            '/SupplierProduct/updateInventory',
            '/SupplierProduct/updateStock',
            '/SupplierProduct/updateStockLevel',
            '/SupplierProduct/updateQty',
            '/SupplierProduct/updateQuantity',
            '/SupplierProduct/inventory',
            '/SupplierProduct/stock',
            '/SupplierInventory/update',
            '/SupplierStock/update',
        ] as $path) {
            $res = $api->rawSupplierPost($path, ['product_code' => $code, 'qty_available' => $qty, 'quantity' => $qty]);
            $this->line(sprintf('  %-36s HTTP %d  %s', $path, $res['status'], $this->short($res['body'])));
            if ($res['status'] >= 200 && $res['status'] < 300 && $this->changed($api, $sku, $qty, $path)) {
                return self::SUCCESS;
            }
        }

        $this->line('');
        $this->info('SupplierProduct/update field sweep:');
        $fields = array_values(array_unique(array_merge(
            $this->qtyKeys($row),
            ['qty_available', 'quantity', 'qty', 'stock', 'inventory', 'remaining_inventory', 'stock_level', 'available_qty', 'quantity_available']
        )));
        foreach ($fields as $field) {
            $res = $api->rawSupplierPost('/SupplierProduct/update', ['product_code' => $code, $field => $qty]);
            $this->line(sprintf('  %-22s HTTP %d  %s', $field, $res['status'], $this->short($res['body'])));
            if ($this->changed($api, $sku, $qty, 'SupplierProduct/update field '.$field)) {
                return self::SUCCESS;
            }
        }

        $this->warn('No call changed the live qty. TopDawg is queueing inventory for review (or ignores API qty);');
        $this->warn('ask help@topdawg.com which supplier API call updates stock levels immediately.');

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function deep(TopDawgApiService $api, string $sku, string $code, string $tdid, int $qty, array $row): int
    {
        $this->line('');
        $this->info('Control: endpoints that surely do not exist (same error = inventory endpoints above do not exist either):');
        foreach (['/SupplierProduct/zzNotARealCall', '/NotARealController/list'] as $path) {
            $res = $api->rawSupplierPost($path, ['product_code' => $code]);
            $this->line(sprintf('  %-36s HTTP %d  %s', $path, $res['status'], $this->short($res['body'])));
        }

        $this->line('');
        $this->info('Known endpoints (to see which calls this key may use):');
        foreach (['/SupplierProduct/list' => ['per_page' => 1, 'page' => 1], '/SupplierOrder/list' => ['per_page' => 1, 'page' => 1]] as $path => $body) {
            $res = $api->rawSupplierPost($path, $body);
            $this->line(sprintf('  %-36s HTTP %d  %s', $path, $res['status'], $this->short($res['body'], 100)));
        }

        $full = $row;
        $full['qty_available'] = $qty;
        $attempts = [
            'tdid + qty_available' => [['tdid' => $tdid, 'qty_available' => $qty], 'POST', false],
            'product_code + tdid + qty_available' => [['product_code' => $code, 'tdid' => $tdid, 'qty_available' => $qty], 'POST', false],
            'qty_available as string' => [['product_code' => $code, 'qty_available' => (string) $qty], 'POST', false],
            'products[] array' => [['products' => [['product_code' => $code, 'qty_available' => $qty]]], 'POST', false],
            'form-encoded' => [['product_code' => $code, 'qty_available' => $qty], 'POST', true],
            'PUT' => [['product_code' => $code, 'qty_available' => $qty], 'PUT', false],
            'full live row with new qty' => [$full, 'POST', false],
        ];

        $this->line('');
        $this->info('SupplierProduct/update request shapes:');
        foreach ($attempts as $label => [$body, $method, $form]) {
            $res = $api->rawSupplierPost('/SupplierProduct/update', $body, $method, $form);
            $this->line(sprintf('  %-38s HTTP %d  %s', $label, $res['status'], $this->short($res['body'])));
            if ($res['status'] >= 200 && $res['status'] < 300 && $this->changed($api, $sku, $qty, $label)) {
                return self::SUCCESS;
            }
        }

        $this->warn('No shape changed the live qty immediately.');
        $this->warn('Re-run without --apply in a few hours: if qty is then '.$qty.', TopDawg applies updates after review; if still unchanged, reviews are never applied.');

        return self::FAILURE;
    }

    private function changed(TopDawgApiService $api, string $sku, int $qty, string $label): bool
    {
        sleep(3);
        $after = $api->liveProductRow($sku);
        $live = $after ? TopDawgApiService::qtyFromLiveProductRow($after) : null;
        if ($live === $qty) {
            $this->info("  ✓ Live qty is now {$qty} after: {$label}");

            return true;
        }
        $this->line('    live qty after: '.var_export($live, true));

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function qtyKeys(array $row): array
    {
        return array_values(array_filter(array_keys($row), static fn ($k) => is_string($k)
            && preg_match('/qty|quant|stock|inventor/i', $k)
            && ! is_array($row[$k])));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function printQtyFields(array $row, string $prefix = '  '): void
    {
        foreach ($row as $key => $value) {
            if (is_array($value)) {
                if (preg_match('/variant|option|child|product|inventor|stock/i', (string) $key)) {
                    $this->line($prefix.$key.': '.$this->short(json_encode($value), 400));
                }
                continue;
            }
            if (preg_match('/qty|quant|stock|inventor|product_code|^sku$|^id$|tdid|status/i', (string) $key)) {
                $this->line($prefix.$key.': '.var_export($value, true));
            }
        }
    }

    private function short(?string $s, int $max = 160): string
    {
        $s = trim(preg_replace('/\s+/', ' ', (string) $s) ?? '');

        return strlen($s) > $max ? substr($s, 0, $max).'…' : $s;
    }
}
