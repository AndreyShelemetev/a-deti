<?php
/**
 * Публичное JSON-API каталога (для партнёров и виджетов), как на исходном сайте:
 *   GET /api/v1/tours/?type=CAMP&region=crimea&age=10&page=1
 *   GET /api/v1/tours/<slug>/
 */
declare(strict_types=1);

/** @var string $slug */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=60');
$card = static function (array $t): array {
    return [
        'id' => (int) $t['id'], 'slug' => $t['slug'], 'url' => '/tour/' . $t['slug'] . '/', 'title' => $t['title'], 'short' => $t['short'],
        'address' => $t['address'], 'ageText' => $t['age_text'], 'ageMin' => $t['age_min'] !== null ? (int) $t['age_min'] : null,
        'ageMax' => $t['age_max'] !== null ? (int) $t['age_max'] : null, 'priceText' => $t['price_text'], 'priceMin' => $t['price_min'] !== null ? (int) $t['price_min'] : null,
        'cover' => $t['cover'], 'type' => $t['type'], 'seasons' => $t['seasons_list'], 'seasonText' => $t['season_text'],
        'isBest' => (bool) $t['is_best'], 'isHit' => (bool) $t['is_hit'],
        'region' => $t['region'] ? ['slug' => $t['region']['slug'], 'name' => $t['region']['name'], 'isForeign' => (bool) $t['region']['is_foreign']] : null,
        'tags' => $t['tags'],
    ];
};
if ($slug !== '') {
    $tour = at_tour_by_slug($slug);
    if ($tour === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Not found'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $out = $card($tour) + [
        'website' => $tour['website'], 'seoTitle' => $tour['seo_title'], 'metaDescription' => $tour['meta_description'],
        'shifts' => array_map(static fn(array $s): array => ['id' => (int) $s['id'], 'group' => $s['group_name'], 'name' => $s['name'], 'startDate' => $s['start_date'], 'endDate' => $s['end_date'], 'price' => $s['price'] !== null ? (int) $s['price'] : null, 'priceText' => $s['price_text']], $tour['shifts']),
        'images' => array_map(static fn(array $i): array => ['url' => $i['url'], 'alt' => $i['alt']], $tour['images']),
        'sections' => array_map(static fn(array $s): array => ['key' => $s['skey'], 'title' => $s['title'], 'html' => $s['html']], $tour['sections']),
        'documents' => array_map(static fn(array $d): array => ['label' => $d['label'], 'url' => $d['url']], $tour['documents']),
    ];
    echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
$params = at_parse_filters($_GET)['params'];
$params['perPage'] = min((int) ($_GET['perPage'] ?? 20) ?: 20, 50);
$result = at_tours_list($params);
$result['items'] = array_map($card, $result['items']);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
