<?php
/** Genel yardımcı fonksiyonlar */

function e($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
function now(): string { return date('Y-m-d H:i:s'); }
function today(): string { return date('Y-m-d'); }

function base_url(): string
{
    static $b = null;
    if ($b !== null) return $b;
    global $CONFIG;
    if (!empty($CONFIG['base_url'])) return $b = rtrim($CONFIG['base_url'], '/');
    $doc = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    $root = realpath(ROOT);
    if ($doc && $root && stripos($root, $doc) === 0) {
        return $b = rtrim(str_replace('\\', '/', substr($root, strlen($doc))), '/');
    }
    return $b = '';
}

function url(string $p = ''): string { return base_url() . '/' . ltrim($p, '/'); }
function asset(string $p): string
{
    $f = ROOT . '/assets/' . ltrim($p, '/');
    $v = is_file($f) ? filemtime($f) : 1;
    return url('assets/' . ltrim($p, '/')) . '?v=' . $v;
}

function site_url(): string
{
    $s = setting('site_url');
    if ($s) return rtrim($s, '/');
    if (!empty($_SERVER['HTTP_HOST'])) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . base_url();
    }
    return '';
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function back(string $fallback = ''): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    redirect($ref ?: ($fallback ?: url('admin/')));
}

function post(string $k, $d = null)
{
    $v = $_POST[$k] ?? $d;
    return is_string($v) ? trim($v) : $v;
}
function get(string $k, $d = null)
{
    $v = $_GET[$k] ?? $d;
    return is_string($v) ? trim($v) : $v;
}
function nul($v) { return ($v === '' || $v === null) ? null : $v; }
function num($v): float
{
    if ($v === null || $v === '') return 0.0;
    $v = str_replace([' ', '₺'], '', (string)$v);
    if (strpos($v, ',') !== false) $v = str_replace(['.', ','], ['', '.'], $v);
    return (float)$v;
}

function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        http_response_code(419);
        exit('Oturum süresi doldu veya geçersiz istek. Lütfen sayfayı yenileyip tekrar deneyin.');
    }
}

/* ---------- Flash ---------- */
function flash(string $type, string $msg): void { $_SESSION['_flash'][] = [$type, $msg]; }
function flashes(): string
{
    $out = '';
    foreach ($_SESSION['_flash'] ?? [] as [$t, $m]) {
        $icon = ['success' => 'check-circle', 'danger' => 'exclamation-triangle', 'warning' => 'exclamation-circle', 'info' => 'info-circle'][$t] ?? 'info-circle';
        $out .= '<div class="alert alert-' . e($t) . ' alert-dismissible fade show" role="alert"><i class="bi bi-' . $icon . ' me-2"></i>' . $m . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['_flash']);
    return $out;
}

/* ---------- Biçimlendirme ---------- */
function money($n): string { return number_format((float)$n, 2, ',', '.') . ' ₺'; }
/** Gizlenebilir tutar (Genel Bakış'taki "bakiyeleri gizle" düğmesiyle bulanıklaşır) */
function money_h($n): string { return '<span class="money">' . money($n) . '</span>'; }
function qty($n): string
{
    $n = (float)$n;
    return floor($n) == $n ? number_format($n, 0, ',', '.') : number_format($n, 2, ',', '.');
}
function dt($d): string { return $d ? date('d.m.Y', strtotime($d)) : '-'; }
function dtt($d): string { return $d ? date('d.m.Y H:i', strtotime($d)) : '-'; }
function tm($d): string { return $d ? date('H:i', strtotime($d)) : ''; }

