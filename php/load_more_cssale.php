<?php
// php/load_more_cssale.php - Lazy Load สำหรับ CSSale options
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['status' => 'error', 'message' => 'ไม่พบข้อมูล'];

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 20;
$limit = 50; // โหลดครั้งละ 50 รายการ

// *** SUPER FAST: Query สำหรับ Lazy Loading ***
$sql = "SELECT cs.docno, cs.custname 
        FROM cssale cs
        WHERE cs.shipflag = 1 
        AND NOT EXISTS (
            SELECT 1 FROM orders o 
            WHERE o.cssale_docno = cs.docno 
            LIMIT 1
        )
        ORDER BY cs.docdate DESC, cs.docno DESC 
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();

    $options = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $options[] = [
                'value' => htmlspecialchars($row['docno']),
                'text' => htmlspecialchars($row['docno'] . ' - ' . $row['custname'])
            ];
        }
        
        $response = [
            'status' => 'success',
            'options' => $options,
            'loaded' => count($options),
            'next_offset' => $offset + count($options)
        ];
    }
    $stmt->close();
} else {
    $response['message'] = 'เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL';
}

$conn->close();
echo json_encode($response);
?>
