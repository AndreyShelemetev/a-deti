<?php
/** Туры: список, полная форма (смены, фото, вкладки, документы, характеристики, SEO). */
declare(strict_types=1);

const ADM_TOUR_STATUSES = ['active' => 'Актуальные', 'published' => 'На сайте', 'draft' => 'Черновики', 'archived' => 'Архив', 'all' => 'Все'];
const ADM_TOUR_SORTS = ['order' => 'По порядку на сайте', 'next_shift' => 'По ближайшей смене', 'updated' => 'Недавно изменённые', 'title' => 'По названию', 'price' => 'По цене'];
const ADM_TOUR_FLAGS = ['best' => '★ Лучшие', 'hit' => '🔥 Хиты', 'no_cover' => 'Без обложки', 'no_photos' => 'Без фото', 'no_shifts' => 'Без смен',
    'no_price' => 'Без цены', 'no_desc' => 'Без описания', 'past' => 'Смены прошли', 'sold_out' => 'Мест нет'];
/** SQL-условия «особых отметок» (t — tours). */
const ADM_TOUR_FLAG_SQL = [
    'best'      => 't.is_best = 1',
    'hit'       => 't.is_hit = 1',
    'no_cover'  => "(t.cover IS NULL OR t.cover = '')",
    'no_photos' => 'NOT EXISTS (SELECT 1 FROM tour_images i WHERE i.tour_id = t.id)',
    'no_shifts' => 'NOT EXISTS (SELECT 1 FROM tour_shifts s WHERE s.tour_id = t.id)',
    'no_price'  => 't.price_min IS NULL',
    'no_desc'   => "NOT EXISTS (SELECT 1 FROM tour_sections c WHERE c.tour_id = t.id AND c.skey = 'description')",
    'past'      => 'EXISTS (SELECT 1 FROM tour_shifts s WHERE s.tour_id = t.id) AND NOT EXISTS (SELECT 1 FROM tour_shifts s WHERE s.tour_id = t.id AND (s.end_date IS NULL OR s.end_date >= CURDATE()))',
    'sold_out'  => "EXISTS (SELECT 1 FROM tour_shifts s WHERE s.tour_id = t.id) AND NOT EXISTS (SELECT 1 FROM tour_shifts s WHERE s.tour_id = t.id AND s.availability <> 'SOLD_OUT')",
];
const ADM_TOUR_FILTER_KEYS = ['q', 'type', 'region', 'season', 'tag', 'status', 'from', 'to', 'price_min', 'price_max', 'flag', 'sort', 'page'];

function adm_tab_tours(): string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        if ($action === 'delete') {
            adm_tour_delete_many([$id]);
            adm_redirect('tours', ['ok' => 1, 'msg' => 'Тур удалён.']);
        }
        if ($action === 'bulk') {
            [$n, $msg] = adm_tour_bulk((string) ($_POST['op'] ?? ''), adm_ids((string) ($_POST['ids'] ?? '')));
            adm_redirect('tours', adm_tour_ret() + ($n > 0 ? ['ok' => 1, 'msg' => $msg] : ['err' => $msg]));
        }
        if ($action === 'duplicate') {
            $next = !empty($_POST['next']);
            $newId = adm_tour_duplicate($id, $next);
            if ($newId === 0) {
                adm_redirect('tours', adm_tour_ret() + ['err' => 'Тур не найден.']);
            }
            adm_redirect('tours', ['id' => $newId, 'ok' => 1, 'msg' => $next
                ? 'Создана копия на следующий сезон: даты смен и годы в названиях сдвинуты на год, наличие мест сброшено. Проверьте цены и опубликуйте.'
                : 'Создана копия тура — черновик.']);
        }
        [$newId, $errors, $draft] = adm_tour_save($id);
        if (!$errors) {
            $ui = (string) ($_POST['ui'] ?? 'basic');
            adm_redirect('tours', ['id' => $newId, 'ok' => 1, 'msg' => $id > 0 ? 'Тур сохранён.' : 'Тур создан. Теперь добавьте смены, фото и описание.', 'ui' => preg_match('~^[a-z]+$~', $ui) ? $ui : 'basic']);
        }
        return adm_tour_form($draft, $errors);
    }
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $tour = at_tour_by_id($id);
        return $tour === null ? '<div class="alert err">Тур не найден.</div>' : adm_tour_form($tour, []);
    }
    if (isset($_GET['new'])) {
        $type = (string) ($_GET['type'] ?? 'CAMP');
        return adm_tour_form(['id' => 0, 'type' => isset(ATOUR_TOUR_TYPES[$type]) ? $type : 'CAMP', 'is_published' => 0, 'sort_order' => 0,
            'seasons_list' => [], 'tags' => [], 'shifts' => [], 'images' => [], 'sections' => [], 'documents' => []], []);
    }
    return adm_tour_list();
}

/** «1,5,9» → [1, 5, 9], не больше 200. */
function adm_ids(string $csv): array
{
    return array_slice(array_values(array_filter(array_map('intval', explode(',', $csv)), static fn(int $n): bool => $n > 0)), 0, 200);
}

/** Фильтры списка из GET — только допустимые значения. @return array<string,string> */
function adm_tour_filters(): array
{
    $g = static fn(string $k): string => is_array($_GET[$k] ?? null) ? '' : trim((string) ($_GET[$k] ?? ''));
    $slug = static fn(string $v): string => preg_match('~^[a-z0-9_\-]{1,120}$~', $v) ? $v : '';
    $date = static fn(string $v): string => preg_match('~^\d{4}-\d{2}-\d{2}$~', $v) ? $v : '';
    $num = static fn(string $v): string => ctype_digit($v) ? $v : '';
    return [
        'q' => mb_substr($g('q'), 0, 100), 'type' => isset(ATOUR_TOUR_TYPES[$g('type')]) ? $g('type') : '',
        'region' => in_array($g('region'), ['foreign', 'russia_all'], true) ? $g('region') : $slug($g('region')),
        'season' => isset(ATOUR_SEASONS[$g('season')]) ? $g('season') : '', 'tag' => $slug($g('tag')),
        'status' => isset(ADM_TOUR_STATUSES[$g('status')]) ? $g('status') : 'active',
        'from' => $date($g('from')), 'to' => $date($g('to')), 'price_min' => $num($g('price_min')), 'price_max' => $num($g('price_max')),
        'flag' => isset(ADM_TOUR_FLAGS[$g('flag')]) ? $g('flag') : '', 'sort' => isset(ADM_TOUR_SORTS[$g('sort')]) ? $g('sort') : 'order',
        'page' => (string) max((int) $g('page'), 1),
    ];
}

/** Фильтры без значений по умолчанию — для ссылок. */
function adm_tour_qs(array $f, array $patch = []): array
{
    $out = [];
    foreach (array_merge($f, $patch) as $k => $v) {
        $v = (string) $v;
        if ($v === '' || ($k === 'status' && $v === 'active') || ($k === 'sort' && $v === 'order') || ($k === 'page' && $v === '1')) {
            continue;
        }
        $out[$k] = $v;
    }
    return $out;
}

/** Куда вернуться после массового действия: фильтры из скрытого поля ret. */
function adm_tour_ret(): array
{
    parse_str((string) ($_POST['ret'] ?? ''), $p);
    $out = [];
    foreach (ADM_TOUR_FILTER_KEYS as $k) {
        if (isset($p[$k]) && !is_array($p[$k]) && (string) $p[$k] !== '') {
            $out[$k] = mb_substr((string) $p[$k], 0, 120);
        }
    }
    return $out;
}

