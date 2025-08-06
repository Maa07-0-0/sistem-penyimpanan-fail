-- MySQL Database Structure Export
-- Database: db_fail_tongod

-- Structure for table borrowing_records
CREATE TABLE `borrowing_records` (
  `id` bigint(20) unsigned NOT NULL ,
  `file_id` bigint(20) unsigned NOT NULL,
  `borrower_id` bigint(20) unsigned NOT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `purpose` text NOT NULL,
  `borrowed_date` date NOT NULL,
  `due_date` date NOT NULL,
  `returned_date` date DEFAULT NULL,
  `returned_to` bigint(20) unsigned DEFAULT NULL,
  `status` enum('dipinjam','dikembalikan','overdue') DEFAULT 'dipinjam',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `file_id` (`file_id`),
  KEY `borrower_id` (`borrower_id`),
  KEY `fk_approved_by` (`approved_by`),
  KEY `fk_returned_to` (`returned_to`),
  CONSTRAINT `borrowing_records_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `files` (`id`),
  CONSTRAINT `borrowing_records_ibfk_2` FOREIGN KEY (`borrower_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_returned_to` FOREIGN KEY (`returned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL
)  =2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Structure for table files
CREATE TABLE `files` (
  `id` bigint(20) unsigned NOT NULL ,
  `file_id` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `document_year` year(4) NOT NULL,
  `department` varchar(255) NOT NULL,
  `document_type` enum('surat_rasmi','perjanjian','permit','laporan','lain_lain') NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('tersedia','dipinjam','arkib','tidak_aktif') DEFAULT 'tersedia',
  `location_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `updated_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_id` (`file_id`),
  KEY `location_id` (`location_id`),
  KEY `created_by` (`created_by`),
  KEY `fk_files_updated_by` (`updated_by`),
  CONSTRAINT `files_ibfk_1` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`),
  CONSTRAINT `files_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_files_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
)  =2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Structure for table locations
CREATE TABLE `locations` (
  `id` bigint(20) unsigned NOT NULL ,
  `room` varchar(50) NOT NULL,
  `rack` varchar(50) NOT NULL,
  `slot` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_location` (`room`,`rack`,`slot`)
)  =7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Structure for table users
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL ,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff_jabatan','staff_pembantu','user_view') DEFAULT 'user_view',
  `department` varchar(255) DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
)  =5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

