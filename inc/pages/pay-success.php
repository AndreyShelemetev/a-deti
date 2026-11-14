<?php
/** Заявка на оплату принята: /pay/success/?id=N. */
declare(strict_types=1);

$id = (int) ($_GET['id'] ?? 0);
$payment = $id > 0 ? at_one('SELECT id, order_number, amount, status FROM payments WHERE id = ?', [$id]) : null;
at_page_start(['key' => 'pay-success', 'title' => 'Заявка на оплату принята', 'description' => 'Заявка на онлайн-оплату принята.', 'canonical' => '/pay/success/', 'noindex' => true]);
echo '<div class="container-site center-block"><div class="big-emoji mint">✅</div><h1 class="page-h1">Заявка на оплату принята</h1><p class="page-lead center">';
if ($payment !== null) {
    echo 'Договор <b>' . at_e($payment['order_number']) . '</b>, сумма <b>' . at_e(at_price((int) $payment['amount'])) . '</b>. ';
}
echo 'Платёжный шлюз пока не подключён к сайту: менеджер свяжется с вами и пришлёт ссылку на оплату на указанный e-mail.</p><a href="/" class="btn-primary mt-8">На главную</a></div>';
at_page_end();
