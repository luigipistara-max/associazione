<?php
/**
 * Database connection with table prefix support
 */

// Load configuration
$config = require __DIR__ . '/config.php';

try {
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        $config['db']['host'],
        $config['db']['dbname'],
        $config['db']['charset']
    );
    
    $pdo = new PDO($dsn, $config['db']['username'], $config['db']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

/**
 * Get table name with prefix
 * 
 * @param string $table Table name without prefix
 * @return string Table name with prefix
 */
function table(string $table): string {
    global $config;
    return $config['db']['prefix'] . $table;
}

/**
 * Get database prefix
 * 
 * @return string Database prefix
 */
function getPrefix(): string {
    global $config;
    return $config['db']['prefix'];
}
