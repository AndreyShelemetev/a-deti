<?php
/** Обзор: счётчики и последние заявки. */
declare(strict_types=1);

function adm_tab_dashboard(): string
{
    $c = static fn(string $sql, array $p = []): int => (int) at_scalar($sql, $p);
    $cards = [
        ['a', $c("SELECT COUNT(*) FROM requests WHERE status = 'NEW'"), 'Новые заявки', adm_url('requests', ['status' => 'NEW'])],
        ['b', $c('SELECT COUNT(*) FROM tours WHERE is_published = 1') . ' / ' . $c('SELECT COUNT(*) FROM tours'), 'Туры (опубликовано)', adm_url('tours')],
        ['c', $c('SELECT COUNT(*) FROM posts'), 'Новости и статьи', adm_url('posts')],
        ['d', $c('SELECT COUNT(*) FROM reviews WHERE is_published = 0'), 'Отзывы на модерации', adm_url('reviews', ['published' => '0'])],
        ['e', $c("SELECT COUNT(*) FROM payments WHERE status IN ('NEW', 'REDIRECTED')"), 'Платежи в работе', adm_url('payments')],
    ];
    $h = '<div class="stats">';
    foreach ($cards as [$tone, $value, $label, $href]) {
        $h .= '<a class="stat ' . $tone . '" href="' . adm_e($href) . '"><b>' . adm_e((string) $value) . '</b>' . adm_e($label) . '</a>';
    }
    // Туры, которым не хватает данных для продажи, и ближайшие заезды — то, с чего начинается день менеджера.
    require_once __DIR__ . '/tab-tours.php';
    $fx = adm_tour_facets();
    $attn = [['no_cover', 'без обложки'], ['no_photos', 'без фото'], ['no_shifts', 'без смен'], ['no_price', 'без цены'], ['no_desc', 'без описания'], ['past', 'все смены прошли'], ['sold_out', 'мест нет']];
    $h .= '</div><div class="card"><h2>Туры: требуют внимания <span class="muted small">актуальных ' . (int) $fx['total'] . ', черновиков ' . (int) $fx['draft'] . ', в архиве ' . (int) $fx['archived'] . '</span></h2><div class="attn">';
    foreach ($attn as [$flag, $label]) {
        $n = (int) ($fx[$flag] ?? 0);
        $h .= '<a href="' . adm_url('tours', ['flag' => $flag]) . '" class="' . ($n === 0 ? 'zero' : '') . '"><b>' . $n . '</b>' . adm_e($label) . '</a>';
    }
    $h .= '<a href="' . adm_url('tours', ['status' => 'draft']) . '" class="' . ((int) $fx['draft'] === 0 ? 'zero' : '') . '"><b>' . (int) $fx['draft'] . '</b>черновики</a></div></div>';
    $soon = at_all("SELECT s.name, s.group_name, s.start_date, s.end_date, s.price, s.availability, s.seats_left, t.id AS tour_id, t.title FROM tour_shifts s JOIN tours t ON t.id = s.tour_id
        WHERE t.archived_at IS NULL AND s.start_date >= CURDATE() ORDER BY s.start_date, t.sort_order LIMIT 8");
    if ($soon) {
        $h .= '<div class="card"><h2>Ближайшие заезды <a href="' . adm_url('shifts') . '" class="muted small">все смены →</a></h2><table><thead><tr><th>Даты</th><th>Тур</th><th>Смена</th><th>Цена</th><th>Места</th></tr></thead><tbody>';
        foreach ($soon as $sh) {
            $h .= '<tr><td style="white-space:nowrap"><b>' . adm_e(at_date_range($sh['start_date'], $sh['end_date'])) . '</b></td><td><a href="' . adm_url('tours', ['id' => (int) $sh['tour_id']]) . '">' . adm_e($sh['title']) . '</a></td>'
                . '<td>' . adm_e(trim(((string) ($sh['group_name'] ?? '') !== '' ? $sh['group_name'] . ': ' : '') . $sh['name'])) . '</td><td>' . adm_e($sh['price'] ? at_price((int) $sh['price']) : 'по запросу') . '</td>'
                . '<td>' . adm_avail_chip((string) $sh['availability'], $sh['seats_left'] !== null ? (int) $sh['seats_left'] : null) . '</td></tr>';
        }
        $h .= '</tbody></table></div>';
    }
    $h .= '<div class="card"><h2>Последние заявки</h2><table><thead><tr><th>#</th><th>Дата</th><th>Тип</th><th>Контакт</th><th>Тур / сообщение</th><th>Статус</th></tr></thead><tbody>';
    foreach (at_all('SELECT * FROM requests ORDER BY id DESC LIMIT 8') as $r) {
        $h .= '<tr><td><a href="' . adm_url('requests', ['id' => (int) $r['id']]) . '">#' . (int) $r['id'] . '</a></td><td>' . adm_e(at_datetime($r['created_at'])) . '</td>'
            . '<td>' . adm_e(ATOUR_REQUEST_KINDS[$r['kind']] ?? $r['kind']) . '</td><td>' . adm_e($r['name'] ?? '—') . '<div class="muted small">' . adm_e(trim(($r['phone'] ?? '') . ' ' . ($r['email'] ?? ''))) . '</div></td>'
            . '<td>' . adm_e(mb_substr((string) ($r['tour_title'] ?: ($r['message'] ?? '—')), 0, 90)) . '</td>'
            . '<td><span class="chip ' . adm_e($r['status']) . '">' . adm_e(ATOUR_REQUEST_STATUSES[$r['status']] ?? $r['status']) . '</span></td></tr>';
    }
    return $h . '</tbody></table></div><p class="muted">SEO-теги страниц, заявки в общем списке платформы и статистика — в <a href="/adm_panel001.php" target="_blank">панели платформы</a>.</p>';
}
