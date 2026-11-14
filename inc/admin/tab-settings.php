<?php
/** Настройки сайта: контакты, реквизиты, соцсети, SEO главной, оплата. */
declare(strict_types=1);

function adm_tab_settings(): string
{
    $groups = [
        'Контакты' => [
            ['contacts.phone1', 'Телефон 1'], ['contacts.phone2', 'Телефон 2'], ['contacts.email', 'E-mail'], ['contacts.addressShort', 'Адрес (кратко, шапка)'],
            ['contacts.address', 'Адрес (полный)'], ['contacts.hours', 'Режим работы'], ['contacts.mapLat', 'Широта (карта)'], ['contacts.mapLng', 'Долгота (карта)'], ['contacts.yandexOrgId', 'Организация в Яндекс Картах', 'Число из адреса карточки Яндекса; используется вместо координат'],
        ],
        'Реквизиты' => [
            ['site.name', 'Название'], ['site.legalName', 'Юридическое лицо'], ['contacts.inn', 'ИНН'], ['contacts.ogrn', 'ОГРН'],
            ['contacts.reestr', 'Номер в реестре туроператоров'], ['contacts.reestrUrl', 'Ссылка на реестр'], ['copyright', 'Копирайт', '{year} подставит текущий год'],
        ],
        'Соцсети' => [['social.vk', 'ВКонтакте'], ['social.telegram', 'Telegram']],
        'Главная страница и SEO' => [
            ['home.seoTitle', 'Title главной'], ['home.metaDescription', 'Description главной', '', 'textarea'], ['home.metaKeywords', 'Keywords главной'],
            ['home.h1', 'Заголовок первого экрана'], ['home.badge', 'Плашка над заголовком'], ['home.lead', 'Текст под заголовком', '', 'textarea'],
            ['site.footerLogo', 'Логотип в подвале', 'Пусто — основной фирменный логотип'],
            ['site.logo', 'Логотип', 'Путь к изображению; пусто — фирменный логотип А-тур'],
            ['home.stats', 'Цифры первого экрана', 'JSON: [{"value":"30+","label":"лет с детьми"}]; пусто — стандартные цифры А-тур', 'textarea'],
            ['home.ticketTitle', 'Заголовок баннера трансфера'], ['home.ticketText', 'Текст баннера «Авиа и ж/д»'], ['reviews.intro', 'Подпись к блоку отзывов', '', 'textarea'],
            ['reviews.hideGoogle', 'Отзывы Google', '', 'select', ['0' => 'Показывать', '1' => 'Скрывать']],
        ],
        'Оплата' => [['payment.gateway_url', 'Адрес платёжного шлюза', 'Пусто — посетитель видит «заявка принята», ссылку на оплату присылает менеджер']],
        'Каталог и фильтры' => [
            ['catalog.year', 'Год каталога', 'Меняется вручную после обновления цен. Подставляется вместо {year}; годы в самих турах сохраняются.', 'number', '2026', 2000, 2099],
            ['catalog.ageMin', 'Минимальный возраст в фильтре', 'По умолчанию 6 лет', 'number', '6'],
            ['catalog.ageMax', 'Максимальный возраст в фильтре', 'По умолчанию 18 лет', 'number', '18'],
        ],
        'Оформление' => [
            ['design.style', 'Стиль по умолчанию', 'Посетитель может переключить стиль в шапке сайта, его выбор запоминается в браузере', 'select', ['bright' => 'Яркий', 'simple' => 'Строгий']],
            ['design.theme', 'Тема по умолчанию', 'Тёмная тема есть только у строгого стиля', 'select', ['light' => 'Светлая', 'dark' => 'Тёмная']],
        ],
    ];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        foreach ($groups as $fields) {
            foreach ($fields as $f) {
                $key = $f[0];
                if ($key === 'catalog.year') {
                    $year = (string) ($_POST['s'][$key] ?? at_catalog_year());
                    if (preg_match('~^20\d{2}$~D', $year)) at_set_setting($key, $year);
                    continue;
                }
                if ($key === 'catalog.ageMin' || $key === 'catalog.ageMax') {
                    $minAge = max(1, min(25, (int) ($_POST['s']['catalog.ageMin'] ?? 6)));
                    $maxAge = max($minAge, min(25, (int) ($_POST['s']['catalog.ageMax'] ?? 18)));
                    at_set_setting($key, (string) ($key === 'catalog.ageMin' ? $minAge : $maxAge));
                    continue;
                }
                at_set_setting($key, mb_substr(trim((string) ($_POST['s'][$key] ?? '')), 0, 2000));
            }
        }
        adm_redirect('settings', ['ok' => 1, 'msg' => 'Настройки сохранены.']);
    }
    $s = at_settings(true);
    $h = adm_form_open('save') . '<div class="grid2">';
    foreach ($groups as $title => $fields) {
        $h .= '<div class="card"><h2>' . adm_e($title) . '</h2>';
        foreach ($fields as $f) {
            [$key, $label] = $f;
            $hint = $f[2] ?? '';
            $h .= match ($f[3] ?? '') {
                'number' => adm_input('s[' . $key . ']', ($s[$key] ?? '') !== '' ? $s[$key] : ($f[4] ?? ''), $label, ['type' => 'number', 'min' => $f[5] ?? 1, 'max' => $f[6] ?? 25], $hint),
                'textarea' => adm_textarea('s[' . $key . ']', $s[$key] ?? '', $label, ['rows' => 2], $hint),
                'select'   => adm_select('s[' . $key . ']', $s[$key] ?? '', $label, (array) ($f[4] ?? []), $hint),
                default    => adm_input('s[' . $key . ']', $s[$key] ?? '', $label, [], $hint),
            };
        }
        $h .= '</div>';
    }
    return $h . '</div><div class="row"><button class="btn">Сохранить настройки</button></div></form>'
        . '<p class="muted">Название сайта в заголовках страниц, SEO-теги отдельных страниц и favicon — в <a href="/adm_panel001.php?tab=seo" target="_blank">панели платформы</a>.</p>';
}
