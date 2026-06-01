<?php

namespace App\Services\Crm;

use App\Models\Crm\ShopifyCustomer;
use App\Models\Crm\ShopifyOrder;
use Illuminate\Support\Facades\Schema;

class ShopifyCustomerClassifier
{
    /**
     * @return array<int, array{value: string, label: string, terms: array<int, string>, domains?: array<int, string>}>
     */
    public function marketplaceChannelDefinitions(): array
    {
        return [
            ['value' => 'amazon', 'label' => 'Amazon', 'terms' => ['amazon'], 'domains' => ['marketplace.amazon.com']],
            ['value' => 'ebay', 'label' => 'eBay', 'terms' => ['ebay'], 'domains' => ['members.ebay.com']],
            ['value' => 'temu', 'label' => 'Temu', 'terms' => ['temu'], 'domains' => ['u.shipping.temuemail.com', 'ul.shipping.temuemail.com']],
            ['value' => 'walmart', 'label' => 'Walmart', 'terms' => ['walmart'], 'domains' => ['relay.walmart.com']],
            ['value' => 'reverb', 'label' => 'Reverb', 'terms' => ['reverb'], 'domains' => ['private-relay.reverb.com']],
            ['value' => 'tiktok-shop', 'label' => 'Tiktok Shop', 'terms' => ['tiktok shop', 'tik tok shop', 'tiktok', 'tik tok'], 'domains' => ['scs.tiktokw.us']],
            ['value' => 'mirakl', 'label' => 'Mirakl', 'terms' => ['mirakl'], 'domains' => ['us.notification.mirakl.net']],
            ['value' => 'vinted-com', 'label' => 'Vinted.com', 'terms' => ['vinted']],
            ['value' => 'newegg', 'label' => 'Newegg', 'terms' => ['newegg']],
            ['value' => 'pls', 'label' => 'PLS', 'terms' => ['pls', 'prolightsounds']],
            ['value' => 'tiendamia', 'label' => 'Tiendamia', 'terms' => ['tiendamia']],
            ['value' => 'business-5core', 'label' => 'Business 5Core', 'terms' => ['business 5core', 'business5core', 'b5c']],
            ['value' => 'mercari-wo-ship', 'label' => 'Mercari wo ship', 'terms' => ['mercari wo ship', 'mercari without ship']],
            ['value' => 'fb-marketplace', 'label' => 'FB Marketplace', 'terms' => ['fb marketplace', 'facebook marketplace']],
            ['value' => 'instagram-shop', 'label' => 'Instagram Shop', 'terms' => ['instagram shop', 'instagram']],
            ['value' => 'depop', 'label' => 'Depop', 'terms' => ['depop']],
            ['value' => 'mercari-w-ship', 'label' => 'Mercari w ship', 'terms' => ['mercari w ship', 'mercari with ship', 'mercari']],
            ['value' => 'topdawg', 'label' => 'TopDawg', 'terms' => ['topdawg', 'topdwag']],
            ['value' => 'tiktok-2', 'label' => 'TikTok 2', 'terms' => ['tiktok 2', 'tik tok 2']],
            ['value' => 'aliexpress', 'label' => 'Aliexpress', 'terms' => ['aliexpress', 'ali express']],
            ['value' => 'inbox-shop-chat', 'label' => 'Inbox Shop Chat', 'terms' => ['inbox shop chat']],
            ['value' => 'shopify-b2b', 'label' => 'Shopify B2B', 'terms' => ['shopify b2b']],
            ['value' => 'faire', 'label' => 'Faire', 'terms' => ['faire']],
            ['value' => 'ebaytwo', 'label' => 'EbayTwo', 'terms' => ['ebaytwo', 'ebay 2', 'ebay2']],
            ['value' => 'shein', 'label' => 'Shein', 'terms' => ['shein']],
            ['value' => 'ebaythree', 'label' => 'EbayThree', 'terms' => ['ebaythree', 'ebay 3', 'ebay3']],
            ['value' => 'wayfair', 'label' => 'Wayfair', 'terms' => ['wayfair']],
            ['value' => 'macys', 'label' => 'Macys', 'terms' => ['macys', "macy's", 'macy']],
            ['value' => 'shopify-b2c', 'label' => 'Shopify B2C', 'terms' => ['shopify b2c']],
            ['value' => 'temu-2', 'label' => 'Temu 2', 'terms' => ['temu 2', 'temu2']],
            ['value' => 'purchasing-power', 'label' => 'Purchasing Power', 'terms' => ['purchasing power']],
            ['value' => 'doba', 'label' => 'Doba', 'terms' => ['doba']],
            ['value' => 'bestbuy-usa', 'label' => 'BestBuy USA', 'terms' => ['bestbuy', 'best buy', 'best buy usa']],
        ];
    }

