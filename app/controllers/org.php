<?php
/** Controller: pohon organisasi + login/logout. */

declare(strict_types=1);

function view(string $name, array $data = []): void
{
    extract($data, EXTR_SKIP);
    require dirname(__DIR__, 2) . '/views/' . $name . '.php';
    exit;
}

function layout_header(array $data = []): void
{
    $user = current_user();
    $flash = flash_get();
    extract($data, EXTR_SKIP);
    require dirname(__DIR__, 2) . '/views/layout/header.php';
}

function layout_footer(): void
{
    require dirname(__DIR__, 2) . '/views/layout/footer.php';
}

// ---------------- Auth ----------------

function auth_show_login(): void
{
    if (current_user() !== null) redirect('/');
    view('login', ['title' => 'Masuk — TreeSync', 'hideLayout' => true]);
}

function auth_do_login(): void
{
    $ident = trim($_POST['username'] ?? '');
    $pass  = $_POST['password'] ?? '';
    if ($ident === '' || $pass === '' || !attempt_login($ident, $pass)) {
        flash_set('error', 'Kredensial salah atau akun tidak aktif.');
        redirect('/login');
    }
    redirect('/');
}

function auth_do_logout(): void
{
    logout();
    redirect('/login');
}

// ---------------- Struktur organisasi ----------------

function org_index(): void
{
    require_login();
    $user = current_user();

    // Admin bukan bagian pohon organisasi
    $members = array_values(array_filter(org_all_users(), fn($u) => ($u['role_slug'] ?? '') !== 'administrator'));
    $roots   = build_tree($members);
    // Semua karyawan dapat melihat struktur organisasi seluruh divisi lengkap
    $trees   = $roots;

    // Semua divisi dimuat lengkap agar seluruh struktur dapat dijelajahi
    $divisions  = all('SELECT * FROM divisions ORDER BY id');
    $byDivision = [];
    foreach (org_all_users() as $u) {
        if ($u['division_id'] !== null) {
            $byDivision[(int)$u['division_id']][] = $u;
        }
    }

    // Siapa yang boleh diatur & opsi atasan baru per anggota
    $canManageGlobal = can(PERM['ORG_MANAGE']);
    $myDescIds = array_map('intval', array_column(descendant_users((int)$user['id']), 'id'));
    $isKetua   = (int)$user['is_ketua'] === 1;
    $atasanOptions = [];
    foreach (org_all_users() as $u) {
        $mid = (int)$u['id'];
        $mayManage = $canManageGlobal
            || ($isKetua && $mid !== (int)$user['id'] && in_array($mid, $myDescIds, true));
        if (!$mayManage) continue;

        if ($canManageGlobal) {
            $opts = [['id' => 0, 'nama' => '— ROOT (pimpinan tertinggi) —']];
            foreach (org_all_users() as $p) {
                if ((int)$p['id'] === $mid || ($p['role_slug'] ?? '') === 'administrator' || !(int)$p['is_active']) continue;
                $opts[] = ['id' => (int)$p['id'], 'nama' => $p['nama'] . ' (' . ($p['divisi_nama'] ?? '') . ')'];
            }
        } else {
            $opts = [['id' => (int)$user['id'], 'nama' => $user['nama'] . ' — saya (ketua)']];
            foreach (descendant_users((int)$user['id']) as $p) {
                if ((int)$p['id'] === $mid || !(int)$p['is_active']) continue;
                $opts[] = ['id' => (int)$p['id'], 'nama' => $p['nama'] . ' (' . ($p['divisi_nama'] ?? '') . ')'];
            }
        }
        $atasanOptions[$mid] = $opts;
    }

    // Siapkan daftar divisi yang bisa diedit oleh user saat ini
    $editableDivisions = [];
    foreach ($divisions as $d) {
        if (can_manage_division((int)$d['id'], $user)) {
            $editableDivisions[(int)$d['id']] = true;
        }
    }

    // Calon personil (non-admin aktif) untuk penambahan anggota / penetapan ketua
    $candidateUsers = array_values(array_filter(org_all_users(), fn($u) =>
        ($u['role_slug'] ?? '') !== 'administrator' && (int)$u['is_active'] === 1
    ));

    layout_header(['title' => 'Struktur Organisasi', 'active' => 'struktur']);
    view('org', [
        'trees' => $trees, 'me' => $user,
        'divisions' => $divisions, 'byDivision' => $byDivision,
        'atasanOptions' => $atasanOptions, 'canManageGlobal' => $canManageGlobal, 'isKetua' => $isKetua,
        'editableDivisions' => $editableDivisions,
        'candidateUsers' => $candidateUsers,
    ]);
}

