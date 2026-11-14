<?php
/**
 * Страница-лендинг тура (лента фото сразу под якорным меню, программа по дням,
 * отель, перелёт с прикидкой бюджета, документы-чеклист, отзывы).
 * Подключается из tour.php, когда у тура задан banner; сам баннер на этой
 * странице не показывается — он живёт в промо-блоке главной. Переменные —
 * из tour.php: $tour, $images, $tabs, $related, $rb, $s, $typeLabel, $typeHref.
 */
declare(strict_types=1);

$sections = [];
foreach ($tour['sections'] as $sec) {
    $sections[(string) $sec['skey']] = $sec;
}
// Программа по дням: <div class="days"><section class="day"><h4>1 день</h4><ul><li class="hl">…</li></ul></section>…
$days = [];
$programIntro = '';
if (isset($sections['program'])) {
    $html = (string) $sections['program']['html'];
    if (preg_match('~<div class="days">(.*)</div>\s*$~su', $html, $m)) {
        $programIntro = trim(substr($html, 0, (int) strpos($html, '<div class="days">')));
        preg_match_all('~<section class="day">\s*<h4>(.*?)</h4>\s*<ul>(.*?)</ul>\s*</section>~su', $m[1], $dm, PREG_SET_ORDER);
        foreach ($dm as $d) {
            preg_match_all('~<li(?<hl>\s+class="hl")?>(?<t>.*?)</li>~su', $d[2], $lm, PREG_SET_ORDER);
            $days[] = ['title' => trim(strip_tags($d[1])), 'items' => array_map(static fn(array $li): array => ['text' => trim(strip_tags($li['t'])), 'hl' => $li['hl'] !== ''], $lm)];
        }
    } else {
        $programIntro = $html;
    }
}
$stops = [];
foreach ($days as $d) {
    foreach ($d['items'] as $it) {
        if ($it['hl'] && !in_array($it['text'], $stops, true)) {
            $stops[] = $it['text'];
        }
    }
}
$flightPrice = 0;
if (isset($sections['transfer']) && preg_match('~data-price="(\d+)"~', (string) $sections['transfer']['html'], $pm)) {
    $flightPrice = (int) $pm[1];
}
$hotel = isset($sections['stay']) && preg_match('~<b>Отель[^<]*</b>\s*([^<]+)~u', (string) $sections['stay']['html'], $hm) ? trim($hm[1]) : '';
$docItems = [];
if (isset($sections['documents']) && preg_match_all('~<li[^>]*>(.*?)</li>~su', (string) $sections['documents']['html'], $dm2)) {
    $docItems = array_map(static fn(string $x): string => trim(strip_tags($x)), $dm2[1]);
}
$anchors = [];
if (count($images) > 1 || $images[0]['url'] !== '') {
    $anchors['photos'] = 'Фото';   // лента фото идёт первой, сразу под якорным меню
}
foreach ($tour['sections'] as $sec) {
    $anchors[(string) $sec['skey']] = (string) $sec['title'];
}
$anchors['reviews'] = 'Отзывы';
$facts = array_values(array_filter([
    $days ? ['n' => count($days), 'label' => at_plural(count($days), ['день', 'дня', 'дней']) . ' в Пекине', 'ic' => 'calendar-range', 'tone' => 'brand'] : null,
    $tour['age_text'] ? ['n' => $tour['age_text'], 'label' => 'возраст участников', 'ic' => 'users', 'tone' => 'berry'] : null,
    $hotel !== '' ? ['n' => 'Отель 4*', 'label' => 'в центре Пекина, питание 3 раза', 'ic' => 'building', 'tone' => 'mint'] : null,
    $flightPrice > 0 ? ['n' => 'от ' . at_price($flightPrice), 'label' => 'групповой перелёт с куратором', 'ic' => 'plane', 'tone' => 'sun'] : null,
]));
$sectionBody = static function (string $key, array $sec) use ($programIntro, $days, $stops, $flightPrice, $docItems, $tour): string {
    $html = (string) $sec['html'];
    switch ($key) {
        case 'program':
            $out = $programIntro !== '' ? at_rich($programIntro) : '';
            if ($days) {
                $out .= '<div class="days-box" data-days>'
                    . '<div class="days-tabs" role="tablist" aria-label="Дни программы">';
                foreach ($days as $i => $d) {
                    $name = trim((string) preg_replace('~^\d+\s*~u', '', $d['title'])) ?: 'день';
                    $out .= '<button type="button" role="tab" class="day-tab' . ($i === 0 ? ' is-on' : '') . '" data-day-tab="' . $i . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '"><span class="day-num">' . ($i + 1) . '</span><span class="day-name">' . at_e($name) . '</span></button>';
                }
                $out .= '<button type="button" class="day-all" data-days-all aria-pressed="false">Все дни</button></div><div class="days-panels">';
                foreach ($days as $i => $d) {
                    $out .= '<section class="day-panel' . ($i === 0 ? ' is-on' : '') . '" data-day-panel="' . $i . '"' . ($i === 0 ? '' : ' hidden') . '><h3 class="day-title">' . at_e($d['title']) . '</h3><ol class="timeline">';
                    foreach ($d['items'] as $it) {
                        $out .= '<li class="tl' . ($it['hl'] ? ' tl-hl' : '') . '"><span class="tl-dot">' . ($it['hl'] ? at_icon('map-pin', 'ic') : '') . '</span><span class="tl-text">' . at_e($it['text']) . '</span></li>';
                    }
                    $out .= '</ol></section>';
                }
                $out .= '</div></div>';
                if ($stops) {
                    $out .= '<div class="stops"><div class="panel-label">' . at_icon('map-pin', 'ic coral') . ' Что увидим за ' . count($days) . ' ' . at_plural(count($days), ['день', 'дня', 'дней']) . '</div><div class="cluster">';
                    foreach ($stops as $st) {
                        $out .= '<span class="chip chip-white">' . at_e($st) . '</span>';
                    }
                    $out .= '</div></div>';
                }
            }
            foreach ($tour['documents'] as $doc) {
                $out .= '<a href="' . at_e($doc['url']) . '" target="_blank" rel="noopener" class="btn-outline doc-btn">' . at_icon('file-down') . ' ' . at_e($doc['label']) . '</a>';
            }
            return $out;
        case 'transfer':
            $out = '<div class="ticket-card">' . at_rich($html) . '</div>';
            if ($flightPrice > 0) {
                $out .= '<div class="budget panel" data-budget data-price="' . $flightPrice . '"><div class="budget-head"><div class="panel-label">' . at_icon('percent', 'ic brand') . ' Прикинуть бюджет перелёта</div>'
                    . '<div class="stepper" role="group" aria-label="Число участников"><button type="button" class="step-btn" data-step="-1" aria-label="Меньше">−</button><output class="step-value" data-budget-n>1</output><button type="button" class="step-btn" data-step="1" aria-label="Больше">+</button></div></div>'
                    . '<div class="budget-total">от <b data-budget-total>' . at_e(at_price($flightPrice)) . '</b> <span class="muted">за перелёт на <span data-budget-label>1 человека</span></span></div>'
                    . '<p class="budget-note">Стоимость самой программы (отель, питание, экскурсии, страховка) рассчитывается по запросу — оставьте заявку, менеджер пришлёт точную сумму.</p></div>';
            }
            return $out;
        case 'documents':
            if (!$docItems) {
                return at_rich($html);
            }
            $out = '<div class="checklist-box" data-checklist="tour-' . (int) $tour['id'] . '"><div class="checklist-head"><span>Отметьте, что уже готово</span><b data-checklist-count>0 из ' . count($docItems) . '</b></div><ul class="checklist">';
            foreach ($docItems as $i => $item) {
                $out .= '<li><label class="check-item"><input type="checkbox" data-check="' . $i . '"><span class="check-box">' . at_icon('check', 'ic') . '</span><span>' . at_e($item) . '</span></label></li>';
            }
            return $out . '</ul><p class="budget-note">Поможем с документами: подскажем, где оформить согласие и проверить паспорт.</p></div>';
        case 'stay':
            return '<div class="stay-card">' . at_rich($html) . '</div>';
        default:
            return at_rich($html);
    }
};
?>
<div class="container-site page-top landing-top">
  <?= at_breadcrumbs([['name' => 'Все туры', 'href' => '/tour/'], ['name' => $typeLabel, 'href' => $typeHref], ['name' => $tour['title']]]) ?>
  <div class="cluster tour-badges">
    <?php // Сезонные плашки на лендинге не показываем: сроки задаёт смена в блоке «Смены и цены». ?>
    <?php if ($tour['is_best']): ?><?= at_badge('sun', '★ Выбор родителей') ?><?php endif; ?>
    <?php if ($tour['region']): ?><?= at_badge('ink', at_e($tour['region']['name'])) ?><?php endif; ?>
  </div>
  <h1 class="page-h1 tour-h1"><?= at_h1('tour-' . $tour['slug'], $tour['title']) ?></h1>
  <?php if ($tour['short']): ?><p class="page-lead"><?= at_e($tour['short']) ?></p><?php endif; ?>
  <?php if ($facts): ?>
    <div class="facts">
      <?php foreach ($facts as $f): ?><div class="fact"><span class="fact-ic fact-<?= at_e($f['tone']) ?>"><?= at_icon($f['ic']) ?></span><b><?= at_e((string) $f['n']) ?></b><span><?= at_e($f['label']) ?></span></div><?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
