<?php
/** Controller: Panel Admin — kelola pengguna, peran & izin, divisi. */

declare(strict_types=1);

// ==================== PENGGUNA ====================

function admin_users_index(): void
{
    require_permission(PERM['USERS_MANAGE']);
    $users = all(
        'SELECT u.*, r.nama AS role_nama, d.nama AS divisi_nama, p.nama AS parent_nama
         FROM users u
         JOIN roles r ON r.id = u.role_id
         LEFT JOIN divisions d ON d.id = u.division_id
         LEFT JOIN users p ON p.id = u.parent_id
         ORDER BY u.id');
    layout_header(['title' => 'Kelola Pengguna', 'active' => 'admin-pengguna']);
    view('admin/users', [
        'users'     => $users,
        'roles'     => all('SELECT id, nama FROM roles ORDER BY id'),
        'divisions' => all('SELECT id, nama FROM divisions ORDER BY id'),
        'me'        => current_user(),
    ]);
}

function validate_user_input(bool $isCreate, array $roles, array $divisions): array
{
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $jabatan = trim($_POST['jabatan'] ?? '');
    $roleId = (int)($_POST['role_id'] ?? 0);
    $divisionId = (int)($_POST['division_id'] ?? 0);
    $parentId = (int)($_POST['parent_id'] ?? 0);
    $isKetua = isset($_POST['is_ketua']) ? 1 : 0;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $password = (string)($_POST['password'] ?? '');

    if ($nama === '' || $username === '' || $email === '' || $jabatan === '' || $roleId === 0) {
        flash_set('error', 'Field bertanda * wajib diisi.');
        redirect('/admin/pengguna');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('error', 'Format email tidak valid.');
        redirect('/admin/pengguna');
    }
    if (!in_array($roleId, array_map(fn($r) => (int)$r['id'], $roles), true)) {
        flash_set('error', 'Peran tidak valid.');
        redirect('/admin/pengguna');
    }
    $roleAdmin = scalar('SELECT COUNT(*) FROM roles WHERE id=? AND slug="administrator"', [$roleId]);
    $div = null; // Administrator di luar pohon organisasi
    if (!$roleAdmin) {
        $div = $divisionId > 0 && in_array($divisionId, array_map(fn($d) => (int)$d['id'], $divisions), true) ? $divisionId : null;
        if ($div === null) {
            flash_set('error', 'Pengguna non-admin wajib punya divisi.');
            redirect('/admin/pengguna');
        }
    }

    // Validasi parent: wajib ada dan bukan admin
    if ($parentId > 0) {
        $parent = one('SELECT id, role_id FROM users WHERE id=?', [$parentId]);
        $adminRoleId = (int)scalar('SELECT id FROM roles WHERE slug="administrator"');
        if ($parent === null || (int)$parent['role_id'] === $adminRoleId) {
            flash_set('error', 'Atasan tidak valid.');
            redirect('/admin/pengguna');
        }
    }
    return compact('nama', 'username', 'email', 'jabatan', 'roleId', 'div', 'parentId', 'isKetua', 'isActive', 'password');
}

/** Cegah siklus parent_id (A -> B -> A). */
function assert_no_parent_cycle(int $userId, int $newParentId): void
{
    if ($userId <= 0 || $newParentId <= 0) return;
    $seen = [$userId => true];
    $cur = $newParentId;
    $guard = 0;
    while ($cur > 0 && $guard++ < 100) {
        if (isset($seen[$cur])) {
            flash_set('error', 'Atasan tidak valid: terdeteksi referensi melingkar pada pohon organisasi.');
            redirect('/admin/pengguna');
        }
        $seen[$cur] = true;
        $cur = (int)scalar('SELECT parent_id FROM users WHERE id=?', [$cur] );
    }
}

function admin_users_store(): void
{
    require_permission(PERM['USERS_MANAGE']);
    $roles = all('SELECT id FROM roles');
    $divisions = all('SELECT id FROM divisions');
    $v = validate_user_input(true, $roles, $divisions);

    if (scalar('SELECT COUNT(*) FROM users WHERE username=? OR email=?', [$v['username'], $v['email']])) {
        flash_set('error', 'Username atau email sudah terpakai.');
        redirect('/admin/pengguna');
    }
    if (strlen($v['password']) < 8) {
        flash_set('error', 'Password minimal 8 karakter.');
        redirect('/admin/pengguna');
    }
    assert_no_parent_cycle(0, $v['parentId']);
    if ($v['parentId'] > 0 && scalar('SELECT COUNT(*) FROM users WHERE id=?', [$v['parentId']]) == 0) {
        flash_set('error', 'Atasan tidak ditemukan.');
        redirect('/admin/pengguna');
    }

    q('INSERT INTO users (username, email, password_hash, nama, jabatan, role_id, division_id, parent_id, is_ketua, is_active)
       VALUES (?,?,?,?,?,?,?,?,?,?)',
       [$v['username'], $v['email'], password_hash($v['password'], PASSWORD_DEFAULT), $v['nama'],
        $v['jabatan'], $v['roleId'], $v['div'], $v['parentId'] ?: null, $v['isKetua'], $v['isActive']]);
    audit_log('USER_CREATE', 'Pengguna baru: ' . $v['username']);
    flash_set('success', 'Pengguna ' . $v['nama'] . ' berhasil ditambahkan.');
    redirect('/admin/pengguna');
}

function admin_users_update(int $id): void
{
    require_permission(PERM['USERS_MANAGE']);
    $target = one('SELECT * FROM users WHERE id=?', [$id]);
    if ($target === null) { flash_set('error', 'Pengguna tidak ditemukan.'); redirect('/admin/pengguna'); }

    $roles = all('SELECT id FROM roles');
    $divisions = all('SELECT id FROM divisions');
    $v = validate_user_input(false, $roles, $divisions);
    assert_no_parent_cycle($id, $v['parentId']);
    if ($v['parentId'] === $id) {
        flash_set('error', 'Pengguna tidak boleh menjadi atasan dirinya sendiri.');
        redirect('/admin/pengguna');
    }

    $params = [$v['nama'], $v['jabatan'], $v['roleId'], $v['div'], $v['parentId'] ?: null, $v['isKetua'], $v['isActive']];
    $sql = 'UPDATE users SET nama=?, jabatan=?, role_id=?, division_id=?, parent_id=?, is_ketua=?, is_active=?';

    // Username/email hanya diubah jika benar-benar diisi dan unik
    if ($v['username'] !== '' && $v['username'] !== $target['username']) {
        if (scalar('SELECT COUNT(*) FROM users WHERE username=? AND id!=?', [$v['username'], $id])) {
            flash_set('error', 'Username sudah terpakai.'); redirect('/admin/pengguna');
        }
        $sql .= ', username=?'; $params[] = $v['username'];
    }
    if ($v['email'] !== '' && $v['email'] !== $target['email']) {
        if (scalar('SELECT COUNT(*) FROM users WHERE email=? AND id!=?', [$v['email'], $id])) {
            flash_set('error', 'Email sudah terpakai.'); redirect('/admin/pengguna');
        }
        $sql .= ', email=?'; $params[] = $v['email'];
    }
    if ($v['password'] !== '') {
        if (strlen($v['password']) < 8) {
            flash_set('error', 'Password minimal 8 karakter.'); redirect('/admin/pengguna');
        }
        $sql .= ', password_hash=?'; $params[] = password_hash($v['password'], PASSWORD_DEFAULT);
    }
    $sql .= ' WHERE id=?';
    $params[] = $id;
    q($sql, $params);

    // Jika akun yang sedang login dinonaktifkan, paksa logout
    if (!$v['isActive'] && (int)current_user()['id'] === $id) {
        logout();
        redirect('/login');
    }
    audit_log('USER_UPDATE', "Ubah pengguna #$id");
    flash_set('success', 'Data pengguna diperbarui.');
    redirect('/admin/pengguna');
}

function admin_users_toggle(int $id): void
{
    require_permission(PERM['USERS_MANAGE']);
    if ($id === (int)current_user()['id']) {
        flash_set('error', 'Tidak bisa menonaktifkan akun sendiri.');
        redirect('/admin/pengguna');
    }
    q('UPDATE users SET is_active = NOT is_active WHERE id=?', [$id]);
    audit_log('USER_TOGGLE', "Toggle status pengguna #$id");
    flash_set('success', 'Status pengguna diubah.');
    redirect('/admin/pengguna');
}

function admin_users_delete(int $id): void
{
    require_permission(PERM['USERS_MANAGE']);
    if ($id === (int)current_user()['id']) {
        flash_set('error', 'Tidak bisa menghapus akun sendiri.');
        redirect('/admin/pengguna');
    }
    if (scalar('SELECT COUNT(*) FROM users WHERE parent_id=?', [$id]) > 0) {
        flash_set('error', 'Pengguna masih memiliki bawahan. Pindahkan bawahan ke atasan lain terlebih dahulu.');
        redirect('/admin/pengguna');
    }
    q('DELETE FROM users WHERE id=?', [$id]);
    audit_log('USER_DELETE', "Hapus pengguna #$id");
    flash_set('success', 'Pengguna dihapus.');
    redirect('/admin/pengguna');
}

// ==================== PERAN & IZIN ====================

function admin_roles_index(): void
{
    require_permission(PERM['ROLES_MANAGE']);
    $roles = all('SELECT r.*,
                     (SELECT COUNT(*) FROM users u WHERE u.role_id = r.id) AS user_count,
                     (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS perm_count
                  FROM roles r ORDER BY r.id');
    $matrix = [];
    foreach (all('SELECT role_id, permission_id FROM role_permissions') as $rp) {
        $matrix[(int)$rp['role_id']][(int)$rp['permission_id']] = true;
    }
    layout_header(['title' => 'Kelola Peran & Izin', 'active' => 'admin-peran']);
    view('admin/roles', ['roles' => $roles, 'matrix' => $matrix, 'me' => current_user()]);
}

function read_role_input(bool $isCreate): array
{
    $nama = trim($_POST['nama'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $perms = array_values(array_unique(array_map('intval', $_POST['permissions'] ?? [])));

    if ($nama === '') { flash_set('error', 'Nama peran wajib diisi.'); redirect('/admin/peran'); }
    $valid = array_map('intval', array_column(all('SELECT id FROM permissions'), 'id'));
    $perms = array_values(array_intersect($perms, $valid));
    $slug = $isCreate
        ? strtolower(preg_replace('/[^a-z0-9]+/i', '-', $nama) ?? '')
        : null;
    return [$nama, $deskripsi, $perms, $slug];
}

function admin_roles_store(): void
{
    require_permission(PERM['ROLES_MANAGE']);
    [$nama, $deskripsi, $perms, $slug] = read_role_input(true);
    if ($slug === '' || scalar('SELECT COUNT(*) FROM roles WHERE slug=? OR nama=?', [$slug, $nama])) {
        flash_set('error', 'Nama peran sudah dipakai.');
        redirect('/admin/peran');
    }
    q('INSERT INTO roles (nama, slug, deskripsi) VALUES (?,?,?)', [$nama, $slug, $deskripsi]);
    $roleId = (int)db()->lastInsertId();
    save_role_permissions($roleId, $perms);
    audit_log('ROLE_CREATE', "Peran baru: $nama");
    flash_set('success', 'Peran ' . $nama . ' berhasil dibuat.');
    redirect('/admin/peran');
}

function admin_roles_update(int $id): void
{
    require_permission(PERM['ROLES_MANAGE']);
    $role = one('SELECT * FROM roles WHERE id=?', [$id]);
    if ($role === null) { flash_set('error', 'Peran tidak ditemukan.'); redirect('/admin/peran'); }
    [$nama, $deskripsi, $perms, ] = read_role_input(false);
    q('UPDATE roles SET nama=?, deskripsi=? WHERE id=?', [$nama, $deskripsi, $id]);
    save_role_permissions($id, $perms);
    audit_log('ROLE_UPDATE', "Ubah peran #$id ($nama) dengan " . count($perms) . ' izin');
    flash_set('success', 'Peran diperbarui.');
    redirect('/admin/peran');
}

function save_role_permissions(int $roleId, array $permIds): void
{
    q('DELETE FROM role_permissions WHERE role_id=?', [$roleId]);
    $stmt = db()->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (?,?)');
    foreach ($permIds as $pid) $stmt->execute([$roleId, $pid]);
}

function admin_roles_delete(int $id): void
{
    require_permission(PERM['ROLES_MANAGE']);
    $role = one('SELECT * FROM roles WHERE id=?', [$id]);
    if ($role === null) { flash_set('error', 'Peran tidak ditemukan.'); redirect('/admin/peran'); }
    if ((int)$role['is_system']) {
        flash_set('error', 'Peran bawaan sistem tidak dapat dihapus.');
        redirect('/admin/peran');
    }
    if (scalar('SELECT COUNT(*) FROM users WHERE role_id=?', [$id]) > 0) {
        flash_set('error', 'Peran masih dipakai oleh pengguna. Alihkan penggunanya terlebih dahulu.');
        redirect('/admin/peran');
    }
    q('DELETE FROM roles WHERE id=?', [$id]);
    audit_log('ROLE_DELETE', "Hapus peran #$id");
    flash_set('success', 'Peran dihapus.');
    redirect('/admin/peran');
}

// ==================== STRUKTUR ORGANISASI ====================

function admin_struktur_index(): void
{
    require_permission(PERM['ORG_MANAGE']);
    $adminRoleId = (int)scalar('SELECT id FROM roles WHERE slug="administrator"');

    $users = all(
        'SELECT u.*, r.nama AS role_nama, d.nama AS divisi_nama, p.nama AS parent_nama
         FROM users u
         JOIN roles r ON r.id = u.role_id
         LEFT JOIN divisions d ON d.id = u.division_id
         LEFT JOIN users p ON p.id = u.parent_id
         ORDER BY u.id');
    $divisions = all('SELECT * FROM divisions ORDER BY id');

    // Anggota per divisi + ketua per divisi
    $membersByDiv = [];
    $ketuaByDiv = [];
    foreach ($users as $u) {
        if ($u['division_id'] === null) continue;
        $membersByDiv[(int)$u['division_id']][] = $u;
        if ((int)$u['is_ketua']) $ketuaByDiv[(int)$u['division_id']][] = $u;
    }

    // Pratinjau pohon (dikelola, jadi tampil penuh)
    $members = array_values(array_filter($users, fn($u) => (int)$u['role_id'] !== $adminRoleId));
    $trees = build_tree($members);

    layout_header(['title' => 'Pengaturan Struktur Organisasi', 'active' => 'admin-struktur']);
    view('admin/struktur', [
        'users' => $users, 'divisions' => $divisions,
        'membersByDiv' => $membersByDiv, 'ketuaByDiv' => $ketuaByDiv,
        'trees' => $trees, 'me' => current_user(), 'adminRoleId' => $adminRoleId,
    ]);
}

function assert_struktur_target(array $target): void
{
    if ((int)$target['role_id'] === (int)scalar('SELECT id FROM roles WHERE slug="administrator"')) {
        flash_set('error', 'Pengguna Administrator berada di luar pohon organisasi.');
        redirect('/admin/struktur');
    }
}

/**
 * Edit satu baris pengguna pada pengaturan struktur:
 * divisi keanggotaan, status ketua, dan atasan langsung.
 */
function admin_struktur_user_update(int $id): void
{
    require_permission(PERM['ORG_MANAGE']);
    $target = one('SELECT * FROM users WHERE id=?', [$id]);
    if ($target === null) { flash_set('error', 'Pengguna tidak ditemukan.'); redirect('/admin/struktur'); }
    assert_struktur_target($target);

    $divisionId = (int)($_POST['division_id'] ?? 0);
    $isKetua    = isset($_POST['is_ketua']) ? 1 : 0;
    $parentId   = (int)($_POST['parent_id'] ?? 0);

    if ($divisionId <= 0 || scalar('SELECT COUNT(*) FROM divisions WHERE id=?', [$divisionId]) == 0) {
        flash_set('error', 'Divisi tidak valid.');
        redirect('/admin/struktur');
    }
    if ($parentId === $id) {
        flash_set('error', 'Pengguna tidak bisa menjadi atasan dirinya sendiri.');
        redirect('/admin/struktur');
    }
    if ($parentId > 0) {
        $parent = one('SELECT id, role_id FROM users WHERE id=?', [$parentId]);
        if ($parent === null) {
            flash_set('error', 'Atasan tidak ditemukan.');
            redirect('/admin/struktur');
        }
        assert_struktur_target($parent);
        assert_no_parent_cycle($id, $parentId);
    }

    // Pindah divisi: status ketua lama dilepas bila tidak dicentang
    $movedDivision = (int)$target['division_id'] !== $divisionId;
    if ($movedDivision && $isKetua === 1) {
        flash_set('error', 'Status ketua hanya bisa diatur lewat "Jadikan Ketua" pada kartu divisi tujuan.');
        redirect('/admin/struktur');
    }

    q('UPDATE users SET division_id=?, is_ketua=?, parent_id=? WHERE id=?',
        [$divisionId, $isKetua, $parentId ?: null, $id]);
    audit_log('ORG_UPDATE', "Struktur: #$id divisi=$divisionId ketua=$isKetua atasan=$parentId");
    flash_set('success', 'Posisi ' . $target['nama'] . ' pada struktur diperbarui.');
    redirect('/admin/struktur');
}

/** Tetapkan ketua sebuah divisi: otomatis pindahkan ke divisi itu & lepaskan ketua sebelumnya. */
function admin_struktur_division_ketua(int $divisionId): void
{
    require_permission(PERM['ORG_MANAGE']);
    $div = one('SELECT * FROM divisions WHERE id=?', [$divisionId]);
    if ($div === null) { flash_set('error', 'Divisi tidak ditemukan.'); redirect('/admin/struktur'); }

    $userId = (int)($_POST['user_id'] ?? 0);
    $user = one('SELECT * FROM users WHERE id=?', [$userId]);
    if ($user === null) { flash_set('error', 'Pengguna tidak ditemukan.'); redirect('/admin/struktur'); }
    assert_struktur_target($user);

    // Lepaskan ketua lama pada divisi ini
    q('UPDATE users SET is_ketua=0 WHERE division_id=? AND is_ketua=1 AND id != ?', [$divisionId, $userId]);
    // Jadikan pengguna terpilih anggota divisi + ketua
    q('UPDATE users SET division_id=?, is_ketua=1 WHERE id=?', [$divisionId, $userId]);
    audit_log('ORG_KETUA', "Ketua divisi #$divisionId -> #$userId");
    flash_set('success', $user['nama'] . ' kini ketua ' . $div['nama'] . '.');
    redirect('/admin/struktur');
}

/** Tambahkan anggota baru ke sebuah divisi. */
function admin_struktur_division_member(int $divisionId): void
{
    require_permission(PERM['ORG_MANAGE']);
    $div = one('SELECT * FROM divisions WHERE id=?', [$divisionId]);
    if ($div === null) { flash_set('error', 'Divisi tidak ditemukan.'); redirect('/admin/struktur'); }

    $userId = (int)($_POST['user_id'] ?? 0);
    $user = one('SELECT * FROM users WHERE id=?', [$userId]);
    if ($user === null) { flash_set('error', 'Pengguna tidak ditemukan.'); redirect('/admin/struktur'); }
    assert_struktur_target($user);
    if ((int)$user['division_id'] === $divisionId) {
        flash_set('error', $user['nama'] . ' sudah menjadi anggota divisi tersebut.');
        redirect('/admin/struktur');
    }

    // Pindah divisi; jika dia ketua divisi lama, status ketua dilepas
    q('UPDATE users SET division_id=?, is_ketua = CASE WHEN division_id = ? THEN is_ketua ELSE 0 END WHERE id=?',
        [$divisionId, $divisionId, $userId]);
    audit_log('ORG_MEMBER', "Anggota #$userId -> divisi #$divisionId");
    flash_set('success', $user['nama'] . ' dipindahkan ke ' . $div['nama'] . '.');
    redirect('/admin/struktur');
}


// ==================== DIVISI ====================

function admin_divisions_index(): void
{
    require_permission(PERM['DIVISIONS_MANAGE']);
    $divisions = all(
        'SELECT d.*,
           (SELECT COUNT(*) FROM users u WHERE u.division_id = d.id AND u.is_active = 1) AS member_count,
           (SELECT COUNT(*) FROM whiteboard_notes n WHERE n.division_id = d.id) AS note_count
         FROM divisions d ORDER BY d.id');
    layout_header(['title' => 'Kelola Divisi', 'active' => 'admin-divisi']);
    view('admin/divisions', ['divisions' => $divisions, 'me' => current_user()]);
}

function admin_divisions_store(): void
{
    require_permission(PERM['DIVISIONS_MANAGE']);
    [$nama, $kode] = read_division_input();
    if (scalar('SELECT COUNT(*) FROM divisions WHERE kode=?', [$kode])) {
        flash_set('error', 'Kode divisi sudah dipakai.');
        redirect('/admin/divisi');
    }
    q('INSERT INTO divisions (nama, kode) VALUES (?,?)', [$nama, $kode]);
    audit_log('DIV_CREATE', "Divisi baru: $nama");
    flash_set('success', 'Divisi ditambahkan.');
    redirect('/admin/divisi');
}

function admin_divisions_update(int $id): void
{
    require_permission(PERM['DIVISIONS_MANAGE']);
    [$nama, $kode] = read_division_input();
    if (scalar('SELECT COUNT(*) FROM divisions WHERE kode=? AND id!=?', [$kode, $id])) {
        flash_set('error', 'Kode divisi sudah dipakai divisi lain.');
        redirect('/admin/divisi');
    }
    q('UPDATE divisions SET nama=?, kode=? WHERE id=?', [$nama, $kode, $id]);
    audit_log('DIV_UPDATE', "Ubah divisi #$id");
    flash_set('success', 'Divisi diperbarui.');
    redirect('/admin/divisi');
}

function read_division_input(): array
{
    $nama = trim($_POST['nama'] ?? '');
    $kode = strtoupper(trim($_POST['kode'] ?? ''));
    if ($nama === '' || $kode === '' || !preg_match('/^[A-Z0-9]{2,20}$/', $kode)) {
        flash_set('error', 'Nama wajib diisi dan kode 2-20 karakter huruf/angka.');
        redirect('/admin/divisi');
    }
    return [$nama, $kode];
}

function admin_divisions_delete(int $id): void
{
    require_permission(PERM['DIVISIONS_MANAGE']);
    if (scalar('SELECT COUNT(*) FROM users WHERE division_id=?', [$id]) > 0) {
        flash_set('error', 'Masih ada pengguna pada divisi ini.');
        redirect('/admin/divisi');
    }
    q('DELETE FROM divisions WHERE id=?', [$id]);
    audit_log('DIV_DELETE', "Hapus divisi #$id");
    flash_set('success', 'Divisi dihapus.');
    redirect('/admin/divisi');
}
