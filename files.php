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

$message = '';
$error = '';
$action = $_GET['action'] ?? 'list';
$file_id = $_GET['id'] ?? null;

// Helper function to generate file ID
function generateFileId($pdo) {
    $year = date('Y');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM files WHERE YEAR(created_at) = ?");
    $stmt->execute([$year]);
    $count = $stmt->fetchColumn() + 1;
    return 'FAIL' . $year . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// Handle form submissions
if ($_POST) {
    if ($action === 'create') {
        $title = $_POST['title'] ?? '';
        $reference_number = $_POST['reference_number'] ?? '';
        $document_year = $_POST['document_year'] ?? '';
        $department = $_POST['department'] ?? '';
        $document_type = $_POST['document_type'] ?? '';
        $description = $_POST['description'] ?? '';
        $location_id = $_POST['location_id'] ?? '';
        
        if ($title && $document_year && $department && $document_type && $location_id) {
            try {
                $file_id_gen = generateFileId($pdo);
                $stmt = $pdo->prepare("INSERT INTO files (file_id, title, reference_number, document_year, department, document_type, description, location_id, created_by, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'tersedia')");
                $stmt->execute([$file_id_gen, $title, $reference_number, $document_year, $department, $document_type, $description, $location_id, $_SESSION['user_id']]);
                
                $message = "Fail berjaya dicipta dengan ID: $file_id_gen";
                $action = 'list';
            } catch (PDOException $e) {
                $error = "Ralat mencipta fail: " . $e->getMessage();
            }
        } else {
            $error = "Sila lengkapkan semua field yang diperlukan.";
        }
    } elseif ($action === 'edit' && $file_id) {
        $title = $_POST['title'] ?? '';
        $reference_number = $_POST['reference_number'] ?? '';
        $document_year = $_POST['document_year'] ?? '';
        $department = $_POST['department'] ?? '';
        $document_type = $_POST['document_type'] ?? '';
        $description = $_POST['description'] ?? '';
        $status = $_POST['status'] ?? '';
        
        if ($title && $document_year && $department && $document_type && $status) {
            try {
                $stmt = $pdo->prepare("UPDATE files SET title = ?, reference_number = ?, document_year = ?, department = ?, document_type = ?, description = ?, status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$title, $reference_number, $document_year, $department, $document_type, $description, $status, $file_id]);
                
                $message = "Fail berjaya dikemaskini.";
                $action = 'view';
            } catch (PDOException $e) {
                $error = "Ralat mengemaskini fail: " . $e->getMessage();
            }
        } else {
            $error = "Sila lengkapkan semua field yang diperlukan.";
        }
    }
}

// Handle delete
if ($action === 'delete' && $file_id && in_array($_SESSION['user_role'], ['admin', 'staff_jabatan'])) {
    try {
        // Check if file is borrowed
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM borrowing_records WHERE file_id = ? AND status = 'dipinjam'");
        $stmt->execute([$file_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Fail tidak boleh dipadam kerana sedang dipinjam.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM files WHERE id = ?");
            $stmt->execute([$file_id]);
            $message = "Fail berjaya dipadam.";
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = "Ralat memadam fail: " . $e->getMessage();
    }
}

// Get data based on action
$files = [];
$file = null;
$locations = [];
$departments = ['Pentadbiran', 'Kewangan', 'Pembangunan', 'Kejuruteraan', 'Perancangan', 'Kesihatan', 'Pendidikan'];

if ($action === 'list') {
    // Build search query
    $search = $_GET['search'] ?? '';
    $department_filter = $_GET['department'] ?? '';
    $document_type_filter = $_GET['document_type'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    $year_filter = $_GET['year'] ?? '';
    
    $sql = "SELECT f.*, l.room, l.rack, l.slot, u.name as created_by_name 
            FROM files f 
            LEFT JOIN locations l ON f.location_id = l.id 
            LEFT JOIN users u ON f.created_by = u.id 
            WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (f.title LIKE ? OR f.file_id LIKE ? OR f.reference_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($department_filter) {
        $sql .= " AND f.department = ?";
        $params[] = $department_filter;
    }
    
    if ($document_type_filter) {
        $sql .= " AND f.document_type = ?";
        $params[] = $document_type_filter;
    }
    
    if ($status_filter) {
        $sql .= " AND f.status = ?";
        $params[] = $status_filter;
    }
    
    if ($year_filter) {
        $sql .= " AND f.document_year = ?";
        $params[] = $year_filter;
    }
    
    $sql .= " ORDER BY f.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($action === 'view' && $file_id) {
    $stmt = $pdo->prepare("SELECT f.*, l.room, l.rack, l.slot, u1.name as created_by_name, u2.name as updated_by_name 
                          FROM files f 
                          LEFT JOIN locations l ON f.location_id = l.id 
                          LEFT JOIN users u1 ON f.created_by = u1.id 
                          LEFT JOIN users u2 ON f.updated_by = u2.id 
                          WHERE f.id = ?");
    $stmt->execute([$file_id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
} elseif (in_array($action, ['create', 'edit'])) {
    // Get locations for dropdown
    $stmt = $pdo->query("SELECT * FROM locations WHERE is_available = 1 ORDER BY room, rack, slot");
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($action === 'edit' && $file_id) {
        $stmt = $pdo->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$file_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Get years for filter
$years_stmt = $pdo->query("SELECT DISTINCT document_year FROM files ORDER BY document_year DESC");
$years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);

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
    <title><?= $action === 'create' ? 'Daftar Fail Baharu' : ($action === 'edit' ? 'Edit Fail' : ($action === 'view' ? 'Lihat Fail' : 'Pengurusan Fail')) ?> - Sistem Penyimpanan Fail Tongod</title>
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
        .badge-tersedia { background-color: #059669; }
        .badge-dipinjam { background-color: #d97706; }
        .badge-arkib { background-color: #6b7280; }
        .badge-tidak_aktif { background-color: #dc2626; }
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
                            <a class="nav-link active" href="files.php">
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
                    <!-- Files List View -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-folder me-2"></i>Pengurusan Fail
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <?php if (in_array($_SESSION['user_role'], ['admin', 'staff_jabatan', 'staff_pembantu'])): ?>
                            <a href="files.php?action=create" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Daftar Fail Baharu
                            </a>
                            <?php endif; ?>
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
                                           placeholder="Tajuk, ID fail, atau nombor rujukan..." 
                                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                </div>
                                <div class="col-md-2">
                                    <label for="department" class="form-label">Jabatan</label>
                                    <select class="form-select" id="department" name="department">
                                        <option value="">Semua Jabatan</option>
                                        <?php foreach ($departments as $dept): ?>
                                            <option value="<?= $dept ?>" <?= ($_GET['department'] ?? '') === $dept ? 'selected' : '' ?>>
                                                <?= $dept ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="document_type" class="form-label">Jenis Dokumen</label>
                                    <select class="form-select" id="document_type" name="document_type">
                                        <option value="">Semua Jenis</option>
                                        <option value="surat_rasmi" <?= ($_GET['document_type'] ?? '') === 'surat_rasmi' ? 'selected' : '' ?>>Surat Rasmi</option>
                                        <option value="perjanjian" <?= ($_GET['document_type'] ?? '') === 'perjanjian' ? 'selected' : '' ?>>Perjanjian</option>
                                        <option value="permit" <?= ($_GET['document_type'] ?? '') === 'permit' ? 'selected' : '' ?>>Permit</option>
                                        <option value="laporan" <?= ($_GET['document_type'] ?? '') === 'laporan' ? 'selected' : '' ?>>Laporan</option>
                                        <option value="lain_lain" <?= ($_GET['document_type'] ?? '') === 'lain_lain' ? 'selected' : '' ?>>Lain-lain</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Semua Status</option>
                                        <option value="tersedia" <?= ($_GET['status'] ?? '') === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                                        <option value="dipinjam" <?= ($_GET['status'] ?? '') === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                                        <option value="arkib" <?= ($_GET['status'] ?? '') === 'arkib' ? 'selected' : '' ?>>Arkib</option>
                                        <option value="tidak_aktif" <?= ($_GET['status'] ?? '') === 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label for="year" class="form-label">Tahun</label>
                                    <select class="form-select" id="year" name="year">
                                        <option value="">Semua Tahun</option>
                                        <?php foreach ($years as $year): ?>
                                            <option value="<?= $year ?>" <?= ($_GET['year'] ?? '') == $year ? 'selected' : '' ?>>
                                                <?= $year ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Cari
                                    </button>
                                    <a href="files.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Files Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold">
                                <i class="fas fa-list me-2"></i>Senarai Fail
                                <span class="badge bg-primary ms-2"><?= count($files) ?></span>
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php if (count($files) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>ID Fail</th>
                                                <th>Tajuk</th>
                                                <th>Jabatan</th>
                                                <th>Jenis</th>
                                                <th>Tahun</th>
                                                <th>Lokasi</th>
                                                <th>Status</th>
                                                <th>Tindakan</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($files as $f): ?>
                                            <tr>
                                                <td>
                                                    <strong class="text-primary"><?= htmlspecialchars($f['file_id']) ?></strong>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars(substr($f['title'], 0, 40)) ?><?= strlen($f['title']) > 40 ? '...' : '' ?></div>
                                                    <?php if ($f['reference_number']): ?>
                                                        <small class="text-muted">Rujukan: <?= htmlspecialchars($f['reference_number']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($f['department']) ?></td>
                                                <td>
                                                    <span class="badge bg-info text-dark">
                                                        <?php
                                                        $types = [
                                                            'surat_rasmi' => 'Surat Rasmi',
                                                            'perjanjian' => 'Perjanjian',
                                                            'permit' => 'Permit',
                                                            'laporan' => 'Laporan',
                                                            'lain_lain' => 'Lain-lain'
                                                        ];
                                                        echo $types[$f['document_type']] ?? $f['document_type'];
                                                        ?>
                                                    </span>
                                                </td>
                                                <td><?= $f['document_year'] ?></td>
                                                <td>
                                                    <?php if ($f['room']): ?>
                                                        <small class="text-primary"><?= htmlspecialchars($f['room']) ?> - <?= htmlspecialchars($f['rack']) ?> - <?= htmlspecialchars($f['slot']) ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= $f['status'] ?>">
                                                        <?php
                                                        $statuses = [
                                                            'tersedia' => 'Tersedia',
                                                            'dipinjam' => 'Dipinjam',
                                                            'arkib' => 'Arkib',
                                                            'tidak_aktif' => 'Tidak Aktif'
                                                        ];
                                                        echo $statuses[$f['status']] ?? $f['status'];
                                                        ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="files.php?action=view&id=<?= $f['id'] ?>" 
                                                           class="btn btn-outline-primary" title="Lihat">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        
                                                        <?php if (in_array($_SESSION['user_role'], ['admin', 'staff_jabatan', 'staff_pembantu'])): ?>
                                                        <a href="files.php?action=edit&id=<?= $f['id'] ?>" 
                                                           class="btn btn-outline-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($f['status'] === 'tersedia' && in_array($_SESSION['user_role'], ['admin', 'staff_jabatan', 'staff_pembantu'])): ?>
                                                        <a href="borrowings.php?action=create&file_id=<?= $f['id'] ?>" 
                                                           class="btn btn-outline-info" title="Pinjam">
                                                            <i class="fas fa-handshake"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (in_array($_SESSION['user_role'], ['admin', 'staff_jabatan'])): ?>
                                                        <a href="files.php?action=delete&id=<?= $f['id'] ?>" 
                                                           class="btn btn-outline-danger" title="Padam"
                                                           onclick="return confirm('Adakah anda pasti mahu memadam fail ini?')">
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
                                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Tiada fail dijumpai</h5>
                                    <p class="text-muted">Cuba ubah kriteria carian anda atau daftar fail baharu.</p>
                                    <?php if (in_array($_SESSION['user_role'], ['admin', 'staff_jabatan', 'staff_pembantu'])): ?>
                                    <a href="files.php?action=create" class="btn btn-primary">
                                        <i class="fas fa-plus me-1"></i>Daftar Fail Baharu
                                    </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php elseif ($action === 'create'): ?>
                    <!-- Create File Form -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-plus me-2"></i>Daftar Fail Baharu
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="files.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Maklumat Fail</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="title" class="form-label">Tajuk Fail <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="document_year" class="form-label">Tahun Dokumen <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="document_year" name="document_year" 
                                               min="1900" max="<?= date('Y') + 5 ?>" value="<?= date('Y') ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="reference_number" class="form-label">Nombor Rujukan</label>
                                        <input type="text" class="form-control" id="reference_number" name="reference_number">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="department" class="form-label">Jabatan <span class="text-danger">*</span></label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="">Pilih Jabatan</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= $dept ?>"><?= $dept ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="document_type" class="form-label">Jenis Dokumen <span class="text-danger">*</span></label>
                                        <select class="form-select" id="document_type" name="document_type" required>
                                            <option value="">Pilih Jenis Dokumen</option>
                                            <option value="surat_rasmi">Surat Rasmi</option>
                                            <option value="perjanjian">Perjanjian</option>
                                            <option value="permit">Permit</option>
                                            <option value="laporan">Laporan</option>
                                            <option value="lain_lain">Lain-lain</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="location_id" class="form-label">Lokasi <span class="text-danger">*</span></label>
                                        <select class="form-select" id="location_id" name="location_id" required>
                                            <option value="">Pilih Lokasi</option>
                                            <?php foreach ($locations as $loc): ?>
                                                <option value="<?= $loc['id'] ?>">
                                                    <?= htmlspecialchars($loc['room']) ?> - <?= htmlspecialchars($loc['rack']) ?> - <?= htmlspecialchars($loc['slot']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Keterangan</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="files.php" class="btn btn-outline-secondary me-md-2">Batal</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Simpan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($action === 'edit' && $file): ?>
                    <!-- Edit File Form -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-edit me-2"></i>Edit Fail: <?= htmlspecialchars($file['file_id']) ?>
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="files.php?action=view&id=<?= $file['id'] ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Kemaskini Maklumat Fail</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="title" class="form-label">Tajuk Fail <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" 
                                               value="<?= htmlspecialchars($file['title']) ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="document_year" class="form-label">Tahun Dokumen <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="document_year" name="document_year" 
                                               min="1900" max="<?= date('Y') + 5 ?>" value="<?= $file['document_year'] ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="reference_number" class="form-label">Nombor Rujukan</label>
                                        <input type="text" class="form-control" id="reference_number" name="reference_number"
                                               value="<?= htmlspecialchars($file['reference_number'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="department" class="form-label">Jabatan <span class="text-danger">*</span></label>
                                        <select class="form-select" id="department" name="department" required>
                                            <option value="">Pilih Jabatan</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?= $dept ?>" <?= $file['department'] === $dept ? 'selected' : '' ?>><?= $dept ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="document_type" class="form-label">Jenis Dokumen <span class="text-danger">*</span></label>
                                        <select class="form-select" id="document_type" name="document_type" required>
                                            <option value="">Pilih Jenis Dokumen</option>
                                            <option value="surat_rasmi" <?= $file['document_type'] === 'surat_rasmi' ? 'selected' : '' ?>>Surat Rasmi</option>
                                            <option value="perjanjian" <?= $file['document_type'] === 'perjanjian' ? 'selected' : '' ?>>Perjanjian</option>
                                            <option value="permit" <?= $file['document_type'] === 'permit' ? 'selected' : '' ?>>Permit</option>
                                            <option value="laporan" <?= $file['document_type'] === 'laporan' ? 'selected' : '' ?>>Laporan</option>
                                            <option value="lain_lain" <?= $file['document_type'] === 'lain_lain' ? 'selected' : '' ?>>Lain-lain</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                                        <select class="form-select" id="status" name="status" required>
                                            <option value="tersedia" <?= $file['status'] === 'tersedia' ? 'selected' : '' ?>>Tersedia</option>
                                            <option value="dipinjam" <?= $file['status'] === 'dipinjam' ? 'selected' : '' ?>>Dipinjam</option>
                                            <option value="arkib" <?= $file['status'] === 'arkib' ? 'selected' : '' ?>>Arkib</option>
                                            <option value="tidak_aktif" <?= $file['status'] === 'tidak_aktif' ? 'selected' : '' ?>>Tidak Aktif</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Keterangan</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($file['description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="files.php?action=view&id=<?= $file['id'] ?>" class="btn btn-outline-secondary me-md-2">Batal</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Kemaskini
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($action === 'view' && $file): ?>
                    <!-- View File Details -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-eye me-2"></i>Butiran Fail: <?= htmlspecialchars($file['file_id']) ?>
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <?php if (in_array($_SESSION['user_role'], ['admin', 'staff_jabatan', 'staff_pembantu'])): ?>
                                <a href="files.php?action=edit&id=<?= $file['id'] ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($file['status'] === 'tersedia' && in_array($_SESSION['user_role'], ['admin', 'staff_jabatan', 'staff_pembantu'])): ?>
                                <a href="borrowings.php?action=create&file_id=<?= $file['id'] ?>" class="btn btn-info">
                                    <i class="fas fa-handshake me-1"></i>Pinjam
                                </a>
                                <?php endif; ?>
                            </div>
                            <a href="files.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="m-0 font-weight-bold">Maklumat Fail</h6>
                                </div>
                                <div class="card-body">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td width="30%" class="fw-semibold">ID Fail:</td>
                                            <td><span class="badge bg-primary fs-6"><?= htmlspecialchars($file['file_id']) ?></span></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Tajuk:</td>
                                            <td><?= htmlspecialchars($file['title']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Nombor Rujukan:</td>
                                            <td><?= $file['reference_number'] ? htmlspecialchars($file['reference_number']) : '<span class="text-muted">-</span>' ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Jabatan:</td>
                                            <td><?= htmlspecialchars($file['department']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Jenis Dokumen:</td>
                                            <td>
                                                <span class="badge bg-info text-dark">
                                                    <?php
                                                    $types = [
                                                        'surat_rasmi' => 'Surat Rasmi',
                                                        'perjanjian' => 'Perjanjian',
                                                        'permit' => 'Permit',
                                                        'laporan' => 'Laporan',
                                                        'lain_lain' => 'Lain-lain'
                                                    ];
                                                    echo $types[$file['document_type']] ?? $file['document_type'];
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Tahun Dokumen:</td>
                                            <td><?= $file['document_year'] ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Status:</td>
                                            <td>
                                                <span class="badge badge-<?= $file['status'] ?> fs-6">
                                                    <?php
                                                    $statuses = [
                                                        'tersedia' => 'Tersedia',
                                                        'dipinjam' => 'Dipinjam',
                                                        'arkib' => 'Arkib',
                                                        'tidak_aktif' => 'Tidak Aktif'
                                                    ];
                                                    echo $statuses[$file['status']] ?? $file['status'];
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Keterangan:</td>
                                            <td><?= $file['description'] ? htmlspecialchars($file['description']) : '<span class="text-muted">-</span>' ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="m-0 font-weight-bold">Maklumat Lokasi</h6>
                                </div>
                                <div class="card-body">
                                    <?php if ($file['room']): ?>
                                        <p class="mb-2"><i class="fas fa-building me-2 text-primary"></i><strong>Bilik:</strong> <?= htmlspecialchars($file['room']) ?></p>
                                        <p class="mb-2"><i class="fas fa-list me-2 text-primary"></i><strong>Rak:</strong> <?= htmlspecialchars($file['rack']) ?></p>
                                        <p class="mb-2"><i class="fas fa-square me-2 text-primary"></i><strong>Slot:</strong> <?= htmlspecialchars($file['slot']) ?></p>
                                        <div class="alert alert-info">
                                            <small><i class="fas fa-map-marker-alt me-1"></i>Lokasi Penuh: <?= htmlspecialchars($file['room']) ?> - <?= htmlspecialchars($file['rack']) ?> - <?= htmlspecialchars($file['slot']) ?></small>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center">Tiada maklumat lokasi</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="m-0 font-weight-bold">Maklumat Sistem</h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2"><small class="text-muted">Dicipta oleh:</small><br><?= htmlspecialchars($file['created_by_name']) ?></p>
                                    <p class="mb-2"><small class="text-muted">Tarikh dicipta:</small><br><?= date('d/m/Y H:i', strtotime($file['created_at'])) ?></p>
                                    <?php if ($file['updated_at'] !== $file['created_at']): ?>
                                        <p class="mb-2"><small class="text-muted">Kemaskini terakhir:</small><br><?= date('d/m/Y H:i', strtotime($file['updated_at'])) ?></p>
                                        <?php if ($file['updated_by_name']): ?>
                                            <p class="mb-0"><small class="text-muted">Dikemaskini oleh:</small><br><?= htmlspecialchars($file['updated_by_name']) ?></p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <!-- Error state -->
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Fail tidak dijumpai atau akses tidak dibenarkan.
                    </div>
                    <a href="files.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i>Kembali ke Senarai Fail
                    </a>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>