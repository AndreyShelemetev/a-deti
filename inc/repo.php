<?php
/**
 * Слой данных сайта А-тур: туры и фильтры каталога, SEO-страницы каталога,
 * новости/статьи, текстовые страницы, блоки главной, поиск, редиректы,
 * карта сайта. Только чтение; запись — в inc/forms.php и в админке.
 */
declare(strict_types=1);

/** @return array<int,array<string,mixed>> */
function at_all(string $sql, array $params = []): array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** @return array<string,mixed>|null */
function at_one(string $sql, array $params = []): ?array
{
    $st = db()->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

function at_scalar(string $sql, array $params = []): mixed
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchColumn();
}

function at_like(string $s): string
{
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
}

function at_in(array $values): string
{
    return implode(',', array_fill(0, max(count($values), 1), '?'));
}

// --- Справочники -------------------------------------------------------------

/** @return array<int,array<string,mixed>> */
function at_regions(): array
{
    static $rows = null;
    return $rows ??= at_all('SELECT * FROM regions ORDER BY sort_order, id');
}

/** @return array<int,array<string,mixed>> */
function at_tags(): array
{
    static $rows = null;
    return $rows ??= at_all('SELECT * FROM tags ORDER BY sort_order, id');
}

function at_tag_by_slug(string $slug): ?array
{
    foreach (at_tags() as $t) {
        if ($t['slug'] === $slug) {
            return $t;
        }
    }
    return null;
}

function at_region_by_slug(string $slug): ?array
{
    foreach (at_regions() as $r) {
        if ($r['slug'] === $slug) {
            return $r;
        }
    }
    return null;
}

// --- Туры --------------------------------------------------------------------

/** Публичные типы: лагеря разделены по географии без изменения типов в базе. */
function at_catalog_types(): array
{
    return ['CAMP_RUSSIA' => 'Лагеря России', 'CAMP_ABROAD' => 'Лагеря за границей'] + ATOUR_TOUR_TYPES;
}

/**
 * Параметры фильтра из GET (type, region, tag, age, season, q, sort, page).
 *
 * @param array<string,mixed> $get
 * @return array{values:array<string,string>,params:array<string,mixed>}
 */
function at_parse_filters(array $get): array
{
    $one = static fn(string $k): string => trim((string) (is_array($get[$k] ?? null) ? ($get[$k][0] ?? '') : ($get[$k] ?? '')));
    $values = ['type' => $one('type'), 'region' => $one('region'), 'tag' => $one('tag'), 'age' => $one('age'), 'season' => $one('season'), 'q' => $one('q')];
    $sort = $one('sort');
    if (!in_array($sort, ['price_asc', 'price_desc', 'new'], true)) {
        $sort = 'default';
    }
    $age = (int) $values['age'];
    return [
        'values' => $values,
        'params' => [
            'type'        => isset(at_catalog_types()[$values['type']]) ? $values['type'] : null,
            'regionSlugs' => $values['region'] !== '' ? [$values['region']] : [],
            'tagSlugs'    => $values['tag'] !== '' ? [$values['tag']] : [],
            'season'      => isset(ATOUR_SEASONS[$values['season']]) ? $values['season'] : null,
            'age'         => $age > 0 ? $age : null,
            'q'           => $values['q'] !== '' ? mb_substr($values['q'], 0, 100) : null,
            'sort'        => $sort,
            'page'        => max((int) $one('page'), 1),
        ],
    ];
}

/** @param array<string,string> $values */
function at_has_filters(array $values): bool
{
    foreach (['type', 'region', 'tag', 'age', 'season', 'q'] as $k) {
        if (($values[$k] ?? '') !== '') {
            return true;
        }
    }
    return false;
}

/** Нормализация запроса: регистр, ё/е, кавычки и пунктуация не влияют на поиск. */
function at_search_words(string $query): array
{
    $query = str_replace('ё', 'е', mb_strtolower(mb_substr(trim($query), 0, 100)));
    preg_match_all('~[\p{L}\p{N}]+~u', $query, $m);
    return array_slice($m[0], 0, 6);
}

