<?php
// pages/manage_cssale.php
require_once '../php/check_session.php';
require_login([4]); // Admin only
require_once '../php/db_connect.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

// Get filter parameters
$filter_start = isset($_GET['filter_start']) ? $_GET['filter_start'] : date('Y-m-d', strtotime('-2 months'));
$filter_end = isset($_GET['filter_end']) ? $_GET['filter_end'] : date('Y-m-d');

$where_clauses = [];
$params = [];
$param_types = "";

// Date filter
$where_clauses[] = "cs.docdate >= ? AND cs.docdate <= ?";
$params[] = $filter_start;
$params[] = $filter_end;
$param_types .= "ss";

// Only show cssale records that don't exist in orders
$sql_where = " WHERE " . implode(" AND ", $where_clauses);

$sql = "SELECT cs.docno, cs.docdate, cs.custname, cs.shipaddr, cs.shipflag, cs.empname, cs.salename 
        FROM cssale cs 
        LEFT JOIN orders o ON cs.docno = o.cssale_docno 
        " . $sql_where . " 
        AND o.cssale_docno IS NULL 
        ORDER BY cs.docdate DESC, cs.docno DESC";

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
    <title>จัดการข้อมูล CSSale ที่ไม่ได้ใช้</title>
    
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
        .filter-card {
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">จัดการข้อมูล CSSale ที่ไม่ได้ใช้</h2>
            <div>
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>กลับหน้าหลัก</a>
                <button class="btn btn-info btn-sm" onclick="location.reload();"><i class="fas fa-sync-alt"></i> รีเฟรช</button>
            </div>
        </div>
        
        <div class="p-3 border rounded bg-light mb-4 filter-card">
            <form method="GET" class="mb-0">
                <div class="form-row align-items-end">
                    <div class="col-md-3">
                        <label for="filter_start">วันที่เริ่มต้น</label>
                        <input type="date" name="filter_start" id="filter_start" class="form-control" value="<?php echo htmlspecialchars($filter_start); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="filter_end">วันที่สิ้นสุด</label>
                        <input type="date" name="filter_end" id="filter_end" class="form-control" value="<?php echo htmlspecialchars($filter_end); ?>">
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> กรองข้อมูล</button>
                        <a href="<?php echo BASE_URL; ?>pages/manage_cssale.php" class="btn btn-outline-secondary ml-2"><i class="fas fa-redo"></i> รีเซ็ต</a>
                    </div>
                </div>
            </form>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> 
            <strong>ข้อมูลนี้แสดงเฉพาะรายการจากตาราง CSSale ที่ยังไม่มีในตาราง Orders</strong> 
            (<?php echo $result->num_rows; ?> รายการ)
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead class="thead-light sticky-top">
                    <tr>
                        <th>เลขที่บิล</th>
                        <th>วันที่บิล</th>
                        <th>ชื่อลูกค้า</th>
                        <th>ที่อยู่จัดส่ง</th>
                        <th>สถานะจัดส่ง</th>
                        <th>พนักงานขาย</th>
                        <th>ผู้แนะนำ</th>
                        <th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody id="cssale-table-body">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr id="cssale-row-<?php echo htmlspecialchars($row['docno']); ?>">
                                <td><?php echo htmlspecialchars($row['docno']); ?></td>
                                <td><?php echo date("d/m/Y", strtotime($row['docdate'])); ?></td>
                                <td><?php echo htmlspecialchars($row['custname']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($row['shipaddr'])); ?></td>
                                <td>
                                    <?php if ($row['shipflag'] == 1): ?>
                                        <span class="badge badge-success">จัดส่งแล้ว</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">ยังไม่จัดส่ง</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['empname'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['salename'] ?: '-'); ?></td>
                                <td class="action-buttons">
                                    <button class="btn btn-danger btn-sm delete-cssale-btn" 
                                            data-docno="<?php echo htmlspecialchars($row['docno']); ?>" 
                                            data-custname="<?php echo htmlspecialchars($row['custname']); ?>">
                                        <i class="fas fa-trash"></i> ลบ
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">ไม่พบข้อมูล CSSale ที่ไม่ได้ใช้ในช่วงวันที่ที่เลือก</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            // Delete CSSale record
            $('#cssale-table-body').on('click', '.delete-cssale-btn', function() {
                const docno = $(this).data('docno');
                const custname = $(this).data('custname');
                
                Swal.fire({
                    title: 'ยืนยันการลบข้อมูล',
                    html: `คุณต้องการลบข้อมูล CSSale นี้ใช่หรือไม่?<br><br>
                           <strong>เลขที่บิล:</strong> ${docno}<br>
                           <strong>ชื่อลูกค้า:</strong> ${custname}<br><br>
                           <span class="text-danger">⚠️ การกระทำนี้ไม่สามารถย้อนกลับได้!</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ลบเลย!',
                    cancelButtonText: 'ไม่'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'กำลังลบข้อมูล...',
                            allowOutsideClick: false,
                            didOpen: () => Swal.showLoading()
                        });
                        
                        $.ajax({
                            url: '<?php echo BASE_URL; ?>php/delete_cssale.php',
                            type: 'POST',
                            data: {
                                docno: docno
                            },
                            dataType: 'json',
                            success: function(response) {
                                Swal.close();
                                if (response.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'ลบข้อมูลสำเร็จ!',
                                        text: response.message,
                                        timer: 2000,
                                        showConfirmButton: false
                                    }).then(() => {
                                        // Remove row from table
                                        $('#cssale-row-' + docno).fadeOut(500, function() {
                                            $(this).remove();
                                            
                                            // Update count in alert
                                            const currentCount = $('#cssale-table-body tr').length;
                                            $('.alert-info').html(
                                                '<i class="fas fa-info-circle"></i> ' +
                                                '<strong>ข้อมูลนี้แสดงเฉพาะรายการจากตาราง CSSale ที่ยังไม่มีในตาราง Orders</strong> ' +
                                                `(${currentCount} รายการ)`
                                            );
                                            
                                            // Show no data message if empty
                                            if (currentCount === 0) {
                                                $('#cssale-table-body').html(
                                                    '<tr><td colspan="8" class="text-center">ไม่พบข้อมูล CSSale ที่ไม่ได้ใช้ในช่วงวันที่ที่เลือก</td></tr>'
                                                );
                                            }
                                        });
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'เกิดข้อผิดพลาด!',
                                        text: response.message
                                    });
                                }
                            },
                            error: function() {
                                Swal.close();
                                Swal.fire({
                                    icon: 'error',
                                    title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ',
                                    text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้'
                                });
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
