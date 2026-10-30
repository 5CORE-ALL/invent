<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Database lease for a scheduled job that must never run twice at once.
 *
 * - acquire() is one atomic UPDATE: it succeeds only when nobody holds the lease or the holder's lease expired.
 * - The holder renews with beat() (throttled to every `heartbeat` seconds, called between work steps).
 * - Every write is guarded by the owner token, so an old / interrupted process can neither renew,
 *   release nor overwrite a lease that another run has taken over.
 * - A holder that dies (SIGKILL, restart, crash) stops beating; the lease expires after `ttl` seconds
 *   and the next scheduled run takes it over on its own. Nothing needs to be unlocked by hand.
 *
 * The token is also the execution UUID used in every log line of the run.
 */
final class SchedulerLease
{
    private const TABLE = 'scheduler_leases';

    private bool $released = false;

    private bool $lost = false;

    private int $lastBeat;

    private bool $armed = false;

    /** pid of the forked heartbeat process, if any (see startBackgroundHeartbeat) */
    private ?int $beater = null;

    /** @var array<string, self> leases held by this process, renewed together by pulse() */
    private static array $active = [];

    /** @param  array<string, mixed>|null  $recovered  The expired holder this run took over from. */
    private function __construct(
        public readonly string $name,
        public readonly string $token,
        private readonly int $ttl,
        private readonly int $heartbeat,
        public readonly ?array $recovered,
        private readonly float $startedAt,
    ) {
        $this->lastBeat = time();
    }

    /**
     * @return self|null  null when a live run still holds the lease
     */
    public static function acquire(string $name, ?int $ttl = null, ?int $heartbeat = null): ?self
    {
        $ttl = max(5, $ttl ?? (int) config('cron-monitor.leases.ttl_seconds', 300));
        $heartbeat = max(1, $heartbeat ?? (int) config('cron-monitor.leases.heartbeat_seconds', 30));
        $now = now();
        $nowStr = $now->toDateTimeString();

        DB::table(self::TABLE)->insertOrIgnore(['name' => $name, 'created_at' => $nowStr, 'updated_at' => $nowStr]);
        $before = DB::table(self::TABLE)->where('name', $name)->first();

        $token = (string) Str::uuid();
        $won = DB::table(self::TABLE)
            ->where('name', $name)
            ->where(function ($q) use ($nowStr) {
                $q->whereNull('owner_token')->orWhereNull('expires_at')->orWhere('expires_at', '<=', $nowStr);
            })
            ->update([
                'owner_token' => $token,
                'owner_pid' => getmypid() ?: null,
                'owner_host' => gethostname() ?: php_uname('n'),
                'acquired_at' => $nowStr,
                'heartbeat_at' => $nowStr,
                'expires_at' => $now->copy()->addSeconds($ttl)->toDateTimeString(),
                'updated_at' => $nowStr,
            ]);

        if ($won !== 1) {
            $holder = DB::table(self::TABLE)->where('name', $name)->first();
            Log::info('[Lease] skipped: another run holds the lease', [
                'lease' => $name,
                'holder_execution' => $holder->owner_token ?? null,
                'holder_pid' => $holder->owner_pid ?? null,
                'holder_heartbeat_at' => $holder->heartbeat_at ?? null,
                'holder_expires_at' => $holder->expires_at ?? null,
            ]);

            return null;
        }

        $recovered = null;
        if ($before !== null && $before->owner_token !== null) {
            $recovered = [
                'execution' => $before->owner_token,
                'pid' => $before->owner_pid,
                'host' => $before->owner_host,
                'acquired_at' => $before->acquired_at,
                'last_heartbeat_at' => $before->heartbeat_at,
                'expired_at' => $before->expires_at,
            ];
            Log::warning('[Lease] stale lease recovered: previous holder stopped heart-beating', ['lease' => $name, 'execution' => $token] + $recovered);
        }

        Log::info('[Lease] acquired', ['lease' => $name, 'execution' => $token, 'ttl' => $ttl, 'heartbeat' => $heartbeat, 'pid' => getmypid()]);

        $lease = new self($name, $token, $ttl, $heartbeat, $recovered, microtime(true));
        self::$active[$token] = $lease;

        return $lease;
    }

