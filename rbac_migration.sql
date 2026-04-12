-- RBAC migration for NR Logistics
-- Run this script once against the logistic database.

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role_id INT NULL AFTER role_level,
    ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1 AFTER assigned_transport_origin_id;

CREATE TABLE IF NOT EXISTS roles (
    role_id INT NOT NULL AUTO_INCREMENT,
    role_key VARCHAR(100) NOT NULL,
    role_name VARCHAR(150) NOT NULL,
    description VARCHAR(255) NULL,
    legacy_role_level INT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id),
    UNIQUE KEY uniq_roles_role_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    permission_id INT NOT NULL AUTO_INCREMENT,
    permission_key VARCHAR(100) NOT NULL,
    permission_name VARCHAR(150) NOT NULL,
    permission_group VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (permission_id),
    UNIQUE KEY uniq_permissions_permission_key (permission_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE users
    ADD CONSTRAINT fk_users_role
    FOREIGN KEY (role_id) REFERENCES roles(role_id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;

INSERT INTO permissions (permission_key, permission_name, permission_group, description)
VALUES
    ('dashboard.view', 'ดูแดชบอร์ด', 'dashboard', 'เข้าหน้า dashboard หลัก'),
    ('orders.create', 'เพิ่มรายการจัดส่ง', 'orders', 'สร้างรายการจัดส่งจากบิล CSSale'),
    ('orders.view_all', 'ดูรายการทั้งหมด', 'orders', 'เข้าหน้าติดตามรายการทั้งหมด'),
    ('orders.view_details', 'ดูรายละเอียดรายการ', 'orders', 'เปิด modal หรือหน้ารายละเอียดรายการ'),
    ('orders.acknowledge', 'รับเรื่องรายการ', 'orders', 'เข้าหน้ารอรับเรื่องและกดรับเรื่อง'),
    ('orders.assign', 'จัดคนและรถ', 'orders', 'เข้าหน้ารอจัดคนรถและบันทึกการจัดส่ง'),
    ('orders.confirm_delivery', 'ยืนยันส่งของ', 'orders', 'เข้าหน้ารอส่งของและยืนยันส่งของแล้ว'),
    ('orders.cancel', 'ยกเลิกรายการ', 'orders', 'ยกเลิกรายการที่ยังไม่จบงาน'),
    ('orders.delete', 'ลบรายการ', 'orders', 'ลบรายการที่ถูกยกเลิกแล้ว'),
    ('orders.change_transport_origin', 'เปลี่ยนต้นทางขนส่ง', 'orders', 'แก้ไขต้นทางขนส่งของรายการ'),
    ('orders.update_driver', 'เปลี่ยนคนขับและรถ', 'orders', 'แก้ไขพนักงานขับรถและรถหลังจัดส่ง'),
    ('analytics.view', 'ดู Analytics', 'reports', 'เข้าหน้า analytics'),
    ('reports.filter_all_origins', 'กรองได้ทุกต้นทาง', 'reports', 'เลือกต้นทางขนส่งในการดูรายงาน/รายการ'),
    ('scope.all_origins', 'เข้าถึงทุกต้นทาง', 'scope', 'ไม่ถูกจำกัดข้อมูลตาม assigned transport origin'),
    ('pricing.view', 'ดูเช็คราคา', 'pricing', 'เข้าเครื่องมือเช็คราคา'),
    ('export.orders', 'ส่งออกข้อมูล', 'reports', 'export รายการเป็น Excel'),
    ('cssale.manage', 'จัดการ CSSale', 'admin', 'ลบหรือจัดการข้อมูล CSSale'),
    ('staff_vehicles.manage', 'จัดการพนักงานและรถ', 'admin', 'จัดการพนักงานขับรถ รถ และรถประจำ'),
    ('users.manage', 'จัดการผู้ใช้งาน', 'admin', 'เพิ่ม แก้ไข ลบ และปิดการใช้งานผู้ใช้งาน'),
    ('roles.manage', 'จัดการบทบาทและสิทธิ์', 'admin', 'สร้าง role และกำหนด permission'),
    ('admin.access', 'เข้าถึงเมนูผู้ดูแลระบบ', 'admin', 'มองเห็นเมนูและหน้าหลังบ้านของผู้ดูแล')
ON DUPLICATE KEY UPDATE
    permission_name = VALUES(permission_name),
    permission_group = VALUES(permission_group),
    description = VALUES(description);

INSERT INTO roles (role_key, role_name, description, legacy_role_level, active)
VALUES
    ('legacy-sales', 'ผู้เพิ่มข้อมูล', 'บทบาทเดิมระดับ 1', 1, 1),
    ('legacy-ops', 'ผู้ปฏิบัติการ', 'บทบาทเดิมระดับ 2', 2, 1),
    ('legacy-dispatch', 'ผู้จัดคนและรถ', 'บทบาทเดิมระดับ 3', 3, 1),
    ('legacy-admin', 'ผู้ดูแลระบบ', 'บทบาทเดิมระดับ 4', 4, 1)
ON DUPLICATE KEY UPDATE
    role_name = VALUES(role_name),
    description = VALUES(description),
    legacy_role_level = VALUES(legacy_role_level),
    active = VALUES(active);

DELETE rp
FROM role_permissions rp
INNER JOIN roles r ON rp.role_id = r.role_id
WHERE r.role_key IN ('legacy-sales', 'legacy-ops', 'legacy-dispatch', 'legacy-admin');

INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r
INNER JOIN permissions p ON
    (r.role_key = 'legacy-sales' AND p.permission_key IN (
        'dashboard.view', 'orders.create', 'orders.view_all', 'orders.view_details',
        'analytics.view', 'reports.filter_all_origins', 'pricing.view', 'export.orders'
    ))
    OR
    (r.role_key = 'legacy-ops' AND p.permission_key IN (
        'dashboard.view', 'orders.create', 'orders.view_all', 'orders.view_details',
        'orders.acknowledge', 'orders.assign', 'orders.confirm_delivery', 'orders.cancel',
        'orders.delete', 'orders.change_transport_origin',
        'analytics.view', 'pricing.view', 'export.orders'
    ))
    OR
    (r.role_key = 'legacy-dispatch' AND p.permission_key IN (
        'dashboard.view', 'orders.view_all', 'orders.view_details',
        'orders.acknowledge', 'orders.assign', 'orders.confirm_delivery',
        'orders.change_transport_origin', 'orders.update_driver',
        'analytics.view', 'pricing.view', 'export.orders'
    ))
    OR
    (r.role_key = 'legacy-admin' AND p.permission_key IN (
        'dashboard.view', 'orders.create', 'orders.view_all', 'orders.view_details',
        'orders.acknowledge', 'orders.assign', 'orders.confirm_delivery', 'orders.cancel',
        'orders.delete', 'orders.change_transport_origin', 'orders.update_driver',
        'analytics.view', 'reports.filter_all_origins', 'scope.all_origins',
        'pricing.view', 'export.orders', 'cssale.manage', 'staff_vehicles.manage',
        'users.manage', 'roles.manage', 'admin.access'
    ));

UPDATE users u
INNER JOIN roles r ON r.legacy_role_level = u.role_level
SET u.role_id = r.role_id
WHERE u.role_id IS NULL;

UPDATE users
SET active = 1
WHERE active IS NULL;
