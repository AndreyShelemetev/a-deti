<?php
/** Главная нового оформления. Содержимое и формы по-прежнему обслуживает CMS. */
declare(strict_types=1);
$s = at_settings();
$blocks = at_home_blocks();
$tours = at_home_tours();
// Одна подборка без дублей: хиты идут первыми, затем прежние лучшие предложения.
$homeOffers = [];
foreach (array_merge($tours['hits'], $tours['bestRussia'], $tours['bestAbroad']) as $tour) {
    $homeOffers[(int) $tour['id']] = $tour;
}
$transferTitle = trim((string) ($s['home.ticketTitle'] ?? '')) ?: 'Приобрести групповой трансфер';
$transferText = trim((string) ($s['home.ticketText'] ?? ''));
if ($transferText === '' || mb_strtolower($transferText) === 'приобрести групповой авиабилет') {
    $transferText = 'Организуем централизованную доставку детей в «Орлёнок», «Артек», лагеря Анапы и Крыма: билеты, сопровождение, питание в дороге.';
}
$faqs = array_values(array_filter(at_home_faq(), static fn(array $item): bool => $item['active']));
$regions = at_regions();
$news = at_latest_posts('NEWS', 3);
$introBadge = at_setting('home.badge', 'Туроператор в федеральном реестре');
$registryUrl = trim((string) ($s['contacts.reestrUrl'] ?? ''));
if (!preg_match('~^https?://~i', $registryUrl)) {
    $registryUrl = 'https://ev.economy.gov.ru/lk_exp/registry/to/f080bc6f-3715-418d-b48b-909090f7b331';
}
$introLead = at_setting('home.lead', 'Детские лагеря на море, в Подмосковье, на Алтае и за рубежом, спортивные сборы и экскурсии. Подбираем программу под возраст и интересы, помогаем с документами и трансфером.');
$introStats = json_decode(at_setting('home.stats', '[{"value":"30+","label":"лет с детьми"},{"value":"100 000+","label":"счастливых детей"},{"value":"4,7 / 5","label":"рейтинг родителей"}]'), true);
$introLogo = at_setting('site.logo', '/assets/design/logo.svg');
at_page_start(['key' => 'home', 'title' => at_year($s['home.seoTitle'] ?? '') ?: at_design_name(), 'description' => $s['home.metaDescription'] ?? '', 'canonical' => '/', 'bodyClass' => 'page-home']);
// HERO и PROMO из CMS показываются в одном слайдере с общим порядком.
$slides = array_merge($blocks['slides'], $blocks['promo']);
usort($slides, static fn(array $a, array $b): int => [(int) $a['sort_order'], (int) $a['id']] <=> [(int) $b['sort_order'], (int) $b['id']]);
?>
<section data-sg-block="home-intro" data-sg-name="Первый экран" class="home-intro container" aria-labelledby="home-intro-title">
  <div class="home-intro__copy">
    <?php if ($introBadge !== ''): ?><a class="home-intro__badge" href="<?= at_e($registryUrl) ?>" target="_blank" rel="noopener noreferrer"><?= at_icon('shield-check', 'ico') ?><?= at_e($introBadge) ?></a><?php endif; ?>
    <h1 class="h1 home-intro__title" id="home-intro-title"><?= at_h1('home', $s['home.h1'] ?? 'Туроператор по детскому отдыху А-тур') ?></h1>
    <p class="home-intro__lead"><?= at_e($introLead) ?></p>
    <div class="home-intro__actions">
      <a class="btn btn--ink" href="/tour/">Смотреть туры <?= at_icon('arrow-right', 'ico') ?></a>
      <a class="btn btn--sun" href="/tour/filter/lager/russia/"><?= at_icon('sun', 'ico') ?>Лагеря России</a>
      <a class="btn btn--ghost" href="/tour/filter/lager/foreign-camps/"><?= at_icon('sparkles', 'ico') ?>Лагеря за границей</a>
    </div>
    <?php if (is_array($introStats) && $introStats): ?><dl class="home-intro__stats"><?php foreach (array_slice($introStats, 0, 3) as $stat): if (!is_array($stat) || !isset($stat['value'], $stat['label']) || !is_scalar($stat['value']) || !is_scalar($stat['label'])) { continue; } ?><div><dt><?= at_e((string) $stat['value']) ?></dt><dd><?= at_e((string) $stat['label']) ?></dd></div><?php endforeach; ?></dl><?php endif; ?>
  </div>
  <div class="home-intro__brand"><?= at_img($introLogo, at_design_name(), 'home-intro__logo', ['width' => 440, 'height' => 304, 'loading' => 'eager', 'fetchpriority' => 'high']) ?></div>