/** Именной запрос: точное название лагеря предпочтительнее случайных упоминаний в описаниях. */
function at_named_tour_ids(string $query): array
{
    static $titles = null, $cache = [];
    $needle = implode(' ', at_search_words($query));
    if (mb_strlen($needle) < 2) {
        return [];
    }
    if (isset($cache[$needle])) {
        return $cache[$needle];
    }
    $titles ??= at_all('SELECT id, title FROM tours WHERE is_published = 1 AND archived_at IS NULL');
    $exact = $prefix = [];
    foreach ($titles as $row) {
        $names = [$row['title']];
        preg_match_all('~«([^»]+)»|"([^"]+)"|“([^”]+)”~u', $row['title'], $quoted, PREG_SET_ORDER);
        foreach ($quoted as $name) {
            $names[] = $name[1] ?: ($name[2] ?: ($name[3] ?? ''));
        }
        foreach ($names as $name) {
            $normalized = implode(' ', at_search_words($name));
            if ($normalized === $needle) {
                $exact[(int) $row['id']] = (int) $row['id'];
            } elseif (mb_strlen($needle) >= 3 && str_starts_with($normalized, $needle)) {
                $prefix[(int) $row['id']] = (int) $row['id'];
            }
        }
    }
    return $cache[$needle] = array_values($exact ?: $prefix);
}

/** SQL REGEXP по целым словам: «вита» не совпадает с «витамины». */
function at_search_pattern(string $word): string
{
    return '(^|[^[:alnum:]_])' . $word . '([^[:alnum:]_]|$)';
}

/**
 * SQL-условие отбора туров.
 *
 * @param array<string,mixed> $p type, regionSlugs, tagSlugs, season, age, q
 * @return array{0:string,1:array<int,mixed>}
 */
function at_tour_where(array $p): array
{
    $where = ['t.is_published = 1 AND t.archived_at IS NULL'];
    $params = [];
    $type = (string) ($p['type'] ?? '');
    if (in_array($type, ['CAMP_RUSSIA', 'CAMP_ABROAD'], true)) {
        $where[] = "t.type = 'CAMP'";
        $where[] = 't.region_id IN (SELECT id FROM regions WHERE is_foreign = ?)';
        $params[] = $type === 'CAMP_ABROAD' ? 1 : 0;
    } elseif ($type !== '' && isset(ATOUR_TOUR_TYPES[$type])) {
        $where[] = 't.type = ?';
        $params[] = $type;
    }
    $regions = array_values(array_filter(array_map('strval', (array) ($p['regionSlugs'] ?? []))));
    if ($regions) {
        $where[] = 't.region_id IN (SELECT id FROM regions WHERE slug IN (' . at_in($regions) . '))';
        array_push($params, ...$regions);
    }
    foreach ((array) ($p['tagSlugs'] ?? []) as $slug) {
        $slug = (string) $slug;
        if ($slug === '') {
            continue;
        }
        $where[] = 'EXISTS (SELECT 1 FROM tour_tags tt JOIN tags g ON g.id = tt.tag_id WHERE tt.tour_id = t.id AND g.slug = ?)';
        $params[] = $slug;
    }
    $season = (string) ($p['season'] ?? '');
    if ($season !== '' && isset(ATOUR_SEASONS[$season])) {
        $where[] = 'FIND_IN_SET(?, t.seasons) > 0';
        $params[] = $season;
    }
    $age = (int) ($p['age'] ?? 0);
    if ($age > 0) {
        $where[] = '(t.age_min IS NULL OR t.age_min <= ?)';
        $params[] = $age;
        $where[] = '(t.age_max IS NULL OR t.age_max >= ?)';
        $params[] = $age;
    }
    $q = trim((string) ($p['q'] ?? ''));
    if ($q !== '') {
        $named = at_named_tour_ids($q);
        $words = at_search_words($q);
        if ($named) {
            $where[] = 't.id IN (' . at_in($named) . ')';
            array_push($params, ...$named);
        } elseif (!$words) {
            $where[] = '1 = 0';
        } else {
            foreach ($words as $word) {
                $where[] = "REPLACE(LOWER(t.search_text), 'ё', 'е') REGEXP ?";
                $params[] = at_search_pattern($word);
            }
        }
    }
    return [implode(' AND ', $where), $params];
}

