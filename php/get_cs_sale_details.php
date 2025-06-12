<?php
// php/get_cs_sale_details.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['status' => 'error', 'message' => 'ไม่พบข้อมูล'];

if (isset($_GET['docno']) && !empty($_GET['docno'])) {
    $docno = trim($_GET['docno']);

    // แก้ไข: เพิ่ม LEFT JOIN กับตาราง csuser และเลือกคอลัมน์ docdate, csuser.name
    $sql = "SELECT 
                cs.custname, 
                cs.shipaddr,
                cs.docdate,
                cu.lname AS salesman_name
            FROM cssale cs
            LEFT JOIN csuser cu ON cs.salesman = cu.code
            WHERE cs.docno = ? 
            LIMIT 1";
            
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("s", $docno);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            
            // แก้ไข: เพิ่มข้อมูลที่ได้มาใหม่ลงใน response
            $response = [
                'status' => 'success',
                'custname' => $data['custname'],
                'shipaddr' => $data['shipaddr'],
                'docdate_formatted' => !empty($data['docdate']) ? date("d/m/Y", strtotime($data['docdate'])) : '-',
                'salesman_name' => $data['salesman_name']
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
