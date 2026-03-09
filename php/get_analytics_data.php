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

if ($action == 'get_customers') {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $customers = [];
    $sql_c = "SELECT cs.custname, COUNT(o.order_id) as order_count 
              FROM cssale cs 
              INNER JOIN orders o ON o.cssale_docno = cs.docno 
              WHERE cs.custname != '' AND cs.custname IS NOT NULL";
    if (!empty($search)) {
        $search_like = '%' . $conn->real_escape_string($search) . '%';
        $sql_c .= " AND cs.custname LIKE '{$search_like}'";
    }
    $sql_c .= " GROUP BY cs.custname ORDER BY order_count DESC, cs.custname ASC LIMIT 50";
    $result_c = $conn->query($sql_c);
    while ($row = $result_c->fetch_assoc()) {
        $customers[] = ['custname' => $row['custname'], 'order_count' => (int)$row['order_count']];
    }
    echo json_encode(['status' => 'success', 'data' => $customers], JSON_UNESCAPED_UNICODE);
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
$filter_customer = isset($_GET['customer']) && !empty($_GET['customer']) ? trim($_GET['customer']) : '';
$top_n = isset($_GET['top_n']) && is_numeric($_GET['top_n']) ? (int)$_GET['top_n'] : 10;
if (!in_array($top_n, [10, 20, 30, 40, 50])) { $top_n = 10; }

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

// กรองตามลูกค้า
if (!empty($filter_customer)) {
    $where_conditions[] = "cs_filter.custname = ?";
    $params[] = $filter_customer;
    $param_types .= "s";
    $join_cssale_filter = true;
}

$sql_where = " WHERE " . implode(" AND ", $where_conditions);
$sql_extra_joins = "";
if ($join_origin) $sql_extra_joins .= " LEFT JOIN origin og_filter ON o.customer_address_origin_id = og_filter.id ";
if ($join_vehicles) $sql_extra_joins .= " LEFT JOIN vehicles v_filter ON o.assigned_vehicle_id = v_filter.vehicle_id ";
if (!empty($filter_customer)) $sql_extra_joins .= " LEFT JOIN cssale cs_filter ON o.cssale_docno = cs_filter.docno COLLATE utf8mb4_unicode_ci ";

try {
    // --- SINGLE QUERY: ดึงข้อมูลทั้งหมดครั้งเดียว แล้วประมวลผลใน PHP ---
    $sql_main = "SELECT 
                    o.order_id,
                    o.status,
                    o.order_date,
                    o.transport_origin_id,
                    o.assigned_staff_id,
                    o.assigned_vehicle_id,
                    COALESCE(t.origin_name, 'ไม่ระบุ') AS branch_name,
                    COALESCE(v.vehicle_name, 'ไม่ระบุ') AS vehicle_name,
                    COALESCE(s.staff_name, 'ไม่ระบุ') AS staff_name,
                    COALESCE(c.custname, 'ไม่ระบุ') AS custname,
                    COALESCE(og.province, '') AS province,
                    COALESCE(og.amphoe, '') AS amphoe,
                    COALESCE(og.tambon, '') AS tambon,
                    COALESCE(og.moo, '') AS moo,
                    COALESCE(og.mooban, '') AS mooban
                FROM orders o
                LEFT JOIN transport_origins t ON o.transport_origin_id = t.transport_origin_id
                LEFT JOIN vehicles v ON o.assigned_vehicle_id = v.vehicle_id
                LEFT JOIN staff s ON o.assigned_staff_id = s.staff_id
                LEFT JOIN cssale c ON o.cssale_docno = c.docno COLLATE utf8mb4_unicode_ci
                LEFT JOIN origin og ON o.customer_address_origin_id = og.id"
                . $sql_extra_joins
                . $sql_where;

    $stmt = $conn->prepare($sql_main);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    // --- ประมวลผลทั้งหมดใน PHP จากข้อมูลที่ดึงมาครั้งเดียว ---
    $order_stats = ['total' => 0, 'pending_ack' => 0, 'pending_assign' => 0,
                    'pending_delivery' => 0, 'delivered' => 0, 'cancelled' => 0];
    $status_map      = [];
    $branch_map      = [];
    $vehicle_map     = [];
    $staff_map       = [];
    $customer_map    = [];
    $province_map    = [];
    $amphoe_map      = [];
    $tambon_map      = [];
    $location_map    = [];
    $monthly_raw     = [];

    while ($row = $result->fetch_assoc()) {
        $status = $row['status'] ?? 'ไม่ระบุ';

        // 1. Stats
        $order_stats['total']++;
        if ($status === 'รอรับเรื่อง')  $order_stats['pending_ack']++;
        if ($status === 'รับเรื่อง')    $order_stats['pending_assign']++;
        if ($status === 'รอส่งของ')    $order_stats['pending_delivery']++;
        if ($status === 'ส่งของแล้ว') $order_stats['delivered']++;
        if ($status === 'ยกเลิก')      $order_stats['cancelled']++;

        // 2. Status distribution
        $status_map[$status] = ($status_map[$status] ?? 0) + 1;

        // 3. Branch
        $branch = $row['branch_name'];
        $branch_map[$branch] = ($branch_map[$branch] ?? 0) + 1;

        // 4. Vehicle
        $vname = trim($row['vehicle_name']) ?: 'ไม่ระบุ';
        $vehicle_map[$vname] = ($vehicle_map[$vname] ?? 0) + 1;

        // 5. Staff (driver) - skip unassigned
        if (!empty($row['assigned_staff_id'])) {
            $sname = $row['staff_name'];
            $staff_map[$sname] = ($staff_map[$sname] ?? 0) + 1;
        }

        // 6. Customer
        $cname = $row['custname'];
        if ($cname !== 'ไม่ระบุ') {
            $customer_map[$cname] = ($customer_map[$cname] ?? 0) + 1;
        }

        // 7-9. Province / Amphoe / Tambon / Location
        $prov = $row['province'];
        $amph = $row['amphoe'];
        $tamb = $row['tambon'];
        $moo  = $row['moo'];
        $moob = $row['mooban'];

        if (!empty($prov)) {
            $province_map[$prov] = ($province_map[$prov] ?? 0) + 1;
            if (!empty($amph)) {
                $amphoe_key = "$prov > $amph";
                $amphoe_map[$amphoe_key] = ($amphoe_map[$amphoe_key] ?? 0) + 1;
                if (!empty($tamb)) {
                    $tambon_key = "$prov > $amph > $tamb";
                    $tambon_map[$tambon_key] = ($tambon_map[$tambon_key] ?? 0) + 1;
                }
            }
            $loc = trim("$prov $amph $tamb $moo $moob");
            $loc = preg_replace('/\s+/', ' ', $loc);
            if (!empty($loc)) {
                $location_map[$loc] = ($location_map[$loc] ?? 0) + 1;
            }
        }

        // 10. Monthly
        $month_key = date('Y-m-01', strtotime($row['order_date']));
        $monthly_raw[$month_key] = ($monthly_raw[$month_key] ?? 0) + 1;
    }
    $stmt->close();

    // --- แปลงข้อมูลเป็น format ที่ frontend ต้องการ ---

    // Status distribution
    $status_distribution = [];
    arsort($status_map);
    foreach ($status_map as $k => $v) $status_distribution[] = ['label' => $k, 'value' => $v];

    // Branch rankings
    arsort($branch_map);
    $branch_rankings = [];
    foreach (array_slice($branch_map, 0, 10, true) as $k => $v) $branch_rankings[] = ['label' => $k, 'value' => $v];

    // Vehicle types
    arsort($vehicle_map);
    $vehicle_types = [];
    foreach (array_slice($vehicle_map, 0, 10, true) as $k => $v) $vehicle_types[] = ['label' => $k, 'value' => $v];

    // Driver performance
    arsort($staff_map);
    $driver_performance = [];
    foreach (array_slice($staff_map, 0, 10, true) as $k => $v) $driver_performance[] = ['name' => $k, 'count' => $v];

    // Top provinces
    arsort($province_map);
    $top_provinces = [];
    foreach (array_slice($province_map, 0, 10, true) as $k => $v) $top_provinces[] = ['label' => $k, 'value' => $v];

    // Top amphoes
    arsort($amphoe_map);
    $top_amphoes = [];
    foreach (array_slice($amphoe_map, 0, 10, true) as $k => $v) $top_amphoes[] = ['label' => $k, 'value' => $v];

    // Top tambons
    arsort($tambon_map);
    $top_tambons = [];
    foreach (array_slice($tambon_map, 0, 10, true) as $k => $v) $top_tambons[] = ['label' => $k, 'value' => $v];

    // Location rankings
    arsort($location_map);
    $location_rankings = [];
    foreach (array_slice($location_map, 0, 10, true) as $k => $v) $location_rankings[] = ['label' => $k, 'value' => $v];

    // Customer rankings
    arsort($customer_map);
    $customer_rankings = [];
    foreach (array_slice($customer_map, 0, $top_n, true) as $k => $v) $customer_rankings[] = ['label' => $k, 'value' => $v];

    // Monthly summary
    $monthly_summary = [];
    $months = [];
    $chart_start = $filter_date_start ? $filter_date_start : date('Y-m-01', strtotime("-5 months"));
    $chart_end   = $filter_date_end   ? $filter_date_end   : date('Y-m-d');
    $start = new DateTime($chart_start);
    $start->modify('first day of this month');
    $end = new DateTime($chart_end);
    $end->modify('first day of this month');
    $interval = DateInterval::createFromDateString('1 month');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
    $loop_count = 0;
    foreach ($period as $dt) {
        if ($loop_count++ > 24) break;
        $date_key = $dt->format("Y-m-01");
        $months[$date_key] = ['label' => $dt->format("m/Y"), 'value' => $monthly_raw[$date_key] ?? 0];
    }
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
            'top_provinces' => $top_provinces,
            'top_amphoes' => $top_amphoes,
            'top_tambons' => $top_tambons,
            'location_rankings' => $location_rankings,
            'customer_rankings' => $customer_rankings,
            'monthly_summary' => $monthly_summary
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

$conn->close();