    public function classify(ShopifyCustomer $customer, ?array $orderPayload = null): ShopifyCustomer
    {
        if (! $this->hasClassificationColumns()) {
            return $customer;
        }

        if ((bool) $customer->classification_overridden) {
            return $customer;
        }

        $payload = is_array($customer->raw_payload) ? $customer->raw_payload : [];
        $tags = $this->tagsFromPayload($payload);

        if ($this->containsAnyTag($tags, $this->dropshipperTagTerms())) {
            return $this->apply($customer, [
                'customer_type' => 'dropshipper',
                'marketplace_channel' => null,
                'classification_source' => 'tag',
                'classification_reason' => 'Matched dropshipper customer tag',
            ]);
        }

        if ($this->containsAnyTag($tags, $this->wholesaleTagTerms())) {
            return $this->apply($customer, [
                'customer_type' => 'wholesale',
                'marketplace_channel' => null,
                'classification_source' => 'tag',
                'classification_reason' => 'Matched wholesale customer tag',
            ]);
        }

        $marketplaceTag = $this->matchMarketplaceTag($tags);
        if ($marketplaceTag !== null) {
            return $this->apply($customer, [
                'customer_type' => 'marketplace',
                'marketplace_channel' => $marketplaceTag['value'],
                'classification_source' => 'tag',
                'classification_reason' => 'Matched marketplace customer tag '.$marketplaceTag['tag'],
            ]);
        }

        $domain = $this->emailDomain((string) ($customer->email ?? ''));
        if ($domain !== null) {
            $domainMatch = $this->matchDomain($domain);
            if ($domainMatch !== null) {
                return $this->apply($customer, [
                    'customer_type' => 'marketplace',
                    'marketplace_channel' => $domainMatch['value'],
                    'classification_source' => 'email_domain',
                    'classification_reason' => 'Matched email domain '.$domain,
                ]);
            }
        }

        $channel = $this->channelDetailsFromOrderPayload($orderPayload ?? $this->latestOrderPayload($customer));
        if ($channel !== null) {
            return $this->apply($customer, [
                'customer_type' => 'marketplace',
                'marketplace_channel' => $channel['value'],
                'classification_source' => 'order_source',
                'classification_reason' => 'Matched order source '.$channel['source'],
            ]);
        }

        return $this->apply($customer, [
            'customer_type' => $this->hasCustomerIdentity($customer) ? 'direct' : 'unknown',
            'marketplace_channel' => null,
            'classification_source' => 'fallback',
            'classification_reason' => $this->hasCustomerIdentity($customer)
                ? 'No marketplace or wholesale rule matched'
                : 'No customer identity available',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function wholesaleTagTerms(): array
    {
        return [
            'wholesale',
            'b2b',
            'shopify b2b',
            'Car stereo store',
            'DJ Service 0 orders',
            'Dj shop',
            'DJ Store',
            'DJ supply store',
            'Drum School',
            'Drum Store',
            'Guitar store',
            'Home audio store',
            'Musical instrument store',
            'Musician',
            'Music School',
            'Music Store',
            'Piano Store',
            'Recording studio',
            'Record store',
            'Resellers 0 Orders',
            'Shop',
            'VerifiedByWholesaleAllInOne',
            'Violin Shop',
            'wholesaler 0 orders',
            'Wholesaler less orders',
            'wholeseller',
        ];
    }

    /**
     * @return array<int, string>
     */
    public function dropshipperTagTerms(): array
    {
        return [
            'dropshipper',
            'drop shipper',
            'drop-shipper',
            'dropship',
            'drop ship',
        ];
    }

    /**
     * Return only the classification tag terms that are actually present in the
     * database for the given type(s), sorted case-insensitively.
     *
     * @param  array<int, string>  $types  e.g. ['wholesale'], ['dropshipper'], or both
     * @return array<int, string>
     */
    public function classificationTagsForTypes(array $types): array
    {
        $termsByType = [
            'wholesale'   => array_map('mb_strtolower', $this->wholesaleTagTerms()),
            'dropshipper' => array_map('mb_strtolower', $this->dropshipperTagTerms()),
        ];

        // Collect all lowercase terms relevant to the requested types
        $relevantTerms = [];
        foreach ($types as $type) {
            foreach ($termsByType[$type] ?? [] as $term) {
                $relevantTerms[] = $term;
            }
        }

        if (empty($relevantTerms)) {
            return [];
        }

        // Pull all raw tag strings from customers of those types
        $query = ShopifyCustomer::query()
            ->whereIn('customer_type', $types)
            ->whereNotNull('raw_payload')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$.\"tags\"')) as tags")
            ->distinct();

        $found = [];
        foreach ($query->pluck('tags') as $rawTags) {
            if (! is_string($rawTags) || trim($rawTags) === '') {
                continue;
            }
            foreach (explode(',', $rawTags) as $tag) {
                $tag = trim($tag);
                if ($tag === '') {
                    continue;
                }
                if (in_array(mb_strtolower($tag), $relevantTerms, true)) {
                    $found[mb_strtolower($tag)] = $tag;
                }
            }
        }

        natcasesort($found);

        return array_values($found);
    }

    public function channelLabel(?string $channel): ?string
    {
        if ($channel === null || $channel === '') {
            return null;
        }

        foreach ($this->marketplaceChannelDefinitions() as $definition) {
            if ($definition['value'] === $channel) {
                return $definition['label'];
            }
        }

        return ucwords(str_replace(['_', '-'], ' ', $channel));
    }

    /**
     * @return array<int, string>
     */
    public function marketplaceChannelOptions(): array
    {
        $options = [];
        foreach ($this->marketplaceChannelDefinitions() as $definition) {
            $options[$definition['value']] = $definition['label'];
        }
        natcasesort($options);

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function tagsFromPayload(array $payload): array
    {
        $rawTags = $payload['tags'] ?? null;
        if (is_string($rawTags) && $rawTags !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $rawTags))));
        }

