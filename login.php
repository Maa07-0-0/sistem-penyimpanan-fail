<?php
session_start();

// Database connection
$host = '127.0.0.1';
$username = 'root';
$password = '';
$dbname = 'db_fail_tongod';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$error = '';

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password_input = $_POST['password'] ?? '';
    
    if ($email && $password_input) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Check password (simple check for demo - password is 'password' for all users)
        if ($user && ($password_input === 'password' || password_verify($password_input, $user['password']))) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_department'] = $user['department'];
            $_SESSION['last_activity'] = time();
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Email atau kata laluan tidak sah.';
        }
    } else {
        $error = 'Sila masukkan email dan kata laluan.';
    }
}
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Log Masuk - Sistem Penyimpanan Fail Tongod</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
        }
        .login-left {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            color: white;
            padding: 3rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2563eb 0%, #3b82f6 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="login-card">
                        <div class="row g-0">
                            <div class="col-lg-6 login-left d-none d-lg-flex align-items-center">
                                <div class="text-center">
                                    <i class="fas fa-archive fa-4x mb-4"></i>
                                    <h2 class="fw-bold mb-3">Sistem Penyimpanan Fail</h2>
                                    <h4 class="fw-normal mb-4">Pejabat Daerah Tongod</h4>
                                    <p class="lead opacity-75">
                                        Sistem pengurusan fail digital yang komprehensif untuk memudahkan 
                                        penyimpanan, carian, dan pengurusan dokumen pejabat.
                                    </p>
                                </div>
                            </div>
                            
                            <div class="col-lg-6 p-5">
                                <div class="text-center mb-4">
                                    <h3 class="fw-bold text-dark">Selamat Kembali!</h3>
                                    <p class="text-muted">Sila log masuk ke akaun anda</p>
                                </div>
                                
                                <?php if ($error): ?>
                                    <div class="alert alert-danger">
                                        <i class="fas fa-exclamation-circle me-2"></i><?= $error ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="email" class="form-label">
                                            <i class="fas fa-envelope me-2"></i>Alamat Email
                                        </label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               placeholder="Masukkan email anda" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Kata Laluan
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" 
                                               placeholder="Masukkan kata laluan" required>
                                    </div>
                                    
                                    <div class="d-grid">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-sign-in-alt me-2"></i>Log Masuk
                                        </button>
                                    </div>
                                </form>
                                
                                <div class="mt-4 p-3 bg-light rounded">
                                    <h6 class="fw-bold mb-2">Demo Credentials:</h6>
                                    <small class="d-block">Admin: admin@tongod.gov.my / password</small>
                                    <small class="d-block">Staff: ahmad@tongod.gov.my / password</small>
                                    <small class="d-block">Pembantu: siti@tongod.gov.my / password</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>