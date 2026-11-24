/* Patikum yönetim paneli betikleri */
(function () {
  const BASE = document.body.dataset.base || '';
  const api = (q) => fetch(BASE + '/admin/api.php?' + q, { credentials: 'same-origin' }).then(r => r.json());
  window.ptApi = api;
  const fmt = (n) => (Number(n) || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
  window.ptMoney = fmt;

  // Onay isteyen buton/formlar
  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-confirm]');
    if (el && el.tagName !== 'FORM' && !confirm(el.dataset.confirm)) { e.preventDefault(); e.stopPropagation(); }
  }, true);
  document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

  // Müşteri seçici (uzaktan arama)
  function initCustomer(sel) {
    if (sel.tomselect) return;
    new TomSelect(sel, {
      valueField: 'id', labelField: 'text', searchField: ['text'], maxOptions: 30, preload: 'focus',
      placeholder: sel.getAttribute('placeholder') || 'Müşteri ara (ad / telefon)…',
      load: (q, cb) => api('a=customers&q=' + encodeURIComponent(q)).then(cb).catch(() => cb()),
      render: { no_results: () => '<div class="no-results">Sonuç yok</div>' },
      onChange: (v) => sel.dispatchEvent(new CustomEvent('customer-change', { detail: v }))
    });
  }
  document.querySelectorAll('select.js-customer').forEach(initCustomer);

  // Müşteriye bağlı hasta listesi
  document.querySelectorAll('select.js-patient').forEach(ps => {
    const cs = document.querySelector(ps.dataset.customer);
    if (!cs) return;
    const load = (cid, keep) => {
      if (!cid) { ps.innerHTML = '<option value="">— Önce müşteri seçin —</option>'; return; }
      api('a=patients&customer_id=' + cid).then(list => {
        const cur = keep ? ps.dataset.selected : '';
        ps.innerHTML = '<option value="">— Hasta seçin —</option>' + list.map(p => `<option value="${p.id}" ${String(p.id) === String(cur) ? 'selected' : ''}>${p.icon} ${p.name}${p.breed ? ' (' + p.breed + ')' : ''}</option>`).join('');
        if (list.length === 1 && !cur) ps.value = list[0].id;
        ps.dispatchEvent(new Event('change'));
      });
    };
    cs.addEventListener('change', () => load(cs.value, false));
    if (cs.value) load(cs.value, true);
  });

  // Ürün seçici
  window.ptProductSelect = function (sel, onPick) {
    return new TomSelect(sel, {
      valueField: 'id', labelField: 'text', searchField: ['text', 'barcode'], maxOptions: 40, preload: 'focus',
      placeholder: 'Ürün / hizmet ara veya barkod okut…',
      load: (q, cb) => api('a=products&q=' + encodeURIComponent(q)).then(cb).catch(() => cb()),
      render: {
        option: (d, esc) => `<div class="d-flex justify-content-between"><span>${esc(d.text)}</span><span class="text-muted small">${d.is_service ? 'Hizmet' : 'Stok: ' + d.stock} · ${fmt(d.price)}</span></div>`
      },
      onChange: function (v) { if (v && onPick) onPick(this.options[v]); }
    });
  };

  // Mini çizgi grafik
  window.ptSpark = function (id, data, color) {
    const c = document.getElementById(id);
    if (!c || !window.Chart) return;
    color = color || '#1d7a64';
    const g = c.getContext('2d').createLinearGradient(0, 0, 0, 64);
    g.addColorStop(0, color + '40'); g.addColorStop(1, color + '00');
    new Chart(c, {
      type: 'line',
      data: { labels: data.map((_, i) => i), datasets: [{ data, borderColor: color, backgroundColor: g, fill: true, tension: .42, pointRadius: 0, borderWidth: 2 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { enabled: false } }, scales: { x: { display: false }, y: { display: false, beginAtZero: true } } }
    });
  };

  // Sekmeli paneller (dashboard)
  document.querySelectorAll('[data-tabs]').forEach(box => {
    const tabs = box.querySelectorAll('.tab[data-target]');
    tabs.forEach(t => t.addEventListener('click', () => {
      tabs.forEach(x => x.classList.remove('active'));
      t.classList.add('active');
      document.querySelectorAll(box.dataset.tabs).forEach(p => p.classList.add('d-none'));
      document.querySelector(t.dataset.target).classList.remove('d-none');
    }));
  });

  // Kenar menü: grup aç/kapa, mobil aç/kapa, daralt
  const html = document.documentElement;
  document.querySelectorAll('[data-pt-group]').forEach(b => b.addEventListener('click', () => {
    if (html.classList.contains('side-mini') && window.innerWidth >= 992) return;
    b.parentElement.classList.toggle('open');
  }));
  document.querySelectorAll('[data-pt-side]').forEach(b => b.addEventListener('click', () => {
    const a = b.dataset.ptSide;
    if (a === 'open') html.classList.add('side-open');
    else if (a === 'close') html.classList.remove('side-open');
    else if (a === 'mini') { html.classList.toggle('side-mini'); try { localStorage.setItem('pt_side_mini', html.classList.contains('side-mini') ? '1' : '0'); } catch (e) {} }
  }));

  // Bakiyeleri gizle / göster (tercih tarayıcıda hatırlanır)
  document.querySelectorAll('.js-money-toggle').forEach(b => b.addEventListener('click', () => {
    const on = html.classList.toggle('money-hidden');
    try { localStorage.setItem('pt_money_hidden', on ? '1' : '0'); } catch (e) {}
  }));

  // "/" ile aramaya odaklan
  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) {
      const i = document.querySelector('.pt-search input'); if (i) { e.preventDefault(); i.focus(); }
    }
  });

  // Telefon alanları: yalnızca rakam, 11 hane (05XXXXXXXXX)
  document.querySelectorAll('input.js-phone').forEach(inp => {
    const fix = () => {
      let v = inp.value.trim();
      if (v.startsWith('+') && !v.replace(/\D/g, '').startsWith('90')) { inp.value = '+' + v.replace(/\D/g, ''); return; }
      let d = v.replace(/\D/g, '');
      if (d.startsWith('90') && d.length === 12) d = d.slice(2);
      if (d.length === 10 && d[0] !== '0') d = '0' + d;
      inp.value = d.slice(0, 11);
    };
    inp.addEventListener('blur', fix);
    inp.addEventListener('input', () => { if (!inp.value.startsWith('+')) inp.value = inp.value.replace(/[^\d]/g, '').slice(0, 12); });
    const f = inp.form;
    if (f) f.addEventListener('submit', (e) => {
      fix();
      if (inp.value && !inp.value.startsWith('+') && inp.value.length !== 11) { e.preventDefault(); inp.classList.add('is-invalid'); inp.focus(); alert('Telefon numarası 11 haneli olmalıdır (ör. 05321234567).'); }
    });
  });

  // Tarih alanı kısayolları
  document.querySelectorAll('[data-fill-date]').forEach(b => b.addEventListener('click', () => {
    const inp = document.querySelector(b.dataset.input);
    const d = new Date(); d.setDate(d.getDate() + parseInt(b.dataset.fillDate, 10));
    inp.value = d.toISOString().slice(0, 10);
  }));
})();
