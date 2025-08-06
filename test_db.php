<?php
// Script untuk test dan buat database secara automatik

$host = '127.0.0.1';
$username = 'root';
$password = '';
$dbname = 'db_fail_tongod';

echo "<h2>🔧 Database Setup Tool</h2>";

// Step 1: Connect tanpa database untuk create database
try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ Connected to MySQL server</p>";
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $pdo->exec($sql);
    echo "<p style='color: green;'>✅ Database '$dbname' created successfully</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error connecting to MySQL: " . $e->getMessage() . "</p>";
    exit;
}

// Step 2: Connect to the specific database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✅ Connected to database '$dbname'</p>";
    
    // Check if tables exist
    $tables = ['users', 'files', 'locations', 'borrowing_records', 'activity_logs'];
    $tablesExist = 0;
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✅ Table '$table' exists</p>";
            $tablesExist++;
        } else {
            echo "<p style='color: orange;'>⚠️ Table '$table' not found</p>";
        }
    }
    
    if ($tablesExist == 0) {
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>📋 Import Tables</h3>";
        echo "<p>Tables belum wujud. Sila import file <strong>database.sql</strong> melalui phpMyAdmin:</p>";
        echo "<ol>";
        echo "<li>Buka <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a></li>";
        echo "<li>Pilih database 'db_fail_tongod'</li>";
        echo "<li>Klik tab 'Import'</li>";
        echo "<li>Pilih file 'database.sql'</li>";
        echo "<li>Klik 'Go'</li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div style='background: #d1edff; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>🎉 Database Setup Complete!</h3>";
        echo "<p>Database dan tables telah siap. Anda boleh teruskan dengan:</p>";
        echo "<ul>";
        echo "<li>Install Composer dan Laravel dependencies</li>";
        echo "<li>Atau test dengan login: <strong>admin@tongod.gov.my</strong> / <strong>password</strong></li>";
        echo "</ul>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='index.php'>← Back to Main Test</a> | <a href='http://localhost/phpmyadmin' target='_blank'>Open phpMyAdmin →</a></p>";
?>