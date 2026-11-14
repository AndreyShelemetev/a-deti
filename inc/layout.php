<?php
/**
 * Разметка сайта А-тур: шапка, подвал, модальные формы и общие компоненты
 * (карточка тура, карточка новости, хлебные крошки, пагинация, блок отзывов,
 * карта, поля форм). Стили — assets/style.css поверх платформенного theme.css.
 */
declare(strict_types=1);

require_once __DIR__ . "/design.php";

const AT_MAIN_NAV = [
    ['label' => 'Лагеря России', 'href' => '/tour/filter/lager/russia/', 'children' => [
        ['label' => 'Лагеря Краснодарского края', 'href' => '/tour/filter/lager/krasnodar_region/'],
        ['label' => 'Лагеря Крыма', 'href' => '/tour/filter/lager/crymea/'],
        ['label' => 'Лагеря Подмосковья', 'href' => '/tour/filter/lager/moscow_region/'],
        ['label' => 'Лагеря на море', 'href' => '/tour/filter/lager/sea-camps/'],
        ['label' => 'Лагеря на Алтае', 'href' => '/tour/filter/lager/altay/'],
    ]],
    ['label' => 'Спортивные сборы', 'href' => '/tour/filter/sport/'],
    ['label' => 'За границей', 'href' => '/tour/filter/lager/foreign-camps/', 'children' => [
        ['label' => 'Лагеря Китая', 'href' => '/tour/filter/lager/china/'],
        ['label' => 'Языковые лагеря', 'href' => '/tour/filter/lager/foreign-camps/lingual-camps/'],
        ['label' => 'Спортивные лагеря', 'href' => '/tour/filter/lager/foreign-camps/sport-camps/'],
    ]],
    ['label' => 'Авиа и ЖД', 'href' => '/tour/filter/transfer/'],
    ['label' => 'Экскурсии', 'href' => '/tour/filter/excursions/', 'children' => [
        ['label' => 'Алтай', 'href' => '/tour/filter/lager/altay/'],
        ['label' => 'Великий Новгород', 'href' => '/tour/tur-na-6-dney-staraya-ladoga-velikiy-novgorod-pushkinskie-gory-pskov-izborsk-pechory/'],
        ['label' => 'Карелия', 'href' => '/tour/zimnyaya-mergubskaya-ekspeditsiya-kareliya-zima-2026/'],
        ['label' => 'Новогодние туры', 'href' => '/tour/filter/new-year/'],
    ]],
];
const AT_FOOTER_PAGES = [
    ['label' => 'Родителям', 'href' => '/rodytelyam/'],
    ['label' => 'Агентствам', 'href' => '/agentstvam/'],
    ['label' => 'Трансфер до лагеря', 'href' => '/transfer-do-lagerya/'],
    ['label' => 'Оплата', 'href' => '/pay/'],
    ['label' => 'Контакты', 'href' => '/kak-nas-nayti/'],
    ['label' => 'Новости', 'href' => '/novosti/'],
    ['label' => 'Статьи', 'href' => '/stati/'],
];
const AT_FOOTER_CATALOG = [
    ['label' => 'Лагеря России', 'href' => '/tour/filter/lager/russia/'],
    ['label' => 'Спортивные сборы', 'href' => '/tour/filter/sport/'],
    ['label' => 'Детские лагеря за границей', 'href' => '/tour/filter/lager/foreign-camps/'],
    ['label' => 'Авиа и ЖД', 'href' => '/tour/filter/transfer/'],
    ['label' => 'Экскурсии', 'href' => '/tour/filter/excursions/'],
    ['label' => 'Все туры', 'href' => '/tour/'],
];
const AT_FOOTER_TYPES = [
    ['label' => 'Детский отдых', 'href' => '/tour/filter/lager/'],
    ['label' => 'Молодёжный отдых', 'href' => '/tour/filter/lager/teenage-camps/'],
    ['label' => 'Семейный отдых', 'href' => '/tour/filter/family-tours/'],
];

// --- Иконки (инлайн-SVG в духе Lucide) -----------------------------------------

function at_icon(string $name, string $class = 'ic'): string
{
    static $paths = [
        'map-pin'        => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'mail'           => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'phone'          => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'search'         => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'chevron-down'   => '<path d="m6 9 6 6 6-6"/>',
        'chevron-right'  => '<path d="m9 18 6-6-6-6"/>',
        'chevron-left'   => '<path d="m15 18-6-6 6-6"/>',
        'home'           => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'menu'           => '<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>',
        'x'              => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'star'           => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'shield-check'   => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
        'sparkles'       => '<path d="M9.94 14.34 12 20l2.06-5.66L20 12l-5.94-2.34L12 4 9.94 9.66 4 12z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/>',
        'moon'           => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'maximize'       => '<path d="M8 3H5a2 2 0 0 0-2 2v3"/><path d="M21 8V5a2 2 0 0 0-2-2h-3"/><path d="M3 16v3a2 2 0 0 0 2 2h3"/><path d="M16 21h3a2 2 0 0 0 2-2v-3"/>',
        'image'          => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>',
        'sun'            => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
        'arrow-right'    => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'calendar'       => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        'calendar-range' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M3 10h18"/><path d="M8 2v4"/><path d="M17 14h-6"/><path d="M13 18H7"/><path d="M7 14h.01"/><path d="M17 18h.01"/>',
        'users'          => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'external-link'  => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'file-down'      => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M12 18v-6"/><path d="m9 15 3 3 3-3"/>',
        'credit-card'    => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
        'printer'        => '<path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 9V3h12v6"/><rect x="6" y="14" width="12" height="8"/>',
        'images'         => '<path d="M18 22H4a2 2 0 0 1-2-2V6"/><path d="m22 13-1.296-1.296a2.41 2.41 0 0 0-3.408 0L11 18"/><circle cx="12" cy="8" r="2"/><rect width="16" height="16" x="6" y="2" rx="2"/>',
        'flame'          => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'plane'          => '<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>',
        'send'           => '<path d="M14.536 21.686a.5.5 0 0 0 .937-.024l6.5-19a.496.496 0 0 0-.635-.635l-19 6.5a.5.5 0 0 0-.024.937l7.93 3.18a2 2 0 0 1 1.112 1.11z"/><path d="m21.854 2.147-10.94 10.939"/>',
        'clock'          => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'building'       => '<rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
        'handshake'      => '<path d="m11 17 2 2a1 1 0 1 0 3-3"/><path d="m14 14 2.5 2.5a1 1 0 1 0 3-3l-3.88-3.88a3 3 0 0 0-4.24 0l-.88.88a1 1 0 1 1-3-3l2.81-2.81a5.79 5.79 0 0 1 7.06-.87l.47.28a2 2 0 0 0 1.42.25L21 4"/><path d="m21 3 1 11h-2"/><path d="M3 3 2 14l6.5 6.5a1 1 0 1 0 3-3"/><path d="M3 4h8"/>',
        'percent'        => '<line x1="19" x2="5" y1="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>',
        'headset'        => '<path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H3z"/><path d="M21 11h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3z"/><path d="M3 11a9 9 0 0 1 18 0"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/>',
        'file-check'     => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/>',
        'sliders'        => '<line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/>',
        'check'          => '<path d="M20 6 9 17l-5-5"/>',
        'grid'           => '<rect width="7" height="7" x="3" y="3" rx="1.5"/><rect width="7" height="7" x="14" y="3" rx="1.5"/><rect width="7" height="7" x="14" y="14" rx="1.5"/><rect width="7" height="7" x="3" y="14" rx="1.5"/>',
        'rows'           => '<rect width="18" height="7" x="3" y="3" rx="1.5"/><rect width="18" height="7" x="3" y="14" rx="1.5"/>',
        'cookie'         => '<path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/><path d="M8.5 8.5v.01"/><path d="M16 15.5v.01"/><path d="M12 12v.01"/><path d="M11 17v.01"/><path d="M7 14v.01"/>',
        'loader'         => '<path d="M21 12a9 9 0 1 1-6.219-8.56"/>',
        'vk'             => '<path fill="currentColor" stroke="none" d="M12.8 18.6c-6.2 0-9.7-4.2-9.9-11.3h3.1c.1 5.2 2.4 7.4 4.2 7.8V7.3h2.9v4.5c1.8-.2 3.7-2.2 4.3-4.5h2.9c-.5 2.8-2.5 4.8-4 5.6 1.5.7 3.8 2.4 4.7 5.7h-3.2c-.7-2.2-2.4-3.9-4.7-4.1v4.1h-.3z"/>',
    ];
    $body = $paths[$name] ?? '';
    return '<svg class="' . at_e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}

