<?php
/** Картинки, загруженные в базу сайта: загрузка (в т.ч. из редакторов через fetch), список, удаление. */
declare(strict_types=1);

function adm_tab_media(): string
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = (string) ($_GET['action'] ?? ($_POST['action'] ?? ''));
        if ($action === 'upload' || $action === 'upload-file') {
            header('Content-Type: application/json; charset=utf-8');
            $res = $action === 'upload' ? adm_upload_image((array) ($_FILES['file'] ?? [])) : adm_upload_file((array) ($_FILES['file'] ?? []));
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($action === 'delete') {
            db()->prepare('DELETE FROM media WHERE id = ?')->execute([(int) ($_POST['id'] ?? 0)]);
            adm_redirect('media', ['ok' => 1, 'msg' => 'Удалено.']);
        }
        if ($action === 'form-upload') {
            $res = adm_upload_image((array) ($_FILES['file'] ?? []));
            adm_redirect('media', $res['ok'] ? ['ok' => 1, 'msg' => 'Загружено: ' . $res['url']] : ['err' => $res['error'] ?? 'Ошибка']);
        }
    }
    $page = max((int) ($_GET['page'] ?? 1), 1);
    $total = (int) at_scalar('SELECT COUNT(*) FROM media');
    $rows = at_all('SELECT id, mime, name, size, created_at FROM media ORDER BY id DESC LIMIT 48 OFFSET ' . (($page - 1) * 48));
    $h = '<div class="card"><form method="post" enctype="multipart/form-data" class="row"><input type="hidden" name="csrf" value="' . adm_e(adm_csrf()) . '"><input type="hidden" name="action" value="form-upload"><input type="file" name="file" accept="image/*" required><button class="btn">Загрузить</button><span class="muted">JPEG, PNG, WebP до 8 МБ (лимит загрузки на платформе); картинка уменьшается до 1920 px и хранится в базе сайта. Адрес вида /media.php?id=N можно вставлять в любые поля картинок.</span></form></div>';
    if (!$rows) {
        return $h . '<div class="card muted">Загруженных картинок пока нет. Фотографии из исходного сайта лежат в /uploads/… и в этот список не входят.</div>';
    }
    $h .= '<div class="card"><table><thead><tr><th>Превью</th><th>Адрес</th><th>Файл</th><th>Размер</th><th>Дата</th><th></th></tr></thead><tbody>';
    foreach ($rows as $m) {
        $url = '/media.php?id=' . (int) $m['id'];
        $h .= '<tr><td><img src="' . $url . '" alt="" style="width:120px;height:80px;object-fit:cover;border-radius:8px"></td><td><code>' . $url . '</code></td><td>' . adm_e($m['name']) . '<div class="muted small">' . adm_e($m['mime']) . '</div></td>'
            . '<td>' . round((int) $m['size'] / 1024) . ' КБ</td><td>' . adm_e(at_date_short($m['created_at'])) . '</td><td>' . adm_delete_form(['id' => (int) $m['id']], '✕', 'Удалить картинку? Ссылки на неё перестанут работать.') . '</td></tr>';
    }
    return $h . '</tbody></table></div>' . adm_pages($page, max((int) ceil($total / 48), 1), 'media');
}
