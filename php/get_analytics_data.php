<?php
header('Content-Type: application/json');

require_once __DIR__ . '/check_session.php';
require_once __DIR__ . '/db_connect.php';

if (!is_logged_in() || !has_permission('analytics.view', [1, 2, 3, 4])) {
    echo json_encode(['status' => 'error', 'message' => 'กรุณาเข้าสู่ระบบ'], JSON_UNESCAPED_UNICODE);
    exit;
}

function run_prepared_query(mysqli $conn, string $sql, string $param_types = '', array $params = []): mysqli_result|false
{
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('ไม่สามารถเตรียมคำสั่ง SQL ได้: ' . $conn->error);
    }

    if ($param_types !== '' && !empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return $result;
}

function map_label_value(mysqli_result|false $result): array
{
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = [
                'label' => $row['label'],
                'value' => (int) $row['value']
            ];
        }
    }
    return $rows;
}

$action = $_GET['action'] ?? 'get_data';

if ($action === 'get_amphoes') {
    $province = isset($_GET['province']) ? trim($_GET['province']) : '';
    $amphoes = [];

    if ($province !== '') {
        $sql = "SELECT DISTINCT amphoe
                FROM origin
                WHERE province = ? AND amphoe != ''
                ORDER BY amphoe";
        $result = run_prepared_query($conn, $sql, 's', [$province]);
        while ($row = $result->fetch_assoc()) {
            $amphoes[] = $row['amphoe'];
        }
    }

    echo json_encode(['status' => 'success', 'data' => $amphoes], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

if ($action === 'get_customers') {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $sql = "SELECT c.custname, COUNT(*) AS order_count
            FROM orders o
            INNER JOIN cssale c ON o.cssale_docno = c.docno
            WHERE c.custname IS NOT NULL AND c.custname != ''";
    $params = [];
    $param_types = '';

    if ($search !== '') {
        $sql .= " AND c.custname LIKE ?";
        $params[] = '%' . $search . '%';
        $param_types .= 's';
    }

    $sql .= " GROUP BY c.custname
              ORDER BY order_count DESC, c.custname ASC
              LIMIT 50";

    $result = run_prepared_query($conn, $sql, $param_types, $params);
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = [
            'custname' => $row['custname'],
            'order_count' => (int) $row['order_count']
        ];
    }

    echo json_encode(['status' => 'success', 'data' => $customers], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

if ($action === 'get_filter_options') {
    $vehicle_types = [];
    $vehicles_result = $conn->query("SELECT DISTINCT vehicle_name FROM vehicles WHERE vehicle_name != '' ORDER BY vehicle_name");
    while ($vehicles_result && ($row = $vehicles_result->fetch_assoc())) {
        $vehicle_types[] = $row['vehicle_name'];
    }

    $drivers = [];
    $drivers_result = $conn->query("SELECT staff_id, staff_name FROM staff WHERE staff_name != '' ORDER BY staff_name");
    while ($drivers_result && ($row = $drivers_result->fetch_assoc())) {
        $drivers[] = $row;
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'vehicle_types' => $vehicle_types,
            'drivers' => $drivers
        ]
    ], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

try {
    $where_conditions = ['1=1'];
    $params = [];
    $param_types = '';

    if (should_limit_to_assigned_origin()) {
        $where_conditions[] = 'o.transport_origin_id = ?';
        $params[] = (int) current_assigned_transport_origin_id();
        $param_types .= 'i';
    }

    $filter_date_start = isset($_GET['date_start']) && $_GET['date_start'] !== '' ? $_GET['date_start'] : '';
    $filter_date_end = isset($_GET['date_end']) && $_GET['date_end'] !== '' ? $_GET['date_end'] : '';
    $filter_transport_origin = isset($_GET['transport_origin']) && $_GET['transport_origin'] !== '' ? (int) $_GET['transport_origin'] : 0;
    $filter_province = isset($_GET['province']) ? trim($_GET['province']) : '';
    $filter_amphoe = isset($_GET['amphoe']) ? trim($_GET['amphoe']) : '';
    $filter_vehicle_type = isset($_GET['vehicle_type']) ? trim($_GET['vehicle_type']) : '';
    $filter_driver_id = isset($_GET['driver_id']) && $_GET['driver_id'] !== '' ? (int) $_GET['driver_id'] : 0;
    $filter_customer = isset($_GET['customer']) ? trim($_GET['customer']) : '';
    $top_n = isset($_GET['top_n']) && is_numeric($_GET['top_n']) ? (int) $_GET['top_n'] : 10;
    if (!in_array($top_n, [10, 20, 30, 40, 50], true)) {
        $top_n = 10;
    }

    if ($filter_date_start !== '') {
        $where_conditions[] = 'o.order_date >= ?';
        $params[] = $filter_date_start;
        $param_types .= 's';
    }

    if ($filter_date_end !== '') {
        $where_conditions[] = 'o.order_date < ?';
        $params[] = date('Y-m-d', strtotime($filter_date_end . ' +1 day'));
        $param_types .= 's';
    }

    if (user_can_filter_all_origins() && $filter_transport_origin > 0) {
        $where_conditions[] = 'o.transport_origin_id = ?';
        $params[] = $filter_transport_origin;
        $param_types .= 'i';
    }

    if ($filter_province !== '') {
        $where_conditions[] = 'og.province = ?';
        $params[] = $filter_province;
        $param_types .= 's';
    }

    if ($filter_amphoe !== '') {
        $where_conditions[] = 'og.amphoe = ?';
        $params[] = $filter_amphoe;
        $param_types .= 's';
    }

    if ($filter_vehicle_type !== '') {
        $where_conditions[] = 'v.vehicle_name = ?';
        $params[] = $filter_vehicle_type;
        $param_types .= 's';
    }

    if ($filter_driver_id > 0) {
        $where_conditions[] = 'o.assigned_staff_id = ?';
        $params[] = $filter_driver_id;
        $param_types .= 'i';
    }

    if ($filter_customer !== '') {
        $where_conditions[] = 'c.custname = ?';
        $params[] = $filter_customer;
        $param_types .= 's';
    }

    $sql_from = " FROM orders o
                  LEFT JOIN transport_origins t ON o.transport_origin_id = t.transport_origin_id
                  LEFT JOIN vehicles v ON o.assigned_vehicle_id = v.vehicle_id
                  LEFT JOIN staff s ON o.assigned_staff_id = s.staff_id
                  LEFT JOIN cssale c ON o.cssale_docno = c.docno
                  LEFT JOIN origin og ON o.customer_address_origin_id = og.id";
    $sql_where = ' WHERE ' . implode(' AND ', $where_conditions);

    $status_result = run_prepared_query(
        $conn,
        "SELECT o.status AS label, COUNT(*) AS value" . $sql_from . $sql_where . " GROUP BY o.status ORDER BY value DESC",
        $param_types,
        $params
    );

    $status_distribution = [];
    $order_stats = [
        'total' => 0,
        'pending_ack' => 0,
        'pending_assign' => 0,
        'pending_delivery' => 0,
        'delivered' => 0,
        'cancelled' => 0
    ];

    while ($status_result && ($row = $status_result->fetch_assoc())) {
        $count = (int) $row['value'];
        $status_distribution[] = ['label' => $row['label'], 'value' => $count];
        $order_stats['total'] += $count;

        switch ($row['label']) {
            case 'รอรับเรื่อง':
                $order_stats['pending_ack'] = $count;
                break;
            case 'รับเรื่อง':
                $order_stats['pending_assign'] = $count;
                break;
            case 'รอส่งของ':
                $order_stats['pending_delivery'] = $count;
                break;
            case 'ส่งของแล้ว':
                $order_stats['delivered'] = $count;
                break;
            case 'ยกเลิก':
                $order_stats['cancelled'] = $count;
                break;
        }
    }

    $branch_rankings = map_label_value(run_prepared_query(
        $conn,
        "SELECT COALESCE(t.origin_name, 'ไม่ระบุ') AS label, COUNT(*) AS value" . $sql_from . $sql_where . "
         GROUP BY t.origin_name
         ORDER BY value DESC, label ASC
         LIMIT 10",
        $param_types,
        $params
    ));

    $vehicle_types = map_label_value(run_prepared_query(
        $conn,
        "SELECT COALESCE(NULLIF(TRIM(v.vehicle_name), ''), 'ไม่ระบุ') AS label, COUNT(*) AS value" . $sql_from . $sql_where . "
         GROUP BY COALESCE(NULLIF(TRIM(v.vehicle_name), ''), 'ไม่ระบุ')
         ORDER BY value DESC, label ASC
         LIMIT 10",
        $param_types,
        $params
    ));

    $driver_result = run_prepared_query(
        $conn,
        "SELECT s.staff_name AS name, COUNT(*) AS count" . $sql_from . $sql_where . "
         AND o.assigned_staff_id IS NOT NULL
         GROUP BY s.staff_id, s.staff_name
         ORDER BY count DESC, name ASC
         LIMIT 10",
        $param_types,
        $params
    );
    $driver_performance = [];
    while ($driver_result && ($row = $driver_result->fetch_assoc())) {
        $driver_performance[] = [
            'name' => $row['name'],
            'count' => (int) $row['count']
        ];
    }

    $top_provinces = map_label_value(run_prepared_query(
        $conn,
        "SELECT og.province AS label, COUNT(*) AS value" . $sql_from . $sql_where . "
         AND og.province != ''
         GROUP BY og.province
         ORDER BY value DESC, label ASC
         LIMIT 10",
        $param_types,
        $params
    ));

    $top_amphoes = map_label_value(run_prepared_query(
        $conn,
        "SELECT CONCAT(og.province, ' > ', og.amphoe) AS label, COUNT(*) AS value" . $sql_from . $sql_where . "
         AND og.province != '' AND og.amphoe != ''
         GROUP BY og.province, og.amphoe
         ORDER BY value DESC, label ASC
         LIMIT 10",
        $param_types,
        $params
    ));

    $top_tambons = map_label_value(run_prepared_query(
        $conn,
        "SELECT CONCAT(og.province, ' > ', og.amphoe, ' > ', og.tambon) AS label, COUNT(*) AS value" . $sql_from . $sql_where . "
         AND og.province != '' AND og.amphoe != '' AND og.tambon != ''
         GROUP BY og.province, og.amphoe, og.tambon
         ORDER BY value DESC, label ASC
         LIMIT 10",
        $param_types,
        $params
    ));

    $location_rankings = map_label_value(run_prepared_query(
        $conn,
        "SELECT TRIM(REPLACE(CONCAT_WS(' ', og.province, og.amphoe, og.tambon, og.moo, og.mooban), '  ', ' ')) AS label, COUNT(*) AS value" . $sql_from . $sql_where . "
         AND (og.province != '' OR og.amphoe != '' OR og.tambon != '' OR og.moo != '' OR og.mooban != '')
         GROUP BY label
         ORDER BY value DESC, label ASC
         LIMIT 10",
        $param_types,
        $params
    ));

    $customer_sql = "SELECT c.custname AS label, COUNT(*) AS value" . $sql_from . $sql_where . "
                     AND c.custname IS NOT NULL AND c.custname != ''
                     GROUP BY c.custname
                     ORDER BY value DESC, label ASC
                     LIMIT ?";
    $customer_rankings = map_label_value(run_prepared_query(
        $conn,
        $customer_sql,
        $param_types . 'i',
        array_merge($params, [$top_n])
    ));

    $monthly_result = run_prepared_query(
        $conn,
        "SELECT DATE_FORMAT(o.order_date, '%Y-%m-01') AS month_key, COUNT(*) AS value" . $sql_from . $sql_where . "
         GROUP BY month_key
         ORDER BY month_key ASC",
        $param_types,
        $params
    );
    $monthly_raw = [];
    while ($monthly_result && ($row = $monthly_result->fetch_assoc())) {
        $monthly_raw[$row['month_key']] = (int) $row['value'];
    }

    $monthly_summary = [];
    $chart_start = $filter_date_start !== '' ? $filter_date_start : date('Y-m-01', strtotime('-5 months'));
    $chart_end = $filter_date_end !== '' ? $filter_date_end : date('Y-m-d');
    $start = new DateTime($chart_start);
    $start->modify('first day of this month');
    $end = new DateTime($chart_end);
    $end->modify('first day of this month');
    $interval = DateInterval::createFromDateString('1 month');
    $period = new DatePeriod($start, $interval, (clone $end)->modify('+1 month'));
    $loop_count = 0;

    foreach ($period as $dt) {
        if ($loop_count++ > 24) {
            break;
        }
        $key = $dt->format('Y-m-01');
        $monthly_summary[] = [
            'label' => $dt->format('m/Y'),
            'value' => $monthly_raw[$key] ?? 0
        ];
    }

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
    echo json_encode([
        'status' => 'error',
        'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

$conn->close();
?>
