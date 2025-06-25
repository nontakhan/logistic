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
            session_regenerate_id(true); 
            
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role_level'] = (int)$user['role_level'];
            $_SESSION['assigned_transport_origin_id'] = $user['assigned_transport_origin_id'] ? (int)$user['assigned_transport_origin_id'] : null;
            
            unset($_SESSION['login_error']);
            header("Location: ../index.php");
            exit();
        }
    }

    $_SESSION['login_error'] = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง";
    header("Location: ../login.php");
    exit();

} else {
    header("Location: ../login.php");
    exit();
}
?>