/** @return array{0:int,1:int} */
function at_age_limits(): array
{
    $min = max(1, min(25, (int) at_setting('catalog.ageMin', '6')));
    return [$min, max($min, min(25, (int) at_setting('catalog.ageMax', '18')))];
}

/**
 * Сколько туров даст каждый вариант фильтра при ТЕКУЩИХ остальных условиях:
 * тип, регион, сезон, характеристика, возраст. Нужно, чтобы выпадающие списки и
 * подсказки показывали число туров до выбора, а не пустой результат после.
 *
 * @param array<string,mixed> $p как в at_tour_where
 * @return array{type:array<string,int>,region:array<string,int>,season:array<string,int>,tag:array<string,int>,age:array<string,int>}
 */
function at_tour_facets(array $p): array
{
    $without = static function (string $key) use ($p): array {
        $q = $p;
        unset($q[$key]);
        return at_tour_where($q);
    };
    $out = ['type' => [], 'region' => [], 'season' => [], 'tag' => [], 'age' => []];
    [$w, $b] = $without('type');
    foreach (at_all("SELECT t.type k, COUNT(*) c FROM tours t WHERE {$w} GROUP BY t.type", $b) as $r) {
        $out['type'][(string) $r['k']] = (int) $r['c'];
    }
    foreach (at_all("SELECT r.is_foreign k, COUNT(*) c FROM tours t JOIN regions r ON r.id = t.region_id WHERE {$w} AND t.type = 'CAMP' GROUP BY r.is_foreign", $b) as $r) {
        $out['type'][$r['k'] ? 'CAMP_ABROAD' : 'CAMP_RUSSIA'] = (int) $r['c'];
    }
    [$w, $b] = $without('regionSlugs');
    foreach (at_all("SELECT r.slug k, COUNT(*) c FROM tours t JOIN regions r ON r.id = t.region_id WHERE {$w} GROUP BY r.slug", $b) as $r) {
        $out['region'][(string) $r['k']] = (int) $r['c'];
    }
    [$w, $b] = $without('season');
    $cols = [];
    foreach (array_keys(ATOUR_SEASONS) as $season) {
        $cols[] = "SUM(FIND_IN_SET('{$season}', t.seasons) > 0) AS `{$season}`";
    }
    $row = at_one('SELECT ' . implode(', ', $cols) . " FROM tours t WHERE {$w}", $b) ?? [];
    foreach (array_keys(ATOUR_SEASONS) as $season) {
        $out['season'][$season] = (int) ($row[$season] ?? 0);
    }
    [$w, $b] = $without('tagSlugs');
    foreach (at_all("SELECT g.slug k, COUNT(*) c FROM tour_tags tt JOIN tags g ON g.id = tt.tag_id JOIN tours t ON t.id = tt.tour_id WHERE {$w} GROUP BY g.slug", $b) as $r) {
        $out['tag'][(string) $r['k']] = (int) $r['c'];
    }
    [$w, $b] = $without('age');
    [$minAge, $maxAge] = at_age_limits();
    $ages = range($minAge, $maxAge);
    if ($maxAge <= 18) { $ages[] = 21; }
    $cols = [];
    foreach ($ages as $age) {
        $cols[] = "SUM((t.age_min IS NULL OR t.age_min <= {$age}) AND (t.age_max IS NULL OR t.age_max >= {$age})) AS `a{$age}`";
    }
    $row = at_one('SELECT ' . implode(', ', $cols) . " FROM tours t WHERE {$w}", $b) ?? [];
    foreach ($ages as $age) {
        $out['age'][(string) $age] = (int) ($row['a' . $age] ?? 0);
    }
    return $out;
}

