<?php /** $divisions, $me */ ?>
<div class="flex justify-between items-center mb-6">
    <p class="text-slate-500 text-sm">Divisi menjadi unit pengelompokan pengumuman, whiteboard, dan keanggotaan pohon organisasi.</p>
    <button onclick="showDivisionModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
        <i class="fa-solid fa-plus mr-2"></i> Tambah Divisi
    </button>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
<?php foreach ($divisions as $d): ?>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
        <div class="flex items-start justify-between mb-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-building-user"></i></div>
            <div class="flex gap-2">
                <button onclick='showDivisionModal(<?= json_encode(['id' => (int)$d['id'], 'nama' => $d['nama'], 'kode' => $d['kode']], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' class="text-blue-600 hover:text-blue-800 text-xs font-medium"><i class="fa-solid fa-pen mr-1"></i>Edit</button>
                <form method="post" action="<?= base_url('/admin/divisi/' . (int)$d['id'] . '/delete') ?>" onsubmit="return confirm('Hapus divisi ini?')">
                    <?= csrf_field() ?>
                    <button class="text-red-600 hover:text-red-800 text-xs font-medium"><i class="fa-solid fa-trash mr-1"></i>Hapus</button>
                </form>
            </div>
        </div>
        <h3 class="font-bold text-slate-800 text-sm"><?= e($d['nama']) ?></h3>
        <p class="text-[10px] text-slate-400 mb-3">Kode: <?= e($d['kode']) ?></p>
        <div class="flex gap-2 text-[10px]">
            <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded-full"><?= (int)$d['member_count'] ?> anggota aktif</span>
            <span class="bg-slate-100 text-slate-600 px-2 py-1 rounded-full"><?= (int)$d['note_count'] ?> catatan</span>
        </div>
    </div>
<?php endforeach; ?>
</div>

<script>
function showDivisionModal(d) {
    const isEdit = !!d;
    openModal(isEdit ? 'Edit Divisi' : 'Tambah Divisi', `
        <form id="div-form" method="post" action="${BASE}/admin/divisi${isEdit ? '/' + d.id + '/update' : '/store'}">
            <input type="hidden" name="_csrf" value="${CSRF}">
            <div class="space-y-3 text-sm">
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Nama Divisi *</label>
                    <input name="nama" required value="${isEdit ? d.nama : ''}" class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2"></div>
                <div><label class="text-[10px] font-bold text-slate-500 uppercase">Kode (2-20 huruf/angka) *</label>
                    <input name="kode" required pattern="[A-Za-z0-9]{2,20}" value="${isEdit ? d.kode : ''}" class="w-full mt-1 border border-slate-300 rounded-lg px-3 py-2 uppercase"></div>
            </div>
        </form>`,
        () => document.getElementById('div-form').submit());
}
</script>

<?php layout_footer(); ?>
