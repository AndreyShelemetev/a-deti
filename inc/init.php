<?php
/**
 * А-тур — общая основа сайта. Платформа подключает этот файл ко ВСЕМ страницам
 * (db.php → inc/init.php), поэтому здесь нет вывода — только определения.
 *
 * Что здесь: справочники (типы туров, сезоны, статусы), схема БД (создаётся один
 * раз, номер версии хранится в atour_settings), первичное наполнение из
 * data/seed.json и хелперы: экранирование, цены, даты, склонения, ЧПУ,
 * настройки сайта, адреса. Подключение к базе — только через готовую db().
 */
declare(strict_types=1);

if (defined('ATOUR_INIT')) {
    return;
}
define('ATOUR_INIT', 1);
define('ATOUR_ROOT', dirname(__DIR__));
define('ATOUR_SCHEMA_VERSION', 9);
define('ATOUR_SITE_NAME', 'А-тур');
define('ATOUR_PLACEHOLDER', '/images/placeholder.svg');

/** Онлайн-оплата приостановлена на время проверки подключения платёжного сервиса. */
function at_payment_enabled(): bool
{
    return false;
}

/** Общий фильтр ссылок: настройки меню и старые данные при этом не изменяются. */
function at_payment_link_visible(string $href): bool
{
    if (at_payment_enabled()) return true;
    $path = parse_url(html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8'), PHP_URL_PATH);
    if (!is_string($path)) return true;
    $path = rawurldecode($path);
    $path = '/' . ltrim((string) preg_replace('~/{2,}~', '/', $path), '/');
    return preg_match('~^/pay(?:/|$)~i', $path) !== 1;
}

const ATOUR_TOUR_TYPES = [
    'CAMP'      => 'Лагеря отдыха',
    'FAMILY'    => 'Семейный отдых',
    'SPORT'     => 'Спортивные сборы',
    'EXCURSION' => 'Экскурсионные туры',
    'TRANSFER'  => 'Авиа и ж/д',
    'YOUTH'     => 'Молодёжные программы',
];
const ATOUR_TYPE_PATHS = [
    'CAMP'      => '/tour/filter/lager/',
    'FAMILY'    => '/tour/filter/family-tours/',
    'SPORT'     => '/tour/filter/sport/',
    'EXCURSION' => '/tour/filter/excursions/',
    'TRANSFER'  => '/tour/filter/transfer/',
    'YOUTH'     => '/tour/?type=YOUTH',
];
const ATOUR_SEASONS     = ['SPRING' => 'Весна', 'SUMMER' => 'Лето', 'AUTUMN' => 'Осень', 'WINTER' => 'Зима'];
const ATOUR_SEASON_TONE = ['SPRING' => 'mint', 'SUMMER' => 'sun', 'AUTUMN' => 'coral', 'WINTER' => 'brand'];
const ATOUR_REQUEST_KINDS = [
    'REQUEST'   => 'Заявка с сайта',
    'CALLBACK'  => 'Заказ обратного звонка',
    'BOOKING'   => 'Бронирование тура',
    'SUBSCRIBE' => 'Подписка на новости',
    'AGENCY'    => 'Запрос доступа для агентства',
    'REVIEW'    => 'Отзыв на модерацию',
];
const ATOUR_REQUEST_STATUSES = ['NEW' => 'Новая', 'IN_PROGRESS' => 'В работе', 'DONE' => 'Закрыта', 'SPAM' => 'Спам'];
const ATOUR_PAYMENT_STATUSES = ['NEW' => 'Новый', 'REDIRECTED' => 'Передан в шлюз', 'PAID' => 'Оплачен', 'FAILED' => 'Ошибка', 'CANCELLED' => 'Отменён'];
const ATOUR_REVIEW_SOURCES   = ['YANDEX' => 'Яндекс', 'GOOGLE' => 'Google', 'SITE' => 'Сайт'];
const ATOUR_SECTION_TITLES = [
    'description'           => 'Описание',
    'residence'             => 'Размещение',
    'location'              => 'Расположение',
    'nutrition'             => 'Питание',
    'infrastructure'        => 'Инфраструктура',
    'safety'                => 'Безопасность и медицина',
    'beach'                 => 'Пляж',
    'transfer'              => 'Трансфер',
    'camp_facts'            => 'Характеристики лагеря',
    'programm'              => 'Программа',
    'payment_smeny'         => 'Стоимость смен',
    'otpravka_children'     => 'Организованная отправка детей',
    'for_parents'           => 'Документы',
    'for_contraindications' => 'Противопоказания',
];
const ATOUR_MONTHS = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];

