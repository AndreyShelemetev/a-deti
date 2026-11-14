<?php
/**
 * Универсальный список/форма/сохранение для простых таблиц админки: слайды,
 * партнёры, рейтинги, отзывы, характеристики, регионы, страницы каталога,
 * новости и статьи, текстовые страницы, редиректы.
 *
 * Конфиг вкладки:
 *   table, one (название записи), fields (имя => описание поля), columns
 *   (список: имя => подпись или функция строки), order, keep (GET-параметры,
 *   которые сохраняем в ссылках), listWhere ([sql, params]), perPage, defaults,
 *   beforeSave(data, id) → [data, errors], afterSave(id, data), search (колонки).
 * Типы полей: text, textarea, number, decimal, checkbox, select (options),
 *   image, html, datetime, slug (from), csv (options — группа чекбоксов),
 *   hidden. Флаги: required, unique, uniqueWith, max, side (правая колонка), hint.
 */
declare(strict_types=1);

function adm_crud(string $tab, array $cfg): string
{
    $table = (string) $cfg['table'];
    $keep = [];
    foreach ((array) ($cfg['keep'] ?? []) as $k) {
        if (isset($_GET[$k]) && !is_array($_GET[$k])) {
            $keep[$k] = (string) $_GET[$k];
        }
    }
    $id = (int) ($_GET['id'] ?? 0);
    $errors = [];
    $formRow = null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'delete') {
            $did = (int) ($_POST['id'] ?? 0);
            db()->prepare("DELETE FROM `{$table}` WHERE id = ?")->execute([$did]);
            if (isset($cfg['afterDelete'])) {
                $cfg['afterDelete']($did);
            }
            adm_redirect($tab, $keep + ['ok' => 1, 'msg' => 'Удалено.']);
        }
        if ($action === 'save') {
            $sid = (int) ($_POST['id'] ?? 0);
            [$data, $errors] = adm_crud_read($table, $cfg['fields'], $sid);
            if (isset($cfg['beforeSave'])) {
                [$data, $more] = $cfg['beforeSave']($data, $sid);
                $errors = array_merge($errors, $more);
            }
            if (!$errors) {
                $newId = adm_crud_write($table, $data, $sid);
                if (isset($cfg['afterSave'])) {
                    $cfg['afterSave']($newId, $data);
                }
                adm_redirect($tab, $keep + ['id' => $newId, 'ok' => 1]);
            }
            $formRow = $data + ['id' => $sid];
        }
    }
    if ($formRow !== null || $id > 0 || isset($_GET['new'])) {
        if ($formRow === null) {
            $formRow = $id > 0 ? at_one("SELECT * FROM `{$table}` WHERE id = ?", [$id]) : (array) ($cfg['defaults'] ?? []);
            if ($formRow === null) {
                return '<div class="alert err">Запись не найдена.</div>';
            }
        }
        return adm_crud_form($tab, $cfg, $formRow, $keep, $errors);
    }
    return adm_crud_list($tab, $cfg, $keep);
}

