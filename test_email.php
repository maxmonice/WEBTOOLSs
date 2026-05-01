<?php
/**
 * Email Test - Check if mail() function is working
 */

$test_result = '';
$test_email = '';
$test_sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $test_email = trim($_POST['test_email'] ?? '');
    
    if (empty($test_email)) {
        $test_result = 'Please enter an email address.';
    } elseif (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $test_result = 'Please enter a valid email address.';
    } else {
        $subject = 'XAMPP Test Email - Luke\'s Seafood';
        $body = "This is a test email from your XAMPP server.\n\n";
        $body .= "If you received this, mail() function is working!\n\n";
        $body .= "Test sent at: " . date('Y-m-d H:i:s') . "\n";
        $body .= "Server: " . $_SERVER['SERVER_NAME'] . "\n";
        
        $headers = "From: test@localhost\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        echo "<pre style='background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
        echo "=== SENDING TEST EMAIL ===\n";
        echo "To: " . htmlspecialchars($test_email) . "\n";
        echo "Subject: " . htmlspecialchars($subject) . "\n";
        echo "Headers: " . htmlspecialchars($headers) . "\n";
        echo "Body length: " . strlen($body) . " bytes\n\n";
        
        // Enable error reporting for this test
        ob_start();
        $result = @mail($test_email, $subject, $body, $headers);
        $output = ob_get_clean();
        
        echo "mail() returned: " . ($result ? "TRUE (email queued)" : "FALSE (failed)") . "\n";
        if ($output) {
            echo "Output: " . htmlspecialchars($output) . "\n";
        }
        echo "=== END TEST ===\n";
        echo "</pre>";
        
        $test_sent = true;
        if ($result) {
            $test_result = "✓ Test email sent to " . htmlspecialchars($test_email) . ". Check your inbox (may take a few seconds or go to spam).";
        } else {
            $test_result = "✗ mail() function failed. Check PHP configuration.";
        }
    }
}

// Check PHP mail configuration
$mail_config = [
    'sendmail_from' => ini_get('sendmail_from'),
    'sendmail_path' => ini_get('sendmail_path'),
    'SMTP' => ini_get('SMTP'),
    'smtp_port' => ini_get('smtp_port'),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Test - Luke's Seafood Trading</title>
    <style>
        body {
            font-family: 'Be Vietnam Pro', sans-serif;
            background: #191919;
            color: white;
            padding: 40px 20px;
            max-width: 600px;
            margin: 0 auto;
        }
        .container { background: #222222; padding: 30px; border-radius: 10px; }
        h1 { color: #C22626; margin-top: 0; }
        .form-group { margin: 20px 0; }
        label { display: block; margin-bottom: 8px; font-weight: 600; }
        input { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #444; 
            border-radius: 5px; 
            background: rgba(255,255,255,0.1); 
            color: white;
            font-family: inherit;
            box-sizing: border-box;
        }
        button {
            background: #C22626;
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 10px;
        }
        button:hover { background: #9B0A1E; }
        .success { color: #86efac; background: rgba(34,197,94,0.15); padding: 15px; border-radius: 5px; margin: 20px 0; }
        .error { color: #ffaaaa; background: rgba(194,38,38,0.18); padding: 15px; border-radius: 5px; margin: 20px 0; }
        .config {
            background: rgba(255,255,255,0.05);
            padding: 15px;
            border-radius: 5px;
            margin-top: 30px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
        }
        .config-item { margin: 8px 0; }
        .config-key { color: #ff8080; font-weight: 600; }
        .config-value { color: #86efac; }
        .back-link { margin-top: 20px; }
        .back-link a { color: #ff8080; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email Test Tool</h1>
        
        <p>Use this to test if your server's mail() function is working properly.</p>

        <?php if ($test_result && !$test_sent): ?>
            <div class="error"><?php echo htmlspecialchars($test_result); ?></div>
        <?php endif; ?>

        <?php if ($test_result && $test_sent): ?>
            <div class="<?php echo strpos($test_result, '✓') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($test_result); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="test_email">Test Email Address:</label>
                <input 
                    type="email" 
                    id="test_email" 
                    name="test_email" 
                    placeholder="your@email.com" 
                    value="<?php echo htmlspecialchars($test_email); ?>"
                    required
                >
                <p style="font-size: 12px; color: rgba(255,255,255,0.6); margin: 8px 0 0 0;">
                    Enter the email where you want to receive the test message
                </p>
            </div>

            <button type="submit">Send Test Email</button>
        </form>

        <div class="config">
            <strong style="color: #ff8080;">PHP Mail Configuration:</strong>
            <?php foreach ($mail_config as $key => $value): ?>
                <div class="config-item">
                    <span class="config-key"><?php echo htmlspecialchars($key); ?>:</span>
                    <span class="config-value">
                        <?php 
                        echo htmlspecialchars($value ?: '(not set)'); 
                        ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="back-link">
            <a href="account.php">← Back to Account</a> | 
            <a href="forgotpassword.php">← Back to Forgot Password</a>
        </div>
    </div>
</body>
</html>
