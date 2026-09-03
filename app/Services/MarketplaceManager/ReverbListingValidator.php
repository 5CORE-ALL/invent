<?php

namespace App\Services\MarketplaceManager;

/**
 * Completeness + Reverb guideline checks for the View Listing modal.
 * Blank editable fields are flagged so sellers can fill every listing input.
 */
class ReverbListingValidator
{
    /** Conditions that may have inventory > 1. */
    public const MULTI_QTY_CONDITIONS = [
        'brand new',
        'b-stock',
        'b stock',
        'mint',
        'new',
    ];

    /**
     * @param  array<string, mixed>  $listing  Editor-shaped listing payload
     * @return array{
     *     ok: bool,
     *     issues: list<array{section: string, field: string, message: string}>,
     *     sections: array<string, bool>
     * }
     */
    public function validate(array $listing): array
    {
        $issues = [];

        $requireText = static function (string $section, string $field, string $label, mixed $value) use (&$issues): string {
            $trimmed = trim((string) ($value ?? ''));
            if ($trimmed === '') {
                $issues[] = [
                    'section' => $section,
                    'field' => $field,
                    'message' => $label.' is blank.',
                ];
            }

            return $trimmed;
        };

        $title = $requireText('details', 'title', 'Title', $listing['title'] ?? '');
        $make = $requireText('details', 'make', 'Make', $listing['make'] ?? '');
        $model = $requireText('details', 'model', 'Model', $listing['model'] ?? '');
        $requireText('details', 'finish', 'Finish', $listing['finish'] ?? '');
        $requireText('details', 'year', 'Year', $listing['year'] ?? '');
        $requireText('details', 'sku', 'SKU', $listing['sku'] ?? '');

        if ($make !== '' && strcasecmp($make, 'Unknown') === 0) {
            $issues[] = ['section' => 'details', 'field' => 'make', 'message' => 'Make cannot be Unknown.'];
        }
        if ($model !== '' && strcasecmp($model, 'Unknown') === 0) {
            $issues[] = ['section' => 'details', 'field' => 'model', 'message' => 'Model cannot be Unknown.'];
        }

        $conditionName = trim((string) ($listing['condition_name'] ?? ''));
        $conditionUuid = trim((string) ($listing['condition_uuid'] ?? ''));
        if ($conditionName === '' && $conditionUuid === '') {
            $issues[] = ['section' => 'details', 'field' => 'condition', 'message' => 'Condition is blank.'];
        }

        $categoryName = trim((string) ($listing['category_name'] ?? ''));
        $categoryUuid = trim((string) ($listing['category_uuid'] ?? ''));
        if ($categoryName === '' && $categoryUuid === '') {
            $issues[] = ['section' => 'details', 'field' => 'category', 'message' => 'Category is blank.'];
        }

        $price = $listing['price_amount'] ?? null;
        if ($price === null || $price === '' || ! is_numeric($price) || (float) $price <= 0) {
            $issues[] = ['section' => 'pricing', 'field' => 'price', 'message' => 'Price is blank or must be greater than 0.'];
        }

        $currency = trim((string) ($listing['price_currency'] ?? $listing['currency'] ?? ''));
        if ($currency === '') {
            $issues[] = ['section' => 'pricing', 'field' => 'currency', 'message' => 'Currency is blank.'];
        }

        $inventoryRaw = $listing['inventory'] ?? null;
        if ($inventoryRaw === null || $inventoryRaw === '') {
            $issues[] = ['section' => 'pricing', 'field' => 'inventory', 'message' => 'Inventory is blank.'];
            $inventory = null;
        } else {
            $inventory = is_numeric($inventoryRaw) ? (int) $inventoryRaw : null;
            if ($inventory === null) {
                $issues[] = ['section' => 'pricing', 'field' => 'inventory', 'message' => 'Inventory must be a number.'];
            } elseif ($inventory < 0) {
                $issues[] = ['section' => 'pricing', 'field' => 'inventory', 'message' => 'Inventory cannot be negative.'];
            } elseif ($inventory > 1 && ! $this->allowsMultiQty($conditionName)) {
                $issues[] = [
                    'section' => 'pricing',
                    'field' => 'inventory',
                    'message' => 'Used conditions allow inventory of 1 only (Brand New / B-Stock / Mint may be higher).',
                ];
            }
        }

        $photos = $this->stringList($listing['photos'] ?? []);
        $photoCount = count($photos);
        if ($photoCount < 11) {
            $issues[] = [
                'section' => 'media',
                'field' => 'photos',
                'message' => 'Need at least 11 images (currently '.$photoCount.').',
            ];
        } elseif ($photoCount > 25) {
            $issues[] = ['section' => 'media', 'field' => 'photos', 'message' => 'Maximum 25 photos allowed.'];
        }
        foreach ($photos as $i => $url) {
            if (! $this->isPublicHttpUrl($url)) {
                $issues[] = [
                    'section' => 'media',
                    'field' => 'photos',
                    'message' => 'Photo #'.($i + 1).' must be a public http(s) URL.',
                ];
            }
        }

        $videos = $this->stringList($listing['videos'] ?? []);
        $videoCount = count($videos);
        if ($videoCount < 1) {
            $issues[] = ['section' => 'media', 'field' => 'videos', 'message' => 'Need at least 1 video (currently 0).'];
        } elseif ($videoCount > 3) {
            $issues[] = ['section' => 'media', 'field' => 'videos', 'message' => 'Maximum 3 videos allowed.'];
        }
        foreach ($videos as $i => $url) {
            if (! $this->isPublicHttpUrl($url)) {
                $issues[] = [
                    'section' => 'media',
                    'field' => 'videos',
                    'message' => 'Video #'.($i + 1).' must be a public http(s) URL.',
                ];
            }
        }

        $description = trim((string) ($listing['description'] ?? ''));
        if ($description === '') {
            $issues[] = ['section' => 'description', 'field' => 'description', 'message' => 'Description is blank.'];
        }

        $bullets = $this->stringList($listing['bullets'] ?? []);
        if ($bullets === []) {
            $issues[] = ['section' => 'description', 'field' => 'bullets', 'message' => 'Highlighted features / bullets are blank.'];
        }

        $shippingProfileId = trim((string) ($listing['shipping_profile_id'] ?? ''));
        $shippingRates = is_array($listing['shipping_rates'] ?? null) ? $listing['shipping_rates'] : [];
        $localPickupOnly = filter_var($listing['local_pickup_only'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $localPickupOnly && $shippingProfileId === '' && $shippingRates === []) {
            $issues[] = [
                'section' => 'shipping',
                'field' => 'shipping',
                'message' => 'Shipping is blank (set profile ID, rates, or local pickup only).',
            ];
        }

        // Unused $title kept for readability / future publish checks.
        unset($title);

        $sections = [
            'media' => true,
            'details' => true,
            'pricing' => true,
            'description' => true,
            'shipping' => true,
        ];
        foreach ($issues as $issue) {
            $sec = (string) ($issue['section'] ?? '');
            if (isset($sections[$sec])) {
                $sections[$sec] = false;
            }
        }

        return [
            'ok' => $issues === [],
            'issues' => $issues,
            'sections' => $sections,
        ];
    }

    /**
     * Table-row check from live listing details (or enriched product row).
     *
     * @param  object|array<string, mixed>  $row
     */
    public function rowNeedsAttention(object|array $row): bool
    {
        $data = is_array($row) ? $row : (array) $row;
        $linked = array_key_exists('linked', $data) ? (bool) $data['linked'] : true;
        if (! $linked) {
            return false;
        }

        // Prefer full live payload when present (from ReverbLiveListingsService).
        if (! empty($data['listing_incomplete'])) {
            return true;
        }

        $listing = [
            'title' => $data['reverb_title'] ?? $data['title'] ?? '',
            'make' => $data['make'] ?? '',
            'model' => $data['model'] ?? '',
            'finish' => $data['finish'] ?? '',
            'year' => $data['year'] ?? '',
            'sku' => $data['sku'] ?? '',
            'condition_name' => $data['condition_name'] ?? '',
            'condition_uuid' => $data['condition_uuid'] ?? '',
            'category_name' => $data['category_name'] ?? '',
            'category_uuid' => $data['category_uuid'] ?? '',
            'upc' => $data['upc'] ?? '',
            'upc_does_not_apply' => $data['upc_does_not_apply'] ?? false,
            'price_amount' => $data['price'] ?? $data['price_amount'] ?? null,
            'price_currency' => $data['price_currency'] ?? 'USD',
            'inventory' => $data['inventory'] ?? $data['rv_quantity'] ?? $data['quantity'] ?? null,
            'photos' => $data['photos'] ?? array_fill(0, (int) ($data['photo_count'] ?? 0), 'https://placeholder.local/x.jpg'),
            'videos' => $data['videos'] ?? array_fill(0, (int) ($data['video_count'] ?? 0), 'https://placeholder.local/v.mp4'),
            'description' => $data['description'] ?? '',
            'bullets' => $data['bullets'] ?? ['x'], // bullets often not in live list payload — don't false-alarm if unknown
            'shipping_profile_id' => $data['shipping_profile_id'] ?? '',
            'shipping_rates' => $data['shipping_rates'] ?? [],
            'local_pickup_only' => $data['local_pickup_only'] ?? false,
        ];

        // If we only have thin row data (title/price), fall back to basic checks.
        $hasRich = isset($data['make']) || isset($data['photo_count']) || isset($data['photos']);
        if (! $hasRich) {
            $title = trim((string) ($listing['title'] ?? ''));
            $price = $listing['price_amount'] ?? null;

            return $title === '' || ! is_numeric($price) || (float) $price <= 0;
        }

        // Skip URL-format checks for placeholder photo/video arrays used only for counts.
        if (isset($data['photo_count']) && ! isset($data['photos'])) {
            $listing['photos'] = array_fill(0, max(0, (int) $data['photo_count']), 'https://cdn.reverb.com/placeholder.jpg');
        }
        if (isset($data['video_count']) && ! isset($data['videos'])) {
            $listing['videos'] = array_fill(0, max(0, (int) $data['video_count']), 'https://www.youtube.com/watch?v=placeholder');
        }

        $result = $this->validate($listing);

        // Ignore bullets-only issues when bullets were not supplied by live API.
        if (! array_key_exists('bullets', $data) && ! empty($result['issues'])) {
            $result['issues'] = array_values(array_filter(
                $result['issues'],
                static fn (array $issue): bool => ($issue['field'] ?? '') !== 'bullets'
            ));
            $result['ok'] = $result['issues'] === [];
        }

        return ! ($result['ok'] ?? true);
    }

    /**
     * Build incompleteness flag + issue count from a live listing details array.
     *
     * @param  array<string, mixed>|null  $live
     * @return array{incomplete: bool, issue_count: int}
     */
    public function incompletenessFromLive(?array $live): array
    {
        if ($live === null || $live === []) {
            return ['incomplete' => true, 'issue_count' => 1];
        }

        $listing = [
            'title' => $live['title'] ?? '',
            'make' => $live['make'] ?? '',
            'model' => $live['model'] ?? '',
            'finish' => $live['finish'] ?? '',
            'year' => $live['year'] ?? '',
            'sku' => $live['sku'] ?? '',
            'condition_name' => $live['condition_name'] ?? '',
            'condition_uuid' => $live['condition_uuid'] ?? '',
            'category_name' => $live['category_name'] ?? '',
            'category_uuid' => $live['category_uuid'] ?? '',
            'upc' => $live['upc'] ?? '',
            'upc_does_not_apply' => $live['upc_does_not_apply'] ?? false,
            'price_amount' => $live['price'] ?? null,
            'price_currency' => $live['price_currency'] ?? 'USD',
            'inventory' => $live['inventory'] ?? 0,
            'photos' => array_fill(0, (int) ($live['photo_count'] ?? count($live['photos'] ?? [])), 'https://cdn.reverb.com/placeholder.jpg'),
            'videos' => array_fill(0, (int) ($live['video_count'] ?? count($live['videos'] ?? [])), 'https://www.youtube.com/watch?v=placeholder'),
            'description' => $live['description'] ?? '',
            'bullets' => ['x'], // not available on list GET — omit from table alert
            'shipping_profile_id' => $live['shipping_profile_id'] ?? '',
            'shipping_rates' => $live['shipping_rates'] ?? [],
            'local_pickup_only' => $live['local_pickup_only'] ?? false,
        ];

        $result = $this->validate($listing);
        $issues = array_values(array_filter(
            $result['issues'] ?? [],
            static fn (array $issue): bool => ($issue['field'] ?? '') !== 'bullets'
        ));

        return [
            'incomplete' => $issues !== [],
            'issue_count' => count($issues),
        ];
    }

    public function allowsMultiQty(?string $conditionName): bool
    {
        $name = strtolower(trim((string) $conditionName));
        if ($name === '') {
            return true;
        }
        foreach (self::MULTI_QTY_CONDITIONS as $allowed) {
            if (str_contains($name, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    protected function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $t = trim($item);
                if ($t !== '') {
                    $out[] = $t;
                }
            } elseif (is_array($item)) {
                $link = trim((string) ($item['link'] ?? $item['url'] ?? $item['href'] ?? ''));
                if ($link !== '') {
                    $out[] = $link;
                }
            }
        }

        return array_values(array_unique($out));
    }

    protected function isPublicHttpUrl(string $url): bool
    {
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }
        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) || str_ends_with($host, '.local')) {
            return false;
        }

        return true;
    }
}
