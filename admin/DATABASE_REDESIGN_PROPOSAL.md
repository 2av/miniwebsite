# Database Redesign Proposal

## Current Problem
The current database structure uses numbered columns (e.g., `d_pro_img1`, `d_pro_img2`, ... `d_pro_img10`) which:
- ❌ Limits the number of items (fixed at 10 or 20)
- ❌ Requires ALTER TABLE to add more items
- ❌ Makes queries complex and inefficient
- ❌ Hard to manage and maintain

## Proposed Solution: Normalized Dynamic Tables

### New Table Structure

#### 1. **card_products_services** (Replaces `digi_card2`)
```sql
CREATE TABLE card_products_services (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    card_id VARCHAR(50) NOT NULL,
    user_email VARCHAR(200),
    product_name VARCHAR(200),
    product_image LONGBLOB,
    display_order INT(11) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_card_id (card_id),
    INDEX idx_user_email (user_email)
);
```
**Benefits:**
- ✅ Unlimited products (no column limit)
- ✅ Each product is a separate row
- ✅ Easy to add/delete/reorder
- ✅ Proper indexing for performance

#### 2. **card_product_pricing** (Replaces `products` table)
```sql
CREATE TABLE card_product_pricing (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    card_id VARCHAR(50) NOT NULL,
    user_email VARCHAR(200),
    product_name VARCHAR(200),
    product_image LONGBLOB,
    mrp DECIMAL(10,2) DEFAULT 0.00,
    selling_price DECIMAL(10,2) DEFAULT 0.00,
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    display_order INT(11) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_card_id (card_id),
    INDEX idx_user_email (user_email)
);
```
**Benefits:**
- ✅ Unlimited products with pricing
- ✅ Proper decimal types for prices
- ✅ Easy to query and sort
- ✅ Better data integrity

#### 3. **card_image_gallery** (Replaces `digi_card3`)
```sql
CREATE TABLE card_image_gallery (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    card_id VARCHAR(50) NOT NULL,
    user_email VARCHAR(200),
    gallery_image LONGBLOB,
    display_order INT(11) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_card_id (card_id),
    INDEX idx_user_email (user_email)
);
```
**Benefits:**
- ✅ Unlimited gallery images
- ✅ Easy to manage and reorder
- ✅ Better performance with indexes

## Migration Strategy

### Step 1: Create New Tables
Run: `admin/create_dynamic_tables.php`
- Creates all three new tables
- Safe to run multiple times (uses IF NOT EXISTS)

### Step 2: Migrate Existing Data
Run: `admin/migrate_to_dynamic_tables.php`
- Copies all data from old tables to new tables
- Preserves display order
- Safe to run multiple times (checks for duplicates)

### Step 3: Update PHP Code
Update these files to use new tables:
- `customer/website/product-and-services.php`
- `customer/website/product-pricing.php`
- `customer/website/image-gallery.php`

### Step 4: Testing
- Test all CRUD operations
- Verify data integrity
- Check performance

### Step 5: Remove Old Tables (Optional)
Only after confirming everything works:
- Backup old tables first
- Drop `digi_card2`, `products`, `digi_card3` (or keep as backup)

## Code Changes Required

### Example: Fetching Products (Old vs New)

**OLD WAY (digi_card2):**
```php
for($i = 1; $i <= 10; $i++) {
    if(!empty($row2["d_pro_name$i"])) {
        echo $row2["d_pro_name$i"];
    }
}
```

**NEW WAY (card_products_services):**
```php
$query = "SELECT * FROM card_products_services 
          WHERE card_id='".$_SESSION['card_id_inprocess']."' 
          ORDER BY display_order ASC";
$result = mysqli_query($connect, $query);
while($row = mysqli_fetch_array($result)) {
    echo $row['product_name'];
}
```

### Example: Adding Product (Old vs New)

**OLD WAY:**
```php
// Need to find empty slot (1-10)
UPDATE digi_card2 SET d_pro_name5='Product Name' WHERE id='123'
```

**NEW WAY:**
```php
// Just insert new row
INSERT INTO card_products_services (card_id, product_name, display_order) 
VALUES ('123', 'Product Name', (SELECT COALESCE(MAX(display_order), 0) + 1 FROM card_products_services WHERE card_id='123'))
```

## Advantages Summary

| Feature | Old Structure | New Structure |
|---------|--------------|---------------|
| **Limit** | Fixed (10-20 items) | Unlimited |
| **Adding Items** | Find empty slot | Just INSERT |
| **Deleting Items** | Clear column | DELETE row |
| **Reordering** | Not possible | Update display_order |
| **Query Performance** | Slow (multiple columns) | Fast (indexed) |
| **Maintenance** | Hard (ALTER TABLE) | Easy (normal SQL) |
| **Scalability** | Poor | Excellent |

## Recommendation

✅ **Strongly Recommended** to migrate to new structure because:
1. Better database design (normalized)
2. Unlimited items without schema changes
3. Better performance
4. Easier to maintain and extend
5. Industry best practices

