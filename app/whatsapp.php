<?php
/**
 * WhatsApp mesajlaşma altyapısı
 *
 * Sürücüler (Ayarlar > WhatsApp):
 *  - manuel : Mesajlar kuyruğa düşer, personel tek tıkla wa.me bağlantısıyla gönderir (ücretsiz, API gerekmez)
 *  - cloud  : Meta WhatsApp Cloud API (resmi). Şablon adı girilmişse onaylı şablonla, yoksa düz metinle gönderir.
 *  - api    : Herhangi bir 3. parti WhatsApp API servisi (URL + token, JSON POST)
 */

function wa_template(string $code): ?array
{
    return row('SELECT * FROM wa_templates WHERE code = ?', [$code]);
}

function wa_common_vars(): array
{
    return [
        'klinik' => setting('clinic_name', 'Patikum Veteriner Kliniği'),
        'telefon' => setting('clinic_phone'),
        'adres' => setting('clinic_address'),
        'site' => site_url(),
        'panel' => site_url() ? site_url() . '/panel/' : '',
    ];
}

function wa_render(string $body, array $vars): string
{
    $vars = $vars + wa_common_vars();
    return preg_replace_callback('/\{([a-z_]+)\}/u', fn($m) => array_key_exists($m[1], $vars) ? (string)$vars[$m[1]] : $m[0], $body);
}

function wa_link(?string $phone, string $text = ''): string
{
    return 'https://wa.me/' . phone_norm($phone) . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/**
 * Mesajı kuyruğa ekler. $codeOrText bir şablon kodu ise şablon kullanılır, değilse düz metin kabul edilir.
 * $refKey verilirse aynı anahtarla daha önce oluşturulmuş mesaj varsa tekrar eklenmez (çift gönderim koruması).
 */
function wa_queue(?int $customerId, ?string $phone, string $codeOrText, array $vars = [], ?string $refKey = null, bool $force = false): ?int
{
    $tpl = preg_match('/^[a-z_]+$/', $codeOrText) ? wa_template($codeOrText) : null;
    if ($tpl && !$tpl['active'] && !$force) return null;
    if ($customerId) {
        $c = row('SELECT phone, wa_optin, name FROM customers WHERE id = ?', [$customerId]);
        if (!$c) return null;
        if (!$c['wa_optin'] && !$force) return null;
        $phone = $phone ?: $c['phone'];
        $vars += ['musteri' => $c['name']];
    }
    $phone = phone_norm($phone);
    if (strlen($phone) < 11) return null;
    if ($refKey && val('SELECT id FROM wa_messages WHERE ref_key = ?', [$refKey])) return null;

    $body = wa_render($tpl ? $tpl['body'] : $codeOrText, $vars);
    $id = insert('wa_messages', [
        'customer_id' => $customerId,
        'phone' => $phone,
        'body' => $body,
        'vars' => json_encode($vars + wa_common_vars(), JSON_UNESCAPED_UNICODE),
        'template_code' => $tpl ? $tpl['code'] : null,
        'status' => 'kuyruk',
        'ref_key' => $refKey,
    ]);
    if (setting('wa_driver', 'manuel') !== 'manuel' && setting('wa_send_instant', '1') === '1') {
        $m = row('SELECT * FROM wa_messages WHERE id = ?', [$id]);
        wa_send($m);
    }
    return $id;
}

/** Şablonun otomatik gönderimi açıksa kuyruğa ekler */
function wa_auto(string $code, ?int $customerId, array $vars = [], ?string $refKey = null): ?int
{
    $tpl = wa_template($code);
    if (!$tpl || !$tpl['active'] || !$tpl['auto_send']) return null;
    return wa_queue($customerId, null, $code, $vars, $refKey);
}

function wa_send(array $m): bool
{
    $driver = setting('wa_driver', 'manuel');
    if ($driver === 'manuel') return false;
    $ok = false;
    $err = null;
    try {
        if ($driver === 'cloud') {
            [$ok, $err] = wa_send_cloud($m);
        } elseif ($driver === 'api') {
            [$ok, $err] = wa_send_api($m);
        }
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
    update('wa_messages', [
        'status' => $ok ? 'gonderildi' : 'hata',
        'sent_at' => $ok ? now() : null,
        'error' => $ok ? null : mb_substr((string)$err, 0, 2000),
        'attempts' => (int)$m['attempts'] + 1,
    ], (int)$m['id']);
    return $ok;
}

function wa_send_cloud(array $m): array
{
    $token = setting('wa_cloud_token');
    $phoneId = setting('wa_cloud_phone_id');
    if (!$token || !$phoneId) return [false, 'Cloud API token / Phone Number ID eksik'];
    $url = 'https://graph.facebook.com/' . setting('wa_cloud_version', 'v21.0') . '/' . rawurlencode($phoneId) . '/messages';
    $tpl = $m['template_code'] ? wa_template($m['template_code']) : null;
    if ($tpl && $tpl['meta_name']) {
        $vars = json_decode((string)$m['vars'], true) ?: [];
        $params = [];
        foreach (array_filter(array_map('trim', explode(',', (string)$tpl['meta_params']))) as $k) {
            $params[] = ['type' => 'text', 'text' => (string)($vars[$k] ?? '-')];
        }
        $payload = [
            'messaging_product' => 'whatsapp', 'to' => $m['phone'], 'type' => 'template',
            'template' => ['name' => $tpl['meta_name'], 'language' => ['code' => setting('wa_cloud_lang', 'tr')],
                'components' => $params ? [['type' => 'body', 'parameters' => $params]] : []],
        ];
    } else {
        $payload = ['messaging_product' => 'whatsapp', 'to' => $m['phone'], 'type' => 'text', 'text' => ['preview_url' => true, 'body' => $m['body']]];
    }
    [$code, $body] = http_post_json($url, $payload, ['Authorization: Bearer ' . $token]);
    return ($code >= 200 && $code < 300) ? [true, null] : [false, "HTTP $code: $body"];
}

function wa_send_api(array $m): array
{
    $url = setting('wa_api_url');
    if (!$url) return [false, 'API URL tanımlı değil'];
    $payload = [
        setting('wa_api_phone_field', 'phone') => $m['phone'],
        setting('wa_api_message_field', 'message') => $m['body'],
    ];
    $extra = json_decode(setting('wa_api_extra', '{}'), true);
    if (is_array($extra)) $payload += $extra;
    $headers = [];
    if ($t = setting('wa_api_token')) $headers[] = setting('wa_api_auth_header', 'Authorization') . ': ' . (setting('wa_api_auth_prefix', 'Bearer') ? setting('wa_api_auth_prefix', 'Bearer') . ' ' : '') . $t;
    [$code, $body] = http_post_json($url, $payload, $headers);
    return ($code >= 200 && $code < 300) ? [true, null] : [false, "HTTP $code: $body"];
}

/** Kuyruktaki mesajları gönderir (otomatik sürücülerde) */
function wa_process_queue(int $limit = 40): array
{
    $sent = $fail = 0;
    if (setting('wa_driver', 'manuel') === 'manuel') return [0, 0];
    $list = rows("SELECT * FROM wa_messages WHERE status IN ('kuyruk','hata') AND attempts < 3 ORDER BY id LIMIT $limit");
    foreach ($list as $m) {
        wa_send($m) ? $sent++ : $fail++;
    }
    return [$sent, $fail];
}
