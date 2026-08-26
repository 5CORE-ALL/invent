<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Strict pre-create duplicate guard for marketplace → Shopify order push.
 *
 * Searches Shopify for an existing order matching channel order id / number
 * (name, tag, note, note attribute). Fail-closed: API errors block create.
 */
trait FindsExistingShopifyOrderByChannelRef
{
    /**
     * @param  array{store_url?: string, token?: string}  $config
     * @param  list<string>  $refs  Channel order ids / numbers to match
     * @param  list<string>  $tagPrefixes  e.g. ['temu-'] → tag "temu-{ref}"
     * @param  list<string>  $noteAttributeKeys  e.g. ['temu_order_id']
     * @return array{id: ?string, matched_by: ?string, error: ?string}
     */
    protected function findExistingShopifyOrderByRefs(
        array $config,
        array $refs,
        array $tagPrefixes = [],
        array $noteAttributeKeys = [],
        string $logContext = 'ShopifyDuplicateCheck'
    ): array {
        $storeUrl = trim((string) ($config['store_url'] ?? ''));
        $token = trim((string) ($config['token'] ?? ''));
        if ($storeUrl === '' || $token === '') {
            return [
                'id' => null,
                'matched_by' => null,
                'error' => 'Shopify store credentials are not configured — cannot run duplicate check.',
            ];
        }

        $candidates = [];
        foreach ($refs as $ref) {
            $ref = trim((string) $ref);
            if ($ref === '') {
                continue;
            }
            $candidates[$ref] = true;
            $stripped = ltrim($ref, '#');
            if ($stripped !== '' && $stripped !== $ref) {
                $candidates[$stripped] = true;
            }
        }
        $candidates = array_keys($candidates);
        if ($candidates === []) {
            return [
                'id' => null,
                'matched_by' => null,
                'error' => 'No channel order id available for duplicate check.',
            ];
        }

        // 1) REST name lookup (exact order name — common for manual Shopify entry).
        foreach ($candidates as $ref) {
            $byName = $this->shopifyRestFindOrderByName($storeUrl, $token, $ref, $logContext);
            if (($byName['error'] ?? null) !== null) {
                return $byName;
            }
            if (! empty($byName['id'])) {
                return $byName;
            }
        }

        // 2) GraphQL search: name / tag / note / custom attribute value.
        $gql = $this->shopifyGraphqlFindOrderByRefs(
            $storeUrl,
            $token,
            $candidates,
            $tagPrefixes,
            $noteAttributeKeys,
            $logContext
        );
        if (! empty($gql['id'])) {
            return $gql;
        }

        $graphqlFailed = ($gql['error'] ?? null) !== null;
        if (! $graphqlFailed) {
            return ['id' => null, 'matched_by' => null, 'error' => null];
        }

        // GraphQL is down — REST tags can still prove an existing copy (link, never create).
        foreach ($candidates as $ref) {
            foreach ($tagPrefixes as $prefix) {
                $tag = trim((string) $prefix).$ref;
                if ($tag === $ref) {
                    continue;
                }
                $byTag = $this->shopifyRestFindOrderByTag($storeUrl, $token, $tag, $logContext);
                if (($byTag['error'] ?? null) !== null) {
                    return $byTag;
                }
                if (! empty($byTag['id'])) {
                    return $byTag;
                }
            }
        }

        Log::warning($logContext.': duplicate check incomplete — create blocked', [
            'error' => $gql['error'],
            'refs' => $candidates,
        ]);

        return [
            'id' => null,
            'matched_by' => null,
            'error' => (string) ($gql['error'] ?? 'Shopify duplicate check failed'),
        ];
    }

