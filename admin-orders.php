<?php
/**
 * Orders UI lives in adminSide/. Merged root-level admin pages still link to admin-orders.php.
 */
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== ''
    ? '?' . $_SERVER['QUERY_STRING']
    : '';
header('Location: adminSide/admin-orders.php' . $qs, true, 302);
exit;


