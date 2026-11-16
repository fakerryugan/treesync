<?php
/**
 * Controller: Pengumuman (feed, suka, komentar, konfirmasi baca, pembaca, edit).
 */

declare(strict_types=1);

const FEED_BASE_SQL = '
    SELECT a.*, u.nama AS author_nama, u.jabatan AS author_jabatan, u.division_id AS author_division_id,
           d.nama AS target_nama,
           (SELECT COUNT(*) FROM announcement_likes l  WHERE l.announcement_id = a.id) AS total_likes,
           (SELECT COUNT(*) FROM announcement_comments c WHERE c.announcement_id = a.id) AS total_comments,
           (SELECT COUNT(*) FROM announcement_reads r  WHERE r.announcement_id = a.id) AS total_read,
           EXISTS(SELECT 1 FROM announcement_likes l2 WHERE l2.announcement_id = a.id AND l2.user_id = ?) AS liked_by_me,
           EXISTS(SELECT 1 FROM announcement_reads r2 WHERE r2.announcement_id = a.id AND r2.user_id = ?) AS read_by_me
    FROM announcements a
    JOIN users u  ON u.id = a.author_id
    LEFT JOIN divisions d ON d.id = a.target_division_id
';

function can_publish_announcement(?array $user = null): bool
{
    $user ??= current_user();
    if ($user === null) return false;
    return can(PERM['ANN_PUBLISH_ALL'], $user)
        || can(PERM['ANN_PUBLISH_DIV'], $user)
        || ($user['role_slug'] ?? '') === 'administrator';
}

function can_edit_announcement(array $post, ?array $user = null): bool
{
    $user ??= current_user();
    if ($user === null) return false;
    // Administrator & izin publish_all bisa edit semua pengumuman
    if (can(PERM['ANN_PUBLISH_ALL'], $user) || ($user['role_slug'] ?? '') === 'administrator') {
        return true;
    }
    // Pembuat pengumuman bisa mengedit pengumumannya sendiri
    if ((int)$post['author_id'] === (int)$user['id']) {
        return true;
    }
    // Ketua divisi bisa mengedit pengumuman yang ditujukan ke divisinya
    if ((int)($user['is_ketua'] ?? 0) === 1 && (int)$post['target_division_id'] === (int)$user['division_id']) {
        return true;
    }
    return false;
}

function can_view_announcement_readers(array $post, ?array $user = null): bool
{
    $user ??= current_user();
    if ($user === null) return false;
    // Pembuat pengumuman berhak melihat siapa yang sudah/belum membaca
    if ((int)$post['author_id'] === (int)$user['id']) {
        return true;
    }
    // Peran dengan wewenang (admin / pimpinan / role dengan publish_all)
    if (can(PERM['ANN_PUBLISH_ALL'], $user) || ($user['role_slug'] ?? '') === 'administrator') {
        return true;
    }
    // Ketua divisi bisa melihat pembaca pengumuman di divisinya atau sub-tree komandonya
    if ((int)($user['is_ketua'] ?? 0) === 1) {
        if ($post['target_division_id'] === null) {
            return true;
        }
        if ((int)$post['target_division_id'] === (int)$user['division_id']) {
            return true;
        }
        if (in_array((int)$post['target_division_id'], visible_division_ids($user), true)) {
            return true;
        }
    }
    return false;
}

function get_announcement_targets(?array $user = null): array
{
    $user ??= current_user();
    if ($user === null) return [];

    $canAll = can(PERM['ANN_PUBLISH_ALL'], $user) || ($user['role_slug'] ?? '') === 'administrator';
    $canDiv = can(PERM['ANN_PUBLISH_DIV'], $user) || (int)($user['is_ketua'] ?? 0) === 1;

    if (!$canAll && !$canDiv) return [];

    $targets = [];
    if ($canAll) {
        $targets[0] = '🌐 Seluruh Organisasi (Semua Divisi)';
    }

    $allDivs = all('SELECT id, nama FROM divisions ORDER BY id');
    $myDivId = (int)($user['division_id'] ?? 0);
    $visible = visible_division_ids($user);

    // Divisi pengguna sendiri (jika ada)
    if ($myDivId > 0) {
        foreach ($allDivs as $d) {
            if ((int)$d['id'] === $myDivId) {
                $targets[$myDivId] = '🏢 Divisi Saya (' . $d['nama'] . ')';
                break;
            }
        }
    }

    // Pilihan divisi lainnya yang diizinkan
    foreach ($allDivs as $d) {
        $did = (int)$d['id'];
        if ($did === $myDivId) continue;
        if ($canAll || in_array($did, $visible, true)) {
            $targets[$did] = '📁 ' . $d['nama'];
        }
    }

    return $targets;
}

