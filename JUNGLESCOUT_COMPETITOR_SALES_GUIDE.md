# JungleScout Competitor Sales Data - Implementation Summary

## ✅ What Was Added

### 1. Enhanced Data Collection (`ProcessJungleScoutSheetData.php`)

**NEW COMPETITOR SALES FIELDS:**
- `approximate_30_day_revenue` - Monthly revenue estimate
- `approximate_30_day_units_sold` - Monthly units sold
- `number_of_sellers` - Total competing sellers
- `buy_box_owner` - Who owns the buy box
- `buy_box_owner_seller_id` - Buy box winner ID
- `seller_type` - FBA, FBM, or Amazon
- `is_variant`, `is_parent`, `variants` - Product structure
- `date_first_available` - Product launch date

### 2. New API Endpoints (`CompetitorSalesController.php`)

**GET** `/junglescout/competitor-sales/asin?asin=B088WVF1V1`
- Get complete sales data for any ASIN

**POST** `/junglescout/competitor-sales/compare`
- Compare multiple competitors side-by-side
- Calculates market share automatically
- Body: `{ "asins": ["B088...", "B075..."] }`

**GET** `/junglescout/competitor-sales/top-sellers?category=Home&limit=20`
- Find top-selling products by revenue
- Filter by category (optional)

**GET** `/junglescout/competitor-sales/buy-box-stats`
- See which sellers dominate buy boxes
- Aggregate revenue by seller

### 3. Documentation Files

- `JUNGLESCOUT_DATA_FIELDS.md` - Complete field reference with examples
- Migration file documenting the enhancement
- Inline code comments

## 🚀 How to Use

### Step 1: Fetch Data from JungleScout

Run the command to fetch/update competitor data:

```bash
php artisan app:process-jungle-scout-sheet-data
```

This fetches data for all ASINs in your `amazon_datsheets` table and stores:
- Your products' sales data
- **Any competitor ASINs you want to track** (just add them to the table)

### Step 2: Query Competitor Sales

#### Option A: Direct Database Query

```php
use App\Models\JungleScoutProductData;

// Get competitor's monthly sales
$competitor = JungleScoutProductData::where('asin', 'B088WVF1V1')->first();
$monthlySales = $competitor->data['approximate_30_day_units_sold'] ?? 0;
$monthlyRevenue = $competitor->data['approximate_30_day_revenue'] ?? 0;

echo "Competitor sells ~{$monthlySales} units/month";
echo "That's ~\${$monthlyRevenue} in monthly revenue";
```

#### Option B: Use API Endpoints

**Get Single Competitor:**
```javascript
// Frontend JavaScript
fetch('/junglescout/competitor-sales/asin?asin=B088WVF1V1')
  .then(r => r.json())
  .then(data => {
    console.log('Monthly Revenue:', data.sales_data.monthly_revenue);
    console.log('Monthly Units:', data.sales_data.monthly_units_sold);
    console.log('Buy Box Owner:', data.competition.buy_box_owner);
  });
```

**Compare Multiple Competitors:**
```javascript
fetch('/junglescout/competitor-sales/compare', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    asins: ['B088WVF1V1', 'B075YT5K59', 'B09ABCDEFG']
  })
})
.then(r => r.json())
.then(data => {
  console.log('Market Totals:', data.market_totals);
  console.log('Your Market Share:', data.competitors[0].market_share_revenue + '%');
  console.log('Top Performer:', data.top_performer.by_revenue);
});
```

**Response Example:**
```json
{
  "competitors": [
    {
      "asin": "B088WVF1V1",
      "brand": "Nike",
      "monthly_revenue": 45000,
      "monthly_units": 1500,
      "price": 30,
      "sellers_count": 8,
      "buy_box_owner": "Nike Official",
      "market_share_revenue": 35.5,
      "market_share_units": 33.2
    }
  ],
  "market_totals": {
    "total_monthly_revenue": 126750,
    "total_monthly_units": 4520
  }
}
```

