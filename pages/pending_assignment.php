<?php
// pages/pending_assignment.php
require_once '../php/check_session.php';
// สิทธิ์ที่ต้องการสำหรับหน้านี้: ระดับ 2, 3, 4
require_login([2, 3, 4]);

require_once '../php/db_connect.php';

// กำหนด BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

$sql_orders = "SELECT o.order_id, o.cssale_docno, cs.custname, CONCAT_WS(', ', ori.moo, ori.mooban, ori.tambon, ori.amphoe, ori.province) AS customer_full_address, cs.shipaddr AS cssale_shipaddr, o.product_details, o.priority, o.order_date, t_org.origin_name AS transport_origin_name FROM orders o LEFT JOIN cssale cs ON o.cssale_docno = cs.docno COLLATE utf8mb4_unicode_ci LEFT JOIN origin ori ON o.customer_address_origin_id = ori.id LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id WHERE o.status = 'รับเรื่อง' ORDER BY CASE o.priority WHEN 'ด่วนที่สุด' THEN 1 WHEN 'ด่วน' THEN 2 WHEN 'ปกติ' THEN 3 ELSE 4 END ASC, o.order_date ASC, o.created_at ASC";
$result_orders = $conn->query($sql_orders);

$staff_options = "";
$sql_staff = "SELECT staff_id, staff_name FROM staff ORDER BY staff_name";
$result_staff = $conn->query($sql_staff);
if ($result_staff && $result_staff->num_rows > 0) { while($row = $result_staff->fetch_assoc()) { $staff_options .= "<option value='" . htmlspecialchars($row['staff_id']) . "'>" . htmlspecialchars($row['staff_name']) . "</option>"; } }

