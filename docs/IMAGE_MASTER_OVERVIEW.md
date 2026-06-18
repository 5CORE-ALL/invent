# Image Master Page Overview

Last reviewed: 2026-06-19

## User-Facing Page

- URL: `/image-master`
- Route name: `image.master`
- View: `resources/views/image-master.blade.php`
- Controller: `App\Http\Controllers\ProductMaster\ImageMasterController`

## Main Files

- `routes/web.php`
  - Defines Image Master routes for data load, save, upload, push, and Shopify pull.
- `app/Http/Controllers/ProductMaster/ImageMasterController.php`
  - Loads page data, saves Product Master images, uploads local files, pushes images to marketplace services, and pulls Shopify images into Product Master.
- `resources/views/image-master.blade.php`
  - UI for table preview, edit modal, marketplace push buttons, Shopify pull modal, and background job monitoring.
- `app/Models/ProductMaster.php`
  - Stores `main_image` and `image1` through `image12`.
- `app/Models/ProductImage.php`
  - Stores uploaded image files under `storage/app/public/products/{sku}/`.
- `app/Services/Support/ShopifyImagePullJobStore.php`
  - Persists background Shopify image pull job state in `storage/app/shopify-image-pull/job.json`.
- `app/Console/Commands/RunShopifyImagePull.php`
  - Background worker command: `image-master:shopify-pull-run`.

## Routes And Endpoints

Page routes:

- `GET /image-master` -> `ImageMasterController@index`
- `GET /image-master-data` -> `ImageMasterController@getData`
- `POST /image-master/save-pm` -> `ImageMasterController@saveProductMasterImages`
- `POST /image-master/upload` -> `ImageMasterController@uploadImages`
- `GET /image-master/sku-images` -> `ImageMasterController@getSkuImages`
- `DELETE /image-master/sku-image/{id}` -> `ImageMasterController@deleteSkuImage`
- `POST /image-master/push` -> `ImageMasterController@pushToMarketplace`

Shopify image pull routes:

- `POST /image-master/shopify-pull-one` -> `ImageMasterController@pullShopifyImagesToMaster`
- `POST /image-master/shopify-pull/start` -> `ImageMasterController@startShopifyPullJob`
- `GET /image-master/shopify-pull/status` -> `ImageMasterController@shopifyPullJobStatus`
- `POST /image-master/shopify-pull/pause` -> `ImageMasterController@pauseShopifyPullJob`
- `POST /image-master/shopify-pull/resume` -> `ImageMasterController@resumeShopifyPullJob`
- `POST /image-master/shopify-pull/stop` -> `ImageMasterController@stopShopifyPullJob`

## Data Loading Flow

1. `/image-master` calls `ImageMasterController@index`.
2. On page load, the blade calls `fetch('/image-master-data')`.
3. `getData()` calls `ProductMasterController@getViewProductData()` to load all `product_master` rows.
4. Product rows include `SKU`, `Parent`, `main_image`, `image1` through `image12`, and legacy `image_path`.
5. `getData()` also loads saved marketplace image JSON from metrics tables via `image_master_json` or `image_urls`.
6. Each product row receives:
   - `image_master`: object keyed by marketplace, containing saved image URL JSON.
   - `preview_thumb`: first displayable image URL for the table preview column.

Preview priority:

1. Saved Product Master images: `main_image`, `image1`, `image2`, ...
2. Fallback legacy preview: `image_path` (Shopify CDN or local storage path from Product Master Values)

## UI Behavior

- The table filters out SKUs containing `PARENT`.
- Columns shown:
  - SKU
  - Product Name
  - Preview thumbnail
  - Action buttons (Edit, per-row Shopify Pull)
  - **MARKET PLACES** — marketplace push/status tiles (same layout as Bullet Points Master)
  - **SHOPIFY** — Shopify Main and Shopify PLS tiles

### Marketplace table layout

Matches **Bullet Points Master** (`bullet-point-master.blade.php`): two marketplace columns instead of three.

| Column | Header label | Platforms (tile labels) |
|---|---|---|
| Marketplaces | `MARKET PLACES` | E1, E2, E3, M, A, T, R, W, B |
| Shopify | `SHOPIFY` | SM, PLS |

Platform keys in `GROUPS` (JavaScript):

- `gChannels`: `ebay`, `ebay2`, `ebay3`, `macy`, `amazon`, `temu`, `reverb`, `wayfair`, `bestbuy`
- `gShopify`: `shopify_main`, `shopify_pls`

Each row cell shows a horizontal row of **stack buttons** (`bp-mp-stack`):

- **Dot above tile** — green when `image_master[marketplace]` has saved pushed image JSON; empty gray when not pushed yet.
- **Colored tile** — click to push Product Master images to that marketplace (replace mode).
- Header uses small **pills** (`bp-mp-th-pill`) with the same colors as row tiles.

