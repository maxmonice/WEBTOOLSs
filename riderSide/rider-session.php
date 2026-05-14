<?php
function startRiderSession(): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $sessionDir = __DIR__ . '/sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0775, true);
    }
    if (is_dir($sessionDir) && is_writable($sessionDir)) {
        session_save_path($sessionDir);
    }

    session_start();
}



