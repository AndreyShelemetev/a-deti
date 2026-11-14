<?php
declare(strict_types=1);

/** Значение из окружения либо Docker secret. Пароли не попадают в вывод команд. */
function at_standalone_env(string $name, string $default = ''): string
{
    $file = getenv($name . '_FILE');
    if (is_string($file) && $file !== '') {
        if (!is_readable($file) || !is_file($file) || filesize($file) > 65536) {
            throw new RuntimeException('Недоступен файл настройки ' . $name . '_FILE.');
        }
        $value = file_get_contents($file);
        if ($value === false) throw new RuntimeException('Не прочитан файл настройки ' . $name . '_FILE.');
        return rtrim($value, "\r\n");
    }
    $value = getenv($name);
    return is_string($value) ? $value : $default;
}

if (!function_exists('db')) {
    function db(): PDO
    {
        static $connection = null;
        if ($connection instanceof PDO) return $connection;
        $dsn = at_standalone_env('AT_DB_DSN');
        $user = at_standalone_env('AT_DB_USER');
        if (!str_starts_with($dsn, 'mysql:') || $user === '') {
            throw new RuntimeException('Задайте AT_DB_DSN (mysql:) и AT_DB_USER в окружении PHP.');
        }
        $connection = new PDO($dsn, $user, at_standalone_env('AT_DB_PASSWORD'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $connection->exec('SET NAMES utf8mb4');
        return $connection;
    }
}

// Сессии здесь не открываем: bootstrap нужен CLI и проверке готовности контейнера.
if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}
require_once dirname(__DIR__) . '/init.php';
