<?php
// php/get_order_details.php
header('Content-Type: application/json');

// ใช้ __DIR__ เพื่อให้ path ถูกต้องเสมอ
require_once __DIR__ . '/check_session.php';
require_permission('orders.view_details', [1, 2, 3, 4]); // ทุกสิทธิ์ที่ login สามารถดูรายละเอียดได้
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
                ss.phone as salesman_phone,
                t_org.origin_name,
                st.staff_name,
                st.staff_phone,
                v.vehicle_name,
                v.vehicle_plate,
                CONCAT_WS(' ', org.moo, org.mooban, org.tambon, org.amphoe, org.province) as full_origin_address
            FROM orders o
            LEFT JOIN cssale cs ON o.cssale_docno = cs.docno
            LEFT JOIN sales_staff ss ON ss.sales_code = cs.code
            LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id
            LEFT JOIN staff st ON o.assigned_staff_id = st.staff_id
            LEFT JOIN vehicles v ON o.assigned_vehicle_id = v.vehicle_id
            LEFT JOIN origin org ON o.customer_address_origin_id = org.id
            WHERE o.order_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            
            // เตรียมข้อมูลที่จำเป็นสำหรับ modal
            $data['order_date_formatted'] = !empty($data['order_date']) ? date("d/m/Y H:i", strtotime($data['order_date'])) : '-';
            $data['updated_at_formatted'] = !empty($data['updated_at']) ? date("d/m/Y H:i", strtotime($data['updated_at'])) : '-';
            
            // จัดเตรียมสถานะ badge
            $status_badge = 'badge-light-secondary';
            switch ($data['status']) {
                case 'รอรับเรื่อง': $status_badge = 'badge-light-danger'; break;
                case 'รับเรื่อง': $status_badge = 'badge-light-primary'; break;
                case 'รอส่งของ': $status_badge = 'badge-light-warning'; break;
                case 'ส่งของแล้ว': $status_badge = 'badge-light-success'; break;
                case 'ยกเลิก': $status_badge = 'badge-light-secondary'; break;
            }
            $data['status_badge'] = $status_badge;
            
            // เตรียมข้อมูลที่อยู่เต็ม
            $address_parts = [];
            if (!empty($data['full_origin_address'])) {
                $address_parts[] = $data['full_origin_address'];
            }
            if (!empty($data['shipaddr'])) {
                $address_parts[] = $data['shipaddr'];
            }
            $data['full_address'] = !empty($address_parts) ? implode(' | ', $address_parts) : '-';
            
            // เตรียมข้อมูลคนขับ
            $data['assigned_staff'] = $data['staff_name'] ?? '-';
            $data['assigned_staff_phone'] = $data['staff_phone'] ?? '';
            
            // เตรียมข้อมูลรถ
            $data['assigned_vehicle'] = $data['vehicle_name'] ?? '-';
            if (!empty($data['vehicle_plate'])) {
                $data['assigned_vehicle'] .= ' (' . $data['vehicle_plate'] . ')';
            }
            
            // เตรียมข้อมูลต้นทาง
            $data['transport_origin'] = $data['origin_name'] ?? '-';
            
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
