<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/header-settings.php';

function adm_header_row(string $index, array $item, string $group = 'header'): string
{
    $prefix = $group . '[links][' . $index . ']';
    return '<div class="rep-row header-menu-row"><div class="grid2">'
        . adm_input($prefix . '[label]', is_string($item['label'] ?? null) ? $item['label'] : '', 'Название пункта', ['maxlength'=>80])
        . adm_input($prefix . '[href]', is_string($item['href'] ?? null) ? $item['href'] : '', 'Ссылка', ['maxlength'=>500, 'placeholder'=>'/tour/filter/lager/russia/']) . '</div>'
        . '<div class="row">' . adm_checkbox($prefix . '[visible]', !empty($item['visible']), 'Показывать на сайте')
        . '<div class="right rep-ctl"><button type="button" data-up aria-label="Переместить пункт выше">↑ Выше</button> <button type="button" data-down aria-label="Переместить пункт ниже">↓ Ниже</button> <button type="button" data-del class="danger">Удалить</button></div></div></div>';
}

function adm_header_menu(string $group, array $links, string $title, string $hint): string
{
    $html = '<div class="card" id="' . ($group === 'header' ? 'header-info-menu' : 'header-main-menu') . '" data-repeater data-next="' . count($links) . '"><h2>' . adm_e($title) . '</h2><p class="muted">' . adm_e($hint) . '</p><div class="rep-list">';
    foreach ($links as $index => $item) $html .= adm_header_row((string) $index, $item, $group);
    return $html . '</div><template>' . adm_header_row('__i__', ['visible'=>true], $group) . '</template><button class="btn g" type="button" data-add>+ Добавить пункт</button></div>';
}

function adm_tab_header(): string
{
    $config = at_header_config(); $main = at_main_header_config(); $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        try {
            $config = at_header_prepare(is_array($_POST['header'] ?? null) ? $_POST['header'] : []);
            // Старое открытое окно верхнего меню не стирает новые настройки основного.
            if (array_key_exists('main_header', $_POST)) $main = at_main_header_prepare(is_array($_POST['main_header']) ? $_POST['main_header'] : []);
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage();
            $posted = is_array($_POST['header'] ?? null) ? $_POST['header'] : [];
            $config['tagline'] = is_string($posted['tagline'] ?? null) ? $posted['tagline'] : '';
            $config['enabled'] = !empty($posted['enabled']);
            if (is_array($posted['links'] ?? null)) $config['links'] = array_values(array_filter(array_slice($posted['links'], 0, 20), 'is_array'));
            $postedMain = is_array($_POST['main_header'] ?? null) ? $_POST['main_header'] : [];
            if (is_array($postedMain['links'] ?? null)) $main['links'] = array_values(array_filter(array_slice($postedMain['links'], 0, 20), 'is_array'));
            if (is_array($postedMain['button'] ?? null)) $main['button'] = array_replace($main['button'], array_filter($postedMain['button'], 'is_string'));
            $main['button']['visible'] = !empty($postedMain['button']['visible']);
        }
        if ($error === '') {
            $pdo = db(); $pdo->beginTransaction();
            try {
                at_set_setting('header.enabled', $config['enabled'] ? '1' : '0');
                at_set_setting('header.tagline', $config['tagline']);
                at_set_setting('header.links', (string) json_encode($config['links'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                at_set_setting('header.mainLinks', (string) json_encode($main['links'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                at_set_setting('header.requestLabel', $main['button']['label']);
                at_set_setting('header.requestHref', $main['button']['href']);
                at_set_setting('header.requestVisible', $main['button']['visible'] ? '1' : '0');
                $pdo->commit();
            } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
            adm_redirect('header', ['ok'=>1, 'msg'=>'Шапка и меню сохранены.']);
        }
    }
    $html = ($error !== '' ? '<div class="alert err">' . adm_e($error) . '</div>' : '') . adm_form_open('save')
        . adm_header_menu('main_header', $main['links'], 'Основное меню рядом с логотипом', 'Лагеря России, лагеря за границей, зимние туры и другие пункты. Меняйте названия, ссылки и порядок; галочка управляет видимостью.');
    $html .= '<div class="card"><h2>Кнопка заявки в шапке</h2>'
        . adm_checkbox('main_header[button][visible]', $main['button']['visible'], 'Показывать кнопку')
        . '<div class="grid2">' . adm_input('main_header[button][label]', $main['button']['label'], 'Текст кнопки', ['maxlength'=>80])
        . adm_input('main_header[button][href]', $main['button']['href'], 'Ссылка кнопки', ['maxlength'=>500, 'placeholder'=>'Пусто — открыть форму заявки']) . '</div>'
        . '<p class="muted">Оставьте ссылку пустой, чтобы открывалась обычная форма заявки. Настройки действуют и на компьютере, и на телефоне.</p>'
        . '<p><a href="' . adm_e(adm_url('settings')) . '">Изменить логотип и телефон в настройках сайта →</a></p></div>'
        . '<div class="card"><h2>Верхняя информационная строка</h2><p class="muted">Подпись слева и ссылки над основным меню. Изменения появятся на всех страницах сайта.</p>'
        . adm_checkbox('header[enabled]', $config['enabled'], 'Показывать верхнюю строку')
        . adm_input('header[tagline]', $config['tagline'], 'Подпись слева', ['maxlength'=>200], 'Оставьте поле пустым, чтобы убрать подпись.') . '</div>'
        . adm_header_menu('header', $config['links'], 'Информационные ссылки', 'Родителям, агентствам, оплата и контакты. Эти пункты находятся над основным меню.');
    return $html . '<div class="row"><button class="btn" type="submit">Сохранить шапку и меню</button><a class="btn g" href="/" target="_blank" rel="noopener">Посмотреть сайт ↗</a></div></form>';
}
