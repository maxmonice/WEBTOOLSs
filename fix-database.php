<?php
// Fix database schema for authentication system
?>
<!DOCTYPE html>
<html>
<head>
    <title>Fix Database Schema</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .container { background: #f5f5f5; padding: 30px; border-radius: 10px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: #666; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .step { margin: 20px 0; padding: 15px; background: white; border-radius: 5px; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Fix Database Schema</h1>
        
        <?php
        if ($_POST['action'] === 'fix') {
            try {
                require_once 'Db.php';
                $pdo = new PDO('mysql:host=localhost', 'root', '');
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                // Create database if it doesn't exist
                $pdo->exec("CREATE DATABASE IF NOT EXISTS lukes_seafood CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE lukes_seafood");
                
                echo "<div class='step'><h3>📋 Fixing users table...</h3>";
                
                // Disable foreign key checks temporarily
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                // Drop existing table and recreate with correct schema
                $pdo->exec("DROP TABLE IF EXISTS users");
                
                $pdo->exec("
                    CREATE TABLE users (
                        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        name VARCHAR(120) NOT NULL,
                        email VARCHAR(180) NOT NULL UNIQUE,
                        password_hash VARCHAR(255) DEFAULT NULL,
                        provider ENUM('email','google','facebook') NOT NULL DEFAULT 'email',
                        provider_id VARCHAR(255) DEFAULT NULL,
                        avatar_url VARCHAR(500) DEFAULT NULL,
                        remember_token VARCHAR(64) DEFAULT NULL,
                        email_verified TINYINT(1) NOT NULL DEFAULT 0,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
                
                // Re-enable foreign key checks
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                echo "<div class='success'>✅ Users table recreated with correct schema</div>";
                
                // Create admin user
                $adminEmail = 'admin@gmail.com';
                $adminPassword = 'admin123';
                
                $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, provider, email_verified) VALUES (?, ?, ?, 'email', 1)");
                $stmt->execute(['Admin User', $adminEmail, $hash]);
                
                echo "<div class='success'>✅ Admin user created</div>";
                
                // Show table structure
                $stmt = $pdo->query("DESCRIBE users");
                echo "<div class='step'><h3>📊 Table Structure:</h3>";
                echo "<table border='1' cellpadding='5' cellspacing='0'>";
                echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
                while ($row = $stmt->fetch()) {
                    echo "<tr>";
                    echo "<td>" . $row['Field'] . "</td>";
                    echo "<td>" . $row['Type'] . "</td>";
                    echo "<td>" . $row['Null'] . "</td>";
                    echo "<td>" . $row['Key'] . "</td>";
                    echo "</tr>";
                }
                echo "</table></div>";
                
                echo "<div class='step'>";
                echo "<h3>🎉 Database Fixed!</h3>";
                echo "<p><strong>Test Credentials:</strong></p>";
                echo "<ul>";
                echo "<li>Email: admin@gmail.com</li>";
                echo "<li>Password: admin123</li>";
                echo "</ul>";
                echo "<p><a href='account.php' style='color: #007bff; text-decoration: none;'>👉 Test Login</a></p>";
                echo "</div>";
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
            }
        } else {
        ?>
        
        <div class="step">
            <h3>🔍 Problem Detected</h3>
            <p>The Google OAuth login is failing because the database table is missing the <code>provider_id</code> column.</p>
            <div class="code">Error: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'provider_id' in 'field list'</div>
        </div>
        
        <div class="step">
            <h3>🛠️ Solution</h3>
            <p>This will recreate the users table with the correct schema including all OAuth columns:</p>
            <ul>
                <li>provider_id (for Google/Facebook user IDs)</li>
                <li>avatar_url (for profile pictures)</li>
                <li>remember_token (for "remember me" functionality)</li>
                <li>email_verified (for email verification status)</li>
            </ul>
        </div>
        
        <form method="post">
            <input type="hidden" name="action" value="fix">
            <button type="submit">🔧 Fix Database Schema</button>
        </form>
        
        <?php } ?>
        
        <div class="step">
            <h3>📝 What This Does:</h3>
            <ol>
                <li>Drops the existing users table (will delete all current users)</li>
                <li>Creates a new users table with correct schema</li>
                <li>Creates the admin user (admin@gmail.com / admin123)</li>
                <li>Fixes Google/Facebook OAuth login</li>
            </ol>
            <p class="error"><strong>Warning:</strong> This will delete all existing users in the database!</p>
        </div>
    </div>
</body>
</html>
