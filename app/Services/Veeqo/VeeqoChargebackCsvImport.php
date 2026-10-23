<?php

namespace App\Services\Veeqo;

use App\Models\VeeqoChargeback;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Veeqo "reports-*.csv" shipping transactions export:
 * Type, Payment Status, Payment Method, Transaction ID, Value, Service, Carrier, Created Date, Order ID, Details
 * Only "Chargeback Debit/Credit - Outbound" rows are kept; dates are in the timezone named in the file name.
 */
class VeeqoChargebackCsvImport
{
    private const REQUIRED = ['Type', 'Transaction ID', 'Value', 'Created Date'];

    /**
     * @return array{ok: bool, message: string, rows: int, chargebacks: int, saved: int}
     */
    public function import(string $path, string $originalName = ''): array
    {
        $fh = @fopen($path, 'r');
        if (! $fh) {
            return $this->result(false, 'Could not open the CSV file.');
        }

        $head = fgetcsv($fh);
        $head = array_map(fn ($h) => trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $h)), (array) $head);
        $missing = array_diff(self::REQUIRED, $head);
        if ($missing !== []) {
            fclose($fh);

            return $this->result(false, 'Not a Veeqo transactions CSV (missing: '.implode(', ', $missing).').');
        }

        $tz = $this->timezoneFromName($originalName !== '' ? $originalName : basename($path));
        $appTz = config('app.timezone');
        $now = now()->format('Y-m-d H:i:s');
        $rows = 0;
        $batch = [];
        $saved = 0;

        while (($line = fgetcsv($fh)) !== false) {
            if ($line === [null]) {
                continue;
            }
            $rows++;
            $r = array_combine($head, array_slice(array_pad($line, count($head), ''), 0, count($head)));
            $type = trim((string) $r['Type']);
            $txId = trim((string) $r['Transaction ID']);
            if (stripos($type, 'Chargeback') !== 0 || $txId === '' || ! is_numeric($r['Value'])) {
                continue;
            }

            $amount = round((float) $r['Value'], 2);
            $order = trim((string) ($r['Order ID'] ?? ''));
            $batch[] = [
                'transaction_id' => mb_substr('veeqo:'.$txId, 0, 128),
                'source' => 'veeqo_csv',
                'transaction_type' => 'Chargeback',
                'description' => mb_substr($type, 0, 100),
                'reason' => $this->str($r['Details'] ?? null, 150),
                'transaction_status' => $this->str($r['Payment Status'] ?? null, 32),
                'posted_at' => $this->date((string) $r['Created Date'], $tz, $appTz),
                'amazon_order_id' => preg_match('/^\d{3}-\d{7}-\d{7}$/', $order) ? $order : null,
                'order_number' => $this->str($order, 100),
                'total_amount' => $amount,
                'currency' => 'USD',
                'direction' => $amount < 0 ? 'debit' : 'credit',
                'carrier' => $this->str($r['Carrier'] ?? null, 100),
                'service' => $this->str($r['Service'] ?? null, 150),
                'payment_status' => $this->str($r['Payment Status'] ?? null, 50),
                'payment_method' => $this->str($r['Payment Method'] ?? null, 100),
                'raw' => json_encode($r),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if (count($batch) >= 300) {
                $saved += $this->flush($batch);
                $batch = [];
            }
        }
        fclose($fh);
        $saved += $this->flush($batch);
        Cache::forget('veeqo_chargeback_component_types');

        return [
            'ok' => true,
            'message' => 'Read '.number_format($rows).' CSV rows; imported '.number_format($saved).' Veeqo chargebacks (times read as '.$tz.').',
            'rows' => $rows,
            'chargebacks' => $saved,
            'saved' => $saved,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     */
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
     * reports-85186_USD_20250901_20260930_AsiaKolkata_original.csv → Asia/Kolkata
     */
    private function timezoneFromName(string $name): string
    {
        if (preg_match('/_(Africa|America|Antarctica|Asia|Atlantic|Australia|Europe|Indian|Pacific)([A-Z][A-Za-z]+)_/', $name, $m)) {
            $city = preg_replace('/(?<=[a-z])(?=[A-Z])/', '_', $m[2]);
            $tz = $m[1].'/'.$city;
            if (in_array($tz, timezone_identifiers_list(), true)) {
                return $tz;
            }
        }
        if (preg_match('/_UTC_/i', $name)) {
            return 'UTC';
        }

        return 'Asia/Kolkata';
    }

    private function date(string $value, string $tz, string $appTz): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            return Carbon::parse($value, $tz)->setTimezone($appTz)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function str(mixed $value, int $max): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /**
     * @return array{ok: bool, message: string, rows: int, chargebacks: int, saved: int}
     */
    private function result(bool $ok, string $message): array
    {
        return ['ok' => $ok, 'message' => $message, 'rows' => 0, 'chargebacks' => 0, 'saved' => 0];
    }
}
