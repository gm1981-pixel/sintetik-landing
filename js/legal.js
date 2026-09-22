'use strict';

// ── Подсветка текущего раздела в оглавлении документа ──
(function () {
  try {
    var links = Array.prototype.slice.call(document.querySelectorAll('.legal-toc a'));
    if (!links.length || !('IntersectionObserver' in window)) return;
    var map = {};
    links.forEach(function (a) { map[a.getAttribute('href').slice(1)] = a; });
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) {
          links.forEach(function (a) { a.classList.remove('active'); });
          var a = map[e.target.id];
          if (a) a.classList.add('active');
        }
      });
    }, { rootMargin: '0px 0px -70% 0px', threshold: 0 });
    document.querySelectorAll('.legal-main section[id]').forEach(function (s) { io.observe(s); });
  } catch (e) {}
})();