$vehicle_options = "";
$sql_vehicles = "SELECT vehicle_id, CONCAT(vehicle_name, ' (', vehicle_plate, ')') AS vehicle_display FROM vehicles ORDER BY vehicle_name";
$result_vehicles = $conn->query($sql_vehicles);
if ($result_vehicles && $result_vehicles->num_rows > 0) { while($row = $result_vehicles->fetch_assoc()) { $vehicle_options .= "<option value='" . htmlspecialchars($row['vehicle_id']) . "'>" . htmlspecialchars($row['vehicle_display']) . "</option>"; } }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการรอจัดคน/รถ</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        .action-buttons button, .action-buttons a { margin: 0 2px; }
        .select2-container { width: 100% !important; }
        .select2-container .select2-selection--single { height: calc(1.5em + .75rem + 2px) !important; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: calc(1.5em + .75rem) !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(1.5em + .75rem) !important; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">รายการรอจัดคน/รถ (สถานะ: รับเรื่อง)</h2>
            <div>
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>กลับหน้าหลัก</a>
                <button class="btn btn-info btn-sm" onclick="location.reload();"><i class="fas fa-sync-alt"></i> รีเฟรช</button>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>ID ติดตาม</th><th>เลขที่บิล</th><th>ชื่อลูกค้า</th><th>ที่อยู่จัดส่ง</th>
                        <th>หมายเหตุ</th><th>ต้นทางขนส่ง</th><th>วันที่สั่ง</th>
                        <th>ความเร่งด่วน</th><th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody id="orders-table-body">
                    <?php if ($result_orders && $result_orders->num_rows > 0): ?>
                        <?php while($row = $result_orders->fetch_assoc()): ?>
                            <tr id="order-row-<?php echo htmlspecialchars($row['order_id']); ?>" class="priority-<?php echo htmlspecialchars($row['priority']); ?>">
                                <td><?php echo htmlspecialchars($row['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['cssale_docno']); ?></td>
                                <td><?php echo htmlspecialchars($row['custname']); ?></td>
                                <td><?php echo !empty($row['customer_full_address']) ? htmlspecialchars($row['customer_full_address']) : htmlspecialchars($row['cssale_shipaddr']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($row['product_details'])); ?></td>
                                <td><?php echo htmlspecialchars($row['transport_origin_name']); ?></td>
                                <td><?php echo date("d/m/Y", strtotime($row['order_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['priority']); ?></td>
                                <td class="action-buttons">
                                    <button class="btn btn-primary btn-sm manage-delivery-btn" data-orderid="<?php echo htmlspecialchars($row['order_id']); ?>" data-docno="<?php echo htmlspecialchars($row['cssale_docno']); ?>" data-custname="<?php echo htmlspecialchars($row['custname']); ?>" data-address="<?php echo htmlspecialchars(!empty($row['customer_full_address']) ? $row['customer_full_address'] : $row['cssale_shipaddr']); ?>" data-details="<?php echo htmlspecialchars($row['product_details']); ?>"><i class="fas fa-truck"></i> จัดการ</button>
                                    <?php if(has_role([2, 4])): ?>
                                    <button class="btn btn-danger btn-sm cancel-btn" data-orderid="<?php echo htmlspecialchars($row['order_id']); ?>" data-docno="<?php echo htmlspecialchars($row['cssale_docno']); ?>"><i class="fas fa-times-circle"></i> ยกเลิก</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="text-center">ไม่มีรายการที่รอจัดคน/รถ</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal สำหรับจัดการจัดส่ง -->
    <div class="modal fade" id="assignDeliveryModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignDeliveryModalLabel">จัดการจัดส่งสำหรับบิลเลขที่: <span id="modal_docno_display"></span></h5>
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
                                <select class="form-control select2-modal" id="assigned_staff_id" name="assigned_staff_id" required><option value="">-- เลือกคนส่งของ --</option><?php echo $staff_options; ?></select>
                            </div>
                            <div class="form-group col-md-6">
                                <label for="assigned_vehicle_id">เลือกรถ:</label>
                                <select class="form-control select2-modal" id="assigned_vehicle_id" name="assigned_vehicle_id" required><option value="">-- เลือกรถ --</option><?php echo $vehicle_options; ?></select>
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

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function() {
            $('.select2-modal').select2({ placeholder: "-- กรุณาเลือก --", allowClear: true, dropdownParent: $('#assignDeliveryModal') });
            
            function getBaseUrl() {
                return "<?php echo BASE_URL; ?>";
            }

            // *** แก้ไข: ใส่โค้ดที่ถูกต้องกลับเข้ามา ***
            $('#orders-table-body').on('click', '.manage-delivery-btn', function() {
                const orderId = $(this).data('orderid');
                const docNo = $(this).data('docno');
                const custName = $(this).data('custname');
                const address = $(this).data('address');
                const details = $(this).data('details');

                $('#modal_order_id').val(orderId);
                $('#modal_docno_hidden').val(docNo);
                $('#modal_docno_display').text(docNo);
                $('#modal_custname').text(custName);
                $('#modal_address').text(address);
                $('#modal_details').text(details);
                
                $('#assigned_staff_id').val(null).trigger('change');
                $('#assigned_vehicle_id').val(null).trigger('change');

                $('#assignDeliveryModal').modal('show');
            });

            $('#assignDeliveryForm').on('submit', function(event) {
                event.preventDefault();

                const orderId = $('#modal_order_id').val();
                const docNoForAlert = $('#modal_docno_hidden').val();
                const staffId = $('#assigned_staff_id').val();
                const vehicleId = $('#assigned_vehicle_id').val();

                if (!staffId || !vehicleId) {
                    Swal.fire({ icon: 'error', title: 'ข้อมูลไม่ครบถ้วน', text: 'กรุณาเลือกคนส่งของและรถ' });
                    return;
                }

                Swal.fire({
                    title: 'ยืนยันการจัดสรร',
                    text: `คุณต้องการจัดสรรคนส่งของและรถสำหรับบิลเลขที่: ${docNoForAlert} ใช่หรือไม่?`,
                    icon: 'question',
                    showCancelButton: true, confirmButtonColor: '#28a745', cancelButtonColor: '#d33',
                    confirmButtonText: 'ใช่, ยืนยัน!', cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'กำลังบันทึก...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        $.ajax({
                            url: getBaseUrl() + 'php/assign_delivery.php',
                            type: 'POST',
                            data: {
                                order_id: orderId,
                                assigned_staff_id: staffId,
                                assigned_vehicle_id: vehicleId
                            },
                            dataType: 'json',
                            success: function(response) {
                                Swal.close();
                                if (response.status === 'success') {
                                    Swal.fire({ icon: 'success', title: 'จัดสรรสำเร็จ!', text: response.message, timer: 1500, showConfirmButton: false })
                                    .then(() => {
                                        $('#assignDeliveryModal').modal('hide');
                                        $('#order-row-' + orderId).fadeOut(500, function() { $(this).remove(); });
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
                    }
                });
            });

            // Cancel button handler
            $('#orders-table-body').on('click', '.cancel-btn', function() {
                const orderId = $(this).data('orderid');
                const docNo = $(this).data('docno');
                Swal.fire({
                    title: 'ยืนยันการยกเลิก', text: `คุณต้องการยกเลิกบิลเลขที่: ${docNo} ใช่หรือไม่?`, icon: 'warning',
                    showCancelButton: true, confirmButtonColor: '#d33', cancelButtonColor: '#3085d6',
                    confirmButtonText: 'ใช่, ยกเลิกเลย!', cancelButtonText: 'ไม่'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({ title: 'กำลังดำเนินการ...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                        $.ajax({
                            url: getBaseUrl() + 'php/cancel_order.php', type: 'POST', data: { order_id: orderId }, dataType: 'json',
                            success: function(response) {
                                Swal.close();
                                if (response.status === 'success') {
                                    Swal.fire({icon: 'success', title: 'ยกเลิกสำเร็จ!', text: response.message, timer: 1500, showConfirmButton: false});
                                    $('#order-row-' + orderId).fadeOut(500, function() { $(this).remove(); });
                                } else {
                                    Swal.fire({icon: 'error', title: 'เกิดข้อผิดพลาด!', text: response.message});
                                }
                            },
                            error: function() {
                                Swal.close();
                                Swal.fire({icon: 'error', title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ'});
                            }
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