</section>
<?php if ($slides): ?>
<section data-sg-block="home-promo" data-sg-name="Рекламные баннеры" class="banner container" aria-label="Акции и направления" data-design-banner>
  <div class="banner__track">
    <?php foreach ($slides as $i => $slide): ?>
    <a class="banner__slide<?= $i === 0 ? ' is-active' : '' ?>" href="<?= at_e($slide['href'] ?: '/tour/') ?>"<?= $i ? ' tabindex="-1" aria-hidden="true"' : '' ?>><?= at_img(at_banner_src($slide['image']), (string) ($slide['title'] ?? 'Наши направления'), '', ['width' => 1600, 'height' => 446, 'loading' => $i === 0 ? 'eager' : 'lazy'] + ($i === 0 ? ['fetchpriority' => 'high'] : [])) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (count($slides) > 1): ?><div class="banner__ctrl"><div class="banner__dots"><?php foreach ($slides as $i => $_): ?><button type="button" aria-label="Баннер <?= $i + 1 ?>" aria-current="<?= $i === 0 ? 'true' : 'false' ?>"></button><?php endforeach; ?></div></div><?php endif; ?>
</section>
<?php endif; ?>
<section data-sg-block="home-search" data-sg-name="Поиск тура" class="container home-tour-search" aria-label="Поиск тура" data-home-search>
  <div class="home-tour-search__panel">
    <form class="home-tour-search__filters" action="/tour/" method="get" role="search" aria-label="Подбор по параметрам" data-home-search-filters>
      <label class="home-tour-search__field"><span>Сезон</span><select class="select" name="season" aria-label="Сезон"><option value="">Все сезоны</option><?php foreach (ATOUR_SEASONS as $value => $label): ?><option value="<?= at_e($value) ?>"><?= at_e($label) ?></option><?php endforeach; ?></select></label>
      <label class="home-tour-search__field"><span>Регион</span><select class="select" name="region" aria-label="Регион"><option value="">Все регионы</option><?php foreach ($regions as $r): ?><option value="<?= at_e($r['slug']) ?>"><?= at_e($r['name']) ?></option><?php endforeach; ?></select></label>
      <label class="home-tour-search__field"><span>Тип</span><select class="select" name="type" aria-label="Тип"><option value="">Все типы</option><?php foreach (['CAMP_RUSSIA' => 'Лагеря России', 'CAMP_ABROAD' => 'Лагеря за границей', 'TRANSFER' => 'Авиа и ЖД', 'SPORT' => 'Спортивные сборы', 'EXCURSION' => 'Экскурсии'] as $value => $label): ?><option value="<?= at_e($value) ?>"><?= at_e($label) ?></option><?php endforeach; ?></select></label>
      <button class="btn btn--accent home-tour-search__submit" type="submit">Подобрать <?= at_icon('arrow-right', 'ico') ?></button>
    </form>
    <p class="home-tour-search__separator" id="home-camp-search-label">или введите название лагеря</p>
    <form class="home-tour-search__quick" id="home-camp-search" action="/tour/" method="get" role="search" aria-labelledby="home-camp-search-label" data-home-search-quick>
      <div class="home-tour-search__input-wrap">
        <?= at_icon('search', 'ico') ?><input type="search" name="q" placeholder="Название лагеря" aria-label="Название лагеря" autocomplete="off" maxlength="100" aria-controls="home-camp-suggestions" aria-expanded="false" data-home-search-query>
        <div class="home-tour-search__suggestions" id="home-camp-suggestions" aria-label="Подсказки лагерей" data-home-search-suggestions hidden></div>
      </div>
      <button class="btn btn--accent" type="submit">Найти</button>
    </form>
    <p class="home-tour-search__status" role="status" data-home-search-status hidden></p>
  </div>
