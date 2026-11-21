<?php
/** Veritabanı katmanı (PDO) — MySQL (canlı) ve SQLite (yerel test) destekler. */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    global $CONFIG;
    $c = $CONFIG['db'];
    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if (($c['driver'] ?? 'mysql') === 'sqlite') {
        $pdo = new PDO('sqlite:' . $c['path'], null, null, $opts);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } else {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], (int)($c['port'] ?? 3306), $c['name']);
        $pdo = new PDO($dsn, $c['user'], $c['pass'], $opts);
        $pdo->exec("SET time_zone = '" . date('P') . "'");
    }
    return $pdo;
}

function db_driver(): string
{
    global $CONFIG;
    return $CONFIG['db']['driver'] ?? 'mysql';
}

function q(string $sql, array $p = []): PDOStatement
{
    $s = db()->prepare($sql);
    $s->execute(array_values($p));
    return $s;
}

function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }

function row(string $sql, array $p = []): ?array
{
    $r = q($sql, $p)->fetch();
    return $r ?: null;
}

function val(string $sql, array $p = [])
{
    $v = q($sql, $p)->fetchColumn();
    return $v === false ? null : $v;
}

function insert(string $table, array $data): int
{
    if ($table !== 'settings' && !array_key_exists('created_at', $data)) $data['created_at'] = now();
    $cols = array_keys($data);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
    q($sql, array_values($data));
    return (int)db()->lastInsertId();
}

function update(string $table, array $data, int $id): void
{
    if (!$data) return;
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
    $p = array_values($data);
    $p[] = $id;
    q("UPDATE $table SET $set WHERE id = ?", $p);
}

function delete_row(string $table, int $id): void
{
    q("DELETE FROM $table WHERE id = ?", [$id]);
}

/** Basit sayfalama: ['rows'=>..., 'page'=>.., 'pages'=>.., 'total'=>..] */
function paginate(string $sql, array $p = [], int $per = 25): array
{
    $page = max(1, (int)($_GET['sayfa'] ?? 1));
    $total = (int)val("SELECT COUNT(*) FROM ($sql) t", $p);
    $pages = max(1, (int)ceil($total / $per));
    $page = min($page, $pages);
    $off = ($page - 1) * $per;
    return ['rows' => rows("$sql LIMIT $per OFFSET $off", $p), 'page' => $page, 'pages' => $pages, 'total' => $total];
}

function settings_all(): array
{
    if (!isset($GLOBALS['__settings'])) {
        $GLOBALS['__settings'] = [];
        try {
            foreach (rows('SELECT skey, svalue FROM settings') as $r) $GLOBALS['__settings'][$r['skey']] = $r['svalue'];
        } catch (Throwable $e) {
        }
    }
    return $GLOBALS['__settings'];
}

function setting(string $key, $default = '')
{
    $all = settings_all();
    return (isset($all[$key]) && $all[$key] !== '') ? $all[$key] : $default;
}

function setting_set(string $key, $value): void
{
    if (val('SELECT COUNT(*) FROM settings WHERE skey = ?', [$key])) {
        q('UPDATE settings SET svalue = ? WHERE skey = ?', [(string)$value, $key]);
    } else {
        q('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$key, (string)$value]);
    }
    settings_all();
    $GLOBALS['__settings'][$key] = (string)$value;
}
