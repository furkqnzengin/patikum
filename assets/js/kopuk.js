/* Köpük — Patikum'un ekranda gezinen tekir kedisi 🐾
 * Ekranın altında oturur, gezinir, uyur; fare yaklaşınca kaçar. Yakalanırsa (tıklanırsa) mırlar.
 * "Köpük'ü gizle" ile kapatılır (tercih tarayıcıda hatırlanır).
 */
(function () {
  'use strict';
  var KEY = 'pt_kopuk_hidden';
  var cfg = window.PT_KOPUK || {};
  try { if (localStorage.getItem(KEY) === '1') { addShowButton(); return; } } catch (e) {}

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var touch = window.matchMedia && window.matchMedia('(hover: none)').matches;
  var msgs = (cfg.messages && cfg.messages.length) ? cfg.messages : ['Merhaba! Ben Köpük 🐾'];
  var fleeMsgs = ['Miyav! 🙀', 'Yakalayamazsın 😼', 'Hop! 🐾', 'Kaçtım! 😸', 'Mrrrr… dokunma 😾'];

  var G = '#a9acb4', G2 = '#8c8f98', G3 = '#767982', W = '#f6f4f0';
  var svg =
    '<svg viewBox="0 0 140 150" aria-hidden="true">' +
    '<ellipse cx="70" cy="145" rx="40" ry="4.5" fill="rgba(20,30,35,.14)"/>' +
    '<g class="k-tail"><path d="M96 130 C 122 131, 129 104, 118 86 C 114 80, 121 73, 126 81 C 138 103, 131 140, 99 141 Z" fill="' + G2 + '"/>' +
    '<path d="M120 92 q6 2 7 7 M124 108 q5 0 6 5 M118 125 q4 3 3 8" fill="none" stroke="' + G3 + '" stroke-width="3" stroke-linecap="round"/></g>' +
    '<g class="k-body">' +
    '<path d="M38 141 C 31 113, 40 86, 70 84 C 100 86, 109 113, 102 141 Z" fill="' + G + '"/>' +
    '<path d="M41 112 q7 -3 11 2 M39 124 q7 -3 11 2 M99 112 q-7 -3 -11 2 M101 124 q-7 -3 -11 2" fill="none" stroke="' + G2 + '" stroke-width="3" stroke-linecap="round"/>' +
    '<path d="M55 141 C 51 118, 57 97, 70 95 C 83 97, 89 118, 85 141 Z" fill="' + W + '"/>' +
    '<g class="k-paw pl"><ellipse cx="57" cy="139" rx="10.5" ry="6.5" fill="' + W + '" stroke="#ddd9d2" stroke-width="1.2"/><path d="M53 137 v4 M57 136.5 v4.5 M61 137 v4" stroke="#cfcac2" stroke-width="1.1" stroke-linecap="round"/></g>' +
    '<g class="k-paw pr"><ellipse cx="83" cy="139" rx="10.5" ry="6.5" fill="' + W + '" stroke="#ddd9d2" stroke-width="1.2"/><path d="M79 137 v4 M83 136.5 v4.5 M87 137 v4" stroke="#cfcac2" stroke-width="1.1" stroke-linecap="round"/></g>' +
    '</g>' +
    '<g class="k-head">' +
    '<g class="k-ear el"><path d="M41 45 L38 15 L63 33 Z" fill="' + G + '"/><path d="M44 40 L43 22 L58 33 Z" fill="#f1b3b6"/></g>' +
    '<g class="k-ear er"><path d="M99 45 L102 15 L77 33 Z" fill="' + G + '"/><path d="M96 40 L97 22 L82 33 Z" fill="#f1b3b6"/></g>' +
    '<ellipse cx="70" cy="60" rx="35" ry="29.5" fill="' + G + '"/>' +
    '<path d="M61 34 q2.5 8 0.5 15 M70 32 v16 M79 34 q-2.5 8 -0.5 15" fill="none" stroke="' + G3 + '" stroke-width="3.2" stroke-linecap="round"/>' +
    '<path d="M36.5 57 h9 M37 63.5 h8 M103.5 57 h-9 M103 63.5 h-8" stroke="' + G2 + '" stroke-width="2.6" stroke-linecap="round"/>' +
    '<ellipse cx="70" cy="76" rx="16" ry="11.5" fill="' + W + '"/>' +
    '<g class="k-eyes-open">' +
    '<circle cx="56" cy="60" r="10.5" fill="#fff" stroke="#6a6d75" stroke-width="1.4"/><circle cx="56" cy="60.5" r="8.4" fill="#9ccbed"/><circle cx="56" cy="61" r="4.8" fill="#1c2430"/><circle cx="53" cy="57" r="2.5" fill="#fff"/><circle cx="59" cy="63.5" r="1.1" fill="#fff"/>' +
    '<circle cx="84" cy="60" r="10.5" fill="#fff" stroke="#6a6d75" stroke-width="1.4"/><circle cx="84" cy="60.5" r="8.4" fill="#9ccbed"/><circle cx="84" cy="61" r="4.8" fill="#1c2430"/><circle cx="81" cy="57" r="2.5" fill="#fff"/><circle cx="87" cy="63.5" r="1.1" fill="#fff"/>' +
    '</g>' +
    '<g class="k-eyes-closed"><path d="M48 61 q8 6 16 0" fill="none" stroke="#5f626a" stroke-width="2.2" stroke-linecap="round"/><path d="M76 61 q8 6 16 0" fill="none" stroke="#5f626a" stroke-width="2.2" stroke-linecap="round"/></g>' +
    '<path d="M66 71 h8 l-4 4.6 z" fill="#e69a92"/>' +
    '<path d="M70 75.6 q-3 4 -6.5 1.6 M70 75.6 q3 4 6.5 1.6" fill="none" stroke="#5c5f66" stroke-width="1.4" stroke-linecap="round"/>' +
    '<path d="M53 74 l-21 -3.5 M53 77.5 l-21 2.2 M87 74 l21 -3.5 M87 77.5 l21 2.2" stroke="#6c6f77" stroke-width=".9" stroke-linecap="round"/>' +
    '</g>' +
    '<path d="M47 88 Q70 101 93 88" fill="none" stroke="#e8673a" stroke-width="6" stroke-linecap="round"/>' +
    '<g class="k-bell"><circle cx="70" cy="98" r="5.2" fill="#f2c14e" stroke="#c39226" stroke-width="1.1"/><path d="M66.5 98.5 h7" stroke="#c39226" stroke-width="1"/><circle cx="70" cy="100.5" r="1" fill="#9a7018"/></g>' +
    '<g class="k-zzz"><text x="104" y="22">z</text><text x="114" y="12">z</text><text x="123" y="4">Z</text></g>' +
    '</svg>';

  var el = document.createElement('div');
  el.className = 'kopuk sitting';
  el.setAttribute('role', 'button');
  el.setAttribute('tabindex', '0');
  el.setAttribute('aria-label', 'Köpük, kliniğin kedisi');
  el.innerHTML = '<div class="kopuk-bubble" role="status"><span class="txt"></span><button type="button" class="kopuk-hide">Köpük\'ü gizle</button></div>' +
    '<div class="kopuk-cat">' + svg + '</div><span class="kopuk-name">Köpük</span>';
  document.body.appendChild(el);
  var cat = el.querySelector('.kopuk-cat');
  var bubble = el.querySelector('.kopuk-bubble');
  var bubbleTxt = bubble.querySelector('.txt');

  var W_ = function () { return document.documentElement.clientWidth; };
  var size = function () { return el.offsetWidth || 104; };
  var MARGIN = 70; // konuşma balonu ekrandan taşmasın
  var x = MARGIN + Math.random() * Math.max(0, W_() - size() - MARGIN * 2);
  var dir = Math.random() < 0.5 ? 1 : -1;
  var mode = 'sit', modeUntil = Date.now() + 4000;
  var mouse = { x: -9999, y: -9999, t: 0 };
  var bubbleTimer = null, last = 0, fleeCool = 0;

  function setMode(m, ms) {
    mode = m;
    modeUntil = Date.now() + ms;
    ['walking', 'sitting', 'sleeping', 'fleeing'].forEach(function (c) { el.classList.remove(c); });
    el.classList.add({ walk: 'walking', sit: 'sitting', sleep: 'sleeping', flee: 'fleeing' }[m]);
  }
  function say(t, ms) {
    bubbleTxt.textContent = t;
    el.classList.add('talk');
    clearTimeout(bubbleTimer);
    bubbleTimer = setTimeout(function () { el.classList.remove('talk'); }, ms || 4500);
  }
  function hearts() {
    for (var i = 0; i < 6; i++) {
      var h = document.createElement('span');
      h.className = 'kopuk-heart';
      h.textContent = ['💛', '🐾', '🧡', '💙'][i % 4];
      h.style.left = (20 + Math.random() * 60) + '%';
      h.style.animationDelay = (i * 0.08) + 's';
      el.appendChild(h);
      setTimeout(function (n) { return function () { n.remove(); }; }(h), 1700);
    }
  }
  function pick(a) { return a[Math.floor(Math.random() * a.length)]; }

  function tick(t) {
    if (!last) last = t;
    var dt = Math.min(64, t - last) / 16;
    last = t;
    var now = Date.now();
    var min = MARGIN, max = Math.max(MARGIN, W_() - size() - MARGIN - (document.querySelector('.wa-float') ? 40 : 0));

    // Fare yaklaştı mı? (yalnızca fareli cihazlarda)
    if (!touch && now - mouse.t < 1500 && now > fleeCool) {
      var r = el.getBoundingClientRect();
      var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
      var dx = cx - mouse.x, dy = cy - mouse.y, dist = Math.sqrt(dx * dx + dy * dy);
      if (dist < 130 && mode !== 'flee') {
        var wasSleeping = mode === 'sleep';
        dir = dx >= 0 ? 1 : -1;
        if ((dir > 0 && x >= max - 10) || (dir < 0 && x <= min + 10)) dir = -dir; // köşeye sıkıştıysa öbür yana atla
        setMode('flee', 700 + Math.random() * 500);
        if (wasSleeping) say('🙀 Uyandım!', 1800);
        else if (Math.random() < 0.45) say(pick(fleeMsgs), 1600);
      }
    }

    var speed = mode === 'flee' ? 6.5 : (mode === 'walk' ? 0.8 : 0);
    if (speed) {
      x += dir * speed * dt;
      if (x <= min) { x = min; dir = 1; if (mode === 'walk') setMode('sit', 2500); }
      if (x >= max) { x = max; dir = -1; if (mode === 'walk') setMode('sit', 2500); }
    }
    if (x > max) x = max;

    if (now > modeUntil) {
      if (mode === 'flee') { setMode('sit', 2500 + Math.random() * 2500); fleeCool = now + 900; }
      else {
        var p = Math.random();
        if (mode === 'sleep' || p < 0.45) { if (Math.random() < 0.35) dir = -dir; setMode('walk', 4000 + Math.random() * 6000); }
        else if (p < 0.85) { setMode('sit', 4000 + Math.random() * 5000); if (Math.random() < 0.4) say(pick(msgs), 4500); }
        else setMode('sleep', 8000 + Math.random() * 9000);
      }
    }
    el.style.transform = 'translateX(' + Math.round(x) + 'px)';
    cat.style.transform = dir < 0 ? 'scaleX(-1)' : 'none';
    requestAnimationFrame(tick);
  }

  document.addEventListener('mousemove', function (e) { mouse.x = e.clientX; mouse.y = e.clientY; mouse.t = Date.now(); }, { passive: true });

  function caught() {
    setMode('sit', 5000);
    fleeCool = Date.now() + 4000; // yakalandıktan sonra bir süre kaçmaz
    say(Math.random() < 0.5 ? 'Yakaladın beni! 😹 Mrrrr…' : pick(msgs), 5000);
    hearts();
  }
  el.addEventListener('click', function (e) {
    if (e.target.closest('.kopuk-hide')) {
      try { localStorage.setItem(KEY, '1'); } catch (x) {}
      el.remove();
      addShowButton();
      return;
    }
    caught();
  });
  el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); caught(); } });

  setMode('sit', 3500);
  if (cfg.greet || msgs[0]) setTimeout(function () { say(cfg.greet || msgs[0], 5000); }, 1500);
  el.style.transform = 'translateX(' + Math.round(x) + 'px)';
  if (!reduce) requestAnimationFrame(tick);

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
