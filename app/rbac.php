<?php
/**
 * RBAC & kebenaran akses:
 * - Permission dinamis per role (dikelola admin).
 * - Ketua otomatis punya kewenangan atas sub-tree organisasinya.
 */

declare(strict_types=1);

function user_permissions(?array $user = null): array
{
    $user ??= current_user();
    if ($user === null) return [];

    static $cache = [];
    $uid = (int)$user['id'];
    if (isset($cache[$uid])) return $cache[$uid];

    $perms = array_column(
        all('SELECT p.kode FROM role_permissions rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = ?', [$user['role_id']]),
        'kode'
    );
    return $cache[$uid] = $perms;
}

function can(string $permission, ?array $user = null): bool
{
    return in_array($permission, user_permissions($user), true);
}

/** Wajib punya izin, atau 403. */
function require_permission(string $permission): void
{
    require_login();
    if (!can($permission)) {
        if (is_api_request()) json_error('Tidak memiliki kewenangan.', 403);
        flash_set('error', 'Anda tidak memiliki kewenangan untuk aksi tersebut.');
        redirect('/');
    }
}

/**
 * Divisi yang bisa dilihat user:
 * - izin announcements.view_all / org.view_all -> semua divisi
 * - divisi sendiri
 * - divisi dari seluruh keturunan (sub-tree) user di pohon organisasi
 */
function visible_division_ids(?array $user = null): array
{
    $user ??= current_user();
    if ($user === null) return [];

    if (can(PERM['ANN_VIEW_ALL'], $user) || can(PERM['ORG_VIEW_ALL'], $user)) {
        return array_map('intval', array_column(all('SELECT id FROM divisions'), 'id'));
    }

    $ids = [];
    if ($user['division_id'] !== null) $ids[] = (int)$user['division_id'];
    foreach (descendant_users($user['id']) as $d) {
        if ($d['division_id'] !== null) $ids[] = (int)$d['division_id'];
    }
    return array_values(array_unique($ids));
}

/** Divisi yang boleh di-edit user di whiteboard. */
function editable_division_ids(?array $user = null): array
{
    $user ??= current_user();
    if ($user === null) return [];
    if (can(PERM['WB_EDIT_ALL'], $user)) {
        return array_map('intval', array_column(all('SELECT id FROM divisions'), 'id'));
    }
    return $user['division_id'] !== null ? [(int)$user['division_id']] : [];
}

/** Apakah $targetUserId berada dalam sub-tree (keturunan) $userId? */
function is_descendant(int $userId, int $targetUserId): bool
{
    if ($userId === $targetUserId) return true;
    foreach (descendant_users($userId) as $d) {
        if ((int)$d['id'] === $targetUserId) return true;
    }
    return false;
}

/** User yang boleh dijadikan penerima delegasi tugas oleh $user. */
function assignable_user_ids(?array $user = null): array
{
    $user ??= current_user();
    if ($user === null) return [];

    if (can(PERM['TASKS_ASSIGN_ALL'], $user)) {
        return array_map('intval', array_column(
            all('SELECT id FROM users WHERE is_active = 1 AND id != ?', [$user['id']]), 'id'));
    }
    // Pendelegasian wajib searah jalur komando (ke bawah)
    return array_map('intval', array_column(descendant_users($user['id']), 'id'));
}

/** Apakah user berwenang mengelola anggota & ketua di divisi tertentu? */
function can_manage_division(int $divisionId, ?array $user = null): bool
{
    $user ??= current_user();
    if ($user === null) return false;
    if (can(PERM['ORG_MANAGE'], $user) || can(PERM['USERS_MANAGE'], $user)) {
        return true;
    }
    // Ketua divisi berwenang atas divisinya sendiri
    if ((int)$user['is_ketua'] === 1 && (int)$user['division_id'] === $divisionId) {
        return true;
    }
    return false;
}
