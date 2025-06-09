<?php
// pages/all_orders.php
require_once '../php/check_session.php';
// สิทธิ์ที่ต้องการสำหรับหน้านี้ยังคงเดิม
require_login([2, 3, 4]);

require_once '../php/db_connect.php';

// กำหนด BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');


$is_ajax_request = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');

// --- Pagination Settings ---
$items_per_page = 15;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $items_per_page;

// --- ดึงข้อมูลสำหรับ Filters ---
$transport_origin_options_filter = "<option value=''>ทั้งหมด</option>";
$sql_transport_filter = "SELECT transport_origin_id, origin_name FROM transport_origins ORDER BY origin_name";
$result_transport_filter = $conn->query($sql_transport_filter);
if ($result_transport_filter && $result_transport_filter->num_rows > 0) {
    while($row = $result_transport_filter->fetch_assoc()) {
        $selected_transport = (isset($_GET['filter_transport_origin']) && $_GET['filter_transport_origin'] == $row['transport_origin_id']) ? 'selected' : '';
        $transport_origin_options_filter .= "<option value='" . htmlspecialchars($row['transport_origin_id']) . "' $selected_transport>" . htmlspecialchars($row['origin_name']) . "</option>";
    }
}

$status_options_values = [
    '' => 'ทั้งหมด', 'รอรับเรื่อง' => 'รอรับเรื่อง', 'รับเรื่อง' => 'รับเรื่อง',
    'รอส่งของ' => 'รอส่งของ', 'ส่งของแล้ว' => 'ส่งของแล้ว', 'ยกเลิก' => 'ยกเลิก'
];
$status_options_filter = "";
foreach ($status_options_values as $value => $label) {
    $selected_status = (isset($_GET['filter_status']) && $_GET['filter_status'] == $value && $_GET['filter_status'] != '') ? 'selected' : '';
    $status_options_filter .= "<option value='" . htmlspecialchars($value) . "' $selected_status>" . htmlspecialchars($label) . "</option>";
}

$priority_options_values = [
    '' => 'ทั้งหมด', 'ปกติ' => 'ปกติ', 'ด่วน' => 'ด่วน', 'ด่วนที่สุด' => 'ด่วนที่สุด'
];
$priority_options_filter = "";
foreach ($priority_options_values as $value => $label) {
    $selected_priority = (isset($_GET['filter_priority']) && $_GET['filter_priority'] == $value && $_GET['filter_priority'] != '') ? 'selected' : '';
    $priority_options_filter .= "<option value='" . htmlspecialchars($value) . "' $selected_priority>" . htmlspecialchars($label) . "</option>";
}


// --- จัดการการค้นหาและกรอง ---
$search_term = isset($_GET['search_term']) ? trim($conn->real_escape_string($_GET['search_term'])) : '';
$filter_status = isset($_GET['filter_status']) ? $conn->real_escape_string($_GET['filter_status']) : '';
$filter_priority = isset($_GET['filter_priority']) ? $conn->real_escape_string($_GET['filter_priority']) : '';
$filter_transport_origin = isset($_GET['filter_transport_origin']) ? $conn->real_escape_string($_GET['filter_transport_origin']) : '';
$filter_date_start = isset($_GET['filter_date_start']) && !empty($_GET['filter_date_start']) ? $conn->real_escape_string($_GET['filter_date_start']) : '';
$filter_date_end = isset($_GET['filter_date_end']) && !empty($_GET['filter_date_end']) ? $conn->real_escape_string($_GET['filter_date_end']) : '';

$where_clauses = [];
$params = []; 
$param_types = ""; 

// *** เพิ่มส่วนนี้: กรองข้อมูลตามสาขาของผู้ใช้ ***
if (isset($_SESSION['role_level']) && $_SESSION['role_level'] != 4 && !empty($_SESSION['assigned_transport_origin_id'])) {
    $where_clauses[] = "o.transport_origin_id = ?";
    $params[] = $_SESSION['assigned_transport_origin_id'];
    $param_types .= "i";
}

