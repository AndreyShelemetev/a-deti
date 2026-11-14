<?php
/** SEO-страница каталога /tour/filter/<путь>/: правило отбора из админки либо страница характеристики. Список — как в /tour/. */
declare(strict_types=1);

/** @var string $filterPath */
$cp = at_catalog_page($filterPath);
$resolved = null;
if ($cp !== null) {
    $resolved = [
        'h1' => $cp['h1'], 'seo_title' => $cp['seo_title'], 'meta_description' => $cp['meta_description'], 'seo_text' => $cp['seo_text'],
        'params' => ['type' => $cp['type'], 'regionSlugs' => $cp['region_slugs_list'], 'tagSlugs' => $cp['tag_slugs_list'], 'season' => $cp['season']],
    ];
} elseif (!str_contains($filterPath, '/')) {
    $tag = at_tag_by_slug($filterPath);
    if ($tag !== null) {
        $resolved = [
            'h1' => $tag['h1'] ?: 'Детский отдых: ' . $tag['label'] . ', {year}', 'seo_title' => $tag['seo_title'], 'meta_description' => $tag['meta_description'], 'seo_text' => null,
            'params' => ['tagSlugs' => [$tag['slug']]],
        ];
    }
}
if ($resolved === null) {
    require __DIR__ . '/notfound.php';
    return;
}
$extra = at_parse_filters($_GET)['params'];
$listParams = $resolved['params'] + ['age' => $extra['age'], 'sort' => $extra['sort'], 'page' => $extra['page']];
$result = at_tours_list($listParams + ['perPage' => 12]);
$facets = at_tour_facets($listParams);
$h1 = at_year((string) $resolved['h1']);
$canonical = '/tour/filter/' . $filterPath . '/';
$hrefFor = static function (int $page) use ($canonical, $extra): string {
    $q = [];
    if (!empty($extra['age'])) { $q['age'] = $extra['age']; }
    if ($extra['sort'] !== 'default') {
        $q['sort'] = $extra['sort'];
    }
    if ($page > 1) {
        $q['page'] = $page;
    }
    return $canonical . ($q ? '?' . http_build_query($q) : '');
};
$rp = $resolved['params'];
$values = [
    'type'   => (string) ($rp['type'] ?? ''),
    'region' => count($rp['regionSlugs'] ?? []) === 1 ? (string) $rp['regionSlugs'][0] : '',
    'tag'    => (string) ($rp['tagSlugs'][0] ?? ''),
    'season' => (string) ($rp['season'] ?? ''),
    'age'    => (string) ($extra['age'] ?? ''),
];
$resultsHtml = at_catalog_results($result, $facets, $extra['sort'], $hrefFor, '', '/tour/', $values, ['heading' => at_strip_html(at_h1('filter-' . $filterPath, $h1)), 'lead' => 'Выберите возраст ребёнка, регион и сезон.', 'mode' => 'landing']);

at_page_start([
    'bodyClass' => 'page-catalog',
    'key'         => 'filter-' . $filterPath,
    'title'       => at_year((string) $resolved['seo_title']) ?: $h1 . '. Заказать и купить путевку в Москве',
    'description' => at_year((string) $resolved['meta_description']) ?: $h1 . '. Описание туров, фото, отзывы, цены. Подбор и бронирование у туроператора А-тур.',
    'canonical'   => $canonical,
    'noindex'     => !empty($_GET['page']) || !empty($_GET['sort']) || !empty($_GET['age']),
]);
?>
<div class="container-site page-top">
  <?= at_breadcrumbs([['name' => 'Все туры', 'href' => '/tour/'], ['name' => $h1]]) ?>
  <h1 class="page-h1"><?= at_h1('filter-' . $filterPath, $h1) ?></h1>
  <p class="page-lead">Выберите возраст ребёнка, регион и сезон.</p>
  <div class="catalog" data-catalog data-base="/tour/" data-live-base="<?= at_e($canonical) ?>" data-live-params="age,sort,page">
    <aside class="catalog-side" id="catalog-filters" data-catalog-side>
      <?= at_tour_filters($values, $facets, $result['total']) ?>
      <?= at_catalog_hints($facets, $values) ?>
      <?= at_help_card() ?>
    </aside>
    <div class="catalog-main">
      <?= $resultsHtml ?>
      <?php if (!empty($resolved['seo_text'])): ?><div class="panel panel-lg mt-10" data-catalog-seo><?= at_rich((string) $resolved['seo_text']) ?></div><?php endif; ?>
    </div>
  </div>
</div>
<?php at_page_end();
