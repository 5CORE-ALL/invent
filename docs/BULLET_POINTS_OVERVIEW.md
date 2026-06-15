# Bullet Points Page Overview

Last reviewed: 2026-06-11

## User-Facing Page

- URL: `/bullet-points`
- Route name: `bullet.points`
- Active view rendered by the route: `resources/views/bullet-point-master.blade.php`
- Controller: `App\Http\Controllers\ProductMaster\BulletPointMasterController`
- The older view `resources/views/bullet-points.blade.php` still exists, but `/bullet-points` currently renders `bullet-point-master`, not this older blade.

## Main Files

- `routes/web.php`
  - Defines both `/bullet-points` compatibility routes and `/bullet-point-master` routes.
- `app/Http/Controllers/ProductMaster/BulletPointMasterController.php`
  - Loads page data, generates AI bullets, saves marketplace table values, and pushes bullet points to marketplace services.
- `resources/views/bullet-point-master.blade.php`
  - Current UI for `/bullet-points`; contains table rendering, modals, search, export/import, AI generation, master bullet save, and push actions.
- `resources/views/bullet-points.blade.php`
  - Older UI using `/bullet-points/save`; likely not active for the current route.
- `app/Http/Controllers/ProductMaster/ProductMasterController.php`
  - Supplies base product data through `getViewProductData()` and still has `saveBulletData()` for the older save endpoint.
- `app/Models/ProductMaster.php`
  - Stores master product fields including `bullet1` through `bullet5`.
- `app/Models/BulletPointAiPromptRule.php`
  - Stores editable AI prompt rules used by bullet generation.
- `database/migrations/2026_06_13_220000_create_bullet_point_ai_prompt_rules_table.php`
  - Creates `bullet_point_ai_prompt_rules` for saved AI prompt rules.
- `database/migrations/2025_12_07_014500_add_bullet_columns_to_product_master_table.php`
  - Adds `bullet1` through `bullet5` to `product_master`.
- `database/migrations/2026_03_15_100000_add_bullet_points_to_metrics_tables.php`
  - Adds `bullet_points` to earlier marketplace metrics tables.
- `database/migrations/2026_03_23_220000_add_bullet_points_to_marketplace_metrics_tables.php`
  - Adds `bullet_points` to the broader marketplace metrics table list used by the current feature.
- `app/Services/Support/ShopifyBulletPointsFormatter.php`
  - Formats pushed Shopify bullet text into `body_html`.
- `app/Services/Concerns/ResolvesBulletPointIdentifier.php`
  - Helper trait used by some services to find metrics rows by SKU or alternate IDs.

## Routes And Endpoints

Current page routes:

- `GET /bullet-points` -> `BulletPointMasterController@index`
- `GET /bullet-points-data` -> `BulletPointMasterController@getData`
- `POST /bullet-points/update` -> `BulletPointMasterController@update`
- `POST /bullet-points/generate` -> `BulletPointMasterController@generate`
- `POST /bullet-points/save` -> `ProductMasterController@saveBulletData`

Bullet point master routes used by the active blade:

- `GET /bullet-point-master` -> `BulletPointMasterController@index`
- `GET /bullet-point-master-data` -> `BulletPointMasterController@getData`
- `GET /bullet-point-master-combined-data` -> `BulletPointMasterController@getCombinedData`
- `POST /bullet-point-master/update` -> `BulletPointMasterController@update`
- `POST /bullet-point-master/update-bulk` -> `BulletPointMasterController@updateBulk`
- `POST /bullet-point-master/generate` -> `BulletPointMasterController@generateBulletPoints`
- `GET /bullet-point-master/ai-prompt-rules` -> `BulletPointMasterController@aiPromptRules`
- `POST /bullet-point-master/ai-prompt-rules` -> `BulletPointMasterController@saveAiPromptRules`

Important: although the browser URL can be `/bullet-points`, the active blade fetches `/bullet-point-master-combined-data`, `/bullet-point-master/update`, and `/bullet-point-master/generate`.

## Data Loading Flow

1. `/bullet-points` calls `BulletPointMasterController@index`.
2. The controller returns `view('bullet-point-master')`.
3. On page load, `bullet-point-master.blade.php` calls `fetch('/bullet-point-master-combined-data')`.
4. `getCombinedData()` delegates to `getData()`.
5. `getData()` calls `ProductMasterController@getViewProductData()` to load all `product_master` rows.
6. Product rows include `SKU`, `Parent`, `bullet1` through `bullet5`, images, descriptions, and flattened `Values` JSON.
7. `getData()` also loads saved marketplace bullet text from these metrics tables:
   - `ebay_metrics`
   - `ebay_2_metrics`
   - `ebay_3_metrics`
   - `macy_metrics`
   - `amazon_metrics`
   - `temu_metrics`
   - `reverb_metrics`
   - `wayfair_metrics`
   - `bestbuy_metrics`
   - `shopify_metrics`
   - `shopify_pls_metrics`
