<?php
/** Текстовая страница из админки: /<slug>/ (родителям, трансфер, политика и т.п.). */
declare(strict_types=1);

/** @var string $slug */
$page = str_starts_with($slug, 'pay') ? null : at_page($slug);
if ($page === null) {
    $redir = at_redirect_lookup('/' . $slug . '/');
    if ($redir !== null) {
        at_redirect((string) $redir['to_path'], (int) $redir['status_code'] === 302 ? 302 : 301);
    }
    require __DIR__ . '/notfound.php';
    return;
}
at_page_start(['key' => 'page-' . $slug, 'title' => $page['seo_title'] ?: $page['title'], 'description' => (string) $page['meta_description'], 'keywords' => (string) $page['meta_keywords'], 'canonical' => '/' . $slug . '/']);
at_page_shell_open('page-' . $slug, $page['title'], [['name' => $page['title']]]);
if (trim((string) $page['html']) !== '') {
    echo '<article class="panel panel-lg">' . at_rich((string) $page['html']) . '</article>';
}
at_page_shell_close();
at_page_end();
