<?php
declare(strict_types=1);

const AT_STANDALONE_INIT_KEY = 'standalone.initialized';

function at_standalone_tables(PDO $pdo): array
{
    return $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
}

function at_standalone_schema_version(PDO $pdo, array $tables): int
{
    if (!in_array('atour_settings', $tables, true)) return 0;
    $value = $pdo->query("SELECT v FROM atour_settings WHERE k = 'schema_version'")->fetchColumn();
    if ($value === false) return 0;
    if (!ctype_digit((string)$value)) throw new RuntimeException('Некорректная версия схемы базы данных.');
    return (int)$value;
}

function at_standalone_lock(PDO $pdo, callable $operation): mixed
{
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '') throw new RuntimeException('В AT_DB_DSN не выбрана база данных.');
    $key = 'atour:setup:' . substr(hash('sha256', $database), 0, 40);
    $query = $pdo->prepare('SELECT GET_LOCK(?, 30)');
    $query->execute([$key]);
    if ((int)$query->fetchColumn() !== 1) throw new RuntimeException('Другая команда уже изменяет базу. Повторите позже.');
    try { return $operation(); }
    finally { $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$key]); }
}

function at_standalone_state(PDO $pdo): ?array
{
    $query = $pdo->prepare('SELECT v FROM atour_settings WHERE k = ?');
    $query->execute([AT_STANDALONE_INIT_KEY]);
    $value = $query->fetchColumn();
    if ($value === false) return null;
    $state = json_decode((string)$value, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($state) || !in_array($state['origin'] ?? '', ['new', 'existing'], true)
        || !in_array($state['seed'] ?? '', ['pending', 'base_seeded', 'done', 'skipped'], true)) {
        throw new RuntimeException('Некорректная отметка инициализации. Данные не изменены.');
    }
    return $state;
}

