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
        if ($uid !== null) {
            $user = one(
                'SELECT u.*, r.nama AS role_nama, r.slug AS role_slug, d.nama AS divisi_nama
                 FROM users u
                 JOIN roles r ON r.id = u.role_id
                 LEFT JOIN divisions d ON d.id = u.division_id
                 WHERE u.id = ? AND u.is_active = 1',
                [$uid]
            );
            if ($user === null) unset($_SESSION['user_id']); // akun dinonaktifkan
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
    session_destroy();
}

function is_api_request(): bool
{
    return str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/');
}
