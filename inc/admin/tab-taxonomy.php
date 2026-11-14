<?php
/** Справочники фильтра: характеристики (теги) и регионы. */
declare(strict_types=1);

function adm_tab_taxonomy(): string
{
    $section = ($_GET['section'] ?? 'tags') === 'regions' ? 'regions' : 'tags';
    $nav = '<div class="filters"><a href="' . adm_url('taxonomy', ['section' => 'tags']) . '" class="' . ($section === 'tags' ? 'on' : '') . '">Характеристики</a><a href="' . adm_url('taxonomy', ['section' => 'regions']) . '" class="' . ($section === 'regions' ? 'on' : '') . '">Регионы</a></div>';
    if ($section === 'regions') {
        return $nav . adm_crud('taxonomy', [
            'table' => 'regions', 'one' => 'регион', 'order' => 'sort_order, id', 'keep' => ['section'], 'perPage' => 200,
            'fields' => [
                'name'       => ['label' => 'Название', 'required' => true, 'max' => 190],
                'slug'       => ['label' => 'Slug', 'type' => 'slug', 'from' => 'name', 'unique' => true, 'hint' => 'латиницей, для адресов фильтра'],
                'is_foreign' => ['label' => 'За границей', 'type' => 'checkbox'],
                'sort_order' => ['label' => 'Сортировка', 'type' => 'number', 'default' => 0],
            ],
            'columns' => ['name' => 'Название', 'slug' => 'Slug', 'is_foreign' => ['За границей', static fn(array $r): string => $r['is_foreign'] ? 'да' : '—'], 'sort_order' => 'Порядок'],
        ]);
    }
    return $nav . adm_crud('taxonomy', [
        'table' => 'tags', 'one' => 'характеристику', 'order' => 'sort_order, id', 'keep' => ['section'], 'perPage' => 200,
        'viewUrl' => static fn(array $r): string => '/tour/filter/' . $r['slug'] . '/',
        'fields' => [
            'label'            => ['label' => 'Название', 'required' => true, 'max' => 190],
            'slug'             => ['label' => 'Slug', 'type' => 'slug', 'from' => 'label', 'unique' => true, 'hint' => 'страница /tour/filter/<slug>/'],
            'h1'               => ['label' => 'H1 страницы характеристики', 'nullable' => true, 'max' => 300, 'hint' => '{year} — год каталога из настроек; меняется вручную'],
            'seo_title'        => ['label' => 'Title', 'nullable' => true, 'max' => 300],
            'meta_description' => ['label' => 'Description', 'type' => 'textarea', 'nullable' => true, 'max' => 500],
            'sort_order'       => ['label' => 'Сортировка', 'type' => 'number', 'default' => 0],
        ],
        'columns' => ['label' => 'Название', 'slug' => 'Slug', 'sort_order' => 'Порядок'],
    ]);
}
