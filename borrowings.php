<?php
session_start();

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff_jabatan', 'staff_pembantu'])) {
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
$borrowing_id = $_GET['id'] ?? null;
$file_id = $_GET['file_id'] ?? null;

// Handle form submissions
if ($_POST) {
    if ($action === 'create') {
        $file_id_post = $_POST['file_id'] ?? '';
        $borrower_id = $_POST['borrower_id'] ?? '';
        $purpose = $_POST['purpose'] ?? '';
        $due_date = $_POST['due_date'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        if ($file_id_post && $borrower_id && $purpose && $due_date) {
            try {
                // Check if file is available
                $stmt = $pdo->prepare("SELECT status FROM files WHERE id = ?");
                $stmt->execute([$file_id_post]);
                $file = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$file || $file['status'] !== 'tersedia') {
                    $error = "Fail ini tidak boleh dipinjam pada masa ini.";
                } else {
                    $pdo->beginTransaction();
                    
                    // Create borrowing record
                    $stmt = $pdo->prepare("INSERT INTO borrowing_records (file_id, borrower_id, approved_by, purpose, borrowed_date, due_date, notes, status) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, 'dipinjam')");
                    $stmt->execute([$file_id_post, $borrower_id, $_SESSION['user_id'], $purpose, $due_date, $notes]);
                    
                    // Update file status
                    $stmt = $pdo->prepare("UPDATE files SET status = 'dipinjam' WHERE id = ?");
                    $stmt->execute([$file_id_post]);
                    
                    $pdo->commit();
                    
                    $message = "Peminjaman fail berjaya direkodkan.";
                    $action = 'list';
                }
            } catch (PDOException $e) {
                $pdo->rollback();
                $error = "Ralat merekodkan peminjaman: " . $e->getMessage();
            }
        } else {
            $error = "Sila lengkapkan semua field yang diperlukan.";
        }
    } elseif ($action === 'return' && $borrowing_id) {
        $notes = $_POST['notes'] ?? '';
        
        try {
            $pdo->beginTransaction();
            
            // Get borrowing details
            $stmt = $pdo->prepare("SELECT file_id FROM borrowing_records WHERE id = ? AND status = 'dipinjam'");
            $stmt->execute([$borrowing_id]);
            $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($borrowing) {
                // Update borrowing record
                $stmt = $pdo->prepare("UPDATE borrowing_records SET returned_date = CURDATE(), returned_to = ?, status = 'dikembalikan', notes = ? WHERE id = ?");
                $stmt->execute([$_SESSION['user_id'], $notes, $borrowing_id]);
                
                // Update file status
                $stmt = $pdo->prepare("UPDATE files SET status = 'tersedia' WHERE id = ?");
                $stmt->execute([$borrowing['file_id']]);
                
                $pdo->commit();
                
                $message = "Fail berjaya dikembalikan.";
                $action = 'view';
            } else {
                $error = "Peminjaman tidak dijumpai atau sudah dikembalikan.";
            }
        } catch (PDOException $e) {
            $pdo->rollback();
            $error = "Ralat memproses pemulangan: " . $e->getMessage();
        }
    } elseif ($action === 'edit' && $borrowing_id) {
        $purpose = $_POST['purpose'] ?? '';
        $due_date = $_POST['due_date'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        if ($purpose && $due_date) {
            try {
                $stmt = $pdo->prepare("UPDATE borrowing_records SET purpose = ?, due_date = ?, notes = ?, updated_at = NOW() WHERE id = ? AND status = 'dipinjam'");
                $stmt->execute([$purpose, $due_date, $notes, $borrowing_id]);
                
                if ($stmt->rowCount() > 0) {
                    $message = "Peminjaman berjaya dikemaskini.";
                    $action = 'view';
                } else {
                    $error = "Hanya peminjaman aktif boleh dikemaskini.";
                }
            } catch (PDOException $e) {
                $error = "Ralat mengemaskini peminjaman: " . $e->getMessage();
            }
        } else {
            $error = "Sila lengkapkan semua field yang diperlukan.";
        }
    }
}

// Handle delete
if ($action === 'delete' && $borrowing_id && $_SESSION['user_role'] === 'admin') {
    try {
        // Get borrowing details
        $stmt = $pdo->prepare("SELECT file_id, status FROM borrowing_records WHERE id = ?");
        $stmt->execute([$borrowing_id]);
        $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($borrowing && $borrowing['status'] !== 'dipinjam') {
            $stmt = $pdo->prepare("DELETE FROM borrowing_records WHERE id = ?");
            $stmt->execute([$borrowing_id]);
            $message = "Rekod peminjaman berjaya dipadam.";
            $action = 'list';
        } else {
            $error = "Peminjaman aktif tidak boleh dipadam.";
        }
    } catch (PDOException $e) {
        $error = "Ralat memadam rekod: " . $e->getMessage();
    }
}

// Get data based on action
$borrowings = [];
$borrowing = null;
$files = [];
$users = [];

if ($action === 'list') {
    // Build search query
    $search = $_GET['search'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $borrower_filter = $_GET['borrower'] ?? '';
    $overdue = $_GET['overdue'] ?? '';
    $due_soon = $_GET['due_soon'] ?? '';
    
    $sql = "SELECT br.*, f.file_id, f.title as file_title, f.department, 
                   u1.name as borrower_name, u2.name as approved_by_name, u3.name as returned_to_name,
                   l.room, l.rack, l.slot,
                   CASE 
                       WHEN br.status = 'dipinjam' AND br.due_date < CURDATE() THEN 'overdue'
                       ELSE br.status 
                   END as actual_status,
                   DATEDIFF(br.due_date, CURDATE()) as days_remaining
            FROM borrowing_records br 
            JOIN files f ON br.file_id = f.id
            JOIN users u1 ON br.borrower_id = u1.id
            LEFT JOIN users u2 ON br.approved_by = u2.id
            LEFT JOIN users u3 ON br.returned_to = u3.id
            LEFT JOIN locations l ON f.location_id = l.id
            WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (f.file_id LIKE ? OR f.title LIKE ? OR u1.name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($status_filter) {
        $sql .= " AND br.status = ?";
        $params[] = $status_filter;
    }
    
    if ($borrower_filter) {
        $sql .= " AND br.borrower_id = ?";
        $params[] = $borrower_filter;
    }
    
    if ($overdue === '1') {
        $sql .= " AND br.status = 'dipinjam' AND br.due_date < CURDATE()";
    }
    
    if ($due_soon === '1') {
        $sql .= " AND br.status = 'dipinjam' AND br.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND br.due_date >= CURDATE()";
    }
    
    $sql .= " ORDER BY br.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get borrowers for filter
    $borrowers_stmt = $pdo->query("SELECT DISTINCT u.id, u.name FROM users u JOIN borrowing_records br ON u.id = br.borrower_id ORDER BY u.name");
    $borrowers = $borrowers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($action === 'view' && $borrowing_id) {
    $stmt = $pdo->prepare("SELECT br.*, f.file_id, f.title as file_title, f.department, f.document_type,
                          u1.name as borrower_name, u1.email as borrower_email, u1.department as borrower_department,
                          u2.name as approved_by_name, u3.name as returned_to_name,
                          l.room, l.rack, l.slot,
                          CASE 
                              WHEN br.status = 'dipinjam' AND br.due_date < CURDATE() THEN 'overdue'
                              ELSE br.status 
                          END as actual_status,
                          DATEDIFF(br.due_date, CURDATE()) as days_remaining
                          FROM borrowing_records br 
                          JOIN files f ON br.file_id = f.id
                          JOIN users u1 ON br.borrower_id = u1.id
                          LEFT JOIN users u2 ON br.approved_by = u2.id
                          LEFT JOIN users u3 ON br.returned_to = u3.id
                          LEFT JOIN locations l ON f.location_id = l.id
                          WHERE br.id = ?");
    $stmt->execute([$borrowing_id]);
    $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
    
} elseif (in_array($action, ['create', 'edit'])) {
    // Get available files for create, or current borrowing for edit
    if ($action === 'create') {
        $sql = "SELECT f.*, l.room, l.rack, l.slot FROM files f 
                LEFT JOIN locations l ON f.location_id = l.id 
                WHERE f.status = 'tersedia'";
        if ($file_id) {
            $sql .= " AND f.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$file_id]);
        } else {
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
        }
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($action === 'edit' && $borrowing_id) {
        $stmt = $pdo->prepare("SELECT * FROM borrowing_records WHERE id = ? AND status = 'dipinjam'");
        $stmt->execute([$borrowing_id]);
        $borrowing = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get users who can borrow files
    $users_stmt = $pdo->query("SELECT id, name, email, department FROM users WHERE is_active = 1 ORDER BY name");
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Role display
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
    <title><?= $action === 'create' ? 'Rekod Peminjaman Baharu' : ($action === 'edit' ? 'Edit Peminjaman' : ($action === 'view' ? 'Butiran Peminjaman' : 'Sistem Peminjaman')) ?> - Sistem Penyimpanan Fail Tongod</title>
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
        .badge-dipinjam { background-color: #d97706; }
        .badge-dikembalikan { background-color: #059669; }
        .badge-overdue { background-color: #dc2626; }
        .overdue-row { background-color: #fef2f2; }
        .due-soon-row { background-color: #fffbeb; }
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
                        
                        <?php if (in_array($_SESSION['user_role'], ['admin', 'staff_jabatan'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="locations.php">
                                <i class="fas fa-map-marker-alt me-2"></i>Lokasi
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <li class="nav-item">
                            <a class="nav-link active" href="borrowings.php">
                                <i class="fas fa-handshake me-2"></i>Peminjaman
                            </a>
                        </li>
                        
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
                    <!-- Borrowings List View -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-handshake me-2"></i>Sistem Peminjaman
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="borrowings.php?action=create" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Rekod Peminjaman Baharu
                            </a>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="row mb-4">
                        <?php
                        $stats_active = $pdo->query("SELECT COUNT(*) FROM borrowing_records WHERE status = 'dipinjam'")->fetchColumn();
                        $stats_overdue = $pdo->query("SELECT COUNT(*) FROM borrowing_records WHERE status = 'dipinjam' AND due_date < CURDATE()")->fetchColumn();
                        $stats_due_soon = $pdo->query("SELECT COUNT(*) FROM borrowing_records WHERE status = 'dipinjam' AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND due_date >= CURDATE()")->fetchColumn();
                        $stats_returned = $pdo->query("SELECT COUNT(*) FROM borrowing_records WHERE status = 'dikembalikan'")->fetchColumn();
                        ?>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">Aktif</div>
                                            <div class="h5 mb-0 font-weight-bold"><?= $stats_active ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-handshake fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card bg-danger text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">Overdue</div>
                                            <div class="h5 mb-0 font-weight-bold"><?= $stats_overdue ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-exclamation-triangle fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">Due Soon</div>
                                            <div class="h5 mb-0 font-weight-bold"><?= $stats_due_soon ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-clock fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <div class="text-xs font-weight-bold text-uppercase mb-1">Dikembalikan</div>
                                            <div class="h5 mb-0 font-weight-bold"><?= $stats_returned ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-check-circle fa-2x opacity-75"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                                <div class="col-md-3">
                                    <label for="search" class="form-label">Carian</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="ID fail, tajuk, atau nama peminjam..." 
                                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Semua Status</option>
                                        <option value="dipinjam" <?= ($_GET['status'] ?? '') === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                                        <option value="dikembalikan" <?= ($_GET['status'] ?? '') === 'dikembalikan' ? 'selected' : '' ?>>Dikembalikan</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="borrower" class="form-label">Peminjam</label>
                                    <select class="form-select" id="borrower" name="borrower">
                                        <option value="">Semua Peminjam</option>
                                        <?php foreach ($borrowers as $borrower): ?>
                                            <option value="<?= $borrower['id'] ?>" <?= ($_GET['borrower'] ?? '') == $borrower['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($borrower['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Filter Cepat</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="overdue" name="overdue" value="1" <?= ($_GET['overdue'] ?? '') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="overdue">Overdue</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="due_soon" name="due_soon" value="1" <?= ($_GET['due_soon'] ?? '') ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="due_soon">Due Soon</label>
                                    </div>
                                </div>
                                <div class="col-md-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search me-1"></i>Cari
                                    </button>
                                    <a href="borrowings.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Borrowings Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-list me-2"></i>Senarai Peminjaman
                                <span class="badge bg-primary ms-2"><?= count($borrowings) ?></span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($borrowings) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID Fail</th>
                                                <th>Tajuk Fail</th>
                                                <th>Peminjam</th>
                                                <th>Tarikh Pinjam</th>
                                                <th>Tarikh Akhir</th>
                                                <th>Status</th>
                                                <th>Tindakan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($borrowings as $br): ?>
                                            <tr class="<?= $br['actual_status'] === 'overdue' ? 'overdue-row' : ($br['actual_status'] === 'dipinjam' && $br['days_remaining'] <= 3 ? 'due-soon-row' : '') ?>">
                                                <td>
                                                    <strong class="text-primary"><?= htmlspecialchars($br['file_id']) ?></strong>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars(substr($br['file_title'], 0, 30)) ?><?= strlen($br['file_title']) > 30 ? '...' : '' ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($br['department']) ?></small>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars($br['borrower_name']) ?></div>
                                                </td>
                                                <td><?= date('d/m/Y', strtotime($br['borrowed_date'])) ?></td>
                                                <td>
                                                    <?= date('d/m/Y', strtotime($br['due_date'])) ?>
                                                    <?php if ($br['status'] === 'dipinjam'): ?>
                                                        <?php if ($br['days_remaining'] < 0): ?>
                                                            <br><small class="text-danger"><?= abs($br['days_remaining']) ?> hari lewat</small>
                                                        <?php elseif ($br['days_remaining'] <= 3): ?>
                                                            <br><small class="text-warning"><?= $br['days_remaining'] ?> hari lagi</small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($br['actual_status'] === 'overdue'): ?>
                                                        <span class="badge badge-overdue">Overdue</span>
                                                    <?php elseif ($br['status'] === 'dipinjam'): ?>
                                                        <span class="badge badge-dipinjam">Dipinjam</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-dikembalikan">Dikembalikan</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="borrowings.php?action=view&id=<?= $br['id'] ?>" 
                                                           class="btn btn-outline-primary" title="Lihat">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <?php if ($br['status'] === 'dipinjam'): ?>
                                                        <a href="borrowings.php?action=edit&id=<?= $br['id'] ?>" 
                                                           class="btn btn-outline-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-outline-success" title="Kembalikan"
                                                                data-bs-toggle="modal" data-bs-target="#returnModal<?= $br['id'] ?>">
                                                            <i class="fas fa-undo"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($_SESSION['user_role'] === 'admin' && $br['status'] !== 'dipinjam'): ?>
                                                        <a href="borrowings.php?action=delete&id=<?= $br['id'] ?>" 
                                                           class="btn btn-outline-danger" title="Padam"
                                                           onclick="return confirm('Adakah anda pasti mahu memadam rekod ini?')">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <!-- Return Modal -->
                                            <?php if ($br['status'] === 'dipinjam'): ?>
                                            <div class="modal fade" id="returnModal<?= $br['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST" action="borrowings.php?action=return&id=<?= $br['id'] ?>">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Kembalikan Fail</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p><strong>Fail:</strong> <?= htmlspecialchars($br['file_id']) ?> - <?= htmlspecialchars($br['file_title']) ?></p>
                                                                <p><strong>Peminjam:</strong> <?= htmlspecialchars($br['borrower_name']) ?></p>
                                                                <div class="mb-3">
                                                                    <label for="notes<?= $br['id'] ?>" class="form-label">Catatan (pilihan)</label>
                                                                    <textarea class="form-control" id="notes<?= $br['id'] ?>" name="notes" rows="3" placeholder="Catatan mengenai pemulangan fail..."></textarea>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                <button type="submit" class="btn btn-success">
                                                                    <i class="fas fa-undo me-1"></i>Kembalikan
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-handshake fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Tiada peminjaman dijumpai</h5>
                                    <p class="text-muted">Cuba ubah kriteria carian anda atau rekod peminjaman baharu.</p>
                                    <a href="borrowings.php?action=create" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Rekod Peminjaman Baharu
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php elseif ($action === 'create'): ?>
                    <!-- Create Borrowing Form -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-plus me-2"></i>Rekod Peminjaman Baharu
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="borrowings.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Maklumat Peminjaman</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="file_id" class="form-label">Fail <span class="text-danger">*</span></label>
                                        <select class="form-select" id="file_id" name="file_id" required>
                                            <option value="">Pilih Fail</option>
                                            <?php foreach ($files as $f): ?>
                                                <option value="<?= $f['id'] ?>" <?= ($file_id && $f['id'] == $file_id) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($f['file_id']) ?> - <?= htmlspecialchars(substr($f['title'], 0, 50)) ?><?= strlen($f['title']) > 50 ? '...' : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="borrower_id" class="form-label">Peminjam <span class="text-danger">*</span></label>
                                        <select class="form-select" id="borrower_id" name="borrower_id" required>
                                            <option value="">Pilih Peminjam</option>
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?= $user['id'] ?>">
                                                    <?= htmlspecialchars($user['name']) ?> - <?= htmlspecialchars($user['department']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="due_date" class="form-label">Tarikh Akhir <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="due_date" name="due_date" 
                                               min="<?= date('Y-m-d', strtotime('+1 day')) ?>" 
                                               value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="purpose" class="form-label">Tujuan Peminjaman <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="purpose" name="purpose" rows="3" 
                                              placeholder="Nyatakan tujuan peminjaman fail..." required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Catatan (pilihan)</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2" 
                                              placeholder="Catatan tambahan..."></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="borrowings.php" class="btn btn-outline-secondary me-md-2">Batal</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Rekod Peminjaman
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($action === 'edit' && $borrowing): ?>
                    <!-- Edit Borrowing Form -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-edit me-2"></i>Edit Peminjaman
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="borrowings.php?action=view&id=<?= $borrowing['id'] ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Kemaskini Maklumat Peminjaman</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="due_date" class="form-label">Tarikh Akhir <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="due_date" name="due_date" 
                                               min="<?= date('Y-m-d') ?>" 
                                               value="<?= $borrowing['due_date'] ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="purpose" class="form-label">Tujuan Peminjaman <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="purpose" name="purpose" rows="3" required><?= htmlspecialchars($borrowing['purpose']) ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Catatan (pilihan)</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="2"><?= htmlspecialchars($borrowing['notes'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="borrowings.php?action=view&id=<?= $borrowing['id'] ?>" class="btn btn-outline-secondary me-md-2">Batal</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Kemaskini
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($action === 'view' && $borrowing): ?>
                    <!-- View Borrowing Details -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-eye me-2"></i>Butiran Peminjaman
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <?php if ($borrowing['status'] === 'dipinjam'): ?>
                                <a href="borrowings.php?action=edit&id=<?= $borrowing['id'] ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#returnModal">
                                    <i class="fas fa-undo me-1"></i>Kembalikan
                                </button>
                                <?php endif; ?>
                            </div>
                            <a href="borrowings.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="m-0 font-weight-bold">Maklumat Peminjaman</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td width="30%" class="fw-semibold">ID Fail:</td>
                                            <td>
                                                <span class="badge bg-primary fs-6"><?= htmlspecialchars($borrowing['file_id']) ?></span>
                                                <a href="files.php?action=view&id=<?= $borrowing['file_id'] ?>" class="btn btn-outline-primary btn-sm ms-2">
                                                    <i class="fas fa-eye"></i> Lihat Fail
                                                </a>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Tajuk Fail:</td>
                                            <td><?= htmlspecialchars($borrowing['file_title']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Jabatan:</td>
                                            <td><?= htmlspecialchars($borrowing['department']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Lokasi:</td>
                                            <td>
                                                <?php if ($borrowing['room']): ?>
                                                    <?= htmlspecialchars($borrowing['room']) ?> - <?= htmlspecialchars($borrowing['rack']) ?> - <?= htmlspecialchars($borrowing['slot']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Peminjam:</td>
                                            <td>
                                                <div><?= htmlspecialchars($borrowing['borrower_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($borrowing['borrower_email']) ?> - <?= htmlspecialchars($borrowing['borrower_department']) ?></small>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Tujuan:</td>
                                            <td><?= htmlspecialchars($borrowing['purpose']) ?></td>
                                        </tr>
                                        <?php if ($borrowing['notes']): ?>
                                        <tr>
                                            <td class="fw-semibold">Catatan:</td>
                                            <td><?= htmlspecialchars($borrowing['notes']) ?></td>
                                        </tr>
                                        <?php endif; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="m-0 font-weight-bold">Status Peminjaman</h6>
                                </div>
                                <div class="card-body text-center">
                                    <?php if ($borrowing['actual_status'] === 'overdue'): ?>
                                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                                        <h5 class="text-danger">OVERDUE</h5>
                                        <p class="text-muted"><?= abs($borrowing['days_remaining']) ?> hari lewat</p>
                                    <?php elseif ($borrowing['status'] === 'dipinjam'): ?>
                                        <?php if ($borrowing['days_remaining'] <= 3): ?>
                                            <i class="fas fa-clock fa-3x text-warning mb-3"></i>
                                            <h5 class="text-warning">DUE SOON</h5>
                                            <p class="text-muted"><?= $borrowing['days_remaining'] ?> hari lagi</p>
                                        <?php else: ?>
                                            <i class="fas fa-handshake fa-3x text-primary mb-3"></i>
                                            <h5 class="text-primary">DIPINJAM</h5>
                                            <p class="text-muted"><?= $borrowing['days_remaining'] ?> hari lagi</p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                        <h5 class="text-success">DIKEMBALIKAN</h5>
                                        <p class="text-muted">Pada <?= date('d/m/Y', strtotime($borrowing['returned_date'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="m-0 font-weight-bold">Tarikh Penting</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2"><small class="text-muted">Tarikh Pinjam:</small><br><?= date('d/m/Y', strtotime($borrowing['borrowed_date'])) ?></p>
                                    <p class="mb-2"><small class="text-muted">Tarikh Akhir:</small><br><?= date('d/m/Y', strtotime($borrowing['due_date'])) ?></p>
                                    <?php if ($borrowing['returned_date']): ?>
                                        <p class="mb-2"><small class="text-muted">Tarikh Dikembalikan:</small><br><?= date('d/m/Y', strtotime($borrowing['returned_date'])) ?></p>
                                    <?php endif; ?>
                                    <hr>
                                    <p class="mb-2"><small class="text-muted">Diluluskan oleh:</small><br><?= htmlspecialchars($borrowing['approved_by_name']) ?></p>
                                    <?php if ($borrowing['returned_to_name']): ?>
                                        <p class="mb-0"><small class="text-muted">Dikembalikan kepada:</small><br><?= htmlspecialchars($borrowing['returned_to_name']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Return Modal -->
                    <?php if ($borrowing['status'] === 'dipinjam'): ?>
                    <div class="modal fade" id="returnModal" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form method="POST" action="borrowings.php?action=return&id=<?= $borrowing['id'] ?>">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Kembalikan Fail</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p><strong>Fail:</strong> <?= htmlspecialchars($borrowing['file_id']) ?> - <?= htmlspecialchars($borrowing['file_title']) ?></p>
                                        <p><strong>Peminjam:</strong> <?= htmlspecialchars($borrowing['borrower_name']) ?></p>
                                        <div class="mb-3">
                                            <label for="return_notes" class="form-label">Catatan (pilihan)</label>
                                            <textarea class="form-control" id="return_notes" name="notes" rows="3" placeholder="Catatan mengenai pemulangan fail..."></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-undo me-1"></i>Kembalikan
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <!-- Error state -->
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Peminjaman tidak dijumpai atau akses tidak dibenarkan.
                    </div>
                    <a href="borrowings.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i>Kembali ke Senarai Peminjaman
                    </a>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>