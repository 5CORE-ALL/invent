# Description Master Page Overview

Last reviewed: 2026-07-03

## User-Facing Pages

Description Master has **two related UIs**:

| Page | URL | View | Controller |
|------|-----|------|------------|
| **Description Master (DM1)** | `/product-description` | `resources/views/product-description.blade.php` | `App\Http\Controllers\ProductMaster\DescriptionMasterController` |
| **Description Master 2.0 (DM2)** | `/product-description-2` | `resources/views/product-description-2.blade.php` | `App\Http\Controllers\ProductMaster\DescriptionMaster2Controller` |

DM1 is the main marketplace description manager (tiered plain text + per-channel push).

DM2 is a separate structured HTML builder (bullets, images, features, specs, package, brand) with fetch from Amazon/eBay and push to Shopify Main + eBay1 only.

## Main Files

### DM1 (primary)

- `app/Http/Controllers/ProductMaster/DescriptionMasterController.php`
  - Paginated data load, AI generate, marketplace push, Amazon A+ fetch, regenerate with images, metrics reset.
- `resources/views/product-description.blade.php`
  - Table UI, tier modals, per-marketplace edit modal, bulk push, import/export.
- `app/Http/Controllers/ProductMaster/ProductMasterController.php`
  - `saveDescriptionData()` — saves Product Master tier columns via `/product-description/save`.
- `app/Services/Support/DescriptionWithImagesFormatter.php`
  - Builds HTML with embedded images for Amazon/regenerate flows.
- `app/Http/Controllers/ProductMaster/Concerns/RetriesMarketplacePush.php`
  - Automatic retry/backoff on marketplace push failures.

### DM2 (structured HTML)

- `app/Http/Controllers/ProductMaster/DescriptionMaster2Controller.php`
- `resources/views/product-description-2.blade.php`
- `app/Services/Support/ProductDescriptionV2HtmlBuilder.php`
- `app/Services/Support/EbayDescriptionToDm2Parser.php`

### Migrations / schema

- `database/migrations/2025_12_07_015000_add_description_column_to_product_master_table.php` — `product_description`
- `database/migrations/2026_03_26_120000_add_description_tier_columns_to_product_master_table.php` — `description_1500`, `description_1000`, `description_800`, `description_600`
- `database/migrations/2026_03_26_100000_add_description_master_to_marketplace_metrics_tables.php` — `description_master` on metrics tables
- `database/migrations/2026_04_04_100000_add_description_v2_columns_to_product_master.php` — DM2 structured fields

## Routes And Endpoints

### DM1

- `GET /product-description` -> `DescriptionMasterController@index`
- `GET /product-description-data` -> `DescriptionMasterController@getDescriptionMasterData`
- `POST /product-description/save` -> `ProductMasterController@saveDescriptionData`
- `POST /product-description/update` -> `DescriptionMasterController@pushDescriptionToMarketplaces`
- `POST /product-description/reset-marketplace` -> `DescriptionMasterController@resetMarketplaceDescription`
- `POST /product-description/generate` -> `DescriptionMasterController@generateDescriptionWithAI`
- `POST /product-description/fetch-amazon-aplus` -> `DescriptionMasterController@fetchAmazonAplusContent`
- `POST /product-description/regenerate-marketplace` -> `DescriptionMasterController@regenerateDescriptionForMarketplace`
- `POST /product-description/with-images` -> `DescriptionMasterController@getDescriptionWithImages`

### DM2

- `GET /product-description-2` -> `DescriptionMaster2Controller@index`
- `GET /product-description-2/data` -> `DescriptionMaster2Controller@getData`
- `POST /product-description-2/save` -> `DescriptionMaster2Controller@save`
- `POST /product-description-2/push` -> `DescriptionMaster2Controller@push`
- `POST /product-description-2/fetch/amazon` -> `DescriptionMaster2Controller@fetchAmazon`
- `POST /product-description-2/fetch/ebay` -> `DescriptionMaster2Controller@fetchEbay`

## Data Loading Flow (DM1)

