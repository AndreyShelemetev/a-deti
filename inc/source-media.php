<?php
/** Локальные копии исходных фотографий. Контент и настройки меню не изменяются. */
declare(strict_types=1);

const AT_SOURCE_MEDIA_SETTING = 'source.media.cache';
const AT_SOURCE_MEDIA_MAX_BYTES = 15 * 1024 * 1024;
// Exact image URLs embedded in the verified a-deti.ru tour snapshot. This is
// deliberately not a host allowlist: another image/path/query is not accepted.
const AT_SOURCE_MEDIA_EXTERNAL_URLS = [
    'https://center-orlyonok.ru/Files/GetImage?id=1f14449d-0fa4-4bf1-8931-4a7b3ed5dbe0&width=null&height=null&defImage=undefined&type=frame',
    'https://pix8.agoda.net/hotelImages/23166424/-1/c4237a4696e63882bc1efff27211569e.jpg?ca=18&ce=1&s=700x768',
    'https://www.hilton.com/im/en/PKXWAUP/15388843/twin-guest-room-twin.jpg?impolicy=crop&cw=4400&ch=2463&gravity=NorthWest&xposition=0&yposition=418&rw=768&rh=430',
];

/** Validated HTTPS source host; no untrusted external URL reaches DNS. */
function at_source_media_host(string $url): ?string
{
    $p = parse_url($url);
    if (!is_array($p) || ($p['scheme'] ?? '') !== 'https' || !is_string($p['host'] ?? null)
        || isset($p['port']) || isset($p['user']) || isset($p['pass']) || isset($p['fragment'])
        || preg_match('~[\x00-\x20\\\\]~', $url)) return null;
    if ($p['host'] === 'a-deti.ru' && preg_match('~^/(?:upload|assets/images)/~', (string) ($p['path'] ?? ''))) return $p['host'];
    return in_array($url, AT_SOURCE_MEDIA_EXTERNAL_URLS, true) ? $p['host'] : null;
}

/** MySQL named locks are server-wide, so each site's database needs its own. */
function at_source_media_lock_name(string $database): string
{
    if ($database === '') throw new RuntimeException('Не удалось определить базу для сохранения фотографий.');
    return 'atour_source_media_' . substr(hash('sha256', $database), 0, 32);
}

/** Только фотографии из проверенного снимка, а не URL из запроса пользователя. */
function at_source_media_urls(array $manifest): array
{
    $urls = [];
    $add = static function (string $url) use (&$urls): void {
        if (at_source_media_host($url) === null) return;
        $urls[$url] = true;
    };
    foreach ($manifest['tours'] ?? [] as $tour) {
        foreach (['cover', 'banner'] as $field) $add((string) ($tour[$field] ?? ''));
        foreach ($tour['images'] ?? [] as $image) $add(is_array($image) ? (string) ($image['url'] ?? '') : (string) $image);
        foreach ($tour['sections'] ?? [] as $section) {
            preg_match_all('~<img\b[^>]*?\s+src\s*=\s*([\'\"])(.*?)\1~is', (string) ($section['html'] ?? ''), $matches);
            foreach ($matches[2] as $url) $add(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            preg_match_all('~<a\b[^>]*?\s+href\s*=\s*([\'\"])(.*?)\1~is', (string) ($section['html'] ?? ''), $links);
            foreach ($links[2] as $url) {
                $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match('~\.(?:jpe?g|png|webp|gif|avif)$~iD', (string) parse_url($url, PHP_URL_PATH))) $add($url);
            }
        }
    }
    return array_keys($urls);
}

/** Происхождение этих файлов зафиксировано при первоначальной загрузке; подбор по похожим фото не используется. */
function at_source_media_local_map(): array
{
    static $map;
    if (isset($map)) return $map;
    $map = [];
    $file = __DIR__ . '/content/source-media-local.json';
    $raw = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
    foreach (is_array($raw) ? $raw : [] as $url => $path) {
        if (is_string($path) && preg_match('~^/(?:assets/design/winter-2027/[a-f0-9]{18}|assets/design/source-photos/[a-f0-9]{64}|uploads/iblock/[a-zA-Z0-9_-]+/[a-zA-Z0-9_-]+)\.(?:jpe?g|png|webp|gif)$~D', $path)
            && is_file(dirname(__DIR__) . $path)) $map[$url] = $path;
    }
    return $map;
}

function at_source_media_saved_map(PDO $pdo): array
{
    $st = $pdo->prepare('SELECT v FROM atour_settings WHERE k = ?');
    $st->execute([AT_SOURCE_MEDIA_SETTING]);
    $map = json_decode((string) $st->fetchColumn(), true);
    return is_array($map) ? array_filter($map, static fn($url): bool => is_string($url) && preg_match('~^/media\.php\?id=[1-9][0-9]*$~D', $url)) : [];
}

/** Не выполняет сетевых запросов при просмотре сайта. */
function at_source_media_resolve(string $url): string
{
    if (at_source_media_host($url) === null) return $url;
    static $map;
    if (!isset($map)) {
        $raw = function_exists('at_setting') ? json_decode(at_setting(AT_SOURCE_MEDIA_SETTING), true) : [];
        $saved = is_array($raw) ? array_filter($raw, static fn($v): bool => is_string($v) && preg_match('~^/media\.php\?id=[1-9][0-9]*$~D', $v)) : [];
        $map = at_source_media_local_map() + $saved;
    }
    return $map[$url] ?? $url;
}

