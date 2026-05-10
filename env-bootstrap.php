<?php
/**
 * Load key=value pairs from project root `.env` when getenv() is unset.
 * Mirrors the pattern used in account.php so Xendit scripts work under Laragon.
 */
if (!function_exists('webtools_load_env')) {
    function webtools_load_env(string $projectRoot): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        $path = $projectRoot . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $eq = strpos($trimmed, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($trimmed, 0, $eq));
            $value = trim(substr($trimmed, $eq + 1));
            if ($key === '') {
                continue;
            }
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
            }
        }
    }
}

if (!function_exists('webtools_truthy_env')) {
    /**
     * True for "1", "true", "yes", "on" (case-insensitive).
     */
    function webtools_truthy_env(?string $value): bool {
        if ($value === null || $value === '') {
            return false;
        }

        return (bool)preg_match('/^(1|true|yes|on)$/i', trim($value));
    }
}

if (!function_exists('webtools_infer_base_url')) {
    /**
     * Build http(s)://host[/projectPath] so demo mode works without SITE_BASE_URL in .env.
     */
    function webtools_infer_base_url(): string {
        $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strcasecmp((string)$_SERVER['HTTPS'], 'off') !== 0;
        $proto = $secure ? 'https' : 'http';

        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            return '';
        }
        $scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = dirname($scriptName === '' ? '/' : $scriptName);
        if ($dir === '/' || $dir === '.' || $dir === '') {
            return $proto . '://' . $host;
        }

        return rtrim($proto . '://' . $host . $dir, '/');
    }
}
