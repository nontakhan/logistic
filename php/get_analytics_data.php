<?php
// php/get_analytics_data.php
header('Content-Type: application/json');

require_once __DIR__ . '/check_session.php';

// ต้อง login ก่อน
if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ']);
    exit;
}

// เชื่อมต่อฐานข้อมูล
$servername = "10.10.202.156";
$username = "nr";
$password = "P@ssw0rd";
$dbname = "logistic";

$conn = @new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้']);
    exit;
}

$conn->set_charset("utf8mb4");

// --- สร้าง WHERE clause ---
$where_conditions = ["1=1"];
$params = [];
$param_types = "";
$join_origin = false; // Flag เพื่อเช็คว่าต้อง Join ตาราง origin ไหม

// กรองตามสาขาของผู้ใช้
if ($_SESSION['role_level'] != 4 && !empty($_SESSION['assigned_transport_origin_id'])) {
    $where_conditions[] = "o.transport_origin_id = ?";
    $params[] = $_SESSION['assigned_transport_origin_id'];
    $param_types .= "i";
}

// รับค่าจาก Query String
$filter_date_start = isset($_GET['date_start']) && !empty($_GET['date_start']) ? $_GET['date_start'] : '';
$filter_date_end = isset($_GET['date_end']) && !empty($_GET['date_end']) ? $_GET['date_end'] : '';
$filter_transport_origin = isset($_GET['transport_origin']) && !empty($_GET['transport_origin']) ? (int)$_GET['transport_origin'] : 0;
$filter_province = isset($_GET['province']) && !empty($_GET['province']) ? $_GET['province'] : '';

// กรองตามวันที่
if (!empty($filter_date_start)) {
    $where_conditions[] = "DATE(o.order_date) >= ?";
    $params[] = $filter_date_start;
    $param_types .= "s";
}
if (!empty($filter_date_end)) {
    $where_conditions[] = "DATE(o.order_date) <= ?";
    $params[] = $filter_date_end;
    $param_types .= "s";
}

// กรองตามสาขา
if (in_array($_SESSION['role_level'], [1, 4]) && $filter_transport_origin > 0) {
    $where_conditions[] = "o.transport_origin_id = ?";
    $params[] = $filter_transport_origin;
    $param_types .= "i";
}

// กรองตามจังหวัด (ต้อง Join ตาราง origin)
if (!empty($filter_province)) {
    $join_origin = true;
    $where_conditions[] = "og_filter.province = ?";
    $params[] = $filter_province;
    $param_types .= "s";
}

$sql_where = " WHERE " . implode(" AND ", $where_conditions);
$sql_join_origin = $join_origin ? " LEFT JOIN origin og_filter ON o.customer_address_origin_id = og_filter.id " : "";

