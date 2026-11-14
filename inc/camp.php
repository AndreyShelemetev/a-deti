<?php
/** Компоненты страницы лагеря. Тексты, фотографии и характеристики хранятся в CMS. */
declare(strict_types=1);
require_once __DIR__ . "/camp-sections.php";
require_once __DIR__ . "/tour-documents.php";
require_once __DIR__ . "/tour-transfer.php";
require_once __DIR__ . "/tour-tables.php";

function at_camp_dom(string $html): DOMDocument
{
    $previous = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="camp-content">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $doc;
}

/** Вспомогательные разделы сохраняют собственную вёрстку; текстовые получают один снимок. */
function at_tour_illustrated_section(string $key): bool
{
    return !str_ends_with($key, '_details') && !in_array($key, ['camp_facts', 'reviews', 'documents', 'docs', 'for_parents', 'for_contraindications', 'payment_smeny', 'transfer', 'otpravka_children'], true);
}

/** The source sometimes appends a map control's caption to the plain address. */
function at_camp_location(array $tour): string
{
    $location = trim((string) ($tour['address'] ?? '')) ?: trim((string) ($tour['region']['name'] ?? ''));
    return trim((string) preg_replace('~\s*(?:показать|посмотреть)\s+на\s+карте\s*~iu', ' ', $location), " \t\n\r,.");
}

function at_camp_facts(array $tour): array
{
    foreach ($tour['sections'] ?? [] as $section) {
        if (($section['skey'] ?? '') !== 'camp_facts') { continue; }
        $doc = at_camp_dom((string) $section['html']);
        $facts = [];
        foreach ($doc->getElementsByTagName('tr') as $row) {
            $cells = (new DOMXPath($doc))->query('./th|./td', $row);
            if ($cells->length < 2) { continue; }
            $label = trim($cells->item(0)->textContent);
            $value = trim($cells->item(1)->textContent);
            if ($label !== '' && $value !== '') { $facts[] = ['label' => $label, 'value' => $value]; }
        }
        $facts = array_slice($facts, 0, 4);
        if (!$facts) return [];
        $location = at_camp_location($tour);
        if ($location !== '') {
            // Preserve custom facts while giving the full location one consistent place.
            $facts = array_values(array_filter($facts, static fn(array $fact): bool => !preg_match('~^(?:регион|адрес|расположение|местоположение|местонахождение)\s*:?$~iu', $fact['label'])));
            array_splice($facts, min(2, count($facts)), 0, [['label' => 'Расположение', 'value' => $location]]);
        }
        return $facts;
    }
    return [];
}

/** У раздела одна иллюстрация справа. Остальные загруженные фото остаются в общей галерее. */
function at_camp_section(string $html, array $photoIndexes = []): array
{
    $doc = at_camp_dom($html);
    $root = $doc->getElementById('camp-content');
    $photo = null;
    $photoHidden = false;
    $photoTables = at_camp_photo_tables($doc);
    foreach (iterator_to_array((new DOMXPath($doc))->query('//*[contains(concat(" ", normalize-space(@class), " "), " tour-no-section-photo ")]')) as $marker) {
        $photoHidden = true;
        $marker->parentNode?->removeChild($marker);
    }
    foreach (iterator_to_array($doc->getElementsByTagName('img')) as $img) {
        $src = trim($img->getAttribute('src'));
        if ($photo === null && preg_match('~^(?:/(?!/)|https?://)~i', $src)) {
            $photo = ['src' => $src, 'alt' => $img->getAttribute('alt')];
            // Только явный процент из редактора: произвольный CSS в атрибуты страницы не переносим.
            if (preg_match('~(?:^|;)\s*width\s*:\s*(\d+(?:\.\d+)?)%\s*(?:;|$)~i', $img->getAttribute('style'), $size)) {
                $photo['width'] = max(10, min(100, (int) round((float) $size[1])));
            }
        }
        $parent = $img->parentNode;
        $parent->removeChild($img);
        if ($parent instanceof DOMElement && $parent->tagName === 'figure' && trim($parent->textContent) === '') {
            $parent->parentNode?->removeChild($parent);
        }
    }
    foreach ($photoTables as $group) {
        $links = $doc->createElement('div');
        $links->setAttribute('class', 'camp-photo-links');
        foreach ($group['pairs'] as $pair) {
            $link = $doc->createElement('a');
            $src = function_exists('at_img_src') ? at_img_src($pair['src']) : $pair['src'];
            $link->setAttribute('href', $src);
            if (isset($photoIndexes[$src])) $link->setAttribute('data-design-photo', (string)$photoIndexes[$src]);
            else { $link->setAttribute('target', '_blank'); $link->setAttribute('rel', 'noopener'); }
            $link->appendChild($doc->createTextNode($pair['label']));
            $hint = $doc->createElement('span', 'Смотреть фото');
            $link->appendChild($hint); $links->appendChild($link);
        }
        $group['table']->parentNode->replaceChild($links, $group['table']);
    }
    if ($photoTables) {
        // Old photo captions used long runs of non-breaking spaces as manual columns.
        foreach ((new DOMXPath($doc))->query('//text()[not(ancestor::table)]') as $text) {
            $text->nodeValue = (string)preg_replace('~(?:[ \t]*\x{00a0}[ \t]*){3,}~u', ' ', $text->nodeValue);
        }
    }
    at_tour_table_scroll($doc);
    $body = '';
    foreach ($root?->childNodes ?? [] as $node) { $body .= $doc->saveHTML($node); }
    return ['html' => $body, 'photo' => $photo, 'photoHidden' => $photoHidden && $photo === null];
}

