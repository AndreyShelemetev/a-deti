<?php
/** Блоки главной: слайдер и хиты, «Коротко о нас», партнёры, рейтинги площадок. */
declare(strict_types=1);

function adm_tab_home(): string
{
    $sections = ['slides' => 'Баннеры', 'faq' => 'Вопросы и ответы', 'features' => '«Коротко о нас»', 'partners' => 'Партнёры', 'platforms' => 'Рейтинги площадок'];
    $section = (string) ($_GET['section'] ?? 'slides');
    if (!isset($sections[$section])) {
        $section = 'slides';
    }
    $nav = '<div class="filters">';
    foreach ($sections as $k => $v) {
        $nav .= '<a href="' . adm_url('home', ['section' => $k]) . '" class="' . ($section === $k ? 'on' : '') . '">' . adm_e($v) . '</a>';
    }
    $nav .= '</div>';
    if ($section === 'faq') { return $nav . adm_home_faq(); }
    $cfg = match ($section) {
        'features' => [
            'table' => 'features', 'one' => 'пункт', 'order' => 'sort_order, id',
            'fields' => ['body' => ['label' => 'Текст', 'required' => true, 'max' => 500], 'icon' => ['label' => 'Иконка', 'type' => 'image', 'nullable' => true], 'sort_order' => ['label' => 'Сортировка', 'type' => 'number', 'default' => 0]],
            'columns' => ['body' => 'Текст', 'sort_order' => 'Порядок'],
        ],
        'partners' => [
            'table' => 'partners', 'one' => 'партнёра', 'order' => 'sort_order, id',
            'fields' => ['name' => ['label' => 'Название', 'required' => true, 'max' => 200], 'logo' => ['label' => 'Логотип', 'type' => 'image', 'required' => true], 'url' => ['label' => 'Ссылка', 'nullable' => true, 'max' => 500], 'is_active' => ['label' => 'Показывать', 'type' => 'checkbox', 'default' => 1], 'sort_order' => ['label' => 'Сортировка', 'type' => 'number', 'default' => 0]],
            'columns' => ['name' => 'Название', 'is_active' => ['Показ', static fn(array $r): string => $r['is_active'] ? 'да' : 'нет'], 'sort_order' => 'Порядок'],
        ],
        'platforms' => [
            'table' => 'review_platforms', 'one' => 'площадку', 'order' => 'sort_order, id',
            'fields' => ['name' => ['label' => 'Название', 'required' => true, 'max' => 100], 'url' => ['label' => 'Ссылка на отзывы', 'required' => true, 'max' => 500], 'rating' => ['label' => 'Рейтинг', 'type' => 'decimal', 'required' => true], 'max_rating' => ['label' => 'Максимум', 'type' => 'number', 'default' => 5], 'logo' => ['label' => 'Логотип', 'type' => 'image', 'nullable' => true], 'sort_order' => ['label' => 'Сортировка', 'type' => 'number', 'default' => 0]],
            'columns' => ['name' => 'Площадка', 'rating' => 'Рейтинг', 'url' => 'Ссылка'],
        ],
        default => [
            'table' => 'slides', 'one' => 'слайд', 'order' => 'sort_order, id',
            'intro' => 'Все включённые баннеры HERO и PROMO показываются в общем слайдере под первым экраном. Поле «Сортировка» задаёт общий порядок: меньшее число — раньше. Чтобы скрыть баннер, снимите отметку «Показывать». Старые баннеры HIT на главной не показываются. В разделе «Хиты продаж» используются карточки туров с отметками «Хит продаж» или «Лучшее предложение».',
            'fields' => ['placement' => ['label' => 'Где показывать', 'type' => 'select', 'options' => ['HERO' => 'Слайдер под первым экраном (HERO)', 'HIT' => 'Старый блок HIT (не показывается)', 'PROMO' => 'Слайдер под первым экраном (PROMO)'], 'required' => true], 'image' => ['label' => 'Картинка', 'type' => 'image', 'required' => true], 'href' => ['label' => 'Ссылка', 'nullable' => true, 'max' => 500, 'hint' => '/tour/… или /tour/filter/…'], 'title' => ['label' => 'Подпись', 'nullable' => true, 'max' => 300], 'subtitle' => ['label' => 'Подзаголовок', 'nullable' => true, 'max' => 300], 'is_active' => ['label' => 'Показывать', 'type' => 'checkbox', 'default' => 1], 'sort_order' => ['label' => 'Сортировка', 'type' => 'number', 'default' => 0]],
            'columns' => ['title' => ['Подпись', static fn(array $r): string => adm_e($r['title'] ?: '(без подписи)')], 'placement' => 'Блок', 'href' => 'Ссылка', 'is_active' => ['Показ', static fn(array $r): string => $r['is_active'] ? 'да' : 'нет'], 'sort_order' => 'Порядок'],
        ],
    };
    $cfg['keep'] = ['section'];
    $cfg['perPage'] = 100;
    return $nav . adm_crud('home', $cfg);
}

/** Вопросы редактируются обычными полями, без HTML и JSON. */
function adm_home_faq(): string
{
    $items = at_home_faq();
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $items = [];
        foreach (array_slice((array) ($_POST['faq'] ?? []), 0, 100) as $row) {
            if (!is_array($row) || !empty($row['remove'])) { continue; }
            $question = mb_substr(trim(is_string($row['question'] ?? null) ? $row['question'] : ''), 0, 300);
            $answer = mb_substr(trim(is_string($row['answer'] ?? null) ? $row['answer'] : ''), 0, 3000);
            if ($question === '' && $answer === '') { continue; }
            if ($question === '' || $answer === '') { $error = 'Заполните и вопрос, и ответ.'; }
            $items[] = ['question' => $question, 'answer' => $answer, 'active' => !empty($row['active'])];
        }
        $encoded = (string) json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($encoded) > 60000) { $error = 'Слишком большой объём вопросов и ответов. Сократите текст.'; }
        if ($error === '') {
            at_set_setting('home.faq', $encoded);
            adm_redirect('home', ['section' => 'faq', 'ok' => 1, 'msg' => 'Вопросы и ответы сохранены.']);
        }
    }
    $html = ($error !== '' ? '<p class="error">' . adm_e($error) . '</p>' : '') . adm_form_open('save-faq');
    $items[] = ['question' => '', 'answer' => '', 'active' => true];
    foreach ($items as $i => $item) {
        $new = $i === count($items) - 1;
        $html .= '<div class="card"><h2>' . ($new ? 'Добавить вопрос' : 'Вопрос ' . ($i + 1)) . '</h2>'
            . adm_input('faq[' . $i . '][question]', $item['question'], 'Вопрос', ['maxlength' => 300])
            . adm_textarea('faq[' . $i . '][answer]', $item['answer'], 'Ответ', ['rows' => 3, 'maxlength' => 3000])
            . '<label><input type="checkbox" name="faq[' . $i . '][active]" value="1"' . ($item['active'] ? ' checked' : '') . '> Показывать на сайте</label>'
            . (!$new ? '<label><input type="checkbox" name="faq[' . $i . '][remove]" value="1"> Удалить этот вопрос</label>' : '') . '</div>';
    }
    return $html . '<button class="btn" type="submit">Сохранить вопросы</button></form>';
}
