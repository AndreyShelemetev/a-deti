/* New presentation interactions; forms and booking delivery stay in app.js. */
(function () {
  'use strict';
  // Меняем только расположение элементов: правки текста и форматирования владельца сохраняются.
  document.querySelectorAll('.page-tour-modern .tour-hero__title').forEach(function (title) {
    var length = Array.from(title.textContent.trim()).length;
    title.classList.toggle('tour-hero__title--long', length > 85);
    title.classList.toggle('tour-hero__title--medium', length > 45 && length <= 85);
  });
  document.querySelectorAll('.page-tour-modern #tour-section-programm').forEach(function (section) {
    var row = section.querySelector('.camp-section-row, .camp-section-full');
    if (!row) return;
    row.classList.add('camp-section-row--program');
    var photo = Array.from(row.children).find(function (element) { return element.classList.contains('camp-section-image'); });
    if (photo && row.firstElementChild !== photo) row.insertBefore(photo, row.firstElementChild);
  });
  var header = document.querySelector('.header');
  var burger = document.getElementById('design-burger');
  var menu = document.getElementById('design-nav');
  function closeMenu() {
    if (!menu || !burger) return;
    menu.classList.remove('is-open');
    burger.setAttribute('aria-expanded', 'false');
  }
  if (burger && menu) {
    burger.addEventListener('click', function () {
      var open = burger.getAttribute('aria-expanded') !== 'true';
      menu.classList.toggle('is-open', open);
      burger.setAttribute('aria-expanded', String(open));
    });
    menu.addEventListener('click', function (event) { if (event.target.closest('a, [data-modal]')) closeMenu(); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeMenu(); });
    document.addEventListener('click', function (event) { if (!header.contains(event.target)) closeMenu(); });
  }
  function updateHeaderHeight() {
    if (header) document.documentElement.style.setProperty('--hdr-h', Math.round(header.getBoundingClientRect().height) + 'px');
  }
  updateHeaderHeight();
  window.addEventListener('resize', updateHeaderHeight, { passive: true });
  if (document.fonts) document.fonts.ready.then(updateHeaderHeight);

  document.querySelectorAll('[data-design-banner]').forEach(function (root) {
    var slides = Array.from(root.querySelectorAll('.banner__slide'));
    var dots = Array.from(root.querySelectorAll('.banner__dots button'));
    var current = 0, timer;
    function show(index) {
      current = index;
      slides.forEach(function (slide, i) {
        slide.classList.toggle('is-active', i === current);
        slide.setAttribute('aria-hidden', String(i !== current));
        slide.tabIndex = i === current ? 0 : -1;
      });
      dots.forEach(function (dot, i) { dot.setAttribute('aria-current', String(i === current)); });
    }
    function stop() { window.clearInterval(timer); }
    function start() {
      stop();
      if (slides.length > 1 && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        timer = window.setInterval(function () { show((current + 1) % slides.length); }, 6500);
      }
    }
    dots.forEach(function (dot, i) { dot.addEventListener('click', function () { show(i); start(); }); });
    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    root.addEventListener('focusin', stop);
    root.addEventListener('focusout', start);
    start();
  });

  document.querySelectorAll('.design-booking').forEach(function (widget) {
    var select = widget.querySelector('.booking-select');
    if (!select) return;
    var rows = Array.from(widget.querySelectorAll('.shifts__list > .shift'));
    var calendar = document.getElementById('modal-shifts');
    var cards = calendar ? Array.from(calendar.querySelectorAll('[data-calendar-shift]')) : [];
    function syncShiftPreview() {
      var selectedRow = null;
      rows.forEach(function (row, index) {
        var radio = row.querySelector('input[type="radio"]');
        radio.checked = radio.value === select.value;
        row.hidden = index >= 4;
        if (radio.checked) selectedRow = row;
      });
      if (selectedRow && selectedRow.hidden) {
        if (rows[3]) rows[3].hidden = true;
        selectedRow.hidden = false;
      }
      cards.forEach(function (card) { card.setAttribute('aria-pressed', String(card.getAttribute('data-calendar-shift') === select.value)); });
    }
    widget.querySelectorAll('input[type="radio"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        select.value = radio.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
    select.addEventListener('change', syncShiftPreview);
    syncShiftPreview();
    if (!calendar) return;
    var search = calendar.querySelector('[data-calendar-search]');
    var month = calendar.querySelector('[data-calendar-month]');
    var count = calendar.querySelector('[data-calendar-count]');
    function filterShifts() {
      var query = search.value.trim().toLowerCase();
      var shown = 0;
      cards.forEach(function (card) {
        card.hidden = (month.value !== '' && card.getAttribute('data-month') !== month.value) || (query !== '' && !card.textContent.toLowerCase().includes(query));
        if (!card.hidden) shown++;
      });
      count.textContent = 'Показано вариантов: ' + shown + ' из ' + cards.length;
      calendar.querySelector('[data-calendar-empty]').hidden = shown > 0;
    }
    search.addEventListener('input', filterShifts);
    month.addEventListener('change', filterShifts);
    cards.forEach(function (card) {
      card.addEventListener('click', function () {
        if (card.disabled) return;
        select.value = card.getAttribute('data-calendar-shift');
        select.dispatchEvent(new Event('change', { bubbles: true }));
        calendar.close();
      });
    });
    document.querySelectorAll('[data-modal="shifts"]').forEach(function (button) {
      button.addEventListener('click', function () { search.value = ''; month.value = ''; month.dispatchEvent(new Event('change', { bubbles: true })); });
    });
  });

  document.querySelectorAll('[data-camp-gallery-more]').forEach(function (button) {
    button.addEventListener('click', function () {
      var gallery = document.getElementById('tour-gallery');
      if (!gallery) return;
      Array.prototype.slice.call(gallery.querySelectorAll('[data-camp-gallery-hidden]'), 0, 12).forEach(function (photo) {
        photo.hidden = false;
        photo.removeAttribute('data-camp-gallery-hidden');
      });
      button.hidden = !gallery.querySelector('[data-camp-gallery-hidden]');
    });
  });

  var lightbox = document.getElementById('design-lightbox');
  var galleryData = document.getElementById('design-gallery-data');
  if (lightbox && galleryData) {
    var photos = JSON.parse(galleryData.textContent);
    var image = lightbox.querySelector('img');
    image.draggable = false;
    lightbox.addEventListener('selectstart', function (event) { event.preventDefault(); });
    var count = lightbox.querySelector('.design-lb-count');
    var index = 0, lastFocus;
    function showPhoto(n) {
      index = (n + photos.length) % photos.length;
      image.src = photos[index].src;
      image.alt = photos[index].alt || '';
      count.textContent = (index + 1) + ' / ' + photos.length;
    }
    document.querySelectorAll('[data-design-photo]').forEach(function (button) {
      button.addEventListener('click', function (event) {
        if (button.tagName === 'A' && (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey)) return;
        event.preventDefault();
        var selection = window.getSelection();
        if (selection) selection.removeAllRanges();
        lastFocus = button;
        showPhoto(Number(button.getAttribute('data-design-photo')));
        lightbox.showModal();
        document.body.classList.add('modal-open');
      });
    });
    lightbox.querySelector('.design-lb-close').addEventListener('click', function () { lightbox.close(); });
    lightbox.querySelector('.design-lb-prev').addEventListener('click', function () { showPhoto(index - 1); });
    lightbox.querySelector('.design-lb-next').addEventListener('click', function () { showPhoto(index + 1); });
    lightbox.addEventListener('click', function (event) { if (event.target === lightbox) lightbox.close(); });
    lightbox.addEventListener('keydown', function (event) {
      if (event.key === 'ArrowLeft') { event.preventDefault(); showPhoto(index - 1); }
      if (event.key === 'ArrowRight') { event.preventDefault(); showPhoto(index + 1); }
    });
    lightbox.addEventListener('close', function () { document.body.classList.remove('modal-open'); if (lastFocus) lastFocus.focus(); });
    var touchX;
    lightbox.addEventListener('touchstart', function (event) { touchX = event.changedTouches[0].clientX; }, { passive: true });
    lightbox.addEventListener('touchend', function (event) {
      var delta = event.changedTouches[0].clientX - touchX;
      if (Math.abs(delta) > 50) showPhoto(index + (delta < 0 ? 1 : -1));
    }, { passive: true });
  }

  var tabs = document.querySelector('.design-tour-tabs');
  if (tabs && 'IntersectionObserver' in window) {
    var links = Array.from(tabs.querySelectorAll('a'));
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        links.forEach(function (link) {
          var active = link.getAttribute('href') === '#' + entry.target.id;
          link.classList.toggle('is-active', active);
          if (active) link.setAttribute('aria-current', 'location'); else link.removeAttribute('aria-current');
        });
      });
    }, { rootMargin: '-22% 0px -60% 0px', threshold: 0 });
    links.forEach(function (link) { var target = document.querySelector(link.getAttribute('href')); if (target) observer.observe(target); });
    function updateTabs() { tabs.classList.toggle('is-stuck', tabs.parentElement.getBoundingClientRect().top <= (header ? header.getBoundingClientRect().height : 73) + 1); }
    window.addEventListener('scroll', updateTabs, { passive: true });
    updateTabs();
  }
})();
