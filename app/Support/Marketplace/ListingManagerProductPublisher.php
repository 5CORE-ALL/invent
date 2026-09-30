<?php

namespace App\Support\Marketplace;

use App\Models\ListingManagerChannelDraft;
use App\Services\Support\ProductMasterMarketplaceMaps;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Save Listing Manager product edits and push title / description / price
 * to the marketplaces the seller selects.
 */
class ListingManagerProductPublisher
{
    public const PARTS = ['title', 'bullets', 'description', 'price', 'images'];

    public const MAX_IMAGES = 20;

    /**
     * Only the keys present in $fields are written, so callers can save a single slice
     * (e.g. just images, or just a sibling's title) without blanking the rest.
     *
     * @param  array<string, mixed>  $fields
     * @return array{saved: bool, message: string}
     */
    public function saveLocal(string $sku, array $fields): array
    {
        $sku = trim($sku);
        if ($sku === '' || ! Schema::hasTable('product_master')) {
            return ['saved' => false, 'message' => 'Product Master is not available.'];
        }

        $update = [];
        $map = [];
        if (array_key_exists('title', $fields)) {
            $title = trim((string) $fields['title']);
            $map += ['title80' => $title, 'title100' => $title, 'title150' => $title];
        }
        if (array_key_exists('description', $fields)) {
            $description = (string) $fields['description'];
            $map += ['description_html' => $description, 'description_1500' => $description, 'product_description' => $description];
        }
        foreach ([
            'short_description' => 'short_description',
            'meta_title' => 'meta_title',
            'seo_description' => 'seo_description',
            'tags' => 'tags',
            'product_type' => 'product_type',
            'vendor' => 'brand',
            'manufacturer' => 'manufacturer',
        ] as $key => $col) {
            if (array_key_exists($key, $fields)) {
                $map[$col] = trim((string) $fields[$key]);
            }
        }
        if (array_key_exists('upc', $fields)) {
            $upc = trim((string) $fields['upc']);
            $map += ['upc' => $upc, 'barcode' => $upc];
        }
        if (array_key_exists('bullets', $fields) && is_array($fields['bullets'])) {
            $bullets = self::cleanBullets($fields['bullets']);
            for ($i = 1; $i <= 5; $i++) {
                $map['bullet'.$i] = $bullets[$i - 1] ?? null;
            }
        }
        if (array_key_exists('images', $fields) && is_array($fields['images'])) {
            $images = self::cleanImages($fields['images']);
            for ($i = 1; $i <= self::MAX_IMAGES; $i++) {
                $map['image'.$i] = $images[$i - 1] ?? null;
            }
            $map['main_image'] = $images[0] ?? null;
        }

        foreach ($map as $col => $value) {
            if (Schema::hasColumn('product_master', $col)) {
                $update[$col] = $value;
            }
        }

        if ($update === []) {
            return ['saved' => false, 'message' => 'No Product Master columns available to save.'];
        }
        if (Schema::hasColumn('product_master', 'updated_at')) {
            $update['updated_at'] = now();
        }

        $query = DB::table('product_master')->where('sku', $sku);
        if ($query->exists()) {
            $query->update($update);
        } else {
            $insert = $update;
            $insert['sku'] = $sku;
            if (Schema::hasColumn('product_master', 'created_at')) {
                $insert['created_at'] = now();
            }
            DB::table('product_master')->insert($insert);
        }

        return ['saved' => true, 'message' => 'Saved to Product Master.'];
    }

