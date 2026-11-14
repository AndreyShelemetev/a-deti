<?php
/**
 * Первичное наполнение базы контентом a-deti.ru из data/seed.json: справочники,
 * 43 тура со сменами/фото/вкладками/документами, SEO-страницы каталога,
 * новости и статьи, текстовые страницы, блоки главной, отзывы, настройки и
 * редиректы со старых адресов.
 *
 * Идемпотентно: записи с ключами (slug/path/kind+slug) обновляются, блоки
 * главной пересобираются целиком, отзывы и настройки, уже правленные в
 * админке, не затираются. Запускается из inc/init.php при пустом каталоге
 * (под блокировкой MySQL, чтобы два первых запроса не сидировали вдвоём) и
 * из админки кнопкой «Загрузить контент заново».
 */
declare(strict_types=1);

/** @return array<string,int> счётчики по разделам */
function at_seed_run(PDO $pdo, string $file, bool $force = false): array
{
    $seed = json_decode((string) file_get_contents($file), true);
    if (!is_array($seed)) {
        throw new \RuntimeException('data/seed.json не читается.');
    }
    $pdo->query("SELECT GET_LOCK('atour_seed', 60)")->fetchColumn();
    try {
        if (!$force && (int) $pdo->query('SELECT COUNT(*) FROM tours')->fetchColumn() > 0) {
            return []; // пока ждали блокировку, наполнил соседний запрос
        }
        return at_seed_apply($pdo, $seed);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('atour_seed')")->fetchColumn();
    }
}

function at_seed_dt(?string $iso, bool $dateOnly = false): ?string
{
    if ($iso === null || trim($iso) === '') {
        return null;
    }
    $ts = strtotime($iso);
    if ($ts === false) {
        return null;
    }
    return date($dateOnly ? 'Y-m-d' : 'Y-m-d H:i:s', $ts);
}