8. Each product row receives:
   - `default_bullets`: `bullet1` through `bullet5` combined from `product_master`.
   - `bullet_points`: object keyed by marketplace, containing saved metrics-table text only.

## UI Behavior

- The table filters out SKUs containing `PARENT`.
- Columns shown by the current blade:
  - SKU
  - Product Name
  - Current Bullets preview
  - Action buttons
  - Marketplace push/status tiles
  - Shopify push/status tiles
- Green dot means that marketplace has non-empty saved text in its metrics table.
- View modal shows all saved marketplace bullet text for one SKU and supports copy-all.
- Edit modal shows five bullet textareas initialized from `product_master.bullet1` through `bullet5`; `default_bullets` is only a fallback.
- Edit modal save posts `bullet1` through `bullet5` to `/bullet-points/save` and updates `product_master`.
- Saved, pulled, and pushed bullet lines are normalized to remove decorative leading emojis or bullet symbols such as `🔊`, `✅`, `•`, `*`, or `-`, while keeping the actual feature title and description text.
- AI Generate calls `/bullet-point-master/generate`, expects exactly five plain-text bullet lines, and only fills modal fields; it does not save until the user clicks Save. The edit modal includes an optional prompt details/keywords textarea that is sent as `prompt_details` so users can provide product context when names are short or unclear. The backend prompt is marketplace-focused for Amazon/Walmart/eBay/Shopify and uses the saved AI Prompt Rules from `bullet_point_ai_prompt_rules` instead of relying only on hardcoded rules. If no saved rules exist, it falls back to the default rule block. The generation still uses a repair pass if the model misses the required length or format.
- The Edit Bullet Points modal has an `AI Prompt Rules` button beside the Title area. It opens an editable modal that loads rules from `/bullet-point-master/ai-prompt-rules` and saves changes to `/bullet-point-master/ai-prompt-rules`. Saved rules persist in the database and are applied to future AI Generate requests.
- Clicking an individual marketplace tile pushes `product_master.bullet1` through `bullet5` to that marketplace through `/bullet-point-master/update`, falling back to `default_bullets` only if the individual columns are empty.
- While a marketplace tile push is still running, the tile shows `Updating...`.
- Export writes an XLSX master-bullets template with `Parent`, `SKU`, `Product Name`, and `Bullet 1` through `Bullet 5`.
- Import reads `Bullet 1` through `Bullet 5` columns and saves each row to `product_master` via `/bullet-points/save`.
- Import also accepts common bulk header variants such as `bullet1`, `Bullet1`, `bullet_1`, `bullet-1`, `Seller SKU`, and can fall back to splitting a `Preview`/`Current Bullets` column if no bullet columns are present.
- Bulk Import updates the Import button with row progress and reports saved/failed counts, including a small failed-SKU sample.
- `Push Selected` and `Push ALL to All Marketplaces` currently show a toast only; bulk behavior is not implemented in the blade.

## Master Save Flow

- The active Edit modal no longer asks for marketplace selection.
- Save posts `{ sku, bullet1, bullet2, bullet3, bullet4, bullet5 }` to `/bullet-points/save`.
- `ProductMasterController@saveBulletData()` updates `product_master.bullet1` through `product_master.bullet5`.
- The page reloads data after save so the Current Bullets preview reflects the saved master bullets.
- Import uses the same save endpoint for each imported SKU.
- Import is master-bullets only: it updates only `product_master.bullet1` through `bullet5`; it does not push to marketplaces, update Shopify, or change product descriptions/titles/images.

## Marketplace Push Flow

- `BulletPointMasterController@update` validates `{ sku, updates: [{ marketplace, bullet_points }] }`.
- Allowed marketplace keys:
  - `ebay`
  - `ebay2`
  - `ebay3`
  - `macy`
  - `amazon`
  - `temu`
  - `reverb`
  - `wayfair`
  - `bestbuy`
  - `shopify_main`
  - `shopify_pls`
- For each update:
  - Loads the latest saved `product_master.bullet1` through `bullet5` server-side and uses those newline-separated bullets for marketplace pushes when the SKU exists.
  - Saves that same pushed text to the mapped metrics table using `saveToMarketplaceTable()`.
  - Calls the related marketplace service `updateBulletPoints()` through retry helper `RetriesMarketplacePush`.
  - Reports per-marketplace `success`, `message`, `local_saved`, `attempts`, and `retried`.
