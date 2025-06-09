<?php
// php/get_origin_prices.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['status' => 'error', 'message' => 'ไม่พบข้อมูล'];

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $origin_id = intval($_GET['id']);

    $sql = "SELECT * FROM origin WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $origin_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            
            // แก้ไข: สร้างที่อยู่เต็มโดยไม่เติมคำนำหน้าซ้ำ
            $full_address = implode(' ', array_filter([
                $data['tambon'],
                $data['amphoe'],
                $data['province']
            ]));

            $response = [
                'status' => 'success',
                'full_address' => $full_address,
                'distance' => $data['distance'],
                'price_pickup' => $data['price_pickup'],
                'price_6wheel' => $data['price_6wheel'],
                'price_10wheel' => $data['price_10wheel'],
                'remark' => $data['remark']
            ];
        }
        $stmt->close();
    } else {
        $response['message'] = 'เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL';
    }
} else {
    $response['message'] = 'ไม่ได้ระบุ ID ของสถานที่';
}

$conn->close();
echo json_encode($response);
?>
