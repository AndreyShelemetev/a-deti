<?php
/** Верхняя строка шапки: подпись и информационные ссылки. */
declare(strict_types=1);

function at_header_defaults(): array
{
    return ['tagline'=>'Детский отдых с 1996 года', 'enabled'=>true, 'links'=>[
        ['label'=>'Родителям', 'href'=>'/rodytelyam/', 'visible'=>true],
        ['label'=>'Агентствам', 'href'=>'/agentstvam/', 'visible'=>true],
        ['label'=>'Оплата', 'href'=>'/pay/', 'visible'=>true],
        ['label'=>'Контакты', 'href'=>'/kak-nas-nayti/', 'visible'=>true],
    ]];
}

function at_header_href(string $href): bool
{
    if (preg_match('~[\x00-\x20\x7f<>"\'\\\\]~u', $href)) return false;
    if (preg_match('~^/(?!/)|^#[a-z0-9_-]*$~iD', $href)) return true;
    return preg_match('~^https?://~i', $href) === 1 && filter_var($href, FILTER_VALIDATE_URL) !== false;
}

function at_header_config(?array $settings = null): array
{
    $settings ??= at_settings();
    $result = at_header_defaults();
    if (array_key_exists('header.tagline', $settings)) $result['tagline'] = (string) $settings['header.tagline'];
    $result['enabled'] = ($settings['header.enabled'] ?? '1') !== '0';
    if (isset($settings['header.links'])) {
        $links = json_decode((string) $settings['header.links'], true);
        if (is_array($links) && array_is_list($links)) {
            $result['links'] = [];
            foreach (array_slice($links, 0, 20) as $row) {
                if (!is_array($row) || !is_string($row['label'] ?? null) || !is_string($row['href'] ?? null)) continue;
                if (trim($row['label']) === '' || !at_header_href($row['href'])) continue;
                $result['links'][] = ['label'=>$row['label'], 'href'=>$row['href'], 'visible'=>!empty($row['visible'])];
            }
        }
    }
    return $result;
}

/** Проверяем весь набор до записи: ошибка не стирает существующее меню. */
function at_header_prepare(array $input): array
{
    if (!array_key_exists('tagline', $input)) throw new InvalidArgumentException('Данные верхнего меню не переданы.');
    if (!is_string($input['tagline'])) throw new InvalidArgumentException('Подпись должна быть текстом.');
    $tagline = is_string($input['tagline'] ?? null) ? trim($input['tagline']) : '';
    if (mb_strlen($tagline) > 200) throw new InvalidArgumentException('Подпись должна быть не длиннее 200 символов.');
    $rows = $input['links'] ?? [];
    if (!is_array($rows) || count($rows) > 20) throw new InvalidArgumentException('В верхнем меню можно добавить до 20 ссылок.');
    $links = [];
    foreach ($rows as $row) {
        if (!is_array($row)) throw new InvalidArgumentException('Проверьте пункты меню.');
        $label = is_string($row['label'] ?? null) ? trim($row['label']) : '';
        $href = is_string($row['href'] ?? null) ? trim($row['href']) : '';
        if ($label === '' && $href === '') continue;
        if ($label === '' || mb_strlen($label) > 80) throw new InvalidArgumentException('Укажите название каждого пункта: до 80 символов.');
        if (strlen($href) > 500 || !at_header_href($href)) throw new InvalidArgumentException('Укажите ссылку вида /rodytelyam/ или https://example.ru.');
        $links[] = ['label'=>$label, 'href'=>$href, 'visible'=>!empty($row['visible'])];
    }
    return ['enabled'=>!empty($input['enabled']), 'tagline'=>$tagline, 'links'=>$links];
}

/** Основное меню рядом с логотипом; старый вид — до первого сохранения. */
function at_main_header_defaults(): array
{
    if (!function_exists('at_main_nav') && !defined('AT_MAIN_NAV')) require_once __DIR__ . '/layout.php';
    $items = function_exists('at_main_nav') ? at_main_nav() : AT_MAIN_NAV;
    $winter = '/tour/filter/winter-2026-2027/';
    if (is_file(__DIR__ . '/content/winter-2027.json') && !in_array($winter, array_column($items, 'href'), true)) {
        array_splice($items, 1, 0, [['label'=>'Зима 2026–2027','href'=>$winter]]);
    }
    return ['links'=>array_map(static fn(array $item): array => $item + ['visible'=>true], $items),
        'button'=>['visible'=>true, 'label'=>'Отправить заявку', 'href'=>'']];
}

function at_main_header_config(?array $settings = null): array
{
    $settings ??= at_settings(); $config = at_main_header_defaults();
    if (isset($settings['header.mainLinks'])) {
        $rows = json_decode((string) $settings['header.mainLinks'], true);
        if (is_array($rows) && array_is_list($rows)) {
            $config['links'] = [];
            foreach (array_slice($rows, 0, 20) as $row) {
                if (!is_array($row) || !is_string($row['label'] ?? null) || !is_string($row['href'] ?? null)
                    || trim($row['label']) === '' || !at_header_href($row['href'])) continue;
                $config['links'][] = ['label'=>$row['label'], 'href'=>$row['href'], 'visible'=>!empty($row['visible'])];
            }
        }
    }
    $config['button']['visible'] = ($settings['header.requestVisible'] ?? '1') !== '0';
    if (isset($settings['header.requestLabel']) && trim((string) $settings['header.requestLabel']) !== '') $config['button']['label'] = (string) $settings['header.requestLabel'];
    $href = (string) ($settings['header.requestHref'] ?? '');
    $config['button']['href'] = $href !== '' && at_header_href($href) ? $href : '';
    return $config;
}

function at_main_header_prepare(array $input): array
{
    // Тот же белый список ссылок и ограничение длины, что в верхней строке.
    $links = at_header_prepare(['tagline'=>'', 'links'=>$input['links'] ?? []])['links'];
    $button = $input['button'] ?? [];
    if (!is_array($button)) throw new InvalidArgumentException('Проверьте настройки кнопки заявки.');
    $label = is_string($button['label'] ?? null) ? trim($button['label']) : '';
    $href = is_string($button['href'] ?? null) ? trim($button['href']) : '';
    if ($label === '' || mb_strlen($label) > 80) throw new InvalidArgumentException('Укажите текст кнопки заявки: до 80 символов.');
    if (strlen($href) > 500 || ($href !== '' && !at_header_href($href))) throw new InvalidArgumentException('Укажите безопасную ссылку кнопки или оставьте поле пустым для формы заявки.');
    return ['links'=>$links, 'button'=>['visible'=>!empty($button['visible']), 'label'=>$label, 'href'=>$href]];
}

function at_header_request_button(string $class, ?array $config = null): string
{
    $button = ($config ?? at_main_header_config())['button'];
    if (!$button['visible']) return '';
    $label = at_e($button['label']); $class = at_e($class);
    return $button['href'] !== '' ? '<a class="' . $class . '" href="' . at_e($button['href']) . '">' . $label . '</a>'
        : '<button class="' . $class . '" type="button" data-modal="request">' . $label . '</button>';
}
