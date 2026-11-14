<?php
/** Текстовые страницы: открываются по /<slug>/ без правки кода. */
declare(strict_types=1);

function adm_tab_pages(): string
{
    return adm_crud('pages', [
        'table' => 'pages', 'one' => 'страницу', 'order' => 'id', 'perPage' => 100, 'search' => ['title', 'slug'],
        'intro' => 'Страница открывается по адресу /&lt;slug&gt;/. Особые страницы (kak-nas-nayti, agentstvam, rodytelyam, transfer-do-lagerya, pay, pay-cashback) выводятся своими шаблонами, но текст и заголовок берут отсюда.',
        'viewUrl' => static fn(array $r): string => str_starts_with((string) $r['slug'], 'pay') ? '/pay/' . ($r['slug'] === 'pay-cashback' ? 'cashback/' : '') : '/' . $r['slug'] . '/',
        'fields' => [
            'title'            => ['label' => 'Заголовок', 'required' => true, 'max' => 300],
            'slug'             => ['label' => 'URL (slug)', 'type' => 'slug', 'from' => 'title', 'unique' => true],
            'html'             => ['label' => 'Текст', 'type' => 'html', 'rows' => 18],
            'is_published'     => ['label' => 'Опубликована', 'type' => 'checkbox', 'side' => true, 'default' => 1],
            'seo_title'        => ['label' => 'Title', 'nullable' => true, 'max' => 300, 'side' => true],
            'meta_description' => ['label' => 'Description', 'type' => 'textarea', 'nullable' => true, 'max' => 500, 'side' => true],
            'meta_keywords'    => ['label' => 'Keywords', 'nullable' => true, 'max' => 500, 'side' => true],
        ],
        'columns' => ['title' => 'Заголовок', 'slug' => ['Адрес', static fn(array $r): string => '/' . adm_e($r['slug']) . '/'], 'is_published' => ['Статус', static fn(array $r): string => $r['is_published'] ? 'опубликована' : 'скрыта']],
    ]);
}