// --- Экранирование и текст ---------------------------------------------------

function at_e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Год опубликованного каталога меняется владельцем, а не календарём сервера. */
function at_catalog_year(): int
{
    $year = at_setting('catalog.year', '2026');
    // 2026 — год исходного опубликованного каталога, а не текущая дата.
    return preg_match('~^20\d{2}$~D', $year) ? (int) $year : 2026;
}

/** «{year}» в SEO-шаблонах: явный год тура либо вручную выбранный год каталога. */
function at_year(?string $text, ?int $year = null): string
{
    return str_replace('{year}', (string) ($year ?? at_catalog_year()), (string) $text);
}

/** 72900 → «72 900 ₽» (неразрывные пробелы). Пусто для null/''. */
function at_price(int|float|string|null $value, string $currency = '₽'): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $n = number_format((float) $value, 0, '', ' ');
    return str_replace(' ', "\u{00A0}", $n) . "\u{00A0}" . $currency;
}

/** at_plural(5, ['тур', 'тура', 'туров']) → «туров». */
function at_plural(int $n, array $forms): string
{
    $abs = abs($n) % 100;
    $last = $abs % 10;
    if ($abs > 10 && $abs < 20) {
        return $forms[2];
    }
    if ($last > 1 && $last < 5) {
        return $forms[1];
    }
    if ($last === 1) {
        return $forms[0];
    }
    return $forms[2];
}

