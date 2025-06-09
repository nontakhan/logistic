<?php
// php/cancel_order.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = array('status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง');

if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);

    // ตรวจสอบสถานะปัจจุบันของรายการ
    $check_sql = "SELECT status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $current_order = $check_result->fetch_assoc();
        $current_status = $current_order['status'];

        // ตรวจสอบเงื่อนไข: ต้องไม่ใช่ 'ส่งของแล้ว' หรือ 'ยกเลิก' อยู่แล้ว
        if ($current_status !== 'ส่งของแล้ว' && $current_status !== 'ยกเลิก') {
            $sql = "UPDATE orders SET status = 'ยกเลิก' WHERE order_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $order_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response['status'] = 'success';
                        $response['message'] = 'ยกเลิกรายการ Order ID: ' . $order_id . ' เรียบร้อยแล้ว';
                    } else {
                        $response['message'] = 'ไม่สามารถอัปเดตสถานะได้ หรือสถานะอาจไม่เปลี่ยนแปลง';
                    }
                } else {
                    error_log("SQL Execute Error in cancel_order.php: " . $stmt->error);
                    $response['message'] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
                }
                $stmt->close();
            } else {
                error_log("SQL Prepare Error in cancel_order.php: " . $conn->error);
                $response['message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL";
            }
        } else {
             $response['message'] = 'ไม่สามารถยกเลิกรายการนี้ได้ เนื่องจากสถานะปัจจุบันคือ "' . htmlspecialchars($current_status) . '"';
        }
    } else {
        $response['message'] = 'ไม่พบรายการ Order ID: ' . $order_id;
    }
    $check_stmt->close();
    $conn->close();
}

echo json_encode($response);
?>
