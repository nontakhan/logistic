<?php
// pages/pending_assignment.php
require_once '../php/check_session.php';
require_login([2, 3, 4]);
require_once '../php/db_connect.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

$search_docno = isset($_GET['search_docno']) ? trim($conn->real_escape_string($_GET['search_docno'])) : '';

$where_clauses = ["o.status = 'รับเรื่อง'"];
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

$sql_orders = "SELECT o.order_id, o.cssale_docno, cs.custname, CONCAT_WS(', ', ori.moo, ori.mooban, ori.tambon, ori.amphoe, ori.province) AS customer_full_address, cs.shipaddr AS cssale_shipaddr, o.product_details, o.priority, o.order_date, t_org.origin_name AS transport_origin_name FROM orders o LEFT JOIN cssale cs ON o.cssale_docno = cs.docno COLLATE utf8mb4_unicode_ci LEFT JOIN origin ori ON o.customer_address_origin_id = ori.id LEFT JOIN transport_origins t_org ON o.transport_origin_id = t_org.transport_origin_id" . $sql_where . " ORDER BY CASE o.priority WHEN 'ด่วนที่สุด' THEN 1 WHEN 'ด่วน' THEN 2 WHEN 'ปกติ' THEN 3 ELSE 4 END ASC, o.order_date ASC, o.created_at ASC";
$stmt = $conn->prepare($sql_orders);
if (!empty($params)) { $stmt->bind_param($param_types, ...$params); }
$stmt->execute();
$result_orders = $stmt->get_result();

$staff_options = "";
$sql_staff = "SELECT staff_id, staff_name FROM staff ORDER BY staff_id";
$result_staff = $conn->query($sql_staff);
if ($result_staff && $result_staff->num_rows > 0) { while($row = $result_staff->fetch_assoc()) { $staff_options .= "<option value='" . htmlspecialchars($row['staff_id']) . "'>" . htmlspecialchars($row['staff_name']) . "</option>"; } }

