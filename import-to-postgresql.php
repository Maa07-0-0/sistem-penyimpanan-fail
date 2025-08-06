<?php
// Import script for PostgreSQL on Railway
// Run this after deploying to Railway and setting up PostgreSQL database

require_once 'vendor/autoload.php';

// Railway PostgreSQL connection
$host = getenv('PGHOST');
$port = getenv('PGPORT');
$database = getenv('PGDATABASE');
$username = getenv('PGUSER');
$password = getenv('PGPASSWORD');

if (!$host || !$database || !$username) {
    die("PostgreSQL environment variables not set. Make sure you're running this on Railway.\n");
}

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to PostgreSQL database successfully.\n";
    
    // Read exported data
    if (!file_exists('database-export.json')) {
        die("database-export.json not found. Please run export-mysql-data.php first.\n");
    }
    
    $data = json_decode(file_get_contents('database-export.json'), true);
    
    // Import data for each table
    foreach ($data as $tableName => $rows) {
        echo "Importing data to table: $tableName\n";
        
        if (empty($rows)) {
            echo "No data to import for $tableName\n";
            continue;
        }
        
        $columns = array_keys($rows[0]);
        $placeholders = ':' . implode(', :', $columns);
        $columnList = implode(', ', $columns);
        
        $sql = "INSERT INTO $tableName ($columnList) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        
        foreach ($rows as $row) {
            try {
                $stmt->execute($row);
            } catch (PDOException $e) {
                echo "Error inserting row in $tableName: " . $e->getMessage() . "\n";
                continue;
            }
        }
        
        echo "Imported " . count($rows) . " rows to $tableName\n";
    }
    
    echo "\nData import completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>