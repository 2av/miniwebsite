# Migration Execution Guide

## Quick Start - Execute Migration Now

Follow these steps in order:

### ✅ Step 1: Backup Database (REQUIRED)
**Do this first before any changes!**

1. Open phpMyAdmin
2. Select your database
3. Click "Export" tab
4. Choose "Quick" method
5. Click "Go" to download backup

**OR** use command line:
```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

---

### ✅ Step 2: Create New Tables
**File:** `admin/create_dynamic_tables.php`

**How to run:**
1. Open in browser: `http://localhost/miniwebsite_php/admin/create_dynamic_tables.php`
2. Or: `http://your-domain/admin/create_dynamic_tables.php`

**Expected output:**
```
✓ card_products_services table created successfully
✓ card_product_pricing table created successfully
✓ card_image_gallery table created successfully
```

**✅ Check:** All 3 tables created without errors

---

### ✅ Step 3: Migrate Data
**File:** `admin/migrate_to_dynamic_tables.php`

**How to run:**
1. Open in browser: `http://localhost/miniwebsite_php/admin/migrate_to_dynamic_tables.php`
2. Or: `http://your-domain/admin/migrate_to_dynamic_tables.php`

**What it does:**
- Clears existing data in new tables
- Copies all data from old tables to new tables
- Maps user_email to user_id automatically
- Preserves display order

**Expected output:**
```
✓ Migrated XXX products/services records
✓ Migrated XXX product pricing records
✓ Migrated XXX gallery image records
```

**✅ Check:** Migration completed, record counts look reasonable

---

### ✅ Step 4: Update PHP Code Files
**Files that need updating:**
- ✅ `n.php` - ALREADY UPDATED
- ✅ `customer/website/product-and-services.php` - ALREADY UPDATED
- ⏳ `customer/website/product-pricing.php` - NEEDS UPDATE
- ⏳ `customer/website/image-gallery.php` - NEEDS UPDATE

**I will update these files for you now.**

---

### ✅ Step 5: Test All Pages
After code updates, test:
1. Product & Services page
2. Product Pricing page
3. Image Gallery page
4. Public card view (n.php)

---

## Current Status

- [x] Migration scripts ready
- [ ] Database backed up (YOU NEED TO DO THIS)
- [ ] New tables created (RUN Step 2)
- [ ] Data migrated (RUN Step 3)
- [x] n.php updated
- [x] product-and-services.php updated
- [ ] product-pricing.php needs update (I'll do this)
- [ ] image-gallery.php needs update (I'll do this)

---

## Next Actions

1. **YOU:** Backup database (Step 1)
2. **YOU:** Run `create_dynamic_tables.php` (Step 2)
3. **YOU:** Run `migrate_to_dynamic_tables.php` (Step 3)
4. **ME:** Update remaining PHP files (Step 4)
5. **YOU:** Test all pages (Step 5)