function at_camp_season_label(array $tour, ?string $customLabel = null): string
{
    // Только названия опубликованных сезонов. Год, даты и число смен остаются в CMS и календаре.
    $labels = [$customLabel, (string) ($tour['season_text'] ?? '')];
    foreach (['seasons_list', 'seasons'] as $key) {
        $seasons = $tour[$key] ?? [];
        if (!is_array($seasons)) $seasons = at_split((string) $seasons);
        $labels[] = implode(', ', array_map(static fn(string $season): string => ATOUR_SEASONS[$season] ?? $season, $seasons));
    }
    foreach ($labels as $label) {
        preg_match_all('~(?<!\p{L})(?:зима|весна|лето|осень)(?!\p{L})~iu', (string) $label, $matches);
        if ($matches[0]) {
            $names = array_map(static fn(string $season): string => mb_convert_case($season, MB_CASE_TITLE, 'UTF-8'), $matches[0]);
            return implode(', ', array_unique($names));
        }
    }
    return '';
}

function at_camp_transfer_note(array $tour): string
{
    foreach ($tour['sections'] ?? [] as $section) {
        if (!in_array($section['skey'] ?? '', ['transfer', 'otpravka_children'], true)) { continue; }
        $doc = at_camp_dom((string) $section['html']);
        $xp = new DOMXPath($doc);
        $value = static fn(string $class): string => trim((string) $xp->evaluate('string(//*[contains(concat(" ",normalize-space(@class)," ")," ' . $class . ' ")])'));
        $price = $value('transfer__price');
        $route = $value('transfer__route');
        if ($price !== '' && $route !== '') { return $route . ': ' . $price; }
    }
    return (string) ($tour['price_note'] ?? '');
}

/** Однократный перенос предоставленного макета в обычные редактируемые записи CMS. */
function at_camp_import_layout(PDO $pdo, string $file): void
{
    $layout = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    $find = $pdo->prepare('SELECT * FROM tours WHERE slug = ?');
    $find->execute([$layout['slug']]);
    $tour = $find->fetch(PDO::FETCH_ASSOC);
    if (!$tour) { return; }
    $id = (int) $tour['id'];
    $marker = 'camp.layout.' . $id;
    $pdo->beginTransaction();
    try {
        $lock = $pdo->query("SELECT v FROM atour_settings WHERE k = 'schema_version'" . ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
        $lock->fetchColumn();
        $exists = $pdo->prepare('SELECT v FROM atour_settings WHERE k = ?');
        $exists->execute([$marker]);
        if ($exists->fetchColumn() !== false) { $pdo->commit(); return; }
        $query = $pdo->prepare('SELECT * FROM tour_sections WHERE tour_id = ? ORDER BY sort_order, id');
        $query->execute([$id]); $oldSections = $query->fetchAll(PDO::FETCH_ASSOC);
        $query = $pdo->prepare('SELECT * FROM tour_images WHERE tour_id = ? ORDER BY sort_order, id');
        $query->execute([$id]); $oldImages = $query->fetchAll(PDO::FETCH_ASSOC);
        $setting = $pdo->prepare('INSERT INTO atour_settings (k,v) VALUES (?,?)');
        $setting->execute(['camp.original.' . $id, json_encode(['cover' => $tour['cover'], 'sectionKeys' => array_column($oldSections, 'skey'), 'images' => $oldImages], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
        $oldByKey = array_column($oldSections, null, 'skey');
        $save = static function (string $key, string $title, string $html, int $order) use ($pdo, $id): void {
            $q = $pdo->prepare('SELECT id FROM tour_sections WHERE tour_id = ? AND skey = ?'); $q->execute([$id, $key]);
            if ($q->fetchColumn() !== false) {
                $pdo->prepare('UPDATE tour_sections SET title=?, html=?, sort_order=? WHERE tour_id=? AND skey=?')->execute([$title, $html, $order, $id, $key]);
            } else {
                $pdo->prepare('INSERT INTO tour_sections (tour_id,skey,title,html,sort_order) VALUES (?,?,?,?,?)')->execute([$id, $key, $title, $html, $order]);
            }
        };
        $keys = array_column($layout['sections'], 'key');
        foreach ($oldSections as $old) {
            if (!in_array($old['skey'], $keys, true)) {
                $pdo->prepare('UPDATE tour_sections SET sort_order=? WHERE id=?')->execute([400 + (int) $old['sort_order'], $old['id']]);
            }
        }
        foreach ($layout['sections'] as $section) {
            $key = $section['key'];
            if (isset($oldByKey[$key]) && trim((string) $oldByKey[$key]['html']) !== '') {
                $old = $oldByKey[$key];
                $save($key . '_details', 'Дополнительная информация: ' . $old['title'], (string) $old['html'], 700 + (int) $section['sortOrder']);
            }
            $save($key, $section['title'], $section['html'], (int) $section['sortOrder']);
        }
        // Исходные фото сохраняются. Галерея макета идёт первой, без подмены счётчика.
        $pdo->prepare('UPDATE tour_images SET sort_order = sort_order + 1000 WHERE tour_id = ?')->execute([$id]);
        $known = array_column($oldImages, 'url');
        $addImage = $pdo->prepare('INSERT INTO tour_images (tour_id,url,alt,sort_order) VALUES (?,?,?,?)');
        foreach ($layout['images'] as $i => $url) {
            if (!in_array($url, $known, true)) { $addImage->execute([$id, $url, $tour['title'], $i]); $known[] = $url; }
        }
        if ($tour['cover'] && !in_array($tour['cover'], $known, true)) { $addImage->execute([$id, $tour['cover'], $tour['title'], 2000]); }
        $pdo->prepare('UPDATE tours SET cover = ? WHERE id = ?')->execute([$layout['cover'], $id]);
        $setting->execute([$marker, '1']);
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}
