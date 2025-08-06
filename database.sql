-- Database: db_fail_tongod
-- Sistem Penyimpanan Fail Pejabat Daerah Tongod

-- Table: users
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff_jabatan', 'staff_pembantu', 'user_view') DEFAULT 'user_view',
    department VARCHAR(255) NULL,
    position VARCHAR(255) NULL,
    phone VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Table: locations
CREATE TABLE locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room VARCHAR(50) NOT NULL,
    rack VARCHAR(50) NOT NULL,
    slot VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_location (room, rack, slot),
    INDEX idx_room (room),
    INDEX idx_rack (rack),
    INDEX idx_slot (slot)
);

-- Table: files
CREATE TABLE files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id VARCHAR(255) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    reference_number VARCHAR(255) NULL,
    document_year YEAR NOT NULL,
    department VARCHAR(255) NOT NULL,
    document_type ENUM('surat_rasmi', 'perjanjian', 'permit', 'laporan', 'lain_lain') NOT NULL,
    description TEXT NULL,
    status ENUM('tersedia', 'dipinjam', 'arkib', 'tidak_aktif') DEFAULT 'tersedia',
    location_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_file_id (file_id),
    INDEX idx_reference_number (reference_number),
    INDEX idx_document_year (document_year),
    INDEX idx_department (department),
    INDEX idx_document_type (document_type),
    INDEX idx_status (status),
    INDEX idx_title_ref (title, reference_number),
    INDEX idx_dept_year (department, document_year)
);

-- Table: file_movements
CREATE TABLE file_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id BIGINT UNSIGNED NOT NULL,
    from_location_id BIGINT UNSIGNED NULL,
    to_location_id BIGINT UNSIGNED NOT NULL,
    moved_by BIGINT UNSIGNED NOT NULL,
    reason TEXT NULL,
    moved_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (from_location_id) REFERENCES locations(id) ON DELETE SET NULL,
    FOREIGN KEY (to_location_id) REFERENCES locations(id) ON DELETE CASCADE,
    FOREIGN KEY (moved_by) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_file_moved (file_id, moved_at)
);

-- Table: borrowing_records
CREATE TABLE borrowing_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id BIGINT UNSIGNED NOT NULL,
    borrower_id BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    purpose TEXT NOT NULL,
    borrowed_date DATE NOT NULL,
    due_date DATE NOT NULL,
    returned_date DATE NULL,
    returned_to BIGINT UNSIGNED NULL,
    status ENUM('dipinjam', 'dikembalikan', 'overdue') DEFAULT 'dipinjam',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
    FOREIGN KEY (borrower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (returned_to) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_file_status (file_id, status),
    INDEX idx_borrower_date (borrower_id, borrowed_date),
    INDEX idx_due_status (due_date, status)
);

-- Table: activity_logs
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(255) NOT NULL,
    subject_type VARCHAR(255) NULL,
    subject_id BIGINT UNSIGNED NULL,
    properties JSON NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_subject (subject_type, subject_id),
    INDEX idx_action_created (action, created_at)
);

-- Table: password_reset_tokens
CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL
);

-- Table: failed_jobs
CREATE TABLE failed_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(255) UNIQUE NOT NULL,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload LONGTEXT NOT NULL,
    exception LONGTEXT NOT NULL,
    failed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table: personal_access_tokens
CREATE TABLE personal_access_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tokenable (tokenable_type, tokenable_id)
);

-- Insert sample data
-- Admin user
INSERT INTO users (name, email, password, role, department, position, is_active) VALUES
('Administrator', 'admin@tongod.gov.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Pentadbiran', 'Pentadbir Sistem', TRUE),
('Ahmad bin Abdullah', 'ahmad@tongod.gov.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff_jabatan', 'Pentadbiran', 'Pegawai Jabatan', TRUE),
('Siti Nurhaliza', 'siti@tongod.gov.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff_pembantu', 'Kewangan', 'Pembantu Tadbir', TRUE);

-- Sample locations
INSERT INTO locations (room, rack, slot, description, is_available) VALUES
('Bilik A', 'Rak 1', 'Slot A', 'Lokasi Bilik A - Rak 1 - Slot A', TRUE),
('Bilik A', 'Rak 1', 'Slot B', 'Lokasi Bilik A - Rak 1 - Slot B', TRUE),
('Bilik A', 'Rak 2', 'Slot A', 'Lokasi Bilik A - Rak 2 - Slot A', TRUE),
('Bilik B', 'Rak 1', 'Slot A', 'Lokasi Bilik B - Rak 1 - Slot A', TRUE),
('Bilik B', 'Rak 1', 'Slot B', 'Lokasi Bilik B - Rak 1 - Slot B', TRUE),
('Bilik C', 'Rak 1', 'Slot A', 'Lokasi Bilik C - Rak 1 - Slot A', TRUE);