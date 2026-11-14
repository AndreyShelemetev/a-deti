/* Поля контактов и подтверждения отправки. Один обработчик для всех форм заявок. */
(function () {
  'use strict';
  function phoneDigits(value) { return String(value || '').replace(/\D/g, ''); }
  function formatPhone(value) {
    var raw = String(value || '').trim(), digits = phoneDigits(raw);
    if (!digits) return '';
    if (raw.charAt(0) === '+' && !/^7/.test(digits)) return '+' + digits.slice(0, 15);
    if (/^[78]/.test(digits) && digits.length > 10 || /^\+7/.test(raw) || digits === '7' || digits === '8') digits = digits.slice(1);
    digits = digits.slice(0, 10);
    var out = '+7';
    if (digits.length) out += ' (' + digits.slice(0, 3);
    if (digits.length >= 3) out += ')';
    if (digits.length > 3) out += ' ' + digits.slice(3, 6);
    if (digits.length > 6) out += '-' + digits.slice(6, 8);
    if (digits.length > 8) out += '-' + digits.slice(8, 10);
    return out;
  }
  function normalizeEmail(value) { return String(value || '').replace(/[\s\u200B-\u200D\uFEFF]/g, ''); }
  function validationMessage(field) {
    var value = String(field.value || '').trim(), type = field.type || '';
    if (field.disabled || type === 'hidden' || type === 'submit' || type === 'button' || field.name === 'website') return '';
    if (type === 'checkbox') return field.required && !field.checked ? 'Подтвердите согласие на обработку персональных данных.' : '';
    if (!value) return field.required ? (type === 'email' ? 'Укажите e-mail.' : type === 'tel' ? 'Укажите телефон.' : field.name === 'name' ? 'Укажите ваше имя.' : 'Заполните это поле.') : '';
    if (field.name === 'name' && field.required && value.length < 2) return 'Укажите имя: не менее двух символов.';
    if (type === 'tel') {
      var digits = phoneDigits(value), russian = /^\+?7/.test(value) || /^8/.test(value);
      if (digits.length < 10 || digits.length > 15 || russian && digits.length !== 11) return 'Введите телефон полностью, например +7 (999) 123-45-67.';
    }
    if (type === 'email' && (!/^[^\s@]+@[^\s@]+\.[^\s@.]{2,}$/.test(value) || /\.\.|^\.|\.@/.test(value) || field.validity && field.validity.typeMismatch)) return 'Введите e-mail в формате name@example.ru.';
    if (field.validity && !field.validity.valid) return 'Проверьте значение этого поля.';
    return '';
  }
  // Чистые функции проверяются без DOM; на сайте наружу ничего не экспортируется.
  if (typeof module !== 'undefined' && module.exports) { module.exports = { formatPhone: formatPhone, normalizeEmail: normalizeEmail, validationMessage: validationMessage }; return; }
  var activeNotice = null, noticeTimer = null, errorIndex = 0;
  function closeNotice() {
    clearTimeout(noticeTimer);
    if (activeNotice) { activeNotice.remove(); activeNotice = null; }
  }
  function notify(form, ok, text) {
    closeNotice();
    var notice = document.createElement('div');
    notice.className = 'lead-notice ' + (ok ? 'lead-notice--success' : 'lead-notice--error');
    notice.setAttribute('role', ok ? 'status' : 'alert');
    notice.setAttribute('aria-live', ok ? 'polite' : 'assertive');
    notice.setAttribute('aria-atomic', 'true');
    notice.innerHTML = '<span class="lead-notice__icon" aria-hidden="true"></span><div class="lead-notice__body"><strong></strong><p></p></div><button type="button" class="lead-notice__close" aria-label="Закрыть уведомление">×</button>';
    notice.querySelector('.lead-notice__icon').textContent = ok ? '✓' : '!';
    notice.querySelector('strong').textContent = ok ? 'Заявка отправлена' : 'Не удалось отправить';
    notice.querySelector('p').textContent = text;
    notice.querySelector('button').addEventListener('click', closeNotice);
    var nativePopover = typeof notice.showPopover === 'function';
    if (nativePopover) notice.setAttribute('popover', 'manual');
    (nativePopover ? document.body : form.closest('dialog[open]') || document.body).appendChild(notice);
    if (nativePopover) notice.showPopover();
    activeNotice = notice;
    function schedule() { clearTimeout(noticeTimer); if (ok) noticeTimer = setTimeout(closeNotice, 8000); }
    notice.addEventListener('pointerenter', function () { clearTimeout(noticeTimer); });
    notice.addEventListener('focusin', function () { clearTimeout(noticeTimer); });
    notice.addEventListener('pointerleave', schedule);
    notice.addEventListener('focusout', schedule);
    schedule();
  }
  function setError(field, text) {
    var wrap = field.closest('[data-field]');
    if (!wrap) return;
    var error = wrap.querySelector('.field-error');
    if (error) {
      if (!error.id) error.id = 'lead-error-' + (++errorIndex);
      error.textContent = text || ''; error.hidden = !text;
      var ids = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
      if (ids.indexOf(error.id) < 0) field.setAttribute('aria-describedby', ids.concat(error.id).join(' '));
    }
    if (text) field.setAttribute('aria-invalid', 'true'); else field.removeAttribute('aria-invalid');
  }
  function showMessage(form, ok, text) {
    var message = form.querySelector('.form-message');
    if (!message) return;
    message.textContent = text; message.hidden = !text;
    message.className = 'form-message ' + (ok ? 'is-ok' : 'is-error');
    message.setAttribute('aria-live', 'off');
    message.removeAttribute('role');
  }
  function showSuccess(form) {
    form.classList.add('is-sent');
    var dialog = form.closest('dialog'), actions = form.querySelector('[data-form-success-actions]');
    if (!actions) {
      actions = document.createElement('div'); actions.setAttribute('data-form-success-actions', '');
      var close = document.createElement('button'); close.type = 'button';
      var submit = form.querySelector('button[type=submit]'); close.className = submit ? submit.className : 'btn';
      close.textContent = dialog ? 'Закрыть' : 'Отправить ещё одну заявку';
      close.addEventListener('click', function () { if (dialog && dialog.open) dialog.close(); else resetSuccess(); });
      actions.appendChild(close); form.appendChild(actions);
      if (dialog) dialog.addEventListener('close', resetSuccess);
    }
    actions.hidden = false;
    var message = form.querySelector('.form-message');
    if (message) { message.setAttribute('tabindex', '-1'); message.focus(); }
    function resetSuccess() {
      form.classList.remove('is-sent'); actions.hidden = true; showMessage(form, true, '');
      var first = form.querySelector('input:not([type=hidden]):not([name=website])'); if (!dialog && first) first.focus();
    }
  }
  function maskPhone(input) {
    input.addEventListener('input', function (event) {
      var old = input.value, position = input.selectionStart == null ? old.length : input.selectionStart;
      var before = phoneDigits(old.slice(0, position)).length, formatted = formatPhone(old);
      if (formatted !== old) {
        input.value = formatted;
        var caret = formatted.length;
        if (position < old.length) {
          var seen = 0;
          for (var i = 0; i < formatted.length; i++) { if (/\d/.test(formatted.charAt(i))) seen++; if (seen >= before) { caret = i + 1; break; } }
        }
        input.setSelectionRange(caret, caret);
      }
      // После удаления скобки/дефиса курсор остаётся перед ним, следующий Backspace удалит цифру.
      if (event.inputType === 'deleteContentBackward' && input.selectionStart > 0 && /[() -]/.test(input.value.charAt(input.selectionStart - 1))) input.setSelectionRange(input.selectionStart - 1, input.selectionStart - 1);
    });
    if (input.value) input.value = formatPhone(input.value);
  }
  document.querySelectorAll('.lead-form, .pay-form').forEach(function (form) {
    var fields = Array.prototype.slice.call(form.querySelectorAll('input, textarea, select'));
    fields.forEach(function (field) {
      if (field.type === 'tel') maskPhone(field);
      if (field.type === 'email') field.addEventListener('input', function () { var normalized = normalizeEmail(field.value); if (normalized !== field.value) field.value = normalized; });
      field.addEventListener(field.type === 'checkbox' ? 'change' : 'input', function () { if (field.hasAttribute('aria-invalid')) setError(field, validationMessage(field)); });
      if (field.type === 'email' || field.type === 'tel') field.addEventListener('blur', function () { setError(field, validationMessage(field)); });
    });
    if (!form.hasAttribute('data-ajax')) return; // Платёж сохраняет обычный переход к платёжному сервису.
    // Проверяем поля здесь: общий обработчик платформы иначе останавливает submit до уведомления.
    form.setAttribute('data-no-validate', '');
    var sending = false;
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (sending) return;
      closeNotice(); showMessage(form, true, '');
      var firstInvalid = null;
      fields.forEach(function (field) { var error = validationMessage(field); setError(field, error); if (error && !firstInvalid) firstInvalid = field; });
      if (firstInvalid) { notify(form, false, 'Проверьте отмеченные поля формы.'); firstInvalid.focus(); return; }
      sending = true; form.setAttribute('aria-busy', 'true');
      var button = form.querySelector('button[type="submit"]'), label = button ? button.textContent : '';
      if (button) { button.disabled = true; button.textContent = button.getAttribute('data-pending') || 'Отправляем…'; }
      var controller = new AbortController(), timeout = setTimeout(function () { controller.abort(); }, 20000);
      fetch(form.getAttribute('action') || '/lead/', { method: 'POST', body: new FormData(form), credentials: 'same-origin', signal: controller.signal, headers: { 'X-Requested-With': 'fetch', Accept: 'application/json' } })
        .then(function (response) { return response.json().then(function (data) { return { response: response, data: data }; }); })
        .then(function (result) {
          var data = result.data;
          if (result.response.ok && data.ok === true) {
            var context = fields.filter(function (field) { return field.type === 'hidden'; }).map(function (field) { return [field, field.value]; });
            form.reset(); context.forEach(function (item) { item[0].value = item[1]; });
            fields.forEach(function (field) { setError(field, ''); });
            var success = data.message || 'Спасибо! Мы получили вашу заявку и свяжемся с вами в ближайшее время.';
            showMessage(form, true, success); showSuccess(form); notify(form, true, success);
            return;
          }
          var errors = data.errors || {}, first = null;
          fields.forEach(function (field) { if (typeof errors[field.name] === 'string') { setError(field, errors[field.name]); if (!first) first = field; } });
          var failure = data.message || 'Попробуйте отправить заявку ещё раз.';
          showMessage(form, false, failure); notify(form, false, failure);
          if (first) first.focus();
        })
        .catch(function () {
          var failure = 'Не удалось получить подтверждение отправки. Проверьте соединение и попробуйте ещё раз.';
          showMessage(form, false, failure); notify(form, false, failure);
        })
        .finally(function () { clearTimeout(timeout); sending = false; form.removeAttribute('aria-busy'); if (button) { button.disabled = false; button.textContent = label; } });
    });
  });
  // Возврат после отправки без fetch использует то же уведомление.
  var params = new URLSearchParams(window.location.search), fallbackForm = document.querySelector('.lead-form');
  if (fallbackForm && params.get('sent')) notify(fallbackForm, true, 'Спасибо! Мы получили вашу заявку и свяжемся с вами в ближайшее время.');
  else if (fallbackForm && params.get('form_error')) notify(fallbackForm, false, params.get('form_error'));
})();
