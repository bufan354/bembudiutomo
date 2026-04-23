<?php
// config/database.php - Koneksi database dengan PDO (PostgreSQL/Supabase)
// VERSI: 4.0 - Migrasi ke Supabase (PostgreSQL) menggunakan PDO

// ============================================
// 1. LOAD KREDENSIAL DARI .env
// ============================================
(function () {
    $candidates = [
        dirname(__DIR__, 1) . '/.env',
        dirname(__DIR__, 2) . '/.env',
        dirname(__DIR__, 3) . '/.env',
    ];

    foreach ($candidates as $envFile) {
        if (!file_exists($envFile)) continue;

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            if (!str_contains($line, '=')) continue;
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            
            // Hapus tanda kutip jika ada (misal: "password" -> password)
            $val = trim($val, "\"'");
            
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $val;
            }
        }
        break;
    }
})();

// ============================================
// 2. KONSTANTA DATABASE
// ============================================
// Prioritaskan environment variables dari sistem (untuk Render/Railway)
defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ''));
defined('DB_PORT') || define('DB_PORT', getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '5432'));
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ''));
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? ''));
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ''));

// ============================================
// 3. MODE DEBUG
// ============================================
defined('DB_DEBUG') || define('DB_DEBUG',
    (defined('APP_ENV') ? APP_ENV : ($_ENV['APP_ENV'] ?? 'production')) === 'development'
);

// ============================================
// 4. KONEKSI DATABASE
// ============================================

/**
 * Mendapatkan koneksi database (singleton per request).
 * Menggunakan PDO dengan driver pgsql.
 *
 * @return PDO
 * @throws RuntimeException jika koneksi gagal
 */
function getConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = sprintf("pgsql:host=%s;port=%s;dbname=%s", DB_HOST, DB_PORT, DB_NAME);
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => true,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("[DB CONNECT ERROR] " . $e->getMessage());

            if (DB_DEBUG) {
                throw new RuntimeException("Koneksi DB gagal: " . $e->getMessage());
            }
            throw new RuntimeException("Terjadi kesalahan sistem. Silakan coba lagi.");
        }
    }

    return $pdo;
}

// ============================================
// 5. QUERY HELPER
// ============================================

/**
 * Jalankan query dengan prepared statement.
 *
 * @param  string $sql
 * @param  array  $params
 * @param  string $types (Deprecated, kept for compatibility)
 * @return PDOStatement|bool
 * @throws RuntimeException
 */
function dbQuery(string $sql, array $params = [], string $types = "") {
    $pdo = getConnection();

    if (DB_DEBUG) {
        error_log("[DB QUERY] " . $sql);
        if (!empty($params)) {
            error_log("[DB PARAMS] " . json_encode($params));
        }
    }

    try {
        if (empty($params)) {
            return $pdo->query($sql);
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if (DB_DEBUG) {
            error_log("[DB SUCCESS] Query executed | row_count: " . $stmt->rowCount());
        }

        return $stmt;
    } catch (PDOException $e) {
        error_log("[DB ERROR] " . $e->getMessage() . " | Query: " . $sql);
        
        $msg = DB_DEBUG ? $e->getMessage() : "Terjadi kesalahan sistem.";
        throw new RuntimeException($msg);
    }
}

/**
 * Ambil satu baris dari hasil query.
 *
 * @param  string     $sql
 * @param  array      $params
 * @param  string     $types
 * @return array|null
 */
function dbFetchOne(string $sql, array $params = [], string $types = ""): ?array {
    $stmt = dbQuery($sql, $params, $types);
    if (!$stmt) return null;
    
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Ambil semua baris dari hasil query.
 *
 * @param  string $sql
 * @param  array  $params
 * @param  string $types
 * @return array
 */
function dbFetchAll(string $sql, array $params = [], string $types = ""): array {
    $stmt = dbQuery($sql, $params, $types);
    if (!$stmt) return [];
    
    return $stmt->fetchAll();
}

/**
 * Insert dan kembalikan ID baru.
 *
 * @return int
 */
function dbInsert(string $sql, array $params = [], string $types = ""): int {
    dbQuery($sql, $params, $types);
    $id = getConnection()->lastInsertId();
    
    if (DB_DEBUG) error_log("[DB INSERT] ID: " . $id);
    return (int) $id;
}

/**
 * Update/Delete dan kembalikan jumlah baris terpengaruh.
 *
 * @return int
 */
function dbUpdate(string $sql, array $params = [], string $types = ""): int {
    $stmt = dbQuery($sql, $params, $types);
    if ($stmt) {
        $affected = $stmt->rowCount();
        if (DB_DEBUG) error_log("[DB UPDATE] Affected rows: " . $affected);
        return (int) $affected;
    }
    return 0;
}

// ============================================
// 6. FUNGSI HELPER
// ============================================

/** Kembalikan pesan error terakhir (dummy for PDO as it uses exceptions) */
function dbError(): string {
    return "";
}

/** Kembalikan ID terakhir yang di-insert */
function dbLastId(): int {
    return (int) getConnection()->lastInsertId();
}

/**
 * Escape string untuk query.
 * Untuk PDO, gunakan quote().
 */
function dbEscape(string $string): string {
    return getConnection()->quote($string);
}

/** Mulai transaksi */
function dbBeginTransaction(): void {
    getConnection()->beginTransaction();
}

/** Commit transaksi */
function dbCommit(): void {
    getConnection()->commit();
}

/** Rollback transaksi */
function dbRollback(): void {
    getConnection()->rollback();
}