/** Дата по-русски: «21 июня 2022». Принимает строку из БД или timestamp. */
function at_date(string|int|null $value): string
{
    $ts = at_ts($value);
    if ($ts === null) {
        return '';
    }
    return date('j', $ts) . ' ' . ATOUR_MONTHS[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

/** Даты смены словами — если заданы и не продублированы в названии («1 смена 01.06–21.06.2026»). */
function at_shift_dates(array $sh): string
{
    if (empty($sh['start_date']) || preg_match('~\d{1,2}\.\d{2}~', (string) ($sh['name'] ?? ''))) {
        return '';
    }
    return at_date_range((string) $sh['start_date'], (string) ($sh['end_date'] ?? ''));
}

/** Наличие мест у смены: [код, подпись для посетителя]; пустая подпись — места есть. */
function at_shift_avail(array $sh): array
{
    $code = (string) ($sh['availability'] ?? 'AVAILABLE');
    return [$code, match ($code) { 'FEW' => 'мало мест', 'SOLD_OUT' => 'мест нет', default => '' }];
}

/** Цена смены текстом: своё поле («от 120 000 ₽») или число. */
function at_shift_price(array $sh): string
{
    $t = trim((string) ($sh['price_text'] ?? ''));
    if ($t !== '') {
        return $t;
    }
    return !empty($sh['price']) ? at_price((int) $sh['price']) : '';
}

/** Диапазон дат по-русски: «27 октября – 1 ноября 2026», «1–21 июня 2026», «27 декабря 2026 – 5 января 2027». */
function at_date_range(string|int|null $from, string|int|null $to): string
{
    $a = at_ts($from);
    $b = at_ts($to);
    if ($a === null) {
        return $b === null ? '' : at_date($b);
    }
    if ($b === null || $b <= $a) {
        return at_date($a);
    }
    $monthA = ATOUR_MONTHS[(int) date('n', $a) - 1];
    if (date('Y-m', $a) === date('Y-m', $b)) {
        return date('j', $a) . '–' . date('j', $b) . ' ' . $monthA . ' ' . date('Y', $b);
    }
    if (date('Y', $a) === date('Y', $b)) {
        return date('j', $a) . ' ' . $monthA . ' – ' . at_date($b);
    }
    return at_date($a) . ' – ' . at_date($b);
}

// --- Смены: даты из названий, автоназвания, сдвиг на следующий сезон ---------------

/** Начало русского названия месяца → номер («июн» → 6, «мая» → 5). */
const ATOUR_MONTH_STEMS = ['январ' => 1, 'феврал' => 2, 'март' => 3, 'апрел' => 4, 'мая' => 5, 'май' => 5, 'июн' => 6, 'июл' => 7,
    'август' => 8, 'сентябр' => 9, 'октябр' => 10, 'ноябр' => 11, 'декабр' => 12];

function at_month_from_word(string $word): ?int
{
    $w = mb_strtolower($word);
    foreach (ATOUR_MONTH_STEMS as $stem => $n) {
        if (str_starts_with($w, $stem)) {
            return $n;
        }
    }
    return null;
}

function at_iso_date(int $y, int $m, int $d): ?string
{
    return $m < 1 || $m > 12 || $d < 1 || $d > 31 ? null : sprintf('%04d-%02d-%02d', $y, $m, $d);
}

/**
 * Даты заезда из свободного названия смены: «1 смена 01.06-21.06.2026 [21 день]»,
 * «6 смена (30.05.2026 - 19.06.2026)», «2 смена (24.06-14.07) 2026», «01 июня – 21 июня 2026»,
 * «с 3 по 23 июля 2026», «28 декабря – 5 января 2027». Год берётся последний из
 * названия; если месяц начала больше месяца конца — заезд через Новый год.
 * @return array{0:?string,1:?string} [начало, конец] в формате Y-m-d или [null, null]
 */
function at_shift_parse_dates(string $name, ?int $defaultYear = null): array
{
    $text = str_replace("\u{a0}", ' ', $name);
    $text = (string) preg_replace('~\s+по\s+~iu', '–', $text);
    $text = (string) preg_replace('~^\s*с\s+~iu', '', $text);
    preg_match_all('~\b(20\d{2})\b~', $text, $ym);
    $fallbackYear = $ym[1] ? (int) end($ym[1]) : $defaultYear;
    // Числовые даты: дд.мм(.гггг|.гг) … дд.мм(.гггг|.гг); «23.06.26» — 2026
    $year = static fn(string $y): ?int => $y === '' ? null : (strlen($y) === 2 ? 2000 + (int) $y : (int) $y);
    preg_match_all('~(\d{1,2})\.(\d{1,2})(?:\.(\d{4}|\d{2})(?!\d))?~', $text, $nums, PREG_SET_ORDER);
    if (count($nums) >= 2) {
        [$d1, $m1, $y1] = [(int) $nums[0][1], (int) $nums[0][2], $year($nums[0][3] ?? '')];
        [$d2, $m2, $y2] = [(int) $nums[1][1], (int) $nums[1][2], $year($nums[1][3] ?? '')];
        $endYear = $y2 ?? $fallbackYear;
        $startYear = $y1 ?? ($endYear !== null && $m1 > $m2 ? $endYear - 1 : $endYear);
        if ($startYear !== null && $endYear !== null) {
            return [at_iso_date($startYear, $m1, $d1), at_iso_date($endYear, $m2, $d2)];
        }
    }
    // Словами: «01 июня – 21 июня 2026», «3–23 июля 2026»
    preg_match_all('~(\d{1,2})\s*(?:[–—-]\s*(\d{1,2})\s+)?([А-Яа-яЁё]{3,8})~u', $text, $words, PREG_SET_ORDER);
    if ($fallbackYear !== null && $words) {
        $first = $words[0];
        $m1 = at_month_from_word($first[3]);
        if ($m1 !== null && ($first[2] ?? '') !== '') {
            return [at_iso_date($fallbackYear, $m1, (int) $first[1]), at_iso_date($fallbackYear, $m1, (int) $first[2])];
        }
        if ($m1 !== null && count($words) >= 2) {
            $m2 = at_month_from_word($words[1][3]);
            if ($m2 !== null) {
                $startYear = $m1 > $m2 ? $fallbackYear - 1 : $fallbackYear;
                return [at_iso_date($startYear, $m1, (int) $first[1]), at_iso_date($fallbackYear, $m2, (int) $words[1][1])];
            }
        }
    }
    return [null, null];
}

/** Год тура для смен без года в названии: первый 20xx из подписи сезона или названия тура. */
function at_tour_year(?string $seasonText, ?string $title): ?int
{
    return preg_match('~\b(20\d{2})\b~', (string) $seasonText . ' ' . (string) $title, $m) ? (int) $m[1] : null;
}

/** Длительность заезда в днях, включая день выезда: 01.06–21.06 → 21. */
function at_shift_days(?string $start, ?string $end): int
{
    $a = at_ts($start);
    $b = at_ts($end);
    return $a === null || $b === null || $b < $a ? 0 : (int) round(($b - $a) / 86400) + 1;
}

/** «3 смена 13.07–02.08.2026 [21 день]» */
function at_shift_build_name(int $index, string $start, string $end, string $label = 'смена'): string
{
    $days = at_shift_days($start, $end);
    return $index . ' ' . $label . ' ' . date('d.m', (int) at_ts($start)) . '–' . date('d.m.Y', (int) at_ts($end))
        . ' [' . $days . ' ' . at_plural($days, ['день', 'дня', 'дней']) . ']';
}

/** Дата плюс N лет (Y-m-d); null остаётся null. */
function at_add_years(?string $date, int $years): ?string
{
    $ts = at_ts($date);
    return $ts === null ? null : date('Y-m-d', mktime(12, 0, 0, (int) date('n', $ts), (int) date('j', $ts), (int) date('Y', $ts) + $years));
}

/** Все годы 20xx в тексте +1: «лето 2026» → «лето 2027». */
function at_bump_years(?string $text): ?string
{
    return $text === null ? null : (string) preg_replace_callback('~\b(20\d{2})\b~', static fn(array $m): string => (string) ((int) $m[1] + 1), $text);
}

function at_date_short(string|int|null $value): string
{
    $ts = at_ts($value);
    return $ts === null ? '' : date('d.m.Y', $ts);
}

function at_datetime(string|int|null $value): string
{
    $ts = at_ts($value);
    return $ts === null ? '' : date('d.m.Y H:i', $ts);
}

function at_iso(string|int|null $value): string
{
    $ts = at_ts($value);
    return $ts === null ? '' : date('c', $ts);
}

function at_ts(string|int|null $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value)) {
        return $value;
    }
    $ts = strtotime($value);
    return $ts === false ? null : $ts;
}

