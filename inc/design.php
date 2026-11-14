<?php
/** Presentation components for the supplied A-tour design. Data and actions stay in the existing CMS. */
declare(strict_types=1);
require_once __DIR__ . "/camp.php";
require_once __DIR__ . '/header-settings.php';

function at_design_name(): string
{
    return trim(at_setting('site.name')) ?: ATOUR_SITE_NAME;
}

function at_design_logo(): string
{
    $src = at_setting('site.logo');
    if ($src === '') {
        if (function_exists('at_logo') && !is_file(ATOUR_ROOT . '/assets/design/logo.svg')) {
            return '<a class="logo design-text-logo" href="/">' . at_logo() . '</a>';
        }
        $src = is_file(ATOUR_ROOT . '/assets/design/logo.svg') ? '/assets/design/logo.svg' : '/images/logo.svg';
    }
    return '<a class="logo design-logo" href="/" aria-label="' . at_e(at_design_name() . ' — на главную') . '">' . at_img($src, at_design_name(), '', ['width' => 75, 'height' => 52, 'loading' => 'eager']) . '</a>';
}

function at_design_nav(): array
{
    return array_values(array_filter(at_main_header_config()['links'], static fn(array $item): bool => $item['visible'] && at_payment_link_visible($item['href'])));
}

function at_design_header(): void
{
    $s = at_settings();
    $phone = (string) ($s['contacts.phone1'] ?? '');
    echo '<a class="design-skip" href="#main-content">К содержимому</a>';
    $top = at_header_config();
    if ($top['enabled']) {
        echo '<div class="topbar"><div class="container topbar__row">';
        if ($top['tagline'] !== '') echo '<span class="topbar__tag">' . at_icon('sun', 'ico') . at_e($top['tagline']) . '</span>';
        $links = array_filter($top['links'], static fn(array $item): bool => $item['visible'] && at_payment_link_visible($item['href']));
        if ($links) {
            echo '<nav aria-label="Информация">';
            foreach ($links as $item) echo '<a href="' . at_e($item['href']) . '">' . at_e($item['label']) . '</a>';
            echo '</nav>';
        }
        echo at_look_switch() . '</div></div>';
    }
    echo '<header class="header"><div class="container header__row">' . at_design_logo();
    echo '<nav class="nav" id="design-nav" aria-label="Основное меню"><div class="nav__main">';
    foreach (at_design_nav() as $item) {
        echo '<a href="' . at_e($item['href']) . '">' . at_e($item['label']) . '</a>';
    }
    echo '</div><div class="nav__extra"><a class="nav__phone" href="' . at_e(at_tel_href($phone)) . '">' . at_e($phone) . '</a>' . at_header_request_button('btn btn--accent btn--wide') . '</div></nav>';
    echo '<div class="header__contacts"><a class="header__phone" href="' . at_e(at_tel_href($phone)) . '">' . at_icon('phone', 'ico') . at_e($phone) . '</a><button class="header__call" type="button" data-modal="callback">Заказать звонок</button>' . at_header_request_button('btn btn--accent btn--sm') . '</div>';
    echo '<button class="burger" type="button" id="design-burger" aria-expanded="false" aria-controls="design-nav" aria-label="Меню"><span></span><span></span></button></div></header>';
}