/** @return array{0:array<string,mixed>,1:array<int,string>} */
function adm_crud_read(string $table, array $fields, int $id): array
{
    $data = [];
    $errors = [];
    foreach ($fields as $name => $f) {
        $type = (string) ($f['type'] ?? 'text');
        $label = (string) ($f['label'] ?? $name);
        $max = (int) ($f['max'] ?? 5000);
        switch ($type) {
            case 'number':
                $v = adm_int($name);
                break;
            case 'decimal':
                $raw = str_replace(',', '.', trim((string) ($_POST[$name] ?? '')));
                $v = $raw === '' ? null : (float) $raw;
                break;
            case 'checkbox':
                $v = adm_bool($name);
                break;
            case 'select':
                $v = adm_str($name, 64, true);
                if ($v !== null && !isset($f['options'][$v])) {
                    $v = null;
                }
                if ($v === null && !empty($f['required'])) {
                    $v = (string) array_key_first((array) $f['options']);
                }
                break;
            case 'html':
                $v = at_sanitize_html((string) ($_POST[$name] ?? ''));
                break;
            case 'datetime':
                $v = adm_datetime($name);
                if ($v === null && !empty($f['required'])) {
                    $v = date('Y-m-d H:i:s');
                }
                break;
            case 'slug':
                $raw = adm_str($name, 190, true);
                $from = (string) ($f['from'] ?? '');
                $src = $raw ?? ($from !== '' ? (string) ($_POST[$from] ?? '') : '');
                $v = at_slugify($src);
                if ($v === '') {
                    $v = 'item-' . time();
                }
                break;
            case 'csv':
                $v = at_join(array_values(array_intersect(adm_list($name), array_keys((array) ($f['options'] ?? [])))));
                break;
            default:
                $v = adm_str($name, $max, !empty($f['nullable']));
        }
        if (!empty($f['required']) && ($v === null || $v === '')) {
            $errors[] = 'Заполните поле «' . $label . '».';
        }
        if (!empty($f['unique']) && $v !== null && $v !== '') {
            $sql = "SELECT id FROM `{$table}` WHERE `{$name}` = ? AND id <> ?";
            $params = [$v, $id];
            if (!empty($f['uniqueWith'])) {
                $sql .= ' AND `' . $f['uniqueWith'] . '` = ?';
                $params[] = (string) ($_POST[$f['uniqueWith']] ?? '');
            }
            if (at_one($sql, $params) !== null) {
                $errors[] = 'Значение поля «' . $label . '» уже занято другой записью.';
            }
        }
        $data[$name] = $v;
    }
    return [$data, $errors];
}

