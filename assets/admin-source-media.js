/* Возобновляемое сохранение оригиналов. Каждый пакет защищён серверной сессией и CSRF. */
(function () {
  'use strict';
  var button = document.getElementById('source-media-start');
  var status = document.getElementById('source-media-status');
  if (!button || !status) return;
  var form = button.form;
  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (button.disabled) return;
    button.disabled = true;
    button.textContent = 'Сохраняем фотографии…';
    var offset = 0;
    var failed = new Set();
    var pending = 0;
    try {
      while (true) {
        var data = new FormData(form);
        data.set('offset', String(offset));
        var response = await fetch(form.getAttribute('action') || location.href, {method: 'POST', body: data, credentials: 'same-origin'});
        var result;
        try { result = await response.json(); }
        catch (_) { throw new Error('Сервер не подтвердил сохранение. Обновите страницу и попробуйте ещё раз.'); }
        if (!response.ok || !result.ok) throw new Error(result.error || 'Не удалось получить подтверждение сохранения.');
        pending = result.pending;
        (result.errors || []).forEach(function (error) { failed.add(error.url); });
        status.textContent = 'Сохранено: ' + result.ready + ' из ' + result.total + '.';
        if (failed.size) status.textContent += ' Недоступных оригиналов: ' + failed.size + '. Продолжаем остальные.';
        if (result.finished) {
          button.hidden = !pending;
          status.textContent = pending ? 'Сохранено: ' + result.ready + ' из ' + result.total + '. Пока недоступно оригиналов: ' + pending + '. Сохранённые фотографии уже доступны на сайте.' : 'Все оригинальные фотографии сохранены на сайте.';
          return;
        }
        if (!(result.nextOffset > offset)) throw new Error('Загрузка остановилась. Повторите сохранение.');
        offset = result.nextOffset;
      }
    } catch (error) {
      status.textContent += ' ' + (error.message || 'Не удалось сохранить фотографии. Повторите попытку.');
    } finally {
      button.disabled = false;
      button.textContent = pending && failed.size ? 'Повторить загрузку недоступных фотографий' : 'Продолжить сохранение фотографий';
    }
  });
}());
