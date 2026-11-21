<?php
/** Satış, stok ve tahsilat işlemleri. Fiyatlar KDV DAHİL kabul edilir. */

function stock_move(int $productId, float $qty, string $type, ?string $note = null, ?string $refType = null, ?int $refId = null, ?float $unitCost = null): void
{
    $p = row('SELECT id, is_service FROM products WHERE id = ?', [$productId]);
    if (!$p || $p['is_service']) return;
    insert('stock_movements', [
        'product_id' => $productId, 'qty' => $qty, 'type' => $type, 'note' => $note,
        'ref_type' => $refType, 'ref_id' => $refId, 'unit_cost' => $unitCost,
        'user_id' => $_SESSION['uid'] ?? null,
    ]);
    q('UPDATE products SET stock = stock + ? WHERE id = ?', [$qty, $productId]);
}

/**
 * $h: customer_id, patient_id, sale_date, discount, note, source, paid, pay_method
 * $items: [ ['product_id'=>?, 'description'=>'', 'qty'=>1, 'unit_price'=>0, 'vat_rate'=>20], ... ]
 */
function sale_create(array $h, array $items): int
{
    $items = array_values(array_filter($items, fn($i) => ($i['description'] ?? '') !== '' && (float)($i['qty'] ?? 0) > 0));
    if (!$items) throw new RuntimeException('Satışa en az bir kalem ekleyin.');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $subtotal = 0;
        $vat = 0;
        foreach ($items as &$i) {
            $i['qty'] = (float)$i['qty'];
            $i['unit_price'] = round((float)$i['unit_price'], 2);
            $i['vat_rate'] = (int)($i['vat_rate'] ?? 20);
            $i['total'] = round($i['qty'] * $i['unit_price'], 2);
            $subtotal += $i['total'];
        }
        unset($i);
        $discount = min(round((float)($h['discount'] ?? 0), 2), $subtotal);
        $total = round($subtotal - $discount, 2);
        $ratio = $subtotal > 0 ? $total / $subtotal : 0;
        foreach ($items as $i) {
            $lt = $i['total'] * $ratio;
            $vat += $lt - $lt / (1 + $i['vat_rate'] / 100);
        }
        $saleId = insert('sales', [
            'customer_id' => nul($h['customer_id'] ?? null),
            'patient_id' => nul($h['patient_id'] ?? null),
            'sale_date' => $h['sale_date'] ?? now(),
            'subtotal' => round($subtotal, 2),
            'discount' => $discount,
            'vat_total' => round($vat, 2),
            'total' => $total,
            'note' => $h['note'] ?? null,
            'source' => $h['source'] ?? 'satis',
            'status' => 'aktif',
            'user_id' => $_SESSION['uid'] ?? null,
        ]);
        foreach ($items as $i) {
            insert('sale_items', [
                'sale_id' => $saleId, 'product_id' => nul($i['product_id'] ?? null), 'description' => mb_substr($i['description'], 0, 250),
                'qty' => $i['qty'], 'unit_price' => $i['unit_price'], 'vat_rate' => $i['vat_rate'], 'total' => $i['total'],
            ]);
            if (!empty($i['product_id'])) stock_move((int)$i['product_id'], -$i['qty'], 'satis', 'Satış #' . $saleId, 'sale', $saleId);
        }
        $paid = round((float)($h['paid'] ?? 0), 2);
        if ($paid > 0) {
            insert('payments', [
                'customer_id' => nul($h['customer_id'] ?? null), 'sale_id' => $saleId, 'amount' => $paid,
                'method' => $h['pay_method'] ?? 'nakit', 'pay_date' => $h['sale_date'] ?? now(),
                'note' => 'Satış #' . $saleId . ' tahsilatı', 'user_id' => $_SESSION['uid'] ?? null,
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return $saleId;
}

function sale_cancel(int $saleId): void
{
    $s = row('SELECT * FROM sales WHERE id = ?', [$saleId]);
    if (!$s || $s['status'] !== 'aktif') return;
    $pdo = db();
    $pdo->beginTransaction();
    foreach (rows('SELECT * FROM sale_items WHERE sale_id = ? AND product_id IS NOT NULL', [$saleId]) as $i) {
        stock_move((int)$i['product_id'], (float)$i['qty'], 'iade', 'Satış #' . $saleId . ' iptali', 'sale', $saleId);
    }
    q('DELETE FROM payments WHERE sale_id = ?', [$saleId]);
    update('sales', ['status' => 'iptal'], $saleId);
    $pdo->commit();
}

function payment_create(?int $customerId, float $amount, string $method, ?string $date = null, ?string $note = null, ?int $saleId = null): int
{
    $id = insert('payments', [
        'customer_id' => $customerId, 'sale_id' => $saleId, 'amount' => round($amount, 2), 'method' => $method,
        'pay_date' => $date ?: now(), 'note' => $note, 'user_id' => $_SESSION['uid'] ?? null,
    ]);
    if ($customerId) {
        wa_auto('tahsilat_bilgi', $customerId, ['tutar' => money($amount), 'borc' => money(max(0, customer_balance($customerId)))], 'pay:' . $id);
    }
    return $id;
}

function sale_paid(int $saleId): float
{
    return (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE sale_id = ?', [$saleId]);
}
