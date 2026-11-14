<?php
/** Новость или статья: /novosti/<slug>/, /stati/<slug>/. */
declare(strict_types=1);

/** @var string $kind @var string $slug */
$base = $kind === 'NEWS' ? '/novosti/' : '/stati/';
$post = at_post($kind, $slug);
if ($post === null) {
    $redir = at_redirect_lookup($base . $slug . '/');
    if ($redir !== null) {
        at_redirect((string) $redir['to_path'], 301);
    }
    require __DIR__ . '/notfound.php';
    return;
}
$crumb = $kind === 'NEWS' ? 'Новости' : 'Все статьи';
$more = array_slice(array_values(array_filter(at_posts_list($kind, 1, 4)['items'], static fn(array $p): bool => (int) $p['id'] !== (int) $post['id'])), 0, 3);
$ld = [
    '@context' => 'https://schema.org', '@type' => $kind === 'NEWS' ? 'NewsArticle' : 'Article', 'headline' => $post['title'],
    'description' => (string) $post['excerpt'], 'datePublished' => at_iso($post['published_at']), 'dateModified' => at_iso($post['updated_at']),
    'mainEntityOfPage' => at_abs($base . $post['slug'] . '/'), 'author' => ['@type' => 'Organization', 'name' => ATOUR_SITE_NAME],
    'publisher' => ['@type' => 'Organization', 'name' => ATOUR_SITE_NAME, 'logo' => ['@type' => 'ImageObject', 'url' => at_abs('/images/logo.svg')]],
];
if (!empty($post['image'])) {
    $ld['image'] = [at_abs($post['image'])];
}
$key = 'post-' . strtolower($kind) . '-' . $post['slug'];
at_page_start([
    'key' => $key, 'title' => $post['seo_title'] ?: $post['title'], 'description' => (string) ($post['meta_description'] ?: $post['excerpt']),
    'canonical' => $base . $post['slug'] . '/', 'image' => (string) ($post['image'] ?? ''), 'type' => 'article', 'published' => at_iso($post['published_at']), 'jsonld' => [$ld],
]);
at_page_shell_open($key, $post['title'], [['name' => $crumb, 'href' => $base], ['name' => $post['title']]]);
echo '<time datetime="' . at_e(at_iso($post['published_at'])) . '" class="post-date post-date-lg">' . at_icon('calendar', 'ic ic-sm') . at_e(at_date($post['published_at'])) . '</time>';
if (!empty($post['image']) && !str_contains((string) $post['html'], (string) $post['image'])) {
    echo '<div class="post-cover">' . at_img($post['image'], $post['title'], '', ['loading' => 'eager']) . '</div>';
}
echo '<article class="panel panel-lg mt-6">' . at_rich((string) $post['html']) . '</article>';
if ($more) {
    echo '<section class="mt-12"><h2 class="section-title section-title-sm">Читайте также</h2><div class="post-grid mt-6">';
    foreach ($more as $p) {
        echo at_post_card($p);
    }
    echo '</div></section>';
}
at_page_shell_close();
at_page_end();