/**
 * Tambah anggota baru ke divisi.
 */
function org_division_member_add(int $divisionId): void
{
    require_login();
    $user = current_user();
    if (!can_manage_division($divisionId, $user)) {
        flash_set('error', 'Anda tidak memiliki hak akses untuk menambah anggota pada divisi ini.');
        redirect('/?open_division=' . $divisionId);
    }

    $div = one('SELECT * FROM divisions WHERE id=?', [$divisionId]);
    if ($div === null) {
        flash_set('error', 'Divisi tidak ditemukan.');
        redirect('/');
    }

    $userId = (int)($_POST['user_id'] ?? 0);
    $target = one('SELECT * FROM users WHERE id=?', [$userId]);
    if ($target === null) {
        flash_set('error', 'Pengguna tidak ditemukan.');
        redirect('/?open_division=' . $divisionId);
    }

    $adminRoleId = (int)scalar('SELECT id FROM roles WHERE slug="administrator"');
    if ((int)$target['role_id'] === $adminRoleId) {
        flash_set('error', 'Administrator tidak dapat dimasukkan ke divisi.');
        redirect('/?open_division=' . $divisionId);
    }

    if ((int)$target['division_id'] === $divisionId) {
        flash_set('error', $target['nama'] . ' sudah menjadi anggota ' . $div['nama'] . '.');
        redirect('/?open_division=' . $divisionId);
    }

    // Pindahkan ke divisi ini; jika dia ketua divisi sebelumnya, lepas status ketuanya
    q('UPDATE users SET division_id=?, is_ketua=0 WHERE id=?', [$divisionId, $userId]);
    audit_log('ORG_MEMBER_ADD', "Anggota #$userId dimasukkan ke divisi #$divisionId oleh #" . $user['id']);
    flash_set('success', $target['nama'] . ' berhasil ditambahkan ke ' . $div['nama'] . '.');
    redirect('/?open_division=' . $divisionId);
}

/**
 * Hapus / keluarkan anggota dari divisi.
 */
function org_division_member_remove(int $divisionId, int $userId): void
{
    require_login();
    $user = current_user();
    if (!can_manage_division($divisionId, $user)) {
        flash_set('error', 'Anda tidak memiliki hak akses untuk mengeluarkan anggota dari divisi ini.');
        redirect('/?open_division=' . $divisionId);
    }

    $div = one('SELECT * FROM divisions WHERE id=?', [$divisionId]);
    if ($div === null) {
        flash_set('error', 'Divisi tidak ditemukan.');
        redirect('/');
    }

    $target = one('SELECT * FROM users WHERE id=?', [$userId]);
    if ($target === null || (int)$target['division_id'] !== $divisionId) {
        flash_set('error', 'Anggota tidak ditemukan pada divisi ini.');
        redirect('/?open_division=' . $divisionId);
    }

    // Set division_id = NULL dan is_ketua = 0
    q('UPDATE users SET division_id=NULL, is_ketua=0 WHERE id=?', [$userId]);
    audit_log('ORG_MEMBER_REMOVE', "Anggota #$userId dikeluarkan dari divisi #$divisionId oleh #" . $user['id']);
    flash_set('success', $target['nama'] . ' berhasil dikeluarkan dari ' . $div['nama'] . '.');
    redirect('/?open_division=' . $divisionId);
}

/**
 * Tetapkan atau ganti ketua divisi.
 */
function org_division_ketua_set(int $divisionId): void
{
    require_login();
    $user = current_user();
    if (!can_manage_division($divisionId, $user)) {
        flash_set('error', 'Anda tidak memiliki hak akses untuk mengatur ketua divisi ini.');
        redirect('/?open_division=' . $divisionId);
    }

    $div = one('SELECT * FROM divisions WHERE id=?', [$divisionId]);
    if ($div === null) {
        flash_set('error', 'Divisi tidak ditemukan.');
        redirect('/');
    }

    $userId = (int)($_POST['user_id'] ?? 0);
    $target = one('SELECT * FROM users WHERE id=?', [$userId]);
    if ($target === null) {
        flash_set('error', 'Pengguna tidak ditemukan.');
        redirect('/?open_division=' . $divisionId);
    }

    $adminRoleId = (int)scalar('SELECT id FROM roles WHERE slug="administrator"');
    if ((int)$target['role_id'] === $adminRoleId) {
        flash_set('error', 'Administrator tidak dapat menjadi ketua divisi.');
        redirect('/?open_division=' . $divisionId);
    }

    // Lepaskan status ketua lama pada divisi ini
    q('UPDATE users SET is_ketua=0 WHERE division_id=? AND is_ketua=1 AND id!=?', [$divisionId, $userId]);
    // Tetapkan pengguna terpilih sebagai ketua dan pastikan divisi_id terpasang
    q('UPDATE users SET division_id=?, is_ketua=1 WHERE id=?', [$divisionId, $userId]);
    audit_log('ORG_KETUA_SET', "Ketua divisi #$divisionId ditetapkan ke #$userId oleh #" . $user['id']);
    flash_set('success', $target['nama'] . ' berhasil ditetapkan sebagai Ketua ' . $div['nama'] . '.');
    redirect('/?open_division=' . $divisionId);
}

