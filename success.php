<?php

declare(strict_types=1);

$params = $_GET;
header('Location: order-xendit-return.php?' . http_build_query($params));
exit;


