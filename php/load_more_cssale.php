<?php
// php/load_more_cssale.php - Lazy Load for CSSale options
header('Content-Type: application/json');
require_once 'check_session.php';
require_permission('orders.create', [1, 2, 3, 4]);
require_once 'db_connect.php';

$response = ['status' => 'error', 'message' => 'ไม่พบข้อมูล'];

$limit = 50;
$last_docdate = isset($_GET['last_docdate']) ? trim($_GET['last_docdate']) : '';
$last_docno = isset($_GET['last_docno']) ? trim($_GET['last_docno']) : '';

$sql = "SELECT cs.docno, cs.custname, cs.docdate
        FROM cssale cs
        WHERE cs.shipflag = 1
        AND NOT EXISTS (
            SELECT 1
            FROM orders o
            WHERE o.cssale_docno = cs.docno
            LIMIT 1
        )";

$params = [];
$param_types = '';

if ($last_docdate !== '' && $last_docno !== '') {
    $sql .= " AND (cs.docdate < ? OR (cs.docdate = ? AND cs.docno < ?))";
    $params[] = $last_docdate;
    $params[] = $last_docdate;
    $params[] = $last_docno;
    $param_types .= 'sss';
}

$sql .= " ORDER BY cs.docdate DESC, cs.docno DESC LIMIT ?";
$params[] = $limit;
$param_types .= 'i';

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $options = [];
    $next_cursor = null;

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $options[] = [
                'value' => htmlspecialchars($row['docno']),
                'text' => htmlspecialchars($row['docno'] . ' - ' . $row['custname'])
            ];
            $next_cursor = [
                'docdate' => $row['docdate'],
                'docno' => $row['docno']
            ];
        }

        $response = [
            'status' => 'success',
            'options' => $options,
            'loaded' => count($options),
            'next_cursor' => $next_cursor,
            'has_more' => count($options) === $limit
        ];
    }

    $stmt->close();
} else {
    $response['message'] = 'เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL';
}

$conn->close();
echo json_encode($response);
?>
