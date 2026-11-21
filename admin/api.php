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
        $sql = 'SELECT id, name, barcode, sell_price, vat_rate, stock, is_service, unit FROM products WHERE active = 1';
        $p = [];
        if ($qs !== '') {
            $sql .= ' AND (name LIKE ? OR barcode = ? OR category LIKE ?)';
            $p = [$like, $qs, $like];
        }
        $sql .= ' ORDER BY is_service DESC, name LIMIT 40';
        json_out(array_map(fn($r) => ['id' => $r['id'], 'text' => $r['name'], 'barcode' => $r['barcode'], 'price' => (float)$r['sell_price'], 'vat' => (int)$r['vat_rate'], 'stock' => qty($r['stock']), 'is_service' => (int)$r['is_service'], 'unit' => $r['unit']], rows($sql, $p)));

    case 'breeds':
        json_out(array_column(rows('SELECT name FROM breeds WHERE species_id = ? ORDER BY name', [(int)get('species_id')]), 'name'));

    default:
        json_out(['error' => 'bilinmeyen istek'], 400);
}
