ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS bill_created_at DATETIME NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS acknowledged_at DATETIME NULL AFTER bill_created_at,
    ADD COLUMN IF NOT EXISTS assigned_at DATETIME NULL AFTER acknowledged_at,
    ADD COLUMN IF NOT EXISTS delivered_at DATETIME NULL AFTER assigned_at;

UPDATE orders
SET bill_created_at = COALESCE(bill_created_at, created_at)
WHERE bill_created_at IS NULL;

UPDATE orders
SET acknowledged_at = COALESCE(acknowledged_at, updated_at)
WHERE acknowledged_at IS NULL
  AND status IN ('รับเรื่อง', 'รอส่งของ', 'ส่งของแล้ว');

UPDATE orders
SET assigned_at = COALESCE(assigned_at, updated_at)
WHERE assigned_at IS NULL
  AND status IN ('รอส่งของ', 'ส่งของแล้ว');

UPDATE orders
SET delivered_at = COALESCE(delivered_at, updated_at)
WHERE delivered_at IS NULL
  AND status = 'ส่งของแล้ว';
