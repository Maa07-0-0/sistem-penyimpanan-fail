<?php
/**
 * MySQL to PostgreSQL Migration Script
 * Exports MySQL database structure and data, converts to PostgreSQL format
 */

require_once 'vendor/autoload.php';

class MySQLToPostgreSQLMigrator {
    private $pdo;
    private $database;
    
    public function __construct($host = '127.0.0.1', $username = 'root', $password = '', $database = 'db_fail_tongod') {
        $this->database = $database;
        try {
            $this->pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "✓ Connected to MySQL database: $database\n";
        } catch (PDOException $e) {
            die("✗ Connection failed: " . $e->getMessage() . "\n");
        }
    }
    
    public function exportDatabase() {
        echo "\n=== EXPORTING DATABASE STRUCTURE AND DATA ===\n";
        
        $tables = $this->getTables();
        $export = [
            'database' => $this->database,
            'tables' => [],
            'data' => [],
            'export_time' => date('Y-m-d H:i:s')
        ];
        
        foreach ($tables as $table) {
            echo "Processing table: $table\n";
            
            // Get table structure
            $structure = $this->getTableStructure($table);
            $export['tables'][$table] = $structure;
            
            // Get table data
            $data = $this->getTableData($table);
            $export['data'][$table] = $data;
            
            echo "  - Columns: " . count($structure['columns']) . "\n";
            echo "  - Records: " . count($data) . "\n";
        }
        
        // Save complete export
        file_put_contents('mysql-export-complete.json', json_encode($export, JSON_PRETTY_PRINT));
        echo "✓ Complete export saved to: mysql-export-complete.json\n";
        
        return $export;
    }
    
    private function getTables() {
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = [];
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        return $tables;
    }
    
    private function getTableStructure($table) {
        // Get CREATE TABLE statement
        $stmt = $this->pdo->query("SHOW CREATE TABLE `$table`");
        $createTable = $stmt->fetch(PDO::FETCH_ASSOC)['Create Table'];
        
        // Get detailed column information
        $stmt = $this->pdo->query("DESCRIBE `$table`");
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = [
                'name' => $row['Field'],
                'type' => $row['Type'],
                'null' => $row['Null'] === 'YES',
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }
        
        // Get indexes
        $stmt = $this->pdo->query("SHOW INDEXES FROM `$table`");
        $indexes = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $indexes[] = $row;
        }
        
