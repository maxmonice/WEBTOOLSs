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
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()]);
            exit;
        }
    }
    return $pdo;
}


