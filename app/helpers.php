<?php
/** Helper umum: escaping, flash, CSRF, redirect, dll. */

declare(strict_types=1);

/** Escape output — pencegahan XSS. Gunakan untuk SEMUA data user. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}

function base_url(string $path = ''): string
{
    $base = rtrim(env('APP_BASE', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

// ---------- Flash message ----------
function flash_set(string $type, string $msg): void
{
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}

function flash_get(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $secret = env('APP_SECRET', 'treesync_default_secret_key');
        $time   = (string)time();
        $rand   = bin2hex(random_bytes(16));
        $sig    = hash_hmac('sha256', "$time:$rand", $secret);
        $_SESSION['csrf'] = "$time:$rand:$sig";
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $sent = (string)($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if ($sent === '') {
        http_response_code(419);
        exit('Sesi tidak valid (CSRF). Silakan muat ulang halaman.');
    }

    // 1. Validasi via sesi aktif
    if (!empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent)) {
        return;
    }

    // 2. Validasi via HMAC signature (tahan pergantian container serverless Vercel)
    $parts = explode(':', $sent);
    if (count($parts) === 3) {
        [$time, $rand, $sig] = $parts;
        $secret = env('APP_SECRET', 'treesync_default_secret_key');
        $expectedSig = hash_hmac('sha256', "$time:$rand", $secret);
        // Berlaku 3 jam (10800 detik)
        if (hash_equals($expectedSig, $sig) && (time() - (int)$time) < 10800 && (time() - (int)$time) >= 0) {
            $_SESSION['csrf'] = $sent;
            return;
        }
    }

    http_response_code(419);
    exit('Sesi tidak valid (CSRF). Silakan muat ulang halaman.');
}

// ---------- Validasi & util ----------
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini = strtoupper(substr($parts[0] ?? 'U', 0, 1)) . strtoupper(substr($parts[1] ?? '', 0, 1));
    return $ini !== '' ? $ini : 'U';
}

function avatar_color(int $id): string
{
    $palette = ['bg-blue-500','bg-emerald-500','bg-purple-500','bg-rose-500','bg-amber-500','bg-cyan-600','bg-indigo-500','bg-teal-500'];
    return $palette[$id % count($palette)];
}

function time_ago(?string $datetime): string
{
    if ($datetime === null) return '';
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60)     return 'Baru saja';
    if ($diff < 3600)   return floor($diff / 60) . ' menit lalu';
    if ($diff < 86400)  return floor($diff / 3600) . ' jam lalu';
    if ($diff < 604800) return floor($diff / 86400) . ' hari lalu';
    return date('d M Y', $ts);
}

function audit_log(string $aksi, string $detail = ''): void
{
    $uid = current_user()['id'] ?? null;
    q('INSERT INTO audit_logs (user_id, aksi, detail, ip_address) VALUES (?,?,?,?)',
        [$uid, $aksi, mb_substr($detail, 0, 255), $_SERVER['REMOTE_ADDR'] ?? '']);
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function json_error(string $msg, int $status = 400): never
{
    json_response(['ok' => false, 'error' => $msg], $status);
}
