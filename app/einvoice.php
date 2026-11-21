<?php
/**
 * E-Dönüşüm (e-Fatura / e-Arşiv)
 * Satışlardan UBL-TR 1.2 formatında fatura XML'i üretir.
 * Gönderim: "manuel" modda XML indirilip entegratör/GİB portalına yüklenir;
 * "entegrator" modunda ayarlardaki uç noktaya (endpoint) XML POST edilir.
 * Not: Her özel entegratörün (Uyumsoft, Logo, Paraşüt, QNB vb.) API'si farklıdır;
 * entegratörünüzle anlaştıktan sonra einvoice_send() fonksiyonu onların dokümanına göre uyarlanmalıdır.
 */

function einvoice_next_no(): string
{
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', setting('einv_prefix', 'PTK')) . 'XXX', 0, 3));
    $year = date('Y');
    $last = val('SELECT invoice_no FROM invoices WHERE invoice_no LIKE ? ORDER BY id DESC LIMIT 1', [$prefix . $year . '%']);
    $seq = $last ? ((int)substr($last, 7)) + 1 : 1;
    return $prefix . $year . str_pad((string)$seq, 9, '0', STR_PAD_LEFT);
}

function einvoice_create(int $saleId, ?string $type = null): int
{
    $s = row('SELECT * FROM sales WHERE id = ?', [$saleId]);
    if (!$s || $s['status'] !== 'aktif') throw new RuntimeException('Satış bulunamadı veya iptal edilmiş.');
    if ($ex = val("SELECT id FROM invoices WHERE sale_id = ? AND status <> 'iptal'", [$saleId])) return (int)$ex;
    $c = $s['customer_id'] ? row('SELECT * FROM customers WHERE id = ?', [$s['customer_id']]) : null;
    $type = $type ?: (($c && $c['is_efatura']) ? 'e-fatura' : 'e-arsiv');
    $id = insert('invoices', [
        'sale_id' => $saleId, 'invoice_type' => $type, 'invoice_no' => einvoice_next_no(), 'uuid' => uuid4(),
        'issue_date' => now(), 'receiver_name' => $c ? ($c['company'] ?: $c['name']) : 'Nihai Tüketici',
        'receiver_vkn' => $c && $c['tc_no'] ? $c['tc_no'] : '11111111111',
        'total' => $s['total'], 'vat_total' => $s['vat_total'], 'status' => 'taslak',
    ]);
    update('invoices', ['xml' => einvoice_xml($id)], $id);
    return $id;
}

function x($s): string { return htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }

function einvoice_party(string $vkn, string $name, string $taxOffice, string $address, string $city, string $district = '', string $phone = '', string $email = ''): string
{
    $scheme = strlen($vkn) === 11 ? 'TCKN' : 'VKN';
    $partyName = '';
    $person = '';
    if ($scheme === 'TCKN') {
        $parts = preg_split('/\s+/u', trim($name));
        $family = count($parts) > 1 ? array_pop($parts) : '-';
        $person = '<cac:Person><cbc:FirstName>' . x(implode(' ', $parts)) . '</cbc:FirstName><cbc:FamilyName>' . x($family) . '</cbc:FamilyName></cac:Person>';
    } else {
        $partyName = '<cac:PartyName><cbc:Name>' . x($name) . '</cbc:Name></cac:PartyName>';
    }
    return '<cac:Party>'
        . '<cac:PartyIdentification><cbc:ID schemeID="' . $scheme . '">' . x($vkn) . '</cbc:ID></cac:PartyIdentification>'
        . $partyName
        . '<cac:PostalAddress><cbc:StreetName>' . x($address ?: '-') . '</cbc:StreetName><cbc:CitySubdivisionName>' . x($district ?: '-') . '</cbc:CitySubdivisionName><cbc:CityName>' . x($city ?: '-') . '</cbc:CityName><cac:Country><cbc:Name>Türkiye</cbc:Name></cac:Country></cac:PostalAddress>'
        . ($taxOffice ? '<cac:PartyTaxScheme><cac:TaxScheme><cbc:Name>' . x($taxOffice) . '</cbc:Name></cac:TaxScheme></cac:PartyTaxScheme>' : '')
        . (($phone || $email) ? '<cac:Contact>' . ($phone ? '<cbc:Telephone>' . x($phone) . '</cbc:Telephone>' : '') . ($email ? '<cbc:ElectronicMail>' . x($email) . '</cbc:ElectronicMail>' : '') . '</cac:Contact>' : '')
        . $person
        . '</cac:Party>';
}

