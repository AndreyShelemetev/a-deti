<?php
/**
 * Ядро админки сайта А-тур: сессия и вход (та же таблица admin_users и та же
 * сессия, что у панели платформы adm_panel001.php — одна учётка на обе),
 * CSRF, разметка, поля форм, редактор HTML, загрузка картинок, репитеры.
 *
 * Вкладки лежат в inc/admin/tab-*.php: каждая объявляет функцию
 * adm_tab_<имя>(): string и сама разбирает свои POST-действия.
 */
declare(strict_types=1);

function adm_e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function adm_url(string $tab, array $params = []): string
{
    return '/site-admin?' . http_build_query(['tab' => $tab] + $params);
}

function adm_redirect(string $tab, array $params = []): never
{
    header('Location: ' . adm_url($tab, $params), true, 303);
    exit;
}

function adm_csrf(): string
{
    if (empty($_SESSION['sg_csrf'])) {
        $_SESSION['sg_csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['sg_csrf'];
}

function adm_check_csrf(): void
{
    if (!isset($_POST['csrf']) || !hash_equals(adm_csrf(), (string) $_POST['csrf'])) {
        http_response_code(400);
        exit('Сессия истекла — обновите страницу и повторите действие.');
    }
}

/** Сессия — как у платформы: своё хранилище и долгий срок жизни. */
function adm_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (function_exists('sg_session_start')) {
        sg_session_start();
    } else {
        session_start();
    }
}

/** Текущий администратор (из admin_users) или null. */
function adm_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    $id = (int) ($_SESSION['sg_admin'] ?? 0);
    if ($id > 0) {
        try {
            $user = at_one('SELECT id, login, must_change FROM admin_users WHERE id = ?', [$id]);
        } catch (\Throwable $e) {
            $user = null;
        }
    }
    return $user;
}

