<?php /** $users, $roles, $divisions, $me */ ?>
<div class="flex justify-between items-center mb-6">
    <p class="text-slate-500 text-sm">Kelola akun pengguna: peran akses, divisi, ketua, dan relasi atasan pada pohon organisasi.</p>
    <button onclick="showUserModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
        <i class="fa-solid fa-user-plus mr-2"></i> Tambah Pengguna
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
<table class="w-full text-sm">
    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] uppercase tracking-wide">
        <tr>
            <th class="px-4 py-3 text-left">Pengguna</th>
            <th class="px-4 py-3 text-left">Peran Akses</th>
            <th class="px-4 py-3 text-left">Divisi</th>
            <th class="px-4 py-3 text-left">Atasan</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3 text-right">Aksi</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
    <?php foreach ($users as $u): ?>
        <tr class="hover:bg-slate-50">
            <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-white text-[10px] <?= avatar_color((int)$u['id']) ?>"><?= e(initials($u['nama'])) ?></div>
                    <div>
                        <p class="font-semibold text-slate-800"><?= e($u['nama']) ?> <?= (int)$u['is_ketua'] ? '<span class="text-[9px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded ml-1">KETUA</span>' : '' ?></p>
                        <p class="text-[11px] text-slate-500">@<?= e($u['username']) ?> • <?= e($u['jabatan']) ?></p>
                    </div>
                </div>
            </td>
            <td class="px-4 py-3"><span class="text-[11px] bg-slate-100 border border-slate-200 px-2 py-1 rounded font-medium text-slate-600"><?= e($u['role_nama']) ?></span></td>
            <td class="px-4 py-3 text-slate-600"><?= e($u['divisi_nama'] ?? '—') ?></td>
            <td class="px-4 py-3 text-slate-600"><?= e($u['parent_nama'] ?? 'ROOT') ?></td>
            <td class="px-4 py-3 text-center">
                <span class="text-[10px] font-bold px-2 py-1 rounded-full <?= (int)$u['is_active'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>"><?= (int)$u['is_active'] ? 'AKTIF' : 'NONAKTIF' ?></span>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
                <button onclick='showUserModal(<?= json_encode([
                    'id' => (int)$u['id'], 'nama' => $u['nama'], 'username' => $u['username'], 'email' => $u['email'],
                    'jabatan' => $u['jabatan'], 'role_id' => (int)$u['role_id'],
                    'division_id' => (int)($u['division_id'] ?? 0), 'parent_id' => (int)($u['parent_id'] ?? 0),
                    'is_ketua' => (int)$u['is_ketua'], 'is_active' => (int)$u['is_active'],
                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="text-blue-600 hover:text-blue-800 text-xs font-medium mr-3"><i class="fa-solid fa-pen mr-1"></i>Edit</button>
                <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                <form method="post" action="<?= base_url('/admin/pengguna/' . (int)$u['id'] . '/toggle') ?>" class="inline" onsubmit="return confirm('Ubah status aktif akun ini?')">
                    <?= csrf_field() ?>
                    <button class="text-amber-600 hover:text-amber-800 text-xs font-medium mr-3"><i class="fa-solid fa-power-off mr-1"></i><?= (int)$u['is_active'] ? 'Nonaktif' : 'Aktifkan' ?></button>
                </form>
                <form method="post" action="<?= base_url('/admin/pengguna/' . (int)$u['id'] . '/delete') ?>" class="inline" onsubmit="return confirm('Hapus permanen pengguna ini?')">
                    <?= csrf_field() ?>
                    <button class="text-red-600 hover:text-red-800 text-xs font-medium"><i class="fa-solid fa-trash mr-1"></i>Hapus</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<script>
const ROLES = <?= json_encode(array_map(fn($r) => ['id' => (int)$r['id'], 'nama' => $r['nama']], $roles), JSON_UNESCAPED_UNICODE) ?>;
const DIVISIONS = <?= json_encode(array_map(fn($d) => ['id' => (int)$d['id'], 'nama' => $d['nama']], $divisions), JSON_UNESCAPED_UNICODE) ?>;
const USERS = <?= json_encode(array_map(fn($u) => ['id' => (int)$u['id'], 'nama' => $u['nama'], 'role_id' => (int)$u['role_id']], $users), JSON_UNESCAPED_UNICODE) ?>;