/** Локализуем фото и ссылки на их оригиналы; текст, документы и записи CMS сохраняются. */
function at_source_media_html(string $html): string
{
    return (string) preg_replace_callback('~(<(?:img\b[^>]*?\s+src|a\b[^>]*?\s+href)\s*=\s*)([\'\"])(.*?)\2~is', static function (array $m): string {
        $url = html_entity_decode($m[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $resolved = at_source_media_resolve($url);
        return $resolved === $url ? $m[0] : $m[1] . $m[2] . htmlspecialchars($resolved, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $m[2];
    }, $html);
}

function at_source_media_status(PDO $pdo, array $manifest): array
{
    $urls = at_source_media_urls($manifest);
    $map = at_source_media_local_map() + at_source_media_saved_map($pdo);
    $ready = count(array_intersect($urls, array_keys($map)));
    return ['total' => count($urls), 'ready' => $ready, 'pending' => count($urls) - $ready];
}

function at_source_media_public_ipv4(string $ip): bool
{
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    // PHP's reserved-range filter differs by version; explicitly exclude shared,
    // benchmark and multicast networks as well as non-routable zero addresses.
    $bytes = array_map('intval', explode('.', $ip));
    return $bytes[0] !== 0 && $bytes[0] < 224
        && !($bytes[0] === 100 && $bytes[1] >= 64 && $bytes[1] <= 127)
        && !($bytes[0] === 198 && in_array($bytes[1], [18, 19], true));
}

/** Проверенный HTTPS-источник, закреплённый публичный IP, без редиректов и отключения TLS. */
function at_source_media_download(string $url): string
{
    $host = at_source_media_host($url);
    if ($host === null) throw new RuntimeException('Недопустимый адрес исходной фотографии.');
    if (!function_exists('curl_init')) throw new RuntimeException('На сервере недоступна загрузка по HTTPS.');
    $addresses = gethostbynamel($host) ?: [];
    $ip = '';
    foreach ($addresses as $address) {
        if (at_source_media_public_ipv4($address)) { $ip = $address; break; }
    }
    if ($ip === '') throw new RuntimeException('Источник фотографий временно недоступен через DNS. Уже сохранённые фотографии не затронуты.');
    $body = ''; $overflow = false;
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_RESOLVE => [$host . ':443:' . $ip], CURLOPT_PROXY => '',
        CURLOPT_USERAGENT => 'A-tour source photo archive', CURLOPT_HTTPHEADER => ['Accept: image/*'],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$overflow): int {
            if (strlen($body) + strlen($chunk) > AT_SOURCE_MEDIA_MAX_BYTES) { $overflow = true; return 0; }
            $body .= $chunk; return strlen($chunk);
        },
    ]);
    $ok = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($overflow) throw new RuntimeException('Фотография больше 15 МБ.');
    if ($ok === false || $status !== 200) throw new RuntimeException('Не удалось загрузить исходную фотографию (HTTP ' . $status . ').');
    return $body;
}

/** Небольшие возобновляемые пакеты. Никаких перезаписей туров: сохраняются только проверенные байты и URL-карта. */
function at_source_media_batch(PDO $pdo, array $manifest, int $offset = 0, int $limit = 2, ?callable $download = null): array
{
    $urls = at_source_media_urls($manifest);
    $offset = max(0, min($offset, count($urls)));
    $limit = max(1, min($limit, 2));
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $lockName = $mysql ? at_source_media_lock_name((string) $pdo->query('SELECT DATABASE()')->fetchColumn()) : '';
    if ($mysql) {
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 1)');
        $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) throw new RuntimeException('Фотографии уже сохраняются. Дождитесь завершения пакета.');
    }
    $download ??= 'at_source_media_download';
    $saved = 0; $errors = [];
    try {
        $map = at_source_media_saved_map($pdo);
        $local = at_source_media_local_map();
        $attempted = 0;
        while ($offset < count($urls) && $attempted < $limit) {
            $url = $urls[$offset++];
            if (isset($local[$url]) || isset($map[$url])) continue;
            $attempted++;
            try {
                $body = $download($url);
                if (!is_string($body) || $body === '' || strlen($body) > AT_SOURCE_MEDIA_MAX_BYTES) throw new RuntimeException('Некорректный размер исходной фотографии.');
                $info = @getimagesizefromstring($body);
                if (!$info || !in_array($info['mime'] ?? '', ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'], true)) throw new RuntimeException('Источник вернул не фотографию.');
                $pdo->beginTransaction();
                $st = $pdo->prepare('INSERT INTO media (mime, name, size, data) VALUES (?, ?, ?, ?)');
                $st->bindValue(1, $info['mime']); $st->bindValue(2, 'source-' . hash('sha256', $url));
                $st->bindValue(3, strlen($body), PDO::PARAM_INT); $st->bindValue(4, $body, PDO::PARAM_LOB); $st->execute();
                $next = $map + [$url => '/media.php?id=' . (int) $pdo->lastInsertId()];
                $pdo->prepare('DELETE FROM atour_settings WHERE k = ?')->execute([AT_SOURCE_MEDIA_SETTING]);
                $pdo->prepare('INSERT INTO atour_settings (k, v) VALUES (?, ?)')->execute([AT_SOURCE_MEDIA_SETTING, json_encode($next, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
                $pdo->commit(); $map = $next; $saved++;
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = ['url' => $url, 'message' => $error->getMessage()];
            }
        }
        return at_source_media_status($pdo, $manifest) + ['saved' => $saved, 'nextOffset' => $offset, 'finished' => $offset >= count($urls), 'errors' => $errors];
    } finally {
        if ($mysql) {
            $release = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $release->execute([$lockName]);
        }
    }
}
