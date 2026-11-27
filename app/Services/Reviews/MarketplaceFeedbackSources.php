<?php

namespace App\Services\Reviews;

use App\Models\Ebay2Metric;
use App\Models\Ebay3Metric;
use App\Models\EbayMetric;
use App\Models\ReverbProduct;
use App\Services\ReverbApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Marketplaces whose API returns the buyer feedback of the whole seller account (not per product):
 *   - eBay 1 / 2 / 3: Trading API GetFeedback (FeedbackReceivedAsSeller), every comment has the ItemID
 *   - Reverb: GET /api/my/feedback/received, every entry links to the listing
 * Each page comes back as normalized reviews plus the marketplace item id, so the fetcher can map it to a SKU.
 */
class MarketplaceFeedbackSources
{
    /** @var list<string> */
    public const SLUGS = ['ebay1', 'ebay2', 'ebay3', 'reverb'];

    public static function handles(string $marketplace): bool
    {
        return in_array(strtolower($marketplace), self::SLUGS, true);
    }

    /**
     * @return array{reviews: list<array<string, mixed>>, total_pages: int, error: string|null}
     */
    public function page(string $marketplace, int $page): array
    {
        try {
            return $marketplace === 'reverb'
                ? $this->reverbPage($page)
                : $this->ebayPage($marketplace, $page);
        } catch (\Throwable $e) {
            Log::warning('MarketplaceFeedbackSources failed', ['marketplace' => $marketplace, 'page' => $page, 'error' => $e->getMessage()]);

            return ['reviews' => [], 'total_pages' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Marketplace item id → our SKU.
     *
     * @param  list<string>  $itemIds
     * @return array<string, string>
     */
    public function skuMap(string $marketplace, array $itemIds): array
    {
        $itemIds = array_values(array_unique(array_filter(array_map('strval', $itemIds))));
        if ($itemIds === []) {
            return [];
        }
        [$model, $idColumn] = match ($marketplace) {
            'ebay1' => [EbayMetric::class, 'item_id'],
            'ebay2' => [Ebay2Metric::class, 'item_id'],
            'ebay3' => [Ebay3Metric::class, 'item_id'],
            'reverb' => [ReverbProduct::class, 'reverb_listing_id'],
            default => [null, null],
        };
        if ($model === null) {
            return [];
        }

        $map = [];
        foreach (array_chunk($itemIds, 500) as $chunk) {
            $model::query()->whereIn($idColumn, $chunk)->whereNotNull('sku')->where('sku', '!=', '')
                ->get([$idColumn, 'sku'])
                ->each(function ($row) use (&$map, $idColumn) {
                    $map[(string) $row->{$idColumn}] ??= trim((string) $row->sku);
                });
        }

        return $map;
    }

    /**
     * @return array{reviews: list<array<string, mixed>>, total_pages: int, error: string|null}
     */
    protected function ebayPage(string $slug, int $page): array
    {
        $cfg = $this->ebayConfig($slug);
        if ($cfg === null) {
            return ['reviews' => [], 'total_pages' => 0, 'error' => $slug.' API credentials are missing'];
        }
        $token = $this->ebayToken($slug, $cfg);
        if ($token === null) {
            return ['reviews' => [], 'total_pages' => 0, 'error' => 'Could not get an eBay access token for '.$slug];
        }
        $userId = $this->ebaySellerId($slug, $cfg, $token);
        if ($userId === null) {
            return ['reviews' => [], 'total_pages' => 0, 'error' => 'Could not read the eBay seller id for '.$slug];
        }

        $res = $this->trading($cfg, $token, 'GetFeedback',
            '<UserID>'.htmlspecialchars($userId).'</UserID><FeedbackType>FeedbackReceivedAsSeller</FeedbackType><DetailLevel>ReturnAll</DetailLevel>'
            .'<Pagination><EntriesPerPage>200</EntriesPerPage><PageNumber>'.$page.'</PageNumber></Pagination>');

        $ack = $res['Ack'] ?? 'Failure';
        if ($ack !== 'Success' && $ack !== 'Warning') {
            $msg = $res['Errors']['LongMessage'] ?? ($res['Errors'][0]['LongMessage'] ?? ($res['raw'] ?? 'GetFeedback failed'));

            return ['reviews' => [], 'total_pages' => 0, 'error' => is_string($msg) ? $msg : 'GetFeedback failed'];
        }

        $details = $res['FeedbackDetailArray']['FeedbackDetail'] ?? [];
        if (isset($details['FeedbackID'])) {
            $details = [$details];
        }

        $reviews = [];
        foreach ((array) $details as $d) {
            if (! is_array($d)) {
                continue;
            }
            $text = trim((string) ($d['CommentText'] ?? ''));
            // A bare thumbs-up with no comment has nothing to show as a review.
            if ($text === '') {
                continue;
            }
            $reviews[] = [
                'external_id' => 'ebay-'.($d['FeedbackID'] ?? ''),
                'item_id' => (string) ($d['ItemID'] ?? ''),
                'item_title' => (string) ($d['ItemTitle'] ?? ''),
                // eBay feedback is Positive / Neutral / Negative, mapped to 5 / 3 / 1 stars.
                'rating' => match ($d['CommentType'] ?? '') {
                    'Positive' => 5,
                    'Neutral' => 3,
                    'Negative' => 1,
                    default => null,
                },
                'title' => '',
                'body' => $text,
                'reviewer' => (string) ($d['CommentingUser'] ?? ''),
                'date' => (string) ($d['CommentTime'] ?? ''),
                'verified' => true,
            ];
        }

        return [
            'reviews' => $reviews,
            'total_pages' => max(1, (int) ($res['PaginationResult']['TotalNumberOfPages'] ?? 1)),
            'error' => null,
        ];
    }

    /**
     * @return array{reviews: list<array<string, mixed>>, total_pages: int, error: string|null}
     */
    protected function reverbPage(int $page): array
    {
        $token = ReverbApiService::getReverbBearerToken();
        if (! $token) {
            return ['reviews' => [], 'total_pages' => 0, 'error' => 'Reverb API token is not configured'];
        }

        $res = Http::withoutVerifying()->timeout(60)->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/hal+json',
            'Accept-Version' => '3.0',
        ])->get('https://reverb.com/api/my/feedback/received', ['per_page' => 50, 'page' => $page]);

        if (! $res->successful()) {
            return ['reviews' => [], 'total_pages' => 0, 'error' => 'Reverb HTTP '.$res->status().' (the token needs the read_feedback scope)'];
        }

        $reviews = [];
        foreach ((array) ($res->json('feedbacks') ?? []) as $f) {
            if (! is_array($f)) {
                continue;
            }
            $text = trim((string) ($f['message'] ?? ''));
            if ($text === '') {
                continue;
            }
            $selfHref = (string) ($f['_links']['self']['href'] ?? '');
            $listingHref = (string) ($f['_links']['listing']['href'] ?? '');
            $reviews[] = [
                'external_id' => $selfHref !== '' ? 'reverb-'.basename($selfHref) : '',
                'item_id' => preg_match('#/item/(\d+)#', $listingHref, $m) ? $m[1] : '',
                'item_title' => (string) ($f['order_title'] ?? ''),
                'rating' => isset($f['rating']) && is_numeric($f['rating']) ? (int) $f['rating'] : null,
                'title' => '',
                'body' => $text,
                'reviewer' => (string) ($f['author_name'] ?? ''),
                'date' => (string) ($f['created_at'] ?? ''),
                'url' => $listingHref,
                'verified' => true,
            ];
        }

        return ['reviews' => $reviews, 'total_pages' => max(1, (int) $res->json('total_pages', 1)), 'error' => null];
    }

    /**
     * @return array{app_id: string, cert_id: string, dev_id: string, refresh_token: string}|null
     */
    protected function ebayConfig(string $slug): ?array
    {
        $key = match ($slug) {
            'ebay1' => 'services.ebay1',
            'ebay2' => 'services.ebay2',
            'ebay3' => 'services.ebay3',
            default => null,
        };
        if ($key === null) {
            return null;
        }
        $cfg = [
            'app_id' => trim((string) config($key.'.app_id')),
            'cert_id' => trim((string) config($key.'.cert_id')),
            'dev_id' => trim((string) (config($key.'.dev_id') ?: config('services.ebay.dev_id'))),
            'refresh_token' => trim((string) config($key.'.refresh_token')),
        ];

        return ($cfg['app_id'] === '' || $cfg['cert_id'] === '' || $cfg['refresh_token'] === '') ? null : $cfg;
    }

    /** @param array<string, string> $cfg */
    protected function ebayToken(string $slug, array $cfg): ?string
    {
        return Cache::remember('reviews:ebay_token:'.$slug, now()->addMinutes(90), function () use ($cfg) {
            $r = Http::withoutVerifying()->asForm()->withBasicAuth($cfg['app_id'], $cfg['cert_id'])
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $cfg['refresh_token'],
                ]);

            return $r->successful() ? (string) $r->json('access_token') : null;
        });
    }

    /** @param array<string, string> $cfg */
    protected function ebaySellerId(string $slug, array $cfg, string $token): ?string
    {
        return Cache::remember('reviews:ebay_user:'.$slug, now()->addDay(), function () use ($cfg, $token) {
            $u = $this->trading($cfg, $token, 'GetUser', '');
            $id = $u['User']['UserID'] ?? null;

            return is_string($id) && $id !== '' ? $id : null;
        });
    }

    /**
     * @param  array<string, string>  $cfg
     * @return array<string, mixed>
     */
    protected function trading(array $cfg, string $token, string $call, string $inner): array
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?><'.$call.'Request xmlns="urn:ebay:apis:eBLBaseComponents">'.$inner.'</'.$call.'Request>';
        $r = Http::withoutVerifying()->timeout(60)->withHeaders([
            'X-EBAY-API-COMPATIBILITY-LEVEL' => (string) config('services.ebay.compat_level', '1189'),
            'X-EBAY-API-DEV-NAME' => $cfg['dev_id'],
            'X-EBAY-API-APP-NAME' => $cfg['app_id'],
            'X-EBAY-API-CERT-NAME' => $cfg['cert_id'],
            'X-EBAY-API-CALL-NAME' => $call,
            'X-EBAY-API-SITEID' => (string) config('services.ebay.site_id', 0),
            'X-EBAY-API-IAF-TOKEN' => $token,
            'Content-Type' => 'text/xml',
        ])->withBody($xml, 'text/xml')->post((string) config('services.ebay.trading_api_endpoint', 'https://api.ebay.com/ws/api.dll'));

        libxml_use_internal_errors(true);
        $parsed = @simplexml_load_string($r->body());

        return $parsed ? (json_decode(json_encode($parsed), true) ?: []) : ['raw' => 'eBay HTTP '.$r->status().' unreadable response'];
    }
}
