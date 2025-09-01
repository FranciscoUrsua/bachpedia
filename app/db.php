<?php
declare(strict_types=1);

/**
 * app/db.php
 *
 * Devuelve una instancia de PDO conectada a MySQL.
 * Prioriza app/config.php; si no existe, usa variables de entorno.
 *
 * Requiere PHP 8+ y la extensión pdo_mysql.
 */

$config = [];
$configPath = __DIR__ . '/config.php';
if (is_file($configPath)) {
    // Debe devolver un array asociativo con claves DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
    $config = require $configPath;
}

// Variables (config.php > entorno > valores por defecto)
$dbHost = $config['DB_HOST'] ?? getenv('DB_HOST') ?: 'localhost';
$dbPort = (string)($config['DB_PORT'] ?? getenv('DB_PORT') ?: '3306');
$dbName = $config['DB_NAME'] ?? getenv('DB_NAME') ?: 'bachdb';
$dbUser = $config['DB_USER'] ?? getenv('DB_USER') ?: 'bachuser';
$dbPass = $config['DB_PASS'] ?? getenv('DB_PASS') ?: '';

// DSN: usa utf8mb4 para Unicode completo (MySQL 5.5+ / 8.0+)
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName);

// Opciones recomendadas de PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // errores como excepciones
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // arrays asociativos por defecto
    PDO::ATTR_EMULATE_PREPARES   => false,                  // prepara en el servidor si es posible
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    // No expongas detalles en web; registra y devuelve 500.
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "DB connection failed: " . $e->getMessage() . PHP_EOL);
        exit(1);
    } else {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        echo 'Database connection error.';
        exit;
    }
}

return $pdo;
