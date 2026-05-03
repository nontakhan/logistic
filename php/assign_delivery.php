<?php
header('Content-Type: application/json');

require_once 'check_session.php';
require_permission('orders.assign', [2, 3, 4]);
require_csrf_token();
require_once 'db_connect.php';

$response = ['status' => 'error', 'message' => 'ข้อมูลที่ส่งมาไม่ถูกต้อง'];

if (
    isset($_POST['order_id'], $_POST['assigned_staff_id'], $_POST['assigned_vehicle_id']) &&
    $_POST['order_id'] !== '' &&
    $_POST['assigned_staff_id'] !== '' &&
    $_POST['assigned_vehicle_id'] !== ''
) {
    $order_id = (int) $_POST['order_id'];
    $assigned_staff_id = (int) $_POST['assigned_staff_id'];
    $assigned_vehicle_id = (int) $_POST['assigned_vehicle_id'];

    $check_sql = "SELECT status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        $current_order = $check_result->fetch_assoc();

        if ($current_order['status'] === 'รับเรื่อง') {
            $staff_stmt = $conn->prepare("SELECT staff_id FROM staff WHERE staff_id = ? AND active = 1");
            $staff_stmt->bind_param("i", $assigned_staff_id);
            $staff_stmt->execute();
            $staff_exists = $staff_stmt->get_result()->num_rows > 0;
            $staff_stmt->close();

            if (!$staff_exists) {
                $response['message'] = 'ไม่พบพนักงานที่เลือก หรือพนักงานถูกปิดใช้งาน';
                $check_stmt->close();
                $conn->close();
                echo json_encode($response);
                exit;
            }

            $vehicle_stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ? AND active = 1");
            $vehicle_stmt->bind_param("i", $assigned_vehicle_id);
            $vehicle_stmt->execute();
            $vehicle_exists = $vehicle_stmt->get_result()->num_rows > 0;
            $vehicle_stmt->close();

            if (!$vehicle_exists) {
                $response['message'] = 'ไม่พบรถที่เลือก หรือรถถูกปิดใช้งาน';
                $check_stmt->close();
                $conn->close();
                echo json_encode($response);
                exit;
            }

            $stmt = $conn->prepare(
                "UPDATE orders
                 SET status = 'รอส่งของ', assigned_staff_id = ?, assigned_vehicle_id = ?, assigned_at = NOW()
                 WHERE order_id = ?"
            );

            if ($stmt) {
                $stmt->bind_param("iii", $assigned_staff_id, $assigned_vehicle_id, $order_id);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        $response['status'] = 'success';
                        $response['message'] = 'จัดสรรคนส่งและรถสำหรับ Order ID: ' . $order_id . ' เรียบร้อยแล้ว สถานะเป็น "รอส่งของ"';
                    } else {
                        $response['message'] = 'ไม่สามารถอัปเดตข้อมูลได้ หรือข้อมูลอาจไม่ได้เปลี่ยนแปลง';
                    }
                } else {
                    $response['message'] = 'เกิดข้อผิดพลาดในการอัปเดต: ' . $stmt->error;
                }

                $stmt->close();
            } else {
                $response['message'] = 'เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: ' . $conn->error;
            }
        } else {
            $response['message'] = 'รายการนี้ไม่ได้อยู่ในสถานะ "รับเรื่อง"';
        }
    } else {
        $response['message'] = 'ไม่พบรายการ Order ID: ' . $order_id;
    }

    $check_stmt->close();
    $conn->close();
} else {
    $response['message'] = 'ข้อมูลที่ส่งมาไม่ครบถ้วน (order_id, staff_id, vehicle_id)';
}

echo json_encode($response);
?>
