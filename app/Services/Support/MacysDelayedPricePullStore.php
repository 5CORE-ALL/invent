<?php

namespace App\Services\Support;

use Illuminate\Support\Carbon;

/**
 * After a Macy S PRC push, schedule one full listed-price pull 10 minutes later.
 */
class MacysDelayedPricePullStore
{
    public const DELAY_MINUTES = 10;

    /**
     * @param  list<string>  $skus
     * @return array<string, mixed>
     */
    public static function schedule(array $skus = []): array
    {
        $state = self::load();
        $now = now();
        $seen = [];
        foreach (array_merge($state['skus'] ?? [], $skus) as $sku) {
            $key = strtoupper(trim((string) $sku));
            if ($key !== '') {
                $seen[$key] = true;
            }
        }

        $state = [
            'due_at' => $now->copy()->addMinutes(self::DELAY_MINUTES)->toDateTimeString(),
            'scheduled_at' => $now->toDateTimeString(),
            'full' => true,
            'skus' => array_keys($seen),
        ];
        self::save($state);

        return $state;
    }

    /**
     * @return array<string, mixed>
     */
    public static function load(): array
    {
        $path = self::path();
        if (! is_file($path)) {
            return self::defaultState();
        }
        $json = file_get_contents($path);
        $state = is_string($json) ? json_decode($json, true) : null;

        return is_array($state) ? array_merge(self::defaultState(), $state) : self::defaultState();
    }

    public static function due(?Carbon $at = null): bool
    {
        $dueAt = self::load()['due_at'] ?? null;
        if (! is_string($dueAt) || $dueAt === '') {
            return false;
        }
        try {
            return Carbon::parse($dueAt)->lte($at ?? now());
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function takeDue(): ?array
    {
        if (! self::due()) {
            return null;
        }
        $state = self::load();
        self::clear();

        return $state;
    }

    public static function clear(): void
    {
        $path = self::path();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function save(array $state): void
    {
        $path = self::path();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * @return array{due_at:?string,scheduled_at:?string,full:bool,skus:list<string>}
     */
    private static function defaultState(): array
    {
        return [
            'due_at' => null,
            'scheduled_at' => null,
            'full' => true,
            'skus' => [],
        ];
    }

    private static function path(): string
    {
        return storage_path('app/macys-push-sprice/delayed-pull.json');
    }
}