function at_design_footer(): void
{
    $s = at_settings();
    $lists = [
        'Каталог' => function_exists('at_footer_catalog') ? at_footer_catalog() : AT_FOOTER_CATALOG,
        'Информация' => AT_FOOTER_PAGES,
    ];
    $footerLogo = at_setting('site.footerLogo', is_file(ATOUR_ROOT . '/assets/design/logo.svg') ? '/assets/design/logo.svg' : '');
    if ($footerLogo === '/assets/design/logo.svg' && is_file(ATOUR_ROOT . '/assets/design/logo-footer.svg')) $footerLogo = '/assets/design/logo-footer.svg';
    $footerBrand = $footerLogo !== '' ? '<a class="footer__brand-logo" href="/" aria-label="' . at_e(at_design_name() . ' — на главную') . '">' . at_img($footerLogo, at_design_name(), '', ['width' => 132, 'height' => 91]) . '</a>' : at_design_logo();
    echo '<footer class="footer"><div class="container footer__grid"><div>' . $footerBrand
        . '<p class="footer__note">' . at_e((string) ($s['site.footerText'] ?? 'Детские лагеря в России и за рубежом, спортивные сборы, экскурсии и трансфер.')) . '</p><div class="socials">';
    foreach (['vk' => 'ВКонтакте', 'telegram' => 'Telegram'] as $key => $label) {
        if (!empty($s['social.' . $key])) {
            echo '<a href="' . at_e($s['social.' . $key]) . '" target="_blank" rel="noopener">' . $label . '</a>';
        }
    }
    echo '</div></div>';
    foreach ($lists as $heading => $items) {
        echo '<div><span class="caption">' . $heading . '</span><ul class="footer__list">';
        foreach ($items as $i) {
            if (!at_payment_link_visible($i['href'])) continue;
            echo '<li><a href="' . at_e($i['href']) . '">' . at_e($i['label']) . '</a></li>';
        }
        echo '</ul></div>';
    }
    echo '<div><span class="caption">Контакты</span><ul class="footer__list">';
    foreach (['contacts.phone1', 'contacts.phone2'] as $key) {
        if (!empty($s[$key])) {
            echo '<li><a class="footer__phone" href="' . at_e(at_tel_href($s[$key])) . '">' . at_e($s[$key]) . '</a></li>';
        }
    }
    if (!empty($s['contacts.email'])) {
        echo '<li><a href="mailto:' . at_e($s['contacts.email']) . '">' . at_e($s['contacts.email']) . '</a></li>';
    }
    echo '<li>' . at_e((string) ($s['contacts.address'] ?? '')) . '</li><li class="footer__muted">' . at_hours_html((string) ($s['contacts.hours'] ?? '')) . '</li></ul></div></div>';
    echo '<div class="container footer__bottom"><span>© ' . date('Y') . ' ' . at_e(at_design_name()) . '</span><a href="/politiks/">Политика конфиденциальности</a><a href="/personal-data/">Согласие на обработку персональных данных</a>' . (at_payment_enabled() ? '<a href="/pay/">Оплата онлайн</a>' : '') . '</div></footer>';
}

/** Keep the display title compact without changing the full title or SEO fields in storage. */
function at_design_tour_title(array $tour): array
{
    $title = (string) $tour['title'];
    if (preg_match('~^(.{0,35}?«[^»]+»)(.*)$~u', $title, $m)) {
        return [trim($m[1]), trim($m[2], ' ,.—')];
    }
    return [$title, ''];
}

function at_design_tour_card(array $t, bool $priority = false, bool $showExcerpt = false): string
{
    [$title] = at_design_tour_title($t);
    $href = '/tour/' . $t['slug'] . '/';
    $season = (string) ($t['season_text'] ?? '');
    $badges = '';
    if (!empty($t['is_hit'])) {
        $badges .= '<span class="pill pill--ink">Хит продаж</span>';
    }
    $meta = !empty($t['age_text']) ? '<span class="pill pill--lime">' . at_e($t['age_text']) . '</span>' : '';
    foreach (array_slice($t['tags'] ?? [], 0, 3) as $tag) {
        $meta .= '<span class="pill">' . at_e($tag['label']) . '</span>';
    }
    return '<article class="card"><a class="card__media" href="' . at_e($href) . '" tabindex="-1" aria-hidden="true">'
        . at_img($t['cover'] ?? '', '', 'photo', $priority ? ['loading' => 'eager'] : [])
        . '<div class="card__badges">' . $badges . '</div></a><div class="card__body"><span class="card__type">' . at_e(ATOUR_TOUR_TYPES[$t['type']] ?? 'Детский отдых') . '</span><h3 class="h3"><a class="card__link" href="' . at_e($href) . '">' . at_e($title) . '</a></h3>'
        . (!empty($t['address']) ? '<p class="card__place">' . at_icon('map-pin', 'ico') . at_e($t['address']) . '</p>' : '')
        . ($showExcerpt && !empty($t['short']) ? '<p class="card__excerpt">' . at_e(at_truncate(at_strip_html((string) $t['short']), 150)) . '</p>' : '')
        . ($season !== '' ? '<p class="card__season">' . at_e($season) . '</p>' : '') . '<div class="card__meta">' . $meta . '</div><div class="card__foot"><div class="card__price"><small>Стоимость</small>' . at_e(at_price_label($t)) . '</div><a class="btn btn--accent" href="' . at_e($href) . '">Подробнее</a></div></div></article>';
}

