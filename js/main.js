'use strict';

// ── Reveal on Scroll ──────────────────────────────────
const revealObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('visible'); revealObserver.unobserve(e.target); }
  });
}, { threshold: 0.05 });
document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));
// Показываем элементы видимые при загрузке
window.addEventListener('load', function() {
  document.querySelectorAll('.reveal').forEach(el => {
    if (el.getBoundingClientRect().top < window.innerHeight) el.classList.add('visible');
  });
});

// ── Animated Counters ─────────────────────────────────
function animateCounter(el) {
  const target = parseInt(el.dataset.target, 10), duration = 1800, step = 16;
  const increment = target / (duration / step);
  let current = 0;
  const timer = setInterval(() => {
    current += increment;
    if (current >= target) { current = target; clearInterval(timer); }
    el.textContent = Math.floor(current).toLocaleString('ru-RU');
  }, step);
}
const counterObserver = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) { animateCounter(e.target); counterObserver.unobserve(e.target); } });
}, { threshold: 0.5 });
document.querySelectorAll('.counter').forEach(el => counterObserver.observe(el));

// ── Header Scroll ─────────────────────────────────────
const header = document.getElementById('header');
window.addEventListener('scroll', () => {
  header.style.background = window.scrollY > 80 ? 'rgba(255,255,255,.97)' : 'rgba(255,255,255,.92)';
}, { passive: true });

// ── Burger Menu ───────────────────────────────────────
const burgerBtn = document.getElementById('burgerBtn');
const mobileNav = document.getElementById('mobileNav');
burgerBtn.addEventListener('click', () => {
  const open = mobileNav.classList.toggle('open');
  burgerBtn.classList.toggle('active', open);
});
mobileNav.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
  mobileNav.classList.remove('open'); burgerBtn.classList.remove('active');
}));

// ── Phone Carousel ────────────────────────────────────
const posts = document.querySelectorAll('.phone-post');
let currentPost = 0;
if (posts.length > 0) setInterval(() => {
  posts[currentPost].classList.remove('active');
  currentPost = (currentPost + 1) % posts.length;
  posts[currentPost].classList.add('active');
}, 3500);

// ── FAQ Accordion ─────────────────────────────────────
document.querySelectorAll('.faq-q').forEach(btn => {
  btn.addEventListener('click', () => {
    const item = btn.closest('.faq-item'), isOpen = item.classList.contains('open');
    document.querySelectorAll('.faq-item.open').forEach(o => {
      o.classList.remove('open'); o.querySelector('.faq-q').setAttribute('aria-expanded', 'false');
    });
    if (!isOpen) { item.classList.add('open'); btn.setAttribute('aria-expanded', 'true'); }
  });
});

// ── Posts Expand/Collapse ─────────────────────────────
document.querySelectorAll('.post-toggle-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const card = btn.closest('.post-card-inner');
    const full = card.querySelector('.post-full'), preview = card.querySelector('.post-preview');
    if (btn.dataset.state === 'collapsed') {
      full.classList.remove('hidden'); preview.style.display = 'none';
      btn.textContent = 'Свернуть ↑'; btn.dataset.state = 'expanded';
    } else {
      full.classList.add('hidden'); preview.style.display = '';
      btn.textContent = 'Читать полностью ↓'; btn.dataset.state = 'collapsed';
    }
  });
});

// ── Consent: подсветка чекбокса ───────────────────────
function highlightConsent(wrap) {
  wrap.classList.remove('consent-shake', 'consent-highlight');
  void wrap.offsetWidth; // reflow для перезапуска анимации
  wrap.classList.add('consent-shake', 'consent-highlight');
  wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
  setTimeout(() => wrap.classList.remove('consent-highlight'), 2500);
  setTimeout(() => wrap.classList.remove('consent-shake'), 500);
}

// Обновление состояния кнопок при смене чекбокса
function initConsent(checkboxId) {
  const cb = document.getElementById(checkboxId); if (!cb) return;
  const btns = document.querySelectorAll(`.btn-consent[data-consent="${checkboxId}"]`);
  function update() {
    btns.forEach(b => b.setAttribute('aria-disabled', cb.checked ? 'false' : 'true'));
  }
  cb.addEventListener('change', update);
  update();
}
initConsent('heroAgree');
initConsent('pricingAgree');
initConsent('finalAgree');

// Клик по кнопкам с чекбоксом — блокируем если не отмечен
// ── Единый обработчик всех кнопок MAX и Telegram ──────
function handleCtaClick(e) {
  const btn = e.currentTarget;
  const isTelegram = btn.href && btn.href.includes('t.me');

  // Кнопка шапки с data-scroll-consent
  if (btn.dataset.scrollConsent) {
    const wrap = document.getElementById(btn.dataset.scrollConsent);
    if (wrap) {
      const cb = wrap.querySelector('.consent-checkbox');
      if (cb && !cb.checked) {
        e.preventDefault();
        e.stopImmediatePropagation();
        const mob = document.getElementById('mobileNav');
        if (mob) mob.classList.remove('open');
        highlightConsent(wrap);
        return;
      }
    }
  }

  // Кнопки с data-consent (Hero, тарифы, финальный CTA)
  if (btn.classList.contains('btn-consent')) {
    if (btn.getAttribute('aria-disabled') === 'true') {
      e.preventDefault();
      e.stopImmediatePropagation();
      const wrapId = btn.dataset.consent === 'heroAgree' ? 'heroConsent'
                   : btn.dataset.consent === 'pricingAgree' ? 'pricingConsent'
                   : 'finalConsent';
      const wrap = document.getElementById(wrapId);
      if (wrap) highlightConsent(wrap);
      return;
    }
  }

  // Галочка отмечена — фиксируем в Метрике
  if (typeof ym !== 'undefined') {
    ym(103486971, 'reachGoal', isTelegram ? 'telegram' : 'max');
  }
}

document.querySelectorAll('a[href*="max.ru"], a[href*="t.me"]').forEach(btn => {
  btn.addEventListener('click', handleCtaClick);
});

// ── Posts Marquee ─────────────────────────────────────
(function initMarquee() {
  const track = document.getElementById('postsTrack'); if (!track) return;
  track.innerHTML = track.innerHTML + track.innerHTML;
})();

// ── Smooth Scroll ─────────────────────────────────────
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const id = a.getAttribute('href').slice(1); if (!id) return;
    const target = document.getElementById(id); if (!target) return;
    e.preventDefault();
    window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - 68, behavior: 'smooth' });
  });
});