function adm_ensure_admin_table(): void
{
    db()->exec('CREATE TABLE IF NOT EXISTS admin_users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        login VARCHAR(64) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        must_change TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}

/** Точка входа: сессия, вход/выход, вкладка. */
function adm_boot(): void
{
    adm_session();
    adm_ensure_admin_table();
    if (isset($_GET['logout'])) {
        $_SESSION = [];
        session_destroy();
        header('Location: /site-admin');
        exit;
    }
    $err = '';
    if (($_POST['do'] ?? '') === 'login') {
        $u = at_one('SELECT * FROM admin_users WHERE login = ?', [(string) ($_POST['login'] ?? '')]);
        if ($u && password_verify((string) ($_POST['password'] ?? ''), (string) $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['sg_admin'] = (int) $u['id'];
            $returnTab = (string) ($_GET['tab'] ?? 'dashboard');
            $returnId = $returnTab === 'requests' ? max(0, (int) ($_GET['id'] ?? 0)) : 0;
            adm_redirect($returnTab, $returnId > 0 ? ['id' => $returnId] : []);
        }
        $err = 'Неверный логин или пароль.';
    }
    if (adm_user() === null) {
        adm_login_page($err);
        exit;
    }
    // Первый вход: платформа выдала временный пароль, и до его смены дальше
    // не пускаем — так же, как adm_panel001.php (учётка у них общая).
    if ((int) (adm_user()['must_change'] ?? 0) === 1) {
        adm_change_password_page((int) adm_user()['id']);
    }
    $tab = (string) ($_GET['tab'] ?? 'dashboard');
    if (!preg_match('~^[a-z]+$~', $tab) || !is_file(__DIR__ . '/tab-' . $tab . '.php')) {
        $tab = 'dashboard';
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        adm_check_csrf();
    }
    require_once __DIR__ . '/tab-' . $tab . '.php';
    $fn = 'adm_tab_' . $tab;
    $body = $fn();
    adm_layout(adm_tab_title($tab), $body, $tab);
}

/** Меню админки по группам: ключ вкладки (или «tours:ТИП» — список туров одного типа) → подпись. */
function adm_nav(): array
{
    return [
        'Продажи' => ['dashboard' => 'Обзор', 'requests' => 'Заявки', 'payments' => 'Платежи', 'subscribers' => 'Подписчики'],
        'Туры'    => ['tours' => 'Все туры', 'tours:CAMP' => 'Лагеря отдыха', 'tours:SPORT' => 'Спортивные сборы', 'tours:EXCURSION' => 'Экскурсионные туры',
            'tours:TRANSFER' => 'Авиа и ж/д', 'shifts' => 'Календарь смен', 'taxonomy' => 'Характеристики и регионы', 'catalog' => 'Страницы каталога (SEO)'],
        'Контент' => ['posts' => 'Новости и статьи', 'pages' => 'Текстовые страницы', 'header' => 'Шапка и меню', 'home' => 'Главная страница', 'reviews' => 'Отзывы', 'media' => 'Картинки'],
        'Система' => ['settings' => 'Настройки сайта', 'redirects' => 'Редиректы', 'tools' => 'Сервис'],
    ];
}

function adm_tab_title(string $tab): string
{
    if ($tab === 'tours') {
        $type = (string) ($_GET['type'] ?? '');
        return isset(ATOUR_TOUR_TYPES[$type]) && !isset($_GET['id']) && !isset($_GET['new']) ? ATOUR_TOUR_TYPES[$type] : 'Туры';
    }
    foreach (adm_nav() as $items) {
        if (isset($items[$tab])) {
            return $items[$tab];
        }
    }
    return 'Панель управления';
}

function adm_login_page(string $err): void
{
    $body = '<div class="login-wrap"><form method="post" class="card login-card"><h1>Вход в управление сайтом</h1>'
        . ($err !== '' ? '<div class="alert err">' . adm_e($err) . '</div>' : '')
        . '<input type="hidden" name="do" value="login">'
        . '<label>Логин<input type="text" name="login" autofocus required></label>'
        . '<label>Пароль<input type="password" name="password" required></label>'
        . '<button class="btn">Войти</button></form></div>';
    adm_layout('Вход', $body, '', true);
}

/** Обязательная смена временного пароля (must_change = 1). */
function adm_change_password_page(int $uid): never
{
    $err = '';
    if (($_POST['do'] ?? '') === 'change_pw') {
        adm_check_csrf();
        $p1 = (string) ($_POST['p1'] ?? '');
        if (strlen($p1) < 6) {
            $err = 'Пароль слишком короткий (минимум 6 символов).';
        } elseif ($p1 !== (string) ($_POST['p2'] ?? '')) {
            $err = 'Пароли не совпадают.';
        } else {
            db()->prepare('UPDATE admin_users SET password_hash = ?, must_change = 0 WHERE id = ?')
                ->execute([password_hash($p1, PASSWORD_DEFAULT), $uid]);
            adm_redirect('dashboard');
        }
    }
    $body = '<div class="login-wrap"><form method="post" class="card login-card"><h1>Смените пароль</h1>'
        . '<p class="muted">При первом входе нужно задать свой пароль. Он общий для этой админки и панели платформы (adm_panel001.php).</p>'
        . ($err !== '' ? '<div class="alert err">' . adm_e($err) . '</div>' : '')
        . '<input type="hidden" name="do" value="change_pw"><input type="hidden" name="csrf" value="' . adm_e(adm_csrf()) . '">'
        . '<label>Новый пароль<input type="password" name="p1" autofocus required minlength="6"></label>'
        . '<label>Повторите пароль<input type="password" name="p2" required minlength="6"></label>'
        . '<button class="btn">Сохранить</button></form></div>';
    adm_layout('Смена пароля', $body, '', true);
    exit;
}

function adm_layout(string $title, string $body, string $tab = '', bool $bare = false): void
{
    $newCount = 0;
    if (!$bare) {
        try {
            $newCount = (int) at_scalar("SELECT COUNT(*) FROM requests WHERE status = 'NEW'");
        } catch (\Throwable $e) {
            $newCount = 0;
        }
    }
    // Платформенный theme.css сюда не подключаем: у админки свои стили, а
    // общие правила темы (body, h1, .btn) сбивали бы её вёрстку.
    echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">';
    echo '<title>' . adm_e($title) . ' — Админка А-тур</title><link rel="icon" href="/images/favicon.svg">';
    $skin = dirname(__DIR__, 2) . '/assets/admin-cabinet.css';
    $skinVersion = is_file($skin) ? (string) filemtime($skin) : '1';
    echo '<style>' . adm_css() . '</style><link rel="stylesheet" href="/assets/design-fonts.css">'
        . '<link rel="stylesheet" href="/assets/admin-cabinet.css?v=' . adm_e($skinVersion) . '"></head><body class="' . ($bare ? 'bare' : '') . '">';
    if ($bare) {
        echo $body;
    } else {
        $u = adm_user();
        echo '<div class="shell"><aside class="side"><div class="side-head"><a href="/site-admin" class="brand">А-тур <span>admin</span></a><a href="/" target="_blank" class="muted small">сайт ↗</a></div><nav class="side-nav">';
        $curType = (string) ($_GET['type'] ?? '');
        foreach (adm_nav() as $group => $items) {
            echo '<div class="side-group">' . adm_e($group) . '</div>';
            foreach ($items as $key => $label) {
                [$t, $type] = array_pad(explode(':', $key, 2), 2, '');
                if (!is_file(__DIR__ . '/tab-' . $t . '.php')) {
                    continue;
                }
                $on = $t === $tab && ($type !== '' ? $curType === $type : ($tab !== 'tours' || !isset($items['tours:' . $curType])));
                echo '<a href="' . adm_url($t, $type !== '' ? ['type' => $type] : []) . '" class="' . ($type !== '' ? 'sub ' : '') . ($on ? 'on' : '') . '">' . adm_e($label)
                    . ($t === 'requests' && $newCount > 0 ? '<span class="badge">' . $newCount . '</span>' : '') . '</a>';
            }
        }
        echo '<div class="side-group">Платформа</div><a href="/adm_panel001.php" target="_blank">SEO страниц, лиды ↗</a></nav>';
        echo '<div class="side-foot"><b>' . adm_e($u['login'] ?? '') . '</b><br><a href="/site-admin?logout=1" class="danger">Выйти</a></div></aside>';
        echo '<main class="main"><div class="top-row"><h1>' . adm_e($title) . '</h1></div>';
        if (isset($_GET['ok'])) {
            echo '<div class="alert ok">' . adm_e((string) ($_GET['msg'] ?? 'Сохранено.')) . '</div>';
        }
        if (isset($_GET['err'])) {
            echo '<div class="alert err">' . adm_e((string) $_GET['err']) . '</div>';
        }
        echo $body . '</main></div>';
    }
    $editorScript = dirname(__DIR__, 2) . '/assets/admin-editor.js';
    $editorVersion = is_file($editorScript) ? (string) filemtime($editorScript) : '1';
    echo '<script src="/assets/admin-editor.js?v=' . adm_e($editorVersion) . '"></script><script>' . adm_js() . '</script></body></html>';
}

function adm_css(): string
{
    return <<<'CSS'
*{box-sizing:border-box}body{margin:0;font-family:"Segoe UI",system-ui,-apple-system,Roboto,Arial,sans-serif;font-size:14px;color:#1f2352;background:#f6f7fb}
a{color:#1f3ff0;text-decoration:none}a:hover{text-decoration:underline}
.shell{display:grid;grid-template-columns:250px 1fr;min-height:100vh}.side{background:#fff;border-right:1px solid #dcdeea;position:sticky;top:0;height:100vh;overflow-y:auto;display:flex;flex-direction:column}
.side-head{display:flex;align-items:center;justify-content:space-between;padding:16px 18px}.brand{font-size:18px;font-weight:900;color:#14163a}.brand span{color:#3559ff}
.side-nav{display:flex;flex-direction:column;padding:0 10px;gap:2px}.side-nav a{display:flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;font-weight:700;color:#33386b}
.side-nav a:hover{background:#eef3ff;color:#1a31c7;text-decoration:none}.side-nav a.on{background:#3559ff;color:#fff}.badge{margin-left:auto;background:#f95a04;color:#fff;border-radius:999px;padding:1px 8px;font-size:11px;font-weight:900}
.side-foot{margin-top:auto;padding:16px 18px;border-top:1px solid #eef0f6;font-size:12px;color:#6a6f97}.danger{color:#d84800}
.main{padding:24px 28px;min-width:0}.top-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:16px}h1{margin:0;font-size:24px;color:#14163a}h2{font-size:17px;margin:0 0 12px;color:#14163a}h3{font-size:15px;margin:0 0 8px}
.card{background:#fff;border-radius:16px;padding:18px;box-shadow:0 1px 3px rgba(20,22,58,.08);margin-bottom:16px}
.muted{color:#6a6f97}.small{font-size:12px}.row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}.right{margin-left:auto}
.alert{padding:10px 14px;border-radius:10px;margin-bottom:14px;font-weight:600}.alert.ok{background:#d3fae1;color:#0f7540}.alert.err{background:#ffe1d1;color:#b03a00}
label{display:block;margin:10px 0 4px;font-size:13px;font-weight:700;color:#33386b}label .hint{font-weight:400;color:#8f93b3}
input[type=text],input[type=number],input[type=email],input[type=url],input[type=password],input[type=date],input[type=datetime-local],textarea,select{width:100%;padding:8px 11px;border:1px solid #d1d5db;border-radius:8px;font:inherit;background:#fff}
textarea{min-height:80px}.inline{display:inline-flex;align-items:center;gap:6px;margin-right:14px;font-weight:600}.inline input{width:auto}
.btn{display:inline-flex;align-items:center;gap:6px;background:#3559ff;color:#fff;border:0;border-radius:8px;padding:9px 16px;font-weight:700;cursor:pointer;font:inherit;font-weight:700;text-decoration:none}
.btn:hover{background:#1f3ff0;text-decoration:none;color:#fff}.btn.g{background:#eef0f6;color:#33386b}.btn.g:hover{background:#dcdeea}.btn.d{background:#ef4444}.btn.sm{padding:5px 10px;font-size:12px}
table{width:100%;border-collapse:collapse;font-size:13px}th,td{text-align:left;padding:8px;border-bottom:1px solid #eef0f6;vertical-align:top}th{color:#6a6f97;font-size:12px;text-transform:uppercase;letter-spacing:.04em}tr:hover td{background:#fafbff}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}.grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:0 16px}.cols{display:grid;grid-template-columns:2fr 1fr;gap:16px;align-items:start}
@media(max-width:900px){.shell{grid-template-columns:1fr}.side{position:static;height:auto}.cols,.grid2,.grid3{grid-template-columns:1fr}}
.chip{display:inline-block;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:800;background:#eef0f6;color:#33386b}.chip.NEW,.chip.on{background:#ffe1d1;color:#b03a00}.chip.IN_PROGRESS{background:#fff4b8;color:#cc8f00}.chip.DONE,.chip.PAID,.chip.pub{background:#d3fae1;color:#0f7540}.chip.SPAM,.chip.FAILED,.chip.CANCELLED{background:#ffe0ef;color:#d11a6f}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:16px}.stat{border-radius:14px;padding:16px;font-weight:700}.stat b{display:block;font-size:26px;font-weight:900}
.stat.a{background:#ffe1d1;color:#b03a00}.stat.b{background:#eef3ff;color:#1a31c7}.stat.c{background:#d3fae1;color:#0f7540}.stat.d{background:#fff4b8;color:#cc8f00}.stat.e{background:#ffe0ef;color:#d11a6f}
.tb{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:6px}.tb button{border:1px solid #d1d5db;background:#fff;border-radius:6px;padding:4px 9px;cursor:pointer;font:inherit;font-size:12px}
.wys{min-height:180px;border:1px solid #d1d5db;border-radius:8px;padding:10px 12px;background:#fff;overflow:auto}.wys img{max-width:100%}.wys table{border-collapse:collapse}.wys td,.wys th{border:1px solid #d1d5db;padding:4px 8px}
.rep-row{display:grid;gap:6px;align-items:center;background:#f6f7fb;border-radius:10px;padding:8px;margin-bottom:6px}.rep-row input{padding:6px 9px;font-size:13px}.rep-ctl{display:flex;gap:2px}.rep-ctl button{border:0;background:#fff;border-radius:6px;padding:4px 7px;cursor:pointer}
.img-field{display:flex;gap:8px;align-items:center}.img-field img{width:64px;height:48px;object-fit:cover;border-radius:6px;background:#eef0f6}.img-field input[type=text]{flex:1}
.thumbs{display:flex;flex-wrap:wrap;gap:8px}.thumbs img{width:120px;height:80px;object-fit:cover;border-radius:8px}
.filters a{display:inline-block;padding:4px 10px;border-radius:999px;background:#fff;box-shadow:0 1px 3px rgba(20,22,58,.08);margin:0 4px 6px 0;font-weight:700;font-size:12px;color:#33386b}.filters a.on{background:#3559ff;color:#fff}
.pages a,.pages span{display:inline-block;min-width:32px;text-align:center;padding:5px 8px;border-radius:8px;background:#fff;margin-right:4px;box-shadow:0 1px 3px rgba(20,22,58,.08)}.pages span{background:#3559ff;color:#fff}
.login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.login-card{width:100%;max-width:380px}.login-card h1{font-size:20px;margin-bottom:6px}.login-card .btn{margin-top:14px;width:100%;justify-content:center}
pre{white-space:pre-wrap;word-break:break-word;background:#f6f7fb;padding:8px;border-radius:8px}.checks{columns:2;font-weight:600}.checks label{display:block;margin:2px 0;font-weight:600}
/* Меню по группам */
.side-group{padding:14px 12px 4px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:#8f93b3}.side-nav a.sub{padding-left:24px;font-weight:600}
/* Список туров: категории, фильтры, таблица, массовые действия */
.sub-title{margin:-8px 0 14px;color:#6a6f97;font-size:13px}
.chips{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;align-items:center}.chips a{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:999px;background:#fff;box-shadow:0 1px 3px rgba(20,22,58,.08);font-weight:700;font-size:12px;color:#33386b}
.chips a:hover{background:#eef3ff;text-decoration:none}.chips a.on{background:#14163a;color:#fff}.chips a.on2{background:#3559ff;color:#fff}.chips a span{opacity:.6}.chips .sep{color:#c9ccdd;margin:0 4px}
.fgrid{display:grid;grid-template-columns:repeat(6,1fr);gap:8px 10px;align-items:end}.fgrid label{margin:0;font-size:11px}.fgrid .span2{grid-column:span 2}.fgrid .range{display:flex;align-items:center;gap:6px}.fgrid .range span{color:#b8bbd1}.fgrid .btns{display:flex;gap:6px}
@media(max-width:1100px){.fgrid{grid-template-columns:repeat(3,1fr)}}@media(max-width:700px){.fgrid{grid-template-columns:1fr 1fr}}
.tlist td{vertical-align:top}.tlist .tt{display:flex;gap:10px;min-width:260px}.tlist .thumb{width:80px;height:56px;object-fit:cover;border-radius:8px;background:#eef0f6;flex:none}.tlist .tt b a{color:#1a31c7}.tlist .meta{font-size:11px;color:#8f93b3;margin-top:2px}
.gap{color:#d84800;font-size:12px}.okk{color:#0f7540;font-size:12px}.chip.pub{background:#d3fae1;color:#0f7540}.chip.draft{background:#fff4b8;color:#9a6b00}.chip.arch{background:#eef0f6;color:#6a6f97}
.bulk{position:sticky;top:8px;z-index:20;display:none;flex-wrap:wrap;align-items:center;gap:6px;background:#14163a;color:#fff;border-radius:14px;padding:8px 12px;margin-bottom:10px;font-size:13px}.bulk.on{display:flex}
.bulk button{border:0;background:rgba(255,255,255,.12);color:#fff;border-radius:999px;padding:4px 11px;font:inherit;font-size:12px;font-weight:700;cursor:pointer}.bulk button:hover{background:rgba(255,255,255,.24)}.bulk button.d{background:rgba(239,68,68,.8);margin-left:auto}.bulk .sep{opacity:.4}
.rowact form{display:inline}.rowact button{border:0;background:none;color:#6a6f97;font:inherit;font-size:12px;cursor:pointer;padding:2px 0;display:block;text-align:left}.rowact button:hover{color:#1a31c7}
.attn{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px}.attn a{display:block;padding:10px 12px;border-radius:12px;background:#fff7f3;color:#b03a00;font-weight:700;font-size:13px}.attn a b{display:block;font-size:22px;font-weight:900}.attn a.zero{background:#f6f7fb;color:#8f93b3}.attn a:hover{text-decoration:none;filter:brightness(.97)}
.savebar{position:sticky;top:0;z-index:25;display:flex;align-items:center;gap:12px;margin:-24px -28px 14px;padding:10px 28px;background:rgba(246,247,251,.96);backdrop-filter:blur(6px);border-bottom:1px solid #dcdeea}
.savebar .t{font-size:17px;font-weight:900;color:#14163a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.savebar .w{font-size:12px;color:#d84800;font-weight:600}.savebar .w.ok{color:#0f7540}
.tabs{display:flex;gap:4px;overflow-x:auto;background:#fff;border-radius:14px;padding:6px;box-shadow:0 1px 3px rgba(20,22,58,.08);margin-bottom:14px}.tabs button{border:0;background:transparent;border-radius:10px;padding:8px 14px;font:inherit;font-weight:800;color:#4a4f7d;cursor:pointer;white-space:nowrap}.tabs button.on{background:#3559ff;color:#fff}.tabs button span{opacity:.7;font-weight:700}
.tabp[hidden]{display:none}.tagbox{max-height:190px;overflow:auto;border:1px solid #eef0f6;border-radius:10px;padding:8px 12px;columns:3}
.shifts th{font-size:11px;white-space:nowrap}.shifts td{padding:4px 3px;border-bottom:1px solid #eef0f6;vertical-align:middle}.shifts input,.shifts select{padding:6px 8px;font-size:13px}.shifts .days{text-align:center;font-weight:800;color:#6a6f97;white-space:nowrap}.shifts tr.past{opacity:.55}
.shifts .ctl{display:flex;gap:2px}.shifts .ctl button,.gal-ctl button{border:0;background:#eef0f6;border-radius:6px;width:26px;height:26px;cursor:pointer;color:#33386b;font:inherit}.shifts .ctl button.x,.gal-ctl button.x{color:#d84800}
.avail-sel{border-radius:8px;font-weight:800;font-size:12px;width:auto}.avail-sel.AVAILABLE{background:#d3fae1;color:#0f7540;border-color:#d3fae1}.avail-sel.FEW{background:#fff4b8;color:#9a6b00;border-color:#fff4b8}.avail-sel.SOLD_OUT{background:#ffe1d1;color:#b03a00;border-color:#ffe1d1}
.gen{background:#f6f7fb;border-radius:12px;padding:10px 14px;margin-top:10px}.gen summary{cursor:pointer;font-weight:800}.gen .grid6{display:grid;grid-template-columns:repeat(6,1fr);gap:8px}.gen label{margin:6px 0 0;font-size:11px}@media(max-width:900px){.gen .grid6{grid-template-columns:1fr 1fr 1fr}}
.dropzone{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:24px;border:2px dashed #bccdff;border-radius:14px;background:#f6f8ff;color:#1a31c7;font-weight:800;cursor:pointer;text-align:center;margin:0}.dropzone.over{background:#e6edff}.dropzone.busy{opacity:.7}.dropzone small{color:#8f93b3;font-weight:600;font-size:12px}
.gal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px;margin-top:12px}.gal-item{margin:0;background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(20,22,58,.08);overflow:hidden;position:relative;outline:2px solid transparent}.gal-item.is-cover{outline-color:#ffc812}
.gal-item img{width:100%;aspect-ratio:4/3;object-fit:cover;display:block;background:#eef0f6}.gal-cover{position:absolute;left:8px;top:8px;background:#ffe97a;color:#14163a;font-size:10px;font-weight:900;padding:2px 7px;border-radius:999px}
.gal-ctl{display:flex;gap:2px;padding:6px}.gal-ctl button.star{color:#cc8f00}.gal-ctl button.x{margin-left:auto}.gal-item input[type=text]{border:0;border-top:1px solid #eef0f6;border-radius:0;font-size:12px;padding:6px 8px}
.status-pick{display:flex;flex-wrap:wrap;gap:8px}.status-pick label{margin:0;display:flex;flex-direction:column;gap:2px;padding:10px 14px;border:2px solid #dcdeea;border-radius:12px;cursor:pointer;font-weight:800}.status-pick label.on{border-color:#3559ff;background:#eef3ff}.status-pick label small{font-weight:500;color:#6a6f97}.status-pick input{width:auto}
.sec-head{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin:18px 0 4px}.sec-head b{font-size:15px;color:#14163a}.sec-head input{width:240px;padding:4px 8px;font-size:12px}.sec-head .hint{font-size:12px;color:#8f93b3;font-weight:400}
.avail{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:800}.avail.AVAILABLE{background:#d3fae1;color:#0f7540}.avail.FEW{background:#fff4b8;color:#9a6b00}.avail.SOLD_OUT{background:#ffe1d1;color:#b03a00}
CSS;
}

function adm_js(): string
{
    return <<<'JS'
(function(){
function q(s,r){return (r||document).querySelector(s)}function qa(s,r){return Array.prototype.slice.call((r||document).querySelectorAll(s))}
/* Редактор HTML: contenteditable + скрытая textarea; переключение на исходник */
qa('[data-wys]').forEach(function(w){var ed=q('.wys',w),ta=q('textarea',w),src=false;
 if(window.admRichText){window.admRichText(w,upload);return}
 function sync(){if(!src)ta.value=ed.innerHTML}
 qa('.tb button',w).forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();var c=b.getAttribute('data-c'),v=b.getAttribute('data-v');
  if(c==='src'){src=!src;if(src){ta.style.display='block';ed.style.display='none';ta.value=ed.innerHTML}else{ed.innerHTML=ta.value;ta.style.display='none';ed.style.display='block'}return}
  if(src)return;ed.focus();
  if(c==='link'){var u=prompt('Адрес ссылки (https://…)');if(u)document.execCommand('createLink',false,u);}
  else if(c==='img'){var f=q('input[type=file]',w);f&&f.click();}
  else if(c==='imgurl'){var iu=prompt('Адрес картинки');if(iu)document.execCommand('insertImage',false,iu);}
  else document.execCommand(c,false,v||null);sync()})});
 ed.addEventListener('input',sync);ed.addEventListener('blur',sync);
 var f=q('input[type=file]',w);if(f)f.addEventListener('change',function(){if(!f.files.length)return;upload(f.files[0],function(url){ed.focus();document.execCommand('insertImage',false,url);sync()});f.value=''});
 var form=w.closest('form');if(form)form.addEventListener('submit',function(){if(!src)ta.value=ed.innerHTML});
});
/* Загрузка картинки в базу сайта */
function upload(file,cb,onErr){var fd=new FormData();fd.append('file',file);fd.append('csrf',q('input[name=csrf]').value);var isImg=(file.type||'').indexOf('image/')===0;var fail=function(m){if(onErr)onErr(m);else alert(m)};
 fetch('/site-admin?tab=media&action='+(isImg?'upload':'upload-file'),{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){if(d.url)cb(d.url);else fail(d.error||'Не удалось загрузить')}).catch(function(){fail('Не удалось загрузить')})}
function changed(){if(window.admChanged)window.admChanged()}
qa('[data-img-field]').forEach(function(w){var inp=q('input[type=text]',w),f=q('input[type=file]',w),img=q('img',w);
 function show(){if(img)img.src=inp.value||'/images/placeholder.svg'}inp.addEventListener('input',show);
 if(f)f.addEventListener('change',function(){if(!f.files.length)return;upload(f.files[0],function(url){inp.value=url;show()});f.value=''})});
/* Репитеры: добавить/удалить/переставить строки */
qa('[data-repeater]').forEach(function(r){var tpl=q('template',r),list=q('.rep-list',r),add=q('[data-add]',r);var next=parseInt(r.getAttribute('data-next')||'0',10);
 function bind(row){qa('[data-up]',row).forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();var p=row.previousElementSibling;if(p)list.insertBefore(row,p)})});
  qa('[data-down]',row).forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();var n=row.nextElementSibling;if(n)list.insertBefore(n,row)})});
  qa('[data-del]',row).forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();row.remove()})});
  var f=q('input[type=file]',row);if(f)f.addEventListener('change',function(){if(!f.files.length)return;upload(f.files[0],function(url){var u=q('input[data-url]',row);if(u)u.value=url;var im=q('img',row);if(im)im.src=url});f.value=''})}
 qa('.rep-row',list).forEach(bind);
 if(add)add.addEventListener('click',function(e){e.preventDefault();var html=tpl.innerHTML.replace(/__i__/g,String(next++));var d=document.createElement('div');d.innerHTML=html.trim();var row=d.firstElementChild;list.appendChild(row);bind(row)});
});
qa('[data-confirm]').forEach(function(f){f.addEventListener('submit',function(e){if(!confirm(f.getAttribute('data-confirm')))e.preventDefault()})});
/* --- Смены: даты из названий, автоназвания, следующая смена, генератор, сезон +1 --- */
var MSTEMS={'январ':1,'феврал':2,'март':3,'апрел':4,'мая':5,'май':5,'июн':6,'июл':7,'август':8,'сентябр':9,'октябр':10,'ноябр':11,'декабр':12};
function monthFromWord(w){w=w.toLowerCase();for(var k in MSTEMS)if(w.indexOf(k)===0)return MSTEMS[k];return null}
function isoDate(y,m,d){if(m<1||m>12||d<1||d>31)return null;return y+'-'+String(m).padStart(2,'0')+'-'+String(d).padStart(2,'0')}
function parseShift(name,defYear){var text=name.replace(/\u00a0/g,' ').replace(/\s+по\s+/gi,'–').replace(/^\s*с\s+/i,'');var ym=text.match(/\b(20\d{2})\b/g);var fy=ym?Number(ym[ym.length-1]):(defYear||null);
 var nums=[],re=/(\d{1,2})\.(\d{1,2})(?:\.(\d{4}|\d{2})(?!\d))?/g,m;while((m=re.exec(text)))nums.push(m);var yr=function(s){return !s?null:(s.length===2?2000+Number(s):Number(s))};
 if(nums.length>=2){var d1=+nums[0][1],m1=+nums[0][2],y1=yr(nums[0][3]),d2=+nums[1][1],m2=+nums[1][2],y2=yr(nums[1][3]);var ey=y2||fy;var sy=y1||(ey&&m1>m2?ey-1:ey);if(sy&&ey)return[isoDate(sy,m1,d1),isoDate(ey,m2,d2)]}
 var words=[],rw=/(\d{1,2})\s*(?:[–—-]\s*(\d{1,2})\s+)?([А-Яа-яЁё]{3,8})/g;while((m=rw.exec(text)))words.push(m);
 if(fy&&words.length){var f=words[0],mm1=monthFromWord(f[3]);if(mm1&&f[2])return[isoDate(fy,mm1,+f[1]),isoDate(fy,mm1,+f[2])];if(mm1&&words.length>=2){var mm2=monthFromWord(words[1][3]);if(mm2){var sy2=mm1>mm2?fy-1:fy;return[isoDate(sy2,mm1,+f[1]),isoDate(fy,mm2,+words[1][1])]}}}
 return[null,null]}
function utc(s){return Date.UTC(+s.slice(0,4),+s.slice(5,7)-1,+s.slice(8,10))}
function shDays(a,b){if(!a||!b)return 0;var x=utc(a),y=utc(b);return y<x?0:Math.round((y-x)/864e5)+1}
function plural(n,f){var m=n%10,h=n%100;return f[(h>10&&h<20)?2:(m===1?0:(m>1&&m<5?1:2))]}
function buildName(i,a,b){var n=shDays(a,b);return i+' смена '+a.slice(8,10)+'.'+a.slice(5,7)+'–'+b.slice(8,10)+'.'+b.slice(5,7)+'.'+b.slice(0,4)+' ['+n+' '+plural(n,['день','дня','дней'])+']'}
function addDays(s,n){var d=new Date(utc(s));d.setUTCDate(d.getUTCDate()+n);return d.toISOString().slice(0,10)}
function addYears(s,n){var y=+s.slice(0,4)+n,m=+s.slice(5,7),dd=+s.slice(8,10);var d=new Date(Date.UTC(y,m-1,dd));if(d.getUTCMonth()!==m-1)d.setUTCDate(0);return d.toISOString().slice(0,10)}
var sh=q('[data-shifts]');if(sh){var shList=q('.rep-list',sh),shTpl=q('template',sh),shNext=parseInt(sh.getAttribute('data-next')||'0',10),today=new Date().toISOString().slice(0,10);
 var tourYear=function(){var t=(q('input[name=season_text]')||{}).value||'',n=(q('input[name=title]')||{}).value||'',mm=(t+' '+n).match(/\b(20\d{2})\b/);return mm?Number(mm[1]):null};
 var rows=function(){return qa('tr.sh-row',shList)};var F=function(row,f){return q('[data-f='+f+']',row)};
 var idx=function(row){var g=F(row,'group').value,n=0,rs=rows();for(var i=0;i<rs.length;i++){if(rs[i]===row)break;if(F(rs[i],'group').value===g)n++}return n+1};
 var refresh=function(row){var a=F(row,'start').value,b=F(row,'end').value,n=shDays(a,b);q('[data-days]',row).textContent=n||'—';row.classList.toggle('past',!!b&&b<today);var av=F(row,'availability');av.className='avail-sel '+av.value};
 var onDates=function(row){var a=F(row,'start').value,b=F(row,'end').value;if(a&&b){var nm=F(row,'name').value;if(!nm.trim()||/смена/i.test(nm))F(row,'name').value=buildName(idx(row),a,b)}refresh(row);changed()};
 var addRow=function(after){var d=document.createElement('tbody');d.innerHTML=shTpl.innerHTML.replace(/__i__/g,String(shNext++)).trim();var row=d.firstElementChild;if(after&&after.nextSibling)shList.insertBefore(row,after.nextSibling);else shList.appendChild(row);bind(row);return row};
 var bind=function(row){F(row,'start').addEventListener('change',function(){onDates(row)});F(row,'end').addEventListener('change',function(){onDates(row)});
  /* Даты из названия: только когда распознаны обе, и пока даты не правили руками (иначе «21.0» на полпути ввода дало бы мусор) */
  var fromName=function(){var p=parseShift(F(row,'name').value,tourYear());if(p[0]&&p[1]&&(!F(row,'start').value||row.dataset.autoDates==='1')){F(row,'start').value=p[0];F(row,'end').value=p[1];row.dataset.autoDates='1';refresh(row)}};
  F(row,'name').addEventListener('input',fromName);F(row,'name').addEventListener('change',fromName);
  F(row,'start').addEventListener('input',function(){row.dataset.autoDates='0'});F(row,'end').addEventListener('input',function(){row.dataset.autoDates='0'});
  F(row,'availability').addEventListener('change',function(){refresh(row)});
  q('[data-up]',row).addEventListener('click',function(e){e.preventDefault();var p=row.previousElementSibling;if(p)shList.insertBefore(row,p)});
  q('[data-down]',row).addEventListener('click',function(e){e.preventDefault();var n=row.nextElementSibling;if(n)shList.insertBefore(n,row)});
  q('[data-del]',row).addEventListener('click',function(e){e.preventDefault();row.remove();changed()});
  q('[data-dup]',row).addEventListener('click',function(e){e.preventDefault();var r=addRow(row),a=F(row,'start').value,b=F(row,'end').value;['group','price','price_text'].forEach(function(f){F(r,f).value=F(row,f).value});
   if(a&&b){var len=shDays(a,b),na=addDays(b,1),nb=addDays(na,len-1);F(r,'start').value=na;F(r,'end').value=nb;F(r,'name').value=buildName(idx(r),na,nb)}else F(r,'name').value=F(row,'name').value.replace(/^(\d+)/,function(x){return String(Number(x)+1)});refresh(r);changed()});
  refresh(row)};
 rows().forEach(bind);
 q('[data-add]',sh).addEventListener('click',function(e){e.preventDefault();var r=addRow();F(r,'name').focus();changed()});
 q('[data-next-season]',sh).addEventListener('click',function(e){e.preventDefault();rows().forEach(function(r){['start','end'].forEach(function(f){if(F(r,f).value)F(r,f).value=addYears(F(r,f).value,1)});F(r,'name').value=F(r,'name').value.replace(/\b(20\d{2})\b/g,function(y){return String(Number(y)+1)});F(r,'availability').value='AVAILABLE';F(r,'seats_left').value='';refresh(r)});changed()});
 q('[data-apply-percent]',sh).addEventListener('click',function(e){e.preventDefault();var inp=q('[data-percent]',sh),p=Number(inp.value);if(!p)return;rows().forEach(function(r){var v=Number(F(r,'price').value);if(v>0)F(r,'price').value=String(Math.round(v*(1+p/100)/100)*100)});inp.value='';changed()});
 q('[data-generate]',sh).addEventListener('click',function(e){e.preventDefault();var g=function(n){return q('[data-gen='+n+']',sh).value};var start=g('start'),dn=parseInt(g('days'),10)||0,cnt=parseInt(g('count'),10)||0,gap=parseInt(g('gap'),10)||0,price=g('price'),group=g('group');if(!start||dn<1||cnt<1){alert('Укажите первый заезд, длину и количество смен.');return}
  var existing=rows().filter(function(r){return F(r,'group').value===group}).length;for(var i=0;i<cnt;i++){var end=addDays(start,dn-1),r=addRow();F(r,'group').value=group;F(r,'start').value=start;F(r,'end').value=end;F(r,'price').value=price;F(r,'name').value=buildName(existing+i+1,start,end);refresh(r);start=addDays(end,1+gap)}changed()});
}
/* --- Галерея: загрузка нескольких файлов, порядок, обложка --- */
var ge=q('[data-gallery-ed]');if(ge){var grid=q('.gal-grid',ge),gTpl=q('template',ge),gNext=parseInt(ge.getAttribute('data-next')||'0',10),coverInp=q('[data-cover-input]'),dz=q('.dropzone',ge),fileInp=q('input[type=file]',dz),dzText=q('[data-dz-text]',dz);
 var gItems=function(){return qa('.gal-item',grid)};
 var paintCover=function(){var c=coverInp?coverInp.value:'';gItems().forEach(function(it){var on=c!==''&&it.getAttribute('data-url')===c;it.classList.toggle('is-cover',on);q('.gal-cover',it).hidden=!on});var prev=q('[data-cover-preview]');if(prev)prev.src=c||'/images/placeholder.svg'};
 var setCover=function(u){if(coverInp)coverInp.value=u;paintCover();changed()};
 var gBind=function(it){q('[data-left]',it).addEventListener('click',function(e){e.preventDefault();var p=it.previousElementSibling;if(p)grid.insertBefore(it,p)});q('[data-right]',it).addEventListener('click',function(e){e.preventDefault();var n=it.nextElementSibling;if(n)grid.insertBefore(n,it)});
  q('[data-cover]',it).addEventListener('click',function(e){e.preventDefault();setCover(it.getAttribute('data-url'))});
  q('[data-del]',it).addEventListener('click',function(e){e.preventDefault();var was=it.classList.contains('is-cover');it.remove();if(was){var f=gItems()[0];setCover(f?f.getAttribute('data-url'):'')}changed()})};
 var addImg=function(url){var d=document.createElement('div');d.innerHTML=gTpl.innerHTML.replace(/__i__/g,String(gNext++)).replace(/__url__/g,'').trim();var it=d.firstElementChild;it.setAttribute('data-url',url);q('img',it).src=url;q('input[type=hidden]',it).value=url;grid.appendChild(it);gBind(it);if(coverInp&&!coverInp.value)coverInp.value=url;paintCover();changed()};
 gItems().forEach(gBind);paintCover();if(coverInp)coverInp.addEventListener('input',paintCover);
 var uploadAll=function(files){var list=Array.prototype.slice.call(files),i=0;var step=function(){if(i>=list.length){dzText.textContent=dzText.getAttribute('data-idle');dz.classList.remove('busy');return}dzText.textContent='Загружаем '+(i+1)+' из '+list.length+'…';dz.classList.add('busy');upload(list[i],function(url){addImg(url);i++;step()},function(err){alert(list[i].name+': '+err);i++;step()})};step()};
 fileInp.addEventListener('change',function(){if(fileInp.files.length)uploadAll(fileInp.files);fileInp.value=''});
 dz.addEventListener('dragover',function(e){e.preventDefault();dz.classList.add('over')});dz.addEventListener('dragleave',function(){dz.classList.remove('over')});dz.addEventListener('drop',function(e){e.preventDefault();dz.classList.remove('over');if(e.dataTransfer.files.length)uploadAll(e.dataTransfer.files)});
}
/* --- Редактор тура: вкладки-шаги, предупреждения, статус, шаблоны разделов --- */
var ed=q('[data-tour-editor]');if(ed){var titleInp=q('input[name=title]',ed),warnEl=q('[data-ed-warn]',ed),titleEl=q('[data-ed-title]',ed);
 var warn=function(){var w=[];if(!titleInp.value.trim())w.push('нет названия');var imgs=qa('.gal-item',ed).length,cover=(q('[data-cover-input]',ed)||{}).value||'';if(!imgs&&!cover)w.push('нет фото');
  var shifts=qa('tr.sh-row',ed);if(!shifts.length)w.push('нет смен');else if(!shifts.some(function(r){return Number(q('[data-f=price]',r).value)>0})&&!(q('input[name=price_min]',ed)||{}).value&&!(q('input[name=price_text]',ed)||{}).value)w.push('нет цен');
  var desc=q('[data-section=description] .wys',ed);if(desc&&!desc.textContent.trim()&&!q('img',desc))w.push('нет описания');
  warnEl.textContent=w.length?'⚠ '+w.join(' · '):'✓ Всё необходимое заполнено';warnEl.classList.toggle('ok',!w.length);if(titleEl)titleEl.textContent=titleInp.value.trim()||'Новый тур';
  var c1=q('[data-tab-count=shifts]',ed),c2=q('[data-tab-count=photos]',ed);if(c1)c1.textContent=shifts.length?'('+shifts.length+')':'';if(c2)c2.textContent=imgs?'('+imgs+')':''};
 window.admChanged=warn;ed.addEventListener('input',warn);ed.addEventListener('change',warn);warn();
 var tabs=qa('.tabs button[data-tab]',ed),panels=qa('.tabp',ed),uiInp=q('input[name=ui]',ed),nextBtn=q('[data-tab-next]',ed);
 var show=function(k){tabs.forEach(function(b){b.classList.toggle('on',b.getAttribute('data-tab')===k)});panels.forEach(function(p){p.hidden=p.getAttribute('data-tabp')!==k});if(uiInp)uiInp.value=k;
  var i=tabs.map(function(b){return b.getAttribute('data-tab')}).indexOf(k);if(nextBtn){nextBtn.hidden=i>=tabs.length-1;if(!nextBtn.hidden)nextBtn.textContent='Далее: '+tabs[i+1].textContent.replace(/^\d+\.\s*/,'').replace(/\s*\(\d+\)\s*$/,'').trim()+' →'}};
 tabs.forEach(function(b){b.addEventListener('click',function(){show(b.getAttribute('data-tab'))})});
 if(nextBtn)nextBtn.addEventListener('click',function(){var i=tabs.map(function(b){return b.classList.contains('on')}).indexOf(true);if(i<tabs.length-1){show(tabs[i+1].getAttribute('data-tab'));window.scrollTo(0,0)}});
 show(ed.getAttribute('data-ui')||'basic');
 qa('.status-pick input',ed).forEach(function(r){r.addEventListener('change',function(){qa('.status-pick label',ed).forEach(function(l){l.classList.toggle('on',q('input',l).checked)})})});
 qa('[data-tpl]',ed).forEach(function(b){b.addEventListener('click',function(e){e.preventDefault();var sec=b.closest('[data-section]'),w=q('.wys',sec),ta=q('textarea',sec);if(w){w.innerHTML=b.getAttribute('data-tpl');ta.value=w.innerHTML;b.hidden=true;warn()}})});
 ed.addEventListener('keydown',function(e){if(e.key==='Enter'&&e.target.tagName==='INPUT'&&e.target.type!=='submit'&&!e.target.closest('.wys')){e.preventDefault()}});
}
/* Формы, которые отправляются сами при изменении (наличие мест в календаре смен) */
qa('form[data-autosubmit]').forEach(function(f){qa('select,input[type=number]',f).forEach(function(i){i.addEventListener('change',function(){f.submit()})})});
/* Массовые действия в списке: панель появляется, когда отмечены строки */
var bulk=q('[data-bulk]');if(bulk){var boxes=qa('input[data-sel]'),all=q('input[data-sel-all]'),cnt=q('[data-bulk-count]',bulk),idsInp=q('input[name=ids]',bulk);
 function refresh(){var sel=boxes.filter(function(b){return b.checked}).map(function(b){return b.value});bulk.classList.toggle('on',sel.length>0);if(cnt)cnt.textContent=sel.length;if(idsInp)idsInp.value=sel.join(',');if(all)all.checked=sel.length===boxes.length&&boxes.length>0}
 boxes.forEach(function(b){b.addEventListener('change',refresh)});if(all)all.addEventListener('change',function(){boxes.forEach(function(b){b.checked=all.checked});refresh()});
 qa('button[data-confirm]',bulk).forEach(function(b){b.addEventListener('click',function(e){if(!confirm(b.getAttribute('data-confirm').replace('%n',String(idsInp.value.split(',').filter(Boolean).length))))e.preventDefault()})});refresh()}
})();
JS;
}

/** Наличие мест у смены — цветная плашка. */
const ADM_AVAIL = ['AVAILABLE' => 'Есть места', 'FEW' => 'Мало мест', 'SOLD_OUT' => 'Мест нет'];
function adm_avail_chip(string $code, ?int $seats = null): string
{
    $code = isset(ADM_AVAIL[$code]) ? $code : 'AVAILABLE';
    return '<span class="avail ' . $code . '">' . ADM_AVAIL[$code] . ($seats !== null && $code !== 'SOLD_OUT' ? ' · ' . $seats : '') . '</span>';
}

// --- Поля форм --------------------------------------------------------------------

function adm_attrs(array $a): string
{
    $s = '';
    foreach ($a as $k => $v) {
        if ($v === false || $v === null) {
            continue;
        }
        $s .= $v === true ? ' ' . $k : ' ' . $k . '="' . adm_e((string) $v) . '"';
    }
    return $s;
}

function adm_label(string $label, string $inner, string $hint = ''): string
{
    return '<label>' . adm_e($label) . ($hint !== '' ? ' <span class="hint">' . adm_e($hint) . '</span>' : '') . $inner . '</label>';
}

function adm_input(string $name, mixed $value, string $label, array $a = [], string $hint = ''): string
{
    $a += ['type' => 'text'];
    return adm_label($label, '<input name="' . adm_e($name) . '" value="' . adm_e((string) $value) . '"' . adm_attrs($a) . '>', $hint);
}

function adm_textarea(string $name, mixed $value, string $label, array $a = [], string $hint = ''): string
{
    return adm_label($label, '<textarea name="' . adm_e($name) . '"' . adm_attrs($a) . '>' . adm_e((string) $value) . '</textarea>', $hint);
}

/** @param array<string,string> $options */
function adm_select(string $name, mixed $value, string $label, array $options, string $hint = ''): string
{
    $h = '<select name="' . adm_e($name) . '">';
    foreach ($options as $k => $v) {
        $h .= '<option value="' . adm_e((string) $k) . '"' . ((string) $k === (string) $value ? ' selected' : '') . '>' . adm_e($v) . '</option>';
    }
    return adm_label($label, $h . '</select>', $hint);
}

function adm_checkbox(string $name, bool $checked, string $label, string $value = '1'): string
{
    return '<label class="inline"><input type="checkbox" name="' . adm_e($name) . '" value="' . adm_e($value) . '"' . ($checked ? ' checked' : '') . '> ' . adm_e($label) . '</label>';
}

function adm_image(string $name, mixed $value, string $label, string $hint = ''): string
{
    return adm_label($label, '<div class="img-field" data-img-field><img src="' . adm_e(at_img_src((string) $value)) . '" alt=""><input type="text" name="' . adm_e($name) . '" value="' . adm_e((string) $value) . '" placeholder="/uploads/… или /media.php?id=N"><input type="file" accept="image/*"></div>', $hint);
}

function adm_html(string $name, mixed $value, string $label, int $rows = 10): string
{
    $tb = '<div class="tb">'
        . '<button data-c="bold"><b>Ж</b></button><button data-c="italic"><i>К</i></button><button data-c="underline"><u>Ч</u></button>'
        . '<button data-c="formatBlock" data-v="h2">H2</button><button data-c="formatBlock" data-v="h3">H3</button><button data-c="formatBlock" data-v="p">Абзац</button>'
        . '<button data-c="insertUnorderedList">• Список</button><button data-c="insertOrderedList">1. Список</button>'
        . '<button data-c="link">Ссылка</button><button data-c="img">Картинка (файл)</button><button data-c="imgurl">Картинка (адрес)</button>'
        . '<button data-c="removeFormat">Очистить</button><button data-c="src">HTML</button></div>';
    return '<div data-wys>' . adm_label($label, '') . $tb . '<div class="wys" contenteditable="true">' . (string) $value . '</div>'
        . '<textarea name="' . adm_e($name) . '" style="display:none;min-height:' . ($rows * 18) . 'px">' . adm_e((string) $value) . '</textarea><input type="file" accept="image/*" style="display:none"></div>';
}

function adm_form_open(string $action = 'save', array $extra = []): string
{
    $h = '<form method="post"><input type="hidden" name="csrf" value="' . adm_e(adm_csrf()) . '"><input type="hidden" name="action" value="' . adm_e($action) . '">';
    foreach ($extra as $k => $v) {
        $h .= '<input type="hidden" name="' . adm_e($k) . '" value="' . adm_e((string) $v) . '">';
    }
    return $h;
}

function adm_delete_form(array $extra, string $label = 'Удалить', string $confirm = 'Удалить безвозвратно?'): string
{
    return '<form method="post" data-confirm="' . adm_e($confirm) . '" style="display:inline"><input type="hidden" name="csrf" value="' . adm_e(adm_csrf()) . '"><input type="hidden" name="action" value="delete">'
        . implode('', array_map(static fn($k, $v): string => '<input type="hidden" name="' . adm_e((string) $k) . '" value="' . adm_e((string) $v) . '">', array_keys($extra), $extra))
        . '<button class="btn d sm">' . adm_e($label) . '</button></form>';
}

function adm_pages(int $page, int $pages, string $tab, array $params = []): string
{
    if ($pages <= 1) {
        return '';
    }
    $h = '<div class="pages">';
    for ($p = 1; $p <= $pages; $p++) {
        $h .= $p === $page ? '<span>' . $p . '</span>' : '<a href="' . adm_url($tab, $params + ['page' => $p]) . '">' . $p . '</a>';
    }
    return $h . '</div>';
}

// --- Разбор значений формы ------------------------------------------------------

function adm_str(string $key, int $max = 5000, bool $nullable = false): ?string
{
    $v = trim((string) (is_array($_POST[$key] ?? null) ? '' : ($_POST[$key] ?? '')));
    $v = mb_substr($v, 0, $max);
    return $v === '' && $nullable ? null : $v;
}

function adm_int(string $key): ?int
{
    $v = trim((string) ($_POST[$key] ?? ''));
    return $v === '' || !preg_match('~^-?\d+$~', $v) ? null : (int) $v;
}

function adm_bool(string $key): int
{
    return !empty($_POST[$key]) ? 1 : 0;
}

/** «2026-06-01T10:00» из datetime-local → «2026-06-01 10:00:00»; пусто → null. */
function adm_datetime(string $key): ?string
{
    $v = trim((string) ($_POST[$key] ?? ''));
    if ($v === '') {
        return null;
    }
    $ts = strtotime($v);
    return $ts === false ? null : date('Y-m-d H:i:s', $ts);
}

function adm_dt_value(?string $db): string
{
    $ts = at_ts($db);
    return $ts === null ? '' : date('Y-m-d\TH:i', $ts);
}

/** @return array<int,string> значения отмеченных чекбоксов name[] */
function adm_list(string $key): array
{
    $v = $_POST[$key] ?? [];
    return is_array($v) ? array_values(array_filter(array_map(static fn($x): string => trim((string) $x), $v), static fn(string $x): bool => $x !== '')) : [];
}

// --- Загрузка документов (PDF, Word, Excel) в таблицу media --------------------------

/** @return array{ok:bool,url?:string,error?:string} */
function adm_upload_file(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Файл не передан или больше 8 МБ (лимит сервера).'];
    }
    if ((int) $file['size'] > 8 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Файл больше 8 МБ.'];
    }
    $types = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    $name = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!isset($types[$ext])) {
        return ['ok' => false, 'error' => 'Можно загружать PDF, Word (doc, docx) и Excel (xls, xlsx).'];
    }
    $data = (string) file_get_contents((string) $file['tmp_name']);
    // PDF и офисные файлы узнаваемы по первым байтам — картинку или скрипт под видом документа не примем.
    if (($ext === 'pdf' && !str_starts_with($data, '%PDF')) || (in_array($ext, ['docx', 'xlsx'], true) && !str_starts_with($data, 'PK')) || (in_array($ext, ['doc', 'xls'], true) && !str_starts_with($data, "\xD0\xCF\x11\xE0"))) {
        return ['ok' => false, 'error' => 'Содержимое файла не похоже на ' . strtoupper($ext) . '.'];
    }
    $st = db()->prepare('INSERT INTO media (mime, name, size, data) VALUES (?, ?, ?, ?)');
    $st->bindValue(1, $types[$ext]);
    $st->bindValue(2, mb_substr($name, 0, 200));
    $st->bindValue(3, strlen($data), PDO::PARAM_INT);
    $st->bindValue(4, $data, PDO::PARAM_LOB);
    $st->execute();
    return ['ok' => true, 'url' => '/media.php?id=' . (int) db()->lastInsertId()];
}

// --- Загрузка картинок в таблицу media ---------------------------------------------

/** @return array{ok:bool,url?:string,error?:string} */
function adm_upload_image(array $file): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Файл не передан или больше 8 МБ (лимит сервера).'];
    }
    $tmp = (string) $file['tmp_name'];
    if ((int) $file['size'] > 8 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'Файл больше 8 МБ.'];
    }
    $info = @getimagesize($tmp);
    if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        return ['ok' => false, 'error' => 'Можно загружать только изображения (JPEG, PNG, WebP, GIF).'];
    }
    $data = (string) file_get_contents($tmp);
    $mime = (string) $info['mime'];
    // Уменьшаем до 1920 px по ширине и переводим в WebP, если есть GD.
    if (function_exists('imagecreatefromstring') && function_exists('imagewebp') && $info[2] !== IMAGETYPE_GIF) {
        $im = @imagecreatefromstring($data);
        if ($im !== false) {
            $w = imagesx($im);
            $h = imagesy($im);
            if ($w > 1920) {
                $nh = (int) round($h * 1920 / $w);
                $res = imagecreatetruecolor(1920, $nh);
                imagealphablending($res, false);
                imagesavealpha($res, true);
                imagecopyresampled($res, $im, 0, 0, 0, 0, 1920, $nh, $w, $h);
                imagedestroy($im);
                $im = $res;
            }
            ob_start();
            imagewebp($im, null, 82);
            $webp = (string) ob_get_clean();
            imagedestroy($im);
            if ($webp !== '') {
                $data = $webp;
                $mime = 'image/webp';
            }
        }
    }
    $st = db()->prepare('INSERT INTO media (mime, name, size, data) VALUES (?, ?, ?, ?)');
    $st->bindValue(1, $mime);
    $st->bindValue(2, mb_substr((string) ($file['name'] ?? ''), 0, 200));
    $st->bindValue(3, strlen($data), PDO::PARAM_INT);
    $st->bindValue(4, $data, PDO::PARAM_LOB);
    $st->execute();
    return ['ok' => true, 'url' => '/media.php?id=' . (int) db()->lastInsertId()];
}
