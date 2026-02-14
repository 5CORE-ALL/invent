-- ============================================================
-- Quick Database Check for Amazon Sales Data Issues
-- Run this in MySQL/PHPMyAdmin to see the problem
-- ============================================================

-- Check date range we're analyzing
SELECT 
    'Date Range' as Check_Type,
    DATE_SUB(CURDATE(), INTERVAL 29 DAY) as Start_Date,
    DATE_SUB(CURDATE(), INTERVAL 1 DAY) as End_Date;

-- ============================================================
-- CHECK 1: How many orders vs items?
-- ============================================================
SELECT 
    'Order vs Items Count' as Check_Type,
    (SELECT COUNT(*) FROM amazon_orders 
     WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     AND order_date <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
     AND (status IS NULL OR status != 'Canceled')
    ) as Total_Orders,
    
    (SELECT COUNT(*) FROM amazon_order_items i
     JOIN amazon_orders o ON i.amazon_order_id = o.id
     WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     AND o.order_date <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
     AND (o.status IS NULL OR o.status != 'Canceled')
    ) as Total_Items,
    
    -- This should be 0! If > 0, these orders are MISSING items
    (SELECT COUNT(*) FROM amazon_orders o
     WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     AND o.order_date <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
     AND (o.status IS NULL OR o.status != 'Canceled')
     AND NOT EXISTS (
         SELECT 1 FROM amazon_order_items i WHERE i.amazon_order_id = o.id
     )
    ) as Orders_WITHOUT_Items;

-- ============================================================
-- CHECK 2: Current revenue vs expected
-- ============================================================
SELECT 
    'Revenue Check' as Check_Type,
    ROUND(SUM(i.price), 2) as Current_Revenue_In_DB,
    142925.00 as Amazon_Reports,
    ROUND(142925.00 - SUM(i.price), 2) as Missing_Revenue,
    ROUND(((142925.00 - SUM(i.price)) / 142925.00) * 100, 1) as Missing_Percentage
FROM amazon_order_items i
JOIN amazon_orders o ON i.amazon_order_id = o.id
WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
AND o.order_date <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
AND (o.status IS NULL OR o.status != 'Canceled');

-- ============================================================
-- CHECK 3: Quantity check
-- ============================================================
SELECT 
    'Quantity Check' as Check_Type,
    SUM(i.quantity) as Current_Quantity_In_DB,
    2609 as Amazon_Reports,
    2609 - SUM(i.quantity) as Missing_Units
FROM amazon_order_items i
JOIN amazon_orders o ON i.amazon_order_id = o.id
WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
AND o.order_date <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
AND (o.status IS NULL OR o.status != 'Canceled');

-- ============================================================
-- CHECK 4: Items with $0 price (data quality issue)
-- ============================================================
SELECT 
    'Zero Price Items' as Check_Type,
    COUNT(*) as Items_With_Zero_Price
FROM amazon_order_items i
JOIN amazon_orders o ON i.amazon_order_id = o.id
WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
AND o.order_date <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
AND (o.status IS NULL OR o.status != 'Canceled')
AND (i.price = 0 OR i.price IS NULL);

-- ============================================================
-- CHECK 5: Sample of orders WITHOUT items (the main problem!)
-- ============================================================
SELECT 
    o.amazon_order_id,
    o.order_date,
    o.status,
    o.total_amount,
    'NO ITEMS FETCHED!' as Problem
FROM amazon_orders o
WHERE o.order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
AND o.order_date <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
AND (o.status IS NULL OR o.status != 'Canceled')
AND NOT EXISTS (
    SELECT 1 FROM amazon_order_items i WHERE i.amazon_order_id = o.id
)
LIMIT 20;

-- ============================================================
-- CHECK 6: Orders by status
-- ============================================================
SELECT 
    COALESCE(status, 'NULL') as Order_Status,
    COUNT(*) as Count
FROM amazon_orders
WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
AND order_date <= DATE_SUB(CURDATE(), INTERVAL 1 DAY)
GROUP BY status
ORDER BY Count DESC;

-- ============================================================
-- SUMMARY: What you should see if data is COMPLETE
-- ============================================================
-- Total Orders: ~1,426 (non-canceled)
-- Total Items: ~2,327 (should match Amazon report)
-- Total Revenue: ~$142,925 (should match Amazon report)
-- Total Quantity: ~2,609 (should match Amazon report)
-- Orders WITHOUT Items: 0 (THIS IS CRITICAL!)
-- Items with $0 Price: 0 (should be fixed)
-- ============================================================
