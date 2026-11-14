<?php
/** Страница 404. */
declare(strict_types=1);

http_response_code(404);
at_page_start(['key' => 'not-found', 'title' => 'Страница не найдена', 'description' => 'Такой страницы нет.', 'noindex' => true]);
echo '<div class="container-site center-block"><div class="big-emoji sun">🧭</div><h1 class="page-h1">Ошибка 404. Нет такой страницы</h1>'
    . '<p class="page-lead center">Страница, которую вы ищете, была перемещена, переименована или никогда не существовала. Перейдите на главную или воспользуйтесь поиском.</p>'
    . '<div class="cluster center mt-8"><a href="/" class="btn-primary">На главную</a><a href="/tour/" class="btn-outline">Все туры</a><a href="/search/" class="btn-ghost">' . at_icon('search') . ' Поиск</a></div></div>';
at_page_end();
