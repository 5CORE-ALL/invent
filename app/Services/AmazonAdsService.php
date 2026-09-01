<?php

namespace App\Services;

use GuzzleHttp\Client;
use InvalidArgumentException;

class AmazonAdsService
{
    protected Client $client;

    protected string $tokenUrl;

    protected string $apiBase;

    protected string $clientId;

    protected string $clientSecret;

    protected string $refreshToken;

    protected string $profileId;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout' => 120,
            'connect_timeout' => 60,
            'http_errors' => true,
        ]);

        $this->tokenUrl = rtrim((string) (config('services.amazon_ads.token_url') ?: env('AMAZON_ADS_TOKEN_URL', 'https://api.amazon.com/auth/o2/token')), '/');
        $this->apiBase = rtrim((string) (config('services.amazon_ads.api_base_url') ?: env('AMAZON_ADS_API_BASE_URL', 'https://advertising-api.amazon.com')), '/');
        $this->clientId = (string) config('services.amazon_ads.client_id', '');
        $this->clientSecret = (string) config('services.amazon_ads.client_secret', '');
        $this->refreshToken = (string) config('services.amazon_ads.refresh_token', '');
        $this->profileId = (string) config('services.amazon_ads.profile_ids', '');
    }

    /**
     * Obtain a new access token on every call (no caching).
     *
     */
    public function getFreshAccessToken(): string
    {
        $this->assertOAuthConfig();

        $response = $this->client->post($this->tokenUrl, [
            'form_params' => [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ],
        ]);

        $data = $this->decodeResponseBody((string) $response->getBody());
        $token = $data['access_token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new InvalidArgumentException('Amazon Ads OAuth response did not include access_token.');
        }

        return $token;
    }

    /**
     * Reusable GET against the Advertising API (or any absolute URL).
     *
     */
    public function get(string $path, array $query = [], array $headers = [], bool $withProfileScope = true): array
    {
        return $this->request('GET', $path, [
            'query' => $query,
            'headers' => $headers,
        ], $withProfileScope);
    }

    /**
     * Reusable POST against the Advertising API (or any absolute URL).
     *
     */
    public function post(string $path, ?array $body = null, array $headers = [], bool $withProfileScope = true): array
    {
        $options = ['headers' => $headers];
        if ($body !== null) {
            $options['json'] = $body;
        }

        return $this->request('POST', $path, $options, $withProfileScope);
    }

    /**
     * Reusable PUT against the Advertising API (or any absolute URL).
     *
     */
    public function put(string $path, ?array $body = null, array $headers = [], bool $withProfileScope = true): array
    {
        $options = ['headers' => $headers];
        if ($body !== null) {
            $options['json'] = $body;
        }

        return $this->request('PUT', $path, $options, $withProfileScope);
    }

    /**
     */
    public function getProfiles(): array
    {
        return $this->get('/v2/profiles', [], [
            'Accept' => 'application/json',
        ], false);
    }

    /**
     * Product Selector metadata (SKU/ASIN inventory for advertising).
     *
     * @param  list<string>  $skus
     * @param  list<string>  $asins
     * @return array<string, mixed>
     */
    public function getProductMetadata(array $skus = [], array $asins = [], int $pageIndex = 0, int $pageSize = 100): array
    {
        $body = [
            'pageIndex' => max(0, $pageIndex),
            'pageSize' => max(1, min(100, $pageSize)),
            'checkItemDetails' => true,
            'adType' => 'SP',
        ];

        $skus = array_values(array_unique(array_filter(array_map('strval', $skus))));
        $asins = array_values(array_unique(array_filter(array_map('strval', $asins))));

        if ($skus !== []) {
            $body['skus'] = $skus;
        } elseif ($asins !== []) {
            $body['asins'] = $asins;
        } else {
            return ['ProductMetadataList' => []];
        }

        return $this->post('/product/metadata', $body, [
            'Content-Type' => 'application/vnd.productmetadatarequest.v1+json',
            'Accept' => 'application/vnd.productmetadataresponse.v1+json',
        ]);
    }

    /**
     * Brand Posts product list — includes customerReviewSummary (avg rating + review count).
     * Query must use repeated asins= (not asins[]=).
     *
     * @param  list<string>  $asins
     * @return array<string, mixed>
     */
    public function getBrandPostProductsByAsins(array $asins): array
    {
        $asins = array_values(array_unique(array_filter(array_map(static function ($a) {
            return strtoupper(trim((string) $a));
        }, $asins))));

        if ($asins === []) {
            return ['eligibleProducts' => [], 'ineligibleProducts' => []];
        }

        // Max practical batch for this GET endpoint
        $asins = array_slice($asins, 0, 20);
        $query = implode('&', array_map(
            static fn (string $a) => 'asins='.rawurlencode($a),
            $asins
        ));

        return $this->get('/bp/v2/products/list?'.$query, [], [
            'Accept' => 'application/vnd.bpProduct.v2+json',
        ]);
    }

    /**
     * Parse avg rating + review count from a Brand Posts product / review-summary payload.
     *
     * @param  array<string, mixed>  $product
     * @return array{rating: float|null, review_count: int|null}
     */
    public static function extractReviewSummary(array $product): array
    {
        $summary = $product['customerReviewSummary']
            ?? $product['customer_review_summary']
            ?? $product['reviewSummary']
            ?? [];

        if (! is_array($summary)) {
            $summary = [];
        }

        $rating = $summary['averageRating']
            ?? $summary['average_rating']
            ?? $summary['starRating']
            ?? $summary['star_rating']
            ?? $summary['rating']
            ?? $product['averageRating']
            ?? $product['starRating']
            ?? $product['rating']
            ?? null;

        $count = $summary['totalReviewCount']
            ?? $summary['total_review_count']
            ?? $summary['reviewCount']
            ?? $summary['review_count']
            ?? $summary['count']
            ?? $product['totalReviewCount']
            ?? $product['reviewCount']
            ?? $product['reviews']
            ?? null;

        return [
            'rating' => is_numeric($rating) ? round((float) $rating, 2) : null,
            'review_count' => is_numeric($count) ? (int) $count : null,
        ];
    }

    /**
     * Sponsored Products campaigns (list).
     *
     */
    public function getCampaigns(): array
    {
        return $this->post('/sp/campaigns/list', [
            'stateFilter' => [
                'include' => ['ENABLED', 'PAUSED', 'ARCHIVED'],
            ],
        ], [
            'Content-Type' => 'application/vnd.spCampaign.v3+json',
            'Accept' => 'application/vnd.spCampaign.v3+json',
        ]);
    }

    /**
     * Sponsored Products ad groups for a single campaign.
     *
     */
    public function getAdGroups(string $campaignId): array
    {
        $campaignId = trim($campaignId);
        if ($campaignId === '') {
            return [];
        }

        return $this->post('/sp/adGroups/list', [
            'campaignIdFilter' => ['include' => [$campaignId]],
            'stateFilter' => [
                'include' => ['ENABLED', 'PAUSED', 'ARCHIVED'],
            ],
        ], [
            'Content-Type' => 'application/vnd.spAdGroup.v3+json',
            'Accept' => 'application/vnd.spAdGroup.v3+json',
        ]);
    }

    /**
     * Sponsored Products keywords for an ad group.
     *
     */
    public function getKeywords(string $adGroupId): array
    {
        $adGroupId = trim($adGroupId);
        if ($adGroupId === '') {
            return [];
        }

        return $this->post('/sp/keywords/list', [
            'adGroupIdFilter' => ['include' => [$adGroupId]],
        ], [
            'Content-Type' => 'application/vnd.spKeyword.v3+json',
            'Accept' => 'application/vnd.spKeyword.v3+json',
        ]);
    }

    /**
     * SP targeting clauses for one or more campaigns (walks nextToken).
     *
     * @param  list<string>  $campaignIds
     * @return list<array<string, mixed>>
     */
    public function listTargetsByCampaignIds(array $campaignIds, int $maxPages = 20): array
    {
        return $this->listSpEntitiesByCampaignIds(
            '/sp/targets/list',
            'application/vnd.spTargetingClause.v3+json',
            ['targetingClauses', 'targets'],
            $campaignIds,
            $maxPages
        );
    }

    /**
     * SP keywords for one or more campaigns (walks nextToken).
     *
     * @param  list<string>  $campaignIds
     * @return list<array<string, mixed>>
     */
    public function listKeywordsByCampaignIds(array $campaignIds, int $maxPages = 20): array
    {
        return $this->listSpEntitiesByCampaignIds(
            '/sp/keywords/list',
            'application/vnd.spKeyword.v3+json',
            ['keywords'],
            $campaignIds,
            $maxPages
        );
    }

    /**
     * @param  list<string>  $campaignIds
     * @param  list<string>  $listKeys
     * @return list<array<string, mixed>>
     */
    protected function listSpEntitiesByCampaignIds(
        string $path,
        string $accept,
        array $listKeys,
        array $campaignIds,
        int $maxPages = 20
    ): array {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $campaignIds
        ), static fn (string $id) => $id !== '')));
        if ($ids === []) {
            return [];
        }

        $out = [];
        $nextToken = null;
        $pages = 0;
        do {
            $pages++;
            $body = [
                'campaignIdFilter' => ['include' => $ids],
                'stateFilter' => ['include' => ['ENABLED', 'PAUSED']],
                'maxResults' => 100,
            ];
            if (is_string($nextToken) && $nextToken !== '') {
                $body['nextToken'] = $nextToken;
            }
            $response = $this->post($path, $body, [
                'Content-Type' => $accept,
                'Accept' => $accept,
            ]);
            $batch = [];
            foreach ($listKeys as $key) {
                if (isset($response[$key]) && is_array($response[$key])) {
                    $batch = $response[$key];
                    break;
                }
            }
            foreach ($batch as $row) {
                if (is_array($row)) {
                    $out[] = $row;
                }
            }
            $nextToken = $response['nextToken'] ?? null;
        } while (is_string($nextToken) && $nextToken !== '' && $pages < $maxPages);

        return $out;
    }

    /**
     * List Sponsored Products product ads (paginated).
     * POST /sp/productAds/list
     *
     * @param  array<int, string>|null  $campaignIds  Optional campaignIdFilter
     * @param  array<int, string>  $states
     * @return array{productAds: array<int, array>, nextToken: ?string}
     */
    public function listProductAdsPage(?array $campaignIds = null, array $states = ['ENABLED', 'PAUSED'], ?string $nextToken = null): array
    {
        $body = [
            'stateFilter' => [
                'include' => array_values($states),
            ],
            'maxResults' => 100,
        ];

        if ($campaignIds !== null && $campaignIds !== []) {
            $body['campaignIdFilter'] = [
                'include' => array_values(array_map('strval', $campaignIds)),
            ];
        }

        if ($nextToken !== null && $nextToken !== '') {
            $body['nextToken'] = $nextToken;
        }

        return $this->post('/sp/productAds/list', $body, [
            'Content-Type' => 'application/vnd.spProductAd.v3+json',
            'Accept' => 'application/vnd.spProductAd.v3+json',
        ]);
    }

    /**
     * Pull all SP product ads (ENABLED + PAUSED) across pages.
     *
     * @param  array<int, string>|null  $campaignIds
     * @return array{success: bool, message?: string, count?: int, ads?: array<int, array>}
     */
    public function fetchAllProductAds(array $states = ['ENABLED', 'PAUSED'], ?array $campaignIds = null): array
    {
        try {
            $this->assertOAuthConfig();
            $this->assertProfileScope();

            $all = [];
            $nextToken = null;
            $pages = 0;
            $maxPages = 500;

            do {
                $pages++;
                $response = $this->listProductAdsPage($campaignIds, $states, $nextToken);
                $batch = $response['productAds'] ?? $response['productAdList'] ?? [];
                if (! is_array($batch)) {
                    $batch = [];
                }

                foreach ($batch as $ad) {
                    if (is_array($ad)) {
                        $all[] = $ad;
                    }
                }

                $nextToken = $response['nextToken'] ?? null;
                if ($pages >= $maxPages) {
                    break;
                }
            } while (is_string($nextToken) && $nextToken !== '');

            return [
                'success' => true,
                'count' => count($all),
                'ads' => $all,
                'profile_id' => $this->resolvedProfileId(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * List Sponsored Brands ads (paginated).
     * POST /sb/v4/ads/list
     *
     * @param  array<int, string>|null  $campaignIds
     * @param  array<int, string>  $states
     * @return array{ads: array<int, array>, nextToken: ?string}
     */
    public function listSbAdsPage(?array $campaignIds = null, array $states = ['ENABLED', 'PAUSED'], ?string $nextToken = null): array
    {
        $body = [
            'maxResults' => 100,
        ];
        if ($states !== []) {
            $body['stateFilter'] = [
                'include' => array_values($states),
            ];
        }
        if ($campaignIds !== null && $campaignIds !== []) {
            $body['campaignIdFilter'] = [
                'include' => array_values(array_map('strval', $campaignIds)),
            ];
        }
        if ($nextToken !== null && $nextToken !== '') {
            $body['nextToken'] = $nextToken;
        }

        return $this->post('/sb/v4/ads/list', $body, [
            'Content-Type' => 'application/vnd.sbadresource.v4+json',
            'Accept' => 'application/vnd.sbadresource.v4+json',
        ]);
    }

    /**
     * Pull all SB ads (ENABLED + PAUSED) across pages.
     *
     * @param  array<int, string>  $states
     * @param  array<int, string>|null  $campaignIds
     * @return array{success: bool, message?: string, count?: int, ads?: array<int, array>}
     */
    public function fetchAllSbAds(array $states = ['ENABLED', 'PAUSED'], ?array $campaignIds = null): array
    {
        try {
            $this->assertOAuthConfig();
            $this->assertProfileScope();

            $all = [];
            $nextToken = null;
            $pages = 0;
            $maxPages = 500;

            do {
                $pages++;
                $response = $this->listSbAdsPage($campaignIds, $states, $nextToken);
                $batch = $response['ads'] ?? $response['adList'] ?? [];
                if (! is_array($batch)) {
                    $batch = [];
                }
                foreach ($batch as $ad) {
                    if (is_array($ad)) {
                        $all[] = $ad;
                    }
                }
                $nextToken = $response['nextToken'] ?? null;
                if ($pages >= $maxPages) {
                    break;
                }
            } while (is_string($nextToken) && $nextToken !== '');

            return [
                'success' => true,
                'count' => count($all),
                'ads' => $all,
                'profile_id' => $this->resolvedProfileId(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * First profile id when AMAZON_ADS_PROFILE_IDS is comma-separated.
     */
    public function resolvedProfileId(): string
    {
        $raw = trim((string) $this->profileId);
        if ($raw === '') {
            return '';
        }

        $parts = preg_split('/\s*,\s*/', $raw) ?: [];

        return trim((string) ($parts[0] ?? $raw));
    }

    /**
     * Create Sponsored Products campaigns (v3).
     *
     * @param  list<array<string, mixed>>  $campaigns
     * @return array<string, mixed>
     */
    public function createCampaigns(array $campaigns): array
    {
        return $this->post('/sp/campaigns', [
            'campaigns' => array_values($campaigns),
        ], [
            'Content-Type' => 'application/vnd.spCampaign.v3+json',
            'Accept' => 'application/vnd.spCampaign.v3+json',
        ]);
    }

    /**
     * Update Sponsored Products campaigns (v3) — budget, state (ENABLED/PROPOSED/PAUSED), etc.
     * Note: ARCHIVED is not accepted here; use deleteCampaigns() / archiveCampaign().
     *
     * @param  list<array<string, mixed>>  $campaigns
     * @return array<string, mixed>
     */
    public function updateCampaigns(array $campaigns): array
    {
        return $this->put('/sp/campaigns', [
            'campaigns' => array_values($campaigns),
        ], [
            'Content-Type' => 'application/vnd.spCampaign.v3+json',
            'Accept' => 'application/vnd.spCampaign.v3+json',
        ]);
    }

    /**
     * Update Sponsored Brands campaigns (v4) — state (ENABLED/PAUSED), etc.
     *
     * @param  list<array<string, mixed>>  $campaigns
     * @return array<string, mixed>
     */
    public function updateSbCampaigns(array $campaigns): array
    {
        return $this->put('/sb/v4/campaigns', [
            'campaigns' => array_values($campaigns),
        ], [
            'Content-Type' => 'application/vnd.sbcampaignresource.v4+json',
            'Accept' => 'application/vnd.sbcampaignresource.v4+json',
        ]);
    }

    /**
     * Delete (archive) Sponsored Products campaigns (v3).
     * Amazon permanently moves them to ARCHIVED via POST /sp/campaigns/delete.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, mixed>
     */
    public function deleteCampaigns(array $campaignIds): array
    {
        $ids = array_values(array_filter(array_map(
            static fn ($id) => trim((string) $id),
            $campaignIds
        ), static fn ($id) => $id !== ''));

        return $this->post('/sp/campaigns/delete', [
            'campaignIdFilter' => [
                'include' => $ids,
            ],
        ], [
            'Content-Type' => 'application/vnd.spCampaign.v3+json',
            'Accept' => 'application/vnd.spCampaign.v3+json',
        ]);
    }

    /**
     * Create Sponsored Products ad groups (v3).
     *
     * @param  list<array<string, mixed>>  $adGroups
     * @return array<string, mixed>
     */
    public function createAdGroups(array $adGroups): array
    {
        return $this->post('/sp/adGroups', [
            'adGroups' => array_values($adGroups),
        ], [
            'Content-Type' => 'application/vnd.spAdGroup.v3+json',
            'Accept' => 'application/vnd.spAdGroup.v3+json',
        ]);
    }

    /**
     * Create Sponsored Products product ads (v3).
     * Sellers must pass sku; vendors pass asin.
     *
     * @param  list<array<string, mixed>>  $productAds
     * @return array<string, mixed>
     */
    public function createProductAds(array $productAds): array
    {
        return $this->post('/sp/productAds', [
            'productAds' => array_values($productAds),
        ], [
            'Content-Type' => 'application/vnd.spProductAd.v3+json',
            'Accept' => 'application/vnd.spProductAd.v3+json',
        ]);
    }

    /**
     * Update Sponsored Products product ads (v3) — state ENABLED / PAUSED.
     *
     * @param  list<array<string, mixed>>  $productAds
     * @return array<string, mixed>
     */
    public function updateProductAds(array $productAds): array
    {
        return $this->put('/sp/productAds', [
            'productAds' => array_values($productAds),
        ], [
            'Content-Type' => 'application/vnd.spProductAd.v3+json',
            'Accept' => 'application/vnd.spProductAd.v3+json',
        ]);
    }

    /**
     * Update Sponsored Brands ads (v4) — state ENABLED / PAUSED.
     *
     * @param  list<array<string, mixed>>  $ads
     * @return array<string, mixed>
     */
    public function updateSbAds(array $ads): array
    {
        return $this->put('/sb/v4/ads', [
            'ads' => array_values($ads),
        ], [
            'Content-Type' => 'application/vnd.sbadresource.v4+json',
            'Accept' => 'application/vnd.sbadresource.v4+json',
        ]);
    }

    /**
     * Add seller SKUs as product ads on an existing SP campaign.
     * Inventory is not checked — Amazon accepts the SKU if it is a valid seller SKU.
     *
     * @param  list<string>  $sellerSkus
     * @return array{
     *   success: bool,
     *   message: string,
     *   campaign_id: string,
     *   ad_group_id: string,
     *   added: list<array{sku: string, ad_id: string}>,
     *   failed: list<array{sku: string, message: string}>,
     *   profile_id: string
     * }
     */
    public function addSellerSkusToCampaign(string $campaignId, array $sellerSkus, ?string $adGroupId = null): array
    {
        $campaignId = preg_replace('/\D+/', '', trim($campaignId)) ?: '';
        $sellerSkus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $sellerSkus
        ))));

        $empty = [
            'success' => false,
            'message' => '',
            'campaign_id' => $campaignId,
            'ad_group_id' => '',
            'added' => [],
            'failed' => [],
            'profile_id' => $this->resolvedProfileId(),
        ];

        if ($campaignId === '') {
            $empty['message'] = 'Campaign ID is required.';

            return $empty;
        }
        if ($sellerSkus === []) {
            $empty['message'] = 'At least one seller SKU is required.';

            return $empty;
        }

        $adGroupId = preg_replace('/\D+/', '', trim((string) $adGroupId)) ?: '';
        if ($adGroupId === '') {
            $adGroupId = $this->resolvePrimaryAdGroupId($campaignId);
        }
        if ($adGroupId === '') {
            $empty['message'] = 'Could not resolve an ad group for this campaign.';

            return $empty;
        }

        $added = [];
        $failed = [];
        foreach (array_chunk($sellerSkus, 100) as $chunk) {
            $payload = [];
            foreach ($chunk as $sku) {
                $payload[] = [
                    'campaignId' => $campaignId,
                    'adGroupId' => $adGroupId,
                    'sku' => $sku,
                    'state' => 'ENABLED',
                ];
            }

            try {
                $adsResp = $this->createProductAds($payload);
            } catch (\Throwable $e) {
                $msg = $this->formatAmazonException($e);
                foreach ($chunk as $sku) {
                    $failed[] = ['sku' => $sku, 'message' => $msg];
                }

                continue;
            }

            $successByIndex = [];
            foreach (data_get($adsResp, 'productAds.success', []) ?: [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $idx = $row['index'] ?? null;
                $adId = (string) ($row['adId'] ?? data_get($row, 'productAd.adId') ?? '');
                $sku = (string) (data_get($row, 'productAd.sku') ?? '');
                if (is_int($idx) || is_numeric($idx)) {
                    $successByIndex[(int) $idx] = ['ad_id' => $adId, 'sku' => $sku];
                } elseif ($sku !== '') {
                    $added[] = ['sku' => $sku, 'ad_id' => $adId];
                }
            }

            foreach ($chunk as $i => $sku) {
                if (isset($successByIndex[$i])) {
                    $hit = $successByIndex[$i];
                    $added[] = [
                        'sku' => $hit['sku'] !== '' ? $hit['sku'] : $sku,
                        'ad_id' => $hit['ad_id'],
                    ];
                }
            }

            foreach (data_get($adsResp, 'productAds.error', []) ?: [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $idx = $row['index'] ?? null;
                $sku = (is_int($idx) || is_numeric($idx))
                    ? (string) ($chunk[(int) $idx] ?? '')
                    : '';
                $failed[] = [
                    'sku' => $sku !== '' ? $sku : '(unknown)',
                    'message' => $this->extractAmazonErrorMessage($row) ?: 'Amazon rejected this product ad.',
                ];
            }
        }

        $ok = $added !== [];
        $msgParts = [];
        if ($added !== []) {
            $n = count($added);
            $msgParts[] = $n === 1 ? '1 SKU added to the campaign' : $n.' SKUs added to the campaign';
        }
        if ($failed !== []) {
            $n = count($failed);
            $msgParts[] = $n === 1 ? '1 failed' : $n.' failed';
        }

        return [
            'success' => $ok,
            'message' => $msgParts !== [] ? implode('. ', $msgParts).'.' : 'No product ads were accepted.',
            'campaign_id' => $campaignId,
            'ad_group_id' => $adGroupId,
            'added' => $added,
            'failed' => $failed,
            'profile_id' => $this->resolvedProfileId(),
        ];
    }

    /**
     * Create campaign-level negative keywords (v3).
     * Match types: NEGATIVE_EXACT, NEGATIVE_PHRASE (Amazon SP campaign rules).
     *
     * @param  list<array{campaignId: string, keywordText: string, matchType: string, state?: string}>  $keywords
     * @return array<string, mixed>
     */
    public function createCampaignNegativeKeywords(array $keywords): array
    {
        $items = [];
        foreach ($keywords as $kw) {
            $items[] = [
                'campaignId' => (string) ($kw['campaignId'] ?? ''),
                'keywordText' => (string) ($kw['keywordText'] ?? ''),
                'matchType' => (string) ($kw['matchType'] ?? 'NEGATIVE_PHRASE'),
                'state' => (string) ($kw['state'] ?? 'ENABLED'),
            ];
        }

        return $this->post('/sp/campaignNegativeKeywords', [
            'campaignNegativeKeywords' => $items,
        ], [
            'Content-Type' => 'application/vnd.spCampaignNegativeKeyword.v3+json',
            'Accept' => 'application/vnd.spCampaignNegativeKeyword.v3+json',
        ]);
    }

    /**
     * Archive a Sponsored Products campaign.
     * PUT /sp/campaigns rejects state=ARCHIVED (only ENABLED/PROPOSED/PAUSED);
     * archiving is done via POST /sp/campaigns/delete.
     *
     * @return array{success: bool, message?: string, response?: array}
     */
    public function archiveCampaign(string $campaignId): array
    {
        $campaignId = trim($campaignId);
        if ($campaignId === '') {
            return ['success' => false, 'message' => 'Campaign ID is required.'];
        }

        try {
            $response = $this->deleteCampaigns([$campaignId]);

            $errors = data_get($response, 'campaigns.error', []);
            if (is_array($errors) && $errors !== []) {
                $msg = $this->formatAmazonMultiStatusError($errors);

                return ['success' => false, 'message' => $msg !== '' ? $msg : 'Amazon rejected campaign archive.', 'response' => $response];
            }

            $success = data_get($response, 'campaigns.success', []);
            if (! is_array($success) || $success === []) {
                return [
                    'success' => false,
                    'message' => 'Amazon did not confirm campaign archive.',
                    'response' => $response,
                ];
            }

            return ['success' => true, 'response' => $response];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $this->formatAmazonException($e)];
        }
    }

    /**
     * Create Sponsored Products positive keywords (v3) on an ad group.
     * Match types: BROAD, PHRASE, EXACT (MANUAL campaigns only).
     *
     * @param  list<array{campaignId: string, adGroupId: string, keywordText: string, matchType: string, state?: string, bid?: float}>  $keywords
     * @return array<string, mixed>
     */
    public function createKeywords(array $keywords): array
    {
        $items = [];
        foreach ($keywords as $kw) {
            $item = [
                'campaignId' => (string) ($kw['campaignId'] ?? ''),
                'adGroupId' => (string) ($kw['adGroupId'] ?? ''),
                'keywordText' => (string) ($kw['keywordText'] ?? ''),
                'matchType' => (string) ($kw['matchType'] ?? 'PHRASE'),
                'state' => (string) ($kw['state'] ?? 'ENABLED'),
            ];
            if (isset($kw['bid']) && is_numeric($kw['bid'])) {
                $item['bid'] = round((float) $kw['bid'], 2);
            }
            $items[] = $item;
        }

        return $this->post('/sp/keywords', [
            'keywords' => $items,
        ], [
            'Content-Type' => 'application/vnd.spKeyword.v3+json',
            'Accept' => 'application/vnd.spKeyword.v3+json',
        ]);
    }

    /**
     * Create one PAUSED Sponsored Products campaign + ad group + seller product ads.
     * AUTO = PT-style; MANUAL = KW-style (required for positive keywords).
     *
     * @param  list<string>  $sellerSkus  Seller SKUs (not ASINs) for product ads
     * @return array{success: bool, message?: string, campaign_id?: string, ad_group_id?: string, campaign_name?: string, targeting_type?: string, product_ads_created?: int, errors?: list<string>}
     */
    public function createPausedAutoCampaignWithProductAds(
        string $campaignName,
        array $sellerSkus,
        float $dailyBudget = 3.0,
        float $defaultBid = 0.60,
        string $targetingType = 'AUTO'
    ): array {
        $campaignName = trim($campaignName);
        $targetingType = strtoupper(trim($targetingType)) === 'MANUAL' ? 'MANUAL' : 'AUTO';
        $sellerSkus = array_values(array_unique(array_filter(array_map(
            static fn ($s) => trim((string) $s),
            $sellerSkus
        ))));

        if ($campaignName === '') {
            return ['success' => false, 'message' => 'Campaign name is required.'];
        }
        if ($sellerSkus === []) {
            return ['success' => false, 'message' => 'At least one seller SKU is required for product ads.'];
        }
        if ($dailyBudget < 1) {
            $dailyBudget = 1.0;
        }
        if ($defaultBid < 0.02) {
            $defaultBid = 0.02;
        }

        $errors = [];

        try {
            $campaignResp = $this->createCampaigns([
                [
                    'name' => $campaignName,
                    'targetingType' => $targetingType,
                    'state' => 'PAUSED',
                    'budget' => [
                        'budget' => round($dailyBudget, 2),
                        'budgetType' => 'DAILY',
                    ],
                    'startDate' => now()->format('Y-m-d'),
                    'dynamicBidding' => [
                        'strategy' => 'LEGACY_FOR_SALES',
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Create campaign failed: '.$this->formatAmazonException($e)];
        }

        $campaignId = (string) (data_get($campaignResp, 'campaigns.success.0.campaignId')
            ?? data_get($campaignResp, 'campaigns.success.0.campaign.campaignId')
            ?? '');
        if ($campaignId === '') {
            $msg = $this->formatAmazonMultiStatusError(data_get($campaignResp, 'campaigns.error', []));

            return [
                'success' => false,
                'message' => $msg !== '' ? $msg : 'Amazon did not return a campaignId.',
                'response' => $campaignResp,
            ];
        }

        try {
            $adGroupResp = $this->createAdGroups([
                [
                    'name' => mb_substr($campaignName, 0, 240).' AG',
                    'campaignId' => $campaignId,
                    'defaultBid' => round($defaultBid, 2),
                    'state' => 'ENABLED',
                ],
            ]);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Campaign created but ad group failed: '.$this->formatAmazonException($e),
                'campaign_id' => $campaignId,
                'campaign_name' => $campaignName,
            ];
        }

        $adGroupId = (string) (data_get($adGroupResp, 'adGroups.success.0.adGroupId')
            ?? data_get($adGroupResp, 'adGroups.success.0.adGroup.adGroupId')
            ?? '');
        if ($adGroupId === '') {
            $msg = $this->formatAmazonMultiStatusError(data_get($adGroupResp, 'adGroups.error', []));

            return [
                'success' => false,
                'message' => $msg !== '' ? $msg : 'Amazon did not return an adGroupId.',
                'campaign_id' => $campaignId,
                'campaign_name' => $campaignName,
                'response' => $adGroupResp,
            ];
        }

        $createdAds = 0;
        foreach (array_chunk($sellerSkus, 100) as $chunk) {
            $payload = [];
            foreach ($chunk as $sku) {
                $payload[] = [
                    'campaignId' => $campaignId,
                    'adGroupId' => $adGroupId,
                    'sku' => $sku,
                    'state' => 'ENABLED',
                ];
            }

            try {
                $adsResp = $this->createProductAds($payload);
            } catch (\Throwable $e) {
                $errors[] = $this->formatAmazonException($e);

                continue;
            }

            $successList = data_get($adsResp, 'productAds.success', []);
            if (is_array($successList)) {
                $createdAds += count($successList);
            }
            $errList = data_get($adsResp, 'productAds.error', []);
            if (is_array($errList) && $errList !== []) {
                $fmt = $this->formatAmazonMultiStatusError($errList);
                if ($fmt !== '') {
                    $errors[] = $fmt;
                }
            }
        }

        if ($createdAds === 0) {
            return [
                'success' => false,
                'message' => 'Campaign and ad group created, but no product ads were accepted.'
                    .($errors !== [] ? ' '.implode(' | ', array_slice($errors, 0, 3)) : ''),
                'campaign_id' => $campaignId,
                'ad_group_id' => $adGroupId,
                'campaign_name' => $campaignName,
                'product_ads_created' => 0,
                'errors' => $errors,
            ];
        }

        return [
            'success' => true,
            'campaign_id' => $campaignId,
            'ad_group_id' => $adGroupId,
            'campaign_name' => $campaignName,
            'targeting_type' => $targetingType,
            'product_ads_created' => $createdAds,
            'errors' => $errors,
        ];
    }

    /**
     * First non-archived ad group id for a campaign (for positive keyword push).
     */
    public function resolvePrimaryAdGroupId(string $campaignId): string
    {
        $campaignId = trim($campaignId);
        if ($campaignId === '') {
            return '';
        }

        try {
            $resp = $this->getAdGroups($campaignId);
        } catch (\Throwable $e) {
            return '';
        }

        $list = $resp['adGroups'] ?? $resp['adGroupList'] ?? [];
        if (! is_array($list)) {
            return '';
        }

        foreach ($list as $ag) {
            if (! is_array($ag)) {
                continue;
            }
            $state = strtoupper(trim((string) ($ag['state'] ?? '')));
            if ($state === 'ARCHIVED') {
                continue;
            }
            $id = trim((string) ($ag['adGroupId'] ?? ''));
            if ($id !== '') {
                return $id;
            }
        }

        return '';
    }

    /**
     * Push ad-group positive keywords in batches of 100 (MANUAL SP campaigns).
     *
     * @param  list<string>  $keywords
     * @return array{success: bool, added: int, failed: int, duplicates: int, message: string, errors: list<string>, ad_group_id: string}
     */
    public function pushAdGroupPositiveKeywords(
        string $campaignId,
        string $adGroupId,
        array $keywords,
        string $matchType = 'PHRASE',
        float $bid = 0.50
    ): array {
        $campaignId = trim($campaignId);
        $adGroupId = trim($adGroupId);
        $matchType = strtoupper(trim($matchType));
        if (! in_array($matchType, ['BROAD', 'PHRASE', 'EXACT'], true)) {
            $matchType = 'PHRASE';
        }
        if ($bid < 0.02) {
            $bid = 0.02;
        }

        $texts = collect($keywords)
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique(fn ($t) => strtolower($t))
            ->values()
            ->all();

        if ($campaignId === '' || $adGroupId === '' || $texts === []) {
            return [
                'success' => false,
                'added' => 0,
                'failed' => 0,
                'duplicates' => 0,
                'message' => 'Campaign ID, ad group ID, and at least one keyword are required.',
                'errors' => [],
                'ad_group_id' => $adGroupId,
            ];
        }

        $added = 0;
        $failed = 0;
        $duplicates = 0;
        $errors = [];

        foreach (array_chunk($texts, 100) as $chunk) {
            $payload = [];
            foreach ($chunk as $text) {
                $payload[] = [
                    'campaignId' => $campaignId,
                    'adGroupId' => $adGroupId,
                    'keywordText' => $text,
                    'matchType' => $matchType,
                    'state' => 'ENABLED',
                    'bid' => round($bid, 2),
                ];
            }

            try {
                $resp = $this->createKeywords($payload);
            } catch (\Throwable $e) {
                $failed += count($chunk);
                $errors[] = $this->formatAmazonException($e);

                continue;
            }

            $successList = data_get($resp, 'keywords.success', []);
            if (is_array($successList)) {
                $added += count($successList);
            }

            $errorList = data_get($resp, 'keywords.error', []);
            if (is_array($errorList)) {
                foreach ($errorList as $e) {
                    $msg = $this->extractAmazonErrorMessage($e);
                    if (stripos($msg, 'duplicate') !== false) {
                        $duplicates++;
                    } else {
                        $failed++;
                        if ($msg !== '') {
                            $errors[] = $msg;
                        }
                    }
                }
            }
        }

        $errors = array_values(array_unique(array_filter($errors)));
        $ok = $added > 0 || ($failed === 0 && $duplicates > 0);
        $msg = "Added {$added} positive keyword(s).";
        if ($duplicates > 0) {
            $msg .= " ({$duplicates} already existed.)";
        }
        if ($failed > 0) {
            $msg .= " ({$failed} rejected.)";
            if ($errors !== []) {
                $msg .= ' '.implode(' | ', array_slice($errors, 0, 3));
            }
        }

        return [
            'success' => $ok,
            'added' => $added,
            'failed' => $failed,
            'duplicates' => $duplicates,
            'message' => $msg,
            'errors' => $errors,
            'ad_group_id' => $adGroupId,
        ];
    }

    /**
     * Push campaign-level negative keywords in batches of 100.
     *
     * @param  list<string>  $keywords
     * @return array{success: bool, added: int, failed: int, duplicates: int, message: string, errors: list<string>}
     */
    public function pushCampaignNegativeKeywords(
        string $campaignId,
        array $keywords,
        string $matchType = 'NEGATIVE_PHRASE'
    ): array {
        $campaignId = trim($campaignId);
        $matchType = strtoupper(trim($matchType));
        if (! in_array($matchType, ['NEGATIVE_EXACT', 'NEGATIVE_PHRASE'], true)) {
            $matchType = 'NEGATIVE_PHRASE';
        }

        $texts = collect($keywords)
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique(fn ($t) => strtolower($t))
            ->values()
            ->all();

        if ($campaignId === '' || $texts === []) {
            return [
                'success' => false,
                'added' => 0,
                'failed' => 0,
                'duplicates' => 0,
                'message' => 'Campaign ID and at least one keyword are required.',
                'errors' => [],
            ];
        }

        $added = 0;
        $failed = 0;
        $duplicates = 0;
        $errors = [];

        foreach (array_chunk($texts, 100) as $chunk) {
            $payload = [];
            foreach ($chunk as $text) {
                $payload[] = [
                    'campaignId' => $campaignId,
                    'keywordText' => $text,
                    'matchType' => $matchType,
                    'state' => 'ENABLED',
                ];
            }

            try {
                $resp = $this->createCampaignNegativeKeywords($payload);
            } catch (\Throwable $e) {
                $failed += count($chunk);
                $errors[] = $this->formatAmazonException($e);

                continue;
            }

            $successList = data_get($resp, 'campaignNegativeKeywords.success', []);
            if (is_array($successList)) {
                $added += count($successList);
            }

            $errorList = data_get($resp, 'campaignNegativeKeywords.error', []);
            if (is_array($errorList)) {
                foreach ($errorList as $e) {
                    $msg = $this->extractAmazonErrorMessage($e);
                    if (stripos($msg, 'duplicate') !== false) {
                        $duplicates++;
                    } else {
                        $failed++;
                        if ($msg !== '') {
                            $errors[] = $msg;
                        }
                    }
                }
            }
        }

        $errors = array_values(array_unique(array_filter($errors)));
        $ok = $added > 0 || ($failed === 0 && $duplicates > 0);
        $msg = "Added {$added} campaign negative keyword(s).";
        if ($duplicates > 0) {
            $msg .= " ({$duplicates} already existed.)";
        }
        if ($failed > 0) {
            $msg .= " ({$failed} rejected.)";
            if ($errors !== []) {
                $msg .= ' '.implode(' | ', array_slice($errors, 0, 3));
            }
        }

        return [
            'success' => $ok,
            'added' => $added,
            'failed' => $failed,
            'duplicates' => $duplicates,
            'message' => $msg,
            'errors' => $errors,
        ];
    }

    /**
     * @param  mixed  $errorList
     */
    protected function formatAmazonMultiStatusError($errorList): string
    {
        if (! is_array($errorList) || $errorList === []) {
            return '';
        }

        $parts = [];
        foreach ($errorList as $e) {
            $msg = $this->extractAmazonErrorMessage($e);
            if ($msg !== '') {
                $parts[] = $msg;
            }
        }

        return implode(' | ', array_slice(array_values(array_unique($parts)), 0, 5));
    }

    /**
     * @param  mixed  $error
     */
    protected function extractAmazonErrorMessage($error): string
    {
        if (! is_array($error)) {
            return trim((string) $error);
        }

        $candidates = [
            data_get($error, 'errors.0.errorValue.message'),
            data_get($error, 'errors.0.message'),
            data_get($error, 'errorValue.message'),
            data_get($error, 'message'),
            data_get($error, 'errorMessage'),
        ];
        foreach ($candidates as $c) {
            $c = trim((string) $c);
            if ($c !== '') {
                return $c;
            }
        }

        return '';
    }

    protected function formatAmazonException(\Throwable $e): string
    {
        $msg = $e->getMessage();
        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $body = (string) $e->getResponse()->getBody();
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $fromMulti = $this->formatAmazonMultiStatusError(
                    data_get($decoded, 'campaigns.error')
                    ?? data_get($decoded, 'adGroups.error')
                    ?? data_get($decoded, 'productAds.error')
                    ?? data_get($decoded, 'campaignNegativeKeywords.error')
                    ?? data_get($decoded, 'keywords.error')
                    ?? []
                );
                if ($fromMulti !== '') {
                    return $fromMulti;
                }
                $detail = (string) (data_get($decoded, 'message')
                    ?? data_get($decoded, 'details')
                    ?? data_get($decoded, 'code')
                    ?? '');
                if ($detail !== '') {
                    return $detail;
                }
            }
            if ($body !== '') {
                return mb_substr($body, 0, 400);
            }
        }

        return $msg;
    }

    /**
     * Request a Sponsored Products search term report (Reporting API v3, async).
     * Returns the create-report payload (e.g. reportId); download after status is COMPLETED.
     * No caching — each call hits Amazon with a new request.
     *
     */
    public function getSearchTerms(?string $startDate = null, ?string $endDate = null): array
    {
        $end = $endDate ?? now()->subDay()->toDateString();
        $start = $startDate ?? $end;
        $timeUnit = ($start === $end) ? 'DAILY' : 'SUMMARY';

        return $this->post('/reporting/reports', [
            'name' => 'spSearchTerm_'.uniqid('', true),
            'startDate' => $start,
            'endDate' => $end,
            'configuration' => [
                'adProduct' => 'SPONSORED_PRODUCTS',
                'reportTypeId' => 'spSearchTerm',
                'timeUnit' => $timeUnit,
                'groupBy' => ['searchTerm'],
                'columns' => [
                    'searchTerm',
                    'campaignName',
                    'adGroupName',
                    'keyword',
                    'matchType',
                    'impressions',
                    'clicks',
                    'cost',
                    'purchases14d',
                    'sales14d',
                ],
                'format' => 'GZIP_JSON',
            ],
        ], [
            'Content-Type' => 'application/vnd.createasyncreportrequest.v3+json',
            'Accept' => 'application/vnd.createasyncreportrequest.v3+json',
        ]);
    }

    /**
     */
    protected function request(string $method, string $path, array $options = [], bool $withProfileScope = true): array
    {
        $url = $this->resolveUrl($path);
        $token = $this->getFreshAccessToken();

        $headers = array_merge([
            'Authorization' => 'Bearer '.$token,
            'Amazon-Advertising-API-ClientId' => $this->clientId,
        ], $options['headers'] ?? []);

        if ($withProfileScope) {
            $this->assertProfileScope();
            $headers['Amazon-Advertising-API-Scope'] = $this->resolvedProfileId();
        }

        $guzzle = ['headers' => $headers];

        if (! empty($options['query'])) {
            $guzzle['query'] = $options['query'];
        }

        if (array_key_exists('json', $options)) {
            $guzzle['json'] = $options['json'];
        }

        $response = $this->client->request($method, $url, $guzzle);

        return $this->decodeResponseBody((string) $response->getBody());
    }

    protected function resolveUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return $this->apiBase.'/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponseBody(string $body): array
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : ['_raw' => $body];
    }

    protected function assertOAuthConfig(): void
    {
        if ($this->clientId === '' || $this->clientSecret === '' || $this->refreshToken === '') {
            throw new InvalidArgumentException(
                'Amazon Ads OAuth is not configured. Set AMAZON_ADS_CLIENT_ID, AMAZON_ADS_CLIENT_SECRET, and AMAZON_ADS_REFRESH_TOKEN.'
            );
        }
    }

    protected function assertProfileScope(): void
    {
        if ($this->resolvedProfileId() === '') {
            throw new InvalidArgumentException(
                'Amazon Ads profile scope is not configured. Set AMAZON_ADS_PROFILE_IDS (used as Amazon-Advertising-API-Scope).'
            );
        }
    }
}
