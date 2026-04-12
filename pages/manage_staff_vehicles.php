<?php
require_once '../php/check_session.php';
require_login([4]);
require_once '../php/db_connect.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

function redirect_back_to_page()
{
    header('Location: manage_staff_vehicles.php');
    exit;
}

function set_flash_message($type, $message)
{
    $_SESSION['fleet_admin_flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_staff') {
            $staff_id = isset($_POST['staff_id']) ? (int) $_POST['staff_id'] : 0;
            $staff_name = trim($_POST['staff_name'] ?? '');
            $staff_phone = trim($_POST['staff_phone'] ?? '');
            $staff_role = trim($_POST['staff_role'] ?? '');
            $active = isset($_POST['staff_active']) ? 1 : 0;

            if ($staff_name === '') {
                throw new RuntimeException('กรุณาระบุชื่อพนักงาน');
            }

            if ($staff_id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE staff
                     SET staff_name = ?, staff_phone = NULLIF(?, ''), staff_role = NULLIF(?, ''), active = ?
                     WHERE staff_id = ?"
                );
                $stmt->bind_param('sssii', $staff_name, $staff_phone, $staff_role, $active, $staff_id);
                $stmt->execute();
                $stmt->close();
                set_flash_message('success', 'บันทึกข้อมูลพนักงานเรียบร้อยแล้ว');
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO staff (staff_name, staff_phone, staff_role, default_vehicle_id, active)
                     VALUES (?, NULLIF(?, ''), NULLIF(?, ''), NULL, ?)"
                );
                $stmt->bind_param('sssi', $staff_name, $staff_phone, $staff_role, $active);
                $stmt->execute();
                $stmt->close();
                set_flash_message('success', 'เพิ่มพนักงานใหม่เรียบร้อยแล้ว');
            }

            redirect_back_to_page();
        }

        if ($action === 'save_vehicle') {
            $vehicle_id = isset($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : 0;
            $vehicle_name = trim($_POST['vehicle_name'] ?? '');
            $vehicle_plate = trim($_POST['vehicle_plate'] ?? '');
            $active = isset($_POST['vehicle_active']) ? 1 : 0;

            if ($vehicle_plate === '') {
                throw new RuntimeException('กรุณาระบุทะเบียนรถ');
            }

            if ($vehicle_id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE vehicles
                     SET vehicle_name = ?, vehicle_plate = ?, active = ?
                     WHERE vehicle_id = ?"
                );
                $stmt->bind_param('ssii', $vehicle_name, $vehicle_plate, $active, $vehicle_id);
                $stmt->execute();
                $stmt->close();
                set_flash_message('success', 'บันทึกข้อมูลรถเรียบร้อยแล้ว');
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO vehicles (vehicle_name, vehicle_plate, active)
                     VALUES (?, ?, ?)"
                );
                $stmt->bind_param('ssi', $vehicle_name, $vehicle_plate, $active);
                $stmt->execute();
                $stmt->close();
                set_flash_message('success', 'เพิ่มรถใหม่เรียบร้อยแล้ว');
            }

            redirect_back_to_page();
        }

        if ($action === 'save_default_vehicle') {
            $staff_id = isset($_POST['binding_staff_id']) ? (int) $_POST['binding_staff_id'] : 0;
            $vehicle_id = isset($_POST['binding_vehicle_id']) && $_POST['binding_vehicle_id'] !== ''
                ? (int) $_POST['binding_vehicle_id']
                : null;

            if ($staff_id <= 0) {
                throw new RuntimeException('กรุณาเลือกพนักงาน');
            }

            $staff_stmt = $conn->prepare("SELECT staff_id FROM staff WHERE staff_id = ?");
            $staff_stmt->bind_param('i', $staff_id);
            $staff_stmt->execute();
            $staff_exists = $staff_stmt->get_result()->num_rows > 0;
            $staff_stmt->close();

            if (!$staff_exists) {
                throw new RuntimeException('ไม่พบพนักงานที่เลือก');
            }

            if ($vehicle_id !== null) {
                $vehicle_stmt = $conn->prepare("SELECT vehicle_id FROM vehicles WHERE vehicle_id = ?");
                $vehicle_stmt->bind_param('i', $vehicle_id);
                $vehicle_stmt->execute();
                $vehicle_exists = $vehicle_stmt->get_result()->num_rows > 0;
                $vehicle_stmt->close();

                if (!$vehicle_exists) {
                    throw new RuntimeException('ไม่พบรถที่เลือก');
                }
            }

            if ($vehicle_id === null) {
                $stmt = $conn->prepare("UPDATE staff SET default_vehicle_id = NULL WHERE staff_id = ?");
                $stmt->bind_param('i', $staff_id);
            } else {
                $stmt = $conn->prepare("UPDATE staff SET default_vehicle_id = ? WHERE staff_id = ?");
                $stmt->bind_param('ii', $vehicle_id, $staff_id);
            }
            $stmt->execute();
            $stmt->close();

            set_flash_message('success', 'อัปเดตรถประจำของพนักงานเรียบร้อยแล้ว');
            redirect_back_to_page();
        }

        throw new RuntimeException('ไม่พบ action ที่ต้องการ');
    } catch (Throwable $e) {
        set_flash_message('danger', $e->getMessage());
        redirect_back_to_page();
    }
}

