<?php

namespace App\Services\MarketplaceManager;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
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
     * @return array{ok: bool, active: int, inactive: int, missing: int, error?: string}
     */
    public function sync(int $store): array
    {
        $store = in_array($store, [1, 2, 3], true) ? $store : 1;
        $token = $this->accessToken($store);
        if (! $token) {
            return ['ok' => false, 'active' => 0, 'inactive' => 0, 'missing' => 0, 'error' => 'No eBay access token'];
        }

        $this->ensureListingStatusColumn($store);

        $allListings = [];
        foreach (['Active' => 'ACTIVE', 'Unsold' => 'INACTIVE', 'Sold' => 'INACTIVE'] as $listType => $status) {
            foreach ($this->fetchListingsByStatus($token, $listType) as $item) {
                $sku = trim((string) ($item['sku'] ?? ''));
                if ($sku === '' || stripos($sku, 'PARENT') !== false) {
                    continue;
                }
                if (isset($allListings[$sku])) {
                    continue;
                }
                $allListings[$sku] = [
                    'status' => $status,
                    'title' => $item['title'] ?? null,
                    'item_id' => $item['item_id'] ?? null,
                    'ebay_link' => $item['ebay_link'] ?? null,
                    'reason' => $listType === 'Sold' ? 'Sold / ended' : ($listType === 'Unsold' ? 'Unsold / ended' : null),
                ];
            }
        }

        $model = $this->modelClass($store);
        $table = $this->table($store);
        if (! Schema::hasTable($table)) {
            return ['ok' => false, 'active' => 0, 'inactive' => 0, 'missing' => 0, 'error' => $table.' missing'];
        }

        if ($store === 1) {
            $existing = $model::query()
                ->whereNotNull('sku')
                ->where('sku', '!=', '')
                ->where('sku', 'NOT LIKE', '%PARENT%')
                ->pluck('sku')
                ->unique();
            foreach ($existing as $sku) {
                $sku = trim((string) $sku);
                if ($sku === '' || isset($allListings[$sku])) {
                    continue;
                }
                $allListings[$sku] = [
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
            foreach ($chunk as $sku => $data) {
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

                $existing = $model::query()->where('sku', $sku)->first();
                if ($existing) {
                    $existing->fill($payload);
                    $existing->save();
                    continue;
                }
                if (($data['status'] ?? '') === 'MISSING') {
                    continue;
                }
                $model::query()->create(array_merge(['sku' => $sku], $payload));
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
        $key = match ($store) {
            2 => 'ebay2',
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
     * @return list<array{sku: string, title: ?string, item_id: ?string, ebay_link: ?string}>
     */
    protected function fetchListingsByStatus(string $accessToken, string $listType): array
    {
        $allItems = [];
        $pageNumber = 1;
        $totalPages = 1;

        do {
            try {
                $xmlBody = '<?xml version="1.0" encoding="utf-8"?>
                    <GetMyeBaySellingRequest xmlns="urn:ebay:apis:eBLBaseComponents">';
                if ($listType === 'Active') {
                    $xmlBody .= '<ActiveList>
                            <Include>true</Include>
                            <Pagination>
                                <EntriesPerPage>200</EntriesPerPage>
                                <PageNumber>'.$pageNumber.'</PageNumber>
                            </Pagination>
                        </ActiveList>
                        <OutputSelector>ActiveList.ItemArray.Item.ItemID</OutputSelector>
                        <OutputSelector>ActiveList.ItemArray.Item.SKU</OutputSelector>
                        <OutputSelector>ActiveList.ItemArray.Item.Title</OutputSelector>
                        <OutputSelector>ActiveList.PaginationResult</OutputSelector>';
                } elseif ($listType === 'Unsold') {
                    $xmlBody .= '<UnsoldList>
                            <Include>true</Include>
                            <Pagination>
                                <EntriesPerPage>200</EntriesPerPage>
                                <PageNumber>'.$pageNumber.'</PageNumber>
                            </Pagination>
                        </UnsoldList>
                        <OutputSelector>UnsoldList.ItemArray.Item.ItemID</OutputSelector>
                        <OutputSelector>UnsoldList.ItemArray.Item.SKU</OutputSelector>
                        <OutputSelector>UnsoldList.ItemArray.Item.Title</OutputSelector>
                        <OutputSelector>UnsoldList.PaginationResult</OutputSelector>';
                } else {
                    $xmlBody .= '<SoldList>
                            <Include>true</Include>
                            <Pagination>
                                <EntriesPerPage>200</EntriesPerPage>
                                <PageNumber>'.$pageNumber.'</PageNumber>
                            </Pagination>
                        </SoldList>
                        <OutputSelector>SoldList.ItemArray.Item.ItemID</OutputSelector>
                        <OutputSelector>SoldList.ItemArray.Item.SKU</OutputSelector>
                        <OutputSelector>SoldList.ItemArray.Item.Title</OutputSelector>
                        <OutputSelector>SoldList.PaginationResult</OutputSelector>';
                }
                $xmlBody .= '</GetMyeBaySellingRequest>';

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
                    break;
                }

                $xml = simplexml_load_string($response->body());
                if ($xml === false || isset($xml->Errors)) {
                    Log::warning('EbayPortalListingStatusSync: API error', [
                        'type' => $listType,
                        'message' => isset($xml->Errors) ? (string) $xml->Errors->LongMessage : 'parse',
                    ]);
                    break;
                }

                $listNode = null;
                if ($listType === 'Active' && isset($xml->ActiveList)) {
                    $listNode = $xml->ActiveList;
                } elseif ($listType === 'Unsold' && isset($xml->UnsoldList)) {
                    $listNode = $xml->UnsoldList;
                } elseif ($listType === 'Sold' && isset($xml->SoldList)) {
                    $listNode = $xml->SoldList;
                }

                if ($pageNumber === 1 && $listNode && isset($listNode->PaginationResult->TotalNumberOfPages)) {
                    $totalPages = max(1, (int) $listNode->PaginationResult->TotalNumberOfPages);
                }

                if ($listNode && isset($listNode->ItemArray->Item)) {
                    foreach ($listNode->ItemArray->Item as $item) {
                        $sku = trim((string) ($item->SKU ?? ''));
                        $itemId = trim((string) ($item->ItemID ?? ''));
                        $title = preg_replace('/[^\x20-\x7E]/', '', trim((string) ($item->Title ?? '')));
                        if ($sku === '') {
                            continue;
                        }
                        $allItems[] = [
                            'sku' => $sku,
                            'title' => $title !== '' ? $title : null,
                            'item_id' => $itemId !== '' ? $itemId : null,
                            'ebay_link' => $itemId !== '' ? 'https://www.ebay.com/itm/'.$itemId : null,
                        ];
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

        return $allItems;
    }
}
