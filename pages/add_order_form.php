<?php
// pages/add_order_form.php
require_once '../php/check_session.php';
// สิทธิ์ที่ต้องการสำหรับหน้านี้: ระดับ 1 และ 4 (Admin)
require_login([1, 4]);

require_once '../php/db_connect.php'; 

// กำหนด BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

// SQL query เพื่อกรองบิลที่ยังไม่ได้เพิ่มเข้าระบบ
$cssale_options = "";
$sql_cssale = "SELECT cs.docno, cs.custname 
               FROM cssale cs
               LEFT JOIN orders o ON cs.docno = o.cssale_docno COLLATE utf8mb4_unicode_ci
               WHERE cs.shipflag = 1 AND o.order_id IS NULL
               ORDER BY cs.docdate DESC, cs.docno DESC 
               LIMIT 200";
$result_cssale = $conn->query($sql_cssale);

if ($result_cssale && $result_cssale->num_rows > 0) { while($row = $result_cssale->fetch_assoc()) { $cssale_options .= "<option value='" . htmlspecialchars($row['docno']) . "'>" . htmlspecialchars($row['docno'] . ' - ' . $row['custname']) . "</option>"; } }

$origin_options = "";
$sql_origin = "SELECT id, CONCAT_WS(' ', mooban, moo, tambon, amphoe, province) AS full_address FROM origin ORDER BY id";
$result_origin = $conn->query($sql_origin);
if ($result_origin && $result_origin->num_rows > 0) { while($row = $result_origin->fetch_assoc()) { $origin_options .= "<option value='" . htmlspecialchars($row['id']) . "'>" . htmlspecialchars($row['full_address']) . "</option>"; } }

$transport_origin_options = "";
$sql_transport = "SELECT transport_origin_id, origin_name FROM transport_origins ORDER BY origin_name";
$result_transport = $conn->query($sql_transport);
if ($result_transport && $result_transport->num_rows > 0) { while($row = $result_transport->fetch_assoc()) { $transport_origin_options .= "<option value='" . htmlspecialchars($row['transport_origin_id']) . "'>" . htmlspecialchars($row['origin_name']) . "</option>"; } }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มรายการจัดส่งใหม่</title>
    <!-- CSS links -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚚</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        .bill-info-box {
            background-color: #f1faff; /* สีฟ้าอ่อน */
            border-left: 4px solid #009ef7; /* เส้นเน้นสีน้ำเงิน */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">เพิ่มรายการจัดส่งใหม่</h2>
            <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-2"></i>กลับหน้าหลัก</a>
        </div>
        <form id="addOrderForm">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="cssale_docno">เลขที่บิล (จาก CS Sale):</label>
                    <select class="form-control select2-basic" id="cssale_docno" name="cssale_docno" required>
                        <option value="">-- เลือกเลขที่บิล --</option>
                        <?php echo $cssale_options; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="customer_address_origin_id">ที่อยู่ลูกค้า (จาก Origin):</label>
                    <select class="form-control select2-basic" id="customer_address_origin_id" name="customer_address_origin_id" required>
                        <option value="">-- เลือกที่อยู่ลูกค้า --</option>
                        <?php echo $origin_options; ?>
                    </select>
                </div>
            </div>

            <div id="bill-details-container" class="mt-2 mb-3 p-3 rounded bill-info-box" style="display: none;">
                <h6>ข้อมูลจากบิล</h6>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>วันที่สร้างบิล:</strong> <span id="display-docdate">-</span></p>
                        <p class="mb-1"><strong>ชื่อลูกค้า:</strong> <span id="display-custname">-</span></p>
                        <p class="mb-0"><strong>พนักงานขาย:</strong> <span id="display-salesman">-</span></p>
                    </div>
                    <div class="col-md-6">
                         <p class="mb-0"><strong>ที่อยู่จัดส่ง (ตามบิล):</strong> <span id="display-shipaddr">-</span></p>
                    </div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="transport_origin_id">ต้นทางขนส่ง:</label>
                    <select class="form-control" id="transport_origin_id" name="transport_origin_id" required>
                        <option value="">-- เลือกต้นทางขนส่ง --</option>
                        <?php echo $transport_origin_options; ?>
                    </select>
                </div>
                <div class="form-group col-md-6">
                    <label for="priority">ความเร่งด่วน:</label>
                    <select class="form-control" id="priority" name="priority" required>
                        <option value="ปกติ">ปกติ</option>
                        <option value="ด่วน">ด่วน</option>
                        <option value="ด่วนที่สุด">ด่วนที่สุด</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="product_details">หมายเหตุ:</label>
                <textarea class="form-control" id="product_details" name="product_details" rows="3"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">บันทึกรายการ</button>
        </form>
    </div>
    <!-- JavaScript scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?php echo BASE_URL; ?>js/script.js?v=1.2"></script>
    <script>
        $(document).ready(function() {
            $('.select2-basic').select2({
                placeholder: "-- กรุณาเลือก --",
                allowClear: true
            });

            function getBaseUrl() {
                return "<?php echo BASE_URL; ?>";
            }

            $('#cssale_docno').on('change', function() {
                const selectedDocNo = $(this).val();
                const detailsContainer = $('#bill-details-container');

                if (selectedDocNo) {
                    $('#display-custname').text('กำลังโหลด...');
                    $('#display-shipaddr').text('กำลังโหลด...');
                    $('#display-docdate').text('กำลังโหลด...');
                    $('#display-salesman').text('กำลังโหลด...');
                    detailsContainer.slideDown();

                    $.ajax({
                        url: getBaseUrl() + 'php/get_cs_sale_details.php',
                        type: 'GET',
                        data: { docno: selectedDocNo },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                let salesmanDisplay = (response.salesman_code && response.salesman_name) ? `${response.salesman_code} - ${response.salesman_name}` : '-';

                                $('#display-custname').text(response.custname || '-');
                                $('#display-shipaddr').text(response.shipaddr || '-');
                                $('#display-docdate').text(response.docdate_formatted || '-');
                                $('#display-salesman').text(salesmanDisplay);
                            } else {
                                $('#display-custname').text('ไม่พบข้อมูล');
                                $('#display-shipaddr').text('ไม่พบข้อมูล');
                                $('#display-docdate').text('-');
                                $('#display-salesman').text('-');
                            }
                        },
                        error: function() {
                            $('#display-custname').text('เกิดข้อผิดพลาด');
                            $('#display-shipaddr').text('เกิดข้อผิดพลาด');
                            $('#display-docdate').text('-');
                            $('#display-salesman').text('-');
                        }
                    });
                } else {
                    detailsContainer.slideUp();
                }
            });
        });
    </script>
</body>
</html>
