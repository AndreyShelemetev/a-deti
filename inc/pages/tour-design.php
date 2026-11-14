<?php
/** Страница тура: макет АРТ-КВЕСТ, общая галерея и бронирование для всех типов тура. */
declare(strict_types=1);
[$displayTitle, $subtitle] = at_design_tour_title($tour);
$location = at_camp_location($tour);
$subtitle = trim((string) ($tour['subtitle'] ?? '')) ?: $subtitle;
// Keep a descriptive subtitle; an address now has a single home in the facts below.
$subtitleKey = static fn(string $value): string => mb_strtolower(trim((string) preg_replace('~[\s\x{00a0}]+~u', ' ', $value), " \t\n\r\0\x0B.,"));
if ($location !== '' && $subtitleKey(at_camp_location(['address' => $subtitle])) === $subtitleKey($location)) $subtitle = '';
$campFacts = at_camp_facts($tour);
foreach ($campFacts as &$fact) {
    if (preg_match('~^сезон\s*:?$~iu', $fact['label'])) {
        $fact = ['label' => 'Сезон', 'value' => at_camp_season_label($tour, $fact['value'])];
    }
}
unset($fact);
$campFacts = array_values(array_filter($campFacts, static fn(array $fact): bool => $fact['value'] !== ''));
$customFacts = (bool) $campFacts;
if (!$campFacts) {
    foreach (['Возраст' => $tour['age_text'] ?? '', 'Сезон' => at_camp_season_label($tour), 'Расположение' => $location, 'Стоимость' => at_price_label($tour)] as $label => $value) {
        if ($value !== '') $campFacts[] = ['label' => $label, 'value' => $value];
    }
}
// Keep each fact once, including custom layouts which formerly used the repeated meta line.
foreach (array_reverse(['Возраст' => $tour['age_text'] ?? '', 'Сезон' => at_camp_season_label($tour)], true) as $label => $value) {
    if ($value !== '' && !in_array($label, array_column($campFacts, 'label'), true)) array_unshift($campFacts, ['label' => $label, 'value' => $value]);
}
$details = [];
foreach ($tabs as $key => $tab) {
    if (str_ends_with($key, '_details')) { $details[substr($key, 0, -8)][] = $tab; unset($tabs[$key]); }
}
unset($tabs['camp_facts']);
$photoIndexes = [];
foreach ($images as $i => $image) $photoIndexes[at_img_src($image['url'])] ??= $i;

?>
<div class="container design-crumbs"><?= at_breadcrumbs([['name' => 'Все туры', 'href' => '/tour/'], ['name' => $displayTitle]]) ?></div>
<section class="container tour-hero">
  <div class="tour-hero__info"><h1 class="h1 tour-hero__title"><?= at_e($displayTitle) ?></h1>
    <?php if ($subtitle): ?><p class="sub tour-hero__sub"><?= at_e($subtitle) ?></p><?php endif; ?>
    <?php if (!empty($tour['is_hit'])): ?><p class="tour-hero__meta"><span>Хит продаж</span></p><?php endif; ?>
    <?php if ($campFacts): ?><dl class="camp-summary-facts"><?php foreach ($campFacts as $fact): ?><div<?= $fact['label'] === 'Расположение' ? ' class="camp-summary-fact--location"' : '' ?>><dt><?= at_e($fact['label']) ?></dt><dd><?php if ($fact['label'] === 'Расположение' && $location !== ''): ?><a class="camp-location-link" href="https://yandex.ru/maps/?text=<?= rawurlencode($location) ?>" target="_blank" rel="noopener"><?= at_e($location) ?></a><?php elseif ($fact['label'] === 'Сезон' && !empty($tour['shifts'])): ?><button type="button" class="camp-season-button" data-modal="shifts" aria-haspopup="dialog"><?= at_e($fact['value']) ?></button><?php else: ?><?= at_e($fact['value']) ?><?php endif; ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
    <?php if (!$customFacts && $tour['short']): ?><p class="design-tour-intro"><?= at_e($tour['short']) ?></p><?php endif; ?>
    <?php if (!$campFacts && $tour['tags']): ?><div class="design-tour-tags"><?php foreach (array_slice($tour['tags'], 0, 4) as $tag): ?><a class="pill" href="/tour/filter/<?= at_e($tag['slug']) ?>/"><?= at_e($tag['label']) ?></a><?php endforeach; ?></div><?php endif; ?>
  </div>
  <div class="mosaic" data-design-mosaic>
    <?php foreach (array_slice($images, 0, 5) as $i => $image): ?><button type="button" class="mosaic__item<?= $i === 0 ? ' mosaic__item--big' : '' ?><?= $i === min(count($images), 5) - 1 && count($images) > 1 ? ' mosaic__item--more' : '' ?>" data-design-photo="<?= $i ?>" aria-label="Открыть фото <?= $i + 1 ?>"><?= at_img($image['url'], $image['alt'], '', ['loading' => $i ? 'lazy' : 'eager']) ?><?php if ($i === min(count($images), 5) - 1 && count($images) > 1): ?><span>Все <?= count($images) ?> фото</span><?php endif; ?></button><?php endforeach; ?>
  </div>
