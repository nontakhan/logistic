<?php
// pages/pending_delivery.php
require_once '../php/check_session.php';
require_login([2, 3, 4]);
require_once '../php/db_connect.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

$search_docno = isset($_GET['search_docno']) ? trim($conn->real_escape_string($_GET['search_docno'])) : '';

$where_clauses = ["o.status = 'รอส่งของ'"];
$params = [];
$param_types = "";

if (is_logged_in() && $_SESSION['role_level'] != 4 && !empty($_SESSION['assigned_transport_origin_id'])) {
    $where_clauses[] = "o.transport_origin_id = ?";
    $params[] = $_SESSION['assigned_transport_origin_id'];
    $param_types .= "i";
}
if (!empty($search_docno)) {
    $where_clauses[] = "o.cssale_docno LIKE ?";
    $search_like = "%" . $search_docno . "%";
    $params[] = $search_like;
    $param_types .= "s";
}
$sql_where = " WHERE " . implode(" AND ", $where_clauses);

$sql = "SELECT o.order_id, o.cssale_docno, cs.custname, CONCAT_WS(', ', ori.moo, ori.mooban, ori.tambon, ori.amphoe, ori.province) AS customer_full_address, cs.shipaddr AS cssale_shipaddr, o.product_details, o.priority, o.order_date, t_org.origin_name AS transport_origin_name, s.staff_name AS assigned_staff_name, CONCAT(v.vehicle_name, ' (', v.vehicle_plate, ')') AS assigned_vehicle_info FROM orders o LEFT JOIN cssale cs ON o.cssale_docno = cs.docno COLLATE utf8mb4_unicode_ci LEFT JOIN origin ori ON o.customer_address_origin_id = ori.id LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id LEFT JOIN staff s ON o.assigned_staff_id = s.staff_id LEFT JOIN vehicles v ON o.assigned_vehicle_id = v.vehicle_id" . $sql_where . " ORDER BY o.order_date DESC, o.created_at DESC";
$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($param_types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการรอส่งของ</title>
    <!-- *** เพิ่ม: Favicon *** -->
    <meta name="theme-color" content="#dc2626">
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">

    <link rel="icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png">
    
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        .action-buttons { white-space: nowrap; }
        .action-buttons button, .action-buttons a { margin: 0 2px; }
        .filter-card { box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">รายการรอส่งของ</h2>
            <div>
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left mr-1"></i>กลับหน้าหลัก</a>
                <button class="btn btn-info btn-sm" onclick="location.reload();"><i class="fas fa-sync-alt"></i> รีเฟรช</button>
            </div>
        </div>

        <div class="p-3 border rounded bg-light mb-4 filter-card">
            <form method="GET" class="mb-0">
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
                        <a href="<?php echo BASE_URL; ?>pages/pending_delivery.php" class="btn btn-outline-secondary ml-2">ล้างค่า</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>ID ติดตาม</th><th>เลขที่บิล</th><th>ชื่อลูกค้า</th>
                        <th>ที่อยู่ลูกค้า</th>
                        <th>หมายเหตุ</th>
                        <th>ต้นทางขนส่ง</th><th>คนส่งของ</th><th>รถที่ใช้</th>
                        <th>วันที่สั่ง</th><th>ความเร่งด่วน</th><th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody id="orders-table-body">
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr id="order-row-<?php echo htmlspecialchars($row['order_id']); ?>" class="priority-<?php echo htmlspecialchars($row['priority']); ?>">
                                <td><?php echo htmlspecialchars($row['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['cssale_docno']); ?></td>
                                <td><?php echo htmlspecialchars($row['custname']); ?></td>
                                <td><?php echo htmlspecialchars(!empty($row['customer_full_address']) ? $row['customer_full_address'] : '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['cssale_shipaddr']); ?></td>
                                <td><?php echo htmlspecialchars($row['transport_origin_name']); ?></td>
                                <td><?php echo htmlspecialchars(!empty($row['assigned_staff_name']) ? $row['assigned_staff_name'] : '-'); ?></td>
                                <td><?php echo htmlspecialchars(!empty($row['assigned_vehicle_info']) ? $row['assigned_vehicle_info'] : '-'); ?></td>
                                <td><?php echo date("d/m/Y", strtotime($row['order_date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['priority']); ?></td>
                                <td class="action-buttons">
                                    <button class="btn btn-warning btn-sm confirm-delivery-btn" data-orderid="<?php echo htmlspecialchars($row['order_id']); ?>" data-docno="<?php echo htmlspecialchars($row['cssale_docno']); ?>"><i class="fas fa-truck-loading"></i> ยืนยันการส่ง</button>
                                    <button class="btn btn-info btn-sm edit-driver-btn" 
                                            data-orderid="<?php echo htmlspecialchars($row['order_id']); ?>" 
                                            data-docno="<?php echo htmlspecialchars($row['cssale_docno']); ?>" 
                                            data-custname="<?php echo htmlspecialchars($row['custname']); ?>"
                                            data-current-staff="<?php echo htmlspecialchars($row['assigned_staff_name']); ?>"
                                            data-current-vehicle="<?php echo htmlspecialchars($row['assigned_vehicle_info']); ?>"
                                            data-current-staff-id="<?php echo htmlspecialchars($row['assigned_staff_id'] ?? ''); ?>"
                                            data-current-vehicle-id="<?php echo htmlspecialchars($row['assigned_vehicle_id'] ?? ''); ?>">
                                        <i class="fas fa-user-edit"></i> แก้ไขคนขับ
                                    </button>
                                    <?php if(has_role([2, 4])): ?>
                                    <button class="btn btn-danger btn-sm cancel-btn" data-orderid="<?php echo htmlspecialchars($row['order_id']); ?>" data-docno="<?php echo htmlspecialchars($row['cssale_docno']); ?>"><i class="fas fa-times-circle"></i> ยกเลิก</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="12" class="text-center">ไม่พบข้อมูล</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal สำหรับแก้ไขคนขับรถ -->
    <div class="modal fade" id="editDriverModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">แก้ไขคนขับรถ</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="editDriverForm">
                        <div class="form-group">
                            <label for="editOrderInfo">ข้อมูลออเดอร์:</label>
                            <input type="text" class="form-control" id="editOrderInfo" readonly>
                        </div>
                        <div class="form-group">
                            <label for="editCurrentStaff">คนขับปัจจุบัน:</label>
                            <input type="text" class="form-control" id="editCurrentStaff" readonly>
                        </div>
                        <div class="form-group">
                            <label for="editCurrentVehicle">รถปัจจุบัน:</label>
                            <input type="text" class="form-control" id="editCurrentVehicle" readonly>
                        </div>
                        <div class="form-group">
                            <label for="newStaff">คนขับใหม่:</label>
                            <select class="form-control" id="newStaff" required>
                                <option value="">-- เลือกคนขับ --</option>
                                <?php
                                $sql_staff = "SELECT staff_id, staff_name FROM staff ORDER BY staff_name";
                                $result_staff = $conn->query($sql_staff);
                                if ($result_staff && $result_staff->num_rows > 0) {
                                    while($staff_row = $result_staff->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($staff_row['staff_id']) . "'>" . htmlspecialchars($staff_row['staff_name']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="newVehicle">รถใหม่:</label>
                            <select class="form-control" id="newVehicle" required>
                                <option value="">-- เลือกรถ --</option>
                                <?php
                                $sql_vehicles = "SELECT vehicle_id, CONCAT(vehicle_name, ' (', vehicle_plate, ')') AS vehicle_info FROM vehicles ORDER BY vehicle_name";
                                $result_vehicles = $conn->query($sql_vehicles);
                                if ($result_vehicles && $result_vehicles->num_rows > 0) {
                                    while($vehicle_row = $result_vehicles->fetch_assoc()) {
                                        echo "<option value='" . htmlspecialchars($vehicle_row['vehicle_id']) . "'>" . htmlspecialchars($vehicle_row['vehicle_info']) . "</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <input type="hidden" id="editOrderId">
                        <input type="hidden" id="editCurrentStaffId">
                        <input type="hidden" id="editCurrentVehicleId">
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
                    <button type="button" class="btn btn-primary" id="saveDriverBtn">บันทึกการเปลี่ยน</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        $(document).ready(function(){function t(){return"<?php echo BASE_URL;?>"}$("#orders-table-body").on("click",".confirm-delivery-btn",function(){const e=$(this).data("orderid"),o=$(this).data("docno");Swal.fire({title:"ยืนยันการส่งของ",text:`คุณต้องการยืนยันการส่งของสำหรับบิลเลขที่: ${o} ใช่หรือไม่?`,icon:"question",showCancelButton:!0,confirmButtonColor:"#ffc107",cancelButtonColor:"#d33",confirmButtonText:"ใช่, ยืนยันการส่ง!",cancelButtonText:"ยกเลิก"}).then(o=>{o.isConfirmed&&(Swal.fire({title:"กำลังดำเนินการ...",allowOutsideClick:!1,didOpen:()=>Swal.showLoading()}),$.ajax({url:t()+"php/confirm_delivery.php",type:"POST",data:{order_id:e},dataType:"json",success:function(t){Swal.close(),"success"===t.status?(Swal.fire({icon:"success",title:"ยืนยันการส่งสำเร็จ!",text:t.message,timer:1500,showConfirmButton:!1}),$("#order-row-"+e).fadeOut(500,function(){$(this).remove()})):Swal.fire({icon:"error",title:"เกิดข้อผิดพลาด!",text:t.message})},error:function(){Swal.close(),Swal.fire({icon:"error",title:"เกิดข้อผิดพลาดในการเชื่อมต่อ"})}}))})}),$("#orders-table-body").on("click",".cancel-btn",function(){const e=$(this).data("orderid"),o=$(this).data("docno");Swal.fire({title:"ยืนยันการยกเลิก",text:`คุณต้องการยกเลิกบิลเลขที่: ${o} ใช่หรือไม่?`,icon:"warning",showCancelButton:!0,confirmButtonColor:"#d33",cancelButtonColor:"#3085d6",confirmButtonText:"ใช่, ยกเลิกเลย!",cancelButtonText:"ไม่"}).then(o=>{o.isConfirmed&&(Swal.fire({title:"กำลังดำเนินการ...",allowOutsideClick:!1,didOpen:()=>Swal.showLoading()}),$.ajax({url:t()+"php/cancel_order.php",type:"POST",data:{order_id:e},dataType:"json",success:function(t){Swal.close(),"success"===t.status?(Swal.fire({icon:"success",title:"ยกเลิกสำเร็จ!",text:t.message,timer:1500,showConfirmButton:!1}),$("#order-row-"+e).fadeOut(500,function(){$(this).remove()})):Swal.fire({icon:"error",title:"เกิดข้อผิดพลาด!",text:t.message})},error:function(){Swal.close(),Swal.fire({icon:"error",title:"เกิดข้อผิดพลาดในการเชื่อมต่อ"})}}))})})});
    </script>
    
    <script>
        // Edit Driver Functionality
        $(document).ready(function() {
            // Open edit driver modal
            $('#orders-table-body').on('click', '.edit-driver-btn', function() {
                const orderId = $(this).data('orderid');
                const docNo = $(this).data('docno');
                const custName = $(this).data('custname');
                const currentStaff = $(this).data('current-staff');
                const currentVehicle = $(this).data('current-vehicle');
                const currentStaffId = $(this).data('current-staff-id');
                const currentVehicleId = $(this).data('current-vehicle-id');
                
                $('#editOrderId').val(orderId);
                $('#editOrderInfo').val(`${docNo} - ${custName}`);
                $('#editCurrentStaff').val(currentStaff || '-');
                $('#editCurrentVehicle').val(currentVehicle || '-');
                $('#editCurrentStaffId').val(currentStaffId || '');
                $('#editCurrentVehicleId').val(currentVehicleId || '');
                
                // Set current values in dropdowns
                $('#newStaff').val(currentStaffId || '');
                $('#newVehicle').val(currentVehicleId || '');
                
                $('#editDriverModal').modal('show');
            });
            
            // Save driver change
            $('#saveDriverBtn').click(function() {
                const orderId = $('#editOrderId').val();
                const newStaffId = $('#newStaff').val();
                const newVehicleId = $('#newVehicle').val();
                const newStaffName = $('#newStaff option:selected').text();
                const newVehicleInfo = $('#newVehicle option:selected').text();
                
                if (!newStaffId || !newVehicleId) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'กรุณาเลือกข้อมูลให้ครบ',
                        text: 'โปรดเลือกทั้งคนขับและรถ'
                    });
                    return;
                }
                
                const currentStaffId = $('#editCurrentStaffId').val();
                const currentVehicleId = $('#editCurrentVehicleId').val();
                
                // Check if anything changed
                if (newStaffId === currentStaffId && newVehicleId === currentVehicleId) {
                    Swal.fire({
                        icon: 'info',
                        title: 'ไม่มีการเปลี่ยนแปลง',
                        text: 'ข้อมูลคนขับและรถยังเหมือนเดิม'
                    });
                    return;
                }
                
                Swal.fire({
                    title: 'ยืนยันการเปลี่ยนคนขับรถ',
                    html: `คุณต้องการเปลี่ยนคนขับรถสำหรับออเดอร์: <strong>${$('#editOrderInfo').val()}</strong><br><br>
                           จาก: <strong>${$('#editCurrentStaff').val()} / ${$('#editCurrentVehicle').val()}</strong><br>
                           ไปเป็น: <strong>${newStaffName} / ${newVehicleInfo}</strong><br><br>
                           ใช่หรือไม่?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#007bff',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'ใช่, เปลี่ยนเลย!',
                    cancelButtonText: 'ไม่'
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'กำลังดำเนินการ...',
                            allowOutsideClick: false,
                            didOpen: () => Swal.showLoading()
                        });
                        
                        $.ajax({
                            url: '<?php echo BASE_URL; ?>php/update_driver.php',
                            type: 'POST',
                            data: {
                                order_id: orderId,
                                staff_id: newStaffId,
                                vehicle_id: newVehicleId
                            },
                            dataType: 'json',
                            success: function(response) {
                                Swal.close();
                                if (response.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'เปลี่ยนคนขับสำเร็จ!',
                                        text: response.message,
                                        timer: 2000,
                                        showConfirmButton: false
                                    }).then(() => {
                                        // Update the table
                                        const row = $('#order-row-' + orderId);
                                        row.find('td:eq(5)').text(newStaffName);
                                        row.find('td:eq(6)').text(newVehicleInfo);
                                        
                                        // Update button data attributes
                                        const editBtn = row.find('.edit-driver-btn');
                                        editBtn.data('current-staff', newStaffName);
                                        editBtn.data('current-vehicle', newVehicleInfo);
                                        editBtn.data('current-staff-id', newStaffId);
                                        editBtn.data('current-vehicle-id', newVehicleId);
                                        
                                        // Close modal
                                        $('#editDriverModal').modal('hide');
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