function at_img(?string $src, string $alt = '', string $class = '', array $attrs = []): string
{
    $a = '';
    foreach ($attrs as $k => $v) {
        $a .= ' ' . $k . '="' . at_e($v) . '"';
    }
    if (!isset($attrs['loading'])) {
        $a .= ' loading="lazy"';
    }
    return '<img src="' . at_e(at_img_src($src)) . '" alt="' . at_e($alt) . '"' . ($class !== '' ? ' class="' . at_e($class) . '"' : '') . $a . '>';
}

/** Режим работы — три читаемые строки; сохранённый текст админки не меняем. */
function at_hours_html(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", trim($text));
    $text = (string) preg_replace('~[,:]?\s*(с\s*\d{1,2}[:.]\d{2}\s*(?:до|–|-)\s*\d{1,2}[:.]\d{2})~ui', "\n$1", $text);
    $text = (string) preg_replace('~[,;]?\s*(суббота[^\n]*)~ui', "\n$1", $text);
    return implode('<br>', array_map('at_e', array_values(array_filter(array_map('trim', explode("\n", $text))))));
}

// --- Страница: начало и конец ---------------------------------------------------

/**
 * Адрес файла из assets/ с версией по содержимому (?v=…). Платформа отдаёт
 * статику с expires 7d, поэтому без версии после «Обновить из sites/…»
 * браузеры ещё неделю показывали бы старые стили и скрипт при новой разметке.
 */
function at_asset(string $path): string
{
    static $cache = [];
    if (!isset($cache[$path])) {
        $file = ATOUR_ROOT . '/' . ltrim($path, '/');
        $cache[$path] = $path . (is_file($file) ? '?v=' . hash_file('crc32b', $file) : '');
    }
    return $cache[$path];
}

/**
 * @param array<string,mixed> $o key, title, description, canonical, noindex, image,
 *                                keywords, type, published, bodyClass, jsonld (array of arrays)
 */
function at_page_start(array $o = []): void
{
    $key = (string) ($o['key'] ?? trim(at_current_path(), '/') ?: 'home');
    $title = (string) ($o['title'] ?? ATOUR_SITE_NAME);
    $desc = at_truncate(at_strip_html((string) ($o['description'] ?? at_setting('home.metaDescription'))), 300);
    $canonical = at_abs((string) ($o['canonical'] ?? at_current_path()));
    $image = at_abs(at_img_src((string) (($o['image'] ?? '') !== '' ? $o['image'] : '/images/og-default.png')));
    $look = at_look();
    echo '<!DOCTYPE html><html lang="ru" data-style="' . $look['style'] . '" data-theme="' . $look['theme'] . '"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    if (function_exists('sg_head')) {
        at_seo_sync($key, $title, $desc);   // платформа помнит первый увиденный заголовок — подтягиваем изменения
        sg_head($key, $title, $desc);
    } else {
        echo '<title>' . at_e($title) . '</title><meta name="description" content="' . at_e($desc) . '"><link rel="canonical" href="' . at_e($canonical) . '">';
    }
    if (!empty($o['keywords'])) {
        echo '<meta name="keywords" content="' . at_e($o['keywords']) . '">';
    }
    echo '<meta name="robots" content="' . (!empty($o['noindex']) ? 'noindex, follow' : 'index, follow') . '">';
    echo '<meta name="theme-color" content="' . ($look['theme'] === 'dark' ? '#0f1116' : ($look['style'] === 'simple' ? '#ffffff' : '#3559ff')) . '">';
    echo '<meta name="color-scheme" content="' . ($look['theme'] === 'dark' ? 'dark' : 'light') . '">';
    echo '<link rel="icon" href="' . at_asset('/assets/icons/favicon.ico') . '" sizes="16x16 32x32 48x48" type="image/x-icon">';
    foreach ([32, 48, 96, 192] as $size) {
        echo '<link rel="icon" href="' . at_asset('/assets/icons/favicon-' . $size . '.png') . '" sizes="' . $size . 'x' . $size . '" type="image/png">';
    }
    echo '<link rel="icon" href="' . at_asset('/assets/icons/favicon.svg') . '" sizes="any" type="image/svg+xml">';
    echo '<link rel="apple-touch-icon" href="' . at_asset('/assets/icons/favicon-180.png') . '" sizes="180x180"><link rel="manifest" href="' . at_asset('/site.webmanifest') . '">';
    echo '<meta property="og:title" content="' . at_e($title) . '"><meta property="og:description" content="' . at_e($desc) . '">';
    echo '<meta property="og:url" content="' . at_e($canonical) . '"><meta property="og:site_name" content="' . at_e(ATOUR_SITE_NAME) . '">';
    echo '<meta property="og:locale" content="ru_RU"><meta property="og:type" content="' . at_e((string) ($o['type'] ?? 'website')) . '">';
    echo '<meta property="og:image" content="' . at_e($image) . '">';
    if (!empty($o['published'])) {
        echo '<meta property="article:published_time" content="' . at_e($o['published']) . '">';
    }
    echo '<meta name="twitter:card" content="summary_large_image">';
    echo '<link rel="stylesheet" href="/theme.css"><link rel="stylesheet" href="' . at_asset('/assets/style.css') . '">';
    echo '<link rel="stylesheet" href="' . at_asset('/assets/design-fonts.css') . '"><link rel="stylesheet" href="' . at_asset('/assets/design.css') . '">';
    if (is_file(ATOUR_ROOT . '/assets/sitegen-overrides.css')) {
        echo '<link rel="stylesheet" href="/assets/sitegen-overrides.css?v=' . substr((string) hash_file('sha256', ATOUR_ROOT . '/assets/sitegen-overrides.css'), 0, 12) . '">';
    }
    echo '<script>document.documentElement.className+=" js";</script>';
    foreach ((array) ($o['jsonld'] ?? []) as $ld) {
        if (is_array($ld) && $ld) {
            echo at_json_ld($ld);
        }
    }
    echo at_json_ld(at_org_jsonld());
    echo '</head><body class="' . at_e((string) ($o['bodyClass'] ?? '')) . '">';
    at_header();
    echo '<main class="site-main" id="main-content">';
}

