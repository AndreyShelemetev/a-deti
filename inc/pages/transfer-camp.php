<?php
/** Трансфер до лагеря /transfer-do-lagerya/: текст + туры-трансферы. */
declare(strict_types=1);

$page = at_page('transfer-do-lagerya');
if ($page === null) {
    require __DIR__ . '/notfound.php';
    return;
}
$transfers = at_tours_list(['type' => 'TRANSFER', 'perPage' => 8]);
at_page_start(['key' => 'page-transfer-do-lagerya', 'title' => $page['seo_title'] ?: $page['title'], 'description' => (string) $page['meta_description'], 'keywords' => (string) $page['meta_keywords'], 'canonical' => '/transfer-do-lagerya/']);
at_page_shell_open('page-transfer-do-lagerya', $page['title'], [['name' => $page['title']]]);
if (trim((string) $page['html']) !== '') {
    echo '<article class="panel panel-lg">' . at_rich((string) $page['html']) . '</article>';
}
if ($transfers['items']) {
    echo '<section class="mt-8"><h2 class="section-title section-title-sm mb-5">Трансферы в лагеря</h2><div class="tour-grid tour-grid-2">';
    foreach ($transfers['items'] as $t) {
        echo at_tour_card($t);
    }
    echo '</div></section>';
}
at_page_shell_close();
at_page_end();
