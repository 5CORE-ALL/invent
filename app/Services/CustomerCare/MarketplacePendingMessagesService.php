<?php

namespace App\Services\CustomerCare;

use App\Models\CcMessagesPending;
use App\Models\ChannelMaster;
use App\Services\AliExpressApiService;
use App\Services\Ebay2ApiService;
use App\Services\EbayApiService;
use App\Services\EbayThreeApiService;
use App\Services\FaireApiService;
use App\Services\ReverbApiService;
use App\Services\Temu2ApiService;
use App\Services\TemuApiService;
use App\Services\WalmartApiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use SimpleXMLElement;
use Throwable;

/**
 * Live pending-message counts from each marketplace's own seller API.
 */
class MarketplacePendingMessagesService
{
    public const STATUS_OK = 'ok';

    public const STATUS_UNSUPPORTED = 'unsupported';

    public const STATUS_ERROR = 'error';

    /**
     * @return array{
     *     channel_id: int,
     *     pending_count: int,
     *     fetch_status: string,
     *     fetch_note: ?string,
     *     source: ?string,
     *     last_fetched_at: ?string
     * }
     */
    public function fetchAndStore(ChannelMaster $channel): array
    {
        $this->ensureSchema();

        try {
            $result = $this->fetch($channel);
        } catch (Throwable $e) {
            Log::warning('CC pending messages pull failed', [
                'channel_id' => $channel->id,
                'channel' => $channel->channel,
                'error' => $e->getMessage(),
            ]);
            $result = $this->makeResult(0, self::STATUS_ERROR, $e->getMessage(), null);
        }

        $user = Auth::user();
        $row = CcMessagesPending::query()->updateOrCreate(
            ['channel_id' => (int) $channel->id],
            [
                'pending_count' => (int) $result['pending_count'],
                'fetch_status' => $result['fetch_status'],
                'fetch_note' => $result['fetch_note'],
                'source' => $result['source'],
                'last_fetched_at' => now(),
                'updated_by_user_id' => $user?->id,
                'updated_by_name' => $user?->name,
            ]
        );

        return [
            'channel_id' => (int) $row->channel_id,
            'pending_count' => (int) $row->pending_count,
            'fetch_status' => (string) $row->fetch_status,
            'fetch_note' => $row->fetch_note,
            'source' => $row->source,
            'last_fetched_at' => optional($row->last_fetched_at)->toIso8601String(),
        ];
    }

