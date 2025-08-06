<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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

// Get statistics
$stats = [];
$stats['total_files'] = $pdo->query("SELECT COUNT(*) FROM files")->fetchColumn();
$stats['available_files'] = $pdo->query("SELECT COUNT(*) FROM files WHERE status = 'tersedia'")->fetchColumn();
$stats['borrowed_files'] = $pdo->query("SELECT COUNT(*) FROM files WHERE status = 'dipinjam'")->fetchColumn();
$stats['total_locations'] = $pdo->query("SELECT COUNT(*) FROM locations")->fetchColumn();
$stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn();

// Get user role display
$role_display = [
    'admin' => 'Pentadbir Sistem',
    'staff_jabatan' => 'Pegawai Jabatan',
    'staff_pembantu' => 'Pembantu Tadbir',
    'user_view' => 'Pengguna Lihat Sahaja'
];

$user_role_display = $role_display[$_SESSION['user_role']] ?? $_SESSION['user_role'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Sistem Penyimpanan Fail Tongod</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            min-height: 100vh;
            background: #1e293b;
            color: #94a3b8;
        }
        .sidebar .nav-link {
            color: #94a3b8;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin: 0.25rem 0.5rem;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            color: white;
            background-color: #2563eb;
        }
        .stat-card {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: white;
            border-radius: 0.75rem;
        }
        .stat-card-success {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
        }
        .stat-card-warning {
            background: linear-gradient(135deg, #d97706, #f59e0b);
            color: white;
        }
        .card {
            border: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="px-3 mb-4">
                        <h5 class="text-white fw-bold">
                            <i class="fas fa-archive me-2"></i>SPF Tongod
                        </h5>
                        <small class="text-muted">Sistem Penyimpanan Fail</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="files.php">
                                <i class="fas fa-folder me-2"></i>Pengurusan Fail
                            </a>
                        </li>
                        
                        <?php if (in_array($_SESSION['user_role'], ['admin', 'staff_jabatan'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="locations.php">
                                <i class="fas fa-map-marker-alt me-2"></i>Lokasi
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (in_array($_SESSION['user_role'], ['admin', 'staff_jabatan', 'staff_pembantu'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="borrowings.php">
                                <i class="fas fa-handshake me-2"></i>Peminjaman
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($_SESSION['user_role'] === 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
                                <i class="fas fa-users me-2"></i>Pengguna
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                    
                    <hr class="text-secondary">
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-light" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>Log Keluar
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Top navbar -->
                <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
                    <div class="container-fluid">
                        <div class="navbar-nav ms-auto">
                            <div class="nav-item dropdown">
                                <div class="d-flex align-items-center">
                                    <div class="me-3 text-end">
                                        <div class="fw-semibold"><?= htmlspecialchars($_SESSION['user_name']) ?></div>
                                        <small class="text-muted"><?= $user_role_display ?></small>
                                    </div>
                                    <i class="fas fa-user-circle fa-2x text-secondary"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </nav>
                
                <!-- Page content -->
                <div class="container-fluid">
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <?php if (in_array($_SESSION['user_role'], ['admin', 'staff_jabatan', 'staff_pembantu'])): ?>
                            <a href="files.php?action=create" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Daftar Fail Baharu
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                                Jumlah Fail
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold"><?= number_format($stats['total_files']) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-folder-open fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card-success">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                                Fail Tersedia
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold"><?= number_format($stats['available_files']) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card stat-card-warning">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">
                                                Fail Dipinjam
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold"><?= number_format($stats['borrowed_files']) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-handshake fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-xl-3 col-md-6 mb-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Lokasi
                                            </div>
                                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($stats['total_locations']) ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-map-marker-alt fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">
                                        <i class="fas fa-bolt me-2"></i>Tindakan Pantas
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <a href="files.php" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-search fa-2x d-block mb-2"></i>
                                                Cari Fail
                                            </a>
                                        </div>
                                        
                                        <?php if (in_array($_SESSION['user_role'], ['admin', 'staff_jabatan', 'staff_pembantu'])): ?>
                                        <div class="col-md-3 mb-3">
                                            <a href="files.php?action=create" class="btn btn-outline-success w-100">
                                                <i class="fas fa-plus fa-2x d-block mb-2"></i>
                                                Daftar Fail
                                            </a>
                                        </div>
                                        
                                        <div class="col-md-3 mb-3">
                                            <a href="borrowings.php" class="btn btn-outline-warning w-100">
                                                <i class="fas fa-handshake fa-2x d-block mb-2"></i>
                                                Peminjaman
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (in_array($_SESSION['user_role'], ['admin', 'staff_jabatan'])): ?>
                                        <div class="col-md-3 mb-3">
                                            <a href="locations.php" class="btn btn-outline-info w-100">
                                                <i class="fas fa-map-marker-alt fa-2x d-block mb-2"></i>
                                                Lokasi
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>