<?php
// php/add_new_bill.php
header('Content-Type: application/json');

require_once 'check_session.php';
// สิทธิ์ที่ต้องการสำหรับหน้านี้: ทุกคนที่ login แล้วสามารถใช้ได้
require_login([1, 2, 4]);
require_once 'db_connect.php';

$response = ['status' => 'error', 'message' => 'มีบางอย่างผิดพลาด', 'errors' => []];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. รับและตรวจสอบข้อมูล
    $docno = isset($_POST['new_docno']) ? trim($_POST['new_docno']) : null;
    $docdate = isset($_POST['new_docdate']) ? trim($_POST['new_docdate']) : null;
    $custname = isset($_POST['new_custname']) ? trim($_POST['new_custname']) : null;
    $shipaddr = isset($_POST['new_shipaddr']) ? trim($_POST['new_shipaddr']) : null;
    $salesman_code = isset($_POST['new_salesman_code']) ? trim($_POST['new_salesman_code']) : null;
    $shipflag = 1; // กำหนดค่าเป็น 1 เสมอ

    // 2. Validation
    if (empty($docno)) $response['errors']['new_docno'] = "กรุณากรอกเลขที่บิล";
    if (empty($docdate)) $response['errors']['new_docdate'] = "กรุณาเลือกวันที่";
    if (empty($custname)) $response['errors']['new_custname'] = "กรุณากรอกชื่อลูกค้า";
    if (empty($shipaddr)) $response['errors']['new_shipaddr'] = "กรุณากรอกที่อยู่";
    if (empty($salesman_code)) $response['errors']['new_salesman_code'] = "กรุณาเลือกพนักงานขาย";

    // ตรวจสอบว่า docno ซ้ำหรือไม่
    $check_sql = "SELECT docno FROM cssale WHERE docno = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $docno);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $response['errors']['new_docno'] = "เลขที่บิลนี้มีอยู่แล้วในระบบ";
    }
    $check_stmt->close();

    if (!empty($response['errors'])) {
        $response['message'] = "ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบ";
        echo json_encode($response);
        exit;
    }

    // 3. ดึงชื่อพนักงาน (lname) จาก code ที่เลือก
    $lname = '';
    $lname_sql = "SELECT lname FROM cssale WHERE code = ? LIMIT 1";
    $lname_stmt = $conn->prepare($lname_sql);
    $lname_stmt->bind_param("s", $salesman_code);
    $lname_stmt->execute();
    $lname_result = $lname_stmt->get_result();
    if($lname_row = $lname_result->fetch_assoc()) {
        $lname = $lname_row['lname'];
    }
    $lname_stmt->close();
    
    // 4. บันทึกข้อมูลลงฐานข้อมูล
    $sql = "INSERT INTO cssale (docno, docdate, custname, shipaddr, shipflag, code, lname, salesman) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NULL)"; // salesman (id) ไม่ได้ใช้งาน ใส่เป็น NULL
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssiss", $docno, $docdate, $custname, $shipaddr, $shipflag, $salesman_code, $lname);

    if ($stmt->execute()) {
        $response['status'] = 'success';
        $response['message'] = 'เพิ่มบิลใหม่สำเร็จ!';
    } else {
        error_log("SQL Execute Error in add_new_bill.php: " . $stmt->error);
        $response['message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
    }
    $stmt->close();

} else {
    $response['message'] = "Invalid request method.";
}

$conn->close();
echo json_encode($response);
?>
