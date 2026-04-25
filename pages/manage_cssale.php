<?php
require_once '../php/check_session.php';
require_permission('cssale.manage', [4]);
require_once '../php/db_connect.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

$filter_start = isset($_GET['filter_start']) ? $_GET['filter_start'] : date('Y-m-d', strtotime('-2 months'));
$filter_end = isset($_GET['filter_end']) ? $_GET['filter_end'] : date('Y-m-d');
$is_ajax_request = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($is_ajax_request) {
    header('Content-Type: application/json');

    $items_per_page = 20;
    $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    $params = [$filter_start, $filter_end];
    $param_types = 'ss';
    $sql_where = " WHERE cs.docdate >= ? AND cs.docdate <= ?
                   AND NOT EXISTS (
                       SELECT 1
                       FROM orders o
                       WHERE o.cssale_docno = cs.docno
                   )";

    $sql_data = "SELECT
                    cs.docno,
                    cs.docdate,
                    cs.custname,
                    cs.shipaddr,
                    cs.shipflag,
                    cs.lname,
                    cs.salesman
                 FROM cssale cs"
                 . $sql_where .
                 " ORDER BY cs.docdate DESC, cs.docno DESC
                   LIMIT ? OFFSET ?";

    $stmt_data = $conn->prepare($sql_data);
    $data_params = array_merge($params, [$items_per_page, $offset]);
    $stmt_data->bind_param($param_types . 'ii', ...$data_params);
    $stmt_data->execute();
    $result = $stmt_data->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['docdate_formatted'] = !empty($row['docdate']) ? date('d/m/Y', strtotime($row['docdate'])) : '-';
        $rows[] = $row;
    }
    $stmt_data->close();

    $sql_count = "SELECT COUNT(*) AS total FROM cssale cs" . $sql_where;
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param($param_types, ...$params);
    $stmt_count->execute();
    $total_items = (int) (($stmt_count->get_result()->fetch_assoc()['total'] ?? 0));
    $stmt_count->close();

    echo json_encode([
        'status' => 'success',
        'rows' => $rows,
        'total_items' => $total_items,
        'total_pages' => (int) ceil($total_items / $items_per_page),
        'current_page' => $current_page
    ]);
    $conn->close();
    exit;
}