function showUserModal(u) {
    const isEdit = !!u;
    const roleOptions = ROLES.map(r => `<option value="${r.id}" ${(isEdit ? u.role_id : 5) === r.id ? 'selected' : ''}>${r.nama}</option>`).join('');
    const divOptions = '<option value="0">— tanpa divisi (admin) —</option>' + DIVISIONS.map(d => `<option value="${d.id}" ${(isEdit ? u.division_id : 0) === d.id ? 'selected' : ''}>${d.nama}</option>`).join('');
    const parentOptions = '<option value="0">— ROOT (tanpa atasan) —</option>' + USERS
        .filter(x => !isEdit || x.id !== u.id)
        .map(x => `<option value="${x.id}" ${(isEdit ? u.parent_id : 0) === x.id ? 'selected' : ''}>${x.nama}</option>`).join('');

    openModal(isEdit ? 'Edit Pengguna' : 'Tambah Pengguna', `
        <form id="user-form" method="post" action="${BASE}/admin/pengguna${isEdit ? '/' + u.id + '/update' : '/store'}">
            <input type="hidden" name="_csrf" value="${CSRF}">
            <div class="space-y-3 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-[10px] font-bold text-slate-500 uppercase">Nama *</label>
                        <input name="nama" required value="${isEdit ? u.nama : ''}" class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2"></div>
                    <div><label class="text-[10px] font-bold text-slate-500 uppercase">Jabatan *</label>
                        <input name="jabatan" required value="${isEdit ? u.jabatan : ''}" class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-[10px] font-bold text-slate-500 uppercase">Username *</label>
                        <input name="username" ${isEdit ? '' : 'required'} value="${isEdit ? u.username : ''}" class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2"></div>
                    <div><label class="text-[10px] font-bold text-slate-500 uppercase">Email *</label>
                        <input type="email" name="email" required value="${isEdit ? u.email : ''}" class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2"></div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-[10px] font-bold text-slate-500 uppercase">Peran Akses *</label>
                        <select name="role_id" id="uf-role" onchange="ufRoleChanged()" class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2">${roleOptions}</select></div>
                    <div><label class="text-[10px] font-bold text-slate-500 uppercase">Divisi</label>
                        <select name="division_id" id="uf-division" class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2">${divOptions}</select></div>
                </div>
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Atasan Langsung</label>
                    <select name="parent_id" id="uf-parent" class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2">${parentOptions}</select></div>
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">${isEdit ? 'Password Baru (kosongkan jika tetap)' : 'Password * (min. 8 karakter)'}</label>
                    <input type="password" name="password" ${isEdit ? '' : 'required minlength="8"'} class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2"></div>
                <div class="flex items-center gap-6 pt-1">
                    <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="is_ketua" id="uf-ketua" ${isEdit && u.is_ketua ? 'checked' : ''}> Jabatan Ketua / Kepala</label>
                    <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="is_active" id="uf-active" ${!isEdit || u.is_active ? 'checked' : ''}> Akun aktif</label>
                </div>
                <p class="text-[10px] text-slate-400 leading-relaxed">Pengguna dengan peran Administrator berada di luar pohon organisasi (tanpa divisi/atasan). Ketua otomatis melihat divisi seluruh bawahannya.</p>
            </div>
        </form>`,
        () => document.getElementById('user-form').submit());

    if (isEdit) ufRoleChanged();
}
function ufRoleChanged() {
    const adminId = ROLES.find(r => r.nama === 'Administrator')?.id;
    if (!adminId) return;
    const isAdmin = document.getElementById('uf-role').value === String(adminId);
    document.getElementById('uf-division').disabled = isAdmin;
    document.getElementById('uf-parent').disabled = isAdmin;
    if (isAdmin) { document.getElementById('uf-division').value = '0'; document.getElementById('uf-parent').value = '0'; }
}
</script>

<?php layout_footer(); ?>
