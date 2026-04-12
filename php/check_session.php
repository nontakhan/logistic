<?php
// php/check_session.php

$session_lifetime = 28800; // 8 hours
ini_set('session.gc_maxlifetime', (string) $session_lifetime);
ini_set('session.cookie_lifetime', (string) $session_lifetime);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function auth_redirect_base_path()
{
    return str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
}

function auth_legacy_permission_map()
{
    return [
        1 => [
            'dashboard.view',
            'orders.create',
            'orders.view_all',
            'orders.view_details',
            'analytics.view',
            'reports.filter_all_origins',
            'pricing.view',
            'export.orders',
        ],
        2 => [
            'dashboard.view',
            'orders.create',
            'orders.view_all',
            'orders.view_details',
            'orders.acknowledge',
            'orders.assign',
            'orders.confirm_delivery',
            'orders.cancel',
            'orders.delete',
            'orders.change_transport_origin',
            'analytics.view',
            'pricing.view',
            'export.orders',
        ],
        3 => [
            'dashboard.view',
            'orders.view_all',
            'orders.view_details',
            'orders.acknowledge',
            'orders.assign',
            'orders.confirm_delivery',
            'orders.change_transport_origin',
            'orders.update_driver',
            'analytics.view',
            'pricing.view',
            'export.orders',
        ],
        4 => [
            'dashboard.view',
            'orders.create',
            'orders.view_all',
            'orders.view_details',
            'orders.acknowledge',
            'orders.assign',
            'orders.confirm_delivery',
            'orders.cancel',
            'orders.delete',
            'orders.change_transport_origin',
            'orders.update_driver',
            'analytics.view',
            'reports.filter_all_origins',
            'scope.all_origins',
            'pricing.view',
            'export.orders',
            'cssale.manage',
            'staff_vehicles.manage',
            'users.manage',
            'roles.manage',
            'admin.access',
        ],
    ];
}

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function current_role_level()
{
    return (int) (isset($_SESSION['role_level']) ? $_SESSION['role_level'] : 0);
}

function current_user_permissions()
{
    if (!is_logged_in()) {
        return [];
    }

    if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
        refresh_session_authorization(true);
    }

    $permissions = isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])
        ? $_SESSION['permissions']
        : [];

    return array_values(array_unique(array_filter($permissions, 'is_string')));
}

function merge_permission_lists()
{
    $permission_sets = func_get_args();
    $merged = [];
    foreach ($permission_sets as $permission_set) {
        foreach ($permission_set as $permission_key) {
            if (is_string($permission_key) && $permission_key !== '') {
                $merged[$permission_key] = true;
            }
        }
    }

    return array_keys($merged);
}

