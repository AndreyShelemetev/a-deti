<?php
/** Новости и статьи. */
declare(strict_types=1);

function adm_tab_posts(): string
{
    $kind = ($_GET['kind'] ?? 'NEWS') === 'ARTICLE' ? 'ARTICLE' : 'NEWS';
    $nav = '<div class="filters"><a href="' . adm_url('posts', ['kind' => 'NEWS']) . '" class="' . ($kind === 'NEWS' ? 'on' : '') . '">Новости</a><a href="' . adm_url('posts', ['kind' => 'ARTICLE']) . '" class="' . ($kind === 'ARTICLE' ? 'on' : '') . '">Статьи</a></div>';
    return $nav . adm_crud('posts', [
        'table' => 'posts', 'one' => $kind === 'NEWS' ? 'новость' : 'статью', 'order' => 'published_at DESC, id DESC', 'keep' => ['kind'], 'perPage' => 40,
        'listWhere' => ['kind = ?', [$kind]], 'search' => ['title', 'slug'], 'defaults' => ['kind' => $kind, 'is_published' => 1],
        'viewUrl' => static fn(array $r): string => at_post_url($r),
        'fields' => [
            'kind'             => ['label' => 'Раздел', 'type' => 'select', 'options' => ['NEWS' => 'Новость', 'ARTICLE' => 'Статья'], 'required' => true],
            'title'            => ['label' => 'Заголовок', 'required' => true, 'max' => 300],
            'slug'             => ['label' => 'URL (slug)', 'type' => 'slug', 'from' => 'title', 'unique' => true, 'uniqueWith' => 'kind', 'hint' => 'пусто — из заголовка'],
            'excerpt'          => ['label' => 'Анонс (для карточки)', 'type' => 'textarea', 'nullable' => true, 'max' => 1000],
            'html'             => ['label' => 'Текст', 'type' => 'html', 'rows' => 16],
            'image'            => ['label' => 'Обложка', 'type' => 'image', 'nullable' => true, 'side' => true],
            'published_at'     => ['label' => 'Дата публикации', 'type' => 'datetime', 'required' => true, 'side' => true],
            'is_published'     => ['label' => 'Опубликовано', 'type' => 'checkbox', 'side' => true, 'default' => 1],
            'seo_title'        => ['label' => 'Title', 'nullable' => true, 'max' => 300, 'side' => true],
            'meta_description' => ['label' => 'Description', 'type' => 'textarea', 'nullable' => true, 'max' => 500, 'side' => true],
        ],
        'columns' => [
            'title'        => 'Заголовок',
            'published_at' => ['Дата', static fn(array $r): string => adm_e(at_date_short($r['published_at']))],
            'is_published' => ['Статус', static fn(array $r): string => '<span class="chip ' . ($r['is_published'] ? 'pub' : '') . '">' . ($r['is_published'] ? 'Опубликовано' : 'Черновик') . '</span>'],
        ],
        'beforeSave' => static function (array $d, int $id): array {
            if (($d['excerpt'] ?? null) === null) {
                $d['excerpt'] = at_truncate(at_strip_html((string) $d['html']), 180);
            }
            return [$d, []];
        },
    ]);
}
