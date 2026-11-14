<?php
/** Интерактивные дни программы, отель, перелёт и документы внутри единого макета тура. */
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
