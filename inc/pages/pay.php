<?php
/** Онлайн-оплата /pay/ и /pay/cashback/: форма по номеру договора → заявка на оплату. */
declare(strict_types=1);

/** @var bool $cashback */
$errors = [];
$old = ['amount' => (string) ((int) ($_GET['amount'] ?? 0) ?: '')];
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $res = at_handle_payment($cashback);
    $errors = $res['errors'];
    $old = $res['old'];
}
$slug = $cashback ? 'pay-cashback' : 'pay';
$page = at_page($slug);
$title = $page['title'] ?? 'Онлайн оплата туров';
at_page_start(['key' => $slug, 'title' => ($page['seo_title'] ?? '') ?: $title, 'description' => (string) ($page['meta_description'] ?? ''), 'keywords' => (string) ($page['meta_keywords'] ?? ''), 'canonical' => $cashback ? '/pay/cashback/' : '/pay/']);
$crumbs = $cashback ? [['name' => 'Оплата', 'href' => '/pay/'], ['name' => 'Карта МИР']] : [['name' => 'Оплата']];
at_page_shell_open($slug, $title, $crumbs);
echo '<div class="panel panel-lg"><div class="pay-head"><span class="chip chip-mint">' . at_icon('shield-check', 'ic ic-xs') . ' Защищённое соединение · ПАО Сбербанк</span><span class="pay-cards">';
foreach ($cashback ? ['mir'] : ['mir', 'visa', 'mastercard'] as $c) {
    echo '<img src="/images/pay/' . $c . '.svg" alt="' . $c . '" width="48" height="30">';
}
echo '</span></div><h2 class="mb-4">Для оплаты введите ваши данные</h2>';
$form = at_form_payment($cashback, (string) ($old['amount'] ?? ''));
// Введённые значения и ошибки после неудачной отправки — прямо в разметку.
foreach (['order_number', 'full_name', 'phone', 'email'] as $f) {
    if (($old[$f] ?? '') !== '') {
        $form = preg_replace('~(name="' . $f . '")~', '$1 value="' . at_e($old[$f]) . '"', $form, 1) ?? $form;
    }
}
if ($errors) {
    foreach ($errors as $f => $msg) {
        $form = preg_replace('~(data-field="' . preg_quote($f, '~') . '"[^>]*>)(.*?)<p class="field-error" hidden></p>~s', '$1$2<p class="field-error">' . at_e($msg) . '</p>', $form, 1) ?? $form;
    }
    $form = str_replace('<div class="form-message" hidden role="alert"></div>', '<div class="form-message is-error" role="alert">Проверьте выделенные поля</div>', $form);
}
echo $form . '</div>';
if (!empty($page['html'])) {
    echo '<article class="panel panel-lg mt-6 text-sm">' . at_rich((string) $page['html']) . '</article>';
}
at_page_shell_close(true, at_help_card('Вопросы по оплате?'));
at_page_end();