function at_tour_order(string $sort): string
{
    return match ($sort) {
        'price_asc'  => '(t.price_min IS NULL), t.price_min ASC, t.sort_order ASC, t.id ASC',
        'price_desc' => '(t.price_min IS NULL), t.price_min DESC, t.sort_order ASC, t.id ASC',
        'new'        => 't.created_at DESC, t.id DESC',
        default      => 't.is_best DESC, t.sort_order ASC, t.id ASC',
    };
}

/**
 * Список туров с пагинацией.
 *
 * @param array<string,mixed> $p как в at_tour_where + sort, page, perPage
 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,perPage:int,pages:int}
 */
function at_tours_list(array $p): array
{
    $perPage = min(max((int) ($p['perPage'] ?? 12), 1), 60);
    $page = max((int) ($p['page'] ?? 1), 1);
    [$where, $params] = at_tour_where($p);
    $total = (int) at_scalar("SELECT COUNT(*) FROM tours t WHERE {$where}", $params);
    // При текстовом запросе первыми идут туры, у которых слово в названии:
    // иначе «лаг» находил одни и те же шесть туров по слову «лагерь» в описании.
    $order = at_tour_order((string) ($p['sort'] ?? 'default'));
    $q = trim((string) ($p['q'] ?? ''));
    if ($q !== '' && ($p['sort'] ?? 'default') === 'default') {
        $rank = [];
        foreach (at_search_words($q) as $word) {
            $rank[] = "(REPLACE(LOWER(t.title), 'ё', 'е') REGEXP ?) DESC";
            $params[] = at_search_pattern($word);
        }
        if ($rank) { $order = implode(', ', $rank) . ', ' . $order; }
    }
    $rows = at_all(
        "SELECT t.* FROM tours t WHERE {$where} ORDER BY {$order}"
        . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
        $params
    );
    return ['items' => at_tours_hydrate($rows), 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'pages' => max((int) ceil($total / $perPage), 1)];
}

/**
 * Дополняет строки туров регионом, тегами и списком сезонов.
 *
 * @param array<int,array<string,mixed>> $rows
 * @return array<int,array<string,mixed>>
 */
function at_tours_hydrate(array $rows): array
{
    if (!$rows) {
        return [];
    }
    $regions = [];
    foreach (at_regions() as $r) {
        $regions[(int) $r['id']] = $r;
    }
    $ids = array_map(static fn(array $r): int => (int) $r['id'], $rows);
    $tagsByTour = [];
    foreach (at_all('SELECT tt.tour_id, g.slug, g.label FROM tour_tags tt JOIN tags g ON g.id = tt.tag_id WHERE tt.tour_id IN (' . at_in($ids) . ') ORDER BY g.sort_order, g.id', $ids) as $t) {
        $tagsByTour[(int) $t['tour_id']][] = ['slug' => $t['slug'], 'label' => $t['label']];
    }
    foreach ($rows as &$row) {
        $row['region'] = $row['region_id'] !== null ? ($regions[(int) $row['region_id']] ?? null) : null;
        $row['tags'] = $tagsByTour[(int) $row['id']] ?? [];
        $row['seasons_list'] = at_split((string) $row['seasons']);
    }
    unset($row);
    return $rows;
}

/** Карточка тура целиком: смены, фото, вкладки, теги, документы, отзывы. */
function at_tour_full(?array $tour): ?array
{
    if ($tour === null) {
        return null;
    }
    $tour = at_tours_hydrate([$tour])[0];
    $id = (int) $tour['id'];
    $tour['shifts']    = at_all('SELECT * FROM tour_shifts WHERE tour_id = ? ORDER BY sort_order, id', [$id]);
    $tour['images']    = at_all('SELECT * FROM tour_images WHERE tour_id = ? ORDER BY sort_order, id', [$id]);
    $tour['sections']  = at_all('SELECT * FROM tour_sections WHERE tour_id = ? ORDER BY sort_order, id', [$id]);
    $tour['documents'] = at_all('SELECT * FROM tour_documents WHERE tour_id = ? ORDER BY sort_order, id', [$id]);
    $tour['reviews']   = at_all('SELECT * FROM reviews WHERE tour_id = ? AND is_published = 1 ORDER BY published_at DESC LIMIT 10', [$id]);
    return $tour;
}

