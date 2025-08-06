<?php
session_start();

// Check if user is logged in and has permission
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['admin', 'staff_jabatan'])) {
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
$location_id = $_GET['id'] ?? null;

// Handle form submissions
if ($_POST) {
    if ($action === 'create') {
        $room = $_POST['room'] ?? '';
        $rack = $_POST['rack'] ?? '';
        $slot = $_POST['slot'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if ($room && $rack && $slot) {
            try {
                // Check if location already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM locations WHERE room = ? AND rack = ? AND slot = ?");
                $stmt->execute([$room, $rack, $slot]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Lokasi dengan kombinasi Bilik-Rak-Slot ini sudah wujud.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO locations (room, rack, slot, description, is_available) VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([$room, $rack, $slot, $description]);
                    
                    $message = "Lokasi berjaya dicipta.";
                    $action = 'list';
                }
            } catch (PDOException $e) {
                $error = "Ralat mencipta lokasi: " . $e->getMessage();
            }
        } else {
            $error = "Sila lengkapkan semua field yang diperlukan.";
        }
    } elseif ($action === 'edit' && $location_id) {
        $room = $_POST['room'] ?? '';
        $rack = $_POST['rack'] ?? '';
        $slot = $_POST['slot'] ?? '';
        $description = $_POST['description'] ?? '';
        $is_available = isset($_POST['is_available']) ? 1 : 0;
        
        if ($room && $rack && $slot) {
            try {
                // Check if location already exists (excluding current)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM locations WHERE room = ? AND rack = ? AND slot = ? AND id != ?");
                $stmt->execute([$room, $rack, $slot, $location_id]);
                if ($stmt->fetchColumn() > 0) {
                    $error = "Lokasi dengan kombinasi Bilik-Rak-Slot ini sudah wujud.";
                } else {
                    $stmt = $pdo->prepare("UPDATE locations SET room = ?, rack = ?, slot = ?, description = ?, is_available = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$room, $rack, $slot, $description, $is_available, $location_id]);
                    
                    $message = "Lokasi berjaya dikemaskini.";
                    $action = 'view';
                }
            } catch (PDOException $e) {
                $error = "Ralat mengemaskini lokasi: " . $e->getMessage();
            }
        } else {
            $error = "Sila lengkapkan semua field yang diperlukan.";
        }
    }
}

// Handle delete
if ($action === 'delete' && $location_id) {
    try {
        // Check if location has files
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM files WHERE location_id = ?");
        $stmt->execute([$location_id]);
        if ($stmt->fetchColumn() > 0) {
            $error = "Lokasi tidak boleh dipadam kerana masih mengandungi fail.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
            $stmt->execute([$location_id]);
            $message = "Lokasi berjaya dipadam.";
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error = "Ralat memadam lokasi: " . $e->getMessage();
    }
}

// Get data based on action
$locations = [];
$location = null;

if ($action === 'list') {
    // Build search query
    $search = $_GET['search'] ?? '';
    $room_filter = $_GET['room'] ?? '';
    $status_filter = $_GET['status'] ?? '';
    
    $sql = "SELECT l.*, COUNT(f.id) as file_count 
            FROM locations l 
            LEFT JOIN files f ON l.id = f.location_id 
            WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (l.room LIKE ? OR l.rack LIKE ? OR l.slot LIKE ? OR l.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if ($room_filter) {
        $sql .= " AND l.room = ?";
        $params[] = $room_filter;
    }
    
    if ($status_filter === 'available') {
        $sql .= " AND l.is_available = 1";
    } elseif ($status_filter === 'occupied') {
        $sql .= " AND EXISTS (SELECT 1 FROM files WHERE location_id = l.id)";
    }
    
    $sql .= " GROUP BY l.id ORDER BY l.room, l.rack, l.slot";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} elseif ($action === 'view' && $location_id) {
    $stmt = $pdo->prepare("SELECT l.*, COUNT(f.id) as file_count 
                          FROM locations l 
                          LEFT JOIN files f ON l.id = f.location_id 
                          WHERE l.id = ? 
                          GROUP BY l.id");
    $stmt->execute([$location_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get files in this location
    if ($location) {
        $stmt = $pdo->prepare("SELECT f.*, u.name as created_by_name, 
                              br.status as borrowing_status, br.borrower_id, ub.name as borrower_name
                              FROM files f 
                              LEFT JOIN users u ON f.created_by = u.id
                              LEFT JOIN borrowing_records br ON f.id = br.file_id AND br.status = 'dipinjam'
                              LEFT JOIN users ub ON br.borrower_id = ub.id
                              WHERE f.location_id = ? 
                              ORDER BY f.created_at DESC");
        $stmt->execute([$location_id]);
        $location['files'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} elseif ($action === 'edit' && $location_id) {
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$location_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get rooms for filter
$rooms_stmt = $pdo->query("SELECT DISTINCT room FROM locations ORDER BY room");
$rooms = $rooms_stmt->fetchAll(PDO::FETCH_COLUMN);

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
    <title><?= $action === 'create' ? 'Tambah Lokasi Baharu' : ($action === 'edit' ? 'Edit Lokasi' : ($action === 'view' ? 'Lihat Lokasi' : 'Pengurusan Lokasi')) ?> - Sistem Penyimpanan Fail Tongod</title>
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
        .location-card {
            transition: transform 0.2s;
        }
        .location-card:hover {
            transform: translateY(-2px);
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
                            <a class="nav-link active" href="locations.php">
                                <i class="fas fa-map-marker-alt me-2"></i>Lokasi
                            </a>
                        </li>
                        
                        <li class="nav-item">
                            <a class="nav-link" href="borrowings.php">
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
                    <!-- Locations List View -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-map-marker-alt me-2"></i>Pengurusan Lokasi
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="locations.php?action=create" class="btn btn-primary">
                                <i class="fas fa-plus me-1"></i>Tambah Lokasi Baharu
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
                                           placeholder="Bilik, rak, slot, atau keterangan..." 
                                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label for="room" class="form-label">Bilik</label>
                                    <select class="form-select" id="room" name="room">
                                        <option value="">Semua Bilik</option>
                                        <?php foreach ($rooms as $room): ?>
                                            <option value="<?= htmlspecialchars($room) ?>" <?= ($_GET['room'] ?? '') === $room ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($room) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="">Semua Status</option>
                                        <option value="available" <?= ($_GET['status'] ?? '') === 'available' ? 'selected' : '' ?>>Tersedia</option>
                                        <option value="occupied" <?= ($_GET['status'] ?? '') === 'occupied' ? 'selected' : '' ?>>Ada Fail</option>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <a href="locations.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Locations Grid -->
                    <?php if (count($locations) > 0): ?>
                        <div class="row">
                            <?php foreach ($locations as $loc): ?>
                            <div class="col-lg-4 col-md-6 mb-4">
                                <div class="card location-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h5 class="card-title mb-0">
                                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                <?= htmlspecialchars($loc['room']) ?>
                                            </h5>
                                            <span class="badge <?= $loc['is_available'] ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $loc['is_available'] ? 'Aktif' : 'Tidak Aktif' ?>
                                            </span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="row text-center">
                                                <div class="col-6">
                                                    <div class="border rounded p-2">
                                                        <i class="fas fa-list text-info"></i>
                                                        <div class="fw-semibold"><?= htmlspecialchars($loc['rack']) ?></div>
                                                        <small class="text-muted">Rak</small>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <div class="border rounded p-2">
                                                        <i class="fas fa-square text-warning"></i>
                                                        <div class="fw-semibold"><?= htmlspecialchars($loc['slot']) ?></div>
                                                        <small class="text-muted">Slot</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($loc['description']): ?>
                                        <p class="card-text text-muted small mb-3">
                                            <?= htmlspecialchars(substr($loc['description'], 0, 80)) ?><?= strlen($loc['description']) > 80 ? '...' : '' ?>
                                        </p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-primary">
                                                    <?= $loc['file_count'] ?> fail
                                                </span>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <a href="locations.php?action=view&id=<?= $loc['id'] ?>" 
                                                   class="btn btn-outline-primary" title="Lihat">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="locations.php?action=edit&id=<?= $loc['id'] ?>" 
                                                   class="btn btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="locations.php?action=delete&id=<?= $loc['id'] ?>" 
                                                   class="btn btn-outline-danger" title="Padam"
                                                   onclick="return confirm('Adakah anda pasti mahu memadam lokasi ini?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Tiada lokasi dijumpai</h5>
                                <p class="text-muted">Cuba ubah kriteria carian anda atau tambah lokasi baharu.</p>
                                <a href="locations.php?action=create" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Tambah Lokasi Baharu
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php elseif ($action === 'create'): ?>
                    <!-- Create Location Form -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-plus me-2"></i>Tambah Lokasi Baharu
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="locations.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Maklumat Lokasi</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="room" class="form-label">Bilik <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="room" name="room" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="rack" class="form-label">Rak <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="rack" name="rack" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="slot" class="form-label">Slot <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="slot" name="slot" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Keterangan</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="locations.php" class="btn btn-outline-secondary me-md-2">Batal</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Simpan
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($action === 'edit' && $location): ?>
                    <!-- Edit Location Form -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-edit me-2"></i>Edit Lokasi
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="locations.php?action=view&id=<?= $location['id'] ?>" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="m-0 font-weight-bold">Kemaskini Maklumat Lokasi</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="room" class="form-label">Bilik <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="room" name="room" 
                                               value="<?= htmlspecialchars($location['room']) ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="rack" class="form-label">Rak <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="rack" name="rack" 
                                               value="<?= htmlspecialchars($location['rack']) ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="slot" class="form-label">Slot <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="slot" name="slot" 
                                               value="<?= htmlspecialchars($location['slot']) ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Keterangan</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($location['description'] ?? '') ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="is_available" name="is_available" 
                                               <?= $location['is_available'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_available">
                                            Lokasi Aktif
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <a href="locations.php?action=view&id=<?= $location['id'] ?>" class="btn btn-outline-secondary me-md-2">Batal</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i>Kemaskini
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php elseif ($action === 'view' && $location): ?>
                    <!-- View Location Details -->
                    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2 fw-bold text-primary">
                            <i class="fas fa-eye me-2"></i>Butiran Lokasi
                        </h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <a href="locations.php?action=edit&id=<?= $location['id'] ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </a>
                            </div>
                            <a href="locations.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Kembali
                            </a>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="m-0 font-weight-bold">Maklumat Lokasi</h6>
                                </div>
                                <div class="card-body">
                                    <div class="text-center mb-4">
                                        <i class="fas fa-map-marker-alt fa-3x text-primary mb-3"></i>
                                        <h4><?= htmlspecialchars($location['room']) ?> - <?= htmlspecialchars($location['rack']) ?> - <?= htmlspecialchars($location['slot']) ?></h4>
                                        <span class="badge <?= $location['is_available'] ? 'bg-success' : 'bg-secondary' ?> fs-6">
                                            <?= $location['is_available'] ? 'Aktif' : 'Tidak Aktif' ?>
                                        </span>
                                    </div>
                                    
                                    <table class="table table-borderless">
                                        <tr>
                                            <td class="fw-semibold">Bilik:</td>
                                            <td><?= htmlspecialchars($location['room']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Rak:</td>
                                            <td><?= htmlspecialchars($location['rack']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Slot:</td>
                                            <td><?= htmlspecialchars($location['slot']) ?></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Jumlah Fail:</td>
                                            <td><span class="badge bg-primary"><?= $location['file_count'] ?></span></td>
                                        </tr>
                                        <tr>
                                            <td class="fw-semibold">Dicipta:</td>
                                            <td><?= date('d/m/Y H:i', strtotime($location['created_at'])) ?></td>
                                        </tr>
                                    </table>
                                    
                                    <?php if ($location['description']): ?>
                                    <div class="mt-3">
                                        <h6>Keterangan:</h6>
                                        <p class="text-muted"><?= htmlspecialchars($location['description']) ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">
                                        <i class="fas fa-folder me-2"></i>Fail di Lokasi Ini
                                        <span class="badge bg-primary ms-2"><?= count($location['files']) ?></span>
                                    </h6>
                                </div>
                                <div class="card-body p-0">
                                    <?php if (count($location['files']) > 0): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>ID Fail</th>
                                                        <th>Tajuk</th>
                                                        <th>Jabatan</th>
                                                        <th>Status</th>
                                                        <th>Tindakan</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($location['files'] as $file): ?>
                                                    <tr>
                                                        <td>
                                                            <strong class="text-primary"><?= htmlspecialchars($file['file_id']) ?></strong>
                                                        </td>
                                                        <td>
                                                            <div class="fw-semibold"><?= htmlspecialchars(substr($file['title'], 0, 30)) ?><?= strlen($file['title']) > 30 ? '...' : '' ?></div>
                                                            <?php if ($file['borrowing_status'] === 'dipinjam'): ?>
                                                                <small class="text-warning">Dipinjam oleh: <?= htmlspecialchars($file['borrower_name']) ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($file['department']) ?></td>
                                                        <td>
                                                            <?php if ($file['status'] === 'tersedia'): ?>
                                                                <span class="badge bg-success">Tersedia</span>
                                                            <?php elseif ($file['status'] === 'dipinjam'): ?>
                                                                <span class="badge bg-warning">Dipinjam</span>
                                                            <?php elseif ($file['status'] === 'arkib'): ?>
                                                                <span class="badge bg-secondary">Arkib</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Tidak Aktif</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <a href="files.php?action=view&id=<?= $file['id'] ?>" 
                                                               class="btn btn-outline-primary btn-sm" title="Lihat">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-folder-open fa-2x text-muted mb-3"></i>
                                            <p class="text-muted">Tiada fail di lokasi ini</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php else: ?>
                    <!-- Error state -->
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>Lokasi tidak dijumpai atau akses tidak dibenarkan.
                    </div>
                    <a href="locations.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-1"></i>Kembali ke Senarai Lokasi
                    </a>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>