function at_page_end(): void
{
    echo '</main>';
    at_footer();
    at_modals();
    echo '<div class="cookie-banner" id="cookie-banner" hidden role="dialog" aria-label="Использование cookie">' . at_icon('cookie', 'ic cookie-ic')
        . '<p>Мы используем cookie, чтобы сайт работал как надо. <a href="/politiks/">Подробнее</a></p>'
        . '<button type="button" class="btn-primary btn-xs" id="cookie-ok">Понятно</button></div>';
    echo '<script src="' . at_asset('/assets/app.js') . '" defer></script><script src="' . at_asset('/assets/forms.js') . '" defer></script><script src="' . at_asset('/assets/design.js') . '" defer></script></body></html>';
}

/** @return array<string,mixed> */
function at_org_jsonld(): array
{
    $s = at_settings();
    $openingHours = [];
    if (preg_match('~(\d{2}:\d{2})\s*(?:до|[–—-])\s*(\d{2}:\d{2})~u', (string) ($s['contacts.hours'] ?? ''), $hours)) {
        $openingHours = ['Mo-Fr ' . $hours[1] . '-' . $hours[2]];
    }
    return [
        '@context'  => 'https://schema.org',
        '@type'     => 'TravelAgency',
        'name'      => $s['site.name'] ?? ATOUR_SITE_NAME,
        'legalName' => $s['site.legalName'] ?? '',
        'url'       => at_abs('/'),
        'logo'      => at_abs('/images/logo.svg'),
        'telephone' => array_values(array_filter([$s['contacts.phone1'] ?? '', $s['contacts.phone2'] ?? ''])),
        'email'     => $s['contacts.email'] ?? '',
        'address'   => ['@type' => 'PostalAddress', 'streetAddress' => 'Цветной бульвар д. 26 с1, помещение 23', 'addressLocality' => 'Москва', 'postalCode' => '127051', 'addressCountry' => 'RU'],
        'geo'       => ['@type' => 'GeoCoordinates', 'latitude' => $s['contacts.mapLat'] ?? '55.7651446', 'longitude' => $s['contacts.mapLng'] ?? '37.6161005'],
        'openingHours' => $openingHours,
        'sameAs'    => array_values(array_filter([$s['social.vk'] ?? '', $s['social.telegram'] ?? ''])),
    ];
}

// --- Шапка -------------------------------------------------------------------------

function at_header(): void
{
    at_design_header();
}

/**
 * Переключатель оформления: сегменты «Яркий / Строгий» и кнопка тёмной темы.
 * Состояние берётся из at_look(), нажатия обрабатывает assets/app.js (cookie +
 * атрибуты на <html>). Тёмная тема включает строгий стиль, яркий — светлую тему.
 */
function at_look_switch(): string
{
    $look = at_look();
    $dark = $look['theme'] === 'dark';
    $h = '<div class="look-switch" role="group" aria-label="Оформление сайта"><div class="look-seg">';
    foreach (['bright' => 'Яркий', 'simple' => 'Строгий'] as $k => $label) {
        $h .= '<button type="button" class="look-btn" data-set-style="' . $k . '" aria-pressed="' . ($look['style'] === $k ? 'true' : 'false') . '">' . $label . '</button>';
    }
    $h .= '</div><button type="button" class="look-theme" data-toggle-theme aria-pressed="' . ($dark ? 'true' : 'false') . '" aria-label="'
        . ($dark ? 'Включить светлую тему' : 'Включить тёмную тему') . '" title="' . ($dark ? 'Светлая тема' : 'Тёмная тема') . '">'
        . at_icon('moon', 'ic ic-moon') . at_icon('sun', 'ic ic-sun') . '</button></div>';
    return $h;
}

// --- Подвал ----------------------------------------------------------------------------

function at_footer(): void
{
    at_design_footer();
}

// --- Модальные окна с формами ------------------------------------------------------------

function at_modal(string $id, string $title, string $body, string $size = 'md'): string
{
    return '<dialog class="modal modal-' . $size . '" id="modal-' . $id . '" aria-labelledby="modal-' . $id . '-title"><div class="modal-in">'
        . '<button type="button" class="modal-close" aria-label="Закрыть" data-close-modal>' . at_icon('x') . '</button>'
        . '<h3 class="modal-title" id="modal-' . $id . '-title">' . at_e($title) . '</h3>' . $body . '</div></dialog>';
}

function at_modals(): void
{
    $path = at_current_path();
    echo at_modal('callback', 'Заказ обратного звонка', at_form_callback($path));
    echo at_modal('request', 'Отправить заявку', at_form_request($path), 'lg');
    echo at_modal('subscribe', 'Подписка на новости', at_form_subscribe());
    echo at_modal('booking', 'Забронировать тур', at_form_booking(), 'lg');
}

// --- Поля форм -------------------------------------------------------------------------------

function at_field(string $label, string $input, string $hint = '', string $class = '', string $name = ''): string
{
    static $number = 0;
    $id = 'at-field-' . (++$number);
    $input = (string) preg_replace('~<(input|textarea|select)\b~', '<$1 id="' . $id . '" aria-describedby="' . $id . '-error' . ($hint !== '' ? ' ' . $id . '-hint' : '') . '"', $input, 1);
    return '<div class="field' . ($class !== '' ? ' ' . $class : '') . '"' . ($name !== '' ? ' data-field="' . at_e($name) . '"' : '') . '>'
        . ($label !== '' ? '<label class="label" for="' . $id . '">' . at_e($label) . (preg_match('~\srequired(?:\s|[=>])~', $input) ? '<span class="field-required" aria-hidden="true">*</span>' : '') . '</label>' : '') . $input
        . ($hint !== '' ? '<p class="hint" id="' . $id . '-hint">' . at_e($hint) . '</p>' : '') . '<p class="field-error" id="' . $id . '-error" hidden></p></div>';
}

function at_input(string $name, array $a = []): string
{
    if (($a['type'] ?? '') === 'tel') $a += ['inputmode' => 'tel', 'autocomplete' => 'tel', 'maxlength' => 30];
    if (($a['type'] ?? '') === 'email') $a += ['inputmode' => 'email', 'autocomplete' => 'email', 'autocapitalize' => 'none', 'spellcheck' => 'false', 'maxlength' => 120];
    $attrs = '';
    foreach ($a as $k => $v) {
        if ($v === false || $v === null) {
            continue;
        }
        $attrs .= $v === true ? ' ' . $k : ' ' . $k . '="' . at_e((string) $v) . '"';
    }
    if (!isset($a['type'])) {
        $attrs .= ' type="text"';
    }
    return '<input class="input" name="' . at_e($name) . '"' . $attrs . '>';
}

function at_textarea(string $name, array $a = []): string
{
    $attrs = '';
    foreach ($a as $k => $v) {
        if ($v === false || $v === null) {
            continue;
        }
        $attrs .= $v === true ? ' ' . $k : ' ' . $k . '="' . at_e((string) $v) . '"';
    }
    return '<textarea class="input textarea" name="' . at_e($name) . '"' . $attrs . '></textarea>';
}

/** @param array<string,string> $options */
/**
 * Выпадающий список. $a['counts'] — число туров на вариант (значение => N):
 * пишется в data-count и в подпись, чтобы выбор не приводил к пустому списку.
 */