    /** False before `php artisan migrate` has created the table (callers decide whether to run unlocked). */
    public static function available(): bool
    {
        try {
            return Schema::hasTable(self::TABLE);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Renew every lease this process holds (throttled). Call it between slow steps: it is what keeps a
     * long run, or a push that holds a campaign lease, from ever expiring while it is really working.
     */
    public static function pulse(): void
    {
        foreach (self::$active as $lease) {
            try {
                $lease->beat();
            } catch (Throwable $e) {
                // A database hiccup must never break the push that is calling this; the next pulse retries.
                Log::warning('[Lease] pulse failed', ['lease' => $lease->name, 'execution' => $lease->token, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Renew the lease (throttled). False means another run owns it now: stop pushing and exit.
     */
    public function beat(bool $force = false): bool
    {
        if ($this->released || $this->lost) {
            return false;
        }
        if (! $force && (time() - $this->lastBeat) < $this->heartbeat) {
            return true;
        }

        $now = now();
        $n = DB::table(self::TABLE)
            ->where('name', $this->name)
            ->where('owner_token', $this->token)
            ->update([
                'heartbeat_at' => $now->toDateTimeString(),
                'expires_at' => $now->copy()->addSeconds($this->ttl)->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ]);

        // MySQL reports 0 changed rows when the same second is written twice: confirm before declaring it lost.
        if ($n === 0 && ! $this->owns()) {
            $this->lost = true;
            Log::error('[Lease] lost: another run took over, this execution must stop', ['lease' => $this->name, 'execution' => $this->token]);

            return false;
        }

        $this->lastBeat = time();
        Log::info('[Lease] heartbeat', ['lease' => $this->name, 'execution' => $this->token, 'elapsed_s' => $this->elapsed()]);

        return true;
    }

    public function owns(): bool
    {
        $row = DB::table(self::TABLE)->where('name', $this->name)->first(['owner_token']);

        return $row !== null && $row->owner_token === $this->token;
    }

    public function lost(): bool
    {
        return $this->lost;
    }

    /**
     * Release only if this execution still owns the lease. A stale process can never free someone else's lease.
     */
    public function release(string $status = 'success'): void
    {
        if ($this->released) {
            return;
        }
        $this->released = true;
        unset(self::$active[$this->token]);
        $this->stopBackgroundHeartbeat();

        try {
            $now = now()->toDateTimeString();
            $n = DB::table(self::TABLE)
                ->where('name', $this->name)
                ->where('owner_token', $this->token)
                ->update([
                    'owner_token' => null,
                    'owner_pid' => null,
                    'expires_at' => null,
                    'last_finished_at' => $now,
                    'last_status' => $status,
                    'updated_at' => $now,
                ]);
            Log::info($n === 1 ? '[Lease] released' : '[Lease] not released: no longer the owner', [
                'lease' => $this->name, 'execution' => $this->token, 'status' => $status, 'duration_s' => $this->elapsed(),
            ]);
        } catch (Throwable $e) {
            // The lease simply expires on its own; the next run recovers it.
            Log::warning('[Lease] release failed, it will expire on its own', ['lease' => $this->name, 'execution' => $this->token, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Keep the lease alive while this process is blocked inside a long call (a slow Google Ads request).
     *
     * A separate heartbeat process renews the lease with the same owner token every `heartbeat` seconds and
     * exits on its own as soon as: this process ends or dies, the lease is released or taken over, or
     * `max_run_seconds` is reached (so a hung run cannot hold the lease forever). It needs pcntl and a shared
     * MySQL/MariaDB database; otherwise it returns false and the run only renews between its own steps.
     * The caller must not be inside a DB transaction (the connection is re-opened after the fork).
     */
    public function startBackgroundHeartbeat(?int $maxSeconds = null): bool
    {
        if ($this->beater !== null || $this->released
            || ! function_exists('pcntl_fork') || ! function_exists('posix_getppid') || ! function_exists('posix_kill')) {
            return false;
        }
        try {
            if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        $maxSeconds ??= (int) config('cron-monitor.leases.max_run_seconds', 1800);
        $parent = getmypid();
        DB::disconnect(); // two processes must never share one MySQL socket

        $pid = pcntl_fork();
        if ($pid === -1) {
            Log::warning('[Lease] background heartbeat not started: fork failed', ['lease' => $this->name, 'execution' => $this->token]);

            return false;
        }
        if ($pid === 0) {
            try {
                $this->heartbeatLoop($parent, $maxSeconds);
            } catch (Throwable) {
                // nothing to do: the lease simply stops being renewed
            }
            // SIGKILL ourselves: no shutdown handlers (they would release the PARENT's lease) and no
            // destructors (a closing connection could drop the parent's session).
            posix_kill(getmypid(), SIGKILL);
            exit(0);
        }

        $this->beater = $pid;
        Log::info('[Lease] background heartbeat started', ['lease' => $this->name, 'execution' => $this->token, 'beater_pid' => $pid, 'max_run_seconds' => $maxSeconds]);

        return true;
    }

    private function heartbeatLoop(int $parent, int $maxSeconds): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, SIG_DFL);
            pcntl_signal(SIGINT, SIG_DFL);
        }
        DB::purge();
        $deadline = time() + $maxSeconds;
        $next = 0;

        while (true) {
            if (posix_getppid() !== $parent || ! posix_kill($parent, 0)) {
                return; // the run ended or died: stop renewing, the lease expires by itself
            }
            if (time() >= $deadline) {
                Log::error('[Lease] background heartbeat stopped: max run time reached, lease will expire', ['lease' => $this->name, 'execution' => $this->token]);

                return;
            }
            if (time() >= $next) {
                $now = now();
                $n = DB::table(self::TABLE)
                    ->where('name', $this->name)
                    ->where('owner_token', $this->token)
                    ->update([
                        'heartbeat_at' => $now->toDateTimeString(),
                        'expires_at' => $now->copy()->addSeconds($this->ttl)->toDateTimeString(),
                        'updated_at' => $now->toDateTimeString(),
                    ]);
                if ($n === 0 && ! $this->owns()) {
                    return; // released or taken over: never renew somebody else's lease
                }
                $next = time() + $this->heartbeat;
            }
            sleep(1);
        }
    }

    private function stopBackgroundHeartbeat(): void
    {
        if ($this->beater === null) {
            return;
        }
        $pid = $this->beater;
        $this->beater = null;
        @posix_kill($pid, SIGKILL);
        @pcntl_waitpid($pid, $status);
    }

    /**
     * Free the lease on fatal errors, exit() and SIGTERM / SIGINT (deploy, restart). SIGKILL cannot be caught:
     * that case is covered by the expiry above.
     */
    public function guardProcess(): void
    {
        if ($this->armed) {
            return;
        }
        $this->armed = true;

        register_shutdown_function(function (): void {
            if (! $this->released) {
                $this->release('interrupted');
            }
        });

        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            foreach ([SIGTERM => 143, SIGINT => 130] as $signal => $code) {
                pcntl_signal($signal, function () use ($code): void {
                    $this->release('interrupted');
                    exit($code);
                });
            }
        }
    }

    public function elapsed(): float
    {
        return round(microtime(true) - $this->startedAt, 2);
    }
}
