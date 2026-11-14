<?php
/** Поиск по сайту /search/?q=… */
declare(strict_types=1);

$q = trim((string) (is_array($_GET['q'] ?? null) ? '' : ($_GET['q'] ?? '')));
$results = $q !== '' ? at_search($q) : null;
$total = $results ? count($results['tours']) + count($results['posts']) + count($results['pages']) : 0;
at_page_start(['key' => 'search', 'title' => 'Поиск по сайту', 'description' => 'Поиск туров, лагерей, новостей и статей на сайте А-тур.', 'canonical' => '/search/', 'noindex' => true]);
at_page_shell_open('search', 'Поиск', [['name' => 'Поиск']], '', false, true);
echo '<form action="/search/" method="get" class="panel search-form">' . at_input('q', ['value' => $q, 'placeholder' => 'Например: Орлёнок, Крым, языковой лагерь…', 'aria-label' => 'Поисковый запрос', 'autofocus' => true, 'data-optional' => true]) . '<button type="submit" class="btn-primary">' . at_icon('search') . ' Искать</button></form>';
if ($results !== null) {
    echo '<div class="mt-8 stack-10"><p class="list-count">По запросу «' . at_e($q) . '» ' . ($total ? 'найдено ' . $total . ' ' . at_plural($total, ['результат', 'результата', 'результатов']) : 'ничего не найдено. Попробуйте другое слово или посмотрите весь каталог.') . '</p>';
    if ($results['tours']) {
        echo '<section><h2 class="section-title section-title-sm mb-5">Туры и лагеря</h2>' . at_tour_list($results['tours']) . '</section>';
    }
    if ($results['posts']) {
        echo '<section><h2 class="section-title section-title-sm mb-5">Новости и статьи</h2><div class="post-grid">';
        foreach ($results['posts'] as $p) {
            echo at_post_card($p);
        }
        echo '</div></section>';
    }
    if ($results['pages']) {
        echo '<section><h2 class="section-title section-title-sm mb-5">Страницы</h2><ul class="plain-list">';
        foreach ($results['pages'] as $p) {
            echo '<li><a href="/' . at_e($p['slug']) . '/" class="link-strong">' . at_e($p['title']) . '</a></li>';
        }
        echo '</ul></section>';
    }
    if (!$total) {
        echo '<a href="/tour/" class="btn-outline">Смотреть все туры</a>';
    }
    echo '</div>';
}
at_page_shell_close(false);
at_page_end();
