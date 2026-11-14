<?php
/** Списки «Новости» (/novosti/) и «Статьи» (/stati/) с пагинацией. */
declare(strict_types=1);

/** @var string $kind */
$meta = $kind === 'NEWS'
    ? ['base' => '/novosti/', 'title' => 'Новости', 'crumb' => 'Новости', 'key' => 'news', 'description' => 'Новости туроператора А-тур: открытие продаж, заезды групп, акции и скидки на детский отдых.']
    : ['base' => '/stati/', 'title' => 'Статьи', 'crumb' => 'Все статьи', 'key' => 'articles', 'description' => 'Полезные статьи для родителей и вожатых: подготовка ребёнка к лагерю, безопасность, советы по отдыху.'];
$page = max((int) ($_GET['page'] ?? 1), 1);
$result = at_posts_list($kind, $page, 9);
if ($page > 1 && !$result['items']) {
    require __DIR__ . '/notfound.php';
    return;
}
at_page_start([
    'key' => $meta['key'], 'title' => $page > 1 ? $meta['title'] . ' — страница ' . $page : $meta['title'],
    'description' => $meta['description'], 'canonical' => $meta['base'], 'noindex' => $page > 1,
]);
$aside = at_help_card() . '<div class="panel mt-4"><div class="help-title">Подписка на новости</div><p class="muted mb-4">Первыми узнавайте об открытии продаж и скидках.</p>' . at_form_subscribe() . '</div>';
at_page_shell_open($meta['key'], $meta['title'], [['name' => $meta['crumb']]], $meta['description']);
echo '<div class="post-grid post-grid-2">';
foreach ($result['items'] as $p) {
    echo at_post_card($p);
}
echo '</div>';
echo at_pagination($result['page'], $result['pages'], static fn(int $p): string => $p > 1 ? $meta['base'] . '?page=' . $p : $meta['base']);
at_page_shell_close(true, $aside);
at_page_end();
