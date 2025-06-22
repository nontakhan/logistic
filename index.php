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
    <meta name="theme-color" content="#dc2626">
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">

    <link rel="icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-red: #dc2626;
            --primary-red-light: #ef4444;
            --primary-red-dark: #b91c1c;
            --primary-red-lighter: #fca5a5;
            --secondary-red: #fee2e2;
            --accent-orange: #f97316;
            --accent-blue: #3b82f6;
            --success-green: #10b981;
            --warning-yellow: #f59e0b;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --text-dark: #1f2937;
            --text-light: #ffffff;
            --text-muted: #6b7280;
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --border-radius: 12px;
            --border-radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #fef2f2 0%, #f8fafc 100%);
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        /* Header Styles */
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--primary-red-dark) 100%);
            color: var(--text-light);
            padding: 2rem 0;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="0.5"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
            opacity: 0.3;
        }

        .dashboard-header .container {
            position: relative;
            z-index: 2;
        }

        .site-brand {
            font-size: 1.25rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .site-brand i {
            font-size: 1.5rem;
        }

        .dashboard-header h1 {
            color: var(--text-light);
            margin-bottom: 0;
            font-size: 2.25rem;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .user-info {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .user-info span {
            font-weight: 500;
            margin-right: 1rem;
        }

        /* Stats Cards */
        .stats-section {
            margin-bottom: 3rem;
        }

        .stat-card {
            background: var(--bg-white);
            border-radius: var(--border-radius-lg);
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-red), var(--primary-red-light));
        }

        .stat-card.pending-ack::before {
            background: linear-gradient(90deg, var(--primary-red), var(--accent-orange));
        }

        .stat-card.pending-assign::before {
            background: linear-gradient(90deg, var(--accent-blue), #6366f1);
        }

        .stat-card.pending-delivery::before {
            background: linear-gradient(90deg, var(--warning-yellow), #fbbf24);
        }

        .stat-card.delivered-today::before {
            background: linear-gradient(90deg, var(--success-green), #34d399);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            opacity: 0.8;
            transition: var(--transition);
        }

        .stat-card.pending-ack .stat-icon { color: var(--primary-red); }
        .stat-card.pending-assign .stat-icon { color: var(--accent-blue); }
        .stat-card.pending-delivery .stat-icon { color: var(--warning-yellow); }
        .stat-card.delivered-today .stat-icon { color: var(--success-green); }

        .stat-card:hover .stat-icon {
            transform: scale(1.1);
            opacity: 1;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-red), var(--primary-red-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
        }

        .stat-card.pending-assign .stat-number {
            background: linear-gradient(135deg, var(--accent-blue), #6366f1);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.pending-delivery .stat-number {
            background: linear-gradient(135deg, var(--warning-yellow), #fbbf24);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card.delivered-today .stat-number {
            background: linear-gradient(135deg, var(--success-green), #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 0;
        }

        /* Section Titles */
        .section-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary-red);
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: var(--primary-red-light);
        }

        /* Quick Actions */
        .quick-actions .btn {
            margin-bottom: 1rem;
            padding: 1rem 1.5rem;
            font-size: 1rem;
            font-weight: 500;
            border-radius: var(--border-radius);
            transition: var(--transition);
            border: none;
            position: relative;
            overflow: hidden;
        }

        .quick-actions .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .quick-actions .btn i {
            margin-right: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-red) 0%, var(--primary-red-light) 100%);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-red-dark) 0%, var(--primary-red) 100%);
            color: white;
        }

        .btn-outline-danger {
            border: 2px solid var(--primary-red);
            color: var(--primary-red);
            background: transparent;
        }

        .btn-outline-danger:hover {
            background: var(--primary-red);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--accent-blue) 0%, #6366f1 100%);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-yellow) 0%, #fbbf24 100%);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-green) 0%, #34d399 100%);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #9ca3af 100%);
        }

        /* Charts */
        .chart-container {
            background: var(--bg-white);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            height: 400px;
            position: relative;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .chart-container:hover {
            box-shadow: var(--shadow-xl);
        }

        .chart-container h5 {
            text-align: center;
            margin-bottom: 1.5rem;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1.25rem;
        }

        /* Alert Styles */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border-left: 4px solid var(--warning-yellow);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.5rem 0;
            }

            .dashboard-header h1 {
                font-size: 1.75rem;
            }

            .stat-card {
                padding: 1.5rem;
                margin-bottom: 1rem;
            }

            .stat-number {
                font-size: 2.5rem;
            }

            .stat-icon {
                font-size: 2rem;
            }

            .chart-container {
                height: 300px;
                padding: 1.5rem;
            }

            .section-title {
                font-size: 1.5rem;
            }
        }
        
        .no-data-message {
            color: var(--text-muted);
            font-style: italic;
            text-align: center;
            padding: 3rem;
            background: rgba(249, 250, 251, 0.5);
            border-radius: var(--border-radius);
            border: 2px dashed var(--border-color);
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="mb-3 mb-md-0">
                    <div class="site-brand">
                        <i class="fas fa-truck"></i>
                        NR Logistics
                    </div>
                    <h1><i class="fas fa-tachometer-alt mr-3"></i>Dashboard</h1>
                </div>
                <div>
                    <?php if (is_logged_in()): ?>
                        <div class="user-info">
                            <span>สวัสดี, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                            <a href="<?php echo BASE_URL; ?>logout.php" class="btn btn-sm btn-outline-light">
                                <i class="fas fa-sign-out-alt mr-1"></i> ออกจากระบบ
                            </a>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-light">
                            <i class="fas fa-sign-in-alt mr-2"></i> เข้าสู่ระบบ
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_SESSION['access_denied_msg'])): ?>
            <div class="alert alert-warning" role="alert">
                <i class="fas fa-exclamation-triangle mr-2"></i> 
                <?php echo $_SESSION['access_denied_msg']; unset($_SESSION['access_denied_msg']); ?>
            </div>
        <?php endif; ?>

        <div class="stats-section">
            <h3 class="section-title">
                <i class="fas fa-chart-pie mr-2"></i>
                ภาพรวมสถานะรายการ
            </h3>
            <div class="row">
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="stat-card pending-ack">
                        <div class="stat-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <div class="stat-number" id="count-pending-ack"><?php echo $counts['pending_ack']; ?></div>
                        <div class="stat-label">รอรับเรื่อง</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="stat-card pending-assign">
                        <div class="stat-icon">
                            <i class="fas fa-user-cog"></i>
                        </div>
                        <div class="stat-number" id="count-pending-assign"><?php echo $counts['pending_assign']; ?></div>
                        <div class="stat-label">รอจัดคน/รถ</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="stat-card pending-delivery">
                        <div class="stat-icon">
                            <i class="fas fa-truck-loading"></i>
                        </div>
                        <div class="stat-number" id="count-pending-delivery"><?php echo $counts['pending_delivery']; ?></div>
                        <div class="stat-label">รอส่งของ</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="stat-card delivered-today">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-number" id="count-delivered-today"><?php echo $counts['delivered_today']; ?></div>
                        <div class="stat-label">ส่งของแล้ว (วันนี้)</div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if(is_logged_in()): ?>
            <h3 class="section-title">
                <i class="fas fa-bolt mr-2"></i>
                เมนูลัด
            </h3>
            <div class="row quick-actions">
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/price_checker.php" class="btn btn-outline-danger btn-block">
                        <i class="fas fa-search-dollar"></i>เช็คราคา
                    </a>
                </div>
                <?php if (has_role([1, 2, 4])): ?>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/add_order_form.php" class="btn btn-primary btn-block">
                        <i class="fas fa-plus-circle"></i>เพิ่มรายการ
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if (has_role([2, 3, 4])): ?>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/pending_acknowledgement.php" class="btn btn-info btn-block">
                        <i class="fas fa-inbox"></i>รอรับเรื่อง
                    </a>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/pending_assignment.php" class="btn btn-warning btn-block">
                        <i class="fas fa-user-cog"></i>รอจัดคน/รถ
                    </a>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/pending_delivery.php" class="btn btn-success btn-block">
                        <i class="fas fa-truck-loading"></i>รอส่งของ
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="col-lg-2 col-md-4 col-sm-6 mb-2">
                    <a href="<?php echo BASE_URL; ?>pages/all_orders.php" class="btn btn-secondary btn-block">
                        <i class="fas fa-list-alt"></i>รายการ
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <h3 class="section-title mt-5">
            <i class="fas fa-chart-bar mr-2"></i>
            ข้อมูลสรุป (กราฟ)
        </h3>
        <div class="row">
            <div class="col-lg-6 col-md-12">
                <div class="chart-container" id="statusChartContainer">
                    <h5><i class="fas fa-pie-chart mr-2"></i>สัดส่วนรายการตามสถานะ</h5>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            <div class="col-lg-6 col-md-12">
                <div class="chart-container" id="dailyOrdersChartContainer">
                    <h5><i class="fas fa-bar-chart mr-2"></i>จำนวนรายการต่อวัน (7 วันล่าสุด)</h5>
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
        $(document).ready(function(){
            // *** แก้ไข: เพิ่ม JavaScript สำหรับ Auto-Refresh กลับเข้ามา ***
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
                    const colorMap = {'รอรับเรื่อง':"rgba(231,76,60,0.8)", 'รับเรื่อง':"rgba(59,130,246,0.8)", 'รอส่งของ':"rgba(245,158,11,0.8)", 'ส่งของแล้ว':"rgba(16,185,129,0.8)", 'ยกเลิก':"rgba(107,114,128,0.8)", 'default':"rgba(155,89,182,0.8)"};
                    const bgColors = labels.map(label => colorMap[label] || colorMap['default']);
                    const config = {
                        type: 'doughnut',
                        data: { labels: labels, datasets: [{ label: 'จำนวนรายการ', data: dataValues, backgroundColor: bgColors, borderColor: 'white', borderWidth: 2, hoverOffset: 4 }] },
                        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' }, title: { display: false } } }
                    };
                    statusChartInstance = new Chart(document.getElementById('statusChart'), config);
                } else {
                    container.find('canvas').hide();
                    if (!container.find('.no-data-message').length) {
                        container.append('<div class="no-data-message"><i class="fas fa-chart-pie fa-3x mb-3 text-muted"></i><p>ไม่มีข้อมูลสถานะสำหรับแสดงกราฟ</p></div>');
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
                        data: { labels: labels, datasets: [{ label: 'จำนวนรายการ', data: dataValues, backgroundColor: 'rgba(220, 38, 38, 0.8)', borderColor: 'rgba(220, 38, 38, 1)', borderWidth: 2, borderRadius: 8 }] },
                        options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }, plugins: { legend: { display: false }, title: { display: false } } }
                    };
                    dailyOrdersChartInstance = new Chart(document.getElementById('dailyOrdersChart'), config);
                } else {
                    container.find('canvas').hide();
                    if (!container.find('.no-data-message').length) {
                        container.append('<div class="no-data-message"><i class="fas fa-chart-bar fa-3x mb-3 text-muted"></i><p>ไม่มีข้อมูลรายการสำหรับแสดงกราฟ</p></div>');
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
            
            // Auto-refresh data every 3 seconds
            setInterval(fetchDashboardData, 3000);
        });
    </script>
</body>
</html>