    /**
     * Recheck Shopify immediately before POST. Never create if the channel order already exists.
     *
     * @param  list<string>  $refs
     * @param  list<string>  $tagPrefixes
     * @param  list<string>  $noteAttributeKeys
     */
    protected function postOrderGuarded(
        array $config,
        array $payload,
        array $refs,
        array $tagPrefixes,
        array $noteAttributeKeys,
        string $logContext,
        ?string $localShopifyId = null
    ): ?string {
        $localShopifyId = trim((string) $localShopifyId);
        if ($localShopifyId !== '') {
            if (property_exists($this, 'lastDuplicateLinkMessage')) {
                $this->lastDuplicateLinkMessage = 'Already imported locally.';
            }

            return $localShopifyId;
        }

        $existing = $this->findExistingShopifyOrderByRefs(
            $config,
            $refs,
            $tagPrefixes,
            $noteAttributeKeys,
            $logContext
        );
        if (($existing['error'] ?? null) !== null) {
            $this->lastFailureReason = $existing['error'].' Push blocked to avoid duplicates.';

            return null;
        }
        if (! empty($existing['id'])) {
            if (property_exists($this, 'lastDuplicateLinkMessage')) {
                $this->lastDuplicateLinkMessage = 'Already exists in Shopify as '.$existing['id']
                    .' (matched '.$existing['matched_by'].'). Create skipped.';
            }
            Log::info($logContext.': existing Shopify order found — create skipped', [
                'shopify_order_id' => $existing['id'],
                'matched_by' => $existing['matched_by'],
                'refs' => $refs,
            ]);

            return (string) $existing['id'];
        }

        return $this->postOrder($config, $payload);
    }

