<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Services\Ebay2ApiService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Pull Active / Unsold / Sold from GetMyeBaySelling so MM Active SKU / Inactive SKU
 * match the eBay seller portal (not item_id or qty heuristics).
 */
class EbayPortalListingStatusSync
{
    /**
     * @return array{ok: bool, unsold_ok: bool, active: int, inactive: int, missing: int, error?: string}
     */
    public function sync(int $store): array
    {
        $store = in_array($store, [1, 2, 3], true) ? $store : 1;
        $token = $this->accessToken($store);
        if (! $token) {
            return ['ok' => false, 'unsold_ok' => false, 'active' => 0, 'inactive' => 0, 'missing' => 0, 'error' => 'No eBay access token'];
        }

        $this->ensureListingStatusColumn($store);

        $model = $this->modelClass($store);
        $table = $this->table($store);
        if (! Schema::hasTable($table)) {
            return ['ok' => false, 'unsold_ok' => false, 'active' => 0, 'inactive' => 0, 'missing' => 0, 'error' => $table.' missing'];
        }

        // Seller Hub Inactive = Unsold / not relisted (last 60 days). Do not use Sold.
        $unsold = $this->fetchListingsByStatus($token, 'Unsold');
        if (! $unsold['ok']) {
            return [
                'ok' => false,
                'unsold_ok' => false,
                'active' => 0,
                'inactive' => 0,
                'missing' => 0,
                'error' => 'Unsold list fetch failed',
            ];
        }
        $active = $this->fetchListingsByStatus($token, 'Active');
        $allListings = [];
        foreach ($unsold['items'] as $item) {
            $this->rememberListing($allListings, $item, 'INACTIVE', 'Unsold / ended');
        }
        foreach ($active['items'] as $item) {
            $this->rememberListing($allListings, $item, 'ACTIVE', null);
        }

        Log::info('EbayPortalListingStatusSync: fetched', [
            'store' => $store,
            'unsold_api' => $unsold['total'],
            'unsold_skus' => $unsold['sku_count'],
            'active_api' => $active['total'],
            'active_skus' => $active['sku_count'],
        ]);

        $unsoldExtracted = $unsold['ok'] && ($unsold['total'] === 0 || $unsold['sku_count'] > 0);
        if ($store === 1 && $unsoldExtracted && $active['ok']) {
            $existing = $model::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->where('sku', 'NOT LIKE', '%PARENT%')
                ->pluck('sku')
                ->unique();
            foreach ($existing as $sku) {
                $sku = trim((string) $sku);
                if ($sku === '' || $this->listingHasSku($allListings, $sku)) {
                    continue;
                }
                $allListings[strtoupper($sku)] = [
                    'sku' => $sku,
                    'status' => 'MISSING',
                    'title' => null,
                    'item_id' => null,
                    'ebay_link' => null,
                    'reason' => null,
                ];
            }
        }

        $this->persist($model, $table, $allListings);

        $statuses = array_column($allListings, 'status');
        $counts = array_count_values($statuses);

        return [
            'ok' => true,
            'unsold_ok' => true,
            'active' => (int) ($counts['ACTIVE'] ?? 0),
            'inactive' => (int) ($counts['INACTIVE'] ?? 0),
            'missing' => (int) ($counts['MISSING'] ?? 0),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<string, array{status: string, title: ?string, item_id: ?string, ebay_link: ?string, reason: ?string}>  $allListings
     */
    protected function persist(string $model, string $table, array $allListings): void
    {
        $hasReason = Schema::hasColumn($table, 'inactive_reason');
        $hasTitle = Schema::hasColumn($table, 'ebay_title');
        $hasLink = Schema::hasColumn($table, 'ebay_link');
        $hasItemId = Schema::hasColumn($table, 'item_id');
        $hasStatus = Schema::hasColumn($table, 'listing_status');
        if (! $hasStatus) {
            return;
        }

        foreach (array_chunk($allListings, 200, true) as $chunk) {
            foreach ($chunk as $key => $data) {
                try {
                    $sku = trim((string) ($data['sku'] ?? $key));
                    if ($sku === '') {
                        continue;
                    }
                    $payload = [
                        'listing_status' => $data['status'],
                    ];
                    if ($hasReason) {
                        $payload['inactive_reason'] = $data['status'] === 'INACTIVE'
                            ? ($data['reason'] ?: 'Inactive on eBay')
                            : null;
                    }
                    if ($hasTitle && ! empty($data['title'])) {
                        $payload['ebay_title'] = $data['title'];
                    }
                    if ($hasLink && ! empty($data['ebay_link'])) {
                        $payload['ebay_link'] = $data['ebay_link'];
                    }
                    if ($hasItemId && ! empty($data['item_id'])) {
                        $payload['item_id'] = $data['item_id'];
                    }

                    $existing = $model::query()
                        ->whereRaw('UPPER(TRIM(sku)) = ?', [strtoupper($sku)])
                        ->first();
                    if ($existing) {
                        $existing->fill($payload);
                        $existing->save();
                        continue;
                    }
                    if (($data['status'] ?? '') === 'MISSING') {
                        continue;
                    }
                    $create = array_merge(['sku' => $sku], $payload);
                    if ($hasItemId && empty($create['item_id'])) {
                        $create['item_id'] = $data['item_id'] ?? ('UNSOLD-'.$sku);
                    }
                    $model::query()->create($create);
                } catch (\Throwable $e) {
                    Log::warning('EbayPortalListingStatusSync: persist failed', [
                        'sku' => $data['sku'] ?? $key ?? '',
                        'table' => $table,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    protected function ensureListingStatusColumn(int $store): void
    {
        $table = $this->table($store);
        if (! Schema::hasTable($table)) {
            return;
        }
        if (! Schema::hasColumn($table, 'listing_status')) {
            Schema::table($table, function ($blueprint) {
                $blueprint->string('listing_status', 32)->nullable()->index();
            });
        }
        if (! Schema::hasColumn($table, 'inactive_reason')) {
            Schema::table($table, function ($blueprint) {
                $blueprint->string('inactive_reason', 191)->nullable();
            });
        }
    }

    /**
     * @return class-string<Model>
     */
    protected function modelClass(int $store): string
    {
        return match ($store) {
            2 => Ebay2Metric::class,
            3 => Ebay3Metric::class,
            default => EbayMetric::class,
        };
    }

    protected function table(int $store): string
    {
        return match ($store) {
            2 => 'ebay_2_metrics',
            3 => 'ebay_3_metrics',
            default => 'ebay_metrics',
        };
    }

    protected function accessToken(int $store): ?string
    {
        if ($store === 2) {
            try {
                $token = app(Ebay2ApiService::class)->generateBearerToken();

                return is_string($token) && $token !== '' ? $token : null;
            } catch (\Throwable $e) {
                Log::warning('EbayPortalListingStatusSync: eBay 2 token failed', [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        $key = match ($store) {
            3 => 'ebay3',
            default => 'ebay',
        };
        $appId = config('services.'.$key.'.app_id');
        $certId = config('services.'.$key.'.cert_id');
        $refreshToken = trim((string) (config('services.'.$key.'.refresh_token') ?? ''), '"');
        if (! $appId || ! $certId || $refreshToken === '') {
            Log::warning('EbayPortalListingStatusSync: missing credentials', ['store' => $store]);

            return null;
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($appId, $certId)
                ->timeout(30)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);
            if (! $response->successful()) {
                Log::warning('EbayPortalListingStatusSync: token failed', [
                    'store' => $store,
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json()['access_token'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('EbayPortalListingStatusSync: token exception', [
                'store' => $store,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, array{sku: string, status: string, title: ?string, item_id: ?string, ebay_link: ?string, reason: ?string}>  $allListings
     * @param  array{sku: string, title: ?string, item_id: ?string, ebay_link: ?string}  $item
     */
    protected function rememberListing(array &$allListings, array $item, string $status, ?string $reason): void
    {
        $sku = trim((string) ($item['sku'] ?? ''));
        if ($sku === '' || stripos($sku, 'PARENT') !== false) {
            return;
        }
        $key = strtoupper($sku);
        if (isset($allListings[$key])) {
            return;
        }
        $allListings[$key] = [
            'sku' => $sku,
            'status' => $status,
            'title' => $item['title'] ?? null,
            'item_id' => $item['item_id'] ?? null,
            'ebay_link' => $item['ebay_link'] ?? null,
            'reason' => $reason,
        ];
    }

    /**
     * @param  array<string, array{sku: string}>  $allListings
     */
    protected function listingHasSku(array $allListings, string $sku): bool
    {
        return isset($allListings[strtoupper(trim($sku))]);
    }

    /**
     * @return array{ok: bool, items: list<array{sku: string, title: ?string, item_id: ?string, ebay_link: ?string}>, total: int, sku_count: int}
     */
    protected function fetchListingsByStatus(string $accessToken, string $listType): array
    {
        $allItems = [];
        $pageNumber = 1;
        $totalPages = 1;
        $totalEntries = 0;
        $ok = false;

        do {
            try {
                $listXml = $this->myEbayListXml($listType, $pageNumber);
                $xmlBody = '<?xml version="1.0" encoding="utf-8"?>'
                    .'<GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">'
                    .$listXml
                    .'</GetMyeBaySellingRequest>';

                $response = Http::timeout(60)
                    ->withHeaders([
                        'X-EBAY-API-SITEID' => '0',
                        'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
                        'X-EBAY-API-CALL-NAME' => 'GetMyeBaySelling',
                        'X-EBAY-API-IAF-TOKEN' => $accessToken,
                    ])
                    ->withBody($xmlBody, 'text/xml')
                    ->post('https://api.ebay.com/ws/api.dll');

                if (! $response->successful()) {
                    Log::warning('EbayPortalListingStatusSync: HTTP failed', [
                        'type' => $listType,
                        'status' => $response->status(),
                    ]);
                    break;
                }

                $xml = simplexml_load_string($response->body());
                if ($xml === false) {
                    break;
                }
                $ack = strtoupper(trim((string) ($xml->Ack ?? '')));
                if ($ack === 'FAILURE') {
                    Log::warning('EbayPortalListingStatusSync: API failure', [
                        'type' => $listType,
                        'message' => (string) ($xml->Errors->LongMessage ?? $xml->Errors->ShortMessage ?? 'failure'),
                    ]);
                    break;
                }
                $ok = true;

                $listNode = null;
                if ($listType === 'Active' && isset($xml->ActiveList)) {
                    $listNode = $xml->ActiveList;
                } elseif ($listType === 'Unsold' && isset($xml->UnsoldList)) {
                    $listNode = $xml->UnsoldList;
                }

                if ($pageNumber === 1 && $listNode && isset($listNode->PaginationResult->TotalNumberOfPages)) {
                    $totalPages = max(1, (int) $listNode->PaginationResult->TotalNumberOfPages);
                    $totalEntries = (int) ($listNode->PaginationResult->TotalNumberOfEntries ?? 0);
                }

                if ($listNode && isset($listNode->ItemArray->Item)) {
                    foreach ($listNode->ItemArray->Item as $item) {
                        foreach ($this->skusFromSellingItem($item) as $sku) {
                            $itemId = trim((string) ($item->ItemID ?? ''));
                            $title = preg_replace('/[^\x20-\x7E]/', '', trim((string) ($item->Title ?? '')));
                            $allItems[] = [
                                'sku' => $sku,
                                'title' => $title !== '' ? $title : null,
                                'item_id' => $itemId !== '' ? $itemId : null,
                                'ebay_link' => $itemId !== '' ? 'https://www.ebay.com/itm/'.$itemId : null,
                            ];
                        }
                    }
                }

                $pageNumber++;
            } catch (\Throwable $e) {
                Log::warning('EbayPortalListingStatusSync: fetch failed', [
                    'type' => $listType,
                    'error' => $e->getMessage(),
                ]);
                break;
            }
        } while ($pageNumber <= $totalPages);

        return [
            'ok' => $ok,
            'items' => $allItems,
            'total' => $totalEntries,
            'sku_count' => count($allItems),
        ];
    }

    protected function myEbayListXml(string $listType, int $pageNumber): string
    {
        $page = (int) $pageNumber;
        $prefix = $listType === 'Unsold' ? 'UnsoldList' : 'ActiveList';
        $duration = $listType === 'Unsold'
            ? '<DurationInDays>60</DurationInDays>'
            : '';

        return '<'.$prefix.'>'
            .'<Include>true</Include>'
            .$duration
            .'<Pagination><EntriesPerPage>200</EntriesPerPage><PageNumber>'.$page.'</PageNumber></Pagination>'
            .'</'.$prefix.'>'
            .'<OutputSelector>'.$prefix.'.ItemArray.Item.ItemID</OutputSelector>'
            .'<OutputSelector>'.$prefix.'.ItemArray.Item.SKU</OutputSelector>'
            .'<OutputSelector>'.$prefix.'.ItemArray.Item.Title</OutputSelector>'
            .'<OutputSelector>'.$prefix.'.ItemArray.Item.Variations</OutputSelector>'
            .'<OutputSelector>'.$prefix.'.PaginationResult</OutputSelector>';
    }

    /**
     * @return list<string>
     */
    protected function skusFromSellingItem(\SimpleXMLElement $item): array
    {
        $skus = [];
        $itemSku = trim((string) ($item->SKU ?? ''));
        if ($itemSku !== '' && stripos($itemSku, 'PARENT') === false) {
            $skus[] = $itemSku;
        }
        if (isset($item->Variations->Variation)) {
            foreach ($item->Variations->Variation as $variation) {
                $sku = trim((string) ($variation->SKU ?? ''));
                if ($sku !== '' && stripos($sku, 'PARENT') === false) {
                    $skus[] = $sku;
                }
            }
        }
        if ($skus === []) {
            $itemId = trim((string) ($item->ItemID ?? ''));
            if ($itemId !== '') {
                $skus[] = $itemId;
            }
        }

        return array_values(array_unique($skus));
    }
}
