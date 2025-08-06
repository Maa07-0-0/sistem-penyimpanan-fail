
<?php
echo "<h1>🛠️ Quick Database Setup</h1>";
echo "<p>Mari kita buat database secara manual:</p>";

// Test MySQL connection first
$host = '127.0.0.1';
$username = 'root';
$password = '';

echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>📋 Manual Steps:</h3>";
echo "<ol style='line-height: 2;'>";
echo "<li><strong>Buka phpMyAdmin:</strong> <a href='http://localhost/phpmyadmin' target='_blank' style='background: #007bff; color: white; padding: 5px 10px; border-radius: 4px; text-decoration: none;'>Klik Sini</a></li>";
echo "<li><strong>Login:</strong> Username = root, Password = (kosong)</li>";
echo "<li><strong>Klik 'New'</strong> di sebelah kiri atau tab 'Databases'</li>";
echo "<li><strong>Database name:</strong> <code style='background: #e9ecef; padding: 2px 5px;'>db_fail_tongod</code></li>";
echo "<li><strong>Collation:</strong> <code style='background: #e9ecef; padding: 2px 5px;'>utf8mb4_unicode_ci</code></li>";
echo "<li><strong>Klik 'Create'</strong></li>";
echo "</ol>";
echo "</div>";

// Try to connect and create database automatically
echo "<div style='background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h3>🔄 Automatic Setup</h3>";

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS db_fail_tongod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    $pdo->exec($sql);
    
    echo "<p style='color: #155724; background: #d4edda; padding: 10px; border-radius: 4px;'>";
    echo "✅ <strong>SUCCESS!</strong> Database 'db_fail_tongod' telah dicipta automatik!";
    echo "</p>";
    
    // Now test connection to the new database
    $pdo2 = new PDO("mysql:host=$host;dbname=db_fail_tongod", $username, $password);
    echo "<p style='color: #155724;'>✅ Connection to new database successful!</p>";
    
    echo "<div style='background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>📥 Import Tables</h4>";
    echo "<p>Database sudah siap! Sekarang import tables:</p>";
    echo "<ol>";
    echo "<li>Pergi ke <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a></li>";
    echo "<li>Pilih database <strong>db_fail_tongod</strong> di sebelah kiri</li>";
    echo "<li>Klik tab <strong>Import</strong></li>";
    echo "<li>Klik <strong>Choose File</strong> dan pilih file <code>database.sql</code></li>";
    echo "<li>Klik <strong>Go</strong></li>";
    echo "</ol>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p style='color: #721c24; background: #f8d7da; padding: 10px; border-radius: 4px;'>";
    echo "❌ <strong>ERROR:</strong> " . $e->getMessage();
    echo "</p>";
    
    echo "<p><strong>Possible issues:</strong></p>";
    echo "<ul>";
    echo "<li>XAMPP MySQL service tidak running</li>";
    echo "<li>MySQL password bukan kosong</li>";
    echo "<li>MySQL port bukan 3306</li>";
    echo "</ul>";
    
    echo "<p><strong>Solutions:</strong></p>";
    echo "<ol>";
    echo "<li>Buka XAMPP Control Panel</li>";
    echo "<li>Pastikan MySQL status = 'Running' (hijau)</li>";
    echo "<li>Jika tidak running, klik 'Start'</li>";
    echo "<li>Refresh page ini</li>";
    echo "</ol>";
}

echo "</div>";

// Quick action buttons
echo "<div style='text-align: center; margin: 30px 0;'>";
echo "<a href='http://localhost/phpmyadmin' target='_blank' style='background: #28a745; color: white; padding: 12px 20px; border-radius: 6px; text-decoration: none; margin: 0 10px; display: inline-block;'>📊 Open phpMyAdmin</a>";
echo "<a href='index.php' style='background: #17a2b8; color: white; padding: 12px 20px; border-radius: 6px; text-decoration: none; margin: 0 10px; display: inline-block;'>🔄 Test Connection</a>";
echo "</div>";

echo "<hr>";
echo "<div style='background: #e2e3e5; padding: 15px; border-radius: 5px;'>";
echo "<h4>🆘 Need Help?</h4>";
echo "<p>Jika masih ada masalah:</p>";
echo "<ol>";
echo "<li><strong>Check XAMPP:</strong> Pastikan Apache & MySQL running</li>";
echo "<li><strong>Check Port:</strong> MySQL biasanya port 3306</li>";
echo "<li><strong>Check Password:</strong> Default XAMPP MySQL password adalah kosong</li>";
echo "</ol>";
echo "</div>";
?>

<style>
body { 
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    max-width: 800px; 
    margin: 0 auto; 
    padding: 20px;
    background: #f8f9fa;
}
code { 
    background: #e9ecef; 
    padding: 2px 6px; 
    border-radius: 3px; 
    font-family: 'Courier New', monospace;
}
</style>