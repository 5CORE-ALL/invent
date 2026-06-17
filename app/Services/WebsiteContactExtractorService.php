<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class WebsiteContactExtractorService
{
    private Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?: new Client([
            'timeout' => 6,
            'connect_timeout' => 3,
            'allow_redirects' => ['max' => 4],
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/125 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ],
        ]);
    }

    public function extract(?string $website, bool $quick = false): array
    {
        $website = $this->normalizeUrl($website);

        if (! $website) {
            Log::info('Website contact extractor skipped empty website');

            return [
                'emails' => [],
                'phones' => [],
                'social_links' => [],
            ];
        }

        Log::info('Website contact extractor started', [
            'website' => $website,
            'quick' => $quick,
        ]);

        $pages = [$website];
        $html = $this->fetch($website);

        if ($html) {
            $pages = array_merge($pages, $this->discoverContactPages($website, $html));
        }

        $emails = [];
        $phones = [];
        $socialLinks = [];

        $maxPages = $quick ? 1 : 3;

        foreach (array_slice(array_unique($pages), 0, $maxPages) as $pageUrl) {
            $pageHtml = $pageUrl === $website ? $html : $this->fetch($pageUrl);

            if (! $pageHtml) {
                continue;
            }

            $emails = array_merge($emails, $this->extractEmails($pageHtml));
            $phones = array_merge($phones, $this->extractPhones($pageHtml));
            $socialLinks = array_merge($socialLinks, $this->extractSocialLinks($pageHtml));
        }

        $result = [
            'emails' => array_values(array_unique($emails)),
            'phones' => array_values(array_unique($phones)),
            'social_links' => $this->normalizeSocialLinks($socialLinks),
        ];

        Log::info('Website contact extractor completed', [
            'website' => $website,
            'pages_checked' => count(array_unique($pages)),
            'email_count' => count($result['emails']),
            'phone_count' => count($result['phones']),
            'social_count' => count($result['social_links']),
        ]);

        return $result;
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function fetch(string $url): ?string
    {
        try {
            $response = $this->client->get($url);
            $statusCode = $response->getStatusCode();

            Log::info('Website contact extractor HTTP response received', [
                'url' => $url,
                'status_code' => $statusCode,
            ]);

            if ($statusCode >= 400) {
                return null;
            }

            return (string) $response->getBody();
        } catch (\Throwable $exception) {
            Log::warning('Website contact extractor HTTP request failed', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function discoverContactPages(string $baseUrl, string $html): array
    {
        $pages = [];

        if (! preg_match_all('/<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER)) {
            return $pages;
        }

        foreach ($matches as $match) {
            $label = strtolower(strip_tags($match[2]));
            $href = html_entity_decode($match[1], ENT_QUOTES);

            if (! preg_match('/contact|about|support|team|location/i', $label . ' ' . $href)) {
                continue;
            }

            $absoluteUrl = $this->toAbsoluteUrl($baseUrl, $href);

            if ($absoluteUrl) {
                $pages[] = $absoluteUrl;
            }
        }

        return $pages;
    }

    private function toAbsoluteUrl(string $baseUrl, string $href): ?string
    {
        if (str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:') || str_starts_with($href, '#')) {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = parse_url($baseUrl);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $path = str_starts_with($href, '/') ? $href : '/' . ltrim($href, '/');

        return $parts['scheme'] . '://' . $parts['host'] . $path;
    }

    private function extractEmails(string $html): array
    {
        $decoded = html_entity_decode($html, ENT_QUOTES);

        preg_match_all('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $decoded, $matches);

        return array_values(array_filter(array_unique($matches[0] ?? []), function (string $email): bool {
            $email = strtolower(trim($email));
            [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

            if (preg_match('/\.(png|jpg|jpeg|gif|webp|svg)$/i', $email)) {
                return false;
            }

            if (in_array($email, ['user@domain.com', 'test@test.com', 'email@example.com', 'example@example.com'], true)) {
                return false;
            }

            if (in_array($local, ['user', 'test', 'example', 'name', 'email', 'yourname', 'username'], true)) {
                return false;
            }

            if (preg_match('/example\.|domain\.com|wixpress\.com|sentry|sentry-next|localhost|invalid/i', $domain)) {
                return false;
            }

            if (preg_match('/^[a-f0-9]{24,}$/i', $local)) {
                return false;
            }

            return true;
        }));
    }

    private function extractPhones(string $html): array
    {
        $text = preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($html, ENT_QUOTES)));

        preg_match_all('/(?:\+?\d[\d\s().-]{7,}\d)/', $text, $matches);

        return array_values(array_filter(array_unique($matches[0] ?? []), function (string $phone): bool {
            $digits = preg_replace('/\D+/', '', $phone);

            if (strlen($digits) < 10 || strlen($digits) > 15) {
                return false;
            }

            $phone = trim($phone);

            if (preg_match('/^\d+\.\d+$/', $phone) || (str_contains($phone, ')') && ! str_contains($phone, '('))) {
                return false;
            }

            return preg_match('/^\+|\(\d{2,4}\)|\d{2,4}[-.]\d{2,4}|\d{3}\s\d{3}\s\d{4}/', $phone) === 1;
        }));
    }

    private function extractSocialLinks(string $html): array
    {
        preg_match_all('#https?://(?:www\.)?(?:facebook|instagram|linkedin|twitter|x|youtube|tiktok)\.com/[^"\'\s<)]+#i', $html, $matches);

        return $this->normalizeSocialLinks($matches[0] ?? []);
    }

    private function normalizeSocialLinks(array $links): array
    {
        $normalized = [];

        foreach ($links as $link) {
            $link = rtrim(html_entity_decode((string) $link, ENT_QUOTES), '.,;/"\'');
            $parts = parse_url($link);

            if (! isset($parts['host'])) {
                continue;
            }

            $host = strtolower(preg_replace('/^www\./', '', $parts['host']));

            if (! preg_match('/^(facebook|instagram|linkedin|twitter|x|youtube|tiktok)\.com$/', $host)) {
                continue;
            }

            // Keep one clean profile per platform per row. Pages often repeat the same
            // platform links many times in headers, footers, widgets, and tracking code.
            if (! isset($normalized[$host])) {
                $path = isset($parts['path']) ? rtrim($parts['path'], '/') : '';
                $normalized[$host] = 'https://' . $host . $path;
            }
        }

        return array_values($normalized);
    }
}
