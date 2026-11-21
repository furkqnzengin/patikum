<?php
/** Personel ve müşteri oturum yönetimi */

function current_user(): ?array
{
    static $u = false;
    if ($u !== false) return $u;
    if (empty($_SESSION['uid'])) return $u = null;
    return $u = row('SELECT * FROM users WHERE id = ? AND active = 1', [$_SESSION['uid']]);
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        redirect(url('admin/login.php'));
    }
    return $u;
}

function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        flash('danger', 'Bu sayfa için yönetici yetkisi gerekiyor.');
        redirect(url('admin/'));
    }
    return $u;
}

function login_throttled(string $key): bool
{
    $a = $_SESSION['_login_attempts'][$key] ?? ['n' => 0, 't' => 0];
    if ($a['n'] >= 5 && time() - $a['t'] < 600) return true;
    return false;
}
function login_failed(string $key): void
{
    $a = $_SESSION['_login_attempts'][$key] ?? ['n' => 0, 't' => 0];
    if (time() - $a['t'] > 600) $a['n'] = 0;
    $a['n']++;
    $a['t'] = time();
    $_SESSION['_login_attempts'][$key] = $a;
}

function staff_login(string $email, string $pass): bool
{
    if (login_throttled('staff')) return false;
    $u = row('SELECT * FROM users WHERE email = ? AND active = 1', [mb_strtolower($email)]);
    if ($u && password_verify($pass, $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$u['id'];
        unset($_SESSION['_login_attempts']['staff']);
        update('users', ['last_login' => now()], (int)$u['id']);
        return true;
    }
    login_failed('staff');
    return false;
}

function current_customer(): ?array
{
    static $c = false;
    if ($c !== false) return $c;
    if (empty($_SESSION['cid'])) return $c = null;
    return $c = row('SELECT * FROM customers WHERE id = ? AND active = 1', [$_SESSION['cid']]);
}

function require_customer(): array
{
    $c = current_customer();
    if (!$c) redirect(url('panel/login.php'));
    return $c;
}

function customer_login(string $phone, string $pass): bool
{
    if (login_throttled('cust')) return false;
    $norm = phone_norm($phone);
    if (strlen($norm) < 10) return false;
    foreach (rows('SELECT * FROM customers WHERE active = 1 AND panel_password IS NOT NULL') as $c) {
        if (phone_norm($c['phone']) === $norm && password_verify($pass, $c['panel_password'])) {
            session_regenerate_id(true);
            $_SESSION['cid'] = (int)$c['id'];
            unset($_SESSION['_login_attempts']['cust']);
            update('customers', ['last_login' => now()], (int)$c['id']);
            return true;
        }
    }
    login_failed('cust');
    return false;
}

function random_password(int $len = 6): string
{
    $chars = '23456789abcdefghjkmnpqrstuvwxyz';
    $p = '';
    for ($i = 0; $i < $len; $i++) $p .= $chars[random_int(0, strlen($chars) - 1)];
    return $p;
}
