<?php
/** Временная остановка оплаты: ни формы, ни создания платежа, ни перехода в шлюз. */
declare(strict_types=1);
http_response_code(503);
header('Retry-After: 3600');
header('Cache-Control: no-store, max-age=0');
header('X-Robots-Tag: noindex, nofollow');
$s = at_settings();
$phone = trim((string) ($s['contacts.phone1'] ?? ''));
$phoneHref = $phone !== '' ? at_tel_href($phone) : '';
?>
<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Онлайн-оплата временно недоступна — А-тур</title>
  <link rel="icon" href="/favicon.ico">
  <link rel="stylesheet" href="<?= at_e(at_asset('/assets/design-fonts.css')) ?>">
  <link rel="stylesheet" href="<?= at_e(at_asset('/assets/design.css')) ?>">
</head>
<body class="payment-paused">
  <main style="max-width:720px;margin:0 auto;padding:clamp(24px,6vw,64px) 20px">
    <a href="/" aria-label="А-тур — на главную"><img src="/assets/design/logo.svg" width="105" height="73" alt="А-тур" style="display:block;margin-bottom:32px"></a>
    <section aria-labelledby="payment-paused-title" style="background:#fff;border:1px solid #dce6ee;border-radius:24px;padding:clamp(24px,5vw,48px);box-shadow:0 12px 40px #0049740c">
      <p style="margin:0 0 16px;color:#004974;font-size:14px;font-weight:600">А-тур · Поможем с оплатой</p>
      <h1 id="payment-paused-title" style="margin:0 0 20px;color:#004974;font-size:clamp(26px,5vw,38px);line-height:1.2">Онлайн-оплата временно недоступна</h1>
      <p style="margin:0 0 28px;color:#52677c;font-size:17px;line-height:1.6">Чтобы уточнить порядок оплаты путёвки, свяжитесь с нашим менеджером.</p>
      <div style="display:flex;flex-wrap:wrap;gap:12px">
        <?php if ($phoneHref !== '' && $phoneHref !== 'tel:'): ?><a class="btn btn--accent" href="<?= at_e($phoneHref) ?>" style="max-width:100%;white-space:normal;text-align:center;overflow-wrap:anywhere"><?= at_e($phone) ?></a><?php endif; ?>
        <a class="btn btn--ghost" href="/kak-nas-nayti/" style="max-width:100%;white-space:normal;text-align:center">Контакты менеджеров</a>
      </div>
    </section>
    <p style="margin:24px 0 0"><a href="/" style="color:#004974;text-underline-offset:4px">Вернуться на главную</a></p>
  </main>
</body>
</html>
