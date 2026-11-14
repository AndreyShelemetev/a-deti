<?php
/** Сервис: повторная загрузка контента из data/seed.json, сводка по базе. */
declare(strict_types=1);

function adm_tab_tools(): string
{
    require_once ATOUR_ROOT . '/inc/source-refresh.php';
    require_once ATOUR_ROOT . '/inc/source-media.php';
    $sourceFile = ATOUR_ROOT . '/inc/content/source-tours-20261030.json';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'source_media') {
        header('Content-Type: application/json; charset=utf-8');
        try {
            if (!at_source_refresh_allowed()) throw new RuntimeException('Сохранение фотографий доступно только проектам А-тур.');
            $manifest = at_source_refresh_manifest($sourceFile);
            if (!hash_equals($manifest['hash'], (string) ($_POST['manifest_hash'] ?? ''))) throw new RuntimeException('Снимок изменился. Обновите страницу.');
            $result = at_source_media_batch(db(), $manifest, (int) ($_POST['offset'] ?? 0));
            echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $error->getMessage()], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'source_refresh') {
        // adm_boot уже проверяет вход и CSRF у всех POST; импорт дополнительно ограничен точными парами домена и ID проектов А-тур.
        try {
            if (!at_source_refresh_allowed()) throw new RuntimeException('Обновление доступно только тестовому и основному проектам А-тур.');
            $result = at_source_refresh_apply(db(), at_source_refresh_manifest($sourceFile), (string) ($_POST['manifest_hash'] ?? ''));
            $message = !empty($result['already']) ? 'Этот снимок уже применён. Повторное обновление не требуется.'
                : 'Туры обновлены: ' . $result['updated'] . ', добавлены: ' . $result['added'] . '. Предыдущий контент сохранён в резервной копии.';
            adm_redirect('tools', ['ok' => 1, 'msg' => $message]);
        } catch (Throwable $error) {
            adm_redirect('tools', ['err' => 'Контент не обновлён: ' . $error->getMessage()]);
        }
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'reseed') {
        require_once ATOUR_ROOT . '/inc/seed.php';
        try {
            $n = at_seed_run(db(), ATOUR_ROOT . '/data/seed.json', true);
            adm_redirect('tools', ['ok' => 1, 'msg' => 'Контент загружен заново: туров ' . ($n['tours'] ?? 0) . ', записей ' . ($n['posts'] ?? 0) . ', страниц ' . ($n['pages'] ?? 0) . '.']);
        } catch (\Throwable $e) {
            adm_redirect('tools', ['err' => 'Не удалось загрузить: ' . $e->getMessage()]);
        }
    }
    $counts = [];
    foreach (['tours', 'tour_shifts', 'tour_images', 'catalog_pages', 'tags', 'regions', 'posts', 'pages', 'reviews', 'requests', 'payments', 'subscribers', 'redirects', 'media'] as $t) {
        $counts[$t] = (int) at_scalar("SELECT COUNT(*) FROM `{$t}`");
    }
    $h = adm_source_refresh_card($sourceFile) . adm_source_media_card($sourceFile);
    $h .= '<div class="card"><h2>Первоначальный контент сайта</h2><p class="muted">Файл data/seed.json содержит первоначальный перенос a-deti.ru, а не свежий снимок. Повторная загрузка обновит туры, страницы, новости и статьи по их адресам, пересоберёт блоки главной, но не тронет отзывы и настройки, правленные здесь, а также заявки.</p>'
        . adm_form_open('reseed') . '<button class="btn d" onclick="return confirm(\'Перезаписать туры, страницы и новости содержимым data/seed.json?\')">Загрузить контент заново</button></form></div>';
    $h .= '<div class="card"><h2>Сводка по базе</h2><table>';
    foreach ($counts as $t => $n) {
        $h .= '<tr><th style="width:220px">' . adm_e($t) . '</th><td>' . $n . '</td></tr>';
    }
    $h .= '</table></div><div class="card"><h2>Где что</h2><ul><li><a href="/adm_panel001.php" target="_blank">Панель платформы</a>: SEO-теги любой страницы, лиды с уведомлениями, название сайта, favicon, ключи.</li><li><a href="/sitemap.xml" target="_blank">sitemap.xml</a> строится из базы автоматически.</li><li><a href="/api/v1/tours/" target="_blank">/api/v1/tours/</a> — публичный JSON каталога для партнёров.</li></ul></div>';
    return $h;
}