$flash = $_SESSION['fleet_admin_flash'] ?? null;
unset($_SESSION['fleet_admin_flash']);

$edit_staff_id = isset($_GET['edit_staff']) ? (int) $_GET['edit_staff'] : 0;
$edit_vehicle_id = isset($_GET['edit_vehicle']) ? (int) $_GET['edit_vehicle'] : 0;
$bind_staff_id = isset($_GET['bind_staff']) ? (int) $_GET['bind_staff'] : 0;

$staff_form = [
    'staff_id' => 0,
    'staff_name' => '',
    'staff_phone' => '',
    'staff_role' => '',
    'active' => 1
];

if ($edit_staff_id > 0) {
    $stmt = $conn->prepare("SELECT staff_id, staff_name, staff_phone, staff_role, active FROM staff WHERE staff_id = ?");
    $stmt->bind_param('i', $edit_staff_id);
    $stmt->execute();
    $staff_result = $stmt->get_result();
    if ($staff_result->num_rows > 0) {
        $staff_form = $staff_result->fetch_assoc();
    }
    $stmt->close();
}

$vehicle_form = [
    'vehicle_id' => 0,
    'vehicle_name' => '',
    'vehicle_plate' => '',
    'active' => 1
];

if ($edit_vehicle_id > 0) {
    $stmt = $conn->prepare("SELECT vehicle_id, vehicle_name, vehicle_plate, active FROM vehicles WHERE vehicle_id = ?");
    $stmt->bind_param('i', $edit_vehicle_id);
    $stmt->execute();
    $vehicle_result = $stmt->get_result();
    if ($vehicle_result->num_rows > 0) {
        $vehicle_form = $vehicle_result->fetch_assoc();
    }
    $stmt->close();
}

$staff_rows = [];
$staff_result = $conn->query(
    "SELECT s.staff_id, s.staff_name, s.staff_phone, s.staff_role, s.active, s.default_vehicle_id,
            v.vehicle_name, v.vehicle_plate, v.active AS vehicle_active
     FROM staff s
     LEFT JOIN vehicles v ON s.default_vehicle_id = v.vehicle_id
     ORDER BY s.active DESC, s.staff_name ASC"
);
while ($staff_result && ($row = $staff_result->fetch_assoc())) {
    $staff_rows[] = $row;
}

$vehicle_rows = [];
$vehicle_result = $conn->query(
    "SELECT vehicle_id, vehicle_name, vehicle_plate, active
     FROM vehicles
     ORDER BY active DESC, vehicle_name ASC, vehicle_plate ASC"
);
while ($vehicle_result && ($row = $vehicle_result->fetch_assoc())) {
    $vehicle_rows[] = $row;
}

