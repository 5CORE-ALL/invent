<?php

namespace App\SocialMedia\Platforms\Concerns;

use App\SocialMedia\Exceptions\SocialMediaApiException;
use App\SocialMedia\Exceptions\SocialMediaAuthException;
use App\SocialMedia\Exceptions\SocialMediaRateLimitException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

trait CallsOfficialApi
{
    protected function officialGet(string $url, string $token, array $query = [], array $headers = []): array
    {
        $pending = Http::withToken($token)->acceptJson()->timeout(30);
        if ($headers !== []) {
            $pending = $pending->withHeaders($headers);
        }

        return $this->decodeOfficial($pending->get($url, $query));
    }

    protected function officialPost(string $url, string $token, array $body = [], array $query = [], array $headers = []): array
    {
        $pending = Http::withToken($token)->acceptJson()->timeout(30);
        if ($headers !== []) {
            $pending = $pending->withHeaders($headers);
        }

        return $this->decodeOfficial($pending->post($url.($query === [] ? '' : '?'.http_build_query($query)), $body));
    }

    protected function decodeOfficial(Response $response): array
    {
        if ($response->status() === 429) {
            throw new SocialMediaRateLimitException();
        }
        if (in_array($response->status(), [401, 403], true)) {
            throw new SocialMediaAuthException($this->authMessage($response));
        }
        if (! $response->successful()) {
            $message = (string) ($response->json('error.message') ?? $response->json('message') ?? 'The platform API returned an error.');
            throw new SocialMediaApiException($message);
        }

        return $response->json() ?? [];
    }

    protected function authMessage(Response $response): string
    {
        $code = (int) ($response->json('error.code') ?? 0);
        if ($code === 190 || $response->status() === 401) {
            return 'Access token expired or was rejected — reconnect the account.';
        }

        return 'The account is missing the API permission required for this sync — reconnect and grant the requested permissions.';
    }

    /**
     * Follow paging.next without replacing the query string the API already returned.
     */
    protected function officialGetAbsolute(string $url, string $token, array $headers = []): array
    {
        $pending = Http::withToken($token)->acceptJson()->timeout(30);
        if ($headers !== []) {
            $pending = $pending->withHeaders($headers);
        }

        return $this->decodeOfficial($pending->get($url));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function collectPages(string $firstUrl, string $token, array $query, int $maxPages): array
    {
        $pages = [];
        $payload = $this->officialGet($firstUrl, $token, $query);
        $pages[] = $payload;
        $next = $payload['paging']['next'] ?? null;
        for ($i = 1; $i < $maxPages && is_string($next) && $next !== ''; $i++) {
            $payload = $this->officialGetAbsolute($next, $token);
            $pages[] = $payload;
            $next = $payload['paging']['next'] ?? null;
        }

        return $pages;
    }
}