function feed_index(): void
{
    require_login();
    $user = current_user();
    $visible = visible_division_ids($user);

    $posts = [];
    if ($visible !== [] || can(PERM['ANN_VIEW_ALL'])) {
        $in = implode(',', array_fill(0, count($visible), '?'));
        $sql = FEED_BASE_SQL . '
            WHERE a.target_division_id IS NULL OR a.target_division_id IN (' . $in . ')
            ORDER BY a.created_at DESC LIMIT 100';
        $posts = all($sql, array_merge([$user['id'], $user['id']], $visible));
    }

    // Hitung total target dan cek hak edit/pembaca tiap pengumuman
    foreach ($posts as &$p) {
        $p['can_edit'] = can_edit_announcement($p, $user);
        $p['can_view_readers'] = can_view_announcement_readers($p, $user);

        if ($p['target_division_id'] === null) {
            $p['total_target'] = (int)scalar(
                'SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id
                 WHERE u.is_active=1 AND r.slug != "administrator"');
        } else {
            $p['total_target'] = (int)scalar(
                'SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id
                 WHERE u.is_active=1 AND r.slug != "administrator" AND u.division_id = ?',
                [$p['target_division_id']]);
        }
    }
    unset($p);

    $targets = get_announcement_targets($user);

    layout_header(['title' => 'Papan Pengumuman', 'active' => 'pengumuman']);
    view('feed', [
        'posts'   => $posts,
        'me'      => $user,
        'targets' => $targets,
        'visible' => $visible,
    ]);
}

function feed_create(): void
{
    require_login();
    $user = current_user();
    $targets = get_announcement_targets($user);
    if ($targets === []) {
        flash_set('error', 'Anda tidak memiliki wewenang untuk mempublikasikan pengumuman.');
        redirect('/pengumuman');
    }

    $judul  = trim($_POST['judul'] ?? '');
    $isi    = trim($_POST['isi'] ?? '');
    $target = (int)($_POST['target_division_id'] ?? 0);
    $imgUrl = trim($_POST['lampiran_url'] ?? '');

    if (!array_key_exists($target, $targets)) {
        flash_set('error', 'Target divisi yang dipilih tidak valid atau di luar wewenang Anda.');
        redirect('/pengumuman');
    }
    if ($judul === '' || $isi === '') {
        flash_set('error', 'Judul dan isi pengumuman wajib diisi.');
        redirect('/pengumuman');
    }

    // Lampiran: URL opsional, atau unggah berkas gambar
    $lampiran = $imgUrl !== '' ? $imgUrl : null;
    if (!empty($_FILES['lampiran_file']['tmp_name']) && is_uploaded_file($_FILES['lampiran_file']['tmp_name'])) {
        $lampiran = handle_image_upload($_FILES['lampiran_file']) ?? $lampiran;
    }

    q('INSERT INTO announcements (author_id, target_division_id, judul, isi, lampiran) VALUES (?,?,?,?,?)',
        [$user['id'], $target ?: null, $judul, $isi, $lampiran]);
    $newId = (int)db()->lastInsertId();

    // Pembuat otomatis tercatat sudah membaca pengumuman buatannya
    q('INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES (?,?)', [$newId, $user['id']]);

    audit_log('ANN_CREATE', 'Pengumuman: ' . $judul);
    flash_set('success', 'Pengumuman berhasil dipublikasikan.');
    redirect('/pengumuman');
}

function feed_update(int $id): void
{
    require_login();
    $user = current_user();
    $post = one('SELECT * FROM announcements WHERE id=?', [$id]);
    if ($post === null) {
        flash_set('error', 'Pengumuman tidak ditemukan.');
        redirect('/pengumuman');
    }

    if (!can_edit_announcement($post, $user)) {
        flash_set('error', 'Anda tidak memiliki wewenang untuk mengedit pengumuman ini.');
        redirect('/pengumuman');
    }

    $targets = get_announcement_targets($user);
    if (!isset($targets[(int)$post['target_division_id']])) {
        $targets[(int)$post['target_division_id']] = (int)$post['target_division_id'] === 0
            ? 'Seluruh Organisasi'
            : (scalar('SELECT nama FROM divisions WHERE id=?', [(int)$post['target_division_id']]) ?? 'Divisi');
    }

    $judul  = trim($_POST['judul'] ?? '');
    $isi    = trim($_POST['isi'] ?? '');
    $target = (int)($_POST['target_division_id'] ?? 0);
    $imgUrl = trim($_POST['lampiran_url'] ?? '');
    $hapusLampiran = !empty($_POST['hapus_lampiran']);

    if (!array_key_exists($target, $targets)) {
        flash_set('error', 'Target divisi yang dipilih tidak valid.');
        redirect('/pengumuman');
    }
    if ($judul === '' || $isi === '') {
        flash_set('error', 'Judul dan isi pengumuman wajib diisi.');
        redirect('/pengumuman');
    }

    $lampiran = $post['lampiran'];
    if ($hapusLampiran) {
        $lampiran = null;
    } elseif ($imgUrl !== '') {
        $lampiran = $imgUrl;
    }
    if (!empty($_FILES['lampiran_file']['tmp_name']) && is_uploaded_file($_FILES['lampiran_file']['tmp_name'])) {
        $lampiran = handle_image_upload($_FILES['lampiran_file']) ?? $lampiran;
    }

    q('UPDATE announcements SET judul=?, isi=?, target_division_id=?, lampiran=? WHERE id=?',
        [$judul, $isi, $target ?: null, $lampiran, $id]);

    audit_log('ANN_UPDATE', "Pengumuman #$id diubah oleh #" . $user['id']);
    flash_set('success', 'Pengumuman berhasil diperbarui.');
    redirect('/pengumuman');
}

function feed_delete(int $id): void
{
    require_login();
    $user = current_user();
    $post = one('SELECT * FROM announcements WHERE id=?', [$id]);
    if ($post === null) {
        flash_set('error', 'Pengumuman tidak ditemukan.');
        redirect('/pengumuman');
    }

    if (!can_edit_announcement($post, $user)) {
        flash_set('error', 'Anda tidak memiliki wewenang untuk menghapus pengumuman ini.');
        redirect('/pengumuman');
    }

    q('DELETE FROM announcements WHERE id=?', [$id]);
    audit_log('ANN_DELETE', "Pengumuman #$id dihapus oleh #" . $user['id']);
    flash_set('success', 'Pengumuman berhasil dihapus.');
    redirect('/pengumuman');
}

function handle_image_upload(array $file): ?string
{
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 3 * 1024 * 1024) return null;
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($exts[$mime])) return null;
    $dir = dirname(__DIR__, 2) . '/public/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = 'ann_' . bin2hex(random_bytes(8)) . '.' . $exts[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) return null;
    return (base_url('uploads/' . $name));
}

