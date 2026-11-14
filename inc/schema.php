<?php
/**
 * Схема базы сайта А-тур. Вызывается один раз из inc/init.php (at_schema_ensure).
 *
 * Без FOREIGN KEY: на платформе внешние ключи регулярно роняют создание
 * таблицы, связи держим логически (колонки *_id + индексы). Родительские
 * таблицы — раньше дочерних. Всё InnoDB/utf8mb4.
 */
declare(strict_types=1);

function at_schema_install(PDO $pdo): void
{
    $tables = [
        "CREATE TABLE IF NOT EXISTS atour_settings (
            k VARCHAR(64) NOT NULL PRIMARY KEY,
            v MEDIUMTEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS regions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(120) NOT NULL UNIQUE,
            name VARCHAR(190) NOT NULL,
            is_foreign TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS tags (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(120) NOT NULL UNIQUE,
            label VARCHAR(190) NOT NULL,
            h1 VARCHAR(300) NULL,
            seo_title VARCHAR(300) NULL,
            meta_description VARCHAR(500) NULL,
            sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS tours (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(190) NOT NULL UNIQUE,
            legacy_id INT NULL,
            title VARCHAR(300) NOT NULL,
            seo_title VARCHAR(300) NULL,
            meta_description VARCHAR(500) NULL,
            meta_keywords VARCHAR(500) NULL,
            short TEXT,
            address VARCHAR(300) NOT NULL DEFAULT '',
            age_text VARCHAR(120) NOT NULL DEFAULT '',
            age_min INT NULL,
            age_max INT NULL,
            website VARCHAR(300) NULL,
            price_text VARCHAR(120) NULL,
            price_min INT NULL,
            price_note VARCHAR(300) NULL,
            archived_at DATETIME NULL,
            internal_note TEXT NULL,
            cover VARCHAR(500) NULL,
            banner VARCHAR(500) NULL,
            type VARCHAR(16) NOT NULL DEFAULT 'CAMP',
            seasons VARCHAR(64) NOT NULL DEFAULT '',
            season_text VARCHAR(120) NULL,
            is_best TINYINT(1) NOT NULL DEFAULT 0,
            is_hit TINYINT(1) NOT NULL DEFAULT 0,
            is_published TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            search_text MEDIUMTEXT,
            region_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_tours_pub (is_published, sort_order),
            INDEX idx_tours_type (type),
            INDEX idx_tours_region (region_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS tour_shifts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tour_id INT NOT NULL,
            group_name VARCHAR(200) NULL,
            name VARCHAR(300) NOT NULL,
            start_date DATE NULL,
            end_date DATE NULL,
            price INT NULL,
            price_text VARCHAR(120) NULL,
            availability VARCHAR(12) NOT NULL DEFAULT 'AVAILABLE',
            seats_left INT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            INDEX idx_shifts_tour (tour_id),
            INDEX idx_shifts_start (start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Что мы в прошлый раз отдали платформе как title/h1/description страницы
        // (см. at_seo_sync): по этому решаем, менять ли её запись в seo_meta.
        "CREATE TABLE IF NOT EXISTS atour_seo_sync (
            page_key VARCHAR(191) NOT NULL PRIMARY KEY,
            title VARCHAR(255) NOT NULL DEFAULT '',
            h1 VARCHAR(255) NOT NULL DEFAULT '',
            description VARCHAR(500) NOT NULL DEFAULT '',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS tour_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tour_id INT NOT NULL,
            url VARCHAR(500) NOT NULL,
            alt VARCHAR(300) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            INDEX idx_images_tour (tour_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS tour_sections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tour_id INT NOT NULL,
            skey VARCHAR(40) NOT NULL,
            title VARCHAR(190) NOT NULL,
            html MEDIUMTEXT,
            sort_order INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_section (tour_id, skey)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS tour_tags (
            tour_id INT NOT NULL,
            tag_id INT NOT NULL,
            PRIMARY KEY (tour_id, tag_id),
            INDEX idx_tour_tags_tag (tag_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS tour_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tour_id INT NOT NULL,
            label VARCHAR(300) NOT NULL,
            url VARCHAR(500) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            INDEX idx_docs_tour (tour_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS catalog_pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            path VARCHAR(200) NOT NULL UNIQUE,
            h1 VARCHAR(300) NOT NULL,
            seo_title VARCHAR(300) NULL,
            meta_description VARCHAR(500) NULL,
            seo_text MEDIUMTEXT NULL,
            type VARCHAR(16) NULL,
            region_slugs VARCHAR(500) NOT NULL DEFAULT '',
            tag_slugs VARCHAR(500) NOT NULL DEFAULT '',
            season VARCHAR(16) NULL,
            is_indexable TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(10) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            title VARCHAR(300) NOT NULL,
            seo_title VARCHAR(300) NULL,
            meta_description VARCHAR(500) NULL,
            excerpt TEXT NULL,
            image VARCHAR(500) NULL,
            html MEDIUMTEXT,
            published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_published TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_post (kind, slug),
            INDEX idx_posts_list (kind, is_published, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS pages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(120) NOT NULL UNIQUE,
            title VARCHAR(300) NOT NULL,
            seo_title VARCHAR(300) NULL,
            meta_description VARCHAR(500) NULL,
            meta_keywords VARCHAR(500) NULL,
            html MEDIUMTEXT,
            is_published TINYINT(1) NOT NULL DEFAULT 1,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS partners (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            logo VARCHAR(500) NOT NULL DEFAULT '',
            url VARCHAR(500) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS slides (
            id INT AUTO_INCREMENT PRIMARY KEY,
            placement VARCHAR(8) NOT NULL DEFAULT 'HERO',
            image VARCHAR(500) NOT NULL DEFAULT '',
            href VARCHAR(500) NULL,
            title VARCHAR(300) NULL,
            subtitle VARCHAR(300) NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS features (
            id INT AUTO_INCREMENT PRIMARY KEY,
            icon VARCHAR(500) NULL,
            body VARCHAR(500) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS review_platforms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            logo VARCHAR(500) NULL,
            url VARCHAR(500) NOT NULL DEFAULT '',
            rating DECIMAL(3,1) NOT NULL DEFAULT 5.0,
            max_rating INT NOT NULL DEFAULT 5,
            sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            author VARCHAR(200) NOT NULL,
            body TEXT,
            rating INT NOT NULL DEFAULT 5,
            source VARCHAR(10) NOT NULL DEFAULT 'SITE',
            source_url VARCHAR(500) NULL,
            published_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_published TINYINT(1) NOT NULL DEFAULT 1,
            tour_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_reviews_list (is_published, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kind VARCHAR(12) NOT NULL DEFAULT 'REQUEST',
            status VARCHAR(12) NOT NULL DEFAULT 'NEW',
            name VARCHAR(200) NULL,
            email VARCHAR(200) NULL,
            phone VARCHAR(64) NULL,
            message TEXT NULL,
            tour_id INT NULL,
            tour_title VARCHAR(300) NULL,
            shift_name VARCHAR(300) NULL,
            amount INT NULL,
            page_url VARCHAR(500) NULL,
            payload TEXT NULL,
            note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_requests_status (status, created_at),
            INDEX idx_requests_kind (kind)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) NOT NULL,
            full_name VARCHAR(200) NOT NULL,
            phone VARCHAR(64) NULL,
            email VARCHAR(200) NOT NULL,
            amount INT NOT NULL,
            is_mir_card TINYINT(1) NOT NULL DEFAULT 0,
            status VARCHAR(12) NOT NULL DEFAULT 'NEW',
            gateway_order_id VARCHAR(100) NULL,
            gateway_url VARCHAR(500) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_payments_status (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS subscribers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(200) NOT NULL UNIQUE,
            name VARCHAR(200) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS redirects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_path VARCHAR(300) NOT NULL UNIQUE,
            to_path VARCHAR(500) NOT NULL,
            status_code INT NOT NULL DEFAULT 301
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // Файлы, загруженные через админку (картинки): сайт не может писать на
        // диск, поэтому они живут в базе и отдаются через /media.php?id=N.
        "CREATE TABLE IF NOT EXISTS media (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mime VARCHAR(64) NOT NULL,
            name VARCHAR(200) NOT NULL DEFAULT '',
            size INT NOT NULL DEFAULT 0,
            data LONGBLOB NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }
}

/**
 * Миграции схемы при обновлении сайта на платформе (данные остаются).
 *
 * Новая версия кода приезжает поверх работающего сайта, и таблицы у него уже
 * есть — CREATE TABLE IF NOT EXISTS их не меняет. Поэтому изменение колонок
 * делается так: поднять ATOUR_SCHEMA_VERSION в inc/init.php и дописать сюда
 * шаг для новой версии. $from — версия, с которой обновляемся (0 — сайт
 * только что развёрнут, таблицы созданы at_schema_install и шаги не нужны).
 *
 *   if ($from > 0 && $from < 2) {
 *       $pdo->exec('ALTER TABLE tours ADD COLUMN video VARCHAR(255) NULL AFTER image');
 *   }
 */
function at_schema_migrate(PDO $pdo, int $from): void
{
    if ($from <= 0) {
        return;   // свежая установка: таблицы созданы сразу в полном виде
    }
    if ($from < 9) {
        // Карта всех исходных фотографий превышает лимит TEXT в 64 КБ.
        // Расширяем только ёмкость поля, сохраняя значения и допустимый NULL.
        $pdo->exec('ALTER TABLE atour_settings MODIFY COLUMN v MEDIUMTEXT NULL');
    }
    if ($from < 8) {
        require_once __DIR__ . '/camp.php';
        at_camp_import_layout($pdo, ATOUR_ROOT . '/data/camp-layouts/art-kvest.json');
    }
    if ($from < 7) {
        $setting = $pdo->prepare('REPLACE INTO atour_settings (k, v) VALUES (?, ?)');
        $setting->execute(['contacts.reestrUrl', 'https://ev.economy.gov.ru/lk_exp/registry/to/f080bc6f-3715-418d-b48b-909090f7b331']);
        $setting->execute(['home.ticketText', 'Организуем централизованную доставку детей в «Орлёнок», «Артек», лагеря Анапы и Крыма: билеты, сопровождение, питание в дороге.']);
        $setting->execute(['site.footerLogo', '/assets/design/logo.svg']);
    }
    if ($from < 6) {
        at_refresh_yandex_profile($pdo);
    }
    // Сначала колонки по версиям, потом содержимое: сид опирается на полную схему.
    if ($from < 2 && !$pdo->query("SHOW COLUMNS FROM tours LIKE 'banner'")->fetch()) {
        // v2: широкий баннер у тура (страница-лендинг, промо на главной).
        $pdo->exec('ALTER TABLE tours ADD COLUMN banner VARCHAR(500) NULL AFTER cover');
    }
    // v4: таблица atour_seo_sync — создаётся в at_schema_install (CREATE IF NOT EXISTS), отдельного шага нет.
    if ($from < 3 && !$pdo->query("SHOW COLUMNS FROM tours LIKE 'price_note'")->fetch()) {
        // v3: примечание к цене под блоком «Смены и цены» («авиаперелёт оплачивается отдельно»).
        $pdo->exec('ALTER TABLE tours ADD COLUMN price_note VARCHAR(300) NULL AFTER price_min');
    }
    if ($from < 5) {
        // v5: CRM туров — архив и заметка менеджера у тура, наличие мест у смены.
        foreach (['tours' => ['archived_at' => 'DATETIME NULL AFTER price_note', 'internal_note' => 'TEXT NULL AFTER archived_at'],
                  'tour_shifts' => ['availability' => "VARCHAR(12) NOT NULL DEFAULT 'AVAILABLE' AFTER price_text", 'seats_left' => 'INT NULL AFTER availability']] as $table => $cols) {
            foreach ($cols as $col => $def) {
                if (!$pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$col}'")->fetch()) {
                    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$def}");
                }
            }
        }
        if (!$pdo->query("SHOW INDEX FROM tour_shifts WHERE Key_name = 'idx_shifts_start'")->fetch()) {
            $pdo->exec('ALTER TABLE tour_shifts ADD INDEX idx_shifts_start (start_date)');
        }
    }
    require_once __DIR__ . '/seed.php';
    $seed = json_decode((string) @file_get_contents(ATOUR_ROOT . '/data/seed.json'), true);
    if (!is_array($seed)) {
        return;
    }
    if ($from < 3) {
        // v2: экскурсионный тур в Пекин получает полное содержимое (программа по
        // дням, отель, перелёт, документы, фото) — раньше карточка была пустой.
        // v3: у него же смена 27 октября – 1 ноября, цена от 120 000 ₽ и
        // примечание про перелёт. Upsert одного тура, остальные не трогаем.
        foreach ((array) ($seed['tours'] ?? []) as $t) {
            if (($t['slug'] ?? '') === 'ekskursionnyy-tur-v-pekin') {
                at_seed_apply($pdo, ['tours' => [$t]]);
                break;
            }
        }
    }
    if ($from < 2) {
        foreach ((array) ($seed['slides'] ?? []) as $sl) {
            if (($sl['placement'] ?? '') === 'PROMO'
                && !(int) $pdo->query("SELECT COUNT(*) FROM slides WHERE placement = 'PROMO'")->fetchColumn()) {
                $pdo->prepare('INSERT INTO slides (placement, image, href, title, subtitle, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)')
                    ->execute(['PROMO', (string) $sl['image'], $sl['href'] ?? null, $sl['title'] ?? null, $sl['subtitle'] ?? null, (int) ($sl['sortOrder'] ?? 0)]);
            }
        }
    }
    if ($from < 5) {
        // v5: у части смен из сида даты были только в названии — распознаём.
        at_shifts_fill_dates($pdo);
    }
}

/** Смены без дат: пробуем распознать их из названия. Возвращает число заполненных. */
function at_shifts_fill_dates(PDO $pdo): int
{
    $n = 0;
    $upd = $pdo->prepare('UPDATE tour_shifts SET start_date = ?, end_date = ? WHERE id = ?');
    foreach ($pdo->query('SELECT s.id, s.name, t.season_text, t.title FROM tour_shifts s JOIN tours t ON t.id = s.tour_id WHERE s.start_date IS NULL')->fetchAll(PDO::FETCH_ASSOC) as $s) {
        [$a, $b] = at_shift_parse_dates((string) $s['name'], at_tour_year($s['season_text'], $s['title']));
        if ($a !== null) {
            $upd->execute([$a, $b ?? $a, (int) $s['id']]);
            $n++;
        }
    }
    return $n;
}

/** v6: актуальная карточка А-тур; отзывы и прочие настройки сохраняются. */
function at_refresh_yandex_profile(PDO $pdo): void
{
    $url = 'https://yandex.ru/profile/70291708728';
    $pdo->beginTransaction();
    try {
        $setting = $pdo->prepare('REPLACE INTO atour_settings (k, v) VALUES (?, ?)');
        $setting->execute(['contacts.yandexOrgId', '70291708728']);
        $setting->execute(['contacts.mapLat', '55.7713']);
        $setting->execute(['contacts.mapLng', '37.622381']);
        $setting->execute(['reviews.hideGoogle', '1']);
        $pdo->prepare("UPDATE review_platforms SET url = ?, rating = 4.7, max_rating = 5 WHERE name = 'Яндекс' OR url LIKE '%yandex.ru/%'")->execute([$url]);
        $pdo->prepare("UPDATE reviews SET source_url = ? WHERE source = 'YANDEX'")->execute([$url]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
