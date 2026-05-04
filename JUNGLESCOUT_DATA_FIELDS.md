# JungleScout Product Data Fields

This document describes all fields available in the `junglescout_product_data.data` JSON column.

## 🆕 Competitor Sales Data (Added 2026-05-03)

These fields provide **estimated monthly sales data for any ASIN** - perfect for competitor analysis:

| Field | Type | Description | Use Case |
|-------|------|-------------|----------|
| `approximate_30_day_revenue` | `float` | Estimated 30-day revenue in USD | Track competitor revenue trends |
| `approximate_30_day_units_sold` | `integer` | Estimated 30-day units sold | Understand competitor sales volume |
| `number_of_sellers` | `integer` | Total sellers offering this ASIN | Assess competition level |
| `buy_box_owner` | `string` | Current buy box winner name | See who's dominating sales |
| `buy_box_owner_seller_id` | `string` | Buy box winner seller ID | Track specific competitors |
| `seller_type` | `string` | Seller type (FBA, FBM, Amazon) | Understand fulfillment strategy |

## Product Structure Info

| Field | Type | Description |
|-------|------|-------------|
| `is_variant` | `boolean` | Whether this is a variant product |
| `is_parent` | `boolean` | Whether this is a parent ASIN |
| `variants` | `integer` | Number of variants |
| `date_first_available` | `date` | When product was first listed on Amazon |

## Basic Product Information

| Field | Type | Description |
|-------|------|-------------|
| `id` | `string` | JungleScout ID (e.g., "us/B088WVF1V1") |
| `price` | `float` | Current product price |
| `reviews` | `integer` | Total number of reviews |
| `rating` | `float` | Average rating (0-5) |
| `category` | `string` | Amazon category |
| `brand` | `string` | Product brand name |
| `product_rank` | `integer` | Best Seller Rank (BSR) |
| `listing_quality_score` | `float` | Listing quality score (0-10) |

## Product Details

| Field | Type | Description |
|-------|------|-------------|
| `image_url` | `string` | Product image URL |
| `parent_asin` | `string` | Parent ASIN if variant |
| `weight` | `float` | Product weight |
| `dimensions` | `string` | Product dimensions (LxWxH) |

## Usage Examples

### Get Competitor Monthly Sales
```php
$competitorData = JungleScoutProductData::where('asin', 'B088WVF1V1')->first();
$monthlySales = $competitorData->data['approximate_30_day_units_sold'] ?? 0;
$monthlyRevenue = $competitorData->data['approximate_30_day_revenue'] ?? 0;

echo "Competitor sells ~{$monthlySales} units/month (~\${$monthlyRevenue} revenue)";
```

### Check Buy Box Winner
```php
$product = JungleScoutProductData::where('asin', $asin)->first();
$buyBoxOwner = $product->data['buy_box_owner'] ?? 'Unknown';
$sellerCount = $product->data['number_of_sellers'] ?? 0;

echo "{$buyBoxOwner} owns the buy box (competing against {$sellerCount} sellers)";
```

### Calculate Market Share
```php
$competitors = JungleScoutProductData::whereIn('asin', $competitorAsins)->get();
$totalMarketUnits = $competitors->sum(fn($c) => $c->data['approximate_30_day_units_sold'] ?? 0);
$myUnits = $myProduct->data['approximate_30_day_units_sold'] ?? 0;
$marketShare = ($myUnits / $totalMarketUnits) * 100;

echo "You have {$marketShare}% market share";
```

## Data Updates

Run this command to fetch/update JungleScout data:
```bash
php artisan app:process-jungle-scout-sheet-data
```

## API Source

Data comes from JungleScout's Product Database API:
- Endpoint: `https://developer.junglescout.com/api/product_database_query`
- Documentation: https://developers.junglescout.com/
- Requires: JungleScout API key in `.env` as `JUNGLESCOUT_API_KEY_WITH_TITLE`
