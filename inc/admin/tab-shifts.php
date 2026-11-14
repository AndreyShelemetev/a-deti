<?php
/**
 * Календарь смен: все заезды актуальных туров по месяцам, фильтры по типу,
 * направлению и наличию мест, смена наличия прямо в списке.
 */
declare(strict_types=1);

const ADM_MONTHS_NOM = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];

function adm_tab_shifts(): string
{
    $g = static fn(string $k): string => is_array($_GET[$k] ?? null) ? '' : trim((string) ($_GET[$k] ?? ''));
    $f = [
        'month' => preg_match('~^\d{4}-\d{2}$~', $g('month')) ? $g('month') : '',
        'type' => isset(ATOUR_TOUR_TYPES[$g('type')]) ? $g('type') : '',
        'region' => $g('region') === 'foreign' || preg_match('~^[a-z0-9_\-]{1,120}$~', $g('region')) ? $g('region') : '',
        'availability' => isset(ADM_AVAIL[$g('availability')]) ? $g('availability') : '',
        'q' => mb_substr($g('q'), 0, 100),
        'past' => $g('past') === '1' ? '1' : '',
    ];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'availability') {
        $avail = (string) ($_POST['availability'] ?? 'AVAILABLE');
        $seats = trim((string) ($_POST['seats_left'] ?? ''));
        db()->prepare('UPDATE tour_shifts SET availability = ?, seats_left = ? WHERE id = ?')
            ->execute([isset(ADM_AVAIL[$avail]) ? $avail : 'AVAILABLE', $seats !== '' && ctype_digit($seats) ? (int) $seats : null, (int) ($_POST['id'] ?? 0)]);
        parse_str((string) ($_POST['ret'] ?? ''), $ret);
        $back = [];
        foreach (array_keys($f) as $k) {
            if (isset($ret[$k]) && !is_array($ret[$k]) && (string) $ret[$k] !== '') {
                $back[$k] = mb_substr((string) $ret[$k], 0, 120);
            }
        }
        adm_redirect('shifts', $back + ['ok' => 1, 'msg' => 'Наличие мест обновлено — на сайте уже видно.']);
    }
    $w = ['t.archived_at IS NULL'];
    $p = [];
    if ($f['month'] !== '') {
        $w[] = 's.start_date >= ? AND s.start_date < ?';
        $p[] = $f['month'] . '-01';
        $p[] = date('Y-m-01', strtotime($f['month'] . '-01 +1 month'));
    } elseif ($f['past'] === '') {
        $w[] = '(s.end_date IS NULL OR s.end_date >= CURDATE())';
    }
    if ($f['type'] !== '') {
        $w[] = 't.type = ?';
        $p[] = $f['type'];
    }
    if ($f['region'] === 'foreign') {
        $w[] = 't.region_id IN (SELECT id FROM regions WHERE is_foreign = 1)';
    } elseif ($f['region'] !== '') {
        $w[] = 't.region_id IN (SELECT id FROM regions WHERE slug = ?)';
        $p[] = $f['region'];
    }
    if ($f['availability'] !== '') {
        $w[] = 's.availability = ?';
        $p[] = $f['availability'];
    }
    if ($f['q'] !== '') {
        $w[] = 't.title LIKE ?';
        $p[] = '%' . at_like($f['q']) . '%';
    }
    $rows = at_all('SELECT s.*, t.id AS tour_id, t.title, t.type, t.is_published, r.name AS region_name FROM tour_shifts s JOIN tours t ON t.id = s.tour_id LEFT JOIN regions r ON r.id = t.region_id
        WHERE ' . implode(' AND ', $w) . ' ORDER BY (s.start_date IS NULL), s.start_date, t.sort_order, t.id, s.sort_order LIMIT 500', $p);
    $groups = [];
    $seats = ['AVAILABLE' => 0, 'FEW' => 0, 'SOLD_OUT' => 0];
    foreach ($rows as $r) {
        $key = $r['start_date'] !== null ? substr((string) $r['start_date'], 0, 7) : 'без даты';
        $groups[$key][] = $r;
        $seats[(string) $r['availability']] = ($seats[(string) $r['availability']] ?? 0) + 1;
    }
    $label = static fn(string $key): string => $key === 'без даты' ? $key : ADM_MONTHS_NOM[(int) substr($key, 5, 2) - 1] . ' ' . substr($key, 0, 4);
    $ret = http_build_query(array_filter($f, static fn(string $v): bool => $v !== ''));

    $h = '<p class="sub-title">' . count($rows) . ' ' . at_plural(count($rows), ['смена', 'смены', 'смен']) . ' · есть места ' . $seats['AVAILABLE'] . ' · мало ' . $seats['FEW'] . ' · нет мест ' . $seats['SOLD_OUT']
        . '. Наличие мест меняется прямо здесь и сразу отображается на сайте.</p>';
    $h .= '<div class="row" style="margin-bottom:12px"><a href="' . adm_url('tours') . '" class="btn g">К списку туров</a></div>';
    $opt = static function (array $options, string $cur, string $any): string {
        $o = '<option value="">' . adm_e($any) . '</option>';
        foreach ($options as $k => $v) {
            $o .= '<option value="' . adm_e((string) $k) . '"' . ((string) $k === $cur ? ' selected' : '') . '>' . adm_e($v) . '</option>';
        }
        return $o;
    };
    $months = [];
    for ($i = -3; $i < 15; $i++) {
        $m = date('Y-m', strtotime(date('Y-m-01') . ' ' . $i . ' month'));
        $months[$m] = $label($m);
    }
    $regionOpts = ['foreign' => 'За границей (все страны)'];
    foreach (at_regions() as $r) {
        $regionOpts[$r['slug']] = ($r['is_foreign'] ? '· ' : '') . $r['name'];
    }
    $h .= '<form method="get" class="card"><input type="hidden" name="tab" value="shifts"><div class="fgrid">'
        . '<label>Месяц<select name="month">' . $opt($months, $f['month'], 'Все будущие') . '</select></label>'
        . '<label>Тип<select name="type">' . $opt(ATOUR_TOUR_TYPES, $f['type'], 'Все типы') . '</select></label>'
        . '<label>Направление<select name="region">' . $opt($regionOpts, $f['region'], 'Все направления') . '</select></label>'
        . '<label>Места<select name="availability">' . $opt(ADM_AVAIL, $f['availability'], 'Любое наличие') . '</select></label>'
        . '<label>Тур<input type="text" name="q" value="' . adm_e($f['q']) . '" placeholder="Название тура"></label>'
        . '<div class="btns"><label class="inline" style="margin:0 0 0 2px"><input type="checkbox" name="past" value="1"' . ($f['past'] === '1' ? ' checked' : '') . '> прошедшие</label><button class="btn">Показать</button></div>'
        . '</div></form>';
    if (!$rows) {
        return $h . '<div class="card muted">Смен с такими параметрами нет.</div>';
    }
    $today = date('Y-m-d');
    foreach ($groups as $key => $list) {
        $h .= '<h2 style="margin-top:18px;text-transform:capitalize">' . adm_e($label((string) $key)) . ' <span class="muted small" style="text-transform:none">· ' . count($list) . '</span></h2>'
            . '<div class="card" style="overflow-x:auto"><table><thead><tr><th>Даты</th><th>Тур</th><th>Смена</th><th>Цена</th><th>Места</th></tr></thead><tbody>';
        foreach ($list as $s) {
            $past = $s['end_date'] !== null && $s['end_date'] < $today;
            $h .= '<tr' . ($past ? ' style="opacity:.55"' : '') . '><td style="white-space:nowrap"><b>' . adm_e($s['start_date'] !== null ? at_date_range($s['start_date'], $s['end_date']) : '—') . '</b></td>'
                . '<td><a href="' . adm_url('tours', ['id' => (int) $s['tour_id'], 'ui' => 'shifts']) . '"><b>' . adm_e($s['title']) . '</b></a><div class="muted small">' . adm_e(ATOUR_TOUR_TYPES[$s['type']] ?? $s['type'])
                . ($s['region_name'] ? ' · ' . adm_e($s['region_name']) : '') . ($s['is_published'] ? '' : ' · не опубликован') . '</div></td>'
                . '<td>' . ((string) ($s['group_name'] ?? '') !== '' ? '<span class="muted">' . adm_e($s['group_name']) . ': </span>' : '') . adm_e($s['name']) . '</td>'
                . '<td><b>' . adm_e(at_shift_price($s) ?: 'по запросу') . '</b></td>'
                . '<td><form method="post" class="row" style="flex-wrap:nowrap;gap:4px" data-autosubmit><input type="hidden" name="csrf" value="' . adm_e(adm_csrf()) . '"><input type="hidden" name="action" value="availability"><input type="hidden" name="id" value="' . (int) $s['id'] . '"><input type="hidden" name="ret" value="' . adm_e($ret) . '">'
                . '<select name="availability" class="avail-sel ' . adm_e((string) $s['availability']) . '" style="width:auto">';
            foreach (ADM_AVAIL as $k => $v) {
                $h .= '<option value="' . $k . '"' . ($k === $s['availability'] ? ' selected' : '') . '>' . $v . '</option>';
            }
            $h .= '</select><input type="number" name="seats_left" value="' . adm_e((string) ($s['seats_left'] ?? '')) . '" placeholder="шт." min="0" style="width:4.5rem;padding:6px 8px" title="Осталось мест"><noscript><button class="btn g sm">ОК</button></noscript></form></td></tr>';
        }
        $h .= '</tbody></table></div>';
    }
    return $h;
}
