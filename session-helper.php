<?php
/**
 * session-helper.php
 * Unified session management.
 */
function startSiloSession(?string $side = null) {
    if (session_status() === PHP_SESSION_NONE) {
        // Use standard PHP session name to avoid redirect loops
        session_name('PHPSESSID');
        session_start();
    }
}
