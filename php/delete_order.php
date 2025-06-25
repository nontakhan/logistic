<?php
// php/delete_order.php
header('Content-Type: application/json');

// ใช้ __DIR__ เพื่อให้ path ถูกต้องเสมอ
require_once __DIR__ . '/check_session.php';
// ตรวจสอบสิทธิ์ที่ต้องการ (ระดับ 2 และ Admin)
require_login([2, 4]); 

require_once __DIR__ . '/db_connect.php';

$response = ['status' => 'error', 'message' => 'ข้อมูลไม่ถูกต้อง'];

if (isset($_POST['order_id']) && !empty($_POST['order_id'])) {
    $order_id = intval($_POST['order_id']);

    // ตรวจสอบว่ารายการที่จะลบมีสถานะเป็น 'ยกเลิก' จริง
    $check_sql = "SELECT status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        $order = $result->fetch_assoc();
        if ($order['status'] === 'ยกเลิก') {
            // เตรียมคำสั่ง DELETE
            $sql = "DELETE FROM orders WHERE order_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $order_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response['status'] = 'success';
                        $response['message'] = 'ลบรายการ ID: ' . $order_id . ' เรียบร้อยแล้ว';
                    } else {
                        $response['message'] = 'ไม่สามารถลบรายการได้ หรือรายการไม่มีอยู่แล้ว';
                    }
                } else {
                    error_log("SQL Execute Error in delete_order.php: " . $stmt->error);
                    $response['message'] = "เกิดข้อผิดพลาดในการลบข้อมูล";
                }
                $stmt->close();
            } else {
                error_log("SQL Prepare Error in delete_order.php: " . $conn->error);
                $response['message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL";
            }
        } else {
            $response['message'] = 'ไม่สามารถลบรายการได้เนื่องจากสถานะไม่ใช่ "ยกเลิก"';
        }
    } else {
        $response['message'] = 'ไม่พบรายการ Order ID: ' . $order_id;
    }
    $check_stmt->close();
    
}

$conn->close();
echo json_encode($response);
?>