/** @param array<string,mixed> $seed */
function at_seed_apply(PDO $pdo, array $seed): array
{
    $n = ['regions' => 0, 'tags' => 0, 'tours' => 0, 'catalog_pages' => 0, 'posts' => 0, 'pages' => 0,
          'slides' => 0, 'features' => 0, 'partners' => 0, 'review_platforms' => 0, 'reviews' => 0,
          'settings' => 0, 'redirects' => 0];

    // Регионы и характеристики.
    $st = $pdo->prepare('INSERT INTO regions (slug, name, is_foreign, sort_order) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE name = VALUES(name), is_foreign = VALUES(is_foreign), sort_order = VALUES(sort_order)');
    foreach ((array) ($seed['regions'] ?? []) as $r) {
        $st->execute([(string) $r['slug'], (string) $r['name'], !empty($r['isForeign']) ? 1 : 0, (int) ($r['sortOrder'] ?? 0)]);
        $n['regions']++;
    }
    $st = $pdo->prepare('INSERT INTO tags (slug, label, h1, seo_title, meta_description, sort_order) VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE label = VALUES(label), h1 = VALUES(h1), seo_title = VALUES(seo_title),
        meta_description = VALUES(meta_description), sort_order = VALUES(sort_order)');
    foreach ((array) ($seed['tags'] ?? []) as $t) {
        $st->execute([(string) $t['slug'], (string) $t['label'], $t['h1'] ?? null, $t['seoTitle'] ?? null,
            $t['metaDescription'] ?? null, (int) ($t['sortOrder'] ?? 0)]);
        $n['tags']++;
    }
    $regionIds   = $pdo->query('SELECT slug, id FROM regions')->fetchAll(PDO::FETCH_KEY_PAIR);
    $regionNames = $pdo->query('SELECT slug, name FROM regions')->fetchAll(PDO::FETCH_KEY_PAIR);
    $tagIds      = $pdo->query('SELECT slug, id FROM tags')->fetchAll(PDO::FETCH_KEY_PAIR);
    $tagLabels   = $pdo->query('SELECT slug, label FROM tags')->fetchAll(PDO::FETCH_KEY_PAIR);

    // Туры.
    $tourSt = $pdo->prepare('INSERT INTO tours (slug, legacy_id, title, seo_title, meta_description, meta_keywords, short,
            address, age_text, age_min, age_max, website, price_text, price_min, price_note, cover, banner, type, seasons, season_text,
            is_best, is_hit, is_published, sort_order, search_text, region_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)
        ON DUPLICATE KEY UPDATE legacy_id = VALUES(legacy_id), title = VALUES(title), seo_title = VALUES(seo_title),
            meta_description = VALUES(meta_description), meta_keywords = VALUES(meta_keywords), short = VALUES(short),
            address = VALUES(address), age_text = VALUES(age_text), age_min = VALUES(age_min), age_max = VALUES(age_max),
            website = VALUES(website), price_text = VALUES(price_text), price_min = VALUES(price_min), price_note = VALUES(price_note), cover = VALUES(cover), banner = VALUES(banner),
            type = VALUES(type), seasons = VALUES(seasons), season_text = VALUES(season_text), is_best = VALUES(is_best),
            is_hit = VALUES(is_hit), sort_order = VALUES(sort_order), search_text = VALUES(search_text), region_id = VALUES(region_id)');
    $idSt    = $pdo->prepare('SELECT id FROM tours WHERE slug = ?');
    $shiftSt = $pdo->prepare('INSERT INTO tour_shifts (tour_id, group_name, name, start_date, end_date, price, price_text, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $imgSt   = $pdo->prepare('INSERT INTO tour_images (tour_id, url, alt, sort_order) VALUES (?, ?, ?, ?)');
    $secSt   = $pdo->prepare('INSERT INTO tour_sections (tour_id, skey, title, html, sort_order) VALUES (?, ?, ?, ?, ?)');
    $tagSt   = $pdo->prepare('INSERT IGNORE INTO tour_tags (tour_id, tag_id) VALUES (?, ?)');
    $docSt   = $pdo->prepare('INSERT INTO tour_documents (tour_id, label, url, sort_order) VALUES (?, ?, ?, ?)');
    foreach ((array) ($seed['tours'] ?? []) as $t) {
        $slug = (string) $t['slug'];
        $tagSlugs = (array) ($t['tagSlugs'] ?? []);
        $sections = (array) ($t['sections'] ?? []);
        $labels = array_map(static fn($s) => (string) ($tagLabels[$s] ?? $s), $tagSlugs);
        $searchParts = [(string) $t['title'], (string) ($t['short'] ?? ''), (string) ($t['address'] ?? ''),
            (string) ($t['ageText'] ?? ''), (string) ($regionNames[$t['regionSlug'] ?? ''] ?? '')];
        foreach ($labels as $l) {
            $searchParts[] = $l;
        }
        foreach ($sections as $s) {
            $searchParts[] = at_strip_html((string) ($s['html'] ?? ''));
        }
        $searchText = mb_strtolower(implode(' ', $searchParts));
        $tourSt->execute([
            $slug, $t['legacyId'] ?? null, (string) $t['title'], $t['seoTitle'] ?? null, $t['metaDescription'] ?? null,
            $t['metaKeywords'] ?? null, (string) ($t['short'] ?? ''), (string) ($t['address'] ?? ''), (string) ($t['ageText'] ?? ''),
            $t['ageMin'] ?? null, $t['ageMax'] ?? null, $t['website'] ?? null, $t['priceText'] ?? null, $t['priceMin'] ?? null, $t['priceNote'] ?? null,
            $t['cover'] ?? null, $t['banner'] ?? null, (string) ($t['type'] ?? 'CAMP'), at_join((array) ($t['seasons'] ?? [])), $t['seasonText'] ?? null,
            !empty($t['isBest']) ? 1 : 0, !empty($t['isHit']) ? 1 : 0, (int) ($t['sortOrder'] ?? 0), $searchText,
            isset($t['regionSlug']) && isset($regionIds[$t['regionSlug']]) ? (int) $regionIds[$t['regionSlug']] : null,
        ]);
        $idSt->execute([$slug]);
        $tourId = (int) $idSt->fetchColumn();
        foreach (['tour_shifts', 'tour_images', 'tour_sections', 'tour_tags', 'tour_documents'] as $child) {
            $pdo->prepare("DELETE FROM {$child} WHERE tour_id = ?")->execute([$tourId]);
        }
        foreach ((array) ($t['shifts'] ?? []) as $s) {
            $start = at_seed_dt($s['startDate'] ?? null, true);
            $end = at_seed_dt($s['endDate'] ?? null, true);
            if ($start === null) {
                // У части смен даты только в названии: «1 смена 01.06-21.06.2026», «с 3 по 23 июля 2026».
                [$start, $end] = at_shift_parse_dates((string) $s['name'], at_tour_year($t['seasonText'] ?? null, (string) $t['title']));
            }
            $shiftSt->execute([$tourId, $s['group'] ?? null, (string) $s['name'], $start, $end ?? $start,
                $s['price'] ?? null, $s['priceText'] ?? null, (int) ($s['sortOrder'] ?? 0)]);
        }
        foreach (array_values((array) ($t['images'] ?? [])) as $i => $url) {
            $imgSt->execute([$tourId, (string) $url, (string) $t['title'], $i]);
        }
        foreach ($sections as $s) {
            $secSt->execute([$tourId, (string) $s['key'], (string) $s['title'], (string) ($s['html'] ?? ''), (int) ($s['sortOrder'] ?? 0)]);
        }
        foreach ($tagSlugs as $ts) {
            if (isset($tagIds[$ts])) {
                $tagSt->execute([$tourId, (int) $tagIds[$ts]]);
            }
        }
        foreach ((array) ($t['documents'] ?? []) as $d) {
            $docSt->execute([$tourId, (string) $d['label'], (string) $d['url'], (int) ($d['sortOrder'] ?? 0)]);
        }
        $n['tours']++;
    }

    // SEO-страницы каталога.
    $st = $pdo->prepare('INSERT INTO catalog_pages (path, h1, seo_title, meta_description, seo_text, type, region_slugs, tag_slugs, season, is_indexable, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE h1 = VALUES(h1), seo_title = VALUES(seo_title), meta_description = VALUES(meta_description),
            seo_text = VALUES(seo_text), type = VALUES(type), region_slugs = VALUES(region_slugs), tag_slugs = VALUES(tag_slugs),
            season = VALUES(season), sort_order = VALUES(sort_order)');
    foreach ((array) ($seed['catalogPages'] ?? []) as $c) {
        $st->execute([(string) $c['path'], (string) $c['h1'], $c['seoTitle'] ?? null, $c['metaDescription'] ?? null,
            $c['seoText'] ?? null, $c['type'] ?? null, at_join((array) ($c['regionSlugs'] ?? [])), at_join((array) ($c['tagSlugs'] ?? [])),
            $c['season'] ?? null, (int) ($c['sortOrder'] ?? 0)]);
        $n['catalog_pages']++;
    }

    // Новости и статьи.
    $st = $pdo->prepare('INSERT INTO posts (kind, slug, title, seo_title, meta_description, excerpt, image, html, published_at, is_published)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE title = VALUES(title), seo_title = VALUES(seo_title), meta_description = VALUES(meta_description),
            excerpt = VALUES(excerpt), image = VALUES(image), html = VALUES(html), published_at = VALUES(published_at)');
    foreach ((array) ($seed['posts'] ?? []) as $p) {
        $st->execute([(string) $p['kind'], (string) $p['slug'], (string) $p['title'], $p['seoTitle'] ?? null, $p['metaDescription'] ?? null,
            $p['excerpt'] ?? null, $p['image'] ?? null, (string) ($p['html'] ?? ''), at_seed_dt($p['publishedAt'] ?? null) ?? date('Y-m-d H:i:s')]);
        $n['posts']++;
    }

    // Текстовые страницы.
    $st = $pdo->prepare('INSERT INTO pages (slug, title, seo_title, meta_description, meta_keywords, html, is_published) VALUES (?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE title = VALUES(title), seo_title = VALUES(seo_title), meta_description = VALUES(meta_description),
            meta_keywords = VALUES(meta_keywords), html = VALUES(html)');
    foreach ((array) ($seed['pages'] ?? []) as $p) {
        $st->execute([(string) $p['slug'], (string) $p['title'], $p['seoTitle'] ?? null, $p['metaDescription'] ?? null,
            $p['metaKeywords'] ?? null, (string) ($p['html'] ?? '')]);
        $n['pages']++;
    }

    // Блоки главной — пересобираем целиком, но ТОЛЬКО если блок есть в сиде:
    // частичный сид (миграция одного тура) не должен стирать слайды и партнёров.
    if (array_key_exists('slides', $seed)) {
        $pdo->exec('DELETE FROM slides');
        $st = $pdo->prepare('INSERT INTO slides (placement, image, href, title, subtitle, sort_order, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
        foreach ((array) $seed['slides'] as $s) {
            $st->execute([(string) ($s['placement'] ?? 'HERO'), (string) $s['image'], $s['href'] ?? null, $s['title'] ?? null, $s['subtitle'] ?? null, (int) ($s['sortOrder'] ?? 0)]);
            $n['slides']++;
        }
    }
    if (array_key_exists('features', $seed)) {
        $pdo->exec('DELETE FROM features');
        $st = $pdo->prepare('INSERT INTO features (icon, body, sort_order) VALUES (?, ?, ?)');
        foreach ((array) $seed['features'] as $f) {
            $st->execute([$f['icon'] ?? null, (string) $f['text'], (int) ($f['sortOrder'] ?? 0)]);
            $n['features']++;
        }
    }
    if (array_key_exists('partners', $seed)) {
        $pdo->exec('DELETE FROM partners');
        $st = $pdo->prepare('INSERT INTO partners (name, logo, url, sort_order, is_active) VALUES (?, ?, ?, ?, 1)');
        foreach ((array) $seed['partners'] as $p) {
            $st->execute([(string) $p['name'], (string) $p['logo'], $p['url'] ?? null, (int) ($p['sortOrder'] ?? 0)]);
            $n['partners']++;
        }
    }
    if (array_key_exists('reviewPlatforms', $seed)) {
        $pdo->exec('DELETE FROM review_platforms');
        $st = $pdo->prepare('INSERT INTO review_platforms (name, logo, url, rating, max_rating, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ((array) $seed['reviewPlatforms'] as $p) {
            $st->execute([(string) $p['name'], $p['logo'] ?? null, (string) $p['url'], (float) ($p['rating'] ?? 5), (int) ($p['max'] ?? 5), (int) ($p['sortOrder'] ?? 0)]);
            $n['review_platforms']++;
        }
    }

    // Отзывы — только если их ещё нет (владелец правит их в админке).
    if ((int) $pdo->query('SELECT COUNT(*) FROM reviews')->fetchColumn() === 0) {
        $st = $pdo->prepare('INSERT INTO reviews (author, body, rating, source, source_url, published_at, is_published) VALUES (?, ?, ?, ?, ?, ?, 1)');
        foreach ((array) ($seed['reviews'] ?? []) as $r) {
            $st->execute([(string) $r['author'], (string) $r['text'], (int) ($r['rating'] ?? 5), (string) ($r['source'] ?? 'SITE'),
                $r['sourceUrl'] ?? null, at_seed_dt($r['publishedAt'] ?? null) ?? date('Y-m-d H:i:s')]);
            $n['reviews']++;
        }
    }

    // Настройки — не перезаписываем правленные в админке.
    $st = $pdo->prepare('INSERT IGNORE INTO atour_settings (k, v) VALUES (?, ?)');
    foreach ((array) ($seed['settings'] ?? []) as $k => $v) {
        if (is_scalar($v)) {
            $st->execute([(string) $k, (string) $v]);
            $n['settings']++;
        }
    }

    // Редиректы со старых адресов.
    $st = $pdo->prepare('INSERT INTO redirects (from_path, to_path, status_code) VALUES (?, ?, 301) ON DUPLICATE KEY UPDATE to_path = VALUES(to_path)');
    foreach ((array) ($seed['redirects'] ?? []) as $r) {
        $st->execute([(string) $r['from'], (string) $r['to']]);
        $n['redirects']++;
    }
    return $n;
}
