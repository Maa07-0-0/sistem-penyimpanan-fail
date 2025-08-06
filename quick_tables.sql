-- Quick table creation for basic functionality
USE db_fail_tongod;

-- Users table
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff_jabatan', 'staff_pembantu', 'user_view') DEFAULT 'user_view',
    department VARCHAR(255) NULL,
    position VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Locations table
CREATE TABLE locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room VARCHAR(50) NOT NULL,
    rack VARCHAR(50) NOT NULL,
    slot VARCHAR(50) NOT NULL,
    description VARCHAR(255) NULL,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_location (room, rack, slot)
);

-- Files table
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Borrowing records table
CREATE TABLE borrowing_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id BIGINT UNSIGNED NOT NULL,
    borrower_id BIGINT UNSIGNED NOT NULL,
    purpose TEXT NOT NULL,
    borrowed_date DATE NOT NULL,
    due_date DATE NOT NULL,
    returned_date DATE NULL,
    status ENUM('dipinjam', 'dikembalikan', 'overdue') DEFAULT 'dipinjam',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES files(id),
    FOREIGN KEY (borrower_id) REFERENCES users(id)
);

-- Insert sample users
INSERT INTO users (name, email, password, role, department, position) VALUES 
('Administrator', 'admin@tongod.gov.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Pentadbiran', 'Pentadbir Sistem'),
('Ahmad bin Abdullah', 'ahmad@tongod.gov.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff_jabatan', 'Pentadbiran', 'Pegawai Jabatan'),
('Siti Nurhaliza', 'siti@tongod.gov.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff_pembantu', 'Kewangan', 'Pembantu Tadbir');

-- Insert sample locations
INSERT INTO locations (room, rack, slot, description) VALUES 
('Bilik A', 'Rak 1', 'Slot A', 'Lokasi Bilik A - Rak 1 - Slot A'),
('Bilik A', 'Rak 1', 'Slot B', 'Lokasi Bilik A - Rak 1 - Slot B'),
('Bilik A', 'Rak 2', 'Slot A', 'Lokasi Bilik A - Rak 2 - Slot A'),
('Bilik B', 'Rak 1', 'Slot A', 'Lokasi Bilik B - Rak 1 - Slot A'),
('Bilik B', 'Rak 1', 'Slot B', 'Lokasi Bilik B - Rak 1 - Slot B');