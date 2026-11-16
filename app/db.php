<?php
/** Koneksi PDO MySQL singleton. */

declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $driver = strtolower(env('DB_CONNECTION', ''));
    $host   = env('DB_HOST', '');

    // Gunakan SQLite jika DB_CONNECTION=sqlite atau jika DB_HOST tidak ditentukan
    if ($driver === 'sqlite' || ($driver === '' && $host === '')) {
        $defaultDb = dirname(__DIR__) . '/database/treesync.sqlite';
        $sqlitePath = env('DB_DATABASE', $defaultDb);

        // Jika di Vercel/serverless (di mana app root read-only), gunakan /tmp agar bisa read/write
        $isServerless = isset($_ENV['VERCEL']) || isset($_SERVER['VERCEL']) || is_dir('/var/task');
        if ($isServerless && is_dir('/tmp')) {
            $tmpDb = '/tmp/treesync.sqlite';
            if (!file_exists($tmpDb) && file_exists($defaultDb)) {
                copy($defaultDb, $tmpDb);
            }
            $sqlitePath = file_exists($tmpDb) ? $tmpDb : $defaultDb;
        }

        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        return $pdo;
    }

    $port = env('DB_PORT', '3306');
    $db   = env('DB_NAME', 'treesync');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Aktifkan SSL/TLS untuk TiDB Cloud atau cloud provider lain
    $isSsl = filter_var(env('DB_SSL', 'false'), FILTER_VALIDATE_BOOLEAN)
        || ($host !== '127.0.0.1' && $host !== 'localhost');

    if ($isSsl) {
        $sslCaKey = defined('Pdo\Mysql::ATTR_SSL_CA') ? \Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA;
        $sslVerifyKey = defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') ? \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT : PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT;

        $caPaths = [
            '/etc/ssl/certs/ca-certificates.crt', // Debian / Ubuntu / Vercel
            '/etc/pki/tls/certs/ca-bundle.crt',   // Amazon Linux / CentOS
        ];
        foreach ($caPaths as $ca) {
            if (file_exists($ca)) {
                $options[$sslCaKey] = $ca;
                break;
            }
        }
        $options[$sslVerifyKey] = false;
    }

    $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), $options);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    // Kompatibilitas query lintas DBMS (SQLite vs MySQL)
    $driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        if (stripos($sql, 'INSERT IGNORE INTO') !== false) {
            $sql = preg_replace('/INSERT\s+IGNORE\s+INTO/i', 'INSERT OR IGNORE INTO', $sql);
        }
    } elseif ($driver === 'mysql') {
        if (stripos($sql, 'INSERT OR IGNORE INTO') !== false) {
            $sql = preg_replace('/INSERT\s+OR\s+IGNORE\s+INTO/i', 'INSERT IGNORE INTO', $sql);
        }
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function scalar(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function last_insert_id(): int
{
    return (int)db()->lastInsertId();
}
