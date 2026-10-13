<?php

namespace App\Console\Commands;

use App\Services\MarketplaceManager\MacyMcmOfferIndex;
use App\Support\Marketplace\AutomatedListingPage;
use App\Support\Marketplace\ListingChannelCounts;
use App\Support\Marketplace\ListingCountsEngine;
use Illuminate\Console\Command;

/**
 * Pull every Macy's MCM offer (active + inactive), recount Listed / Missing L
 * from it, and print the SKUs that are really not on Macy's.
 */
class MacyListingCheck extends Command
{
    protected $signature = 'macy:listing-check {--limit=200 : Max missing SKUs to print}';

    protected $description = "Recount Macy's Missing L from the live MCM offer list and print the missing SKUs.";

    public function handle(MacyMcmOfferIndex $offerIndex): int
    {
        $this->info("Reading all Macy's MCM offers (OF21, inactive included)...");
        $index = $offerIndex->refresh();
        if ($index === null) {
            $this->error("Could not read Macy's MCM offers (check MACY_MCM_API_KEY / MACY_SHOP_ID and the log).");

            return self::FAILURE;
        }

        $offers = $index['offers'];
        $active = count(array_filter($offers, static fn ($o) => ! empty($o['active'])));
        $this->line(sprintf('Offers on Macy\'s: %d (active %d, inactive %d)', count($offers), $active, count($offers) - $active));

        $counts = ListingChannelCounts::refreshChannelOnMissingListingPage('macys');
        $this->line(sprintf('REQ %d | NRL %d | Listed %d | Missing L %d', $counts['REQ'], $counts['NRL'], $counts['Listed'], $counts['Pending']));

        $missing = AutomatedListingPage::rows('macys')
            ->filter(fn ($r) => (int) ($r->INV ?? 0) > 0 && ($r->nr_req ?? '') === 'REQ' && ($r->listed ?? '') !== 'Listed')
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
            'Catalog checks: Macy MCM catalog by UPC (P31) %s | Mirakl Connect macys products %s',
            $catalogByUpc === null ? 'FAILED' : 'ok',
            $connect === null ? 'FAILED' : count($connect).' read'
        ));

        $this->line('');
        $this->info('Not an offer on Macy\'s ('.$missing->count().'):');
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
                $where[] = $upc === '' ? 'no UPC in Product Master' : 'not in Macy catalog';
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
        $apiKey = trim((string) config('services.macy.mcm_api_key', ''));
        $baseUrl = rtrim((string) config('services.macy.mcm_base_url', 'https://macysus-prod.mirakl.net'), '/');
        if ($apiKey === '' || $upcs === []) {
            return $apiKey === '' ? null : [];
        }

        $out = [];
        foreach (array_chunk($upcs, 50) as $chunk) {
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
            $id = config('services.macy.client_id');
            $secret = config('services.macy.client_secret');
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
            $url = 'https://miraklconnect.com/api/products?limit=1000&channel_code=macys'.($pageToken ? '&page_token='.urlencode($pageToken) : '');
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
