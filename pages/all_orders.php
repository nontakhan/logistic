<?php
// pages/all_orders.php
require_once __DIR__ . '/../php/check_session.php';
// สิทธิ์ที่ต้องการสำหรับหน้านี้
require_permission('orders.view_all', [1, 2, 3, 4]);

require_once __DIR__ . '/../php/db_connect.php';

// กำหนด BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']), '/\\');
$project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $project_folder . '/');

$is_ajax_request = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

// ==================================================================================
//  PART 1: AJAX HANDLER (API) - ทำงานเฉพาะตอนที่ JavaScript เรียกขอข้อมูล
// ==================================================================================
if ($is_ajax_request) {
    header('Content-Type: application/json');

    // --- Pagination Settings ---
    $items_per_page = 20;
    $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($current_page - 1) * $items_per_page;

    // --- รับค่า Filter ---
    $search_term = isset($_GET['search_term']) ? trim($conn->real_escape_string($_GET['search_term'])) : '';
    $filter_status = isset($_GET['filter_status']) && is_array($_GET['filter_status']) ? $_GET['filter_status'] : [];
    $filter_salesman = isset($_GET['filter_salesman']) ? $conn->real_escape_string($_GET['filter_salesman']) : '';
    $filter_transport_origin = isset($_GET['filter_transport_origin']) ? $conn->real_escape_string($_GET['filter_transport_origin']) : '';
    $filter_destination_text = isset($_GET['filter_destination_text']) ? trim($conn->real_escape_string($_GET['filter_destination_text'])) : '';

    $is_date_filtered = !empty($_GET['filter_date_start']) && !empty($_GET['filter_date_end']);
    $filter_date_start = $is_date_filtered ? $conn->real_escape_string($_GET['filter_date_start']) : date('Y-m-d', strtotime('-1 month'));
    $filter_date_end = $is_date_filtered ? $conn->real_escape_string($_GET['filter_date_end']) : date('Y-m-d');

    // --- สร้างเงื่อนไข SQL (WHERE) ---
    $where_clauses = [];
    $params = []; 
    $param_types = ""; 

    // กรองตามสาขา (Access Control)
    if (should_limit_to_assigned_origin()) {
        $where_clauses[] = "o.transport_origin_id = ?";
        $params[] = current_assigned_transport_origin_id();
        $param_types .= "i";
    }

    // กรองตามคำค้นหา
    if (!empty($search_term)) {
        $where_clauses[] = "(o.cssale_docno LIKE ? OR cs.custname LIKE ?)";
        $search_like = "%" . $search_term . "%";
        $params[] = $search_like;
        $params[] = $search_like;
        $param_types .= "ss";
    }

    // กรองตามปลายทาง
    if (!empty($filter_destination_text)) {
        $where_clauses[] = "CONCAT_WS(' ', org.moo, org.mooban, org.tambon, org.amphoe, org.province) LIKE ?";
        $dest_like = "%" . $filter_destination_text . "%";
        $params[] = $dest_like;
        $param_types .= "s";
    }

    // กรองตามสถานะ (Multiple)
    if (!empty($filter_status)) {
        $placeholders = implode(',', array_fill(0, count($filter_status), '?'));
        $where_clauses[] = "o.status IN (" . $placeholders . ")";
        foreach ($filter_status as $status_value) {
            $params[] = $status_value;
        }
        $param_types .= str_repeat('s', count($filter_status));
    }

    // กรองตามพนักงานขาย
    if (!empty($filter_salesman)) {
        $where_clauses[] = "cs.code = ?";
        $params[] = $filter_salesman;
        $param_types .= "s";
    }

    // กรองตามต้นทางขนส่ง (Admin/Level 1)
    if (user_can_filter_all_origins() && !empty($filter_transport_origin)) {
        $where_clauses[] = "o.transport_origin_id = ?";
        $params[] = $filter_transport_origin;
        $param_types .= "i";
    }

    // กรองตามวันที่ (บังคับเสมอ)
    $date_start_param = $filter_date_start . ' 00:00:00';
    $date_end_param = $filter_date_end . ' 23:59:59';
    $where_clauses[] = "o.updated_at BETWEEN ? AND ?";
    $params[] = $date_start_param;
    $params[] = $date_end_param;
    $param_types .= "ss";

    $sql_where = "";
    if (!empty($where_clauses)) {
        $sql_where = " WHERE " . implode(" AND ", $where_clauses);
    }

    // --- Query ข้อมูล + นับจำนวน (SQL_CALC_FOUND_ROWS) ---
    $sql_from = " FROM orders o
                LEFT JOIN cssale cs ON o.cssale_docno = cs.docno
                LEFT JOIN transport_origins t ON o.transport_origin_id = t.transport_origin_id
                LEFT JOIN origin org ON o.customer_address_origin_id = org.id";

    $sql_data = "SELECT 
                    o.order_id, o.cssale_docno, cs.custname, cs.code as salesman_code, cs.lname as salesman_name, 
                    t.origin_name AS transport_origin_name, o.status, o.updated_at, 
                    CONCAT_WS(', ', org.moo, org.mooban, org.tambon, org.amphoe, org.province) as destination_address
                " . $sql_from . $sql_where . "
                ORDER BY o.updated_at DESC
                LIMIT ? OFFSET ?";

    $stmt_data = $conn->prepare($sql_data);
    $current_params = $params;
    $current_param_types = $param_types;
    // เพิ่ม limit offset params
    $current_params[] = $items_per_page;
    $current_params[] = $offset;
    $current_param_types .= "ii";

    if ($stmt_data) {
        if (!empty($current_params)) {
            $stmt_data->bind_param($current_param_types, ...$current_params);
        }
        $stmt_data->execute();
        $result_orders = $stmt_data->get_result();
        $stmt_data->close();
    }

    // ดึงจำนวนทั้งหมด
    $sql_count = "SELECT COUNT(*) AS total" . $sql_from . $sql_where;
    $stmt_count = $conn->prepare($sql_count);
    $total_items = 0;
    if ($stmt_count) {
        if (!empty($params)) {
            $stmt_count->bind_param($param_types, ...$params);
        }
        $stmt_count->execute();
        $result_total = $stmt_count->get_result();
        $total_items = $result_total ? (int)($result_total->fetch_assoc()['total'] ?? 0) : 0;
        $stmt_count->close();
    }
    $total_pages = ceil($total_items / $items_per_page);

    // เตรียมข้อมูล JSON
    $orders_data_array = [];
    if ($result_orders) {
        while($row = $result_orders->fetch_assoc()) {
            $row['updated_at_formatted'] = !empty($row['updated_at']) ? date("d/m/Y H:i", strtotime($row['updated_at'])) : '-';
            $orders_data_array[] = $row;
        }
    }

    // สร้าง Query String สำหรับลิงก์ Pagination (เผื่อใช้)
    $query_string_params = $_GET;
    unset($query_string_params['page']);
    $base_query_string = http_build_query($query_string_params);

    echo json_encode([
        'orders' => $orders_data_array,
        'total_items' => (int)$total_items,
        'total_pages' => (int)$total_pages,
        'current_page' => (int)$current_page,
        'base_query_string' => $base_query_string
    ]);

    if (isset($conn)) $conn->close();
    exit; // จบการทำงานของ PHP ทันทีเมื่อเป็น AJAX request
}

