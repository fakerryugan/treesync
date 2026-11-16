<?php
/**
 * views/feed.php
 * Papan Pengumuman — Feed, Tambah/Edit Pengumuman, Detail Pembaca, Suka & Komentar.
 * 
 * @var array $posts
 * @var array $me
 * @var array $targets
 * @var array $visible
 */
?>
<div id="tab-pengumuman" class="tab-pane max-w-2xl mx-auto pb-12">

<!-- ============ FORM TAMBAH PENGUMUMAN ============ -->
<?php if ($targets !== []): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5 mb-6">
        <div class="flex items-center gap-2 mb-3 pb-2 border-b border-slate-100">
            <i class="fa-solid fa-bullhorn text-blue-600"></i>
            <h2 class="text-xs font-bold text-slate-700 uppercase tracking-wider">Publikasikan Pengumuman</h2>
        </div>
        <form method="post" action="<?= base_url('/pengumuman/create') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white shadow-xs flex-shrink-0 <?= avatar_color((int)$me['id']) ?>">
                    <?= e(initials($me['nama'])) ?>
                </div>
                <div class="flex-1 space-y-2.5">
                    <input name="judul" required maxlength="150" placeholder="Judul pengumuman..." class="w-full bg-slate-50 border border-slate-200 focus:border-blue-400 focus:bg-white outline-none rounded-xl py-2 px-3.5 text-sm font-semibold transition">
                    
                    <textarea name="isi" rows="3" required placeholder="Tuliskan isi pengumuman..." class="w-full bg-slate-50 border border-slate-200 focus:border-blue-400 focus:bg-white focus:ring-2 focus:ring-blue-100 outline-none rounded-xl py-2.5 px-3.5 text-sm transition resize-none"></textarea>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2 pt-1">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Target Penayangan</label>
                            <select name="target_division_id" required class="w-full bg-slate-50 border border-slate-200 outline-none rounded-lg py-1.5 px-2.5 text-xs focus:border-blue-400">
                                <?php foreach ($targets as $tid => $tnama): ?>
                                    <option value="<?= $tid ?>"><?= e($tnama) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">URL Gambar (Opsional)</label>
                            <input type="text" name="lampiran_url" placeholder="https://..." class="w-full bg-slate-50 border border-slate-200 outline-none rounded-lg py-1.5 px-2.5 text-xs focus:border-blue-400">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Upload Gambar (Maks 3MB)</label>
                            <input type="file" name="lampiran_file" accept="image/*" class="w-full bg-slate-50 border border-slate-200 outline-none rounded-lg py-1 px-2 text-xs file:mr-2 file:text-[10px] file:px-2 file:py-0.5 file:rounded file:border-0 file:bg-blue-50 file:text-blue-600">
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <span class="text-[11px] text-slate-400"><i class="fa-solid fa-lock text-[9px] mr-1"></i>Hanya yang berwenang dapat mempublikasikan</span>
                        <button class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-xl text-xs font-semibold transition shadow-sm flex items-center gap-1.5">
                            <i class="fa-solid fa-paper-plane text-[10px]"></i> Publikasikan
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="bg-blue-50/80 border border-blue-200 text-blue-700 text-xs rounded-2xl p-4 mb-6 flex items-center gap-3">
        <div class="w-8 h-8 rounded-xl bg-blue-100 flex items-center justify-center flex-shrink-0 text-blue-600">
            <i class="fa-solid fa-bullhorn"></i>
        </div>
        <p class="leading-relaxed">Anda dapat membaca dan berdiskusi pada pengumuman yang ditujukan untuk Anda/divisi Anda. Publikasi pengumuman baru hanya dapat dilakukan oleh akun dengan wewenang khusus.</p>
    </div>
<?php endif; ?>

<!-- ============ DAFTAR PENGUMUMAN ============ -->
<div id="feed-container" class="space-y-6">
<?php if ($posts === []): ?>
    <div class="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-400">
        <i class="fa-regular fa-bell-slash text-4xl mb-3 text-slate-300"></i>
        <p class="text-sm font-medium">Belum ada pengumuman dalam jangkauan Anda.</p>
    </div>
