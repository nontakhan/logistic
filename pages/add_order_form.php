<?php
// pages/add_order_form_super_fast.php - Super Fast Version
require_once '../php/check_session.php';
require_login([1, 2, 4]);

require_once '../php/db_connect.php'; 

// กำหนด BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

// *** SUPER FAST: โหลดแค่ข้อมูลพื้นฐานก่อน ***
$cssale_options = "";
$origin_options = "";
$transport_origin_options = "";
$salesman_modal_options = "";

// โหลดแค่ 50 รายการแรกเท่านั้น (สำหรับการแสดงผลทันที)
$sql_cssale_initial = "SELECT cs.docno, cs.custname 
                       FROM cssale cs
                       WHERE cs.shipflag = 1 
                       AND NOT EXISTS (
                           SELECT 1 FROM orders o 
                           WHERE o.cssale_docno = cs.docno 
                           LIMIT 1
                       )
                       ORDER BY cs.docdate DESC, cs.docno DESC 
                       LIMIT 50";

$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3); // ลดเหลือ 3 วินาที
$start_time = microtime(true);

$result_cssale = $conn->query($sql_cssale_initial);

if ($result_cssale && $result_cssale->num_rows > 0) { 
    while($row = $result_cssale->fetch_assoc()) { 
        $cssale_options .= "<option value='" . htmlspecialchars($row['docno']) . "'>" . htmlspecialchars($row['docno'] . ' - ' . $row['custname']) . "</option>"; 
    } 
}

$query_time = microtime(true) - $start_time;
error_log("SUPER FAST CSSale query time: " . $query_time . " seconds");

// โหลดข้อมูลที่อยู่ทั้งหมด
$sql_origin = "SELECT id, CONCAT_WS(' ', mooban, moo, tambon, amphoe, province) AS full_address FROM origin ORDER BY id";
$result_origin = $conn->query($sql_origin);
if ($result_origin && $result_origin->num_rows > 0) { 
    while($row = $result_origin->fetch_assoc()) { 
        $origin_options .= "<option value='" . htmlspecialchars($row['id']) . "'>" . htmlspecialchars($row['full_address']) . "</option>"; 
    } 
}

$sql_transport = "SELECT transport_origin_id, origin_name FROM transport_origins ORDER BY origin_name LIMIT 10";
$result_transport = $conn->query($sql_transport);
if ($result_transport && $result_transport->num_rows > 0) { 
    while($row = $result_transport->fetch_assoc()) { 
        $transport_origin_options .= "<option value='" . htmlspecialchars($row['transport_origin_id']) . "'>" . htmlspecialchars($row['origin_name']) . "</option>"; 
    } 
}

