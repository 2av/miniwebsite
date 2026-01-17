# Step-by-Step Migration Guide

## Overview
This guide will help you migrate from the old numbered-column structure to the new dynamic table structure.

---

## Step 1: Backup Your Database ⚠️ IMPORTANT
**Before making any changes, backup your database!**

### Option A: Using phpMyAdmin
1. Open phpMyAdmin
2. Select your database
3. Click "Export" tab
4. Choose "Quick" method
5. Click "Go" to download backup

### Option B: Using Command Line
```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

---

## Step 2: Create New Dynamic Tables
**File to run:** `admin/create_dynamic_tables.php`

### How to run:
1. Open in browser: `http://localhost/miniwebsite_php/admin/create_dynamic_tables.php`
2. Or access via: `http://your-domain/admin/create_dynamic_tables.php`

### What it does:
- Creates 3 new tables with proper user_id management:
  - `card_products_services` (for product-and-services.php) - uses `user_id INT(11)` with foreign key
  - `card_product_pricing` (for product-pricing.php) - uses `user_id INT(11)` with foreign key
  - `card_image_gallery` (for image-gallery.php) - uses `user_id INT(11)` with foreign key
- All tables have foreign key relationships to `customer_login(id)` with CASCADE delete

### Expected output:
```
✓ card_products_services table created successfully
✓ card_product_pricing table created successfully
✓ card_image_gallery table created successfully
```

### ✅ Checkpoint:
- [ ] All 3 tables created successfully
- [ ] No errors shown

---

## Step 3: Migrate Existing Data
**File to run:** `admin/migrate_to_dynamic_tables.php`

### How to run:
1. Open in browser: `http://localhost/miniwebsite_php/admin/migrate_to_dynamic_tables.php`
2. Or access via: `http://your-domain/admin/migrate_to_dynamic_tables.php`

### What it does:
- **Clears existing data** in new tables first (TRUNCATE)
- **Updates table structure** to use `user_id` if needed
- **Copies data** from old tables to new tables with proper user_id mapping:
  - `digi_card2` → `card_products_services` (up to 10 products)
  - `products` → `card_product_pricing` (up to 20 products)
  - `digi_card3` → `card_image_gallery` (up to 10 images)
- **Maps user_email to user_id** by joining with `customer_login` table
- Automatically fixes column size issues
- Validates and cleans data
- Reports any records that couldn't be migrated (user not found)

### Expected output:
```
0. Checking and updating table structure...
✓ Updated tax_rate column to DECIMAL(10,2)

1. Migrating Products & Services...
✓ Migrated XXX products/services records

2. Migrating Product Pricing...
✓ Migrated XXX product pricing records

3. Migrating Image Gallery...
✓ Migrated XXX gallery image records
```

### ⚠️ Notes:
- If you see warnings about tax values, that's normal - they're being fixed automatically
- The script is safe to run multiple times (won't create duplicates)

### ✅ Checkpoint:
- [ ] Migration completed without fatal errors
- [ ] Record counts look reasonable
- [ ] Check database to verify data was copied

---

## Step 4: Verify Data Migration
**Check in phpMyAdmin or MySQL:**

### Verify Products & Services:
```sql
SELECT COUNT(*) FROM card_products_services;
SELECT * FROM card_products_services LIMIT 5;
```

### Verify Product Pricing:
```sql
SELECT COUNT(*) FROM card_product_pricing;
SELECT * FROM card_product_pricing LIMIT 5;
```

### Verify Image Gallery:
```sql
SELECT COUNT(*) FROM card_image_gallery;
SELECT * FROM card_image_gallery LIMIT 5;
```

### ✅ Checkpoint:
- [ ] Data exists in new tables
- [ ] Record counts match expectations
- [ ] Sample records look correct

---

## Step 5: Update PHP Code Files
**Files to update (I'll do this for you):**

1. `customer/website/product-and-services.php`
   - Change from `digi_card2` to `card_products_services`
   - Update queries to use new structure

2. `customer/website/product-pricing.php`
   - Change from `products` to `card_product_pricing`
   - Update queries to use new structure

3. `customer/website/image-gallery.php`
   - Change from `digi_card3` to `card_image_gallery`
   - Update queries to use new structure

### ✅ Checkpoint:
- [ ] Code updated (I'll do this)
- [ ] Ready for testing

---

## Step 6: Test the Updated Pages
**Test each page:**

1. **Product & Services Page:**
   - URL: `customer/website/product-and-services.php`
   - Test: Add, edit, delete products
   - Verify: Products appear in table

2. **Product Pricing Page:**
   - URL: `customer/website/product-pricing.php`
   - Test: Add, edit, delete products with pricing
   - Verify: Products with MRP and prices appear

3. **Image Gallery Page:**
   - URL: `customer/website/image-gallery.php`
   - Test: Add, edit, delete gallery images
   - Verify: Images appear in table

### ✅ Checkpoint:
- [ ] All pages load correctly
- [ ] Can add new items
- [ ] Can edit existing items
- [ ] Can delete items
- [ ] No errors in browser console

---

## Step 7: (Optional) Remove Old Tables
**⚠️ ONLY AFTER CONFIRMING EVERYTHING WORKS!**

### Backup old tables first:
```sql
CREATE TABLE digi_card2_backup AS SELECT * FROM digi_card2;
CREATE TABLE products_backup AS SELECT * FROM products;
CREATE TABLE digi_card3_backup AS SELECT * FROM digi_card3;
```

### Then drop old tables (if desired):
```sql
DROP TABLE digi_card2;
DROP TABLE products;
DROP TABLE digi_card3;
```

**Note:** You can keep old tables as backup - they won't interfere with new tables.

---

## Summary Checklist

- [ ] **Step 1:** Database backed up
- [ ] **Step 2:** New tables created (`create_dynamic_tables.php`)
- [ ] **Step 3:** Data migrated (`migrate_to_dynamic_tables.php`)
- [ ] **Step 4:** Data verified in database
- [ ] **Step 5:** PHP code updated (I'll do this)
- [ ] **Step 6:** All pages tested and working
- [ ] **Step 7:** (Optional) Old tables backed up/removed

---

## Troubleshooting

### If migration fails:
1. Check error messages
2. Verify database connection
3. Check table permissions
4. Review the warnings (they're usually safe to ignore)

### If data looks wrong:
1. Check the migration warnings
2. Verify source data in old tables
3. Re-run migration (it's safe - won't create duplicates)

### If pages don't work after code update:
1. Check browser console for errors
2. Verify database queries
3. Check PHP error logs
4. Revert to old code if needed (old tables still exist)

---

## Need Help?
If you encounter any issues, check:
- Browser console (F12)
- PHP error logs
- Database error messages
- Migration script output

