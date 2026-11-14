<?php
/** Отзывы: модерация, привязка к туру. */
declare(strict_types=1);

function adm_tab_reviews(): string
{
    $pub = (string) ($_GET['published'] ?? '');
    $nav = '<div class="filters"><a href="' . adm_url('reviews') . '" class="' . ($pub === '' ? 'on' : '') . '">Все</a><a href="' . adm_url('reviews', ['published' => '0']) . '" class="' . ($pub === '0' ? 'on' : '') . '">На модерации</a><a href="' . adm_url('reviews', ['published' => '1']) . '" class="' . ($pub === '1' ? 'on' : '') . '">Опубликованные</a></div>';
    $tours = ['' => '— без привязки —'] + array_column(at_all('SELECT id, title FROM tours ORDER BY title'), 'title', 'id');
    return $nav . adm_crud('reviews', [
        'table' => 'reviews', 'one' => 'отзыв', 'order' => 'published_at DESC, id DESC', 'keep' => ['published'], 'perPage' => 50,
        'listWhere' => $pub === '0' || $pub === '1' ? ['is_published = ?', [(int) $pub]] : ['1=1', []],
        'defaults' => ['rating' => 5, 'source' => 'SITE', 'is_published' => 1],
        'fields' => [
            'author'       => ['label' => 'Автор', 'required' => true, 'max' => 200],
            'body'         => ['label' => 'Текст', 'type' => 'textarea', 'required' => true, 'rows' => 6],
            'rating'       => ['label' => 'Оценка (1–5)', 'type' => 'number', 'required' => true],
            'source'       => ['label' => 'Источник', 'type' => 'select', 'options' => ATOUR_REVIEW_SOURCES, 'side' => true],
            'source_url'   => ['label' => 'Ссылка на оригинал', 'nullable' => true, 'max' => 500, 'side' => true],
            'tour_id'      => ['label' => 'Тур', 'type' => 'select', 'options' => $tours, 'side' => true],
            'published_at' => ['label' => 'Дата', 'type' => 'datetime', 'required' => true, 'side' => true],
            'is_published' => ['label' => 'Опубликован', 'type' => 'checkbox', 'side' => true],
        ],
        'columns' => [
            'author'       => 'Автор',
            'body'         => ['Текст', static fn(array $r): string => adm_e(mb_substr((string) $r['body'], 0, 120))],
            'rating'       => 'Оценка',
            'source'       => ['Источник', static fn(array $r): string => adm_e(ATOUR_REVIEW_SOURCES[$r['source']] ?? $r['source'])],
            'is_published' => ['Статус', static fn(array $r): string => '<span class="chip ' . ($r['is_published'] ? 'pub' : 'NEW') . '">' . ($r['is_published'] ? 'опубликован' : 'на модерации') . '</span>'],
        ],
        'beforeSave' => static function (array $d, int $id): array {
            $d['rating'] = min(max((int) ($d['rating'] ?? 5), 1), 5);
            $d['tour_id'] = ($d['tour_id'] ?? '') === '' ? null : (int) $d['tour_id'];
            return [$d, []];
        },
    ]);
}
