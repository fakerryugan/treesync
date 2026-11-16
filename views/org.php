<?php
/**
 * views/org.php
 * Struktur Organisasi — Bagan Pohon + Modal Interaktif Kotak Divisi (Ketua & Anggota).
 * 
 * @var array $trees
 * @var array $me
 * @var array $divisions
 * @var array $byDivision
 * @var array $atasanOptions
 * @var bool  $canManageGlobal
 * @var bool  $isKetua
 * @var array $editableDivisions
 * @var array $candidateUsers
 */

// Siapkan data JSON untuk modal interaktif divisi
$divisionsJsonData = [];
foreach ($divisions as $d) {
    $dId = (int)$d['id'];
    $mList = $byDivision[$dId] ?? [];
    $kList = array_values(array_filter($mList, fn($m) => (int)$m['is_ketua'] === 1));
    $aList = array_values(array_filter($mList, fn($m) => (int)$m['is_ketua'] !== 1));

    $divisionsJsonData[$dId] = [
        'id'          => $dId,
        'nama'        => $d['nama'],
        'kode'        => $d['kode'],
        'canEdit'     => !empty($editableDivisions[$dId]),
        'ketua'       => array_map(fn($k) => [
            'id'       => (int)$k['id'],
            'nama'     => $k['nama'],
            'jabatan'  => $k['jabatan'],
            'username' => $k['username'],
        ], $kList),
        'anggota'     => array_map(fn($a) => [
            'id'        => (int)$a['id'],
            'nama'      => $a['nama'],
            'jabatan'   => $a['jabatan'],
            'username'  => $a['username'],
            'is_active' => (int)$a['is_active'],
        ], $aList),
    ];
}

$candidateJsonData = array_map(fn($u) => [
    'id'          => (int)$u['id'],
    'nama'        => $u['nama'],
    'jabatan'     => $u['jabatan'],
    'division_id' => $u['division_id'] !== null ? (int)$u['division_id'] : null,
    'divisi_nama' => $u['divisi_nama'] ?? null,
], $candidateUsers);

$myDivisionName = $me['divisi_nama'] ?? null;
?>

<!-- ============ HEADER & LEGENDA WARNA ============ -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <div>
        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
            <i class="fa-solid fa-sitemap text-blue-600"></i> Struktur Organisasi
        </h2>
        <p class="text-xs text-slate-500 mt-0.5">
            Menampilkan seluruh divisi organisasi. Klik kotak node untuk langsung melihat siapa ketua &amp; anggotanya.
        </p>
    </div>
    <div class="flex items-center gap-2.5 flex-wrap text-xs">
        <?php if (!empty($myDivisionName)): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-orange-100 text-orange-800 font-bold border border-orange-300 shadow-xs">
                <i class="fa-solid fa-circle text-orange-500 text-[8px]"></i> Divisi Anda: <?= e($myDivisionName) ?>
            </span>
        <?php endif; ?>
        <span class="inline-flex items-center gap-1.5 text-slate-600 font-medium px-2 py-0.5 rounded bg-slate-100">
            <i class="fa-solid fa-circle text-blue-500 text-[8px]"></i> Ketua
        </span>
        <span class="inline-flex items-center gap-1.5 text-slate-600 font-medium px-2 py-0.5 rounded bg-slate-100">
            <i class="fa-solid fa-circle text-slate-400 text-[8px]"></i> Anggota
        </span>
        <?php if ($canManageGlobal): ?>
            <a href="<?= base_url('/admin/struktur') ?>" class="text-xs text-blue-600 hover:text-blue-800 font-semibold inline-flex items-center gap-1 transition ml-1">
                <i class="fa-solid fa-sliders"></i> Panel Admin
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="w-full overflow-x-auto pb-10 bg-slate-50/50 rounded-2xl border border-slate-200/80 p-4 mb-8">
    <div class="org-tree min-w-max mx-auto" id="tree-container">
        <?php if ($trees === []): ?>
            <p class="text-slate-400 text-sm py-8 text-center">Tidak ada struktur organisasi yang dapat ditampilkan.</p>
        <?php endif; ?>
        <?php foreach ($trees as $root): ?>
            <ul><?= render_tree_nodes([$root], $me) ?></ul>
        <?php endforeach; ?>
    </div>
