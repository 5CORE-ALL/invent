# Video Master Page Overview

Last reviewed: 2026-07-04

## User-Facing Page

- URL: `/video-master`
- Route name: `video.master`
- View: `resources/views/video-master.blade.php`
- Controller: `App\Http\Controllers\ProductMaster\VideoMasterController`
- Push runner: `App\Services\Support\VideoMasterPushRunner` (background job)

## Pull vs push (UI)

| Action | UI |
|---|---|
| **Shopify pull** | **Run in BG** (toolbar modal or row download icon) |
| **Marketplace push** | Row marketplace tiles (**24** channels), **Push ALL** |

Backend APIs accept `dry_run: true` on `POST /video-master/push`. Artisan audit: `php artisan marketplace:audit-master video --dry-run`.

## Routes

- `GET /video-master-data` — product rows + `video_master_json` per marketplace
- `POST /video-master/push` — live push (optional `dry_run: true`)
- `POST /video-master/save-pm` — save `product_master` video slots (`video1`–`video5`)
- `POST /video-master/upload` — upload video files
- `GET /video-master/amazon-videos` / `ebay-videos` / `sku-videos` — fetch helpers
- Shopify pull: `shopify-pull/start`, `shopify-pull-one`, status/pause/resume/stop

## Marketplace Service Map

Canonical map: `ProductMasterMarketplaceMaps::videoPushMap()` — **24** marketplaces.

| Code | Service method | Metrics persistence |
|------|----------------|---------------------|
| ebay / ebay2 / ebay3 | `updateListingVideos` | `ebay_*_metrics.video_master_json` |
| amazon / temu / temu2 / wayfair / bestbuy / macy / doba / walmart / faire / shein | `updateVideos` | respective `*_metrics` |
| shopify_main / shopify_pls / shopify_b5c | `updateVideos` | `shopify_metrics` / `shopify_pls_metrics` |
| reverb | `updateVideos` | `reverb_products` (special table) |
| aliexpress / alibaba | `updateVideos` | `aliexpress_metric` / `alibaba_metrics` |
| purchasing_power / newegg / topdawg / tiktok / tiktok2 | `updateVideos` | respective `*_metrics` |

UI tiles: `AllMarketplaceChannelRegistry` with `video => true` via `partials/all-marketplace-master-channels`, `allMpMaster => video`. Table shows **24** enabled push buttons (gChannels + gShopify).

Services without a real API override fall back to `VideoMasterMarketplaceMethods` trait (returns “not yet implemented” on live push). Dry-run audit still passes when the method exists.

## Safe testing (dry-run)

Default test SKU: `SP 12120 4OHM GTR` (`config/marketplace_testing.php` → `video_sku`).

```bash
php artisan marketplace:audit-master video --dry-run --sku="SP 12120 4OHM GTR"
```

**Content required for live push:** at least one video URL in `product_master` (`video1`–`video5`) or uploaded SKU videos. Empty video list fails push (except Shopify clear-all replace mode).

Blank `video_master_json` in metrics does **not** block push when PM has video URLs.

## Per-marketplace video limits (controller)

| Marketplace | Max videos |
|-------------|------------|
| amazon | 3 |
| doba, walmart | 1 |
| reverb | 5 |
| shopify_main, shopify_pls | 5 (PM_MAX_VIDEOS) |
| default | 5 |

**Add mode** (append): shopify_main, shopify_pls, reverb only. Others downgrade to replace.

**Clear-all** (empty list): shopify_main, shopify_pls only.

## Shopify pull

- Writes to `product_master` video slots / `product_videos` table.
- Background worker: `App\Jobs\RunShopifyVideoPullJob` on queue **`shopify-video-pull`**.
- Progress: `storage/app/shopify-video-pull/job.json`; log: `storage/logs/shopify-video-pull.log`.

## Marketplace push (background job)

- Push runs via `RunVideoMasterPushJob` → `VideoMasterPushRunner`.
- Progress: `storage/app/video-master-push/job.json` (`VideoMasterPushJobStore`).
- Requires dedicated push queue worker (same pattern as Image Master).

Non-Shopify pushes rewrite local storage URLs to public URLs before API call (`rewriteLocalStorageUrlsToPublic`).

## Current status (audit 2026-07-04, SKU `SP 12120 4OHM GTR`)

- **Dry-run wiring:** 24/24 ready
- **Content:** 0 video URL(s) in `product_master` — live push will reject until videos are uploaded or pulled
- **Metrics rows:** ebay, ebay3, temu, doba, walmart (listing sync); `video_master_json` empty until first push
- **UI buttons:** 24/24 enabled marketplaces
- **Live push:** not run (deferred)

## Debugging

- Push failures → `storage/logs/laravel.log`, `storage/app/video-master-push/job.json`
- Pull stuck → `storage/app/shopify-video-pull/job.json`, `storage/logs/shopify-video-pull.log`

## Deploy checklist

- `php artisan migrate` (video columns / metrics if needed)
- `php artisan config:clear && php artisan view:clear`
- `php artisan queue:restart`
- Hard-refresh `video-master.blade.php` after JS changes
