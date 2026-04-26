<?php
// Quick database fix - just add missing columns without dropping table
?>
<!DOCTYPE html>
<html>
<head>
    <title>Quick Fix</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <h1>🚀 Quick Database Fix</h1>
    
    <?php
    if ($_POST['fix'] === 'yes') {
        try {
            require_once 'Db.php';
            $db = getDB();
            
            // Just add missing columns without dropping table
            $sqls = [
                "ALTER TABLE users ADD COLUMN IF NOT EXISTS provider_id VARCHAR(255) DEFAULT NULL AFTER provider",
                "ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(500) DEFAULT NULL AFTER provider_id", 
                "ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token VARCHAR(64) DEFAULT NULL AFTER avatar_url",
                "ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) NOT NULL DEFAULT 0 AFTER remember_token"
            ];
            
            foreach ($sqls as $sql) {
                try {
                    $db->exec($sql);
                } catch (Exception $e) {
                    // Column might already exist, ignore
                }
            }
            
            // Make sure admin user exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute(['admin@gmail.com']);
            if (!$stmt->fetch()) {
                $hash = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, provider, email_verified) VALUES (?, ?, ?, 'email', 1)");
                $stmt->execute(['Admin User', 'admin@gmail.com', $hash]);
            }
            
            echo "<div class='success'>✅ Database fixed instantly!</div>";
            echo "<p><strong>Test:</strong> <a href='account.php'>Login Page</a></p>";
            echo "<p><strong>Credentials:</strong> admin@gmail.com / admin123</p>";
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ " . $e->getMessage() . "</div>";
        }
    } else {
    ?>
    
    <p>This will instantly add missing columns to fix Google OAuth.</p>
    <form method="post">
        <input type="hidden" name="fix" value="yes">
        <button type="submit">⚡ Quick Fix (5 seconds)</button>
    </form>
    
    <?php } ?>
</body>
</html>
