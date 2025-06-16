<?php
// php/export_excel.php

// 1. Includes and Session Check
require_once __DIR__ . '/check_session.php';
require_login([1, 2, 3, 4]); // อนุญาตให้ทุกสิทธิ์ที่ login แล้วสามารถ export ได้
require_once __DIR__ . '/db_connect.php';

// 2. Get filter parameters from GET request
$search_term = isset($_GET['search_term']) ? trim($conn->real_escape_string($_GET['search_term'])) : '';
$filter_status = isset($_GET['filter_status']) ? $conn->real_escape_string($_GET['filter_status']) : '';
$filter_salesman = isset($_GET['filter_salesman']) ? $conn->real_escape_string($_GET['filter_salesman']) : '';
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
    $where_clauses[] = "(o.cssale_docno LIKE ? OR cs.custname LIKE ? OR cs.lname LIKE ?)";
    $search_like = "%" . $search_term . "%";
    array_push($params, $search_like, $search_like, $search_like);
    $param_types .= "sss";
}
if (!empty($filter_status)) {
    $where_clauses[] = "o.status = ?"; 
    $params[] = $filter_status; 
    $param_types .= "s"; 
}
if (!empty($filter_salesman)) {
    $where_clauses[] = "cs.code = ?"; 
    $params[] = $filter_salesman; 
    $param_types .= "s";
}
if (!empty($filter_date_start)) {
    $where_clauses[] = "DATE(o.updated_at) >= ?";
    $params[] = $filter_date_start;
    $param_types .= "s";
}
if (!empty($filter_date_end)) {
    $where_clauses[] = "DATE(o.updated_at) <= ?";
    $params[] = $filter_date_end;
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
                cs.shipaddr, 
                o.status, 
                o.updated_at
            FROM orders o
            LEFT JOIN cssale cs ON o.cssale_docno = cs.docno COLLATE utf8mb4_unicode_ci
            LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id"
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