    /**
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    public function fetch(ChannelMaster $channel): array
    {
        $driver = $this->driverFor($channel);
        if ($driver === null) {
            return $this->makeResult(
                0,
                self::STATUS_UNSUPPORTED,
                'No seller messages API is wired for this channel.',
                null
            );
        }

        return match ($driver) {
            'aliexpress' => $this->fetchAliExpress(),
            'amazon' => $this->makeResult(
                0,
                self::STATUS_UNSUPPORTED,
                'Amazon SP-API cannot read Seller Central buyer messages. Use the messages link.',
                'amazon'
            ),
            'ebay' => $this->fetchEbay('ebay'),
            'ebay2' => $this->fetchEbay('ebay2'),
            'ebay3' => $this->fetchEbay('ebay3'),
            'faire' => $this->fetchFaire(),
            'mirakl:bestbuy' => $this->fetchMirakl('bestbuy'),
            'mirakl:macy' => $this->fetchMirakl('macy'),
            'mirakl:purchasingpower' => $this->fetchMirakl('purchasingpower'),
            'reverb' => $this->fetchReverb(),
            'shopify' => $this->fetchShopify('shopify'),
            'shopify_b5c' => $this->fetchShopify('shopify_b5c'),
            'shopify_pls' => $this->fetchShopify('prolightsounds'),
            'temu' => $this->fetchTemu(app(TemuApiService::class), 'temu'),
            'temu2' => $this->fetchTemu(app(Temu2ApiService::class), 'temu2'),
            'walmart' => $this->fetchWalmart(),
            default => $this->makeResult(0, self::STATUS_UNSUPPORTED, 'No seller messages API is wired for this channel.', null),
        };
    }

    public function slugFor(ChannelMaster $channel): string
    {
        $raw = strtolower(trim((string) $channel->channel));
        $raw = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $raw) ?? $raw;

        return preg_replace('/[^a-z0-9]+/', '', $raw) ?? '';
    }

    public function driverFor(ChannelMaster $channel): ?string
    {
        $slug = $this->slugFor($channel);

        return match ($slug) {
            'aliexpress', 'ae' => 'aliexpress',
            'amazon', 'amazon1', 'amazonus' => 'amazon',
            'b2b', 'business5core', 'shopifyb2b' => 'shopify_b5c',
            'bestbuy', 'bestbuyusa' => 'mirakl:bestbuy',
            'ebay', 'ebay1' => 'ebay',
            'ebaytwo', 'ebay2' => 'ebay2',
            'ebaythree', 'ebay3' => 'ebay3',
            'faire' => 'faire',
            'macy', 'macys' => 'mirakl:macy',
            'pls', 'prolightsounds', 'shopifypls' => 'shopify_pls',
            'purchasingpower', 'pp' => 'mirakl:purchasingpower',
            'reverb' => 'reverb',
            'shopify', 'shopify1', '5core' => 'shopify',
            'temu' => 'temu',
            'temu2', 'temutwo' => 'temu2',
            'walmart' => 'walmart',
            default => null,
        };
    }

    /**
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchAliExpress(): array
    {
        $resp = app(AliExpressApiService::class)->getPendingMessageCount();
        if (empty($resp['success'])) {
            return $this->makeResult(0, self::STATUS_ERROR, (string) ($resp['message'] ?? 'AliExpress message pull failed.'), 'aliexpress');
        }

        return $this->makeResult((int) ($resp['count'] ?? 0), self::STATUS_OK, null, 'aliexpress');
    }

    /**
     * Unread member conversations, with Trading API fallback.
     *
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchEbay(string $account): array
    {
        $token = $this->ebayToken($account);
        if ($token === '') {
            return $this->makeResult(0, self::STATUS_ERROR, 'eBay credentials are missing.', $account);
        }

        $commerce = $this->ebayUnreadConversations($token);
        if ($commerce['ok']) {
            return $this->makeResult($commerce['count'], self::STATUS_OK, null, $account.':commerce.message');
        }

        $inbox = $this->ebayTradingUnreadInbox($account, $token);
        $asq = $this->ebayTradingUnansweredQuestions($account, $token);
        if ($inbox['ok'] || $asq['ok']) {
            $count = ($inbox['ok'] ? $inbox['count'] : 0) + ($asq['ok'] ? $asq['count'] : 0);
            $note = $inbox['ok'] && $asq['ok']
                ? null
                : trim(($inbox['ok'] ? '' : (string) $inbox['error']).' '.($asq['ok'] ? '' : (string) $asq['error']));

            return $this->makeResult($count, self::STATUS_OK, $note !== '' ? $note : null, $account.':trading');
        }

        $err = $commerce['error'] ?: $inbox['error'] ?: $asq['error'] ?: 'eBay message pull failed.';

        return $this->makeResult(0, self::STATUS_ERROR, $err, $account);
    }

    /**
     * @return array{ok: bool, count: int, error: ?string}
     */
    protected function ebayUnreadConversations(string $token): array
    {
        $total = 0;
        foreach (['FROM_MEMBERS', 'FROM_EBAY'] as $type) {
            try {
                $response = Http::withoutVerifying()
                    ->withToken($token)
                    ->acceptJson()
                    ->timeout(12)
                    ->connectTimeout(6)
                    ->get('https://api.ebay.com/commerce/message/v1/conversation', [
                        'conversation_type' => $type,
                        'conversation_status' => 'UNREAD',
                        'limit' => 1,
                    ]);
            } catch (Throwable $e) {
                return ['ok' => false, 'count' => 0, 'error' => $e->getMessage()];
            }

            if ($response->status() === 403 || $response->status() === 401) {
                return ['ok' => false, 'count' => 0, 'error' => 'eBay message scope is not granted on this refresh token.'];
            }
            if (! $response->successful()) {
                return ['ok' => false, 'count' => 0, 'error' => 'eBay conversations HTTP '.$response->status()];
            }

            $json = $response->json() ?? [];
            $total += max(0, (int) ($json['total'] ?? 0));
        }

        return ['ok' => true, 'count' => $total, 'error' => null];
    }

    /**
     * @return array{ok: bool, count: int, error: ?string}
     */
    protected function ebayTradingUnreadInbox(string $account, string $token): array
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><GetMyMessagesRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $xml->addChild('RequesterCredentials')->addChild('eBayAuthToken', $token);
        $xml->addChild('DetailLevel', 'ReturnSummary');
        $xml->addChild('FolderID', '0');

