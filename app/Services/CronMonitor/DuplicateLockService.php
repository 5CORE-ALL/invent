<?php

namespace App\Services\CronMonitor;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class DuplicateLockService
{
    /** @var array<string, Lock> */
    protected array $held = [];

    public function keyFor(string $commandOrJob): string
    {
        $prefix = config('cron-monitor.locks.prefix', 'cron-monitor:lock:');

        return $prefix . md5($commandOrJob);
    }

    /**
     * @throws RuntimeException when another instance holds the lock
     */
    public function acquire(string $commandOrJob, ?int $ttl = null): string
    {
        if (! config('cron-monitor.locks.enabled', true)) {
            return '';
        }

        $ttl ??= (int) config('cron-monitor.locks.ttl_seconds', 7200);
        $key = $this->keyFor($commandOrJob);
        $lock = Cache::lock($key, $ttl);

        if (! $lock->get()) {
            throw new RuntimeException("Duplicate cron blocked: [{$commandOrJob}] is already running.");
        }

        $this->held[$key] = $lock;

        return $key;
    }

    public function release(?string $key): void
    {
        if (! $key || ! isset($this->held[$key])) {
            return;
        }

        try {
            $this->held[$key]->release();
        } catch (\Throwable) {
            // lock may have expired
        }

        unset($this->held[$key]);
    }

    public function forceRelease(string $commandOrJob): bool
    {
        $key = $this->keyFor($commandOrJob);

        try {
            Cache::lock($key)->forceRelease();
            unset($this->held[$key]);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isLocked(string $commandOrJob): bool
    {
        $key = $this->keyFor($commandOrJob);
        $lock = Cache::lock($key, 1);

        if ($lock->get()) {
            $lock->release();

            return false;
        }

        return true;
    }
}