</div>

<!-- ============ MODAL KOTAK DIVISI (KETUA & ANGGOTA) ============ -->
<div id="division-modal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[100] hidden items-center justify-center opacity-0 transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[88vh] overflow-hidden flex flex-col transform scale-95 transition-transform mx-4" id="division-modal-content">
        <!-- Header Modal -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/70">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-building-user"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2" id="div-modal-title">
                        <!-- Judul divisi di-inject via JS -->
                    </div>
                    <p class="text-xs text-slate-400 mt-0.5" id="div-modal-subtitle">Detail Personil</p>
                </div>
            </div>
            <button onclick="closeDivisionModal()" class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- Body Modal (Scrollable) -->
        <div class="p-6 overflow-y-auto space-y-5 flex-1" id="div-modal-body">
            <!-- Konten dinamis di-inject via JS -->
        </div>

        <!-- Footer Modal -->
        <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 flex justify-end">
            <button onclick="closeDivisionModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs font-semibold rounded-lg transition">Tutup</button>
        </div>
    </div>
</div>

<script>
const DIVISIONS_DATA  = <?= json_encode($divisionsJsonData, JSON_UNESCAPED_UNICODE) ?>;
const CANDIDATE_USERS = <?= json_encode($candidateJsonData, JSON_UNESCAPED_UNICODE) ?>;
const CSRF_FIELD      = <?= json_encode(csrf_field(), JSON_UNESCAPED_UNICODE) ?>;
const BASE_URL        = <?= json_encode(rtrim(base_url(), '/'), JSON_UNESCAPED_UNICODE) ?>;

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

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

