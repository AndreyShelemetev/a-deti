<?php
/** SEO-страницы каталога /tour/filter/<путь>/: правило отбора и тексты. */
declare(strict_types=1);

function adm_tab_catalog(): string
{
    $regions = array_column(at_regions(), 'name', 'slug');
    $tags = array_column(at_tags(), 'label', 'slug');
    return adm_crud('catalog', [
        'table' => 'catalog_pages', 'one' => 'страницу', 'order' => 'sort_order, path', 'perPage' => 100, 'search' => ['path', 'h1'],
        'intro' => 'Каждая страница — посадочная /tour/filter/&lt;путь&gt;/ с правилом отбора туров. Путь без /tour/filter/ и без слэшей по краям, например <code>lager/crymea</code>.',
        'viewUrl' => static fn(array $r): string => '/tour/filter/' . $r['path'] . '/',
        'fields' => [
            'path'             => ['label' => 'Путь', 'required' => true, 'unique' => true, 'max' => 200, 'hint' => 'lager/crymea'],
            'h1'               => ['label' => 'Заголовок H1', 'required' => true, 'max' => 300, 'hint' => '{year} — год каталога из настроек; меняется вручную'],
            'seo_title'        => ['label' => 'Title', 'nullable' => true, 'max' => 300],
            'meta_description' => ['label' => 'Description', 'type' => 'textarea', 'nullable' => true, 'max' => 500],
            'seo_text'         => ['label' => 'SEO-текст под списком', 'type' => 'html', 'rows' => 8],
            'type'             => ['label' => 'Тип отдыха', 'type' => 'select', 'options' => ['' => 'Любой'] + ATOUR_TOUR_TYPES, 'side' => true],
            'season'           => ['label' => 'Сезон', 'type' => 'select', 'options' => ['' => 'Любой'] + ATOUR_SEASONS, 'side' => true],
            'region_slugs'     => ['label' => 'Регионы (любой из отмеченных)', 'type' => 'csv', 'options' => $regions, 'side' => true],
            'tag_slugs'        => ['label' => 'Характеристики (все отмеченные)', 'type' => 'csv', 'options' => $tags, 'side' => true],
            'is_indexable'     => ['label' => 'Разрешить индексацию (в sitemap)', 'type' => 'checkbox', 'side' => true, 'default' => 1],
            'sort_order'       => ['label' => 'Сортировка', 'type' => 'number', 'side' => true, 'default' => 0],
        ],
        'columns' => [
            'path' => ['Путь', static fn(array $r): string => adm_e($r['path'])],
            'h1'   => ['H1', static fn(array $r): string => adm_e(at_year($r['h1']))],
            'rule' => ['Правило', static fn(array $r): string => adm_e(trim(implode(' · ', array_filter([ATOUR_TOUR_TYPES[$r['type'] ?? ''] ?? '', $r['region_slugs'] ? 'регионы: ' . $r['region_slugs'] : '', $r['tag_slugs'] ? 'теги: ' . $r['tag_slugs'] : '', ATOUR_SEASONS[$r['season'] ?? ''] ?? '']))) ?: '—')],
            'is_indexable' => ['Индекс', static fn(array $r): string => $r['is_indexable'] ? 'да' : 'нет'],
        ],
        'beforeSave' => static function (array $d, int $id): array {
            $d['path'] = trim((string) preg_replace('~^/?tour/filter/~', '', (string) $d['path']), '/');
            $d['type'] = $d['type'] === '' ? null : $d['type'];
            $d['season'] = $d['season'] === '' ? null : $d['season'];
            $d['seo_text'] = $d['seo_text'] === '' ? null : $d['seo_text'];
            return [$d, $d['path'] === '' ? ['Укажите путь.'] : []];
        },
    ]);
}