/** @return array{0:string,1:array<int,mixed>} [sql, params] */
function adm_tour_where(array $f): array
{
    $w = [];
    $p = [];
    $w[] = match ($f['status']) {
        'published' => 't.archived_at IS NULL AND t.is_published = 1',
        'draft'     => 't.archived_at IS NULL AND t.is_published = 0',
        'archived'  => 't.archived_at IS NOT NULL',
        'all'       => '1=1',
        default     => 't.archived_at IS NULL',
    };
    if ($f['q'] !== '') {
        $w[] = '(t.title LIKE ? OR t.slug LIKE ? OR t.address LIKE ?)';
        $like = '%' . at_like($f['q']) . '%';
        array_push($p, $like, $like, $like);
    }
    if ($f['type'] !== '') {
        $w[] = 't.type = ?';
        $p[] = $f['type'];
    }
    if ($f['region'] === 'foreign' || $f['region'] === 'russia_all') {
        $w[] = 't.region_id IN (SELECT id FROM regions WHERE is_foreign = ' . ($f['region'] === 'foreign' ? 1 : 0) . ')';
    } elseif ($f['region'] !== '') {
        $w[] = 't.region_id IN (SELECT id FROM regions WHERE slug = ?)';
        $p[] = $f['region'];
    }
    if ($f['season'] !== '') {
        $w[] = "CONCAT(',', t.seasons, ',') LIKE ?";
        $p[] = '%,' . $f['season'] . ',%';
    }
    if ($f['tag'] !== '') {
        $w[] = 't.id IN (SELECT tt.tour_id FROM tour_tags tt JOIN tags g ON g.id = tt.tag_id WHERE g.slug = ?)';
        $p[] = $f['tag'];
    }
    if ($f['from'] !== '' || $f['to'] !== '') {
        $w[] = 't.id IN (SELECT s.tour_id FROM tour_shifts s WHERE s.start_date IS NOT NULL' . ($f['from'] !== '' ? ' AND s.start_date >= ?' : '') . ($f['to'] !== '' ? ' AND s.start_date <= ?' : '') . ')';
        if ($f['from'] !== '') {
            $p[] = $f['from'];
        }
        if ($f['to'] !== '') {
            $p[] = $f['to'];
        }
    }
    if ($f['price_min'] !== '') {
        $w[] = 't.price_min >= ?';
        $p[] = (int) $f['price_min'];
    }
    if ($f['price_max'] !== '') {
        $w[] = 't.price_min <= ?';
        $p[] = (int) $f['price_max'];
    }
    if ($f['flag'] !== '') {
        $w[] = ADM_TOUR_FLAG_SQL[$f['flag']];
    }
    return [implode(' AND ', $w), $p];
}

/** Счётчики для категорий, статусов и блока «требуют внимания» (актуальные туры). */
function adm_tour_facets(): array
{
    $c = static fn(string $w): int => (int) at_scalar("SELECT COUNT(*) FROM tours t WHERE {$w}");
    $byType = [];
    foreach (at_all('SELECT type, COUNT(*) AS c FROM tours t WHERE t.archived_at IS NULL GROUP BY type') as $r) {
        $byType[(string) $r['type']] = (int) $r['c'];
    }
    $out = ['byType' => $byType, 'total' => array_sum($byType), 'published' => $c('t.archived_at IS NULL AND t.is_published = 1'),
        'draft' => $c('t.archived_at IS NULL AND t.is_published = 0'), 'archived' => $c('t.archived_at IS NOT NULL')];
    foreach (ADM_TOUR_FLAG_SQL as $flag => $sql) {
        if ($flag !== 'best' && $flag !== 'hit') {
            $out[$flag] = $c('t.archived_at IS NULL AND ' . $sql);
        }
    }
    return $out;
}

