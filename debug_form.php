<?php
// Debug form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo '<pre>';
    echo 'POST data received:' . PHP_EOL;
    print_r($_POST);
    echo PHP_EOL;
    echo 'Field checks:' . PHP_EOL;
    echo 'Name: ' . (isset($_POST['name']) ? 'SET' : 'NOT SET') . PHP_EOL;
    echo 'Email: ' . (isset($_POST['email']) ? 'SET' : 'NOT SET') . PHP_EOL;
    echo 'Password: ' . (isset($_POST['password']) ? 'SET' : 'NOT SET') . PHP_EOL;
    echo 'Confirm: ' . (isset($_POST['confirmPassword']) ? 'SET' : 'NOT SET') . PHP_EOL;
    echo 'Role: ' . (isset($_POST['role']) ? 'SET' : 'NOT SET') . PHP_EOL;
    echo '</pre>';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Form Debug</title>
</head>
<body>
    <h1>Form Debug Test</h1>
    <form method="POST">
        <input type="text" name="name" placeholder="Name" required/><br><br>
        <input type="email" name="email" placeholder="Email" required/><br><br>
        <input type="password" name="password" placeholder="Password" required/><br><br>
        <input type="password" name="confirmPassword" placeholder="Confirm Password" required/><br><br>
        <select name="role" required>
            <option value="customer">Customer</option>
            <option value="staff">Staff</option>
            <option value="admin">Admin</option>
        </select><br><br>
        <button type="submit">Submit</button>
    </form>
</body>
</html>


