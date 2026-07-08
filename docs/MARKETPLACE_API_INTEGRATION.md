# Marketplace API Integration

Last updated: 2026-07-05  
Reference implementation: `App\Services\ShopifyApiService` (rate limits, structured logging, `array{success,message}` responses).

## Architecture

| Layer | Path | Role |
|-------|------|------|
| Registry | `app/Services/Support/ProductMasterMarketplaceMaps.php` | Maps marketplaces → metrics tables & API service classes |
| Credentials | `app/Services/Support/MarketplaceApiConfigService.php` | Static credential checks for push UIs |
| Character limits | `app/Services/Support/MarketplaceCharacterLimits.php` | Title / description / bullet limits |
| Title push | `app/Services/MarketplaceTitlePushService.php` | Central Title Master API dispatch |
| Exception helper | `app/Services/Support/Concerns/HandlesMarketplaceApiExceptions.php` | Shared HTTP/auth error handling |
| Push retries | `app/Http/Controllers/ProductMaster/Concerns/RetriesMarketplacePush.php` | 4-attempt retry with backoff (bullets, description, title) |
| Shopify rate limit | `app/Services/Support/Concerns/ShopifyAdminRateLimitRetry.php` | Shopify REST/GraphQL leaky-bucket retry |

## Master Modules

### Title Master
- **Registry:** `AllMarketplaceChannelRegistry::titleMeta()` — 24 channels, tiers 150/100/80/60
- **Per-tile push:** `POST /api/marketplaces/push-single` → `ProductMasterController::pushSingleMarketplace` → `MarketplaceTitlePushService::push`
- **Bulk Title 150:** `POST /api/marketplaces/push-title` → all `titlePushKeysForType('150')` (15 channels) via `MarketplaceTitlePushService` (not legacy direct service calls)
- **AI push:** `pushTitleToMarketplace` — saves metrics row **and** calls live API when channel is in `config/marketplaces.php` `api_title_push`
- **Requires content:** non-empty PM tier (`title150`, `title100`, `title80`, or `title60` per marketplace)
- **Pull:** Not implemented (titles edited in PM / AI)
- **Audit:** `php artisan marketplace:audit-master title --sku="SP 12120 4OHM GTR"`
- **Overview:** `docs/TITLE_MASTER_OVERVIEW.md`

### Description Master
- **Push:** `DescriptionMasterController` → `ProductMasterMarketplaceMaps::descriptionServiceMap()` (**24** marketplaces)
- **Requires content:** non-empty PM tier (`description_1500`–`description_600`) — empty text fails push before API call
- **Shopify pull:** not implemented (bullets/images have pull)
- **Audit:** `php artisan marketplace:audit-master description --dry-run --sku="SP 12120 4OHM GTR"`

### Bullet Points Master
- **Push:** `BulletPointMasterController` → `ProductMasterMarketplaceMaps::bulletServiceMap()` (**24** marketplaces)
- **Metrics:** `MarketplaceMetricsTableResolver::bulletTableMap()` — push allowed only when the mapped table exists
- **Shopify pull:** `ShopifyBulletPullRunner` + job/command
- **Default bullet rule:** 5 bullets × 90–100 chars (PM AI); Amazon/eBay/Shopify allow longer per-bullet text in API
- **Audit:** `php artisan marketplace:audit-master bullet --dry-run --sku="SP 12120 4OHM GTR"`

### Image Master
- **Push:** `ImageMasterPushRunner` (queued) → `ProductMasterMarketplaceMaps::imagePushMap()` (**24** marketplaces)
- **Shopify pull:** `ShopifyImagePullRunner` + dedicated `shopify-image-pull` queue
- **HTTP dry_run:** `POST /image-master/push` with `dry_run: true`
- **Audit:** `php artisan marketplace:audit-master image --dry-run --sku="SP 12120 4OHM GTR"`
- **Clear-all / add mode:** Supported on Shopify + Reverb; most others replace only

### Video Master
- **Push:** `VideoMasterPushRunner` (queued) → `ProductMasterMarketplaceMaps::videoPushMap()` (**24** marketplaces)
- **Shopify pull:** `ShopifyVideoPullRunner` + `shopify-video-pull` queue
- **HTTP dry_run:** `POST /video-master/push` with `dry_run: true`
- **Audit:** `php artisan marketplace:audit-master video --dry-run --sku="SP 12120 4OHM GTR"`
- **Fallback:** `VideoMasterMarketplaceMethods` trait when a service has no real `updateVideos` override
- **Add / clear-all:** Shopify Main/PLS (+ Reverb add); clear-all videos only Shopify Main/PLS

## Marketplace Support Matrix

| Marketplace | Title API | Bullets | Description | Images | Video | Pull (listing content) |
|-------------|-----------|---------|-------------|--------|-------|------------------------|
| shopify_main | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ bullets/images/video/desc |
| shopify_pls | ✓ | ✓ | ✓ | ✓ | ✓ | desc |
| amazon | ✓ | ✓ | ✓ (A+) | ✓ | ✓ | — |
| ebay / ebay2 / ebay3 | ✓ (item_id) | ✓ | ✓ | ✓ | ✓ | — |
| temu / temu2 | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| walmart | ✓ | ✓ | ✓ (shortDescription feed) | ✓ | ✓ (1 URL) | — |
| wayfair | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| bestbuy | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| macy / faire | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| reverb | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| doba | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| shein / aliexpress / alibaba | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| newegg / topdawg / tiktok / tiktok2 | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| shopify_b5c / purchasing_power | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| sheet-only channels | — | — | — | — | — | — |

