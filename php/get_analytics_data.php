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

// --- Handle Actions (Helper Data) ---
$action = isset($_GET['action']) ? $_GET['action'] : 'get_data';

if ($action == 'get_amphoes') {
    $province = isset($_GET['province']) ? $conn->real_escape_string($_GET['province']) : '';
    $amphoes = [];
    if ($province) {
        $sql = "SELECT DISTINCT amphoe FROM origin WHERE province = ? AND amphoe != '' ORDER BY amphoe";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $province);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $amphoes[] = $row['amphoe'];
        }
    }
    echo json_encode(['status' => 'success', 'data' => $amphoes]);
    exit;
}

if ($action == 'get_filter_options') {
    // Vehicle Types
    $vehicle_types = [];
    $sql_v = "SELECT DISTINCT vehicle_name FROM vehicles WHERE vehicle_name != '' ORDER BY vehicle_name";
    $result_v = $conn->query($sql_v);
    while ($row = $result_v->fetch_assoc()) {
        $vehicle_types[] = $row['vehicle_name'];
    }

    // Drivers (Staff)
    $drivers = [];
    // Select staff who are assigned to orders OR all staff if preferred. 
    // Let's select all staff for now to be safe.
    $sql_d = "SELECT staff_id, staff_name FROM staff WHERE staff_name != '' ORDER BY staff_name";
    $result_d = $conn->query($sql_d);
    while ($row = $result_d->fetch_assoc()) {
        $drivers[] = $row;
    }

    echo json_encode(['status' => 'success', 'data' => ['vehicle_types' => $vehicle_types, 'drivers' => $drivers]]);
    exit;
}

// --- Main Analytics Data ---

// --- สร้าง WHERE clause ---
$where_conditions = ["1=1"];
$params = [];
$param_types = "";
$join_origin = false; 
$join_vehicles = false;

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
$filter_amphoe = isset($_GET['amphoe']) && !empty($_GET['amphoe']) ? $_GET['amphoe'] : '';
$filter_vehicle_type = isset($_GET['vehicle_type']) && !empty($_GET['vehicle_type']) ? $_GET['vehicle_type'] : '';
$filter_driver_id = isset($_GET['driver_id']) && !empty($_GET['driver_id']) ? (int)$_GET['driver_id'] : 0;

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

// กรองตามจังหวัด
if (!empty($filter_province)) {
    $join_origin = true;
    $where_conditions[] = "og_filter.province = ?";
    $params[] = $filter_province;
    $param_types .= "s";
}

// กรองตามอำเภอ
if (!empty($filter_amphoe)) {
    $join_origin = true;
    $where_conditions[] = "og_filter.amphoe = ?";
    $params[] = $filter_amphoe;
    $param_types .= "s";
}

// กรองตามประเภทรถ
if (!empty($filter_vehicle_type)) {
    $join_vehicles = true;
    $where_conditions[] = "v_filter.vehicle_name = ?";
    $params[] = $filter_vehicle_type;
    $param_types .= "s";
}

// กรองตามคนขับ
if ($filter_driver_id > 0) {
    $where_conditions[] = "o.assigned_staff_id = ?";
    $params[] = $filter_driver_id;
    $param_types .= "i";
}

$sql_where = " WHERE " . implode(" AND ", $where_conditions);
$sql_extra_joins = "";
if ($join_origin) $sql_extra_joins .= " LEFT JOIN origin og_filter ON o.customer_address_origin_id = og_filter.id ";
if ($join_vehicles) $sql_extra_joins .= " LEFT JOIN vehicles v_filter ON o.assigned_vehicle_id = v_filter.vehicle_id ";

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
                  . $sql_extra_joins 
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
                  . $sql_extra_joins 
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
                   . $sql_extra_joins 
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
    $sql_vehicle = "SELECT 
                        CASE 
                            WHEN TRIM(v.vehicle_name) = '' OR v.vehicle_name IS NULL THEN 'ไม่ระบุ'
                            ELSE v.vehicle_name 
                        END as clean_vehicle_name, 
                        COUNT(o.order_id) as count 
                    FROM orders o 
                    LEFT JOIN vehicles v ON o.assigned_vehicle_id = v.vehicle_id" 
                    . $sql_extra_joins 
                    . $sql_where . " 
                    GROUP BY clean_vehicle_name 
                    ORDER BY count DESC LIMIT 10";
                    
    $stmt = $conn->prepare($sql_vehicle);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $vehicle_types[] = ['label' => $row['clean_vehicle_name'], 'value' => (int)$row['count']];
    }
    $stmt->close();

    // --- 5. Driver Performance (พนักงานขับรถ Top 10) ---
    $driver_performance = [];
    $sql_driver = "SELECT COALESCE(s.staff_name, 'ไม่ระบุ') as staff_name, COUNT(o.order_id) as count 
                   FROM orders o 
                   LEFT JOIN staff s ON o.assigned_staff_id = s.staff_id" 
                   . $sql_extra_joins 
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
        // ถ้ามีการ join origin แล้ว (จาก filter) ต้องระวังเรื่อง alias
        // แต่ในที่นี้เราใช้ og_filter สำหรับ filter parameter
        // ส่วนการ group by location เราอาจจะใช้ og_filter เลยก็ได้ถ้ามัน join แล้ว
        // แต่เพื่อความชัวร์และง่าย เรา join ใหม่ใน alias ที่ต่างกันหรือใช้ alias เดิม?
        // ถ้าใช้ og_filter มันจะตัด row ที่ไม่ตรง filter ออกไปแล้ว ซึ่งถูกต้อง
        // ดังนั้นเราสามารถ select จาก og_filter ได้เลยถ้า join_origin เป็น true
        
        $sql_location = "SELECT CONCAT(
                            COALESCE(og_filter.province, ''), ' ',
                            COALESCE(og_filter.amphoe, ''), ' ',
                            COALESCE(og_filter.tambon, ''), ' ',
                            COALESCE(og_filter.moo, ''), ' ',
                            COALESCE(og_filter.mooban, '')
                        ) as full_location, 
                        COUNT(o.order_id) as count 
                        FROM orders o " 
                        . $sql_extra_joins 
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
                        . $sql_extra_joins
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
                     . $sql_extra_joins 
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

    // --- 10. Monthly Summary (Dynamic based on filter) ---
    $monthly_summary = [];
    $months = [];
    
    // Determine date range for the chart
    $chart_start = $filter_date_start ? $filter_date_start : date('Y-m-01', strtotime("-5 months"));
    $chart_end = $filter_date_end ? $filter_date_end : date('Y-m-d');

    // Generate month keys
    $start = new DateTime($chart_start);
    $start->modify('first day of this month');
    $end = new DateTime($chart_end);
    $end->modify('first day of this month');
    
    // Safety check: limit to 24 months to prevent massive loops if user selects huge range
    $interval = DateInterval::createFromDateString('1 month');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
    
    $loop_count = 0;
    foreach ($period as $dt) {
        if ($loop_count++ > 24) break; 
        $date_key = $dt->format("Y-m-01");
        $months[$date_key] = ['label' => $dt->format("m/Y"), 'value' => 0];
    }

    $sql_monthly = "SELECT DATE_FORMAT(o.order_date, '%Y-%m-01') as month, COUNT(*) as count 
                    FROM orders o" 
                    . $sql_extra_joins 
                    . $sql_where . " 
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
