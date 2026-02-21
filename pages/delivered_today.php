<?php
// pages/delivered_today.php
require_once '../php/check_session.php';
require_login([2, 3, 4]);
require_once '../php/db_connect.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

// Get search parameters
$search_docno = isset($_GET['search_docno']) ? trim($_GET['search_docno']) : '';
$search_custname = isset($_GET['search_custname']) ? trim($_GET['search_custname']) : '';

// Build WHERE clause
$where_clauses = [];
$params = [];
$param_types = "";

// Filter by delivered today
$where_clauses[] = "DATE(o.updated_at) = CURDATE()";
$where_clauses[] = "o.status = 'ส่งของแล้ว'";

if (!empty($search_docno)) {
    $where_clauses[] = "o.cssale_docno LIKE ?";
    $search_like = "%" . $search_docno . "%";
    $params[] = $search_like;
    $param_types .= "s";
}

if (!empty($search_custname)) {
    $where_clauses[] = "cs.custname LIKE ?";
    $custname_like = "%" . $search_custname . "%";
    $params[] = $custname_like;
    $param_types .= "s";
}

$sql_where = " WHERE " . implode(" AND ", $where_clauses);

$sql = "SELECT o.order_id, o.cssale_docno, cs.custname, 
        CONCAT_WS(', ', ori.moo, ori.mooban, ori.tambon, ori.amphoe, ori.province) AS customer_full_address, 
        cs.shipaddr AS cssale_shipaddr, o.product_details, o.priority, o.order_date, 
        t_org.origin_name AS transport_origin_name, 
        s.staff_name AS assigned_staff_name, 
        CONCAT(v.vehicle_name, ' (', v.vehicle_plate, ')') AS assigned_vehicle_info,
        o.updated_at as delivery_time
        FROM orders o 
        LEFT JOIN cssale cs ON o.cssale_docno = cs.docno COLLATE utf8mb4_unicode_ci 
        LEFT JOIN origin ori ON o.customer_address_origin_id = ori.id 
        LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id 
        LEFT JOIN staff s ON o.assigned_staff_id = s.staff_id 
        LEFT JOIN vehicles v ON o.assigned_vehicle_id = v.vehicle_id" . 
        $sql_where . " 
        ORDER BY o.updated_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการส่งของแล้ว (วันนี้)</title>
    
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style> 
        .action-buttons button, .action-buttons a { margin: 0 2px; } 
        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
        }
        .delivery-time {
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">รายการส่งของแล้ว (วันนี้)</h2>
            <div>
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>กลับหน้าหลัก</a>
                <button class="btn btn-info btn-sm" onclick="location.reload();"><i class="fas fa-sync-alt"></i> รีเฟรช</button>
            </div>
        </div>
        
        <div class="p-3 border rounded bg-light mb-4">
            <form method="GET" class="mb-0">
                <div class="form-row">
                    <div class="col-md-4">
                        <label for="search_docno">ค้นหาเลขที่บิล</label>
                        <input type="text" name="search_docno" id="search_docno" class="form-control" value="<?php echo htmlspecialchars($search_docno); ?>" placeholder="เลขที่บิล">
                    </div>
                    <div class="col-md-4">
                        <label for="search_custname">ค้นหาชื่อลูกค้า</label>
                        <input type="text" name="search_custname" id="search_custname" class="form-control" value="<?php echo htmlspecialchars($search_custname); ?>" placeholder="ชื่อลูกค้า">
                    </div>
                    <div class="col-md-4">
                        <label>&nbsp;</label><br>
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> ค้นหา</button>
                        <a href="<?php echo BASE_URL; ?>pages/delivered_today.php" class="btn btn-outline-secondary ml-2"><i class="fas fa-redo"></i> รีเซ็ต</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            <strong>รายการที่ส่งของแล้วในวันนี้</strong> 
            (<?php echo $result->num_rows; ?> รายการ)
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead class="thead-light sticky-top">
                    <tr>
                        <th>ID ติดตาม</th>
                        <th>เลขที่บิล</th>
                        <th>ชื่อลูกค้า</th>
                        <th>ที่อยู่ลูกค้า</th>
                        <th>หมายเหตุ</th>
                        <th>ต้นทางขนส่ง</th>
                        <th>คนส่งของ</th>
                        <th>รถที่ใช้</th>
                        <th>วันที่สั่ง</th>
                        <th>เวลาส่ง</th>
                        <th>ความเร่งด่วน</th>
                    </tr>
                </thead>
                <tbody id="orders-table-body">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['cssale_docno']); ?></td>
                                <td><?php echo htmlspecialchars($row['custname']); ?></td>
                                <td><?php echo htmlspecialchars(!empty($row['customer_full_address']) ? $row['customer_full_address'] : '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['cssale_shipaddr']); ?></td>
                                <td><?php echo htmlspecialchars($row['transport_origin_name']); ?></td>
                                <td><?php echo htmlspecialchars(!empty($row['assigned_staff_name']) ? $row['assigned_staff_name'] : '-'); ?></td>
                                <td><?php echo htmlspecialchars(!empty($row['assigned_vehicle_info']) ? $row['assigned_vehicle_info'] : '-'); ?></td>
                                <td><?php echo date("d/m/Y", strtotime($row['order_date'])); ?></td>
                                <td>
                                    <div class="delivery-time">
                                        <?php echo date("H:i", strtotime($row['delivery_time'])); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($row['priority']); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="11" class="text-center">ไม่พบรายการที่ส่งของแล้วในวันนี้</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        $(document).ready(function() {
            // Auto-refresh every 30 seconds
            setInterval(function() {
                location.reload();
            }, 30000);
        });
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