function at_design_post_card(array $p): string
{
    $href = at_post_url($p);
    return '<article class="news__item"><a class="news__img" href="' . at_e($href) . '" tabindex="-1" aria-hidden="true">' . at_img($p['image'] ?? '', '', 'photo')
        . '</a><div class="news__body"><time datetime="' . at_e(at_iso($p['published_at'])) . '">' . at_e(at_date($p['published_at'])) . '</time><h3 class="h3"><a href="' . at_e($href) . '">' . at_e($p['title'])
        . '</a></h3><a class="news__more" href="' . at_e($href) . '">Читать ' . at_icon('arrow-right', 'ico') . '</a></div></article>';
}

/** Price and availability use the same select contract and booking action as the original widget. */
function at_design_booking(array $tour): string
{
    $shifts = $tour['shifts'] ?? [];
    $selected = null;
    foreach ($shifts as $sh) {
        if (at_shift_avail($sh)[0] !== 'SOLD_OUT') {
            $selected = $sh;
            break;
        }
    }
    $out = '<div class="shifts design-booking" id="tour-prices" data-booking-widget data-tour-id="' . (int) $tour['id'] . '" data-tour-title="' . at_e($tour['title']) . '"><h2 class="h3">Смены и цены</h2>';
    if ($shifts) {
        $options = '';
        $radios = ''; $calendarCards = ''; $months = [];
        $previewIds = array_map(static fn(array $shift): int => (int) $shift['id'], array_slice($shifts, 0, 4));
        if ($selected && !in_array((int) $selected['id'], $previewIds, true)) $previewIds[count($previewIds) - 1] = (int) $selected['id'];
        foreach ($shifts as $sh) {
            [$avail, $availText] = at_shift_avail($sh);
            $sold = $avail === 'SOLD_OUT';
            $isSelected = $selected !== null && (int) $selected['id'] === (int) $sh['id'];
            $label = trim((string) ($sh['group_name'] ?? '') . ' ' . $sh['name']);
            $visibleLabel = !empty($sh['start_date']) ? trim((string) preg_replace('~\s*\d{1,2}\.\d{1,2}.*$~u', '', $label)) : $label;
            $dates = !empty($sh['start_date']) ? at_date_range((string) $sh['start_date'], (string) ($sh['end_date'] ?? '')) : at_shift_dates($sh);
            $price = at_shift_price($sh);
            $options .= '<option value="' . (int) $sh['id'] . '" data-price="' . (int) ($sh['price'] ?? 0) . '" data-label="' . at_e($label) . '" data-price-text="' . at_e($price) . '" data-dates="' . at_e($dates) . '" data-avail-text="' . at_e($availText) . '"' . ($sold ? ' disabled' : '') . ($isSelected ? ' selected' : '') . '>' . at_e($label) . '</option>';
            $radios .= '<label class="shift' . ($sold ? ' shift--sold' : '') . '"' . (!in_array((int) $sh['id'], $previewIds, true) ? ' hidden' : '') . '><input type="radio" name="design-shift" value="' . (int) $sh['id'] . '"' . ($sold ? ' disabled' : '') . ($isSelected ? ' checked' : '') . '><span class="shift__body"><strong>' . at_e($visibleLabel ?: $label) . '</strong><span class="shift__dates">' . at_e($dates) . '</span>' . ($availText !== '' ? '<small>' . at_e($availText) . '</small>' : '') . '</span><span class="shift__price">' . at_e($price) . '</span></label>';
            $month = preg_match('~^(20\d{2}-\d{2})-~', (string) ($sh['start_date'] ?? ''), $match) ? $match[1] : '';
            if ($month !== '') $months[$month] = substr($month, 5) . '.' . substr($month, 0, 4);
            $calendarCards .= '<button type="button" class="shift-calendar__option" data-calendar-shift="' . (int) $sh['id'] . '" data-month="' . at_e($month) . '" aria-pressed="' . ($isSelected ? 'true' : 'false') . '"' . ($sold ? ' disabled' : '') . '><span><strong>' . at_e($visibleLabel ?: $label) . '</strong><span class="shift-calendar__dates">' . at_e($dates ?: 'Даты уточняются') . '</span></span><span class="shift-calendar__cost">' . at_e($price ?: 'Цена по запросу') . '<small>' . at_e($sold ? 'Мест нет' : ($availText ?: 'Выбрать смену')) . '</small></span></button>';
        }
        $out .= '<select class="booking-select" aria-label="Выбранная смена" hidden>' . $options . '</select><div class="shifts__list">' . $radios . '</div>';
        if (count($shifts) > 4) $out .= '<button type="button" class="btn btn--ghost btn--wide shifts__calendar-button" data-modal="shifts" aria-haspopup="dialog">Открыть календарь смен (' . count($shifts) . ')</button>';
    }
    $fallback = at_price_label($tour);
    $out .= '<div class="shifts__total"><span class="caption">Стоимость путёвки</span><div class="shifts__from" data-booking-price data-fallback="' . at_e($fallback) . '">' . at_e($selected ? at_shift_price($selected) : $fallback) . '</div><p class="muted" data-booking-note>Точную стоимость и наличие подтвердит менеджер</p></div>';
    $transferNote = at_camp_transfer_note($tour);
    $out .= '<button type="button" class="btn btn--accent btn--wide" data-modal="booking" data-book' . ($shifts && $selected === null ? ' disabled' : '') . '>Отправить заявку ' . at_icon('arrow-right', 'ico') . '</button><button type="button" class="btn btn--ghost btn--wide" data-modal="callback">Заказать звонок</button>' . (at_payment_enabled() ? '<a class="design-pay" href="/pay/" data-pay-link>Оплатить онлайн</a>' : '') . '<p class="shifts__note">' . at_e($transferNote ?: 'Бронирование бесплатно. Менеджер подтвердит наличие мест и поможет с документами.') . '</p></div>';
    if ($shifts) {
        ksort($months); $monthOptions = '';
        foreach ($months as $value => $monthLabel) $monthOptions .= '<option value="' . at_e($value) . '">' . at_e($monthLabel) . '</option>';
        $out .= '<dialog class="modal shift-calendar" id="modal-shifts" aria-labelledby="shift-calendar-title"><div class="shift-calendar__inner"><header class="shift-calendar__header"><div><h2 id="shift-calendar-title">Календарь смен</h2><p>' . at_e($tour['title']) . '</p></div><button type="button" class="shift-calendar__close" data-close-modal aria-label="Закрыть календарь">' . at_icon('x', 'ico') . '</button></header><div class="shift-calendar__filters"><label>Название лагеря или смены<input type="search" class="input" data-calendar-search placeholder="Найти смену" autocomplete="off"></label><label>Месяц<select class="select" data-calendar-month><option value="">Все месяцы</option>' . $monthOptions . '</select></label></div><div class="shift-calendar__results"><p class="shift-calendar__count" data-calendar-count role="status">Всего вариантов: ' . count($shifts) . '</p><div class="shift-calendar__grid">' . $calendarCards . '</div><p hidden data-calendar-empty>По выбранным условиям смен нет. Измените поиск или месяц.</p></div><footer class="shift-calendar__footer">Выберите смену — она появится в блоке бронирования. Заявка отправляется после заполнения формы.</footer></div></dialog>';
    }
    return $out;
}
