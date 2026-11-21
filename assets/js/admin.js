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
  window.ptSpark = function (id, data) {
    const c = document.getElementById(id);
    if (!c || !window.Chart) return;
    new Chart(c, {
      type: 'line',
      data: { labels: data.map((_, i) => i), datasets: [{ data, borderColor: 'rgba(255,255,255,.95)', backgroundColor: 'rgba(255,255,255,.18)', fill: true, tension: .4, pointRadius: 0, borderWidth: 2 }] },
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

  // Tarih alanı kısayolları
  document.querySelectorAll('[data-fill-date]').forEach(b => b.addEventListener('click', () => {
    const inp = document.querySelector(b.dataset.input);
    const d = new Date(); d.setDate(d.getDate() + parseInt(b.dataset.fillDate, 10));
    inp.value = d.toISOString().slice(0, 10);
  }));
})();
