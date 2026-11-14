<?php
/** Карточка тура /tour/<slug>/: галерея, смены и цены, вкладки, документы, отзывы, похожие. */
declare(strict_types=1);

/** @var string $slug */
$tour = at_tour_by_slug($slug);
if ($tour === null) {
    $redir = at_redirect_lookup('/tour/' . $slug . '/');
    if ($redir !== null) {
        at_redirect((string) $redir['to_path'], 301);
    }
    require __DIR__ . '/notfound.php';
    return;
}
$related = at_related_tours($tour);
$rb = at_reviews_data();
$s = at_settings();
$tourYear = at_tour_year($tour['season_text'] ?? null, $tour['title'] ?? null);
// Resolve only the legacy placeholder; explicit years and stored CMS content stay intact.
foreach ($tour['sections'] as &$section) $section['html'] = at_year((string) $section['html'], $tourYear);
unset($section);
$images = [];
if (!empty($tour['cover'])) {
    $images[] = ['url' => $tour['cover'], 'alt' => $tour['title']];
}
foreach ($tour['images'] as $im) {
    if ($im['url'] !== ($tour['cover'] ?? '')) {
        $images[] = ['url' => $im['url'], 'alt' => $im['alt'] ?: $tour['title']];
    }
}
if (!$images) {
    $images[] = ['url' => '', 'alt' => $tour['title']];
}
foreach ($tour['sections'] as $section) {
    if (!at_tour_illustrated_section((string) $section['skey'])) continue;
    $document = at_camp_dom((string) $section['html']);
    foreach ($document->getElementsByTagName('img') as $image) {
        $src = trim($image->getAttribute('src'));
        if (preg_match('~^(?:/(?!/)|https?://)~i', $src) && !in_array($src, array_column($images, 'url'), true)) {
            $images[] = ['url' => $src, 'alt' => $image->getAttribute('alt') ?: $tour['title']];
        }
    }
}
$offers = [];
foreach ($tour['shifts'] as $sh) {
    if (!empty($sh['price'])) {
        $offer = ['@type' => 'Offer', 'name' => $sh['name'], 'price' => (int) $sh['price'], 'priceCurrency' => 'RUB', 'availability' => 'https://schema.org/InStock', 'url' => at_abs('/tour/' . $tour['slug'] . '/')];
        if (!empty($sh['start_date'])) {
            $offer['validFrom'] = at_iso($sh['start_date']);
        }
        $offers[] = $offer;
    }
}
$productLd = [
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $tour['title'], 'description' => (string) $tour['short'],
    'image' => array_map(static fn(array $i): string => at_abs(at_img_src($i['url'])), array_slice(array_filter($images, static fn(array $i): bool => $i['url'] !== ''), 0, 6)),
    'brand' => ['@type' => 'Organization', 'name' => ATOUR_SITE_NAME],
];
if ($offers) {
    $productLd['offers'] = count($offers) === 1 ? $offers[0]
        : ['@type' => 'AggregateOffer', 'lowPrice' => (int) ($tour['price_min'] ?? 0), 'priceCurrency' => 'RUB', 'offerCount' => count($offers), 'offers' => $offers];
}
// Единый макет используется для всех типов тура; содержимое экскурсий сохраняет интерактивные блоки.
$hasBanner = trim((string) ($tour['banner'] ?? '')) !== '';
if ($hasBanner) require __DIR__ . '/tour-content.php';
at_page_start([
    'bodyClass'   => 'page-tour page-tour-modern',
    'key'         => 'tour-' . $tour['slug'],
    'title'       => at_year((string) $tour['seo_title'], $tourYear) ?: $tour['title'],
    'description' => at_year((string) ($tour['meta_description'] ?: ($tour['short'] ?: $tour['title'] . ': описание программы, смены и цены, документы, отзывы. Бронирование у туроператора А-тур.')), $tourYear),
    'keywords'    => (string) $tour['meta_keywords'],
    'canonical'   => '/tour/' . $tour['slug'] . '/',
    'image'       => (string) ($tour['cover'] ?? ''),
    'jsonld'      => [$productLd],
]);
$typeLabel = ATOUR_TOUR_TYPES[$tour['type']] ?? $tour['type'];
$typeHref = ATOUR_TYPE_PATHS[$tour['type']] ?? '/tour/';
$tabs = [];
foreach ($tour['sections'] as $sec) {
    $tabs[$sec['skey']] = ['title' => $sec['title'], 'html' => $hasBanner ? $sectionBody((string) $sec['skey'], $sec) : at_rich((string) $sec['html'])];
}
if (!$hasBanner && $tour['documents']) {
    $docs = '<ul class="docs-list">';
    foreach ($tour['documents'] as $d) {
        $docs .= '<li><a href="' . at_e($d['url']) . '" target="_blank" rel="noopener">' . at_icon('file-down', 'ic') . at_e($d['label']) . '</a></li>';
    }
    $docs .= '</ul>';
    if (isset($tabs['for_parents'])) {
        $tabs['for_parents']['html'] .= $docs;
    } else {
        $tabs['docs'] = ['title' => 'Документы', 'html' => $docs];
    }
}
// У всех лагерей один порядок разделов, независимо от структуры текста в CMS.
if (($tour['type'] ?? '') === 'CAMP') {
    $sectionsForDisplay = [];
    foreach ($tabs as $key => $tab) $sectionsForDisplay[] = ['skey' => $key] + $tab;
    $tabs = [];
    foreach (at_camp_order_sections($sectionsForDisplay) as $section) {
        $tabs[$section['skey']] = ['title' => $section['title'], 'html' => $section['html']];
    }
}
// Document cards share a layout; every tour keeps its own text and download links.
$tabs = at_tour_service_tabs($tabs, (string)($tour['type'] ?? ''));
$reviewsForTab = array_slice(array_merge($tour['reviews'], $rb['reviews']), 0, 6);
$tabs['reviews'] = ['title' => 'Отзывы', 'html' => at_reviews_block($rb['platforms'], $reviewsForTab, '', (int) $tour['id'], true)];
require __DIR__ . '/tour-design.php';
at_page_end();
