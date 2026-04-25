<?php
require_once '../php/check_session.php';
require_login([4]);
require_once '../php/db_connect.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

function access_redirect_back()
{
    header('Location: manage_users_roles.php');
    exit;
}

function access_set_flash($type, $message)
{
    $_SESSION['access_admin_flash'] = array(
        'type' => $type,
        'message' => $message,
    );
}

function available_role_levels()
{
    return array(
        1 => 'ระดับ 1 - ผู้เพิ่มข้อมูล',
        2 => 'ระดับ 2 - ผู้ปฏิบัติการ',
        3 => 'ระดับ 3 - ผู้จัดคิว/คนขับ',
        4 => 'ระดับ 4 - ผู้ดูแลระบบ',
    );
}

function role_level_badge_class($role_level)
{
    switch ((int) $role_level) {
        case 1:
            return 'badge-primary';
        case 2:
            return 'badge-info';
        case 3:
            return 'badge-warning';
        case 4:
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf_token();
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    try {
        if ($action === 'save_user') {
            $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
            $password = isset($_POST['password']) ? trim($_POST['password']) : '';
            $role_level = isset($_POST['role_level']) ? (int) $_POST['role_level'] : 0;
            $origin_id = isset($_POST['assigned_transport_origin_id']) && $_POST['assigned_transport_origin_id'] !== ''
                ? (int) $_POST['assigned_transport_origin_id']
                : null;
            $active = isset($_POST['active']) ? 1 : 0;
            $allowed_roles = array_keys(available_role_levels());

            if ($username === '' || $full_name === '') {
                throw new RuntimeException('กรุณากรอกชื่อผู้ใช้และชื่อที่แสดงให้ครบ');
            }

            if (!in_array($role_level, $allowed_roles, true)) {
                throw new RuntimeException('กรุณาเลือกระดับสิทธิ์ที่ถูกต้อง');
            }

            if ($user_id === 0 && $password === '') {
                throw new RuntimeException('กรุณากำหนดรหัสผ่านสำหรับผู้ใช้ใหม่');
            }

            if ($origin_id !== null) {
                $origin_stmt = $conn->prepare('SELECT transport_origin_id FROM transport_origins WHERE transport_origin_id = ?');
                $origin_stmt->bind_param('i', $origin_id);
                $origin_stmt->execute();
                $origin_exists = $origin_stmt->get_result()->num_rows > 0;
                $origin_stmt->close();
                if (!$origin_exists) {
                    throw new RuntimeException('ไม่พบต้นทางขนส่งที่เลือก');
                }
            }

            if ($user_id > 0) {
                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET username = ?, full_name = ?, password_hash = ?, role_level = ?, assigned_transport_origin_id = ?, active = ?
                         WHERE user_id = ?'
                    );
                    $stmt->bind_param('sssiiii', $username, $full_name, $password_hash, $role_level, $origin_id, $active, $user_id);
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET username = ?, full_name = ?, role_level = ?, assigned_transport_origin_id = ?, active = ?
                         WHERE user_id = ?'
                    );
                    $stmt->bind_param('ssiiii', $username, $full_name, $role_level, $origin_id, $active, $user_id);
                }
                $stmt->execute();
                $stmt->close();

                if ($user_id === (int) (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0)) {
                    $_SESSION['role_level'] = $role_level;
                    $_SESSION['assigned_transport_origin_id'] = $origin_id;
                    refresh_session_authorization(true);
                }

                access_set_flash('success', 'บันทึกการแก้ไขผู้ใช้เรียบร้อยแล้ว');
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    'INSERT INTO users (username, password_hash, full_name, role_level, assigned_transport_origin_id, active)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('sssiii', $username, $password_hash, $full_name, $role_level, $origin_id, $active);
                $stmt->execute();
                $stmt->close();

                access_set_flash('success', 'เพิ่มผู้ใช้งานใหม่เรียบร้อยแล้ว');
            }

            access_redirect_back();
        }

        if ($action === 'delete_user') {
            $user_id = isset($_POST['delete_user_id']) ? (int) $_POST['delete_user_id'] : 0;
            if ($user_id <= 0) {
                throw new RuntimeException('ไม่พบผู้ใช้ที่ต้องการลบ');
            }
            if ($user_id === (int) (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0)) {
                throw new RuntimeException('ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้');
            }

            $stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();

            access_set_flash('success', 'ลบผู้ใช้งานเรียบร้อยแล้ว');
            access_redirect_back();
        }

        throw new RuntimeException('ไม่รองรับคำสั่งที่ส่งมา');
    } catch (Exception $e) {
        access_set_flash('danger', $e->getMessage());
        access_redirect_back();
    }
}