</section>
<?php if ($homeOffers): ?>
<section data-sg-block="home-hits" data-sg-name="Хиты продаж" class="section container home-offers" id="hits" aria-labelledby="home-hits-title">
  <div class="section__head"><h2 class="h2" id="home-hits-title">Хиты продаж</h2><a class="btn btn--ghost btn--sm" href="/tour/">Все туры <?= at_icon('arrow-right', 'ico') ?></a></div>
  <div class="cards"><?php foreach ($homeOffers as $tour) { echo at_tour_card($tour, false, true); } ?></div>
</section>
<?php endif; ?>
<section data-sg-block="home-transfer" data-sg-name="Групповой трансфер" class="section container" id="avia"><div class="avia avia--blue tile">
  <div class="avia__copy"><span class="avia__label"><?= at_icon('plane', 'ico') ?>Авиа и ЖД</span><h2 class="h2 avia__title"><?= at_e($transferTitle) ?></h2><p class="avia__text"><?= at_e($transferText) ?></p></div>
  <div class="avia__btns"><a class="btn btn--sun" href="/tour/filter/transfer/">Расписание и цены</a><button class="btn btn--ghost" type="button" data-modal="request">Оставить заявку</button></div>
</div></section>
<?php if ($news): ?><section data-sg-block="home-news" data-sg-name="Новости" class="section container" id="news"><div class="section__head"><h2 class="h2">Наши новости</h2><div class="section__actions"><button class="btn btn--ghost btn--sm" type="button" data-modal="subscribe">Подписаться</button><a class="btn btn--ghost btn--sm" href="/novosti/">Все новости <?= at_icon('arrow-right', 'ico') ?></a></div></div><div class="news"><?php foreach ($news as $post) { echo at_post_card($post); } ?></div></section><?php endif; ?>
<section data-sg-block="home-about" data-sg-name="Почему выбирают нас" class="section container about-sec" id="about"><div class="section__head"><h2 class="h2">Почему родители выбирают <?= at_e(at_design_name()) ?></h2></div><ul class="about">
  <?php foreach ($blocks['features'] as $feature): ?><li><span class="about__logo"><?= at_img($feature['icon'] ?? '', '') ?></span><p><?= at_e($feature['body']) ?></p></li><?php endforeach; ?>
  <?php foreach ([['documents', 'Официальный договор и полный пакет документов для лагеря'], ['travel', 'Сопровождение детей в поезде и самолёте, встреча на месте'], ['support', 'Менеджер на связи в течение всей смены']] as [$art, $text]): ?><li><span class="about__art" aria-hidden="true"><img src="/assets/design/benefit-<?= at_e($art) ?>.svg" width="84" height="84" loading="lazy" alt=""></span><p><?= $text ?></p></li><?php endforeach; ?>
</ul></section>
<div data-sg-block="home-reviews" data-sg-name="Отзывы"><?= at_reviews_block($blocks['platforms'], $blocks['reviews'], (string) ($s['reviews.intro'] ?? '')) ?></div>
<?php if ($faqs): ?><section data-sg-block="home-faq" data-sg-name="Вопросы родителей" class="section container faq" id="faq"><div class="section__head"><h2 class="h2">Вопросы родителей</h2></div>
  <?php foreach ($faqs as $i => $faq): ?><details<?= $i === 0 ? ' open' : '' ?>><summary><?= at_e($faq['question']) ?></summary><div><?= nl2br(at_e($faq['answer'])) ?></div></details><?php endforeach; ?>
</section><?php endif; ?>
<?php if ($blocks['partners']): ?><section data-sg-block="home-partners" data-sg-name="Наши партнёры" class="section container partners"><div class="section__head"><h2 class="h2">Наши партнёры</h2></div><ul class="partners__list"><?php foreach ($blocks['partners'] as $partner): ?><li><?= at_img($partner['logo'], $partner['name']) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
<div data-sg-block="home-map" data-sg-name="Контакты и карта"><?= at_map_block() ?></div>
<section data-sg-block="home-request" data-sg-name="Форма заявки" class="section container" id="request"><div class="request tile tile--lime"><div class="request__intro"><h2 class="h2">Отправить заявку</h2><p class="sub">Расскажите о ребёнке и пожеланиях — подберём подходящие варианты и ответим на вопросы.</p><ul class="checks"><li>Ответим в течение рабочего дня</li><li>Подберём лагерь по возрасту, бюджету и интересам</li><li>Поможем с документами и трансфером</li></ul></div><div class="request__form"><?= at_form_request('/') ?></div></div></section>
<?php at_page_end();