function adm_source_media_card(string $file): string
{
    if (!at_source_refresh_allowed() || !is_file($file)) return '';
    try {
        $manifest = at_source_refresh_manifest($file);
        $status = at_source_media_status(db(), $manifest);
        return '<div class="card"><h2>Фотографии исходного сайта</h2><p>Сохраним оригинальные фотографии на этом сайте, чтобы их загрузка не зависела от доступности a-deti.ru. У каждого раздела останется его исходная фотография.</p>'
            . '<p role="status" aria-live="polite" id="source-media-status">Сохранено: ' . $status['ready'] . ' из ' . $status['total'] . '.</p>'
            . ($status['pending'] ? adm_form_open('source_media') . '<input type="hidden" name="manifest_hash" value="' . adm_e($manifest['hash']) . '"><button class="btn" id="source-media-start">Сохранить фотографии на сайте</button></form>'
                . '<script src="/assets/admin-source-media.js" defer></script>' : '<p class="alert ok">Все фотографии из снимка сохранены на сайте.</p>')
            . '</div>';
    } catch (Throwable $error) {
        return '<div class="card"><h2>Фотографии исходного сайта</h2><p class="alert err">' . adm_e($error->getMessage()) . '</p></div>';
    }
}

function adm_source_refresh_card(string $file): string
{
    if (!at_source_refresh_allowed() || !is_file($file)) return '';
    try {
        $manifest = at_source_refresh_manifest($file);
        $plan = at_source_refresh_plan(db(), $manifest);
        $previous = at_source_refresh_previous(db(), $manifest['hash']);
        $date = strtotime((string) ($manifest['capturedAt'] ?? ''));
        $targetLabel = strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')) === 'a-tur.gdesite.ru' ? 'основном' : 'тестовом';
        $h = '<div class="card"><h2>Обновление туров из a-deti.ru</h2><p class="muted">Проверенный снимок исходного сайта'
            . ($date ? ' от ' . date('d.m.Y', $date) : '') . '. Тексты, фотографии, смены, трансфер и документы будут заменены данными источника. Оформление страниц сохранится.</p>'
            . '<div class="grid3"><div><strong>' . $plan['existing'] . '</strong><p>туров обновится</p></div><div><strong>' . $plan['new'] . '</strong><p>новых туров добавится</p></div><div><strong>' . $plan['untouched'] . '</strong><p>остальных туров сохранятся</p></div></div>'
            . '<p>Обновление применяется на ' . $targetLabel . ' сайте. Перед записью сохраняется резервная копия туров. Главная, шапка и меню, настройки, отзывы и заявки останутся без изменений.</p>';
        if ($plan['redirects']) $h .= '<p>Для ' . count($plan['redirects']) . ' страниц с изменившимися адресами источника сохранятся ваши привычные ссылки. Новые адреса будут вести на соответствующие страницы.</p>';
        if ($previous !== null) {
            $h .= '<div class="alert ok">Этот снимок уже применён. Последующие правки в админке сохранены.</div>';
        } else {
            $h .= '<details><summary>Какие страницы будут обновлены</summary><ul>';
            foreach ($plan['items'] as $item) $h .= '<li>' . adm_e($item['source']['title']) . ($item['old'] === null ? ' — новая страница' : '') . '</li>';
            $h .= '</ul></details>' . adm_form_open('source_refresh') . '<input type="hidden" name="manifest_hash" value="' . adm_e($manifest['hash']) . '">'
                . '<button class="btn" style="margin-top:16px">Обновить туры на ' . $targetLabel . ' сайте</button></form>';
        }
        return $h . '</div>';
    } catch (Throwable $error) {
        return '<div class="card"><h2>Обновление туров из a-deti.ru</h2><div class="alert err">Снимок пока нельзя применить: ' . adm_e($error->getMessage()) . '</div></div>';
    }
}
