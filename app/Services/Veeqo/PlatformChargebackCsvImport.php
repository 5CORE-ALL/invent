<?php

namespace App\Services\Veeqo;

use App\Models\VeeqoChargeback;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Shipping label charge exports from platforms with no label-billing API.
 *
 * Shopify bill export (Charge category, Description, Amount, Order, Charge date, Bill #, ...):
 *   shipping_fee rows are label purchases ("Ground Advantage to Bronx, New York") or
 *   "Shipping Label Price Adjustment" (carrier chargebacks). Amounts are positive charges.
 * Temu "Transactions by custom date range" (Date, Transaction type, Order ID, Other, Total, ...):
 *   "Shipping label purchase adjustment" rows keyed by the label order (DO...). Totals are signed.
 *
 * Neither export has a transaction id, so rows are keyed by their content plus the occurrence
 * number of identical rows in the file; re-importing the same or an overlapping file upserts.
 */
class PlatformChargebackCsvImport
{
    public const PLATFORMS = [
        'shopify' => ['source' => 'shopify_csv', 'name' => 'Shopify', 'required' => ['Charge category', 'Description', 'Amount', 'Order', 'Charge date']],
        'temu1' => ['source' => 'temu1_csv', 'name' => 'Temu 1', 'required' => ['Date', 'Transaction type', 'Order ID', 'Total']],
        'temu2' => ['source' => 'temu2_csv', 'name' => 'Temu 2', 'required' => ['Date', 'Transaction type', 'Order ID', 'Total']],
        'temu3' => ['source' => 'temu3_csv', 'name' => 'Temu 3', 'required' => ['Date', 'Transaction type', 'Order ID', 'Total']],
    ];

    public const SHOPIFY_LABEL = 'Shopify Shipping Label';

    public const SHOPIFY_ADJUSTMENT = 'Shopify Label Price Adjustment';

    public const TEMU_ADJUSTMENT = 'Temu Label Purchase Adjustment';

    /**
     * @return array{ok: bool, message: string, rows: int, saved: int}
     */
    public function import(string $platform, string $path): array
    {
        $cfg = self::PLATFORMS[$platform] ?? null;
        if (! $cfg) {
            return ['ok' => false, 'message' => 'Unknown platform '.$platform.'.', 'rows' => 0, 'saved' => 0];
        }
        $fh = @fopen($path, 'r');
        if (! $fh) {
            return ['ok' => false, 'message' => 'Could not open the CSV file.', 'rows' => 0, 'saved' => 0];
        }
        $head = array_map(fn ($h) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), (array) fgetcsv($fh));
        $missing = array_diff($cfg['required'], $head);
        if ($missing !== []) {
            fclose($fh);

            return ['ok' => false, 'message' => 'This is not a '.$cfg['name'].' file (missing columns: '.implode(', ', $missing).').', 'rows' => 0, 'saved' => 0];
        }

        $now = now()->format('Y-m-d H:i:s');
        $seen = [];
        $batch = [];
        $rows = 0;
        $saved = 0;
        while (($line = fgetcsv($fh)) !== false) {
            if ($line === [null]) {
                continue;
            }
            $rows++;
            $r = array_combine($head, array_slice(array_pad($line, count($head), ''), 0, count($head)));
            $row = str_starts_with($platform, 'temu') ? $this->temuRow($cfg, $r) : $this->shopifyRow($cfg, $r);
            if ($row === null) {
                continue;
            }
            $key = sha1($cfg['source'].'|'.$row['_key']);
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            unset($row['_key']);
            $batch[] = $row + [
                'transaction_id' => mb_substr($cfg['source'].':'.$key.':'.$seen[$key], 0, 128),
                'source' => $cfg['source'],
                'marketplace_name' => $cfg['name'],
                'currency' => 'USD',
                'raw' => json_encode($r),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($batch) >= 500) {
                $saved += $this->flush($batch);
                $batch = [];
            }
        }
        fclose($fh);
        $saved += $this->flush($batch);
        if ($platform === 'shopify') {
            $this->fillAdjustmentCarriers($cfg['source']);
        }
        Cache::forget('veeqo_chargeback_component_types');

        return ['ok' => true, 'message' => 'Read '.number_format($rows).' rows; imported '.number_format($saved).' '.$cfg['name'].' shipping charges.', 'rows' => $rows, 'saved' => $saved];
    }

    private function shopifyRow(array $cfg, array $r): ?array
    {
        $amount = $r['Amount'] ?? '';
        if (! is_numeric($amount) || trim((string) $r['Charge category']) !== 'shipping_fee') {
            return null;
        }
        $description = trim((string) $r['Description']);
        $isAdjustment = stripos($description, 'adjustment') !== false;
        $service = $isAdjustment ? null : trim((string) preg_replace('/\s+to\s+.*$/u', '', $description));
        $total = round(-(float) $amount, 2);
        $bill = trim((string) ($r['Bill #'] ?? ''));
        $order = trim((string) $r['Order']);
        $date = $this->date((string) $r['Charge date'], ['d-m-Y', 'Y-m-d', 'm/d/Y']);

        return [
            '_key' => implode('|', [$description, $amount, $order, $r['Charge date']]),
            'transaction_type' => 'shipping_fee',
            'description' => $isAdjustment ? self::SHOPIFY_ADJUSTMENT : self::SHOPIFY_LABEL,
            'reason' => $isAdjustment ? 'Shipping label price adjustment' : null,
            'transaction_status' => $bill !== '' ? 'Billed' : 'Not billed yet',
            'posted_at' => $date,
            'order_number' => $this->str($order === '0' ? null : $order, 100),
            'settlement_id' => $this->str($bill, 64),
            'total_amount' => $total,
            'direction' => $total < 0 ? 'debit' : 'credit',
            'carrier' => $service ? $this->carrier($service) : null,
            'service' => $this->str($service, 150),
            'item_types' => $this->str($isAdjustment ? null : preg_replace('/^.*?\s+to\s+/u', '', $description), 500),
        ];
    }

    private function temuRow(array $cfg, array $r): ?array
    {
        $total = $r['Total'] ?? '';
        if (! is_numeric($total)) {
            return null;
        }
        $type = trim((string) $r['Transaction type']);
        $total = round((float) $total, 2);
        $order = trim((string) $r['Order ID']);

        return [
            '_key' => implode('|', [$r['Date'], $type, $order, $r['Other'] ?? '', $r['Total']]),
            'transaction_type' => $this->str($type, 64),
            'description' => strcasecmp($type, 'Shipping label purchase adjustment') === 0 ? self::TEMU_ADJUSTMENT : mb_substr('Temu '.$type, 0, 100),
            'reason' => $this->str($r['Product details'] ?? null, 150),
            'transaction_status' => null,
            'posted_at' => $this->date((string) $r['Date'], ['Y/m/d', 'Y-m-d', 'm/d/Y']),
            'order_number' => $this->str($order, 100),
            'settlement_id' => null,
            'total_amount' => $total,
            'direction' => $total < 0 ? 'debit' : 'credit',
            'carrier' => null,
            'service' => null,
            'item_types' => null,
        ];
    }

    /**
     * Shopify adjustment lines carry no service; take carrier and service from the label bought for the same order.
     */
    private function fillAdjustmentCarriers(string $source): void
    {
        DB::statement(
            'UPDATE veeqo_chargebacks a
             JOIN (SELECT order_number, MAX(carrier) AS carrier, MAX(service) AS service
                   FROM veeqo_chargebacks
                   WHERE source = ? AND description = ? AND carrier IS NOT NULL AND order_number IS NOT NULL
                   GROUP BY order_number) l ON l.order_number = a.order_number
             SET a.carrier = l.carrier, a.service = COALESCE(a.service, l.service)
             WHERE a.source = ? AND a.description = ? AND a.carrier IS NULL',
            [$source, self::SHOPIFY_LABEL, $source, self::SHOPIFY_ADJUSTMENT]
        );
    }

    private function carrier(string $service): ?string
    {
        return match (true) {
            stripos($service, 'fedex') !== false => 'FedEx',
            stripos($service, 'ups') === 0 => 'UPS',
            stripos($service, 'dhl') !== false => 'DHL',
            (bool) preg_match('/ground advantage|priority mail|first[- ]class|media mail|parcel select|usps/i', $service) => 'USPS',
            default => null,
        };
    }

    private function flush(array $batch): int
    {
        if ($batch === []) {
            return 0;
        }
        $update = array_values(array_diff(array_keys($batch[0]), ['transaction_id', 'created_at']));
        VeeqoChargeback::query()->upsert($batch, ['transaction_id'], $update);

        return count($batch);
    }

    /**
     * @param  list<string>  $formats
     */
    private function date(string $value, array $formats): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        foreach ($formats as $format) {
            try {
                $d = Carbon::createFromFormat('!'.$format, $value, config('app.timezone'));
                if ($d && $d->format($format) === $value) {
                    return $d->setTime(12, 0)->format('Y-m-d H:i:s');
                }
            } catch (\Throwable $e) {
            }
        }

        return null;
    }

    private function str(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