function adm_crud_write(string $table, array $data, int $id): int
{
    $cols = array_keys($data);
    if ($id > 0) {
        $set = implode(', ', array_map(static fn(string $c): string => "`{$c}` = ?", $cols));
        db()->prepare("UPDATE `{$table}` SET {$set} WHERE id = ?")->execute([...array_values($data), $id]);
        return $id;
    }
    $names = implode(', ', array_map(static fn(string $c): string => "`{$c}`", $cols));
    db()->prepare("INSERT INTO `{$table}` ({$names}) VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($data));
    return (int) db()->lastInsertId();
}

function adm_crud_field(string $name, array $f, mixed $value): string
{
    $label = (string) ($f['label'] ?? $name);
    $hint = (string) ($f['hint'] ?? '');
    switch ((string) ($f['type'] ?? 'text')) {
        case 'textarea':
            return adm_textarea($name, $value, $label, ['rows' => (int) ($f['rows'] ?? 3)], $hint);
        case 'number':
            return adm_input($name, $value, $label, ['type' => 'number', 'step' => '1'], $hint);
        case 'decimal':
            return adm_input($name, $value, $label, ['type' => 'number', 'step' => '0.1'], $hint);
        case 'checkbox':
            return '<div style="margin:12px 0">' . adm_checkbox($name, (bool) $value, $label) . '</div>';
        case 'select':
            return adm_select($name, $value, $label, (array) $f['options'], $hint);
        case 'image':
            return adm_image($name, $value, $label, $hint);
        case 'html':
            return adm_html($name, $value, $label, (int) ($f['rows'] ?? 10));
        case 'datetime':
            return adm_input($name, adm_dt_value(is_string($value) ? $value : null), $label, ['type' => 'datetime-local'], $hint);
        case 'csv':
            $set = at_split(is_string($value) ? $value : '');
            $h = '<label>' . adm_e($label) . '</label><div class="checks">';
            foreach ((array) $f['options'] as $k => $v) {
                $h .= adm_checkbox($name . '[]', in_array((string) $k, $set, true), $v, (string) $k);
            }
            return $h . '</div>';
        case 'hidden':
            return '<input type="hidden" name="' . adm_e($name) . '" value="' . adm_e((string) $value) . '">';
        default:
            return adm_input($name, $value, $label, ['maxlength' => (int) ($f['max'] ?? 5000)], $hint);
    }
}

function adm_crud_form(string $tab, array $cfg, array $row, array $keep, array $errors): string
{
    $id = (int) ($row['id'] ?? 0);
    $h = '<div class="row" style="margin-bottom:12px"><a href="' . adm_url($tab, $keep) . '" class="btn g sm">← К списку</a>'
        . ($id > 0 && isset($cfg['viewUrl']) ? '<a href="' . adm_e($cfg['viewUrl']($row)) . '" target="_blank" class="btn g sm">Открыть на сайте ↗</a>' : '') . '</div>';
    if ($errors) {
        $h .= '<div class="alert err">' . implode('<br>', array_map('adm_e', $errors)) . '</div>';
    }
    $main = '';
    $side = '';
    foreach ($cfg['fields'] as $name => $f) {
        $html = adm_crud_field($name, $f, $row[$name] ?? ($f['default'] ?? ''));
        if (!empty($f['side'])) {
            $side .= $html;
        } else {
            $main .= $html;
        }
    }
    $h .= adm_form_open('save', ['id' => $id]) . '<div class="cols"><div class="card">' . $main . '</div>'
        . ($side !== '' ? '<div class="card">' . $side . '</div>' : '') . '</div>'
        . '<div class="row"><button class="btn">' . ($id > 0 ? 'Сохранить' : 'Создать') . '</button></div></form>';
    if ($id > 0) {
        $h .= '<div style="margin-top:12px">' . adm_delete_form(['id' => $id], 'Удалить ' . adm_e((string) ($cfg['one'] ?? 'запись'))) . '</div>';
    }
    return $h;
}

function adm_crud_list(string $tab, array $cfg, array $keep): string
{
    $table = (string) $cfg['table'];
    $perPage = (int) ($cfg['perPage'] ?? 50);
    $page = max((int) ($_GET['page'] ?? 1), 1);
    [$where, $params] = $cfg['listWhere'] ?? ['1=1', []];
    $q = trim((string) ($_GET['q'] ?? ''));
    if ($q !== '' && !empty($cfg['search'])) {
        $like = [];
        foreach ((array) $cfg['search'] as $c) {
            $like[] = "`{$c}` LIKE ?";
            $params[] = '%' . at_like($q) . '%';
        }
        $where .= ' AND (' . implode(' OR ', $like) . ')';
    }
    $total = (int) at_scalar("SELECT COUNT(*) FROM `{$table}` WHERE {$where}", $params);
    $rows = at_all("SELECT * FROM `{$table}` WHERE {$where} ORDER BY " . ($cfg['order'] ?? 'id DESC') . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);
    $h = '<div class="row" style="margin-bottom:12px"><a href="' . adm_url($tab, $keep + ['new' => 1]) . '" class="btn">+ Добавить ' . adm_e(mb_strtolower((string) ($cfg['one'] ?? 'запись'))) . '</a>';
    if (!empty($cfg['search'])) {
        $h .= '<form method="get" class="row"><input type="hidden" name="tab" value="' . adm_e($tab) . '">';
        foreach ($keep as $k => $v) {
            $h .= '<input type="hidden" name="' . adm_e($k) . '" value="' . adm_e($v) . '">';
        }
        $h .= '<input type="text" name="q" value="' . adm_e($q) . '" placeholder="Поиск" style="width:240px"><button class="btn g">Найти</button></form>';
    }
    $h .= '<span class="muted right">Всего: ' . $total . '</span></div>';
    if (!empty($cfg['intro'])) {
        $h .= '<p class="muted">' . $cfg['intro'] . '</p>';
    }
    if (!$rows) {
        return $h . '<div class="card muted">Пока пусто.</div>';
    }
    $h .= '<div class="card"><table><thead><tr>';
    foreach ($cfg['columns'] as $name => $col) {
        $h .= '<th>' . adm_e(is_array($col) ? $col[0] : (is_string($col) ? $col : $name)) . '</th>';
    }
    $h .= '<th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        $h .= '<tr>';
        $first = true;
        foreach ($cfg['columns'] as $name => $col) {
            $cell = is_array($col) && is_callable($col[1] ?? null) ? $col[1]($r) : adm_e((string) ($r[$name] ?? ''));
            if ($first) {
                $cell = '<a href="' . adm_url($tab, $keep + ['id' => (int) $r['id']]) . '"><b>' . $cell . '</b></a>';
                $first = false;
            }
            $h .= '<td>' . $cell . '</td>';
        }
        $h .= '<td style="white-space:nowrap"><a href="' . adm_url($tab, $keep + ['id' => (int) $r['id']]) . '" class="btn g sm">Править</a> ' . adm_delete_form(['id' => (int) $r['id']], '✕', 'Удалить запись?') . '</td></tr>';
    }
    $h .= '</tbody></table></div>' . adm_pages($page, max((int) ceil($total / $perPage), 1), $tab, $keep);
    return $h;
}