function at_select(string $name, array $options, string $value = '', array $a = []): string
{
    $counts = is_array($a['counts'] ?? null) ? $a['counts'] : null;
    $hideEmpty = !empty($a['hideEmpty']);
    unset($a['counts'], $a['hideEmpty']);
    // Свой class дописывается к базовому, а не даёт второй атрибут class
    // (браузер берёт первый — так select-sm у сортировки не применялся).
    $class = 'input select' . (isset($a['class']) ? ' ' . trim((string) $a['class']) : '');
    unset($a['class']);
    $attrs = '';
    foreach ($a as $k => $v) {
        if ($v === false || $v === null) {
            continue;
        }
        $attrs .= $v === true ? ' ' . $k : ' ' . $k . '="' . at_e((string) $v) . '"';
    }
    $html = '<select class="' . at_e($class) . '" name="' . at_e($name) . '"' . $attrs . '>';
    foreach ($options as $k => $label) {
        $k = (string) $k;
        $count = $counts !== null && $k !== '' ? (int) ($counts[$k] ?? 0) : null;
        $html .= '<option value="' . at_e($k) . '"' . ($k === $value ? ' selected' : '')
            . ($count !== null ? ' data-count="' . $count . '"' : '')
            . ($hideEmpty && $count === 0 && $k !== $value ? ' hidden disabled' : '') . '>' . at_e($label)
            . ($count !== null ? ' · ' . $count : '') . '</option>';
    }
    return $html . '</select>';
}

function at_consent(): string
{
    return '<div class="field" data-field="consent"><label class="consent"><input type="checkbox" name="consent" value="on" required data-optional> <span>Я согласен(а) с <a href="/personal-data/" target="_blank">политикой обработки персональных данных</a></span></label><p class="field-error" hidden></p></div>';
}

function at_honeypot(): string
{
    return '<div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off" data-optional></label></div>';
}

function at_form_open(string $kind, string $pageUrl = '', string $class = ''): string
{
    return '<form class="lead-form' . ($class !== '' ? ' ' . $class : '') . '" method="post" action="/lead/" data-ajax novalidate>'
        . '<input type="hidden" name="kind" value="' . at_e($kind) . '"><input type="hidden" name="page_url" value="' . at_e($pageUrl) . '">'
        . at_honeypot() . '<div class="form-message" hidden role="alert"></div>';
}

function at_submit(string $label, string $class = 'btn-primary'): string
{
    return '<button type="submit" class="' . at_e($class) . '" data-pending="Отправляем…">' . at_e($label) . '</button>';
}

function at_form_request(string $pageUrl = '', bool $compact = false): string
{
    return at_form_open('REQUEST', $pageUrl)
        . '<div class="' . ($compact ? 'stack-4' : 'grid-2') . '">'
        . at_field('Ваше имя', at_input('name', ['required' => true, 'autocomplete' => 'name', 'placeholder' => 'Как к вам обращаться']), '', '', 'name')
        . at_field('Телефон', at_input('phone', ['type' => 'tel', 'data-optional' => true, 'autocomplete' => 'tel', 'placeholder' => '+7 (___) ___-__-__', 'inputmode' => 'tel']), '', '', 'phone')
        . at_field('E-mail', at_input('email', ['type' => 'email', 'required' => true, 'autocomplete' => 'email', 'placeholder' => 'name@example.ru']), '', $compact ? '' : 'span-2', 'email')
        . '</div>'
        . at_field('Сообщение', at_textarea('message', ['placeholder' => 'Какой лагерь интересует, возраст ребёнка, желаемые даты…']), '', '', 'message')
        . at_consent() . at_submit('Отправить заявку') . '</form>';
}

function at_form_callback(string $pageUrl = ''): string
{
    return at_form_open('CALLBACK', $pageUrl)
        . at_field('Ваше имя', at_input('name', ['required' => true, 'autocomplete' => 'name', 'placeholder' => 'Как к вам обращаться']), '', '', 'name')
        . at_field('Телефон', at_input('phone', ['type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '+7 (___) ___-__-__', 'inputmode' => 'tel']), '', '', 'phone')
        . at_field('Ваш вопрос', at_textarea('message', ['placeholder' => 'Кратко опишите, чем помочь', 'class' => 'input textarea short']), '', '', 'message')
        . at_consent() . at_submit('Заказать звонок', 'btn-accent') . '</form>';
}

function at_form_subscribe(): string
{
    return at_form_open('SUBSCRIBE')
        . at_field('Ваше имя', at_input('name', ['autocomplete' => 'name']), '', '', 'name')
        . at_field('E-mail', at_input('email', ['type' => 'email', 'required' => true, 'autocomplete' => 'email', 'placeholder' => 'name@example.ru']), '', '', 'email')
        . at_consent() . at_submit('Подписаться', 'btn-sun') . '</form>';
}

/** Форма брони живёт в модалке: тур и смену подставляет виджет «Смены и цены». */
function at_form_booking(): string
{
    return at_form_open('BOOKING', '', 'booking-form')
        . '<input type="hidden" name="tour_id" value=""><input type="hidden" name="shift_name" value=""><input type="hidden" name="amount" value="">'
        . '<div class="booking-summary"><div class="booking-summary-title" data-booking="title"></div><div class="booking-summary-shift" data-booking="shift"></div><div class="booking-summary-price" data-booking="price"></div></div>'
        . '<div class="grid-2">'
        . at_field('Ваше имя', at_input('name', ['required' => true, 'autocomplete' => 'name']), '', '', 'name')
        . at_field('Телефон', at_input('phone', ['type' => 'tel', 'data-optional' => true, 'autocomplete' => 'tel', 'placeholder' => '+7 (___) ___-__-__', 'inputmode' => 'tel']), '', '', 'phone')
        . at_field('E-mail', at_input('email', ['type' => 'email', 'required' => true, 'autocomplete' => 'email']), '', 'span-2', 'email')
        . '</div>'
        . at_field('Комментарий', at_textarea('message', ['placeholder' => 'Возраст ребёнка, количество путёвок, пожелания', 'class' => 'input textarea short']), '', '', 'message')
        . at_consent() . at_submit('Отправить заявку на бронирование') . '</form>';
}

function at_form_agency(): string
{
    return at_form_open('AGENCY', '/agentstvam/')
        . '<div class="grid-2">'
        . at_field('Название агентства', at_input('company', ['required' => true, 'autocomplete' => 'organization']), '', 'span-2', 'company')
        . at_field('Контактное лицо', at_input('name', ['required' => true, 'autocomplete' => 'name']), '', '', 'name')
        . at_field('Телефон', at_input('phone', ['type' => 'tel', 'required' => true, 'autocomplete' => 'tel', 'placeholder' => '+7 (___) ___-__-__']), '', '', 'phone')
        . at_field('E-mail', at_input('email', ['type' => 'email', 'required' => true, 'autocomplete' => 'email']), '', 'span-2', 'email')
        . '</div>'
        . at_field('Комментарий', at_textarea('message', ['placeholder' => 'Какие направления интересуют', 'class' => 'input textarea short']), '', '', 'message')
        . at_consent() . at_submit('Получить доступ') . '</form>';
}

function at_form_review(?int $tourId = null): string
{
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= '<button type="button" class="star-btn' . ($i <= 5 ? ' on' : '') . '" data-star="' . $i . '" aria-label="' . $i . ' из 5">' . at_icon('star') . '</button>';
    }
    return at_form_open('REVIEW', at_current_path())
        . ($tourId ? '<input type="hidden" name="tour_id" value="' . $tourId . '">' : '')
        . '<input type="hidden" name="rating" value="5">'
        . at_field('Оценка', '<div class="stars-pick" role="radiogroup" aria-label="Оценка">' . $stars . '</div>')
        . at_field('Ваше имя', at_input('name', ['required' => true, 'autocomplete' => 'name']), '', '', 'name')
        . at_field('Отзыв', at_textarea('text', ['required' => true, 'placeholder' => 'Расскажите, как прошёл отдых']), '', '', 'text')
        . at_consent() . at_submit('Отправить отзыв') . '</form>';
}

function at_form_payment(bool $cashback = false, string $amount = ''): string
{
    if (!at_payment_enabled()) return '';
    $html = '<form class="pay-form" method="post" action="' . ($cashback ? '/pay/cashback/' : '/pay/') . '" novalidate>' . at_honeypot()
        . '<div class="form-message" hidden role="alert"></div><div class="grid-2">'
        . at_field('Номер договора *', at_input('order_number', ['required' => true, 'placeholder' => 'Например, 1234/26']), '', '', 'order_number')
        . at_field('Сумма, ₽ *', at_input('amount', ['type' => 'number', 'min' => '1', 'step' => '1', 'required' => true, 'inputmode' => 'numeric', 'placeholder' => '72900', 'value' => $amount]), '', '', 'amount')
        . at_field('Фамилия Имя Отчество *', at_input('full_name', ['required' => true, 'autocomplete' => 'name']), '', 'span-2', 'full_name')
        . at_field('Телефон', at_input('phone', ['type' => 'tel', 'autocomplete' => 'tel', 'placeholder' => '+7 (___) ___-__-__', 'data-optional' => true]), '', '', 'phone')
        . at_field('Электронная почта *', at_input('email', ['type' => 'email', 'required' => true, 'autocomplete' => 'email']), 'На неё придёт чек', '', 'email')
        . '</div>';
    if ($cashback) {
        $html .= '<label class="consent"><input type="checkbox" name="is_mir_card" value="on"> <span>Я произвожу оплату картой, зарегистрированной в <a href="https://privetmir.ru/" target="_blank" rel="noopener">программе лояльности МИР</a></span></label>';
    }
    return $html . at_consent() . '<p class="hint">* Поля, отмеченные звёздочкой, обязательны для заполнения.</p>'
        . at_submit('Оплатить', 'btn-accent') . '</form>';
}

// --- Компоненты ------------------------------------------------------------------------------

/** @param array<int,array{name:string,href?:string}> $items */
function at_breadcrumbs(array $items): string
{
    $all = array_merge([['name' => 'Главная', 'href' => '/']], $items);
    $ld = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => []];
    $html = '<nav class="crumbs" aria-label="Хлебные крошки"><ol>';
    foreach ($all as $i => $c) {
        $last = $i === count($all) - 1;
        $item = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name']];
        if (!empty($c['href'])) {
            $item['item'] = at_abs($c['href']);
        }
        $ld['itemListElement'][] = $item;
        $html .= '<li>' . ($i > 0 ? at_icon('chevron-right', 'ic ic-xs crumb-sep') : '');
        if (!empty($c['href']) && !$last) {
            $html .= '<a href="' . at_e($c['href']) . '">' . ($i === 0 ? at_icon('home', 'ic ic-xs') : '') . at_e($c['name']) . '</a>';
        } else {
            $html .= '<span aria-current="page">' . at_e($c['name']) . '</span>';
        }
        $html .= '</li>';
    }
    return $html . '</ol></nav>' . at_json_ld($ld);
}

function at_section_title(string $accent, string $text, string $subtitle = '', string $class = ''): string
{
    return '<div class="section-head' . ($class !== '' ? ' ' . $class : '') . '"><h2 class="section-title">' . ($accent !== '' ? '<span class="accent">' . at_e($accent) . '</span> ' : '') . at_e($text) . '</h2>'
        . ($subtitle !== '' ? '<p class="section-sub">' . at_e($subtitle) . '</p>' : '') . '</div>';
}

function at_badge(string $tone, string $html): string
{
    return '<span class="chip chip-' . at_e($tone) . '">' . $html . '</span>';
}

function at_stars(int $n): string
{
    $html = '<span class="stars" aria-label="' . $n . ' из 5">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= at_icon('star', 'ic ic-sm' . ($i <= $n ? ' on' : ''));
    }
    return $html . '</span>';
}

