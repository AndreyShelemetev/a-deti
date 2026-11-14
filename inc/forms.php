<?php
/**
 * Заявки с форм сайта (общая заявка, звонок, бронирование, подписка,
 * агентства, отзыв) и заявка на онлайн-оплату. Всё пишется в таблицу
 * requests сайта И уходит в платформенную sg_lead() — заявки видны и в
 * админке сайта, и во вкладке «Лиды» панели платформы.
 *
 * Здесь же — очистка HTML из админки (белый список тегов) и валидация.
 */
declare(strict_types=1);

// --- Очистка HTML ---------------------------------------------------------------

/** HTML из редактора админки: только безопасные теги и атрибуты. */
function at_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $tags = ['p', 'h2', 'h3', 'h4', 'h5', 'ul', 'ol', 'li', 'strong', 'b', 'em', 'i', 'u', 'a', 'br', 'table', 'thead', 'tbody',
        'tr', 'td', 'th', 'img', 'blockquote', 'hr', 'sup', 'sub', 'figure', 'figcaption', 'iframe', 'span', 'div'];
    $attrs = [
        'a'      => ['href', 'target', 'rel', 'title'],
        'img'    => ['src', 'alt', 'width', 'height', 'loading', 'style'],
        'td'     => ['colspan', 'rowspan', 'style'],
        'th'     => ['colspan', 'rowspan', 'style'],
        'table'  => ['style'],
        'iframe' => ['src', 'width', 'height', 'allow', 'allowfullscreen', 'title'],
        '*'      => ['class'],
    ];
    $iframeHosts = ['www.youtube.com', 'www.youtube-nocookie.com', 'rutube.ru', 'vk.com', 'yandex.ru'];
    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="at-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $doc->getElementById('at-root');
    if ($root === null) {
        return '';
    }
    $walk = static function (DOMNode $node) use (&$walk, $tags, $attrs, $iframeHosts): void {
        $children = [];
        foreach ($node->childNodes as $c) {
            $children[] = $c;
        }
        foreach ($children as $c) {
            if ($c instanceof DOMComment) {
                $node->removeChild($c);
                continue;
            }
            if (!$c instanceof DOMElement) {
                continue;
            }
            $name = strtolower($c->tagName);
            if (in_array($name, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select'], true)) {
                $node->removeChild($c);
                continue;
            }
            if (!in_array($name, $tags, true)) {
                // Неизвестный тег — разворачиваем, содержимое оставляем.
                while ($c->firstChild) {
                    $node->insertBefore($c->firstChild, $c);
                }
                $node->removeChild($c);
                continue;
            }
            $allowed = array_merge($attrs['*'], $attrs[$name] ?? []);
            $remove = [];
            foreach ($c->attributes as $a) {
                if (!in_array(strtolower($a->name), $allowed, true)) {
                    $remove[] = $a->name;
                }
            }
            foreach ($remove as $r) {
                $c->removeAttribute($r);
            }
            foreach (['href', 'src'] as $urlAttr) {
                if ($c->hasAttribute($urlAttr)) {
                    $v = trim($c->getAttribute($urlAttr));
                    if (!preg_match('~^(https?://|mailto:|tel:|/|#)~i', $v)) {
                        $c->removeAttribute($urlAttr);
                    }
                }
            }
            if ($c->hasAttribute('style') && preg_match('~(expression|javascript|url\s*\()~i', $c->getAttribute('style'))) {
                $c->removeAttribute('style');
            }
            if ($name === 'iframe') {
                $host = strtolower((string) parse_url($c->getAttribute('src'), PHP_URL_HOST));
                if (!in_array($host, $iframeHosts, true)) {
                    $node->removeChild($c);
                    continue;
                }
            }
            if ($name === 'a' && preg_match('~^https?://~i', $c->getAttribute('href'))) {
                $c->setAttribute('target', '_blank');
                $c->setAttribute('rel', 'noopener');
            }
            $walk($c);
        }
    };
    $walk($root);
    $out = '';
    foreach ($root->childNodes as $c) {
        $out .= $doc->saveHTML($c);
    }
    return trim($out);
}

// --- Валидация -------------------------------------------------------------------

/**
 * Проверка полей формы.
 *
 * @param array<string,mixed> $data обычно $_POST
 * @param array<string,array<string,mixed>> $rules поле => [required, min, max, phone, email, int, msg]
 * @return array{values:array<string,mixed>,errors:array<string,string>}
 */
