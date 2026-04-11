<?php
require_once '../php/check_session.php';
require_login([2, 3, 4]);
require_once '../php/db_connect.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

$search_docno = isset($_GET['search_docno']) ? trim($_GET['search_docno']) : '';
$search_custname = isset($_GET['search_custname']) ? trim($_GET['search_custname']) : '';
$is_ajax_request = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($is_ajax_request) {
    header('Content-Type: application/json');

    $items_per_page = 20;
    $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    $where_clauses = [
        "o.updated_at >= CURDATE()",
        "o.updated_at < CURDATE() + INTERVAL 1 DAY",
        "o.status = 'ส่งของแล้ว'"
    ];
    $params = [];
    $param_types = '';

    if ($search_docno !== '') {
        $where_clauses[] = 'o.cssale_docno LIKE ?';
        $params[] = '%' . $search_docno . '%';
        $param_types .= 's';
    }

    if ($search_custname !== '') {
        $where_clauses[] = 'cs.custname LIKE ?';
        $params[] = '%' . $search_custname . '%';
        $param_types .= 's';
    }

    $sql_from = " FROM orders o
                  LEFT JOIN cssale cs ON o.cssale_docno = cs.docno
                  LEFT JOIN origin ori ON o.customer_address_origin_id = ori.id
                  LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id
                  LEFT JOIN staff s ON o.assigned_staff_id = s.staff_id
                  LEFT JOIN vehicles v ON o.assigned_vehicle_id = v.vehicle_id";
    $sql_where = ' WHERE ' . implode(' AND ', $where_clauses);

    $sql_data = "SELECT
                    o.order_id,
                    o.cssale_docno,
                    cs.custname,
                    CONCAT_WS(', ', ori.moo, ori.mooban, ori.tambon, ori.amphoe, ori.province) AS customer_full_address,
                    cs.shipaddr AS cssale_shipaddr,
                    o.product_details,
                    o.priority,
                    o.order_date,
                    t_org.origin_name AS transport_origin_name,
                    s.staff_name AS assigned_staff_name,
                    CONCAT(v.vehicle_name, ' (', v.vehicle_plate, ')') AS assigned_vehicle_info,
                    o.updated_at AS delivery_time"
                . $sql_from . $sql_where .
                " ORDER BY o.updated_at DESC
                  LIMIT ? OFFSET ?";

    $stmt_data = $conn->prepare($sql_data);
    $data_params = $params;
    $data_types = $param_types . 'ii';
    $data_params[] = $items_per_page;
    $data_params[] = $offset;
    $stmt_data->bind_param($data_types, ...$data_params);
    $stmt_data->execute();
    $result = $stmt_data->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $row['order_date_formatted'] = !empty($row['order_date']) ? date('d/m/Y', strtotime($row['order_date'])) : '-';
        $row['delivery_time_formatted'] = !empty($row['delivery_time']) ? date('H:i', strtotime($row['delivery_time'])) : '-';
        $rows[] = $row;
    }
    $stmt_data->close();

    $sql_count = "SELECT COUNT(*) AS total" . $sql_from . $sql_where;
    $stmt_count = $conn->prepare($sql_count);
    if (!empty($params)) {
        $stmt_count->bind_param($param_types, ...$params);
    }
    $stmt_count->execute();
    $total_items = (int) (($stmt_count->get_result()->fetch_assoc()['total'] ?? 0));
    $stmt_count->close();

    echo json_encode([
        'status' => 'success',
        'orders' => $rows,
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
        .table-responsive { background: #fff; border-radius: .5rem; box-shadow: 0 .125rem .25rem rgba(0,0,0,.075); }
        .delivery-time { font-size: 0.9em; color: #666; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">รายการส่งของแล้ว (วันนี้)</h2>
            <div>
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>กลับหน้าหลัก</a>
                <button class="btn btn-info btn-sm" id="refreshBtn"><i class="fas fa-sync-alt"></i> รีเฟรช</button>
            </div>
        </div>

        <div class="p-3 border rounded bg-light mb-4">
            <form id="filterForm" class="mb-0">
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
                        <button type="button" id="resetBtn" class="btn btn-outline-secondary ml-2"><i class="fas fa-redo"></i> รีเซ็ต</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <strong>รายการที่ส่งของแล้วในวันนี้</strong>
            <span id="items-count-info" class="ml-2">กำลังโหลดข้อมูล...</span>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped mb-0">
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
                    <tr><td colspan="11" class="text-center py-4">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-3">
            <nav id="paginationContainer"></nav>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        let currentPage = 1;

        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }

        function fetchData(page = 1) {
            currentPage = page;
            $('#orders-table-body').html('<tr><td colspan="11" class="text-center py-4">กำลังโหลดข้อมูล...</td></tr>');

            $.ajax({
                url: 'delivered_today.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    page: page,
                    search_docno: $('#search_docno').val(),
                    search_custname: $('#search_custname').val()
                },
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(response) {
                    if (response.status !== 'success') {
                        $('#orders-table-body').html('<tr><td colspan="11" class="text-center text-danger py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>');
                        return;
                    }

                    renderTable(response.orders || []);
                    renderPagination(response.total_pages || 0, response.current_page || 1);
                    $('#items-count-info').html(`(${response.total_items} รายการ)`);
                },
                error: function() {
                    $('#orders-table-body').html('<tr><td colspan="11" class="text-center text-danger py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>');
                }
            });
        }

        function renderTable(orders) {
            const tbody = $('#orders-table-body');
            tbody.empty();

            if (!orders.length) {
                tbody.html('<tr><td colspan="11" class="text-center py-4">ไม่พบรายการที่ส่งของแล้วในวันนี้</td></tr>');
                return;
            }

            orders.forEach(function(row) {
                tbody.append(`
                    <tr>
                        <td>${row.order_id}</td>
                        <td>${escapeHtml(row.cssale_docno || '-')}</td>
                        <td>${escapeHtml(row.custname || '-')}</td>
                        <td>${escapeHtml(row.customer_full_address || '-')}</td>
                        <td>${escapeHtml(row.cssale_shipaddr || '-')}</td>
                        <td>${escapeHtml(row.transport_origin_name || '-')}</td>
                        <td>${escapeHtml(row.assigned_staff_name || '-')}</td>
                        <td>${escapeHtml(row.assigned_vehicle_info || '-')}</td>
                        <td>${escapeHtml(row.order_date_formatted || '-')}</td>
                        <td><div class="delivery-time">${escapeHtml(row.delivery_time_formatted || '-')}</div></td>
                        <td>${escapeHtml(row.priority || '-')}</td>
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
                $('#search_docno').val('');
                $('#search_custname').val('');
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

            setInterval(function() {
                fetchData(currentPage);
            }, 30000);
        });
    </script>
</body>
</html>
