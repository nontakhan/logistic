<?php
// php/change_transport_origin.php
require_once 'check_session.php';
require_permission('orders.change_transport_origin', [2, 3, 4]);
require_csrf_token();
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $order_id = $_POST['order_id'] ?? '';
    $transport_origin_id = $_POST['transport_origin_id'] ?? '';
    
    // Validate input
    if (empty($order_id) || empty($transport_origin_id)) {
        throw new Exception('ข้อมูลไม่ครบถ้วน');
    }
    
    // Verify order exists and is in correct status
    $check_sql = "SELECT order_id, status FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('ไม่พบข้อมูลออเดอร์');
    }
    
    $order_data = $check_result->fetch_assoc();
    
    // Allow changing origin before delivery is assigned
    $allowed_statuses = ['รอรับเรื่อง', 'รับเรื่อง'];
    if (!in_array($order_data['status'], $allowed_statuses, true)) {
        throw new Exception('สามารถเปลี่ยนต้นทางได้เฉพาะออเดอร์ที่มีสถานะ "รอรับเรื่อง" หรือ "รับเรื่อง" เท่านั้น');
    }
    
    // Verify transport origin exists
    $origin_sql = "SELECT transport_origin_id, origin_name FROM transport_origins WHERE transport_origin_id = ?";
    $origin_stmt = $conn->prepare($origin_sql);
    $origin_stmt->bind_param('i', $transport_origin_id);
    $origin_stmt->execute();
    $origin_result = $origin_stmt->get_result();
    
    if ($origin_result->num_rows === 0) {
        throw new Exception('ไม่พบข้อมูลต้นทางขนส่ง');
    }
    
    // Update transport origin
    $update_sql = "UPDATE orders SET transport_origin_id = ?, updated_at = NOW() WHERE order_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('ii', $transport_origin_id, $order_id);
    
    if ($update_stmt->execute()) {
        $origin_data = $origin_result->fetch_assoc();
        
        echo json_encode([
            'status' => 'success',
            'message' => "เปลี่ยนต้นทางขนส่งเป็น '{$origin_data['origin_name']}' เรียบร้อยแล้ว",
            'new_origin_name' => $origin_data['origin_name']
        ]);
    } else {
        throw new Exception('ไม่สามารถอัพเดตข้อมูลได้');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Close connections
if (isset($check_stmt)) $check_stmt->close();
if (isset($origin_stmt)) $origin_stmt->close();
if (isset($update_stmt)) $update_stmt->close();
$conn->close();
?>