### Step 3: Add Competitor ASINs to Track

To track competitors who aren't already in your database:

```php
// Add competitor ASINs to amazon_datsheets table
DB::table('amazon_datsheets')->insert([
    'asin' => 'B088WVF1V1',  // Competitor ASIN
    'sku' => 'COMP-NIKE-001',  // Your internal reference
    'marketplace' => 'Amazon.com',
    'created_at' => now(),
]);

// Then run the fetch command
Artisan::call('app:process-jungle-scout-sheet-data');
```

## 📊 Use Cases

### 1. Market Share Analysis
Track your share vs competitors in your category:
```php
$myAsin = 'B075YT5K59';
$competitorAsins = ['B088WVF1V1', 'B09ABCDEFG', 'B07HIJKLMN'];

// Use compare endpoint to get market share automatically
```

### 2. Pricing Strategy
See if competitors are selling at higher volumes with lower prices:
```php
$competitor = JungleScoutProductData::where('asin', $competitorAsin)->first();
$theirPrice = $competitor->data['price'];
$theirUnits = $competitor->data['approximate_30_day_units_sold'];
$myUnits = JungleScoutProductData::where('asin', $myAsin)->first()
    ->data['approximate_30_day_units_sold'];

if ($theirUnits > $myUnits && $theirPrice < $myPrice) {
    echo "Consider lowering price to compete";
}
```

### 3. Buy Box Analysis
See who dominates the buy box in your niche:
```php
// Use /buy-box-stats endpoint
// Shows which sellers win most buy boxes and their total revenue
```

### 4. New Product Validation
Before launching, check if similar products are selling:
```php
$similarProduct = JungleScoutProductData::where('asin', $similarAsin)->first();
$monthlySales = $similarProduct->data['approximate_30_day_units_sold'];

if ($monthlySales > 500) {
    echo "Good market demand - proceed with launch";
}
```

### 5. Identify Market Gaps
Find categories with low competition but good sales:
```php
$products = JungleScoutProductData::whereNotNull('data')
    ->get()
    ->filter(function($p) {
        $revenue = $p->data['approximate_30_day_revenue'] ?? 0;
        $sellers = $p->data['number_of_sellers'] ?? 999;
        return $revenue > 10000 && $sellers < 5;  // High revenue, low competition
    });
```

## 🔧 Technical Details

### Data Storage
- All data stored in `junglescout_product_data.data` column (JSON)
- No schema changes needed - flexible structure
- Automatically updated when command runs

### Data Freshness
- Run command daily/weekly to keep data current
- JungleScout provides 30-day rolling estimates
- Can be automated via Laravel scheduler

### API Rate Limits
- JungleScout API: 300 requests/minute
- Command batches in groups of 100 ASINs
- Handles errors gracefully

## 📝 Next Steps

1. **Run the fetch command** to populate data:
   ```bash
   php artisan app:process-jungle-scout-sheet-data
   ```

2. **Add competitor ASINs** you want to track to `amazon_datsheets`

3. **Test the API endpoints** using the examples above

4. **Integrate into dashboards** - show competitor metrics alongside your own

5. **Automate** - schedule the command to run daily:
   ```php
   // In app/Console/Kernel.php
   $schedule->command('app:process-jungle-scout-sheet-data')
       ->dailyAt('02:00');
   ```

## 🎯 Key Benefits

✅ **Track competitor sales in real-time** (30-day estimates)  
✅ **Calculate your market share automatically**  
✅ **Identify pricing opportunities**  
✅ **See who owns buy boxes in your niche**  
✅ **Validate new product ideas with sales data**  
✅ **Make data-driven business decisions**

## 📖 More Information

- Field reference: `JUNGLESCOUT_DATA_FIELDS.md`
- Controller code: `app/Http/Controllers/JungleScout/CompetitorSalesController.php`
- Data fetching: `app/Console/Commands/ProcessJungleScoutSheetData.php`
- API routes: `routes/web.php` (search for "junglescout/competitor-sales")
