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

    // ตรวจสอบให้แน่ใจว่าดึงคอลัมน์ assigned_transport_origin_id มาด้วย
    $sql = "SELECT user_id, username, password_hash, full_name, role_level, assigned_transport_origin_id FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        // Handle SQL prepare error
        error_log("SQL prepare failed: " . $conn->error);
        $_SESSION['login_error'] = "เกิดข้อผิดพลาดในระบบ";
        header("Location: ../login.php");
        exit();
    }

    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password_hash'])) {
            // รหัสผ่านถูกต้อง, สร้าง session
            session_regenerate_id(true); // ป้องกัน session fixation
            
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_level'] = (int)$user['role_level'];
            // เก็บ ID สาขาของผู้ใช้ไว้ใน session
            // ใช้ (int) หรือปล่อยเป็น NULL ถ้าค่าใน DB เป็น NULL
            $_SESSION['assigned_transport_origin_id'] = $user['assigned_transport_origin_id'] ? (int)$user['assigned_transport_origin_id'] : null;
            
            unset($_SESSION['login_error']);
            header("Location: ../index.php");
            exit();
        }
    }

    // ถ้าไม่สำเร็จ
    $_SESSION['login_error'] = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";
    header("Location: ../login.php");
    exit();

} else {
    header("Location: ../login.php");
    exit();
}
?>
