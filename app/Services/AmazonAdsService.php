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
     * @return array{success: bool, message?: string, count?: int, ads?: array<int, array>}
     */
    public function fetchAllProductAds(array $states = ['ENABLED', 'PAUSED']): array
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
                $response = $this->listProductAdsPage(null, $states, $nextToken);
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
     * Update Sponsored Products campaigns (v3) — budget, state (ENABLED/PAUSED/ARCHIVED), etc.
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
     * Archive a Sponsored Products campaign (Amazon has no hard delete — use ARCHIVED).
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
            $response = $this->updateCampaigns([
                [
                    'campaignId' => $campaignId,
                    'state' => 'ARCHIVED',
                ],
            ]);

            $errors = data_get($response, 'campaigns.error', []);
            if (is_array($errors) && $errors !== []) {
                $msg = $this->formatAmazonMultiStatusError($errors);

                return ['success' => false, 'message' => $msg !== '' ? $msg : 'Amazon rejected campaign archive.', 'response' => $response];
            }

            return ['success' => true, 'response' => $response];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $this->formatAmazonException($e)];
        }
    }

    /**
     * Create one PAUSED AUTO Sponsored Products campaign + ad group + seller product ads.
     * Amazon equivalent of a parent-level Shopping campaign targeting specific child listings.
     *
     * @param  list<string>  $sellerSkus  Seller SKUs (not ASINs) for product ads
     * @return array{success: bool, message?: string, campaign_id?: string, ad_group_id?: string, campaign_name?: string, product_ads_created?: int, errors?: list<string>}
     */
    public function createPausedAutoCampaignWithProductAds(
        string $campaignName,
        array $sellerSkus,
        float $dailyBudget = 10.0,
        float $defaultBid = 0.50
    ): array {
        $campaignName = trim($campaignName);
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
                    'targetingType' => 'AUTO',
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
            'product_ads_created' => $createdAds,
            'errors' => $errors,
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
