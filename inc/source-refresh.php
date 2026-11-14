<?php
/** Явное обновление контента А-тур из проверенного снимка a-deti.ru. Только проекты А-тур: тестовый № 6 и основной № 511. */
declare(strict_types=1);

function at_source_refresh_target(string $host, int $project): bool
{
    return match (strtolower($host)) {
        'a-tur.t.gdesite.ru' => $project === 6,
        'a-tur.gdesite.ru' => $project === 511,
        default => false,
    };
}

function at_source_refresh_allowed(): bool
{
    // Изолированные CLI-проверки не подключают служебный runtime платформы.
    if (PHP_SAPI === 'cli' && !function_exists('sg_project_id')) return true;
    return function_exists('sg_project_id')
        && at_source_refresh_target((string) ($_SERVER['HTTP_HOST'] ?? ''), (int) sg_project_id());
}

function at_source_refresh_url(string $url, bool $image = false): bool
{
    if ($url === '') return true;
    if (preg_match('~[\x00-\x20]~', $url) || str_contains($url, '\\')) return false;
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) return true;
    $parts = parse_url($url);
    return is_array($parts) && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
        && !isset($parts['user']) && !isset($parts['pass']) && !empty($parts['host'])
        && (!$image || strtolower($parts['host']) === 'a-deti.ru');
}

function at_source_refresh_manifest(string $file): array
{
    $raw = file_get_contents($file);
    if ($raw === false) throw new RuntimeException('Не удалось прочитать снимок исходного сайта.');
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || ($data['version'] ?? null) !== 1 || ($data['source'] ?? '') !== 'https://a-deti.ru/'
        || !is_array($data['tours'] ?? null) || !$data['tours'] || count($data['tours']) > 500) {
        throw new RuntimeException('Неверный формат снимка туров a-deti.ru.');
    }
    $slugs = [];
    foreach ($data['tours'] as $tour) {
        $slug = (string) ($tour['slug'] ?? '');
        if (!preg_match('~^[a-z0-9][a-z0-9-]{0,189}$~D', $slug) || isset($slugs[$slug]) || trim((string) ($tour['title'] ?? '')) === '') {
            throw new RuntimeException('Пустой или повторяющийся адрес/заголовок тура в снимке.');
        }
        $slugs[$slug] = true;
        if (isset($tour['matchSlug']) && !preg_match('~^[a-z0-9][a-z0-9-]{0,189}$~D', (string) $tour['matchSlug'])) {
            throw new RuntimeException('Неверный адрес сопоставления тура: ' . $slug);
        }
        foreach (['sections', 'images', 'shifts', 'documents'] as $required) {
            if (!isset($tour[$required]) || !is_array($tour[$required])) throw new RuntimeException('Отсутствует полный раздел ' . $required . ': ' . $slug);
        }
        foreach (['cover', 'banner'] as $field) if (!at_source_refresh_url((string) ($tour[$field] ?? ''), true)) throw new RuntimeException('Неверный источник изображения: ' . $slug);
        foreach ($tour['images'] as $image) if (!at_source_refresh_url(is_array($image) ? (string) ($image['url'] ?? '') : (string) $image, true)) throw new RuntimeException('Неверный источник фотографии: ' . $slug);
        foreach ($tour['documents'] as $document) if (trim((string) ($document['label'] ?? '')) === '' || empty($document['url']) || !at_source_refresh_url((string) $document['url'])) throw new RuntimeException('Неверная ссылка на документ: ' . $slug);
        if (isset($tour['type']) && !in_array($tour['type'], ['CAMP', 'FAMILY', 'SPORT', 'EXCURSION', 'TRANSFER', 'YOUTH'], true)) throw new RuntimeException('Неизвестный тип тура: ' . $slug);
        $keys = [];
        foreach ($tour['sections'] as $section) {
            $key = (string) ($section['key'] ?? '');
            if (!preg_match('~^[a-z][a-z0-9_-]{0,39}$~D', $key) || isset($keys[$key]) || trim((string) ($section['title'] ?? '')) === '') {
                throw new RuntimeException('Неверный или повторяющийся раздел: ' . $slug);
            }
            $keys[$key] = true;
            if (preg_match('~<(?:script|iframe|object|embed|form|input|button)\b|\son[a-z]+\s*=|(?:javascript|vbscript)\s*:~iu', (string) ($section['html'] ?? ''))) {
                throw new RuntimeException('Исполняемая разметка в описании: ' . $slug);
            }
        }
        foreach ($tour['shifts'] as $shift) {
            if (trim((string) ($shift['name'] ?? '')) === '') throw new RuntimeException('Смена без названия: ' . $slug);
            foreach (['startDate', 'endDate'] as $field) {
                $date = (string) ($shift[$field] ?? '');
                if ($date !== '' && (!preg_match('~^\d{4}-\d{2}-\d{2}$~D', $date)
                    || date('Y-m-d', strtotime($date) ?: 0) !== $date)) throw new RuntimeException('Неверная дата смены: ' . $slug);
            }
            if (!empty($shift['startDate']) && !empty($shift['endDate']) && $shift['startDate'] > $shift['endDate']) throw new RuntimeException('Перепутаны даты смены: ' . $slug);
        }
    }
    $data['hash'] = hash('sha256', $raw);
    return $data;
}

