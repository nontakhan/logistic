<?php
// php/check_session.php

// --- ตั้งค่า Session Timeout ---
$session_lifetime = 28800; // 8 ชั่วโมง
ini_set('session.gc_maxlifetime', $session_lifetime);
ini_set('session.cookie_lifetime', $session_lifetime);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ฟังก์ชันสำหรับตรวจสอบว่า Login อยู่หรือไม่
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// ฟังก์ชันสำหรับตรวจสอบระดับสิทธิ์
function has_role($required_roles) {
    if (!is_logged_in()) {
        return false;
    }
    // ทำให้ $required_roles เป็น array เสมอ
    if (!is_array($required_roles)) {
        $required_roles = [$required_roles];
    }
    return in_array($_SESSION['role_level'], $required_roles);
}

// ฟังก์ชันสำหรับ redirect ถ้าไม่มีสิทธิ์
function require_login($required_roles = []) {
    if (!is_logged_in()) {
        $base_url_for_redirect = str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
        header("Location: " . $base_url_for_redirect . "/login.php");
        exit();
    }
    if (!empty($required_roles) && !has_role($required_roles)) {
        $base_url_for_redirect = str_replace('/pages', '', dirname($_SERVER['SCRIPT_NAME']));
        $_SESSION['access_denied_msg'] = "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
        header("Location: " . $base_url_for_redirect . "/index.php");
        exit();
    }
}
