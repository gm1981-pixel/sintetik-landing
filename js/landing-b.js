'use strict';

// Вариант B лендинга: демонстрация переписки с ботом, FAQ и цели Метрики.
// Чекбокса согласия здесь нет: акцепт оферты — само нажатие кнопки (ст. 438 ГК),
// текст об этом стоит под кнопками. Поэтому обработчик только отправляет цель.
(function () {
  var METRIKA_ID = 103486971;

  // ── Переписка с ботом в первом экране ──
  var body = document.getElementById('chatBody');
  var status = document.getElementById('chatStatus');
  var SCRIPT = [
    { type: 'out', delay: 600, text: '🎤 Голосовое, 7 сек' },
    { type: 'in', delay: 1100, text: 'Понял: пост о том, почему хорошо быть предпринимателем. Пишу и рисую картинку…', status: 'печатает…' },
    { type: 'post', delay: 1800, img: 'images/post-03.jpg', text: '<strong>🚀 Почему хорошо быть предпринимателем</strong><br>Не про розовые единороги, а про будни, где сам выбираешь темп, нишу и потолок…', meta: 'Готово за 1 мин 54 с' },
    { type: 'out', delay: 1400, text: 'Опубликовать' },
    { type: 'in', delay: 900, text: '✅ Опубликовано в канале. Следующий пост по расписанию — завтра в 10:00.', status: 'бот онлайн' }
  ];

  function addMessage(item) {
    var el = document.createElement('div');
    if (item.type === 'post') {
      el.className = 'msg msg--in msg--post';
      el.innerHTML = '<img src="' + item.img + '" alt="Пост, созданный сервисом" />' +
        '<div class="msg__text">' + item.text + '</div>' +
        '<div class="msg__meta">' + item.meta + '</div>';
    } else {
      el.className = 'msg msg--' + (item.type === 'out' ? 'out' : 'in');
      el.textContent = item.text;
    }
    body.appendChild(el);
  }

  function playChat() {
    var i = 0;
    (function next() {
      if (i >= SCRIPT.length) return;
      var item = SCRIPT[i++];
      setTimeout(function () {
        if (item.status && status) status.textContent = item.status;
        addMessage(item);
        next();
      }, item.delay);
    })();
  }

  if (body) {
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      SCRIPT.forEach(addMessage);   // без анимации — сразу всё
    } else if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        if (entries[0].isIntersecting) { io.disconnect(); playChat(); }
      }, { threshold: 0.25 });
      io.observe(document.getElementById('chat'));
    } else {
      playChat();
    }
  }

  // ── FAQ ──
  document.querySelectorAll('.faq__q').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.closest('.faq__item');
      var open = item.classList.contains('open');
      document.querySelectorAll('.faq__item.open').forEach(function (o) {
        o.classList.remove('open');
        o.querySelector('.faq__q').setAttribute('aria-expanded', 'false');
      });
      if (!open) { item.classList.add('open'); btn.setAttribute('aria-expanded', 'true'); }
    });
  });

  // ── Цели Метрики: те же, что в варианте A ──
  document.querySelectorAll('a[href*="t.me"], a[href*="max.ru"]').forEach(function (a) {
    a.addEventListener('click', function () {
      if (typeof ym === 'undefined') return;
      ym(METRIKA_ID, 'reachGoal', a.href.indexOf('t.me') !== -1 ? 'telegram' : 'max', { place: a.getAttribute('data-cta') || '' });
    });
  });
})();
