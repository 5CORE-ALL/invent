# Image Master Page Overview

Last reviewed: 2026-06-25

## User-Facing Page

- URL: `/image-master`
- Route name: `image.master`
- View: `resources/views/image-master.blade.php`
- Controller: `App\Http\Controllers\ProductMaster\ImageMasterController`

## Pull vs push (UI)

| Action | UI |
|---|---|
| **Shopify pull** | **Run in BG** (toolbar modal or row download icon) |
| **Marketplace push** | Row marketplace tiles, **Push ALL** |

No dry-run buttons in the UI. Backend APIs still accept `dry_run: true` for developer/testing use only.

## Routes

- `POST /image-master/push` — live push (optional `dry_run: true` for API tests only)
- `POST /image-master/shopify-pull-one` — single SKU pull (optional `dry_run: true` for API tests only)
- `POST /image-master/shopify-pull/start` — background live pull

## Shopify pull

- **Run in BG** writes to `main_image`, `image1`–`image12`.
- Does not push to marketplaces.
- Background worker: `App\Jobs\RunShopifyImagePullJob` on queue **`shopify-image-pull`** (dedicated — not the default queue).
- Requires a dedicated queue worker on the server:

```bash
php artisan queue:work --queue=shopify-image-pull --sleep=3 --tries=1 --timeout=14400
```

- Supervisor template: `config/supervisor/shopify-image-pull-worker.conf`
- Crontab watchdog (starts worker if not running, every 5 min): `scripts/cron-shopify-image-pull-worker.sh`
- Progress state: `storage/app/shopify-image-pull/job.json`; log: `storage/logs/shopify-image-pull.log`.
- Manual re-dispatch: `php artisan image-master:shopify-pull-run` (or `--sync` to run in the current shell).

## Push paths

| Path | Behavior |
|---|---|
| Row marketplace tile | Live replace push, per-platform main image |
| **Push ALL** | Live bulk push to all 11 marketplaces |
| Push Selected | Stub |

## Marketplace push (background job)

- Push runs as a background job; progress state: `storage/app/image-master-push/job.json`, store class `App\Services\Support\ImageMasterPushJobStore`.
- Requires the **push** queue worker on the server (separate from the pull worker). A stuck/dead "running" job is auto-cleared once stale (`isStale`, 300s); the user can also force-cancel it (`forceStop`). On cancel/stale-clear the `ShouldBeUnique` lock is released (`releaseUniqueJobLock`) so a new push can dispatch.

### Image source = CDN (all marketplaces fetch the same URL)

- Images uploaded in the modal are saved locally **and** uploaded to the **Shopify Files CDN** (`ShopifyApiService::uploadImageToShopifyCdn`); the CDN URL + file id are stored on `product_images.cdn_url` / `cdn_file_id` (migration `2026_06_24_120000_add_cdn_columns_to_product_images`).
- This fixes the old problem where self-hosted URLs were unreachable by marketplaces. Every marketplace push now sends the public CDN URL.
- **Auto-resize:** images over Shopify's 20-megapixel limit are downscaled before upload (`downscaleImageBytes`, GD).
- **Upload cap:** the modal blocks uploads that would exceed 20 images (shows an error, adds nothing — user removes extras).

### Shopify push (GraphQL, not REST)

- When all URLs are public, `updateListingImages` routes to `attachProductImagesViaGraphql` (one `productCreateMedia` + `productDeleteMedia` + `productReorderMedia`) — avoids REST ~2 req/sec 429 drops. REST is the fallback when URLs aren't all public.

### Reverb push (order + main correctness)

- `ReverbApiService::replaceReverbListingImages` rebuilds the gallery in the correct **display order** (Reverb has no position field; oldest-uploaded photo = main). Reverb won't allow removing all photos on a live listing, so it uploads the new main first, deletes the anchor, then uploads the rest cumulatively, and verifies the final **display** count (`reverbDisplayPhotoCount` / `waitForReverbDisplayCount`). If the gallery already matches the last push it skips the rebuild (fast path).
- Note: Reverb's `/images/` endpoint is ID-sorted, **not** display order — use `listing.photos[]` for true order.

## Delete-on-Save (removed images)

- Removing an image with ✕ only drops it from the on-screen list; **nothing is deleted until Save**. Cancelling deletes nothing.
- Frontend tracks `knownImageUrls` and sends `removed_urls` (known − current) with the save.
- `ImageMasterController::purgeRemovedSkuImages` deletes the local file, DB row, and **Shopify CDN file** for a removed image **only when all three hold**: (1) the row belongs to this SKU, (2) it matches a URL the user actually removed (`cdn_url` or local basename), (3) it is **not** in the final saved set (critical guard — a kept image is never deleted even if `removed_urls` is wrong).
- CDN delete result is logged per image: `cdn_delete` = `deleted` | `not_found` | `error` | `none` (look for `Image Master purge:` lines in `laravel.log`).

## Other notes

- Per-marketplace main image is clamped to the available image count in JS (`normalizeMainByMarketplace`) and server-side (`sanitizeMainByMarketplace`).
- Push progress box: shown at the top (not sticky), does not auto-hide (user closes it), survives page refresh (`resumeImagePushProgress`), and the spinner stops when the job finishes.

## Debugging

- Push failures → `storage/logs/laravel.log`, progress box at top of page; job state in `storage/app/image-master-push/job.json`
- CDN delete result → `Image Master purge:` lines in `storage/logs/laravel.log` (`cdn_delete` field)
- Pull job stuck → check queue worker is running, then `storage/app/shopify-image-pull/job.json`, `storage/logs/shopify-image-pull.log`
- Push job stuck → user can cancel from the UI; auto-clears when stale (300s)

## Deploy checklist (when shipping image-master changes)

- `php artisan migrate` (adds `cdn_url` / `cdn_file_id` if not yet applied)
- `php artisan config:clear && php artisan view:clear`
- `php artisan queue:restart` (so workers pick up new code)
- Hard-refresh the page (new `image-master.blade.php` JS)