$flash = isset($_SESSION['access_admin_flash']) ? $_SESSION['access_admin_flash'] : null;
unset($_SESSION['access_admin_flash']);

$edit_user_id = isset($_GET['edit_user']) ? (int) $_GET['edit_user'] : 0;

$user_form = array(
    'user_id' => 0,
    'username' => '',
    'full_name' => '',
    'role_level' => 1,
    'assigned_transport_origin_id' => '',
    'active' => 1,
);

if ($edit_user_id > 0) {
    $stmt = $conn->prepare('SELECT user_id, username, full_name, role_level, assigned_transport_origin_id, active FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $edit_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user_form = $result->fetch_assoc();
    }
    $stmt->close();
}

$users = array();
$users_result = $conn->query(
    'SELECT u.user_id, u.username, u.full_name, u.active, u.role_level,
            u.assigned_transport_origin_id, t.origin_name
     FROM users u
     LEFT JOIN transport_origins t ON u.assigned_transport_origin_id = t.transport_origin_id
     ORDER BY u.active DESC, u.username ASC'
);
while ($users_result && ($row = $users_result->fetch_assoc())) {
    $users[] = $row;
}

$transport_origins = array();
$origin_result = $conn->query('SELECT transport_origin_id, origin_name FROM transport_origins ORDER BY origin_name ASC');
while ($origin_result && ($row = $origin_result->fetch_assoc())) {
    $transport_origins[] = $row;
}

$role_options = available_role_levels();
$summary = array(
    'total_users' => count($users),
    'active_users' => 0,
    'admins' => 0,
);