$query_string_params = [];
if (!empty($search_term)) {
    $where_clauses[] = "(CAST(o.order_id AS CHAR) LIKE ? OR o.cssale_docno LIKE ? OR cs.custname LIKE ?)";
    $search_like = "%" . $search_term . "%";
    array_push($params, $search_like, $search_like, $search_like);
    $param_types .= "sss";
    $query_string_params['search_term'] = $search_term;
}
if (!empty($filter_status)) {
    $where_clauses[] = "o.status = ?"; $params[] = $filter_status; $param_types .= "s"; $query_string_params['filter_status'] = $filter_status;
}
if (!empty($filter_priority)) {
    $where_clauses[] = "o.priority = ?"; $params[] = $filter_priority; $param_types .= "s"; $query_string_params['filter_priority'] = $filter_priority;
}
// การกรองตามสาขาจากฟอร์ม จะทำงานได้สำหรับ Admin เท่านั้น เพราะถ้าไม่ใช่ Admin จะถูกบังคับกรองตามสาขาตัวเองไปแล้ว
if (isset($_SESSION['role_level']) && $_SESSION['role_level'] == 4 && !empty($filter_transport_origin)) {
    $where_clauses[] = "o.transport_origin_id = ?"; $params[] = $filter_transport_origin; $param_types .= "i"; $query_string_params['filter_transport_origin'] = $filter_transport_origin;
}
if (!empty($filter_date_start)) {
    $where_clauses[] = "o.order_date >= ?"; $params[] = $filter_date_start; $param_types .= "s"; $query_string_params['filter_date_start'] = $filter_date_start;
}
if (!empty($filter_date_end)) {
    $where_clauses[] = "o.order_date <= ?"; $params[] = $filter_date_end; $param_types .= "s"; $query_string_params['filter_date_end'] = $filter_date_end;
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
$sql_data_base = "SELECT 
                    o.order_id, o.cssale_docno, cs.custname, o.status, o.priority,
                    CONCAT_WS(', ', ori.moo, ori.mooban, ori.tambon, ori.amphoe, ori.province) AS customer_full_address,
                    cs.shipaddr AS cssale_shipaddr,
                    o.product_details, o.order_date,
                    t_org.origin_name AS transport_origin_name,
                    s.staff_name AS assigned_staff_name,
                    CONCAT(v.vehicle_name, ' (', v.vehicle_plate, ')') AS assigned_vehicle_info,
                    o.created_at, o.updated_at
                FROM orders o
                LEFT JOIN cssale cs ON o.cssale_docno = cs.docno COLLATE utf8mb4_unicode_ci
                LEFT JOIN origin ori ON o.customer_address_origin_id = ori.id
                LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id
                LEFT JOIN staff s ON o.assigned_staff_id = s.staff_id
                LEFT JOIN vehicles v ON o.assigned_vehicle_id = v.vehicle_id";
$sql_data_final = $sql_data_base . $sql_where . " ORDER BY o.updated_at DESC, o.order_id DESC LIMIT ? OFFSET ?";
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
        $row['order_date_formatted'] = !empty($row['order_date']) ? date("d/m/Y", strtotime($row['order_date'])) : '-';
        $row['updated_at_formatted'] = !empty($row['updated_at']) ? date("d/m/Y H:i", strtotime($row['updated_at'])) : '-';
        $row['product_details_escaped'] = htmlspecialchars($row['product_details']);
        $row['customer_display_address'] = htmlspecialchars(!empty($row['customer_full_address']) ? $row['customer_full_address'] : $row['cssale_shipaddr']);
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
        'items_per_page' => (int)$items_per_page,
        'base_query_string' => $base_query_string
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
    <title>รายการใบสั่งซื้อทั้งหมด</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        .loading-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background-color: rgba(255, 255, 255, 0.7); z-index: 9999;
            display: flex; align-items: center; justify-content: center;
        }
        .loading-overlay .spinner-border { width: 3rem; height: 3rem; }
    </style>
