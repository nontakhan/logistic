<?php
// php/confirm_delivery.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = array('status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง');

if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);

    // ตรวจสอบสถานะปัจจุบัน (optional)
    $check_sql = "SELECT status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $current_order = $check_result->fetch_assoc();
        if ($current_order['status'] == 'รอส่งของ') {
            $sql = "UPDATE orders SET status = 'ส่งของแล้ว' WHERE order_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $order_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response['status'] = 'success';
                        $response['message'] = 'ยืนยันการส่งของสำหรับ Order ID: ' . $order_id . ' เรียบร้อยแล้ว';
                    } else {
                        $response['message'] = 'ไม่สามารถอัปเดตสถานะได้ หรือสถานะอาจไม่เปลี่ยนแปลง';
                    }
                } else {
                    $response['message'] = "เกิดข้อผิดพลาดในการอัปเดต: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $response['message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            }
        } else {
             $response['message'] = 'รายการนี้ไม่ได้อยู่ในสถานะ "รอส่งของ" (สถานะปัจจุบัน: ' . htmlspecialchars($current_order['status']) . ')';
        }
    } else {
        $response['message'] = 'ไม่พบรายการ Order ID: ' . $order_id;
    }
    $check_stmt->close();
    $conn->close();
}

echo json_encode($response);
?>
