<?php
// Simple test file untuk check system
echo "<h1>Sistem Penyimpanan Fail Tongod</h1>";
echo "<p>PHP Version: " . phpversion() . "</p>";

// Test database connection
$host = '127.0.0.1';
$dbname = 'db_fail_tongod';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    echo "<p style='color: green;'>✅ Database connection successful!</p>";
    
    // Test if tables exist
    $tables = ['users', 'files', 'locations', 'borrowing_records'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>❌ Table '$table' not found - Run migrations</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Make sure to create database 'db_fail_tongod' in phpMyAdmin</p>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Install Composer jika belum ada</li>";
echo "<li>Run: composer install</li>";
echo "<li>Run: php artisan migrate</li>";
echo "<li>Run: php artisan db:seed</li>";
echo "<li>Run: php artisan serve</li>";
echo "</ol>";

echo "<p><a href='http://localhost/phpmyadmin' target='_blank'>Open phpMyAdmin</a></p>";
?>