- Marketplace push success is based on the external marketplace service result, not the local metrics-table save. Local table save can still succeed when the marketplace push fails.
- Shopify Main (`SM`) resolves the product/variant, fetches the current Shopify `product.body_html`, and replaces only the existing `About Item` / `Key Features` bullet block. Shopify bullet formatting no longer adds checkmark emojis to pushed bullet list items.
- Shopify PLS (`PLS`) resolves by SKU, variant ID, product ID, or GraphQL, fetches the current PLS `product.body_html`, and replaces only the existing bullet block.
- Shopify bullet pushes preserve the existing Shopify product description HTML and do not rebuild it from Product Master description data.
- Shopify pushes still write bullet text into `body_html`, not a separate Shopify bullet metafield.
- New Shopify bullet blocks are wrapped with `bullet-points-master-section` / `data-bullet-points-master="1"` markers, so future pushes can replace by marker before falling back to legacy pattern matching.
- Shopify duplicate-bullet cleanup handles rich `About Item` heading blocks and older paragraph/list-style blocks, including standalone nested `About Item:` paragraphs, Amazon A+ pasted `aplus-3p-center-content` bullet blocks, `Highlighted Features`/`Key Benefits`/`Product Highlights`/`Main Features`/`Bullet Points` heading sections, single or grouped span-wrapped bold bracket bullets (`<span><strong>【...】</strong>...</span>`), mixed paragraph/heading bracket bullets, top-of-description bold-label bullets (`<strong>Label -</strong> text` or `<strong>Label</strong> - text`) in paragraphs or headings, Google-docs style `ol`/`ul` lists containing bracket bullets, top-of-description symbol/numbered bullets, top-of-description simple lists, and empty list shells left behind by cleanup.
- Shopify manual download links (`Download Product Manual`) are preserved above the generated bullet block when they exist near the top of the product HTML.
- Shopify-to-local pull is available from the Bullet Points page via the `Shopify Pull` toolbar button, which opens a modal for filtered-SKU/background monitoring. The modal contains Run in BG, Pause, Resume, Stop, progress, and recent log messages. Individual SKU pulls use the row action download icon directly: it opens a Bootstrap confirmation modal, changes the row button to Syncing after confirmation, starts the same background worker, and shows a toast without opening the progress modal. Before starting, the UI confirms that Shopify bullet points will update the existing Product Master bullet fields. Refresh does not stop the pull. Progress is stored in `storage/app/shopify-bullet-pull/job.json`, the page polls status after refresh, and the worker saves only to `product_master.bullet1` through `bullet5`, waits 6s between SKUs, uses backend 429 retry/backoff, reuses catalog product IDs when possible, and writes persistent details to `storage/logs/shopify-bullet-pull.log`. It does not write to Shopify or push marketplaces. Pull mapping is store-aware: if `shopify_catalog_variants` has a SKU row, its `store` decides whether to use Main or PLS Shopify before falling back to legacy `shopify_skus`. For each SKU, pull checks the public storefront product JSON first (`www.5core.com/products/{handle}.js`) so it imports the user-served product description. If storefront content is unavailable or has no bullets, it falls back to Shopify Admin API `body_html`; if live sources return only 0-1 bullets and cached catalog HTML has a richer bullet block, it uses cached catalog as the last fallback. Extraction fallbacks include checkmark/emoji `Key Features` lines, plain `About Item` line bullets, colon-label bullets such as `Feature: description`, spreadsheet-pasted bracket bullets separated by `<br>` inside one paragraph, inline `About Item:【...】【...】` bracket bullets collapsed into one paragraph, top bracket paragraphs/headings separated by blank/non-breaking-space spacer paragraphs, multiple bold-label bullets inside one paragraph, plain colon-label paragraphs after bold-label bullets, mixed `About Item` bracket bullets followed by bold-label paragraphs, and leading `About Item` plus bold-label paragraphs/headings with non-breaking spaces/extra attributes and separators either inside or outside `<strong>`. `About Item` bracket paragraphs are prioritized before broad bold-label extraction so specification tables are not mistaken for bullet points. The spaced bracket fallback requires a real bracket marker so bold-label paragraphs that only contain non-breaking spaces are not mistaken for bracket bullets. Bold-label cleanup stops before adjacent description headings even when tags/images remove spacing. Bracket bullet cleanup stops before later description/features/spec/package/about-brand sections so long-form content is not appended to the last bullet, while normal sentence text like `This product features...` is not treated as a section heading.
- The Bullet Points table toolbar includes a bullet-status filter for Product Master bullets present/missing and exact bullet counts from 1 to 5. This filter also controls which SKUs are included when running the filtered Shopify pull.
- eBay1 (`E1`) and eBay2 (`E2`) fetch the current seller Description HTML and replace only the first visible bullet block above the product description, preserving the rest of the description. They handle normal top list blocks (`ol`/`ul`), top Google Sheets-style bold-label paragraphs separated by `<br>` tags, top bracket-label paragraphs such as `【Feature】 benefit`, and Shopify/eBay `Highlighted Features` sections made from multiple rich-text `<p>` bullet paragraphs before the product description. When a rich top bullet block and duplicate top list both exist before the product description, the duplicate top list is removed. Lists after a product-description heading are treated as lower/spec/package content and are not replaced.
- E1/E2 preserve exact user-entered bullet text inside seller-description list items.
- eBay3 (`E3`) fetches the current item and updates Item Specifics `Bullet Point 1` through `Bullet Point 5`, preserving existing non-bullet specifics and leaving seller Description HTML unchanged. E3 bullet lines are trimmed to the configured eBay item-specific max length (`EBAY_ITEM_SPECIFIC_BULLET_MAX_LENGTH`, default 65), and E3 adds a configurable `Type` fallback (`EBAY_TYPE_FALLBACK_VALUE`, default `Audio Connector`) when the listing has no existing `Type`.
- Temu (`T`) updates only the configured bullet/summary field (`goodsSummary` by default) and local `temu_metrics.goods_summary`; it does not send or save `goodsDesc` during bullet pushes.
- Reverb (`R`) updates the visible `Highlighted Features` block in the listing description because Reverb's public page/API did not expose the separate `features` field for the tested listing. It preserves the rest of the description, logs request/response details, and preserves exact user-entered feature text.
- Wayfair (`W`) updates Product Catalog `keyFeatures` through `WayfairApiService::updateBulletPoints()`, sending the current bullet lines as Wayfair key features.
- Best Buy (`B`) updates only Mirakl `bulletPoints` for channel `bestbuyusa` through `BestBuyApiService::updateBulletPoints()`; it no longer writes bullet pushes to `longDescription` or `productDescription`.

