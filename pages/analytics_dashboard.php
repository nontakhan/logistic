<?php
// pages/analytics_dashboard.php
require_once '../php/check_session.php';
require_login([1, 2, 3, 4]);

// กำหนด BASE_URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

$servername = "10.10.202.156";
$username = "nr";
$password = "P@ssw0rd";
$dbname = "logistic";
$conn = @new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// ดึงรายการสาขา
$transport_origins = [];
$result = $conn->query("SELECT transport_origin_id, origin_name FROM transport_origins ORDER BY origin_name");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $transport_origins[] = $row;
    }
}

// ดึงรายการจังหวัด
$provinces = [];
$result_provinces = $conn->query("SELECT DISTINCT province FROM origin WHERE province IS NOT NULL AND province != '' ORDER BY province");
if ($result_provinces) {
    while ($row = $result_provinces->fetch_assoc()) {
        $provinces[] = $row['province'];
    }
}

$conn->close();

$default_date_start = date('Y-m-01');
$default_date_end = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - NR Logistics</title>
    
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📊</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        :root {
            --primary-red: #dc3545;
            --primary-red-dark: #b02a37;
            --primary-red-light: #f8d7da;
            --gradient-red: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%);
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --shadow-hover: 0 8px 30px rgba(220, 53, 69, 0.2);
        }
        
        * {
            font-family: 'Sarabun', sans-serif;
        }
        
        body {
            background: #f5f7fa;
            min-height: 100vh;
            padding-bottom: 40px;
        }
        
        /* Header */
        .dashboard-header {
            background: var(--gradient-red);
            padding: 20px 0;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .dashboard-header h2 {
            color: white;
            margin: 0;
            font-weight: 700;
            font-size: 1.6rem;
        }
        
        .btn-back {
            background: rgba(255,255,255,0.2);
            border: 2px solid white;
            color: white;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            background: white;
            color: var(--primary-red);
            text-decoration: none;
        }
        
        /* Content Container */
        .content-container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 50px;
        }
        
        /* Filter Section */
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--primary-red);
        }
        
        .filter-card label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .form-control {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 8px 15px; /* Reduced vertical padding */
            height: 45px; /* Fixed height for consistency */
            line-height: 1.5;
            transition: all 0.3s;
        }

        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3E%3Cpath fill='%23333' d='M0 2l4 4 4-4z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 10px;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            padding-right: 30px; /* Space for arrow */
        }
        
        .form-control:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.1);
        }

        /* ... existing styles ... */

        
        .btn-search {
            background: var(--gradient-red);
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            color: white;
        }
        
        .btn-reset {
            background: #6c757d;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            color: white;
        }
        
        .btn-reset:hover {
            background: #5a6268;
            color: white;
        }
        
        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }
        
        .stat-card.total::before { background: var(--gradient-red); }
        .stat-card.pending::before { background: linear-gradient(135deg, #ffc107 0%, #ffda6a 100%); }
        .stat-card.success::before { background: linear-gradient(135deg, #28a745 0%, #5dd879 100%); }
        .stat-card.danger::before { background: linear-gradient(135deg, #dc3545 0%, #ff6b6b 100%); }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-icon {
            width: 55px;
            height: 55px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 15px;
        }
        
        .stat-card.total .stat-icon { background: var(--primary-red-light); color: var(--primary-red); }
        .stat-card.pending .stat-icon { background: #fff3cd; color: #856404; }
        .stat-card.success .stat-icon { background: #d4edda; color: #155724; }
        .stat-card.danger .stat-icon { background: #f8d7da; color: #721c24; }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
        }
        
        .stat-card.total .stat-value { color: var(--primary-red); }
        .stat-card.pending .stat-value { color: #856404; }
        .stat-card.success .stat-value { color: #155724; }
        .stat-card.danger .stat-value { color: #721c24; }
        
        .stat-label {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.95rem;
        }
        
        /* Section Title */
        .section-title {
            color: #333;
            font-weight: 700;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary-red);
            display: inline-block;
        }
        
        .section-title i {
            color: var(--primary-red);
            margin-right: 8px;
        }
        
        /* Chart Cards */
        .chart-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 40px;
            box-shadow: var(--shadow);
            height: 100%;
        }
        
        .chart-card h5 {
            color: #333;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
            font-size: 1.05rem;
        }
        
        .chart-card h5 i {
            color: var(--primary-red);
            margin-right: 8px;
        }
        
        /* Adjust chart container height for location charts */
        .top-location-charts .chart-container {
            height: 280px;
        }
        
        /* Responsive adjustments for location charts */
        @media (max-width: 992px) {
            .top-location-charts .chart-container {
                height: 250px;
            }
        }
        
        @media (max-width: 768px) {
            .top-location-charts .chart-container {
                height: 200px;
            }
        }
        
        /* Special handling for tambon chart with very long labels */
        #tambonChart {
            max-width: 100%;
        }
        
        .top-location-charts #tambonChart {
            position: relative;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
        
        /* Table Styles */
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 40px;
            box-shadow: var(--shadow);
        }
        
        .table-card h5 {
            color: #333;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
            font-size: 1.05rem;
        }
        
        .table-card h5 i {
            color: var(--primary-red);
            margin-right: 8px;
        }
        
        /* Loading */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--primary-red-light);
            border-top-color: var(--primary-red);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .loading-text {
            margin-top: 15px;
            color: var(--primary-red);
            font-weight: 600;
        }
        
        /* Responsive */
        /* Print Styles */
        @media print {
            .dashboard-header, .filter-card, .loading-overlay { display: none !important; }
            .content-container { padding: 0; max-width: 100%; }
            .chart-card, .stat-card { box-shadow: none; border: 1px solid #ddd; page-break-inside: avoid; }
            body { background: white; margin: 0; padding: 0; }
            .row { display: flex; flex-wrap: wrap; }
            .col-lg-3, .col-lg-6, .col-12 { flex: 0 0 auto; width: 100%; }
            .col-lg-3 { width: 25%; }
            .col-lg-6 { width: 50%; }
            /* Hide URL printing */
            a[href]:after { content: none !important; }
        }
    </style>
</head>
<body>
    <!-- Loading -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
        <div class="loading-text">กำลังโหลดข้อมูล...</div>
    </div>

    <!-- Header -->
    <div class="dashboard-header">
        <div class="content-container">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="d-flex align-items-center">
                    <i class="fas fa-chart-line mr-2"></i>Analytics Dashboard
                </h2>
                <div>
                    <button type="button" onclick="window.print()" class="btn btn-back mr-2" style="background: white; color: var(--primary-red);">
                        <i class="fas fa-print mr-1"></i>พิมพ์รายงาน
                    </button>
                    <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-back">
                        <i class="fas fa-arrow-left mr-2"></i>กลับหน้าหลัก
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="content-container">
        <!-- Filter -->
        <div class="filter-card">
            <form id="filterForm">
                <div class="row">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label><i class="fas fa-calendar-alt mr-1"></i>วันที่เริ่มต้น</label>
                        <input type="date" class="form-control" id="date_start" value="<?php echo $default_date_start; ?>">
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label><i class="fas fa-calendar-alt mr-1"></i>วันที่สิ้นสุด</label>
                        <input type="date" class="form-control" id="date_end" value="<?php echo $default_date_end; ?>">
                    </div>
                    <?php if (in_array($_SESSION['role_level'], [1, 4])): ?>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label><i class="fas fa-building mr-1"></i>สาขา</label>
                        <select class="form-control" id="transport_origin">
                            <option value="">ทุกสาขา</option>
                            <?php foreach ($transport_origins as $origin): ?>
                                <option value="<?php echo $origin['transport_origin_id']; ?>">
                                    <?php echo htmlspecialchars($origin['origin_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label><i class="fas fa-map-marker-alt mr-1"></i>จังหวัด</label>
                        <select class="form-control" id="transport_province">
                            <option value="">ทุกจังหวัด</option>
                            <?php foreach ($provinces as $province): ?>
                                <option value="<?php echo htmlspecialchars($province); ?>">
                                    <?php echo htmlspecialchars($province); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Row 2 Filters -->
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label><i class="fas fa-map-pin mr-1"></i>อำเภอ</label>
                        <select class="form-control" id="filter_amphoe" disabled>
                            <option value="">ทุกอำเภอ</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label><i class="fas fa-truck mr-1"></i>ประเภทรถ</label>
                        <select class="form-control" id="filter_vehicle_type">
                            <option value="">กำลังโหลด...</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <label><i class="fas fa-user-circle mr-1"></i>คนขับ</label>
                        <select class="form-control" id="filter_driver">
                            <option value="">กำลังโหลด...</option>
                        </select>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-search btn-block">
                            <i class="fas fa-search mr-1"></i>ค้นหา / กรอง
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Stat Cards -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card total">
                    <div class="stat-icon"><i class="fas fa-box"></i></div>
                    <div class="stat-value" id="stat-total">0</div>
                    <div class="stat-label">ออเดอร์ทั้งหมด</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card pending">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-value" id="stat-pending">0</div>
                    <div class="stat-label">รอดำเนินการ</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card success">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-value" id="stat-delivered">0</div>
                    <div class="stat-label">ส่งแล้ว</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card danger">
                    <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
                    <div class="stat-value" id="stat-cancelled">0</div>
                    <div class="stat-label">ยกเลิก</div>
                </div>
            </div>
        </div>

        <!-- Charts Row 1: Status & Branch -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="chart-card">
                    <h5><i class="fas fa-chart-bar"></i>สัดส่วนสถานะออเดอร์</h5>
                    <div class="chart-container"><canvas id="statusChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-card">
                    <h5><i class="fas fa-building"></i>อันดับการส่งจากแต่ละสาขา</h5>
                    <div class="chart-container"><canvas id="branchChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Charts Row 2: Vehicle & Driver -->
        <div class="row mb-4">
            <div class="col-lg-6">
                <div class="chart-card">
                    <h5><i class="fas fa-truck"></i>ประเภทรถที่ใช้</h5>
                    <div class="chart-container"><canvas id="vehicleChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="chart-card">
                    <h5><i class="fas fa-user-tie"></i>Top 10 พนักงานขับรถยอดจัดส่งสูงสุด</h5>
                    <div class="chart-container"><canvas id="driverChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Top Locations Section Title -->
        <h4 class="section-title mt-4"><i class="fas fa-map-marked-alt"></i>Top 10 สถานที่จัดส่ง (แยกตามระดับ)</h4>

        <!-- Top Locations Row: 3 Charts in One Row -->
        <div class="row mb-4 top-location-charts">
            <div class="col-lg-3">
                <div class="chart-card">
                    <h5><i class="fas fa-map-marker-alt"></i>Top 10 จังหวัด</h5>
                    <div class="chart-container"><canvas id="provinceChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="chart-card">
                    <h5><i class="fas fa-map-pin"></i>Top 10 อำเภอ</h5>
                    <div class="chart-container"><canvas id="amphoeChart"></canvas></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="chart-card">
                    <h5><i class="fas fa-location-dot"></i>Top 10 ตำบล</h5>
                    <div class="chart-container" style="height: 320px;"><canvas id="tambonChart"></canvas></div>
                </div>
            </div>
        </div>

        <!-- Location Section Title -->
        <h4 class="section-title mt-4"><i class="fas fa-map-marked-alt"></i>สถานที่จัดส่ง</h4>

        <!-- Location Chart: Combined -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="chart-card">
                    <h5><i class="fas fa-map"></i>Top 10 สถานที่ที่มีการจัดส่งมากที่สุด (เรียงตามจำนวน)</h5>
                    <div class="chart-container" style="height: 400px;">
                        <canvas id="locationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Chart -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="chart-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0"><i class="fas fa-users"></i><span id="customerChartTitle">Top 10</span> ลูกค้าที่มีการสั่งสินค้ามากที่สุด</h5>
                        <div class="d-flex align-items-center">
                            <label for="topNSelect" class="mr-2 mb-0 text-muted" style="font-size:0.9rem;">แสดง:</label>
                            <select id="topNSelect" class="form-control form-control-sm" style="width:80px;">
                                <option value="10" selected>10</option>
                                <option value="20">20</option>
                                <option value="30">30</option>
                                <option value="40">40</option>
                                <option value="50">50</option>
                            </select>
                            <span class="ml-2 text-muted" style="font-size:0.9rem;">รายการ</span>
                        </div>
                    </div>
                    <div class="chart-container" id="customerChartContainer" style="height: 400px;">
                        <canvas id="customerChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Chart -->
        <div class="row">
            <div class="col-12">
                <div class="chart-card">
                    <h5><i class="fas fa-chart-area"></i>สรุปยอดจัดส่งรายเดือน</h5>
                    <div class="chart-container" style="height: 280px;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Register datalabels plugin
        Chart.register(ChartDataLabels);

        const charts = {};
        const colors = {
            red: '#dc3545', green: '#28a745', blue: '#007bff', yellow: '#ffc107',
            purple: '#6f42c1', orange: '#fd7e14', teal: '#20c997', pink: '#e83e8c', gray: '#6c757d'
        };

        function showLoading() { document.getElementById('loadingOverlay').style.display = 'flex'; }
        function hideLoading() { document.getElementById('loadingOverlay').style.display = 'none'; }

        function fetchAnalytics() {
            const params = new URLSearchParams({
                date_start: $('#date_start').val(),
                date_end: $('#date_end').val(),
                transport_origin: $('#transport_origin').val() || '',
                province: $('#transport_province').val() || '',
                amphoe: $('#filter_amphoe').val() || '',
                vehicle_type: $('#filter_vehicle_type').val() || '',
                driver_id: $('#filter_driver').val() || '',
                top_n: $('#topNSelect').val() || 10
            });

            showLoading();

            $.ajax({
                url: '<?php echo BASE_URL; ?>php/get_analytics_data.php?' + params.toString(),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        updateDashboard(response.data);
                    } else {
                        alert('เกิดข้อผิดพลาด: ' + (response.message || 'ไม่สามารถโหลดข้อมูลได้'));
                    }
                },
                error: function() {
                    alert('ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้');
                },
                complete: hideLoading
            });
        }

        function loadFilterOptions() {
            $.getJSON('<?php echo BASE_URL; ?>php/get_analytics_data.php?action=get_filter_options', function(response) {
                if(response.status === 'success') {
                    // Vehicle Types
                    let vHtml = '<option value="">ทุกประเภท</option>';
                    if(response.data.vehicle_types) {
                        response.data.vehicle_types.forEach(v => vHtml += `<option value="${v}">${v}</option>`);
                    }
                    $('#filter_vehicle_type').html(vHtml);

                    // Drivers
                    let dHtml = '<option value="">ทุกคน</option>';
                    if(response.data.drivers) {
                        response.data.drivers.forEach(d => dHtml += `<option value="${d.staff_id}">${d.staff_name}</option>`);
                    }
                    $('#filter_driver').html(dHtml);
                }
            });
        }

        // Load Amphoes on Province Change
        $('#transport_province').change(function() {
            const province = $(this).val();
            const amphoeSelect = $('#filter_amphoe');
            
            if(!province) {
                amphoeSelect.html('<option value="">ทุกอำเภอ</option>').prop('disabled', true);
                return;
            }

            $.getJSON('<?php echo BASE_URL; ?>php/get_analytics_data.php?action=get_amphoes&province=' + encodeURIComponent(province), function(response) {
                if(response.status === 'success') {
                    let html = '<option value="">ทุกอำเภอ</option>';
                    if(response.data) {
                        response.data.forEach(a => html += `<option value="${a}">${a}</option>`);
                    }
                    amphoeSelect.html(html).prop('disabled', false);
                }
            });
        });

        function updateDashboard(data) {
            // Stats
            $('#stat-total').text(data.order_stats.total.toLocaleString());
            const pending = data.order_stats.pending_ack + data.order_stats.pending_assign + data.order_stats.pending_delivery;
            $('#stat-pending').text(pending.toLocaleString());
            $('#stat-delivered').text(data.order_stats.delivered.toLocaleString());
            $('#stat-cancelled').text(data.order_stats.cancelled.toLocaleString());

            // Charts
            updateBarChart('status', 'statusChart', data.status_distribution, [colors.green, colors.blue, colors.yellow, colors.red, colors.gray]);
            updateBarChart('branch', 'branchChart', data.branch_rankings, colors.red);
            updateBarChart('vehicle', 'vehicleChart', data.vehicle_types, [colors.red, colors.green, colors.blue, colors.yellow, colors.purple, colors.orange]);
            updateHorizontalBarChart('driver', 'driverChart', data.driver_performance.map(d => ({label: d.name, value: d.count})), colors.orange);
            
            // New Top Location Charts
            updateBarChart('province', 'provinceChart', data.top_provinces, colors.blue);
            updateHorizontalBarChart('amphoe', 'amphoeChart', data.top_amphoes, colors.green);
            updateHorizontalBarChart('tambon', 'tambonChart', data.top_tambons, colors.purple);
            
            // Combined Location Chart (using Horizontal Bar)
            updateHorizontalBarChart('location', 'locationChart', data.location_rankings, colors.teal, true);
            
            // Customer Chart
            const topN = parseInt($('#topNSelect').val()) || 10;
            const chartHeight = Math.max(400, topN * 36);
            $('#customerChartContainer').css('height', chartHeight + 'px');
            $('#customerChartTitle').text('Top ' + topN);
            updateHorizontalBarChart('customer', 'customerChart', data.customer_rankings, colors.pink, true);

            updateLineChart('monthly', 'monthlyChart', data.monthly_summary);
        }

        function updateBarChart(key, canvasId, data, color) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            if (charts[key]) charts[key].destroy();
            if (!data || data.length === 0) return;
            
            charts[key] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{ data: data.map(d => d.value), backgroundColor: color, borderRadius: 6, maxBarThickness: 50 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 25, right: 20, left: 10, bottom: 10 } }, // Prevent label clipping
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            color: '#333',
                            font: { weight: 'bold', size: 14 },
                            formatter: value => value.toLocaleString()
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            grid: { color: '#f0f0f0' },
                            grace: '5%' // Add space at top
                        },
                        x: { 
                            grid: { display: false },
                            ticks: { autoSkip: false, maxRotation: 45, minRotation: 0 } // Force show all labels
                        }
                    }
                }
            });
        }

        function updateHorizontalBarChart(key, canvasId, data, color, isLongLabel = false) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            if (charts[key]) charts[key].destroy();
            if (!data || data.length === 0) return;
            
            // Special handling for tambon chart with very long labels
            const isTambonChart = canvasId === 'tambonChart';
            const maxLabelLength = isTambonChart ? 80 : 50;
            
            charts[key] = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{ data: data.map(d => d.value), backgroundColor: color, borderRadius: 4, maxBarThickness: 30 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    layout: { 
                        padding: { 
                            right: isTambonChart ? 60 : 40, 
                            top: 10, 
                            bottom: 10,
                            left: isTambonChart ? 20 : 10
                        } 
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: function(tooltipItems) {
                                    let label = tooltipItems[0].label;
                                    if (isTambonChart && label.length > maxLabelLength) {
                                        return label.match(/.{1,60}(\s|$)/g) || [label];
                                    }
                                    return label;
                                }
                            }
                        },
                        datalabels: {
                            anchor: 'end',
                            align: 'right',
                            color: '#333',
                            font: { weight: 'bold', size: isTambonChart ? 11 : 13 },
                            formatter: value => value.toLocaleString()
                        }
                    },
                    scales: {
                        x: { 
                            beginAtZero: true, 
                            grid: { color: '#f0f0f0' },
                            grace: '5%' // Add space on right
                        },
                        y: { 
                            grid: { display: false }, 
                            ticks: { 
                                font: { size: isTambonChart ? 10 : 12 },
                                callback: function(value, index, values) {
                                    let label = this.getLabelForValue(value);
                                    if (isLongLabel || isTambonChart) {
                                        if (label.length > maxLabelLength) {
                                            return label.substr(0, maxLabelLength) + '...'; 
                                        }
                                    }
                                    return label;
                                }
                            } 
                        }
                    }
                }
            });
        }

        function updateLineChart(key, canvasId, data) {
            const ctx = document.getElementById(canvasId).getContext('2d');
            if (charts[key]) charts[key].destroy();
            
            const gradient = ctx.createLinearGradient(0, 0, 0, 280);
            gradient.addColorStop(0, 'rgba(220, 53, 69, 0.25)');
            gradient.addColorStop(1, 'rgba(220, 53, 69, 0.01)');
            
            charts[key] = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.map(d => d.label),
                    datasets: [{
                        data: data.map(d => d.value),
                        borderColor: colors.red,
                        backgroundColor: gradient,
                        tension: 0.4,
                        fill: true,
                        pointBackgroundColor: colors.red,
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            anchor: 'end',
                            align: 'top',
                            color: colors.red,
                            font: { weight: 'bold', size: 14 },
                            formatter: value => value.toLocaleString()
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        $(document).ready(function() {
            fetchAnalytics();
            loadFilterOptions();
            $('#filterForm').on('submit', function(e) { e.preventDefault(); fetchAnalytics(); });
            $('#topNSelect').on('change', function() { fetchAnalytics(); });
        });
    </script>
</body>
</html>
