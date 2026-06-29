<?php

namespace App\Services\Support;

use App\Models\ChannelMaster;
use App\Models\ChannelMasterCalculatedData;
use Illuminate\Support\Facades\Schema;

/**
 * Static credential checks for marketplace push UIs (no live token validation).
 * Channel slugs align with /all-marketplace-master (channel_master_calculated_data).
 */
class MarketplaceApiConfigService
{
    /** @var array<string, string> Master UI aliases → API credential key */
    private const ALIASES = [
        'ebay1' => 'ebay',
        'shopify' => 'shopify_main',
        'amazon_fba' => 'amazon',
        'amazonfba' => 'amazon',
        'tiktok2' => 'tiktok',
        'tiktokshop' => 'tiktok',
        'tiktokshop2' => 'tiktok',
        'shopify_b5c' => 'shopify_b5c',
        'business5core' => 'shopify_b5c',
        'shopify_b2b' => 'shopify_b2b',
        'shopifyb2c' => 'shopify_main',
        'shopifyb2b' => 'shopify_b2b',
        'purchasing_power' => 'purchasingpower',
        'instagram_shop' => 'instagramshop',
        'mercari_wship' => 'mercariwship',
        'mercari_woship' => 'mercariwoship',
        'fb_marketplace' => 'fbmarketplace',
        'fb_shop' => 'fbshop',
        'macys' => 'macy',
        'bestbuyusa' => 'bestbuy',
        'pls' => 'shopify_pls',
        'newegg' => 'newegg',
        'topdawg' => 'topdawg',
    ];

    /**
     * Normalized channel slug (from channel_master) → API credential key.
     * null = no listing push API for this channel (sheet/manual only).
     *
     * @var array<string, string|null>
     */
    private const CHANNEL_SLUG_TO_API = [
        'amazon' => 'amazon',
        'amazonfba' => 'amazon',
        'ebay' => 'ebay',
        'ebaytwo' => 'ebay2',
        'ebaythree' => 'ebay3',
        'macys' => 'macy',
        'tiendamia' => null,
        'tendamia' => null,
        'bestbuyusa' => 'bestbuy',
        'newegg' => 'newegg',
        'reverb' => 'reverb',
        'doba' => 'doba',
        'temu' => 'temu',
        'temu2' => 'temu2',
        'walmart' => 'walmart',
        'pls' => 'shopify_pls',
        'wayfair' => 'wayfair',
        'faire' => 'faire',
        'purchasingpower' => null,
        'shein' => 'shein',
        'tiktokshop' => 'tiktok',
        'tiktokshop2' => 'tiktok',
        'tiktok2' => 'tiktok',
        'depop' => null,
        'instagramshop' => null,
        'aliexpress' => 'aliexpress',
        'mercariwship' => null,
        'mercariwoship' => null,
        'fbmarketplace' => null,
        'fbshop' => null,
        'business5core' => 'shopify_b5c',
        'topdawg' => 'topdawg',
        'shopifyb2c' => 'shopify_main',
        'shopifyb2b' => null,
        'shopify_b2b' => null,
        'shopify' => 'shopify_main',
        'amazon_fba' => 'amazon',
    ];

    /** @var list<string> Keys used by Bullet / Description / Image / Video / Title masters */
    private const MASTER_MARKETPLACE_KEYS = [
        'ebay', 'ebay2', 'ebay3', 'macy', 'amazon', 'amazon_fba', 'temu', 'temu2',
        'reverb', 'wayfair', 'bestbuy', 'walmart', 'doba', 'faire',
        'shein', 'aliexpress', 'shopify_main', 'shopify_pls', 'shopify_b5c', 'shopify_b2b',
        'tiktok', 'tiktok2', 'newegg', 'topdawg',
        'tiendamia', 'purchasing_power', 'depop', 'instagram_shop',
        'mercari_wship', 'mercari_woship', 'fb_marketplace', 'fb_shop',
    ];

    public function normalizeChannelKey(?string $name): string
    {
        $key = strtolower(trim((string) $name));
        $key = preg_replace('/\s+/', ' ', $key) ?? $key;

        $displayAliases = [
            'tiktok shop 2' => 'tiktok 2',
            'depop.com' => 'depop',
        ];
        $key = $displayAliases[$key] ?? $key;

        return strtolower(str_replace([' ', '-', '&', '/'], '', $key));
    }

