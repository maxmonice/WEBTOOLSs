<?php
// Test admin login directly
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Admin Login</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: #666; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .code { background: #f8f9fa; padding: 10px; border-radius: 5px; font-family: monospace; }
    </style>
</head>
<body>
    <h1>🔐 Test Admin Login</h1>
    
    <?php
    if ($_POST['test'] === 'yes') {
        try {
            require_once 'Db.php';
            $db = getDB();
            
            echo "<div class='info'><h3>📊 Checking Database:</h3>";
            
            // Check if admin user exists
            $stmt = $db->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ?");
            $stmt->execute(['admin@gmail.com']);
            $admin = $stmt->fetch();
            
            if ($admin) {
                echo "<div class='success'>✅ Admin user found</div>";
                echo "<p><strong>ID:</strong> " . $admin['id'] . "</p>";
                echo "<p><strong>Name:</strong> " . $admin['name'] . "</p>";
                echo "<p><strong>Email:</strong> " . $admin['email'] . "</p>";
                echo "<p><strong>Password Hash:</strong> <code>" . substr($admin['password_hash'], 0, 20) . "...</code></p>";
                
                // Test password verification
                $testPassword = 'admin123';
                if (password_verify($testPassword, $admin['password_hash'])) {
                    echo "<div class='success'>✅ Password 'admin123' verifies correctly!</div>";
                    
                    // Test actual login process
                    $stmt = $db->prepare("SELECT id, name, email, password_hash FROM users WHERE email = ? AND provider = 'email'");
                    $stmt->execute(['admin@gmail.com']);
                    $user = $stmt->fetch();
                    
                    if ($user && password_verify($testPassword, $user['password_hash'])) {
                        echo "<div class='success'>✅ Full login test PASSED!</div>";
                        echo "<p><strong>Ready to login:</strong> <a href='account.php'>Go to Login Page</a></p>";
                    } else {
                        echo "<div class='error'>❌ Full login test FAILED!</div>";
                    }
                } else {
                    echo "<div class='error'>❌ Password 'admin123' does NOT verify!</div>";
                    echo "<p>Let's recreate the admin user with correct password...</p>";
                    
                    // Recreate admin user
                    $hash = password_hash('admin123', PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE email = ?");
                    $stmt->execute([$hash, 'admin@gmail.com']);
                    
                    echo "<div class='success'>✅ Admin password reset to 'admin123'</div>";
                    echo "<p><strong>Try again:</strong> <a href='test-admin.php'>Test Again</a></p>";
                }
            } else {
                echo "<div class='error'>❌ Admin user NOT found!</div>";
                
                // Create admin user
                $hash = password_hash('admin123', PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (name, email, password_hash, provider, email_verified) VALUES (?, ?, ?, 'email', 1)");
                $stmt->execute(['Admin User', 'admin@gmail.com', $hash]);
                
                echo "<div class='success'>✅ Admin user created with password 'admin123'</div>";
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
        }
    } else {
    ?>
    
    <p>This will test the admin login credentials and fix any issues.</p>
    <form method="post">
        <input type="hidden" name="test" value="yes">
        <button type="submit">🔍 Test Admin Login</button>
    </form>
    
    <div class="info">
        <h3>Expected Credentials:</h3>
        <p><strong>Email:</strong> admin@gmail.com</p>
        <p><strong>Password:</strong> admin123</p>
    </div>
    
    <?php } ?>
</body>
</html>