$sql_salesman_modal = "SELECT DISTINCT code, lname FROM cssale WHERE code IS NOT NULL AND lname IS NOT NULL AND lname != '' ORDER BY lname ASC LIMIT 20";
$result_salesman_modal = $conn->query($sql_salesman_modal);
if ($result_salesman_modal && $result_salesman_modal->num_rows > 0) { 
    while($row = $result_salesman_modal->fetch_assoc()) { 
        $salesman_modal_options .= "<option value='" . htmlspecialchars($row['code']) . "'>" . htmlspecialchars($row['code'] . ' - ' . $row['lname']) . "</option>"; 
    } 
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มรายการจัดส่งใหม่</title>
    <!-- CSS links -->
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
        .bill-info-box {
            background-color: #f1faff;
            border-left: 4px solid #009ef7;
        }
        
        /* *** SUPER FAST: Improved Loading Indicator *** */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        
        .loading-content {
            text-align: center;
        }
        
        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #dc2626;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .hide-loading {
            display: none;
        }
        
        /* *** SUPER FAST: Loading more indicator *** */
        .loading-more {
            text-align: center;
            padding: 10px;
            color: #666;
            font-style: italic;
        }
        
        .select2-container--default .select2-selection--single {
            height: 38px;
        }
        
        /* *** SUPER FAST: Highlight info text *** */
        .text-info.highlight {
            color: #17a2b8;
            font-weight: 500;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        /* *** SUPER FAST: Ensure scroll bar for dropdown *** */
        .select2-container--default .select2-results > .select2-results__options {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .select2-dropdown {
            border: 1px solid #aaa;
            border-radius: 4px;
        }
        
        /* *** SUPER FAST: Loading overlay fix *** */
        .loading-overlay.hide-loading,
        .loading-overlay[style*="display: none"] {
            display: none !important;
            opacity: 0 !important;
            visibility: hidden !important;
        }
    </style>
</head>
<body>
    <!-- *** SUPER FAST: Loading Overlay *** -->
    <div id="loadingOverlay" class="loading-overlay">
        <div class="loading-content">
            <div class="loading-spinner"></div>
            <p>กำลังโหลดเร็วๆ...</p>
        </div>
    </div>

    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">เพิ่มรายการจัดส่งใหม่</h2>
            <div>
                <button type="button" class="btn btn-outline-danger" data-toggle="modal" data-target="#addNewBillModal">
                    <i class="fas fa-plus"></i> เพิ่มบิลใหม่
                </button>
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary ml-2"><i class="fas fa-arrow-left mr-2"></i>กลับหน้าหลัก</a>
            </div>
        </div>
        
        <form id="addOrderForm">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <div class="d-flex justify-content-between align-items-center">
                        <label for="cssale_docno" class="mb-0">เลขที่บิล (จาก CS Sale):</label>
                        <small class="text-info highlight">👆 คลิกโหลดเพิ่ม 50 รายการ (มี 50 รายการแรกอยู่แล้ว)</small>
                    </div>
                    <select class="form-control select2-basic mt-2" id="cssale_docno" name="cssale_docno" required>
                        <option value="">-- เลือกเลขที่บิล --</option>
                        <?php echo $cssale_options; ?>
                    </select>
                    <div id="cssaleLoadingMore" class="loading-more" style="display: none;">
                        <i class="fas fa-spinner fa-spin"></i> กำลังโหลดเพิ่ม 50 รายการ...
                    </div>
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

    <!-- Modal สำหรับเพิ่มบิลใหม่ -->
    <div class="modal fade" id="addNewBillModal" tabindex="-1" role="dialog" aria-labelledby="addNewBillModalLabel" aria-hidden="true">
      <div class="modal-dialog" role="document">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="addNewBillModalLabel"><i class="fas fa-file-invoice-dollar mr-2"></i>เพิ่มข้อมูลบิลใหม่</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <form id="addNewBillForm">
            <div class="modal-body">
              <div class="form-group">
                <label for="new_docno">เลขที่บิล</label>
                <input type="text" class="form-control" id="new_docno" name="new_docno" required>
              </div>
              <div class="form-group">
                <label for="new_docdate">วันที่สร้างบิล</label>
                <input type="date" class="form-control" id="new_docdate" name="new_docdate" required value="<?php echo date('Y-m-d'); ?>">
              </div>
              <div class="form-group">
                <label for="new_custname">ชื่อลูกค้า</label>
                <input type="text" class="form-control" id="new_custname" name="new_custname" required>
              </div>
               <div class="form-group">
                <label for="new_salesman_code">พนักงานขาย</label>
                <select class="form-control" id="new_salesman_code" name="new_salesman_code" required style="width: 100%;">
                    <option value="">-- เลือกพนักงานขาย --</option>
                    <?php echo $salesman_modal_options; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="new_shipaddr">ที่อยู่จัดส่ง</label>
                <textarea class="form-control" id="new_shipaddr" name="new_shipaddr" rows="3" required></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-dismiss="modal">ยกเลิก</button>
              <button type="submit" class="btn btn-primary">บันทึกข้อมูลบิล</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- JavaScript scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?php echo BASE_URL; ?>js/script.js?v=1.2"></script>
    <script>
        $(document).ready(function() {
            // *** SUPER FAST: ซ่อน loading overlay อย่างแน่นอน ***
            // วิธีที่ 1: ซ่อนทันทีเมื่อ DOM ready
            $('#loadingOverlay').hide();
            
            // วิธีที่ 2: ซ่อนเมื่อ window load (backup)
            $(window).on('load', function() {
                setTimeout(function() {
                    $('#loadingOverlay').hide();
                }, 100);
            });
            
            // วิธีที่ 3: ซ่อนเมื่อ page fully loaded (backup 2)
            $(document).on('pageshow', function() {
                $('#loadingOverlay').hide();
            });
            
            // วิธีที่ 4: ซ่อนเมื่อมีการ focus บนฟอร์ม (backup 3)
            $('#addOrderForm input, #addOrderForm select, #addOrderForm button').on('focus', function() {
                $('#loadingOverlay').hide();
            });
            
            // วิธีที่ 5: ซ่อนเมื่อมีการคลิกใดๆ (backup 4)
            $('body').on('click', function() {
                $('#loadingOverlay').hide();
            });

            $('.select2-basic').select2({
                placeholder: "-- กรุณาเลือก --",
                allowClear: true
            });

            function getBaseUrl() {
                return "<?php echo BASE_URL; ?>";
            }

            // *** SUPER FAST: Lazy Load สำหรับ CSSale Dropdown ***
            let cssaleLoaded = false;
            let cssaleOffset = 50; // เริ่มจาก 50 รายการแรก

            // ลองหลายวิธีเพื่อให้มั่นใจว่าจะโหลด
            function triggerLoadMoreCSSale() {
                if (!cssaleLoaded) {
                    console.log('Loading more CSSale options...');
                    loadMoreCSSaleOptions();
                    cssaleLoaded = true;
                }
            }

            $('#cssale_docno').on('click', function() {
                console.log('CSSale clicked');
                setTimeout(triggerLoadMoreCSSale, 100);
            });

            $('#cssale_docno').on('focus', function() {
                console.log('CSSale focused');
                setTimeout(triggerLoadMoreCSSale, 200);
            });

            // Backup: ใช้ select2:opening ด้วย
            $('#cssale_docno').on('select2:opening', function() {
                console.log('Select2 opening');
                setTimeout(triggerLoadMoreCSSale, 50);
            });

            function loadMoreCSSaleOptions() {
                console.log('loadMoreCSSaleOptions called, cssaleLoaded:', cssaleLoaded);
                $('#cssaleLoadingMore').show();
                
                $.ajax({
                    url: getBaseUrl() + 'php/load_more_cssale.php',
                    type: 'GET',
                    data: { offset: cssaleOffset },
                    dataType: 'json',
                    timeout: 8000, // 8 วินาที
                    success: function(response) {
                        console.log('AJAX success:', response);
                        if (response.status === 'success' && response.options) {
                            const $select = $('#cssale_docno');
                            
                            // เพิ่ม options ใหม่เข้าไปใน Select2 โดยตรง
                            response.options.forEach(function(option) {
                                // สร้าง option element ใหม่
                                var newOption = new Option(option.text, option.value, false, false);
                                $select.append(newOption);
                            });
                            
                            // อัปเดต Select2 ให้รู้จัก options ใหม่
                            $select.trigger('change');
                            
                            // เปิด dropdown อีกครั้งโดยอัตโนมัติ
                            setTimeout(function() {
                                $select.select2('open');
                            }, 100);
                            
                            cssaleOffset += response.options.length;
                            console.log('Added', response.options.length, 'options. New offset:', cssaleOffset);
                        }
                        $('#cssaleLoadingMore').hide();
                    },
                    error: function(xhr, status, error) {
                        console.log('AJAX error:', status, error);
                        $('#cssaleLoadingMore').html('<small class="text-danger">โหลดเพิ่มไม่สำเร็จ</small>').show();
                    }
                });
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
                        timeout: 8000, // ลดเหลือ 8 วินาที
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
                        error: function(xhr, status, error) {
                            if (status === 'timeout') {
                                $('#display-custname').text('หมดเวลา');
                                $('#display-shipaddr').text('หมดเวลา');
                            } else {
                                $('#display-custname').text('เกิดข้อผิดพลาด');
                                $('#display-shipaddr').text('เกิดข้อผิดพลาด');
                            }
                            $('#display-docdate').text('-');
                            $('#display-salesman').text('-');
                        }
                    });
                } else {
                    detailsContainer.slideUp();
                }
            });
            
            $('#new_salesman_code').select2({
                placeholder: "-- เลือกพนักงานขาย --",
                dropdownParent: $('#addNewBillModal')
            });

            $('#addNewBillForm').on('submit', function(e) {
                e.preventDefault();
                const formData = $(this).serialize();

                Swal.fire({
                    title: 'กำลังบันทึกข้อมูลบิล...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                $.ajax({
                    url: "<?php echo BASE_URL; ?>php/add_new_bill.php",
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    timeout: 12000, // 12 วินาที
                    success: function(response) {
                        Swal.close();
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'สำเร็จ!',
                                text: response.message
                            }).then(() => {
                                $('#addNewBillModal').modal('hide');
                                location.reload();
                            });
                        } else {
                            let errorHtml = response.message;
                            if (response.errors) {
                                errorHtml += '<ul class="text-left mt-2">';
                                for(const key in response.errors) {
                                    errorHtml += `<li>${response.errors[key]}</li>`;
                                }
                                errorHtml += '</ul>';
                            }
                            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', html: errorHtml });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.close();
                        if (status === 'timeout') {
                            Swal.fire({ icon: 'error', title: 'หมดเวลาในการเชื่อมต่อ' });
                        } else {
                            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาดในการเชื่อมต่อ' });
                        }
                    }
                });
            });

            $('#addNewBillModal').on('hidden.bs.modal', function () {
                $('#addNewBillForm')[0].reset();
                $('#new_salesman_code').val(null).trigger('change');
            });
        });
    </script>
</body>
</html>
