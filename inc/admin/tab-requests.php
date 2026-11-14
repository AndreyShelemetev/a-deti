<?php
/** Заявки с форм сайта: список с фильтрами, карточка со статусом и заметкой. */
declare(strict_types=1);

function adm_tab_requests(): string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $id = (int) ($_POST['id'] ?? 0);
        if (($_POST['action'] ?? '') === 'delete') {
            db()->prepare('DELETE FROM requests WHERE id = ?')->execute([$id]);
            adm_redirect('requests', ['ok' => 1, 'msg' => 'Заявка удалена.']);
        }
        $status = (string) ($_POST['status'] ?? 'NEW');
        if (!isset(ATOUR_REQUEST_STATUSES[$status])) {
            $status = 'NEW';
        }
        db()->prepare('UPDATE requests SET status = ?, note = ? WHERE id = ?')->execute([$status, adm_str('note', 5000, true), $id]);
        adm_redirect('requests', ['id' => $id, 'ok' => 1]);
    }
    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $r = at_one('SELECT * FROM requests WHERE id = ?', [$id]);
        if ($r === null) {
            return '<div class="alert err">Заявка не найдена.</div>';
        }
        $rows = [
            'Тип' => ATOUR_REQUEST_KINDS[$r['kind']] ?? $r['kind'], 'Дата' => at_datetime($r['created_at']), 'Имя' => $r['name'], 'Телефон' => $r['phone'],
            'E-mail' => $r['email'], 'Тур' => $r['tour_title'], 'Смена' => $r['shift_name'], 'Сумма' => $r['amount'] ? at_price((int) $r['amount']) : '',
            'Страница' => $r['page_url'], 'Сообщение' => $r['message'],
        ];
        $payload = $r['payload'] ? json_decode((string) $r['payload'], true) : null;
        if (is_array($payload)) {
            foreach ($payload as $k => $v) {
                $rows[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
            }
        }
        $copyId = 'request-copy-' . $id;
        $copyLines = ['Заявка #' . $id];
        foreach ($rows as $k => $v) if ((string) $v !== '') $copyLines[] = $k . ': ' . $v;
        $copyText = implode("\n", $copyLines);
        $h = '<div class="row"><a href="' . adm_url('requests') . '" class="btn g sm">← Все заявки</a><button type="button" class="btn" data-copy-request="' . $copyId . '">Скопировать данные</button></div>'
            . '<p class="muted small" role="status" aria-live="polite" data-copy-request-status hidden></p>'
            . '<textarea id="' . $copyId . '" readonly hidden rows="12" aria-label="Данные заявки для копирования">' . adm_e($copyText) . '</textarea>'
            . '<div class="cols" style="margin-top:12px"><div class="card"><h2>Заявка #' . $id . '</h2><table>';
        foreach ($rows as $k => $v) {
            if ((string) $v === '') {
                continue;
            }
            $h .= '<tr><th style="width:160px">' . adm_e($k) . '</th><td>' . nl2br(adm_e($v)) . '</td></tr>';
        }
        $h .= '</table></div><div class="card">' . adm_form_open('save', ['id' => $id])
            . adm_select('status', $r['status'], 'Статус', ATOUR_REQUEST_STATUSES)
            . adm_textarea('note', $r['note'], 'Заметка менеджера', ['rows' => 5])
            . '<div class="row" style="margin-top:12px"><button class="btn">Сохранить</button></div></form><div style="margin-top:12px">' . adm_delete_form(['id' => $id], 'Удалить заявку') . '</div></div></div>';
        $version = hash_file('crc32b', ATOUR_ROOT . '/assets/admin-request-copy.js');
        return $h . '<script src="/assets/admin-request-copy.js?v=' . adm_e($version) . '" defer></script>';
    }
    $status = (string) ($_GET['status'] ?? '');
    $kind = (string) ($_GET['kind'] ?? '');
    $where = '1=1';
    $params = [];
    if (isset(ATOUR_REQUEST_STATUSES[$status])) {
        $where .= ' AND status = ?';
        $params[] = $status;
    }
    if (isset(ATOUR_REQUEST_KINDS[$kind])) {
        $where .= ' AND kind = ?';
        $params[] = $kind;
    }
    $page = max((int) ($_GET['page'] ?? 1), 1);
    $total = (int) at_scalar("SELECT COUNT(*) FROM requests WHERE {$where}", $params);
    $items = at_all("SELECT * FROM requests WHERE {$where} ORDER BY id DESC LIMIT 30 OFFSET " . (($page - 1) * 30), $params);
    $keep = array_filter(['status' => $status, 'kind' => $kind]);
    $h = '<div class="filters"><a href="' . adm_url('requests') . '" class="' . ($status === '' && $kind === '' ? 'on' : '') . '">Все</a>';
    foreach (ATOUR_REQUEST_STATUSES as $k => $v) {
        $h .= '<a href="' . adm_url('requests', array_filter(['status' => $k, 'kind' => $kind])) . '" class="' . ($status === $k ? 'on' : '') . '">' . adm_e($v) . '</a>';
    }
    $h .= ' | ';
    foreach (ATOUR_REQUEST_KINDS as $k => $v) {
        $h .= '<a href="' . adm_url('requests', array_filter(['kind' => $k, 'status' => $status])) . '" class="' . ($kind === $k ? 'on' : '') . '">' . adm_e($v) . '</a>';
    }
    $h .= '</div><p class="muted">Всего: ' . $total . '. Те же заявки дублируются во вкладку «Лиды» панели платформы (с уведомлениями владельцу).</p>';
    if (!$items) {
        return $h . '<div class="card muted">Заявок с такими параметрами нет.</div>';
    }
    $h .= '<div class="card"><table><thead><tr><th>#</th><th>Дата</th><th>Тип</th><th>Контакт</th><th>Тур / сообщение</th><th>Статус</th></tr></thead><tbody>';
    foreach ($items as $r) {
        $h .= '<tr><td><a href="' . adm_url('requests', ['id' => (int) $r['id']]) . '"><b>#' . (int) $r['id'] . '</b></a></td><td style="white-space:nowrap">' . adm_e(at_datetime($r['created_at'])) . '</td>'
            . '<td>' . adm_e(ATOUR_REQUEST_KINDS[$r['kind']] ?? $r['kind']) . '</td><td><b>' . adm_e($r['name'] ?? '—') . '</b><div class="muted small">' . adm_e(trim(($r['phone'] ?? '') . ' ' . ($r['email'] ?? ''))) . '</div></td>'
            . '<td>' . ($r['tour_title'] ? '<b>' . adm_e($r['tour_title']) . '</b> ' : '') . ($r['shift_name'] ? '<span class="muted">· ' . adm_e($r['shift_name']) . '</span> ' : '') . adm_e(mb_substr((string) ($r['message'] ?? ''), 0, 120)) . '</td>'
            . '<td><span class="chip ' . adm_e($r['status']) . '">' . adm_e(ATOUR_REQUEST_STATUSES[$r['status']] ?? $r['status']) . '</span></td></tr>';
    }
    return $h . '</tbody></table></div>' . adm_pages($page, max((int) ceil($total / 30), 1), 'requests', $keep);
}
