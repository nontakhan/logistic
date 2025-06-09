<?php
// php/assign_delivery.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = array('status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง');

if (isset($_POST['order_id']) && !empty($_POST['order_id']) &&
    isset($_POST['assigned_staff_id']) && !empty($_POST['assigned_staff_id']) &&
    isset($_POST['assigned_vehicle_id']) && !empty($_POST['assigned_vehicle_id'])) {

    $order_id = intval($_POST['order_id']);
    $assigned_staff_id = intval($_POST['assigned_staff_id']);
    $assigned_vehicle_id = intval($_POST['assigned_vehicle_id']);

    // ตรวจสอบว่า order_id นี้มีสถานะเป็น "รับเรื่อง" จริงหรือไม่ (optional)
    $check_sql = "SELECT status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $current_order = $check_result->fetch_assoc();
        if ($current_order['status'] == 'รับเรื่อง') {
            $sql = "UPDATE orders SET status = 'รอส่งของ', assigned_staff_id = ?, assigned_vehicle_id = ? WHERE order_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("iii", $assigned_staff_id, $assigned_vehicle_id, $order_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response['status'] = 'success';
                        $response['message'] = 'จัดสรรคนส่งและรถสำหรับ Order ID: ' . $order_id . ' เรียบร้อยแล้ว สถานะเป็น "รอส่งของ"';
                    } else {
                        $response['message'] = 'ไม่สามารถอัปเดตข้อมูลได้ หรือข้อมูลอาจไม่เปลี่ยนแปลง';
                    }
                } else {
                    $response['message'] = "เกิดข้อผิดพลาดในการอัปเดต: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $response['message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
            }
        } else {
             $response['message'] = 'รายการนี้ไม่ได้อยู่ในสถานะ "รับเรื่อง" (สถานะปัจจุบัน: ' . htmlspecialchars($current_order['status']) . ')';
        }
    } else {
        $response['message'] = 'ไม่พบรายการ Order ID: ' . $order_id;
    }
    $check_stmt->close();
    $conn->close();
} else {
    $response['message'] = "ข้อมูลที่ส่งมาไม่ครบถ้วน (order_id, staff_id, vehicle_id)";
}

echo json_encode($response);
?>
