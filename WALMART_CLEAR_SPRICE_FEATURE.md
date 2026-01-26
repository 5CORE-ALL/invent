# Walmart - Clear S PRC Feature

## ✅ New Feature: Clear S PRC for Selected Rows

Added ability to **bulk clear saved prices (S PRC)** for selected SKUs and revert to API prices!

---

## 🎯 What It Does

**Clears saved prices (S PRC) for selected SKUs** and reverts them to Walmart API prices.

**Before:**
```
S PRC: $28.00  (manually edited)
```

**After Clear:**
```
S PRC: $29.99  (reverted to API PRC)
```

---

## 🚀 How to Use

### Step 1: Select Rows

**Option A: Select Individual Rows**
- Click checkboxes next to SKUs you want to clear

**Option B: Select All (Filtered)**
- Filter the table (e.g., click "W 0 Sold")
- Click "Select All" checkbox
- All filtered rows selected!

**Option C: Select Multiple**
- Click multiple checkboxes
- Or use Shift+Click for range selection

### Step 2: Click "Clear S PRC" Button

Located in the bulk operations bar (shows when rows selected):

```
┌────────────────────────────────────────────────────┐
│ [3 SKUs selected] [Percentage▼] [Enter %]          │
│ [Apply] [Sugg Amz Prc] [Clear S PRC] ← Click here │
└────────────────────────────────────────────────────┘
```

### Step 3: Confirm

Confirmation dialog appears:
```
Clear S PRC for 3 selected SKU(s)?

This will remove saved prices and revert to API prices.

[Cancel] [OK]
```

### Step 4: Done!

**Success message:**
```
✓ S PRC cleared for 3 SKU(s) (reverted to API price)
```

---

## 💡 What Happens

### For Each Selected SKU:

1. **Clears S PRC** in the table display
2. **Reverts to API Price** (if available)
3. **Saves to database** (`walmart_data_view.sprice = API price`)
4. **Shows success** message

### Example:

**Before:**
```
SKU     | API PRC | S PRC  | Status
--------|---------|--------|--------
ABC-123 | $29.99  | $28.00 | Edited (saved)
DEF-456 | $35.00  | $32.50 | Edited (saved)
GHI-789 | $19.99  | $18.00 | Edited (saved)
```

**After Clear:**
```
SKU     | API PRC | S PRC  | Status
--------|---------|--------|--------
ABC-123 | $29.99  | $29.99 | Reverted to API ✅
DEF-456 | $35.00  | $35.00 | Reverted to API ✅
GHI-789 | $19.99  | $19.99 | Reverted to API ✅
```

---

## 🎨 Button Appearance

```
┌────────────────────────────────────────────┐
│ Bulk Operations Bar (when rows selected)  │
├────────────────────────────────────────────┤
│                                            │
│ [3 SKUs selected]                          │
│                                            │
│ [Percentage▼] [Enter %] [Apply]            │
│                                            │
│ [Sugg Amz Prc]                             │
│                                            │
│ [Clear S PRC] ← Red button with eraser    │
│                                            │
└────────────────────────────────────────────┘
```

**Button Style:**
- Color: Red (`btn-danger`)
- Icon: Eraser (`fa-eraser`)
- Label: "Clear S PRC"

---

## 📋 Use Cases

### 1. **Reset to API Prices**

You edited prices manually but want to revert:
```
1. Filter to specific items
2. Select all filtered rows
3. Click "Clear S PRC"
4. All revert to Walmart API prices ✅
```

### 2. **Remove Old Edits**

Clear outdated manual price changes:
```
1. Select items with old S PRC values
2. Click "Clear S PRC"
3. Fresh start with current API prices ✅
```

### 3. **Bulk Reset After Test**

Tested pricing strategy, want to reset:
```
1. Select all affected SKUs
2. Click "Clear S PRC"  
3. Back to API baseline ✅
```

---

## 🔧 Technical Details

### What Gets Cleared:

```javascript
// Clears sprice in database (walmart_data_view)
field: 'sprice',
value: 0  // Or API price if available
```

### Backend Call:

```
POST /walmart-sheet-update-cell

Body:
{
    sku: "ABC-123",
    field: "sprice",
    value: 29.99  // API price or 0
}
```

### Revert Logic:

```javascript
const apiPrice = parseFloat(rowData['api_price']) || 0;

// If API price exists, use it
// Otherwise use 0
sprice: apiPrice > 0 ? apiPrice : 0
```

---

## ✅ Safety Features

### 1. **Confirmation Dialog**
```
Asks for confirmation before clearing
Prevents accidental clears
```

### 2. **Success/Failure Tracking**
```
Counts successful clears
Reports failures
Shows final status
```

### 3. **Database Persistence**
```
Saves to walmart_data_view table
Changes persist across page reloads
```

### 4. **Selection Auto-Clear**
```
Clears selection after operation
Prevents accidental re-clear
```

---

## 🧪 Test the Feature

### Test 1: Clear Single SKU

1. Select 1 row
2. Note current S PRC value
3. Click "Clear S PRC"
4. Confirm
5. Verify S PRC = API PRC ✅

### Test 2: Clear Multiple SKUs

1. Select 5 rows
2. Click "Clear S PRC"
3. Confirm
4. Verify all 5 S PRC values updated ✅

### Test 3: Clear Filtered Results

1. Click "W 0 Sold" badge
2. Click "Select All"
3. Click "Clear S PRC"
4. All filtered items cleared ✅

---

## 📁 Files Modified

1. ✅ `resources/views/market-places/walmart_sheet_upload_view.blade.php`
   - Added "Clear S PRC" button (red, with eraser icon)
   - Added clearSpriceForSelected() function
   - Added click handler

---

## Summary

**New Feature:**
- ✅ "Clear S PRC" button (red)
- ✅ Works on selected rows
- ✅ Reverts to API prices
- ✅ Saves to database
- ✅ Confirmation dialog
- ✅ Success/failure tracking

**Use Cases:**
- Reset to API prices ✅
- Remove old edits ✅
- Bulk reset after testing ✅
- Start fresh with current API data ✅

**Ready to use!** Select rows and click "Clear S PRC" button! 🎉
