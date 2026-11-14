<?php
/** Контакты /kak-nas-nayti/: карта, реквизиты, форма. */
declare(strict_types=1);

$s = at_settings();
$page = at_page('kak-nas-nayti');
$title = $page['title'] ?? 'Как нас найти';
at_page_start(['key' => 'contacts', 'title' => ($page['seo_title'] ?? '') ?: $title, 'description' => (string) ($page['meta_description'] ?? 'Контакты туроператора А-тур: адрес офиса в Москве, телефоны, режим работы, реквизиты.'), 'canonical' => '/kak-nas-nayti/']);
$rows = [
    ['Полное наименование', 'Общество с ограниченной ответственностью «А-тур»'],
    ['Сокращённое наименование', $s['site.legalName'] ?? ''],
    ['ИНН', $s['contacts.inn'] ?? ''],
    ['ОГРН', $s['contacts.ogrn'] ?? ''],
    ['Реестровый номер туроператора', $s['contacts.reestr'] ?? ''],
    ['Адрес', $s['contacts.address'] ?? ''],
    ['Режим работы', $s['contacts.hours'] ?? ''],
];
at_page_shell_open('contacts', $title, [['name' => 'Контакты']], '', false, true);
echo at_map_block(false);
echo '<div class="grid-2 contacts-grid"><div class="panel panel-lg"><h2 class="h2-icon">' . at_icon('building', 'ic brand') . ' Реквизиты</h2><dl class="req-list">';
foreach ($rows as [$k, $v]) {
    echo '<div><dt>' . at_e($k) . '</dt><dd>' . ($k === 'Режим работы' ? at_hours_html((string) $v) : at_e($v)) . '</dd></div>';
}
echo '</dl><a href="' . at_e($s['contacts.reestrUrl'] ?? '#') . '" target="_blank" rel="noopener" class="link-mint">' . at_icon('shield-check') . ' Проверить в едином федеральном реестре туроператоров</a>';
if (!empty($page['html'])) {
    echo '<div class="mt-6">' . at_rich((string) $page['html']) . '</div>';
}
echo '</div><div class="panel panel-lg"><h2>Напишите нам</h2><p class="muted mb-5">Ответим в течение рабочего дня.</p>' . at_form_request('/kak-nas-nayti/', true) . '</div></div>';
at_page_shell_close(false);
at_page_end();