function feed_like(int $id): void
{
    require_login();
    assert_announcement_visible($id);
    $uid = current_user()['id'];

    if (one('SELECT 1 FROM announcement_likes WHERE announcement_id=? AND user_id=?', [$id, $uid])) {
        q('DELETE FROM announcement_likes WHERE announcement_id=? AND user_id=?', [$id, $uid]);
    } else {
        q('INSERT INTO announcement_likes (announcement_id, user_id) VALUES (?,?)', [$id, $uid]);
    }
    redirect('/pengumuman');
}

function feed_mark_read(int $id): void
{
    require_login();
    assert_announcement_visible($id);
    q('INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES (?,?)', [$id, current_user()['id']]);
    flash_set('success', 'Pengumuman ditandai sudah dibaca.');
    redirect('/pengumuman');
}

function feed_comment(int $id): void
{
    require_login();
    assert_announcement_visible($id);
    $uid = current_user()['id'];
    $isi = trim($_POST['isi'] ?? '');
    $parentId = (int)($_POST['parent_id'] ?? 0);
    if ($isi === '') redirect('/pengumuman');

    if ($parentId > 0) {
        // Maksimal 2 level bersarang: balasan menempel pada komentar utama
        $parent = one('SELECT id, parent_id FROM announcement_comments WHERE id=? AND announcement_id=?', [$parentId, $id]);
        if ($parent === null) redirect('/pengumuman');
        $parentId = (int)$parent['parent_id'] ?: (int)$parent['id'];
    }

    q('INSERT INTO announcement_comments (announcement_id, author_id, parent_id, isi) VALUES (?,?,?,?)',
        [$id, $uid, $parentId ?: null, $isi]);

    // ATURAN: Jika menulis komentar, otomatis tercatat sudah membaca pengumuman jika sebelumnya lupa klik
    q('INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES (?,?)', [$id, $uid]);

    redirect('/pengumuman');
}

/**
 * API: Detail siapa saja yang sudah baca dan belum membaca pengumuman.
 * Hanya bisa diakses oleh pembuat pengumuman atau peran yang memiliki wewenang.
 */