$vehicle_options = "";
$sql_vehicles = "SELECT vehicle_id, CONCAT(vehicle_name, ' (', vehicle_plate, ')') AS vehicle_display FROM vehicles ORDER BY vehicle_id";
$result_vehicles = $conn->query($sql_vehicles);
if ($result_vehicles && $result_vehicles->num_rows > 0) { while($row = $result_vehicles->fetch_assoc()) { $vehicle_options .= "<option value='" . htmlspecialchars($row['vehicle_id']) . "'>" . htmlspecialchars($row['vehicle_display']) . "</option>"; } }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการรอจัดคน/รถ</title>
    <!-- *** เพิ่ม: Favicon *** -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚚</text></svg>">
    
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        .action-buttons { white-space: nowrap; }
        .action-buttons button, .action-buttons a { margin: 0 2px; } 
        .filter-card { box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
        .select2-container { width: 100% !important; }
        .select2-container .select2-selection--single { height: calc(1.5em + .75rem + 2px) !important; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: calc(1.5em + .75rem) !important; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(1.5em + .75rem) !important; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">รายการรอจัดคน/รถ</h2>
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
                        <a href="<?php echo BASE_URL; ?>pages/pending_assignment.php" class="btn btn-outline-secondary ml-2">ล้างค่า</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-striped">
                <thead class="thead-light">
                    <tr>
                        <th>ID ติดตาม</th><th>เลขที่บิล</th><th>ชื่อลูกค้า</th>
                        <th>หมายเหตุ</th>
                        <th>ต้นทางขนส่ง</th><th>วันที่สั่ง</th><th>ความเร่งด่วน</th><th>ดำเนินการ</th>
                    </tr>
                </thead>
                <tbody id="orders-table-body">
                    <?php if ($result_orders && $result_orders->num_rows > 0): ?>
                        <?php while($row = $result_orders->fetch_assoc()): ?>
                            <tr id="order-row-<?php echo htmlspecialchars($row['order_id']); ?>" class="priority-<?php echo htmlspecialchars($row['priority']); ?>">
                                <td><?php echo htmlspecialchars($row['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($row['cssale_docno']); ?></td>
                                <td><?php echo htmlspecialchars($row['custname']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($row['cssale_shipaddr'])); ?></td>
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
                            <div class="form-group col-md-6"><label for="assigned_staff_id">เลือกคนส่งของ:</label><select class="form-control select2-modal" id="assigned_staff_id" name="assigned_staff_id" required><option value="">-- เลือกคนส่งของ --</option><?php echo $staff_options; ?></select></div>
                            <div class="form-group col-md-6"><label for="assigned_vehicle_id">เลือกรถ:</label><select class="form-control select2-modal" id="assigned_vehicle_id" name="assigned_vehicle_id" required><option value="">-- เลือกรถ --</option><?php echo $vehicle_options; ?></select></div>
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
        $(document).ready(function(){function t(){return"<?php echo BASE_URL;?>"}$(".select2-modal").select2({placeholder:"-- กรุณาเลือก --",allowClear:!0,dropdownParent:$("#assignDeliveryModal")}),$("#orders-table-body").on("click",".manage-delivery-btn",function(){const t=$(this).data("orderid"),e=$(this).data("docno"),o=$(this).data("custname"),a=$(this).data("address"),r=$(this).data("details");$("#modal_order_id").val(t),$("#modal_docno_hidden").val(e),$("#modal_docno_display").text(e),$("#modal_custname").text(o),$("#modal_address").text(a),$("#modal_details").text(r),$("#assigned_staff_id").val(null).trigger("change"),$("#assigned_vehicle_id").val(null).trigger("change"),$("#assignDeliveryModal").modal("show")}),$("#assignDeliveryForm").on("submit",function(e){e.preventDefault();const o=$("#modal_order_id").val(),a=$("#modal_docno_hidden").val(),r=$("#assigned_staff_id").val(),s=$("#assigned_vehicle_id").val();r&&s?Swal.fire({title:"ยืนยันการจัดสรร",text:`คุณต้องการจัดสรรคนส่งของและรถสำหรับบิลเลขที่: ${a} ใช่หรือไม่?`,icon:"question",showCancelButton:!0,confirmButtonColor:"#28a745",cancelButtonColor:"#d33",confirmButtonText:"ใช่, ยืนยัน!",cancelButtonText:"ยกเลิก"}).then(e=>{e.isConfirmed&&(Swal.fire({title:"กำลังบันทึก...",allowOutsideClick:!1,didOpen:()=>Swal.showLoading()}),$.ajax({url:t()+"php/assign_delivery.php",type:"POST",data:{order_id:o,assigned_staff_id:r,assigned_vehicle_id:s},dataType:"json",success:function(t){Swal.close(),"success"===t.status?Swal.fire({icon:"success",title:"จัดสรรสำเร็จ!",text:t.message,timer:1500,showConfirmButton:!1}).then(()=>{t("#assignDeliveryModal").modal("hide"),$("#order-row-"+o).fadeOut(500,function(){$(this).remove()})}):Swal.fire({icon:"error",title:"เกิดข้อผิดพลาด!",text:t.message})},error:function(){Swal.close(),Swal.fire({icon:"error",title:"เกิดข้อผิดพลาดในการเชื่อมต่อ"})}}))}):Swal.fire({icon:"error",title:"ข้อมูลไม่ครบถ้วน",text:"กรุณาเลือกคนส่งของและรถ"})}),$("#orders-table-body").on("click",".cancel-btn",function(){const e=$(this).data("orderid"),o=$(this).data("docno");Swal.fire({title:"ยืนยันการยกเลิก",text:`คุณต้องการยกเลิกบิลเลขที่: ${o} ใช่หรือไม่?`,icon:"warning",showCancelButton:!0,confirmButtonColor:"#d33",cancelButtonColor:"#3085d6",confirmButtonText:"ใช่, ยกเลิกเลย!",cancelButtonText:"ไม่"}).then(o=>{o.isConfirmed&&(Swal.fire({title:"กำลังดำเนินการ...",allowOutsideClick:!1,didOpen:()=>Swal.showLoading()}),$.ajax({url:t()+"php/cancel_order.php",type:"POST",data:{order_id:e},dataType:"json",success:function(t){Swal.close(),"success"===t.status?(Swal.fire({icon:"success",title:"ยกเลิกสำเร็จ!",text:t.message,timer:1500,showConfirmButton:!1}),$("#order-row-"+e).fadeOut(500,function(){$(this).remove()})):Swal.fire({icon:"error",title:"เกิดข้อผิดพลาด!",text:t.message})},error:function(){Swal.close(),Swal.fire({icon:"error",title:"เกิดข้อผิดพลาดในการเชื่อมต่อ"})}}))})})});
    </script>
</body>
</html>
