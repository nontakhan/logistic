<?php
// php/get_cs_sale_details.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['status' => 'error', 'message' => 'ไม่พบข้อมูล'];

if (isset($_GET['docno']) && !empty($_GET['docno'])) {
    $docno = trim($_GET['docno']);

    $sql = "SELECT custname, shipaddr FROM cssale WHERE docno = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $docno);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $response = [
                'status' => 'success',
                'custname' => $data['custname'],
                'shipaddr' => $data['shipaddr']
            ];
        }
        $stmt->close();
    } else {
        $response['message'] = 'เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL';
    }
} else {
    $response['message'] = 'ไม่ได้ระบุเลขที่บิล';
}

$conn->close();
echo json_encode($response);
?>