Toolbar buttons:

- Export, Import, Shopify Pull, Push Selected, Push ALL to All Marketplaces

- Edit modal:
  - Upload images locally
  - Reorder images by drag or arrow buttons
  - Remove images from the save list
  - Save to Product Master (`main_image`, `image1` through `image12`)
- Marketplace push is done from row buttons after saving, not from the edit modal.
- Table preview uses saved Product Master images when present; otherwise it falls back to legacy `image_path`.

## Master Save Flow

- Edit modal posts ordered image URLs to `/image-master/save-pm`.
- `saveProductMasterImages()` updates `product_master.main_image` and `product_master.image1` through `image12`.
- Empty save lists are allowed and clear Product Master image fields.
- Uploaded files are stored on the public disk and referenced by URL in Product Master.
- `public/storage` symlink must exist for local image previews to load in the browser.

## Shopify Image Pull Flow

Mirrors the Bullet Points Shopify pull pattern.

### Toolbar pull

- `Shopify Pull` opens a modal for filtered SKUs.
- Modal supports:
  - Run in BG
  - Pause
  - Resume
  - Stop
  - Progress bar and log output
- Scope defaults to currently filtered SKUs in the table.
- Before starting, the UI confirms that Shopify images will update Product Master image fields.

### Row pull

- Each row has a download icon beside Edit.
- Clicking it confirms and starts a background pull for that single SKU.
- The row button shows a spinner while the job is queued.

### Background worker

- Job state file: `storage/app/shopify-image-pull/job.json`
- Worker command: `php artisan image-master:shopify-pull-run`
- Log file: `storage/logs/shopify-image-pull.log`
- Waits 6 seconds between SKUs.
- Uses backend 429 retry/backoff for Shopify Admin API calls.
- Refresh does not stop the pull; the page polls status every 3 seconds and reloads table data when the job completes or stops.

### What pull saves

- Pull writes only to Product Master:
  - `main_image`
  - `image1` through `image12`
- Pull does not push to Shopify or other marketplaces.
- Pull does not modify `product_images` upload records.

### Image source priority per SKU

1. Shopify Admin API product images (`product.images`, ordered by `position`)
2. Public storefront product JSON (`/products/{handle}.js`)
3. Cached `shopify_catalog_products` image fields (`image_urls`, `images`, `image_src`)

Store selection is store-aware:

- If `shopify_catalog_variants.store` is `pls`, PLS Shopify credentials are used.
- Otherwise Main Shopify credentials are used.
- Falls back to `shopify_skus.variant_id` when catalog mapping is missing.

## Marketplace Push Flow

- Row marketplace buttons push saved Product Master images to one marketplace at a time.
- `POST /image-master/push` validates `{ sku, mode, updates: [{ marketplace, images }] }`.
- Allowed marketplace keys:
  - `ebay`
  - `ebay2`
  - `ebay3`
  - `macy`
  - `amazon`
  - `temu`
  - `wayfair`
  - `bestbuy`
  - `shopify_main`
  - `shopify_pls`
  - `reverb`
- Localhost storage URLs are rewritten to public URLs for external marketplaces.
- Shopify Main and Shopify PLS keep local storage URLs so their services can upload files as base64 attachments.
- On successful remote push, image URLs are saved to marketplace metrics tables and Shopify catalog tables when available.

## Marketplace Service Map

- `ebay` -> `App\Services\EbayApiService`
- `ebay2` -> `App\Services\Ebay2ApiService`
- `ebay3` -> `App\Services\EbayThreeApiService`
- `macy` -> `App\Services\MacysApiService`
- `amazon` -> `App\Services\AmazonSpApiService`
- `temu` -> `App\Services\TemuApiService`
- `wayfair` -> `App\Services\WayfairApiService`
- `bestbuy` -> `App\Services\BestBuyApiService`
- `shopify_main` -> `App\Services\ShopifyApiService`
- `shopify_pls` -> `App\Services\ShopifyPLSApiService`
- `reverb` -> `App\Services\ReverbApiService`

## Things To Watch While Debugging

- Preview blank but Shopify image exists: check whether Product Master image fields are empty and whether `image_path` fallback is present.
- Preview shows old Shopify image after save: saved Product Master images should take priority over legacy `image_path`.
- Shopify push fails with `No images could be uploaded to Shopify`: confirm local `/storage/...` URLs are not rewritten before Shopify service upload.
- Shopify pull fails with `Shopify variant mapping not found`: check `shopify_catalog_variants` or `shopify_skus`.
- Background pull appears stuck: inspect `storage/app/shopify-image-pull/job.json` and `storage/logs/shopify-image-pull.log`.
- Local previews fail: run `php artisan storage:link`.
