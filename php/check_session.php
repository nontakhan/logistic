<?php

// --- เพิ่มส่วนนี้: ตั้งค่า Session Timeout ---

// กำหนดระยะเวลาของ Session เป็น 8 ชั่วโมง (8 ชั่วโมง * 60 นาที * 60 วินาที)
$session_lifetime = 28800;

// ตั้งค่า gc_maxlifetime (ระยะเวลาที่ server จะเก็บ session ที่ไม่มีการใช้งาน)
ini_set('session.gc_maxlifetime', $session_lifetime);

// ตั้งค่า cookie_lifetime (เพื่อให้ cookie ของ session ในเบราว์เซอร์มีอายุเท่ากัน)
// การตั้งค่านี้จะช่วยให้ session ยังคงอยู่แม้จะปิดเบราว์เซอร์แล้วเปิดใหม่
ini_set('session.cookie_lifetime', $session_lifetime);

// --- สิ้นสุดส่วนที่เพิ่ม ---

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
