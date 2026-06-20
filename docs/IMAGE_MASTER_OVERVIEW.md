# Image Master Page Overview

Last reviewed: 2026-06-19

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
- Progress state: `storage/app/shopify-image-pull/job.json`; log: `storage/logs/shopify-image-pull.log`.
- Manual re-dispatch: `php artisan image-master:shopify-pull-run` (or `--sync` to run in the current shell).

## Push paths

| Path | Behavior |
|---|---|
| Row marketplace tile | Live replace push, per-platform main image |
| **Push ALL** | Live bulk push to all 11 marketplaces |
| Push Selected | Stub |

## Debugging

- Push failures → `storage/logs/laravel.log`, progress box under table
- Pull job stuck → check queue worker is running, then `storage/app/shopify-image-pull/job.json`, `storage/logs/shopify-image-pull.log`
