<?php
require_once '../php/check_session.php';
require_permission('orders.assign', [2, 3, 4]);
require_once '../php/db_connect.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

$search_docno = isset($_GET['search_docno']) ? trim($conn->real_escape_string($_GET['search_docno'])) : '';
$is_ajax_request = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

if ($is_ajax_request) {
    header('Content-Type: application/json');

    $items_per_page = 20;
    $current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $offset = ($current_page - 1) * $items_per_page;

    $where_clauses = ["o.status = 'รับเรื่อง'"];
    $params = [];
    $param_types = '';

if (should_limit_to_assigned_origin()) {
    $where_clauses[] = 'o.transport_origin_id = ?';
    $params[] = (int) current_assigned_transport_origin_id();
    $param_types .= 'i';
}

    if ($search_docno !== '') {
        $where_clauses[] = 'o.cssale_docno LIKE ?';
        $params[] = '%' . $search_docno . '%';
        $param_types .= 's';
    }

    $sql_from = " FROM orders o
                  LEFT JOIN cssale cs ON o.cssale_docno = cs.docno
                  LEFT JOIN origin ori ON o.customer_address_origin_id = ori.id
                  LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id";
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
                    t_org.origin_name AS transport_origin_name"
                . $sql_from . $sql_where .
                " ORDER BY o.order_date DESC, o.created_at DESC
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
        $rows[] = $row;
    }
    $stmt_data->close();

    $sql_count = "SELECT COUNT(*) AS total FROM orders o" . $sql_where;
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

$staff_options = '';
$result_staff = $conn->query("SELECT staff_id, staff_name, default_vehicle_id FROM staff WHERE active = 1 ORDER BY staff_name");
if ($result_staff) {
    while ($row = $result_staff->fetch_assoc()) {
        $default_vehicle_attr = $row['default_vehicle_id'] !== null
            ? " data-default-vehicle='" . htmlspecialchars($row['default_vehicle_id']) . "'"
            : '';
        $staff_options .= "<option value='" . htmlspecialchars($row['staff_id']) . "'" . $default_vehicle_attr . ">" . htmlspecialchars($row['staff_name']) . "</option>";
    }
}

$vehicle_options = '';
$result_vehicles = $conn->query("SELECT vehicle_id, CONCAT(vehicle_name, ' (', vehicle_plate, ')') AS vehicle_display FROM vehicles WHERE active = 1 ORDER BY vehicle_name, vehicle_plate");
if ($result_vehicles) {
    while ($row = $result_vehicles->fetch_assoc()) {
        $vehicle_options .= "<option value='" . htmlspecialchars($row['vehicle_id']) . "'>" . htmlspecialchars($row['vehicle_display']) . "</option>";
    }
}

