<?php
/**
 * Controller: Delegasi & Kanban tugas.
 * Mendukung Multi-Assignee & Permintaan Tugas Lintas Divisi untuk Ketua Divisi.
 */

declare(strict_types=1);

function tasks_index(): void
{
    require_login();
    $user = current_user();
    $uid = (int)$user['id'];
    $isAdmin = ($user['role_slug'] ?? '') === 'administrator';
    $isKetua = (int)($user['is_ketua'] ?? 0) === 1;
    $myDivId = (int)($user['division_id'] ?? 0);

    if ($isAdmin || can(PERM['TASKS_VIEW_ALL'])) {
        $tasks = all(
            'SELECT t.*,
                    au.nama AS assigner_nama,
                    au.division_id AS assigner_divisi_id,
                    ad.nama AS assigner_divisi_nama,
                    td.nama AS target_divisi_nama
             FROM tasks t
             JOIN users au ON au.id = t.assigner_id
             LEFT JOIN divisions ad ON ad.id = au.division_id
             LEFT JOIN divisions td ON td.id = t.target_division_id
             ORDER BY t.updated_at DESC LIMIT 250');
    } else {
        // Tugas milik saya, yang saya berikan, sub-tree, atau permintaan untuk divisi saya (jika ketua)
        $subtreeIds = array_map('intval', array_column(descendant_users($uid), 'id'));
        $subtreeIds[] = $uid;
        $in = implode(',', $subtreeIds);

        $tasks = all(
            "SELECT DISTINCT t.*,
                    au.nama AS assigner_nama,
                    au.division_id AS assigner_divisi_id,
                    ad.nama AS assigner_divisi_nama,
                    td.nama AS target_divisi_nama
             FROM tasks t
             JOIN users au ON au.id = t.assigner_id
             LEFT JOIN divisions ad ON ad.id = au.division_id
             LEFT JOIN divisions td ON td.id = t.target_division_id
             LEFT JOIN task_assignees ta ON ta.task_id = t.id
             LEFT JOIN users tu ON tu.id = ta.user_id
             WHERE t.assigner_id = ?
                OR t.assignee_id = ?
                OR ta.user_id = ?
                OR (t.assigner_id = ? AND (t.assignee_id IN ($in) OR ta.user_id IN ($in)))
                OR (? = 1 AND (t.target_division_id = ? OR tu.division_id = ?))
             ORDER BY t.updated_at DESC LIMIT 250",
            [$uid, $uid, $uid, $uid, $isKetua ? 1 : 0, $myDivId, $myDivId]
        );
    }

    if ($tasks !== []) {
        $taskIds = array_map('intval', array_column($tasks, 'id'));
        $inTaskIds = implode(',', $taskIds);

        // 1. Ambil semua penerima (assignees)
        $assignees = all(
            "SELECT ta.task_id, u.id, u.nama, u.jabatan, u.division_id, d.nama AS divisi_nama
             FROM task_assignees ta
             JOIN users u ON u.id = ta.user_id
             LEFT JOIN divisions d ON d.id = u.division_id
             WHERE ta.task_id IN ($inTaskIds)
             ORDER BY u.nama"
        );
        $assigneesByTask = [];
        foreach ($assignees as $a) {
            $assigneesByTask[(int)$a['task_id']][] = [
                'id'          => (int)$a['id'],
                'nama'        => $a['nama'],
                'jabatan'     => $a['jabatan'],
                'division_id' => (int)($a['division_id'] ?? 0),
                'divisi_nama' => $a['divisi_nama'] ?? 'Tanpa Divisi',
            ];
        }

        // 2. Ambil jumlah komentar per tugas
        $commentCounts = all(
            "SELECT task_id, COUNT(*) AS count
             FROM task_comments
             WHERE task_id IN ($inTaskIds)
             GROUP BY task_id"
        );
        $commentMap = [];
        foreach ($commentCounts as $cc) {
            $commentMap[(int)$cc['task_id']] = (int)$cc['count'];
        }

        foreach ($tasks as &$task) {
            $tId = (int)$task['id'];
            $task['assignees'] = $assigneesByTask[$tId] ?? [];
            if ($task['assignees'] === [] && !empty($task['assignee_id'])) {
                $fallback = one(
                    'SELECT u.id, u.nama, u.jabatan, u.division_id, d.nama AS divisi_nama
                     FROM users u
                     LEFT JOIN divisions d ON d.id = u.division_id
                     WHERE u.id = ?', [(int)$task['assignee_id']]
                );
                if ($fallback !== null) {
                    $task['assignees'][] = [
                        'id'          => (int)$fallback['id'],
                        'nama'        => $fallback['nama'],
                        'jabatan'     => $fallback['jabatan'],
                        'division_id' => (int)($fallback['division_id'] ?? 0),
                        'divisi_nama' => $fallback['divisi_nama'] ?? 'Tanpa Divisi',
                    ];
                }
            }
            $task['comment_count'] = $commentMap[$tId] ?? 0;

            // Tentukan target divisi jika belum tercatat di target_division_id
            if (empty($task['target_division_id']) && $task['assignees'] !== []) {
                $firstAssignee = $task['assignees'][0];
                if (!empty($firstAssignee['divisi_nama']) && $firstAssignee['divisi_nama'] !== 'Tanpa Divisi') {
                    $task['target_divisi_nama'] = $firstAssignee['divisi_nama'];
                }
            }
        }
        unset($task);
    }

    $assignableIds = assignable_user_ids($user);
    $assignable = [];
    if ($assignableIds !== []) {
        $assignable = all(
            'SELECT u.id, u.nama, u.jabatan, u.division_id, d.nama AS divisi_nama
             FROM users u
             LEFT JOIN divisions d ON d.id = u.division_id
             WHERE u.is_active = 1 AND u.id IN (' . implode(',', $assignableIds) . ')
             ORDER BY u.nama');
    }

    // Daftar semua divisi untuk kebutuhan permintaan tugas lintas divisi
    $divisions = all('SELECT d.*, (SELECT u.nama FROM users u WHERE u.division_id=d.id AND u.is_ketua=1 LIMIT 1) AS ketua_nama FROM divisions d ORDER BY d.nama');

    // Anggota divisi saya (untuk Ketua yang ingin mendistribusikan tugas saat diterima)
    $myDivisionMembers = [];
    if ($myDivId > 0) {
        $myDivisionMembers = all(
            'SELECT u.id, u.nama, u.jabatan, d.nama AS divisi_nama
             FROM users u
             LEFT JOIN divisions d ON d.id = u.division_id
             WHERE u.division_id = ? AND u.is_active = 1
             ORDER BY u.nama', [$myDivId]);
    }

    layout_header(['title' => 'Delegasi Tugas', 'active' => 'tugas']);
    view('tasks', [
        'tasks'             => $tasks,
        'me'                => $user,
        'assignable'        => $assignable,
        'divisions'         => $divisions,
        'myDivisionMembers' => $myDivisionMembers,
        'isKetua'           => $isKetua,
        'isAdmin'           => $isAdmin,
        'myDivId'           => $myDivId,
    ]);
}

function tasks_create(): void
{
    require_login();
    $user = current_user();
    $uid = (int)$user['id'];
    $isAdmin = ($user['role_slug'] ?? '') === 'administrator';
    $userDivId = (int)($user['division_id'] ?? 0);

    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $mode = $_POST['mode'] ?? 'internal'; // 'internal' | 'cross_division'
    $targetDivisionId = (int)($_POST['target_division_id'] ?? 0);

    if ($judul === '') {
        flash_set('error', 'Judul tugas wajib diisi.');
        redirect('/tugas');
    }

    $rawAssignees = $_POST['assignee_ids'] ?? ($_POST['assignee_id'] ?? []);
    if (is_string($rawAssignees)) {
        $rawAssignees = explode(',', $rawAssignees);
    } elseif (!is_array($rawAssignees)) {
        $rawAssignees = [$rawAssignees];
    }
    $assigneeIds = array_values(array_unique(array_filter(array_map('intval', $rawAssignees), fn($id) => $id > 0)));

    $status = 'TODO';

    if ($mode === 'cross_division') {
        // Permintaan tugas lintas divisi
        if ($targetDivisionId <= 0) {
            flash_set('error', 'Silakan pilih divisi tujuan untuk permintaan tugas lintas divisi.');
            redirect('/tugas');
        }

        // Jika tidak ada personil spesifik yang dipilih, otomatis arahkan ke Ketua divisi tujuan (jika ada)
        if ($assigneeIds === []) {
            $targetKetuaId = (int)(scalar('SELECT id FROM users WHERE division_id=? AND is_ketua=1 AND is_active=1 LIMIT 1', [$targetDivisionId]) ?? 0);
            if ($targetKetuaId > 0) {
                $assigneeIds = [$targetKetuaId];
            }
        }

        // Tugas antar-divisi berstatus REQUESTED dan menunggu persetujuan ketua divisi tujuan
        $status = 'REQUESTED';
    } else {
        // Delegasi internal
        if ($assigneeIds === []) {
            flash_set('error', 'Minimal satu penerima tugas wajib dipilih.');
            redirect('/tugas');
        }

        // Cek apakah personil yang dipilih berasal dari divisi lain
        $firstAssigneeDiv = (int)(scalar('SELECT division_id FROM users WHERE id=?', [$assigneeIds[0]]) ?? 0);
        if ($userDivId > 0 && $firstAssigneeDiv > 0 && $firstAssigneeDiv !== $userDivId && !$isAdmin) {
            // Otomatis ubah menjadi permintaan lintas divisi (REQUESTED)
            $status = 'REQUESTED';
            $targetDivisionId = $firstAssigneeDiv;
        } else {
            $allowedIds = assignable_user_ids($user);
            foreach ($assigneeIds as $aId) {
                if (!in_array($aId, $allowedIds, true) && !$isAdmin) {
                    flash_set('error', 'Salah satu personil penerima di luar wewenang jalur komando Anda.');
                    redirect('/tugas');
                }
            }
        }
    }

    $primaryAssignee = $assigneeIds[0] ?? null;
    $targetDivVal = $targetDivisionId > 0 ? $targetDivisionId : null;

    q('INSERT INTO tasks (assigner_id, assignee_id, target_division_id, judul, deskripsi, status) VALUES (?,?,?,?,?,?)',
        [$uid, $primaryAssignee, $targetDivVal, $judul, $deskripsi, $status]);
    $taskId = last_insert_id();

    foreach ($assigneeIds as $aId) {
        q('INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?, ?)', [$taskId, $aId]);
    }

    $count = count($assigneeIds);
    if ($status === 'REQUESTED') {
        $divName = scalar('SELECT nama FROM divisions WHERE id=?', [$targetDivisionId]) ?? 'divisi tujuan';
        audit_log('TASK_REQUEST', "Permintaan tugas '$judul' ke $divName (#$taskId)");
        flash_set('success', "Permintaan tugas berhasil diajukan ke $divName. Menunggu konfirmasi Ketua Divisi.");
    } else {
        audit_log('TASK_CREATE', "Tugas '$judul' kepada $count personil (#$taskId)");
        flash_set('success', "Tugas berhasil didelegasikan kepada $count personil.");
    }

    redirect('/tugas');
}

function tasks_update(int $id): void
{
    require_login();
    $user = current_user();
    $task = one('SELECT * FROM tasks WHERE id=?', [$id]);
    if ($task === null) {
        flash_set('error', 'Tugas tidak ditemukan.');
        redirect('/tugas');
    }

    $mayEdit = (int)$task['assigner_id'] === (int)$user['id']
        || can(PERM['TASKS_ASSIGN_ALL'])
        || ($user['role_slug'] ?? '') === 'administrator';

    if (!$mayEdit) {
        flash_set('error', 'Anda tidak berwenang mengedit atau mengalihkan tugas ini.');
        redirect('/tugas');
    }

    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status    = $_POST['status'] ?? $task['status'];

    $rawAssignees = $_POST['assignee_ids'] ?? ($_POST['assignee_id'] ?? []);
    if (is_string($rawAssignees)) {
        $rawAssignees = explode(',', $rawAssignees);
    } elseif (!is_array($rawAssignees)) {
        $rawAssignees = [$rawAssignees];
    }
    $assigneeIds = array_values(array_unique(array_filter(array_map('intval', $rawAssignees), fn($aId) => $aId > 0)));

    if ($judul === '') {
        flash_set('error', 'Judul tugas wajib diisi.');
        redirect('/tugas');
    }
    if (!in_array($status, ['REQUESTED', 'TODO', 'IN_PROGRESS', 'DONE', 'REJECTED'], true)) {
        flash_set('error', 'Status tugas tidak valid.');
        redirect('/tugas');
    }

    $primaryAssignee = $assigneeIds[0] ?? null;
    q('UPDATE tasks SET judul=?, deskripsi=?, assignee_id=?, status=? WHERE id=?',
        [$judul, $deskripsi, $primaryAssignee, $status, $id]);

    if ($assigneeIds !== []) {
        q('DELETE FROM task_assignees WHERE task_id=?', [$id]);
        foreach ($assigneeIds as $aId) {
            q('INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?, ?)', [$id, $aId]);
        }
    }

    $count = count($assigneeIds);
    audit_log('TASK_UPDATE', "Tugas #$id diperbarui ($count personil)");
    flash_set('success', 'Tugas berhasil diperbarui.');
    redirect('/tugas');
}

function tasks_delete(int $id): void
{
    require_login();
    $user = current_user();
    $task = one('SELECT * FROM tasks WHERE id=?', [$id]);
    if ($task === null) {
        flash_set('error', 'Tugas tidak ditemukan.');
        redirect('/tugas');
    }

    $mayDelete = (int)$task['assigner_id'] === (int)$user['id']
        || can(PERM['TASKS_ASSIGN_ALL'])
        || ($user['role_slug'] ?? '') === 'administrator';

    if (!$mayDelete) {
        flash_set('error', 'Anda tidak berwenang menghapus tugas ini.');
        redirect('/tugas');
    }

    q('DELETE FROM tasks WHERE id=?', [$id]);
    audit_log('TASK_DELETE', "Tugas #$id dihapus");
    flash_set('success', 'Tugas berhasil dihapus.');
    redirect('/tugas');
}

function tasks_api_status(int $id): void
{
    require_login();
    $user = current_user();
    $task = one('SELECT * FROM tasks WHERE id=?', [$id]);
    if ($task === null) json_error('Tugas tidak ditemukan.', 404);

    $status = $_POST['status'] ?? '';
    if (!in_array($status, ['REQUESTED', 'TODO', 'IN_PROGRESS', 'DONE', 'REJECTED'], true)) {
        json_error('Status tidak valid.');
    }

    $isAssignee = (int)($task['assignee_id'] ?? 0) === (int)$user['id']
        || (bool)one('SELECT 1 FROM task_assignees WHERE task_id=? AND user_id=? LIMIT 1', [$id, $user['id']]);

    $mayMove = (int)$task['assigner_id'] === (int)$user['id']
        || $isAssignee
        || can(PERM['TASKS_ASSIGN_ALL'])
        || ($user['role_slug'] ?? '') === 'administrator';

    if (!$mayMove) json_error('Tidak berwenang mengubah tugas ini.', 403);

    q('UPDATE tasks SET status=? WHERE id=?', [$status, $id]);
    json_response(['ok' => true, 'status' => $status]);
}

/**
 * Menerima / mengambil tugas permintaan lintas divisi.
 * Hanya Ketua Divisi penerima atau Admin yang dapat menerima tugas ini.
 */
function tasks_api_accept(int $id): void
{
    require_login();
    $user = current_user();
    $task = one('SELECT * FROM tasks WHERE id=?', [$id]);
    if ($task === null) json_error('Tugas tidak ditemukan.', 404);

    $uid = (int)$user['id'];
    $isKetua = (int)($user['is_ketua'] ?? 0) === 1;
    $myDivId = (int)($user['division_id'] ?? 0);
    $isAdmin = ($user['role_slug'] ?? '') === 'administrator';

    $targetDiv = (int)($task['target_division_id'] ?? 0);
    if ($targetDiv === 0 && !empty($task['assignee_id'])) {
        $targetDiv = (int)(scalar('SELECT division_id FROM users WHERE id=?', [$task['assignee_id']]) ?? 0);
    }

    $canAccept = $isAdmin || ($isKetua && $myDivId > 0 && $myDivId === $targetDiv);
    if (!$canAccept) {
        json_error('Hanya Ketua Divisi tujuan atau Administrator yang dapat menerima tugas ini.', 403);
    }

    // Personil penerima baru (opsional dari modal terima)
    $rawAssignees = $_POST['assignee_ids'] ?? [];
    if (is_string($rawAssignees)) $rawAssignees = explode(',', $rawAssignees);
    $assigneeIds = array_values(array_unique(array_filter(array_map('intval', (array)$rawAssignees), fn($i) => $i > 0)));

    if ($assigneeIds === []) {
        $existing = array_map('intval', array_column(all('SELECT user_id FROM task_assignees WHERE task_id=?', [$id]), 'user_id'));
        if ($existing !== []) {
            $assigneeIds = $existing;
        } else {
            $assigneeIds = [$uid];
        }
    }

    $primaryAssignee = $assigneeIds[0];
    q('UPDATE tasks SET status="TODO", assignee_id=? WHERE id=?', [$primaryAssignee, $id]);

    q('DELETE FROM task_assignees WHERE task_id=?', [$id]);
    foreach ($assigneeIds as $aId) {
        q('INSERT IGNORE INTO task_assignees (task_id, user_id) VALUES (?, ?)', [$id, $aId]);
    }

    $catatan = trim($_POST['catatan'] ?? '');
    $commentMsg = "Tugas DITERIMA oleh " . $user['nama'] . " (" . ($user['jabatan'] ?? 'Ketua Divisi') . "). Tugas kini masuk ke kolom To Do.";
    if ($catatan !== '') {
        $commentMsg .= "\nCatatan: " . $catatan;
    }
    q('INSERT INTO task_comments (task_id, author_id, isi, tipe) VALUES (?, ?, ?, "ACCEPT")',
        [$id, $uid, $commentMsg]);

    audit_log('TASK_ACCEPT', "Tugas #$id diterima oleh Ketua #$uid");
    json_response(['ok' => true, 'message' => 'Tugas berhasil diterima dan masuk ke kolom To Do.']);
}

/**
 * Menolak tugas permintaan lintas divisi dengan alasan.
 */
function tasks_api_reject(int $id): void
{
    require_login();
    $user = current_user();
    $task = one('SELECT * FROM tasks WHERE id=?', [$id]);
    if ($task === null) json_error('Tugas tidak ditemukan.', 404);

    $uid = (int)$user['id'];
    $isKetua = (int)($user['is_ketua'] ?? 0) === 1;
    $myDivId = (int)($user['division_id'] ?? 0);
    $isAdmin = ($user['role_slug'] ?? '') === 'administrator';

    $targetDiv = (int)($task['target_division_id'] ?? 0);
    if ($targetDiv === 0 && !empty($task['assignee_id'])) {
        $targetDiv = (int)(scalar('SELECT division_id FROM users WHERE id=?', [$task['assignee_id']]) ?? 0);
    }

    $canReject = $isAdmin || ($isKetua && $myDivId > 0 && $myDivId === $targetDiv);
    if (!$canReject) {
        json_error('Hanya Ketua Divisi tujuan atau Administrator yang dapat menolak tugas ini.', 403);
    }

    $alasan = trim($_POST['alasan'] ?? '');
    if ($alasan === '') {
        json_error('Alasan penolakan tugas wajib diisi.');
    }

    q('UPDATE tasks SET status="REJECTED" WHERE id=?', [$id]);

    $rejectMsg = "Tugas DITOLAK oleh " . $user['nama'] . " (" . ($user['jabatan'] ?? 'Ketua Divisi') . ").\nAlasan: " . $alasan;
    q('INSERT INTO task_comments (task_id, author_id, isi, tipe) VALUES (?, ?, ?, "REJECT")',
        [$id, $uid, $rejectMsg]);

    audit_log('TASK_REJECT', "Tugas #$id ditolak oleh Ketua #$uid: $alasan");
    json_response(['ok' => true, 'message' => 'Tugas berhasil ditolak dengan alasan tercatat.']);
}

/**
 * Mengambil riwayat komentar & diskusi pada tugas.
 */
function tasks_api_comments(int $id): void
{
    require_login();
    $task = one(
        'SELECT t.id, t.judul, t.status, t.assigner_id, t.target_division_id,
                au.nama AS assigner_nama, td.nama AS target_divisi_nama
         FROM tasks t
         JOIN users au ON au.id = t.assigner_id
         LEFT JOIN divisions td ON td.id = t.target_division_id
         WHERE t.id = ?', [$id]);
    if ($task === null) json_error('Tugas tidak ditemukan.', 404);

    $comments = all(
        'SELECT tc.*, u.nama AS author_nama, u.jabatan AS author_jabatan,
                d.nama AS author_divisi
         FROM task_comments tc
         JOIN users u ON u.id = tc.author_id
         LEFT JOIN divisions d ON d.id = u.division_id
         WHERE tc.task_id = ?
         ORDER BY tc.created_at ASC',
        [$id]
    );

    json_response([
        'ok'       => true,
        'task'     => $task,
        'comments' => array_map(fn($c) => [
            'id'             => (int)$c['id'],
            'author_id'      => (int)$c['author_id'],
            'author_nama'    => $c['author_nama'],
            'author_jabatan' => $c['author_jabatan'],
            'author_divisi'  => $c['author_divisi'] ?? 'Tanpa Divisi',
            'isi'            => $c['isi'],
            'tipe'           => $c['tipe'],
            'created_at'     => date('d M Y, H:i', strtotime($c['created_at'])),
            'time_ago'       => time_ago($c['created_at']),
        ], $comments)
    ]);
}

/**
 * Menambahkan komentar atau pertanyaan klarifikasi pada tugas.
 */
function tasks_api_comment_add(int $id): void
{
    require_login();
    $user = current_user();
    $task = one('SELECT id FROM tasks WHERE id=?', [$id]);
    if ($task === null) json_error('Tugas tidak ditemukan.', 404);

    $isi = trim($_POST['isi'] ?? '');
    if ($isi === '') {
        json_error('Pesan komentar / pertanyaan tidak boleh kosong.');
    }

    q('INSERT INTO task_comments (task_id, author_id, isi, tipe) VALUES (?, ?, ?, "COMMENT")',
        [$id, $user['id'], $isi]);
    $commentId = last_insert_id();

    audit_log('TASK_COMMENT', "Komentar pada tugas #$id oleh #" . $user['id']);
    json_response([
        'ok'      => true,
        'message' => 'Komentar berhasil dikirim.',
        'comment' => [
            'id'             => $commentId,
            'author_id'      => (int)$user['id'],
            'author_nama'    => $user['nama'],
            'author_jabatan' => $user['jabatan'],
            'author_divisi'  => $user['divisi_nama'] ?? 'Tanpa Divisi',
            'isi'            => $isi,
            'tipe'           => 'COMMENT',
            'created_at'     => date('d M Y, H:i'),
            'time_ago'       => 'Baru saja',
        ]
    ]);
}
