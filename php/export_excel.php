<?php
// php/export_excel.php

// 1. Includes and Session Check
require_once __DIR__ . '/check_session.php';
require_login([1, 2, 3, 4]); // อนุญาตให้ทุกสิทธิ์ที่ login แล้วสามารถ export ได้
require_once __DIR__ . '/db_connect.php';

// 2. Get filter parameters from GET request
$search_term = isset($_GET['search_term']) ? trim($conn->real_escape_string($_GET['search_term'])) : '';
$filter_status = isset($_GET['filter_status']) && is_array($_GET['filter_status']) ? $_GET['filter_status'] : [];
$filter_salesman = isset($_GET['filter_salesman']) ? $conn->real_escape_string($_GET['filter_salesman']) : '';
$filter_transport_origin = isset($_GET['filter_transport_origin']) ? $conn->real_escape_string($_GET['filter_transport_origin']) : '';
$filter_destination_text = isset($_GET['filter_destination_text']) ? trim($conn->real_escape_string($_GET['filter_destination_text'])) : '';
$filter_date_start = isset($_GET['filter_date_start']) && !empty($_GET['filter_date_start']) ? $conn->real_escape_string($_GET['filter_date_start']) : '';
$filter_date_end = isset($_GET['filter_date_end']) && !empty($_GET['filter_date_end']) ? $conn->real_escape_string($_GET['filter_date_end']) : '';

// 3. Build WHERE clause (same logic as all_orders.php)
$where_clauses = [];
$params = []; 
$param_types = ""; 

if (is_logged_in() && $_SESSION['role_level'] != 4 && !empty($_SESSION['assigned_transport_origin_id'])) {
    $where_clauses[] = "o.transport_origin_id = ?";
    $params[] = $_SESSION['assigned_transport_origin_id'];
    $param_types .= "i";
}

if (!empty($search_term)) {
    $where_clauses[] = "(o.cssale_docno LIKE ? OR cs.custname LIKE ?)";
    $search_like = "%" . $search_term . "%";
    array_push($params, $search_like, $search_like);
    $param_types .= "ss";
}

// กรองตามปลายทาง
if (!empty($filter_destination_text)) {
    $where_clauses[] = "CONCAT_WS(' ', org.moo, org.mooban, org.tambon, org.amphoe, org.province) LIKE ?";
    $dest_like = "%" . $filter_destination_text . "%";
    $params[] = $dest_like;
    $param_types .= "s";
}

if (!empty($filter_status)) {
    $placeholders = implode(',', array_fill(0, count($filter_status), '?'));
    $where_clauses[] = "o.status IN (" . $placeholders . ")";
    foreach ($filter_status as $status_value) {
        $params[] = $status_value;
    }
    $param_types .= str_repeat('s', count($filter_status));
}

// กรองตามต้นทางขนส่ง
if (!empty($filter_transport_origin)) {
    $where_clauses[] = "o.transport_origin_id = ?";
    $params[] = $filter_transport_origin;
    $param_types .= "i";
}
if (!empty($filter_salesman)) {
    $where_clauses[] = "cs.code = ?"; 
    $params[] = $filter_salesman; 
    $param_types .= "s";
}
if (!empty($filter_date_start)) {
    $where_clauses[] = "o.updated_at >= ?";
    $params[] = $filter_date_start . ' 00:00:00';
    $param_types .= "s";
}
if (!empty($filter_date_end)) {
    $where_clauses[] = "o.updated_at < ?";
    $params[] = date('Y-m-d H:i:s', strtotime($filter_date_end . ' +1 day'));
    $param_types .= "s";
}

$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// 4. Fetch ALL filtered data (without pagination)
// *** แก้ไข: นำการ JOIN กับ csuser ออก และดึงข้อมูลพนักงานจาก cssale โดยตรง ***
$sql_data = "SELECT 
                o.cssale_docno, 
                cs.custname,
                CONCAT(cs.code, ' - ', cs.lname) AS salesman_info,
                t_org.origin_name AS transport_origin_name,
                CONCAT_WS(', ', org.moo, org.mooban, org.tambon, org.amphoe, org.province) AS destination_address,
                v.vehicle_name AS vehicle_type,
                s.staff_name AS driver_name,
                cs.shipaddr, 
                o.status, 
                o.updated_at
            FROM orders o
            LEFT JOIN cssale cs ON o.cssale_docno = cs.docno
            LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id
            LEFT JOIN origin org ON o.customer_address_origin_id = org.id
            LEFT JOIN vehicles v ON o.assigned_vehicle_id = v.vehicle_id
            LEFT JOIN staff s ON o.assigned_staff_id = s.staff_id"
            . $sql_where . " ORDER BY o.updated_at DESC";

$stmt = $conn->prepare($sql_data);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// 5. Generate and output CSV file
$filename = "nr_logistics_export_" . date('Y-m-d') . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Add BOM to support Thai characters in Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Add header row (in Thai)
fputcsv($output, [
    'เลขที่บิล', 
    'ชื่อลูกค้า', 
    'พนักงานขาย', 
    'ต้นทางขนส่ง', 
    'ปลายทาง', 
    'ประเภทรถ',
    'พนักงานขับรถ',
    'สถานที่ส่ง', 
    'สถานะ', 
    'อัปเดตล่าสุด'
]);

// Add data rows
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['cssale_docno'],
            $row['custname'],
            $row['salesman_info'],
            $row['transport_origin_name'],
            $row['destination_address'] ?: '-',
            $row['vehicle_type'] ?: '-',
            $row['driver_name'] ?: '-',
            $row['shipaddr'],
            $row['status'],
            $row['updated_at']
        ]);
    }
}

fclose($output);
$stmt->close();
$conn->close();
exit();
?>
