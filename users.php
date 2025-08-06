<?php
session_start();

// Check if user is logged in and has admin permission
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
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

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$user_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_POST) {
    if ($action === 'create') {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password_input = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $department = $_POST['department'] ?? '';
        $position = $_POST['position'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($name && $email && $password_input && $role) {
            try {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Email telah digunakan oleh pengguna lain.";
                } else {
                    $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, department, position, phone, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hashed_password, $role, $department, $position, $phone, $is_active]);
                    
                    $message = "Pengguna baharu berjaya dicipta.";
                    $action = 'list';
                }
            } catch (PDOException $e) {
                $error = "Ralat mencipta pengguna: " . $e->getMessage();
            }
        } else {
            $error = "Sila lengkapkan semua field yang diperlukan.";
        }
    } elseif ($action === 'edit' && $user_id) {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $password_input = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';
        $department = $_POST['department'] ?? '';
        $position = $_POST['position'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        if ($name && $email && $role) {
            try {
                // Check if email already exists (excluding current user)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Email telah digunakan oleh pengguna lain.";
                } else {
                    $updateData = [
                        'name' => $name,
                        'email' => $email,
                        'role' => $role,
                        'department' => $department,
                        'position' => $position,
                        'phone' => $phone,
                        'is_active' => $is_active
                    ];
                    
                    $sql = "UPDATE users SET name = ?, email = ?, role = ?, department = ?, position = ?, phone = ?, is_active = ?, updated_at = NOW()";
                    $params = array_values($updateData);
                    
                    // Update password if provided
                    if (!empty($password_input)) {
                        $sql .= ", password = ?";
                        $params[] = password_hash($password_input, PASSWORD_DEFAULT);
                    }
                    
                    $sql .= " WHERE id = ?";
                    $params[] = $user_id;
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    
                    $message = "Pengguna berjaya dikemaskini.";
                    $action = 'view';
                }
            } catch (PDOException $e) {
                $error = "Ralat mengemaskini pengguna: " . $e->getMessage();
            }
        } else {
            $error = "Sila lengkapkan semua field yang diperlukan.";
        }
    }
}

// Handle toggle status
if ($action === 'toggle-status' && $user_id) {
    if ($user_id == $_SESSION['user_id']) {
        $error = "Anda tidak boleh mengubah status akaun sendiri.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$user_id]);
            
            $stmt = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $is_active = $stmt->fetchColumn();
            
            $status = $is_active ? 'diaktifkan' : 'dinonaktifkan';
            $message = "Status pengguna berjaya $status.";
            $action = 'list';
        } catch (PDOException $e) {
            $error = "Ralat mengubah status: " . $e->getMessage();
        }
    }
}

// Handle delete
if ($action === 'delete' && $user_id) {
    if ($user_id == $_SESSION['user_id']) {
        $error = "Anda tidak boleh memadam akaun sendiri.";
    } else {
        try {
            // Check if user has created files or active borrowings
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM files WHERE created_by = ?");
            $stmt->execute([$user_id]);
            $files_count = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowing_records WHERE borrower_id = ? AND status = 'dipinjam'");
            $stmt->execute([$user_id]);
            $active_borrowings = $stmt->fetchColumn();
            
            if ($files_count > 0 || $active_borrowings > 0) {
                $error = "Pengguna tidak boleh dipadam kerana mempunyai rekod fail atau peminjaman aktif.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $message = "Pengguna berjaya dipadam.";
                $action = 'list';
            }
        } catch (PDOException $e) {
            $error = "Ralat memadam pengguna: " . $e->getMessage();
        }
    }
}

// Get data based on action
$users = [];
$user = null;

