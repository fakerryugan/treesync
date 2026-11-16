<?php /** $users, $divisions, $membersByDiv, $ketuaByDiv, $trees, $me, $adminRoleId */ ?>
<p class="text-slate-500 text-sm mb-6">Atur siapa ketua tiap divisi, anggotanya, dan hubungan atasan-bawahan. Semua perubahan langsung memengaruhi pohon organisasi, kewenangan divisi, dan distribusi pengumuman.</p>

<!-- ============ KARTU DIVISI: KETUA & ANGGOTA ============ -->
<h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3"><i class="fa-solid fa-building-user text-blue-600 mr-1"></i> Ketua &amp; Anggota Divisi</h2>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 mb-10">
<?php
$userOptions = array_filter($users, fn($u) => (int)$u['role_id'] !== $adminRoleId);
foreach ($divisions as $d):
    $dId = (int)$d['id'];
    $ketuaList = $ketuaByDiv[$dId] ?? [];
    $members = $membersByDiv[$dId] ?? [];
?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h3 class="font-bold text-slate-800 text-sm"><?= e($d['nama']) ?></h3>
                <p class="text-[10px] text-slate-400">Kode: <?= e($d['kode']) ?> • <?= count($members) ?> anggota</p>
            </div>
            <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-users"></i></div>
        </div>

        <!-- Ketua divisi -->
        <div class="bg-blue-50/60 border border-blue-100 rounded-lg p-3 mb-3">
            <p class="text-[10px] font-bold text-blue-700 uppercase tracking-wide mb-2"><i class="fa-solid fa-user-tie mr-1"></i> Ketua Divisi</p>
            <?php if ($ketuaList === []): ?>
                <p class="text-xs text-slate-400 italic mb-2">Belum ada ketua ditetapkan</p>
            <?php else: ?>
                <div class="space-y-1 mb-2">
                <?php foreach ($ketuaList as $k): ?>
                    <p class="text-xs font-semibold text-slate-700"><span class="w-5 h-5 inline-flex items-center justify-center rounded-full text-white text-[8px] <?= avatar_color((int)$k['id']) ?> mr-1.5"><?= e(initials($k['nama'])) ?></span><?= e($k['nama']) ?> <span class="text-slate-400 font-normal">— <?= e($k['jabatan']) ?></span></p>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= base_url('/admin/struktur/division/' . $dId . '/ketua') ?>" class="flex gap-2">
                <?= csrf_field() ?>
                <select name="user_id" required class="flex-1 min-w-0 text-xs bg-white border border-slate-300 rounded-lg px-2 py-1.5 outline-none focus:border-blue-400">
                    <option value="">— pilih ketua —</option>
                    <?php foreach ($userOptions as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= e($u['nama']) ?> (<?= e($u['divisi_nama'] ?? 'tanpa divisi') ?>)<?= (int)$u['is_ketua'] ? ' ★' : '' ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-semibold px-3 rounded-lg transition whitespace-nowrap">Jadikan Ketua</button>
            </form>
        </div>

        <!-- Anggota -->
        <div class="bg-slate-50 border border-slate-100 rounded-lg p-3">
            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wide mb-2"><i class="fa-solid fa-user-group mr-1"></i> Anggota</p>
            <div class="flex flex-wrap gap-1.5 mb-3">
                <?php if ($members === []): ?>
                    <p class="text-xs text-slate-400 italic">Belum ada anggota</p>
                <?php endif; ?>
                <?php foreach ($members as $m): ?>
                    <span class="text-[10px] font-medium px-2 py-1 rounded-full border <?= (int)$m['is_ketua'] ? 'bg-blue-100 border-blue-200 text-blue-800' : 'bg-white border-slate-200 text-slate-600' ?>">
                        <?= e($m['nama']) ?><?= (int)$m['is_ketua'] ? ' ★' : '' ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <form method="post" action="<?= base_url('/admin/struktur/division/' . $dId . '/member') ?>" class="flex gap-2">
                <?= csrf_field() ?>
                <select name="user_id" required class="flex-1 min-w-0 text-xs bg-white border border-slate-300 rounded-lg px-2 py-1.5 outline-none focus:border-blue-400">
                    <option value="">— tambah anggota —</option>
                    <?php foreach ($userOptions as $u): if ((int)$u['division_id'] === $dId) continue; ?>
                        <option value="<?= (int)$u['id'] ?>"><?= e($u['nama']) ?> (<?= e($u['divisi_nama'] ?? 'tanpa divisi') ?>)</option>
                    <?php endforeach; ?>
                </select>
                <button class="bg-slate-200 hover:bg-slate-300 text-slate-700 text-[11px] font-semibold px-3 rounded-lg transition whitespace-nowrap">+ Anggota</button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- ============ TABEL: HUBUNGAN ATASAN-BAWAHAN ============ -->
<h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3"><i class="fa-solid fa-diagram-project text-blue-600 mr-1"></i> Jalur Komando (Siapa di Bawah Siapa)</h2>
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto mb-10">
<table class="w-full text-sm">
    <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 text-[11px] uppercase tracking-wide">
        <tr>
            <th class="px-4 py-3 text-left">Pengguna</th>
            <th class="px-4 py-3 text-left">Divisi Keanggotaan</th>
            <th class="px-4 py-3 text-center">Ketua</th>
            <th class="px-4 py-3 text-left">Atasan Langsung</th>
            <th class="px-4 py-3 text-right"></th>
        </tr>
    </thead>
    <tbody class="divide-y divide-slate-100">
    <?php foreach ($users as $u): ?>
        <?php if ((int)$u['role_id'] === $adminRoleId): ?>
        <tr class="bg-slate-50/50">
            <td class="px-4 py-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-white text-[10px] <?= avatar_color((int)$u['id']) ?>"><?= e(initials($u['nama'])) ?></div>
                    <div><p class="font-semibold text-slate-800"><?= e($u['nama']) ?></p><p class="text-[11px] text-slate-500">@<?= e($u['username']) ?></p></div>
                </div>
            </td>
            <td class="px-4 py-3 text-xs text-slate-400 italic" colspan="4">Administrator — di luar pohon organisasi (atur lewat menu Kelola Pengguna)</td>
        </tr>
        <?php continue; endif; ?>
        <?php $rowForm = 'st-row-' . (int)$u['id']; ?>
        <tr class="hover:bg-slate-50">
            <td class="px-4 py-3">
                <form id="<?= $rowForm ?>" method="post" action="<?= base_url('/admin/struktur/user/' . (int)$u['id'] . '/update') ?>" class="hidden"><?= csrf_field() ?></form>
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-white text-[10px] <?= avatar_color((int)$u['id']) ?>"><?= e(initials($u['nama'])) ?></div>
                    <div><p class="font-semibold text-slate-800"><?= e($u['nama']) ?><?= !(int)$u['is_active'] ? ' <span class="text-[9px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded">NONAKTIF</span>' : '' ?></p><p class="text-[11px] text-slate-500"><?= e($u['jabatan']) ?> • <?= e($u['role_nama']) ?></p></div>
                </div>
            </td>
            <td class="px-4 py-2">
                <select name="division_id" form="<?= $rowForm ?>" class="w-40 text-xs bg-slate-50 border border-slate-300 rounded-lg px-2 py-1.5 outline-none focus:border-blue-400">
                    <?php foreach ($divisions as $d): ?>
                        <option value="<?= (int)$d['id'] ?>" <?= (int)$u['division_id'] === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['nama']) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="px-4 py-2 text-center">
                <input type="checkbox" name="is_ketua" form="<?= $rowForm ?>" <?= (int)$u['is_ketua'] ? 'checked' : '' ?> class="w-4 h-4 accent-blue-600" title="Tandai sebagai ketua divisinya">
            </td>
            <td class="px-4 py-2">
                <select name="parent_id" form="<?= $rowForm ?>" class="w-44 text-xs bg-slate-50 border border-slate-300 rounded-lg px-2 py-1.5 outline-none focus:border-blue-400">
                    <option value="0">— ROOT (pimpinan tertinggi) —</option>
                    <?php foreach ($userOptions as $p): if ((int)$p['id'] === (int)$u['id']) continue; ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (int)$u['parent_id'] === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['nama']) ?> (<?= e($p['divisi_nama'] ?? '') ?>)</option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td class="px-4 py-2 text-right">
                <button form="<?= $rowForm ?>" class="bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-semibold px-3 py-1.5 rounded-lg transition"><i class="fa-solid fa-check mr-1"></i>Simpan</button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<!-- ============ PRATINJAU POHON ============ -->
<h2 class="text-sm font-bold text-slate-700 uppercase tracking-wide mb-3"><i class="fa-solid fa-eye text-blue-600 mr-1"></i> Pratinjau Pohon Organisasi</h2>
<div class="w-full overflow-x-auto pb-6">
    <div class="org-tree min-w-max mx-auto mt-2">
        <?php foreach ($trees as $root): ?>
            <ul><?= render_tree_nodes([$root], $me) ?></ul>
        <?php endforeach; ?>
    </div>
</div>

<?php layout_footer(); ?>
