<?php
// php/submit_order.php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = array('status' => 'error', 'message' => 'มีบางอย่างผิดพลาด', 'errors' => []);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // รับและทำความสะอาดข้อมูลเบื้องต้น
    $cssale_docno = isset($_POST['cssale_docno']) ? trim($_POST['cssale_docno']) : null;
    $customer_address_origin_id = isset($_POST['customer_address_origin_id']) ? filter_var(trim($_POST['customer_address_origin_id']), FILTER_VALIDATE_INT) : null;
    $transport_origin_id = isset($_POST['transport_origin_id']) ? filter_var(trim($_POST['transport_origin_id']), FILTER_VALIDATE_INT) : null;
    $product_details = isset($_POST['product_details']) ? trim($_POST['product_details']) : ''; // รับค่ามาเป็น string ว่างถ้าไม่มี
    $priority = isset($_POST['priority']) ? trim($_POST['priority']) : null;
    
    $order_date = date('Y-m-d');

    // --- Server-side validation ---
    if (empty($cssale_docno)) {
        $response['errors']['cssale_docno'] = "กรุณาเลือกเลขที่บิล";
    }
    if ($customer_address_origin_id === false || $customer_address_origin_id === null) {
        $response['errors']['customer_address_origin_id'] = "กรุณาเลือกที่อยู่ลูกค้าให้ถูกต้อง";
    }
    if ($transport_origin_id === false || $transport_origin_id === null) {
        $response['errors']['transport_origin_id'] = "กรุณาเลือกต้นทางขนส่งให้ถูกต้อง";
    }
    // แก้ไข: ลบการตรวจสอบช่องหมายเหตุ (product_details) ออกจากส่วนนี้
    // if (empty($product_details)) {
    //     $response['errors']['product_details'] = "กรุณากรอกหมายเหตุ";
    // }
    if (empty($priority)) {
        $response['errors']['priority'] = "กรุณาเลือกความเร่งด่วน";
    } else if (!in_array($priority, ['ปกติ', 'ด่วน', 'ด่วนที่สุด'])) {
        $response['errors']['priority'] = "ค่าความเร่งด่วนไม่ถูกต้อง";
    }

    if (!empty($response['errors'])) {
        $response['message'] = "ข้อมูลไม่ถูกต้อง กรุณาตรวจสอบ";
        echo json_encode($response);
        exit;
    }

    // --- เตรียม SQL Statement ---
    $sql = "INSERT INTO orders (cssale_docno, customer_address_origin_id, transport_origin_id, product_details, priority, order_date, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'รอรับเรื่อง')";
    
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("SQL Prepare Error in submit_order.php: " . $conn->error . " | SQL: " . $sql);
        $response['message'] = "เกิดข้อผิดพลาดในการเตรียมข้อมูลเพื่อบันทึก กรุณาลองใหม่อีกครั้ง";
        echo json_encode($response);
        $conn->close();
        exit;
    }
    
    $stmt->bind_param("siisss", 
        $cssale_docno, 
        $customer_address_origin_id, 
        $transport_origin_id, 
        $product_details, 
        $priority, 
        $order_date
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['status'] = 'success';
            $response['message'] = 'บันทึกรายการจัดส่งใหม่สำเร็จ!';
        } else {
            $response['message'] = "ไม่สามารถบันทึกข้อมูลได้ (No rows affected)";
             error_log("SQL Execute Warning in submit_order.php: No rows affected for INSERT. Data: " . json_encode($_POST));
        }
    } else {
        error_log("SQL Execute Error in submit_order.php: " . $stmt->error . " | Data: " . json_encode($_POST));
        $response['message'] = "เกิดข้อผิดพลาดในการบันทึกข้อมูลลงฐานข้อมูล กรุณาลองใหม่อีกครั้ง";
    }
    $stmt->close();
    $conn->close();

} else {
    $response['message'] = "Invalid request method.";
}

echo json_encode($response);
?>