<?php endif; ?>

<?php foreach ($posts as $post): ?>
    <?php
    $comments = feed_build_comments((int)$post['id']);
    $totalTarget = (int)$post['total_target'];
    $totalRead   = (int)$post['total_read'];
    $progressPct = $totalTarget > 0 ? min(100, round(($totalRead / $totalTarget) * 100)) : 100;
    $readByMe    = (int)$post['read_by_me'] === 1;
    $likedByMe   = (int)$post['liked_by_me'] === 1;
    $canEdit     = !empty($post['can_edit']);
    $canViewRdr  = !empty($post['can_view_readers']);
    ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden transition-shadow hover:shadow">
        <!-- Header Pengumuman -->
        <div class="p-5 flex justify-between items-start">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white shadow-xs <?= avatar_color((int)$post['author_id']) ?>">
                    <?= e(initials($post['author_nama'])) ?>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 text-sm leading-tight"><?= e($post['author_nama']) ?></h3>
                    <p class="text-[11px] text-slate-400 mt-0.5"><?= e($post['author_jabatan']) ?> • <?= e(time_ago($post['created_at'])) ?></p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span class="bg-slate-100 text-slate-700 text-[10px] px-2.5 py-1 rounded-full border border-slate-200 font-semibold flex items-center gap-1">
                    <i class="fa-solid <?= $post['target_division_id'] ? 'fa-building text-blue-500' : 'fa-globe text-emerald-500' ?> text-[9px]"></i>
                    <?= $post['target_nama'] ? e($post['target_nama']) : 'Seluruh Organisasi' ?>
                </span>

                <?php if ($canEdit): ?>
                    <div class="flex items-center gap-1 ml-1">
                        <button type="button"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode([
                                    'id'                 => (int)$post['id'],
                                    'judul'              => $post['judul'],
                                    'isi'                => $post['isi'],
                                    'target_division_id' => (int)$post['target_division_id'],
                                    'lampiran'           => $post['lampiran'],
                                ]), ENT_QUOTES, 'UTF-8') ?>)"
                                class="w-7 h-7 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 flex items-center justify-center transition"
                                title="Edit Pengumuman">
                            <i class="fa-solid fa-pen-to-square text-xs"></i>
                        </button>
                        <form method="post" action="<?= base_url('/pengumuman/' . (int)$post['id'] . '/delete') ?>" onsubmit="return confirm('Apakah Anda yakin ingin menghapus pengumuman ini?')" class="inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="w-7 h-7 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 flex items-center justify-center transition" title="Hapus Pengumuman">
                                <i class="fa-solid fa-trash-can text-xs"></i>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Konten Pengumuman -->
        <div class="px-5 pb-4">
            <h4 class="font-bold text-slate-800 mb-2 text-base"><?= e($post['judul']) ?></h4>
            <p class="text-slate-700 text-sm leading-relaxed mb-4 whitespace-pre-line"><?= e($post['isi']) ?></p>
            <?php if ($post['lampiran']): ?>
                <div class="rounded-xl overflow-hidden border border-slate-100 shadow-xs mb-2">
                    <img src="<?= e($post['lampiran']) ?>" alt="Lampiran" class="w-full h-auto object-cover max-h-80" loading="lazy">
                </div>
            <?php endif; ?>
        </div>

        <!-- Bar Keterbacaan -->
        <div class="bg-slate-50/90 px-5 py-3 border-y border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex-1 max-w-sm">
                <div class="flex justify-between items-center text-[11px] mb-1">
                    <span class="text-slate-600 font-medium">Progres Keterbacaan</span>
                    <div class="flex items-center gap-1.5">
                        <span class="text-blue-600 font-bold"><?= $totalRead ?> / <?= $totalTarget ?></span>
                        <span class="text-slate-400">(<?= $progressPct ?>%)</span>
                    </div>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                    <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" style="width: <?= $progressPct ?>%"></div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <?php if ($canViewRdr): ?>
                    <button type="button"
                            onclick="openReadersModal(<?= (int)$post['id'] ?>)"
                            class="px-3 py-1.5 bg-white hover:bg-blue-50 text-blue-600 border border-blue-200 hover:border-blue-300 rounded-lg text-xs font-semibold transition flex items-center gap-1.5 shadow-xs">
                        <i class="fa-solid fa-users-viewfinder text-xs"></i> Detail Pembaca
                    </button>
                <?php endif; ?>

                <form method="post" action="<?= base_url('/pengumuman/' . (int)$post['id'] . '/read') ?>">
                    <?= csrf_field() ?>
                    <button <?= $readByMe ? 'disabled' : '' ?> class="px-3.5 py-1.5 <?= $readByMe ? 'bg-slate-100 text-slate-400 border-slate-200 cursor-not-allowed' : 'bg-emerald-50 text-emerald-700 border-emerald-300 hover:bg-emerald-100' ?> border rounded-lg text-xs font-semibold transition flex items-center gap-1.5 shadow-xs">
                        <i class="<?= $readByMe ? 'fa-solid fa-check-double text-emerald-600' : 'fa-regular fa-circle-check' ?>"></i>
                        <?= $readByMe ? 'Sudah Dibaca' : 'Tandai Dibaca' ?>
                    </button>
                </form>
            </div>
        </div>

        <!-- Tombol Suka & Jumlah Komentar -->
        <div class="px-5 py-2.5 flex items-center gap-6 text-xs text-slate-500 border-b border-slate-100">
            <form method="post" action="<?= base_url('/pengumuman/' . (int)$post['id'] . '/like') ?>">
                <?= csrf_field() ?>
                <button class="flex items-center gap-1.5 hover:text-blue-600 transition font-medium <?= $likedByMe ? 'text-blue-600 font-bold' : '' ?>">
                    <i class="<?= $likedByMe ? 'fa-solid text-blue-600' : 'fa-regular' ?> fa-thumbs-up text-sm"></i>
                    <span><?= (int)$post['total_likes'] ?> Suka</span>
                </button>
            </form>
            <span class="flex items-center gap-1.5 font-medium">
                <i class="fa-regular fa-comment text-sm"></i>
                <span><?= (int)$post['total_comments'] ?> Komentar</span>
            </span>
        </div>

        <!-- Diskusi / Komentar -->
        <div class="p-5 bg-white">
            <div class="space-y-3.5 mb-4">
                <?php foreach ($comments as $c): ?>
                    <?php $ca = avatar_color((int)$c['author_id']); ?>
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 flex-shrink-0 rounded-full flex items-center justify-center font-bold text-white text-[10px] <?= $ca ?> shadow-xs">
                            <?= e(initials($c['author_nama'])) ?>
                        </div>
                        <div class="flex-1">
                            <div class="bg-slate-100/80 px-4 py-2.5 rounded-2xl rounded-tl-none inline-block max-w-[95%]">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-xs text-slate-800"><?= e($c['author_nama']) ?></span>
                                    <?php if (!empty($c['divisi_nama'])): ?>
                                        <span class="text-[9px] text-slate-400">· <?= e($c['divisi_nama']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-slate-700 mt-1 whitespace-pre-line"><?= e($c['isi']) ?></p>
                            </div>
                            <div class="flex items-center text-[10px] text-slate-400 mt-1 ml-2 space-x-3 font-semibold">
                                <button onclick="showReplyPrompt(<?= (int)$post['id'] ?>, <?= (int)$c['id'] ?>, <?= htmlspecialchars(json_encode($c['author_nama']), ENT_QUOTES, 'UTF-8') ?>)" class="text-blue-600 hover:underline">Balas</button>
                                <span class="font-normal"><?= e(time_ago($c['created_at'])) ?></span>
                            </div>

                            <!-- Balasan komentar -->
                            <?php if (!empty($c['replies'])): ?>
                                <div class="mt-2.5 space-y-2 border-l-2 border-slate-200 pl-3">
                                    <?php foreach ($c['replies'] as $r): ?>
                                        <div class="flex items-start gap-2">
                                            <div class="w-6 h-6 flex-shrink-0 rounded-full flex items-center justify-center font-bold text-white text-[8px] <?= avatar_color((int)$r['author_id']) ?>">
                                                <?= e(initials($r['author_nama'])) ?>
                                            </div>
                                            <div class="flex-1">
                                                <div class="bg-slate-50 border border-slate-100 px-3 py-1.5 rounded-xl rounded-tl-none inline-block">
                                                    <span class="font-bold text-[11px] text-slate-800"><?= e($r['author_nama']) ?></span>
                                                    <p class="text-xs text-slate-700 mt-0.5 whitespace-pre-line"><?= e($r['isi']) ?></p>
                                                </div>
                                                <p class="text-[9px] text-slate-400 ml-2 mt-0.5"><?= e(time_ago($r['created_at'])) ?></p>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Form Tulis Komentar -->
            <form method="post" action="<?= base_url('/pengumuman/' . (int)$post['id'] . '/comment') ?>" class="flex items-center gap-2 pt-2 border-t border-slate-100">
                <?= csrf_field() ?>
                <input type="hidden" name="parent_id" value="0">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-white text-[10px] flex-shrink-0 <?= avatar_color((int)$me['id']) ?>">
                    <?= e(initials($me['nama'])) ?>
                </div>
                <input type="text" name="isi" required placeholder="Tulis komentar... (otomatis menandai sudah membaca)" class="flex-1 bg-slate-50 border border-slate-200 focus:border-blue-400 focus:bg-white outline-none rounded-xl py-2 px-3 text-xs transition">
                <button class="w-8 h-8 flex items-center justify-center bg-blue-600 hover:bg-blue-700 text-white rounded-full transition shadow-xs flex-shrink-0" title="Kirim komentar">
                    <i class="fa-solid fa-paper-plane text-xs"></i>
                </button>
            </form>
        </div>
    </div>
<?php endforeach; ?>
</div>

</div>

<!-- ============ MODAL EDIT PENGUMUMAN ============ -->
<div id="edit-ann-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] hidden items-center justify-center opacity-0 transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-hidden flex flex-col transform scale-95 transition-transform mx-4" id="edit-ann-content">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <h3 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-pen-to-square text-blue-600"></i> Edit Pengumuman
            </h3>
            <button onclick="closeEditModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form id="edit-ann-form" method="post" action="" enctype="multipart/form-data" class="p-6 overflow-y-auto space-y-4 flex-1 text-xs">
            <?= csrf_field() ?>
            <div>
                <label class="block font-bold text-slate-600 uppercase tracking-wider text-[10px] mb-1">Judul Pengumuman</label>
                <input type="text" name="judul" id="edit-judul" required maxlength="150" class="w-full bg-slate-50 border border-slate-200 focus:border-blue-400 focus:bg-white outline-none rounded-xl py-2 px-3 text-xs font-semibold transition">
            </div>

            <div>
                <label class="block font-bold text-slate-600 uppercase tracking-wider text-[10px] mb-1">Isi Pengumuman</label>
                <textarea name="isi" id="edit-isi" rows="4" required class="w-full bg-slate-50 border border-slate-200 focus:border-blue-400 focus:bg-white outline-none rounded-xl p-3 text-xs transition resize-none"></textarea>
            </div>

            <div>
                <label class="block font-bold text-slate-600 uppercase tracking-wider text-[10px] mb-1">Target Penayangan</label>
                <select name="target_division_id" id="edit-target" class="w-full bg-slate-50 border border-slate-200 outline-none rounded-xl py-2 px-3 text-xs focus:border-blue-400">
                    <?php foreach ($targets as $tid => $tnama): ?>
                        <option value="<?= $tid ?>"><?= e($tnama) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="edit-lampiran-preview" class="hidden p-3 bg-slate-50 rounded-xl border border-slate-200">
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Lampiran Saat Ini</p>
                <img id="edit-lampiran-img" src="" alt="Lampiran" class="max-h-36 rounded-lg object-cover mb-2 border border-slate-200">
                <label class="flex items-center gap-2 text-slate-600 cursor-pointer text-xs">
                    <input type="checkbox" name="hapus_lampiran" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span>Hapus lampiran gambar ini</span>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-slate-600 uppercase tracking-wider text-[10px] mb-1">Ganti URL Gambar</label>
                    <input type="text" name="lampiran_url" id="edit-url" placeholder="https://..." class="w-full bg-slate-50 border border-slate-200 outline-none rounded-xl py-2 px-3 text-xs focus:border-blue-400">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 uppercase tracking-wider text-[10px] mb-1">Ganti File Gambar (Maks 3MB)</label>
                    <input type="file" name="lampiran_file" accept="image/*" class="w-full bg-slate-50 border border-slate-200 outline-none rounded-xl py-1 px-2 text-xs file:mr-2 file:text-[10px] file:px-2 file:py-0.5 file:rounded file:border-0 file:bg-blue-50 file:text-blue-600">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-end gap-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-xl transition">Batal</button>
                <button type="submit" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition shadow-xs flex items-center gap-1.5">
                    <i class="fa-solid fa-check"></i> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ============ MODAL DETAIL PEMBACA (SUDAH & BELUM BACA) ============ -->
<div id="readers-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] hidden items-center justify-center opacity-0 transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[88vh] overflow-hidden flex flex-col transform scale-95 transition-transform mx-4" id="readers-content">
        <!-- Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <div>
                <h3 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-users-viewfinder text-blue-600"></i> Detail Keterbacaan Pengumuman
                </h3>
                <p class="text-xs text-slate-400 mt-0.5 truncate max-w-md" id="readers-post-title">Memuat...</p>
            </div>
            <button onclick="closeReadersModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Filter & Tab Navigation -->
        <div class="px-6 pt-3 pb-2 border-b border-slate-100 bg-white space-y-3">
            <div class="flex items-center gap-2">
                <button type="button" id="tab-btn-read" onclick="switchReaderTab('read')" class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-300">
                    <i class="fa-solid fa-circle-check text-emerald-500"></i>
                    <span>Sudah Membaca</span>
                    <span id="badge-count-read" class="ml-1 text-[10px] bg-emerald-200/80 px-1.5 py-0.2 rounded-full">0</span>
                </button>
                <button type="button" id="tab-btn-unread" onclick="switchReaderTab('unread')" class="flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 bg-slate-100 text-slate-600 border border-slate-200">
                    <i class="fa-solid fa-circle-exclamation text-amber-500"></i>
                    <span>Belum Membaca</span>
                    <span id="badge-count-unread" class="ml-1 text-[10px] bg-slate-200 px-1.5 py-0.2 rounded-full">0</span>
                </button>
            </div>
            <div>
                <input type="text" id="readers-search" oninput="filterReadersList()" placeholder="Cari nama atau jabatan..." class="w-full bg-slate-50 border border-slate-200 focus:border-blue-400 outline-none rounded-xl py-1.5 px-3 text-xs transition">
            </div>
        </div>

        <!-- Body List -->
        <div class="p-6 overflow-y-auto space-y-2 flex-1 max-h-96" id="readers-list-body">
            <div class="py-8 text-center text-slate-400 text-xs">Memuat data pembaca...</div>
        </div>

        <!-- Footer -->
        <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
            <span id="readers-summary-text">Target: 0 Personil</span>
            <button onclick="closeReadersModal()" class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold rounded-lg transition">Tutup</button>
        </div>
    </div>
</div>

<script>
let CURRENT_READERS_DATA = null;
let CURRENT_TAB = 'read';

function initials(name) {
    if (!name) return 'U';
    const parts = name.trim().split(/\s+/);
    const i1 = (parts[0] || 'U').substring(0, 1).toUpperCase();
    const i2 = parts[1] ? parts[1].substring(0, 1).toUpperCase() : '';
    return i1 + i2;
}

function avatarColor(id) {
    const palette = ['bg-blue-500','bg-emerald-500','bg-purple-500','bg-rose-500','bg-amber-500','bg-cyan-600','bg-indigo-500','bg-teal-500'];
    return palette[Math.abs(Number(id || 0)) % palette.length];
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

/* ============ EDIT PENGUMUMAN ============ */
function openEditModal(post) {
    const modal = document.getElementById('edit-ann-modal');
    const form = document.getElementById('edit-ann-form');
    form.action = `${BASE}/pengumuman/${post.id}/update`;

    document.getElementById('edit-judul').value = post.judul || '';
    document.getElementById('edit-isi').value = post.isi || '';
    document.getElementById('edit-target').value = post.target_division_id || 0;
    document.getElementById('edit-url').value = '';

    const previewBox = document.getElementById('edit-lampiran-preview');
    const previewImg = document.getElementById('edit-lampiran-img');
    if (post.lampiran) {
        previewImg.src = post.lampiran;
        previewBox.classList.remove('hidden');
    } else {
        previewBox.classList.add('hidden');
    }

    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        document.getElementById('edit-ann-content').classList.remove('scale-95');
        document.getElementById('edit-ann-content').classList.add('scale-100');
    });
}