function at_tour_by_slug(string $slug, bool $publishedOnly = true): ?array
{
    $row = at_one('SELECT * FROM tours WHERE slug = ?' . ($publishedOnly ? ' AND is_published = 1 AND archived_at IS NULL' : ''), [$slug]);
    return at_tour_full($row);
}

function at_tour_by_id(int $id): ?array
{
    return at_tour_full(at_one('SELECT * FROM tours WHERE id = ?', [$id]));
}

/** @return array<int,array<string,mixed>> */
function at_related_tours(array $tour, int $take = 4): array
{
    $rows = at_all(
        'SELECT t.* FROM tours t WHERE t.is_published = 1 AND t.archived_at IS NULL AND t.id <> ? AND (t.region_id = ? OR t.type = ?)
         ORDER BY t.is_best DESC, t.sort_order ASC, t.id ASC LIMIT ' . $take,
        [(int) $tour['id'], (int) ($tour['region_id'] ?? -1), (string) $tour['type']]
    );
    return at_tours_hydrate($rows);
}

/** @return array{bestRussia:array,bestAbroad:array,hits:array} */
function at_home_tours(): array
{
    $best = 'SELECT t.* FROM tours t JOIN regions r ON r.id = t.region_id WHERE t.is_published = 1 AND t.archived_at IS NULL AND t.is_best = 1 AND r.is_foreign = ? ORDER BY t.sort_order, t.id LIMIT 6';
    return [
        'bestRussia' => at_tours_hydrate(at_all($best, [0])),
        'bestAbroad' => at_tours_hydrate(at_all($best, [1])),
        'hits'       => at_tours_hydrate(at_all('SELECT t.* FROM tours t WHERE t.is_published = 1 AND t.archived_at IS NULL AND t.is_hit = 1 ORDER BY t.sort_order, t.id LIMIT 6')),
    ];
}

function at_tours_count(): int
{
    return (int) at_scalar('SELECT COUNT(*) FROM tours WHERE is_published = 1 AND archived_at IS NULL');
}

// --- Главная -----------------------------------------------------------------

/** @return array{slides:array,hits:array,features:array,partners:array,platforms:array,reviews:array} */
function at_home_blocks(): array
{
    return [
        'slides'    => at_all("SELECT * FROM slides WHERE is_active = 1 AND placement = 'HERO' ORDER BY sort_order, id"),
        'hits'      => at_all("SELECT * FROM slides WHERE is_active = 1 AND placement = 'HIT' ORDER BY sort_order, id"),
        'promo'     => at_all("SELECT * FROM slides WHERE is_active = 1 AND placement = 'PROMO' ORDER BY sort_order, id"),
        'features'  => at_all('SELECT * FROM features ORDER BY sort_order, id'),
        'partners'  => at_all('SELECT * FROM partners WHERE is_active = 1 ORDER BY sort_order, id'),
        'platforms' => at_all('SELECT * FROM review_platforms ORDER BY sort_order, id'),
        'reviews'   => at_all('SELECT * FROM reviews WHERE is_published = 1 ORDER BY published_at DESC LIMIT 8'),
    ];
}

/** @return array{platforms:array,reviews:array} */
function at_reviews_data(): array
{
    return [
        'platforms' => at_all('SELECT * FROM review_platforms ORDER BY sort_order, id'),
        'reviews'   => at_all('SELECT * FROM reviews WHERE is_published = 1 ORDER BY published_at DESC LIMIT 8'),
    ];
}