<nav class="subnav" data-subnav aria-label="Разделы страницы"><div class="container-site subnav-in">
  <?php foreach ($anchors as $key => $title): ?><a href="#<?= at_e($key) ?>" data-subnav-link="<?= at_e($key) ?>"><?= at_e($title) ?></a><?php endforeach; ?>
</div></nav>
<div class="container-site">
  <div class="tour-grid-page landing-grid">
    <div class="tour-main">
      <?php if (isset($anchors['photos'])): ?>
        <section class="landing-section landing-photos" id="photos" data-subnav-section="photos">
          <div class="gallery" data-gallery data-gallery-grid>
            <div class="photo-strip-head"><span class="panel-label"><?= at_icon('image', 'ic brand') ?> Фото · <?= count($images) ?></span></div>
            <div class="photo-strip">
              <?php foreach ($images as $i => $im): ?>
                <button type="button" class="photo-cell" data-gallery-thumb="<?= $i ?>" data-src="<?= at_e(at_img_src($im['url'])) ?>" data-alt="<?= at_e($im['alt']) ?>" aria-label="Фото <?= $i + 1 ?>: открыть на весь экран"><?= at_img($im['url'], $im['alt'], '', ['loading' => $i < 4 ? 'eager' : 'lazy']) ?><span class="photo-zoom" aria-hidden="true"><?= at_icon('maximize') ?></span></button>
              <?php endforeach; ?>
            </div>
            <dialog class="lightbox" data-lightbox aria-label="Галерея">
              <button type="button" class="lightbox-close" data-lightbox-close aria-label="Закрыть"><?= at_icon('x') ?></button>
              <button type="button" class="lightbox-full" data-lightbox-full aria-label="На весь экран" title="На весь экран"><?= at_icon('maximize') ?></button>
              <img src="" alt="" data-lightbox-img>
              <?php if (count($images) > 1): ?>
                <button type="button" class="gallery-nav gallery-prev" data-gallery-prev aria-label="Предыдущее фото"><?= at_icon('chevron-left') ?></button>
                <button type="button" class="gallery-nav gallery-next" data-gallery-next aria-label="Следующее фото"><?= at_icon('chevron-right') ?></button>
              <?php endif; ?>
              <div class="lightbox-counter"><span data-gallery-index>1</span> / <?= count($images) ?></div>
            </dialog>
          </div>
        </section>
      <?php endif; ?>
      <?php foreach ($tour['sections'] as $sec): $key = (string) $sec['skey']; ?>
        <section class="landing-section" id="<?= at_e($key) ?>" data-subnav-section="<?= at_e($key) ?>">
          <h2 class="landing-h2"><?= at_e($sec['title']) ?></h2>
          <?= $sectionBody($key, $sec) ?>
        </section>
      <?php endforeach; ?>
      <section class="landing-section" id="reviews" data-subnav-section="reviews">
        <h2 class="landing-h2">Отзывы</h2>
        <?= $tabs['reviews']['html'] ?>
      </section>
    </div>
    <aside class="tour-aside"><?= at_booking_widget($tour) ?></aside>
  </div>
  <div class="tour-booking-mobile"><?= at_booking_widget($tour) ?></div>
  <?php if ($related): ?>
    <section class="mt-14"><?= at_section_title('Похожие', 'предложения') ?><div class="tour-grid tour-grid-4"><?php foreach ($related as $t) { echo at_tour_card($t); } ?></div></section>
  <?php endif; ?>
  <p class="tour-contacts-line">Вопросы по программе: <?= at_e($s['contacts.phone1'] ?? '') ?> · <?= at_e($s['contacts.email'] ?? '') ?></p>
</div>
