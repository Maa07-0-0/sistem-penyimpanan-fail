<?php
echo "<h1>📥 Import Database Tables</h1>";

$host = '127.0.0.1';
$username = 'root';
$password = '';
$dbname = 'db_fail_tongod';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Connected to database '$dbname'</p>";
    
    // Read and execute the SQL file
    $sqlFile = 'database.sql';
    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
        
        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $successful = 0;
        $failed = 0;
        
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>🔄 Executing SQL Statements...</h3>";
        
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue; // Skip empty lines and comments
            }
            
            try {
                $pdo->exec($statement);
                
                // Check if it's a CREATE TABLE statement
                if (stripos($statement, 'CREATE TABLE') !== false) {
                    preg_match('/CREATE TABLE\s+(\w+)/i', $statement, $matches);
                    if (isset($matches[1])) {
                        echo "<p style='color: #155724;'>✅ Table '{$matches[1]}' created</p>";
                    }
                }
                
                $successful++;
            } catch (PDOException $e) {
                echo "<p style='color: #721c24;'>❌ Error: " . $e->getMessage() . "</p>";
                $failed++;
            }
        }
        
        echo "</div>";
        
        echo "<div style='background: " . ($failed == 0 ? '#d4edda' : '#fff3cd') . "; padding: 15px; border-radius: 5px;'>";
        echo "<h3>" . ($failed == 0 ? '🎉' : '⚠️') . " Import Results</h3>";
        echo "<p><strong>Successful:</strong> $successful statements</p>";
        echo "<p><strong>Failed:</strong> $failed statements</p>";
        echo "</div>";
        
        // Verify tables created
        echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 5px; margin-top: 20px;'>";
        echo "<h3>🔍 Verify Tables</h3>";
        
        $tables = ['users', 'files', 'locations', 'borrowing_records', 'activity_logs'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<p style='color: #155724;'>✅ Table '$table' exists</p>";
            } else {
                echo "<p style='color: #721c24;'>❌ Table '$table' missing</p>";
            }
        }
        echo "</div>";
        
    } else {
        echo "<p style='color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px;'>";
        echo "❌ File 'database.sql' not found!";
        echo "</p>";
        
        // Create tables manually
        echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h3>🔧 Manual Table Creation</h3>";
        echo "<p>Creating tables manually...</p>";
        
        $createTables = [
            "CREATE TABLE users (
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
            )",
            
            "CREATE TABLE locations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                room VARCHAR(50) NOT NULL,
                rack VARCHAR(50) NOT NULL,
                slot VARCHAR(50) NOT NULL,
                description VARCHAR(255) NULL,
                is_available BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_location (room, rack, slot)
            )",
            
            "INSERT INTO users (name, email, password, role, department, position) VALUES 
            ('Administrator', 'admin@tongod.gov.my', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Pentadbiran', 'Pentadbir Sistem')",
            
            "INSERT INTO locations (room, rack, slot, description) VALUES 
            ('Bilik A', 'Rak 1', 'Slot A', 'Lokasi Bilik A - Rak 1 - Slot A'),
            ('Bilik A', 'Rak 1', 'Slot B', 'Lokasi Bilik A - Rak 1 - Slot B'),
            ('Bilik B', 'Rak 1', 'Slot A', 'Lokasi Bilik B - Rak 1 - Slot A')"
        ];
        
        foreach ($createTables as $sql) {
            try {
                $pdo->exec($sql);
                if (stripos($sql, 'CREATE TABLE') !== false) {
                    preg_match('/CREATE TABLE\s+(\w+)/i', $sql, $matches);
                    if (isset($matches[1])) {
                        echo "<p style='color: #155724;'>✅ Table '{$matches[1]}' created manually</p>";
                    }
                } elseif (stripos($sql, 'INSERT INTO') !== false) {
                    echo "<p style='color: #0c5460;'>✅ Sample data inserted</p>";
                }
            } catch (PDOException $e) {
                echo "<p style='color: #721c24;'>❌ Error: " . $e->getMessage() . "</p>";
            }
        }
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px;'>";
    echo "❌ Database connection failed: " . $e->getMessage();
    echo "</p>";
}

echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='index.php' style='background: #28a745; color: white; padding: 12px 20px; border-radius: 6px; text-decoration: none; margin: 0 10px; display: inline-block;'>🔄 Test Database</a>";
echo "<a href='http://localhost/phpmyadmin' target='_blank' style='background: #17a2b8; color: white; padding: 12px 20px; border-radius: 6px; text-decoration: none; margin: 0 10px; display: inline-block;'>📊 Open phpMyAdmin</a>";
echo "</div>";
?>

<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    max-width: 900px; 
    margin: 0 auto; 
    padding: 20px;
    background: #f8f9fa;
}
</style>