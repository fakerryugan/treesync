<?php
/** Controller: Delegasi & Kanban tugas. */

declare(strict_types=1);

function tasks_index(): void
{
    require_login();
    $user = current_user();
    $uid = (int)$user['id'];

    if (can(PERM['TASKS_VIEW_ALL'])) {
        $tasks = all(
            'SELECT t.*, au.nama AS assigner_nama, ae.nama AS assignee_nama
             FROM tasks t
             JOIN users au ON au.id = t.assigner_id
             JOIN users ae ON ae.id = t.assignee_id
             ORDER BY t.updated_at DESC LIMIT 200');
    } else {
        // Tugas milik saya, yang saya berikan, atau yang saya terima dari sub-tree
        $subtreeIds = array_map('intval', array_column(descendant_users($uid), 'id'));
        $subtreeIds[] = $uid;
        $in = implode(',', $subtreeIds);
        $tasks = all(
            "SELECT t.*, au.nama AS assigner_nama, ae.nama AS assignee_nama
             FROM tasks t
             JOIN users au ON au.id = t.assigner_id
             JOIN users ae ON ae.id = t.assignee_id
             WHERE t.assignee_id = ? OR t.assigner_id = ?
                OR (t.assignee_id IN ($in) AND t.assigner_id = ?)
             ORDER BY t.updated_at DESC LIMIT 200", [$uid, $uid, $uid]);
    }

    $assignableIds = assignable_user_ids($user);
    $assignable = [];
    if ($assignableIds !== []) {
        $assignable = all(
            'SELECT id, nama, jabatan FROM users
             WHERE is_active = 1 AND id IN (' . implode(',', $assignableIds) . ')
             ORDER BY nama');
    }

    layout_header(['title' => 'Delegasi Tugas', 'active' => 'tugas']);
    view('tasks', ['tasks' => $tasks, 'me' => $user, 'assignable' => $assignable]);
}

function tasks_create(): void
{
    require_login();
    $user = current_user();
    $judul = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $assignee = (int)($_POST['assignee_id'] ?? 0);

    if ($judul === '' || $assignee === 0) {
        flash_set('error', 'Judul dan penerima tugas wajib diisi.');
        redirect('/tugas');
    }
    if (!in_array($assignee, assignable_user_ids(), true)) {
        flash_set('error', 'Delegasi hanya boleh ke bawah jalur komando Anda (sub-tree).');
        redirect('/tugas');
    }

    q('INSERT INTO tasks (assigner_id, assignee_id, judul, deskripsi) VALUES (?,?,?,?)',
        [$user['id'], $assignee, $judul, $deskripsi]);
    audit_log('TASK_CREATE', "Tugas '$judul' kepada #$assignee");
    flash_set('success', 'Tugas berhasil didelegasikan.');
    redirect('/tugas');
}

function tasks_api_status(int $id): void
{
    require_login();
    $user = current_user();
    $task = one('SELECT * FROM tasks WHERE id=?', [$id]);
    if ($task === null) json_error('Tugas tidak ditemukan.', 404);

    $status = $_POST['status'] ?? '';
    if (!in_array($status, ['TODO', 'IN_PROGRESS', 'DONE'], true)) {
        json_error('Status tidak valid.');
    }
    $mayMove = (int)$task['assigner_id'] === (int)$user['id']
        || (int)$task['assignee_id'] === (int)$user['id']
        || can(PERM['TASKS_ASSIGN_ALL']);
    if (!$mayMove) json_error('Tidak berwenang mengubah tugas ini.', 403);

    q('UPDATE tasks SET status=? WHERE id=?', [$status, $id]);
    json_response(['ok' => true, 'status' => $status]);
}
