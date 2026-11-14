/* А-тур: меню, модальные формы, вкладки, галерея, виджет брони, отправка форм. Без библиотек. */
(function () {
  'use strict';
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /* --- Мобильное меню --- */
  var burger = $('#burger'), menu = $('#mobile-menu');
  function openMenu() { if (!menu) return; menu.hidden = false; document.body.classList.add('menu-open'); burger && burger.setAttribute('aria-expanded', 'true'); }
  function closeMenu() { if (!menu) return; menu.hidden = true; document.body.classList.remove('menu-open'); burger && burger.setAttribute('aria-expanded', 'false'); }
  if (burger) burger.addEventListener('click', openMenu);
  $$('[data-close-menu]').forEach(function (b) { b.addEventListener('click', closeMenu); });
  $$('.mobile-expand').forEach(function (b) {
    b.addEventListener('click', function () {
      var sub = b.closest('li').querySelector('.mobile-sub'), open = b.getAttribute('aria-expanded') === 'true';
      b.setAttribute('aria-expanded', open ? 'false' : 'true');
      if (sub) sub.hidden = open;
    });
  });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && menu && !menu.hidden) closeMenu(); });

  /* --- Модальные окна (dialog) --- */
  function openModal(name) {
    var d = $('#modal-' + name);
    if (!d || typeof d.showModal !== 'function') return;
    if (!d.open) d.showModal();
    document.body.classList.add('modal-open');
    var first = d.querySelector('input:not([type=hidden]):not([tabindex="-1"]), textarea, select');
    if (first) setTimeout(function () { first.focus(); }, 50);
  }
  function closeModal(d) { if (d && d.open) d.close(); }
  $$('.modal').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) closeModal(d); });
    d.addEventListener('close', function () { if (!$$('.modal[open]').length) document.body.classList.remove('modal-open'); });
    $$('[data-close-modal]', d).forEach(function (b) { b.addEventListener('click', function () { closeModal(d); }); });
  });
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-modal]');
    if (!t) return;
    e.preventDefault();
    if (t.hasAttribute('data-book')) fillBooking(t.closest('[data-booking-widget]'));
    openModal(t.getAttribute('data-modal'));
  });

  /* --- Виджет «Смены и цены» --- */
  function money(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ₽'; }
  function widgetState(w) {
    var sel = w.querySelector('.booking-select'), opt = sel && sel.options[sel.selectedIndex];
    var price = opt ? parseInt(opt.getAttribute('data-price') || '0', 10) : 0;
    return { title: w.getAttribute('data-tour-title') || '', id: w.getAttribute('data-tour-id') || '', shift: opt ? (opt.getAttribute('data-label') || '') : '', price: price,
      priceText: opt ? (opt.getAttribute('data-price-text') || '') : '', dates: opt ? (opt.getAttribute('data-dates') || '') : '', avail: opt ? (opt.getAttribute('data-avail-text') || '') : '' };
  }
  function updateWidget(w) {
    var st = widgetState(w), box = w.querySelector('[data-booking-price]'), note = w.querySelector('[data-booking-note]'), pay = w.querySelector('[data-pay-link]'), dates = w.querySelector('[data-booking-dates]');
    if (box) box.textContent = st.priceText || (st.price > 0 ? money(st.price) : (box.getAttribute('data-fallback') || 'по запросу'));
    if (note) note.hidden = st.price > 0;
    if (dates) { dates.textContent = st.dates + (st.avail ? (st.dates ? ' · ' : '') + st.avail : ''); dates.hidden = !st.dates && !st.avail; }
    if (pay) pay.setAttribute('href', '/pay/' + (st.price > 0 ? '?amount=' + st.price : ''));
  }
  function fillBooking(w) {
    if (!w) return;
    var st = widgetState(w), f = $('#modal-booking form');
    if (!f) return;
    f.querySelector('[name=tour_id]').value = st.id;
    f.querySelector('[name=shift_name]').value = st.shift;
    f.querySelector('[name=amount]').value = st.price > 0 ? st.price : '';
    $('[data-booking=title]', f).textContent = st.title;
    $('[data-booking=shift]', f).textContent = st.shift;
    $('[data-booking=price]', f).textContent = st.priceText || (st.price > 0 ? money(st.price) : '');
  }
  $$('[data-booking-widget]').forEach(function (w) {
    var sel = w.querySelector('.booking-select');
    if (sel) sel.addEventListener('change', function () { updateWidget(w); });
    updateWidget(w);
  });
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  /* --- Вкладки --- */
  $$('[data-tabs]').forEach(function (root) {
    var btns = $$('[data-tab]', root), panels = $$('[data-panel]', root);
    btns.forEach(function (b) {
      b.addEventListener('click', function () {
        var key = b.getAttribute('data-tab');
        btns.forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-selected', on ? 'true' : 'false'); });
        panels.forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== key; });
      });
    });
  });

  /* --- Галерея тура + лайтбокс --- */
  $$('[data-gallery]').forEach(function (g) {
    var thumbs = $$('[data-gallery-thumb]', g), main = $('[data-gallery-main]', g), lb = $('[data-lightbox]', g), lbImg = $('[data-lightbox-img]', g);
    var list = thumbs.length ? thumbs.map(function (t) { return { src: t.getAttribute('data-src'), alt: t.getAttribute('data-alt') || '' }; }) : [{ src: main ? main.getAttribute('src') : '', alt: main ? main.getAttribute('alt') : '' }];
    var i = 0;
    function show(n) {
      i = (n + list.length) % list.length;
      if (main) { main.src = list[i].src; main.alt = list[i].alt; }
      if (lbImg) { lbImg.src = list[i].src; lbImg.alt = list[i].alt; }
      thumbs.forEach(function (t, k) { t.classList.toggle('on', k === i); });
      $$('[data-gallery-index]', g).forEach(function (c) { c.textContent = String(i + 1); });
    }
    var grid = g.hasAttribute('data-gallery-grid');
    thumbs.forEach(function (t, k) { t.addEventListener('click', function () { show(k); if (grid && lb && typeof lb.showModal === 'function') { lb.showModal(); document.body.classList.add('modal-open'); } }); });
    $$('[data-gallery-prev]', g).forEach(function (b) { b.addEventListener('click', function (e) { e.stopPropagation(); show(i - 1); }); });
    $$('[data-gallery-next]', g).forEach(function (b) { b.addEventListener('click', function (e) { e.stopPropagation(); show(i + 1); }); });
    var opener = $('[data-gallery-open]', g);
    if ((opener || grid) && lb && typeof lb.showModal === 'function') {
      if (opener) opener.addEventListener('click', function () { show(i); lb.showModal(); document.body.classList.add('modal-open'); });
      $$('[data-lightbox-close]', g).forEach(function (b) { b.addEventListener('click', function () { lb.close(); }); });
      lb.addEventListener('click', function (e) { if (e.target === lb || e.target === lbImg) lb.close(); });
      lb.addEventListener('close', function () { document.body.classList.remove('modal-open'); if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(function () {}); });
      var full = $('[data-lightbox-full]', g), docEl = document.documentElement;
      if (full) {
        if (!docEl.requestFullscreen) full.hidden = true; // iOS Safari: полноэкранного API для страницы нет
        else full.addEventListener('click', function (e) { e.stopPropagation(); if (document.fullscreenElement) document.exitFullscreen().catch(function () {}); else docEl.requestFullscreen().catch(function () {}); });
      }
      document.addEventListener('keydown', function (e) {
        if (!lb.open) return;
        if (e.key === 'ArrowLeft') show(i - 1);
        if (e.key === 'ArrowRight') show(i + 1);
      });
    }
  });

  /* --- Сортировка: переход по смене значения (в каталоге список обновляется без перехода) --- */
  $$('[data-sort]').forEach(function (sel) {
    sel.addEventListener('change', function () {
      if (sel.closest('[data-catalog]')) return;
      var u = new URL(window.location.href);
      if (sel.value === 'default') u.searchParams.delete('sort'); else u.searchParams.set('sort', sel.value);
      u.searchParams.delete('page');
      window.location.href = u.toString();
    });
  });

  /* --- Оценка звёздами в форме отзыва --- */
  $$('.stars-pick').forEach(function (box) {
    var form = box.closest('form'), input = form && form.querySelector('[name=rating]');
    $$('.star-btn', box).forEach(function (b) {
      b.addEventListener('click', function () {
        var n = parseInt(b.getAttribute('data-star'), 10);
        if (input) input.value = String(n);
        $$('.star-btn', box).forEach(function (x) { x.classList.toggle('on', parseInt(x.getAttribute('data-star'), 10) <= n); });
      });
    });
  });

  /* --- Красивый выпадающий список поверх системного select --- */
  function ddLabel(opt) { return (opt.textContent || '').replace(/\s*·\s*\d+\s*$/, ''); }
  function enhanceSelect(sel) {
    if (sel.dataset.dd || sel.multiple || sel.hidden) return; // hidden: список нужен только скрипту (одна смена в виджете)
    sel.dataset.dd = '1';
    var wrap = document.createElement('div');
    wrap.className = 'dd' + (sel.classList.contains('select-sm') ? ' dd-sm' : '');
    var btn = document.createElement('button');
    btn.type = 'button'; btn.className = 'dd-btn'; btn.setAttribute('aria-haspopup', 'listbox'); btn.setAttribute('aria-expanded', 'false');
    var label = sel.getAttribute('aria-label') || (sel.closest('label') && sel.closest('label').querySelector('.label') ? sel.closest('label').querySelector('.label').textContent : '');
    if (label) btn.setAttribute('aria-label', label);
    var val = document.createElement('span'); val.className = 'dd-value';
    var cnt = document.createElement('span'); cnt.className = 'dd-count'; cnt.hidden = true;
    btn.appendChild(val); btn.appendChild(cnt);
    var list = document.createElement('ul'); list.className = 'dd-list'; list.setAttribute('role', 'listbox'); list.hidden = true; list.tabIndex = -1;
    sel.classList.add('dd-native'); sel.setAttribute('tabindex', '-1'); sel.setAttribute('aria-hidden', 'true');
    sel.parentNode.insertBefore(wrap, sel); wrap.appendChild(btn); wrap.appendChild(list); wrap.appendChild(sel);
    var items = [], active = -1;
    function render() {
      list.innerHTML = ''; items = [];
      Array.prototype.forEach.call(sel.children, function (node) {
        if (node.tagName === 'OPTGROUP') {
          var g = document.createElement('li'); g.className = 'dd-group'; g.textContent = node.label; list.appendChild(g);
          Array.prototype.forEach.call(node.children, function (o) { addItem(o); });
        } else addItem(node);
      });
      function addItem(o) {
        if (o.hidden) return;
        var li = document.createElement('li'); li.className = 'dd-item'; li.setAttribute('role', 'option'); li.dataset.value = o.value;
        if (o.disabled) { li.classList.add('is-disabled'); li.setAttribute('aria-disabled', 'true'); }
        var t = document.createElement('span'); t.textContent = ddLabel(o); li.appendChild(t);
        if (o.hasAttribute('data-count')) {
          var c = document.createElement('span'); c.className = 'dd-count'; c.textContent = o.getAttribute('data-count'); li.appendChild(c);
          if (o.getAttribute('data-count') === '0' && o.value !== '') li.classList.add('is-zero');
        }
        li.setAttribute('aria-selected', o.selected ? 'true' : 'false');
        li.addEventListener('click', function (e) { e.preventDefault(); if (!o.disabled) choose(o.value); });
        li.addEventListener('mousemove', function () { setActive(items.indexOf(li)); });
        list.appendChild(li); items.push(li);
      }
      sync();
      active = Math.max(items.findIndex(function (li) { return li.dataset.value === sel.value; }), 0);
      if (!list.hidden) setActive(active);
    }
    function sync() {
      var o = sel.options[sel.selectedIndex];
      val.textContent = o ? ddLabel(o) : '';
      val.classList.toggle('is-empty', !sel.value);
      var c = o && o.getAttribute('data-count');
      cnt.hidden = !(c && sel.value); cnt.textContent = c || '';
      items.forEach(function (li) { li.setAttribute('aria-selected', li.dataset.value === sel.value ? 'true' : 'false'); });
    }
    function setActive(i) {
      active = i;
      items.forEach(function (li, k) { li.classList.toggle('is-active', k === i); });
      if (items[i]) items[i].scrollIntoView({ block: 'nearest' });
    }
    function open() {
      if (!list.hidden) return;
      closeAll();
      list.hidden = false; wrap.classList.add('is-open'); btn.setAttribute('aria-expanded', 'true');
      setActive(Math.max(items.findIndex(function (li) { return li.dataset.value === sel.value; }), 0));
    }
    function close() { list.hidden = true; wrap.classList.remove('is-open'); btn.setAttribute('aria-expanded', 'false'); }
    function choose(v) { var chosen = Array.prototype.filter.call(sel.options, function (x) { return x.value === v; })[0]; if (chosen && chosen.disabled) return;
      var changed = sel.value !== v;
      sel.value = v; sync(); close(); btn.focus();
      if (changed) sel.dispatchEvent(new Event('change', { bubbles: true }));
    }
    // Нажатие мышью внутри списка не должно уводить фокус с кнопки: иначе
    // focusout закрывал список раньше, чем приходил click по пункту.
    list.addEventListener('mousedown', function (e) { e.preventDefault(); });
    btn.addEventListener('click', function () { list.hidden ? open() : close(); });
    btn.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); if (list.hidden) open(); else setActive(Math.min(Math.max(active + (e.key === 'ArrowDown' ? 1 : -1), 0), items.length - 1)); }
      else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (list.hidden) open(); else if (items[active]) choose(items[active].dataset.value); }
      else if (e.key === 'Escape' || e.key === 'Tab') { close(); }
      else if (e.key.length === 1 && /\S/.test(e.key)) {
        var k = e.key.toLowerCase(), hit = items.findIndex(function (li, i) { return i > active && li.textContent.toLowerCase().indexOf(k) === 0; });
        if (hit < 0) hit = items.findIndex(function (li) { return li.textContent.toLowerCase().indexOf(k) === 0; });
        if (hit >= 0) { if (list.hidden) open(); setActive(hit); }
      }
    });
    wrap.addEventListener('focusout', function (e) { if (!wrap.contains(e.relatedTarget)) close(); });
    wrap._ddClose = close; wrap._ddRender = render;
    render();
    sel.addEventListener('dd:refresh', render);
    sel.addEventListener('change', sync);
  }
  function closeAll() { $$('.dd.is-open').forEach(function (w) { w._ddClose && w._ddClose(); }); }
  document.addEventListener('click', function (e) { if (!e.target.closest('.dd')) closeAll(); });
  $$('select.select').forEach(enhanceSelect);

  /* --- Слайдер в герое: один кадр, плавное растворение --- */
  $$('[data-slider]').forEach(function (root) {
    var slides = $$('[data-slide]', root), dots = $$('[data-slider-to]', root);
    if (slides.length < 2) return;
    var i = 0, timer = null, interval = parseInt(root.getAttribute('data-interval') || '6000', 10);
    var still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    function show(n) {
      i = (n + slides.length) % slides.length;
      slides.forEach(function (sl, k) {
        var on = k === i;
        sl.classList.toggle('is-active', on);
        sl.setAttribute('aria-hidden', on ? 'false' : 'true');
        sl.setAttribute('tabindex', on ? '0' : '-1');
      });
      dots.forEach(function (d, k) { d.classList.toggle('is-on', k === i); if (k === i) d.setAttribute('aria-current', 'true'); else d.removeAttribute('aria-current'); });
      // Фокус не должен остаться на скрытом кадре: Enter открывал бы невидимую ссылку.
      if (document.activeElement && document.activeElement.hasAttribute('data-slide')) slides[i].focus({ preventScroll: true });
    }
    var hover = false;
    function start() { stop(); if (!still && !hover && !document.hidden) timer = setInterval(function () { show(i + 1); }, interval); }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }
    $$('[data-slider-prev]', root).forEach(function (b) { b.addEventListener('click', function () { show(i - 1); start(); }); });
    $$('[data-slider-next]', root).forEach(function (b) { b.addEventListener('click', function () { show(i + 1); start(); }); });
    dots.forEach(function (d, k) { d.addEventListener('click', function () { show(k); start(); }); });
    root.addEventListener('mouseenter', function () { hover = true; stop(); }); root.addEventListener('mouseleave', function () { hover = false; start(); });
    root.addEventListener('focusin', stop); root.addEventListener('focusout', start);
    root.addEventListener('keydown', function (e) { if (e.key === 'ArrowLeft') show(i - 1); if (e.key === 'ArrowRight') show(i + 1); });
    var sx = null;
    root.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
    root.addEventListener('touchend', function (e) { if (sx === null) return; var dx = e.changedTouches[0].clientX - sx; if (Math.abs(dx) > 40) show(dx < 0 ? i + 1 : i - 1); sx = null; start(); });
    document.addEventListener('visibilitychange', function () { document.hidden ? stop() : start(); });
    start();
  });

  function debounce(fn, ms) { var t; var run = function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; run.cancel = function () { clearTimeout(t); }; return run; }

  /* --- Главная: сезон, регион, тип и отдельный быстрый поиск лагеря --- */
  $$('[data-home-search]').forEach(function (root) {
    var form = $('[data-home-search-filters]', root), quick = $('[data-home-search-quick]', root);
    var query = $('[data-home-search-query]', root), suggestions = $('[data-home-search-suggestions]', root), status = $('[data-home-search-status]', root);
    var facetsRequest = null, searchRequest = null, active = -1;
    if (!form || !quick || !query || !suggestions) return;
    function endpoint(path, json) {
      if (!/\/projects\/\d+\/editor\/preview\/?$/.test(window.location.pathname)) return path;
      var url = new URL(window.location.href); url.searchParams.set('path', path);
      if (json) url.searchParams.set('format', 'json'); else url.searchParams.delete('format');
      return url.href;
    }
    function filterPath() { return '/tour/?' + new URLSearchParams(new FormData(form)).toString(); }
    function facets() {
      if (facetsRequest) facetsRequest.abort();
      var request = new AbortController(), path = filterPath(); facetsRequest = request;
      form.setAttribute('aria-busy', 'true'); status.hidden = true;
      fetch(endpoint(path + '&_facets=1', true), { signal: request.signal, cache: 'no-store', headers: { Accept: 'application/json', 'X-Requested-With': 'fetch' } }).then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status); return r.json();
      }).then(function (data) {
        if (request !== facetsRequest || path !== filterPath()) return;
        $$('select', form).forEach(function (select) {
          var counts = data.facets[select.name] || {};
          Array.prototype.forEach.call(select.options, function (option) {
            option.hidden = !!option.value && !counts[option.value] && option.value !== select.value;
            option.disabled = option.hidden;
          });
          select.dispatchEvent(new Event('dd:refresh'));
        });
        form.removeAttribute('aria-busy');
      }).catch(function (err) {
        if (request !== facetsRequest || err.name === 'AbortError') return;
        form.removeAttribute('aria-busy');
        status.textContent = 'Не удалось уточнить варианты. Нажмите «Подобрать», чтобы выполнить поиск.'; status.hidden = false;
      });
    }
    form.addEventListener('change', facets);
    // Обычная GET-форма работает и без JavaScript, в редакторе сохраняем оболочку предпросмотра.
    form.addEventListener('submit', function (event) {
      if (facetsRequest) facetsRequest.abort();
      var path = filterPath(), target = endpoint(path, false);
      if (target !== path) { event.preventDefault(); window.location.href = target; }
    });
    facets();
    function hideSuggestions() { suggestions.hidden = true; query.setAttribute('aria-expanded', 'false'); active = -1; }
    function cancelSearch() { suggest.cancel(); if (searchRequest) searchRequest.abort(); searchRequest = null; hideSuggestions(); }
    var suggest = debounce(function () {
      var text = query.value.trim();
      if (text.length < 2) { cancelSearch(); return; }
      var request = new AbortController(); searchRequest = request;
      fetch(endpoint('/api/v1/tours/?q=' + encodeURIComponent(text) + '&perPage=6', true), { signal: request.signal, headers: { Accept: 'application/json' } }).then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status); return r.json();
      }).then(function (data) {
        if (request !== searchRequest || text !== query.value.trim() || quick.hidden) return;
        suggestions.innerHTML = ''; active = -1;
        (data.items || []).forEach(function (item) {
          var link = document.createElement('a'); link.href = endpoint(item.url, false);
          var title = document.createElement('strong'); title.textContent = item.title; link.appendChild(title);
          if (item.region) { var place = document.createElement('span'); place.textContent = item.region.name; link.appendChild(place); }
          suggestions.appendChild(link);
        });
        if (!data.items || !data.items.length) { var empty = document.createElement('p'); empty.textContent = 'Лагерь не найден. Попробуйте другое название.'; suggestions.appendChild(empty); }
        suggestions.hidden = false; query.setAttribute('aria-expanded', 'true');
      }).catch(function (err) {
        if (request !== searchRequest || err.name === 'AbortError') return;
        suggestions.textContent = 'Подсказки недоступны. Нажмите «Найти», чтобы повторить поиск.';
        suggestions.hidden = false; query.setAttribute('aria-expanded', 'true');
      });
    }, 220);
    query.addEventListener('input', function () { cancelSearch(); suggest(); });
    query.addEventListener('keydown', function (event) {
      var links = $$('a', suggestions);
      if ((event.key === 'ArrowDown' || event.key === 'ArrowUp') && !suggestions.hidden && links.length) {
        event.preventDefault(); active = (active + (event.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
        links.forEach(function (link, i) { link.classList.toggle('is-active', i === active); });
      } else if (event.key === 'Enter' && !suggestions.hidden && links[active]) {
        event.preventDefault(); links[active].click();
      } else if (event.key === 'Escape') { cancelSearch(); }
    });
    quick.addEventListener('submit', function (event) {
      var text = query.value.trim(); cancelSearch();
      if (!text) { event.preventDefault(); query.focus(); return; }
      var path = '/tour/?q=' + encodeURIComponent(text), target = endpoint(path, false);
      if (target !== path) { event.preventDefault(); window.location.href = target; }
    });
    document.addEventListener('click', function (event) { if (!quick.contains(event.target)) cancelSearch(); });
  });

  /* --- Каталог: фильтры слева обновляют список без перезагрузки, подсказки, вид, поиск --- */
  $$('[data-catalog]').forEach(function (cat) {
    var form = $('[data-catalog-form]', cat), results = $('[data-catalog-results]', cat), countBox = $('[data-catalog-count]', cat), side = $('[data-catalog-side]', cat);
    var base = cat.getAttribute('data-base') || '/tour/', sortSel = $('[data-sort]', cat), ctrl = null, facetCtrl = null;
    var liveBase = cat.getAttribute('data-live-base') || '', liveParams = (cat.getAttribute('data-live-params') || '').split(',').filter(Boolean);
    // В предпросмотре документ находится на домене платформы, а <base> ведёт на сайт.
    // JSON запрашиваем через проверяющий владельца прокси, историю оставляем в превью.
    var previewUrl = /\/projects\/\d+\/editor\/preview\/?$/.test(window.location.pathname) ? new URL(window.location.href) : null;
    function publicUrl() { return previewUrl ? (new URL(window.location.href).searchParams.get('path') || '/') : window.location.pathname + window.location.search; }
    function browserUrl(url) {
      if (!previewUrl) return url;
      var target = new URL(previewUrl.href); target.searchParams.set('path', url); target.searchParams.delete('format'); return target.href;
    }
    function fetchUrl(url) {
      if (!previewUrl) return url;
      var target = new URL(browserUrl(url)); target.searchParams.set('format', 'json'); return target.href;
    }
    var initialValues = {};
    if (form) Array.prototype.forEach.call(form.elements, function (el) { if (el.name) initialValues[el.name] = el.value; });
    var toggle = $('[data-side-toggle]', cat), message = document.createElement('p');
    message.className = 'catalog-message'; message.hidden = true; message.setAttribute('role', 'status');
    if (results) results.before(message);
    if (toggle && side) toggle.addEventListener('click', function () { var on = side.classList.toggle('is-open'); toggle.setAttribute('aria-expanded', on ? 'true' : 'false'); });
    var view = 'grid';
    try { view = localStorage.getItem('adeti-catalog-view') || 'grid'; } catch (err) {}
    function applyView(v) {
      view = v; if (results) results.classList.toggle('view-rows', v === 'rows');
      $$('[data-view]', cat).forEach(function (b) { var on = b.getAttribute('data-view') === v; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      try { localStorage.setItem('adeti-catalog-view', v); } catch (err) {}
    }
    $$('[data-view]', cat).forEach(function (b) { b.addEventListener('click', function () { applyView(b.getAttribute('data-view')); }); });
    applyView(view);
    if (!form || !results) return;
    function narrow() { return window.innerWidth < 1024; }
    function usesLanding() {
      if (!liveBase || new URL(publicUrl(), document.baseURI).pathname !== new URL(liveBase, document.baseURI).pathname) return false;
      return Array.prototype.every.call(form.elements, function (el) { return !el.name || el.type === 'submit' || liveParams.indexOf(el.name) >= 0 || el.value === (initialValues[el.name] || ''); });
    }
    function currentUrl(extra) {
      var onlyLive = usesLanding(), p = new URLSearchParams();
      Array.prototype.forEach.call(form.elements, function (el) { if (el.name && el.value && el.type !== 'submit' && (!onlyLive || liveParams.indexOf(el.name) >= 0)) p.set(el.name, el.value); });
      if (sortSel && sortSel.value && sortSel.value !== 'default') p.set('sort', sortSel.value);
      if (extra) Object.keys(extra).forEach(function (k) { if (extra[k]) p.set(k, extra[k]); else p.delete(k); });
      var q = p.toString();
      return (onlyLive ? liveBase : base) + (q ? '?' + q : '');
    }
    function apply(closeFilters) { load(currentUrl({ page: '' }), true, !!closeFilters); }
    function cancelFacets() {
      if (facetCtrl) facetCtrl.abort();
      facetCtrl = null; form.removeAttribute('aria-busy');
    }
    function updateFacets(facets) {
      Object.keys(facets || {}).forEach(function (dim) {
        var select = form.querySelector('select[name="' + dim + '"]'); if (!select) return;
        Array.prototype.forEach.call(select.options, function (option) {
          if (!option.value) return;
          var n = facets[dim][option.value] || 0;
          option.setAttribute('data-count', String(n)); option.textContent = ddLabel(option) + ' · ' + n;
          // Выбор пользователя сохраняем даже при нулевом результате; остальные пустые варианты убираем.
          option.hidden = n === 0 && option.value !== select.value;
          option.disabled = option.hidden;
        });
        select.dispatchEvent(new Event('dd:refresh'));
      });
    }
    function pending() {
      var label = $('[data-catalog-submit-label]', form); if (label) label.textContent = 'Показать туры';
      message.hidden = true; cancelFacets();
      if (ctrl) ctrl.abort(); ctrl = null; results.classList.remove('is-loading');
      var url = currentUrl({ page: '' }), request = new AbortController(); facetCtrl = request;
      form.setAttribute('aria-busy', 'true');
      var target = new URL(url, document.baseURI); target.searchParams.set('_facets', '1');
      fetch(fetchUrl(target.pathname + target.search), { signal: request.signal, cache: 'no-store', headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' } }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); }).then(function (d) {
        if (request !== facetCtrl || url !== currentUrl({ page: '' })) return;
        updateFacets(d.facets); form.removeAttribute('aria-busy'); facetCtrl = null;
      }).catch(function (err) {
        if (request !== facetCtrl || err.name === 'AbortError') return;
        form.removeAttribute('aria-busy'); facetCtrl = null;
        message.textContent = 'Не удалось пересчитать варианты фильтра. Выбранные параметры сохранены. Нажмите «Показать туры», чтобы повторить.'; message.hidden = false;
      });
    }
    function syncForm(url) {
      var target = new URL(url, document.baseURI), p = target.searchParams;
      var onLanding = liveBase && target.pathname === new URL(liveBase, document.baseURI).pathname;
      Array.prototype.forEach.call(form.elements, function (el) {
        if (!el.name || el.type === 'submit') return;
        var value = p.has(el.name) ? p.get(el.name) : (onLanding ? initialValues[el.name] || '' : '');
        if (el.value !== value) { el.value = value; if (el.tagName === 'SELECT') el.dispatchEvent(new Event('dd:refresh')); }
      });
      if (sortSel) { sortSel.value = p.get('sort') || 'default'; sortSel.dispatchEvent(new Event('dd:refresh')); }
    }
    function load(url, push, closeFilters) {
      if (pendingSearch) pendingSearch.cancel();
      if (suggest) suggest.cancel();
      if (sctrl) sctrl.abort();
      if (box) box.hidden = true;
      cancelFacets();
      if (ctrl) ctrl.abort();
      var request = new AbortController(); ctrl = request;
      results.classList.add('is-loading'); message.hidden = true;
      fetch(fetchUrl(url), { signal: request.signal, cache: 'no-store', headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' } }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); }).then(function (d) {
        if (request !== ctrl) return;
        results.innerHTML = d.html;
        if (countBox) countBox.innerHTML = d.count;
        var label = $('[data-catalog-submit-label]', form); if (label) label.textContent = 'Показать туры';
        var hints = $('[data-catalog-hints]', cat); if (hints && d.hints) { var tmp = document.createElement('div'); tmp.innerHTML = d.hints; var fresh = tmp.querySelector('[data-catalog-hints]'); if (fresh) hints.innerHTML = fresh.innerHTML; }
        updateFacets(d.facets);
        if (d.heading) {
          var heading = cat.closest('.page-top').querySelector('.page-h1'); if (heading) heading.textContent = d.heading;
          var lead = cat.closest('.page-top').querySelector('.page-lead'); if (lead && d.lead) lead.textContent = d.lead;
        }
        var seo = $('[data-catalog-seo]', cat); if (seo) seo.hidden = d.mode === 'catalog';
        if (push) history.pushState({ catalog: url }, '', browserUrl(url));
        results.classList.remove('is-loading'); applyView(view);
        if (closeFilters && side) { side.classList.remove('is-open'); if (toggle) toggle.setAttribute('aria-expanded', 'false'); }
        var top = results.getBoundingClientRect().top + window.pageYOffset - 90;
        if (push && (closeFilters || window.pageYOffset > top)) window.scrollTo({ top: top, behavior: 'smooth' });
      }).catch(function (err) {
        if (request !== ctrl || err.name === 'AbortError') return;
        results.classList.remove('is-loading'); message.textContent = 'Не удалось обновить список. Выбранные параметры сохранены. Нажмите «Показать туры», чтобы повторить.'; message.hidden = false;
      });
    }
    form.addEventListener('change', function (event) { if (event.target.name === 'q') return; if (narrow()) pending(); else apply(false); });
    form.addEventListener('submit', function (event) { event.preventDefault(); apply(narrow()); });
    if (sortSel) sortSel.addEventListener('change', function () { apply(false); });
    cat.addEventListener('click', function (event) {
      var reset = event.target.closest('[data-catalog-reset]');
      if (reset) {
        event.preventDefault();
        Array.prototype.forEach.call(form.elements, function (el) { if (el.name && el.type !== 'submit') { el.value = ''; if (el.tagName === 'SELECT') el.dispatchEvent(new Event('dd:refresh')); } });
        if (sortSel) { sortSel.value = 'default'; sortSel.dispatchEvent(new Event('dd:refresh')); }
        if (narrow() && side && side.classList.contains('is-open')) pending(); else load(base, true, false);
        return;
      }
      var hint = event.target.closest('[data-hint]');
      if (hint) {
        event.preventDefault();
        var select = form.querySelector('select[name="' + hint.getAttribute('data-hint') + '"]');
        if (select) { select.value = hint.classList.contains('is-on') ? '' : hint.getAttribute('data-value'); select.dispatchEvent(new Event('dd:refresh')); }
        if (narrow() && side && side.classList.contains('is-open')) pending(); else apply(false);
        return;
      }
      var page = event.target.closest('[data-catalog-results] .pagination a');
      if (page) { event.preventDefault(); load(page.getAttribute('href'), true, false); }
    });
    window.addEventListener('popstate', function (event) { if (event.state && event.state.catalog) { syncForm(event.state.catalog); load(event.state.catalog, false, false); } });
    history.replaceState({ catalog: publicUrl() }, '');
    /* подсказки по названию */
    var q = $('[data-suggest]', form), box = $('[data-suggest-list]', form), sctrl = null, sActive = -1, pendingSearch = null;
    if (q && box) {
      pendingSearch = debounce(function () { if (narrow()) pending(); }, 250);
      q.addEventListener('input', pendingSearch);
      var hide = function () { if (suggest) suggest.cancel(); if (sctrl) sctrl.abort(); box.hidden = true; box.innerHTML = ''; sActive = -1; };
      var suggest = debounce(function () {
        var text = q.value.trim();
        if (text.length < 2) { hide(); return; }
        if (sctrl) sctrl.abort();
        var suggestionRequest = new AbortController(); sctrl = suggestionRequest;
        fetch(fetchUrl('/api/v1/tours/?q=' + encodeURIComponent(text) + '&perPage=6'), { signal: suggestionRequest.signal, headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (d) {
          if (suggestionRequest !== sctrl || suggestionRequest.signal.aborted || q.value.trim() !== text) return;
          box.innerHTML = '';
          if (!d.items || !d.items.length) { var em = document.createElement('div'); em.className = 'suggest-empty'; em.textContent = 'Ничего не нашли — попробуйте другое слово'; box.appendChild(em); box.hidden = false; return; }
          d.items.forEach(function (it) {
            var a = document.createElement('a'); a.href = it.url;
            var img = document.createElement('img'); img.src = it.cover || '/images/placeholder.svg'; img.alt = ''; a.appendChild(img);
            var sp = document.createElement('span'); sp.textContent = it.title + (it.region ? ' · ' + it.region.name : ''); a.appendChild(sp);
            box.appendChild(a);
          });
          var all = document.createElement('a'); all.className = 'suggest-all'; all.href = currentUrl({ page: '' }); all.textContent = 'Показать все ' + d.total;
          all.addEventListener('click', function (e) { e.preventDefault(); hide(); apply('q'); });
          box.appendChild(all);
          box.hidden = false;
        }).catch(function () {});
      }, 250);
      q.addEventListener('input', suggest);
      q.addEventListener('keydown', function (e) {
        var links = $$('a', box);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { if (box.hidden || !links.length) return; e.preventDefault(); sActive = (sActive + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length; links.forEach(function (a, k) { a.classList.toggle('is-active', k === sActive); }); }
        else if (e.key === 'Enter') { e.preventDefault(); if (!box.hidden && links[sActive]) links[sActive].click(); else { hide(); apply('q'); } }
        else if (e.key === 'Escape') hide();
      });
      document.addEventListener('click', function (e) { if (!e.target.closest('[data-suggest-wrap]')) hide(); });
    }
  });

  /* --- Лендинг тура: якорное меню с подсветкой, дни программы, чеклист документов, бюджет перелёта --- */
  $$('[data-subnav]').forEach(function (nav) {
    var links = $$('[data-subnav-link]', nav), sections = $$('[data-subnav-section]');
    if (!links.length || !sections.length || !('IntersectionObserver' in window)) return;
    var current = '';
    function mark(key) {
      if (key === current) return;
      current = key;
      links.forEach(function (a) { var on = a.getAttribute('data-subnav-link') === key; a.classList.toggle('is-on', on); if (on) a.scrollIntoView({ block: 'nearest', inline: 'nearest' }); });
    }
    var io = new IntersectionObserver(function (entries) {
      var best = null;
      entries.forEach(function (en) { if (en.isIntersecting && (!best || en.boundingClientRect.top < best.boundingClientRect.top)) best = en; });
      if (best) mark(best.target.getAttribute('data-subnav-section'));
    }, { rootMargin: '-40% 0px -50% 0px', threshold: 0 });
    sections.forEach(function (sec) { io.observe(sec); });
    links.forEach(function (a) { a.addEventListener('click', function () { mark(a.getAttribute('data-subnav-link')); }); });
  });
  $$('[data-days]').forEach(function (box) {
    var tabs = $$('[data-day-tab]', box), panels = $$('[data-day-panel]', box), all = $('[data-days-all]', box);
    function showDay(n) {
      box.classList.remove('show-all'); if (all) all.setAttribute('aria-pressed', 'false');
      tabs.forEach(function (t) { var on = t.getAttribute('data-day-tab') === String(n); t.classList.toggle('is-on', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); });
      panels.forEach(function (p) { p.hidden = p.getAttribute('data-day-panel') !== String(n); });
    }
    tabs.forEach(function (t) { t.addEventListener('click', function () { showDay(t.getAttribute('data-day-tab')); }); });
    if (all) all.addEventListener('click', function () {
      var on = all.getAttribute('aria-pressed') !== 'true';
      if (!on) { showDay(0); return; }
      all.setAttribute('aria-pressed', 'true'); box.classList.add('show-all');
      tabs.forEach(function (t) { t.classList.remove('is-on'); t.setAttribute('aria-selected', 'false'); });
      panels.forEach(function (p) { p.hidden = false; });
    });
  });
  $$('[data-checklist]').forEach(function (box) {
    var key = 'adeti-check-' + box.getAttribute('data-checklist'), boxes = $$('[data-check]', box), count = $('[data-checklist-count]', box), saved = {};
    try { saved = JSON.parse(localStorage.getItem(key) || '{}') || {}; } catch (err) { saved = {}; }
    function render() {
      var n = boxes.filter(function (b) { return b.checked; }).length;
      if (count) count.textContent = n + ' из ' + boxes.length + (n === boxes.length ? ' — всё готово' : '');
      box.classList.toggle('is-done', n === boxes.length);
    }
    boxes.forEach(function (b) {
      b.checked = !!saved[b.getAttribute('data-check')];
      b.addEventListener('change', function () { saved[b.getAttribute('data-check')] = b.checked; try { localStorage.setItem(key, JSON.stringify(saved)); } catch (err) {} render(); });
    });
    render();
  });
  $$('[data-budget]').forEach(function (box) {
    var price = parseInt(box.getAttribute('data-price') || '0', 10), n = 1, out = $('[data-budget-n]', box), total = $('[data-budget-total]', box), label = $('[data-budget-label]', box);
    function plural(k, f) { var m = k % 10, h = k % 100; return f[(h > 10 && h < 20) ? 2 : (m === 1 ? 0 : (m > 1 && m < 5 ? 1 : 2))]; }
    function render() { if (out) out.textContent = String(n); if (total) total.textContent = money(price * n); if (label) label.textContent = n + ' ' + plural(n, ['человека', 'человек', 'человек']); }
    $$('[data-step]', box).forEach(function (b) { b.addEventListener('click', function () { n = Math.min(12, Math.max(1, n + parseInt(b.getAttribute('data-step'), 10))); render(); }); });
    render();
  });

  /* --- Оформление: яркий/строгий стиль и тёмная тема. Сервер ставит атрибуты на <html> по cookie (at_look) --- */
  (function () {
    var root = document.documentElement;
    function setCookie(k, v) { try { document.cookie = k + '=' + v + '; path=/; max-age=31536000; SameSite=Lax'; } catch (err) {} }
    function apply(style, theme) {
      if (theme === 'dark') style = 'simple'; // тёмная тема есть только у строгого стиля
      root.setAttribute('data-style', style); root.setAttribute('data-theme', theme);
      setCookie('at_style', style); setCookie('at_theme', theme);
      $$('[data-set-style]').forEach(function (b) { b.setAttribute('aria-pressed', b.getAttribute('data-set-style') === style ? 'true' : 'false'); });
      $$('[data-toggle-theme]').forEach(function (b) {
        var dark = theme === 'dark';
        b.setAttribute('aria-pressed', dark ? 'true' : 'false');
        b.setAttribute('aria-label', dark ? 'Включить светлую тему' : 'Включить тёмную тему');
        b.title = dark ? 'Светлая тема' : 'Тёмная тема';
      });
      var tc = $('meta[name="theme-color"]'); if (tc) tc.setAttribute('content', theme === 'dark' ? '#0f1116' : (style === 'simple' ? '#ffffff' : '#3559ff'));
      var cs = $('meta[name="color-scheme"]'); if (cs) cs.setAttribute('content', theme === 'dark' ? 'dark' : 'light');
    }
    $$('[data-set-style]').forEach(function (b) {
      b.addEventListener('click', function () {
        var style = b.getAttribute('data-set-style');
        apply(style, style === 'bright' ? 'light' : (root.getAttribute('data-theme') || 'light'));
      });
    });
    $$('[data-toggle-theme]').forEach(function (b) {
      b.addEventListener('click', function () {
        var dark = root.getAttribute('data-theme') === 'dark';
        apply('simple', dark ? 'light' : 'dark');
      });
    });
  })();

  /* --- Cookie-плашка --- */
  var cb = $('#cookie-banner');
  if (cb) {
    var consent = null;
    try { consent = localStorage.getItem('adeti-cookie-consent'); } catch (err) { consent = '1'; }
    if (!consent) cb.hidden = false;
    var okBtn = $('#cookie-ok');
    if (okBtn) okBtn.addEventListener('click', function () { try { localStorage.setItem('adeti-cookie-consent', '1'); } catch (err) {} cb.hidden = true; });
  }
})();