Channels without Product Master API: Depop, Instagram Shop, Mercari, FB Marketplace/Shop, Tiendamia, Shopify B2B, Vinted (no creds).

## Marketplace-Specific Notes

### Shopify (reference)
- REST + GraphQL; `ShopifyAdminRateLimitRetry` on all calls
- Title via product PUT; bullets embedded in `body_html`; images via GraphQL when URLs are public
- Post-push bullet verification with re-fetch

### Walmart
- All listing content via **MP_ITEM XML feeds** (async poll until PROCESSED)
- **Description** uses `shortDescription` only; **bullets** use `shelfDescription` + `shortDescription`
- **Video:** max 1 URL per feed
- Inventory/pricing uses separate `WalmartApiService` (not `WalmartService`)

### eBay
- Trading API XML; title push requires `item_id` from `ebay_*_metrics`
- Title limit: 80 characters

### Temu
- Signed OpenAPI; title update sends full SKU payload (not title-only)

### Best Buy / Macy's / Purchasing Power
- Mirakl Connect API; bullets vs `longDescription` are separate fields
- Purchasing Power uses `PurchasingPowerApiService` (Mirakl channel `purchasingpower`)

### Newegg / TopDawg
- Newegg: `NeweggApiService::updateBulletPoints` → `BulletDescription` via content API (SKU-based)
- TopDawg: `TopDawgApiService` supplier product update API

### TikTok Shop
- `TikTokShopService` — bullets via `updateBulletPoints`; product resolved by SKU catalog scan or numeric `product_id`
- Metrics: `tiktok_metrics` (shared by `tiktok` and `tiktok2`)

### Reverb
- Listing content via PUT `/api/listings/{id}` (`description`, `plain_text_description`, title, price, images, videos).
- **Bullets:** `ReverbApiService::updateBulletPoints()` strips legacy bullet HTML (shared `ShopifyBulletPointsFormatter::stripLegacyBulletBlocksForMarketplace()`), prepends a `Highlighted Features` block, and verifies via PUT + GET read-back.
- **Duplicate listings:** `getAllListingIdsBySku()` matches normalized SKU (trim, collapse whitespace, case-insensitive) across `my/listings?state=all`. Bullet push updates **every** matching listing; title/description/image/video still resolve one primary listing via `getListingIdBySku()`.
- **Sold-out:** Brand New / Mint listings with zero inventory still accept description updates; used sold listings may be API-locked.
- Lookup: `reverb_products.reverb_listing_id` + API `my/listings?sku=&state=all` (always use `state=all` for drafts/sold copies).

### Channels without API push
Depop, Instagram Shop, Mercari, FB Marketplace/Shop, Tiendamia, Shopify B2B (sheet/manual), Vinted (credentials missing).

## Exception Handling Conventions

1. Services return `['success' => bool, 'message' => string]` (title may return `bool` on legacy Shopify methods — wrapped by `MarketplaceTitlePushService`).
2. Never throw to controllers for expected API failures; log with SKU/marketplace context.
3. Controllers use `RetriesMarketplacePush` for transient failures (title, bullets, description).
4. Failed single-SKU push does not abort bulk jobs — runners log and continue.

## Configuration

- `config/marketplaces.php` — title limits, metrics tables, AI Title Manager UI
- `config/services.php` — per-channel credentials
- `.env` — see `shopify.env.example` and channel-specific env vars

## Safe testing (live platforms)

Product Master pushes update **real listings**. Use this order:

### Run all masters (dry-run) and save results

```bash
php artisan marketplace:audit-master all --save
# or
php scripts/marketplace-audit-all-masters.php
```

Results saved to:
- `docs/MARKETPLACE_MASTER_DRY_RUN_RESULTS.md` (human-readable)
- `storage/app/marketplace-master-audit/latest.json` (machine-readable)

### Per-master dry-run

```bash
php artisan marketplace:audit-master bullet --dry-run
php artisan marketplace:audit-master title --dry-run
php artisan marketplace:audit-master description --dry-run
php artisan marketplace:audit-master image --dry-run
php artisan marketplace:audit-master video --dry-run
```

Test SKU defaults to `SP 12120 4OHM GTR` from `config/marketplace_testing.php`.

Image / Video Master HTTP APIs also accept `dry_run: true` in the push request body.

### Phase 2 — Live single push (after all masters audited)

Run live only when you approve — one SKU, one channel at a time.

- `docs/TITLE_MASTER_OVERVIEW.md`
- `docs/BULLET_POINTS_OVERVIEW.md`
- `docs/DESCRIPTION_MASTER_OVERVIEW.md`
- `docs/IMAGE_MASTER_OVERVIEW.md`
- `docs/VIDEO_MASTER_OVERVIEW.md`