function tr_month(int $m): string
{
    return ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'][$m] ?? '';
}
function tr_day(int $w): string { return ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'][$w] ?? ''; }
function dt_long($d): string
{
    if (!$d) return '-';
    $t = strtotime($d);
    return date('j', $t) . ' ' . tr_month((int)date('n', $t)) . ' ' . date('Y', $t);
}

function age_text(?string $birth): string
{
    if (!$birth) return '-';
    $a = new DateTime($birth);
    $d = $a->diff(new DateTime());
    if ($d->y > 0) return $d->y . ' yaş' . ($d->m ? ' ' . $d->m . ' ay' : '');
    if ($d->m > 0) return $d->m . ' ay';
    return $d->days . ' gün';
}

function days_diff(string $date): int
{
    return (int)floor((strtotime($date) - strtotime(today())) / 86400);
}

/** Telefonu 90XXXXXXXXXX biçimine çevirir */
function phone_norm(?string $p): string
{
    $d = preg_replace('/\D+/', '', (string)$p);
    if ($d === '') return '';
    if (strlen($d) === 10 && $d[0] === '5') $d = '90' . $d;
    elseif (strlen($d) === 11 && $d[0] === '0') $d = '9' . $d;
    return $d;
}
/** Türkiye cep/sabit numarasını 11 haneli 05XXXXXXXXX biçimine getirir; yurt dışı numaralarına dokunmaz */
function phone_tr(?string $p): string
{
    $raw = trim((string)$p);
    if ($raw === '') return '';
    $d = preg_replace('/\D+/', '', $raw);
    if (str_starts_with($raw, '+') && !str_starts_with($d, '90')) return '+' . $d;
    if (str_starts_with($d, '0090')) $d = substr($d, 4);
    if (strlen($d) === 12 && str_starts_with($d, '90')) $d = substr($d, 2);
    if (strlen($d) === 10 && $d[0] !== '0') $d = '0' . $d;
    return $d;
}
function phone_valid(?string $p): bool
{
    $p = (string)$p;
    return str_starts_with($p, '+') ? strlen($p) >= 9 : (bool)preg_match('/^0\d{10}$/', $p);
}

function phone_fmt(?string $p): string
{
    $d = phone_norm($p);
    if (strlen($d) === 12 && str_starts_with($d, '90')) {
        return '0' . substr($d, 2, 3) . ' ' . substr($d, 5, 3) . ' ' . substr($d, 8, 2) . ' ' . substr($d, 10, 2);
    }
    return (string)$p;
}

function status_badge(string $s): string
{
    $map = [
        'planlandi' => ['Planlandı', 'info'], 'yapildi' => ['Yapıldı', 'success'], 'iptal' => ['İptal', 'secondary'],
        'talep' => ['Talep', 'warning'], 'onayli' => ['Onaylı', 'primary'], 'tamamlandi' => ['Tamamlandı', 'success'], 'gelmedi' => ['Gelmedi', 'danger'],
        'kuyruk' => ['Kuyrukta', 'warning'], 'gonderildi' => ['Gönderildi', 'success'], 'hata' => ['Hata', 'danger'],
        'taslak' => ['Taslak', 'secondary'], 'aktif' => ['Aktif', 'success'], 'vefat' => ['Vefat', 'dark'], 'kayip' => ['Kayıp', 'danger'],
    ];
    [$t, $c] = $map[$s] ?? [$s, 'secondary'];
    return '<span class="badge rounded-pill text-bg-' . $c . '">' . e($t) . '</span>';
}

function pay_method_label(string $m): string
{
    return ['nakit' => 'Nakit', 'kart' => 'Kredi Kartı', 'havale' => 'Havale/EFT', 'diger' => 'Diğer'][$m] ?? $m;
}

function pager(array $p): string
{
    if ($p['pages'] <= 1) return '';
    $qs = $_GET;
    $h = '<nav><ul class="pagination pagination-sm justify-content-end mb-0">';
    for ($i = 1; $i <= $p['pages']; $i++) {
        if ($p['pages'] > 12 && abs($i - $p['page']) > 3 && $i !== 1 && $i !== $p['pages']) {
            if (abs($i - $p['page']) === 4) $h .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
            continue;
        }
        $qs['sayfa'] = $i;
        $h .= '<li class="page-item' . ($i === $p['page'] ? ' active' : '') . '"><a class="page-link" href="?' . e(http_build_query($qs)) . '">' . $i . '</a></li>';
    }
    return $h . '</ul></nav>';
}

function options(array $items, $selected, string $valKey = 'id', string $labelKey = 'name', string $empty = ''): string
{
    $h = $empty !== '' ? '<option value="">' . e($empty) . '</option>' : '';
    foreach ($items as $k => $it) {
        $v = is_array($it) ? $it[$valKey] : $k;
        $l = is_array($it) ? $it[$labelKey] : $it;
        $h .= '<option value="' . e($v) . '"' . ((string)$v === (string)$selected ? ' selected' : '') . '>' . e($l) . '</option>';
    }
    return $h;
}

function json_out($data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr(ord($d[6]) & 0x0f | 0x40);
    $d[8] = chr(ord($d[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

function http_post_json(string $url, $payload, array $headers = [], bool $raw = false): array
{
    $ch = curl_init($url);
    $hdr = array_merge([$raw ? 'Content-Type: application/xml' : 'Content-Type: application/json'], $headers);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $raw ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => $hdr,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body === false ? $err : $body];
}

function species_list(): array
{
    static $l = null;
    return $l ??= rows('SELECT * FROM species ORDER BY sort, name');
}

function appointment_types(): array
{
    return array_values(array_filter(array_map('trim', explode(',', setting('appointment_types', 'Muayene,Aşı,Kontrol,Operasyon,Tıraş / Bakım,Tahlil,Diş Bakımı,Diğer')))));
}

function exam_types(): array
{
    return ['Muayene', 'Kontrol', 'Operasyon', 'Tahlil / Laboratuvar', 'Görüntüleme', 'Tedavi / Pansuman', 'Tıraş / Bakım', 'Diş', 'Kısırlaştırma', 'Diğer'];
}

/** Müşteri bakiyesi: pozitif = müşterinin borcu */
function customer_balance(int $cid): float
{
    $s = (float)val("SELECT COALESCE(SUM(total),0) FROM sales WHERE customer_id = ? AND status = 'aktif'", [$cid]);
    $p = (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE customer_id = ?', [$cid]);
    return round($s - $p, 2);
}

function balance_sql(): string
{
    return "(COALESCE((SELECT SUM(s.total) FROM sales s WHERE s.customer_id = c.id AND s.status = 'aktif'),0) - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.customer_id = c.id),0))";
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: explode(' ', trim($name));
    $i = mb_substr($parts[0] ?? '', 0, 1);
    if (count($parts) > 1) $i .= mb_substr(end($parts), 0, 1);
    return mb_strtoupper($i);
}

function species_icon(?string $icon): string { return $icon ?: '🐾'; }
