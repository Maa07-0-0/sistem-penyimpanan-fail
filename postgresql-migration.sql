-- PostgreSQL Migration Script
-- Generated from MySQL database: db_fail_tongod
-- Generated at: 2025-08-06 01:57:19

-- Drop existing tables
DROP TABLE IF EXISTS borrowing_records CASCADE;
DROP TABLE IF EXISTS files CASCADE;
DROP TABLE IF EXISTS locations CASCADE;
DROP TABLE IF EXISTS users CASCADE;

-- Create tables
-- Table: borrowing_records
CREATE TABLE borrowing_records (
    id BIGSERIAL NOT NULL,
    file_id BIGINT(20) NOT NULL,
    borrower_id BIGINT(20) NOT NULL,
    approved_by BIGINT(20),
    purpose TEXT NOT NULL,
    borrowed_date DATE NOT NULL,
    due_date DATE NOT NULL,
    returned_date DATE,
    returned_to BIGINT(20),
    status VARCHAR('dipinjam','dikembalikan','overdue') DEFAULT 'dipinjam',
    notes TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT 'current_timestamp()',
    updated_at TIMESTAMP NOT NULL DEFAULT 'current_timestamp()',
    PRIMARY KEY (id)
);

-- Table: files
CREATE TABLE files (
    id BIGSERIAL NOT NULL,
    file_id VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    reference_number VARCHAR(255),
    document_year INTEGER(4) NOT NULL,
    department VARCHAR(255) NOT NULL,
    document_type VARCHAR('surat_rasmi','perjanjian','permit','laporan','lain_lain') NOT NULL,
    description TEXT,
    status VARCHAR('tersedia','dipinjam','arkib','tidak_aktif') DEFAULT 'tersedia',
    location_id BIGINT(20) NOT NULL,
    created_by BIGINT(20) NOT NULL,
    updated_by BIGINT(20),
    created_at TIMESTAMP NOT NULL DEFAULT 'current_timestamp()',
    updated_at TIMESTAMP NOT NULL DEFAULT 'current_timestamp()',
    deleted_at TIMESTAMP,
    PRIMARY KEY (id)
);

-- Table: locations
CREATE TABLE locations (
    id BIGSERIAL NOT NULL,
    room VARCHAR(50) NOT NULL,
    rack VARCHAR(50) NOT NULL,
    slot VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    is_available SMALLINT(1) DEFAULT '1',
    created_at TIMESTAMP NOT NULL DEFAULT 'current_timestamp()',
    updated_at TIMESTAMP NOT NULL DEFAULT 'current_timestamp()',
    PRIMARY KEY (id)
);

-- Table: users
CREATE TABLE users (
    id BIGSERIAL NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR('admin','staff_jabatan','staff_pembantu','user_view') DEFAULT 'user_view',
    department VARCHAR(255),
    position VARCHAR(255),
    phone VARCHAR(255),
    is_active SMALLINT(1) DEFAULT '1',
    created_at TIMESTAMP NOT NULL DEFAULT 'current_timestamp()',
    updated_at TIMESTAMP NOT NULL DEFAULT 'current_timestamp()',
    PRIMARY KEY (id)
);

-- Insert data
-- Data for table: borrowing_records
INSERT INTO borrowing_records (id, file_id, borrower_id, approved_by, purpose, borrowed_date, due_date, returned_date, returned_to, status, notes, created_at, updated_at) VALUES ('1', '1', '3', '3', 'Untuk merujuk isi kandungan ', '2025-08-05', '2025-08-12', '2025-08-05', '3', 'dikembalikan', '', '2025-08-05 10:48:45', '2025-08-05 10:49:14');

-- Data for table: files
INSERT INTO files (id, file_id, title, reference_number, document_year, department, document_type, description, status, location_id, created_by, updated_by, created_at, updated_at, deleted_at) VALUES ('1', 'FAIL20250001', 'Kertas Cadangan Sistem Penyimpanan Fail Pejabat Daerah Tongod ', 'PDTGD - 001 / (08 / 2025)', '2025', 'Pentadbiran', 'laporan', 'Kertas Cadangan Sistem Penyimpanan Fail', 'tersedia', '1', '3', NULL, '2025-08-05 10:05:31', '2025-08-05 10:49:14', NULL);

-- Data for table: locations
INSERT INTO locations (id, room, rack, slot, description, is_available, created_at, updated_at) VALUES ('1', 'Bilik A', 'Rak 1', 'Slot A', 'Lokasi Bilik A - Rak 1 - Slot A', '1', '2025-08-04 22:46:34', '2025-08-04 22:46:34');
INSERT INTO locations (id, room, rack, slot, description, is_available, created_at, updated_at) VALUES ('2', 'Bilik A', 'Rak 1', 'Slot B', 'Lokasi Bilik A - Rak 1 - Slot B', '1', '2025-08-04 22:46:34', '2025-08-04 22:46:34');
INSERT INTO locations (id, room, rack, slot, description, is_available, created_at, updated_at) VALUES ('3', 'Bilik A', 'Rak 2', 'Slot A', 'Lokasi Bilik A - Rak 2 - Slot A', '1', '2025-08-04 22:46:34', '2025-08-04 22:46:34');
INSERT INTO locations (id, room, rack, slot, description, is_available, created_at, updated_at) VALUES ('4', 'Bilik B', 'Rak 1', 'Slot A', 'Lokasi Bilik B - Rak 1 - Slot A', '1', '2025-08-04 22:46:34', '2025-08-04 22:46:34');
INSERT INTO locations (id, room, rack, slot, description, is_available, created_at, updated_at) VALUES ('5', 'Bilik B', 'Rak 1', 'Slot B', 'Lokasi Bilik B - Rak 1 - Slot B', '1', '2025-08-04 22:46:34', '2025-08-04 22:46:34');
INSERT INTO locations (id, room, rack, slot, description, is_available, created_at, updated_at) VALUES ('6', 'Bilik C', 'Rak 1', 'Slot B', '', '1', '2025-08-05 09:48:32', '2025-08-05 09:49:03');

-- Data for table: users
INSERT INTO users (id, name, email, password, role, department, position, phone, is_active, created_at, updated_at) VALUES ('1', 'Administrator', 'admin@tongod.gov.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Pentadbiran', 'Pentadbir Sistem', NULL, '1', '2025-08-04 22:46:33', '2025-08-04 22:46:33');
INSERT INTO users (id, name, email, password, role, department, position, phone, is_active, created_at, updated_at) VALUES ('2', 'Ahmad bin Abdullah', 'ahmad@tongod.gov.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff_jabatan', 'Pentadbiran', 'Pegawai Jabatan', NULL, '1', '2025-08-04 22:46:33', '2025-08-04 22:46:33');
INSERT INTO users (id, name, email, password, role, department, position, phone, is_active, created_at, updated_at) VALUES ('3', 'Siti Nurhaliza', 'siti@tongod.gov.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff_pembantu', 'Kewangan', 'Pembantu Tadbir', NULL, '1', '2025-08-04 22:46:33', '2025-08-04 22:46:33');
INSERT INTO users (id, name, email, password, role, department, position, phone, is_active, created_at, updated_at) VALUES ('4', 'Jusman Juspin', 'jusman@tongod.gov.my', '$2y$10$Dkb2DzCEZyUzjfg/BuKZ2OB0n0X.mIpXOPqq.ERMKaUxJ71z7Iwb2', 'staff_pembantu', 'Kewangan', 'Pembantu Tadbir', '0111234567', '1', '2025-08-05 15:58:18', '2025-08-05 15:58:18');