    /**
     * @return array{id: ?string, matched_by: ?string, error: ?string}
     */
    protected function shopifyRestFindOrderByName(
        string $storeUrl,
        string $token,
        string $name,
        string $logContext
    ): array {
        $name = ltrim(trim($name), '#');
        if ($name === '') {
            return ['id' => null, 'matched_by' => null, 'error' => null];
        }

        try {
            $url = 'https://'.$storeUrl.'/admin/api/2024-01/orders.json';
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get($url, [
                'status' => 'any',
                'name' => $name,
                'limit' => 10,
                'fields' => 'id,name,tags,note,note_attributes',
            ]);

            if (! $response->successful()) {
                $status = $response->status();
                $this->rememberDuplicateCheckHttpStatus($status);
                $msg = 'Shopify duplicate check (name) failed: HTTP '.$status;

                Log::warning($logContext.': REST name search failed', [
                    'name' => $name,
                    'status' => $status,
                    'body' => mb_substr($response->body(), 0, 300),
                ]);

                return ['id' => null, 'matched_by' => null, 'error' => $msg, 'status' => $status];
            }

            $orders = $response->json('orders') ?? [];
            if (! is_array($orders)) {
                return ['id' => null, 'matched_by' => null, 'error' => null];
            }

            foreach ($orders as $order) {
                if (! is_array($order)) {
                    continue;
                }
                if ($this->shopifyOrderMatchesChannelRefs($order, [$name], [], [])) {
                    $id = (string) ($order['id'] ?? '');
                    if ($id !== '') {
                        return [
                            'id' => $id,
                            'matched_by' => 'name:'.($order['name'] ?? $name),
                            'error' => null,
                        ];
                    }
                }
            }

            return ['id' => null, 'matched_by' => null, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning($logContext.': REST name search exception', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);

            return [
                'id' => null,
                'matched_by' => null,
                'error' => 'Shopify duplicate check (name) failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @return array{id: ?string, matched_by: ?string, error: ?string}
     */
    protected function shopifyRestFindOrderByTag(
        string $storeUrl,
        string $token,
        string $tag,
        string $logContext
    ): array {
        $tag = trim($tag);
        if ($tag === '') {
            return ['id' => null, 'matched_by' => null, 'error' => null];
        }

        try {
            $url = 'https://'.$storeUrl.'/admin/api/2024-01/orders.json';
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(30)->get($url, [
                'status' => 'any',
                'tag' => $tag,
                'limit' => 10,
                'fields' => 'id,name,tags,note,note_attributes',
            ]);

            if (! $response->successful()) {
                $status = $response->status();
                $this->rememberDuplicateCheckHttpStatus($status);
                $msg = 'Shopify duplicate check (tag) failed: HTTP '.$status;

                Log::warning($logContext.': REST tag search failed', [
                    'tag' => $tag,
                    'status' => $status,
                    'body' => mb_substr($response->body(), 0, 300),
                ]);

                return ['id' => null, 'matched_by' => null, 'error' => $msg, 'status' => $status];
            }

            $orders = $response->json('orders') ?? [];
            if (! is_array($orders)) {
                return ['id' => null, 'matched_by' => null, 'error' => null];
            }

            foreach ($orders as $order) {
                if (! is_array($order)) {
                    continue;
                }
                $id = (string) ($order['id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $tagsRaw = $order['tags'] ?? '';
                $tags = is_array($tagsRaw)
                    ? array_map('strval', $tagsRaw)
                    : array_map('trim', explode(',', (string) $tagsRaw));
                foreach ($tags as $existing) {
                    if (strcasecmp((string) $existing, $tag) === 0) {
                        return [
                            'id' => $id,
                            'matched_by' => 'tag:'.$tag,
                            'error' => null,
                        ];
                    }
                }
            }

            return ['id' => null, 'matched_by' => null, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning($logContext.': REST tag search exception', [
                'tag' => $tag,
                'error' => $e->getMessage(),
            ]);

            return [
                'id' => null,
                'matched_by' => null,
                'error' => 'Shopify duplicate check (tag) failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * @param  list<string>  $candidates
     * @param  list<string>  $tagPrefixes
     * @param  list<string>  $noteAttributeKeys
     * @return array{id: ?string, matched_by: ?string, error: ?string}
     */
    protected function shopifyGraphqlFindOrderByRefs(
        string $storeUrl,
        string $token,
        array $candidates,
        array $tagPrefixes,
        array $noteAttributeKeys,
        string $logContext
    ): array {
        $parts = [];
        foreach ($candidates as $ref) {
            $escaped = $this->escapeShopifySearchValue($ref);
            if ($escaped === '') {
                continue;
            }
            $parts[] = 'name:'.$escaped;
            $parts[] = 'name:#'.$escaped;
            $parts[] = 'note:'.$escaped;
            foreach ($tagPrefixes as $prefix) {
                $tag = $this->escapeShopifySearchValue(trim($prefix).$ref);
                if ($tag !== '') {
                    $parts[] = 'tag:'.$tag;
                }
            }
        }
        $parts = array_values(array_unique($parts));
        if ($parts === []) {
            return ['id' => null, 'matched_by' => null, 'error' => null];
        }

        // Shopify query string OR groups — keep batches small.
        $batches = array_chunk($parts, 8);
        foreach ($batches as $batch) {
            $queryStr = 'status:any AND ('.implode(' OR ', $batch).')';
            $result = $this->shopifyGraphqlOrdersQuery($storeUrl, $token, $queryStr, $logContext);
            if (($result['error'] ?? null) !== null) {
                return $result;
            }
            foreach ($result['orders'] ?? [] as $order) {
                $match = $this->shopifyGraphqlOrderMatches($order, $candidates, $tagPrefixes, $noteAttributeKeys);
                if ($match !== null) {
                    return [
                        'id' => $match['id'],
                        'matched_by' => $match['matched_by'],
                        'error' => null,
                    ];
                }
            }
        }

        return ['id' => null, 'matched_by' => null, 'error' => null];
    }

    /**
     * @return array{orders?: list<array<string, mixed>>, error?: ?string}
     */
    protected function shopifyGraphqlOrdersQuery(
        string $storeUrl,
        string $token,
        string $queryStr,
        string $logContext
    ): array {
        $payload = [
            'query' => <<<'GQL'
query OrdersByRef($q: String!) {
  orders(first: 10, query: $q, sortKey: CREATED_AT, reverse: true) {
    edges {
      node {
        legacyResourceId
        name
        tags
        note
        customAttributes { key value }
      }
    }
  }
}
GQL,
            'variables' => ['q' => $queryStr],
        ];

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $token,
                'Content-Type' => 'application/json',
            ])->timeout(45)->post('https://'.$storeUrl.'/admin/api/2024-01/graphql.json', $payload);

            if (! $response->successful()) {
                $status = $response->status();
                $this->rememberDuplicateCheckHttpStatus($status);
                $msg = 'Shopify duplicate check (search) failed: HTTP '.$status;
                Log::warning($logContext.': GraphQL search failed', [
                    'status' => $status,
                    'query' => $queryStr,
                    'body' => mb_substr($response->body(), 0, 400),
                ]);

                return ['error' => $msg, 'status' => $status];
            }

            $json = $response->json() ?? [];
            if (! empty($json['errors']) && is_array($json['errors'])) {
                $first = (string) ($json['errors'][0]['message'] ?? 'GraphQL error');
                Log::warning($logContext.': GraphQL errors', [
                    'query' => $queryStr,
                    'errors' => $json['errors'],
                ]);

                if (stripos($first, 'throttl') !== false) {
                    $this->rememberDuplicateCheckHttpStatus(429);

                    return ['error' => 'Shopify duplicate check (search) failed: '.$first, 'status' => 429];
                }

                return ['error' => 'Shopify duplicate check (search) failed: '.$first];
            }

            $edges = $json['data']['orders']['edges'] ?? [];
            $orders = [];
            if (is_array($edges)) {
                foreach ($edges as $edge) {
                    $node = $edge['node'] ?? null;
                    if (is_array($node)) {
                        $orders[] = $node;
                    }
                }
            }

            return ['orders' => $orders, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning($logContext.': GraphQL search exception', [
                'query' => $queryStr,
                'error' => $e->getMessage(),
            ]);

            return ['error' => 'Shopify duplicate check (search) failed: '.$e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $order  REST order shape
     * @param  list<string>  $candidates
     * @param  list<string>  $tagPrefixes
     * @param  list<string>  $noteAttributeKeys
     */
    protected function shopifyOrderMatchesChannelRefs(
        array $order,
        array $candidates,
        array $tagPrefixes,
        array $noteAttributeKeys
    ): bool {
        $name = ltrim(trim((string) ($order['name'] ?? '')), '#');
        $note = (string) ($order['note'] ?? '');
        $tagsRaw = $order['tags'] ?? '';
        $tags = is_array($tagsRaw)
            ? array_map('strval', $tagsRaw)
            : array_map('trim', explode(',', (string) $tagsRaw));
        $attrs = [];
        foreach ($order['note_attributes'] ?? [] as $attr) {
            if (is_array($attr) && isset($attr['name'])) {
                $attrs[(string) $attr['name']] = (string) ($attr['value'] ?? '');
            }
        }

        foreach ($candidates as $ref) {
            $ref = ltrim(trim($ref), '#');
            if ($ref === '') {
                continue;
            }
            if (strcasecmp($name, $ref) === 0) {
                return true;
            }
            if ($note !== '' && str_contains($note, $ref)) {
                return true;
            }
            foreach ($tags as $tag) {
                if (strcasecmp((string) $tag, $ref) === 0) {
                    return true;
                }
                foreach ($tagPrefixes as $prefix) {
                    if (strcasecmp((string) $tag, trim($prefix).$ref) === 0) {
                        return true;
                    }
                }
            }
            foreach ($noteAttributeKeys as $key) {
                if (isset($attrs[$key]) && strcasecmp($attrs[$key], $ref) === 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $node  GraphQL order node
     * @param  list<string>  $candidates
     * @param  list<string>  $tagPrefixes
     * @param  list<string>  $noteAttributeKeys
     * @return array{id: string, matched_by: string}|null
     */
    protected function shopifyGraphqlOrderMatches(
        array $node,
        array $candidates,
        array $tagPrefixes,
        array $noteAttributeKeys
    ): ?array {
        $id = trim((string) ($node['legacyResourceId'] ?? ''));
        if ($id === '') {
            return null;
        }

        $restShape = [
            'id' => $id,
            'name' => $node['name'] ?? '',
            'note' => $node['note'] ?? '',
            'tags' => $node['tags'] ?? [],
            'note_attributes' => [],
        ];
        foreach ($node['customAttributes'] ?? [] as $attr) {
            if (is_array($attr)) {
                $restShape['note_attributes'][] = [
                    'name' => $attr['key'] ?? '',
                    'value' => $attr['value'] ?? '',
                ];
            }
        }

        if (! $this->shopifyOrderMatchesChannelRefs($restShape, $candidates, $tagPrefixes, $noteAttributeKeys)) {
            return null;
        }

        return [
            'id' => $id,
            'matched_by' => 'search:'.(string) ($node['name'] ?? $id),
        ];
    }

    protected function escapeShopifySearchValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        // Keep search tokens safe; wrap in quotes when special chars present.
        if (preg_match('/[\s:#\-]/', $value)) {
            $value = str_replace('"', '', $value);

            return '"'.$value.'"';
        }

        return $value;
    }

    protected function rememberDuplicateCheckHttpStatus(int $status): void
    {
        if (property_exists($this, 'lastApiStatus')) {
            $this->lastApiStatus = $status;
        }
    }
}