function at_standalone_save_state(PDO $pdo, array $state): void
{
    $pdo->prepare('INSERT INTO atour_settings (k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)')
        ->execute([AT_STANDALONE_INIT_KEY, json_encode($state, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
}

/** Только структура. Старые миграции содержимого намеренно выключены. */
function at_standalone_migrate(PDO $pdo, bool $seed = false): array
{
    return at_standalone_lock($pdo, static function () use ($pdo, $seed): array {
        $tables = at_standalone_tables($pdo);
        $version = at_standalone_schema_version($pdo, $tables);
        if ($version > ATOUR_SCHEMA_VERSION) throw new RuntimeException('База новее этого выпуска. Установите соответствующую версию кода; понижение схемы запрещено.');
        if ($tables !== [] && !in_array('tours', $tables, true) && !in_array('atour_settings', $tables, true)) {
            throw new RuntimeException('В базе уже есть посторонние таблицы. Выберите отдельную базу сайта.');
        }
        $state = in_array('atour_settings', $tables, true) ? at_standalone_state($pdo) : null;
        if ($state === null) {
            $state = ['origin'=>$tables === [] ? 'new' : 'existing', 'seed'=>$tables === [] && $seed ? 'pending' : 'skipped', 'at'=>gmdate(DATE_ATOM)];
            // Сохраняем происхождение до остальных DDL: прерванную свежую установку можно продолжить.
            $pdo->exec('CREATE TABLE IF NOT EXISTS atour_settings (
                k VARCHAR(64) NOT NULL PRIMARY KEY, v MEDIUMTEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
            at_standalone_save_state($pdo, $state);
        }
        require_once dirname(__DIR__) . '/schema.php';
        at_schema_install($pdo);
        if ($version < ATOUR_SCHEMA_VERSION) {
            // База без номера, но с таблицей tours — прежняя схема v1, а не пустая установка.
            at_schema_migrate($pdo, $version === 0 && in_array('tours', $tables, true) ? 1 : $version, false);
            $pdo->prepare("INSERT INTO atour_settings (k,v) VALUES ('schema_version',?) ON DUPLICATE KEY UPDATE v=VALUES(v)")
                ->execute([(string)ATOUR_SCHEMA_VERSION]);
        }
        $counts = [];
        if ($seed && $state['origin'] === 'new' && $state['seed'] === 'pending') {
            // Даже при сохранённом pending восстановленный/частично вручную заполненный сайт не пересидируем.
            foreach (at_standalone_tables($pdo) as $table) {
                $quoted = '`' . str_replace('`', '``', (string)$table) . '`';
                $sql = $table === 'atour_settings'
                    ? "SELECT 1 FROM atour_settings WHERE k NOT IN ('schema_version','" . AT_STANDALONE_INIT_KEY . "') LIMIT 1"
                    : 'SELECT 1 FROM ' . $quoted . ' LIMIT 1';
                if ($pdo->query($sql)->fetchColumn() !== false) throw new RuntimeException('База уже содержит данные. Исходное наполнение не выполнено; используйте migrate.');
            }
            $file = ATOUR_ROOT . '/data/seed.json';
            $contents = file_get_contents($file);
            if ($contents === false) throw new RuntimeException('Не найден data/seed.json.');
            $initial = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($initial) || !is_array($initial['tours'] ?? null)) throw new RuntimeException('Некорректное исходное наполнение.');
            require_once dirname(__DIR__) . '/seed.php';
            $pdo->beginTransaction();
            try {
                $counts = at_seed_apply($pdo, $initial);
                $state['seed'] = 'base_seeded';
                at_standalone_save_state($pdo, $state);
                $pdo->commit();
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        }
        if ($seed && $state['origin'] === 'new' && $state['seed'] === 'base_seeded') {
            at_standalone_content($pdo);
            $state['seed'] = 'done';
            at_standalone_save_state($pdo, $state);
        }
        return ['ok'=>true, 'schema_version'=>ATOUR_SCHEMA_VERSION, 'initialization'=>$state, 'seed_counts'=>$counts];
    });
}

/** Явная установка дополнений; при обычной миграции восстановленной базы не вызывается. */
function at_standalone_content(PDO $pdo): array
{
    require_once dirname(__DIR__) . '/camp.php';
    at_camp_import_layout($pdo, ATOUR_ROOT . '/data/camp-layouts/art-kvest.json');
    require_once dirname(__DIR__) . '/catalog-additions.php';
    return at_catalog_additions($pdo, dirname(__DIR__) . '/content/winter-2027.json');
}

function at_standalone_status(PDO $pdo): array
{
    $tables = at_standalone_tables($pdo);
    $version = at_standalone_schema_version($pdo, $tables);
    $state = in_array('atour_settings', $tables, true) ? at_standalone_state($pdo) : null;
    return ['ok'=>$version === ATOUR_SCHEMA_VERSION && in_array('tours', $tables, true)
            && !in_array($state['seed'] ?? '', ['pending', 'base_seeded'], true), 'database'=>'up',
        'schema_version'=>$version, 'target_schema_version'=>ATOUR_SCHEMA_VERSION,
        'initialization'=>$state,
        'tours'=>in_array('tours', $tables, true) ? (int)$pdo->query('SELECT COUNT(*) FROM tours')->fetchColumn() : 0,
        'admin_exists'=>in_array('admin_users', $tables, true) && (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0];
}

/** Создаётся только первый администратор. Повторный deploy не меняет доступы восстановленного сайта. */
function at_standalone_admin(PDO $pdo, string $login, callable $password): array
{
    return at_standalone_lock($pdo, static function () use ($pdo, $login, $password): array {
        $tables = at_standalone_tables($pdo);
        if (at_standalone_schema_version($pdo, $tables) !== ATOUR_SCHEMA_VERSION) throw new RuntimeException('Сначала выполните init или migrate для текущего выпуска.');
        if (in_array('admin_users', $tables, true) && (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0) {
            return ['ok'=>true, 'admin'=>'existing', 'changed'=>false];
        }
        if (!preg_match('~^[A-Za-z0-9][A-Za-z0-9_.@+\-]{2,63}$~D', $login)) throw new RuntimeException('Логин: от 3 до 64 латинских букв, цифр и символов _.@+-.');
        $secret = $password();
        if (!is_string($secret) || !mb_check_encoding($secret, 'UTF-8') || mb_strlen($secret) < 12 || strlen($secret) > 72 || strpbrk($secret, "\0\r\n") !== false) {
            throw new RuntimeException('Пароль должен содержать от 12 символов до 72 байт, без переводов строки и нулевых байтов.');
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY, login VARCHAR(64) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL, must_change TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $pdo->prepare('INSERT INTO admin_users (login,password_hash,must_change) VALUES (?,?,0)')
            ->execute([$login, password_hash($secret, PASSWORD_DEFAULT)]);
        return ['ok'=>true, 'admin'=>'created', 'changed'=>true];
    });
}
