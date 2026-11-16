<?php
/** Autentikasi sesi login. */

declare(strict_types=1);

function current_user(): ?array
{
    static $user = null;
    static $loaded = false;
    if (!$loaded) {
        $loaded = true;
        $uid = $_SESSION['user_id'] ?? null;

        // Fallback: baca signed auth cookie jika sesi /tmp hilang karena pergantian container serverless Vercel
        if ($uid === null && !empty($_COOKIE['treesync_auth'])) {
            $decoded = base64_decode((string)$_COOKIE['treesync_auth'], true);
            if ($decoded && substr_count($decoded, ':') === 2) {
                [$cUid, $issuedAt, $sig] = explode(':', $decoded, 3);
                $secret = env('APP_SECRET', 'treesync_default_secret_key');
                $expectedSig = hash_hmac('sha256', "$cUid:$issuedAt", $secret);
                if (hash_equals($expectedSig, $sig) && (time() - (int)$issuedAt) < (86400 * 14) && (time() - (int)$issuedAt) >= 0) {
                    $uid = (int)$cUid;
                    $_SESSION['user_id'] = $uid;
                }
            }
        }

        if ($uid !== null) {
            $user = one(
                'SELECT u.*, r.nama AS role_nama, r.slug AS role_slug, d.nama AS divisi_nama
                 FROM users u
                 JOIN roles r ON r.id = u.role_id
                 LEFT JOIN divisions d ON d.id = u.division_id
                 WHERE u.id = ? AND u.is_active = 1',
                [$uid]
            );
            if ($user === null) {
                unset($_SESSION['user_id']); // akun dinonaktifkan
                logout();
            }
        }
    }
    return $user;
}

function require_login(): void
{
    if (current_user() === null) {
        if (is_api_request()) json_error('Belum login.', 401);
        redirect('/login');
    }
}

function attempt_login(string $username, string $password): bool
{
    $user = one('SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1', [$username, $username]);
    if ($user === null || !password_verify($password, $user['password_hash'])) {
        return false;
    }
    if (!(int)$user['is_active']) {
        flash_set('error', 'Akun Anda dinonaktifkan. Hubungi admin.');
        return false;
    }
    session_regenerate_id(true); // cegah session fixation
    $_SESSION['user_id'] = (int)$user['id'];
    unset($_SESSION['perm_cache']);

    // Terbitkan signed auth cookie agar login tahan saat berpindah serverless Lambda instance
    $secret = env('APP_SECRET', 'treesync_default_secret_key');
    $time = (string)time();
    $uidStr = (string)$user['id'];
    $sig = hash_hmac('sha256', "$uidStr:$time", $secret);
    $cookieVal = base64_encode("$uidStr:$time:$sig");
    setcookie('treesync_auth', $cookieVal, [
        'expires'  => time() + (86400 * 14),
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || env('APP_ENV', 'dev') === 'production',
    ]);

    audit_log('LOGIN', 'Login pengguna ' . $user['username']);
    return true;
}

function logout(): void
{
    audit_log('LOGOUT', 'Keluar sesi');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    setcookie('treesync_auth', '', [
        'expires'  => time() - 42000,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || env('APP_ENV', 'dev') === 'production',
    ]);
    session_destroy();
}

function is_api_request(): bool
{
    return str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
}
