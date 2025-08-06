-- Script untuk membuat database
CREATE DATABASE IF NOT EXISTS db_fail_tongod 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

-- Pilih database yang baru dibuat
USE db_fail_tongod;

-- Verify database creation
SELECT DATABASE() as current_database;