function closeEditModal() {
    const modal = document.getElementById('edit-ann-modal');
    if (!modal || modal.classList.contains('hidden')) return;

    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    document.getElementById('edit-ann-content').classList.remove('scale-100');
    document.getElementById('edit-ann-content').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 200);
}

/* ============ DETAIL PEMBACA ============ */
function openReadersModal(postId) {
    const modal = document.getElementById('readers-modal');
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    document.getElementById('readers-post-title').textContent = 'Memuat rincian...';
    document.getElementById('readers-list-body').innerHTML = '<div class="py-8 text-center text-slate-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1.5"></i>Memuat data pembaca...</div>';
    document.getElementById('readers-search').value = '';

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        document.getElementById('readers-content').classList.remove('scale-95');
        document.getElementById('readers-content').classList.add('scale-100');
    });

    fetch(`${BASE}/api/pengumuman/${postId}/readers`)
        .then(res => res.json())
        .then(data => {
            if (!data.ok) {
                document.getElementById('readers-list-body').innerHTML = `<div class="py-8 text-center text-red-500 text-xs">${escapeHtml(data.error || 'Gagal memuat data')}</div>`;
                return;
            }
            CURRENT_READERS_DATA = data;
            document.getElementById('readers-post-title').textContent = `${data.judul} (${data.target})`;
            document.getElementById('badge-count-read').textContent = data.total_read;
            document.getElementById('badge-count-unread').textContent = data.total_unread;
            document.getElementById('readers-summary-text').textContent = `Target: ${data.total_target} Personil (${data.total_read} dibaca / ${data.total_unread} belum)`;
            switchReaderTab('read');
        })
        .catch(err => {
            document.getElementById('readers-list-body').innerHTML = '<div class="py-8 text-center text-red-500 text-xs">Terjadi kesalahan koneksi.</div>';
        });
}