function at_price_label(array $t): string
{
    if (!empty($t['price_min'])) {
        return 'от ' . at_price((int) $t['price_min']);
    }
    $pt = trim((string) ($t['price_text'] ?? ''));
    if ($pt !== '' && !preg_match('~запрос~iu', $pt)) {
        return $pt;
    }
    return 'Цена по запросу';
}

/** Карточка тура в списках. */
function at_tour_card(array $t, bool $priority = false, bool $showExcerpt = false): string
{
    return at_design_tour_card($t, $priority, $showExcerpt);
}

/** @param array<int,array<string,mixed>> $items */
function at_tour_list(array $items, string $emptyText = 'По вашему запросу туров пока нет. Попробуйте изменить фильтры или напишите нам — подберём вариант вручную.'): string
{
    if (!$items) {
        return '<div class="empty-card"><div class="empty-icon">🏕️</div><p>' . at_e($emptyText) . '</p></div>';
    }
    $html = '<div class="tour-grid">';
    foreach ($items as $i => $t) {
        $html .= at_tour_card($t, $i < 3);
    }
    return $html . '</div>';
}

function at_post_card(array $p): string
{
    return at_design_post_card($p);
}

function at_pagination(int $page, int $pages, callable $hrefFor, string $class = ''): string
{
    if ($pages <= 1) {
        return '';
    }
    $set = array_unique(array_filter([1, $pages, $page, $page - 1, $page + 1, $page - 2, $page + 2], static fn(int $p): bool => $p >= 1 && $p <= $pages));
    sort($set);
    $html = '<nav class="pagination' . ($class !== '' ? ' ' . $class : '') . '" aria-label="Пагинация">';
    if ($page > 1) {
        $html .= '<a href="' . at_e($hrefFor($page - 1)) . '" rel="prev" class="btn-outline pg-btn" aria-label="Предыдущая страница">' . at_icon('chevron-left') . '</a>';
    }
    $prev = 0;
    foreach ($set as $p) {
        if ($prev && $p - $prev > 1) {
            $html .= '<span class="pg-gap">…</span>';
        }
        $html .= $p === $page
            ? '<span class="btn-primary pg-btn" aria-current="page">' . $p . '</span>'
            : '<a href="' . at_e($hrefFor($p)) . '" class="btn-outline pg-btn">' . $p . '</a>';
        $prev = $p;
    }
    if ($page < $pages) {
        $html .= '<a href="' . at_e($hrefFor($page + 1)) . '" rel="next" class="btn-outline pg-btn" aria-label="Следующая страница">' . at_icon('chevron-right') . '</a>';
    }
    return $html . '</nav>';
}

function at_help_card(string $title = 'Поможем выбрать'): string
{
    $s = at_settings();
    $phone = $s['contacts.phone1'] ?? '';
    return '<div class="help-card"><div class="blob blob-sun"></div><div class="help-in"><div class="help-title">' . at_e($title) . '</div>'
        . '<p>Позвоните или оставьте заявку — менеджер ответит на вопросы и подберёт подходящую программу.</p>'
        . '<a href="' . at_e(at_tel_href($phone)) . '" class="help-phone">' . at_icon('phone', 'ic coral') . at_e($phone) . '</a>'
        . '<div class="help-hours">' . at_hours_html((string) ($s['contacts.hours'] ?? '')) . '</div>'
        . '<div class="help-actions"><button type="button" class="btn-primary btn-block" data-modal="request">Отправить заявку</button><button type="button" class="btn-outline btn-block" data-modal="callback">Заказать звонок</button></div></div></div>';
}

