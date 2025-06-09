<?php
// php/auth.php
session_start();
require_once 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน";
        header("Location: ../login.php");
        exit();
    }

    $sql = "SELECT user_id, username, password_hash, full_name, role_level FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        // ตรวจสอบรหัสผ่านที่ hash ไว้
        if (password_verify($password, $user['password_hash'])) {
            // รหัสผ่านถูกต้อง, สร้าง session
            session_regenerate_id(); // ป้องกัน session fixation
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_level'] = (int)$user['role_level'];
            
            // ลบ session error เก่า (ถ้ามี)
            unset($_SESSION['login_error']);

            // ไปยังหน้า Dashboard
            header("Location: ../index.php");
            exit();
        }
    }

    // ถ้าไม่สำเร็จ
    $_SESSION['login_error'] = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";
    header("Location: ../login.php");
    exit();

} else {
    // ถ้าไม่ได้เข้ามาด้วย POST method
    header("Location: ../login.php");
    exit();
}
?>