// ==================================================================================
//  PART 2: HTML RENDERING (View) - โหลดเฉพาะโครงสร้าง ไม่ดึงข้อมูล Order
// ==================================================================================

// เตรียมข้อมูลสำหรับ Dropdowns (เล็กน้อย ไม่หนักมาก โหลดพร้อมหน้าเว็บได้)
$salesman_options_html = '<option value="">พนักงานขายทั้งหมด</option>';
$sql_salesman = "SELECT DISTINCT code, lname FROM cssale WHERE code IS NOT NULL AND lname IS NOT NULL AND lname != '' ORDER BY lname ASC";
$result_salesman = $conn->query($sql_salesman);
if ($result_salesman && $result_salesman->num_rows > 0) {
    while($row = $result_salesman->fetch_assoc()) {
        $salesman_options_html .= "<option value='" . htmlspecialchars($row['code']) . "'>" . htmlspecialchars($row['code'] . ' - ' . $row['lname']) . "</option>";
    }
}

$transport_origin_options_html = '<option value="">ต้นทางทั้งหมด</option>';
if (user_can_filter_all_origins()) {
    $sql_transport = "SELECT transport_origin_id, origin_name FROM transport_origins ORDER BY origin_name";
    $result_transport = $conn->query($sql_transport);
    if ($result_transport && $result_transport->num_rows > 0) {
        while($row = $result_transport->fetch_assoc()) {
            $transport_origin_options_html .= "<option value='" . htmlspecialchars($row['transport_origin_id']) . "'>" . htmlspecialchars($row['origin_name']) . "</option>";
        }
    }
}