</section>
<div class="container tabs-wrap"><nav class="tabs design-tour-tabs" aria-label="Разделы тура">
  <?php foreach ($tabs as $key => $tab): ?><a href="#tour-section-<?= at_e($key) ?>"><?= at_e($tab['title']) ?></a><?php endforeach; ?><a href="#tour-gallery">Фото</a><a href="#tour-prices">Смены и цены</a>
</nav></div>
<div class="container tour-layout">
  <div class="tour-main">
    <?php foreach ($tabs as $key => $tab):
        $illustrated = at_tour_illustrated_section(at_camp_section_key((string) $key, (string) $tab['title']));
        $content = $illustrated ? at_camp_section($tab['html'], $photoIndexes) : ['html' => $key === 'payment_smeny' ? at_tour_tables_html($tab['html']) : $tab['html'], 'photo' => null];
        // Фотография принадлежит этому разделу. Общая галерея не заменяет отсутствующую иллюстрацию.
    ?><section class="t-sec camp-section" id="tour-section-<?= at_e($key) ?>"><h2 class="h2"><?= at_e($tab['title']) ?></h2>
      <div class="<?= $content['photo'] ? 'camp-section-row' : 'camp-section-full' ?>"><div class="design-rich"><?= $content['html'] ?></div>
        <?php if ($content['photo']): ?><figure class="camp-section-image<?= isset($content['photo']['width']) ? ' camp-section-image--sized' : '' ?>"<?= isset($content['photo']['width']) ? ' style="width:' . (int) $content['photo']['width'] . '%"' : '' ?>><?= at_img($content['photo']['src'], $content['photo']['alt'] ?: $tab['title']) ?></figure><?php endif; ?>
      </div>
      <?php foreach ($details[$key] ?? [] as $detail): ?><details class="camp-original-details"><summary><?= at_e($detail['title']) ?></summary><div class="design-rich"><?= $detail['html'] ?></div></details><?php endforeach; ?>
    </section><?php endforeach; ?>
    <section class="t-sec" id="tour-gallery"><h2 class="h2">Фотографии тура</h2><div class="design-photo-grid"><?php foreach ($images as $i => $image): ?><button type="button" data-design-photo="<?= $i ?>"<?= $i >= 12 ? ' hidden data-camp-gallery-hidden' : '' ?> aria-label="Открыть фото <?= $i + 1 ?>"><?= at_img($image['url'], $image['alt']) ?></button><?php endforeach; ?></div><?php if (count($images) > 12): ?><button class="btn btn--ghost camp-gallery-more" type="button" data-camp-gallery-more>Показать ещё фотографии (<?= count($images) ?>)</button><?php endif; ?></section>
  </div>
  <aside class="tour-aside"><?= at_design_booking($tour) ?><p class="aside-phone">Нужна помощь? <a href="<?= at_e(at_tel_href($s['contacts.phone1'] ?? '')) ?>"><?= at_e($s['contacts.phone1'] ?? '') ?></a></p></aside>
</div>
<dialog class="design-lightbox" id="design-lightbox" aria-label="Фотографии тура"><button type="button" class="design-lb-close" aria-label="Закрыть галерею"><?= at_icon('x') ?></button><button type="button" class="design-lb-prev" aria-label="Предыдущее фото"><?= at_icon('chevron-left') ?></button><img src="<?= at_e(at_img_src($images[0]['url'])) ?>" alt="<?= at_e($images[0]['alt']) ?>"><button type="button" class="design-lb-next" aria-label="Следующее фото"><?= at_icon('chevron-right') ?></button><p class="design-lb-count"></p><script type="application/json" id="design-gallery-data"><?= json_encode(array_map(static fn(array $im): array => ['src' => at_img_src($im['url']), 'alt' => $im['alt']], $images), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script></dialog>
<div class="design-mobile-book"><span><?= at_e(at_price_label($tour)) ?></span><a class="btn btn--accent" href="#tour-prices">Выбрать смену</a></div>
<?php if ($related): ?><section class="section container"><div class="section__head"><h2 class="h2">Вам также понравится</h2></div><div class="cards"><?php foreach ($related as $other) { echo at_tour_card($other); } ?></div></section><?php endif; ?>
