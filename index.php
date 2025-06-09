<?php
// logistic/index.php

// --- เพิ่ม: เปิด Error Reporting ชั่วคราวเพื่อ Debug ---
// หากหน้านี้แสดงหน้าขาว หรือข้อมูลไม่ขึ้น ให้ลองเอา comment 2 บรรทัดล่างนี้ออกเพื่อดู error
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// กำหนด BASE_URL (วิธีที่ง่ายขึ้น)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // หาชื่อโฟลเดอร์โปรเจกต์อัตโนมัติ
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $project_folder . '/');

// --- เพิ่ม: ตรวจสอบการ include ไฟล์ ---
if (!@include_once __DIR__ . '/php/check_session.php') {
    die('เกิดข้อผิดพลาด: ไม่สามารถโหลดไฟล์ check_session.php ได้');
}
if (!@include_once __DIR__ . '/php/db_connect.php') {
    die('เกิดข้อผิดพลาด: ไม่สามารถโหลดไฟล์ db_connect.php ได้');
}

// --- เพิ่ม: ตรวจสอบการเชื่อมต่อฐานข้อมูล ---
if ($conn->connect_error) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $conn->connect_error);
}

// --- ดึงข้อมูลสำหรับ Big Numbers ---
$sql_counts = "SELECT 
                 SUM(CASE WHEN status = 'รอรับเรื่อง' THEN 1 ELSE 0 END) AS count_pending_ack,
                 SUM(CASE WHEN status = 'รับเรื่อง' THEN 1 ELSE 0 END) AS count_pending_assign,
                 SUM(CASE WHEN status = 'รอส่งของ' THEN 1 ELSE 0 END) AS count_pending_delivery,
                 SUM(CASE WHEN status = 'ส่งของแล้ว' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) AS count_delivered_today
               FROM orders";
$result_counts = $conn->query($sql_counts);
$counts = ['pending_ack' => 0, 'pending_assign' => 0, 'pending_delivery' => 0, 'delivered_today' => 0];
if ($result_counts === false) {
    // เพิ่ม: แสดง error หาก query ล้มเหลว
    error_log("SQL Error (Big Numbers): " . $conn->error); 
} else if ($result_counts->num_rows > 0) {
    $counts_data = $result_counts->fetch_assoc();
    $counts['pending_ack'] = $counts_data['count_pending_ack'] ?: 0;
    $counts['pending_assign'] = $counts_data['count_pending_assign'] ?: 0;
    $counts['pending_delivery'] = $counts_data['count_pending_delivery'] ?: 0;
    $counts['delivered_today'] = $counts_data['count_delivered_today'] ?: 0;
}

// --- ดึงข้อมูลสำหรับกราฟวงกลม: สัดส่วนสถานะ ---
$sql_status_distribution = "SELECT status, COUNT(order_id) as count FROM orders GROUP BY status";
$result_status_distribution = $conn->query($sql_status_distribution);
$status_data_for_chart = [];
if ($result_status_distribution === false) {
    error_log("SQL Error (Status Chart): " . $conn->error);
} else if ($result_status_distribution->num_rows > 0) { 
    while($row = $result_status_distribution->fetch_assoc()){ 
        $status_data_for_chart[] = ['label' => htmlspecialchars($row['status']), 'value' => (int)$row['count']]; 
    } 
}
$status_chart_json = json_encode($status_data_for_chart);

// --- ดึงข้อมูลสำหรับกราฟแท่ง: จำนวนรายการที่สร้างใน 7 วันล่าสุด ---
$daily_orders_data = [];
$sql_daily_orders = "SELECT DATE(order_date) as order_day, COUNT(order_id) as count FROM orders WHERE order_date >= CURDATE() - INTERVAL 6 DAY AND order_date <= CURDATE() GROUP BY DATE(order_date) ORDER BY DATE(order_date) ASC";
$result_daily_orders = $conn->query($sql_daily_orders);
$temp_daily_data = [];
if ($result_daily_orders === false) {
    error_log("SQL Error (Daily Chart): " . $conn->error);
} else if ($result_daily_orders->num_rows > 0) { 
    while($row = $result_daily_orders->fetch_assoc()){ 
        $temp_daily_data[$row['order_day']] = (int)$row['count']; 
    } 
}
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $formatted_date = date('d/m', strtotime($date)); 
    $daily_orders_data[] = ['label' => $formatted_date, 'value' => isset($temp_daily_data[$date]) ? $temp_daily_data[$date] : 0];
}
$daily_orders_chart_json = json_encode($daily_orders_data);

