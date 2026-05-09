<?php
$content = file_get_contents('account-dashboard.php');

$content = preg_replace('/<style>.*?<\/style>/s', '<link rel="stylesheet" href="account-dashboard.css">', $content, 1);
$content = preg_replace('/<script>\s*\/\/\s*── Session guard ──.*?<\/script>/s', '<script src="account-dashboard.js?v=<?= time() ?>"></script>', $content, 1);

file_put_contents('account-dashboard.php', $content);
echo "Replaced inline styles and scripts with external links.\n";
?>
