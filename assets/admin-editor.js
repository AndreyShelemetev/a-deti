/* Фотографии в редакторе HTML. Панель находится вне сохраняемого содержимого. */
(function () {
  'use strict';
  window.admRichText = function (wrapper, upload) {
    var editor = wrapper.querySelector('.wys'), source = wrapper.querySelector('textarea');
    var file = wrapper.querySelector('input[type=file]'), form = wrapper.closest('form');
    var selected = null, range = null, sourceMode = false, busy = false, replaceTarget = null;
    var panel = document.createElement('div');
    panel.className = 'wys-image-tools'; panel.hidden = true;
    panel.setAttribute('role', 'group'); panel.setAttribute('aria-label', 'Настройки фотографии');
    panel.innerHTML = '<strong>Фотография</strong><label class="wys-image-size">Ширина, % <input type="range" min="10" max="100" step="1" aria-label="Ширина фотографии"><input type="number" min="10" max="100" step="1" aria-label="Ширина фотографии в процентах"></label>' +
      '<label class="wys-image-alt">Описание фото<input type="text" maxlength="300" placeholder="Что изображено на фотографии"></label>' +
      '<div class="wys-image-actions"><button type="button" data-image-action="replace">Заменить фото</button><button type="button" data-image-action="delete">Удалить из описания</button><button type="button" data-image-action="close" aria-label="Закрыть настройки фотографии">×</button></div>';
    editor.before(panel);
    var slider = panel.querySelector('input[type=range]'), size = panel.querySelector('input[type=number]'), alt = panel.querySelector('input[type=text]');
    var help = document.createElement('p'); help.className = 'wys-image-help';
    help.textContent = 'Нажмите на фотографию, чтобы заменить, удалить или изменить её размер.';
    panel.before(help);
    var status = document.createElement('p'); status.className = 'wys-image-status'; status.hidden = true;
    status.setAttribute('role', 'status'); status.setAttribute('aria-live', 'polite'); help.after(status);

    function html() {
      var clone = editor.cloneNode(true);
      clone.querySelectorAll('[data-adm-image-selected]').forEach(function (image) { image.removeAttribute('data-adm-image-selected'); });
      return clone.innerHTML;
    }
    function sync() { if (!sourceMode) source.value = html(); }
    function changed() { sync(); editor.dispatchEvent(new Event('input', { bubbles: true })); }
    function message(text, error) { status.textContent = text; status.hidden = !text; status.classList.toggle('is-error', !!error); }
    function remember() {
      var selection = window.getSelection();
      if (selection.rangeCount && editor.contains(selection.getRangeAt(0).commonAncestorContainer)) range = selection.getRangeAt(0).cloneRange();
    }
    function restore() {
      editor.focus();
      if (!range || !editor.contains(range.commonAncestorContainer)) { range = document.createRange(); range.selectNodeContents(editor); range.collapse(false); }
      var selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
    }
    function deselect() { if (selected) selected.removeAttribute('data-adm-image-selected'); selected = null; panel.hidden = true; }
    function select(image) {
      document.querySelectorAll('[data-adm-image-selected]').forEach(function (other) { other.removeAttribute('data-adm-image-selected'); });
      deselect(); selected = image; image.setAttribute('data-adm-image-selected', ''); panel.hidden = false;
      var percent = /^(\d+(?:\.\d+)?)%$/.exec(image.style.width);
      var width = percent ? Number(percent[1]) : image.getBoundingClientRect().width / Math.max(1, editor.clientWidth - 32) * 100;
      slider.value = size.value = String(Math.max(10, Math.min(100, Math.round(width || 100)))); alt.value = image.alt || '';
      range = document.createRange(); range.selectNode(image);
      var selection = window.getSelection(); selection.removeAllRanges(); selection.addRange(range);
    }
    function noPhotoMarker() {
      if (!editor.querySelector('img') && !editor.querySelector('.tour-no-section-photo')) {
        var marker = document.createElement('span'); marker.className = 'tour-no-section-photo'; editor.appendChild(marker);
      }
    }
    function removePhoto() {
      if (!selected || busy) return;
      var image = selected, parent = image.parentElement;
      range = document.createRange(); range.setStartBefore(image); range.collapse(true);
      deselect(); image.remove();
      if (parent !== editor && /^(FIGURE|A|P)$/.test(parent.tagName) && !parent.textContent.trim() && !parent.querySelector('img,iframe,table')) parent.remove();
      noPhotoMarker(); changed(); message('Фотография удалена из описания. Галерея тура не изменена.');
    }
    function resize(value) {
      if (!selected || busy || !Number.isFinite(Number(value)) || value === '') return;
      var percent = Math.max(10, Math.min(100, Math.round(Number(value))));
      selected.style.width = percent + '%'; selected.style.height = 'auto'; selected.style.maxWidth = '100%';
      selected.style.display = 'block';
      selected.removeAttribute('width'); selected.removeAttribute('height'); slider.value = size.value = String(percent); changed();
    }
    function validUrl(value) { return /^(?:\/(?!\/)|https?:\/\/)[^\s<>]+$/i.test(value); }
    function insert(url, target) {
      if (!validUrl(url)) { message('Укажите адрес изображения, начинающийся с https:// или /.', true); return; }
      editor.querySelectorAll('.tour-no-section-photo').forEach(function (marker) { marker.remove(); });
      if (target && editor.contains(target)) { target.src = url; select(target); }
      else {
        if (selected && range) range.collapse(false);
        deselect(); restore(); var image = document.createElement('img'); image.src = url; image.alt = '';
        image.style.width = '100%'; image.style.height = 'auto'; image.style.maxWidth = '100%'; image.style.display = 'block';
        // insertHTML сохраняет позицию курсора и обычную историю отмены браузера.
        document.execCommand('insertHTML', false, image.outerHTML);
        var selection = window.getSelection(), node = selection.anchorNode;
        var inserted = node && node.nodeType === 1 ? node.childNodes[Math.max(0, selection.anchorOffset - 1)] : null;
        if (inserted && inserted.nodeName === 'IMG') select(inserted);
        remember();
      }
      changed(); message('Фотография добавлена. Сохраните изменения тура.');
    }
    function choose(target) { if (busy || sourceMode) return; remember(); replaceTarget = target || null; file.click(); }
    function loading(on) {
      busy = on; wrapper.toggleAttribute('aria-busy', on);
      wrapper.querySelectorAll('.tb button, .wys-image-tools button, .wys-image-tools input').forEach(function (control) { control.disabled = on; });
    }
    slider.addEventListener('input', function () { resize(slider.value); });
    size.addEventListener('change', function () { resize(size.value); });
    alt.addEventListener('input', function () { if (selected) { selected.alt = alt.value; changed(); } });
    panel.addEventListener('click', function (event) {
      var button = event.target.closest('button'); if (!button) return;
      var action = button.getAttribute('data-image-action');
      if (action === 'delete') removePhoto(); else if (action === 'replace') choose(selected); else { deselect(); editor.focus(); }
    });
    editor.addEventListener('click', function (event) { if (event.target.tagName === 'IMG') { event.preventDefault(); select(event.target); } else deselect(); });
    editor.addEventListener('keydown', function (event) {
      if (!selected) return;
      var selection = window.getSelection(), current = selection.rangeCount ? selection.getRangeAt(0) : null;
      if ((event.key === 'Delete' || event.key === 'Backspace') && current && current.startContainer === selected.parentNode && current.endOffset === current.startOffset + 1 && current.startContainer.childNodes[current.startOffset] === selected) { event.preventDefault(); removePhoto(); }
      else if (/^(Escape|Arrow)/.test(event.key)) deselect();
    });
    editor.addEventListener('input', function () { if (selected && !editor.contains(selected)) deselect(); sync(); });
    editor.addEventListener('blur', sync);
    document.addEventListener('selectionchange', remember);
    wrapper.querySelectorAll('.tb button').forEach(function (button) {
      button.type = 'button'; button.addEventListener('mousedown', function (event) { event.preventDefault(); remember(); });
      button.addEventListener('click', function () {
        var command = button.getAttribute('data-c'), value = button.getAttribute('data-v');
        if (command === 'src') {
          if (!sourceMode) { sync(); deselect(); source.value = html(); }
          else { editor.innerHTML = source.value; range = null; }
          sourceMode = !sourceMode; source.style.display = sourceMode ? 'block' : 'none'; editor.style.display = sourceMode ? 'none' : 'block'; help.hidden = sourceMode;
          button.setAttribute('aria-pressed', String(sourceMode)); return;
        }
        if (sourceMode) return;
        if (command === 'img') { choose(null); return; }
        if (command === 'imgurl') { var url = prompt('Адрес картинки (https://… или /…)'); if (url) insert(url.trim(), null); return; }
        restore(); deselect();
        if (command === 'link') { var link = prompt('Адрес ссылки (https://…)'); if (link) document.execCommand('createLink', false, link); }
        else document.execCommand(command, false, value || null);
        remember(); changed();
      });
    });
    file.addEventListener('change', function () {
      var chosen = file.files[0], target = replaceTarget; file.value = ''; replaceTarget = null;
      if (!chosen) return;
      if (!/\.(jpe?g|png|webp|gif)$/i.test(chosen.name) || chosen.size > 8 * 1024 * 1024) { message('Выберите JPEG, PNG, WebP или GIF размером до 8 МБ.', true); return; }
      loading(true); message('Загружаем фотографию…');
      upload(chosen, function (url) { loading(false); insert(url, target); }, function (error) { loading(false); message(error || 'Не удалось загрузить фотографию. Попробуйте ещё раз.', true); });
    });
    if (form) form.addEventListener('submit', function (event) {
      if (busy) { event.preventDefault(); message('Дождитесь завершения загрузки фотографии.', true); return; }
      sync();
    });
  };
})();
