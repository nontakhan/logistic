<?php
// pages/all_orders.php
require_once '../php/check_session.php';
// สิทธิ์ที่ต้องการสำหรับหน้านี้
require_login([2, 3, 4]);

require_once '../php/db_connect.php';

// กำหนด BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');


$is_ajax_request = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

// --- Pagination Settings ---
$items_per_page = 20;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $items_per_page;

// --- ดึงข้อมูลสำหรับ Filters ---
// *** เพิ่ม: ดึงข้อมูลพนักงานขายสำหรับ Dropdown ***
$salesman_options_filter = "<option value=''>พนักงานขายทั้งหมด</option>";
$sql_salesman_filter = "SELECT code, lname FROM csuser WHERE lname IS NOT NULL AND lname != '' ORDER BY lname ASC";
$result_salesman_filter = $conn->query($sql_salesman_filter);
if ($result_salesman_filter && $result_salesman_filter->num_rows > 0) {
    while($row = $result_salesman_filter->fetch_assoc()) {
        $selected_salesman = (isset($_GET['filter_salesman']) && $_GET['filter_salesman'] == $row['code']) ? 'selected' : '';
        $salesman_options_filter .= "<option value='" . htmlspecialchars($row['code']) . "' $selected_salesman>" . htmlspecialchars($row['code'] . ' - ' . $row['lname']) . "</option>";
    }
}


// --- จัดการการค้นหาและกรอง ---
$search_term = isset($_GET['search_term']) ? trim($conn->real_escape_string($_GET['search_term'])) : '';
$filter_status = isset($_GET['filter_status']) ? $conn->real_escape_string($_GET['filter_status']) : '';
$filter_salesman = isset($_GET['filter_salesman']) ? $conn->real_escape_string($_GET['filter_salesman']) : ''; // รับค่า filter พนักงานขาย
$filter_date_start = isset($_GET['filter_date_start']) && !empty($_GET['filter_date_start']) ? $conn->real_escape_string($_GET['filter_date_start']) : '';
$filter_date_end = isset($_GET['filter_date_end']) && !empty($_GET['filter_date_end']) ? $conn->real_escape_string($_GET['filter_date_end']) : '';

$where_clauses = [];
$params = []; 
$param_types = ""; 

// กรองตามสาขาของผู้ใช้ (ยกเว้น Admin)
if (is_logged_in() && $_SESSION['role_level'] != 4 && !empty($_SESSION['assigned_transport_origin_id'])) {
    $where_clauses[] = "o.transport_origin_id = ?";
    $params[] = $_SESSION['assigned_transport_origin_id'];
    $param_types .= "i";
}

// สร้าง query string สำหรับ pagination links
$query_string_params = [];
if (!empty($search_term)) {
    $where_clauses[] = "(o.cssale_docno LIKE ? OR cs.custname LIKE ?)";
    $search_like = "%" . $search_term . "%";
    array_push($params, $search_like, $search_like);
    $param_types .= "ss";
    $query_string_params['search_term'] = $search_term;
}
if (!empty($filter_status)) {
    $where_clauses[] = "o.status = ?"; 
    $params[] = $filter_status; 
    $param_types .= "s"; 
    $query_string_params['filter_status'] = $filter_status;
}
// *** เพิ่ม: เงื่อนไขการกรองตามพนักงานขาย ***
if (!empty($filter_salesman)) {
    $where_clauses[] = "cs.saleman = ?"; 
    $params[] = $filter_salesman; 
    $param_types .= "s"; 
    $query_string_params['filter_salesman'] = $filter_salesman;
}
if (!empty($filter_date_start)) {
    $where_clauses[] = "DATE(o.updated_at) >= ?";
    $params[] = $filter_date_start;
    $param_types .= "s";
    $query_string_params['filter_date_start'] = $filter_date_start;
}
if (!empty($filter_date_end)) {
    $where_clauses[] = "DATE(o.updated_at) <= ?";
    $params[] = $filter_date_end;
    $param_types .= "s";
    $query_string_params['filter_date_end'] = $filter_date_end;
}

$base_query_string = http_build_query($query_string_params);

$sql_where = "";
if (!empty($where_clauses)) {
    $sql_where = " WHERE " . implode(" AND ", $where_clauses);
}

