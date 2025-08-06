<?php
require_once 'vendor/autoload.php';

$host = '127.0.0.1';
$username = 'root';
$password = '';
$database = 'db_fail_tongod';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to MySQL database successfully.\n";
    
    // Get all tables
    $tables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    
    $exportData = [];
    
    foreach ($tables as $table) {
        echo "Exporting table: $table\n";
        
        // Get table data
        $stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $exportData[$table] = $rows;
        echo "Exported " . count($rows) . " rows from $table\n";
    }
    
    // Save to JSON file
    file_put_contents('database-export.json', json_encode($exportData, JSON_PRETTY_PRINT));
    echo "\nDatabase exported to database-export.json\n";
    
    // Also create SQL dump for structure
    echo "Creating SQL structure dump...\n";
    $sqlDump = "-- MySQL Database Structure Export\n";
    $sqlDump .= "-- Database: $database\n\n";
    
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $createTable = $row['Create Table'];
        
        // Convert MySQL syntax to PostgreSQL compatible
        $createTable = str_replace('AUTO_INCREMENT', '', $createTable);
        $createTable = str_replace('ENGINE=InnoDB', '', $createTable);
        
        $sqlDump .= "-- Structure for table $table\n";
        $sqlDump .= $createTable . ";\n\n";
    }
    
    file_put_contents('database-structure.sql', $sqlDump);
    echo "SQL structure exported to database-structure.sql\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>