function switchReaderTab(tab) {
    CURRENT_TAB = tab;
    const btnRead = document.getElementById('tab-btn-read');
    const btnUnread = document.getElementById('tab-btn-unread');

    if (tab === 'read') {
        btnRead.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-300';
        btnUnread.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 bg-slate-100 text-slate-600 border border-slate-200';
    } else {
        btnUnread.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 bg-amber-50 text-amber-800 border border-amber-300';
        btnRead.className = 'flex-1 py-2 px-3 rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 bg-slate-100 text-slate-600 border border-slate-200';
    }

    filterReadersList();
}

function filterReadersList() {
    if (!CURRENT_READERS_DATA) return;
    const query = (document.getElementById('readers-search').value || '').toLowerCase().trim();
    const list = CURRENT_TAB === 'read' ? CURRENT_READERS_DATA.read : CURRENT_READERS_DATA.unread;

    const filtered = list.filter(item => {
        return (item.nama || '').toLowerCase().includes(query)
            || (item.jabatan || '').toLowerCase().includes(query)
            || (item.divisi || '').toLowerCase().includes(query);
    });

    const body = document.getElementById('readers-list-body');
    if (filtered.length === 0) {
        body.innerHTML = `<p class="py-8 text-center text-slate-400 text-xs italic">${CURRENT_TAB === 'read' ? 'Tidak ada pembaca yang cocok.' : 'Semua personil yang dicari sudah membaca atau tidak ditemukan.'}</p>`;
        return;
    }

    body.innerHTML = filtered.map(item => `
        <div class="flex items-center justify-between p-2.5 rounded-xl border ${CURRENT_TAB === 'read' ? 'bg-emerald-50/30 border-emerald-100' : 'bg-slate-50 border-slate-200'}">
            <div class="flex items-center gap-2.5 min-w-0">
                <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-white text-[10px] ${avatarColor(item.id)} flex-shrink-0">
                    ${initials(item.nama)}
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-slate-800 text-xs truncate">${escapeHtml(item.nama)}</p>
                    <p class="text-[11px] text-slate-500 truncate">${escapeHtml(item.jabatan)} • ${escapeHtml(item.divisi)}</p>
                </div>
            </div>
            <div class="text-right flex-shrink-0 ml-3">
                ${CURRENT_TAB === 'read'
                    ? `<span class="text-[10px] text-emerald-700 font-semibold bg-emerald-100/70 border border-emerald-200 px-2 py-0.5 rounded-full inline-flex items-center gap-1"><i class="fa-solid fa-check text-[8px]"></i> ${escapeHtml(item.read_at)}</span>`
                    : `<span class="text-[10px] text-amber-700 font-semibold bg-amber-100/70 border border-amber-200 px-2 py-0.5 rounded-full inline-flex items-center gap-1"><i class="fa-solid fa-clock text-[8px]"></i> Belum Baca</span>`
                }
            </div>
        </div>
    `).join('');
}

