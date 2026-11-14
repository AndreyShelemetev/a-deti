(function () {
  'use strict';
  document.querySelectorAll('[data-copy-request]').forEach(function (button) {
    button.addEventListener('click', async function () {
      var field = document.getElementById(button.getAttribute('data-copy-request'));
      var status = document.querySelector('[data-copy-request-status]');
      if (!field || !status) return;
      var copied = false;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        try { await navigator.clipboard.writeText(field.value); copied = true; } catch (error) { /* Use the selectable fallback below. */ }
      }
      if (!copied) {
        field.hidden = false;
        field.focus();
        field.select();
        try { copied = document.execCommand('copy'); } catch (error) { copied = false; }
      }
      if (copied) {
        field.hidden = true;
        button.focus();
        status.textContent = 'Данные заявки скопированы. Теперь их можно вставить в письмо или сообщение.';
      } else {
        status.textContent = 'Автоматическое копирование недоступно. Данные выделены ниже — скопируйте их вручную.';
      }
      status.hidden = false;
    });
  });
}());
