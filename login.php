<?php
// logistic/login.php
session_start();
// ถ้า login อยู่แล้ว ให้ redirect ไปหน้า index.php
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - NR Logistics</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="themes/modern_red_theme.css" rel="stylesheet">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background-color: var(--bg-light);
        }
        .login-card {
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="card login-card">
        <div class="card-header text-center bg-danger text-white">
            <h4>NR Logistics</h4>
            <p class="mb-0">ระบบติดตามการจัดส่ง</p>
        </div>
        <div class="card-body">
            <h5 class="card-title text-center mb-4">เข้าสู่ระบบ</h5>
            <?php if (isset($_SESSION['login_error'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
                </div>
            <?php endif; ?>
            <form action="php/auth.php" method="POST">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user mr-2"></i>ชื่อผู้ใช้งาน</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock mr-2"></i>รหัสผ่าน</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">เข้าสู่ระบบ</button>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