function at_validate(array $data, array $rules): array
{
    $values = [];
    $errors = [];
    foreach ($rules as $field => $r) {
        $raw = $data[$field] ?? '';
        $v = is_array($raw) ? '' : trim((string) $raw);
        $required = !empty($r['required']);
        if ($v === '') {
            if ($required) {
                $errors[$field] = (string) ($r['msg'] ?? 'Заполните это поле');
            }
            $values[$field] = isset($r['int']) ? null : '';
            continue;
        }
        if (isset($r['int'])) {
            if (!preg_match('~^-?\d+$~', $v)) {
                $errors[$field] = (string) ($r['msg'] ?? 'Укажите число');
                continue;
            }
            $n = (int) $v;
            [$lo, $hi] = $r['int'];
            if ($n < $lo || $n > $hi) {
                $errors[$field] = (string) ($r['msg'] ?? 'Недопустимое значение');
                continue;
            }
            $values[$field] = $n;
            continue;
        }
        if (isset($r['max']) && mb_strlen($v) > $r['max']) {
            $v = mb_substr($v, 0, (int) $r['max']);
        }
        if (isset($r['min']) && mb_strlen($v) < $r['min']) {
            $errors[$field] = (string) ($r['msg'] ?? 'Слишком коротко');
            continue;
        }
        $phoneDigits = (string) preg_replace('~\D~', '', $v);
        if (!empty($r['phone']) && (strlen($phoneDigits) < 10 || strlen($phoneDigits) > 15 || (str_starts_with($v, '+7') && strlen($phoneDigits) !== 11) || !preg_match('~^[+\d][\d\s()\-]+$~', $v))) {
            $errors[$field] = 'Некорректный телефон';
            continue;
        }
        if (!empty($r['email']) && filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
            $errors[$field] = 'Некорректный e-mail';
            continue;
        }
        $values[$field] = $v;
    }
    return ['values' => $values, 'errors' => $errors];
}

function at_is_ajax(): bool
{
    return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch'
        || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
}

/** Ответ формы: JSON для fetch, редирект с параметром — без JS. */
function at_form_respond(bool $ok, string $message, array $errors = [], string $back = '/', string $kind = ''): never
{
    if (at_is_ajax()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => $ok, 'message' => $message, 'errors' => $errors], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $back = (string) parse_url($back, PHP_URL_PATH) ?: '/';
    $sep = '?';
    at_redirect($back . $sep . ($ok ? 'sent=' . rawurlencode($kind) : 'form_error=' . rawurlencode($message)) . '#form-status', 303);
}

/** Правила общей заявки: связаться можно по e-mail, телефон необязателен. */
function at_request_rules(): array
{
    return [
        'name' => ['required' => true, 'min' => 2, 'max' => 120, 'msg' => 'Укажите имя'],
        'email' => ['required' => true, 'max' => 120, 'email' => true, 'msg' => 'Укажите e-mail'],
        'phone' => ['max' => 30, 'phone' => true], 'message' => ['max' => 3000],
        'consent' => ['required' => true, 'msg' => 'Нужно согласие на обработку персональных данных'], 'page_url' => ['max' => 500],
    ];
}

/** В бронировании обязательны имя, e-mail, согласие и выбранный тур. */
function at_booking_rules(): array
{
    return array_replace(at_request_rules(), [
        'tour_id' => ['required' => true, 'int' => [1, PHP_INT_MAX], 'msg' => 'Тур не найден'],
        'shift_name' => ['max' => 300], 'amount' => ['int' => [0, 100000000]],
    ]);
}