foreach ($users as $user_row) {
    if ((int) $user_row['active'] === 1) {
        $summary['active_users']++;
    }
    if ((int) $user_row['role_level'] === 4) {
        $summary['admins']++;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้งาน</title>
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
        body {
            font-family: 'Sarabun', sans-serif;
            background:
                radial-gradient(circle at top left, rgba(220, 38, 38, 0.08), transparent 28%),
                linear-gradient(180deg, #fff7f7 0%, #f8fafc 58%, #f3f6fb 100%);
            min-height: 100vh;
        }
        .page-shell {
            max-width: 1280px;
            margin: 0 auto;
            padding: 2rem 1.25rem 3rem;
        }
        .hero-card,
        .section-card,
        .summary-card {
            backdrop-filter: blur(10px);
        }
        .hero-card,
        .section-card,
        .summary-card {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 18px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }
        .hero-card {
            padding: 2rem;
            margin-bottom: 1.75rem;
            overflow: hidden;
            position: relative;
        }
        .hero-card:before {
            content: '';
            position: absolute;
            top: -60px;
            right: -30px;
            width: 220px;
            height: 220px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(220, 38, 38, 0.14), rgba(220, 38, 38, 0));
            pointer-events: none;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .45rem .8rem;
            border-radius: 999px;
            background: rgba(220, 38, 38, 0.08);
            color: #b91c1c;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .hero-title {
            font-size: clamp(1.9rem, 3vw, 2.6rem);
            font-weight: 700;
            color: #111827;
            margin-bottom: .5rem;
        }
        .hero-text {
            color: #475569;
            max-width: 680px;
            margin-bottom: 0;
            line-height: 1.75;
        }
        .summary-card {
            padding: 1.15rem 1.3rem;
            height: 100%;
        }
        .summary-label {
            color: #64748b;
            font-size: .92rem;
            margin-bottom: .35rem;
        }
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            line-height: 1;
            color: #0f172a;
        }
        .layout-grid {
            display: grid;
            grid-template-columns: minmax(320px, 390px) minmax(0, 1fr);
            gap: 1.75rem;
            align-items: start;
        }
        .section-card {
            padding: 1.6rem;
            margin-bottom: 1.6rem;
        }
        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .section-heading h4 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
        }
        .muted-note {
            color: #64748b;
            font-size: .95rem;
            line-height: 1.7;
        }
        .sticky-card {
            position: sticky;
            top: 1.5rem;
        }
        .form-label {
            font-weight: 600;
            color: #334155;
        }
        .form-control,
        .custom-select {
            border-radius: 12px;
            min-height: 46px;
            border-color: #d7deea;
            box-shadow: none;
        }
        .form-control:focus,
        .custom-select:focus {
            border-color: rgba(220, 38, 38, 0.45);
            box-shadow: 0 0 0 0.2rem rgba(220, 38, 38, 0.12);
        }
        .table-responsive {
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #e5eaf2;
        }
        #usersTable thead th {
            background: linear-gradient(180deg, #fff5f5 0%, #fff 100%);
            border-bottom: 1px solid #e7edf4;
            color: #334155;
        }
        #usersTable tbody td {
            vertical-align: middle;
        }
        #usersTable tbody tr:hover {
            background: #fff8f8;
        }
        .action-cell {
            white-space: nowrap;
        }
        .action-cell .action-group {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: nowrap;
        }
        .badge {
            font-weight: 600;
            padding: .45rem .65rem;
            border-radius: 999px;
        }
        .alert {
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
        }
        div.dataTables_wrapper div.dataTables_filter input,
        div.dataTables_wrapper div.dataTables_length select {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            min-height: 38px;
        }
        @media (max-width: 991.98px) {
            .page-shell {
                padding: 1.2rem .85rem 2rem;
            }
            .layout-grid {
                grid-template-columns: 1fr;
            }
            .sticky-card {
                position: static;
            }
            .hero-card {
                padding: 1.4rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <div class="hero-card">
            <div class="hero-badge">
                <i class="fas fa-user-shield"></i>
                พื้นที่ผู้ดูแลระบบ
            </div>
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end">
                <div>
                    <h1 class="hero-title">จัดการผู้ใช้งาน</h1>
                    <p class="hero-text">
                        หน้านี้ใช้ระดับสิทธิ์คงที่ 1-4 เท่านั้น ผู้ดูแลระบบสามารถเพิ่ม แก้ไข ปิดการใช้งาน และลบผู้ใช้ได้โดยไม่ต้องสร้าง role เพิ่มเอง
                    </p>
                </div>
                <div class="mt-3 mt-lg-0">
                    <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-outline-danger">
                        <i class="fas fa-arrow-left mr-2"></i>กลับหน้าแดชบอร์ด
                    </a>
                </div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col-lg-4 mb-3">
                <div class="summary-card">
                    <div class="summary-label">ผู้ใช้ทั้งหมด</div>
                    <div class="summary-value"><?php echo number_format($summary['total_users']); ?></div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="summary-card">
                    <div class="summary-label">ผู้ใช้ที่เปิดใช้งาน</div>
                    <div class="summary-value text-success"><?php echo number_format($summary['active_users']); ?></div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="summary-card">
                    <div class="summary-label">ผู้ดูแลระบบ</div>
                    <div class="summary-value text-danger"><?php echo number_format($summary['admins']); ?></div>
                </div>
            </div>
        </div>

        <div class="layout-grid">
            <div>
                <div class="section-card sticky-card">
                    <div class="section-heading">
                        <div>
                            <h4><?php echo (int) $user_form['user_id'] > 0 ? 'แก้ไขผู้ใช้งาน' : 'เพิ่มผู้ใช้งาน'; ?></h4>
                            <div class="muted-note">กำหนดสิทธิ์จากระดับ 1-4 ที่ระบบใช้งานจริงอยู่แล้ว เพื่อให้ดูแลง่ายและไม่ซับซ้อน</div>
                        </div>
                    </div>
                    <form method="post">
                        <?php echo csrf_input(); ?>
                        <input type="hidden" name="action" value="save_user">
                        <input type="hidden" name="user_id" value="<?php echo (int) $user_form['user_id']; ?>">
                        <div class="form-group">
                            <label class="form-label" for="username">ชื่อผู้ใช้</label>
                            <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_form['username']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="full_name">ชื่อที่แสดง</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user_form['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="password">รหัสผ่าน</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="<?php echo (int) $user_form['user_id'] > 0 ? 'เว้นว่างไว้หากไม่ต้องการเปลี่ยนรหัสผ่าน' : 'จำเป็นสำหรับผู้ใช้ใหม่'; ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="role_level">ระดับสิทธิ์</label>
                            <select class="custom-select" id="role_level" name="role_level" required>
                                <?php foreach ($role_options as $role_value => $role_label): ?>
                                    <option value="<?php echo $role_value; ?>" <?php echo (int) $user_form['role_level'] === (int) $role_value ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($role_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="assigned_transport_origin_id">ต้นทางขนส่งที่รับผิดชอบ</label>
                            <select class="custom-select" id="assigned_transport_origin_id" name="assigned_transport_origin_id">
                                <option value="">ไม่กำหนดตายตัว</option>
                                <?php foreach ($transport_origins as $origin): ?>
                                    <option value="<?php echo (int) $origin['transport_origin_id']; ?>" <?php echo (string) $user_form['assigned_transport_origin_id'] === (string) $origin['transport_origin_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($origin['origin_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="custom-control custom-switch mb-4">
                            <input type="checkbox" class="custom-control-input" id="active" name="active" <?php echo !empty($user_form['active']) ? 'checked' : ''; ?>>
                            <label class="custom-control-label" for="active">เปิดใช้งานบัญชีนี้</label>
                        </div>
                        <div class="d-flex flex-wrap" style="gap:.75rem;">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-save mr-2"></i>บันทึกข้อมูล
                            </button>
                            <?php if ((int) $user_form['user_id'] > 0): ?>
                                <a href="manage_users_roles.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-plus mr-2"></i>เพิ่มผู้ใช้ใหม่
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div>
                <div class="section-card">
                    <div class="section-heading">
                        <div>
                            <h4>รายการผู้ใช้งาน</h4>
                            <div class="muted-note">ค้นหา ตรวจสอบ และจัดการผู้ใช้ทั้งหมดได้จากตารางเดียว</div>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table id="usersTable" class="table table-hover table-striped w-100">
                            <thead class="thead-light">
                                <tr>
                                    <th>ชื่อผู้ใช้</th>
                                    <th>ชื่อที่แสดง</th>
                                    <th>ระดับสิทธิ์</th>
                                    <th>ต้นทางขนส่ง</th>
                                    <th>สถานะ</th>
                                    <th style="width: 180px;">จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td>
                                            <span class="badge <?php echo role_level_badge_class($user['role_level']); ?>">
                                                <?php echo htmlspecialchars($role_options[(int) $user['role_level']]); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['origin_name'] ? $user['origin_name'] : '-'); ?></td>
                                        <td>
                                            <span class="badge <?php echo (int) $user['active'] === 1 ? 'badge-success' : 'badge-secondary'; ?>">
                                                <?php echo (int) $user['active'] === 1 ? 'ใช้งาน' : 'ปิดใช้งาน'; ?>
                                            </span>
                                        </td>
                                        <td class="action-cell">
                                            <div class="action-group">
                                                <a href="?edit_user=<?php echo (int) $user['user_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit mr-1"></i>แก้ไข
                                                </a>
                                                <?php if ((int) $user['user_id'] !== (int) (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0)): ?>
                                                    <form method="post" class="delete-user-form mb-0">
                                                        <?php echo csrf_input(); ?>
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="delete_user_id" value="<?php echo (int) $user['user_id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash-alt mr-1"></i>ลบ
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
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
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap4.min.js"></script>
    <script>
        $(function () {
            $('#usersTable').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[0, 'asc']]
            });

            $('.delete-user-form').on('submit', function (event) {
                event.preventDefault();
                const form = this;

                Swal.fire({
                    title: 'ยืนยันการลบผู้ใช้',
                    text: 'การลบนี้ไม่สามารถย้อนกลับได้',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    confirmButtonText: 'ลบผู้ใช้',
                    cancelButtonText: 'ยกเลิก'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>
