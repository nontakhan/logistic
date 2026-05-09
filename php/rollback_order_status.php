<?php
header('Content-Type: application/json');

require_once __DIR__ . '/check_session.php';
require_permission('orders.rollback_status', [3, 4]);
require_csrf_token();
require_once 'db_connect.php';

$response = array('status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง');

if (!isset($_POST['order_id']) || $_POST['order_id'] === '') {
    echo json_encode($response);
    exit;
}

$order_id = (int) $_POST['order_id'];

$check_sql = 'SELECT status, transport_origin_id FROM orders WHERE order_id = ? LIMIT 1';
$check_stmt = $conn->prepare($check_sql);
if (!$check_stmt) {
    $response['message'] = 'เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL';
    echo json_encode($response);
    exit;
}

$check_stmt->bind_param('i', $order_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$order = $check_result ? $check_result->fetch_assoc() : null;
$check_stmt->close();

if (!$order) {
    $response['message'] = 'ไม่พบรายการ Order ID: ' . $order_id;
    $conn->close();
    echo json_encode($response);
    exit;
}

if (should_limit_to_assigned_origin() && (int) $order['transport_origin_id'] !== (int) current_assigned_transport_origin_id()) {
    $response['message'] = 'คุณไม่มีสิทธิ์ย้อนสถานะรายการนี้';
    $conn->close();
    echo json_encode($response);
    exit;
}

$current_status = $order['status'];
$target_status = null;
$sql = null;

if ($current_status === 'รับเรื่อง') {
    $target_status = 'รอรับเรื่อง';
    $sql = "UPDATE orders
            SET status = 'รอรับเรื่อง',
                acknowledged_at = NULL,
                assigned_staff_id = NULL,
                assigned_vehicle_id = NULL,
                assigned_at = NULL,
                delivered_at = NULL
            WHERE order_id = ?";
} elseif ($current_status === 'รอส่งของ') {
    $target_status = 'รับเรื่อง';
    $sql = "UPDATE orders
            SET status = 'รับเรื่อง',
                assigned_staff_id = NULL,
                assigned_vehicle_id = NULL,
                assigned_at = NULL,
                delivered_at = NULL
            WHERE order_id = ?";
} elseif ($current_status === 'ส่งของแล้ว') {
    $target_status = 'รอส่งของ';
    $sql = "UPDATE orders
            SET status = 'รอส่งของ',
                delivered_at = NULL
            WHERE order_id = ?";
}

if ($sql === null) {
    $response['message'] = 'ไม่สามารถย้อนสถานะจาก "' . htmlspecialchars($current_status) . '" ได้';
    $conn->close();
    echo json_encode($response);
    exit;
}

$stmt = $conn->prepare($sql);
if (!$stmt) {
    $response['message'] = 'เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL';
    $conn->close();
    echo json_encode($response);
    exit;
}

$stmt->bind_param('i', $order_id);
if ($stmt->execute() && $stmt->affected_rows > 0) {
    $response['status'] = 'success';
    $response['message'] = 'ย้อนสถานะ Order ID: ' . $order_id . ' จาก "' . $current_status . '" กลับเป็น "' . $target_status . '" เรียบร้อยแล้ว';
} else {
    $response['message'] = 'ไม่สามารถย้อนสถานะได้ หรือสถานะอาจไม่เปลี่ยนแปลง';
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>
