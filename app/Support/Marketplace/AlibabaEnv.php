<?php

namespace App\Support\Marketplace;

use Illuminate\Support\Facades\Config;

/**
 * Laravel keeps the first .env value. An empty ALIBABA_ACCESS_TOKEN above a
 * later token leaves Marketplace Manager, Missing Listing, and Map Issues offline.
 */
class AlibabaEnv
{
    /**
     * @var array<string, string>
     */
    private const KEYS = [
        'ALIBABA_ACCESS_TOKEN' => 'services.alibaba.access_token',
        'ALIBABA_REFRESH_TOKEN' => 'services.alibaba.refresh_token',
        'ALIBABA_AUTH_BASE' => 'services.alibaba.auth_base',
    ];

    public static function apply(): void
    {
        foreach (self::KEYS as $envKey => $configKey) {
            $last = self::lastNonEmpty($envKey);
            if ($last === null) {
                continue;
            }
            if ((string) config($configKey) === $last) {
                continue;
            }
            Config::set($configKey, $last);
        }
    }

    public static function lastNonEmpty(string $key): ?string
    {
        $path = base_path('.env');
        if (! is_file($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if (! is_array($lines)) {
            return null;
        }

        $last = null;
        $prefix = $key.'=';
        foreach ($lines as $line) {
            $trim = ltrim($line);
            if ($trim === '' || str_starts_with($trim, '#')) {
                continue;
            }
            if (! str_starts_with($trim, $prefix)) {
                continue;
            }
            $value = trim(substr($trim, strlen($prefix)));
            if ($value !== '' && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
                $value = substr($value, 1, -1);
            }
            if ($value !== '') {
                $last = $value;
            }
        }

        return $last;
    }
}
