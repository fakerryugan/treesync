<?php
/**
 * TreeSync — Front controller / Router.
 * Menangani routing, CSRF, dan dispatch ke controller.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/config.php';

require_once dirname(__DIR__) . '/app/controllers/org.php';
require_once dirname(__DIR__) . '/app/controllers/feed.php';
require_once dirname(__DIR__) . '/app/controllers/whiteboard.php';
require_once dirname(__DIR__) . '/app/controllers/tasks.php';
require_once dirname(__DIR__) . '/app/controllers/admin.php';

$base = rtrim(env('APP_BASE', ''), '/');
$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($base !== '' && str_starts_with($uri, $base)) {
    $uri = substr($uri, strlen($base));
}
$uri    = '/' . trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Semua request POST wajib CSRF valid
if ($method === 'POST') {
    verify_csrf();
}

/**
 * Tabel routing: [method, regex, handler]
 * {id} ditranslasi menjadi (\d+)
 */
$routes = [
    ['GET',  '/login',                        'auth_show_login'],
    ['POST', '/login',                        'auth_do_login'],
    ['POST', '/logout',                       'auth_do_logout'],

    ['GET',  '/',                             'org_index'],
    ['GET',  '/pengumuman',                   'feed_index'],
    ['POST', '/pengumuman/create',            'feed_create'],
    ['POST', '/pengumuman/{id}/update',       'feed_update'],
    ['POST', '/pengumuman/{id}/delete',       'feed_delete'],
    ['POST', '/pengumuman/{id}/like',         'feed_like'],
    ['POST', '/pengumuman/{id}/read',         'feed_mark_read'],
    ['POST', '/pengumuman/{id}/comment',      'feed_comment'],
    ['GET',  '/api/pengumuman/{id}/readers',  'feed_api_readers'],

    ['GET',  '/whiteboard',                   'whiteboard_index'],
    ['POST', '/api/notes',                    'whiteboard_api_create'],
    ['POST', '/api/notes/{id}/move',          'whiteboard_api_move'],
    ['POST', '/api/notes/{id}/text',          'whiteboard_api_text'],
    ['POST', '/api/notes/{id}/color',         'whiteboard_api_color'],
    ['POST', '/api/notes/{id}/size',          'whiteboard_api_size'],
    ['POST', '/api/notes/{id}/delete',        'whiteboard_api_delete'],

    ['GET',  '/tugas',                        'tasks_index'],
    ['POST', '/tugas/create',                 'tasks_create'],
    ['POST', '/api/tasks/{id}/status',        'tasks_api_status'],

    ['GET',  '/admin/pengguna',               'admin_users_index'],
    ['POST', '/admin/pengguna/store',         'admin_users_store'],
    ['POST', '/admin/pengguna/{id}/update',   'admin_users_update'],
    ['POST', '/admin/pengguna/{id}/toggle',   'admin_users_toggle'],
    ['POST', '/admin/pengguna/{id}/delete',   'admin_users_delete'],

    ['GET',  '/admin/peran',                  'admin_roles_index'],
    ['POST', '/admin/peran/store',            'admin_roles_store'],
    ['POST', '/admin/peran/{id}/update',      'admin_roles_update'],
    ['POST', '/admin/peran/{id}/delete',      'admin_roles_delete'],

    ['GET',  '/admin/struktur',               'admin_struktur_index'],
    ['POST', '/admin/struktur/user/{id}/update',   'admin_struktur_user_update'],
    ['POST', '/admin/struktur/division/{id}/ketua', 'admin_struktur_division_ketua'],
    ['POST', '/admin/struktur/division/{id}/member', 'admin_struktur_division_member'],

    ['POST', '/struktur/anggota/{id}/atasan', 'org_anggota_atasan'],
    ['POST', '/struktur/division/{id}/member/add',         'org_division_member_add'],
    ['POST', '/struktur/division/{id}/member/{id}/remove', 'org_division_member_remove'],
    ['POST', '/struktur/division/{id}/ketua/set',          'org_division_ketua_set'],
    ['POST', '/struktur/division/{id}/ketua/{id}/unset',   'org_division_ketua_unset'],

    ['GET',  '/admin/divisi',                 'admin_divisions_index'],
    ['POST', '/admin/divisi/store',           'admin_divisions_store'],
    ['POST', '/admin/divisi/{id}/update',     'admin_divisions_update'],
    ['POST', '/admin/divisi/{id}/delete',     'admin_divisions_delete'],
];

foreach ($routes as [$routeMethod, $pattern, $handler]) {
    if ($routeMethod !== $method) continue;
    $regex = '#^' . str_replace('{id}', '(\d+)', $pattern) . '$#';
    if (preg_match($regex, $uri, $m)) {
        array_shift($m); // buang full match
        call_user_func_array($handler, array_map('intval', $m));
        return;
    }
}

http_response_code(404);
view('errors/404', ['title' => 'Halaman Tidak Ditemukan']);
