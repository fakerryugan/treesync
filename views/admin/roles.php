<?php /** $roles, $matrix, $me */ ?>
<?php $permList = all('SELECT * FROM permissions ORDER BY id'); ?>
<div class="flex justify-between items-center mb-6">
    <p class="text-slate-500 text-sm">Satu peran menentukan izin apa saja yang bisa diakses penggunanya. Centang untuk memberi kewenangan.</p>
    <button onclick="showRoleModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
        <i class="fa-solid fa-plus mr-2"></i> Tambah Peran
    </button>
</div>

<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
<table class="w-full text-sm">
    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] uppercase tracking-wide">
        <tr>
            <th class="px-4 py-3 text-left">Peran</th>
            <th class="px-4 py-3 text-center">Pengguna</th>
            <th class="px-4 py-3 text-center">Izin Aktif</th>
            <th class="px-4 py-3 text-left">Deskripsi</th>
            <th class="px-4 py-3 text-right">Aksi</th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
    <?php foreach ($roles as $r): ?>
        <tr class="hover:bg-slate-50">
            <td class="px-4 py-3">
                <p class="font-semibold text-slate-800"><?= e($r['nama']) ?> <?= (int)$r['is_system'] ? '<span class="text-[9px] bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded">BAWAAN</span>' : '' ?></p>
                <p class="text-[10px] text-slate-400">/<?= e($r['slug']) ?></p>
            </td>
            <td class="px-4 py-3 text-center text-slate-600"><?= (int)$r['user_count'] ?></td>
            <td class="px-4 py-3 text-center">
                <?php foreach (($matrix[(int)$r['id']] ?? []) as $pid => $_): ?>
                    <?php $p = array_values(array_filter($permList, fn($x) => (int)$x['id'] === (int)$pid))[0] ?? null; ?>
                    <?php if ($p): ?><span class="text-[9px] bg-blue-50 text-blue-700 border border-blue-100 px-1.5 py-0.5 rounded mr-1 mb-1 inline-block"><?= e($p['nama']) ?></span><?php endif; ?>
                <?php endforeach; ?>
            </td>
            <td class="px-4 py-3 text-[11px] text-slate-500 max-w-xs"><?= e($r['deskripsi']) ?></td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
                <button onclick='showRoleModal(<?= json_encode([
                    'id' => (int)$r['id'], 'nama' => $r['nama'], 'deskripsi' => $r['deskripsi'],
                    'perms' => array_map('intval', array_keys($matrix[(int)$r['id']] ?? [])),
                ], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="text-blue-600 hover:text-blue-800 text-xs font-medium mr-3"><i class="fa-solid fa-pen mr-1"></i>Edit</button>
                <?php if (!(int)$r['is_system']): ?>
                <form method="post" action="<?= base_url('/admin/peran/' . (int)$r['id'] . '/delete') ?>" class="inline" onsubmit="return confirm('Hapus peran ini?')">
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
const PERMS = <?= json_encode(array_map(fn($p) => ['id' => (int)$p['id'], 'kode' => $p['kode'], 'nama' => $p['nama'], 'deskripsi' => $p['deskripsi']], $permList), JSON_UNESCAPED_UNICODE) ?>;

function showRoleModal(role) {
    const isEdit = !!role;
    const permHtml = PERMS.map(p => `
        <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-slate-100 hover:bg-slate-50 cursor-pointer">
            <input type="checkbox" name="permissions[]" value="${p.id}" ${isEdit && role.perms.includes(p.id) ? 'checked' : ''} class="mt-0.5">
            <span><span class="text-xs font-semibold text-slate-700">${p.nama}</span>
            <span class="block text-[10px] text-slate-400">${p.deskripsi}</span></span>
        </label>`).join('');

    openModal(isEdit ? 'Edit Peran & Izin' : 'Tambah Peran Baru', `
        <form id="role-form" method="post" action="${BASE}/admin/peran${isEdit ? '/' + role.id + '/update' : '/store'}">
            <input type="hidden" name="_csrf" value="${CSRF}">
            <div class="space-y-3 text-sm">
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Nama Peran *</label>
                    <input name="nama" required value="${isEdit ? role.nama : ''}" class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2"></div>
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Deskripsi</label>
                    <input name="deskripsi" value="${isEdit ? role.deskripsi : ''}" class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2"></div>
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Izin Akses (centang yang boleh diakses)</label>
                    <div class="mt-1 max-h-64 overflow-y-auto grid grid-cols-1 gap-1">${permHtml}</div>
                </div>
            </div>
        </form>`,
        () => document.getElementById('role-form').submit());
}
</script>

<?php layout_footer(); ?>