$binding_staff_options = [];
$binding_vehicle_options = [];
$binding_vehicle_id = null;

foreach ($staff_rows as $row) {
    $binding_staff_options[] = $row;
    if ($bind_staff_id > 0 && $bind_staff_id === (int) $row['staff_id']) {
        $binding_vehicle_id = $row['default_vehicle_id'] !== null ? (int) $row['default_vehicle_id'] : null;
    }
}

foreach ($vehicle_rows as $row) {
    $binding_vehicle_options[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการพนักงานและรถ</title>
    <meta name="theme-color" content="#dc2626">
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png" sizes="192x192">
    <link rel="apple-touch-icon" href="<?php echo BASE_URL; ?>assets/images/icon-192x192.png">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; }
        .section-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.9);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .section-card h4 {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .table td, .table th { vertical-align: middle; }
        .badge-soft {
            padding: 0.45rem 0.7rem;
            border-radius: 999px;
            font-weight: 500;
        }
        .badge-soft-success { background: #dcfce7; color: #166534; }
        .badge-soft-secondary { background: #e2e8f0; color: #334155; }
        .muted-note { color: #64748b; font-size: 0.92rem; }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0.35rem 0.6rem;
        }
        .dataTables_wrapper .page-link {
            color: #dc2626;
        }
        .dataTables_wrapper .page-item.active .page-link {
            background-color: #dc2626;
            border-color: #dc2626;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div>
                <h2 class="mb-1">จัดการพนักงานขับรถและรถ</h2>
                <div class="muted-note">สำหรับผู้ดูแลระบบเท่านั้น ใช้กำหนดข้อมูลพนักงาน รถ และรถประจำของพนักงาน</div>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left mr-1"></i>กลับหน้าหลัก
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?>">
                <?php echo htmlspecialchars($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-4">
                <div class="section-card">
                    <h4><i class="fas fa-user mr-2 text-danger"></i><?php echo $staff_form['staff_id'] ? 'แก้ไขพนักงาน' : 'เพิ่มพนักงาน'; ?></h4>
                    <form method="post">
                        <input type="hidden" name="action" value="save_staff">
                        <input type="hidden" name="staff_id" value="<?php echo (int) $staff_form['staff_id']; ?>">
                        <div class="form-group">
                            <label for="staff_name">ชื่อพนักงาน</label>
                            <input type="text" class="form-control" id="staff_name" name="staff_name" required value="<?php echo htmlspecialchars($staff_form['staff_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="staff_phone">เบอร์โทร</label>
                            <input type="text" class="form-control" id="staff_phone" name="staff_phone" value="<?php echo htmlspecialchars($staff_form['staff_phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="staff_role">บทบาท/หมายเหตุ</label>
                            <input type="text" class="form-control" id="staff_role" name="staff_role" value="<?php echo htmlspecialchars($staff_form['staff_role'] ?? ''); ?>">
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="staff_active" name="staff_active" value="1" <?php echo !empty($staff_form['active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="staff_active">เปิดใช้งานพนักงานคนนี้</label>
                        </div>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-save mr-1"></i>บันทึกพนักงาน
                        </button>
                        <?php if ($staff_form['staff_id']): ?>
                            <a href="manage_staff_vehicles.php" class="btn btn-outline-secondary ml-2">ยกเลิกการแก้ไข</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="section-card">
                    <h4><i class="fas fa-truck mr-2 text-danger"></i><?php echo $vehicle_form['vehicle_id'] ? 'แก้ไขรถ' : 'เพิ่มรถ'; ?></h4>
                    <form method="post">
                        <input type="hidden" name="action" value="save_vehicle">
                        <input type="hidden" name="vehicle_id" value="<?php echo (int) $vehicle_form['vehicle_id']; ?>">
                        <div class="form-group">
                            <label for="vehicle_name">ประเภทรถ / ชื่อรถ</label>
                            <input type="text" class="form-control" id="vehicle_name" name="vehicle_name" value="<?php echo htmlspecialchars($vehicle_form['vehicle_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="vehicle_plate">ทะเบียนรถ</label>
                            <input type="text" class="form-control" id="vehicle_plate" name="vehicle_plate" required value="<?php echo htmlspecialchars($vehicle_form['vehicle_plate']); ?>">
                        </div>
                        <div class="form-group form-check">
                            <input type="checkbox" class="form-check-input" id="vehicle_active" name="vehicle_active" value="1" <?php echo !empty($vehicle_form['active']) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="vehicle_active">เปิดใช้งานรถคันนี้</label>
                        </div>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-save mr-1"></i>บันทึกรถ
                        </button>
                        <?php if ($vehicle_form['vehicle_id']): ?>
                            <a href="manage_staff_vehicles.php" class="btn btn-outline-secondary ml-2">ยกเลิกการแก้ไข</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="section-card">
                    <h4><i class="fas fa-link mr-2 text-danger"></i>กำหนดรถประจำของพนักงาน</h4>
                    <form method="post">
                        <input type="hidden" name="action" value="save_default_vehicle">
                        <div class="form-group">
                            <label for="binding_staff_id">พนักงาน</label>
                            <select class="form-control" id="binding_staff_id" name="binding_staff_id" required>
                                <option value="">-- เลือกพนักงาน --</option>
                                <?php foreach ($binding_staff_options as $staff): ?>
                                    <option value="<?php echo (int) $staff['staff_id']; ?>" <?php echo $bind_staff_id === (int) $staff['staff_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff['staff_name']); ?><?php echo empty($staff['active']) ? ' [ปิดใช้งาน]' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="binding_vehicle_id">รถประจำ</label>
                            <select class="form-control" id="binding_vehicle_id" name="binding_vehicle_id">
                                <option value="">-- ไม่กำหนดรถประจำ --</option>
                                <?php foreach ($binding_vehicle_options as $vehicle): ?>
                                    <option value="<?php echo (int) $vehicle['vehicle_id']; ?>" <?php echo $binding_vehicle_id === (int) $vehicle['vehicle_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(trim(($vehicle['vehicle_name'] !== '' ? $vehicle['vehicle_name'] . ' ' : '') . '(' . $vehicle['vehicle_plate'] . ')')); ?><?php echo empty($vehicle['active']) ? ' [ปิดใช้งาน]' : ''; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-save mr-1"></i>บันทึกรถประจำ
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="section-card">
                    <h4><i class="fas fa-users mr-2 text-danger"></i>รายการพนักงานขับรถ</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover w-100" id="staffTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>ชื่อ</th>
                                    <th>เบอร์โทร</th>
                                    <th>บทบาท</th>
                                    <th>รถประจำ</th>
                                    <th>สถานะ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($staff_rows)): ?>
                                    <tr><td colspan="7" class="text-center">ยังไม่มีข้อมูลพนักงาน</td></tr>
                                <?php else: ?>
                                    <?php foreach ($staff_rows as $row): ?>
                                        <?php
                                        $default_vehicle = '-';
                                        if (!empty($row['default_vehicle_id'])) {
                                            $default_vehicle = trim(($row['vehicle_name'] !== '' ? $row['vehicle_name'] . ' ' : '') . '(' . $row['vehicle_plate'] . ')');
                                            if (isset($row['vehicle_active']) && (int) $row['vehicle_active'] === 0) {
                                                $default_vehicle .= ' [ปิดใช้งาน]';
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo (int) $row['staff_id']; ?></td>
                                            <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['staff_phone'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['staff_role'] ?: '-'); ?></td>
                                            <td><?php echo htmlspecialchars($default_vehicle); ?></td>
                                            <td>
                                                <span class="badge-soft <?php echo !empty($row['active']) ? 'badge-soft-success' : 'badge-soft-secondary'; ?>">
                                                    <?php echo !empty($row['active']) ? 'ใช้งาน' : 'ปิดใช้งาน'; ?>
                                                </span>
                                            </td>
                                            <td class="text-nowrap">
                                                <a href="manage_staff_vehicles.php?edit_staff=<?php echo (int) $row['staff_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i> แก้ไข
                                                </a>
                                                <a href="manage_staff_vehicles.php?bind_staff=<?php echo (int) $row['staff_id']; ?>" class="btn btn-sm btn-outline-danger">
                                                    <i class="fas fa-link"></i> ตั้งค่ารถประจำ
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="section-card">
                    <h4><i class="fas fa-shipping-fast mr-2 text-danger"></i>รายการรถ</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover w-100" id="vehicleTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>ID</th>
                                    <th>ชื่อ/ประเภทรถ</th>
                                    <th>ทะเบียน</th>
                                    <th>สถานะ</th>
                                    <th>จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($vehicle_rows)): ?>
                                    <tr><td colspan="5" class="text-center">ยังไม่มีข้อมูลรถ</td></tr>
                                <?php else: ?>
                                    <?php foreach ($vehicle_rows as $row): ?>
                                        <tr>
                                            <td><?php echo (int) $row['vehicle_id']; ?></td>
                                            <td><?php echo htmlspecialchars($row['vehicle_name'] !== '' ? $row['vehicle_name'] : '-'); ?></td>
                                            <td><?php echo htmlspecialchars($row['vehicle_plate']); ?></td>
                                            <td>
                                                <span class="badge-soft <?php echo !empty($row['active']) ? 'badge-soft-success' : 'badge-soft-secondary'; ?>">
                                                    <?php echo !empty($row['active']) ? 'ใช้งาน' : 'ปิดใช้งาน'; ?>
                                                </span>
                                            </td>
                                            <td class="text-nowrap">
                                                <a href="manage_staff_vehicles.php?edit_vehicle=<?php echo (int) $row['vehicle_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i> แก้ไข
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="section-card">
                    <h4><i class="fas fa-map-signs mr-2 text-danger"></i>สรุปรถประจำของพนักงาน</h4>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped w-100" id="bindingSummaryTable">
                            <thead class="thead-light">
                                <tr>
                                    <th>พนักงาน</th>
                                    <th>รถประจำ</th>
                                    <th>หมายเหตุ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_rows as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['staff_name']); ?></td>
                                        <td>
                                            <?php
                                            if (!empty($row['default_vehicle_id'])) {
                                                echo htmlspecialchars(trim(($row['vehicle_name'] !== '' ? $row['vehicle_name'] . ' ' : '') . '(' . $row['vehicle_plate'] . ')'));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            if (empty($row['default_vehicle_id'])) {
                                                echo 'ยังไม่กำหนดรถประจำ';
                                            } elseif (isset($row['vehicle_active']) && (int) $row['vehicle_active'] === 0) {
                                                echo 'รถคันนี้ถูกปิดใช้งานอยู่';
                                            } else {
                                                echo 'พร้อมใช้เป็นค่าเริ่มต้นในหน้าจัดคน/รถ';
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
    <script>
        $(function () {
            const thaiLanguage = {
                search: 'ค้นหา:',
                lengthMenu: 'แสดง _MENU_ รายการ',
                info: 'แสดง _START_ ถึง _END_ จาก _TOTAL_ รายการ',
                infoEmpty: 'แสดง 0 ถึง 0 จาก 0 รายการ',
                infoFiltered: '(กรองจากทั้งหมด _MAX_ รายการ)',
                zeroRecords: 'ไม่พบข้อมูลที่ตรงกัน',
                emptyTable: 'ไม่มีข้อมูลในตาราง',
                paginate: {
                    first: 'แรก',
                    previous: 'ก่อนหน้า',
                    next: 'ถัดไป',
                    last: 'สุดท้าย'
                }
            };

            $('#staffTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[0, 'asc']],
                language: thaiLanguage,
                columnDefs: [
                    { orderable: false, targets: 6 }
                ]
            });

            $('#vehicleTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[0, 'asc']],
                language: thaiLanguage,
                columnDefs: [
                    { orderable: false, targets: 4 }
                ]
            });

            $('#bindingSummaryTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[0, 'asc']],
                language: thaiLanguage
            });
        });
    </script>
</body>
</html>
