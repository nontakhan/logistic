-- SQL Script สำหรับแก้ไขปัญหาสมรรถภาพปุ่ม "เพิ่มรายการ"

-- 1. เพิ่ม Index ที่จำเป็นสำหรับตาราง cssale
ALTER TABLE cssale ADD INDEX idx_shipflag_docdate (shipflag, docdate DESC);
ALTER TABLE cssale ADD INDEX idx_docno (docno);

-- 2. เพิ่ม Index สำหรับตาราง orders
ALTER TABLE orders ADD INDEX idx_cssale_docno (cssale_docno);

-- 3. เพิ่ม Index สำหรับตาราง transport_origins และ origin
ALTER TABLE transport_origins ADD INDEX idx_origin_name (origin_name);
ALTER TABLE origin ADD INDEX idx_full_address (id, mooban, moo, tambon, amphoe, province);

-- 4. สร้าง View สำหรับ Query บิลที่รอเพิ่ม (เพื่อลดการคำนวณในแต่ละครั้ง)
CREATE OR REPLACE VIEW available_bills AS
SELECT 
    cs.docno, 
    cs.custname,
    cs.docdate,
    cs.code,
    cs.lname,
    cs.shipaddr
FROM cssale cs
WHERE cs.shipflag = 1 
AND NOT EXISTS (
    SELECT 1 FROM orders o 
    WHERE o.cssale_docno = cs.docno 
    LIMIT 1
)
ORDER BY cs.docdate DESC, cs.docno DESC;

-- 5. เพิ่ม Index สำหรับ salesman query
ALTER TABLE cssale ADD INDEX idx_salesman (code, lname);

COMMIT;
