<?php
// php/delete_cssale.php
require_once 'check_session.php';
require_login([4]); // Admin only
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $docno = $_POST['docno'] ?? '';
    
    // Validate input
    if (empty($docno)) {
        throw new Exception('ข้อมูลไม่ครบถ้วน');
    }
    
    // Check if cssale record exists
    $check_sql = "SELECT docno, custname FROM cssale WHERE docno = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('s', $docno);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('ไม่พบข้อมูล CSSale');
    }
    
    // Check if this cssale has any related orders
    $order_check_sql = "SELECT COUNT(*) as count FROM orders WHERE cssale_docno = ?";
    $order_check_stmt = $conn->prepare($order_check_sql);
    $order_check_stmt->bind_param('s', $docno);
    $order_check_stmt->execute();
    $order_check_result = $order_check_stmt->get_result();
    $order_count = $order_check_result->fetch_assoc()['count'];
    
    if ($order_count > 0) {
        throw new Exception('ไม่สามารถลบข้อมูลนี้ได้ เนื่องจากมีออเดอร์ที่เกี่ยวข้องอยู่');
    }
    
    // Delete the cssale record
    $delete_sql = "DELETE FROM cssale WHERE docno = ?";
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('s', $docno);
    
    if ($delete_stmt->execute()) {
        $cssale_data = $check_result->fetch_assoc();
        
        // Log the deletion (optional - you might want to add a log table)
        $log_message = "Admin {$_SESSION['username']} deleted CSSale record: {$docno} (Customer: {$cssale_data['custname']})";
        
        echo json_encode([
            'status' => 'success',
            'message' => "ลบข้อมูล CSSale เลขที่ {$docno} เรียบร้อยแล้ว"
        ]);
    } else {
        throw new Exception('ไม่สามารถลบข้อมูลได้');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Close connections
if (isset($check_stmt)) $check_stmt->close();
if (isset($order_check_stmt)) $order_check_stmt->close();
if (isset($delete_stmt)) $delete_stmt->close();
$conn->close();
?>
