<?php
// php/auth.php
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/check_session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit();
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? trim($_POST['password']) : '';

if ($username === '' || $password === '') {
    $_SESSION['login_error'] = 'กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน';
    header('Location: ../login.php');
    exit();
}

$sql = "SELECT
            user_id,
            username,
            password_hash,
            full_name,
            role_level,
            assigned_transport_origin_id,
            active
        FROM users
        WHERE username = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log('SQL prepare failed: ' . $conn->error);
    $_SESSION['login_error'] = 'เกิดข้อผิดพลาดในระบบ';
    header('Location: ../login.php');
    exit();
}

$stmt->bind_param('s', $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result && $result->num_rows === 1 ? $result->fetch_assoc() : null;
$stmt->close();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = 'ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง';
    header('Location: ../login.php');
    exit();
}

if ((int) (isset($user['active']) ? $user['active'] : 0) !== 1) {
    $_SESSION['login_error'] = 'บัญชีนี้ถูกปิดการใช้งาน';
    header('Location: ../login.php');
    exit();
}

session_regenerate_id(true);

$_SESSION['user_id'] = (int) $user['user_id'];
$_SESSION['username'] = $user['username'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role_level'] = (int) $user['role_level'];
$_SESSION['assigned_transport_origin_id'] = !empty($user['assigned_transport_origin_id'])
    ? (int) $user['assigned_transport_origin_id']
    : null;
unset($_SESSION['access_denied_msg'], $_SESSION['login_error']);

refresh_session_authorization(true);

header('Location: ../index.php');
exit();
