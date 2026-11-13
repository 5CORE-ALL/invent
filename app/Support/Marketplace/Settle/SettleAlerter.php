<?php

namespace App\Support\Marketplace\Settle;

use App\Models\SettleAlertLog;
use App\Support\Marketplace\YesterdaySettleLedger as Ledger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends settle alerts: always to the log, optionally by e-mail (SETTLE_ALERT_EMAILS) and webhook
 * (SETTLE_ALERT_WEBHOOK). The same alert (type + date + task + check codes) is not repeated
 * within settle.alert_repeat_hours. Alerts never throw into the caller.
 */
final class SettleAlerter
{
    /**
     * @param  list<array<string, mixed>>  $events  from SettleVerifier::events()
     * @return list<array<string, mixed>> the events that were actually sent
     */
    public function dispatch(string $date, array $events, ?Carbon $now = null): array
    {
        if ($events === []) {
            return [];
        }
        $now = ($now ?? Carbon::now(Ledger::TZ))->copy();
        $hours = max(1, (int) config('settle.alert_repeat_hours', 12));

        $fresh = [];
        foreach ($events as $e) {
            $fp = self::fingerprint($date, $e);
            try {
                $recent = SettleAlertLog::query()
                    ->where('fingerprint', $fp)
                    ->where('sent_at', '>=', $now->copy()->subHours($hours))
                    ->exists();
            } catch (\Throwable $ex) {
                Log::warning('[settle-alert] alert log unavailable: '.$ex->getMessage());
                $recent = false;
            }
            if (! $recent) {
                $e['fingerprint'] = $fp;
                $fresh[] = $e;
            }
        }
        if ($fresh === []) {
            return [];
        }

        $subject = sprintf('[Yesterday settle %s] %d alert(s): %s', $date, count($fresh), implode(', ', array_unique(array_map(fn ($e) => SettleCatalog::marketplace((string) $e['task']), $fresh))));
        $body = $subject."\n\n".implode("\n\n", array_map(fn ($e) => '['.$e['type'].'] '.$e['title']."\n".$e['message'], $fresh));

        $channels = ['log'];
        Log::error('[settle-alert] '.$body);

        $emails = (array) config('settle.alert_emails', []);
        if ($emails !== []) {
            try {
                Mail::raw($body, function ($m) use ($emails, $subject) {
                    $m->to($emails)->subject($subject);
                });
                $channels[] = 'mail';
            } catch (\Throwable $ex) {
                Log::warning('[settle-alert] mail failed: '.$ex->getMessage());
            }
        }

        $hook = config('settle.alert_webhook');
        if (is_string($hook) && $hook !== '') {
            try {
                Http::timeout(5)->post($hook, ['text' => mb_substr($body, 0, 3500)]);
                $channels[] = 'webhook';
            } catch (\Throwable $ex) {
                Log::warning('[settle-alert] webhook failed: '.$ex->getMessage());
            }
        }

        foreach ($fresh as $e) {
            try {
                SettleAlertLog::query()->create([
                    'settle_date' => $date,
                    'task_key' => $e['task'],
                    'type' => $e['type'],
                    'severity' => 'error',
                    'fingerprint' => $e['fingerprint'],
                    'title' => mb_substr((string) $e['title'], 0, 255),
                    'message' => (string) $e['message'],
                    'channels' => $channels,
                    'sent_at' => $now,
                ]);
            } catch (\Throwable $ex) {
                Log::warning('[settle-alert] could not store alert log: '.$ex->getMessage());
            }
        }

        return $fresh;
    }

    /** @param  array<string, mixed>  $e */
    public static function fingerprint(string $date, array $e): string
    {
        $codes = (array) ($e['codes'] ?? []);
        sort($codes);

        return md5($e['type'].'|'.$date.'|'.$e['task'].'|'.implode(',', $codes));
    }
}
