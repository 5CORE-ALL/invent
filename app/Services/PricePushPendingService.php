<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\CvrMasterController;
use App\Models\PricePushPending;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks every price push made from the /price-increase modals, retries the ones that did not go
 * through (hourly, see price-push:retry-pending) and e-mails an alert while something is still pending.
 */
class PricePushPendingService
{
    public const ALERT_EMAIL = 'software5@5core.com';
    public const MAX_ATTEMPTS = 72;     // hourly → about 3 days, then the row is marked "failed" (no more retries)
    public const REALERT_HOURS = 6;     // digest e-mail is repeated at most this often while something is pending
    private const EXTRA_KEYS = ['self_pick_price', 'goods_id', 'sku_id', 'product_id'];

    public static function key(string $marketplace): string
    {
        return preg_replace('/\s+/', '', strtolower(trim($marketplace)));
    }

    public function available(): bool
    {
        try {
            return Schema::hasTable('price_push_pendings');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Call the real push exactly like the modal does and return [ok, message, body]. */
    public function runPush(array $payload, ?Request $original = null): array
    {
        try {
            // From the web the real request is passed through (logged-in user → "pushed by" in the history)
            $resp = app(CvrMasterController::class)->pushPriceToAmazon(
                $original ?: Request::create('/cvr-master-push-price', 'POST', $payload)
            );
            $body = method_exists($resp, 'getData') ? $resp->getData(true) : [];
            $body = is_array($body) ? $body : [];
            $ok = ! empty($body['success']);

            return [$ok, (string) ($body['message'] ?? ($ok ? 'Pushed' : 'Push failed')), $body, $resp];
        } catch (\Throwable $e) {
            return [false, $e->getMessage() ?: 'Push exception', ['success' => false], null];
        }
    }

    /** Store the outcome of one push attempt (success → pushed, failure → pending / failed). */
    public function record(string $sku, string $marketplace, ?string $channel, float $price, array $extras, bool $ok, string $message, ?string $by = null): ?PricePushPending
    {
        if (! $this->available()) {
            return null;
        }
        $sku = strtoupper(trim($sku));
        $mp = self::key($marketplace);
        if ($sku === '' || $mp === '' || ! ($price > 0)) {
            return null;
        }

        try {
            $row = PricePushPending::firstOrNew(['sku' => $sku, 'marketplace' => $mp]);
            $samePrice = $row->exists && abs((float) $row->price - $price) < 0.005;
            if (! $samePrice) {
                $row->attempts = 0;      // a new price is a new job
                $row->alerted_at = null;
            }
            $row->channel = $channel ?: ($row->channel ?: $marketplace);
            $row->price = $price;
            $row->extras = $extras ?: null;
            $row->last_attempt_at = now();
            if ($by) {
                $row->requested_by = $by;
            }
            $row->attempts = (int) $row->attempts + 1;
            if ($ok) {
                $row->status = 'pushed';
                $row->pushed_at = now();
                $row->last_error = null;
            } else {
                $row->status = $row->attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
                $row->last_error = mb_substr($message, 0, 1000);
            }
            $row->save();

            return $row;
        } catch (\Throwable $e) {
            Log::warning('PricePushPending record failed: ' . $e->getMessage());

            return null;
        }
    }

    /** Re-push one stored row. */
    public function retryRow(PricePushPending $row): bool
    {
        $payload = array_merge(
            (array) ($row->extras ?? []),
            ['sku' => $row->sku, 'price' => $row->price, 'marketplace' => $row->marketplace]
        );
        [$ok, $msg] = $this->runPush($payload);
        $this->record($row->sku, $row->marketplace, $row->channel, (float) $row->price, (array) ($row->extras ?? []), $ok, $msg);

        return $ok;
    }

    /** Retry every outstanding row (not "failed"), up to $limit. Returns [tried, ok]. */
    public function retryAll(int $limit = 500): array
    {
        if (! $this->available()) {
            return [0, 0];
        }
        $rows = PricePushPending::where('status', 'pending')->orderBy('last_attempt_at')->limit($limit)->get();
        $ok = 0;
        foreach ($rows as $row) {
            if ($this->retryRow($row)) {
                $ok++;
            }
            usleep(400000); // be gentle with the marketplace APIs
        }

        return [$rows->count(), $ok];
    }

    /** E-mail software5@5core.com. $rows = outstanding rows to list. */
    public function sendAlert($rows, string $reason): bool
    {
        if (count($rows) === 0) {
            return false;
        }
        $lines = [];
        foreach ($rows as $r) {
            $lines[] = sprintf(
                '%s | %s | $%s | tries: %d | %s%s',
                $r->sku,
                $r->channel ?: $r->marketplace,
                number_format((float) $r->price, 2),
                (int) $r->attempts,
                $r->last_error ? mb_substr(preg_replace('/\s+/', ' ', (string) $r->last_error), 0, 160) : 'not pushed',
                $r->status === 'failed' ? ' | GAVE UP — push it manually' : ''
            );
        }
        $body = $reason . "\n\nSKU | Channel | Price | Tries | Last error\n" . implode("\n", $lines)
            . "\n\nThe pricing page shows a red alert while any of these is pending; the system retries every hour."
            . "\n" . url('/price-increase');
        try {
            Mail::raw($body, function ($m) use ($rows) {
                $m->to(self::ALERT_EMAIL)->subject('[Pricing] ' . count($rows) . ' channel price push(es) not pushed');
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('PricePushPending alert mail failed: ' . $e->getMessage());

            return false;
        }
    }

    /** Hourly digest: only when something is outstanding and the last alert is older than REALERT_HOURS. */
    public function alertDigestIfDue(): int
    {
        if (! $this->available()) {
            return 0;
        }
        $rows = PricePushPending::outstanding()->orderBy('sku')->get();
        if ($rows->isEmpty()) {
            return 0;
        }
        $due = $rows->contains(function ($r) {
            return $r->alerted_at === null || $r->alerted_at->lt(now()->subHours(self::REALERT_HOURS));
        });
        if (! $due) {
            return 0;
        }
        if ($this->sendAlert($rows, 'These channel prices are still NOT pushed (hourly retry did not fix them).')) {
            PricePushPending::whereIn('id', $rows->pluck('id'))->update(['alerted_at' => now()]);

            return $rows->count();
        }

        return 0;
    }
}
