<?php
// php/get_order_details.php
header('Content-Type: application/json');

// ใช้ __DIR__ เพื่อให้ path ถูกต้องเสมอ
require_once __DIR__ . '/check_session.php';
require_login([1, 2, 3, 4]); // ทุกสิทธิ์ที่ login สามารถดูรายละเอียดได้
require_once __DIR__ . '/db_connect.php';

$response = ['status' => 'error', 'message' => 'ไม่พบข้อมูล'];

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $order_id = intval($_GET['id']);

    // ดึงข้อมูลทั้งหมดที่เกี่ยวข้องกับ order นี้
    $sql = "SELECT
                o.*,
                cs.custname,
                cs.shipaddr,
                cs.code as salesman_code,
                cs.lname as salesman_name,
                t_org.origin_name,
                st.staff_name,
                st.staff_phone,
                v.vehicle_name,
                v.vehicle_plate
            FROM orders o
            LEFT JOIN cssale cs ON o.cssale_docno = cs.docno COLLATE utf8mb4_unicode_ci
            LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id
            LEFT JOIN staff st ON o.assigned_staff_id = st.staff_id
            LEFT JOIN vehicles v ON o.assigned_vehicle_id = v.vehicle_id
            WHERE o.order_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $response = [
                'status' => 'success',
                'data' => $data
            ];
        }
        $stmt->close();
    } else {
        $response['message'] = "เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: " . $conn->error;
    }
} else {
    $response['message'] = 'ไม่ได้ระบุ ID ของรายการ';
}

$conn->close();
echo json_encode($response);
?>