function adm_tour_list(): string
{
    $f = adm_tour_filters();
    [$where, $params] = adm_tour_where($f);
    $order = match ($f['sort']) {
        'next_shift' => '(next_start IS NULL), next_start ASC, t.sort_order, t.id',
        'updated'    => 't.updated_at DESC, t.id DESC',
        'title'      => 't.title ASC',
        'price'      => '(t.price_min IS NULL), t.price_min ASC, t.id',
        default      => 't.is_published DESC, t.sort_order, t.id',
    };
    $perPage = 25;
    $page = (int) $f['page'];
    $total = (int) at_scalar("SELECT COUNT(*) FROM tours t WHERE {$where}", $params);
    $pages = max((int) ceil($total / $perPage), 1);
    $page = min($page, $pages);
    $rows = at_all("SELECT t.*, r.name AS region_name,
        (SELECT COUNT(*) FROM tour_shifts s WHERE s.tour_id = t.id) AS n_shifts,
        (SELECT COUNT(*) FROM tour_shifts s WHERE s.tour_id = t.id AND (s.end_date IS NULL OR s.end_date >= CURDATE())) AS n_future,
        (SELECT COUNT(*) FROM tour_shifts s WHERE s.tour_id = t.id AND s.availability <> 'SOLD_OUT') AS n_avail,
        (SELECT MIN(s.start_date) FROM tour_shifts s WHERE s.tour_id = t.id AND s.start_date >= CURDATE()) AS next_start,
        (SELECT s.end_date FROM tour_shifts s WHERE s.tour_id = t.id AND s.start_date >= CURDATE() ORDER BY s.start_date LIMIT 1) AS next_end,
        (SELECT COUNT(*) FROM tour_images i WHERE i.tour_id = t.id) AS n_images,
        (SELECT COUNT(*) FROM tour_sections c WHERE c.tour_id = t.id) AS n_sections,
        (SELECT COUNT(*) FROM tour_sections c WHERE c.tour_id = t.id AND c.skey = 'description') AS has_desc,
        (SELECT COUNT(*) FROM requests q WHERE q.tour_id = t.id) AS n_requests
        FROM tours t LEFT JOIN regions r ON r.id = t.region_id WHERE {$where} ORDER BY {$order} LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage), $params);
    $fx = adm_tour_facets();
    $url = static fn(array $patch): string => adm_url('tours', adm_tour_qs($f, $patch + ['page' => '']));

    $h = '<p class="sub-title">' . $total . ' из ' . (int) $fx['total'] . ' актуальных · на сайте ' . (int) $fx['published'] . ' · черновиков ' . (int) $fx['draft'] . ' · в архиве ' . (int) $fx['archived'] . '</p>';
    $h .= '<div class="row" style="margin-bottom:12px"><a href="' . adm_url('tours', ['new' => 1] + ($f['type'] !== '' ? ['type' => $f['type']] : [])) . '" class="btn">+ Добавить тур</a>'
        . (is_file(__DIR__ . '/tab-shifts.php') ? '<a href="' . adm_url('shifts') . '" class="btn g">Календарь смен</a>' : '') . '</div>';
    // Категории и статусы
    $h .= '<div class="chips"><a href="' . $url(['type' => '']) . '" class="' . ($f['type'] === '' ? 'on' : '') . '">Все <span>' . (int) $fx['total'] . '</span></a>';
    foreach (ATOUR_TOUR_TYPES as $k => $label) {
        $h .= '<a href="' . $url(['type' => $k]) . '" class="' . ($f['type'] === $k ? 'on' : '') . '">' . adm_e($label) . ' <span>' . (int) ($fx['byType'][$k] ?? 0) . '</span></a>';
    }
    $h .= '<span class="sep">|</span>';
    foreach (ADM_TOUR_STATUSES as $k => $label) {
        $n = match ($k) { 'active' => $fx['total'], 'published' => $fx['published'], 'draft' => $fx['draft'], 'archived' => $fx['archived'], default => $fx['total'] + $fx['archived'] };
        $h .= '<a href="' . $url(['status' => $k]) . '" class="' . ($f['status'] === $k ? 'on2' : '') . '">' . adm_e($label) . ' <span>' . (int) $n . '</span></a>';
    }
    $h .= '</div>';
    // Фильтры
    $opt = static function (array $options, string $cur, string $any): string {
        $o = '<option value="">' . adm_e($any) . '</option>';
        foreach ($options as $k => $v) {
            $o .= '<option value="' . adm_e((string) $k) . '"' . ((string) $k === $cur ? ' selected' : '') . '>' . adm_e($v) . '</option>';
        }
        return $o;
    };
    $regionOpts = ['russia_all' => 'Россия (все регионы)', 'foreign' => 'За границей (все страны)'];
    foreach (at_regions() as $r) {
        $regionOpts[$r['slug']] = ($r['is_foreign'] ? '· ' : '') . $r['name'];
    }
    $tagOpts = array_column(at_tags(), 'label', 'slug');
    $active = count(array_filter([$f['q'], $f['region'], $f['season'], $f['tag'], $f['from'], $f['to'], $f['price_min'], $f['price_max'], $f['flag']], static fn(string $v): bool => $v !== ''));
    $h .= '<form method="get" class="card"><input type="hidden" name="tab" value="tours">'
        . ($f['type'] !== '' ? '<input type="hidden" name="type" value="' . adm_e($f['type']) . '">' : '')
        . ($f['status'] !== 'active' ? '<input type="hidden" name="status" value="' . adm_e($f['status']) . '">' : '')
        . '<div class="fgrid">'
        . '<label class="span2">Поиск<input type="text" name="q" value="' . adm_e($f['q']) . '" placeholder="Название, адрес, slug"></label>'
        . '<label>Направление<select name="region">' . $opt($regionOpts, $f['region'], 'Все направления') . '</select></label>'
        . '<label>Сезон<select name="season">' . $opt(ATOUR_SEASONS, $f['season'], 'Все сезоны') . '</select></label>'
        . '<label>Характеристика<select name="tag">' . $opt($tagOpts, $f['tag'], 'Все характеристики') . '</select></label>'
        . '<label>Особые отметки<select name="flag">' . $opt(ADM_TOUR_FLAGS, $f['flag'], 'Любые') . '</select></label>'
        . '<label class="span2">Смены в период<div class="range"><input type="date" name="from" value="' . adm_e($f['from']) . '"><span>—</span><input type="date" name="to" value="' . adm_e($f['to']) . '"></div></label>'
        . '<label class="span2">Цена, ₽<div class="range"><input type="number" name="price_min" value="' . adm_e($f['price_min']) . '" placeholder="от"><span>—</span><input type="number" name="price_max" value="' . adm_e($f['price_max']) . '" placeholder="до"></div></label>'
        . '<label>Сортировка<select name="sort">';
    foreach (ADM_TOUR_SORTS as $k => $v) {
        $h .= '<option value="' . $k . '"' . ($f['sort'] === $k ? ' selected' : '') . '>' . adm_e($v) . '</option>';
    }
    $h .= '</select></label><div class="btns"><button class="btn">Показать</button>'
        . ($active > 0 ? '<a href="' . $url(['q' => '', 'region' => '', 'season' => '', 'tag' => '', 'from' => '', 'to' => '', 'price_min' => '', 'price_max' => '', 'flag' => '']) . '" class="btn g" title="Сбросить фильтры">✕</a>' : '')
        . '</div></div></form>';
    if (!$rows) {
        return $h . '<div class="card muted">Туров с такими параметрами нет. Измените фильтры или добавьте тур.</div>';
    }
    // Массовые действия
    $ret = http_build_query(adm_tour_qs($f));
    $h .= '<form method="post" class="bulk" data-bulk><input type="hidden" name="csrf" value="' . adm_e(adm_csrf()) . '"><input type="hidden" name="action" value="bulk"><input type="hidden" name="ids" value=""><input type="hidden" name="ret" value="' . adm_e($ret) . '">'
        . '<b>Выбрано: <span data-bulk-count>0</span></b><span class="sep">|</span>'
        . '<button name="op" value="publish">Опубликовать</button><button name="op" value="unpublish">Скрыть</button>'
        . ($f['status'] === 'archived' ? '<button name="op" value="unarchive">Вернуть из архива</button>' : '<button name="op" value="archive" data-confirm="Отправить %n тур(ов) в архив? Они пропадут с сайта, но останутся здесь.">В архив</button>')
        . '<button name="op" value="best_on">★ Лучшее</button><button name="op" value="best_off">Снять ★</button><button name="op" value="hit_on">🔥 Хит</button><button name="op" value="hit_off">Снять хит</button>'
        . '<button name="op" value="delete" class="d" data-confirm="Удалить %n тур(ов) без возможности восстановления?">Удалить</button></form>';
    $h .= '<div class="card" style="overflow-x:auto"><table class="tlist"><thead><tr><th style="width:28px"><input type="checkbox" data-sel-all aria-label="Выбрать все"></th><th>Тур</th><th>Направление</th><th>Ближайшая смена</th><th>Цена от</th><th>Наполнение</th><th>Статус</th><th></th></tr></thead><tbody>';
    foreach ($rows as $t) {
        $id = (int) $t['id'];
        $gaps = array_filter([
            ($t['cover'] ?? '') === '' ? 'нет обложки' : '', (int) $t['n_shifts'] === 0 ? 'нет смен' : '',
            $t['price_min'] === null ? 'нет цены' : '', (int) $t['has_desc'] === 0 ? 'нет описания' : '',
        ]);
        $meta = array_filter([ATOUR_TOUR_TYPES[$t['type']] ?? $t['type'], (string) ($t['age_text'] ?? ''), (string) ($t['season_text'] ?? ''), $t['is_best'] ? '★ лучшее' : '', $t['is_hit'] ? '🔥 хит' : '']);
        if ($t['next_start'] !== null) {
            $next = '<b>' . adm_e(at_date_range($t['next_start'], $t['next_end'])) . '</b><div class="meta">' . (int) $t['n_shifts'] . ' ' . at_plural((int) $t['n_shifts'], ['смена', 'смены', 'смен']) . '</div>';
        } elseif ((int) $t['n_shifts'] > 0) {
            $next = '<span class="gap">все ' . (int) $t['n_shifts'] . ' ' . at_plural((int) $t['n_shifts'], ['смена прошла', 'смены прошли', 'смен прошли']) . '</span>';
        } else {
            $next = '<span class="muted">—</span>';
        }
        $status = $t['archived_at'] !== null ? '<span class="chip arch">Архив</span>' : ($t['is_published'] ? '<span class="chip pub">На сайте</span>' : '<span class="chip draft">Черновик</span>');
        $dup = static fn(bool $next, string $label, string $title): string => '<form method="post"><input type="hidden" name="csrf" value="' . adm_e(adm_csrf()) . '"><input type="hidden" name="action" value="duplicate"><input type="hidden" name="id" value="' . $id . '">'
            . ($next ? '<input type="hidden" name="next" value="1">' : '') . '<input type="hidden" name="ret" value="' . adm_e($ret) . '"><button title="' . adm_e($title) . '">' . $label . '</button></form>';
        $h .= '<tr><td><input type="checkbox" data-sel value="' . $id . '" aria-label="Выбрать"></td>'
            . '<td><div class="tt"><img class="thumb" src="' . adm_e(at_img_src((string) ($t['cover'] ?? ''))) . '" alt="" loading="lazy"><div><b><a href="' . adm_url('tours', ['id' => $id]) . '">' . adm_e($t['title']) . '</a></b>'
            . '<div class="meta">' . adm_e(implode(' · ', $meta)) . '</div></div></div></td>'
            . '<td>' . adm_e($t['region_name'] ?? '—') . '</td><td>' . $next . '</td>'
            . '<td><b>' . adm_e($t['price_min'] ? at_price((int) $t['price_min']) : ($t['price_text'] ?: '—')) . '</b></td>'
            . '<td><div class="meta">' . (int) $t['n_images'] . ' фото · ' . (int) $t['n_sections'] . ' вкл. · ' . (int) $t['n_requests'] . ' заяв.</div>'
            . ($gaps ? '<div class="gap">' . adm_e(implode(', ', $gaps)) . '</div>' : '<div class="okk">заполнено</div>')
            . ((int) $t['n_shifts'] > 0 && (int) $t['n_avail'] === 0 ? '<div class="gap"><b>мест нет</b></div>' : '') . '</td>'
            . '<td>' . $status . '</td>'
            . '<td class="rowact">' . $dup(false, 'копия', 'Создать копию тура (черновик)') . $dup(true, 'след. сезон', 'Копия на следующий сезон: даты смен и годы в названиях +1') . '</td></tr>';
    }
    $h .= '</tbody></table></div>';
    if ($pages > 1) {
        $h .= '<div class="pages">';
        for ($p = 1; $p <= $pages; $p++) {
            $h .= $p === $page ? '<span>' . $p . '</span>' : '<a href="' . adm_url('tours', adm_tour_qs($f, ['page' => (string) $p])) . '">' . $p . '</a>';
        }
        $h .= '</div>';
    }
    return $h;
}