    /**
     * @param  list<mixed>  $bullets
     * @return list<string>
     */
    public static function cleanBullets(array $bullets): array
    {
        $out = [];
        foreach ($bullets as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', (string) $line) ?? (string) $line);
            $line = preg_replace('/^\s*(?:\d+[.)]|[-•*▪●])\s*/u', '', $line) ?? $line;
            if ($line !== '') {
                $out[] = $line;
            }
            if (count($out) >= 5) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $images
     * @return list<string>
     */
    public static function cleanImages(array $images): array
    {
        $out = [];
        foreach ($images as $url) {
            $url = trim((string) $url);
            if ($url === '' || $url === '-') {
                continue;
            }
            if (str_starts_with($url, '//')) {
                $url = 'https:'.$url;
            }
            if (! preg_match('#^https?://#i', $url) && ! str_starts_with($url, '/')) {
                continue;
            }
            if (! in_array($url, $out, true)) {
                $out[] = $url;
            }
            if (count($out) >= self::MAX_IMAGES) {
                break;
            }
        }

        return $out;
    }

    /**
     * @param  list<mixed>  $parts
     * @return list<string>
     */
    public static function normalizeParts(array $parts): array
    {
        $parts = array_values(array_intersect(array_map('strval', $parts), self::PARTS));

        return $parts === [] ? ['title', 'description', 'price'] : $parts;
    }

    /**
     * Update live marketplace listings in place. Only create/update a Listing Manager
     * draft when the SKU is not already live on that channel; with $updateOnly such
     * channels are skipped entirely so nothing new is created anywhere.
     *
     * @param  Collection<int, object>  $channels
     * @param  list<string>  $parts
     * @return list<array{channel_id: int, channel: string, marketplace: string, success: bool, message: string, mode: string}>
     */
    public function pushSelectedChannels(string $sku, Collection $channels, array $fields, array $parts, bool $updateOnly = false): array
    {
        @set_time_limit(180);
        @ini_set('max_execution_time', '180');

        $sku = trim($sku);
        $parts = self::normalizeParts($parts);

        $rows = [];
        foreach ($channels as $ch) {
            $channelId = (int) ($ch->id ?? 0);
            $name = (string) ($ch->channel ?? '');
            try {
                $rows[] = $this->pushOneChannel($sku, $channelId, $name, $fields, $parts, $updateOnly);
            } catch (\Throwable $e) {
                Log::warning('ListingManager channel push failed', [
                    'sku' => $sku,
                    'channel' => $name,
                    'error' => $e->getMessage(),
                ]);
                $rows[] = [
                    'channel_id' => $channelId,
                    'channel' => $name,
                    'marketplace' => (string) (self::marketplaceKeyFromChannel($name) ?? ''),
                    'success' => false,
                    'message' => $e->getMessage(),
                    'mode' => 'error',
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<string>  $parts
     * @return array{channel_id: int, channel: string, marketplace: string, success: bool, message: string, mode: string}
     */
    private function pushOneChannel(string $sku, int $channelId, string $channelName, array $fields, array $parts, bool $updateOnly = false): array
    {
        $key = self::marketplaceKeyFromChannel($channelName) ?? '';
        $live = ListingManagerPublishStatus::check($channelName, $sku);
        $draft = $this->findDraft($channelId, $sku);
        $appOnly = ListingManagerPublishStatus::requiresAppPublishForActive($channelName);
        $draftListedFromApp = $draft
            && $draft->status === 'listed'
            && (! $appOnly || ListingManagerPublishStatus::wasPublishedFromListingManager($draft->notes));
        $isLive = $draftListedFromApp || ((! $appOnly) && ($live['listed'] ?? false));
        if (! $isLive && self::marketplaceKeyFromChannel($channelName) === 'amazon') {
            $asin = ListingManagerPublishStatus::amazonAsinForSku($sku);
            if ($asin !== null) {
                $isLive = true;
                $live = ['listed' => true, 'listing_id' => $asin, 'source' => 'amazon_listings'];
            }
        }

        // App-only channels: the SKU may already exist on the marketplace (active or inactive —
        // e.g. Newegg without images) without being "Active" in Listing Manager. Updating that
        // product in place is fine; only the local Active status stays untouched.
        $existsOnMarketplace = false;
        $marketplaceState = 'unknown';
        if (! $isLive && $appOnly && $key !== '') {
            $exists = ListingManagerPublishStatus::existsOnMarketplace($channelName, $sku);
            if ($exists['exists']) {
                $existsOnMarketplace = true;
                $marketplaceState = $exists['state'];
                $live = ['listed' => true, 'listing_id' => $exists['listing_id'], 'source' => $exists['source']];
            }
        }

        if ($isLive || $existsOnMarketplace) {
            if ($draft) {
                // Only keep/flip to Active when Listing Manager already treats it as live.
                $this->applyFieldsToDraft($draft, $fields, $isLive, $live['listing_id'] ?? null);
            }
            if ($key === '') {
                return [
                    'channel_id' => $channelId,
                    'channel' => $channelName,
                    'marketplace' => '',
                    'success' => false,
                    'message' => 'This SKU is already live, but a live API update is not configured for this marketplace.',
                    'mode' => 'live',
                ];
            }
            $push = $this->pushToMarketplaces($sku, [$key], $fields, $parts);
            $row = $push[$key] ?? ['success' => false, 'message' => 'No response from this marketplace.'];
            $ok = (bool) ($row['success'] ?? false);
            $detail = trim((string) ($row['message'] ?? ''));
            if ($detail === '') {
                $detail = $ok ? 'Updated.' : 'Update failed.';
            }
            $prefix = 'Live listing updated. ';
            if ($existsOnMarketplace) {
                $prefix = $marketplaceState === 'inactive'
                    ? 'Marketplace listing updated (it is currently inactive on '.$channelName.'; it goes live once the marketplace accepts the content). '
                    : 'Marketplace listing updated. ';
            }

            return [
                'channel_id' => $channelId,
                'channel' => $channelName,
                'marketplace' => $key,
                'success' => $ok,
                'message' => $ok ? ($prefix.$detail) : $detail,
                'mode' => 'live',
            ];
        }

        if ($updateOnly) {
            return [
                'channel_id' => $channelId,
                'channel' => $channelName,
                'marketplace' => $key,
                'success' => true,
                'message' => 'Skipped: this SKU is not listed on '.$channelName.' yet, so there is no product to update. Nothing was created.',
                'mode' => 'skipped',
            ];
        }

        $this->saveAsNewDraft($channelId, $channelName, $sku, $fields);

        return [
            'channel_id' => $channelId,
            'channel' => $channelName,
            'marketplace' => $key,
            'success' => true,
            'message' => 'Saved as a Listing Manager draft only. This SKU is not live on '.$channelName.' yet, so nothing was sent to the marketplace.',
            'mode' => 'draft',
        ];
    }

    private function findDraft(int $channelId, string $sku): ?ListingManagerChannelDraft
    {
        if ($channelId <= 0 || ! Schema::hasTable('listing_manager_channel_drafts')) {
            return null;
        }

        return ListingManagerChannelDraft::query()
            ->where('channel_id', $channelId)
            ->whereRaw('LOWER(TRIM(seller_sku)) = ?', [mb_strtolower($sku)])
            ->first();
    }

    private function applyFieldsToDraft(
        ListingManagerChannelDraft $draft,
        array $fields,
        bool $keepListed,
        ?string $listingId = null
    ): void {
        $details = ListingManagerPublishStatus::normalizeDetails(
            is_array($draft->listing_details) ? $draft->listing_details : []
        );
        if (array_key_exists('title', $fields) && trim((string) $fields['title']) !== '') {
            $draft->title = trim((string) $fields['title']);
        }
        if (array_key_exists('price', $fields) && $fields['price'] !== '' && $fields['price'] !== null) {
            $draft->price = (float) $fields['price'];
        }
        if (array_key_exists('description', $fields)) {
            $details['description'] = (string) $fields['description'];
        }
        $draft->listing_details = $details;
        if ($keepListed) {
            $draft->status = 'listed';
            if (trim((string) $draft->external_listing_id) === '' && trim((string) ($listingId ?? '')) !== '') {
                $draft->external_listing_id = trim((string) $listingId);
            }
            if ($draft->listed_at === null) {
                $draft->listed_at = now();
            }
        }
        $draft->save();
    }

    /**
     * Create or update a local draft for a SKU that is not live yet.
     */
    private function saveAsNewDraft(int $channelId, string $channelName, string $sku, array $fields): void
    {
        if ($channelId <= 0 || ! Schema::hasTable('listing_manager_channel_drafts')) {
            return;
        }

        $existing = $this->findDraft($channelId, $sku);
        if ($existing && $existing->status === 'listed') {
            return;
        }
        if ($existing) {
            $this->applyFieldsToDraft($existing, $fields, false);

            return;
        }

        $hydrated = ListingManagerAmazonHydrator::hydrate($sku, false);
        $title = trim((string) ($fields['title'] ?? '')) ?: (string) ($hydrated['title'] ?? $sku);
        $price = isset($fields['price']) && is_numeric($fields['price']) ? (float) $fields['price'] : ($hydrated['price'] ?? null);
        $details = ListingManagerAmazonHydrator::detailsFromHydration($hydrated, [], $channelName);
        if (array_key_exists('description', $fields)) {
            $details['description'] = (string) $fields['description'];
        }
        $details = ListingManagerPublishStatus::normalizeDetails($details);
        $ready = ListingManagerPublishStatus::readiness(
            $title,
            $price,
            $hydrated['quantity'] ?? null,
            $details,
            'draft',
            $channelName
        );

        ListingManagerChannelDraft::create([
            'channel_id' => $channelId,
            'seller_sku' => $sku,
            'asin' => $hydrated['asin'] ?? null,
            'title' => $title,
            'thumbnail_image' => $hydrated['thumbnail'] ?? null,
            'price' => $price,
            'quantity' => $hydrated['quantity'] ?? null,
            'status' => $ready['ready'] ? 'ready' : 'draft',
            'listing_details' => $details,
            'amazon_snapshot' => $hydrated['snapshot'] ?? null,
            'created_by' => optional(Auth::user())->id,
            'notes' => 'Draft created from Update on All Platforms because this SKU is not live on '.$channelName.'.',
        ]);
    }

    /**
     * @param  list<string>  $marketplaceKeys
     * @param  list<string>  $parts  title|bullets|description|price|images
     * @return array<string, array{success: bool, message: string, parts: array<string, string>}>
     */
    public function pushToMarketplaces(string $sku, array $marketplaceKeys, array $fields, array $parts): array
    {
        $results = [];
        $sku = trim($sku);
        $parts = self::normalizeParts($parts);
        $bullets = in_array('bullets', $parts, true) && is_array($fields['bullets'] ?? null)
            ? self::cleanBullets($fields['bullets'])
            : [];
        $images = in_array('images', $parts, true) && is_array($fields['images'] ?? null)
            ? $this->publicImageUrls(self::cleanImages($fields['images']))
            : [];

        foreach ($marketplaceKeys as $key) {
            $key = strtolower(trim($key));
            if ($key === '') {
                continue;
            }
            $partResults = [];
            $ok = true;
            $messages = [];

            if (in_array('title', $parts, true) && trim((string) ($fields['title'] ?? '')) !== '') {
                $res = $this->pushTitle($key, $sku, trim((string) $fields['title']));
                $partResults['title'] = $res['message'];
                $ok = $ok && $res['success'];
                $messages[] = 'Title: '.$res['message'];
            }
            if ($bullets !== []) {
                $res = $this->pushBullets($key, $sku, $bullets);
                $partResults['bullets'] = $res['message'];
                $ok = $ok && $res['success'];
                $messages[] = 'Bullets: '.$res['message'];
            }
            if (in_array('description', $parts, true) && trim((string) ($fields['description'] ?? '')) !== '') {
                $res = $this->pushDescription($key, $sku, (string) $fields['description']);
                $partResults['description'] = $res['message'];
                $ok = $ok && $res['success'];
                $messages[] = 'Description: '.$res['message'];
            }
            if (in_array('price', $parts, true) && is_numeric($fields['price'] ?? null) && (float) $fields['price'] > 0) {
                $res = $this->pushPrice($key, $sku, (float) $fields['price']);
                $partResults['price'] = $res['message'];
                $ok = $ok && $res['success'];
                $messages[] = 'Price: '.$res['message'];
            }
            if ($images !== []) {
                $res = $this->pushImages($key, $sku, $images);
                $partResults['images'] = $res['message'];
                $ok = $ok && $res['success'];
                $messages[] = 'Images: '.$res['message'];
            }

            if ($messages === []) {
                $ok = false;
                $messages[] = 'Nothing to push for this marketplace.';
            }

            $results[$key] = [
                'success' => $ok,
                'message' => implode(' ', $messages),
                'parts' => $partResults,
            ];
        }

        return $results;
    }

    /**
     * Map a Channel Master name to a Product Master marketplace key.
     */
    public static function marketplaceKeyFromChannel(string $channelName): ?string
    {
        $n = ListingChannelCounts::normalize($channelName);
        $aliases = [
            'amazon' => 'amazon',
            'amazonfba' => 'amazon',
            'ebay' => 'ebay',
            'ebay1' => 'ebay',
            'ebayone' => 'ebay',
            'ebay2' => 'ebay2',
            'ebaytwo' => 'ebay2',
            'ebay3' => 'ebay3',
            'ebaythree' => 'ebay3',
            'temu' => 'temu',
            'temu2' => 'temu2',
            'temutwo' => 'temu2',
            'tiktok' => 'tiktok',
            'tiktokshop' => 'tiktok',
            'tiktok2' => 'tiktok2',
            'tiktokshop2' => 'tiktok2',
            'tiktoktwo' => 'tiktok2',
            'reverb' => 'reverb',
            'reverbcom' => 'reverb',
            'faire' => 'faire',
            'walmart' => 'walmart',
            'wayfair' => 'wayfair',
            'bestbuy' => 'bestbuy',
            'bestbuyusa' => 'bestbuy',
            'macy' => 'macy',
            'macys' => 'macy',
            'doba' => 'doba',
            'shein' => 'shein',
            'aliexpress' => 'aliexpress',
            'alibaba' => 'alibaba',
            'newegg' => 'newegg',
            'topdawg' => 'topdawg',
            'shopify' => 'shopify_main',
            'shopifymain' => 'shopify_main',
            'shopifypls' => 'shopify_pls',
            'shopifyb5c' => 'shopify_b5c',
            'purchasingpower' => 'purchasing_power',
        ];

        return $aliases[$n] ?? (isset(ProductMasterMarketplaceMaps::descriptionServiceMap()[$n]) ? $n : null);
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function pushTitle(string $marketplace, string $sku, string $title): array
    {
        if (in_array($marketplace, ['ebay', 'ebay2', 'ebay3'], true)) {
            return $this->pushEbayTitle($marketplace, $sku, $title);
        }

        $class = $this->serviceClass($marketplace);
        if ($class === null) {
            return ['success' => false, 'message' => 'Title push is not configured for this marketplace.'];
        }

        try {
            $service = app($class);
            if (! method_exists($service, 'updateTitle')) {
                return ['success' => false, 'message' => 'Title update is not supported on this marketplace.'];
            }
            $result = $service->updateTitle($sku, $title);

            return $this->normalizeResult($result, 'Title updated.');
        } catch (\Throwable $e) {
            Log::warning('ListingManager title push failed', ['mp' => $marketplace, 'sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function pushDescription(string $marketplace, string $sku, string $html): array
    {
        $map = ProductMasterMarketplaceMaps::descriptionServiceMap();
        if (! isset($map[$marketplace])) {
            return ['success' => false, 'message' => 'Description push is not configured for this marketplace.'];
        }

        [$class, $method] = $map[$marketplace];

        try {
            $service = app($class);
            if (! method_exists($service, $method)) {
                return ['success' => false, 'message' => 'Description update is not supported on this marketplace.'];
            }
            $twoArgOnly = in_array($marketplace, ['ebay', 'ebay2', 'ebay3', 'doba', 'walmart', 'faire', 'shein', 'aliexpress'], true);
            $result = $twoArgOnly
                ? $service->{$method}($sku, $html)
                : $service->{$method}($sku, $html, []);

            return $this->normalizeResult($result, 'Description updated.');
        } catch (\Throwable $e) {
            Log::warning('ListingManager description push failed', ['mp' => $marketplace, 'sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function pushPrice(string $marketplace, string $sku, float $price): array
    {
        $class = $this->serviceClass($marketplace);
        if ($class === null) {
            return ['success' => false, 'message' => 'Price push is not configured for this marketplace.'];
        }

        try {
            $service = app($class);
            if (! method_exists($service, 'updatePrice')) {
                return ['success' => false, 'message' => 'Price update is not supported on this marketplace.'];
            }
            $result = $service->updatePrice($sku, $price);

            return $this->normalizeResult($result, 'Price updated.');
        } catch (\Throwable $e) {
            Log::warning('ListingManager price push failed', ['mp' => $marketplace, 'sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $bullets
     * @return array{success: bool, message: string}
     */
    private function pushBullets(string $marketplace, string $sku, array $bullets): array
    {
        $class = ProductMasterMarketplaceMaps::bulletServiceMap()[$marketplace] ?? null;
        if ($class === null || ! class_exists($class)) {
            return ['success' => false, 'message' => 'Bullet point push is not configured for this marketplace.'];
        }

        try {
            $service = app($class);
            if (! method_exists($service, 'updateBulletPoints')) {
                return ['success' => false, 'message' => 'Bullet point update is not supported on this marketplace.'];
            }
            $result = $service->updateBulletPoints($sku, implode("\n", $bullets));

            return $this->normalizeResult($result, 'Bullet points updated.');
        } catch (\Throwable $e) {
            Log::warning('ListingManager bullets push failed', ['mp' => $marketplace, 'sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $images  public https URLs
     * @return array{success: bool, message: string}
     */
    private function pushImages(string $marketplace, string $sku, array $images): array
    {
        $entry = ProductMasterMarketplaceMaps::imagePushMap()[$marketplace] ?? null;
        if ($entry === null) {
            return ['success' => false, 'message' => 'Image push is not configured for this marketplace.'];
        }
        [$class, $method] = $entry;

        try {
            $service = app($class);
            if (! method_exists($service, $method)) {
                return ['success' => false, 'message' => 'Image update is not supported on this marketplace.'];
            }
            // Some services take a third "mode" argument (replace|append); always replace so the
            // marketplace gallery mirrors what the seller sees in the modal.
            $arity = (new \ReflectionMethod($service, $method))->getNumberOfParameters();
            $result = $arity >= 3
                ? $service->{$method}($sku, $images, 'replace')
                : $service->{$method}($sku, $images);

            return $this->normalizeResult($result, 'Images updated.');
        } catch (\Throwable $e) {
            Log::warning('ListingManager images push failed', ['mp' => $marketplace, 'sku' => $sku, 'error' => $e->getMessage()]);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Marketplaces must be able to download the photos: prefer CDN originals over app-served copies
     * and turn relative /storage paths into absolute URLs.
     *
     * @param  list<string>  $images
     * @return list<string>
     */
    private function publicImageUrls(array $images): array
    {
        $out = [];
        foreach (ListingManagerImageStore::publishUrls($images, $images) as $url) {
            $url = trim((string) $url);
            if (str_starts_with($url, '//')) {
                $url = 'https:'.$url;
            }
            if (preg_match('#^https?://#i', $url) && ! in_array($url, $out, true)) {
                $out[] = $url;
            }
        }

        return $out;
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function pushEbayTitle(string $marketplace, string $sku, string $title): array
    {
        $table = ProductMasterMarketplaceMaps::metricsTableMap()[$marketplace] ?? null;
        $class = $this->serviceClass($marketplace);
        if (! $table || $class === null || ! Schema::hasTable($table)) {
            return ['success' => false, 'message' => 'eBay metrics table is missing.'];
        }

        $itemId = '';
        try {
            $row = DB::table($table)->whereRaw('LOWER(TRIM(sku)) = ?', [mb_strtolower($sku)])->first();
            $itemId = trim((string) ($row->item_id ?? ''));
        } catch (\Throwable) {
            $itemId = '';
        }
        if ($itemId === '') {
            return ['success' => false, 'message' => 'No eBay item ID found for this SKU.'];
        }

        try {
            $service = app($class);
            if (! method_exists($service, 'updateTitle')) {
                return ['success' => false, 'message' => 'eBay title update is not available.'];
            }

            return $this->normalizeResult($service->updateTitle($itemId, $title, $sku), 'Title updated.');
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function serviceClass(string $marketplace): ?string
    {
        $map = ProductMasterMarketplaceMaps::descriptionServiceMap();
        if (isset($map[$marketplace][0])) {
            return $map[$marketplace][0];
        }
        $image = ProductMasterMarketplaceMaps::imagePushMap();

        return $image[$marketplace][0] ?? null;
    }

    /**
     * @return array{success: bool, message: string}
     */
    private function normalizeResult(mixed $result, string $okMessage): array
    {
        if ($result === true) {
            return ['success' => true, 'message' => $okMessage];
        }
        if ($result === false) {
            return ['success' => false, 'message' => 'Update failed.'];
        }
        if (is_array($result)) {
            $success = (bool) ($result['success'] ?? $result['ok'] ?? false);
            $message = (string) ($result['message'] ?? '');
            if ($message === '' && ! empty($result['errors'][0]['message'])) {
                $message = (string) $result['errors'][0]['message'];
            }
            if ($message === '') {
                $message = $success ? $okMessage : 'Update failed.';
            }

            return [
                'success' => $success,
                'message' => $message,
            ];
        }

        return ['success' => false, 'message' => 'Unexpected marketplace response.'];
    }
}