function at_rich(string $html, string $class = ''): string
{
    require_once __DIR__ . '/source-media.php';
    return '<div class="rich' . ($class !== '' ? ' ' . $class : '') . '">' . at_source_media_html(at_year($html)) . '</div>';
}

/** Блок «Отзывы»: рейтинг площадок + карточки + форма своего отзыва. */
function at_reviews_block(array $platforms, array $reviews, string $intro = '', ?int $tourId = null, bool $compact = false): string
{
    $orgId = at_yandex_org_id();
    $yandexUrl = $orgId !== '' ? 'https://yandex.ru/profile/' . $orgId : '';
    if (at_setting('reviews.hideGoogle') === '1') {
        $platforms = array_filter($platforms, static fn(array $p): bool => !preg_match('~google|гугл~ui', (string) ($p['name'] ?? '') . ' ' . (string) ($p['url'] ?? '')));
        $reviews = array_filter($reviews, static fn(array $r): bool => ($r['source'] ?? '') !== 'GOOGLE');
    }
    $html = '<section class="' . ($compact ? 'reviews-compact' : 'container-site section-pad') . '">';
    if (!$compact) {
        $html .= at_section_title('Отзывы', '', $intro !== '' ? $intro : 'Мы публикуем только живые отзывы родителей.');
    }
    $html .= '<div class="reviews-grid"><div class="reviews-side"><div class="panel"><div class="panel-label">Наш рейтинг</div><div class="stack-3">';
    foreach ($platforms as $p) {
        if ($yandexUrl !== '' && preg_match('~yandex|яндекс~ui', (string) ($p['name'] ?? '') . ' ' . (string) ($p['url'] ?? ''))) { $p['url'] = $yandexUrl; }
        $html .= '<a href="' . at_e($p['url']) . '" target="_blank" rel="noopener" class="platform-row"><span class="platform-logo">' . (!empty($p['logo']) ? at_img($p['logo'], $p['name']) : '') . '</span>'
            . '<span class="platform-body"><span class="platform-name">' . at_e($p['name']) . '</span>' . at_stars((int) round((float) $p['rating'])) . '</span>'
            . '<span class="platform-rating">' . at_e(str_replace('.', ',', rtrim(rtrim((string) $p['rating'], '0'), '.'))) . '<span class="muted"> / ' . (int) $p['max_rating'] . '</span></span></a>';
    }
    $html .= '</div></div><details class="panel review-details"><summary>+ Оставить свой отзыв</summary><div class="mt-4">' . at_form_review($tourId) . '</div></details></div>';
    $html .= '<div class="reviews-cards">';
    foreach ($reviews as $r) {
        $src = (string) ($r['source'] ?? 'SITE');
        if ($src === 'YANDEX' && $yandexUrl !== '') { $r['source_url'] = $yandexUrl; }
        $html .= '<article class="panel review-card"><div class="review-head"><div class="review-author">' . at_e($r['author']) . '</div>' . at_stars((int) $r['rating']) . '</div>'
            . '<p class="review-text">' . at_e($r['body']) . '</p><div class="review-foot"><span>' . at_e(at_date_short($r['published_at'])) . '</span>'
            . (!empty($r['source_url'])
                ? '<a href="' . at_e($r['source_url']) . '" target="_blank" rel="noopener">' . at_e(ATOUR_REVIEW_SOURCES[$src] ?? $src) . ' ' . at_icon('external-link', 'ic ic-xs') . '</a>'
                : '<span>' . ($src === 'SITE' ? 'С сайта' : at_e(ATOUR_REVIEW_SOURCES[$src] ?? $src)) . '</span>')
            . '</div></article>';
    }
    return $html . '</div></div></section>';
}

/** Идентификатор организации вместо метки по устаревшим координатам. */
function at_yandex_org_id(): string
{
    $id = trim(at_setting('contacts.yandexOrgId'));
    return preg_match('~^[1-9][0-9]{5,19}$~D', $id) ? $id : '';
}

function at_map_block(bool $withTitle = true): string
{
    $s = at_settings();
    $lat = $s['contacts.mapLat'] ?? '55.7651446';
    $lng = $s['contacts.mapLng'] ?? '37.6161005';
    $src = 'https://yandex.ru/map-widget/v1/?ll=' . rawurlencode($lng) . '%2C' . rawurlencode($lat) . '&z=16&pt=' . rawurlencode($lng) . '%2C' . rawurlencode($lat) . '%2Cpm2rdl';
    $orgId = at_yandex_org_id();
    if ($orgId !== '') { $src = 'https://yandex.ru/map-widget/v1/?oid=' . $orgId . '&ol=biz&z=16'; }
    $row = static fn(string $icon, string $label, string $body): string => '<div class="map-row">' . at_icon($icon, 'ic ' . $icon) . '<div><div class="map-label">' . $label . '</div><div class="map-value">' . $body . '</div></div></div>';
    return '<section class="container-site section-pad">' . ($withTitle ? at_section_title('Мы', 'на карте') : '')
        . '<div class="map-grid"><div class="panel stack-4">'
        . $row('map-pin', 'Адрес офиса', at_e($s['contacts.address'] ?? ''))
        . $row('phone', 'Телефоны', '<a href="' . at_e(at_tel_href($s['contacts.phone1'] ?? '')) . '">' . at_e($s['contacts.phone1'] ?? '') . '</a><br><a href="' . at_e(at_tel_href($s['contacts.phone2'] ?? '')) . '">' . at_e($s['contacts.phone2'] ?? '') . '</a>')
        . $row('mail', 'E-mail', '<a href="mailto:' . at_e($s['contacts.email'] ?? '') . '">' . at_e($s['contacts.email'] ?? '') . '</a>')
        . $row('clock', 'Режим работы', at_hours_html((string) ($s['contacts.hours'] ?? '')))
        . ($orgId !== '' ? '<a class="link-strong" href="https://yandex.ru/profile/' . at_e($orgId) . '" target="_blank" rel="noopener">Открыть в Яндекс Картах ' . at_icon('external-link', 'ic ic-xs') . '</a>' : '')
        . '</div><div class="map-frame"><iframe src="' . at_e($src) . '" title="Карта проезда" loading="lazy" allowfullscreen></iframe></div></div></section>';
}

/** Шапка внутренней страницы: крошки + H1 (+ лид), опционально с боковой колонкой. */
function at_page_shell_open(string $h1Key, string $title, array $crumbs, string $lead = '', bool $aside = true, bool $wide = false): void
{
    echo '<div class="container-site page-top">' . at_breadcrumbs($crumbs);
    echo '<h1 class="page-h1">' . (function_exists('sg_h1') ? sg_h1($h1Key, $title) : at_e($title)) . '</h1>';
    if ($lead !== '') {
        echo '<p class="page-lead">' . at_e($lead) . '</p>';
    }
    echo '<div class="' . ($aside ? 'page-cols' : ($wide ? 'page-wide' : 'page-narrow')) . '"><div class="page-main">';
}

function at_page_shell_close(bool $aside = true, string $asideHtml = ''): void
{
    echo '</div>';
    if ($aside) {
        echo '<aside class="page-aside">' . ($asideHtml !== '' ? $asideHtml : at_help_card()) . '</aside>';
    }
    echo '</div></div>';
}

// --- Ещё компоненты: фильтры каталога, сортировка, возраст, H1 -------------------------

function at_h1(string $key, string $default): string
{
    return function_exists('sg_h1') ? sg_h1($key, $default) : at_e($default);
}