## Marketplace Service Map

- `ebay` -> `App\Services\EbayApiService`
- `ebay2` -> `App\Services\Ebay2ApiService`
- `ebay3` -> `App\Services\EbayThreeApiService`
- `macy` -> `App\Services\MacysApiService`
- `amazon` -> `App\Services\AmazonSpApiService`
- `temu` -> `App\Services\TemuApiService`
- `reverb` -> `App\Services\ReverbApiService`
- `wayfair` -> `App\Services\WayfairApiService`
- `bestbuy` -> `App\Services\BestBuyApiService`
- `shopify_main` -> `App\Services\ShopifyApiService`
- `shopify_pls` -> `App\Services\ShopifyPLSApiService`

## Things To Watch While Debugging

- URL mismatch is intentional right now: `/bullet-points` renders a blade that calls `/bullet-point-master-*` endpoints.
- `resources/views/bullet-points.blade.php` may be stale; check before editing it.
- Master bullet saving and marketplace pushing are separate in the active blade.
- `ProductMasterController@saveBulletData()` accepts full string bullet text and saves it into `product_master`.
- Current `update()` success semantics can hide external push failures when the metrics table save succeeds.
- Empty bullet text can still be saved locally, but many services reject empty bullet text.
- `temu_metrics` also updates `goods_summary` when that column exists.
- Shopify bullet pushes should not overwrite the product description section; they replace only the top bullet/About Item block and preserve the remaining current Shopify `body_html`.
- eBay1/eBay2 bullet pushes edit the listing Description field, but only by replacing the first visible bullet block and preserving the rest of the existing seller description HTML. E3 bullet pushes update item-specific bullet fields instead.
- Storefront/theme caching may delay visible Shopify changes.
- AI Generate uses `services.anthropic.model` with a Sonnet 4 default and strips common bullet/check symbols from generated lines before filling the edit modal fields. `Regenerate Existing Bullet Points with AI` sends the current five bullet fields as source content and asks AI to rewrite them using the saved prompt rules. Each bullet field also has a `Change` action that opens a prompt modal and rewrites only that one bullet via `/bullet-point-master/rewrite-bullet`, sending the product title, optional AI prompt details, target bullet, and all five current bullets as context so the rewrite stays related and does not randomize the remaining points. After an AI rewrite, the affected fields show `Revert` so the user can switch back to the previous text before saving.
