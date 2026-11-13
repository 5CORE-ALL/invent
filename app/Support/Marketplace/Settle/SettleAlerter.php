<?php

namespace App\Support\Marketplace\Settle;

use App\Models\SettleAlertLog;
use App\Support\Marketplace\YesterdaySettleLedger as Ledger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends settle alerts to the log (always) and, when configured, by e-mail (SETTLE_ALERT_EMAILS) and
 * webhook (SETTLE_ALERT_WEBHOOK). Alerts never throw into the caller.
 *
 * Delivery is tracked PER CHANNEL. Every attempt is one settle_alert_logs row holding the channels
 * that were really DELIVERED (`channels`) and those that were attempted but failed
 * (`failed_channels`, e.g. "webhook: HTTP 500"). An alert is suppressed (settle.alert_repeat_hours)
 * only for channels that already delivered it, so:
 *  - an HTTP 4xx/5xx webhook answer or a mail error is never recorded as delivered and is retried
 *    (at most every alert_retry_minutes, at most alert_max_delivery_attempts times);
 *  - a log-only alert (no mail / webhook configured at the time) does not block a later mail /
 *    webhook delivery once one is configured.
 */
final class SettleAlerter
{
    /**
     * @param  list<array<string, mixed>>  $events  from SettleVerifier::events()
     * @return list<array<string, mixed>> the events that were newly delivered on at least one channel
     */
    public function dispatch(string $date, array $events, ?Carbon $now = null): array
    {
        if ($events === []) {
            return [];
        }
        $now = ($now ?? Carbon::now(Ledger::TZ))->copy();
        $hours = max(1, (int) config('settle.alert_repeat_hours', 12));
        $retryMinutes = max(1, (int) config('settle.alert_retry_minutes', 10));
        $maxAttempts = max(1, (int) config('settle.alert_max_delivery_attempts', 6));

        $emails = (array) config('settle.alert_emails', []);
        $hook = config('settle.alert_webhook');
        $hook = is_string($hook) && $hook !== '' ? $hook : null;
        $required = array_values(array_filter(['log', $emails !== [] ? 'mail' : null, $hook !== null ? 'webhook' : null]));

        // Which channels still have to deliver each event?
        $todo = [];
        foreach ($events as $e) {
            $fp = self::fingerprint($date, $e);
            $e['fingerprint'] = $fp;
            $delivered = [];
            $failedAttempts = 0;
            $lastFailure = null;
            try {
                $rows = SettleAlertLog::query()
                    ->where('fingerprint', $fp)
                    ->where('created_at', '>=', $now->copy()->subHours($hours))
                    ->get(['channels', 'failed_channels', 'created_at']);
                foreach ($rows as $r) {
                    $delivered = array_merge($delivered, (array) ($r->channels ?? []));
                    if (! empty($r->failed_channels)) {
                        $failedAttempts++;
                        if ($lastFailure === null || $r->created_at->gt($lastFailure)) {
                            $lastFailure = $r->created_at;
                        }
                    }
                }
            } catch (\Throwable $ex) {
                Log::warning('[settle-alert] alert log unavailable: '.$ex->getMessage());
            }

            $pending = array_values(array_diff($required, $delivered));
            if ($pending === []) {
                continue; // every configured channel already has it
            }
            if (! in_array('log', $pending, true)) {
                // Only mail / webhook are left (they failed earlier, or were configured later): retry
                // them at a calm pace.
                if ($lastFailure !== null && $lastFailure->copy()->addMinutes($retryMinutes)->gt($now)) {
                    continue;
                }
                if ($failedAttempts >= $maxAttempts) {
                    continue;
                }
            }
            $todo[] = ['event' => $e, 'pending' => $pending];
        }
        if ($todo === []) {
            return [];
        }

        // One message per channel, with the events that still need that channel.
        $outcome = []; // channel => ['ok' => bool, 'reason' => string]
        foreach (['log', 'mail', 'webhook'] as $channel) {
            $batch = array_values(array_filter($todo, fn ($t) => in_array($channel, $t['pending'], true)));
            if ($batch === []) {
                continue;
            }
            $list = array_map(fn ($t) => $t['event'], $batch);
            $subject = sprintf('[Yesterday settle %s] %d alert(s): %s', $date, count($list), implode(', ', array_unique(array_map(fn ($e) => SettleCatalog::marketplace((string) $e['task']), $list))));
            $body = $subject."\n\n".implode("\n\n", array_map(fn ($e) => '['.$e['type'].'] '.$e['title']."\n".$e['message'], $list));
            $outcome[$channel] = $this->send($channel, $subject, $body, $emails, $hook);
        }

        $sent = [];
        foreach ($todo as $t) {
            $delivered = [];
            $failed = [];
            foreach ($t['pending'] as $channel) {
                if (! isset($outcome[$channel])) {
                    continue;
                }
                $outcome[$channel]['ok'] ? $delivered[] = $channel : $failed[] = $channel.': '.$outcome[$channel]['reason'];
            }
            $e = $t['event'];
            try {
                SettleAlertLog::query()->create([
                    'settle_date' => $date,
                    'task_key' => $e['task'],
                    'type' => $e['type'],
                    'severity' => 'error',
                    'fingerprint' => $e['fingerprint'],
                    'title' => mb_substr((string) $e['title'], 0, 255),
                    'message' => (string) $e['message'],
                    'channels' => $delivered,
                    'failed_channels' => $failed === [] ? null : $failed,
                    'sent_at' => $delivered === [] ? null : $now,
                    'created_at' => $now,
                ]);
            } catch (\Throwable $ex) {
                Log::warning('[settle-alert] could not store alert log: '.$ex->getMessage());
            }
            if ($delivered !== []) {
                $sent[] = $e;
            }
        }

        return $sent;
    }

    /**
     * @param  list<string>  $emails
     * @return array{ok: bool, reason: string}
     */
    private function send(string $channel, string $subject, string $body, array $emails, ?string $hook): array
    {
        try {
            if ($channel === 'log') {
                Log::error('[settle-alert] '.$body);

                return ['ok' => true, 'reason' => ''];
            }
            if ($channel === 'mail') {
                Mail::raw($body, function ($m) use ($emails, $subject) {
                    $m->to($emails)->subject($subject);
                });

                return ['ok' => true, 'reason' => ''];
            }
            $response = Http::timeout(5)->post((string) $hook, ['text' => mb_substr($body, 0, 3500)]);
            if (! $response->successful()) {
                Log::warning('[settle-alert] webhook answered HTTP '.$response->status());

                return ['ok' => false, 'reason' => 'HTTP '.$response->status()];
            }

            return ['ok' => true, 'reason' => ''];
        } catch (\Throwable $ex) {
            Log::warning("[settle-alert] {$channel} failed: ".$ex->getMessage());

            return ['ok' => false, 'reason' => mb_substr(class_basename($ex).': '.$ex->getMessage(), 0, 160)];
        }
    }

    /** @param  array<string, mixed>  $e */
    public static function fingerprint(string $date, array $e): string
    {
        $codes = (array) ($e['codes'] ?? []);
        sort($codes);

        return md5($e['type'].'|'.$date.'|'.$e['task'].'|'.implode(',', $codes));
    }
}
