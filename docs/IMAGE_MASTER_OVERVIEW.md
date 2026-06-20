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

## Push paths

| Path | Behavior |
|---|---|
| Row marketplace tile | Live replace push, per-platform main image |
| **Push ALL** | Live bulk push to all 11 marketplaces |
| Push Selected | Stub |

## Debugging

- Push failures → `storage/logs/laravel.log`, progress box under table
- Pull job stuck → `storage/app/shopify-image-pull/job.json`, `storage/logs/shopify-image-pull.log`
