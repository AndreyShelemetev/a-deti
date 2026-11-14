<?php
/** Каталог /tour/: фильтры слева (с числом туров на вариант), список справа — плитка или строки, обновление без перезагрузки. */
declare(strict_types=1);

$parsed = at_parse_filters($_GET);
$values = $parsed['values'];
$params = $parsed['params'];
$result = at_tours_list($params + ['perPage' => 12]);
$facets = at_tour_facets($params);
$active = at_has_filters($values);
$noindex = $active || !empty($_GET['page']) || !empty($_GET['sort']);
$hrefFor = static function (int $page) use ($values, $params): string {
    $q = [];
    foreach ($values as $k => $v) {
        if ($v !== '') {
            $q[$k] = $v;
        }
    }
    if ($params['sort'] !== 'default') {
        $q['sort'] = $params['sort'];
    }
    if ($page > 1) {
        $q['page'] = $page;
    }
    return '/tour/' . ($q ? '?' . http_build_query($q) : '');
};
$chips = array_filter([
    $values['type'] !== '' ? (at_catalog_types()[$values['type']] ?? null) : null,
    $values['region'] !== '' ? (at_region_by_slug($values['region'])['name'] ?? null) : null,
    $values['tag'] !== '' ? (at_tag_by_slug($values['tag'])['label'] ?? null) : null,
    $values['age'] !== '' ? $values['age'] . ' лет' : null,
    $values['season'] !== '' ? (ATOUR_SEASONS[$values['season']] ?? null) : null,
    $values['q'] !== '' ? '«' . $values['q'] . '»' : null,
]);
// Обновление списка скриптом: JSON вместо страницы (внутри — exit).
$resultsHtml = at_catalog_results($result, $facets, $params['sort'], $hrefFor, implode(' · ', $chips), '/tour/', $values, ['heading' => at_strip_html(at_h1('catalog', 'Все туры и детские лагеря — каталог')), 'lead' => 'Выберите возраст ребёнка, регион и сезон.', 'mode' => 'catalog']);

at_page_start([
    'bodyClass' => 'page-catalog',
    'key'         => 'catalog',
    'title'       => 'Все туры и детские лагеря — каталог',
    'description' => 'Каталог детских лагерей, спортивных сборов, экскурсионных и семейных туров от туроператора А-тур. Подбор по возрасту, региону, сезону и интересам ребёнка.',
    'canonical'   => '/tour/',
    'noindex'     => $noindex,
]);
?>
<div class="container-site page-top">
  <?= at_breadcrumbs([['name' => 'Все туры']]) ?>
  <h1 class="page-h1"><?= at_h1('catalog', 'Все туры') ?></h1>
  <p class="page-lead">Выберите возраст ребёнка, регион и сезон.</p>
  <div class="catalog" data-catalog data-base="/tour/">
    <aside class="catalog-side" id="catalog-filters" data-catalog-side>
      <?= at_tour_filters($values, $facets, $result['total']) ?>
      <?= at_catalog_hints($facets, $values) ?>
      <?= at_help_card() ?>
    </aside>
    <div class="catalog-main">
      <?= $resultsHtml ?>
    </div>
  </div>
</div>
<?php at_page_end();