/** @return array<string,string> Возраста из настроек каталога. */
function at_age_options(bool $withAdult = false): array
{
    $out = [];
    [$min, $max] = at_age_limits();
    for ($a = $min; $a <= $max; $a++) {
        $out[(string) $a] = $a . ' ' . at_plural($a, ['год', 'года', 'лет']);
    }
    if ($withAdult && $max <= 18) {
        $out['21'] = 'Старше 18';
    }
    return $out;
}

/**
 * Боковая панель каталога: поиск с подсказками, тип, регион, характеристика,
 * возраст, сезон — у каждого варианта число туров при остальных условиях.
 * GET-форма ведёт в общий каталог; с JS список справа обновляется сам.
 *
 * @param array<string,string> $values текущие значения
 * @param array<string,array<string,int>> $facets из at_tour_facets
 */
function at_tour_filters(array $values, array $facets = [], int $total = 0, string $action = '/tour/'): string
{
    $regions = ['' => 'Все регионы'] + array_column(at_regions(), 'name', 'slug');
    $tags = ['' => 'Все характеристики'] + array_column(at_tags(), 'label', 'slug');
    $html = '<form action="' . at_e($action) . '" method="get" class="side-filters panel" aria-label="Подбор тура" data-catalog-form>'
        . '<div class="side-title">' . at_icon('sliders', 'ic brand') . ' Подобрать тур<a href="' . at_e($action) . '" class="side-reset" data-catalog-reset>сбросить</a></div>'
        . '<div class="side-q" data-suggest-wrap>' . at_input('q', ['value' => (string) ($values['q'] ?? ''), 'placeholder' => 'Название, место, программа…', 'aria-label' => 'Поиск по турам', 'autocomplete' => 'off', 'data-optional' => true, 'data-suggest' => true])
        . '<div class="suggest" data-suggest-list hidden></div></div>'
        . '<label class="side-field"><span class="label">Тип отдыха</span>' . at_select('type', ['' => 'Все типы'] + at_catalog_types(), (string) ($values['type'] ?? ''), ['counts' => $facets['type'] ?? null, 'hideEmpty' => true]) . '</label>'
        . '<label class="side-field"><span class="label">Регион</span>' . at_select('region', $regions, (string) ($values['region'] ?? ''), ['counts' => $facets['region'] ?? null, 'hideEmpty' => true]) . '</label>'
        . '<label class="side-field"><span class="label">Характеристика</span>' . at_select('tag', $tags, (string) ($values['tag'] ?? ''), ['counts' => $facets['tag'] ?? null, 'hideEmpty' => true]) . '</label>'
        . '<label class="side-field"><span class="label">Возраст ребёнка</span>' . at_select('age', ['' => 'Любой возраст'] + at_age_options(true), (string) ($values['age'] ?? ''), ['counts' => $facets['age'] ?? null, 'hideEmpty' => true]) . '</label>'
        . '<label class="side-field"><span class="label">Сезон</span>' . at_select('season', ['' => 'Все сезоны'] + ATOUR_SEASONS, (string) ($values['season'] ?? ''), ['counts' => $facets['season'] ?? null, 'hideEmpty' => true]) . '</label>'
        . '<button type="submit" class="btn-primary btn-block side-submit" data-catalog-submit>' . at_icon('search') . ' <span data-catalog-submit-label>Показать туры</span></button>'
        . '</form>';
    return $html;
}

/**
 * «Быстрый выбор»: самые наполненные регионы, характеристики и сезоны с
 * числом туров — один клик вместо заполнения формы.
 *
 * @param array<string,array<string,int>> $facets
 * @param array<string,string> $values текущие значения (выбранное подсвечивается)
 */
function at_catalog_hints(array $facets, array $values): string
{
    $chips = [];
    $names = ['region' => array_column(at_regions(), 'name', 'slug'), 'tag' => array_column(at_tags(), 'label', 'slug'), 'season' => ATOUR_SEASONS, 'type' => at_catalog_types()];
    foreach (['season' => 4, 'region' => 5, 'tag' => 6, 'type' => 3] as $dim => $take) {
        $items = $facets[$dim] ?? [];
        arsort($items);
        foreach (array_slice($items, 0, $take, true) as $slug => $n) {
            if ($n <= 0 || !isset($names[$dim][$slug])) {
                continue;
            }
            $on = ($values[$dim] ?? '') === (string) $slug;
            $chips[] = '<a href="/tour/?' . http_build_query([$dim => $slug]) . '" class="chip chip-hint' . ($on ? ' is-on' : '') . '" data-hint="' . at_e($dim) . '" data-value="' . at_e((string) $slug) . '">'
                . at_e($names[$dim][$slug]) . '<b>' . (int) $n . '</b></a>';
        }
    }
    if (!$chips) {
        return '';
    }
    return '<div class="side-hints"><div class="panel-label">' . at_icon('sparkles', 'ic sun') . ' Быстрый выбор</div><div class="cluster" data-catalog-hints>' . implode('', $chips) . '</div></div>';
}

/**
 * Список результатов каталога с шапкой (счётчик, сортировка, вид) — общий для
 * /tour/ и SEO-страниц. При запросе с JS (X-Requested-With: fetch) отдаёт JSON
 * с готовой разметкой и фасетами вместо страницы.
 *
 * @param array{items:array<int,array<string,mixed>>,total:int,page:int,pages:int} $result
 * @param array<string,array<string,int>> $facets
 */
