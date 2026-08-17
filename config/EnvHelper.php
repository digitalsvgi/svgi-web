<?php
// config/EnvHelper.php

class EnvHelper {
    /**
     * Loads variables from the specified .env file into PHP environments.
     */
    public static function load($path) {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }

            // Parse key and value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Strip surrounding quotes if present
                if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                    $value = $matches[1];
                }

                // Inject into environments if not already set on the host server
                if (getenv($key) === false) {
                    if (!array_key_exists($key, $_ENV)) {
                        $_ENV[$key] = $value;
                    }
                    if (!array_key_exists($key, $_SERVER)) {
                        $_SERVER[$key] = $value;
                    }
                    putenv("{$key}={$value}");
                }
            }
        }
    }
}