function einvoice_xml(int $invoiceId): string
{
    $inv = row('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
    $s = row('SELECT * FROM sales WHERE id = ?', [$inv['sale_id']]);
    $items = rows('SELECT * FROM sale_items WHERE sale_id = ?', [$inv['sale_id']]);
    $c = $s['customer_id'] ? row('SELECT * FROM customers WHERE id = ?', [$s['customer_id']]) : null;
    $profile = $inv['invoice_type'] === 'e-fatura' ? 'TEMELFATURA' : 'EARSIVFATURA';
    $ratio = $s['subtotal'] > 0 ? $s['total'] / $s['subtotal'] : 1;

    $lines = '';
    $taxGroups = [];
    $lineExt = 0;
    foreach ($items as $n => $i) {
        $gross = $i['total'] * $ratio;
        $net = round($gross / (1 + $i['vat_rate'] / 100), 2);
        $tax = round($gross - $net, 2);
        $lineExt += $net;
        $taxGroups[$i['vat_rate']] = [($taxGroups[$i['vat_rate']][0] ?? 0) + $net, ($taxGroups[$i['vat_rate']][1] ?? 0) + $tax];
        $unitNet = $i['qty'] > 0 ? round($net / $i['qty'], 4) : 0;
        $lines .= '<cac:InvoiceLine><cbc:ID>' . ($n + 1) . '</cbc:ID><cbc:InvoicedQuantity unitCode="C62">' . x($i['qty']) . '</cbc:InvoicedQuantity>'
            . '<cbc:LineExtensionAmount currencyID="TRY">' . number_format($net, 2, '.', '') . '</cbc:LineExtensionAmount>'
            . '<cac:TaxTotal><cbc:TaxAmount currencyID="TRY">' . number_format($tax, 2, '.', '') . '</cbc:TaxAmount><cac:TaxSubtotal><cbc:TaxableAmount currencyID="TRY">' . number_format($net, 2, '.', '') . '</cbc:TaxableAmount><cbc:TaxAmount currencyID="TRY">' . number_format($tax, 2, '.', '') . '</cbc:TaxAmount><cbc:Percent>' . (int)$i['vat_rate'] . '</cbc:Percent><cac:TaxCategory><cac:TaxScheme><cbc:Name>KDV</cbc:Name><cbc:TaxTypeCode>0015</cbc:TaxTypeCode></cac:TaxScheme></cac:TaxCategory></cac:TaxSubtotal></cac:TaxTotal>'
            . '<cac:Item><cbc:Name>' . x($i['description']) . '</cbc:Name></cac:Item>'
            . '<cac:Price><cbc:PriceAmount currencyID="TRY">' . number_format($unitNet, 4, '.', '') . '</cbc:PriceAmount></cac:Price></cac:InvoiceLine>';
    }
    $taxSubs = '';
    $taxSum = 0;
    foreach ($taxGroups as $rate => [$base, $tax]) {
        $taxSum += $tax;
        $taxSubs .= '<cac:TaxSubtotal><cbc:TaxableAmount currencyID="TRY">' . number_format($base, 2, '.', '') . '</cbc:TaxableAmount><cbc:TaxAmount currencyID="TRY">' . number_format($tax, 2, '.', '') . '</cbc:TaxAmount><cbc:Percent>' . (int)$rate . '</cbc:Percent><cac:TaxCategory><cac:TaxScheme><cbc:Name>KDV</cbc:Name><cbc:TaxTypeCode>0015</cbc:TaxTypeCode></cac:TaxScheme></cac:TaxCategory></cac:TaxSubtotal>';
    }
    $t = strtotime($inv['issue_date']);
    $supplier = einvoice_party(setting('einv_vkn', '1111111111'), setting('einv_title', setting('clinic_name', 'Patikum Veteriner Kliniği')), setting('einv_tax_office'), setting('einv_address', setting('clinic_address')), setting('einv_city', 'İstanbul'), setting('einv_district'), setting('clinic_phone'), setting('clinic_email'));
    $customer = einvoice_party($inv['receiver_vkn'] ?: '11111111111', $inv['receiver_name'] ?: 'Nihai Tüketici', $c['tax_office'] ?? '', $c['address'] ?? '', $c['city'] ?? '', $c['district'] ?? '', $c['phone'] ?? '', $c['email'] ?? '');

    return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2" xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2">'
        . '<cbc:UBLVersionID>2.1</cbc:UBLVersionID><cbc:CustomizationID>TR1.2</cbc:CustomizationID>'
        . '<cbc:ProfileID>' . $profile . '</cbc:ProfileID><cbc:ID>' . x($inv['invoice_no']) . '</cbc:ID><cbc:CopyIndicator>false</cbc:CopyIndicator>'
        . '<cbc:UUID>' . x($inv['uuid']) . '</cbc:UUID><cbc:IssueDate>' . date('Y-m-d', $t) . '</cbc:IssueDate><cbc:IssueTime>' . date('H:i:s', $t) . '</cbc:IssueTime>'
        . '<cbc:InvoiceTypeCode>SATIS</cbc:InvoiceTypeCode>'
        . ($s['note'] ? '<cbc:Note>' . x($s['note']) . '</cbc:Note>' : '')
        . '<cbc:DocumentCurrencyCode>TRY</cbc:DocumentCurrencyCode><cbc:LineCountNumeric>' . count($items) . '</cbc:LineCountNumeric>'
        . '<cac:AccountingSupplierParty>' . $supplier . '</cac:AccountingSupplierParty>'
        . '<cac:AccountingCustomerParty>' . $customer . '</cac:AccountingCustomerParty>'
        . '<cac:TaxTotal><cbc:TaxAmount currencyID="TRY">' . number_format($taxSum, 2, '.', '') . '</cbc:TaxAmount>' . $taxSubs . '</cac:TaxTotal>'
        . '<cac:LegalMonetaryTotal><cbc:LineExtensionAmount currencyID="TRY">' . number_format($lineExt, 2, '.', '') . '</cbc:LineExtensionAmount>'
        . '<cbc:TaxExclusiveAmount currencyID="TRY">' . number_format($lineExt, 2, '.', '') . '</cbc:TaxExclusiveAmount>'
        . '<cbc:TaxInclusiveAmount currencyID="TRY">' . number_format($s['total'], 2, '.', '') . '</cbc:TaxInclusiveAmount>'
        . '<cbc:PayableAmount currencyID="TRY">' . number_format($s['total'], 2, '.', '') . '</cbc:PayableAmount></cac:LegalMonetaryTotal>'
        . $lines . '</Invoice>';
}

function einvoice_send(int $invoiceId): array
{
    $inv = row('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
    if (!$inv) return [false, 'Fatura bulunamadı'];
    if (setting('einv_provider', 'manuel') !== 'entegrator') {
        return [false, 'Manuel modda XML dosyasını indirip entegratör / GİB portalına yükleyin, ardından "Gönderildi" olarak işaretleyin.'];
    }
    $url = setting('einv_api_url');
    if (!$url) return [false, 'Entegratör API adresi ayarlarda tanımlı değil.'];
    $headers = ['Authorization: Basic ' . base64_encode(setting('einv_api_user') . ':' . setting('einv_api_pass'))];
    [$code, $body] = http_post_json($url, $inv['xml'], $headers, true);
    $ok = $code >= 200 && $code < 300;
    update('invoices', ['status' => $ok ? 'gonderildi' : 'hata', 'response' => mb_substr("HTTP $code\n" . $body, 0, 5000)], $invoiceId);
    return [$ok, $ok ? 'Fatura entegratöre iletildi.' : "Entegratör hatası (HTTP $code)"];
}
