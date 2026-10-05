'use strict';

// ── Cookie-баннер и запуск счётчиков только после согласия ──
// Подключение: <script src="js/cookie.js" defer></script>
// На странице с трекингом MAX добавить атрибут data-tgtrack="<адрес скрипта tgtrack>".
(function () {
  var KEY = 'sm_cookie_consent';
  var VERSION = '2026-09-21'; // редакция текста баннера
  var METRIKA_ID = 103486971;
  var script = document.currentScript;
  var tgtrackSrc = script && script.getAttribute('data-tgtrack');
  var full = false;   // включён ли расширенный режим (Вебвизор, карта кликов, рекламный трекер)

  function readChoice() {
    try { var v = JSON.parse(localStorage.getItem(KEY)); return v && v.version === VERSION ? v.choice : null; }
    catch (e) { return null; }
  }
  function saveChoice(choice) {
    try { localStorage.setItem(KEY, JSON.stringify({ choice: choice, version: VERSION, ts: new Date().toISOString(), page: location.href })); }
    catch (e) {}
  }

  // Яндекс Метрика. Базовый подсчёт посещений идёт сразу — решение владельца сайта
  // от 05.10.2026. Вебвизор (запись действий на странице) и карта кликов включаются
  // только после «Принять»: они собирают заметно больше, чем счётчик визитов.
  // Параметры счётчика после инициализации не меняются, поэтому расширенный режим
  // применяется со следующей загрузки страницы после согласия.
  function startMetrika(full) {
    (function (m, e, t, r, i, k, a) {
      m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments); };
      m[i].l = 1 * new Date();
      for (var j = 0; j < document.scripts.length; j++) { if (document.scripts[j].src === r) { return; } }
      k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a);
    })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js?id=' + METRIKA_ID, 'ym');
    ym(METRIKA_ID, 'init', {
      ssr: true,
      webvisor: !!full,
      clickmap: !!full,
      ecommerce: 'dataLayer',
      referrer: document.referrer,
      url: location.href,
      accurateTrackBounce: true,
      trackLinks: true
    });
  }
  function startTgtrack() {
    if (!tgtrackSrc) return;
    var s = document.createElement('script');
    s.src = tgtrackSrc; s.async = true;
    document.body.appendChild(s);
  }
  // Полный режим: Метрика с Вебвизором и картой кликов + рекламный трекер
  function startCounters() {
    if (full) return;
    full = true;
    startMetrika(true);
    startTgtrack();
    window.dispatchEvent(new Event('cookieConsentAccepted'));
  }

  var banner;
  function buildBanner() {
    banner = document.createElement('div');
    banner.className = 'cookie-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Файлы cookie');
    banner.innerHTML =
      '<div class="cookie-banner__text"><strong>Мы используем файлы cookie</strong>' +
      'Технические файлы нужны для работы сайта, а Яндекс Метрика считает посещения и источники переходов. ' +
      'Запись действий на странице (Вебвизор), карту кликов и учёт переходов в рекламе мы включаем только с вашего согласия. ' +
      'Подробнее — в <a href="policy.html" target="_blank">политике обработки персональных данных</a>.</div>' +
      '<div class="cookie-banner__actions">' +
      '<button type="button" class="btn btn-outline btn-sm" data-cookie="accept">Принять</button>' +
      '<button type="button" class="btn btn-outline btn-sm" data-cookie="reject">Отклонить</button>' +
      '</div>';
    banner.addEventListener('click', function (e) {
      var b = e.target.closest('[data-cookie]'); if (!b) return;
      var choice = b.getAttribute('data-cookie') === 'accept' ? 'accepted' : 'rejected';
      saveChoice(choice);
      hideBanner();
      if (choice === 'accepted') {
        // Метрика уже считает в базовом режиме: сразу подключаем рекламный трекер,
        // Вебвизор и карта кликов заработают со следующей загрузки страницы.
        if (!full) {
          full = true;
          startTgtrack();
          window.dispatchEvent(new Event('cookieConsentAccepted'));
        }
      } else if (full) {
        location.reload(); // отзыв согласия — страница без Вебвизора и трекера
      }
    });
    document.body.appendChild(banner);
  }
  function showBanner() { if (!banner) buildBanner(); banner.hidden = false; }
  function hideBanner() { if (banner) banner.hidden = true; }

  function init() {
    var choice = readChoice();
    if (choice === 'accepted') {
      startCounters();
    } else {
      startMetrika(false);               // базовый подсчёт посещений — всем
      if (choice !== 'rejected') showBanner();
    }
    // Ссылка «Файлы cookie» в футере — повторный выбор
    document.querySelectorAll('[data-cookie-settings]').forEach(function (a) {
      a.addEventListener('click', function (e) { e.preventDefault(); showBanner(); });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