        return [
            'create_sql' => $createTable,
            'columns' => $columns,
            'indexes' => $indexes
        ];
    }
    
    private function getTableData($table) {
        $stmt = $this->pdo->query("SELECT * FROM `$table`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function convertToPostgreSQL($export) {
        echo "\n=== CONVERTING TO POSTGRESQL FORMAT ===\n";
        
        $postgresql = [
            'database' => $export['database'],
            'tables' => [],
            'data' => $export['data'],
            'migration_sql' => []
        ];
        
        foreach ($export['tables'] as $tableName => $tableInfo) {
            echo "Converting table: $tableName\n";
            
            $pgTable = $this->convertTableStructure($tableName, $tableInfo);
            $postgresql['tables'][$tableName] = $pgTable;
            $postgresql['migration_sql'][$tableName] = $pgTable['create_sql'];
        }
        
        // Generate complete PostgreSQL migration script
        $this->generatePostgreSQLScript($postgresql);
        
        return $postgresql;
    }
    
    private function convertTableStructure($tableName, $tableInfo) {
        $columns = [];
        $constraints = [];
        $sequences = [];
        
        foreach ($tableInfo['columns'] as $column) {
            $pgColumn = $this->convertColumnType($column);
            $columns[] = $pgColumn;
            
            // Handle auto increment
            if (strpos($column['extra'], 'auto_increment') !== false) {
                $sequences[] = [
                    'table' => $tableName,
                    'column' => $column['name'],
                    'sequence' => $tableName . '_' . $column['name'] . '_seq'
                ];
            }
            
            // Handle primary key
            if ($column['key'] === 'PRI') {
                $constraints[] = "PRIMARY KEY ({$column['name']})";
            }
        }
        
        // Build CREATE TABLE SQL
        $createSQL = "CREATE TABLE $tableName (\n";
        $createSQL .= "    " . implode(",\n    ", $columns);
        
        if (!empty($constraints)) {
            $createSQL .= ",\n    " . implode(",\n    ", $constraints);
        }
        
        $createSQL .= "\n);";
        
        return [
            'create_sql' => $createSQL,
            'columns' => $columns,
            'constraints' => $constraints,
            'sequences' => $sequences
        ];
    }
    
    private function convertColumnType($column) {
        $name = $column['name'];
        $mysqlType = strtolower($column['type']);
        $null = $column['null'] ? '' : ' NOT NULL';
        $default = '';
        
        // Handle default values
        if ($column['default'] !== null && $column['default'] !== '') {
            if ($column['default'] === 'CURRENT_TIMESTAMP') {
                $default = ' DEFAULT CURRENT_TIMESTAMP';
            } else {
                $default = " DEFAULT '" . $column['default'] . "'";
            }
        }
        
        // Convert MySQL types to PostgreSQL
        $pgType = $this->mapDataType($mysqlType, $column['extra']);
        
        return "$name $pgType$null$default";
    }
    
    private function mapDataType($mysqlType, $extra = '') {
        // Handle auto increment
        if (strpos($extra, 'auto_increment') !== false) {
            if (strpos($mysqlType, 'bigint') !== false) {
                return 'BIGSERIAL';
            } else {
                return 'SERIAL';
            }
        }
        
        // Basic type mapping
        $typeMap = [
            'tinyint(1)' => 'BOOLEAN',
            'varchar' => 'VARCHAR',
            'text' => 'TEXT',
            'longtext' => 'TEXT',
            'mediumtext' => 'TEXT',
            'int' => 'INTEGER',
            'bigint' => 'BIGINT',
            'smallint' => 'SMALLINT',
            'tinyint' => 'SMALLINT',
            'decimal' => 'DECIMAL',
            'float' => 'REAL',
            'double' => 'DOUBLE PRECISION',
            'datetime' => 'TIMESTAMP',
            'timestamp' => 'TIMESTAMP',
            'date' => 'DATE',
            'time' => 'TIME',
            'year' => 'INTEGER',
            'enum' => 'VARCHAR',
            'set' => 'TEXT',
            'json' => 'JSONB'
        ];
        
        // Extract base type and size
        if (preg_match('/^(\w+)(\([^)]+\))?/', $mysqlType, $matches)) {
            $baseType = $matches[1];
            $size = isset($matches[2]) ? $matches[2] : '';
            
            if (isset($typeMap[$baseType])) {
                return $typeMap[$baseType] . $size;
            } else if (isset($typeMap[$mysqlType])) {
                return $typeMap[$mysqlType];
            }
        }
        
        // Fallback
        return 'TEXT';
    }
    
    private function generatePostgreSQLScript($postgresql) {
        $script = "-- PostgreSQL Migration Script\n";
        $script .= "-- Generated from MySQL database: {$postgresql['database']}\n";
        $script .= "-- Generated at: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Drop tables if exist
        $script .= "-- Drop existing tables\n";
        foreach (array_keys($postgresql['tables']) as $table) {
            $script .= "DROP TABLE IF EXISTS $table CASCADE;\n";
        }
        $script .= "\n";
        
        // Create tables
        $script .= "-- Create tables\n";
        foreach ($postgresql['migration_sql'] as $table => $sql) {
            $script .= "-- Table: $table\n";
            $script .= "$sql\n\n";
        }
        
        // Insert data
        $script .= "-- Insert data\n";
        foreach ($postgresql['data'] as $table => $rows) {
            if (empty($rows)) continue;
            
            $script .= "-- Data for table: $table\n";
            $columns = array_keys($rows[0]);
            $columnList = implode(', ', $columns);
            
            foreach ($rows as $row) {
                $values = array_map(function($value) {
                    return $value === null ? 'NULL' : "'" . addslashes($value) . "'";
                }, array_values($row));
                $valueList = implode(', ', $values);
                $script .= "INSERT INTO $table ($columnList) VALUES ($valueList);\n";
            }
            $script .= "\n";
        }
        
        file_put_contents('postgresql-migration.sql', $script);
        echo "✓ PostgreSQL migration script saved to: postgresql-migration.sql\n";
        
        // Also save structured data
        file_put_contents('postgresql-export.json', json_encode($postgresql, JSON_PRETTY_PRINT));
        echo "✓ PostgreSQL structure saved to: postgresql-export.json\n";
    }
}

// Run the migration
echo "MySQL to PostgreSQL Database Migrator\n";
echo "=====================================\n";

$migrator = new MySQLToPostgreSQLMigrator();
$export = $migrator->exportDatabase();
$postgresql = $migrator->convertToPostgreSQL($export);

echo "\n=== MIGRATION SUMMARY ===\n";
echo "Tables exported: " . count($export['tables']) . "\n";
echo "Total records: " . array_sum(array_map('count', $export['data'])) . "\n";
echo "\nFiles generated:\n";
echo "- mysql-export-complete.json (complete MySQL export)\n";
echo "- postgresql-export.json (converted PostgreSQL structure)\n";
echo "- postgresql-migration.sql (ready-to-run migration script)\n";
echo "\n✓ Migration conversion completed!\n";
?>