// --- SEO-страницы каталога ---------------------------------------------------

function at_catalog_page(string $path): ?array
{
    $row = at_one('SELECT * FROM catalog_pages WHERE path = ?', [$path]);
    if ($row !== null) {
        $row['region_slugs_list'] = at_split((string) $row['region_slugs']);
        $row['tag_slugs_list'] = at_split((string) $row['tag_slugs']);
    }
    return $row;
}

/** @return array<int,array<string,mixed>> */
function at_catalog_pages(bool $indexableOnly = true): array
{
    return at_all('SELECT * FROM catalog_pages' . ($indexableOnly ? ' WHERE is_indexable = 1' : '') . ' ORDER BY sort_order, path');
}

// --- Новости и статьи ---------------------------------------------------------

/** @return array{items:array,total:int,page:int,perPage:int,pages:int} */
function at_posts_list(string $kind, int $page = 1, int $perPage = 9): array
{
    $page = max($page, 1);
    $total = (int) at_scalar('SELECT COUNT(*) FROM posts WHERE kind = ? AND is_published = 1', [$kind]);
    $items = at_all(
        'SELECT id, kind, slug, title, excerpt, image, published_at FROM posts WHERE kind = ? AND is_published = 1
         ORDER BY published_at DESC, id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
        [$kind]
    );
    return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'pages' => max((int) ceil($total / $perPage), 1)];
}

function at_post(string $kind, string $slug): ?array
{
    return at_one('SELECT * FROM posts WHERE kind = ? AND slug = ? AND is_published = 1', [$kind, $slug]);
}

/** @return array<int,array<string,mixed>> */
function at_latest_posts(string $kind, int $take = 3): array
{
    return at_all('SELECT id, kind, slug, title, excerpt, image, published_at FROM posts WHERE kind = ? AND is_published = 1 ORDER BY published_at DESC, id DESC LIMIT ' . $take, [$kind]);
}

function at_post_url(array $post): string
{
    return ($post['kind'] === 'NEWS' ? '/novosti/' : '/stati/') . $post['slug'] . '/';
}

// --- Текстовые страницы --------------------------------------------------------

function at_page(string $slug): ?array
{
    return at_one('SELECT * FROM pages WHERE slug = ? AND is_published = 1', [$slug]);
}

/** @return array<int,array<string,mixed>> */
function at_pages_published(): array
{
    return at_all('SELECT slug, title, updated_at FROM pages WHERE is_published = 1 ORDER BY id');
}

// --- Поиск ---------------------------------------------------------------------

/** @return array{tours:array,posts:array,pages:array} */
function at_search(string $q): array
{
    $query = mb_substr(trim($q), 0, 100);
    if (mb_strlen($query) < 2) {
        return ['tours' => [], 'posts' => [], 'pages' => []];
    }
    $words = at_search_words($query);
    $tours = at_tours_list(['q' => $query, 'perPage' => 30])['items'];
    if (!$words || at_named_tour_ids($query)) {
        return ['tours' => $tours, 'posts' => [], 'pages' => []];
    }
    $cond = [];
    $params = [];
    foreach ($words as $word) {
        $cond[] = "(REPLACE(LOWER(title), 'ё', 'е') REGEXP ? OR REPLACE(LOWER(html), 'ё', 'е') REGEXP ?)";
        $params[] = at_search_pattern($word);
        $params[] = at_search_pattern($word);
    }
    $where = $cond ? ' AND ' . implode(' AND ', $cond) : '';
    $posts = at_all('SELECT id, kind, slug, title, excerpt, image, published_at FROM posts WHERE is_published = 1' . $where . ' ORDER BY published_at DESC LIMIT 20', $params);
    $pages = at_all('SELECT slug, title FROM pages WHERE is_published = 1' . $where . ' LIMIT 10', $params);
    return ['tours' => $tours, 'posts' => $posts, 'pages' => $pages];
}

// --- Редиректы со старых адресов ---------------------------------------------

