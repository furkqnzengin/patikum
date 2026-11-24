<?php
/**
 * Basit Excel (.xlsx) ve CSV okuyucu — ek kütüphane gerektirmez.
 * Her satırı sütun sırasına göre dizi olarak döndürür. Boş hücreler '' olur.
 */

function sheet_read(string $path, string $origName = ''): array
{
    $ext = strtolower(pathinfo($origName ?: $path, PATHINFO_EXTENSION));
    $head = (string)file_get_contents($path, false, null, 0, 4);
    if ($ext === 'xlsx' || str_starts_with($head, "PK\x03\x04")) return xlsx_read($path);
    if ($ext === 'xls' || str_starts_with($head, "\xD0\xCF\x11\xE0")) {
        throw new RuntimeException('Eski .xls biçimi desteklenmiyor. Dosyayı Excel\'de açıp "Farklı Kaydet → Excel Çalışma Kitabı (.xlsx)" olarak kaydedin.');
    }
    return csv_read($path);
}

function xlsx_read(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('Sunucuda PHP "zip" eklentisi kapalı. cPanel → Select PHP Version → Extensions bölümünden "zip" eklentisini açın ya da dosyayı CSV olarak yükleyin.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new RuntimeException('Excel dosyası açılamadı. Dosyanın bozuk olmadığından emin olun.');

    // Paylaşılan metinler
    $strings = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT);
        if ($sx) {
            foreach ($sx->si as $si) {
                if (isset($si->t)) {
                    $strings[] = (string)$si->t;
                } else {
                    $t = '';
                    foreach ($si->r as $r) $t .= (string)$r->t;
                    $strings[] = $t;
                }
            }
        }
    }

    // İlk çalışma sayfasının yolunu bul
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wb = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb !== false && $rels !== false) {
        $wbx = simplexml_load_string($wb, 'SimpleXMLElement', LIBXML_NONET);
        $rx = simplexml_load_string($rels, 'SimpleXMLElement', LIBXML_NONET);
        if ($wbx && $rx && isset($wbx->sheets->sheet[0])) {
            $rid = (string)$wbx->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            foreach ($rx->Relationship as $rel) {
                if ((string)$rel['Id'] === $rid) {
                    $t = ltrim((string)$rel['Target'], '/');
                    $sheetPath = str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
                }
            }
        }
    }
    $xml = $zip->getFromName($sheetPath);
    $zip->close();
    if ($xml === false) throw new RuntimeException('Excel dosyasında çalışma sayfası bulunamadı.');

    $sx = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
    if (!$sx) throw new RuntimeException('Excel sayfası okunamadı.');
    $out = [];
    foreach ($sx->sheetData->row as $r) {
        $line = [];
        $auto = 0;
        foreach ($r->c as $c) {
            $ref = (string)$c['r'];
            $col = $ref !== '' ? xlsx_col_index($ref) : $auto;
            $auto = $col + 1;
            $type = (string)$c['t'];
            if ($type === 's') {
                $v = $strings[(int)$c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $v = isset($c->is->t) ? (string)$c->is->t : '';
                if (!isset($c->is->t) && isset($c->is->r)) foreach ($c->is->r as $rr) $v .= (string)$rr->t;
            } elseif ($type === 'b') {
                $v = ((string)$c->v) === '1' ? '1' : '0';
            } else {
                $v = isset($c->v) ? (string)$c->v : '';
            }
            $line[$col] = $v;
        }
        if (!$line) { $out[] = []; continue; }
        $max = max(array_keys($line));
        $row = [];
        for ($i = 0; $i <= $max; $i++) $row[] = trim((string)($line[$i] ?? ''));
        $out[] = $row;
    }
    return $out;
}

function xlsx_col_index(string $ref): int
{
    $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
    $n = 0;
    for ($i = 0; $i < strlen($letters); $i++) $n = $n * 26 + (ord($letters[$i]) - 64);
    return max(0, $n - 1);
}

function csv_read(string $path): array
{
    $raw = (string)file_get_contents($path);
    if (str_starts_with($raw, "\xEF\xBB\xBF")) $raw = substr($raw, 3);
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1254');
    $first = strtok($raw, "\n");
    $sep = substr_count((string)$first, ';') >= substr_count((string)$first, ',') ? ';' : ',';
    if (substr_count((string)$first, "\t") > substr_count((string)$first, $sep)) $sep = "\t";
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $raw);
    rewind($fh);
    $out = [];
    while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false) $out[] = array_map(fn($v) => trim((string)$v), $r);
    fclose($fh);
    return $out;
}
