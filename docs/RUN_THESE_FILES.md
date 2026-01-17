# Step-by-Step: Which Files to Run

## ⚠️ IMPORTANT: Backup Your Database First!
Before running any scripts, backup your database using phpMyAdmin or command line.

---

## Step 1: Create New Tables with user_id
**File:** `admin/create_dynamic_tables.php`

### How to Run:
1. Open your browser
2. Go to: `http://localhost/miniwebsite_php/admin/create_dynamic_tables.php`
3. Or: `http://your-domain/admin/create_dynamic_tables.php`

### What You'll See:
```
✓ card_products_services table created successfully
✓ card_product_pricing table created successfully
✓ card_image_gallery table created successfully
```

### ✅ Check:
- [ ] All 3 tables created successfully
- [ ] No errors shown

---

## Step 2: Migrate Data with user_id
**File:** `admin/migrate_to_dynamic_tables.php`

### How to Run:
1. Open your browser
2. Go to: `http://localhost/miniwebsite_php/admin/migrate_to_dynamic_tables.php`
3. Or: `http://your-domain/admin/migrate_to_dynamic_tables.php`

### What You'll See:
```
0. Clearing existing data and updating table structure...
✓ Cleared existing data from new tables
✓ Updated card_products_services to use user_id
✓ Updated card_product_pricing to use user_id
✓ Updated card_image_gallery to use user_id

1. Migrating Products & Services...
✓ Migrated XXX products/services records

2. Migrating Product Pricing...
✓ Migrated XXX product pricing records

3. Migrating Image Gallery...
✓ Migrated XXX gallery image records

Migration Complete!
```

### ✅ Check:
- [ ] Migration completed without fatal errors
- [ ] Record counts look reasonable
- [ ] Check for any warnings (some are normal)

---

## Step 3: Verify Data (Optional but Recommended)
**Tool:** phpMyAdmin or MySQL Command Line

### Check Products & Services:
```sql
SELECT COUNT(*) FROM card_products_services;
SELECT * FROM card_products_services LIMIT 5;
```

### Check Product Pricing:
```sql
SELECT COUNT(*) FROM card_product_pricing;
SELECT * FROM card_product_pricing LIMIT 5;
```

### Check Image Gallery:
```sql
SELECT COUNT(*) FROM card_image_gallery;
SELECT * FROM card_image_gallery LIMIT 5;
```

### ✅ Check:
- [ ] Data exists in new tables
- [ ] `user_id` column shows numbers (not emails)
- [ ] Record counts match expectations

---

## Step 4: Update PHP Code (I'll Do This)
**Files to Update:**
- `customer/website/product-and-services.php`
- `customer/website/product-pricing.php`
- `customer/website/image-gallery.php`

**Status:** ⏳ Waiting for you to complete Steps 1-3

---

## Step 5: Test Pages
**After I update the code, test these pages:**

1. **Product & Services:**
   - URL: `customer/website/product-and-services.php?card_number=YOUR_CARD_ID`
   - Test: Add, edit, delete products

2. **Product Pricing:**
   - URL: `customer/website/product-pricing.php?card_number=YOUR_CARD_ID`
   - Test: Add, edit, delete products with pricing

3. **Image Gallery:**
   - URL: `customer/website/image-gallery.php?card_number=YOUR_CARD_ID`
   - Test: Add, edit, delete gallery images

---

## Quick Summary

| Step | File to Run | Purpose |
|------|-------------|---------|
| 1 | `admin/create_dynamic_tables.php` | Create new tables with user_id |
| 2 | `admin/migrate_to_dynamic_tables.php` | Migrate data with user_id |
| 3 | phpMyAdmin (verify) | Check data migrated correctly |
| 4 | (I'll update) | Update PHP code files |
| 5 | Test pages | Verify everything works |

---

## Troubleshooting

### If Step 1 fails:
- Check database connection
- Verify you have CREATE TABLE permissions
- Check PHP error logs

### If Step 2 fails:
- Check if Step 1 completed successfully
- Verify old tables exist (digi_card2, products, digi_card3)
- Check for foreign key constraint errors
- Review warnings in output (some are normal)

### If migration shows warnings:
- Warnings about "User not found" are normal if some old records don't have matching users
- These records will be skipped (not migrated)
- This is expected behavior

---

## Need Help?
- Check browser console (F12)
- Check PHP error logs
- Review migration script output messages
- Verify database connection settings

