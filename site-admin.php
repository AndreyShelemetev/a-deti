<?php
/**
 * Управление сайтом А-тур: туры, страницы каталога, новости и статьи,
 * текстовые страницы, блоки главной, отзывы, заявки, платежи, подписчики,
 * настройки. Вход — учётной записью админки платформы (таблица admin_users,
 * общая сессия с adm_panel001.php). SEO страниц и лиды — в панели платформы.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/forms.php';
require_once __DIR__ . '/inc/admin/core.php';
require_once __DIR__ . '/inc/admin/crud.php';

adm_boot();