function at_strip_html(string $html): string
{
    $t = preg_replace('~<[^>]+>~', ' ', $html) ?? '';
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) preg_replace('~\s+~u', ' ', $t));
}

function at_truncate(string $text, int $max = 160): string
{
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max);
    $idx = mb_strrpos($cut, ' ');
    if ($idx !== false && $idx > $max * 0.5) {
        $cut = mb_substr($cut, 0, $idx);
    }
    return $cut . '…';
}

/** «Детский лагерь «Вита»» → detskiy-lager-vita (a–z, 0–9, дефис; до 120 символов). */
function at_slugify(string $text): string
{
    static $map = [
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ъ' => '', 'ы' => 'y', 'ь' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    ];
    $s = strtr(mb_strtolower($text), $map);
    $s = (string) preg_replace('~[^a-z0-9]+~', '-', $s);
    return mb_substr(trim($s, '-'), 0, 120);
}

/** 8 (903) 629-06-21 → +79036290621. */
function at_normalize_phone(string $phone): string
{
    $digits = (string) preg_replace('~\D~', '', $phone);
    if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
        return '+7' . substr($digits, 1);
    }
    if (strlen($digits) === 10) {
        return '+7' . $digits;
    }
    return trim($phone);
}

function at_tel_href(string $phone): string
{
    return 'tel:' . preg_replace('~[^\d+]~', '', $phone);
}

/** Список через запятую (сезоны, слаги) ↔ массив. */
function at_split(?string $csv): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string) $csv)), static fn(string $s): bool => $s !== ''));
}

function at_join(array $list): string
{
    return implode(',', array_values(array_filter(array_map('strval', $list), static fn(string $s): bool => $s !== '')));
}

/** Картинка: пустой путь → заглушка. */
function at_img_src(?string $src): string
{
    $src = trim((string) $src);
    if ($src === '') return ATOUR_PLACEHOLDER;
    require_once __DIR__ . '/source-media.php';
    return at_source_media_resolve($src);
}

function at_json_ld(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return '<script type="application/ld+json">' . str_replace('<', '\u003c', (string) $json) . '</script>' . "\n";
}

// --- Адреса ------------------------------------------------------------------

