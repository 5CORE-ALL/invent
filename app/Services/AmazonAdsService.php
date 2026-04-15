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
            $headers['Amazon-Advertising-API-Scope'] = $this->profileId;
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
        if ($this->profileId === '') {
            throw new InvalidArgumentException(
                'Amazon Ads profile scope is not configured. Set AMAZON_ADS_PROFILE_IDS (used as Amazon-Advertising-API-Scope).'
            );
        }
    }
}
