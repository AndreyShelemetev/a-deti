<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
putenv('AT_STANDALONE=1');

function console_password(bool $stdin): string
{
    $value = at_standalone_env('AT_ADMIN_PASSWORD');
    if ($value !== '') return $value;
    if ($stdin) {
        $value = stream_get_contents(STDIN, 1025);
        return rtrim((string)$value, "\r\n");
    }
    if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
        throw new RuntimeException('Задайте AT_ADMIN_PASSWORD_FILE, AT_ADMIN_PASSWORD или передайте --password-stdin.');
    }
    fwrite(STDERR, 'Пароль первого администратора: ');
    $hidden = false;
    if (DIRECTORY_SEPARATOR !== '\\' && function_exists('exec')) {
        exec('stty -echo 2>/dev/null', $unused, $code); $hidden = $code === 0;
    }
    if (!$hidden) throw new RuntimeException('Не удалось скрыть ввод. Используйте файл пароля или --password-stdin.');
    try { return rtrim((string)fgets(STDIN, 1025), "\r\n"); }
    finally { exec('stty echo 2>/dev/null'); fwrite(STDERR, "\n"); }
}

try {
    $command = $argv[1] ?? 'help';
    if ($command === 'help' || $command === '--help') {
        echo "А-тур: управление самостоятельной установкой\n"
            . "  status                         Готовность базы, без изменений\n"
            . "  migrate                        Только структура, без наполнения и правки содержимого\n"
            . "  init [--seed]                  Новая база; --seed заполняет только новую пустую установку\n"
            . "  admin:create --login=LOGIN     Только первый администратор; пароль из окружения/secret\n"
            . "               [--password-stdin] Пароль из стандартного ввода\n"
            . "  content:additions              Явно добавить материалы выпуска, вне migrate\n";
        exit(0);
    }
    if (!in_array($command, ['status','migrate','init','admin:create','content:additions'], true)) throw new RuntimeException('Неизвестная команда. Список: php bin/console.php help');
    $options = [];
    foreach (array_slice($argv, 2) as $argument) {
        if ($argument === '--seed' && $command === 'init') $options['seed'] = true;
        elseif ($argument === '--password-stdin' && $command === 'admin:create') $options['stdin'] = true;
        elseif (str_starts_with($argument, '--login=') && $command === 'admin:create') $options['login'] = substr($argument, 8);
        else throw new RuntimeException('Недопустимый параметр команды. Список: php bin/console.php help');
    }
    require_once dirname(__DIR__) . '/inc/standalone/bootstrap.php';
    require_once dirname(__DIR__) . '/inc/standalone/database.php';
    $pdo = db();
    $result = match ($command) {
        'status' => at_standalone_status($pdo),
        'migrate','init' => at_standalone_migrate($pdo, $command === 'init' && !empty($options['seed'])),
        'admin:create' => at_standalone_admin($pdo, (string)($options['login'] ?? at_standalone_env('AT_ADMIN_LOGIN', 'admin')), static fn(): string=>console_password(!empty($options['stdin']))),
        'content:additions' => at_standalone_lock($pdo, static function () use ($pdo): array {
            if (!at_standalone_status($pdo)['ok']) throw new RuntimeException('Сначала выполните migrate для текущего выпуска.');
            return ['ok'=>true, 'counts'=>at_standalone_content($pdo)];
        }),
    };
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
    exit(!empty($result['ok']) ? 0 : 1);
} catch (Throwable $error) {
    $message = $error instanceof PDOException
        ? 'Операция базы данных не выполнена (SQLSTATE ' . $error->getCode() . '). Проверьте доступ, совместимость дампа и состояние схемы.'
        : ($error instanceof JsonException ? 'Не читается JSON исходного наполнения или состояния установки.' : $error->getMessage());
    fwrite(STDERR, json_encode(['ok'=>false, 'error'=>$message], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
    exit(1);
}