function feed_api_readers(int $id): void
{
    require_login();
    $user = current_user();
    $post = one(
        'SELECT a.*, d.nama AS target_nama
         FROM announcements a
         LEFT JOIN divisions d ON d.id = a.target_division_id
         WHERE a.id = ?',
        [$id]
    );

    if ($post === null) {
        json_error('Pengumuman tidak ditemukan.', 404);
    }

    if (!can_view_announcement_readers($post, $user)) {
        json_error('Anda tidak memiliki wewenang untuk melihat detail pembaca pengumuman ini.', 403);
    }

    // 1. Pengguna yang SUDAH membaca
    $reads = all(
        'SELECT u.id, u.nama, u.jabatan, u.username, d.nama AS divisi_nama, r.read_at
         FROM announcement_reads r
         JOIN users u ON u.id = r.user_id
         LEFT JOIN divisions d ON d.id = u.division_id
         WHERE r.announcement_id = ?
         ORDER BY r.read_at DESC',
        [$id]
    );

    $readList = array_map(fn($r) => [
        'id'        => (int)$r['id'],
        'nama'      => $r['nama'],
        'jabatan'   => $r['jabatan'],
        'username'  => $r['username'],
        'divisi'    => $r['divisi_nama'] ?? 'Tanpa Divisi',
        'read_at'   => time_ago($r['read_at']) . ' (' . date('d M Y, H:i', strtotime($r['read_at'])) . ')',
    ], $reads);

    // 2. Pengguna yang BELUM membaca
    // Ditentukan oleh target: jika target_division_id NULL -> seluruh user non-admin aktif
    // jika target_division_id = X -> seluruh user non-admin aktif di divisi X
    if ($post['target_division_id'] === null) {
        $unread = all(
            'SELECT u.id, u.nama, u.jabatan, u.username, d.nama AS divisi_nama
             FROM users u
             JOIN roles ro ON ro.id = u.role_id
             LEFT JOIN divisions d ON d.id = u.division_id
             WHERE u.is_active = 1
               AND ro.slug != "administrator"
               AND u.id NOT IN (SELECT user_id FROM announcement_reads WHERE announcement_id = ?)
             ORDER BY d.nama ASC, u.nama ASC',
            [$id]
        );
    } else {
        $unread = all(
            'SELECT u.id, u.nama, u.jabatan, u.username, d.nama AS divisi_nama
             FROM users u
             JOIN roles ro ON ro.id = u.role_id
             LEFT JOIN divisions d ON d.id = u.division_id
             WHERE u.is_active = 1
               AND ro.slug != "administrator"
               AND u.division_id = ?
               AND u.id NOT IN (SELECT user_id FROM announcement_reads WHERE announcement_id = ?)
             ORDER BY u.nama ASC',
            [$post['target_division_id'], $id]
        );
    }

    $unreadList = array_map(fn($u) => [
        'id'       => (int)$u['id'],
        'nama'     => $u['nama'],
        'jabatan'  => $u['jabatan'],
        'username' => $u['username'],
        'divisi'   => $u['divisi_nama'] ?? 'Tanpa Divisi',
    ], $unread);

    json_response([
        'ok'           => true,
        'id'           => (int)$post['id'],
        'judul'        => $post['judul'],
        'target'       => $post['target_nama'] ?: 'Seluruh Organisasi',
        'total_read'   => count($readList),
        'total_unread' => count($unreadList),
        'total_target' => count($readList) + count($unreadList),
        'read'         => $readList,
        'unread'       => $unreadList,
    ]);
}

/** Pastikan pengumuman masuk dalam jangkauan kewenangan user aktif. */
function assert_announcement_visible(int $id): void
{
    $user = current_user();
    $ann = one('SELECT target_division_id FROM announcements WHERE id=?', [$id]);
    if ($ann === null) {
        flash_set('error', 'Pengumuman tidak ditemukan.');
        redirect('/pengumuman');
    }
    if ($ann['target_division_id'] !== null
        && !can(PERM['ANN_VIEW_ALL'])
        && !in_array((int)$ann['target_division_id'], visible_division_ids($user), true)) {
        flash_set('error', 'Pengumuman di luar kewenangan Anda.');
        redirect('/pengumuman');
    }
}

/** Komentar bersarang maksimal 2 level. */
function feed_build_comments(int $announcementId): array
{
    $rows = all(
        'SELECT c.*, u.nama AS author_nama, u.jabatan AS author_jabatan, d.nama AS divisi_nama
         FROM announcement_comments c
         JOIN users u ON u.id = c.author_id
         LEFT JOIN divisions d ON d.id = u.division_id
         WHERE c.announcement_id = ?
         ORDER BY c.created_at ASC', [$announcementId]);
    $top = [];
    $byId = [];
    foreach ($rows as $r) {
        $r['replies'] = [];
        $byId[(int)$r['id']] = $r;
    }
    foreach ($byId as $r) {
        if ($r['parent_id'] !== null && isset($byId[(int)$r['parent_id']])) {
            $byId[(int)$r['parent_id']]['replies'][] = &$byId[(int)$r['id']];
        } else {
            $top[] = &$byId[(int)$r['id']];
        }
    }
    return $top;
}