function at_catalog_results(array $result, array $facets, string $sort, callable $hrefFor, string $chipsText = '', string $resetHref = '/tour/', array $values = [], array $meta = []): string
{
    $total = (int) $result['total'];
    $count = 'Найдено <b>' . $total . '</b> ' . at_plural($total, ['тур', 'тура', 'туров'])
        . ($chipsText !== '' ? ' <span class="muted">по запросу: ' . at_e($chipsText) . ' · <a href="' . at_e($resetHref) . '" class="link-strong" data-catalog-reset>сбросить</a></span>' : '');
    // Мобильный выбор пересчитывает варианты, не заменяя карточки и не закрывая панель.
    if (at_is_fetch() && (string) ($_GET['_facets'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['facets' => $facets, 'total' => $total], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    $list = at_tour_list($result['items']) . at_pagination((int) $result['page'], (int) $result['pages'], $hrefFor);
    if (at_is_fetch()) {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'heading' => $meta['heading'] ?? '',
            'lead' => $meta['lead'] ?? '',
            'mode' => $meta['mode'] ?? 'catalog',
            'total'  => $total,
            'count'  => $count,
            'html'   => $list,
            'facets' => $facets,
            'label'  => 'Показать ' . $total . ' ' . at_plural($total, ['тур', 'тура', 'туров']),
            'hints'  => at_catalog_hints($facets, $values ?: at_parse_filters($_GET)['values']),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    return '<div class="list-head"><button type="button" class="btn-outline catalog-side-toggle" data-side-toggle aria-expanded="false" aria-controls="catalog-filters">' . at_icon('sliders') . ' Фильтры</button>'
        . '<div class="list-tools">' . at_sort_select($sort)
        . '<div class="view-toggle" role="group" aria-label="Вид списка"><button type="button" class="view-btn is-on" data-view="grid" aria-pressed="true" aria-label="Плитка">' . at_icon('grid') . '</button><button type="button" class="view-btn" data-view="rows" aria-pressed="false" aria-label="Список">' . at_icon('rows') . '</button></div></div></div>'
        . '<div class="catalog-results" id="catalog-results" data-catalog-results aria-live="polite">' . $list . '</div>';
}

/**
 * Загрузки CMS и внешние адреса передаём обычному обработчику изображений.
 * Для отсутствующего локального файла (например images/tours/pekin/banner.jpg)
 * сохраняем прежний широкий баннер из той же папки (banner-2025.webp).
 */
function at_banner_src(string $src): string
{
    $src = trim($src);
    if (preg_match('~^/media\.php\?id=[1-9][0-9]*$~D', $src) || preg_match('~^https?://~i', $src)) {
        return $src;
    }
    if ($src !== '' && is_file(ATOUR_ROOT . $src)) {
        return $src;
    }
    $alt = $src !== '' ? preg_replace('~/[^/]+$~', '/banner-2025.webp', $src) : null;
    return ($alt !== null && is_file(ATOUR_ROOT . $alt)) ? $alt : ATOUR_PLACEHOLDER;
}

/**
 * Атрибуты width/height для локальной картинки — чтобы место под неё было
 * зарезервировано до загрузки и страница не прыгала (широкие баннеры).
 *
 * @return array<string,int>
 */
function at_img_dims(string $src): array
{
    $file = ATOUR_ROOT . $src;
    if ($src === '' || $src[0] !== '/' || str_contains($src, '..') || !is_file($file)) {
        return [];
    }
    $info = @getimagesize($file);
    return is_array($info) && $info[0] > 0 ? ['width' => (int) $info[0], 'height' => (int) $info[1]] : [];
}

/** Запрос пришёл от скрипта сайта (fetch), а не от браузера за страницей. */
function at_is_fetch(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
}

/** Выбор сортировки: с JS — переход по смене значения, без JS — кнопка «Ок». */
function at_sort_select(string $sort): string
{
    $options = ['default' => 'По популярности', 'price_asc' => 'Сначала дешевле', 'price_desc' => 'Сначала дороже', 'new' => 'Сначала новые'];
    $html = '<form method="get" class="sort-form" data-sort-form>';
    foreach ($_GET as $k => $v) {
        if (in_array($k, ['sort', 'page'], true) || is_array($v)) {
            continue;
        }
        $html .= '<input type="hidden" name="' . at_e((string) $k) . '" value="' . at_e((string) $v) . '">';
    }
    $html .= '<label class="sort-label"><span>Сортировка:</span>' . at_select('sort', $options, $sort, ['class' => 'input select select-sm', 'aria-label' => 'Сортировка', 'data-sort' => true]) . '</label>'
        . '<button type="submit" class="btn-outline btn-xs sort-submit">Ок</button></form>';
    return $html;
}

/** Виджет «Смены и цены» карточки тура (данные для формы брони берёт app.js). */
function at_booking_widget(array $tour): string
{
    $shifts = $tour['shifts'] ?? [];
    $html = '<div class="booking panel" data-booking-widget data-tour-id="' . (int) $tour['id'] . '" data-tour-title="' . at_e($tour['title']) . '">'
        . '<div class="panel-label">' . at_icon('calendar', 'ic brand') . ' Смены и цены</div>';
    $first = $shifts[0] ?? null;
    if ($shifts) {
        $groups = [];
        foreach ($shifts as $sh) {
            $groups[(string) ($sh['group_name'] ?? '')][] = $sh;
        }
        // Одна смена — показываем её текстом; список остаётся скрытым для скрипта (цена, подпись в заявке).
        $single = count($shifts) === 1;
        $html .= '<select class="input select booking-select" aria-label="Выберите смену"' . ($single ? ' hidden' : '') . '>';
        foreach ($groups as $g => $items) {
            if ($g !== '') {
                $html .= '<optgroup label="' . at_e($g) . '">';
            }
            foreach ($items as $sh) {
                $dates = at_shift_dates($sh);
                $priceText = at_shift_price($sh);
                [$avail, $availText] = at_shift_avail($sh);
                $label = ($g !== '' ? $g . ': ' : '') . $sh['name'] . ($dates !== '' ? ', ' . $dates : '');
                // Смена без мест остаётся в списке, но выбрать её нельзя — менеджер видит спрос по звонкам.
                $html .= '<option value="' . (int) $sh['id'] . '" data-price="' . (int) ($sh['price'] ?? 0) . '" data-price-text="' . at_e($priceText)
                    . '" data-dates="' . at_e($dates) . '" data-label="' . at_e($label) . '" data-avail="' . at_e($avail) . '" data-avail-text="' . at_e($availText) . '"' . ($avail === 'SOLD_OUT' ? ' disabled' : '') . '>'
                    . at_e($sh['name']) . ($priceText !== '' ? ' — ' . at_e($priceText) : '') . ($availText !== '' ? ' (' . $availText . ')' : '') . '</option>';
            }
            if ($g !== '') {
                $html .= '</optgroup>';
            }
        }
        $html .= '</select>';
        $firstDates = at_shift_dates($first);
        [$firstAvail, $firstAvailText] = at_shift_avail($first);
        $availChip = $firstAvailText !== '' ? ' <span class="booking-avail is-' . at_e(strtolower($firstAvail)) . '">' . $firstAvailText . '</span>' : '';
        if ($single) {
            $meta = implode(' · ', array_filter([(string) ($first['group_name'] ?? ''), $firstDates]));
            $html .= '<div class="booking-shift"><div class="booking-shift-name">' . at_e($first['name']) . $availChip . '</div>'
                . ($meta !== '' ? '<div class="booking-shift-meta">' . at_e($meta) . '</div>' : '') . '</div>';
        } else {
            $html .= '<div class="booking-shift-meta booking-shift-dates" data-booking-dates' . ($firstDates === '' && $availChip === '' ? ' hidden' : '') . '>' . at_e($firstDates) . $availChip . '</div>';
        }
    }
    $price = (int) ($first['price'] ?? 0);
    $priceText = trim((string) ($tour['price_text'] ?? ''));
    $shiftPrice = $first ? at_shift_price($first) : '';
    $priceHtml = $shiftPrice !== '' ? at_e($shiftPrice)
        : (!empty($tour['price_min']) ? 'от ' . at_e(at_price((int) $tour['price_min']))
        : (($priceText !== '' && !preg_match('~запрос~iu', $priceText)) ? at_e($priceText) : 'по запросу'));
    $note = trim((string) ($tour['price_note'] ?? ''));
    $html .= '<div class="booking-price-box"><div class="price-label">Стоимость</div><div class="booking-price" data-booking-price data-fallback="' . at_e(strip_tags($priceHtml)) . '">' . $priceHtml . '</div>'
        . '<div class="booking-note" data-booking-note' . ($price > 0 ? ' hidden' : '') . '>Точную стоимость уточнит менеджер</div>'
        . ($note !== '' ? '<div class="booking-note booking-note-fixed">* ' . at_e($note) . '</div>' : '') . '</div>'
        . '<div class="stack-2">'
        . '<button type="button" class="btn-primary btn-block" data-modal="booking" data-book>Отправить заявку</button>'
        . (at_payment_enabled() ? '<a href="/pay/' . ($price > 0 ? '?amount=' . $price : '') . '" class="btn-outline btn-block" data-pay-link>' . at_icon('credit-card') . ' Оплатить онлайн</a>' : '')
        . '<button type="button" class="btn-ghost btn-block" data-modal="callback">' . at_icon('phone') . ' Заказать звонок</button>'
        . '<button type="button" class="btn-ghost btn-block btn-print" data-print>' . at_icon('printer') . ' Версия для печати</button>'
        . '</div><p class="booking-hint">Бронирование бесплатно и ни к чему не обязывает. Менеджер подтвердит наличие мест.</p></div>';
    return $html;
}