/** Сохраняет заявку в requests и дублирует в платформенные лиды. */
function at_save_request(string $kind, array $d): int
{
    $st = db()->prepare('INSERT INTO requests (kind, status, name, email, phone, message, tour_id, tour_title, shift_name, amount, page_url, payload)
        VALUES (?, "NEW", ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([
        $kind,
        $d['name'] ?? null,
        ($d['email'] ?? '') !== '' ? mb_strtolower((string) $d['email']) : null,
        ($d['phone'] ?? '') !== '' ? at_normalize_phone((string) $d['phone']) : null,
        ($d['message'] ?? '') !== '' ? $d['message'] : null,
        $d['tour_id'] ?? null,
        $d['tour_title'] ?? null,
        ($d['shift_name'] ?? '') !== '' ? $d['shift_name'] : null,
        $d['amount'] ?? null,
        ($d['page_url'] ?? '') !== '' ? mb_substr((string) $d['page_url'], 0, 500) : null,
        !empty($d['payload']) ? json_encode($d['payload'], JSON_UNESCAPED_UNICODE) : null,
    ]);
    $id = (int) db()->lastInsertId();
    if (function_exists('sg_lead')) {
        $lead = [
            'name' => $d['name'] ?? '', 'phone' => $d['phone'] ?? '', 'email' => $d['email'] ?? '', 'message' => $d['message'] ?? '',
            'tour' => $d['tour_title'] ?? '', 'shift' => $d['shift_name'] ?? '', 'amount' => $d['amount'] ?? '', 'page' => $d['page_url'] ?? '',
        ];
        foreach ((array) ($d['payload'] ?? []) as $k => $v) {
            $lead[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        }
        // Local request ID keeps the email's copy link tied to the saved application.
        $lead['request_id'] = (string) $id;
        sg_lead(array_filter($lead, static fn($v): bool => $v !== '' && $v !== null), (ATOUR_REQUEST_KINDS[$kind] ?? 'Заявка') . ' #' . $id);
    }
    return $id;
}

/** POST /lead/ — все формы обратной связи. */
function at_handle_lead(): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        at_redirect('/', 302);
    }
    $kind = strtoupper(trim((string) ($_POST['kind'] ?? '')));
    $back = (string) ($_POST['page_url'] ?? ($_SERVER['HTTP_REFERER'] ?? '/'));
    if (!isset(ATOUR_REQUEST_KINDS[$kind])) {
        at_form_respond(false, 'Неизвестная форма', [], $back);
    }
    // Ловушка для ботов: поле, которое человек не видит и не заполняет.
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        at_form_respond(true, 'Спасибо! Мы получили вашу заявку.', [], $back, $kind);
    }
    $consent = ['required' => true, 'msg' => 'Нужно согласие на обработку персональных данных'];
    $name = ['required' => true, 'min' => 2, 'max' => 120, 'msg' => 'Укажите имя'];
    $phone = ['required' => true, 'min' => 6, 'max' => 30, 'phone' => true, 'msg' => 'Укажите телефон'];
    $emailOpt = ['max' => 120, 'email' => true];
    $success = 'Спасибо! Мы получили вашу заявку и свяжемся с вами в ближайшее время.';

    switch ($kind) {
        case 'REQUEST':
            $v = at_validate($_POST, at_request_rules());
            if ($v['errors']) {
                at_form_respond(false, 'Проверьте выделенные поля', $v['errors'], $back);
            }
            at_save_request('REQUEST', $v['values']);
            break;

        case 'CALLBACK':
            $v = at_validate($_POST, ['name' => $name, 'phone' => $phone, 'message' => ['max' => 2000], 'consent' => $consent, 'page_url' => ['max' => 500]]);
            if ($v['errors']) {
                at_form_respond(false, 'Проверьте выделенные поля', $v['errors'], $back);
            }
            at_save_request('CALLBACK', $v['values']);
            $success = 'Спасибо! Мы перезвоним вам в рабочее время.';
            break;

        case 'BOOKING':
            $v = at_validate($_POST, at_booking_rules());
            if ($v['errors']) {
                at_form_respond(false, 'Проверьте выделенные поля', $v['errors'], $back);
            }
            $tour = at_one('SELECT id, title, slug FROM tours WHERE id = ?', [(int) $v['values']['tour_id']]);
            if ($tour === null) {
                at_form_respond(false, 'Тур не найден', [], $back);
            }
            $d = $v['values'];
            $d['tour_title'] = $tour['title'];
            $d['page_url'] = '/tour/' . $tour['slug'] . '/';
            at_save_request('BOOKING', $d);
            $success = 'Заявка на бронирование отправлена! Менеджер свяжется с вами для подтверждения места.';
            break;

        case 'SUBSCRIBE':
            $v = at_validate($_POST, ['name' => ['max' => 120], 'email' => ['required' => true, 'max' => 120, 'email' => true, 'msg' => 'Укажите e-mail'], 'consent' => $consent]);
            if ($v['errors']) {
                at_form_respond(false, 'Проверьте выделенные поля', $v['errors'], $back);
            }
            $email = mb_strtolower((string) $v['values']['email']);
            db()->prepare('INSERT INTO subscribers (email, name, is_active) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE name = COALESCE(NULLIF(VALUES(name), ""), name), is_active = 1')
                ->execute([$email, (string) $v['values']['name']]);
            at_save_request('SUBSCRIBE', ['name' => $v['values']['name'], 'email' => $email]);
            $success = 'Вы подписаны на новости А-тур!';
            break;

        case 'AGENCY':
            $v = at_validate($_POST, ['name' => $name, 'company' => ['required' => true, 'min' => 2, 'max' => 200, 'msg' => 'Укажите название агентства'],
                'phone' => $phone, 'email' => ['required' => true, 'max' => 120, 'email' => true, 'msg' => 'Укажите e-mail'],
                'message' => ['max' => 2000], 'consent' => $consent]);
            if ($v['errors']) {
                at_form_respond(false, 'Проверьте выделенные поля', $v['errors'], $back);
            }
            $d = $v['values'];
            $d['page_url'] = '/agentstvam/';
            $d['payload'] = ['company' => $d['company']];
            at_save_request('AGENCY', $d);
            $success = 'Спасибо! Мы свяжемся с вами и откроем доступ к агентской базе.';
            break;

        case 'REVIEW':
            $v = at_validate($_POST, ['name' => $name, 'text' => ['required' => true, 'min' => 10, 'max' => 3000, 'msg' => 'Напишите хотя бы пару предложений'],
                'rating' => ['required' => true, 'int' => [1, 5], 'msg' => 'Поставьте оценку'], 'tour_id' => ['int' => [1, PHP_INT_MAX]], 'consent' => $consent, 'page_url' => ['max' => 500]]);
            if ($v['errors']) {
                at_form_respond(false, 'Проверьте выделенные поля', $v['errors'], $back);
            }
            $d = $v['values'];
            $tourId = $d['tour_id'] ?? null;
            if ($tourId !== null && at_scalar('SELECT COUNT(*) FROM tours WHERE id = ?', [$tourId]) === 0) {
                $tourId = null;
            }
            db()->prepare('INSERT INTO reviews (author, body, rating, source, is_published, tour_id) VALUES (?, ?, ?, "SITE", 0, ?)')
                ->execute([$d['name'], $d['text'], $d['rating'], $tourId]);
            at_save_request('REVIEW', ['name' => $d['name'], 'message' => $d['text'], 'tour_id' => $tourId, 'page_url' => $d['page_url'] ?? '', 'payload' => ['rating' => $d['rating']]]);
            $success = 'Спасибо за отзыв! Он появится на сайте после проверки модератором.';
            break;
    }
    at_form_respond(true, $success, [], $back, $kind);
}