/** Удаление туров вместе с дочерними таблицами. @param array<int,int> $ids */
function adm_tour_delete_many(array $ids): void
{
    foreach ($ids as $id) {
        db()->prepare('DELETE FROM tours WHERE id = ?')->execute([$id]);
        foreach (['tour_shifts', 'tour_images', 'tour_sections', 'tour_tags', 'tour_documents'] as $t) {
            db()->prepare("DELETE FROM {$t} WHERE tour_id = ?")->execute([$id]);
        }
    }
}

/** Массовое действие над отмеченными турами. @return array{0:int,1:string} [сколько, сообщение] */
function adm_tour_bulk(string $op, array $ids): array
{
    if (!$ids) {
        return [0, 'Ничего не выбрано.'];
    }
    $set = match ($op) {
        'publish'   => 'is_published = 1, archived_at = NULL',
        'unpublish' => 'is_published = 0',
        'archive'   => 'archived_at = NOW(), is_published = 0',
        'unarchive' => 'archived_at = NULL',
        'best_on'   => 'is_best = 1', 'best_off' => 'is_best = 0',
        'hit_on'    => 'is_hit = 1', 'hit_off' => 'is_hit = 0',
        'delete'    => '',
        default     => null,
    };
    if ($set === null) {
        return [0, 'Неизвестное действие.'];
    }
    if ($op === 'delete') {
        adm_tour_delete_many($ids);
    } else {
        db()->prepare("UPDATE tours SET {$set} WHERE id IN (" . at_in($ids) . ')')->execute($ids);
    }
    $n = count($ids);
    $what = $n . ' ' . at_plural($n, ['тур', 'тура', 'туров']);
    $msg = match ($op) {
        'publish' => 'Опубликовано: ' . $what . '.', 'unpublish' => 'Скрыто с сайта: ' . $what . '.',
        'archive' => 'В архив: ' . $what . '.', 'unarchive' => 'Возвращено из архива: ' . $what . '.',
        'delete' => 'Удалено: ' . $what . '.', default => 'Готово: ' . $what . '.',
    };
    return [$n, $msg];
}

/**
 * Копия тура (черновик). С $nextSeason даты смен сдвигаются на год вперёд,
 * годы в названиях смен, тура и текстах — тоже, наличие мест сбрасывается.
 * Возвращает id новой записи или 0, если тура нет.
 */