// --- Count total items for pagination ---
$sql_count_base = "SELECT COUNT(o.order_id) as total_count FROM orders o LEFT JOIN cssale cs ON o.cssale_docno = cs.docno COLLATE utf8mb4_unicode_ci";
$sql_count_final = $sql_count_base . $sql_where;
$stmt_count = $conn->prepare($sql_count_final);
if (!empty($params)) {
    $stmt_count->bind_param($param_types, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_items = $result_count->fetch_assoc()['total_count'];
$total_pages = ceil($total_items / $items_per_page);
$stmt_count->close();

// --- Fetch data for the current page ---
// *** เพิ่ม: JOIN กับ csuser และเลือก salesman_name (lname) ***
$sql_data_base = "SELECT 
                    o.order_id, o.cssale_docno, cs.custname, cs.shipaddr, o.status, o.updated_at,
                    t_org.origin_name AS transport_origin_name,
                    cu.lname AS salesman_name
                FROM orders o
                LEFT JOIN cssale cs ON o.cssale_docno = cs.docno COLLATE utf8mb4_unicode_ci
                LEFT JOIN csuser cu ON cs.salesman = cu.code
                LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id
                LEFT JOIN staff s ON o.assigned_staff_id = s.staff_id";
$sql_data_final = $sql_data_base . $sql_where . " ORDER BY o.updated_at DESC LIMIT ? OFFSET ?";
$params_data = $params;
$params_data[] = $items_per_page;
$params_data[] = $offset;
$param_types_data = $param_types . "ii"; 
$stmt_data = $conn->prepare($sql_data_final);
$stmt_data->bind_param($param_types_data, ...$params_data);
$stmt_data->execute();
$result_orders_mysqli = $stmt_data->get_result();
$orders_data_array = [];
if ($result_orders_mysqli) {
    while($row = $result_orders_mysqli->fetch_assoc()) {
        $row['updated_at_formatted'] = !empty($row['updated_at']) ? date("d/m/Y H:i", strtotime($row['updated_at'])) : '-';
        $orders_data_array[] = $row;
    }
}
$stmt_data->close();

if ($is_ajax_request) {
    header('Content-Type: application/json');
    echo json_encode([
        'orders' => $orders_data_array,
        'total_items' => (int)$total_items,
        'total_pages' => (int)$total_pages,
        'current_page' => (int)$current_page,
    ]);
    if (isset($conn)) $conn->close();
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดตามรายการทั้งหมด</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        .loading-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background-color:rgba(255,255,255,.7);z-index:9999;display:flex;align-items:center;justify-content:center}.loading-overlay .spinner-border{width:3rem;height:3rem}
        .badge-status{font-size: .85em; padding: .5em .8em; font-weight: 600;}
        .badge-status .icon{margin-right: 5px;}
        .badge-light-danger { color: #f1416c; background-color: #fff5f8; }
        .badge-light-primary { color: #009ef7; background-color: #f1faff; }
        .badge-light-warning { color: #ffc700; background-color: #fff8dd; }
        .badge-light-success { color: #50cd89; background-color: #e8fff3; }
        .badge-light-secondary { color: #7e8299; background-color: #f8f9fa; }
        .table-hover tbody tr.status-รอรับเรื่อง:hover, .table-hover tbody tr.status-รอรับเรื่อง { background-color: rgba(241, 65, 108, 0.05); }
        .table-hover tbody tr.status-รับเรื่อง:hover, .table-hover tbody tr.status-รับเรื่อง { background-color: rgba(0, 158, 247, 0.05); }
        .table-hover tbody tr.status-รอส่งของ:hover, .table-hover tbody tr.status-รอส่งของ { background-color: rgba(255, 199, 0, 0.08); }
        .table-hover tbody tr.status-ยกเลิก { text-decoration: line-through; color: #a1a5b7; }
        .table-hover tbody tr.status-ยกเลิก:hover, .table-hover tbody tr.status-ยกเลิก { background-color: #f5f8fa; }
    </style>
</head>
<body>
    <div class="loading-overlay" style="display: none;">
        <div class="spinner-border text-danger" role="status"><span class="sr-only">Loading...</span></div>
    </div>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">ติดตามรายการทั้งหมด</h2>
            <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-2"></i>กลับหน้าหลัก</a>
        </div>

        <form id="filterForm" class="filter-form mb-4 p-3 border rounded bg-light">
            <div class="form-row">
                <div class="form-group col-md-4">
                    <label for="search_term">ค้นหา</label>
                    <input type="text" class="form-control" id="search_term" name="search_term" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="ค้นหาจากเลขที่บิล หรือ ชื่อลูกค้า...">
                </div>
                <div class="form-group col-md-3">
                    <label for="filter_salesman">พนักงานขาย</label>
                    <select class="form-control select2-filter" id="filter_salesman" name="filter_salesman">
                        <?php echo $salesman_options_filter; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="filter_date_start">อัปเดตจากวันที่</label>
                    <input type="date" class="form-control" id="filter_date_start" name="filter_date_start" value="<?php echo htmlspecialchars($filter_date_start); ?>">
                </div>
                <div class="form-group col-md-2">
                    <label for="filter_status">สถานะ</label>
                    <select class="form-control select2-filter" id="filter_status" name="filter_status">
                        <option value="">ทุกสถานะ</option>
                        <option value="รอรับเรื่อง">รอรับเรื่อง</option>
                        <option value="รับเรื่อง">รับเรื่อง</option>
                        <option value="รอส่งของ">รอส่งของ</option>
                        <option value="ส่งของแล้ว">ส่งของแล้ว</option>
                        <option value="ยกเลิก">ยกเลิก</option>
                    </select>
                </div>
            </div>
             <div class="form-row">
                <div class="col-12 text-right">
                    <a href="<?php echo BASE_URL; ?>pages/all_orders.php" class="btn btn-danger"><i class="fas fa-undo"></i> ล้างค่า</a>
                </div>
             </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="thead-light">
                    <tr>
                        <th>เลขที่บิล</th>
                        <th>ชื่อลูกค้า</th>
                        <th>พนักงานขาย</th> <!-- เพิ่ม Header -->
                        <th>ต้นทางขนส่ง</th>
                        <th>สถานที่ส่ง</th>
                        <th class="text-center">สถานะ</th>
                        <th>อัปเดตล่าสุด</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <!-- Data will be loaded here by AJAX -->
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3">
            <div id="items-count-info"><small>กำลังโหลดข้อมูล...</small></div>
            <nav id="paginationContainer"></nav>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-filter').select2({
                 // allowClear: true ถ้าต้องการให้มีปุ่ม x
            });

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
                return `<span class="badge badge-status ${badgeClass}"><i class="fas ${iconClass} icon"></i> ${status}</span>`;
            }

            function buildTableRow(row) {
                let statusClass = 'status-' + (row.status || '').toLowerCase().replace(/[\s\/]/g, '-');
                return `
                    <tr class="${statusClass}">
                        <td><strong>${row.cssale_docno || '-'}</strong></td>
                        <td>${row.custname || '-'}</td>
                        <td>${row.salesman_name || '-'}</td>
                        <td>${row.transport_origin_name || '-'}</td>
                        <td>${row.shipaddr || '-'}</td>
                        <td class="text-center">${renderStatusBadge(row.status)}</td>
                        <td>${row.updated_at_formatted || '-'}</td>
                    </tr>
                `;
            }

            function renderPagination(totalPages, currentPage) {
                if (totalPages <= 1) {
                    $('#paginationContainer').html('');
                    return;
                }
                let paginationHtml = '<ul class="pagination justify-content-center">';
                paginationHtml += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a></li>`;
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, currentPage + 2);
                if (startPage > 1) { paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`; if (startPage > 2) { paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`; } }
                for (let i = startPage; i <= endPage; i++) { paginationHtml += `<li class="page-item ${currentPage == i ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`; }
                if (endPage < totalPages) { if (endPage < totalPages - 1) { paginationHtml += `<li class="page-item disabled"><span class="page-link">...</span></li>`; } paginationHtml += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`; }
                paginationHtml += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a></li>`;
                paginationHtml += '</ul>';
                $('#paginationContainer').html(paginationHtml);
            }

            let searchTimeout;
            function fetchData(page = 1) {
                clearTimeout(searchTimeout);
                let formData = $('#filterForm').serialize();
                formData += `&page=${page}`;
                
                let currentUrl = window.location.pathname + '?' + formData;
                history.pushState({path: currentUrl}, '', currentUrl);

                $('.loading-overlay').show();

                $.ajax({
                    url: 'all_orders.php', 
                    type: 'GET',
                    data: formData,
                    dataType: 'json',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    success: function(response) {
                        $('#ordersTableBody').empty();
                        if (response.orders && response.orders.length > 0) {
                            response.orders.forEach(row => $('#ordersTableBody').append(buildTableRow(row)));
                        } else {
                            $('#ordersTableBody').append('<tr><td colspan="7" class="text-center py-5">ไม่พบข้อมูลตามเงื่อนไขที่ระบุ</td></tr>');
                        }
                        $('#items-count-info').html(`<small>พบทั้งหมด ${response.total_items} รายการ (หน้า ${response.current_page} จาก ${response.total_pages})</small>`);
                        renderPagination(response.total_pages, response.current_page);
                    },
                    error: function() {
                        $('#ordersTableBody').html('<tr><td colspan="7" class="text-center py-5 text-danger">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>');
                    },
                    complete: function() {
                        $('.loading-overlay').hide();
                    }
                });
            }

            // Initial data load
            fetchData(<?php echo $current_page; ?>);

            // Event handlers for filters
            $('#filterForm input, #filterForm select').on('change', function() {
                 fetchData(1);
            });
            $('#search_term').on('keyup', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    fetchData(1);
                }, 500);
            });

            $('#paginationContainer').on('click', 'a.page-link', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page && !$(this).closest('.page-item').hasClass('disabled')) {
                    fetchData(page);
                }
            });
        });
    </script>
</body>
</html>
