<?php
/**
 * А-тур (a-deti.ru) — сайт туроператора по детскому отдыху.
 *
 * Единый роутер: адреса сохранены как на исходном сайте (все со слэшем в
 * конце), сервер платформы отдаёт index.php для любого пути без файла, а
 * путь читается из REQUEST_URI. Страницы лежат в inc/pages/, общие функции —
 * в inc/init.php (подключается платформой ко всем страницам), разметка — в
 * inc/layout.php, приём заявок — в inc/forms.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';
require_once __DIR__ . '/inc/layout.php';
require_once __DIR__ . '/inc/forms.php';

$uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$path = rawurldecode((string) (parse_url($uri, PHP_URL_PATH) ?: '/'));
$path = '/' . ltrim((string) preg_replace('~/{2,}~', '/', $path), '/');
$qs   = (string) ($_SERVER['QUERY_STRING'] ?? '');
$withQs = static fn(string $to): string => $to . ($qs !== '' ? (str_contains($to, '?') ? '&' : '?') . $qs : '');

// Блокируем и прямой POST, и все старые адреса оплаты до редиректов и показа форм.
if (!at_payment_enabled() && preg_match('~^/pay(?:/|$)~i', $path)) {
    require __DIR__ . '/inc/pages/payment-paused.php';
    exit;
}

// --- Старые адреса Битрикса → новые (301) ------------------------------------
if ($path === '/index.php') {
    at_redirect($withQs('/'), 301);
}
if (preg_match('~^/news/([^/]+)\.html$~', $path, $m)) {
    at_redirect('/novosti/' . $m[1] . '/', 301);
}
if ($path === '/news' || $path === '/news/') {
    at_redirect('/novosti/', 301);
}
if ($path === '/personal_data.php') {
    at_redirect('/personal-data/', 301);
}
if ($path === '/pay/cacheback.php') {
    at_redirect('/pay/cashback/', 301);
}
if (str_starts_with($path, '/tour/filter/type-is-')) {
    at_redirect('/tour/', 301);
}
if ($path === '/admin' || str_starts_with($path, '/admin/')) {
    at_redirect('/site-admin', 302);
}

// --- Канонический вид: слэш в конце у всех страниц (как на оригинале) ----------
if ($path !== '/' && $path !== '/sitemap.xml' && !str_ends_with($path, '/')) {
    if (preg_match('~\.[a-z0-9]{2,5}$~i', $path)) {
        require __DIR__ . '/inc/pages/notfound.php';
        exit;
    }
    at_redirect($withQs($path . '/'), 301);
}

// --- Маршруты ---------------------------------------------------------------------
if ($path === '/') {
    require __DIR__ . '/inc/pages/home.php';
} elseif ($path === '/lead/') {
    at_handle_lead();
} elseif ($path === '/tour/' || $path === '/tour/filter/') {
    require __DIR__ . '/inc/pages/catalog.php';
} elseif (preg_match('~^/tour/filter/([a-z0-9_\-/]+)/$~i', $path, $m)) {
    $filterPath = trim($m[1], '/');
    require __DIR__ . '/inc/pages/filter.php';
} elseif (preg_match('~^/tour/([a-z0-9_\-]+)/$~i', $path, $m)) {
    $slug = $m[1];
    require __DIR__ . '/inc/pages/tour.php';
} elseif ($path === '/novosti/' || $path === '/stati/') {
    $kind = $path === '/novosti/' ? 'NEWS' : 'ARTICLE';
    require __DIR__ . '/inc/pages/posts.php';
} elseif (preg_match('~^/(novosti|stati)/([^/]+)/$~', $path, $m)) {
    $kind = $m[1] === 'novosti' ? 'NEWS' : 'ARTICLE';
    $slug = $m[2];
    require __DIR__ . '/inc/pages/post.php';
} elseif ($path === '/search/') {
    require __DIR__ . '/inc/pages/search.php';
} elseif ($path === '/pay/' || $path === '/pay/cashback/') {
    $cashback = $path === '/pay/cashback/';
    require __DIR__ . '/inc/pages/pay.php';
} elseif ($path === '/pay/success/') {
    require __DIR__ . '/inc/pages/pay-success.php';
} elseif ($path === '/kak-nas-nayti/') {
    require __DIR__ . '/inc/pages/contacts.php';
} elseif ($path === '/agentstvam/') {
    require __DIR__ . '/inc/pages/agency.php';
} elseif ($path === '/rodytelyam/') {
    require __DIR__ . '/inc/pages/parents.php';
} elseif ($path === '/transfer-do-lagerya/') {
    require __DIR__ . '/inc/pages/transfer-camp.php';
} elseif ($path === '/sitemap.xml') {
    require __DIR__ . '/inc/pages/sitemap.php';
} elseif (preg_match('~^/api/v1/tours/(?:([a-z0-9_\-]+)/)?$~i', $path, $m)) {
    $slug = $m[1] ?? '';
    require __DIR__ . '/inc/pages/api.php';
} elseif (preg_match('~^/([a-z0-9_\-]+)/$~i', $path, $m)) {
    $slug = $m[1];
    require __DIR__ . '/inc/pages/page.php';
} else {
    $redir = at_redirect_lookup($path);
    if ($redir !== null) {
        at_redirect((string) $redir['to_path'], (int) $redir['status_code'] === 302 ? 302 : 301);
    }
    require __DIR__ . '/inc/pages/notfound.php';
}