$conn->close(); 
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NR Logistics - Dashboard</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    
    <style>
        .site-brand {
            font-size: 1.1rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 5px;
        }
        .dashboard-header {
            background-color: var(--primary-red);
            color: var(--text-light);
            padding: 25px 20px;
            margin-bottom: 30px;
            border-radius: var(--border-radius-base);
        }
        .dashboard-header .container-fluid {
            background-color: transparent !important;
            padding-left: 0;
            padding-right: 0;
        }
        .dashboard-header h1 {
            color: var(--text-light);
            margin-bottom: 0;
            font-size: 1.75rem;
        }
        .stat-card {
            background-color: var(--bg-white);
            border-radius: var(--border-radius-base);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            text-align: center;
            border-left: 5px solid var(--primary-red-lighter);
        }
        .stat-card .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-red);
            margin-bottom: 5px;
        }
        .stat-card .stat-label {
            font-size: 1rem;
            color: var(--text-muted);
        }
        .quick-actions .btn {
            margin-bottom: 10px;
            width: 100%;
            padding: 10px 15px;
            font-size: 1.05rem;
        }
        .chart-container {
            background-color: var(--bg-white);
            padding: 20px;
            border-radius: var(--border-radius-base);
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            margin-bottom: 20px;
            height: 380px;
            position: relative;
        }
        .chart-container h5 {
            text-align: center;
            margin-bottom: 20px;
        }
        .section-title {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="site-brand">NR Logistics</div>
                    <h1><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</h1>
                </div>
                <div>
                    <?php if (is_logged_in()): ?>
                        <div class="text-right">
                            <span class="text-white">สวัสดี, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                            <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-sm btn-outline-light ml-2"><i class="fas fa-sign-out-alt"></i> ออกจากระบบ</a>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-light"><i class="fas fa-sign-in-alt"></i> เข้าสู่ระบบ</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <?php if (isset($_SESSION['access_denied_msg'])): ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['access_denied_msg']; unset($_SESSION['access_denied_msg']); ?>
            </div>
        <?php endif; ?>

        <!-- Big Numbers -->
        <h3 class="section-title">ภาพรวมสถานะรายการ</h3>
        <div class="row">
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $counts['pending_ack']; ?></div>
                    <div class="stat-label">รอรับเรื่อง</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $counts['pending_assign']; ?></div>
                    <div class="stat-label">รอจัดคน/รถ</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $counts['pending_delivery']; ?></div>
                    <div class="stat-label">รอส่งของ</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $counts['delivered_today']; ?></div>
                    <div class="stat-label">ส่งของแล้ว (วันนี้)</div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <?php if(is_logged_in()): ?>
            <h3 class="section-title mt-4">เมนูลัด</h3>
            <div class="row quick-actions">
                <?php if (has_role([1, 4])): ?>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/add_order_form.php" class="btn btn-primary btn-block"><i class="fas fa-plus-circle mr-1"></i>เพิ่มรายการ</a>
                </div>
                <?php endif; ?>
                <?php if (has_role([2, 3, 4])): ?>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/pending_acknowledgement.php" class="btn btn-info btn-block"><i class="fas fa-inbox mr-1"></i>รอรับเรื่อง</a>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/pending_assignment.php" class="btn btn-warning btn-block"><i class="fas fa-user-cog mr-1"></i>รอจัดคน/รถ</a>
                </div>
                 <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/pending_delivery.php" class="btn btn-success btn-block"><i class="fas fa-truck-loading mr-1"></i>รอส่งของ</a>
                </div>
                <?php endif; ?>
                <?php if (has_role([1, 2, 3, 4])): ?>
                 <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/all_orders.php" class="btn btn-secondary btn-block"><i class="fas fa-list-alt mr-1"></i>รายการทั้งหมด</a>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Charts Section -->
        <h3 class="section-title mt-5">ข้อมูลสรุป (กราฟ)</h3>
        <div class="row">
            <div class="col-md-6">
                <div class="chart-container" id="statusChartContainer"> 
                    <h5>สัดส่วนรายการตามสถานะ</h5>
                    <canvas id="statusChart"></canvas> 
                </div>
            </div>
            <div class="col-md-6">
                <div class="chart-container" id="dailyOrdersChartContainer"> 
                    <h5>จำนวนรายการต่อวัน (7 วันล่าสุด)</h5>
                    <canvas id="dailyOrdersChart"></canvas> 
                </div>
            </div>
        </div>
    </div> 

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script> 
    <script>
        $(document).ready(function() {
            const statusChartData = <?php echo $status_chart_json; ?>;
            if (Array.isArray(statusChartData) && statusChartData.length > 0) {
                const statusLabels = statusChartData.map(item => item.label);
                const statusDataValues = statusChartData.map(item => item.value);
                const statusColorMap = {
                    'รอรับเรื่อง': 'rgba(231, 76, 60, 0.7)',
                    'รับเรื่อง': 'rgba(52, 152, 219, 0.7)',
                    'รอส่งของ': 'rgba(241, 196, 15, 0.7)',
                    'ส่งของแล้ว': 'rgba(46, 204, 113, 0.7)',
                    'ยกเลิก': 'rgba(149, 165, 166, 0.7)',
                    'default': 'rgba(155, 89, 182, 0.7)'
                };
                const backgroundColors = statusLabels.map(label => statusColorMap[label] || statusColorMap['default']);
                const statusPieData = {
                    labels: statusLabels,
                    datasets: [{
                        label: 'จำนวนรายการ',
                        data: statusDataValues,
                        backgroundColor: backgroundColors,
                        borderColor: backgroundColors.map(color => color.replace('0.7', '1')),
                        borderWidth: 1
                    }]
                };
                const statusPieConfig = {
                    type: 'doughnut',
                    data: statusPieData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { position: 'bottom' },
                            title: { display: false }
                        }
                    }
                };
                const statusChartCtx = document.getElementById('statusChart');
                if (statusChartCtx) { new Chart(statusChartCtx, statusPieConfig); }
            } else {
                 const statusChartContainerEl = document.getElementById('statusChartContainer');
                 if(statusChartContainerEl){
                    const canvasEl = document.getElementById('statusChart');
                    if (canvasEl) canvasEl.style.display = 'none'; 
                    let titleHtml = '<h5>สัดส่วนรายการตามสถานะ</h5>';
                    if (!statusChartContainerEl.querySelector('h5')) { statusChartContainerEl.insertAdjacentHTML('afterbegin', titleHtml); }
                    if (!statusChartContainerEl.querySelector('.no-data-message')) { statusChartContainerEl.insertAdjacentHTML('beforeend', '<p class="text-muted text-center mt-5 pt-5 no-data-message">ไม่มีข้อมูลสถานะสำหรับแสดงกราฟ</p>'); }
                 }
            }
            const dailyOrdersChartData = <?php echo $daily_orders_chart_json; ?>;
            if (Array.isArray(dailyOrdersChartData) && dailyOrdersChartData.length > 0) {
                const dailyLabels = dailyOrdersChartData.map(item => item.label);
                const dailyDataValues = dailyOrdersChartData.map(item => item.value);
                const dailyChartConfig = {
                    type: 'bar',
                    data: {
                        labels: dailyLabels,
                        datasets: [{
                            label: 'จำนวนรายการ',
                            data: dailyDataValues,
                            backgroundColor: 'rgba(231, 76, 60, 0.7)',
                            borderColor: 'rgba(231, 76, 60, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
                        plugins: { legend: { display: false }, title: { display: false } }
                    }
                };
                const dailyOrdersChartCtx = document.getElementById('dailyOrdersChart');
                if (dailyOrdersChartCtx) { new Chart(dailyOrdersChartCtx, dailyChartConfig); }
            } else {
                const dailyOrdersContainerEl = document.getElementById('dailyOrdersChartContainer');
                if (dailyOrdersContainerEl) {
                    const canvasEl = document.getElementById('dailyOrdersChart');
                    if (canvasEl) canvasEl.style.display = 'none';
                    let titleHtml = '<h5>จำนวนรายการต่อวัน (7 วันล่าสุด)</h5>';
                    if (!dailyOrdersContainerEl.querySelector('h5')) { dailyOrdersContainerEl.insertAdjacentHTML('afterbegin', titleHtml); }
                    if (!dailyOrdersContainerEl.querySelector('.no-data-message')) { dailyOrdersContainerEl.insertAdjacentHTML('beforeend', '<p class="text-muted text-center mt-5 pt-5 no-data-message">ไม่มีข้อมูลรายการสำหรับแสดงกราฟ</p>'); }
                }
            }
        });
    </script>
</body>
</html>