function at_is_https(): bool
{
    return (($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** https://хост — без завершающего слэша. */
function at_base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = (string) preg_replace('~[^A-Za-z0-9.:\-]~', '', $host);
    $base = (at_is_https() ? 'https' : 'http') . '://' . $host;
    return $base;
}

function at_abs(string $path): string
{
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }
    return at_base_url() . ($path !== '' && $path[0] === '/' ? $path : '/' . $path);
}

/** Путь текущего запроса без query-строки, всегда с ведущим слэшем. */
function at_current_path(): string
{
    $p = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return $p === '' ? '/' : $p;
}

function at_redirect(string $to, int $code = 302): never
{
    $to = str_replace(["\r", "\n"], '', $to);
    header('Location: ' . $to, true, $code);
    exit;
}

// --- Настройки сайта (таблица atour_settings) --------------------------------

/** Исправляет термин в отображении, не меняя сохранённый текст владельца. */
function at_transfer_copy(string $text): string
{
    if (trim($text) === '' || mb_strtolower(trim($text)) === 'приобрести групповой авиабилет') {
        return 'Организуем централизованный трансфер детей в «Орлёнок», «Артек», лагеря Анапы и Крыма: билеты, сопровождение, питание в дороге.';
    }
    return str_ireplace(['централизованную доставку','доставку детей'], ['централизованный трансфер','трансфер детей'], $text);
}

/** @return array<string,string> */
function at_settings(bool $reload = false): array
{
    static $cache = null;
    if ($cache !== null && !$reload) {
        return $cache;
    }
    $cache = [];
    try {
        foreach (db()->query('SELECT k, v FROM atour_settings')->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $v) {
            $cache[(string) $k] = (string) $v;
        }
    } catch (\Throwable $e) {
        // до установки схемы настроек нет — вернём пустой набор
    }
    $cache['home.ticketText'] = at_transfer_copy($cache['home.ticketText'] ?? '');
    return $cache;
}

function at_setting(string $key, string $default = ''): string
{
    $s = at_settings();
    $v = $s[$key] ?? '';
    return $v !== '' ? $v : $default;
}

/**
 * Оформление сайта: стиль (bright — яркий, simple — строгий) и тема (light / dark).
 * Выбор посетителя — в cookie at_style / at_theme (их пишет переключатель в шапке),
 * без cookie — значения по умолчанию из настроек design.style / design.theme.
 * Тёмная тема есть только у строгого стиля.
 * @return array{style:string,theme:string}
 */
function at_look(): array
{
    static $look = null;
    if ($look !== null) {
        return $look;
    }
    $style = (string) ($_COOKIE['at_style'] ?? '');
    if (!in_array($style, ['bright', 'simple'], true)) {
        $style = at_setting('design.style', 'bright');
        if (!in_array($style, ['bright', 'simple'], true)) {
            $style = 'bright';
        }
    }
    $theme = (string) ($_COOKIE['at_theme'] ?? '');
    if (!in_array($theme, ['light', 'dark'], true)) {
        $theme = at_setting('design.theme', 'light') === 'dark' ? 'dark' : 'light';
    }
    if ($theme === 'dark') {
        $style = 'simple';
    }
    return $look = ['style' => $style, 'theme' => $theme];
}

function at_set_setting(string $key, string $value): void
{
    $st = db()->prepare('INSERT INTO atour_settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
    $st->execute([$key, $value]);
    at_settings(true);
}

// --- База: схема и первичное наполнение -----------------------------------

/**
 * Схема создаётся один раз: версия лежит в atour_settings, и на каждой странице
 * делается один дешёвый SELECT вместо двух десятков CREATE TABLE IF NOT EXISTS.
 */
/**
 * Платформа хранит title/h1/description каждой страницы в seo_meta (правятся в
 * adm_panel001.php → SEO) и запоминает их при первом показе страницы. Для
 * страниц из базы сайта (туры, публикации, страницы каталога) это значило бы,
 * что после правки в /site-admin заголовок на сайте не меняется: платформа
 * продолжает отдавать первый увиденный. Поэтому запоминаем, ЧТО мы отдали
 * платформе в прошлый раз (atour_seo_sync), и если наше значение изменилось,
 * а платформенное осталось прежним — его никто не правил руками — обновляем
 * его следом. Поле, изменённое в админке платформы, не трогаем. Первый вызов
 * после появления таблицы считает существующую запись автоматической.
 */
function at_seo_sync(string $key, string $title, string $description): void
{
    if (!function_exists('sg_head')) {
        return;   // локальный запуск без платформенных файлов
    }
    $key = mb_substr($key, 0, 191);
    $cur = ['title' => mb_substr($title, 0, 255), 'h1' => mb_substr($title, 0, 255), 'description' => mb_substr($description, 0, 500)];
    try {
        $pdo = db();
        $st = $pdo->prepare('SELECT title, h1, description FROM atour_seo_sync WHERE page_key = ?');
        $st->execute([$key]);
        $prev = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($prev && $prev['title'] === $cur['title'] && $prev['h1'] === $cur['h1'] && $prev['description'] === $cur['description']) {
            return;   // с прошлого раза ничего не менялось
        }
        $st = $pdo->prepare('SELECT label, title, h1, description FROM seo_meta WHERE page_key = ?');
        $st->execute([$key]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $set = [];
            foreach (['title', 'h1', 'description'] as $f) {
                $val = (string) ($row[$f] ?? '');
                // не правили руками: пусто, ровно то, что мы отдали в прошлый раз, или запись старше таблицы
                if ($val === '' || $prev === null || $val === (string) $prev[$f]) {
                    if ($val !== $cur[$f]) {
                        $set[$f] = $cur[$f];
                    }
                }
            }
            if (isset($set['title']) && (string) ($row['label'] ?? '') === (string) ($prev['title'] ?? $row['title'])) {
                $set['label'] = mb_substr($cur['title'], 0, 191);
            }
            if ($set) {
                $pdo->prepare('UPDATE seo_meta SET ' . implode(', ', array_map(static fn(string $f): string => "`{$f}` = ?", array_keys($set))) . ' WHERE page_key = ?')
                    ->execute([...array_values($set), $key]);
            }
        }
        $pdo->prepare('INSERT INTO atour_seo_sync (page_key, title, h1, description) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE title = VALUES(title), h1 = VALUES(h1), description = VALUES(description)')
            ->execute([$key, $cur['title'], $cur['h1'], $cur['description']]);
    } catch (\Throwable $e) {
        // нет таблицы платформы или базы — страница важнее синхронизации
    }
}

function at_schema_ensure(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $v = 0;
    try {
        $v = (int) $pdo->query("SELECT v FROM atour_settings WHERE k = 'schema_version'")->fetchColumn();
    } catch (\Throwable $e) {
        $v = 0;
    }
    if ($v >= ATOUR_SCHEMA_VERSION) {
        return;
    }
    require_once __DIR__ . '/schema.php';
    at_schema_install($pdo);
    // Существующие таблицы CREATE IF NOT EXISTS не меняет — изменения колонок
    // живут в at_schema_migrate по номерам версий.
    at_schema_migrate($pdo, $v);
    $pdo->prepare("INSERT INTO atour_settings (k, v) VALUES ('schema_version', ?) ON DUPLICATE KEY UPDATE v = VALUES(v)")
        ->execute([(string) ATOUR_SCHEMA_VERSION]);
}

/** Пустой каталог → загружаем контент a-deti.ru из data/seed.json (один раз, под блокировкой). */
function at_seed_if_empty(PDO $pdo): void
{
    $file = ATOUR_ROOT . '/data/seed.json';
    if (!is_file($file)) {
        return;
    }
    if ((int) $pdo->query('SELECT COUNT(*) FROM tours')->fetchColumn() > 0) {
        return;
    }
    require_once __DIR__ . '/seed.php';
    at_seed_run($pdo, $file);
}

// --- Запуск -------------------------------------------------------------------

// На отдельном хостинге структуру и исходное наполнение меняет только bin/console.php.
// Платформенные установки сохраняют прежний порядок автоматического запуска.
if (function_exists('db') && getenv('AT_STANDALONE') !== '1') {
    $atourPdo = db();
    at_schema_ensure($atourPdo);
    at_seed_if_empty($atourPdo);
    if (is_file(__DIR__ . "/catalog-additions.php")) {
        require_once __DIR__ . "/catalog-additions.php";
        at_catalog_additions($atourPdo, __DIR__ . "/content/winter-2027.json");
    }
    unset($atourPdo);
}

require_once __DIR__ . '/repo.php';
