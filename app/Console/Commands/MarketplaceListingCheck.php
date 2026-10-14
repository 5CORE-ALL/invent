<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\MiraklMcmOfferIndex;
use App\Services\MarketplaceManager\MissingListingCatalogRefresh;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Re-read a marketplace's live catalog (Mirakl MCM offers, or TopDawg
 * SupplierProduct/list), recount Listed / Missing L from it, and print the
 * SKUs that are really not on the marketplace.
 */
class MarketplaceListingCheck extends Command
{
    protected $signature = 'marketplace:listing-check
        {channel=macy : macy | bestbuy | purchasingpower | topdawg | aliexpress | alibaba}
        {--limit=200 : Max missing SKUs to print}
        {--dump= : Comma-separated SKUs: print their raw Mirakl Connect product JSON}';

    protected $aliases = ['macy:listing-check'];

    /** @var array<string, array<string, mixed>> */
    private array $dumpRows = [];

    private string $cfgKey = 'macy';

    private string $connectCode = 'macys';

    protected $description = 'Recount Missing L from the live marketplace catalog and print the missing SKUs.';

    public function handle(MiraklMcmOfferIndex $offerIndex): int
    {
        $channel = strtolower(trim((string) $this->argument('channel')));
        if ($channel === 'topdawg') {
            return $this->checkTopDawg();
        }
        if ($channel === 'aliexpress') {
            return $this->checkAliExpress();
        }
        if ($channel === 'alibaba') {
            return $this->checkAlibaba();
        }

        $cfgKey = MiraklMcmOfferIndex::configKey($channel);
        if ($cfgKey === null) {
            $this->error('Unknown channel. Use macy, bestbuy, purchasingpower, topdawg, aliexpress or alibaba.');

            return self::FAILURE;
        }
        $this->cfgKey = $cfgKey;
        [$listingKey, $this->connectCode, $label] = match ($cfgKey) {
            'bestbuy' => ['bestbuyusa', 'bestbuyusa', 'Best Buy'],
            'purchasingpower' => ['purchasingpower', 'purchasingpower', 'Purchasing Power'],
            default => ['macys', 'macys', "Macy's"],
        };

        $this->info("Reading all {$label} MCM offers (OF21, inactive included)...");
        $index = $offerIndex->refresh($cfgKey);
        if ($index === null) {
            $this->warn("Could not read {$label} MCM offers: ".($offerIndex->lastError ?? 'unknown error'));
            $index = $offerIndex->get($cfgKey, false);
            if ($index === null) {
                $this->warn('No saved offer list. Counting from Mirakl Connect products only.');
                $index = ['fetched_at' => '', 'offers' => []];
            } else {
                $this->warn('Using the last saved offer list from '.$index['fetched_at'].'.');
            }
        }

        $offers = $index['offers'];
        $active = count(array_filter($offers, static fn ($o) => ! empty($o['active'])));
        $this->line(sprintf('Offers on %s: %d (active %d, inactive %d)', $label, count($offers), $active, count($offers) - $active));

        $connectService = app(\App\Services\MarketplaceManager\MiraklConnectProductIndex::class);
        $connectIndex = $connectService->refresh($cfgKey);
        if ($connectIndex === null) {
            $this->warn('Could not read Mirakl Connect products: '.($connectService->lastError ?? 'unknown error'));
        } else {
            $this->line('Mirakl Connect products: '.$connectIndex['count'].' (created on '.$label.', not Missing L)');
        }

        $counts = ListingChannelCounts::refreshChannelOnMissingListingPage($listingKey);
        $this->line(sprintf('REQ %d | NRL %d | Listed %d | Missing L %d', $counts['REQ'], $counts['NRL'], $counts['Listed'], $counts['Pending']));

        $missing = AutomatedListingPage::rows($listingKey)
            ->filter(fn ($r) => (int) ($r->INV ?? 0) > 0 && ($r->nr_req ?? '') === 'REQ' && ($r->listed ?? '') === 'Pending')
            ->values();

        $compactOffers = [];
        foreach ($offers as $o) {
            foreach ([(string) $o['shop_sku'], (string) $o['product_sku']] as $s) {
                $c = ListingCountsEngine::skuLookupKeys($s);
                if ($s !== '' && $c !== []) {
                    $compactOffers[end($c)] = $o['shop_sku'];
                }
            }
        }

        $missingSkus = $missing->map(fn ($r) => (string) $r->sku)->all();
        $upcs = $this->productMasterUpcs($missingSkus);
        $catalogByUpc = $this->mcmCatalogByUpc(array_values(array_unique(array_filter($upcs))));
        $connect = $this->connectProducts();

        $this->line('');
        $this->line(sprintf(
            'Catalog checks: MCM catalog by UPC (P31) %s | Mirakl Connect %s products %s',
            $catalogByUpc === null ? 'FAILED' : 'ok',
            $this->connectCode,
            $connect === null ? 'FAILED' : count($connect).' read'
        ));

        foreach ($this->dumpSkus() as $dumpSku) {
            $this->line('');
            $this->info('Connect product JSON for '.$dumpSku.':');
            $this->line(isset($this->dumpRows[$dumpSku])
                ? (string) json_encode($this->dumpRows[$dumpSku], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : '  not found in Connect');
        }

        $this->line('');
        $this->info('Not an offer on '.$label.' ('.$missing->count().'):');
        foreach ($missing->take((int) $this->option('limit')) as $row) {
            $sku = (string) $row->sku;
            $upc = $upcs[strtoupper($sku)] ?? '';
            $where = [];
            if ($upc !== '' && isset($catalogByUpc[$upc])) {
                $where[] = 'MCM catalog: '.$catalogByUpc[$upc];
            }
            $connectHit = $connect[strtoupper(trim($sku))] ?? null;
            if ($connectHit !== null) {
                $where[] = 'Connect: '.$connectHit;
            }
            if ($where === []) {
                $where[] = $upc === '' ? 'no UPC in Product Master' : 'not in '.$label.' catalog';
            }
            $keys = ListingCountsEngine::skuLookupKeys($sku);
            $mine = (string) end($keys);
            $near = [];
            foreach ($compactOffers as $c => $shopSku) {
                if ($mine !== '' && (str_contains($c, $mine) || str_contains($mine, $c)) && strlen($c) >= 4) {
                    $near[] = $shopSku;
                    if (count($near) >= 3) {
                        break;
                    }
                }
            }
            $this->line(sprintf('  %-28s INV %-5d %s%s', $sku, (int) $row->INV, implode(' | ', $where), $near !== [] ? ' | similar offers: '.implode(', ', $near) : ''));
        }

        return self::SUCCESS;
    }

    private function checkAlibaba(): int
    {
        $this->info('Reading the Alibaba product catalog...');
        try {
            $result = app(\App\Services\MarketplaceManager\AlibabaLinkMapSyncService::class)->syncAll(20);
            $this->line((string) ($result['message'] ?? 'Alibaba catalog sync finished.'));
            if (empty($result['success'])) {
                $this->warn('Alibaba catalog sync failed. Counting from the saved catalog.');
            }
        } catch (\Throwable $e) {
            $this->warn('Alibaba catalog sync failed: '.$e->getMessage());
        }

        $counts = ListingChannelCounts::refreshChannelOnMissingListingPage('alibaba');
        $this->line(sprintf('REQ %d | NRL %d | Listed %d | Missing L %d', $counts['REQ'], $counts['NRL'], $counts['Listed'], $counts['Pending']));

        $missing = AutomatedListingPage::rows('alibaba')
            ->filter(fn ($r) => (int) ($r->INV ?? 0) > 0 && ($r->nr_req ?? '') === 'REQ' && ($r->listed ?? '') === 'Pending')
            ->values();

        $this->line('');
        $this->info('Not on Alibaba ('.$missing->count().'):');
        foreach ($missing->take((int) $this->option('limit')) as $row) {
            $this->line(sprintf('  %-28s INV %d', (string) $row->sku, (int) $row->INV));
        }

        return self::SUCCESS;
    }

    private function checkAliExpress(): int
    {
        $this->info('Reading the AliExpress catalog (onSelling, auditing, editing, offline)...');
        try {
            app(\App\Services\MarketplaceManager\AliexpressLiveListingsService::class)->all(true);
        } catch (\Throwable $e) {
            $this->warn('AliExpress catalog refresh failed: '.$e->getMessage());
        }

        $counts = ListingChannelCounts::refreshChannelOnMissingListingPage('aliexpress');
        $this->line(sprintf('REQ %d | NRL %d | Listed %d | Missing L %d', $counts['REQ'], $counts['NRL'], $counts['Listed'], $counts['Pending']));

        $missing = AutomatedListingPage::rows('aliexpress')
            ->filter(fn ($r) => (int) ($r->INV ?? 0) > 0 && ($r->nr_req ?? '') === 'REQ' && ($r->listed ?? '') === 'Pending')
            ->values();

        $this->line('');
        $this->info('Not on AliExpress ('.$missing->count().'):');
        foreach ($missing->take((int) $this->option('limit')) as $row) {
            $this->line(sprintf('  %-28s INV %d', (string) $row->sku, (int) $row->INV));
        }

        return self::SUCCESS;
    }

    private function checkTopDawg(): int
    {
        $this->info('Reading the full TopDawg catalog (SupplierProduct/list)...');
        Cache::forget('ml.listed_catalog.fresh.topdawg');
        $status = app(MissingListingCatalogRefresh::class)->refreshChannel('topdawg');
        if ($status !== 'ok') {
            $this->error('TopDawg catalog refresh: '.$status);

            return self::FAILURE;
        }

        $states = \App\Models\TopDawgProduct::query()
            ->selectRaw("COALESCE(NULLIF(listing_state, ''), '(none)') AS s, COUNT(*) AS n")
            ->groupBy('s')->pluck('n', 's')->all();
        $this->line('Products on TopDawg: '.array_sum($states).' — '.implode(', ', array_map(
            static fn ($s, $n) => $s.' '.$n, array_keys($states), $states
        )));

        $counts = ListingChannelCounts::refreshChannelOnMissingListingPage('topdawg');
        $this->line(sprintf('REQ %d | NRL %d | Listed %d | Missing L %d', $counts['REQ'], $counts['NRL'], $counts['Listed'], $counts['Pending']));

        $missing = AutomatedListingPage::rows('topdawg')
            ->filter(fn ($r) => (int) ($r->INV ?? 0) > 0 && ($r->nr_req ?? '') === 'REQ' && ($r->listed ?? '') === 'Pending')
            ->values();

        $this->line('');
        $this->info('Not on TopDawg ('.$missing->count().'):');
        foreach ($missing->take((int) $this->option('limit')) as $row) {
            $this->line(sprintf('  %-28s INV %d', (string) $row->sku, (int) $row->INV));
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function dumpSkus(): array
    {
        return array_values(array_filter(array_map(
            static fn ($s) => strtoupper(trim($s)),
            explode(',', (string) $this->option('dump'))
        )));
    }

    /**
     * @param  list<string>  $skus
     * @return array<string, string>
     */
    private function productMasterUpcs(array $skus): array
    {
        $out = [];
        \App\Models\ProductMaster::query()->whereIn('sku', $skus)->get(['sku', 'Values'])->each(function ($pm) use (&$out) {
            $values = is_array($pm->Values) ? $pm->Values : [];
            $upc = ltrim(preg_replace('/\D/', '', (string) ($values['upc'] ?? $values['gtin'] ?? '')) ?? '', '0');
            $out[strtoupper(trim((string) $pm->sku))] = $upc;
        });

        return $out;
    }

    /**
     * MCM P31 — products in Macy's catalog by UPC (exist even with no offer).
     *
     * @param  list<string>  $upcs
     * @return array<string, string>|null  UPC => "product_sku title"
     */
    private function mcmCatalogByUpc(array $upcs): ?array
    {
        $apiKey = trim((string) config('services.'.$this->cfgKey.'.mcm_api_key', ''));
        $baseUrl = rtrim((string) config('services.'.$this->cfgKey.'.mcm_base_url', ''), '/');
        if ($apiKey === '' || $baseUrl === '' || $upcs === []) {
            return $apiKey === '' ? null : [];
        }

        $out = [];
        // P31 allows at most 100 product_references; each UPC is sent in 4 paddings.
        foreach (array_chunk($upcs, 25) as $chunk) {
            $refs = [];
            foreach ($chunk as $upc) {
                foreach (array_unique([$upc, str_pad($upc, 12, '0', STR_PAD_LEFT), str_pad($upc, 13, '0', STR_PAD_LEFT), str_pad($upc, 14, '0', STR_PAD_LEFT)]) as $v) {
                    $refs[] = 'UPC|'.$v;
                }
            }
            $response = \Illuminate\Support\Facades\Http::withoutVerifying()
                ->withHeaders(['Authorization' => $apiKey, 'Accept' => 'application/json'])
                ->timeout(60)
                ->get($baseUrl.'/api/products', ['product_references' => implode(',', $refs)]);
            if (! $response->successful()) {
                $this->warn('  P31 HTTP '.$response->status().': '.substr((string) $response->body(), 0, 200));

                return null;
            }
            foreach ($response->json('products') ?? [] as $p) {
                foreach ($p['product_references'] ?? [] as $ref) {
                    $u = ltrim(preg_replace('/\D/', '', (string) ($ref['reference'] ?? '')) ?? '', '0');
                    if ($u !== '') {
                        $out[$u] = trim(($p['product_sku'] ?? '').' '.substr((string) ($p['product_title'] ?? ''), 0, 40));
                    }
                }
            }
            usleep(300000);
        }

        return $out;
    }

    /**
     * Mirakl Connect products for the macys channel, upper SKU => status summary.
     *
     * @return array<string, string>|null
     */
    private function connectProducts(): ?array
    {
        $token = \Illuminate\Support\Facades\Cache::get('macy_access_token');
        if (! is_string($token) || $token === '') {
            $id = config('services.'.$this->cfgKey.'.client_id') ?: config('services.macy.client_id');
            $secret = config('services.'.$this->cfgKey.'.client_secret') ?: config('services.macy.client_secret');
            if (! $id || ! $secret) {
                return null;
            }
            $res = \Illuminate\Support\Facades\Http::withoutVerifying()->asForm()->timeout(30)
                ->post('https://auth.mirakl.net/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => $secret]);
            $token = $res->successful() ? (string) $res->json('access_token') : '';
            if ($token === '') {
                return null;
            }
        }

        $out = [];
        $pageToken = null;
        $pages = 0;
        do {
            $url = 'https://miraklconnect.com/api/products?limit=1000&channel_code='.$this->connectCode.($pageToken ? '&page_token='.urlencode($pageToken) : '');
            $res = \Illuminate\Support\Facades\Http::withoutVerifying()->withToken($token)->timeout(60)->get($url);
            if (! $res->successful()) {
                $this->warn('  Connect HTTP '.$res->status().': '.substr((string) $res->body(), 0, 200));

                return $out === [] ? null : $out;
            }
            foreach ($res->json('data') ?? [] as $p) {
                $sku = strtoupper(trim((string) ($p['id'] ?? $p['sku'] ?? '')));
                if ($sku === '') {
                    continue;
                }
                if (in_array($sku, $this->dumpSkus(), true)) {
                    $this->dumpRows[$sku] = $p;
                }
                $bits = [];
                foreach (['status', 'state', 'channel_status', 'offer_status'] as $k) {
                    if (isset($p[$k]) && ! is_array($p[$k]) && $p[$k] !== '') {
                        $bits[] = $k.'='.$p[$k];
                    }
                }
                if (isset($p['channels']) && is_array($p['channels'])) {
                    $bits[] = 'channels='.substr((string) json_encode($p['channels']), 0, 120);
                }
                $out[$sku] = $bits !== [] ? implode(' ', $bits) : 'present';
            }
            $pageToken = $res->json('next_page_token') ?: null;
            $pages++;
        } while ($pageToken && $pages < 50);

        return $out;
    }
}