function closeReadersModal() {
    const modal = document.getElementById('readers-modal');
    if (!modal || modal.classList.contains('hidden')) return;

    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    document.getElementById('readers-content').classList.remove('scale-100');
    document.getElementById('readers-content').classList.add('scale-95');
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 200);
}

// Tutup modal jika klik di backdrop atau tekan Escape
document.addEventListener('click', function(e) {
    const editModal = document.getElementById('edit-ann-modal');
    const rdrModal = document.getElementById('readers-modal');
    if (e.target === editModal) closeEditModal();
    if (e.target === rdrModal) closeReadersModal();
});
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
        closeReadersModal();
    }
});

/* ============ BALAS KOMENTAR ============ */
function showReplyPrompt(postId, commentId, authorName) {
    openModal(`Balas Komentar ${escapeHtml(authorName)}`,
        `<form id="reply-form" method="post" action="${BASE}/pengumuman/${postId}/comment" class="space-y-3">
            <input type="hidden" name="_csrf" value="${CSRF}">
            <input type="hidden" name="parent_id" value="${commentId}">
            <p class="text-xs text-slate-500">Menulis balasan otomatis menandai pengumuman ini sudah Anda baca.</p>
            <textarea name="isi" rows="3" required class="w-full bg-slate-50 border border-slate-300 focus:border-blue-400 outline-none rounded-xl p-3 text-xs" placeholder="Tulis balasan..."></textarea>
        </form>`,
        () => document.getElementById('reply-form').submit());
}
</script>

<?php layout_footer(); ?>
