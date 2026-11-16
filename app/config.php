<?php
/**
 * TreeSync — Konfigurasi & Bootstrap aplikasi.
 * Kredensial diambil dari file .env (dilarang hardcode).
 */

declare(strict_types=1);

// Muat .env sederhana (KEY=VALUE per baris)
$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key); $val = trim($val, " \t\"'");
        if ($key !== '' && getenv($key) === false) putenv("$key=$val");
    }
}

date_default_timezone_set(env('APP_TZ', 'Asia/Jakarta'));

// Session aman
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => (env('APP_ENV', 'dev') === 'production'),
    ]);
    session_name(env('APP_SESSION', 'treesync_sid'));
    session_start();
}

// Nama-nama izin (permission codes)
const PERM = [
    'USERS_MANAGE'        => 'users.manage',
    'DIVISIONS_MANAGE'    => 'divisions.manage',
    'ROLES_MANAGE'        => 'roles.manage',
    'ANN_VIEW_ALL'        => 'announcements.view_all',
    'ANN_PUBLISH_ALL'     => 'announcements.publish_all',
    'ANN_PUBLISH_DIV'     => 'announcements.publish_division',
    'WB_VIEW_ALL'         => 'whiteboard.view_all',
    'WB_EDIT_ALL'         => 'whiteboard.edit_all',
    'TASKS_VIEW_ALL'      => 'tasks.view_all',
    'TASKS_ASSIGN_ALL'    => 'tasks.assign_all',
    'ORG_VIEW_ALL'        => 'org.view_all',
    'ORG_MANAGE'          => 'org.manage',
];

// Katalog izin (untuk halaman kelola peran)
const PERMISSION_CATALOG = [
    'users.manage'              => ['Kelola Pengguna', 'Tambah, ubah, nonaktifkan pengguna beserta atasan & divisinya.'],
    'divisions.manage'          => ['Kelola Divisi', 'Tambah, ubah, hapus divisi organisasi.'],
    'roles.manage'              => ['Kelola Peran & Izin', 'Buat peran baru, atur izin akses per peran.'],
    'announcements.view_all'    => ['Lihat Semua Pengumuman', 'Melihat pengumuman dari seluruh divisi.'],
    'announcements.publish_all' => ['Publikasi Organisasi', 'Membuat pengumuman untuk seluruh organisasi.'],
    'announcements.publish_division' => ['Publikasi Divisi', 'Membuat pengumuman untuk divisi/sub-tree miliknya.'],
    'whiteboard.view_all'       => ['Lihat Semua Whiteboard', 'Melihat whiteboard divisi lain.'],
    'whiteboard.edit_all'       => ['Edit Semua Whiteboard', 'Mengubah sticky note di whiteboard divisi mana pun.'],
    'tasks.view_all'            => ['Lihat Semua Tugas', 'Melihat tugas seluruh organisasi.'],
    'tasks.assign_all'          => ['Delegasi Bebas', 'Mendelegasikan tugas tanpa batasan sub-tree.'],
    'org.view_all'              => ['Lihat Seluruh Pohon', 'Melihat struktur organisasi lengkap.'],
    'org.manage'                => ['Kelola Struktur Organisasi', 'Mengatur ketua, anggota divisi, dan hubungan atasan pada pohon organisasi.'],
];

function env(string $key, ?string $default = null): ?string
{
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($v === false || $v === '' || $v === null) ? $default : (string)$v;
}

// Autoload modul aplikasi
spl_autoload_register(function (string $class) {
    $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) require $file;
});

require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/rbac.php';
require __DIR__ . '/org.php';