try {
    // --- 1. Order Statistics ---
    $order_stats = [
        'total' => 0, 'pending_ack' => 0, 'pending_assign' => 0,
        'pending_delivery' => 0, 'delivered' => 0, 'cancelled' => 0
    ];

    $sql_stats = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'รอรับเรื่อง' THEN 1 ELSE 0 END) AS pending_ack,
                    SUM(CASE WHEN status = 'รับเรื่อง' THEN 1 ELSE 0 END) AS pending_assign,
                    SUM(CASE WHEN status = 'รอส่งของ' THEN 1 ELSE 0 END) AS pending_delivery,
                    SUM(CASE WHEN status = 'ส่งของแล้ว' THEN 1 ELSE 0 END) AS delivered,
                    SUM(CASE WHEN status = 'ยกเลิก' THEN 1 ELSE 0 END) AS cancelled
                  FROM orders o" 
                  . $sql_join_origin 
                  . $sql_where;

    $stmt = $conn->prepare($sql_stats);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $order_stats['total'] = (int)($row['total'] ?? 0);
        $order_stats['pending_ack'] = (int)($row['pending_ack'] ?? 0);
        $order_stats['pending_assign'] = (int)($row['pending_assign'] ?? 0);
        $order_stats['pending_delivery'] = (int)($row['pending_delivery'] ?? 0);
        $order_stats['delivered'] = (int)($row['delivered'] ?? 0);
        $order_stats['cancelled'] = (int)($row['cancelled'] ?? 0);
    }
    $stmt->close();

    // --- 2. Status Distribution ---
    $status_distribution = [];
    $sql_status = "SELECT status, COUNT(*) as count FROM orders o" 
                  . $sql_join_origin 
                  . $sql_where . " GROUP BY status ORDER BY count DESC";
    $stmt = $conn->prepare($sql_status);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $status_distribution[] = ['label' => $row['status'] ?? 'ไม่ระบุ', 'value' => (int)$row['count']];
    }
    $stmt->close();

    // --- 3. Branch Rankings ---
    $branch_rankings = [];
    $sql_branch = "SELECT COALESCE(t.origin_name, 'ไม่ระบุ') as origin_name, COUNT(o.order_id) as count 
                   FROM orders o 
                   LEFT JOIN transport_origins t ON o.transport_origin_id = t.transport_origin_id" 
                   . $sql_join_origin 
                   . $sql_where . " GROUP BY t.origin_name ORDER BY count DESC LIMIT 10";
    $stmt = $conn->prepare($sql_branch);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $branch_rankings[] = ['label' => $row['origin_name'], 'value' => (int)$row['count']];
    }
    $stmt->close();

    // --- 4. Vehicle Types ---
    $vehicle_types = [];
    $sql_vehicle = "SELECT COALESCE(v.vehicle_name, 'ไม่ระบุ') as vehicle_name, COUNT(o.order_id) as count 
                    FROM orders o 
                    LEFT JOIN vehicles v ON o.assigned_vehicle_id = v.vehicle_id" 
                    . $sql_join_origin 
                    . $sql_where . " GROUP BY v.vehicle_name ORDER BY count DESC LIMIT 10";
    $stmt = $conn->prepare($sql_vehicle);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $vehicle_types[] = ['label' => $row['vehicle_name'], 'value' => (int)$row['count']];
    }
    $stmt->close();

    // --- 5. Driver Performance (พนักงานขับรถ Top 10) ---
    $driver_performance = [];
    $sql_driver = "SELECT COALESCE(s.staff_name, 'ไม่ระบุ') as staff_name, COUNT(o.order_id) as count 
                   FROM orders o 
                   LEFT JOIN staff s ON o.assigned_staff_id = s.staff_id" 
                   . $sql_join_origin 
                   . $sql_where . " AND o.assigned_staff_id IS NOT NULL
                   GROUP BY s.staff_id, s.staff_name ORDER BY count DESC LIMIT 10";
    $stmt = $conn->prepare($sql_driver);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $driver_performance[] = ['name' => $row['staff_name'], 'count' => (int)$row['count']];
    }
    $stmt->close();

    // --- 6. สถานที่จัดส่งยอดนิยม (Grouped Location) ---
    $location_rankings = [];
    if ($join_origin) {
        $sql_location = "SELECT CONCAT(
                            COALESCE(og_filter.province, ''), ' ',
                            COALESCE(og_filter.amphoe, ''), ' ',
                            COALESCE(og_filter.tambon, ''), ' ',
                            COALESCE(og_filter.moo, ''), ' ',
                            COALESCE(og_filter.mooban, '')
                        ) as full_location, 
                        COUNT(o.order_id) as count 
                        FROM orders o " 
                        . $sql_join_origin 
                        . $sql_where . " 
                        GROUP BY full_location 
                        HAVING full_location != ''
                        ORDER BY count DESC LIMIT 10";
    } else {
        $sql_location = "SELECT CONCAT(
                            COALESCE(og.province, ''), ' ',
                            COALESCE(og.amphoe, ''), ' ',
                            COALESCE(og.tambon, ''), ' ',
                            COALESCE(og.moo, ''), ' ',
                            COALESCE(og.mooban, '')
                        ) as full_location, 
                        COUNT(o.order_id) as count 
                        FROM orders o 
                        LEFT JOIN origin og ON o.customer_address_origin_id = og.id" 
                        . $sql_where . " 
                        GROUP BY full_location 
                        HAVING full_location != ''
                        ORDER BY count DESC LIMIT 10";
    }

    $stmt = $conn->prepare($sql_location);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $location = trim($row['full_location']);
        // Format string ให้ดูง่ายขึ้น (ลบช่องว่างซ้ำซ้อน)
        $location = preg_replace('/\s+/', ' ', $location);
        if (empty($location)) $location = 'ไม่ระบุ';
        $location_rankings[] = ['label' => $location, 'value' => (int)$row['count']];
    }
    $stmt->close();


    // --- 9. Top 10 Customers ---
    $customer_rankings = [];
    $sql_customer = "SELECT COALESCE(c.custname, 'ไม่ระบุ') as customer_name, COUNT(o.order_id) as count 
                     FROM orders o 
                     LEFT JOIN cssale c ON o.cssale_docno = c.docno " 
                     . $sql_join_origin 
                     . $sql_where . " 
                     GROUP BY c.custname 
                     HAVING customer_name != 'ไม่ระบุ' 
                     ORDER BY count DESC LIMIT 10";

    $stmt = $conn->prepare($sql_customer);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $customer_rankings[] = ['label' => $row['customer_name'], 'value' => (int)$row['count']];
    }
    $stmt->close();

    // --- 10. Monthly Summary ---
    $monthly_summary = [];
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $date = date('Y-m-01', strtotime("-$i months"));
        $months[$date] = ['label' => date('m/Y', strtotime($date)), 'value' => 0];
    }

    $sql_monthly = "SELECT DATE_FORMAT(o.order_date, '%Y-%m-01') as month, COUNT(*) as count 
                    FROM orders o" 
                    . $sql_join_origin 
                    . $sql_where . " 
                    AND o.order_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                    GROUP BY month ORDER BY month ASC";
    $stmt = $conn->prepare($sql_monthly);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $month_key = $row['month'];
        if (isset($months[$month_key])) {
            $months[$month_key]['value'] = (int)$row['count'];
        }
    }
    $stmt->close();
    $monthly_summary = array_values($months);

    // --- ส่ง Response ---
    echo json_encode([
        'status' => 'success',
        'data' => [
            'order_stats' => $order_stats,
            'status_distribution' => $status_distribution,
            'branch_rankings' => $branch_rankings,
            'vehicle_types' => $vehicle_types,
            'driver_performance' => $driver_performance,
            'location_rankings' => $location_rankings,
            'customer_rankings' => $customer_rankings,
            'monthly_summary' => $monthly_summary
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