/**
 * Copot / lepas status ketua divisi.
 */
function org_division_ketua_unset(int $divisionId, int $userId): void
{
    require_login();
    $user = current_user();
    if (!can_manage_division($divisionId, $user)) {
        flash_set('error', 'Anda tidak memiliki hak akses untuk mencopot ketua divisi ini.');
        redirect('/?open_division=' . $divisionId);
    }

    $div = one('SELECT * FROM divisions WHERE id=?', [$divisionId]);
    if ($div === null) {
        flash_set('error', 'Divisi tidak ditemukan.');
        redirect('/');
    }

    $target = one('SELECT * FROM users WHERE id=?', [$userId]);
    if ($target === null || (int)$target['division_id'] !== $divisionId) {
        flash_set('error', 'Pengguna tidak ditemukan pada divisi ini.');
        redirect('/?open_division=' . $divisionId);
    }

    q('UPDATE users SET is_ketua=0 WHERE id=? AND division_id=?', [$userId, $divisionId]);
    audit_log('ORG_KETUA_UNSET', "Status ketua #$userId di divisi #$divisionId dicopot oleh #" . $user['id']);
    flash_set('success', 'Status ketua ' . $target['nama'] . ' berhasil dicopot (kini menjadi anggota biasa).');
    redirect('/?open_division=' . $divisionId);
}

/**
 * Ketua mengatur atasan langsung bawahannya (hanya dalam jalur komandonya).
 * Admin dengan izin org.manage boleh mengatur siapa pun.
 */
function org_anggota_atasan(int $id): void
{
    require_login();
    $user = current_user();
    $target = one('SELECT * FROM users WHERE id=?', [$id]);
    if ($target === null) { flash_set('error', 'Pengguna tidak ditemukan.'); redirect('/'); }

    $canManageGlobal = can(PERM['ORG_MANAGE']);
    $mayManage = $canManageGlobal
        || ((int)$user['is_ketua'] === 1 && $id !== (int)$user['id'] && is_descendant((int)$user['id'], $id));
    if (!$mayManage) {
        flash_set('error', 'Anda hanya bisa mengatur bawahan di bawah jalur komando Anda.');
        redirect('/');
    }
    if ((int)$target['role_id'] === (int)scalar('SELECT id FROM roles WHERE slug="administrator"')) {
        flash_set('error', 'Pengguna Administrator berada di luar pohon organisasi.');
        redirect('/');
    }

    $parentId = (int)($_POST['parent_id'] ?? 0);
    if ($parentId === $id) {
        flash_set('error', 'Pengguna tidak bisa menjadi atasan dirinya sendiri.');
        redirect('/');
    }
    $parent = $parentId > 0 ? one('SELECT id, role_id FROM users WHERE id=?', [$parentId]) : null;
    if ($parentId > 0 && $parent === null) {
        flash_set('error', 'Atasan tidak ditemukan.');
        redirect('/');
    }
    if ($parent !== null && (int)$parent['role_id'] === (int)scalar('SELECT id FROM roles WHERE slug="administrator"')) {
        flash_set('error', 'Atasan tidak valid: Administrator di luar pohon organisasi.');
        redirect('/');
    }
    if (!$canManageGlobal) {
        // Ketua: atasan baru wajib dirinya sendiri atau keturunannya
        $allowed = [(int)$user['id']];
        foreach (descendant_users((int)$user['id']) as $d) $allowed[] = (int)$d['id'];
        if (!in_array($parentId, $allowed, true)) {
            flash_set('error', 'Atasan baru harus berada di bawah jalur komando Anda.');
            redirect('/');
        }
    }
    assert_no_parent_cycle($id, $parentId);

    q('UPDATE users SET parent_id=? WHERE id=?', [$parentId ?: null, $id]);
    audit_log('ORG_ANGGOTA', "Atasan #$id -> " . ($parentId ?: 'ROOT') . " oleh #" . $user['id']);
    flash_set('success', 'Posisi ' . $target['nama'] . ' pada jalur komando diperbarui.');
    redirect('/');
}