function at_source_refresh_marker(string $hash): string
{
    return 'source.refresh.' . substr($hash, 0, 48);
}

function at_source_refresh_previous(PDO $pdo, string $hash): ?array
{
    $st = $pdo->prepare('SELECT v FROM atour_settings WHERE k = ?');
    $st->execute([at_source_refresh_marker($hash)]);
    $value = $st->fetchColumn();
    if ($value === false) return null;
    $result = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
    if (($result['hash'] ?? '') !== $hash) throw new RuntimeException('Не совпадает контрольная сумма прошлого обновления.');
    return $result;
}

/** Однозначное сопоставление, без догадок по похожим названиям. */
function at_source_refresh_plan(PDO $pdo, array $manifest, bool $lock = false): array
{
    $sql = 'SELECT * FROM tours ORDER BY id';
    if ($lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
    $existing = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $bySlug = array_column($existing, null, 'slug');
    $used = []; $plan = []; $redirects = [];
    $redirectSql = 'SELECT * FROM redirects';
    if ($lock && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $redirectSql .= ' FOR UPDATE';
    $existingRedirects = array_column($pdo->query($redirectSql)->fetchAll(PDO::FETCH_ASSOC), null, 'from_path');
    foreach ($manifest['tours'] as $tour) {
        $slug = (string) ($tour['matchSlug'] ?? $tour['slug']);
        $old = $bySlug[$slug] ?? null;
        if ($old === null && !empty($tour['legacyId'])) {
            $matches = array_values(array_filter($existing, static fn(array $row): bool => (int) ($row['legacy_id'] ?? 0) === (int) $tour['legacyId']));
            if (count($matches) > 1) throw new RuntimeException('Несколько туров с одним исходным ID: ' . $tour['slug']);
            $old = $matches[0] ?? null;
        }
        if (isset($tour['matchSlug']) && $old === null) throw new RuntimeException('Не найден тур для обновления: ' . $slug);
        if ($old !== null && isset($used[(int) $old['id']])) throw new RuntimeException('Две исходные страницы соответствуют одному туру: ' . $slug);
        if ($old !== null) $used[(int) $old['id']] = true;
        if ($old !== null && $old['slug'] !== $tour['slug']) {
            if (isset($bySlug[$tour['slug']])) throw new RuntimeException('Адрес источника занят другим туром: ' . $tour['slug']);
            $from = '/tour/' . $tour['slug'] . '/'; $to = '/tour/' . $old['slug'] . '/';
            $previousRedirect = $existingRedirects[$from] ?? null;
            if ($previousRedirect !== null && ($previousRedirect['to_path'] !== $to || (int) $previousRedirect['status_code'] !== 301)) {
                throw new RuntimeException('Для адреса ' . $from . ' уже настроена другая переадресация. Она сохранена; сначала разрешите конфликт в админке.');
            }
            $redirects[] = ['from_path' => $from, 'to_path' => $to, 'status_code' => 301, 'old' => $previousRedirect];
        }
        $plan[] = ['source' => $tour, 'old' => $old];
    }
    return ['items' => $plan, 'existing' => count($used), 'new' => count($plan) - count($used), 'untouched' => count($existing) - count($used), 'redirects' => $redirects];
}

/** Полные прежние строки для восстановления; персональные заявки и отзывы не затрагиваются. */
function at_source_refresh_snapshot(PDO $pdo, array $plan): array
{
    $snapshot = ['tours' => [], 'newSlugs' => [], 'newRedirectPaths' => [], 'tables' => ['redirects' => []]];
    $ids = [];
    foreach ($plan['items'] as $item) {
        if ($item['old'] === null) $snapshot['newSlugs'][] = $item['source']['slug'];
        else { $snapshot['tours'][] = $item['old']; $ids[] = (int) $item['old']['id']; }
    }
    foreach (['tour_sections', 'tour_images', 'tour_shifts', 'tour_documents', 'tour_tags'] as $table) {
        $snapshot['tables'][$table] = $ids ? $pdo->query('SELECT * FROM ' . $table . ' WHERE tour_id IN (' . implode(',', $ids) . ')')->fetchAll(PDO::FETCH_ASSOC) : [];
    }
    foreach (['regions', 'tags'] as $table) $snapshot['tables'][$table] = $pdo->query('SELECT * FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($plan['redirects'] as $redirect) {
        if ($redirect['old'] === null) $snapshot['newRedirectPaths'][] = $redirect['from_path'];
        else $snapshot['tables']['redirects'][] = $redirect['old'];
    }
    return $snapshot;
}

function at_source_refresh_insert(PDO $pdo, string $table, array $row): int
{
    $columns = array_keys($row);
    $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')')->execute(array_values($row));
    return (int) $pdo->lastInsertId();
}

/** Цена и текст — из источника; статус мест — из уже работающей админки. */
function at_source_refresh_shifts(PDO $pdo, int $tourId, array $shifts): void
{
    $find = $pdo->prepare('SELECT * FROM tour_shifts WHERE tour_id = ? ORDER BY sort_order,id');
    $find->execute([$tourId]); $old = $find->fetchAll(PDO::FETCH_ASSOC); $used = [];
    foreach ($shifts as $i => $source) {
        $row = ['tour_id' => $tourId, 'group_name' => $source['group'] ?? null, 'name' => $source['name'],
            'start_date' => ($source['startDate'] ?? '') ?: null, 'end_date' => ($source['endDate'] ?? '') ?: null,
            'price' => $source['price'] ?? null, 'price_text' => $source['priceText'] ?? null, 'sort_order' => (int) ($source['sortOrder'] ?? $i)];
        $matches = array_values(array_filter($old, static fn(array $s): bool => !isset($used[(int) $s['id']])
            && (string) $s['start_date'] === (string) $row['start_date'] && (string) $s['end_date'] === (string) $row['end_date']
            && (string) $s['group_name'] === (string) $row['group_name']));
        if (count($matches) !== 1) $matches = array_values(array_filter($matches, static fn(array $s): bool => $s['name'] === $row['name']));
        // Источник иногда переименовывает корпус. Единственная смена с теми же датами — та же запись.
        if (!$matches && $row['start_date'] !== null) {
            $byDates = array_values(array_filter($old, static fn(array $s): bool => !isset($used[(int) $s['id']])
                && $s['start_date'] === $row['start_date'] && $s['end_date'] === $row['end_date']));
            if (count($byDates) === 1) $matches = $byDates;
        }
        if (count($matches) === 1) {
            $id = (int) $matches[0]['id']; $used[$id] = true;
            $pdo->prepare('UPDATE tour_shifts SET ' . implode(',', array_map(static fn(string $c): string => $c . '=?', array_keys($row))) . ' WHERE id=?')->execute([...array_values($row), $id]);
        } else {
            $id = at_source_refresh_insert($pdo, 'tour_shifts', $row + ['availability' => 'AVAILABLE', 'seats_left' => null]); $used[$id] = true;
        }
    }
    foreach ($old as $row) if (!isset($used[(int) $row['id']])) $pdo->prepare('DELETE FROM tour_shifts WHERE id=?')->execute([(int) $row['id']]);
}

function at_source_refresh_apply(PDO $pdo, array $manifest, string $expectedHash): array
{
    if (!at_source_refresh_allowed()) throw new RuntimeException('Обновление источника доступно только тестовому и основному проектам А-тур.');
    if (!hash_equals((string) $manifest['hash'], $expectedHash)) throw new RuntimeException('Снимок обновился. Откройте предварительный просмотр заново.');
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    if ($mysql && (int) $pdo->query("SELECT GET_LOCK('atour_source_refresh', 5)")->fetchColumn() !== 1) throw new RuntimeException('Обновление уже выполняется. Дождитесь завершения.');
    try {
        if ($prior = at_source_refresh_previous($pdo, $expectedHash)) return $prior + ['already' => true];
        $pdo->exec('CREATE TABLE IF NOT EXISTS atour_content_refresh_backups (manifest_hash VARCHAR(64) PRIMARY KEY, created_at VARCHAR(32) NOT NULL, source VARCHAR(255) NOT NULL, snapshot_json ' . ($mysql ? 'LONGTEXT' : 'TEXT') . ' NOT NULL)' . ($mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : ''));
        $pdo->beginTransaction();
        $plan = at_source_refresh_plan($pdo, $manifest, true);
        $snapshot = at_source_refresh_snapshot($pdo, $plan);
        at_source_refresh_insert($pdo, 'atour_content_refresh_backups', ['manifest_hash' => $expectedHash, 'created_at' => gmdate(DATE_ATOM), 'source' => $manifest['source'], 'snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        // Справочники дополняются, но названия, уже изменённые владельцем, не заменяются.
        $regions = $pdo->query('SELECT slug,id FROM regions')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($manifest['regions'] ?? [] as $r) if (!isset($regions[$r['slug']])) $regions[$r['slug']] = at_source_refresh_insert($pdo, 'regions', ['slug' => $r['slug'], 'name' => $r['name'], 'is_foreign' => !empty($r['isForeign']) ? 1 : 0, 'sort_order' => (int) ($r['sortOrder'] ?? 0)]);
        $tags = $pdo->query('SELECT slug,id FROM tags')->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach ($manifest['tags'] ?? [] as $tag) if (!isset($tags[$tag['slug']])) $tags[$tag['slug']] = at_source_refresh_insert($pdo, 'tags', ['slug' => $tag['slug'], 'label' => $tag['label'], 'sort_order' => (int) ($tag['sortOrder'] ?? 0)]);
        $map = ['legacyId' => 'legacy_id', 'title' => 'title', 'seoTitle' => 'seo_title', 'metaDescription' => 'meta_description', 'metaKeywords' => 'meta_keywords', 'short' => 'short', 'address' => 'address', 'ageText' => 'age_text', 'ageMin' => 'age_min', 'ageMax' => 'age_max', 'website' => 'website', 'priceText' => 'price_text', 'priceMin' => 'price_min', 'priceNote' => 'price_note', 'cover' => 'cover', 'banner' => 'banner', 'type' => 'type', 'seasonText' => 'season_text'];
        foreach ($plan['items'] as $item) {
            $source = $item['source']; $old = $item['old']; $row = [];
            foreach ($map as $from => $to) if (array_key_exists($from, $source)) $row[$to] = $source[$from];
            if (isset($source['seasons'])) $row['seasons'] = implode(',', $source['seasons']);
            if (isset($source['regionSlug']) && isset($regions[$source['regionSlug']])) $row['region_id'] = (int) $regions[$source['regionSlug']];
            $search = [$source['title'], $source['short'] ?? '', $source['address'] ?? '', $source['ageText'] ?? ''];
            foreach ($source['sections'] as $section) $search[] = html_entity_decode(strip_tags($section['html']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $row['search_text'] = mb_strtolower(implode(' ', $search));
            if ($old) {
                $id = (int) $old['id'];
                $pdo->prepare('UPDATE tours SET ' . implode(',', array_map(static fn(string $c): string => $c . '=?', array_keys($row))) . ' WHERE id=?')->execute([...array_values($row), $id]);
            } else {
                $id = at_source_refresh_insert($pdo, 'tours', $row + ['slug' => $source['slug'], 'type' => 'CAMP', 'is_published' => 1, 'is_best' => !empty($source['isBest']) ? 1 : 0, 'is_hit' => !empty($source['isHit']) ? 1 : 0, 'sort_order' => (int) ($source['sortOrder'] ?? 0)]);
            }
            foreach (['tour_sections', 'tour_images', 'tour_documents'] as $table) $pdo->prepare('DELETE FROM ' . $table . ' WHERE tour_id=?')->execute([$id]);
            foreach ($source['sections'] as $i => $section) at_source_refresh_insert($pdo, 'tour_sections', ['tour_id' => $id, 'skey' => $section['key'], 'title' => $section['title'], 'html' => $section['html'], 'sort_order' => (int) ($section['sortOrder'] ?? $i)]);
            foreach ($source['images'] as $i => $image) at_source_refresh_insert($pdo, 'tour_images', ['tour_id' => $id, 'url' => is_array($image) ? $image['url'] : $image, 'alt' => is_array($image) ? ($image['alt'] ?? $source['title']) : $source['title'], 'sort_order' => $i]);
            foreach ($source['documents'] as $i => $document) at_source_refresh_insert($pdo, 'tour_documents', ['tour_id' => $id, 'label' => $document['label'], 'url' => $document['url'], 'sort_order' => (int) ($document['sortOrder'] ?? $i)]);
            at_source_refresh_shifts($pdo, $id, $source['shifts']);
            // Фильтры существующих туров сохраняются; у новых используются исходные метки.
            if ($old === null) foreach (array_unique($source['tagSlugs'] ?? []) as $tag) if (isset($tags[$tag])) at_source_refresh_insert($pdo, 'tour_tags', ['tour_id' => $id, 'tag_id' => (int) $tags[$tag]]);
        }
        $addedRedirects = 0;
        foreach ($plan['redirects'] as $redirect) {
            if ($redirect['old'] !== null) continue;
            unset($redirect['old']);
            at_source_refresh_insert($pdo, 'redirects', $redirect); $addedRedirects++;
        }
        $result = ['hash' => $expectedHash, 'updated' => $plan['existing'], 'added' => $plan['new'], 'untouched' => $plan['untouched'], 'redirects' => $addedRedirects, 'appliedAt' => gmdate(DATE_ATOM)];
        at_source_refresh_insert($pdo, 'atour_settings', ['k' => at_source_refresh_marker($expectedHash), 'v' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    } finally {
        if ($mysql) $pdo->query("SELECT RELEASE_LOCK('atour_source_refresh')");
    }
}