1. `/product-description` renders `product-description.blade.php`.
2. Frontend calls `GET /product-description-data` with pagination (`page`, `per_page`, optional `q_sku`, `q_text`).
3. Unlike Bullet Points Master and Image Master, **data is paginated** (default 75 rows per page, max 100).
4. For each page of SKUs, controller loads:
   - Product Master tier fields: `product_description`, `description_1500`, `description_1000`, `description_800`, `description_600`
   - Optional: `amazon_aplus_content`, `amazon_aplus_images`
   - Per-marketplace saved text from metrics `description_master` column
   - Shopify bullet text from `shopify_metrics` / `shopify_pls_metrics` (for Shopify body composition)
5. Each row receives:
   - `descriptions`: object keyed by marketplace code (saved metrics text only)
   - `shopify_main_bullets` / `shopify_pls_bullets`: bullet text used when pushing Shopify descriptions

## Character Tiers And Marketplace Groups

Product Master stores four description tiers. The UI maps them to marketplace character limits:

| Tier column | Max chars | Marketplaces (PM tier source on quick push) |
|-------------|-----------|---------------------------------------------|
| `description_1500` | 1500 | Amazon, Temu, Temu2, Reverb, Wayfair, Best Buy, Walmart, Shein, AliExpress |
| `description_1000` | 1000 | Shopify Main, Shopify PLS, Doba (falls back to 1500) |
| `description_800` | 800 | eBay1, eBay2, eBay3 (falls back to 1500) |
| `description_600` | 600 | Macy's, Faire, Alibaba, Purchasing Power, Newegg, TopDawg, TikTok, TikTok2, Shopify B5C (falls back to 1500) |

Fallback logic when a tier is empty: shorter tiers are auto-truncated from `description_1500` (see `getPmTextForMp()` in the blade).

`product_description` is kept in sync with `description_1500` on save.

## UI Behavior (DM1)

### Table columns

- SKU (server-side filter via `q_sku`)
- Product Name
- Preview (PM) — first ~100 chars of `description_1500`
- Action buttons
- **DESC 1500** — Amazon, Temu, Temu2, Reverb, Wayfair, Best Buy, Walmart, Shein, AliExpress (from `__ALL_MP__.enabled` tier map)
- **DESC 1000** — Shopify Main, Shopify PLS, Doba
- **DESC 800** — eBay1, eBay2, eBay3
- **DESC 600** — Macy's, Faire, Alibaba, Purchasing Power, Newegg, TopDawg, TikTok, TikTok2, Shopify B5C

Table tile buttons are built dynamically in `product-description.blade.php` from `AllMarketplaceChannelRegistry` (`partials/all-marketplace-master-channels`, `allMpMaster => description`). The edit modal lists all **24** enabled marketplaces as tabs.

### Marketplace tiles

- Green dot = non-empty `description_master` saved in local metrics for that SKU + marketplace.
- Click tile = quick push using Product Master tier text for that marketplace group.
- Checkbox on tile = include in **Push Selected** bulk action.
- **Push ALL (this page)** pushes tier text for every marketplace on the current page.

### Edit flows

1. **View** (eye icon) — read-only plain text of all tier fields.
2. **Edit marketplace descriptions** (pen icon) — per-marketplace textarea blocks with Save & push / Reset / AI generate per tier.
3. **Edit Product Master tiers** (align-left icon) — modal for `description_1500`–`description_600`, AI generate, Amazon A+ fetch, Save to PM, Push to selected marketplaces.

### Shopify special behavior

When pushing Shopify Main or Shopify PLS, listing body combines:

- Bullet Points Master text (from metrics or PM bullets)
- Plus the 1000-character description tier

This is handled in `ShopifyApiService::updateDescription()` / `ShopifyPLSApiService::updateDescription()`.

### Import / Export

- Export writes tier columns to XLSX.
- Import reads tier columns and saves via `/product-description/save` per row.
- Import is Product Master only; it does not push to marketplaces.

## Master Save Flow