        $parsed = $this->ebayTradingCall($account, 'GetMyMessages', $xml->asXML() ?: '');
        if (! $parsed['ok']) {
            return $parsed;
        }

        $summary = $parsed['data']['Summary'] ?? [];
        $folders = $summary['FolderSummary'] ?? $summary;
        $count = 0;
        $rows = isset($folders[0]) ? $folders : [$folders];
        foreach ($rows as $folder) {
            if (! is_array($folder)) {
                continue;
            }
            foreach (['NewMessageCount', 'UnreadMessageCount', 'NewAlertCount'] as $key) {
                if (isset($folder[$key]) && is_numeric($folder[$key])) {
                    $count += (int) $folder[$key];
                    break;
                }
            }
        }
        if ($count === 0 && isset($summary['NewMessageCount'])) {
            $count = (int) $summary['NewMessageCount'];
        }

        return ['ok' => true, 'count' => max(0, $count), 'error' => null];
    }

    /**
     * @return array{ok: bool, count: int, error: ?string}
     */
    protected function ebayTradingUnansweredQuestions(string $account, string $token): array
    {
        $end = gmdate('Y-m-d\TH:i:s.000\Z');
        $start = gmdate('Y-m-d\TH:i:s.000\Z', strtotime('-90 days'));
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><GetMemberMessagesRequest xmlns="urn:ebay:apis:eBLBaseComponents"/>');
        $xml->addChild('RequesterCredentials')->addChild('eBayAuthToken', $token);
        $xml->addChild('MailMessageType', 'AskSellerQuestion');
        $xml->addChild('MessageStatus', 'Unanswered');
        $xml->addChild('StartCreationTime', $start);
        $xml->addChild('EndCreationTime', $end);
        $pagination = $xml->addChild('Pagination');
        $pagination->addChild('EntriesPerPage', '1');
        $pagination->addChild('PageNumber', '1');

        $parsed = $this->ebayTradingCall($account, 'GetMemberMessages', $xml->asXML() ?: '');
        if (! $parsed['ok']) {
            return $parsed;
        }

        $total = (int) (
            $parsed['data']['PaginationResult']['TotalNumberOfEntries']
            ?? $parsed['data']['MemberMessage']['PaginationResult']['TotalNumberOfEntries']
            ?? 0
        );

        return ['ok' => true, 'count' => max(0, $total), 'error' => null];
    }

    /**
     * @return array{ok: bool, count: int, error: ?string, data?: array}
     */
    protected function ebayTradingCall(string $account, string $call, string $xmlBody): array
    {
        $cfg = $this->ebayConfig($account);
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'X-EBAY-API-COMPATIBILITY-LEVEL' => $cfg['compat'],
                    'X-EBAY-API-DEV-NAME' => $cfg['dev'],
                    'X-EBAY-API-APP-NAME' => $cfg['app'],
                    'X-EBAY-API-CERT-NAME' => $cfg['cert'],
                    'X-EBAY-API-CALL-NAME' => $call,
                    'X-EBAY-API-SITEID' => $cfg['site'],
                    'Content-Type' => 'text/xml',
                ])
                ->timeout(12)
                ->connectTimeout(6)
                ->withBody($xmlBody, 'text/xml')
                ->post($cfg['endpoint']);
        } catch (Throwable $e) {
            return ['ok' => false, 'count' => 0, 'error' => $e->getMessage()];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string) $response->body());
        if ($xml === false) {
            return ['ok' => false, 'count' => 0, 'error' => $call.' returned invalid XML.'];
        }
        $data = json_decode(json_encode($xml), true);
        $ack = strtolower((string) ($data['Ack'] ?? ''));
        if (! in_array($ack, ['success', 'warning'], true)) {
            $err = $data['Errors']['LongMessage'] ?? $data['Errors'][0]['LongMessage'] ?? $data['Errors']['ShortMessage'] ?? $call.' failed.';

            return ['ok' => false, 'count' => 0, 'error' => is_array($err) ? json_encode($err) : (string) $err];
        }

        return ['ok' => true, 'count' => 0, 'error' => null, 'data' => is_array($data) ? $data : []];
    }

    protected function ebayToken(string $account): string
    {
        try {
            $token = match ($account) {
                'ebay2' => app(Ebay2ApiService::class)->generateBearerToken(),
                'ebay3' => app(EbayThreeApiService::class)->generateBearerToken(),
                default => app(EbayApiService::class)->generateBearerToken(),
            };
        } catch (Throwable $e) {
            Log::warning('eBay token for pending messages failed', ['account' => $account, 'error' => $e->getMessage()]);

            return '';
        }

        return is_string($token) ? $token : '';
    }

    /**
     * @return array{app: string, cert: string, dev: string, site: string, compat: string, endpoint: string}
     */
    protected function ebayConfig(string $account): array
    {
        $key = match ($account) {
            'ebay2' => 'ebay2',
            'ebay3' => 'ebay3',
            default => 'ebay',
        };

        return [
            'app' => (string) (config('services.'.$key.'.app_id') ?: config('services.ebay.app_id')),
            'cert' => (string) (config('services.'.$key.'.cert_id') ?: config('services.ebay.cert_id')),
            'dev' => (string) (config('services.'.$key.'.dev_id') ?: config('services.ebay.dev_id')),
            'site' => (string) config('services.ebay.site_id', 0),
            'compat' => (string) config('services.ebay.compat_level', '1189'),
            'endpoint' => (string) config('services.ebay.trading_api_endpoint', 'https://api.ebay.com/ws/api.dll'),
        ];
    }

    /**
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchMirakl(string $configKey): array
    {
        $apiKey = trim((string) config('services.'.$configKey.'.mcm_api_key', ''));
        $baseUrl = rtrim((string) config('services.'.$configKey.'.mcm_base_url', ''), '/');
        if ($apiKey === '' || $baseUrl === '') {
            return $this->makeResult(0, self::STATUS_ERROR, 'Mirakl shop API key is missing.', $configKey);
        }

        $count = 0;
        $pageToken = null;
        $pages = 0;
        do {
            $pages++;
            $query = ['with_messages' => 'false', 'limit' => 50];
            $shopId = config('services.'.$configKey.'.shop_id');
            if ($shopId !== null && $shopId !== '') {
                $query['shop_id'] = (int) $shopId;
            }
            if ($pageToken) {
                $query['page_token'] = $pageToken;
            }
            try {
                $response = Http::withoutVerifying()
                    ->withHeaders([
                        'Authorization' => $apiKey,
                        'Accept' => 'application/json',
                    ])
                    ->timeout(12)
                    ->connectTimeout(6)
                    ->get($baseUrl.'/api/inbox/threads', $query);
            } catch (Throwable $e) {
                return $this->makeResult(0, self::STATUS_ERROR, $e->getMessage(), $configKey.':mirakl');
            }
            if (! $response->successful()) {
                return $this->makeResult(0, self::STATUS_ERROR, 'Mirakl inbox HTTP '.$response->status(), $configKey.':mirakl');
            }
            $json = $response->json() ?? [];
            foreach ($json['data'] ?? [] as $thread) {
                $needed = $thread['metadata']['shop_reply_needed_since']
                    ?? $thread['shop_reply_needed_since']
                    ?? null;
                if ($needed) {
                    $count++;
                }
            }
            $pageToken = $json['next_page_token'] ?? null;
        } while ($pageToken && $pages < 20);

        return $this->makeResult($count, self::STATUS_OK, null, $configKey.':mirakl');
    }

    /**
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchWalmart(): array
    {
        $token = app(WalmartApiService::class)->getAccessToken();
        if (! $token) {
            return $this->makeResult(0, self::STATUS_ERROR, 'Walmart credentials are missing.', 'walmart');
        }

        $base = rtrim((string) config('services.walmart.api_endpoint', 'https://marketplace.walmartapis.com'), '/');
        $base = (string) preg_replace('#/v3$#', '', $base);
        $headers = [
            'WM_SEC.ACCESS_TOKEN' => $token,
            'WM_QOS.CORRELATION_ID' => (string) \Illuminate\Support\Str::uuid(),
            'WM_SVC.NAME' => 'Walmart Marketplace',
            'Accept' => 'application/json',
        ];

        foreach ([
            '/v3/questions?status=UNANSWERED',
            '/v3/qnas?status=UNANSWERED',
        ] as $path) {
            try {
                $response = Http::withoutVerifying()
                    ->withHeaders($headers)
                    ->timeout(12)
                    ->connectTimeout(6)
                    ->get($base.$path);
            } catch (Throwable $e) {
                return $this->makeResult(0, self::STATUS_ERROR, $e->getMessage(), 'walmart');
            }
            if ($response->status() === 404) {
                continue;
            }
            if (! $response->successful()) {
                return $this->makeResult(
                    0,
                    $response->status() === 403 ? self::STATUS_UNSUPPORTED : self::STATUS_ERROR,
                    'Walmart questions HTTP '.$response->status(),
                    'walmart'
                );
            }
            $json = $response->json() ?? [];
            $total = $json['totalItems'] ?? $json['totalCount'] ?? $json['meta']['totalCount'] ?? null;
            if (is_numeric($total)) {
                return $this->makeResult((int) $total, self::STATUS_OK, null, 'walmart');
            }
            $list = $json['questions'] ?? $json['payload'] ?? $json['items'] ?? [];
            if (is_array($list)) {
                return $this->makeResult(count($list), self::STATUS_OK, null, 'walmart');
            }
        }

        return $this->makeResult(0, self::STATUS_UNSUPPORTED, 'Walmart Partner API does not expose a seller inbox count.', 'walmart');
    }

    /**
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchShopify(string $configKey): array
    {
        $cfg = config('services.'.$configKey, []);
        $domain = preg_replace('#^https?://#', '', (string) ($cfg['domain'] ?? $cfg['store_url'] ?? ''));
        $domain = rtrim((string) $domain, '/');
        $token = (string) ($cfg['access_token'] ?? $cfg['password'] ?? '');
        if ($domain === '' || $token === '') {
            return $this->makeResult(0, self::STATUS_ERROR, 'Shopify credentials are missing.', $configKey);
        }

        $query = <<<'GQL'
        {
          conversationsCount(query: "status:open") { count }
        }
        GQL;

        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'X-Shopify-Access-Token' => $token,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(12)
                ->connectTimeout(6)
                ->post("https://{$domain}/admin/api/2024-10/graphql.json", ['query' => $query]);
        } catch (Throwable $e) {
            return $this->makeResult(0, self::STATUS_ERROR, $e->getMessage(), $configKey);
        }

        $json = $response->json() ?? [];
        if (isset($json['data']['conversationsCount']['count'])) {
            return $this->makeResult((int) $json['data']['conversationsCount']['count'], self::STATUS_OK, null, $configKey.':shopify');
        }

        $gqlError = $json['errors'][0]['message'] ?? null;

        return $this->makeResult(
            0,
            self::STATUS_UNSUPPORTED,
            $gqlError ?: 'Shopify Inbox conversations are not enabled for this store.',
            $configKey
        );
    }

    /**
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchFaire(): array
    {
        $token = app(FaireApiService::class)->getAccessToken();
        if (! $token) {
            return $this->makeResult(0, self::STATUS_ERROR, 'Faire credentials are missing.', 'faire');
        }
        $base = rtrim((string) config('services.faire.base_url', 'https://www.faire.com/external-api/v2'), '/');

        foreach (['/conversations', '/brand/conversations', '/messages'] as $path) {
            try {
                $response = Http::withoutVerifying()
                    ->withToken($token)
                    ->acceptJson()
                    ->timeout(12)
                    ->connectTimeout(6)
                    ->get($base.$path, ['status' => 'UNREAD']);
            } catch (Throwable $e) {
                return $this->makeResult(0, self::STATUS_ERROR, $e->getMessage(), 'faire');
            }
            if ($response->status() === 404) {
                continue;
            }
            if (! $response->successful()) {
                continue;
            }
            $json = $response->json() ?? [];
            $total = $json['total'] ?? $json['count'] ?? null;
            if (is_numeric($total)) {
                return $this->makeResult((int) $total, self::STATUS_OK, null, 'faire');
            }
            foreach (['conversations', 'messages', 'data'] as $key) {
                if (isset($json[$key]) && is_array($json[$key])) {
                    return $this->makeResult(count($json[$key]), self::STATUS_OK, null, 'faire');
                }
            }
        }

        return $this->makeResult(0, self::STATUS_UNSUPPORTED, 'Faire API does not expose a seller inbox count.', 'faire');
    }

    /**
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchReverb(): array
    {
        $token = ReverbApiService::getReverbBearerToken();
        if (! $token) {
            return $this->makeResult(0, self::STATUS_ERROR, 'Reverb credentials are missing.', 'reverb');
        }

        try {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->acceptJson()
                ->timeout(12)
                ->connectTimeout(6)
                ->get('https://api.reverb.com/api/my/conversations', [
                    'state' => 'awaiting_response',
                ]);
        } catch (Throwable $e) {
            return $this->makeResult(0, self::STATUS_ERROR, $e->getMessage(), 'reverb');
        }

        if ($response->status() === 404) {
            return $this->makeResult(0, self::STATUS_UNSUPPORTED, 'Reverb conversations API is not available.', 'reverb');
        }
        if (! $response->successful()) {
            return $this->makeResult(0, self::STATUS_ERROR, 'Reverb conversations HTTP '.$response->status(), 'reverb');
        }

        $json = $response->json() ?? [];
        $total = $json['total'] ?? $json['meta']['total'] ?? null;
        if (is_numeric($total)) {
            return $this->makeResult((int) $total, self::STATUS_OK, null, 'reverb');
        }
        $list = $json['conversations'] ?? $json['messages'] ?? [];

        return $this->makeResult(is_array($list) ? count($list) : 0, self::STATUS_OK, null, 'reverb');
    }

    /**
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function fetchTemu(TemuApiService $api, string $label): array
    {
        if (! $api->isConfigured()) {
            return $this->makeResult(0, self::STATUS_ERROR, 'Temu credentials are missing.', $label);
        }

        $types = [
            'bg.gomall.aftersales.chat.unread.get',
            'bg.aftersales.unread.msg.get',
            'bg.gomall.cs.unread.count.get',
        ];
        foreach ($types as $type) {
            $resp = $api->callOpenApi($type, [], 12);
            if (empty($resp['success'])) {
                continue;
            }
            $result = $resp['result'] ?? [];
            $count = is_array($result)
                ? ($result['unreadCount'] ?? $result['unread_count'] ?? $result['count'] ?? $result['total'] ?? null)
                : (is_numeric($result) ? $result : null);
            if (is_numeric($count)) {
                return $this->makeResult((int) $count, self::STATUS_OK, null, $label);
            }
        }

        return $this->makeResult(0, self::STATUS_UNSUPPORTED, 'Temu Open API does not expose a seller inbox count.', $label);
    }

    /**
     * @return array{pending_count: int, fetch_status: string, fetch_note: ?string, source: ?string}
     */
    protected function makeResult(int $count, string $status, ?string $note, ?string $source): array
    {
        $note = $note !== null ? mb_substr(trim($note), 0, 500) : null;

        return [
            'pending_count' => max(0, $count),
            'fetch_status' => $status,
            'fetch_note' => $note !== '' ? $note : null,
            'source' => $source,
        ];
    }

    public function ensureSchema(): void
    {
        if (! Schema::hasTable('cc_messages_pending')) {
            Schema::create('cc_messages_pending', function ($table) {
                $table->id();
                $table->unsignedBigInteger('channel_id')->unique();
                $table->unsignedInteger('pending_count')->default(0);
                $table->string('messages_link', 2048)->nullable();
                $table->string('fetch_status', 32)->nullable();
                $table->string('fetch_note', 512)->nullable();
                $table->string('source', 64)->nullable();
                $table->timestamp('last_fetched_at')->nullable();
                $table->unsignedBigInteger('updated_by_user_id')->nullable();
                $table->string('updated_by_name', 191)->nullable();
                $table->timestamps();
            });

            return;
        }

        $adds = [];
        if (! Schema::hasColumn('cc_messages_pending', 'fetch_status')) {
            $adds[] = 'fetch_status';
        }
        if (! Schema::hasColumn('cc_messages_pending', 'fetch_note')) {
            $adds[] = 'fetch_note';
        }
        if (! Schema::hasColumn('cc_messages_pending', 'source')) {
            $adds[] = 'source';
        }
        if (! Schema::hasColumn('cc_messages_pending', 'last_fetched_at')) {
            $adds[] = 'last_fetched_at';
        }
        if ($adds === []) {
            return;
        }

        Schema::table('cc_messages_pending', function ($table) use ($adds) {
            if (in_array('fetch_status', $adds, true)) {
                $table->string('fetch_status', 32)->nullable()->after('messages_link');
            }
            if (in_array('fetch_note', $adds, true)) {
                $table->string('fetch_note', 512)->nullable();
            }
            if (in_array('source', $adds, true)) {
                $table->string('source', 64)->nullable();
            }
            if (in_array('last_fetched_at', $adds, true)) {
                $table->timestamp('last_fetched_at')->nullable();
            }
        });
    }
}
