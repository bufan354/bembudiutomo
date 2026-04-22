<?php
// TEMPORARY DEBUG — HAPUS SETELAH ERROR KETAHUAN
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cek satu per satu file yang di-include
echo "<pre style='background:#111;color:#0f0;padding:15px;font-family:monospace;font-size:13px;'>";
echo "=== DEBUG LOGIN.PHP ===\n\n";

// Step 1: Cek PHP version
echo "PHP Version: " . PHP_VERSION . "\n";

// Step 2: Cek apakah file-file ada
$files = [
    '../config/database.php'    => __DIR__ . '/../config/database.php',
    '../config/app.php'         => __DIR__ . '/../config/app.php',
    '../config/path-detection.php' => __DIR__ . '/../config/path-detection.php',
    '../includes/functions.php' => __DIR__ . '/../includes/functions.php',
    '.env (root)'               => __DIR__ . '/../.env',
    '.env (parent)'             => dirname(__DIR__, 2) . '/.env',
];

echo "--- Cek File ---\n";
foreach ($files as $label => $path) {
    $exists = file_exists($path) ? "✅ ADA" : "❌ TIDAK ADA";
    echo "{$exists} | {$label}\n";
    echo "         Path: {$path}\n";
}

// Step 3: Coba load satu per satu
echo "\n--- Load database.php ---\n";
try {
    require_once __DIR__ . '/../config/database.php';
    echo "✅ database.php loaded\n";
    echo "DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT DEFINED') . "\n";
    echo "DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT DEFINED') . "\n";
    echo "DB_PASS: " . (defined('DB_PASS') ? (empty(DB_PASS) ? '❌ KOSONG' : '✅ ada (' . strlen(DB_PASS) . ' chars)') : 'NOT DEFINED') . "\n";
    echo "DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT DEFINED') . "\n";
    echo "DB_DEBUG: " . (defined('DB_DEBUG') ? (DB_DEBUG ? 'true' : 'false') : 'NOT DEFINED') . "\n";
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " line " . $e->getLine() . "\n";
}

echo "\n--- Test Koneksi DB ---\n";
try {
    $conn = getConnection();
    echo "✅ Koneksi DB berhasil\n";
} catch (Throwable $e) {
    echo "❌ Koneksi DB gagal: " . $e->getMessage() . "\n";
}

echo "\n--- Load functions.php ---\n";
try {
    require_once __DIR__ . '/../includes/functions.php';
    echo "✅ functions.php loaded\n";
    echo "APP_ENV: " . (defined('APP_ENV') ? APP_ENV : 'NOT DEFINED') . "\n";
    echo "BASE_URL: " . (defined('BASE_URL') ? BASE_URL : 'NOT DEFINED') . "\n";
    echo "session_status: " . session_status() . " (1=none, 2=active)\n";
} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " line " . $e->getLine() . "\n";
}

// Step 4: Cek tabel users
echo "\n--- Cek Tabel users ---\n";
try {
    $cols = dbFetchAll("SHOW COLUMNS FROM users");
    $colNames = array_column($cols, 'Field');
    echo "Kolom ada: " . implode(', ', $colNames) . "\n";
    
    $needed = ['id', 'username', 'password', 'role', 'is_active', 'last_ip', 'periode_id', 'can_access_all'];
    foreach ($needed as $col) {
        $status = in_array($col, $colNames) ? "✅" : "❌ TIDAK ADA";
        echo "{$status} {$col}\n";
    }
} catch (Throwable $e) {
    echo "❌ ERROR cek tabel: " . $e->getMessage() . "\n";
}

echo "\n=== END DEBUG ===";
echo "</pre>";
die(); // Stop di sini, jangan lanjut ke login
?>