$conn->close();
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
        .action-buttons button { margin: 0 2px; }
        .filter-card { box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .table-responsive { background: #fff; border-radius: .5rem; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">จัดการข้อมูล CSSale ที่ไม่ได้ใช้</h2>
            <div>
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>กลับหน้าหลัก</a>
                <button class="btn btn-info btn-sm" id="refreshBtn"><i class="fas fa-sync-alt"></i> รีเฟรช</button>
            </div>
        </div>

        <div class="p-3 border rounded bg-light mb-4 filter-card">
            <form id="filterForm" class="mb-0">
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
                        <button type="button" id="resetBtn" class="btn btn-outline-secondary ml-2"><i class="fas fa-redo"></i> รีเซ็ต</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="alert alert-info d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-info-circle"></i>
                <strong>ข้อมูลนี้แสดงเฉพาะรายการจากตาราง CSSale ที่ยังไม่มีในตาราง Orders</strong>
                <span id="items-count-info" class="ml-2">กำลังโหลดข้อมูล...</span>
            </div>
            <button class="btn btn-danger btn-sm" id="deleteAllBtn" style="display:none;">
                <i class="fas fa-trash-alt"></i> ลบทั้งหมด
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped mb-0">
                <thead class="thead-light sticky-top">
                    <tr>
                        <th>เลขที่บิล</th>
                        <th>วันที่บิล</th>
                        <th>ชื่อลูกค้า</th>
                        <th>ที่อยู่จัดส่ง</th>
                        <th>สถานะจัดส่ง</th>
                        <th>รหัสพนักงาน</th>
                        <th>รหัสผู้แนะนำ</th>
                        <th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody id="cssale-table-body">
                    <tr><td colspan="8" class="text-center py-4">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-3">
            <nav id="paginationContainer"></nav>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php echo csrf_ajax_script(); ?>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        let currentPage = 1;
        const defaultStart = <?php echo json_encode(date('Y-m-d', strtotime('-2 months'))); ?>;
        const defaultEnd = <?php echo json_encode(date('Y-m-d')); ?>;

        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }

        function escapeAttr(value) {
            return String(value || '').replace(/"/g, '&quot;');
        }

        function fetchData(page = 1) {
            currentPage = page;
            $('#cssale-table-body').html('<tr><td colspan="8" class="text-center py-4">กำลังโหลดข้อมูล...</td></tr>');

            $.ajax({
                url: 'manage_cssale.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    page: page,
                    filter_start: $('#filter_start').val(),
                    filter_end: $('#filter_end').val()
                },
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(response) {
                    if (response.status !== 'success') {
                        $('#cssale-table-body').html('<tr><td colspan="8" class="text-center text-danger py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>');
                        return;
                    }

                    renderTable(response.rows || []);
                    renderPagination(response.total_pages || 0, response.current_page || 1);
                    $('#items-count-info').text(`(${response.total_items} รายการ)`);
                    $('#deleteAllBtn').toggle((response.total_items || 0) > 0).text(`ลบทั้งหมด (${response.total_items} รายการ)`);
                },
                error: function() {
                    $('#cssale-table-body').html('<tr><td colspan="8" class="text-center text-danger py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>');
                }
            });
        }

        function renderTable(rows) {
            const tbody = $('#cssale-table-body');
            tbody.empty();

            if (!rows.length) {
                tbody.html('<tr><td colspan="8" class="text-center py-4">ไม่พบข้อมูล CSSale ที่ไม่ได้ใช้ในช่วงวันที่ที่เลือก</td></tr>');
                return;
            }

            rows.forEach(function(row) {
                tbody.append(`
                    <tr id="cssale-row-${row.docno}">
                        <td>${escapeHtml(row.docno || '-')}</td>
                        <td>${escapeHtml(row.docdate_formatted || '-')}</td>
                        <td>${escapeHtml(row.custname || '-')}</td>
                        <td>${escapeHtml(row.shipaddr || '-')}</td>
                        <td>${row.shipflag == 1 ? '<span class="badge badge-success">จัดส่งแล้ว</span>' : '<span class="badge badge-warning">ยังไม่ได้จัดส่ง</span>'}</td>
                        <td>${escapeHtml(row.salesman || '-')}</td>
                        <td>${escapeHtml(row.lname || '-')}</td>
                        <td class="action-buttons">
                            <button class="btn btn-danger btn-sm delete-cssale-btn" data-docno="${escapeAttr(row.docno || '')}" data-custname="${escapeAttr(row.custname || '')}">
                                <i class="fas fa-trash"></i> ลบ
                            </button>
                        </td>
                    </tr>
                `);
            });
        }

        function renderPagination(totalPages, activePage) {
            const container = $('#paginationContainer');
            if (totalPages <= 1) {
                container.html('');
                return;
            }

            let html = '<ul class="pagination">';
            html += `<li class="page-item ${activePage <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${activePage - 1}">ก่อนหน้า</a></li>`;
            const startPage = Math.max(1, activePage - 2);
            const endPage = Math.min(totalPages, activePage + 2);

            if (startPage > 1) {
                html += '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
                if (startPage > 2) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            for (let i = startPage; i <= endPage; i++) {
                html += `<li class="page-item ${i === activePage ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
            }
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                html += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
            }
            html += `<li class="page-item ${activePage >= totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${activePage + 1}">ถัดไป</a></li>`;
            html += '</ul>';
            container.html(html);
        }

        $(document).ready(function() {
            fetchData(1);

            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                fetchData(1);
            });

            $('#resetBtn').on('click', function() {
                $('#filter_start').val(defaultStart);
                $('#filter_end').val(defaultEnd);
                fetchData(1);
            });

            $('#refreshBtn').on('click', function() {
                fetchData(currentPage);
            });

            $('#paginationContainer').on('click', 'a.page-link', function(e) {
                e.preventDefault();
                const page = Number($(this).data('page'));
                if (page) fetchData(page);
            });

            $('#cssale-table-body').on('click', '.delete-cssale-btn', function() {
                const docno = $(this).data('docno');
                const custname = $(this).data('custname');

                Swal.fire({
                    title: 'ยืนยันการลบข้อมูล',
                    html: `คุณต้องการลบข้อมูล CSSale นี้ใช่หรือไม่?<br><br>
                           <strong>เลขที่บิล:</strong> ${docno}<br>
                           <strong>ชื่อลูกค้า:</strong> ${custname}<br><br>
                           <span class="text-danger">การกระทำนี้ไม่สามารถย้อนกลับได้!</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ลบเลย!',
                    cancelButtonText: 'ไม่'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    Swal.fire({ title: 'กำลังลบข้อมูล...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    $.ajax({
                        url: '<?php echo BASE_URL; ?>php/delete_cssale.php',
                        type: 'POST',
                        dataType: 'json',
                        data: { docno: docno },
                        success: function(response) {
                            Swal.close();
                            if (response.status === 'success') {
                                Swal.fire({ icon: 'success', title: 'ลบข้อมูลสำเร็จ!', text: response.message, timer: 2000, showConfirmButton: false })
                                    .then(() => fetchData(currentPage));
                            } else {
                                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด!', text: response.message });
                            }
                        },
                        error: function() {
                            Swal.close();
                            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ', text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้' });
                        }
                    });
                });
            });

            $('#deleteAllBtn').on('click', function() {
                Swal.fire({
                    title: 'ยืนยันการลบข้อมูลทั้งหมด',
                    html: `คุณต้องการลบข้อมูล CSSale ทั้งหมดในช่วงวันที่ที่เลือกใช่หรือไม่?<br><br>
                           <span class="text-danger">การกระทำนี้จะลบเฉพาะข้อมูลที่ยังไม่มีใน Orders และไม่สามารถย้อนกลับได้!</span>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ลบทั้งหมด!',
                    cancelButtonText: 'ไม่',
                    reverseButtons: true
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    Swal.fire({
                        title: 'กำลังลบข้อมูลทั้งหมด...',
                        html: 'กรุณารอสักครู่ กำลังลบข้อมูล CSSale ทั้งหมด...',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });

                    $.ajax({
                        url: '<?php echo BASE_URL; ?>php/delete_all_cssale.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            filter_start: $('#filter_start').val(),
                            filter_end: $('#filter_end').val()
                        },
                        success: function(response) {
                            Swal.close();
                            if (response.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'ลบข้อมูลทั้งหมดสำเร็จ!',
                                    html: `ลบข้อมูล CSSale ทั้งหมด <strong>${response.deleted_count}</strong> รายการเรียบร้อยแล้ว`,
                                    timer: 3000,
                                    showConfirmButton: false
                                }).then(() => fetchData(1));
                            } else {
                                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด!', text: response.message });
                            }
                        },
                        error: function() {
                            Swal.close();
                            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ', text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้' });
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