    public function resolveKey(string $marketplace): string
    {
        $raw = strtolower(trim($marketplace));
        if (isset(self::ALIASES[$raw])) {
            return self::ALIASES[$raw];
        }

        $slug = $this->normalizeChannelKey($marketplace);
        $apiKey = self::CHANNEL_SLUG_TO_API[$slug] ?? null;
        if ($apiKey !== null) {
            return $apiKey;
        }

        return $slug;
    }

    public function isConfigured(string $marketplace): bool
    {
        $slug = $this->normalizeChannelKey($marketplace);
        $raw = strtolower(trim($marketplace));

        if (isset(self::ALIASES[$raw])) {
            return $this->isApiKeyConfigured(self::ALIASES[$raw]);
        }

        if (array_key_exists($slug, self::CHANNEL_SLUG_TO_API)) {
            $apiKey = self::CHANNEL_SLUG_TO_API[$slug];

            return $apiKey !== null && $this->isApiKeyConfigured($apiKey);
        }

        return $this->isApiKeyConfigured($this->resolveKey($marketplace));
    }

    /**
     * @param  list<string>|null  $marketplaces
     * @return array<string, bool>
     */
    public function configuredMap(?array $marketplaces = null): array
    {
        if ($marketplaces !== null) {
            $map = [];
            foreach ($marketplaces as $mp) {
                $map[$mp] = $this->isConfigured($mp);
            }

            return $map;
        }

        $map = [];

        foreach (self::MASTER_MARKETPLACE_KEYS as $mp) {
            $map[$mp] = $this->isConfigured($mp);
        }

        foreach (array_keys(self::CHANNEL_SLUG_TO_API) as $slug) {
            $map[$slug] = $this->isConfigured($slug);
        }

        foreach ($this->discoverChannelNames() as $name) {
            $slug = $this->normalizeChannelKey($name);
            $configured = $this->isConfigured($slug);
            $map[$slug] = $configured;
            $trimmed = trim((string) $name);
            if ($trimmed !== '') {
                $map[$trimmed] = $configured;
            }
        }

        foreach (self::ALIASES as $alias => $canonical) {
            $map[$alias] = $this->isConfigured($alias);
            if (isset($map[$canonical])) {
                $map[$alias] = $map[$canonical];
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function discoverChannelNames(): array
    {
        try {
            if (Schema::hasTable('channel_master_calculated_data')) {
                return ChannelMasterCalculatedData::query()
                    ->distinct()
                    ->orderBy('channel')
                    ->pluck('channel')
                    ->filter()
                    ->values()
                    ->all();
            }

            if (Schema::hasTable('channel_master')) {
                return ChannelMaster::query()
                    ->distinct()
                    ->orderBy('channel')
                    ->pluck('channel')
                    ->filter()
                    ->values()
                    ->all();
            }
        } catch (\Throwable) {
            // Local / partial installs may not have channel tables.
        }

        return [];
    }

    private function isApiKeyConfigured(string $key): bool
    {
        return match ($key) {
            'ebay' => $this->ebayConfigured('ebay'),
            'ebay2' => $this->ebayConfigured('ebay2'),
            'ebay3' => $this->ebayConfigured('ebay3'),
            'amazon' => $this->filledAll([
                'services.amazon_sp.client_id',
                'services.amazon_sp.client_secret',
                'services.amazon_sp.refresh_token',
                'services.amazon_sp.seller_id',
            ]),
            'temu', 'temu2' => $this->filledAll([
                'services.temu.app_key',
                'services.temu.secret_key',
                'services.temu.access_token',
            ]),
            'macy', 'bestbuy' => $this->filledAll([
                'services.macy.client_id',
                'services.macy.client_secret',
            ]),
            'reverb' => $this->reverbConfigured(),
            'wayfair' => $this->filledAll([
                'services.wayfair.client_id',
                'services.wayfair.client_secret',
            ]),
            'walmart' => $this->filledAll([
                'services.walmart.client_id',
                'services.walmart.client_secret',
            ]),
            'doba' => $this->filledAll([
                'services.doba.app_key',
                'services.doba.private_key',
            ]),
            'faire' => $this->faireConfigured(),
            'shein' => $this->sheinConfigured(),
            'aliexpress' => $this->filledAll([
                'services.aliexpress.app_key',
                'services.aliexpress.app_secret',
                'services.aliexpress.access_token',
            ]),
            'shopify_main' => $this->shopifyMainConfigured(),
            'shopify_pls' => $this->shopifyPlsConfigured(),
            'shopify_b5c' => $this->shopifyB5cConfigured(),
            'tiktok' => $this->filledAll([
                'services.tiktok.app_key',
                'services.tiktok.app_secret',
                'services.tiktok.access_token',
            ]),
            'newegg' => $this->filledAll([
                'services.newegg.api_key',
                'services.newegg.secret_key',
                'services.newegg.seller_id',
            ]),
            'topdawg' => $this->filled('services.topdawg.token'),
            'shopify_b2b' => $this->shopifyB2bConfigured(),
            default => false,
        };
    }

    private function filled(string $configKey): bool
    {
        $value = config($configKey);

        if (is_string($value)) {
            return trim($value) !== '';
        }

        return ! empty($value);
    }

    /**
     * @param  list<string>  $keys
     */
    private function filledAll(array $keys): bool
    {
        foreach ($keys as $key) {
            if (! $this->filled($key)) {
                return false;
            }
        }

        return true;
    }

    private function ebayConfigured(string $account): bool
    {
        $prefix = match ($account) {
            'ebay' => 'services.ebay',
            'ebay2' => 'services.ebay2',
            'ebay3' => 'services.ebay3',
            default => null,
        };

        if ($prefix === null) {
            return false;
        }

        return $this->filledAll([
            "{$prefix}.app_id",
            "{$prefix}.cert_id",
            "{$prefix}.dev_id",
            "{$prefix}.refresh_token",
        ]);
    }

    private function reverbConfigured(): bool
    {
        $clientId = trim((string) config('services.reverb.client_id', ''));
        $clientSecret = trim((string) config('services.reverb.client_secret', ''));
        $staticToken = trim((string) config('services.reverb.token', ''));

        if ($clientId !== '' && $clientSecret !== '') {
            return true;
        }

        return $staticToken !== '';
    }

    private function faireConfigured(): bool
    {
        if ($this->filledAll(['services.faire.app_id', 'services.faire.app_secret'])) {
            return true;
        }

        foreach (['services.faire.bearer_token', 'services.faire.access_token', 'services.faire.token'] as $key) {
            if ($this->filled($key)) {
                return true;
            }
        }

        return false;
    }

    private function sheinConfigured(): bool
    {
        $openKeyId = config('services.shein.open_key_id') ?: config('services.shein.app_id');
        $secretKey = config('services.shein.secret_key')
            ?: config('services.shein.app_secret')
            ?: config('services.shein.app_s');

        return trim((string) $openKeyId) !== '' && trim((string) $secretKey) !== '';
    }

    private function shopifyMainConfigured(): bool
    {
        $domain = config('services.shopify.store_url') ?: config('services.shopify.domain');
        $token = config('services.shopify.access_token') ?: config('services.shopify.password');

        return trim((string) $domain) !== '' && trim((string) $token) !== '';
    }

    private function shopifyPlsConfigured(): bool
    {
        $domain = config('services.prolightsounds.domain') ?? config('services.prolightsounds.store_url');
        if (trim((string) $domain) === '') {
            return false;
        }

        if ($this->filled('services.prolightsounds.client_id') && $this->filled('services.prolightsounds.client_secret')) {
            return true;
        }

        return $this->filled('services.prolightsounds.password')
            || $this->filled('services.prolightsounds.access_token');
    }

    private function shopifyB5cConfigured(): bool
    {
        $domain = config('services.shopify_b5c.domain');
        $token = config('services.shopify_b5c.access_token') ?: config('services.shopify_b5c.password');

        return trim((string) $domain) !== '' && trim((string) $token) !== '';
    }

    private function shopifyB2bConfigured(): bool
    {
        $domain = config('services.shopify_b2b.domain');
        $token = config('services.shopify_b2b.access_token') ?: config('services.shopify_b2b.password');

        return trim((string) $domain) !== '' && trim((string) $token) !== '';
    }
}