</head>
<body>
    <div class="loading-overlay" style="display: none;">
        <div class="spinner-border text-danger" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">รายการใบสั่งซื้อทั้งหมด</h2>
            <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-2"></i>กลับหน้าหลัก</a>
        </div>

        <form id="filterForm" method="GET" action="all_orders.php" class="filter-form mb-4 p-3 border rounded bg-light">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="search_term">ค้นหา:</label>
                    <input type="text" class="form-control form-control-sm" id="search_term" name="search_term" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="ID, เลขที่บิล, ชื่อลูกค้า">
                </div>
                <div class="form-group col-md-2">
                    <label for="filter_status">สถานะ:</label>
                    <select class="form-control form-control-sm select2-filter" id="filter_status" name="filter_status">
                        <?php echo $status_options_filter; ?>
                    </select>
                </div>
                <div class="form-group col-md-2">
                    <label for="filter_priority">ความเร่งด่วน:</label>
                    <select class="form-control form-control-sm select2-filter" id="filter_priority" name="filter_priority">
                         <?php echo $priority_options_filter; ?>
                    </select>
                </div>
                <!-- *** เพิ่มส่วนนี้: ซ่อน/แสดง filter สาขาสำหรับ Admin *** -->
                <?php if (isset($_SESSION['role_level']) && $_SESSION['role_level'] == 4): ?>
                <div class="form-group col-md-2">
                    <label for="filter_transport_origin">ต้นทางขนส่ง:</label>
                    <select class="form-control form-control-sm select2-filter" id="filter_transport_origin" name="filter_transport_origin">
                        <?php echo $transport_origin_options_filter; ?>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            <div class="form-row">
                 <div class="form-group col-md-3">
                    <label for="filter_date_start">วันที่สั่ง (เริ่มต้น):</label>
                    <input type="date" class="form-control form-control-sm" id="filter_date_start" name="filter_date_start" value="<?php echo htmlspecialchars($filter_date_start); ?>">
                </div>
                <div class="form-group col-md-3">
                    <label for="filter_date_end">วันที่สั่ง (สิ้นสุด):</label>
                    <input type="date" class="form-control form-control-sm" id="filter_date_end" name="filter_date_end" value="<?php echo htmlspecialchars($filter_date_end); ?>">
                </div>
                <div class="form-group col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary btn-sm mr-2"><i class="fas fa-search"></i> กรองข้อมูล</button>
                    <a href="<?php echo BASE_URL; ?>pages/all_orders.php" class="btn btn-secondary btn-sm"><i class="fas fa-undo"></i> ล้างค่า</a>
                </div>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-2" id="items-count-info">
            <small>แสดง <?php echo $result_orders_mysqli ? $result_orders_mysqli->num_rows : 0; ?> จากทั้งหมด <?php echo $total_items; ?> รายการ</small>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>เลขที่บิล</th><th>ลูกค้า</th><th>ที่อยู่</th><th>สินค้า</th>
                        <th>ต้นทาง</th><th>คนส่ง</th><th>รถ</th><th>วันที่สั่ง</th>
                        <th>ความเร่งด่วน</th><th>สถานะ</th><th>อัปเดตล่าสุด</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <?php if (!empty($orders_data_array)): ?>
                        <?php foreach($orders_data_array as $row): ?>
                            <?php 
                                $status_class = 'status-' . str_replace([' ', '/'], ['-', '-'], strtolower(htmlspecialchars($row['status'])));
                                $priority_class = 'priority-' . str_replace(' ', '-', htmlspecialchars($row['priority']));
                            ?>
                            <tr id="order-row-<?php echo htmlspecialchars($row['order_id']); ?>" class="<?php echo $status_class; ?>">
                                <td><?php echo htmlspecialchars($row['cssale_docno']); ?></td>
                                <td><?php echo htmlspecialchars($row['custname']); ?></td>
                                <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo $row['customer_display_address']; ?>"><?php echo $row['customer_display_address']; ?></td>
                                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo $row['product_details_escaped']; ?>"><?php echo $row['product_details_escaped']; ?></td>
                                <td><?php echo htmlspecialchars($row['transport_origin_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['assigned_staff_name'] ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['assigned_vehicle_info'] ?: '-'); ?></td>
                                <td><?php echo $row['order_date_formatted']; ?></td>
                                <td class="<?php echo $priority_class; ?>"><?php echo htmlspecialchars($row['priority']); ?></td>
                                <td class="status-cell"><?php echo htmlspecialchars($row['status']); ?></td>
                                <td><?php echo $row['updated_at_formatted']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center">ไม่พบข้อมูลตามเงื่อนไขที่ระบุ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <nav aria-label="Page navigation" id="paginationContainer"></nav>
        <?php if(isset($conn)) $conn->close(); ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            function getBaseUrl() {
                return "<?php echo BASE_URL; ?>";
            }
            function renderPagination(totalPages, currentPage, baseQueryString) {
                if (totalPages <= 1) { $('#paginationContainer').html(''); return; }
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

            renderPagination(<?php echo $total_pages; ?>, <?php echo $current_page; ?>, '<?php echo $base_query_string; ?>');

            function buildTableRow(row) {
                let statusClass = 'status-' + row.status.toLowerCase().replace(/[\s\/]/g, '-');
                let priorityClass = 'priority-' + row.priority.replace(' ', '-');
                return `
                    <tr id="order-row-${row.order_id}" class="${statusClass}">
                        <td>${row.order_id}</td>
                        <td>${row.cssale_docno}</td>
                        <td>${row.custname || '-'}</td>
                        <td style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${row.customer_display_address}">${row.customer_display_address}</td>
                        <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="${row.product_details_escaped}">${row.product_details_escaped}</td>
                        <td>${row.transport_origin_name || '-'}</td>
                        <td>${row.assigned_staff_name || '-'}</td>
                        <td>${row.assigned_vehicle_info || '-'}</td>
                        <td>${row.order_date_formatted}</td>
                        <td class="${priorityClass}">${row.priority}</td>
                        <td class="status-cell">${row.status}</td>
                        <td>${row.updated_at_formatted}</td>
                    </tr>`;
            }

            function fetchData(page = 1) {
                let formData = $('#filterForm').serialize();
                formData += `&page=${page}`;
                let currentUrl = getBaseUrl() + 'pages/all_orders.php?' + formData;
                history.pushState({path: currentUrl}, '', currentUrl);
                $('.loading-overlay').show();
                $.ajax({
                    url: getBaseUrl() + 'pages/all_orders.php', type: 'GET', data: formData, dataType: 'json',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    success: function(response) {
                        $('#ordersTableBody').empty();
                        if (response.orders && response.orders.length > 0) {
                            response.orders.forEach(row => $('#ordersTableBody').append(buildTableRow(row)));
                        } else {
                            $('#ordersTableBody').append('<tr><td colspan="12" class="text-center">ไม่พบข้อมูลตามเงื่อนไขที่ระบุ</td></tr>');
                        }
                        $('#items-count-info').html(`<small>แสดง ${response.orders ? response.orders.length : 0} จากทั้งหมด ${response.total_items} รายการ (หน้า ${response.current_page} จาก ${response.total_pages})</small>`);
                        renderPagination(response.total_pages, response.current_page, response.base_query_string);
                        $('[data-toggle="tooltip"]').tooltip('dispose').tooltip();
                    },
                    error: function() {
                        Swal.fire({icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถโหลดข้อมูลได้ กรุณาลองใหม่อีกครั้ง'});
                    },
                    complete: function() {
                        $('.loading-overlay').hide();
                    }
                });
            }

            $('.select2-filter').select2({ allowClear: true, placeholder: "ทั้งหมด" });
            $('[data-toggle="tooltip"]').tooltip();

            $('#filterForm').on('submit', e => { e.preventDefault(); fetchData(1); });

            $('#paginationContainer').on('click', 'a.page-link', function(e) {
                e.preventDefault();
                const page = $(this).data('page');
                if (page && !$(this).closest('.page-item').is('.disabled, .active')) {
                    fetchData(page);
                }
            });
        });
    </script>
</body>
</html>
