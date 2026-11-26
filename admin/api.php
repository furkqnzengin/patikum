<?php
require __DIR__ . '/../app/bootstrap.php';
if (!current_user()) json_out(['error' => 'auth'], 401);

$a = get('a');
$qs = (string)get('q', '');
$like = '%' . $qs . '%';

switch ($a) {
    case 'customers':
        $digits = preg_replace('/\D/', '', $qs);
        $sql = 'SELECT c.id, c.name, c.phone, ' . balance_sql() . ' AS bakiye FROM customers c WHERE c.active = 1';
        $p = [];
        if ($qs !== '') {
            $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ?' . ($digits ? ' OR REPLACE(REPLACE(c.phone,\' \',\'\'),\'-\',\'\') LIKE ?' : '') . ' OR c.id IN (SELECT customer_id FROM patients WHERE name LIKE ?))';
            $p = [$like, $like];
            if ($digits) $p[] = '%' . ltrim($digits, '0') . '%';
            $p[] = $like;
        }
        $sql .= ' ORDER BY c.name LIMIT 30';
        json_out(array_map(fn($r) => ['id' => $r['id'], 'text' => $r['name'] . ($r['phone'] ? ' · ' . phone_fmt($r['phone']) : '') . ((float)$r['bakiye'] > 0 ? ' · Borç: ' . money($r['bakiye']) : '')], rows($sql, $p)));

    case 'patients':
        $list = rows("SELECT p.id, p.name, p.breed, s.icon FROM patients p LEFT JOIN species s ON s.id = p.species_id WHERE p.customer_id = ? AND p.status = 'aktif' ORDER BY p.name", [(int)get('customer_id')]);
        json_out(array_map(fn($r) => ['id' => $r['id'], 'name' => $r['name'], 'breed' => $r['breed'], 'icon' => species_icon($r['icon'])], $list));

    case 'products':
        $sql = 'SELECT id, name, barcode, sell_price, vat_rate, stock, is_service, unit, effect_days FROM products WHERE active = 1';
        $p = [];
        if ($qs !== '') {
            $sql .= ' AND (name LIKE ? OR barcode = ? OR category LIKE ?)';
            $p = [$like, $qs, $like];
        }
        $sql .= ' ORDER BY is_service DESC, name LIMIT 40';
        json_out(array_map(fn($r) => ['id' => $r['id'], 'text' => $r['name'], 'barcode' => $r['barcode'], 'price' => (float)$r['sell_price'], 'vat' => (int)$r['vat_rate'], 'stock' => qty($r['stock']), 'is_service' => (int)$r['is_service'], 'unit' => $r['unit'], 'effect' => (int)$r['effect_days']], rows($sql, $p)));

    case 'search':
        // Üst arama kutusu: yazdıkça müşteri, hasta ve ürün önerileri (baştan eşleşenler önce)
        if (mb_strlen($qs) < 2) json_out(['customers' => [], 'patients' => [], 'products' => []]);
        $pre = $qs . '%';
        $digits = ltrim(preg_replace('/\D/', '', $qs), '0');
        $cp = [$like, $like, $like];
        $csql = 'SELECT c.id, c.name, c.phone, (SELECT COUNT(*) FROM patients p WHERE p.customer_id = c.id AND p.status = \'aktif\') AS pets FROM customers c
                 WHERE c.active = 1 AND (c.name LIKE ? OR c.phone LIKE ? OR c.tc_no LIKE ?';
        if (strlen($digits) >= 3) { $csql .= " OR REPLACE(REPLACE(c.phone,' ',''),'-','') LIKE ?"; $cp[] = '%' . $digits . '%'; }
        $csql .= ') ORDER BY CASE WHEN c.name LIKE ? THEN 0 ELSE 1 END, c.name LIMIT 7';
        $cp[] = $pre;
        $pats = rows("SELECT p.id, p.name, p.breed, s.icon, s.name AS tur, c.name AS sahip FROM patients p JOIN customers c ON c.id = p.customer_id LEFT JOIN species s ON s.id = p.species_id
                      WHERE p.status = 'aktif' AND (p.name LIKE ? OR p.chip_no LIKE ? OR c.name LIKE ?) ORDER BY CASE WHEN p.name LIKE ? THEN 0 WHEN p.name LIKE ? THEN 1 ELSE 2 END, p.name LIMIT 7", [$like, $like, $like, $pre, $like]);
        $prods = rows('SELECT id, name, stock, is_service, sell_price FROM products WHERE active = 1 AND (name LIKE ? OR barcode = ?) ORDER BY CASE WHEN name LIKE ? THEN 0 ELSE 1 END, name LIMIT 4', [$like, $qs, $pre]);
        json_out([
            'customers' => array_map(fn($r) => ['url' => url('admin/musteri.php?id=' . $r['id']), 'title' => $r['name'], 'sub' => trim(phone_fmt($r['phone']) . ' · ' . (int)$r['pets'] . ' hasta', ' ·')], rows($csql, $cp)),
            'patients' => array_map(fn($r) => ['url' => url('admin/hasta.php?id=' . $r['id']), 'title' => $r['name'], 'icon' => species_icon($r['icon']), 'sub' => trim(($r['tur'] ?: '') . ($r['breed'] ? ' · ' . $r['breed'] : '') . ' · Sahibi: ' . $r['sahip'], ' ·')], $pats),
            'products' => array_map(fn($r) => ['url' => url('admin/urunler.php?islem=duzenle&id=' . $r['id']), 'title' => $r['name'], 'sub' => money($r['sell_price']) . ($r['is_service'] ? ' · Hizmet' : ' · Stok ' . qty($r['stock']))], $prods),
        ]);

    case 'breeds':
        json_out(array_column(rows('SELECT name FROM breeds WHERE species_id = ? ORDER BY name', [(int)get('species_id')]), 'name'));

    default:
        json_out(['error' => 'bilinmeyen istek'], 400);
}
