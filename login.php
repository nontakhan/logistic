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
    <!-- *** เพิ่ม: Favicon *** -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🚚</text></svg>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-red: #dc2626;
            --secondary-red: #b91c1c;
            --dark-red: #991b1b;
            --light-red: #fef2f2;
            --accent-red: #f87171;
            --text-dark: #1f2937;
            --text-light: #6b7280;
            --border-color: #e5e7eb;
            --shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-lg: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #dc2626 0%, #b91c1c 50%, #991b1b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Background Animation */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="2" fill="rgba(255,255,255,0.1)"/></svg>') repeat;
            background-size: 50px 50px;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(0px) translateX(0px); }
            50% { transform: translateY(-20px) translateX(10px); }
            100% { transform: translateY(0px) translateX(0px); }
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 24px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .login-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 32px 64px -12px rgba(0, 0, 0, 0.3);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-red));
            color: white;
            padding: 2rem;
            text-align: center;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .brand-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
            40% { transform: translateY(-10px); }
            60% { transform: translateY(-5px); }
        }

        .brand-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .brand-subtitle {
            font-size: 1rem;
            opacity: 0.9;
            font-weight: 400;
        }

        .card-body {
            padding: 2.5rem;
        }

        .login-title {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--text-dark);
            font-weight: 600;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-label {
            color: var(--text-dark);
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
        }

        .form-label i {
            color: var(--primary-red);
            margin-right: 0.5rem;
            width: 20px;
        }

        .form-control {
            border: 2px solid var(--border-color);
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-control:focus {
            border-color: var(--primary-red);
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
            background: white;
            outline: none;
        }

        .form-control:hover {
            border-color: var(--accent-red);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-red));
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            padding: 0.875rem 2rem;
            width: 100%;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4);
            background: linear-gradient(135deg, var(--secondary-red), var(--dark-red));
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-danger {
            background: rgba(220, 38, 38, 0.1);
            color: var(--dark-red);
            border-left: 4px solid var(--primary-red);
        }

        /* Input Animation */
        .input-group {
            position: relative;
        }

        .input-group::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-red);
            transition: width 0.3s ease;
        }

        .input-group:focus-within::after {
            width: 100%;
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .login-container {
                max-width: 100%;
                margin: 0 10px;
            }
            
            .card-body {
                padding: 2rem 1.5rem;
            }
            
            .card-header {
                padding: 1.5rem;
            }
            
            .brand-title {
                font-size: 1.5rem;
            }
            
            .brand-icon {
                font-size: 2.5rem;
            }
        }

        /* Loading Animation */
        .btn-login.loading {
            pointer-events: none;
            position: relative;
        }

        .btn-login.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card login-card">
            <div class="card-header">
                <i class="fas fa-truck brand-icon"></i>
                <h4 class="brand-title">NR Logistics</h4>
                <p class="brand-subtitle">ระบบติดตามการจัดส่ง</p>
            </div>
            <div class="card-body">
                <h5 class="login-title">เข้าสู่ระบบ</h5>
                <?php if (isset($_SESSION['login_error'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
                    </div>
                <?php endif; ?>
                <form action="php/auth.php" method="POST">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user"></i>ชื่อผู้ใช้งาน
                        </label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock"></i>รหัสผ่าน
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-sign-in-alt mr-2"></i>เข้าสู่ระบบ
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        // Add loading animation on form submit
        $('form').on('submit', function() {
            const btn = $('.btn-login');
            btn.addClass('loading');
            btn.html('<i class="fas fa-spinner fa-spin mr-2"></i>กำลังเข้าสู่ระบบ...');
        });

        // Add focus animations
        $('.form-control').on('focus', function() {
            $(this).parent().addClass('focused');
        }).on('blur', function() {
            $(this).parent().removeClass('focused');
        });

        // Add shake animation for error
        <?php if (isset($_SESSION['login_error'])): ?>
            $('.login-card').addClass('animate__animated animate__headShake');
        <?php endif; ?>
    </script>
</body>
</html>