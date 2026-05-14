<?php
/**
 * Database Setup Script
 * Run this once to initialize the database and create all necessary tables
 */

try {
    $socket = is_readable('/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock') 
        ? '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock' 
        : null;
    
    $dsn = $socket
        ? "mysql:unix_socket=" . $socket . ";charset=utf8mb4"
        : "mysql:host=localhost;charset=utf8mb4";
    
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✓ Connected to MySQL\n";

    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS lukes_seafood CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database created\n";

    // Select database
    $pdo->exec("USE lukes_seafood");
    echo "✓ Database selected\n";

    // Create users table
    $createUsersSQL = "
    CREATE TABLE IF NOT EXISTS users (
        id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name            VARCHAR(120)        NOT NULL,
        email           VARCHAR(180)        NOT NULL UNIQUE,
        password_hash   VARCHAR(255)        DEFAULT NULL,
        provider        ENUM('email','google','facebook') NOT NULL DEFAULT 'email',
        role            ENUM('customer','staff','admin','rider') NOT NULL DEFAULT 'customer',
        provider_id     VARCHAR(255)        DEFAULT NULL,
        email_verified  TINYINT(1)          NOT NULL DEFAULT 0,
        otp_code        VARCHAR(6)          DEFAULT NULL,
        otp_expires_at  DATETIME            DEFAULT NULL,
        otp_attempts    TINYINT(4)          NOT NULL DEFAULT 0,
        avatar_url      VARCHAR(500)        DEFAULT NULL,
        remember_token  VARCHAR(64)         DEFAULT NULL,
        created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        reset_token     VARCHAR(64)         DEFAULT NULL,
        token_expiry    DATETIME            DEFAULT NULL,
        status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
        is_archived     TINYINT(1)          NOT NULL DEFAULT 0,
        archived_at     TIMESTAMP           NULL DEFAULT NULL,
        KEY idx_email (email),
        KEY idx_provider (provider, provider_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $pdo->exec($createUsersSQL);
    echo "✓ Users table created\n";

    // Insert admin user if not exists
    $adminHash = '$2y$12$KBNsBLoOobrK.T8zx9KeNehmWHB4Suij0IhHQ7hX/4hDMswZDl5xu';
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password_hash, provider, role, email_verified) 
        VALUES (?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE id=id
    ");
    $stmt->execute(['Administrator', 'admin@gmail.com', $adminHash, 'email', 'admin']);
    echo "✓ Admin user created (admin@gmail.com / password123)\n";

    echo "\n✅ Database setup completed successfully!\n";
    echo "\nYou can now log in with:\n";
    echo "  Email: admin@gmail.com\n";
    echo "  Password: password123\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>


