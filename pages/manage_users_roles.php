<?php
require_once '../php/check_session.php';
require_permission('users.manage', [4]);
require_once '../php/db_connect.php';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$project_folder = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_project_folder = str_replace('/pages', '', $project_folder);
define('BASE_URL', $protocol . $_SERVER['HTTP_HOST'] . $base_project_folder . '/');

function access_redirect_back(): void
{
    header('Location: manage_users_roles.php');
    exit;
}

function access_set_flash(string $type, string $message): void
{
    $_SESSION['access_admin_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function generate_role_key(string $role_name): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $role_name);
    $ascii = strtolower($ascii ?: $role_name);
    $ascii = preg_replace('/[^a-z0-9]+/', '-', $ascii);
    $ascii = trim($ascii ?? '', '-');
    return $ascii !== '' ? $ascii : 'role-' . time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'save_user') {
            $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
            $username = trim($_POST['username'] ?? '');
            $full_name = trim($_POST['full_name'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $role_id = isset($_POST['role_id']) && $_POST['role_id'] !== '' ? (int) $_POST['role_id'] : null;
            $origin_id = isset($_POST['assigned_transport_origin_id']) && $_POST['assigned_transport_origin_id'] !== ''
                ? (int) $_POST['assigned_transport_origin_id']
                : null;
            $active = isset($_POST['active']) ? 1 : 0;

            if ($username === '' || $full_name === '') {
                throw new RuntimeException('Please provide both username and display name.');
            }

            if ($user_id === 0 && $password === '') {
                throw new RuntimeException('A password is required for a new user.');
            }

            if ($role_id !== null) {
                $role_stmt = $conn->prepare('SELECT role_id, COALESCE(legacy_role_level, 1) AS legacy_role_level FROM roles WHERE role_id = ? AND active = 1');
                $role_stmt->bind_param('i', $role_id);
                $role_stmt->execute();
                $role_row = $role_stmt->get_result()->fetch_assoc();
                $role_stmt->close();
                if (!$role_row) {
                    throw new RuntimeException('The selected role is not available.');
                }
                $legacy_role_level = (int) $role_row['legacy_role_level'];
            } else {
                $legacy_role_level = 1;
            }

            if ($origin_id !== null) {
                $origin_stmt = $conn->prepare('SELECT transport_origin_id FROM transport_origins WHERE transport_origin_id = ?');
                $origin_stmt->bind_param('i', $origin_id);
                $origin_stmt->execute();
                $origin_exists = $origin_stmt->get_result()->num_rows > 0;
                $origin_stmt->close();
                if (!$origin_exists) {
                    throw new RuntimeException('The selected transport origin was not found.');
                }
            }

            if ($user_id > 0) {
                if ($password !== '') {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET username = ?, full_name = ?, password_hash = ?, role_id = ?, role_level = ?, assigned_transport_origin_id = ?, active = ?
                         WHERE user_id = ?'
                    );
                    $stmt->bind_param('sssiiiii', $username, $full_name, $password_hash, $role_id, $legacy_role_level, $origin_id, $active, $user_id);
                } else {
                    $stmt = $conn->prepare(
                        'UPDATE users
                         SET username = ?, full_name = ?, role_id = ?, role_level = ?, assigned_transport_origin_id = ?, active = ?
                         WHERE user_id = ?'
                    );
                    $stmt->bind_param('ssiiiii', $username, $full_name, $role_id, $legacy_role_level, $origin_id, $active, $user_id);
                }
                $stmt->execute();
                $stmt->close();

                if ($user_id === (int) ($_SESSION['user_id'] ?? 0)) {
                    refresh_session_authorization(true);
                }

                access_set_flash('success', 'User updated successfully.');
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    'INSERT INTO users (username, password_hash, full_name, role_level, role_id, assigned_transport_origin_id, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('sssiiii', $username, $password_hash, $full_name, $legacy_role_level, $role_id, $origin_id, $active);
                $stmt->execute();
                $stmt->close();

                access_set_flash('success', 'User created successfully.');
            }

            access_redirect_back();
        }

        if ($action === 'delete_user') {
            $user_id = isset($_POST['delete_user_id']) ? (int) $_POST['delete_user_id'] : 0;
            if ($user_id <= 0) {
                throw new RuntimeException('User not found.');
            }
            if ($user_id === (int) ($_SESSION['user_id'] ?? 0)) {
                throw new RuntimeException('You cannot delete the account you are currently using.');
            }

            $stmt = $conn->prepare('DELETE FROM users WHERE user_id = ?');
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $stmt->close();

            access_set_flash('success', 'User deleted successfully.');
            access_redirect_back();
        }

        if ($action === 'save_role') {
            require_permission('roles.manage', [4]);

            $role_id = isset($_POST['role_id']) ? (int) $_POST['role_id'] : 0;
            $role_name = trim($_POST['role_name'] ?? '');
            $role_key = trim($_POST['role_key'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $legacy_role_level = isset($_POST['legacy_role_level']) && $_POST['legacy_role_level'] !== ''
                ? (int) $_POST['legacy_role_level']
                : null;
            $active = isset($_POST['role_active']) ? 1 : 0;
            $permission_ids = isset($_POST['permission_ids']) && is_array($_POST['permission_ids'])
                ? array_values(array_unique(array_map('intval', $_POST['permission_ids'])))
                : [];

            if ($role_name === '') {
                throw new RuntimeException('Role name is required.');
            }
            if ($role_key === '') {
                $role_key = generate_role_key($role_name);
            }

            if ($role_id > 0) {
                $stmt = $conn->prepare(
                    'UPDATE roles
                     SET role_key = ?, role_name = ?, description = ?, legacy_role_level = ?, active = ?
                     WHERE role_id = ?'
                );
                $stmt->bind_param('sssiii', $role_key, $role_name, $description, $legacy_role_level, $active, $role_id);
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO roles (role_key, role_name, description, legacy_role_level, active)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('sssii', $role_key, $role_name, $description, $legacy_role_level, $active);
            }
            $stmt->execute();
            if ($role_id === 0) {
                $role_id = (int) $stmt->insert_id;
            }
            $stmt->close();

            $delete_stmt = $conn->prepare('DELETE FROM role_permissions WHERE role_id = ?');
            $delete_stmt->bind_param('i', $role_id);
            $delete_stmt->execute();
            $delete_stmt->close();

            if (!empty($permission_ids)) {
                $insert_stmt = $conn->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
                foreach ($permission_ids as $permission_id) {
                    $insert_stmt->bind_param('ii', $role_id, $permission_id);
                    $insert_stmt->execute();
                }
                $insert_stmt->close();
            }

            $sync_stmt = $conn->prepare(
                'UPDATE users u
                 INNER JOIN roles r ON u.role_id = r.role_id
                 SET u.role_level = COALESCE(r.legacy_role_level, u.role_level)
                 WHERE u.role_id = ?'
            );
            $sync_stmt->bind_param('i', $role_id);
            $sync_stmt->execute();
            $sync_stmt->close();

            refresh_session_authorization(true);
            access_set_flash('success', 'Role saved successfully.');
            access_redirect_back();
        }

        if ($action === 'delete_role') {
            require_permission('roles.manage', [4]);

            $role_id = isset($_POST['delete_role_id']) ? (int) $_POST['delete_role_id'] : 0;
            if ($role_id <= 0) {
                throw new RuntimeException('Role not found.');
            }

            $usage_stmt = $conn->prepare('SELECT COUNT(*) AS total FROM users WHERE role_id = ?');
            $usage_stmt->bind_param('i', $role_id);
            $usage_stmt->execute();
            $usage_count = (int) (($usage_stmt->get_result()->fetch_assoc()['total'] ?? 0));
            $usage_stmt->close();

            if ($usage_count > 0) {
                throw new RuntimeException('This role is still assigned to users and cannot be deleted.');
            }

            $stmt = $conn->prepare('DELETE FROM roles WHERE role_id = ?');
            $stmt->bind_param('i', $role_id);
            $stmt->execute();
            $stmt->close();

            access_set_flash('success', 'Role deleted successfully.');
            access_redirect_back();
        }

        throw new RuntimeException('Unsupported action.');
    } catch (Throwable $e) {
        access_set_flash('danger', $e->getMessage());
        access_redirect_back();
    }
}

$flash = $_SESSION['access_admin_flash'] ?? null;
unset($_SESSION['access_admin_flash']);

$edit_user_id = isset($_GET['edit_user']) ? (int) $_GET['edit_user'] : 0;
$edit_role_id = isset($_GET['edit_role']) ? (int) $_GET['edit_role'] : 0;

$user_form = [
    'user_id' => 0,
    'username' => '',
    'full_name' => '',
    'role_id' => '',
    'assigned_transport_origin_id' => '',
    'active' => 1,
];

if ($edit_user_id > 0) {
    $stmt = $conn->prepare('SELECT user_id, username, full_name, role_id, assigned_transport_origin_id, active FROM users WHERE user_id = ?');
    $stmt->bind_param('i', $edit_user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user_form = $result->fetch_assoc();
    }
    $stmt->close();
}

$role_form = [
    'role_id' => 0,
    'role_name' => '',
    'role_key' => '',
    'description' => '',
    'legacy_role_level' => '',
    'active' => 1,
];
$selected_permission_ids = [];

if ($edit_role_id > 0) {
    $stmt = $conn->prepare('SELECT role_id, role_name, role_key, description, legacy_role_level, active FROM roles WHERE role_id = ?');
    $stmt->bind_param('i', $edit_role_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $role_form = $result->fetch_assoc();
    }
    $stmt->close();

    $permission_stmt = $conn->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
    $permission_stmt->bind_param('i', $edit_role_id);
    $permission_stmt->execute();
    $permission_result = $permission_stmt->get_result();
    while ($permission_result && ($permission_row = $permission_result->fetch_assoc())) {
        $selected_permission_ids[] = (int) $permission_row['permission_id'];
    }
    $permission_stmt->close();
}

$roles = [];
$roles_result = $conn->query(
    'SELECT r.role_id, r.role_key, r.role_name, r.description, r.legacy_role_level, r.active,
            COUNT(u.user_id) AS assigned_users
     FROM roles r
     LEFT JOIN users u ON r.role_id = u.role_id
     GROUP BY r.role_id, r.role_key, r.role_name, r.description, r.legacy_role_level, r.active
     ORDER BY r.active DESC, r.role_name ASC'
);
while ($roles_result && ($row = $roles_result->fetch_assoc())) {
    $roles[] = $row;
}

$permissions_by_group = [];
$permissions_result = $conn->query(
    'SELECT permission_id, permission_key, permission_name, permission_group
     FROM permissions
     ORDER BY permission_group ASC, permission_name ASC'
);
while ($permissions_result && ($row = $permissions_result->fetch_assoc())) {
    $permissions_by_group[$row['permission_group']][] = $row;
}

$users = [];
$users_result = $conn->query(
    'SELECT u.user_id, u.username, u.full_name, u.active, u.role_level, u.role_id,
            u.assigned_transport_origin_id, r.role_name, t.origin_name
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.role_id
     LEFT JOIN transport_origins t ON u.assigned_transport_origin_id = t.transport_origin_id
     ORDER BY u.active DESC, u.username ASC'
);
while ($users_result && ($row = $users_result->fetch_assoc())) {
    $users[] = $row;
}

$transport_origins = [];
$origin_result = $conn->query('SELECT transport_origin_id, origin_name FROM transport_origins ORDER BY origin_name ASC');
while ($origin_result && ($row = $origin_result->fetch_assoc())) {
    $transport_origins[] = $row;
}

$active_user_count = count(array_filter($users, static fn($user) => !empty($user['active'])));
$active_role_count = count(array_filter($roles, static fn($role) => !empty($role['active'])));
$permission_count = 0;
foreach ($permissions_by_group as $group_permissions) {
    $permission_count += count($group_permissions);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User & Role Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="<?php echo BASE_URL; ?>themes/modern_red_theme.css" rel="stylesheet">
    <style>
        body { font-family: 'Sarabun', sans-serif; background: linear-gradient(180deg, #fff5f5 0%, #f8fafc 22%, #f8fafc 100%); }
        .page-shell { max-width: 1480px; margin: 0 auto; }
        .hero-card {
            background: linear-gradient(135deg, #7f1d1d 0%, #dc2626 55%, #f97316 100%);
            color: #fff;
            border-radius: 24px;
            padding: 1.8rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 20px 40px rgba(127, 29, 29, 0.22);
            position: relative;
            overflow: hidden;
        }
        .hero-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at top right, rgba(255,255,255,0.18), transparent 28%), radial-gradient(circle at bottom left, rgba(255,255,255,0.12), transparent 24%);
        }
        .hero-content { position: relative; z-index: 1; }
        .hero-title { font-size: 2rem; font-weight: 700; margin-bottom: 0.35rem; }
        .hero-subtitle { max-width: 780px; color: rgba(255,255,255,0.9); }
        .hero-actions .btn { border-radius: 999px; }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
        .summary-card { background: #fff; border-radius: 18px; border: 1px solid rgba(226,232,240,.9); padding: 1.1rem 1.2rem; box-shadow: 0 12px 28px rgba(15,23,42,.08); }
        .summary-label { color: #64748b; font-size: .86rem; margin-bottom: .35rem; }
        .summary-value { font-size: 1.9rem; font-weight: 700; color: #0f172a; line-height: 1; }
        .layout-grid { display: grid; grid-template-columns: minmax(320px, 390px) minmax(0, 1fr); gap: 1.5rem; align-items: start; }
        .sidebar-stack { position: sticky; top: 1rem; display: grid; gap: 1.5rem; }
        .section-card { background: #fff; border-radius: 18px; border: 1px solid rgba(226,232,240,.9); padding: 1.5rem; box-shadow: 0 10px 24px rgba(15,23,42,.08); }
        .form-card { border-top: 4px solid #dc2626; }
        .table-card { border-top: 4px solid #0f172a; }
        .section-header { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .section-header h4 { margin: 0; font-size: 1.1rem; font-weight: 700; }
        .section-header p { margin: 0; color: #64748b; font-size: .92rem; }
        .badge-soft { padding: .4rem .7rem; border-radius: 999px; font-weight: 500; }
        .badge-soft-success { background: #dcfce7; color: #166534; }
        .badge-soft-secondary { background: #e2e8f0; color: #334155; }
        .permission-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; }
        .role-editor-card .permission-grid { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .role-editor-card .permission-group { min-height: 100%; }
        .permission-group { border: 1px solid #e2e8f0; border-radius: 14px; padding: 1rem; background: linear-gradient(180deg, #fff 0%, #f8fafc 100%); }
        .permission-group h6 { color: #b91c1c; font-size: .9rem; font-weight: 700; margin-bottom: .75rem; text-transform: uppercase; }
        .table thead th { white-space: nowrap; font-size: .86rem; text-transform: uppercase; letter-spacing: .03em; }
        .dataTables_wrapper .dataTables_filter input,
        .dataTables_wrapper .dataTables_length select { border: 1px solid #cbd5e1; border-radius: 8px; padding: .35rem .6rem; }
        .muted-pill { display: inline-flex; align-items: center; gap: .35rem; background: #fee2e2; color: #991b1b; border-radius: 999px; padding: .35rem .7rem; font-size: .82rem; font-weight: 600; }
        @media (max-width: 991.98px) {
            .layout-grid { grid-template-columns: 1fr; }
            .sidebar-stack { position: static; }
            .hero-title { font-size: 1.65rem; }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="page-shell">
            <div class="hero-card">
                <div class="hero-content d-flex justify-content-between align-items-start flex-wrap">
                    <div>
                        <div class="muted-pill mb-3"><i class="fas fa-user-lock"></i><span>Access Control Center</span></div>
                        <div class="hero-title">User & Role Management</div>
                        <div class="hero-subtitle">Manage users, roles, permissions, and transport-origin scope from one place while keeping the legacy flow intact.</div>
                    </div>
                    <div class="hero-actions mt-3 mt-md-0">
                        <a href="<?php echo BASE_URL; ?>index.php" class="btn btn-light btn-sm shadow-sm">
                            <i class="fas fa-arrow-left mr-1"></i>Back to dashboard
                        </a>
                    </div>
                </div>
            </div>

            <div class="summary-grid">
                <div class="summary-card"><div class="summary-label">Active users</div><div class="summary-value"><?php echo $active_user_count; ?></div></div>
                <div class="summary-card"><div class="summary-label">Active roles</div><div class="summary-value"><?php echo $active_role_count; ?></div></div>
                <div class="summary-card"><div class="summary-label">Permissions</div><div class="summary-value"><?php echo $permission_count; ?></div></div>
                <div class="summary-card"><div class="summary-label">Transport origins</div><div class="summary-value"><?php echo count($transport_origins); ?></div></div>
            </div>

            <?php if ($flash): ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> shadow-sm">
                    <?php echo htmlspecialchars($flash['message']); ?>
                </div>
            <?php endif; ?>

            <div class="layout-grid">
                <div class="sidebar-stack">
                    <div class="section-card form-card">
                        <div class="section-header">
                            <div>
                                <h4><?php echo !empty($user_form['user_id']) ? 'Edit user' : 'Create user'; ?></h4>
                                <p>Update login, role assignment, branch scope, and account status.</p>
                            </div>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="save_user">
                            <input type="hidden" name="user_id" value="<?php echo (int) $user_form['user_id']; ?>">
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required value="<?php echo htmlspecialchars($user_form['username']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="full_name">Display name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($user_form['full_name']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="password"><?php echo !empty($user_form['user_id']) ? 'New password (leave blank to keep current)' : 'Password'; ?></label>
                                <input type="password" class="form-control" id="password" name="password" <?php echo empty($user_form['user_id']) ? 'required' : ''; ?>>
                            </div>
                            <div class="form-group">
                                <label for="role_id">Role</label>
                                <select class="form-control" id="role_id" name="role_id">
                                    <option value="">-- Select role --</option>
                                    <?php foreach ($roles as $role): ?>
                                        <option value="<?php echo (int) $role['role_id']; ?>" <?php echo (string) $user_form['role_id'] === (string) $role['role_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['role_name']); ?><?php echo empty($role['active']) ? ' [inactive]' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="assigned_transport_origin_id">Assigned transport origin</label>
                                <select class="form-control" id="assigned_transport_origin_id" name="assigned_transport_origin_id">
                                    <option value="">-- No origin restriction --</option>
                                    <?php foreach ($transport_origins as $origin): ?>
                                        <option value="<?php echo (int) $origin['transport_origin_id']; ?>" <?php echo (string) $user_form['assigned_transport_origin_id'] === (string) $origin['transport_origin_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($origin['origin_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group form-check">
                                <input type="checkbox" class="form-check-input" id="active" name="active" value="1" <?php echo !empty($user_form['active']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="active">Account is active</label>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-save mr-1"></i><?php echo !empty($user_form['user_id']) ? 'Save changes' : 'Create user'; ?>
                            </button>
                            <?php if (!empty($user_form['user_id'])): ?>
                                <a href="manage_users_roles.php" class="btn btn-outline-secondary ml-2">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>

                </div>

                <div>
                    <div class="section-card table-card">
                        <div class="section-header">
                            <div>
                                <h4><i class="fas fa-users mr-2 text-danger"></i>Users</h4>
                                <p>Quickly review status, assigned role, and branch scope.</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover w-100" id="usersTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Display name</th>
                                        <th>Role</th>
                                        <th>Transport origin</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo (int) $user['user_id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role_name'] ?: ('Legacy level ' . (int) $user['role_level'])); ?></td>
                                            <td><?php echo htmlspecialchars($user['origin_name'] ?: '-'); ?></td>
                                            <td><span class="badge-soft <?php echo !empty($user['active']) ? 'badge-soft-success' : 'badge-soft-secondary'; ?>"><?php echo !empty($user['active']) ? 'Active' : 'Inactive'; ?></span></td>
                                            <td class="text-nowrap">
                                                <a href="manage_users_roles.php?edit_user=<?php echo (int) $user['user_id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Edit</a>
                                                <?php if ((int) $user['user_id'] !== (int) ($_SESSION['user_id'] ?? 0)): ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this user?');">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="delete_user_id" value="<?php echo (int) $user['user_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i> Delete</button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if (has_permission('roles.manage', [4])): ?>
                    <div class="section-card form-card role-editor-card">
                        <div class="section-header">
                            <div>
                                <h4><i class="fas fa-user-tag mr-2 text-danger"></i><?php echo !empty($role_form['role_id']) ? 'Edit role' : 'Create role'; ?></h4>
                                <p>Use the wider editor below to set the role profile and permission bundle more comfortably.</p>
                            </div>
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="save_role">
                            <input type="hidden" name="role_id" value="<?php echo (int) $role_form['role_id']; ?>">
                            <div class="form-row">
                                <div class="form-group col-lg-4">
                                    <label for="role_name">Role name</label>
                                    <input type="text" class="form-control" id="role_name" name="role_name" required value="<?php echo htmlspecialchars($role_form['role_name']); ?>">
                                </div>
                                <div class="form-group col-lg-4">
                                    <label for="role_key">Role key</label>
                                    <input type="text" class="form-control" id="role_key" name="role_key" value="<?php echo htmlspecialchars($role_form['role_key']); ?>">
                                    <small class="form-text text-muted">Leave blank to auto-generate a key.</small>
                                </div>
                                <div class="form-group col-lg-4">
                                    <label for="legacy_role_level">Legacy role level</label>
                                    <select class="form-control" id="legacy_role_level" name="legacy_role_level">
                                        <option value="">-- No legacy fallback --</option>
                                        <?php for ($level = 1; $level <= 4; $level++): ?>
                                            <option value="<?php echo $level; ?>" <?php echo (string) $role_form['legacy_role_level'] === (string) $level ? 'selected' : ''; ?>>Level <?php echo $level; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row align-items-end">
                                <div class="form-group col-lg-9">
                                    <label for="description">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="2"><?php echo htmlspecialchars($role_form['description']); ?></textarea>
                                </div>
                                <div class="form-group col-lg-3">
                                    <div class="form-check mb-2">
                                        <input type="checkbox" class="form-check-input" id="role_active" name="role_active" value="1" <?php echo !empty($role_form['active']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="role_active">Role is active</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Permissions</label>
                                <div class="permission-grid">
                                    <?php foreach ($permissions_by_group as $group => $group_permissions): ?>
                                        <div class="permission-group">
                                            <h6><?php echo htmlspecialchars($group); ?></h6>
                                            <?php foreach ($group_permissions as $permission): ?>
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="permission_ids[]" value="<?php echo (int) $permission['permission_id']; ?>" id="permission_<?php echo (int) $permission['permission_id']; ?>" <?php echo in_array((int) $permission['permission_id'], $selected_permission_ids, true) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="permission_<?php echo (int) $permission['permission_id']; ?>">
                                                        <strong><?php echo htmlspecialchars($permission['permission_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($permission['permission_key']); ?></small>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-save mr-1"></i><?php echo !empty($role_form['role_id']) ? 'Save role' : 'Create role'; ?>
                            </button>
                            <?php if (!empty($role_form['role_id'])): ?>
                                <a href="manage_users_roles.php" class="btn btn-outline-secondary ml-2">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="section-card table-card">
                        <div class="section-header">
                            <div>
                                <h4><i class="fas fa-shield-alt mr-2 text-danger"></i>Roles</h4>
                                <p>See usage, fallback level, and edit permission bundles.</p>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover w-100" id="rolesTable">
                                <thead class="thead-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Role</th>
                                        <th>Role key</th>
                                        <th>Legacy</th>
                                        <th>Users</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($roles as $role): ?>
                                        <tr>
                                            <td><?php echo (int) $role['role_id']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($role['role_name']); ?></strong><br><small class="text-muted"><?php echo htmlspecialchars($role['description'] ?: '-'); ?></small></td>
                                            <td><?php echo htmlspecialchars($role['role_key']); ?></td>
                                            <td><?php echo $role['legacy_role_level'] !== null ? 'Level ' . (int) $role['legacy_role_level'] : '-'; ?></td>
                                            <td><?php echo (int) $role['assigned_users']; ?></td>
                                            <td><span class="badge-soft <?php echo !empty($role['active']) ? 'badge-soft-success' : 'badge-soft-secondary'; ?>"><?php echo !empty($role['active']) ? 'Active' : 'Inactive'; ?></span></td>
                                            <td class="text-nowrap">
                                                <a href="manage_users_roles.php?edit_role=<?php echo (int) $role['role_id']; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-edit"></i> Edit</a>
                                                <?php if ((int) $role['assigned_users'] === 0): ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Delete this role?');">
                                                    <input type="hidden" name="action" value="delete_role">
                                                    <input type="hidden" name="delete_role_id" value="<?php echo (int) $role['role_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt"></i> Delete</button>
                                                </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
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
            const language = {
                search: 'Search:',
                lengthMenu: 'Show _MENU_ rows',
                info: 'Showing _START_ to _END_ of _TOTAL_ rows',
                infoEmpty: 'Showing 0 to 0 of 0 rows',
                infoFiltered: '(filtered from _MAX_ rows)',
                zeroRecords: 'No matching records found',
                emptyTable: 'No data available',
                paginate: {
                    first: 'First',
                    previous: 'Previous',
                    next: 'Next',
                    last: 'Last'
                }
            };

            $('#usersTable').DataTable({
                responsive: true,
                pageLength: 10,
                language: language,
                columnDefs: [{ orderable: false, targets: 6 }]
            });

            $('#rolesTable').DataTable({
                responsive: true,
                pageLength: 10,
                language: language,
                columnDefs: [{ orderable: false, targets: 6 }]
            });
        });
    </script>
</body>
</html>
