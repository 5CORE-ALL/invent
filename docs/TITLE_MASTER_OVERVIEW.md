# Title Master Page Overview

Last reviewed: 2026-07-04

## User-Facing Page

- URL: `/title-master`
- Route name: `title.master`
- View: `resources/views/title-master.blade.php`
- Controller: `App\Http\Controllers\ProductMaster\ProductMasterController`
- Central API dispatch: `App\Services\MarketplaceTitlePushService`

## Pull vs push (UI)

| Action | UI |
|---|---|
| **Marketplace push** | Row marketplace tiles (**24** channels), per-row **Distribute ALL**, toolbar **Push ALL** / **Push Selected** |
| **Pull** | Not implemented for marketplace listings (titles edited in PM or AI flows) |

Artisan audit (dry-run, no API writes):

```bash
php artisan marketplace:audit-master title --sku="SP 12120 4OHM GTR"
```

## Title tiers and marketplace mapping

Canonical map: `AllMarketplaceChannelRegistry::titleMeta()` — registry key → `{ push, type }`.

| PM column | Tier | Marketplaces (push key) |
|-----------|------|-------------------------|
| `title150` / `amazon_title` | 75 | amazon (truncated to 75 chars on push) |
| `title150` / `amazon_title` | 150 | temu, temu2, reverb, wayfair, walmart, bestbuy, shein, aliexpress, alibaba, purchasing_power, newegg, topdawg, tiktok, tiktok2 |
| `title100` | 100 | shopify_main, shopify_pls, shopify_b5c, doba |
| `title80` | 80 | ebay1, ebay2, ebay3 (registry keys: ebay, ebay2, ebay3) |
| `title60` | 60 | macy, faire |

UI tiles are built from `AllMarketplaceChannelRegistry::jsConfig('title')` (`__ALL_MP__` in the blade). Only `enabled` channels (24 with API) render push buttons.

## Push routes

| Endpoint | Method | Behavior |
|----------|--------|----------|
| `POST /api/marketplaces/push-single` | `pushSingleMarketplace` | One SKU → one marketplace; validates push key + tier via registry |
| `POST /api/marketplaces/push-title` | `pushTitleToAllMarketplaces` | One SKU → all **Title 150** channels via `MarketplaceTitlePushService` |
| `POST /api/marketplaces/push-bulk` | `pushBulkToAllMarketplaces` | Many SKUs; each calls `pushTitleToAllMarketplaces` |
| `POST /api/ai-title-push` (AI Title Manager) | `pushTitleToMarketplace` | Saves metrics row + live API when `config/marketplaces.php` `api_title_push` includes channel |

Per-tile push sends `{ sku, marketplace, title_type, title }` where `marketplace` is the **push key** (`ebay1`, `shopify_main`, `bestbuy`, etc.).

## Push flow (single marketplace)

1. Validate `marketplace` against `AllMarketplaceChannelRegistry::titlePushKeys()`.
2. Normalize aliases (`ebay` → `ebay1`, `shopify` → `shopify_main`, `macys` → `macy`).
3. Ensure `title_type` matches registry tier for that marketplace.
4. Resolve title from request body or PM columns when empty.
5. `MarketplaceCharacterLimits::truncateTitle()` per channel.
6. `MarketplaceApiConfigService` credential guard.
7. `RetriesMarketplacePush` → `MarketplaceTitlePushService::push()`.
8. Log to `marketplace_push_logs` (`MarketplacePushLog::MARKETPLACES` — all 24 push keys).

## Push flow (Distribute ALL — Title 150)

Row **Distribute ALL** and toolbar bulk push call `POST /api/marketplaces/push-title` with the row’s Title 150 / Amazon title text. The controller pushes to every channel in `titlePushKeysForType('150')` (14 marketplaces). Amazon is tier 75 and is pushed from its own Market Places tile.

## Configuration

- `config/marketplaces.php` → `api_title_push` — channels that receive live API from AI Title Manager
- `config/marketplace_testing.php` → default audit SKU `SP 12120 4OHM GTR`
- Credentials: `config/services.php` + `.env`

## Safe testing

Default test SKU: `SP 12120 4OHM GTR`.

```bash
php artisan marketplace:audit-master title --sku="SP 12120 4OHM GTR" --save
php artisan marketplace:audit-master all --save
```

Dry-run checks credentials, service wiring, metrics hints, and PM title text per tier. It does **not** call marketplace write APIs.

Unlike description, **empty metrics title columns do not block push** when PM title fields have content.

## Live push risks (test SKU notes)

| Channel | Risk |
|---------|------|
| ebay2 | No `item_id` in metrics — eBay API lookup on push |
| aliexpress | No `product_id` in `aliexpress_metric` — may fail until synced |

## Related docs

- `docs/MARKETPLACE_API_INTEGRATION.md` — cross-master architecture
- `docs/MARKETPLACE_MASTER_DRY_RUN_RESULTS.md` — latest audit output
