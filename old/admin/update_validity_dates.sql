-- =====================================================
-- ALTER TABLE SCRIPT TO ADD VALIDITY_DATE COLUMN
-- =====================================================

-- Add validity_date column to digi_card table
ALTER TABLE digi_card ADD COLUMN validity_date DATETIME NULL DEFAULT NULL;

-- =====================================================
-- UPDATE QUERIES TO SET 1-YEAR VALIDITY DATES
-- =====================================================

-- Update paid cards (Success status) - Set validity to 1 year from payment date
UPDATE digi_card 
SET validity_date = DATE_ADD(d_payment_date, INTERVAL 1 YEAR) 
WHERE d_payment_status = 'Success' 
  AND d_payment_date IS NOT NULL 
  AND validity_date IS NULL;

-- Update trial cards (Created status) - Set validity to 1 year from creation date
UPDATE digi_card 
SET validity_date = DATE_ADD(uploaded_date, INTERVAL 1 YEAR) 
WHERE d_payment_status = 'Created' 
  AND uploaded_date IS NOT NULL 
  AND validity_date IS NULL;

-- Update any other cards with different statuses - Set validity to 1 year from creation date
UPDATE digi_card 
SET validity_date = DATE_ADD(uploaded_date, INTERVAL 1 YEAR) 
WHERE d_payment_status NOT IN ('Success', 'Created') 
  AND uploaded_date IS NOT NULL 
  AND validity_date IS NULL;

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Check how many records were updated
SELECT 
    d_payment_status,
    COUNT(*) as total_cards,
    COUNT(validity_date) as cards_with_validity_date,
    COUNT(*) - COUNT(validity_date) as cards_without_validity_date
FROM digi_card 
GROUP BY d_payment_status;

-- Show sample of updated records
SELECT 
    id,
    d_comp_name,
    d_payment_status,
    uploaded_date,
    d_payment_date,
    validity_date,
    DATEDIFF(validity_date, COALESCE(d_payment_date, uploaded_date)) as validity_days
FROM digi_card 
WHERE validity_date IS NOT NULL 
ORDER BY id DESC 
LIMIT 10;