/**
 * POST /pay/ и /pay/cashback/ — заявка на онлайн-оплату по номеру договора.
 * Возвращает ошибки и введённые значения для повторного показа формы;
 * при успехе уходит в шлюз (если настроен адрес payment.gateway_url) или на
 * страницу «заявка принята».
 *
 * @return array{errors:array<string,string>,old:array<string,string>}
 */
function at_handle_payment(bool $cashback): array
{
    if (!at_payment_enabled()) {
        http_response_code(503);
        return ['errors' => ['payment' => 'Онлайн-оплата временно недоступна. Свяжитесь с менеджером.'], 'old' => []];
    }
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        at_redirect('/pay/success/', 303);
    }
    $v = at_validate($_POST, [
        'order_number' => ['required' => true, 'max' => 50, 'msg' => 'Укажите номер договора'],
        'full_name'    => ['required' => true, 'min' => 5, 'max' => 200, 'msg' => 'Укажите ФИО'],
        'phone'        => ['max' => 30, 'phone' => true],
        'email'        => ['required' => true, 'max' => 120, 'email' => true, 'msg' => 'Укажите e-mail'],
        'amount'       => ['required' => true, 'int' => [1, 10000000], 'msg' => 'Укажите сумму'],
        'consent'      => ['required' => true, 'msg' => 'Нужно согласие на обработку персональных данных'],
    ]);
    $old = array_map(static fn($x): string => is_array($x) ? '' : (string) $x, $_POST);
    if ($v['errors']) {
        return ['errors' => $v['errors'], 'old' => $old];
    }
    $d = $v['values'];
    $isMir = $cashback && (string) ($_POST['is_mir_card'] ?? '') === 'on';
    db()->prepare('INSERT INTO payments (order_number, full_name, phone, email, amount, is_mir_card, status) VALUES (?, ?, ?, ?, ?, ?, "NEW")')
        ->execute([$d['order_number'], $d['full_name'], $d['phone'] !== '' ? at_normalize_phone((string) $d['phone']) : null, mb_strtolower((string) $d['email']), $d['amount'], $isMir ? 1 : 0]);
    $id = (int) db()->lastInsertId();
    if (function_exists('sg_lead')) {
        sg_lead(['name' => $d['full_name'], 'phone' => $d['phone'], 'email' => $d['email'], 'message' => 'Договор ' . $d['order_number'] . ', сумма ' . $d['amount'] . ' ₽' . ($isMir ? ', карта МИР' : '')], 'Онлайн-оплата #' . $id);
    }
    $gateway = at_setting('payment.gateway_url');
    if ($gateway !== '' && preg_match('~^https://~i', $gateway)) {
        $url = $gateway . (str_contains($gateway, '?') ? '&' : '?') . http_build_query([
            'orderNumber' => $d['order_number'] . '-' . $id,
            'amount'      => (int) $d['amount'] * 100,
            'email'       => $d['email'],
        ]);
        db()->prepare('UPDATE payments SET status = "REDIRECTED", gateway_url = ? WHERE id = ?')->execute([$url, $id]);
        at_redirect($url, 303);
    }
    at_redirect('/pay/success/?id=' . $id, 303);
}
