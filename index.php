<?php
// logistic/index.php
// PHP logic ส่วนบนยังคงเหมือนเดิมเพื่อการโหลดหน้าครั้งแรกที่สมบูรณ์
require_once __DIR__ . '/php/check_session.php';
require_once __DIR__ . '/php/db_connect.php'; 
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $project_folder . '/');
$dashboard_where_clauses = [];$dashboard_params = [];$dashboard_param_types = "";
if (is_logged_in() && $_SESSION['role_level'] != 4 && !empty($_SESSION['assigned_transport_origin_id'])) {
    $dashboard_where_clauses[] = "transport_origin_id = ?";
    $dashboard_params[] = $_SESSION['assigned_transport_origin_id'];
    $dashboard_param_types .= "i";
}
$dashboard_sql_where = "";
if (!empty($dashboard_where_clauses)) { $dashboard_sql_where = " WHERE " . implode(" AND ", $dashboard_where_clauses); }
$sql_counts = "SELECT SUM(CASE WHEN status = 'รอรับเรื่อง' THEN 1 ELSE 0 END) AS count_pending_ack, SUM(CASE WHEN status = 'รับเรื่อง' THEN 1 ELSE 0 END) AS count_pending_assign, SUM(CASE WHEN status = 'รอส่งของ' THEN 1 ELSE 0 END) AS count_pending_delivery, SUM(CASE WHEN status = 'ส่งของแล้ว' AND DATE(updated_at) = CURDATE() THEN 1 ELSE 0 END) AS count_delivered_today FROM orders" . $dashboard_sql_where;
$stmt_counts = $conn->prepare($sql_counts);
if (!empty($dashboard_params)) { $stmt_counts->bind_param($dashboard_param_types, ...$dashboard_params); }
$stmt_counts->execute(); $result_counts = $stmt_counts->get_result();
$counts = ['pending_ack' => 0, 'pending_assign' => 0, 'pending_delivery' => 0, 'delivered_today' => 0];
if ($result_counts && $result_counts->num_rows > 0) {
    $counts_data = $result_counts->fetch_assoc();
    $counts['pending_ack'] = $counts_data['count_pending_ack'] ?: 0;
    $counts['pending_assign'] = $counts_data['count_pending_assign'] ?: 0;
    $counts['pending_delivery'] = $counts_data['count_pending_delivery'] ?: 0;
    $counts['delivered_today'] = $counts_data['count_delivered_today'] ?: 0;
}
$stmt_counts->close();
$sql_status_distribution = "SELECT status, COUNT(order_id) as count FROM orders" . $dashboard_sql_where . " GROUP BY status";
$stmt_status = $conn->prepare($sql_status_distribution);
if (!empty($dashboard_params)) { $stmt_status->bind_param($dashboard_param_types, ...$dashboard_params); }
$stmt_status->execute(); $result_status_distribution = $stmt_status->get_result();
$status_data_for_chart = [];
if ($result_status_distribution && $result_status_distribution->num_rows > 0) { while($row = $result_status_distribution->fetch_assoc()){ $status_data_for_chart[] = ['label' => htmlspecialchars($row['status']), 'value' => (int)$row['count']]; } }
$status_chart_json = json_encode($status_data_for_chart);
$stmt_status->close();
$daily_orders_data = [];
$daily_where_clauses = $dashboard_where_clauses; $daily_params = $dashboard_params; $daily_param_types = $dashboard_param_types;
$daily_where_clauses[] = "order_date >= CURDATE() - INTERVAL 6 DAY AND order_date <= CURDATE()";
$sql_daily_where = " WHERE " . implode(" AND ", $daily_where_clauses);
$sql_daily_orders = "SELECT DATE(order_date) as order_day, COUNT(order_id) as count FROM orders" . $sql_daily_where . " GROUP BY DATE(order_date) ORDER BY DATE(order_date) ASC";
$stmt_daily = $conn->prepare($sql_daily_orders);
if (!empty($daily_params)) { $stmt_daily->bind_param($daily_param_types, ...$daily_params); }
$stmt_daily->execute(); $result_daily_orders = $stmt_daily->get_result();
$temp_daily_data = [];
if ($result_daily_orders && $result_daily_orders->num_rows > 0) { while($row = $result_daily_orders->fetch_assoc()){ $temp_daily_data[$row['order_day']] = (int)$row['count']; } }
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $formatted_date = date('d/m', strtotime($date)); 
    $daily_orders_data[] = ['label' => $formatted_date, 'value' => isset($temp_daily_data[$date]) ? $temp_daily_data[$date] : 0];
}
$daily_orders_chart_json = json_encode($daily_orders_data);
$stmt_daily->close();
$conn->close(); 
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NR Logistics - Dashboard</title>
    <!-- *** เพิ่ม: Favicon *** -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚚</text></svg>">
    
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        .site-brand{font-size:1.1rem;font-weight:600;color:rgba(255,255,255,.9);margin-bottom:5px}.dashboard-header{background-color:var(--primary-red);color:var(--text-light);padding:25px 20px;margin-bottom:30px;border-radius:var(--border-radius-base)}.dashboard-header .container-fluid{background-color:transparent!important;padding-left:0;padding-right:0}.dashboard-header h1{color:var(--text-light);margin-bottom:0;font-size:1.75rem}.stat-card{background-color:var(--bg-white);border-radius:var(--border-radius-base);padding:20px;margin-bottom:20px;box-shadow:0 2px 8px rgba(0,0,0,.07);text-align:center;border-left:5px solid var(--primary-red-lighter)}.stat-card .stat-number{font-size:2.5rem;font-weight:700;color:var(--primary-red);margin-bottom:5px}.stat-card .stat-label{font-size:1rem;color:var(--text-muted)}.quick-actions .btn{margin-bottom:10px;width:100%;padding:10px 15px;font-size:1.05rem}.chart-container{background-color:var(--bg-white);padding:20px;border-radius:var(--border-radius-base);box-shadow:0 2px 8px rgba(0,0,0,.07);margin-bottom:20px;height:380px;position:relative}.chart-container h5{text-align:center;margin-bottom:20px}.section-title{margin-bottom:20px;padding-bottom:10px;border-bottom:1px solid var(--border-color);font-weight:600}
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

        <h3 class="section-title">ภาพรวมสถานะรายการ</h3>
        <div class="row">
            <div class="col-lg-3 col-md-6 col-sm-6"><div class="stat-card"><div class="stat-number" id="count-pending-ack"><?php echo $counts['pending_ack']; ?></div><div class="stat-label">รอรับเรื่อง</div></div></div>
            <div class="col-lg-3 col-md-6 col-sm-6"><div class="stat-card"><div class="stat-number" id="count-pending-assign"><?php echo $counts['pending_assign']; ?></div><div class="stat-label">รอจัดคน/รถ</div></div></div>
            <div class="col-lg-3 col-md-6 col-sm-6"><div class="stat-card"><div class="stat-number" id="count-pending-delivery"><?php echo $counts['pending_delivery']; ?></div><div class="stat-label">รอส่งของ</div></div></div>
            <div class="col-lg-3 col-md-6 col-sm-6"><div class="stat-card"><div class="stat-number" id="count-delivered-today"><?php echo $counts['delivered_today']; ?></div><div class="stat-label">ส่งของแล้ว (วันนี้)</div></div></div>
        </div>
        
        <?php if(is_logged_in()): ?>
            <h3 class="section-title mt-4">เมนูลัด</h3>
            <div class="row quick-actions">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2"><a href="<?php echo BASE_URL; ?>pages/price_checker.php" class="btn btn-outline-danger btn-block"><i class="fas fa-search-dollar mr-1"></i>เช็คราคา</a></div>
                <?php if (has_role([1, 4])): ?><div class="col-lg-2 col-md-4 col-sm-6 mb-2"><a href="<?php echo BASE_URL; ?>pages/add_order_form.php" class="btn btn-primary btn-block"><i class="fas fa-plus-circle mr-1"></i>เพิ่มรายการ</a></div><?php endif; ?>
                
                <?php if (has_role([2, 3, 4])): ?>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2"><a href="<?php echo BASE_URL; ?>pages/pending_acknowledgement.php" class="btn btn-info btn-block"><i class="fas fa-inbox mr-1"></i>รอรับเรื่อง</a></div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2"><a href="<?php echo BASE_URL; ?>pages/pending_assignment.php" class="btn btn-warning btn-block"><i class="fas fa-user-cog mr-1"></i>รอจัดคน/รถ</a></div>
                 <div class="col-lg-2 col-md-4 col-sm-6 mb-2"><a href="<?php echo BASE_URL; ?>pages/pending_delivery.php" class="btn btn-success btn-block"><i class="fas fa-truck-loading mr-1"></i>รอส่งของ</a></div>
                <?php endif; ?>
                
                <!-- *** แก้ไข: ปุ่มรายการทั้งหมดเข้าได้ทุกสิทธิ์ *** -->
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/all_orders.php" class="btn btn-secondary btn-block"><i class="fas fa-list-alt mr-1"></i>รายการทั้งหมด</a>
                </div>
            </div>
        <?php endif; ?>

        <h3 class="section-title mt-5">ข้อมูลสรุป (กราฟ)</h3>
        <div class="row">
            <div class="col-md-6"><div class="chart-container" id="statusChartContainer"><h5>สัดส่วนรายการตามสถานะ</h5><canvas id="statusChart"></canvas></div></div>
            <div class="col-md-6"><div class="chart-container" id="dailyOrdersChartContainer"><h5>จำนวนรายการต่อวัน (7 วันล่าสุด)</h5><canvas id="dailyOrdersChart"></canvas></div></div>
        </div>
    </div> 

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script> 
    <script>
        $(document).ready(function(){
            let statusChartInstance;
            let dailyOrdersChartInstance;

            function createOrUpdateStatusChart(chartData) {
                const container = $('#statusChartContainer');
                if (statusChartInstance) { statusChartInstance.destroy(); }
                if (Array.isArray(chartData) && chartData.length > 0) {
                    container.find('.no-data-message').remove();
                    container.find('canvas').show();
                    const labels = chartData.map(item => item.label);
                    const dataValues = chartData.map(item => item.value);
                    const colorMap = {'รอรับเรื่อง':"rgba(231,76,60,0.7)", 'รับเรื่อง':"rgba(52,152,219,0.7)", 'รอส่งของ':"rgba(241,196,15,0.7)", 'ส่งของแล้ว':"rgba(46,204,113,0.7)", 'ยกเลิก':"rgba(149,165,166,0.7)", 'default':"rgba(155,89,182,0.7)"};
                    const bgColors = labels.map(label => colorMap[label] || colorMap['default']);
                    const config = {
                        type: 'doughnut',
                        data: { labels: labels, datasets: [{ label: 'จำนวนรายการ', data: dataValues, backgroundColor: bgColors, borderColor: bgColors.map(c=>c.replace('0.7','1')), borderWidth: 1 }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, title: { display: false } } }
                    };
                    statusChartInstance = new Chart(document.getElementById('statusChart'), config);
                } else {
                    container.find('canvas').hide();
                    if (!container.find('.no-data-message').length) {
                        container.append('<p class="text-muted text-center mt-5 pt-5 no-data-message">ไม่มีข้อมูลสถานะสำหรับแสดงกราฟ</p>');
                    }
                }
            }

            function createOrUpdateDailyChart(chartData) {
                const container = $('#dailyOrdersChartContainer');
                if (dailyOrdersChartInstance) { dailyOrdersChartInstance.destroy(); }
                if (Array.isArray(chartData) && chartData.some(item => item.value > 0)) {
                    container.find('.no-data-message').remove();
                    container.find('canvas').show();
                    const labels = chartData.map(item => item.label);
                    const dataValues = chartData.map(item => item.value);
                    const config = {
                        type: 'bar',
                        data: { labels: labels, datasets: [{ label: 'จำนวนรายการ', data: dataValues, backgroundColor: 'rgba(231, 76, 60, 0.7)', borderColor: 'rgba(231, 76, 60, 1)', borderWidth: 1 }] },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false }, title: { display: false } } }
                    };
                    dailyOrdersChartInstance = new Chart(document.getElementById('dailyOrdersChart'), config);
                } else {
                    container.find('canvas').hide();
                    if (!container.find('.no-data-message').length) {
                        container.append('<p class="text-muted text-center mt-5 pt-5 no-data-message">ไม่มีข้อมูลรายการสำหรับแสดงกราฟ</p>');
                    }
                }
            }
            
            function updateDashboardData(data) {
                $('#count-pending-ack').text(data.big_numbers.pending_ack);
                $('#count-pending-assign').text(data.big_numbers.pending_assign);
                $('#count-pending-delivery').text(data.big_numbers.pending_delivery);
                $('#count-delivered-today').text(data.big_numbers.delivered_today);
                createOrUpdateStatusChart(data.status_chart_data);
                createOrUpdateDailyChart(data.daily_chart_data);
            }

            function fetchDashboardData() {
                $.ajax({
                    url: "<?php echo BASE_URL; ?>php/get_dashboard_data.php",
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            updateDashboardData(response);
                        }
                    },
                    error: function() {
                        // console.error("Could not fetch dashboard data.");
                    }
                });
            }

            // Initial chart creation
            createOrUpdateStatusChart(<?php echo $status_chart_json; ?>);
            createOrUpdateDailyChart(<?php echo $daily_orders_chart_json; ?>);
            
            // Set interval to fetch new data every 3 seconds (3000 milliseconds)
            setInterval(fetchDashboardData, 5000);
        });
    </script>
</body>
</html>
