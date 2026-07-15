# Marketplace Manager (MM) Reference

Living doc for channels under `/marketplace-manager` and `/marketplace/{slug}`. Update when adding marketplaces so agents don’t rediscover the stack.

## Channels

| Slug | Label | Template | Live listings + state UI | Inventory API |
|------|-------|----------|--------------------------|---------------|
| `aliexpress` | AliExpress | **canonical full pattern** | Yes (`AliexpressLiveListingsService`) | `AliExpressApiService` |
| `reverb` | Reverb | Same as AE + sold/draft state rules | Yes (`ReverbLiveListingsService`) | `ReverbListingService` / `ReverbApiService` |
| `alibaba` | Alibaba | Sync stack without live/state tabs | No | `AlibabaApiService` |
| `newegg` | Newegg | **Clone of AliExpress** (2026-07-15) | Yes — states `active` / `inactive` from `newegg_pricing` | `NeweggApiService::updateItemInventory*` (Seller Part #) |

### Newegg first-run checklist

1. `.env`: `NEWEGG_SELLER_ID`, `NEWEGG_API_KEY`, `NEWEGG_SECRET_KEY` (+ Cloudflare IP whitelist on prod).
2. `php artisan migrate` (creates `newegg_metric`, `newegg_order_metrics`, `newegg_pricing_prices`).
3. Ensure `newegg_pricing` is populated (`newegg:items` / pricing fetch commands already exist).
4. Marketplace Manager → Newegg → Connect → Test connection.
5. Listings → Sync link map (seeds `newegg_metric` from `newegg_pricing`).
6. Settings → turn on `inventory_sync` (+ orders if needed).
7. Deploy PHP + restart `marketplace-manager` worker.

## Hard inventory rules (`MarketplaceLiveInventoryRules`)

1. Never update **unlinked** SKUs (`product_id` empty or `product_id === sku`).
2. Push qty from **live Shopify API** only (not DB `shopify_skus`).
3. Shopify ≤ 0 ⇒ marketplace **0** (never invent via `min_quantity`).
4. Reverb extras: draft may update qty but **never publish**; unpublished/suspended/dead blocked; sold-family may `publish` when restocking (OPEN BOX / Good / ordered often **cannot** republish — Reverb locks them; re-list manually).

## Newegg specifics

- Auth: `NEWEGG_API_KEY`, `NEWEGG_SECRET_KEY`, `NEWEGG_SELLER_ID` (`config/services.php` → `newegg`). Cloudflare IP whitelist required.
- Identity: **Seller Part #** = SKU; **Newegg Item #** = `product_id` when known.
- Local catalog/stock cache: existing `newegg_pricing` (`NeweggPricing`) — `seller_part_number`, `newegg_item_number`, `available_quantity`, `active`.
- MM link map: `newegg_metric` (seeded from `newegg_pricing` by link-map sync).
- MM orders: `newegg_order_metrics` (Shopify import tracking; separate from legacy `newegg_orders` / `newegg_order_items`).
- Inventory update: `PUT /marketplace/contentmgmt/item/inventoryandprice` with `Type=1`, `Value=<SPN>`, `Inventory=<qty>` (inventory-only payload).
- Listed / linked: row in `newegg_metric` with real `product_id` ≠ sku, or pricing row with `newegg_item_number`.

## File map (per channel)

| Concern | Path pattern |
|---------|----------------|
| Registry | `app/Services/MarketplaceManager/MarketplaceManagerRegistry.php` |
| SyncController | `app/Http/Controllers/MarketPlace/{Name}SyncController.php` |
| Inventory / orders / link map / live / detail / push | `app/Services/MarketplaceManager/{Name}*Service.php` |
| Settings model | `app/Models/MarketplaceSyncSettings.php` (`getFor('slug')`) |
| Metric / order metric | `app/Models/{Name}Metric.php`, `{Name}OrderMetric.php` |
| Views | `resources/views/marketplace/{slug}/` |
| Routes | `routes/web.php` — manager group + `marketplace/{marketplace}` whitelist |
| Router | `app/Http/Controllers/MarketplaceController.php` |
| Jobs | `RunMarketplaceInventorySyncJob`, `PushLinkedSkuInventoryFromShopify`, `Warm*LiveListingsCache` |
| Schedule | `app/Console/Kernel.php` — inventory + orders every 15m, link-map hourly |
| Commands | `{slug}:sync-inventory-from-shopify`, `:sync-orders`, `:sync-link-map` |

## UI entry

- Hub: Marketplace Manager sidebar → channel card → Overview / Connect / Listings / Orders / Settings.
- Listings (AE/Reverb/Newegg): Shopify-first **Linked** tab, **State** dropdown, live Shopify + live channel qty.

## Deploy notes

- Upload PHP + blades; `chown inventory_5c_usr`; restart `marketplace-manager` queue worker (opcache).
- Windows CRLF breaks remote bash — prefer SFTP + LF for deploy scripts.
- Production: `31.59.184.74` → `/var/www/inventory_5c_usr/data/www/inventory.5coremanagement.com`.

## Adding another marketplace

1. Clone **AliExpress** MM files (not Alibaba) unless the channel has no listing states.
2. Register slug in `MarketplaceManagerRegistry`, routes, `MarketplaceController`, settings defaults, jobs, Kernel schedule.
3. Document the row in the Channels table above.
4. Prefer a thin `*ApiService` already in `app/Services/` behind MM services.