$transport_origin_options_html = '<option value="">-- เลือกต้นทางขนส่ง --</option>';
$result_transport_origins = $conn->query("SELECT transport_origin_id, origin_name FROM transport_origins ORDER BY origin_name");
if ($result_transport_origins) {
    while ($origin_row = $result_transport_origins->fetch_assoc()) {
        $transport_origin_options_html .= "<option value='" . htmlspecialchars($origin_row['transport_origin_id']) . "'>" . htmlspecialchars($origin_row['origin_name']) . "</option>";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการรอจัดคน/รถ</title>
    <meta name="theme-color" content="#dc2626">
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        .action-buttons { white-space: nowrap; }
        .action-buttons button { margin: 0 2px; }
        .filter-card { box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .select2-container { width: 100% !important; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">รายการรอจัดคน/รถ</h2>
            <div>
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>กลับหน้าหลัก</a>
                <button class="btn btn-info btn-sm" id="refreshBtn"><i class="fas fa-sync-alt"></i> รีเฟรช</button>
            </div>
        </div>

        <div class="p-3 border rounded bg-light mb-4 filter-card">
            <form id="filterForm" class="mb-0">
                <div class="form-row align-items-end">
                    <div class="col-md-4">
                        <label for="search_docno">ค้นหาเลขที่บิล</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                            </div>
                            <input type="text" name="search_docno" id="search_docno" class="form-control" placeholder="กรอกเลขที่บิล..." value="<?php echo htmlspecialchars($search_docno); ?>">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <button class="btn btn-primary" type="submit">ค้นหา</button>
                        <button type="button" id="resetBtn" class="btn btn-outline-secondary ml-2">ล้างค่า</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-responsive bg-white rounded shadow-sm">
            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <span id="items-count-info">กำลังโหลดข้อมูล...</span>
            </div>
            <table class="table table-bordered table-hover table-striped mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>ID ติดตาม</th>
                        <th>เลขที่บิล</th>
                        <th>ชื่อลูกค้า</th>
                        <th>ที่อยู่ลูกค้า</th>
                        <th>หมายเหตุ</th>
                        <th>ต้นทางขนส่ง</th>
                        <th>วันที่สั่ง</th>
                        <th>ความเร่งด่วน</th>
                        <th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody id="orders-table-body">
                    <tr><td colspan="9" class="text-center py-4">กำลังโหลดข้อมูล...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-3">
            <nav id="paginationContainer"></nav>
        </div>
    </div>

    <div class="modal fade" id="assignDeliveryModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">จัดการจัดส่งสำหรับบิลเลขที่: <span id="modal_docno_display"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <form id="assignDeliveryForm">
                    <div class="modal-body">
                        <input type="hidden" id="modal_order_id" name="order_id">
                        <input type="hidden" id="modal_docno_hidden" name="docno_for_alert">
                        <p><strong>ลูกค้า:</strong> <span id="modal_custname"></span></p>
                        <p><strong>ที่อยู่:</strong> <span id="modal_address"></span></p>
                        <p><strong>หมายเหตุ:</strong> <span id="modal_details"></span></p>
                        <hr>
                        <div class="form-row">
                            <div class="form-group col-md-6">
                                <label for="assigned_staff_id">เลือกคนส่งของ:</label>
                                <select class="form-control select2-modal" id="assigned_staff_id" name="assigned_staff_id" required>
                                    <option value="">-- เลือกคนส่งของ --</option>
                                    <?php echo $staff_options; ?>
                                </select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="assigned_vehicle_id">เลือกรถ:</label>
                                <select class="form-control select2-modal" id="assigned_vehicle_id" name="assigned_vehicle_id" required>
                                    <option value="">-- เลือกรถ --</option>
                                    <?php echo $vehicle_options; ?>
                                </select>
                                <small class="form-text text-muted" id="defaultVehicleHint">เมื่อเลือกพนักงาน ระบบจะเลือกคันประจำให้อัตโนมัติถ้ามีการกำหนดไว้</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                        <button type="submit" class="btn btn-success">บันทึกการจัดส่ง</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="changeOriginModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">เปลี่ยนต้นทางขนส่ง</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="currentOrigin">ต้นทางปัจจุบัน:</label>
                        <input type="text" class="form-control" id="currentOrigin" readonly>
                    </div>
                    <div class="form-group">
                        <label for="newOrigin">ต้นทางใหม่:</label>
                        <select class="form-control" id="newOrigin" required>
                            <?php echo $transport_origin_options_html; ?>
                        </select>
                    </div>
                    <input type="hidden" id="changeOrderId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" id="saveOriginBtn">บันทึกการเปลี่ยน</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="detailsModal" tabindex="-1" role="dialog" aria-labelledby="detailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel">รายละเอียดการจัดส่ง</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div id="modal-content-placeholder" class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
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
        let currentPage = 1;

        function escapeHtml(value) {
            return $('<div>').text(value || '').html();
        }

        function escapeAttr(value) {
            return String(value || '').replace(/"/g, '&quot;');
        }

        function applyDefaultVehicleForSelectedStaff() {
            const selectedStaff = $('#assigned_staff_id').find(':selected');
            const defaultVehicleId = selectedStaff.data('defaultVehicle');

            if (defaultVehicleId && $('#assigned_vehicle_id option[value="' + defaultVehicleId + '"]').length) {
                $('#assigned_vehicle_id').val(String(defaultVehicleId)).trigger('change');
                $('#defaultVehicleHint').text('เลือกรถประจำของพนักงานให้แล้ว แต่ยังเปลี่ยนเป็นคันอื่นได้');
                return;
            }

            $('#defaultVehicleHint').text('พนักงานคนนี้ยังไม่ได้กำหนดรถประจำ หรือรถประจำถูกปิดใช้งาน');
        }

        function fetchData(page = 1) {
            currentPage = page;
            $('#orders-table-body').html('<tr><td colspan="9" class="text-center py-4">กำลังโหลดข้อมูล...</td></tr>');

            $.ajax({
                url: 'pending_assignment.php',
                type: 'GET',
                dataType: 'json',
                data: {
                    page: page,
                    search_docno: $('#search_docno').val()
                },
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function(response) {
                    if (response.status !== 'success') {
                        $('#orders-table-body').html('<tr><td colspan="9" class="text-center text-danger py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>');
                        return;
                    }

                    renderTable(response.orders || []);
                    renderPagination(response.total_pages || 0, response.current_page || 1);
                    const start = response.total_items > 0 ? ((response.current_page - 1) * 20) + 1 : 0;
                    const end = Math.min(response.current_page * 20, response.total_items || 0);
                    $('#items-count-info').html(`แสดงผล <strong>${start}</strong> - <strong>${end}</strong> จากทั้งหมด <strong>${response.total_items}</strong> รายการ`);
                },
                error: function() {
                    $('#orders-table-body').html('<tr><td colspan="9" class="text-center text-danger py-4">เกิดข้อผิดพลาดในการโหลดข้อมูล</td></tr>');
                }
            });
        }

        function renderTable(orders) {
            const tbody = $('#orders-table-body');
            tbody.empty();

            if (!orders.length) {
                tbody.html('<tr><td colspan="9" class="text-center py-4">ไม่มีรายการที่รอจัดคน/รถ</td></tr>');
                return;
            }

            orders.forEach(function(row) {
                const address = row.customer_full_address || row.cssale_shipaddr || '-';
                tbody.append(`
                    <tr id="order-row-${row.order_id}" class="priority-${row.priority || ''}">
                        <td>${row.order_id}</td>
                        <td>${escapeHtml(row.cssale_docno || '-')}</td>
                        <td>${escapeHtml(row.custname || '-')}</td>
                        <td>${escapeHtml(row.customer_full_address || '-')}</td>
                        <td>${escapeHtml(row.cssale_shipaddr || '-')}</td>
                        <td>${escapeHtml(row.transport_origin_name || '-')}</td>
                        <td>${escapeHtml(row.order_date_formatted || '-')}</td>
                        <td>${escapeHtml(row.priority || '-')}</td>
                        <td class="action-buttons">
                            <button class="btn btn-info btn-sm view-details-btn" data-orderid="${row.order_id}">
                                <i class="fas fa-eye"></i> ดูรายละเอียด
                            </button>
                            <button class="btn btn-primary btn-sm manage-delivery-btn"
                                data-orderid="${row.order_id}"
                                data-docno="${escapeAttr(row.cssale_docno || '')}"
                                data-custname="${escapeAttr(row.custname || '')}"
                                data-address="${escapeAttr(address)}"
                                data-details="${escapeAttr(row.product_details || '')}">
                                <i class="fas fa-truck"></i> จัดการ
                            </button>
                            <button class="btn btn-info btn-sm change-origin-btn"
                                data-orderid="${row.order_id}"
                                data-current-origin="${escapeAttr(row.transport_origin_name || '')}">
                                <i class="fas fa-exchange-alt"></i> เปลี่ยนต้นทาง
                            </button>
                            <?php if (has_permission('orders.cancel', [2, 3, 4])): ?>
                            <button class="btn btn-danger btn-sm cancel-btn" data-orderid="${row.order_id}" data-docno="${escapeAttr(row.cssale_docno || '')}">
                                <i class="fas fa-times-circle"></i> ยกเลิก
                            </button>
                            <?php endif; ?>
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
            $('.select2-modal').select2({
                placeholder: '-- กรุณาเลือก --',
                allowClear: true,
                dropdownParent: $('#assignDeliveryModal')
            });

            fetchData(1);

            $('#filterForm').on('submit', function(e) {
                e.preventDefault();
                fetchData(1);
            });

            $('#resetBtn').on('click', function() {
                $('#search_docno').val('');
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

            $('#orders-table-body').on('click', '.view-details-btn', function() {
                const orderId = $(this).data('orderid');
                const modalPlaceholder = $('#modal-content-placeholder');

                modalPlaceholder.html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"></div></div>');
                $('#detailsModal').modal('show');

                $.ajax({
                    url: '<?php echo BASE_URL; ?>php/get_order_details.php',
                    type: 'GET',
                    data: { id: orderId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            const d = response.data;
                            let staffInfo = escapeHtml(d.assigned_staff || '-');
                            if (d.assigned_staff_phone) {
                                staffInfo += ` (${escapeHtml(d.assigned_staff_phone)})`;
                            }
                            let salesmanInfo = d.salesman_code
                                ? `${escapeHtml(d.salesman_code)} - ${escapeHtml(d.salesman_name || '')}`
                                : '-';
                            if (d.salesman_phone) {
                                salesmanInfo += ` (${escapeHtml(d.salesman_phone)})`;
                            }

                            const html = `
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h5 class="text-primary"><i class="fas fa-file-invoice mr-2"></i>ข้อมูลใบสั่งซื้อ</h5>
                                            <table class="table table-sm table-bordered">
                                                <tr><th style="width: 35%;">ID ติดตาม</th><td>${escapeHtml(d.order_id)}</td></tr>
                                                <tr><th>เลขที่บิล</th><td>${escapeHtml(d.cssale_docno || '-')}</td></tr>
                                                <tr><th>วันที่สั่ง</th><td>${escapeHtml(d.order_date_formatted || '-')}</td></tr>
                                                <tr><th>สถานะ</th><td><span class="badge ${escapeHtml(d.status_badge || 'badge-light-secondary')} p-2">${escapeHtml(d.status || '-')}</span></td></tr>
                                                <tr><th>อัปเดตล่าสุด</th><td>${escapeHtml(d.updated_at_formatted || '-')}</td></tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <h5 class="text-primary"><i class="fas fa-user-tie mr-2"></i>ข้อมูลลูกค้า</h5>
                                            <table class="table table-sm table-bordered">
                                                <tr><th style="width: 35%;">ชื่อลูกค้า</th><td>${escapeHtml(d.custname || '-')}</td></tr>
                                                <tr><th>ที่อยู่ (ตามบิล)</th><td>${escapeHtml(d.shipaddr || '-')}</td></tr>
                                                <tr><th>พนักงานขาย</th><td>${salesmanInfo}</td></tr>
                                            </table>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <h5 class="text-primary"><i class="fas fa-shipping-fast mr-2"></i>ข้อมูลการจัดส่ง</h5>
                                            <table class="table table-sm table-bordered">
                                                <tr><th style="width: 25%;">ต้นทางขนส่ง</th><td>${escapeHtml(d.transport_origin || '-')}</td></tr>
                                                <tr><th>ปลายทาง</th><td>${escapeHtml(d.full_address || '-')}</td></tr>
                                                <tr><th>คนขับรถ</th><td>${staffInfo}</td></tr>
                                                <tr><th>รถที่ใช้</th><td>${escapeHtml(d.assigned_vehicle || '-')}</td></tr>
                                                <tr><th>หมายเหตุ</th><td>${escapeHtml(d.product_details || '-')}</td></tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            `;

                            modalPlaceholder.html(html);
                        } else {
                            modalPlaceholder.html(`<p class="text-danger">${response.message}</p>`);
                        }
                    },
                    error: function() {
                        modalPlaceholder.html('<p class="text-danger">เกิดข้อผิดพลาดในการเชื่อมต่อ</p>');
                    }
                });
            });

            $('#orders-table-body').on('click', '.manage-delivery-btn', function() {
                $('#modal_order_id').val($(this).data('orderid'));
                $('#modal_docno_hidden').val($(this).data('docno'));
                $('#modal_docno_display').text($(this).data('docno'));
                $('#modal_custname').text($(this).data('custname'));
                $('#modal_address').text($(this).data('address'));
                $('#modal_details').text($(this).data('details') || '-');
                $('#assigned_staff_id').val(null).trigger('change');
                $('#assigned_vehicle_id').val(null).trigger('change');
                $('#defaultVehicleHint').text('เมื่อเลือกพนักงาน ระบบจะเลือกคันประจำให้อัตโนมัติถ้ามีการกำหนดไว้');
                $('#assignDeliveryModal').modal('show');
            });

            $('#orders-table-body').on('click', '.change-origin-btn', function() {
                $('#changeOrderId').val($(this).data('orderid'));
                $('#currentOrigin').val($(this).data('current-origin') || '-');
                $('#newOrigin').val('');
                $('#changeOriginModal').modal('show');
            });

            $('#assigned_staff_id').on('change', function() {
                if (!$(this).val()) {
                    $('#assigned_vehicle_id').val(null).trigger('change');
                    $('#defaultVehicleHint').text('เมื่อเลือกพนักงาน ระบบจะเลือกคันประจำให้อัตโนมัติถ้ามีการกำหนดไว้');
                    return;
                }

                applyDefaultVehicleForSelectedStaff();
            });

            $('#assignDeliveryForm').on('submit', function(e) {
                e.preventDefault();
                const orderId = $('#modal_order_id').val();
                const docNo = $('#modal_docno_hidden').val();
                const staffId = $('#assigned_staff_id').val();
                const vehicleId = $('#assigned_vehicle_id').val();

                if (!staffId || !vehicleId) {
                    Swal.fire({ icon: 'error', title: 'ข้อมูลไม่ครบถ้วน', text: 'กรุณาเลือกคนส่งของและรถ' });
                    return;
                }

                Swal.fire({
                    title: 'ยืนยันการจัดสรร',
                    text: `คุณต้องการจัดสรรคนส่งของและรถสำหรับบิลเลขที่: ${docNo} ใช่หรือไม่?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'ใช่, ยืนยัน!',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    $.ajax({
                        url: '<?php echo BASE_URL; ?>php/assign_delivery.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            order_id: orderId,
                            assigned_staff_id: staffId,
                            assigned_vehicle_id: vehicleId
                        },
                        success: function(response) {
                            Swal.close();
                            if (response.status === 'success') {
                                Swal.fire({ icon: 'success', title: 'จัดสรรสำเร็จ!', text: response.message, timer: 1500, showConfirmButton: false })
                                    .then(() => {
                                        $('#assignDeliveryModal').modal('hide');
                                        fetchData(currentPage);
                                    });
                            } else {
                                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด!', text: response.message });
                            }
                        },
                        error: function() {
                            Swal.close();
                            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ' });
                        }
                    });
                });
            });

            $('#saveOriginBtn').on('click', function() {
                const orderId = $('#changeOrderId').val();
                const newOriginId = $('#newOrigin').val();

                if (!newOriginId) {
                    Swal.fire({ icon: 'error', title: 'ข้อมูลไม่ครบถ้วน', text: 'กรุณาเลือกต้นทางขนส่งใหม่' });
                    return;
                }

                Swal.fire({
                    title: 'ยืนยันการเปลี่ยนต้นทาง',
                    text: 'ต้องการบันทึกการเปลี่ยนต้นทางขนส่งใช่หรือไม่?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'ใช่, บันทึก!',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    $.ajax({
                        url: '<?php echo BASE_URL; ?>php/change_transport_origin.php',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            order_id: orderId,
                            transport_origin_id: newOriginId
                        },
                        success: function(response) {
                            Swal.close();
                            if (response.status === 'success') {
                                Swal.fire({ icon: 'success', title: 'เปลี่ยนต้นทางสำเร็จ!', text: response.message, timer: 1500, showConfirmButton: false })
                                    .then(() => {
                                        $('#changeOriginModal').modal('hide');
                                        fetchData(currentPage);
                                    });
                            } else {
                                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด!', text: response.message });
                            }
                        },
                        error: function() {
                            Swal.close();
                            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ' });
                        }
                    });
                });
            });

            $('#orders-table-body').on('click', '.cancel-btn', function() {
                const orderId = $(this).data('orderid');
                const docNo = $(this).data('docno');

                Swal.fire({
                    title: 'ยืนยันการยกเลิก',
                    text: `คุณต้องการยกเลิกบิลเลขที่: ${docNo} ใช่หรือไม่?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ยกเลิกเลย!',
                    cancelButtonText: 'ไม่'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    Swal.fire({ title: 'กำลังดำเนินการ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    $.ajax({
                        url: '<?php echo BASE_URL; ?>php/cancel_order.php',
                        type: 'POST',
                        dataType: 'json',
                        data: { order_id: orderId },
                        success: function(response) {
                            Swal.close();
                            if (response.status === 'success') {
                                Swal.fire({ icon: 'success', title: 'ยกเลิกสำเร็จ!', text: response.message, timer: 1500, showConfirmButton: false })
                                    .then(() => fetchData(currentPage));
                            } else {
                                Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด!', text: response.message });
                            }
                        },
                        error: function() {
                            Swal.close();
                            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ' });
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>
