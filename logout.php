<?php
// logout.php
require_once __DIR__ . '/php/check_session.php';

// ลบ session ทั้งหมด
$_SESSION = array();

// ทำลาย session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Redirect ไปหน้า login
header("Location: login.php");
exit();
?>
