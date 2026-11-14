<?php
/** Редиректы со старых адресов (Битрикс) на новые. */
declare(strict_types=1);

function adm_tab_redirects(): string
{
    return adm_crud('redirects', [
        'table' => 'redirects', 'one' => 'редирект', 'order' => 'from_path', 'perPage' => 200, 'search' => ['from_path', 'to_path'],
        'intro' => 'Старый адрес (со слэшем в конце) → новый. Срабатывает, если страницы по старому адресу нет.',
        'fields' => [
            'from_path'   => ['label' => 'Старый адрес', 'required' => true, 'unique' => true, 'max' => 300, 'hint' => '/tour/staryy-lager/'],
            'to_path'     => ['label' => 'Куда', 'required' => true, 'max' => 500, 'hint' => '/tour/'],
            'status_code' => ['label' => 'Код', 'type' => 'select', 'options' => ['301' => '301 — навсегда', '302' => '302 — временно'], 'required' => true],
        ],
        'columns' => ['from_path' => 'Откуда', 'to_path' => 'Куда', 'status_code' => 'Код'],
        'beforeSave' => static function (array $d, int $id): array {
            $d['from_path'] = '/' . trim((string) $d['from_path'], '/') . '/';
            $d['status_code'] = (int) $d['status_code'];
            return [$d, []];
        },
    ]);
}
