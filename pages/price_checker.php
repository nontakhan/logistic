<?php
// pages/price_checker.php
require_once '../php/check_session.php';
// สิทธิ์ที่ต้องการสำหรับหน้านี้: ทุกคนที่ login แล้วสามารถใช้ได้
require_login([1, 2, 3, 4]);

require_once '../php/db_connect.php'; 

// กำหนด BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

// ดึงข้อมูลสถานที่ทั้งหมดสำหรับ Dropdown
$origin_options = "";
// *** แก้ไข: ปรับปรุง SQL query เพื่อเรียงลำดับที่อยู่ใหม่ ***
$sql_origin = "SELECT id, CONCAT_WS(' ', mooban, moo, tambon, amphoe, province) AS full_address FROM origin ORDER BY id";
$result_origin = $conn->query($sql_origin);
if ($result_origin && $result_origin->num_rows > 0) { 
    while($row = $result_origin->fetch_assoc()) { 
        $origin_options .= "<option value='" . htmlspecialchars($row['id']) . "'>" . htmlspecialchars($row['full_address']) . "</option>"; 
    } 
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบราคาค่าขนส่ง</title>
    <!-- *** เพิ่ม: Favicon *** -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚚</text></svg>">
    
    <!-- CSS links -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        .price-card {
            background-color: #f8f9fa;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-base);
            padding: 15px;
            margin-bottom: 15px;
            text-align: center;
        }
        .price-card .price-vehicle {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--text-dark);
        }
        .price-card .price-amount {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-red);
        }
        #results-container {
            border-top: 2px solid var(--primary-red-lighter);
            padding-top: 20px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fas fa-search-dollar mr-2"></i>ตรวจสอบราคาค่าขนส่ง</h2>
            <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-2"></i>กลับหน้าหลัก</a>
        </div>

        <div class="form-group">
            <label for="origin-search">ค้นหาและเลือกสถานที่ปลายทาง:</label>
            <select class="form-control select2-search" id="origin-search" name="origin_id">
                <option value="">-- กรุณาเลือกสถานที่ --</option>
                <?php echo $origin_options; ?>
            </select>
        </div>

        <div id="results-container" style="display: none;">
            <h4 id="result-title" class="mb-3">รายละเอียดราคา</h4>
            <div class="row">
                <div class="col-md-4">
                    <div class="price-card">
                        <div class="price-vehicle"><i class="fas fa-truck-pickup"></i> รถกระบะ</div>
                        <div class="price-amount" id="price-pickup">0</div>
                        <div class="price-unit">บาท</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="price-card">
                        <div class="price-vehicle"><i class="fas fa-truck"></i> รถ 6 ล้อ</div>
                        <div class="price-amount" id="price-6wheel">0</div>
                        <div class="price-unit">บาท</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="price-card">
                        <div class="price-vehicle"><i class="fas fa-truck-monster"></i> รถ 10 ล้อ</div>
                        <div class="price-amount" id="price-10wheel">0</div>
                        <div class="price-unit">บาท</div>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <h5>รายละเอียดเพิ่มเติม</h5>
                <dl class="row">
                    <dt class="col-sm-3">ระยะทาง:</dt>
                    <dd class="col-sm-9"><span id="result-distance"></span> กม.</dd>

                    <dt class="col-sm-3">หมายเหตุ:</dt>
                    <dd class="col-sm-9"><span id="result-remark"></span></dd>
                </dl>
            </div>
        </div>
    </div>

    <!-- JavaScript scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-search').select2({
                placeholder: "-- กรุณาพิมพ์เพื่อค้นหาสถานที่ --",
                allowClear: true
            });

            function getBaseUrl() {
                return "<?php echo BASE_URL; ?>";
            }

            $('#origin-search').on('change', function() {
                const selectedOriginId = $(this).val();
                const resultsContainer = $('#results-container');

                if (selectedOriginId) {
                    // แสดง loading text ชั่วคราว
                    $('#result-title').text('กำลังโหลดข้อมูล...');
                    $('.price-amount').text('...');
                    $('#result-distance').text('...');
                    $('#result-remark').text('...');
                    resultsContainer.slideDown();

                    $.ajax({
                        url: getBaseUrl() + 'php/get_origin_prices.php', // Path ไปยัง PHP script ใหม่
                        type: 'GET',
                        data: { id: selectedOriginId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.status === 'success') {
                                $('#result-title').text('รายละเอียดราคาสำหรับ: ' + response.full_address);
                                $('#price-pickup').text(Number(response.price_pickup).toLocaleString('th-TH'));
                                $('#price-6wheel').text(Number(response.price_6wheel).toLocaleString('th-TH'));
                                $('#price-10wheel').text(Number(response.price_10wheel).toLocaleString('th-TH'));
                                $('#result-distance').text(response.distance || '-');
                                $('#result-remark').text(response.remark || '-');
                            } else {
                                $('#result-title').text('ไม่พบข้อมูล');
                                $('.price-amount').text('0');
                            }
                        },
                        error: function() {
                            $('#result-title').text('เกิดข้อผิดพลาดในการโหลดข้อมูล');
                            $('.price-amount').text('0');
                        }
                    });
                } else {
                    resultsContainer.slideUp();
                }
            });
        });
    </script>
</body>
</html>
