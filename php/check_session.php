<?php
// php/check_session.php
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
        header("Location: ../login.php"); // ถ้า path ไม่ถูก อาจจะต้องปรับเป็น /logistic/login.php
        exit();
    }
    if (!empty($required_roles) && !has_role($required_roles)) {
        // อาจจะสร้างหน้า Access Denied หรือแค่ redirect ไปหน้าแรก
        $_SESSION['access_denied_msg'] = "คุณไม่มีสิทธิ์เข้าถึงหน้านี้";
        header("Location: ../index.php"); // หรือ /logistic/index.php
        exit();
    }
}