function refresh_session_authorization($force = false)
{
    if (!is_logged_in()) {
        return false;
    }

    if (!$force && isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return true;
    }

    require_once __DIR__ . '/db_connect.php';

    if (!isset($conn) || !($conn instanceof mysqli)) {
        $legacy_map = auth_legacy_permission_map();
        $_SESSION['permissions'] = isset($legacy_map[current_role_level()]) ? $legacy_map[current_role_level()] : [];
        return false;
    }

    $sql = "SELECT
                u.user_id,
                u.username,
                u.full_name,
                u.role_level AS user_role_level,
                u.role_id,
                u.assigned_transport_origin_id,
                u.active AS user_active,
                r.role_name,
                r.role_key,
                r.legacy_role_level,
                r.active AS role_active
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.role_id
            WHERE u.user_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $legacy_map = auth_legacy_permission_map();
        $_SESSION['permissions'] = isset($legacy_map[current_role_level()]) ? $legacy_map[current_role_level()] : [];
        return false;
    }

    $user_id = (int) $_SESSION['user_id'];
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user || (int) (isset($user['user_active']) ? $user['user_active'] : 0) !== 1) {
        $_SESSION = [];
        session_destroy();
        return false;
    }

    $effective_role_level = (int) (isset($user['legacy_role_level'])
        ? $user['legacy_role_level']
        : (isset($user['user_role_level']) ? $user['user_role_level'] : 0));
    if ($effective_role_level <= 0) {
        $effective_role_level = (int) (isset($user['user_role_level']) ? $user['user_role_level'] : 0);
    }

    $legacy_map = auth_legacy_permission_map();
    $permissions = isset($legacy_map[$effective_role_level]) ? $legacy_map[$effective_role_level] : [];

    if (!empty($user['role_id']) && (int) (isset($user['role_active']) ? $user['role_active'] : 0) === 1) {
        $permission_stmt = $conn->prepare(
            "SELECT p.permission_key
             FROM role_permissions rp
             INNER JOIN permissions p ON rp.permission_id = p.permission_id
             WHERE rp.role_id = ?
             ORDER BY p.permission_group, p.permission_name"
        );

        if ($permission_stmt) {
            $role_id = (int) $user['role_id'];
            $permission_stmt->bind_param('i', $role_id);
            $permission_stmt->execute();
            $permission_result = $permission_stmt->get_result();

            $role_permissions = [];
            while ($permission_result && ($permission_row = $permission_result->fetch_assoc())) {
                $role_permissions[] = $permission_row['permission_key'];
            }

            $permission_stmt->close();

            if (!empty($role_permissions)) {
                $permissions = merge_permission_lists($permissions, $role_permissions);
            }
        }
    }

    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role_id'] = !empty($user['role_id']) ? (int) $user['role_id'] : null;
    $_SESSION['role_name'] = isset($user['role_name']) ? $user['role_name'] : null;
    $_SESSION['role_key'] = isset($user['role_key']) ? $user['role_key'] : null;
    $_SESSION['role_level'] = $effective_role_level;
    $_SESSION['assigned_transport_origin_id'] = !empty($user['assigned_transport_origin_id'])
        ? (int) $user['assigned_transport_origin_id']
        : null;
    $_SESSION['permissions'] = $permissions;

    return true;
}

function has_role($required_roles)
{
    if (!is_logged_in()) {
        return false;
    }

    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }

    refresh_session_authorization();

    return in_array(current_role_level(), $required_roles, true);
}

function has_permission($required_permissions, $legacy_roles = array())
{
    if (!is_logged_in()) {
        return false;
    }

    refresh_session_authorization();

    if (is_string($required_permissions)) {
        $required_permissions = [$required_permissions];
    }

    $required_permissions = array_values(array_filter($required_permissions, 'is_string'));
    $permissions = current_user_permissions();

    foreach ($required_permissions as $permission_key) {
        if (in_array($permission_key, $permissions, true)) {
            return true;
        }
    }

    return !empty($legacy_roles) ? has_role($legacy_roles) : empty($required_permissions);
}

function require_login($required_roles = array())
{
    if (!is_logged_in()) {
        header('Location: ' . auth_redirect_base_path() . '/login.php');
        exit();
    }

    if (!empty($required_roles) && !has_role($required_roles)) {
        $_SESSION['access_denied_msg'] = 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
        header('Location: ' . auth_redirect_base_path() . '/index.php');
        exit();
    }
}

function require_permission($required_permissions, $legacy_roles = array())
{
    if (!is_logged_in()) {
        header('Location: ' . auth_redirect_base_path() . '/login.php');
        exit();
    }

    if (!has_permission($required_permissions, $legacy_roles)) {
        $_SESSION['access_denied_msg'] = 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้';
        header('Location: ' . auth_redirect_base_path() . '/index.php');
        exit();
    }
}

function current_assigned_transport_origin_id()
{
    return !empty($_SESSION['assigned_transport_origin_id'])
        ? (int) $_SESSION['assigned_transport_origin_id']
        : null;
}

function user_has_global_origin_access()
{
    return has_permission('scope.all_origins', [4]);
}

function user_can_filter_all_origins()
{
    return has_permission('reports.filter_all_origins', [1, 4]);
}

function should_limit_to_assigned_origin()
{
    return is_logged_in()
        && !user_has_global_origin_access()
        && current_assigned_transport_origin_id() !== null;
}

if (is_logged_in()) {
    refresh_session_authorization();
}
