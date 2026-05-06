# UGC Images Master - Setup Complete

## Summary

A new "UGC Images Masters" page has been created under the Product Masters group. UGC (User Generated Content) Images Master displays the same essential columns as Hero and Trust Images Master pages for managing UGC image data.

## Files Created/Modified

### 1. **View File**
- **File**: `/resources/views/ugc-images-master.blade.php`
- **Description**: Main blade view file with Tabulator table
- **Features**:
  - Tabulator-based table with 9 columns
  - Vertical column headers for space efficiency
  - Export to Excel functionality
  - Column visibility controls
  - Competitors (LMP) modal functionality
  - No summary statistics cards

### 2. **Controller**
- **File**: `/app/Http/Controllers/PurchaseMaster/UGCImageController.php`
- **Methods**:
  - `ugcImagesMaster()`: Returns the UGC images master view
  - `getUGCImagesMasterData()`: Fetches data for the table (Image, Parent, SKU, INV, Ovl30, Dil, LQS, B/S, Comp)

### 3. **Routes**
- **File**: `/routes/web.php`
- **Added Routes**:
  ```php
  Route::get('/ugc-images-master', [UGCImageController::class, 'ugcImagesMaster'])->name('ugc.images.master');
  Route::get('/ugc-images-master-data-view', [UGCImageController::class, 'getUGCImagesMasterData'])->name('ugc.images.master.data');
  ```

### 4. **Menu**
- **File**: `/resources/views/layouts/shared/left-sidebar.blade.php`
- **Added**: Menu item "UGC Images Masters" under Product Masters group (after Trust Images Masters)

## Columns Included

1. **Image** - Product image thumbnail
2. **Parent** - Parent product identifier
3. **SKU** - Product SKU with copy button
4. **INV** - Shopify inventory
5. **Ovl30** - Shopify quantity (last 30 days)
6. **Dil** - Days in Inventory (percentage)
7. **LQS** - Listing Quality Score (colored badge)
8. **B/S** - Buyer/Seller links
9. **Comp** - Competitors button (opens LMP modal)

## Features

### Included
- **Tabulator Table**: Modern, interactive table with sorting and filtering
- **Export to Excel**: One-click export of all data
- **Column Visibility**: Toggle columns on/off
- **Vertical Headers**: Space-efficient column headers
- **Frozen Columns**: Image, Parent, and SKU columns stay visible when scrolling
- **Competitors Modal**: View and manage competitor data (LMP)
- **Copy SKU**: Quick copy button for SKU values
- **LQS AVG Badge**: Shows average Listing Quality Score
- **Color Coding**: 
  - LQS scores (red/yellow/green)
  - Dil percentages (different thresholds)

### Not Included
- Summary statistics cards (Total, Parents, SKUs, DB Missing)
- Add/Import functionality
- Status column and toggle
- Audit columns and history
- A+(P) and A+(S) image upload
- DB column
- History column
- Action column
- Push column

## Data Source

The controller fetches data from:
- **ProductMaster** table (main product data)
- **ShopifySku** table (inventory and quantity)
- **AmazonDataView** table (buyer/seller links)
- **junglescout_product_data** table (LQS scores)

## Access

- **URL**: `/ugc-images-master`
- **Menu**: Product Masters → UGC Images Masters
- **Route Name**: `ugc.images.master`

## Technical Details

- **Table ID**: `#ugc-table`
- **AJAX URL**: `/ugc-images-master-data-view`
- **Local Storage Key**: `ugc_tabulator_column_visibility`
- **Layout**: `fitDataStretch` (fills available space)
- **Pagination**: 100 rows per page (default)
- **Styling**: Blue gradient headers, vertical text orientation

## Comparison with Other Image Masters

| Feature | A+ Images | Hero Images | Trust Images | UGC Images |
|---------|-----------|-------------|--------------|------------|
| Columns | Many (15+) | 9 columns | 9 columns | 9 columns |
| Add/Import | Yes | No | No | No |
| Audit | Yes | No | No | No |
| Image Upload | Yes | No | No | No |
| Summary Stats | Yes | No | No | No |
| Controller | CategoryController | HeroImageController | TrustImageController | UGCImageController |
| Purpose | A+ Content Images | Hero Images | Trust Badges | User Generated Content |

## Menu Order (Product Masters)

1. CP Masters
2. Category Master
3. ID Master
4. Dimensions & Weight Master
5. Dim Wt Master (CTN)
6. QC Upgrade
7. Shipping Master
8. General Specific Masters
9. Compliance Masters
10. Packing Inner Design
11. Extra Features Masters
12. **A+ Images Masters**
13. **Hero Images Masters**
14. **Trust Images Masters**
15. **UGC Images Masters** ← NEW
16. Keywords Master
17. Package Includes Master
18. Q&A Master
19. Competitors Master
20. Target Keywords Master
21. Target Products Master
22. Tag lines Masters
23. Group Masters

## Testing Checklist

- [ ] Page loads without errors
- [ ] Data displays correctly in all columns
- [ ] Competitors modal opens and displays data
- [ ] Export to Excel works
- [ ] Column visibility toggle works
- [ ] Sorting works on sortable columns
- [ ] Copy SKU button works
- [ ] Frozen columns stay in place during horizontal scroll
- [ ] LQS color coding appears correctly
- [ ] Dil percentage color coding works
- [ ] B/S links are clickable and open in new tab
- [ ] LQS AVG badge displays correctly

## Cache Status

✅ Application cache cleared
✅ Config cache cleared and rebuilt
✅ Route cache cleared
✅ View cache cleared

## Next Steps

1. Hard refresh browser (Ctrl+Shift+R / Cmd+Shift+R)
2. Navigate to Product Masters → UGC Images Masters
3. Test all functionalities listed in the checklist above

## Notes

- UGC stands for "User Generated Content"
- The UGC Images Master page is functionally identical to Hero and Trust Images Masters
- All three pages (Hero, Trust, UGC) share the same data source
- Each page maintains its own column visibility preferences via local storage
- Perfect for managing different types of product images in separate interfaces

## Use Cases

The separate image master pages allow you to:
- **A+ Images Master**: Manage A+ Content images with full editing capabilities
- **Hero Images Master**: Focus on hero/main product images
- **Trust Images Master**: Manage trust badges and certification images
- **UGC Images Master**: Handle user-generated content images (reviews, social media, etc.)
