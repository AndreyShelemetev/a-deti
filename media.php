<?php
/**
 * Отдача файлов, загруженных через админку сайта (картинки туров, обложки).
 * Сайт не может писать на диск, поэтому файлы лежат в таблице media, а
 * ссылка на них — /media.php?id=N. db() и inc/init.php подключены платформой.
 */
declare(strict_types=1);

require_once __DIR__ . '/inc/init.php';

$id = (int) ($_GET['id'] ?? 0);
$row = $id > 0 ? at_one('SELECT mime, data, size FROM media WHERE id = ?', [$id]) : null;
if ($row === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Файл не найден';
    exit;
}
header('Content-Type: ' . (string) $row['mime']);
if (!str_starts_with((string) $row['mime'], 'image/')) {
    // Документы (PDF, Word, Excel): браузер откроет или скачает под своим именем.
    $name = (string) (at_one('SELECT name FROM media WHERE id = ?', [$id])['name'] ?? 'file');
    header("Content-Disposition: inline; filename*=UTF-8''" . rawurlencode($name));
}
header('Content-Length: ' . strlen((string) $row['data']));
header('Cache-Control: public, max-age=2592000, immutable');
header('X-Content-Type-Options: nosniff');
echo $row['data'];