- `POST /product-description/save` updates:
  - `description_1500`, `description_1000`, `description_800`, `description_600`
  - `product_description` (synced from `description_1500`)
- Save does **not** push to marketplaces or update metrics `description_master`.

## Marketplace Push Flow

- `POST /product-description/update` with `{ sku, updates: [{ marketplace, description, description_html?, image_urls? }] }`.
- For each marketplace:
  1. Resolves text from request body, or Product Master tier (`loadMasterDescriptionTextForSku`) when empty.
  2. Validates non-empty text and character limit (`MarketplaceCharacterLimits::descriptionLimit()`).
  3. Calls marketplace service via `ProductMasterMarketplaceMaps::descriptionServiceMap()` with retry/backoff (`RetriesMarketplacePush`).
  4. On **API success only**, saves to metrics `description_master` (`saveDescriptionToMarketplaceTable`).
  5. Returns per-marketplace `success`, `message`, `local_saved`, `attempts`, `retried`.
- Push success is based on **external API result**; metrics save runs only after a successful API response.
- `reset-marketplace` clears local metrics `description_master` only; live listings are unchanged until a new push.
- Separate endpoint `saveMarketplaceDescription` can save metrics text without pushing (edit modal Save).

## Marketplace Service Map (DM1)

Canonical map: `ProductMasterMarketplaceMaps::descriptionServiceMap()` — **24** marketplaces (same SET API coverage as Bullet/Title/Image/Video masters).

| Code | Metrics table | Service method |
|------|---------------|----------------|
| `amazon` | `amazon_metrics` | `AmazonSpApiService::updateAplusContent` |
| `temu` / `temu2` | `temu_metrics` / `temu2_metrics` | `TemuApiService` / `Temu2ApiService::updateDescription` |
| `reverb` | `reverb_metrics` | `ReverbApiService::updateDescription` |
| `macy` | `macy_metrics` | `MacysApiService::updateDescription` |
| `ebay` / `ebay2` / `ebay3` | `ebay_metrics` / `ebay_2_metrics` / `ebay_3_metrics` | `EbayApiService` / `Ebay2ApiService` / `EbayThreeApiService::updateDescription` |
| `wayfair` | `wayfair_metrics` | `WayfairApiService::updateProductDescription` |
| `bestbuy` | `bestbuy_metrics` | `BestBuyApiService::updateDescription` |
| `doba` | `doba_metrics` | `DobaApiService::updateProductDescription` |
| `walmart` | `walmart_metrics` | `WalmartService::updateProductDescription` |
| `faire` | `faire_metrics` | `FaireService::updateProductDescription` |
| `shein` | `shein_metrics` | `SheinApiService::updateProductDescription` |
| `aliexpress` | `aliexpress_metric` | `AliExpressApiService::updateProductDescription` |
| `alibaba` | `alibaba_metrics` | `AlibabaApiService::updateProductDescription` |
| `shopify_main` | `shopify_metrics` | `ShopifyApiService::updateDescription` |
| `shopify_pls` | `shopify_pls_metrics` | `ShopifyPLSApiService::updateDescription` |
| `shopify_b5c` | `shopify_pls_metrics` | `ShopifyPLSApiService::updateDescription` |
| `purchasing_power` | `purchasing_power_metrics` | `PurchasingPowerApiService::updateDescription` |
| `newegg` | `newegg_metrics` | `NeweggApiService::updateDescription` |
| `topdawg` | `topdawg_metrics` | `TopDawgApiService::updateDescription` |
| `tiktok` / `tiktok2` | `tiktok_metrics` | `TikTokShopService::updateDescription` |

UI tiles: `AllMarketplaceChannelRegistry` with `description => true` (via `partials/all-marketplace-master-channels`).

## Safe testing (dry-run)

Default test SKU: `SP 12120 4OHM GTR` (`config/marketplace_testing.php` → `description_sku`).

```bash
php artisan marketplace:audit-master description --dry-run --sku="SP 12120 4OHM GTR"
```

Unlike bullets, **description push requires non-empty PM tier text** — empty `description_1500`–`description_600` will fail push with “Description cannot be empty” even when API wiring is ready.