// ค่า Default ของวันที่ (สำหรับใส่ใน Input HTML)
$default_date_start = date('Y-m-d', strtotime('-1 month'));
$default_date_end = date('Y-m-d');

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามรายการทั้งหมด - NR Logistics</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚚</text></svg>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css?v=1.5" rel="stylesheet">
    <style>
        .table-danger, .table-danger > th, .table-danger > td { background-color: #fee2e2 !important; }
        .table-info, .table-info > th, .table-info > td { background-color: #dbeafe !important; }
        .table-warning, .table-warning > th, .table-warning > td { background-color: #fef3c7 !important; }
        .table-success, .table-success > th, .table-success > td { background-color: #d1fae5 !important; }
        .table-secondary, .table-secondary > th, .table-secondary > td { background-color: #f3f4f6 !important; color: #6b7280; }
        .table-secondary td { text-decoration: line-through; }

        .select2-container--default .select2-selection--multiple {
            min-height: calc(1.5em + .75rem + 2px);
            padding: 0;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--multiple .select2-selection__rendered {
            padding-left: .75rem;
            padding-right: .75rem;
        }
        .select2-container--default .select2-search--inline .select2-search__field {
            margin-top: 0;
            padding: 0;
            line-height: calc(1.5em + .75rem);
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-dark"><i class="fas fa-list-alt mr-2"></i>ติดตามรายการทั้งหมด</h2>
            <div>
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left mr-1"></i> กลับหน้าหลัก
                </a>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <form id="filterForm">
                    <div class="row">
                        <div class="col-lg-4 col-md-6 mb-3">
                             <label for="search_term">ค้นหาทั่วไป</label>
                            <input type="text" class="form-control" id="search_term" name="search_term" placeholder="เลขที่บิล, ชื่อลูกค้า...">
                        </div>
                        
                        <div class="col-lg-4 col-md-6 mb-3">
                            <label for="filter_salesman">พนักงานขาย</label>
                            <select class="form-control select2-basic" id="filter_salesman" name="filter_salesman">
                                <?php echo $salesman_options_html; ?>
                            </select>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 mb-3">
                             <label for="filter_destination_text">ค้นหาปลายทาง</label>
                            <input type="text" class="form-control" id="filter_destination_text" name="filter_destination_text" placeholder="หมู่บ้าน, ตำบล, อำเภอ...">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-4 mb-3">
                            <label>อัปเดตล่าสุดระหว่างวันที่</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="filter_date_start" name="filter_date_start" value="<?php echo $default_date_start; ?>">
                                <div class="input-group-prepend input-group-append"><span class="input-group-text">ถึง</span></div>
                                <input type="date" class="form-control" id="filter_date_end" name="filter_date_end" value="<?php echo $default_date_end; ?>">
                            </div>
                        </div>
                        <div class="col-lg-2 col-md-6 mb-3">
                             <label for="filter_status">สถานะ</label>
                            <select class="form-control" id="filter_status" name="filter_status[]" multiple="multiple">
                                <option value="รอรับเรื่อง">รอรับเรื่อง</option>
                                <option value="รับเรื่อง">รับเรื่อง</option>
                                <option value="รอส่งของ">รอส่งของ</option>
                                <option value="ส่งของแล้ว">ส่งของแล้ว</option>
                                <option value="ยกเลิก">ยกเลิก</option>
                            </select>
                        </div>
                        <?php if (user_can_filter_all_origins()): ?>
                        <div class="col-lg-2 col-md-6 mb-3">
                            <label for="filter_transport_origin">ต้นทางขนส่ง</label>
                            <select class="form-control select2-basic" id="filter_transport_origin" name="filter_transport_origin">
                                <?php echo $transport_origin_options_html; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="col-lg-4 d-flex align-items-end mb-3">
                             <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-filter mr-1"></i> กรองข้อมูล</button>
                             <button type="button" id="resetBtn" class="btn btn-danger mr-2"><i class="fas fa-eraser mr-1"></i> ล้างค่า</button>
                             <button type="button" id="exportBtn" class="btn btn-success"><i class="fas fa-file-excel mr-1"></i> Export</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-responsive bg-white rounded shadow-sm">
             <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <span id="items-count-info">กำลังเตรียมข้อมูล...</span>
            </div>
            <table class="table table-hover table-striped mb-0">
                <thead class="thead-light">
                    <tr>
                        <th class="no-wrap">เลขที่บิล</th>
                        <th>ชื่อลูกค้า</th>
                        <th class="no-wrap">พนักงานขาย</th>
                        <th>ต้นทางขนส่ง</th>
                        <th>ปลายทาง</th>
                        <th class="text-center">สถานะ</th>
                        <th class="no-wrap">อัปเดตล่าสุด</th>
                        <th class="text-center">ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">กำลังโหลดข้อมูลรายการ...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-3">
            <nav id="paginationContainer"></nav>
        </div>

    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-labelledby="detailsModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="detailsModalLabel">รายละเอียดการจัดส่ง</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div id="modal-content-placeholder" class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="sr-only">Loading...</span>
                </div>
            </div>
          </div>
          <div class="modal-footer">
            <div id="modal-action-buttons" class="mr-auto"></div>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">ปิด</button>
          </div>
        </div>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <?php echo csrf_ajax_script(); ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const canDeleteCancelledOrder = <?php echo json_encode(has_permission('orders.delete', [2, 4])); ?>;
        // เก็บ State ปัจจุบันของการค้นหาไว้ในตัวแปร JS เพื่อใช้กับการ Pagination และ Export
        let currentPage = 1;
        let currentFilters = {};

        $(document).ready(function() {
            // Setup Select2
            $('.select2-basic').select2({ placeholder: "-- ทั้งหมด --", allowClear: true });
            $('#filter_status').select2({ placeholder: "เลือกสถานะ (เลือกได้หลายอัน)", allowClear: true, closeOnSelect: false });

            // Initialize Filters from Inputs
            function getFilters() {
                return {
                    search_term: $('#search_term').val(),
                    filter_salesman: $('#filter_salesman').val(),
                    filter_destination_text: $('#filter_destination_text').val(),
                    filter_status: $('#filter_status').val(),
                    filter_transport_origin: $('#filter_transport_origin').val(),
                    filter_date_start: $('#filter_date_start').val(),
                    filter_date_end: $('#filter_date_end').val()
                };
            }

            // Main Function to Fetch Data
            function fetchData(page = 1) {
                currentPage = page;
                currentFilters = getFilters();
                
                // Show Loading in Table
                $('#ordersTableBody').html('<tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted">กำลังโหลดข้อมูล...</p></td></tr>');
                $('.loading-overlay').show();

                // Prepare Data Object for AJAX
                const ajaxData = {
                    ...currentFilters,
                    page: page
                };

                $.ajax({
                    url: 'all_orders.php', 
                    type: 'GET',
                    data: ajaxData,
                    dataType: 'json',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    success: function(response) {
                        renderTable(response.orders);
                        renderPagination(response.total_pages, response.current_page);
                        $('#items-count-info').html(`แสดงผล <strong>${response.orders.length > 0 ? (response.current_page - 1) * 20 + 1 : 0}</strong> - <strong>${Math.min(response.current_page * 20, response.total_items)}</strong> จากทั้งหมด <strong>${response.total_items}</strong> รายการ`);
                        $('.loading-overlay').hide();
                    },
                    error: function() {
                        $('#ordersTableBody').html('<tr><td colspan="8" class="text-center py-5 text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>');
                        $('.loading-overlay').hide();
                    }
                });
            }

            // Helper: Render Table Rows
            function renderTable(orders) {
                const tbody = $('#ordersTableBody');
                tbody.empty();
                
                if (!orders || orders.length === 0) {
                    tbody.html('<tr><td colspan="8" class="text-center text-muted py-5">ไม่พบข้อมูลตามเงื่อนไขที่ระบุ</td></tr>');
                    return;
                }

                orders.forEach(row => {
                    let statusClass = 'status-' + (row.status || '').toLowerCase().replace(/[\s\/]/g, '-');
                    let salesmanDisplay = row.salesman_name ? `${row.salesman_code} - ${row.salesman_name}` : '-';
                    let actionButtonHtml = `<button class="btn btn-info btn-sm view-details-btn" data-orderid="${row.order_id}" title="ดูรายละเอียด"><i class="fas fa-eye"></i></button>`;

                    const tr = `
                        <tr class="${statusClass}">
                            <td class="no-wrap font-weight-bold">${row.cssale_docno || '-'}</td>
                            <td>${row.custname || '-'}</td>
                            <td class="no-wrap">${salesmanDisplay}</td>
                            <td>${row.transport_origin_name || '-'}</td>
                            <td>${row.destination_address || '-'}</td>
                            <td class="text-center">${renderStatusBadge(row.status)}</td>
                            <td class="no-wrap">${row.updated_at_formatted || '-'}</td>
                            <td class="text-center">${actionButtonHtml}</td>
                        </tr>
                    `;
                    tbody.append(tr);
                });
            }

            function renderStatusBadge(status) {
                let badgeClass = 'badge-light-secondary';
                let iconClass = 'fa-question-circle';
                switch (status) {
                    case 'รอรับเรื่อง': badgeClass = 'badge-light-danger'; iconClass = 'fa-inbox'; break;
                    case 'รับเรื่อง': badgeClass = 'badge-light-primary'; iconClass = 'fa-check-circle'; break;
                    case 'รอส่งของ': badgeClass = 'badge-light-warning'; iconClass = 'fa-truck'; break;
                    case 'ส่งของแล้ว': badgeClass = 'badge-light-success'; iconClass = 'fa-check-double'; break;
                    case 'ยกเลิก': badgeClass = 'badge-light-secondary'; iconClass = 'fa-times-circle'; break;
                }
                return `<span class="badge badge-pill ${badgeClass} p-2" style="font-size: 0.9em;"><i class="fas ${iconClass} mr-1"></i> ${status}</span>`;
            }

            function renderPagination(totalPages, currentPage) {
                const container = $('#paginationContainer');
                if (totalPages <= 1) {
                    container.html('');
                    return;
                }

                let html = '<ul class="pagination">';
                html += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage - 1}">ก่อนหน้า</a></li>`;
                
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, currentPage + 2);

                if (startPage > 1) {
                    html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
                    if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                }

                for (let i = startPage; i <= endPage; i++) {
                    html += `<li class="page-item ${i === currentPage ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
                }

                if (endPage < totalPages) {
                    if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
                    html += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
                }

                html += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage + 1}">ถัดไป</a></li>`;
                html += '</ul>';
                container.html(html);
            }

            // *** Trigger Initial Load ***
            fetchData(1);

            // Event Listeners
            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                fetchData(1);
            });

            $('#resetBtn').on('click', function() {
                // Reset inputs manually
                $('#search_term').val('');
                $('#filter_destination_text').val('');
                $('#filter_salesman').val(null).trigger('change');
                $('#filter_status').val(null).trigger('change');
                $('#filter_transport_origin').val(null).trigger('change');
                // Reset dates to default (1 month back)
                const today = new Date();
                const lastMonth = new Date();
                lastMonth.setMonth(today.getMonth() - 1);
                $('#filter_date_start').val(lastMonth.toISOString().split('T')[0]);
                $('#filter_date_end').val(today.toISOString().split('T')[0]);
                
                fetchData(1);
            });

            $('#paginationContainer').on('click', 'a.page-link', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page) fetchData(page);
            });

            $('#exportBtn').on('click', function(e) {
                e.preventDefault();
                const params = $.param(getFilters());
                window.location.href = `<?php echo BASE_URL; ?>php/export_excel.php?${params}`;
            });

            // Modal Logic (Same as before)
            $('#ordersTableBody').on('click', '.view-details-btn', function() {
                const orderId = $(this).data('orderid');
                const modalPlaceholder = $('#modal-content-placeholder');
                const actionButtonsContainer = $('#modal-action-buttons');
                
                modalPlaceholder.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>');
                actionButtonsContainer.empty();
                $('#detailsModal').modal('show');
                
                $.ajax({
                    url: '<?php echo BASE_URL; ?>php/get_order_details.php',
                    type: 'GET',
                    data: { id: orderId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            const d = response.data;
                            let staffInfo = d.assigned_staff;
                            if (d.assigned_staff_phone) staffInfo += ` (${d.assigned_staff_phone})`;
                            let salesmanInfo = d.salesman_code ? `${d.salesman_code} - ${d.salesman_name || ''}` : '-';
                            if (d.salesman_phone) salesmanInfo += ` (${d.salesman_phone})`;

                            let html = `
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5 class="text-primary"><i class="fas fa-file-invoice mr-2"></i>ข้อมูลใบสั่งซื้อ</h5>
                                            <table class="table table-sm table-bordered">
                                                <tr><th style="width: 35%;">ID ติดตาม</th><td>${d.order_id}</td></tr>
                                                <tr><th>เลขที่บิล</th><td>${d.cssale_docno}</td></tr>
                                                <tr><th>วันที่สั่ง</th><td>${d.order_date_formatted}</td></tr>
                                                <tr><th>สถานะ</th><td><span class="badge ${d.status_badge} p-2">${d.status}</span></td></tr>
                                                <tr><th>อัปเดตล่าสุด</th><td>${d.updated_at_formatted}</td></tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="text-primary"><i class="fas fa-user-tie mr-2"></i>ข้อมูลลูกค้า</h5>
                                            <table class="table table-sm table-bordered">
                                                <tr><th style="width: 35%;">ชื่อลูกค้า</th><td>${d.custname}</td></tr>
                                                <tr><th>ที่อยู่ (ตามบิล)</th><td>${d.shipaddr}</td></tr>
                                                <tr><th>พนักงานขาย</th><td>${salesmanInfo}</td></tr>
                                            </table>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h5 class="text-primary"><i class="fas fa-shipping-fast mr-2"></i>ข้อมูลการจัดส่ง</h5>
                                            <table class="table table-sm table-bordered">
                                                <tr><th style="width: 25%;">ต้นทางขนส่ง</th><td>${d.transport_origin}</td></tr>
                                                <tr><th>ปลายทาง</th><td>${d.full_address}</td></tr>
                                                <tr><th>คนขับรถ</th><td>${staffInfo}</td></tr>
                                                <tr><th>รถที่ใช้</th><td>${d.assigned_vehicle}</td></tr>
                                                <tr><th>หมายเหตุ</th><td>${d.product_details}</td></tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            `;
                            modalPlaceholder.html(html);

                             if (d.status === 'ยกเลิก' && canDeleteCancelledOrder) {
                                const deleteButton = `<button type="button" class="btn btn-danger delete-in-modal-btn" data-id="${d.order_id}"><i class="fas fa-trash-alt mr-1"></i> ลบรายการนี้</button>`;
                                actionButtonsContainer.html(deleteButton);
                            }
                        } else {
                            modalPlaceholder.html(`<p class="text-danger">${response.message}</p>`);
                        }
                    },
                    error: function() { modalPlaceholder.html('<p class="text-danger">เกิดข้อผิดพลาดในการเชื่อมต่อ</p>'); }
                });
            });

             // Modal Delete Handler
            $('#detailsModal').on('click', '.delete-in-modal-btn', function() {
                const orderId = $(this).data('id');
                Swal.fire({
                    title: 'ยืนยันการลบ',
                    text: "การกระทำนี้ไม่สามารถย้อนกลับได้!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'ลบเลย!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '<?php echo BASE_URL; ?>php/delete_order.php',
                            type: 'POST',
                            data: { order_id: orderId },
                            dataType: 'json',
                            success: function(response) {
                                if (response.status === 'success') {
                                    $('#detailsModal').modal('hide');
                                    Swal.fire('สำเร็จ!', response.message, 'success');
                                    fetchData(currentPage); // Reload current page
                                } else {
                                    Swal.fire('เกิดข้อผิดพลาด!', response.message, 'error');
                                }
                            }
                        });
                    }
                });
            });

        });
    </script>
</body>
</html>