function adm_tour_duplicate(int $id, bool $nextSeason): int
{
    $src = at_one('SELECT * FROM tours WHERE id = ?', [$id]);
    if ($src === null) {
        return 0;
    }
    $bump = static fn(?string $t): ?string => $nextSeason ? at_bump_years($t) : $t;
    $baseSlug = (string) ($nextSeason ? at_bump_years((string) $src['slug']) : $src['slug']);
    $slug = $baseSlug === $src['slug'] ? $src['slug'] . '-copy' : $baseSlug;
    for ($i = 2; at_one('SELECT id FROM tours WHERE slug = ?', [$slug]) !== null; $i++) {
        $slug = $baseSlug . '-' . $i;
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $d = $src;
        unset($d['id'], $d['created_at'], $d['updated_at']);
        $d['slug'] = $slug;
        $d['title'] = $nextSeason ? at_bump_years((string) $src['title']) : $src['title'] . ' (копия)';
        $d['seo_title'] = $bump($src['seo_title']);
        $d['season_text'] = $bump($src['season_text']);
        $d['search_text'] = $bump($src['search_text']);
        $d['is_published'] = 0;
        $d['is_hit'] = 0;
        $d['archived_at'] = null;
        $cols = array_keys($d);
        $pdo->prepare('INSERT INTO tours (' . implode(', ', array_map(static fn(string $c): string => "`{$c}`", $cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
            ->execute(array_values($d));
        $new = (int) $pdo->lastInsertId();
        $st = $pdo->prepare('INSERT INTO tour_shifts (tour_id, group_name, name, start_date, end_date, price, price_text, availability, seats_left, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach (at_all('SELECT * FROM tour_shifts WHERE tour_id = ? ORDER BY sort_order, id', [$id]) as $s) {
            $st->execute([$new, $s['group_name'], $bump((string) $s['name']), $nextSeason ? at_add_years($s['start_date'], 1) : $s['start_date'],
                $nextSeason ? at_add_years($s['end_date'], 1) : $s['end_date'], $s['price'], $s['price_text'],
                $nextSeason ? 'AVAILABLE' : $s['availability'], $nextSeason ? null : $s['seats_left'], (int) $s['sort_order']]);
        }
        $st = $pdo->prepare('INSERT INTO tour_images (tour_id, url, alt, sort_order) VALUES (?, ?, ?, ?)');
        foreach (at_all('SELECT * FROM tour_images WHERE tour_id = ? ORDER BY sort_order, id', [$id]) as $im) {
            $st->execute([$new, $im['url'], $im['alt'], (int) $im['sort_order']]);
        }
        $st = $pdo->prepare('INSERT INTO tour_sections (tour_id, skey, title, html, sort_order) VALUES (?, ?, ?, ?, ?)');
        foreach (at_all('SELECT * FROM tour_sections WHERE tour_id = ? ORDER BY sort_order, id', [$id]) as $sec) {
            $st->execute([$new, $sec['skey'], $sec['title'], $bump((string) $sec['html']), (int) $sec['sort_order']]);
        }
        $st = $pdo->prepare('INSERT IGNORE INTO tour_tags (tour_id, tag_id) VALUES (?, ?)');
        foreach (at_all('SELECT tag_id FROM tour_tags WHERE tour_id = ?', [$id]) as $tg) {
            $st->execute([$new, (int) $tg['tag_id']]);
        }
        $st = $pdo->prepare('INSERT INTO tour_documents (tour_id, label, url, sort_order) VALUES (?, ?, ?, ?)');
        foreach (at_all('SELECT * FROM tour_documents WHERE tour_id = ? ORDER BY sort_order, id', [$id]) as $doc) {
            $st->execute([$new, $doc['label'], $doc['url'], (int) $doc['sort_order']]);
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $new;
}

/** Подсказки и шаблоны разделов описания (ключ → [подпись, подсказка]). */
const ADM_SECTION_HINTS = [
    'description'           => 'Текст раздела и одно фото: на странице лагеря фотография располагается справа.',
    'camp_facts'            => 'Таблица из двух столбцов: характеристика и значение. Первые четыре строки показываются в шапке лагеря.',
    'residence'             => 'Корпуса, номера, удобства, инфраструктура.',
    'programm'              => 'Кружки, спорт, экскурсии, мероприятия.',
    'payment_smeny'         => 'Что входит в стоимость и что оплачивается отдельно.',
    'otpravka_children'     => 'Как добраться: поезд, автобус, сопровождение, место сбора.',
    'for_parents'           => 'Какие документы и вещи подготовить.',
    'for_contraindications' => 'Медицинские ограничения для заезда.',
];
const ADM_SECTION_TEMPLATES = [
    'camp_facts' => '<table><tbody><tr><th>Пляж</th><td></td></tr><tr><th>Территория</th><td></td></tr><tr><th>Питание</th><td></td></tr><tr><th>Комнаты</th><td></td></tr></tbody></table>',
    'payment_smeny' => '<p><strong>В стоимость путёвки входит:</strong></p><ul><li>проживание;</li><li>5-разовое питание;</li><li>программа лагеря;</li><li>пользование инфраструктурой;</li><li>медицинское сопровождение;</li><li>страховка.</li></ul><p><strong>Оплачивается дополнительно:</strong></p><ul><li>трансфер;</li><li>экскурсии.</li></ul>',
    'for_parents' => '<p><strong>В лагерь необходимо подготовить:</strong></p><ul><li>медицинскую справку по форме 079/у;</li><li>справку об эпидокружении (за 3 дня до заезда);</li><li>копию свидетельства о рождении (паспорта с 14 лет);</li><li>копию полиса ОМС;</li><li>согласие на медицинское вмешательство.</li></ul><p><strong>В дорогу:</strong></p><ul><li>оригинал свидетельства о рождении или паспорта.</li></ul>',
    'for_contraindications' => '<ul><li>все заболевания в остром периоде;</li><li>бронхиальная астма средней и тяжёлой степени;</li><li>сахарный диабет;</li><li>эпилепсия;</li><li>инфекционные и паразитарные заболевания.</li></ul>',
];
const ADM_TOUR_TABS = ['basic' => 'Основное', 'shifts' => 'Смены и цены', 'photos' => 'Фото', 'content' => 'Описание', 'docs' => 'Документы', 'publish' => 'Публикация и SEO'];

/** @return array{0:int,1:array<int,string>,2:array<string,mixed>} */
function adm_tour_save(int $id): array
{
    $errors = [];
    $status = (string) ($_POST['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'published', 'archived'], true)) {
        $status = 'draft';
    }
    $prev = $id > 0 ? at_one('SELECT archived_at FROM tours WHERE id = ?', [$id]) : null;
    $d = [
        'title'            => adm_str('title', 300),
        'slug'             => at_slugify((string) (adm_str('slug', 190) ?: adm_str('title', 300))),
        'type'             => adm_str('type', 16),
        'region_id'        => adm_int('region_id'),
        'short'            => adm_str('short', 1000),
        'address'          => adm_str('address', 300),
        'age_text'         => adm_str('age_text', 120),
        'age_min'          => adm_int('age_min'),
        'age_max'          => adm_int('age_max'),
        'website'          => adm_str('website', 300, true),
        'price_text'       => adm_str('price_text', 120, true),
        'price_min'        => adm_int('price_min'),
        'price_note'       => adm_str('price_note', 300, true),
        'cover'            => adm_str('cover', 500, true),
        'banner'           => adm_str('banner', 500, true),
        'seasons'          => at_join(array_values(array_intersect(adm_list('seasons'), array_keys(ATOUR_SEASONS)))),
        'season_text'      => adm_str('season_text', 120, true),
        'is_best'          => adm_bool('is_best'),
        'is_hit'           => adm_bool('is_hit'),
        'is_published'     => $status === 'published' ? 1 : 0,
        // В архив — дата отправки; уже архивный тур сохраняет свою дату.
        'archived_at'      => $status === 'archived' ? ($prev['archived_at'] ?? date('Y-m-d H:i:s')) : null,
        'internal_note'    => adm_str('internal_note', 5000, true),
        'sort_order'       => adm_int('sort_order') ?? 0,
        'seo_title'        => adm_str('seo_title', 300, true),
        'meta_description' => adm_str('meta_description', 500, true),
        'meta_keywords'    => adm_str('meta_keywords', 500, true),
    ];
    if (mb_strlen((string) $d['title']) < 3) {
        $errors[] = 'Укажите название тура.';
    }
    if (!isset(ATOUR_TOUR_TYPES[$d['type']])) {
        $d['type'] = 'CAMP';
    }
    if ($d['slug'] === '') {
        $d['slug'] = 'tour-' . time();
    }
    if (at_one('SELECT id FROM tours WHERE slug = ? AND id <> ?', [$d['slug'], $id]) !== null) {
        $errors[] = 'Такой URL (slug) уже занят другим туром.';
    }
    if ($d['region_id'] !== null && at_one('SELECT id FROM regions WHERE id = ?', [$d['region_id']]) === null) {
        $d['region_id'] = null;
    }
    // Смены: даты из полей, а если их не заполнили — из названия («1 смена 01.06-21.06.2026»).
    $tourYear = at_tour_year($d['season_text'], (string) $d['title']);
    $isDate = static fn(string $v): ?string => preg_match('~^\d{4}-\d{2}-\d{2}$~', $v) ? $v : null;
    $shifts = [];
    foreach ((array) ($_POST['shifts'] ?? []) as $row) {
        if (!is_array($row) || trim((string) ($row['name'] ?? '')) === '') {
            continue;
        }
        $price = trim((string) ($row['price'] ?? ''));
        $seats = trim((string) ($row['seats_left'] ?? ''));
        $start = $isDate((string) ($row['start'] ?? ''));
        $end = $isDate((string) ($row['end'] ?? ''));
        if ($start === null) {
            [$start, $end] = at_shift_parse_dates(trim((string) $row['name']), $tourYear);
        }
        $shifts[] = [
            'group_name' => trim((string) ($row['group'] ?? '')) ?: null, 'name' => mb_substr(trim((string) $row['name']), 0, 300),
            'price' => $price !== '' && ctype_digit($price) ? (int) $price : null,
            'price_text' => mb_substr(trim((string) ($row['price_text'] ?? '')), 0, 120) ?: null,
            'start_date' => $start, 'end_date' => $end ?? $start,
            'availability' => isset(ADM_AVAIL[(string) ($row['availability'] ?? '')]) ? (string) $row['availability'] : 'AVAILABLE',
            'seats_left' => $seats !== '' && ctype_digit($seats) ? (int) $seats : null,
        ];
    }
    $images = [];
    foreach ((array) ($_POST['images'] ?? []) as $row) {
        if (!is_array($row) || trim((string) ($row['url'] ?? '')) === '') {
            continue;
        }
        $images[] = ['url' => mb_substr(trim((string) $row['url']), 0, 500), 'alt' => mb_substr(trim((string) ($row['alt'] ?? '')), 0, 300) ?: null];
    }
    $documents = [];
    foreach ((array) ($_POST['documents'] ?? []) as $row) {
        if (!is_array($row) || trim((string) ($row['url'] ?? '')) === '') {
            continue;
        }
        $documents[] = ['label' => mb_substr(trim((string) ($row['label'] ?? '')), 0, 300) ?: basename((string) $row['url']), 'url' => mb_substr(trim((string) $row['url']), 0, 500)];
    }
    // Разделы: стандартные ключи плюс свои (у лендинга Пекина — program, stay, transfer, documents), чтобы ничего не терялось.
    $keys = array_keys(ATOUR_SECTION_TITLES);
    foreach (array_keys((array) ($_POST['section'] ?? [])) as $k) {
        if (preg_match('~^[a-z][a-z0-9_]{0,31}$~', (string) $k) && !in_array((string) $k, $keys, true)) {
            $keys[] = (string) $k;
        }
    }
    $sections = [];
    $i = 0;
    foreach ($keys as $key) {
        $html = at_sanitize_html((string) ($_POST['section'][$key] ?? ''));
        if ($html !== '') {
            $sections[] = ['skey' => $key, 'title' => mb_substr(trim((string) ($_POST['section_title'][$key] ?? '')), 0, 190) ?: (ATOUR_SECTION_TITLES[$key] ?? $key), 'html' => $html, 'sort_order' => $i];
        }
        $i++;
    }
    $tagIds = array_values(array_filter(array_map('intval', adm_list('tags')), static fn(int $x): bool => $x > 0));
    $tagRows = $tagIds ? at_all('SELECT id, label FROM tags WHERE id IN (' . at_in($tagIds) . ')', $tagIds) : [];
    $prices = array_values(array_filter(array_column($shifts, 'price'), static fn($p): bool => $p !== null && $p > 0));
    if ($d['price_min'] === null && $prices) {
        $d['price_min'] = min($prices);
    }
    if (($d['cover'] ?? null) === null && $images) {
        $d['cover'] = $images[0]['url'];
    }
    $region = $d['region_id'] !== null ? at_one('SELECT name FROM regions WHERE id = ?', [$d['region_id']]) : null;
    $parts = [$d['title'], $d['short'], $d['address'], $d['age_text'], (string) ($region['name'] ?? '')];
    foreach ($tagRows as $t) {
        $parts[] = $t['label'];
    }
    foreach ($sections as $s) {
        $parts[] = at_strip_html($s['html']);
    }
    $d['search_text'] = mb_strtolower(implode(' ', $parts));

    $draft = $d + ['id' => $id, 'status' => $status, 'seasons_list' => at_split($d['seasons']), 'tags' => array_map(static fn(array $t): array => ['id' => (int) $t['id'], 'label' => $t['label']], $tagRows),
        'shifts' => $shifts, 'images' => $images, 'documents' => $documents,
        'sections' => array_map(static fn(array $s): array => $s, $sections)];
    if ($errors) {
        return [0, $errors, $draft];
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $cols = array_keys($d);
        if ($id > 0) {
            $pdo->prepare('UPDATE tours SET ' . implode(', ', array_map(static fn(string $c): string => "`{$c}` = ?", $cols)) . ' WHERE id = ?')->execute([...array_values($d), $id]);
        } else {
            $pdo->prepare('INSERT INTO tours (' . implode(', ', array_map(static fn(string $c): string => "`{$c}`", $cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($d));
            $id = (int) $pdo->lastInsertId();
        }
        foreach (['tour_shifts', 'tour_images', 'tour_sections', 'tour_tags', 'tour_documents'] as $t) {
            $pdo->prepare("DELETE FROM {$t} WHERE tour_id = ?")->execute([$id]);
        }
        $st = $pdo->prepare('INSERT INTO tour_shifts (tour_id, group_name, name, start_date, end_date, price, price_text, availability, seats_left, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($shifts as $i => $s) {
            $st->execute([$id, $s['group_name'], $s['name'], $s['start_date'], $s['end_date'], $s['price'], $s['price_text'] ?? ($s['price'] ? at_price($s['price']) : null), $s['availability'], $s['seats_left'], $i]);
        }
        $st = $pdo->prepare('INSERT INTO tour_images (tour_id, url, alt, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($images as $i => $im) {
            $st->execute([$id, $im['url'], $im['alt'] ?? $d['title'], $i]);
        }
        $st = $pdo->prepare('INSERT INTO tour_sections (tour_id, skey, title, html, sort_order) VALUES (?, ?, ?, ?, ?)');
        foreach ($sections as $s) {
            $st->execute([$id, $s['skey'], $s['title'], $s['html'], $s['sort_order']]);
        }
        $st = $pdo->prepare('INSERT IGNORE INTO tour_tags (tour_id, tag_id) VALUES (?, ?)');
        foreach ($tagRows as $t) {
            $st->execute([$id, (int) $t['id']]);
        }
        $st = $pdo->prepare('INSERT INTO tour_documents (tour_id, label, url, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($documents as $i => $doc) {
            $st->execute([$id, $doc['label'], $doc['url'], $i]);
        }
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        return [0, ['Не удалось сохранить: ' . $e->getMessage()], $draft];
    }
    return [$id, [], $draft];
}

/**
 * Репитер строк с полями. $file: false — без файла, true — картинка с превью и
 * кнопкой «Файл», 'file' — кнопка «Файл» для документов (PDF, Word, Excel).
 */
function adm_repeater(string $name, array $rows, array $cols, string $addLabel, string $grid, bool|string $file = false): string
{
    $row = static function (string $i, array $r) use ($name, $cols, $grid, $file): string {
        $h = '<div class="rep-row" style="grid-template-columns:' . $grid . '">';
        if ($file === true) {
            $h .= '<img src="' . adm_e(at_img_src((string) ($r['url'] ?? ''))) . '" alt="" style="width:64px;height:48px;object-fit:cover;border-radius:6px">';
        }
        foreach ($cols as $key => [$placeholder, $type]) {
            $h .= '<input type="' . $type . '" name="' . $name . '[' . $i . '][' . $key . ']" value="' . adm_e((string) ($r[$key] ?? '')) . '" placeholder="' . adm_e($placeholder) . '"' . ($key === 'url' ? ' data-url' : '') . '>';
        }
        if ($file === true) {
            $h .= '<label class="btn g sm" style="margin:0">Файл<input type="file" accept="image/*" style="display:none"></label>';
        } elseif ($file === 'file') {
            $h .= '<label class="btn g sm" style="margin:0" title="Загрузить PDF, Word или Excel до 8 МБ">Файл<input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,application/pdf" style="display:none"></label>';
        }
        return $h . '<div class="rep-ctl"><button data-up title="Выше">↑</button><button data-down title="Ниже">↓</button><button data-del title="Удалить">✕</button></div></div>';
    };
    $h = '<div data-repeater data-next="' . count($rows) . '"><div class="rep-list">';
    foreach ($rows as $i => $r) {
        $h .= $row((string) $i, $r);
    }
    return $h . '</div><template>' . $row('__i__', []) . '</template><button class="btn g sm" data-add>+ ' . adm_e($addLabel) . '</button></div>';
}

/** Строка редактора смен (индекс __i__ в шаблоне). */
function adm_shift_row(string $i, array $s): string
{
    $f = static fn(string $key): string => 'shifts[' . $i . '][' . $key . ']';
    $v = static fn(string $key): string => adm_e((string) ($s[$key] ?? ''));
    $avail = isset(ADM_AVAIL[(string) ($s['availability'] ?? '')]) ? (string) $s['availability'] : 'AVAILABLE';
    $h = '<tr class="sh-row">'
        . '<td><input type="text" name="' . $f('group') . '" value="' . $v('group') . '" placeholder="—" list="shift-groups" data-f="group" style="width:9rem"></td>'
        . '<td><input type="text" name="' . $f('name') . '" value="' . $v('name') . '" placeholder="1 смена 01.06–21.06.2026" data-f="name" style="min-width:16rem"></td>'
        . '<td><input type="date" name="' . $f('start') . '" value="' . $v('start') . '" data-f="start"></td>'
        . '<td><input type="date" name="' . $f('end') . '" value="' . $v('end') . '" data-f="end"></td>'
        . '<td class="days" data-days>—</td>'
        . '<td><input type="number" name="' . $f('price') . '" value="' . $v('price') . '" placeholder="по запросу" data-f="price" style="width:7rem"></td>'
        . '<td><input type="text" name="' . $f('price_text') . '" value="' . $v('price_text') . '" placeholder="от 120 000 ₽" data-f="price_text" style="width:8rem" title="Текст цены на сайте; пусто — число"></td>'
        . '<td><div class="row" style="flex-wrap:nowrap;gap:4px"><select name="' . $f('availability') . '" data-f="availability" class="avail-sel ' . $avail . '">';
    foreach (ADM_AVAIL as $k => $label) {
        $h .= '<option value="' . $k . '"' . ($k === $avail ? ' selected' : '') . '>' . $label . '</option>';
    }
    $h .= '</select><input type="number" name="' . $f('seats_left') . '" value="' . $v('seats_left') . '" placeholder="шт." title="Осталось мест (необязательно)" data-f="seats_left" style="width:4.2rem" min="0"></div></td>'
        . '<td><div class="ctl"><button data-up title="Выше">↑</button><button data-down title="Ниже">↓</button><button data-dup title="Добавить следующую смену такой же длины">⧉</button><button data-del class="x" title="Удалить">✕</button></div></td></tr>';
    return $h;
}

function adm_tour_form(array $t, array $errors): string
{
    $id = (int) ($t['id'] ?? 0);
    $regions = ['' => '—'];
    foreach (at_regions() as $r) {
        $regions[(string) $r['id']] = ($r['is_foreign'] ? '· ' : '') . $r['name'];
    }
    $tagIds = array_map(static fn(array $x): int => (int) ($x['id'] ?? 0), $t['tags'] ?? []);
    if ($id > 0 && !isset($t['tags'][0]['id'])) {
        $tagIds = array_map('intval', array_column(at_all('SELECT tag_id FROM tour_tags WHERE tour_id = ?', [$id]), 'tag_id'));
    }
    $shifts = array_map(static fn(array $s): array => ['group' => $s['group_name'] ?? '', 'name' => $s['name'] ?? '', 'price' => $s['price'] ?? '', 'price_text' => $s['price_text'] ?? '',
        'start' => $s['start_date'] ?? '', 'end' => $s['end_date'] ?? '', 'availability' => $s['availability'] ?? 'AVAILABLE', 'seats_left' => $s['seats_left'] ?? ''], $t['shifts'] ?? []);
    $groups = array_values(array_unique(array_filter(array_column($shifts, 'group'))));
    $images = array_values((array) ($t['images'] ?? []));
    $sections = [];
    foreach ($t['sections'] ?? [] as $s) {
        $sections[(string) $s['skey']] = $s;
    }
    $status = (string) ($t['status'] ?? (!empty($t['archived_at']) ? 'archived' : (!empty($t['is_published']) ? 'published' : 'draft')));
    $ui = (string) ($_GET['ui'] ?? '');
    $ui = isset(ADM_TOUR_TABS[$ui]) ? $ui : 'basic';
    $cover = (string) ($t['cover'] ?? '');

    $h = '<div class="row" style="margin-bottom:12px"><a href="' . adm_url('tours') . '" class="btn g sm">← Все туры</a>'
        . ($id > 0 ? '<a href="/tour/' . adm_e($t['slug']) . '/" target="_blank" class="btn g sm">Открыть на сайте ↗</a>' : '') . '</div>';
    if ($errors) {
        $h .= '<div class="alert err">' . implode('<br>', array_map('adm_e', $errors)) . '</div>';
    }
    $h .= '<form method="post" data-tour-editor data-ui="' . $ui . '"><input type="hidden" name="csrf" value="' . adm_e(adm_csrf()) . '"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="ui" value="' . $ui . '">';
    // Липкая панель: название, что не заполнено, кнопка сохранения
    $h .= '<div class="savebar"><div style="min-width:0;flex:1"><div class="t" data-ed-title>' . adm_e((string) ($t['title'] ?? '') ?: 'Новый тур') . '</div><div class="w" data-ed-warn></div></div>'
        . '<button class="btn">' . ($id > 0 ? 'Сохранить' : 'Создать тур') . '</button></div>';
    // Вкладки-шаги
    $h .= '<div class="tabs" role="tablist">';
    $n = 1;
    foreach (ADM_TOUR_TABS as $key => $label) {
        $cnt = $key === 'shifts' ? count($shifts) : ($key === 'photos' ? count($images) : 0);
        $h .= '<button type="button" role="tab" data-tab="' . $key . '"' . ($key === $ui ? ' class="on"' : '') . '>' . $n++ . '. ' . adm_e($label) . ' <span data-tab-count="' . $key . '">' . ($cnt > 0 ? '(' . $cnt . ')' : '') . '</span></button>';
    }
    $h .= '</div>';

    // 1. Основное
    $h .= '<section class="card tabp" data-tabp="basic"><div class="grid2">'
        . '<div style="grid-column:1/-1">' . adm_input('title', $t['title'] ?? '', 'Название тура', ['required' => true, 'placeholder' => 'Например: ДОЛ «Морская звезда», лето 2027']) . '</div>'
        . adm_select('type', $t['type'] ?? 'CAMP', 'Тип', ATOUR_TOUR_TYPES)
        . adm_select('region_id', (string) ($t['region_id'] ?? ''), 'Направление (регион)', $regions, 'заграничные отмечены точкой; список — в «Характеристики и регионы»')
        . '<div style="grid-column:1/-1">' . adm_input('address', $t['address'] ?? '', 'Адрес / место', ['placeholder' => 'Крым, г. Саки, ул. Морская, 11']) . '</div>'
        . '<div style="grid-column:1/-1">' . adm_textarea('short', $t['short'] ?? '', 'Краткое описание для карточки', ['rows' => 2], '1–2 предложения: что это и для кого') . '</div>'
        . '<div class="grid3">' . adm_input('age_min', $t['age_min'] ?? '', 'Возраст от', ['type' => 'number', 'min' => 0]) . adm_input('age_max', $t['age_max'] ?? '', 'до', ['type' => 'number', 'min' => 0]) . adm_input('age_text', $t['age_text'] ?? '', 'Возраст текстом', ['placeholder' => 'от 7 до 16 лет']) . '</div>'
        . '<div>' . adm_input('website', $t['website'] ?? '', 'Сайт лагеря', ['placeholder' => 'https://']) . '</div>'
        . '<div><label>Сезоны</label><div>';
    foreach (ATOUR_SEASONS as $k => $v) {
        $h .= adm_checkbox('seasons[]', in_array($k, $t['seasons_list'] ?? [], true), $v, $k);
    }
    $h .= '</div></div><div>' . adm_input('season_text', $t['season_text'] ?? '', 'Сезон подписью', ['placeholder' => 'лето 2027'], 'показывается в карточке; год отсюда подставляется в смены без года') . '</div>'
        . '<div style="grid-column:1/-1"><label>Характеристики <span class="hint">по ним работает фильтр в каталоге</span></label><div class="checks tagbox">';
    foreach (at_tags() as $tag) {
        $h .= adm_checkbox('tags[]', in_array((int) $tag['id'], $tagIds, true), $tag['label'], (string) $tag['id']);
    }
    $h .= '</div></div></div></section>';

    // 2. Смены и цены
    $h .= '<section class="card tabp" data-tabp="shifts"><p class="muted small">Заезды с датами, ценой и наличием мест. Название складывается из дат автоматически; если написать даты в названии, они распознаются сами. Группа нужна, когда у лагеря несколько корпусов или программ с разными ценами.</p>'
        . '<div data-shifts data-next="' . count($shifts) . '"><div style="overflow-x:auto"><table class="shifts"><thead><tr><th>Группа / корпус</th><th>Название</th><th>Заезд</th><th>Выезд</th><th>Дней</th><th>Цена, ₽</th><th>Текст цены</th><th>Места</th><th></th></tr></thead><tbody class="rep-list">';
    foreach ($shifts as $i => $s) {
        $h .= adm_shift_row((string) $i, $s);
    }
    $h .= '</tbody></table></div><template>' . adm_shift_row('__i__', []) . '</template><datalist id="shift-groups">';
    foreach ($groups as $g) {
        $h .= '<option value="' . adm_e((string) $g) . '">';
    }
    $h .= '</datalist><div class="row" style="margin-top:10px"><button type="button" class="btn g sm" data-add>+ Добавить смену</button>'
        . '<button type="button" class="btn g sm" data-next-season title="Все даты +1 год, годы в названиях +1, наличие мест сбрасывается">Перенести на следующий сезон</button>'
        . '<span class="right row" style="gap:6px;font-size:12px;font-weight:700;color:#6a6f97">Изменить все цены на <input type="number" data-percent placeholder="+10" style="width:5rem;padding:5px 8px"> % <button type="button" class="btn g sm" data-apply-percent>Применить</button></span></div>'
        . '<details class="gen"><summary>✦ Сгенерировать смены подряд</summary><div class="grid6">'
        . '<label>Первый заезд<input type="date" data-gen="start"></label><label>Дней в смене<input type="number" data-gen="days" value="21" min="1"></label><label>Количество смен<input type="number" data-gen="count" value="4" min="1" max="20"></label>'
        . '<label>Перерыв, дней<input type="number" data-gen="gap" value="0" min="0"></label><label>Цена, ₽<input type="number" data-gen="price"></label><label>Группа<input type="text" data-gen="group" list="shift-groups" placeholder="—"></label>'
        . '</div><button type="button" class="btn sm" data-generate style="margin-top:8px">Создать смены</button></details></div>'
        . '<div class="grid3" style="margin-top:14px">' . adm_input('price_min', $t['price_min'] ?? '', 'Цена «от», ₽', ['type' => 'number'], 'пусто — минимум по сменам')
        . adm_input('price_text', $t['price_text'] ?? '', 'Текст цены', ['placeholder' => 'от 1 200 $ или по запросу'], 'если цена не числом')
        . adm_input('price_note', $t['price_note'] ?? '', 'Примечание к цене', ['placeholder' => 'авиаперелёт оплачивается отдельно'], 'под ценой в блоке «Смены и цены»') . '</div></section>';

    // 3. Фото
    $h .= '<section class="card tabp" data-tabp="photos"><div data-gallery-ed data-next="' . count($images) . '">'
        . '<label class="dropzone"><input type="file" accept="image/*" multiple hidden><span data-dz-text data-idle="Перетащите фотографии сюда или нажмите, чтобы выбрать">Перетащите фотографии сюда или нажмите, чтобы выбрать</span><small>JPG, PNG, WebP до 8 МБ каждая; фото уменьшаются до 1920 px и переводятся в WebP. Первое загруженное станет обложкой, если её нет.</small></label>'
        . '<div class="gal-grid rep-list">';
    $galItem = static function (string $i, string $url, string $alt) use ($cover): string {
        return '<figure class="gal-item' . ($url !== '' && $url === $cover ? ' is-cover' : '') . '" data-url="' . adm_e($url) . '"><img src="' . adm_e($url !== '' ? at_img_src($url) : '') . '" alt="" loading="lazy"><span class="gal-cover"' . ($url !== '' && $url === $cover ? '' : ' hidden') . '>ОБЛОЖКА</span>'
            . '<input type="hidden" name="images[' . $i . '][url]" value="' . adm_e($url) . '">'
            . '<div class="gal-ctl"><button data-left title="Левее">←</button><button data-right title="Правее">→</button><button data-cover class="star" title="Сделать обложкой">★</button><button data-del class="x" title="Удалить из галереи">✕</button></div>'
            . '<input type="text" name="images[' . $i . '][alt]" value="' . adm_e($alt) . '" placeholder="Подпись (alt)"></figure>';
    };
    foreach ($images as $i => $im) {
        $h .= $galItem((string) $i, (string) ($im['url'] ?? ''), (string) ($im['alt'] ?? ''));
    }
    $h .= '</div><template>' . $galItem('__i__', '__url__', '') . '</template></div>'
        . '<div class="grid2" style="margin-top:16px"><div><label>Обложка <span class="hint">обычно достаточно нажать ★ на фото</span></label><div class="img-field"><img src="' . adm_e(at_img_src($cover)) . '" alt="" data-cover-preview><input type="text" name="cover" value="' . adm_e($cover) . '" data-cover-input placeholder="/uploads/… или /media.php?id=N"></div></div>'
        . '<div>' . adm_image('banner', $t['banner'] ?? '', 'Широкий баннер (промо на главной и лендинг)', 'Картинка 2000×550 для промо-блока на главной. Если задана, страница тура строится как лендинг: лента фото, якорное меню, программа по дням, перелёт, документы') . '</div></div></section>';

    // 4. Описание
    $h .= '<section class="card tabp" data-tabp="content"><p class="muted small">Разделы показываются вкладками на странице тура. Пустые разделы на сайте не выводятся.</p>';
    $keys = array_keys(ATOUR_SECTION_TITLES);
    foreach (array_keys($sections) as $k) {
        if (!in_array($k, $keys, true)) {
            $keys[] = $k;
        }
    }
    foreach ($keys as $key) {
        $defTitle = ATOUR_SECTION_TITLES[$key] ?? (string) ($sections[$key]['title'] ?? $key);
        $html = (string) ($sections[$key]['html'] ?? '');
        $tpl = ADM_SECTION_TEMPLATES[$key] ?? '';
        $h .= '<div data-section="' . adm_e($key) . '"><div class="sec-head"><b>' . adm_e($defTitle) . '</b>'
            . '<input type="text" name="section_title[' . adm_e($key) . ']" value="' . adm_e((string) ($sections[$key]['title'] ?? $defTitle)) . '" aria-label="Название вкладки на сайте" title="Название вкладки на сайте">'
            . ($tpl !== '' ? '<button type="button" class="btn g sm" data-tpl="' . adm_e($tpl) . '"' . (trim($html) !== '' ? ' hidden' : '') . '>Вставить шаблон</button>' : '')
            . (isset(ADM_SECTION_HINTS[$key]) ? '<span class="hint">' . adm_e(ADM_SECTION_HINTS[$key]) . '</span>' : '') . '</div>'
            . adm_html('section[' . $key . ']', $html, '', $key === 'description' ? 12 : 6) . '</div>';
    }
    $h .= '</section>';

    // 5. Документы
    $h .= '<section class="card tabp" data-tabp="docs"><p class="muted small">Файлы для родителей: анкеты, формы справок, договор, программа. Загрузите PDF (Word, Excel) кнопкой «Файл» или укажите ссылку.</p>'
        . adm_repeater('documents', $t['documents'] ?? [], ['label' => ['Название (Медицинская справка)', 'text'], 'url' => ['/uploads/… или https://', 'text']], 'Добавить документ', '1fr 1fr auto auto', 'file') . '</section>';

    // 6. Публикация и SEO
    $statuses = [['draft', 'Черновик', 'не виден на сайте'], ['published', 'Опубликован', 'виден в каталоге и на главной'], ['archived', 'Архив', 'скрыт, хранится для копирования']];
    $h .= '<section class="card tabp" data-tabp="publish"><div class="cols"><div><label>Статус</label><div class="status-pick">';
    foreach ($statuses as [$k, $label, $hint]) {
        $h .= '<label class="' . ($status === $k ? 'on' : '') . '"><span><input type="radio" name="status" value="' . $k . '"' . ($status === $k ? ' checked' : '') . '> ' . $label . '</span><small>' . $hint . '</small></label>';
    }
    $h .= '</div><div style="margin:14px 0">' . adm_checkbox('is_best', (bool) ($t['is_best'] ?? 0), '★ Лучшее предложение (блок на главной)') . adm_checkbox('is_hit', (bool) ($t['is_hit'] ?? 0), '🔥 Хит продаж') . '</div>'
        . '<div class="grid2">' . adm_input('sort_order', $t['sort_order'] ?? 0, 'Порядок в каталоге', ['type' => 'number'], 'меньше — выше')
        . adm_input('slug', $t['slug'] ?? '', 'URL (slug)', [], 'пусто — из названия; менять осторожно: старый адрес перестанет работать') . '</div>'
        . adm_textarea('internal_note', $t['internal_note'] ?? '', 'Заметка для менеджеров', ['rows' => 3, 'placeholder' => 'Контакты лагеря, условия комиссии, что уточнить…'], 'на сайте не видна') . '</div>'
        . '<div><h2>SEO</h2>' . adm_input('seo_title', $t['seo_title'] ?? '', 'Title страницы', [], '{year} — сохранённый год тура или год каталога из настроек; пусто — название тура')
        . adm_textarea('meta_description', $t['meta_description'] ?? '', 'Meta description', ['rows' => 3], 'пусто — краткое описание')
        . adm_input('meta_keywords', $t['meta_keywords'] ?? '', 'Keywords') . '</div></div></section>';

    $h .= '<div class="row" style="margin-top:4px"><button class="btn">' . ($id > 0 ? 'Сохранить' : 'Создать тур') . '</button><button type="button" class="btn g" data-tab-next>Далее →</button></div></form>';
    if ($id > 0) {
        $h .= '<div style="margin-top:12px">' . adm_delete_form(['id' => $id], 'Удалить тур', 'Удалить тур вместе со сменами, фото и вкладками? Обычно достаточно отправить его в архив.') . '</div>';
    }
    return $h;
}