See `docs/MARKETPLACE_API_INTEGRATION.md` and `docs/MARKETPLACE_MASTER_DRY_RUN_RESULTS.md`.

## AI Generate

- `POST /product-description/generate` uses Anthropic Claude.
- Tier parameter: `1500`, `1000`, `800`, or `600` sets min/max length targets.
- Requires `ANTHROPIC_API_KEY` / `services.anthropic.key`.
- Plain text only (no HTML).

## Amazon A+ Fetch

- `POST /product-description/fetch-amazon-aplus` fetches A+ content via `AmazonSpApiService::fetchAplusContent`.
- Saves to `product_master.amazon_aplus_content` and `amazon_aplus_images` when columns exist.
- Used in PM tier modal to populate/regenerate Amazon-oriented content.

## Description Master 2.0 (DM2)

Separate workflow for rich HTML product pages.

### Product Master fields

- `description_v2_bullets`
- `description_v2_description`
- `description_v2_images` (up to 12 URLs)
- `description_v2_features` (title/body pairs)
- `description_v2_specifications` (key/value pairs)
- `description_v2_package`
- `description_v2_brand`

### Fetch sources

- Amazon A+ via `/product-description-2/fetch/amazon`
- eBay listing HTML via `/product-description-2/fetch/ebay` (parsed into DM2 fields)

### Push targets

- Shopify Main (`updateBodyHtml`)
- eBay1 (`updateListingDescriptionRawHtml`)

DM2 does **not** share the DM1 marketplace tile table or metrics `description_master` push pattern.

---

## Current Status Summary (audit 2026-07-03)

### What works today

- DM1 page with pagination and SKU/text filters.
- Product Master tier save (`description_1500`–`description_600`).
- Per-marketplace push for **24 marketplaces** (full SET API parity with other masters).
- Per-row and bulk push (selected checkboxes, push all on page).
- Per-marketplace edit modal with save+push and metrics reset.
- AI description generation by tier.
- Amazon A+ fetch into Product Master.
- Import/export of tier columns.
- Automatic push retries with user-visible retry messaging.
- DM2 structured editor with Amazon/eBay fetch and Shopify+eBay HTML push.

### Dry-run (test SKU `SP 12120 4OHM GTR`)

- **Wiring:** 24/24 ready (credentials + service + metrics table).
- **Content:** all description tier fields empty in `product_master` — dry-run warns `desc 0 chars`; live push will reject until tiers are filled or AI-generated.
- **Metrics rows:** same as bullets — only ebay, ebay3, temu, doba, walmart have listing sync rows; `description_master` column empty until first push.

### Gaps vs Bullet Points / Image Master

| Feature | Bullet Points | Image Master | Description Master (DM1) |
|---------|---------------|--------------|--------------------------|
| Shopify Pull (import from website → PM) | yes | yes | **no** |
| Full table load (no pagination) | yes | yes | **no** (paginated) |
| Row pull icon | yes (bullets) | yes (images) | **no** |
| Push without PM content | yes (uses `bullet1`–`5`) | uses image URLs | **no** (requires tier text) |

### Likely fix priorities (optional UX)

1. **Shopify Description Pull** — mirror Bullet Points / Image Master pull.
2. **DM1 vs DM2** — clarify which page is canonical for Shopify/eBay HTML vs plain tier text.
3. **Test SKU content** — populate `description_1500` etc. before live description push tests.

## Things To Watch While Debugging

- Green marketplace dot means **local metrics `description_master` text exists**, not that the live listing matches.
- Push uses **Product Master tier text** on quick tile click when the edit modal field is empty.
- **Empty PM tiers block push** — unlike bullets, description cannot push from an empty master.
- Blank metrics rows (no `description_master`) do **not** block push when PM tiers have content; metrics fill in after push.
- Shopify push combines **Bullet Points Master** text + description tier in `body_html`.
- DM2 and DM1 are separate; changes in one do not automatically sync to the other.
- Pagination means bulk push only affects **the current page**, not the full catalog.
