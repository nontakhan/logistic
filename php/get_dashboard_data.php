<?php
// php/get_dashboard_data.php
header('Content-Type: application/json');

// ใช้ __DIR__ เพื่อให้ path ถูกต้องเสมอ
require_once __DIR__ . '/check_session.php';
require_once __DIR__ . '/db_connect.php';

// ต้อง login ก่อนถึงจะดึงข้อมูลได้
if (!is_logged_in()) {
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// --- กรองข้อมูลตามสาขา (Logic เดิมจาก index.php) ---
$dashboard_where_clauses = [];
$dashboard_params = [];
$dashboard_param_types = "";
if ($_SESSION['role_level'] != 4 && !empty($_SESSION['assigned_transport_origin_id'])) {
    $dashboard_where_clauses[] = "transport_origin_id = ?";
    $dashboard_params[] = $_SESSION['assigned_transport_origin_id'];
    $dashboard_param_types .= "i";
}
$dashboard_sql_where = "";
if (!empty($dashboard_where_clauses)) {
    $dashboard_sql_where = " WHERE " . implode(" AND ", $dashboard_where_clauses);
}

// --- ดึงข้อมูลสำหรับ Big Numbers ---
$sql_counts = "SELECT 
                 SUM(CASE WHEN status = 'รอรับเรื่อง' THEN 1 ELSE 0 END) AS count_pending_ack,
                 SUM(CASE WHEN status = 'รับเรื่อง' THEN 1 ELSE 0 END) AS count_pending_assign,
                 SUM(CASE WHEN status = 'รอส่งของ' THEN 1 ELSE 0 END) AS count_pending_delivery,
                 SUM(CASE WHEN status = 'ส่งของแล้ว' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) AS count_delivered_today
               FROM orders" . $dashboard_sql_where;
$stmt_counts = $conn->prepare($sql_counts);
if (!empty($dashboard_params)) {
    $stmt_counts->bind_param($dashboard_param_types, ...$dashboard_params);
}
$stmt_counts->execute();
$result_counts = $stmt_counts->get_result();
$counts = ['pending_ack' => 0, 'pending_assign' => 0, 'pending_delivery' => 0, 'delivered_today' => 0];
if ($result_counts && $result_counts->num_rows > 0) {
    $counts_data = $result_counts->fetch_assoc();
    $counts['pending_ack'] = $counts_data['count_pending_ack'] ?: 0;
    $counts['pending_assign'] = $counts_data['count_pending_assign'] ?: 0;
    $counts['pending_delivery'] = $counts_data['count_pending_delivery'] ?: 0;
    $counts['delivered_today'] = $counts_data['count_delivered_today'] ?: 0;
}
$stmt_counts->close();

// --- ดึงข้อมูลสำหรับกราฟวงกลม: สัดส่วนสถานะ ---
$sql_status_distribution = "SELECT status, COUNT(order_id) as count FROM orders" . $dashboard_sql_where . " GROUP BY status";
$stmt_status = $conn->prepare($sql_status_distribution);
if (!empty($dashboard_params)) {
    $stmt_status->bind_param($dashboard_param_types, ...$dashboard_params);
}
$stmt_status->execute();
$result_status_distribution = $stmt_status->get_result();
$status_data_for_chart = [];
if ($result_status_distribution && $result_status_distribution->num_rows > 0) { while($row = $result_status_distribution->fetch_assoc()){ $status_data_for_chart[] = ['label' => htmlspecialchars($row['status']), 'value' => (int)$row['count']]; } }
$stmt_status->close();

// --- ดึงข้อมูลสำหรับกราฟแท่ง: จำนวนรายการที่สร้างใน 7 วันล่าสุด ---
$daily_orders_data = [];
$daily_where_clauses = $dashboard_where_clauses;
$daily_params = $dashboard_params;
$daily_param_types = $dashboard_param_types;
$daily_where_clauses[] = "order_date >= CURDATE() - INTERVAL 6 DAY AND order_date <= CURDATE()";
$sql_daily_where = " WHERE " . implode(" AND ", $daily_where_clauses);

$sql_daily_orders = "SELECT DATE(order_date) as order_day, COUNT(order_id) as count FROM orders" . $sql_daily_where . " GROUP BY DATE(order_date) ORDER BY DATE(order_date) ASC";
$stmt_daily = $conn->prepare($sql_daily_orders);
if (!empty($daily_params)) {
    $stmt_daily->bind_param($daily_param_types, ...$daily_params);
}
$stmt_daily->execute();
$result_daily_orders = $stmt_daily->get_result();
$temp_daily_data = [];
if ($result_daily_orders && $result_daily_orders->num_rows > 0) { while($row = $result_daily_orders->fetch_assoc()){ $temp_daily_data[$row['order_day']] = (int)$row['count']; } }
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $formatted_date = date('d/m', strtotime($date)); 
    $daily_orders_data[] = ['label' => $formatted_date, 'value' => isset($temp_daily_data[$date]) ? $temp_daily_data[$date] : 0];
}
$stmt_daily->close();

// --- รวมข้อมูลทั้งหมดเพื่อส่งกลับเป็น JSON ---
$response_data = [
    'status' => 'success',
    'big_numbers' => $counts,
    'status_chart_data' => $status_data_for_chart,
    'daily_chart_data' => $daily_orders_data
];

echo json_encode($response_data);
$conn->close();
?>
