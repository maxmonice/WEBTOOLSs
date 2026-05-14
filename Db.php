<?php
// =====================================================
//  db.php — Database connection
//  Update these credentials to match your server
// =====================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'lukes_seafood');   // your database name
define('DB_USER', 'root');            // your MySQL username
define('DB_PASS', '');                // your MySQL password
define('DB_CHARSET', 'utf8mb4');
define('DB_SOCKET', '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $socket = is_readable(DB_SOCKET) ? DB_SOCKET : null;
        $dsn = $socket
            ? "mysql:unix_socket=" . $socket . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET
            : "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            try {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'status'"
                );
                $stmt->execute();
                if ((int)$stmt->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN status ENUM('active','suspended') NOT NULL DEFAULT 'active' AFTER role");
                }
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_archived'"
                );
                $stmt->execute();
                if ((int)$stmt->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER status");
                }
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'archived_at'"
                );
                $stmt->execute();
                if ((int)$stmt->fetchColumn() === 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN archived_at TIMESTAMP NULL DEFAULT NULL AFTER is_archived");
                }
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings'"
                );
                $stmt->execute();
                if ((int)$stmt->fetchColumn() > 0) {
                    $stmt = $pdo->prepare(
                        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bookings' AND COLUMN_NAME = 'event_time_end'"
                    );
                    $stmt->execute();
                    if ((int)$stmt->fetchColumn() === 0) {
                        $pdo->exec(
                            'ALTER TABLE bookings ADD COLUMN event_time_end TIME NULL DEFAULT NULL AFTER event_time'
                        );
                    }
                }
            } catch (Throwable $_) {}
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database connection failed.']);
            exit;
        }
    }
    return $pdo;
}