function openDivisionModal(dId, highlightUserId = null) {
    const d = DIVISIONS_DATA[dId];
    if (!d) return;

    // Pastikan elemen modal berada langsung di document.body agar tidak terpotong container overflow
    const modal = document.getElementById('division-modal');
    if (modal && modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    // Set judul header modal
    const titleEl = document.getElementById('div-modal-title');
    titleEl.innerHTML = `
        <h3 class="font-bold text-base text-slate-800">${escapeHtml(d.nama)}</h3>
        <span class="text-[11px] font-mono font-semibold px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">${escapeHtml(d.kode)}</span>
        ${d.canEdit ? '<span class="text-[10px] bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold px-2 py-0.5 rounded ml-2 inline-flex items-center gap-1"><i class="fa-solid fa-pen-to-square"></i> Akses Edit Aktif</span>' : '<span class="text-[10px] bg-slate-100 text-slate-500 font-medium px-2 py-0.5 rounded ml-2">Mode Baca</span>'}
    `;

    const totalCount = (d.ketua ? d.ketua.length : 0) + (d.anggota ? d.anggota.length : 0);
    document.getElementById('div-modal-subtitle').textContent = `Total ${totalCount} personil di divisi ini`;

    // Render body
    const bodyEl = document.getElementById('div-modal-body');
    let html = '';

    // ================= 1. KETUA DIVISI =================
    html += `
        <div class="bg-slate-50/70 border border-slate-200/80 rounded-xl p-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-xs font-bold text-blue-800 uppercase tracking-wide flex items-center gap-1.5">
                    <i class="fa-solid fa-user-tie text-blue-600"></i> Ketua Divisi
                </h4>
            </div>`;

    if (d.ketua && d.ketua.length > 0) {
        html += `<div class="space-y-2">`;
        d.ketua.forEach(k => {
            const isHighlighted = highlightUserId && k.id === highlightUserId;
            html += `
                <div class="flex items-center justify-between p-3.5 bg-white border ${isHighlighted ? 'border-blue-500 ring-2 ring-blue-300 bg-blue-50/40' : 'border-blue-200/90'} rounded-xl shadow-xs transition">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white text-xs ${avatarColor(k.id)} flex-shrink-0 shadow-sm">
                            ${initials(k.nama)}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-bold text-slate-800 text-sm truncate">${escapeHtml(k.nama)}</p>
                                <span class="text-[9px] font-bold bg-blue-600 text-white px-1.5 py-0.5 rounded tracking-wide flex-shrink-0">KETUA</span>
                                ${isHighlighted ? '<span class="text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-300 px-1.5 py-0.5 rounded flex-shrink-0"><i class="fa-solid fa-check mr-0.5"></i>Dipilih di Bagan</span>' : ''}
                            </div>
                            <p class="text-xs text-slate-500 truncate">${escapeHtml(k.jabatan)} • @${escapeHtml(k.username)}</p>
                        </div>
                    </div>
                    ${d.canEdit ? `
                        <form method="post" action="${BASE_URL}/struktur/division/${d.id}/ketua/${k.id}/unset" onsubmit="return confirm('Copot jabatan ketua dari ${escapeHtml(k.nama)}? Pengguna ini akan tetap berada di divisi ini sebagai anggota biasa.')" class="ml-3 flex-shrink-0">
                            ${CSRF_FIELD}
                            <button type="submit" class="text-xs font-semibold text-amber-700 hover:text-amber-900 bg-amber-50 hover:bg-amber-100 border border-amber-200 rounded-lg px-2.5 py-1.5 transition flex items-center gap-1" title="Copot status ketua">
                                <i class="fa-solid fa-user-xmark text-[11px]"></i> Copot Ketua
                            </button>
                        </form>
                    ` : ''}
                </div>`;
        });
        html += `</div>`;
    } else {
        html += `
            <div class="p-3 bg-white border border-dashed border-amber-300 rounded-xl">
                <p class="text-xs text-amber-700 italic"><i class="fa-solid fa-circle-exclamation mr-1"></i>Belum ada ketua yang ditetapkan untuk divisi ini.</p>
                ${d.canEdit ? `
                    <form method="post" action="${BASE_URL}/struktur/division/${d.id}/ketua/set" class="flex gap-2 mt-2.5">
                        ${CSRF_FIELD}
                        <select name="user_id" required class="flex-1 text-xs bg-slate-50 border border-slate-300 rounded-lg px-2.5 py-1.5 outline-none focus:border-blue-400">
                            <option value="">— Pilih personil untuk dijadikan Ketua —</option>
                            ${CANDIDATE_USERS.map(u => `
                                <option value="${u.id}">${escapeHtml(u.nama)} (${u.divisi_nama ? escapeHtml(u.divisi_nama) : 'tanpa divisi'}) — ${escapeHtml(u.jabatan)}</option>
                            `).join('')}
                        </select>
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition whitespace-nowrap flex items-center gap-1 shadow-sm">
                            <i class="fa-solid fa-crown text-[10px]"></i> Tetapkan Ketua
                        </button>
                    </form>
                ` : ''}
            </div>`;
    }
    html += `</div>`;

    // ================= 2. ANGGOTA DIVISI =================
    html += `
        <div class="bg-slate-50/70 border border-slate-200/80 rounded-xl p-4">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-xs font-bold text-slate-700 uppercase tracking-wide flex items-center gap-1.5">
                    <i class="fa-solid fa-user-group text-slate-600"></i> Daftar Anggota (${d.anggota ? d.anggota.length : 0} orang)
                </h4>
            </div>`;

    if (d.anggota && d.anggota.length > 0) {
        html += `<div class="space-y-2 max-h-64 overflow-y-auto pr-1">`;
        d.anggota.forEach(m => {
            const isHighlighted = highlightUserId && m.id === highlightUserId;
            html += `
                <div class="flex items-center justify-between p-3 bg-white hover:bg-slate-50 border ${isHighlighted ? 'border-blue-500 ring-2 ring-blue-300 bg-blue-50/30' : 'border-slate-200/80'} rounded-xl shadow-xs transition">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white text-[11px] ${avatarColor(m.id)} flex-shrink-0">
                            ${initials(m.nama)}
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-bold text-slate-800 text-xs truncate">
                                    ${escapeHtml(m.nama)}
                                </p>
                                <span class="text-[9px] font-bold bg-slate-100 text-slate-600 border border-slate-200 px-1.5 py-0.5 rounded">ANGGOTA</span>
                                ${isHighlighted ? '<span class="text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-300 px-1.5 py-0.5 rounded flex-shrink-0"><i class="fa-solid fa-check mr-0.5"></i>Dipilih di Bagan</span>' : ''}
                                ${!m.is_active ? '<span class="text-[9px] bg-red-100 text-red-600 px-1 py-0.5 rounded ml-1">NONAKTIF</span>' : ''}
                            </div>
                            <p class="text-[11px] text-slate-500 truncate mt-0.5">${escapeHtml(m.jabatan)} • @${escapeHtml(m.username)}</p>
                        </div>
                    </div>
                    ${d.canEdit ? `
                        <div class="flex items-center gap-1.5 ml-2 flex-shrink-0">
                            <form method="post" action="${BASE_URL}/struktur/division/${d.id}/ketua/set" onsubmit="return confirm('Tetapkan ${escapeHtml(m.nama)} sebagai Ketua ${escapeHtml(d.nama)}?')" class="inline">
                                ${CSRF_FIELD}
                                <input type="hidden" name="user_id" value="${m.id}">
                                <button type="submit" class="text-[11px] font-semibold text-blue-700 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg px-2.5 py-1.5 transition flex items-center gap-1" title="Angkat menjadi ketua divisi">
                                    <i class="fa-solid fa-crown text-[10px]"></i> Jadikan Ketua
                                </button>
                            </form>
                            <form method="post" action="${BASE_URL}/struktur/division/${d.id}/member/${m.id}/remove" onsubmit="return confirm('Keluarkan ${escapeHtml(m.nama)} dari divisi ${escapeHtml(d.nama)}?')" class="inline">
                                ${CSRF_FIELD}
                                <button type="submit" class="text-[11px] font-semibold text-red-600 hover:text-red-800 bg-red-50 hover:bg-red-100 border border-red-200 rounded-lg px-2.5 py-1.5 transition flex items-center gap-1" title="Keluarkan dari divisi ini">
                                    <i class="fa-solid fa-user-minus text-[10px]"></i> Keluarkan
                                </button>
                            </form>
                        </div>
                    ` : ''}
                </div>`;
        });
        html += `</div>`;
    } else {
        html += `<p class="text-xs text-slate-400 italic p-3 bg-white border border-dashed border-slate-200 rounded-xl text-center">Belum ada anggota staf di divisi ini.</p>`;
    }
    html += `</div>`;

    // ================= 3. TAMBAH ANGGOTA BARU =================
    if (d.canEdit) {
        const currentMemberIds = new Set([
            ...(d.ketua || []).map(k => k.id),
            ...(d.anggota || []).map(a => a.id),
        ]);
        const availableCandidates = CANDIDATE_USERS.filter(c => !currentMemberIds.has(c.id));

        html += `
            <div class="bg-emerald-50/50 border border-emerald-200/80 rounded-xl p-4">
                <h4 class="text-xs font-bold text-emerald-800 uppercase tracking-wide mb-2 flex items-center gap-1.5">
                    <i class="fa-solid fa-user-plus text-emerald-600"></i> Tambah Anggota ke Divisi Ini
                </h4>`;

        if (availableCandidates.length > 0) {
            html += `
                <form method="post" action="${BASE_URL}/struktur/division/${d.id}/member/add" class="flex gap-2">
                    ${CSRF_FIELD}
                    <select name="user_id" required class="flex-1 min-w-0 text-xs bg-white border border-slate-300 rounded-lg px-3 py-2 outline-none focus:border-emerald-500">
                        <option value="">— Pilih personil untuk ditambahkan ke divisi ini —</option>
                        ${availableCandidates.map(c => `
                            <option value="${c.id}">${escapeHtml(c.nama)} (${c.divisi_nama ? 'saat ini: ' + escapeHtml(c.divisi_nama) : 'tanpa divisi'}) — ${escapeHtml(c.jabatan)}</option>
                        `).join('')}
                    </select>
                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition whitespace-nowrap flex items-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-plus text-[10px]"></i> Tambahkan
                    </button>
                </form>`;
        } else {
            html += `<p class="text-xs text-slate-400 italic">Semua personil aktif sudah berada di divisi ini.</p>`;
        }
        html += `</div>`;
    }

    bodyEl.innerHTML = html;

    // Tampilkan modal
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    requestAnimationFrame(() => {
        modal.classList.remove('opacity-0');
        modal.classList.add('opacity-100');
        const content = document.getElementById('division-modal-content');
        if (content) {
            content.classList.remove('scale-95');
            content.classList.add('scale-100');
        }
    });
}

function closeDivisionModal() {
    const modal = document.getElementById('division-modal');
    const content = document.getElementById('division-modal-content');
    if (!modal || modal.classList.contains('hidden')) return;

    modal.classList.remove('opacity-100');
    modal.classList.add('opacity-0');
    if (content) {
        content.classList.remove('scale-100');
        content.classList.add('scale-95');
    }
    setTimeout(() => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }, 200);
}