function at_redirect_lookup(string $path): ?array
{
    $withSlash = rtrim($path, '/') . '/';
    $noSlash = rtrim($path, '/');
    return at_one('SELECT * FROM redirects WHERE from_path IN (?, ?) LIMIT 1', [$withSlash, $noSlash === '' ? '/' : $noSlash]);
}

// --- Карта сайта ----------------------------------------------------------------

/** @return array<int,array{loc:string,lastmod:string,changefreq:string,priority:string}> */
function at_sitemap(): array
{
    $now = date('c');
    $out = [
        ['loc' => '/', 'lastmod' => $now, 'changefreq' => 'daily', 'priority' => '1.0'],
        ['loc' => '/tour/', 'lastmod' => $now, 'changefreq' => 'daily', 'priority' => '0.9'],
        ['loc' => '/novosti/', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.6'],
        ['loc' => '/stati/', 'lastmod' => $now, 'changefreq' => 'monthly', 'priority' => '0.5'],
    ];
    foreach (at_catalog_pages() as $c) {
        $out[] = ['loc' => '/tour/filter/' . $c['path'] . '/', 'lastmod' => $now, 'changefreq' => 'weekly', 'priority' => '0.8'];
    }
    foreach (at_all('SELECT slug, updated_at FROM tours WHERE is_published = 1 AND archived_at IS NULL ORDER BY id') as $t) {
        $out[] = ['loc' => '/tour/' . $t['slug'] . '/', 'lastmod' => at_iso($t['updated_at']), 'changefreq' => 'weekly', 'priority' => '0.8'];
    }
    foreach (at_all('SELECT kind, slug, updated_at FROM posts WHERE is_published = 1 ORDER BY id') as $p) {
        $out[] = ['loc' => at_post_url($p), 'lastmod' => at_iso($p['updated_at']), 'changefreq' => 'monthly', 'priority' => '0.5'];
    }
    foreach (at_pages_published() as $p) {
        if (str_starts_with((string) $p['slug'], 'pay')) {
            continue;
        }
        $out[] = ['loc' => '/' . $p['slug'] . '/', 'lastmod' => at_iso($p['updated_at']), 'changefreq' => 'monthly', 'priority' => '0.4'];
    }
    return $out;
}

/** Вопросы главной: пустой сохранённый список скрывает весь раздел. */
function at_home_faq(): array
{
    $raw = at_setting('home.faq');
    $items = $raw !== '' ? json_decode($raw, true) : [
        ['question' => 'Как забронировать путёвку?', 'answer' => 'Выберите лагерь и смену на странице тура и нажмите «Отправить заявку». Менеджер подтвердит наличие мест, ответит на вопросы и пришлёт договор.', 'active' => true],
        ['question' => 'Не нашли подходящий лагерь на сайте?', 'answer' => 'Оставьте заявку и расскажите о возрасте ребёнка, датах и пожеланиях. Поможем подобрать подходящий вариант.', 'active' => true],
        ['question' => 'Можно купить только билеты до лагеря?', 'answer' => 'Посмотрите раздел «Авиа и ЖД» или свяжитесь с менеджером, чтобы уточнить маршрут, даты и сопровождение.', 'active' => true],
        ['question' => 'Какие документы нужны для поездки?', 'answer' => 'Список зависит от программы. Документы размещены на странице выбранного тура и в разделе «Родителям».', 'active' => true],
    ];
    if (!is_array($items) || !array_is_list($items)) { return []; }
    $out = [];
    foreach (array_slice($items, 0, 100) as $item) {
        if (!is_array($item) || !is_string($item['question'] ?? null) || !is_string($item['answer'] ?? null)) { continue; }
        $question = mb_substr(trim($item['question']), 0, 300);
        $answer = mb_substr(trim($item['answer']), 0, 3000);
        if ($question !== '' && $answer !== '') { $out[] = ['question' => $question, 'answer' => $answer, 'active' => !empty($item['active'])]; }
    }
    return $out;
}
