<?php
/** Заявки на онлайн-оплату: список и смена статуса. */
declare(strict_types=1);

function adm_tab_payments(): string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $id = (int) ($_POST['id'] ?? 0);
        if (($_POST['action'] ?? '') === 'delete') {
            db()->prepare('DELETE FROM payments WHERE id = ?')->execute([$id]);
            adm_redirect('payments', ['ok' => 1, 'msg' => 'Удалено.']);
        }
        $status = (string) ($_POST['status'] ?? 'NEW');
        if (isset(ATOUR_PAYMENT_STATUSES[$status])) {
            db()->prepare('UPDATE payments SET status = ? WHERE id = ?')->execute([$status, $id]);
        }
        adm_redirect('payments', ['ok' => 1]);
    }
    $page = max((int) ($_GET['page'] ?? 1), 1);
    $total = (int) at_scalar('SELECT COUNT(*) FROM payments');
    $rows = at_all('SELECT * FROM payments ORDER BY id DESC LIMIT 30 OFFSET ' . (($page - 1) * 30));
    $gateway = at_setting('payment.gateway_url');
    $h = '<p class="muted">' . ($gateway !== '' ? 'Шлюз оплаты: ' . adm_e($gateway) : 'Платёжный шлюз не подключён: посетитель видит «заявка принята», менеджер присылает ссылку на оплату вручную. Адрес шлюза задаётся в настройках сайта.') . '</p>';
    if (!$rows) {
        return $h . '<div class="card muted">Заявок на оплату пока нет.</div>';
    }
    $h .= '<div class="card"><table><thead><tr><th>#</th><th>Дата</th><th>Договор</th><th>Плательщик</th><th>Сумма</th><th>МИР</th><th>Статус</th><th></th></tr></thead><tbody>';
    foreach ($rows as $p) {
        $h .= '<tr><td>' . (int) $p['id'] . '</td><td style="white-space:nowrap">' . adm_e(at_datetime($p['created_at'])) . '</td><td><b>' . adm_e($p['order_number']) . '</b></td>'
            . '<td>' . adm_e($p['full_name']) . '<div class="muted small">' . adm_e(trim(($p['phone'] ?? '') . ' ' . $p['email'])) . '</div></td><td>' . adm_e(at_price((int) $p['amount'])) . '</td><td>' . ($p['is_mir_card'] ? 'да' : '—') . '</td>'
            . '<td>' . adm_form_open('status', ['id' => (int) $p['id']]) . '<select name="status" onchange="this.form.submit()" style="width:auto">';
        foreach (ATOUR_PAYMENT_STATUSES as $k => $v) {
            $h .= '<option value="' . $k . '"' . ($p['status'] === $k ? ' selected' : '') . '>' . adm_e($v) . '</option>';
        }
        $h .= '</select></form></td><td>' . adm_delete_form(['id' => (int) $p['id']], '✕', 'Удалить платёж?') . '</td></tr>';
    }
    return $h . '</tbody></table></div>' . adm_pages($page, max((int) ceil($total / 30), 1), 'payments');
}