// Tutup modal jika klik di luar box modal atau tekan Escape
document.addEventListener('click', function(e) {
    const modal = document.getElementById('division-modal');
    if (e.target === modal) closeDivisionModal();
});
window.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDivisionModal();
});

// Pindahkan elemen modal ke body saat DOM siap & cek query parameter ?open_division=ID
window.addEventListener('DOMContentLoaded', function() {
    const dm = document.getElementById('division-modal');
    if (dm && dm.parentElement !== document.body) {
        document.body.appendChild(dm);
    }

    const urlParams = new URLSearchParams(window.location.search);
    const openDivId = urlParams.get('open_division');
    if (openDivId && DIVISIONS_DATA[openDivId]) {
        openDivisionModal(parseInt(openDivId, 10));
    }
});

/**
 * Saat klik node personil pada bagan pohon:
 * Jika memiliki divisi, LANGSUNG buka modal divisi tersebut memperlihatkan ketua & anggotanya.
 */
function showNodeDetail(d) {
    const divId = parseInt(d.divisionId, 10);
    if (divId > 0 && DIVISIONS_DATA[divId]) {
        openDivisionModal(divId, parseInt(d.id, 10));
        return;
    }

    // Jika node tidak terikat ke divisi (misal Administrator di luar pohon)
    const html = `
        <div class="space-y-3 text-sm">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-lg">${d.nama.split(' ').map(w=>w[0]).slice(0,2).join('')}</div>
                <div>
                    <p class="font-bold text-slate-800">${escapeHtml(d.nama)}</p>
                    <p class="text-xs text-slate-500">${escapeHtml(d.jabatan)}</p>
                </div>
                <span class="ml-auto text-[10px] font-bold px-2 py-1 rounded bg-slate-100 text-slate-600">${escapeHtml(d.badge)}</span>
            </div>
            <div class="bg-slate-50 border border-slate-100 rounded-lg p-3 space-y-1.5 text-xs">
                <p><span class="text-slate-400 font-medium">Peran Akses:</span> ${escapeHtml(d.role)}</p>
                <p><span class="text-slate-400 font-medium">ID Node:</span> #${escapeHtml(d.id)}</p>
            </div>
        </div>`;
    openModal('Detail Personil Organisasi', html, () => closeModal());
    const confirmBtn = document.getElementById('modal-confirm');
    if (confirmBtn) {
        confirmBtn.style.display = 'none';
        setTimeout(() => { confirmBtn.style.display = ''; }, 300);
    }
}
</script>

<?php layout_footer(); ?>
