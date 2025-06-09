<?php
// php/acknowledge_order.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = array('status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง');

if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']); // Ensure it's an integer

    // ตรวจสอบว่า order_id นี้มีสถานะเป็น "รอรับเรื่อง" จริงหรือไม่ (optional check)
    $check_sql = "SELECT status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $current_order = $check_result->fetch_assoc();
        if ($current_order['status'] == 'รอรับเรื่อง') {
            // อัปเดตสถานะเป็น "รับเรื่อง"
            $sql = "UPDATE orders SET status = 'รับเรื่อง' WHERE order_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $order_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response['status'] = 'success';
                        $response['message'] = 'รับเรื่องรายการ ID: ' . $order_id . ' เรียบร้อยแล้ว';
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
             $response['message'] = 'รายการนี้ไม่ได้อยู่ในสถานะ "รอรับเรื่อง" (สถานะปัจจุบัน: ' . $current_order['status'] . ')';
        }
    } else {
        $response['message'] = 'ไม่พบรายการ ID: ' . $order_id;
    }
    $check_stmt->close();
    $conn->close();
}

echo json_encode($response);
?>
