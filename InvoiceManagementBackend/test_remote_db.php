<?php
// Load .env
$envPath = __DIR__ . '/.env';
$env = [];
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $env[trim($k)] = trim($v);
        }
    }
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$db   = $env['DB_DATABASE'] ?? 'forge';
$user = $env['DB_USERNAME'] ?? 'forge';
$pass = $env['DB_PASSWORD'] ?? '';

echo "Connecting to MySQL at {$host}:{$port}...\n";
echo "Database: {$db}\n";
echo "Username: {$user}\n";

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 7,
        ]
    );
    echo "SUCCESS: Connected successfully to the remote database!\n";
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($tables) . " tables:\n";
    foreach ($tables as $t) {
        echo " - " . $t . "\n";
    }
} catch (PDOException $e) {
    echo "CONNECTION FAILED: " . $e->getMessage() . "\n";
}
