<?php

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

function legacy_role_permissions_map()
{
    return array(
        1 => array(
            'dashboard.view',
            'orders.create',
            'orders.view_all',
            'orders.view_details',
            'analytics.view',
            'reports.filter_all_origins',
            'pricing.view',
            'export.orders',
        ),
        2 => array(
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
        ),
        3 => array(
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
        ),
        4 => array(
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
            'admin.access',
        ),
    );
}

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function current_role_level()
{
    return isset($_SESSION['role_level']) ? (int) $_SESSION['role_level'] : 0;
}

function current_user_permissions()
{
    $map = legacy_role_permissions_map();
    $role_level = current_role_level();
    return isset($map[$role_level]) ? $map[$role_level] : array();
}

function refresh_session_authorization($force = false)
{
    if (!is_logged_in()) {
        return false;
    }

    if (!$force && isset($_SESSION['full_name']) && isset($_SESSION['role_level'])) {
        return true;
    }

    require_once __DIR__ . '/db_connect.php';
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return false;
    }

    $sql = 'SELECT user_id, username, full_name, role_level, assigned_transport_origin_id, active
            FROM users
            WHERE user_id = ?
            LIMIT 1';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $user_id = (int) $_SESSION['user_id'];
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$user || (int) (isset($user['active']) ? $user['active'] : 0) !== 1) {
        $_SESSION = array();
        session_destroy();
        return false;
    }

    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role_level'] = (int) $user['role_level'];
    $_SESSION['assigned_transport_origin_id'] = !empty($user['assigned_transport_origin_id'])
        ? (int) $user['assigned_transport_origin_id']
        : null;

    return true;
}

function has_role($required_roles)
{
    if (!is_logged_in()) {
        return false;
    }

    if (!is_array($required_roles)) {
        $required_roles = array($required_roles);
    }

    refresh_session_authorization();

    return in_array(current_role_level(), $required_roles, true);
}

function permission_allowed_roles_map()
{
    return array(
        'dashboard.view' => array(1, 2, 3, 4),
        'orders.create' => array(1, 2, 4),
        'orders.view_all' => array(1, 2, 3, 4),
        'orders.view_details' => array(1, 2, 3, 4),
        'orders.acknowledge' => array(2, 3, 4),
        'orders.assign' => array(2, 3, 4),
        'orders.confirm_delivery' => array(2, 3, 4),
        'orders.cancel' => array(2, 4),
        'orders.delete' => array(2, 4),
        'orders.change_transport_origin' => array(2, 3, 4),
        'orders.update_driver' => array(2, 3, 4),
        'analytics.view' => array(1, 2, 3, 4),
        'reports.filter_all_origins' => array(1, 4),
        'scope.all_origins' => array(4),
        'pricing.view' => array(1, 2, 3, 4),
        'export.orders' => array(1, 2, 3, 4),
        'cssale.manage' => array(4),
        'staff_vehicles.manage' => array(4),
        'users.manage' => array(4),
        'admin.access' => array(4),
    );
}

function has_permission($required_permissions, $legacy_roles = array())
{
    if (!is_logged_in()) {
        return false;
    }

    refresh_session_authorization();

    if (is_string($required_permissions)) {
        $required_permissions = array($required_permissions);
    }

    $permission_map = permission_allowed_roles_map();
    foreach ($required_permissions as $permission_key) {
        if (isset($permission_map[$permission_key]) && has_role($permission_map[$permission_key])) {
            return true;
        }
    }

    return !empty($legacy_roles) ? has_role($legacy_roles) : false;
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
    return has_role(array(4));
}

function user_can_filter_all_origins()
{
    return has_role(array(1, 4));
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