        if (is_array($rawTags)) {
            return array_values(array_filter(array_map('trim', $rawTags)));
        }

        return [];
    }

    /**
     * @return array{value: string, label: string, source: string}|null
     */
    public function channelDetailsFromOrderPayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $parts = [];
        foreach (['source_name', 'tags', 'source_identifier', 'source_url', 'landing_site', 'referring_site'] as $field) {
            $value = $payload[$field] ?? null;
            if (is_array($value)) {
                $value = implode(' ', array_filter(array_map(static fn ($item) => is_scalar($item) ? (string) $item : '', $value)));
            }
            if (is_scalar($value) && trim((string) $value) !== '') {
                $parts[$field] = trim((string) $value);
            }
        }

        if ($parts === []) {
            return null;
        }

        $haystack = mb_strtolower(implode(' ', $parts));
        foreach ($this->marketplaceChannelDefinitions() as $definition) {
            foreach ($definition['terms'] as $term) {
                if (str_contains($haystack, mb_strtolower($term))) {
                    return [
                        'value' => $definition['value'],
                        'label' => $definition['label'],
                        'source' => $parts['source_name'] ?? $parts['tags'] ?? reset($parts),
                    ];
                }
            }
        }

        return null;
    }

    protected function apply(ShopifyCustomer $customer, array $attributes): ShopifyCustomer
    {
        $customer->forceFill([
            'customer_type' => $attributes['customer_type'],
            'marketplace_channel' => $attributes['marketplace_channel'],
            'classification_source' => $attributes['classification_source'],
            'classification_reason' => mb_substr((string) $attributes['classification_reason'], 0, 255),
            'classified_at' => now(),
        ])->save();

        return $customer->refresh();
    }

    protected function emailDomain(string $email): ?string
    {
        $email = trim(mb_strtolower($email));
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        return trim(substr(strrchr($email, '@') ?: '', 1)) ?: null;
    }

    protected function matchDomain(string $domain): ?array
    {
        foreach ($this->marketplaceChannelDefinitions() as $definition) {
            foreach (($definition['domains'] ?? []) as $candidate) {
                if ($domain === mb_strtolower($candidate)) {
                    return $definition;
                }
            }
        }

        if ($domain === 'no-reply.com') {
            return ['value' => 'other', 'label' => 'Other'];
        }

        return null;
    }

    /**
     * @return array{value: string, label: string, tag: string}|null
     */
    protected function matchMarketplaceTag(array $tags): ?array
    {
        $normalizedTags = [];
        foreach ($tags as $tag) {
            $normalizedTags[$this->normalizeTagTerm((string) $tag)] = (string) $tag;
        }

        foreach ($this->marketplaceChannelDefinitions() as $definition) {
            foreach ($definition['terms'] as $term) {
                $key = $this->normalizeTagTerm($term);
                if (isset($normalizedTags[$key])) {
                    return [
                        'value' => $definition['value'],
                        'label' => $definition['label'],
                        'tag' => $normalizedTags[$key],
                    ];
                }
            }
        }

        return null;
    }

    protected function latestOrderPayload(ShopifyCustomer $customer): ?array
    {
        $order = ShopifyOrder::query()
            ->where('shopify_customer_id', $customer->shopify_customer_id)
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->first(['raw_payload']);

        return is_array($order?->raw_payload) ? $order->raw_payload : null;
    }

    protected function hasCustomerIdentity(ShopifyCustomer $customer): bool
    {
        foreach (['email', 'phone', 'first_name', 'last_name'] as $field) {
            if (trim((string) ($customer->{$field} ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    protected function containsAnyTag(array $tags, array $terms): bool
    {
        $normalizedTerms = array_flip(array_map([$this, 'normalizeTagTerm'], $terms));

        foreach ($tags as $tag) {
            if (isset($normalizedTerms[$this->normalizeTagTerm((string) $tag)])) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeTagTerm(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return $value;
    }

    protected function hasClassificationColumns(): bool
    {
        static $hasColumns = null;
        if ($hasColumns !== null) {
            return $hasColumns;
        }

        $columns = [
            'customer_type',
            'marketplace_channel',
            'classification_source',
            'classification_reason',
            'classification_overridden',
            'classified_at',
        ];

        $hasColumns = Schema::hasTable('shopify_customers');
        foreach ($columns as $column) {
            $hasColumns = $hasColumns && Schema::hasColumn('shopify_customers', $column);
        }

        return $hasColumns;
    }
}
