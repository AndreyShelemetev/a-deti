<?php
/** Подписчики рассылки: список, отключение, удаление, выгрузка. */
declare(strict_types=1);

function adm_tab_subscribers(): string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $id = (int) ($_POST['id'] ?? 0);
        if (($_POST['action'] ?? '') === 'delete') {
            db()->prepare('DELETE FROM subscribers WHERE id = ?')->execute([$id]);
        } elseif (($_POST['action'] ?? '') === 'toggle') {
            db()->prepare('UPDATE subscribers SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
        }
        adm_redirect('subscribers', ['ok' => 1]);
    }
    if (isset($_GET['export'])) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="subscribers.csv"');
        echo "\xEF\xBB\xBF" . "email;name;active;created\n";
        foreach (at_all('SELECT * FROM subscribers ORDER BY id') as $s) {
            echo implode(';', [$s['email'], str_replace(';', ',', (string) $s['name']), $s['is_active'], $s['created_at']]) . "\n";
        }
        exit;
    }
    $rows = at_all('SELECT * FROM subscribers ORDER BY id DESC LIMIT 500');
    $h = '<div class="row" style="margin-bottom:12px"><a href="' . adm_url('subscribers', ['export' => 1]) . '" class="btn g">Выгрузить CSV</a><span class="muted right">Всего: ' . count($rows) . '</span></div>';
    if (!$rows) {
        return $h . '<div class="card muted">Подписчиков пока нет.</div>';
    }
    $h .= '<div class="card"><table><thead><tr><th>E-mail</th><th>Имя</th><th>Дата</th><th>Активен</th><th></th></tr></thead><tbody>';
    foreach ($rows as $s) {
        $h .= '<tr><td><b>' . adm_e($s['email']) . '</b></td><td>' . adm_e($s['name'] ?? '') . '</td><td>' . adm_e(at_date_short($s['created_at'])) . '</td>'
            . '<td>' . adm_form_open('toggle', ['id' => (int) $s['id']]) . '<button class="btn g sm">' . ($s['is_active'] ? 'да · отключить' : 'нет · включить') . '</button></form></td>'
            . '<td>' . adm_delete_form(['id' => (int) $s['id']], '✕', 'Удалить подписчика?') . '</td></tr>';
    }
    return $h . '</tbody></table></div>';
}
