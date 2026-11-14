<?php
/** Родителям /rodytelyam/: памятка + полезные статьи. */
declare(strict_types=1);

$page = at_page('rodytelyam');
if ($page === null) {
    require __DIR__ . '/notfound.php';
    return;
}
$articles = at_latest_posts('ARTICLE', 3);
at_page_start(['key' => 'page-rodytelyam', 'title' => $page['seo_title'] ?: $page['title'], 'description' => (string) $page['meta_description'], 'keywords' => (string) $page['meta_keywords'], 'canonical' => '/rodytelyam/']);
at_page_shell_open('page-rodytelyam', $page['title'], [['name' => $page['title']]]);
$cards = [
    ['📋', 'Документы в лагерь', 'Списки документов есть на странице каждого тура во вкладке «Документы».', ''],
    ['🚆', 'Доставка', 'Организуем ж/д и авиа трансфер с сопровождением.', '/transfer/'],
    ['💳', 'Оплата онлайн', 'Оплатите путёвку картой по номеру договора.', '/pay/'],
];
echo '<div class="grid-3 mb-6">';
foreach ($cards as [$icon, $t, $text, $href]) {
    if (!at_payment_link_visible($href)) continue;
    echo '<div class="panel"><div class="emoji-lg">' . $icon . '</div><div class="perk-title mt-2">' . at_e($t) . '</div><p class="muted">' . at_e($text) . '</p>' . ($href !== '' ? '<a href="' . at_e($href) . '" class="link-strong">Подробнее →</a>' : '') . '</div>';
}
echo '</div>';
if (trim((string) $page['html']) !== '') {
    echo '<article class="panel panel-lg">' . at_rich((string) $page['html']) . '</article>';
}
if ($articles) {
    echo '<section class="mt-10"><div class="section-row"><h2 class="section-title section-title-sm">Полезные статьи</h2><a href="/stati/" class="btn-ghost">Все статьи</a></div><div class="post-grid mt-5">';
    foreach ($articles as $p) {
        echo at_post_card($p);
    }
    echo '</div></section>';
}
at_page_shell_close();
at_page_end();
