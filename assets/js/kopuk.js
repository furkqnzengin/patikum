/* Köpük — Patikum'un ekranda gezinen kedisi 🐾
 * Ekranın altında yürür, arada oturur, uyur; tıklayınca miyavlar ve mesaj gösterir.
 * Gizlemek için baloncuktaki "Köpük'ü gizle" bağlantısı (tercih tarayıcıda hatırlanır).
 */
(function () {
  'use strict';
  var KEY = 'pt_kopuk_hidden';
  var cfg = window.PT_KOPUK || {};
  try { if (localStorage.getItem(KEY) === '1') { addShowButton(); return; } } catch (e) {}

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var msgs = (cfg.messages && cfg.messages.length) ? cfg.messages : ['Miyav! Ben Köpük 🐾'];

  var svg =
    '<svg viewBox="0 0 140 100" aria-hidden="true">' +
    '<g class="k-tail"><path d="M33 52 C 15 49, 8 31, 15 17 C 18 12, 25 13, 23 19 C 17 30, 21 42, 37 47 Z" fill="#fff" stroke="#cfd7de" stroke-width="1.6" stroke-linejoin="round"/></g>' +
    '<g class="k-leg l2"><rect x="48" y="57" width="10" height="27" rx="5" fill="#eef2f5" stroke="#cfd7de" stroke-width="1.4"/></g>' +
    '<g class="k-leg l4"><rect x="91" y="57" width="10" height="27" rx="5" fill="#eef2f5" stroke="#cfd7de" stroke-width="1.4"/></g>' +
    '<ellipse class="k-body" cx="67" cy="56" rx="37" ry="19.5" fill="#fff" stroke="#cfd7de" stroke-width="1.6"/>' +
    '<path d="M44 46 q4 -6 9 -2 q4 -6 9 -1 q4 -5 9 0" fill="none" stroke="#e3e9ee" stroke-width="2" stroke-linecap="round"/>' +
    '<g class="k-leg l1"><rect x="39" y="58" width="10.5" height="27" rx="5" fill="#fff" stroke="#cfd7de" stroke-width="1.4"/><ellipse cx="44.2" cy="84" rx="6.5" ry="3.2" fill="#fff" stroke="#cfd7de" stroke-width="1.2"/></g>' +
    '<g class="k-leg l3"><rect x="82" y="58" width="10.5" height="27" rx="5" fill="#fff" stroke="#cfd7de" stroke-width="1.4"/><ellipse cx="87.2" cy="84" rx="6.5" ry="3.2" fill="#fff" stroke="#cfd7de" stroke-width="1.2"/></g>' +
    '<g class="k-head">' +
    '<path d="M90 31 L94 9 L106 23 Z" fill="#fff" stroke="#cfd7de" stroke-width="1.6" stroke-linejoin="round"/><path d="M93 27 L95 15 L102 23 Z" fill="#f7b8c6"/>' +
    '<path d="M110 22 L121 8 L124 31 Z" fill="#fff" stroke="#cfd7de" stroke-width="1.6" stroke-linejoin="round"/><path d="M113 23 L120 14 L121 27 Z" fill="#f7b8c6"/>' +
    '<circle cx="107" cy="40" r="19.5" fill="#fff" stroke="#cfd7de" stroke-width="1.6"/>' +
    '<g class="k-eyes-open"><ellipse cx="100" cy="39" rx="3" ry="4.2" fill="#2a8088"/><ellipse cx="114" cy="39" rx="3" ry="4.2" fill="#2a8088"/>' +
    '<ellipse cx="100.4" cy="39.6" rx="1.3" ry="2.6" fill="#10262a"/><ellipse cx="114.4" cy="39.6" rx="1.3" ry="2.6" fill="#10262a"/>' +
    '<circle cx="99.3" cy="37.3" r="1" fill="#fff"/><circle cx="113.3" cy="37.3" r="1" fill="#fff"/></g>' +
    '<g class="k-eyes-closed"><path d="M96.5 40 q3.5 2.6 7 0" fill="none" stroke="#5b6670" stroke-width="1.6" stroke-linecap="round"/><path d="M110.5 40 q3.5 2.6 7 0" fill="none" stroke="#5b6670" stroke-width="1.6" stroke-linecap="round"/></g>' +
    '<circle cx="96" cy="47" r="3" fill="#f7b8c6" opacity=".55"/><circle cx="118" cy="47" r="3" fill="#f7b8c6" opacity=".55"/>' +
    '<path d="M105 45.5 h4 l-2 2.6 z" fill="#f08aa2"/>' +
    '<path d="M107 48 q-1.8 3 -4 1.4 M107 48 q1.8 3 4 1.4" fill="none" stroke="#7a858f" stroke-width="1.1" stroke-linecap="round"/>' +
    '<path d="M99 46 l-12 -2 M99 48 l-12 1.5 M115 46 l12 -2 M115 48 l12 1.5" stroke="#b8c2ca" stroke-width=".9" stroke-linecap="round"/>' +
    '<path d="M91 52 q16 9 32 -2" fill="none" stroke="#1f6a70" stroke-width="4" stroke-linecap="round"/>' +
    '<circle cx="106" cy="58" r="3.6" fill="#d9a74e" stroke="#a8692f" stroke-width="1"/>' +
    '</g>' +
    '<g class="k-zzz"><text x="118" y="14">z</text><text x="126" y="7">z</text></g>' +
    '</svg>';

  var el = document.createElement('div');
  el.className = 'kopuk';
  el.setAttribute('role', 'button');
  el.setAttribute('tabindex', '0');
  el.setAttribute('aria-label', 'Köpük, kliniğin kedisi');
  el.innerHTML = '<div class="kopuk-bubble" role="status"><span class="txt"></span><button type="button" class="kopuk-hide">Köpük\'ü gizle</button></div>' +
    '<div class="kopuk-cat">' + svg + '</div><span class="kopuk-name">Köpük</span>';
  document.body.appendChild(el);
  var cat = el.querySelector('.kopuk-cat');
  var bubble = el.querySelector('.kopuk-bubble');
  var bubbleTxt = bubble.querySelector('.txt');

  var W = function () { return document.documentElement.clientWidth; };
  var size = function () { return el.offsetWidth || 110; };
  var x = 40 + Math.random() * Math.max(0, W() - 220);
  var dir = Math.random() < 0.5 ? 1 : -1;
  var speed = 0.75;
  var mode = reduce ? 'sit' : 'walk';
  var modeUntil = Date.now() + 6000;
  var bubbleTimer = null;
  var last = 0;

  function setMode(m, ms) {
    mode = m;
    modeUntil = Date.now() + ms;
    el.classList.toggle('walking', m === 'walk');
    el.classList.toggle('sitting', m === 'sit');
    el.classList.toggle('sleeping', m === 'sleep');
  }
  function say(t, ms) {
    bubbleTxt.textContent = t;
    el.classList.add('talk');
    clearTimeout(bubbleTimer);
    bubbleTimer = setTimeout(function () { el.classList.remove('talk'); }, ms || 5000);
  }
  function hearts() {
    for (var i = 0; i < 5; i++) {
      var h = document.createElement('span');
      h.className = 'kopuk-heart';
      h.textContent = ['💚', '🐾', '💛', '❤️'][i % 4];
      h.style.left = (30 + Math.random() * 50) + '%';
      h.style.animationDelay = (i * 0.09) + 's';
      el.appendChild(h);
      setTimeout(function (n) { return function () { n.remove(); }; }(h), 1600);
    }
  }
  function pick() { return msgs[Math.floor(Math.random() * msgs.length)]; }

  function tick(t) {
    if (!last) last = t;
    var dt = Math.min(64, t - last);
    last = t;
    var now = Date.now();
    var max = Math.max(40, W() - size() - 40);
    if (mode === 'walk' && !el.classList.contains('hover')) {
      x += dir * speed * dt / 16;
      if (x <= 40) { x = 40; dir = 1; }
      if (x >= max) { x = max; dir = -1; }
    }
    if (x > max) x = max;
    if (now > modeUntil && !el.classList.contains('hover')) {
      var r = Math.random();
      if (mode !== 'walk' || r < 0.15) {
        if (Math.random() < 0.3) dir = -dir;
        setMode('walk', 7000 + Math.random() * 9000);
      } else if (r < 0.75) {
        setMode('sit', 3500 + Math.random() * 5000);
        if (Math.random() < 0.45) say(pick(), 4500);
      } else {
        setMode('sleep', 9000 + Math.random() * 9000);
      }
    }
    el.style.transform = 'translateX(' + Math.round(x) + 'px)';
    cat.style.transform = dir < 0 ? 'scaleX(-1)' : 'none';
    requestAnimationFrame(tick);
  }

  function poke() {
    var wasSleeping = mode === 'sleep';
    setMode('sit', 5000);
    say(wasSleeping ? 'Mrrr… uyuyordum ama olsun 😺' : (Math.random() < 0.35 ? 'Miyaaav! 😸' : pick()), 5500);
    hearts();
  }
  el.addEventListener('click', function (e) {
    if (e.target.closest('.kopuk-hide')) {
      try { localStorage.setItem(KEY, '1'); } catch (x) {}
      el.remove();
      addShowButton();
      return;
    }
    poke();
  });
  el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); poke(); } });
  el.addEventListener('mouseenter', function () { el.classList.add('hover'); });
  el.addEventListener('mouseleave', function () { el.classList.remove('hover'); });

  setMode(mode, 6000);
  if (cfg.greet) setTimeout(function () { say(cfg.greet, 6000); }, 1800);
  if (reduce) { x = Math.max(0, W() - size() - 16); el.style.transform = 'translateX(' + x + 'px)'; }
  else requestAnimationFrame(tick);

  function addShowButton() {
    document.querySelectorAll('[data-kopuk-show]').forEach(function (b) {
      b.classList.remove('d-none');
      b.addEventListener('click', function (e) {
        e.preventDefault();
        try { localStorage.removeItem(KEY); } catch (x) {}
        location.reload();
      });
    });
  }
})();
