<?php
// php/update_driver.php
require_once 'check_session.php';
require_login([2, 3, 4]); // Staff, Manager, Admin can update driver
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $order_id = $_POST['order_id'] ?? '';
    $staff_id = $_POST['staff_id'] ?? '';
    $vehicle_id = $_POST['vehicle_id'] ?? '';
    
    // Validate input
    if (empty($order_id) || empty($staff_id) || empty($vehicle_id)) {
        throw new Exception('ข้อมูลไม่ครบถ้วน');
    }
    
    // Verify order exists and is in correct status
    $check_sql = "SELECT order_id, cssale_docno, status, assigned_staff_id, assigned_vehicle_id FROM orders WHERE order_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param('i', $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        throw new Exception('ไม่พบข้อมูลออเดอร์');
    }
    
    $order_data = $check_result->fetch_assoc();
    
    // Only allow changing driver for orders that are ready for delivery
    if ($order_data['status'] !== 'รอส่งของ') {
        throw new Exception('สามารถเปลี่ยนคนขับได้เฉพาะออเดอร์ที่มีสถานะ "รอส่งของ" เท่านั้น');
    }
    
    // Verify staff exists
    $staff_sql = "SELECT staff_id, staff_name FROM staff WHERE staff_id = ?";
    $staff_stmt = $conn->prepare($staff_sql);
    $staff_stmt->bind_param('i', $staff_id);
    $staff_stmt->execute();
    $staff_result = $staff_stmt->get_result();
    
    if ($staff_result->num_rows === 0) {
        throw new Exception('ไม่พบข้อมูลพนักงาน');
    }
    
    // Verify vehicle exists
    $vehicle_sql = "SELECT vehicle_id, vehicle_name, vehicle_plate FROM vehicles WHERE vehicle_id = ?";
    $vehicle_stmt = $conn->prepare($vehicle_sql);
    $vehicle_stmt->bind_param('i', $vehicle_id);
    $vehicle_stmt->execute();
    $vehicle_result = $vehicle_stmt->get_result();
    
    if ($vehicle_result->num_rows === 0) {
        throw new Exception('ไม่พบข้อมูลรถ');
    }
    
    // Update driver and vehicle assignment
    $update_sql = "UPDATE orders SET assigned_staff_id = ?, assigned_vehicle_id = ?, updated_at = NOW() WHERE order_id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param('iii', $staff_id, $vehicle_id, $order_id);
    
    if ($update_stmt->execute()) {
        $staff_data = $staff_result->fetch_assoc();
        $vehicle_data = $vehicle_result->fetch_assoc();
        $vehicle_info = $vehicle_data['vehicle_name'] . ' (' . $vehicle_data['vehicle_plate'] . ')';
        
        // Log the change (optional - you might want to add a log table)
        $old_staff_id = $order_data['assigned_staff_id'];
        $old_vehicle_id = $order_data['assigned_vehicle_id'];
        $log_message = "User {$_SESSION['username']} updated driver for order {$order_id} ({$order_data['cssale_docno']}) from staff_id {$old_staff_id}/vehicle_id {$old_vehicle_id} to staff_id {$staff_id}/vehicle_id {$vehicle_id}";
        
        echo json_encode([
            'status' => 'success',
            'message' => "เปลี่ยนคนขับเป็น '{$staff_data['staff_name']}' และรถเป็น '{$vehicle_info}' เรียบร้อยแล้ว",
            'new_staff_name' => $staff_data['staff_name'],
            'new_vehicle_info' => $vehicle_info
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
if (isset($staff_stmt)) $staff_stmt->close();
if (isset($vehicle_stmt)) $vehicle_stmt->close();
if (isset($update_stmt)) $update_stmt->close();
$conn->close();
?>
