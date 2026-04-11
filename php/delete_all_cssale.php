<?php
// php/delete_all_cssale.php
require_once 'check_session.php';
require_login([4]); // Admin only
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Get POST data
    $filter_start = $_POST['filter_start'] ?? '';
    $filter_end = $_POST['filter_end'] ?? '';
    
    // Validate input
    if (empty($filter_start) || empty($filter_end)) {
        throw new Exception('ข้อมูลวันที่ไม่ครบถ้วน');
    }
    
    // Start transaction for safety
    $conn->begin_transaction();
    
    // First, get all CSSale records that will be deleted (for logging and count)
    $get_records_sql = "SELECT cs.docno, cs.custname
                      FROM cssale cs
                      WHERE cs.docdate >= ? AND cs.docdate <= ?
                      AND NOT EXISTS (
                          SELECT 1
                          FROM orders o
                          WHERE o.cssale_docno = cs.docno
                      )";
    
    $get_stmt = $conn->prepare($get_records_sql);
    $get_stmt->bind_param('ss', $filter_start, $filter_end);
    $get_stmt->execute();
    $get_result = $get_stmt->get_result();
    
    $records_to_delete = [];
    while ($row = $get_result->fetch_assoc()) {
        $records_to_delete[] = $row;
    }
    
    if (empty($records_to_delete)) {
        throw new Exception('ไม่พบข้อมูล CSSale ที่สามารถลบได้ในช่วงวันที่ที่เลือก');
    }
    
    // Delete all CSSale records that don't have related orders
    $delete_sql = "DELETE FROM cssale
                  WHERE docdate >= ? AND docdate <= ?
                  AND NOT EXISTS (
                      SELECT 1
                      FROM orders o
                      WHERE o.cssale_docno = cssale.docno
                  )";
    
    $delete_stmt = $conn->prepare($delete_sql);
    $delete_stmt->bind_param('ss', $filter_start, $filter_end);
    
    if ($delete_stmt->execute()) {
        $deleted_count = $delete_stmt->affected_rows;
        
        // Log the deletion (optional - you might want to add a log table)
        $log_message = "Admin {$_SESSION['username']} deleted {$deleted_count} CSSale records from {$filter_start} to {$filter_end}";
        
        // Commit transaction
        $conn->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => "ลบข้อมูล CSSale ทั้งหมด {$deleted_count} รายการเรียบร้อยแล้ว",
            'deleted_count' => $deleted_count,
            'date_range' => "จากวันที่ {$filter_start} ถึง {$filter_end}"
        ]);
    } else {
        throw new Exception('ไม่สามารถลบข้อมูลได้');
    }
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

// Close connections
if (isset($get_stmt)) $get_stmt->close();
if (isset($delete_stmt)) $delete_stmt->close();
$conn->close();
?>