if ($action === 'list') {
    // Build search query
    $search = $_GET['search'] ?? '';
    $role_filter = $_GET['role'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    $sql = "SELECT u.*, 
                   COUNT(DISTINCT f.id) as files_created,
                   COUNT(DISTINCT br.id) as total_borrowings,
                   COUNT(DISTINCT CASE WHEN br.status = 'dipinjam' THEN br.id END) as active_borrowings
            FROM users u 
            LEFT JOIN files f ON u.id = f.created_by
            LEFT JOIN borrowing_records br ON u.id = br.borrower_id
            WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.department LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($role_filter) {
        $sql .= " AND u.role = ?";
        $params[] = $role_filter;
    }
    
    if ($status_filter === 'active') {
        $sql .= " AND u.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $sql .= " AND u.is_active = 0";
    }
    
    $sql .= " GROUP BY u.id ORDER BY u.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($action === 'view' && $user_id) {
    $stmt = $pdo->prepare("SELECT u.*, 
                          COUNT(DISTINCT f.id) as files_created,
                          COUNT(DISTINCT br.id) as total_borrowings,
                          COUNT(DISTINCT CASE WHEN br.status = 'dipinjam' THEN br.id END) as active_borrowings
                          FROM users u 
                          LEFT JOIN files f ON u.id = f.created_by
                          LEFT JOIN borrowing_records br ON u.id = br.borrower_id
                          WHERE u.id = ?
                          GROUP BY u.id");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Get recent files created by user
        $stmt = $pdo->prepare("SELECT f.*, l.room, l.rack, l.slot FROM files f 
                              LEFT JOIN locations l ON f.location_id = l.id 
                              WHERE f.created_by = ? 
                              ORDER BY f.created_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $user['recent_files'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent borrowings
        $stmt = $pdo->prepare("SELECT br.*, f.file_id, f.title FROM borrowing_records br 
                              JOIN files f ON br.file_id = f.id 
                              WHERE br.borrower_id = ? 
                              ORDER BY br.created_at DESC LIMIT 5");
        $stmt->execute([$user_id]);
        $user['recent_borrowings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} elseif ($action === 'edit' && $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Role display
$role_display = [
    'admin' => 'Pentadbir Sistem',
    'staff_jabatan' => 'Pegawai Jabatan',
    'staff_pembantu' => 'Pembantu Tadbir',
    'user_view' => 'Pengguna Lihat Sahaja'
];
$user_role_display = $role_display[$_SESSION['user_role']] ?? $_SESSION['user_role'];

$departments = ['Pentadbiran', 'Kewangan', 'Pembangunan', 'Kejuruteraan', 'Perancangan', 'Kesihatan', 'Pendidikan'];
?>
<!DOCTYPE html>
<html lang="ms">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $action === 'create' ? 'Tambah Pengguna Baharu' : ($action === 'edit' ? 'Edit Pengguna' : ($action === 'view' ? 'Butiran Pengguna' : 'Pengurusan Pengguna')) ?> - Sistem Penyimpanan Fail Tongod</title>
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
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: white;
            background-color: #2563eb;
        }
        .card {
            border: none;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 0.75rem;
        }
        .user-avatar {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
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
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="files.php">
                                <i class="fas fa-folder me-2"></i>Pengurusan Fail
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="locations.php">
                                <i class="fas fa-map-marker-alt me-2"></i>Lokasi
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="borrowings.php">
                                <i class="fas fa-handshake me-2"></i>Peminjaman
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="users.php">
                                <i class="fas fa-users me-2"></i>Pengguna
                            </a>
                        </li>
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
                    <?php if ($message): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($action === 'list'): ?>
                    <!-- Users List View -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-users me-2"></i>Pengurusan Pengguna
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="users.php?action=create" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Tambah Pengguna Baharu
                            </a>
                        </div>
                    </div>

                    <!-- Search and Filter -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-search me-2"></i>Carian dan Penapis
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <input type="hidden" name="action" value="list">
                                <div class="col-md-4">
                                    <label for="search" class="form-label">Carian</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Nama, email, atau jabatan..." 
                                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="role" class="form-label">Peranan</label>
                                    <select class="form-select" id="role" name="role">
                                        <option value="">Semua Peranan</option>
                                        <option value="admin" <?= ($_GET['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Pentadbir Sistem</option>
                                        <option value="staff_jabatan" <?= ($_GET['role'] ?? '') === 'staff_jabatan' ? 'selected' : '' ?>>Pegawai Jabatan</option>
                                        <option value="staff_pembantu" <?= ($_GET['role'] ?? '') === 'staff_pembantu' ? 'selected' : '' ?>>Pembantu Tadbir</option>
                                        <option value="user_view" <?= ($_GET['role'] ?? '') === 'user_view' ? 'selected' : '' ?>>Pengguna Lihat Sahaja</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Semua Status</option>
                                        <option value="active" <?= ($_GET['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktif</option>
                                        <option value="inactive" <?= ($_GET['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Tidak Aktif</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <a href="users.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Users Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-list me-2"></i>Senarai Pengguna
                                <span class="badge bg-primary ms-2"><?= count($users) ?></span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($users) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Pengguna</th>
                                                <th>Email</th>
                                                <th>Peranan</th>
                                                <th>Jabatan</th>
                                                <th>Aktiviti</th>
                                                <th>Status</th>
                                                <th>Tindakan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $u): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-3" style="width: 40px; height: 40px; font-size: 1rem;">
                                                            <?= strtoupper(substr($u['name'], 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold"><?= htmlspecialchars($u['name']) ?></div>
                                                            <?php if ($u['position']): ?>
                                                                <small class="text-muted"><?= htmlspecialchars($u['position']) ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($u['email']) ?></td>
                                                <td>
                                                    <span class="badge bg-info text-dark">
                                                        <?= $role_display[$u['role']] ?? $u['role'] ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($u['department'] ?? '-') ?></td>
                                                <td>
                                                    <small class="d-block">Fail: <?= $u['files_created'] ?></small>
                                                    <small class="d-block">Pinjam: <?= $u['total_borrowings'] ?> (<?= $u['active_borrowings'] ?> aktif)</small>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                                        <?= $u['is_active'] ? 'Aktif' : 'Tidak Aktif' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="users.php?action=view&id=<?= $u['id'] ?>" 
                                                           class="btn btn-outline-primary" title="Lihat">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <a href="users.php?action=edit&id=<?= $u['id'] ?>" 
                                                           class="btn btn-outline-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                                        <a href="users.php?action=toggle-status&id=<?= $u['id'] ?>" 
                                                           class="btn btn-outline-<?= $u['is_active'] ? 'secondary' : 'success' ?>" 
                                                           title="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>"
                                                           onclick="return confirm('Adakah anda pasti mahu mengubah status pengguna ini?')">
                                                            <i class="fas fa-<?= $u['is_active'] ? 'ban' : 'check' ?>"></i>
                                                        </a>
                                                        <a href="users.php?action=delete&id=<?= $u['id'] ?>" 
                                                           class="btn btn-outline-danger" title="Padam"
                                                           onclick="return confirm('Adakah anda pasti mahu memadam pengguna ini?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Tiada pengguna dijumpai</h5>
                                    <p class="text-muted">Cuba ubah kriteria carian anda atau tambah pengguna baharu.</p>
                                    <a href="users.php?action=create" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Tambah Pengguna Baharu
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php elseif ($action === 'create'): ?>
                    <!-- Create User Form -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-plus me-2"></i>Tambah Pengguna Baharu
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Maklumat Pengguna</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Nama Penuh <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Alamat Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">Kata Laluan <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                                        <div class="form-text">Minimum 6 aksara</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="role" class="form-label">Peranan <span class="text-danger">*</span></label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="">Pilih Peranan</option>
                                            <option value="admin">Pentadbir Sistem</option>
                                            <option value="staff_jabatan">Pegawai Jabatan</option>
                                            <option value="staff_pembantu">Pembantu Tadbir</option>
                                            <option value="user_view">Pengguna Lihat Sahaja</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="department" class="form-label">Jabatan</label>
                                        <select class="form-select" id="department" name="department">
                                            <option value="">Pilih Jabatan</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= $dept ?>"><?= $dept ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="position" class="form-label">Jawatan</label>
                                        <input type="text" class="form-control" id="position" name="position">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Nombor Telefon</label>
                                        <input type="tel" class="form-control" id="phone" name="phone">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                                            <label class="form-check-label" for="is_active">
                                                Akaun Aktif
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="users.php" class="btn btn-outline-secondary me-md-2">Batal</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Simpan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($action === 'edit' && $user): ?>
                    <!-- Edit User Form -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-edit me-2"></i>Edit Pengguna: <?= htmlspecialchars($user['name']) ?>
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="users.php?action=view&id=<?= $user['id'] ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Kemaskini Maklumat Pengguna</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Nama Penuh <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?= htmlspecialchars($user['name']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Alamat Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?= htmlspecialchars($user['email']) ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">Kata Laluan Baharu</label>
                                        <input type="password" class="form-control" id="password" name="password" minlength="6">
                                        <div class="form-text">Kosongkan jika tidak mahu mengubah kata laluan</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="role" class="form-label">Peranan <span class="text-danger">*</span></label>
                                        <select class="form-select" id="role" name="role" required>
                                            <option value="">Pilih Peranan</option>
                                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Pentadbir Sistem</option>
                                            <option value="staff_jabatan" <?= $user['role'] === 'staff_jabatan' ? 'selected' : '' ?>>Pegawai Jabatan</option>
                                            <option value="staff_pembantu" <?= $user['role'] === 'staff_pembantu' ? 'selected' : '' ?>>Pembantu Tadbir</option>
                                            <option value="user_view" <?= $user['role'] === 'user_view' ? 'selected' : '' ?>>Pengguna Lihat Sahaja</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="department" class="form-label">Jabatan</label>
                                        <select class="form-select" id="department" name="department">
                                            <option value="">Pilih Jabatan</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= $dept ?>" <?= $user['department'] === $dept ? 'selected' : '' ?>><?= $dept ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="position" class="form-label">Jawatan</label>
                                        <input type="text" class="form-control" id="position" name="position" 
                                               value="<?= htmlspecialchars($user['position'] ?? '') ?>">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Nombor Telefon</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" 
                                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                                   <?= $user['is_active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_active">
                                                Akaun Aktif
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="users.php?action=view&id=<?= $user['id'] ?>" class="btn btn-outline-secondary me-md-2">Batal</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Kemaskini
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($action === 'view' && $user): ?>
                    <!-- View User Details -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-eye me-2"></i>Butiran Pengguna
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <a href="users.php?action=edit&id=<?= $user['id'] ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <a href="users.php?action=toggle-status&id=<?= $user['id'] ?>" 
                                   class="btn btn-<?= $user['is_active'] ? 'secondary' : 'success' ?>"
                                   onclick="return confirm('Adakah anda pasti mahu mengubah status pengguna ini?')">
                                    <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check' ?> me-1"></i>
                                    <?= $user['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                </a>
                                <?php endif; ?>
                            </div>
                            <a href="users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="m-0 font-weight-bold">Maklumat Pengguna</h6>
                                </div>
                                <div class="card-body text-center">
                                    <div class="user-avatar mx-auto mb-3">
                                        <?= strtoupper(substr($user['name'], 0, 1)) ?>
                                    </div>
                                    <h4><?= htmlspecialchars($user['name']) ?></h4>
                                    <p class="text-muted"><?= htmlspecialchars($user['position'] ?? 'Tiada jawatan') ?></p>
                                    <span class="badge <?= $user['is_active'] ? 'bg-success' : 'bg-secondary' ?> fs-6 mb-3">
                                        <?= $user['is_active'] ? 'Aktif' : 'Tidak Aktif' ?>
                                    </span>
                                    
                                    <table class="table table-borderless text-start">
                                        <tr>
                                            <td class="fw-semibold">Email:</td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Peranan:</td>
                                            <td>
                                                <span class="badge bg-info text-dark">
                                                    <?= $role_display[$user['role']] ?? $user['role'] ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Jabatan:</td>
                                            <td><?= htmlspecialchars($user['department'] ?? '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Telefon:</td>
                                            <td><?= htmlspecialchars($user['phone'] ?? '-') ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Daftar:</td>
                                            <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Statistics -->
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="m-0 font-weight-bold">Statistik Aktiviti</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="border rounded p-2">
                                                <h4 class="text-primary mb-0"><?= $user['files_created'] ?></h4>
                                                <small class="text-muted">Fail Dicipta</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="border rounded p-2">
                                                <h4 class="text-success mb-0"><?= $user['total_borrowings'] ?></h4>
                                                <small class="text-muted">Peminjaman</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row text-center mt-2">
                                        <div class="col-12">
                                            <div class="border rounded p-2">
                                                <h4 class="text-warning mb-0"><?= $user['active_borrowings'] ?></h4>
                                                <small class="text-muted">Peminjaman Aktif</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-8">
                            <!-- Recent Files Created -->
                            <div class="card mb-3">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-folder me-2"></i>Fail Dicipta Terkini
                                        <span class="badge bg-primary ms-2"><?= count($user['recent_files']) ?></span>
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (count($user['recent_files']) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>ID Fail</th>
                                                        <th>Tajuk</th>
                                                        <th>Status</th>
                                                        <th>Dicipta</th>
                                                        <th>Tindakan</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($user['recent_files'] as $file): ?>
                                                    <tr>
                                                        <td><strong class="text-primary"><?= htmlspecialchars($file['file_id']) ?></strong></td>
                                                        <td><?= htmlspecialchars(substr($file['title'], 0, 40)) ?><?= strlen($file['title']) > 40 ? '...' : '' ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $file['status'] === 'tersedia' ? 'success' : ($file['status'] === 'dipinjam' ? 'warning' : 'secondary') ?>">
                                                                <?= ucfirst($file['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= date('d/m/Y', strtotime($file['created_at'])) ?></td>
                                                        <td>
                                                            <a href="files.php?action=view&id=<?= $file['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-3">
                                            <p class="text-muted mb-0">Tiada fail dicipta lagi</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Recent Borrowings -->
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-handshake me-2"></i>Peminjaman Terkini
                                        <span class="badge bg-primary ms-2"><?= count($user['recent_borrowings']) ?></span>
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (count($user['recent_borrowings']) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>ID Fail</th>
                                                        <th>Tajuk</th>
                                                        <th>Tarikh Pinjam</th>
                                                        <th>Status</th>
                                                        <th>Tindakan</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($user['recent_borrowings'] as $borrowing): ?>
                                                    <tr>
                                                        <td><strong class="text-primary"><?= htmlspecialchars($borrowing['file_id']) ?></strong></td>
                                                        <td><?= htmlspecialchars(substr($borrowing['title'], 0, 40)) ?><?= strlen($borrowing['title']) > 40 ? '...' : '' ?></td>
                                                        <td><?= date('d/m/Y', strtotime($borrowing['borrowed_date'])) ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= $borrowing['status'] === 'dipinjam' ? 'warning' : 'success' ?>">
                                                                <?= ucfirst($borrowing['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="borrowings.php?action=view&id=<?= $borrowing['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-3">
                                            <p class="text-muted mb-0">Tiada peminjaman lagi</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <!-- Error state -->
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Pengguna tidak dijumpai atau akses tidak dibenarkan.
                    </div>
                    <a href="users.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i>Kembali ke Senarai Pengguna
                    </a>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>