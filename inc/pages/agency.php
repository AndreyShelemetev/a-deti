<?php
/** Агентствам /agentstvam/: условия, форма доступа к агентской базе. */
declare(strict_types=1);

$page = at_page('agentstvam');
if ($page === null) {
    require __DIR__ . '/notfound.php';
    return;
}
at_page_start(['key' => 'page-agentstvam', 'title' => $page['seo_title'] ?: $page['title'], 'description' => (string) $page['meta_description'], 'keywords' => (string) $page['meta_keywords'], 'canonical' => '/agentstvam/']);
$perks = [
    ['percent', 'Агентская комиссия', 'Прозрачные условия и выплаты по каждому забронированному туру.'],
    ['file-check', 'Полный пакет документов', 'Договоры, памятки, списки документов для родителей — всё готово.'],
    ['headset', 'Поддержка менеджера', 'Ответы по наличию мест и условиям в течение рабочего дня.'],
    ['handshake', 'Прямые контракты', 'Работаем напрямую с лагерями и центрами, в том числе с ВДЦ «Орлёнок».'],
];
at_page_shell_open('page-agentstvam', $page['title'], [['name' => $page['title']]]);
if (trim((string) $page['html']) !== '') {
    echo '<article class="panel panel-lg">' . at_rich((string) $page['html']) . '</article>';
}
echo '<div class="grid-2 mt-6">';
foreach ($perks as [$icon, $t, $text]) {
    echo '<div class="panel perk"><div class="perk-icon">' . at_icon($icon) . '</div><div><div class="perk-title">' . at_e($t) . '</div><p class="muted">' . at_e($text) . '</p></div></div>';
}
echo '</div><section class="panel panel-lg mt-8" id="access"><h2>Доступ к агентской базе</h2><p class="muted mb-6">Для туристических агентств. Заполните форму — откроем личный кабинет и пришлём условия сотрудничества.</p>' . at_form_agency() . '</section>';
at_page_shell_close();
at_page_end();
