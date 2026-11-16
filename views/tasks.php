<?php
/**
 * views/tasks.php
 * Kanban & Delegasi Tugas.
 * Mendukung Multi-Assignee & Kolom Permintaan Masuk Lintas Divisi untuk Ketua Divisi.
 * 
 * @var array $tasks
 * @var array $me
 * @var array $assignable
 * @var array $divisions
 * @var array $myDivisionMembers
 * @var bool  $isKetua
 * @var bool  $isAdmin
 * @var int   $myDivId
 */

$hasAssignAll = can(PERM['TASKS_ASSIGN_ALL']);
$showIncomingCol = $isKetua || $isAdmin;
?>
<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
    <div>
        <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide flex items-center gap-2">
            <i class="fa-solid fa-list-check text-indigo-600"></i> Papan Kanban &amp; Delegasi Tugas
        </h2>
        <p class="text-slate-500 text-xs mt-0.5">
            <?php if ($isKetua): ?>
                Sebagai Ketua Divisi, Anda dapat meninjau, menerima, atau menolak permintaan tugas dari divisi lain serta berdiskusi langsung.
            <?php else: ?>
                Kelola alur kerja tugas Anda dan tim. Seret kartu tugas antar-kolom untuk memperbarui status.
            <?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <?php if ($isKetua): ?>
            <span class="text-[10px] bg-amber-50 text-amber-800 border border-amber-200 font-semibold px-2.5 py-1 rounded-full flex items-center gap-1 shadow-xs">
                <i class="fa-solid fa-user-tie text-[9px]"></i> Ketua Divisi (<?= e($me['divisi_nama'] ?? 'Divisi') ?>)
            </span>
        <?php endif; ?>
        <?php if ($isAdmin || $hasAssignAll): ?>
            <span class="text-[10px] bg-indigo-50 text-indigo-700 border border-indigo-200 font-semibold px-2.5 py-1 rounded-full flex items-center gap-1 shadow-xs">
                <i class="fa-solid fa-shield-halved text-[9px]"></i> Hak Akses Penuh
            </span>
        <?php endif; ?>
        <button onclick="promptAddTask()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-xl text-xs font-semibold transition shadow-xs flex items-center gap-1.5">
            <i class="fa-solid fa-plus text-[10px]"></i> Delegasikan Tugas
        </button>
    </div>
</div>

<!-- Mobile Column Quick Selector / Filter Bar -->
<div class="flex items-center gap-1.5 overflow-x-auto pb-2 mb-3 md:hidden no-scrollbar">
    <button type="button" onclick="filterMobileKanban('ALL')" id="mob-col-all" class="mob-col-tab px-3 py-1.5 rounded-full text-xs font-bold bg-slate-800 text-white whitespace-nowrap shadow-xs">
        Semua (<?= count($tasks) ?>)
    </button>
    <?php if ($showIncomingCol): ?>
        <button type="button" onclick="filterMobileKanban('REQUESTED')" id="mob-col-requested" class="mob-col-tab px-3 py-1.5 rounded-full text-xs font-semibold bg-white border border-amber-300 text-amber-800 whitespace-nowrap">
            Permintaan (<?= count(array_filter($tasks, fn($t) => $t['status'] === 'REQUESTED')) ?>)
        </button>
    <?php endif; ?>
    <button type="button" onclick="filterMobileKanban('TODO')" id="mob-col-todo" class="mob-col-tab px-3 py-1.5 rounded-full text-xs font-semibold bg-white border border-slate-200 text-slate-700 whitespace-nowrap">
        To Do (<?= count(array_filter($tasks, fn($t) => $t['status'] === 'TODO')) ?>)
    </button>
    <button type="button" onclick="filterMobileKanban('IN_PROGRESS')" id="mob-col-inprogress" class="mob-col-tab px-3 py-1.5 rounded-full text-xs font-semibold bg-white border border-blue-200 text-blue-700 whitespace-nowrap">
        Progress (<?= count(array_filter($tasks, fn($t) => $t['status'] === 'IN_PROGRESS')) ?>)
    </button>
    <button type="button" onclick="filterMobileKanban('DONE')" id="mob-col-done" class="mob-col-tab px-3 py-1.5 rounded-full text-xs font-semibold bg-white border border-emerald-200 text-emerald-700 whitespace-nowrap">
        Done (<?= count(array_filter($tasks, fn($t) => $t['status'] === 'DONE')) ?>)
    </button>
</div>

<!-- Kolom Kanban Grid -->
<div class="flex-1 grid grid-cols-1 <?= $showIncomingCol ? 'md:grid-cols-2 lg:grid-cols-4' : 'md:grid-cols-3' ?> gap-4 overflow-hidden pb-4" id="kanban-container">
<?php
// Susunan kolom
$columns = [];
if ($showIncomingCol) {
    $columns['REQUESTED'] = [
        'Permintaan Masuk',
        'bg-amber-100',
        'text-amber-900',
        'bg-amber-200',
        'text-amber-900',
        'border-amber-200',
        'fa-inbox text-amber-600'
    ];
}
$columns['TODO'] = [
    'To Do',
    'bg-slate-200/90',
    'text-slate-700',
    'bg-slate-300',
    'text-slate-700',
    'border-slate-200',
    'fa-clock text-slate-500'
];
$columns['IN_PROGRESS'] = [
    'In Progress',
    'bg-blue-100',
    'text-blue-800',
    'bg-blue-200',
    'text-blue-800',
    'border-blue-200',
    'fa-spinner text-blue-600'
];
$columns['DONE'] = [
    'Done',
    'bg-emerald-100',
    'text-emerald-800',
    'bg-emerald-200',
    'text-emerald-800',
    'border-emerald-200',
    'fa-circle-check text-emerald-600'
];

foreach ($columns as $status => [$label, $headBg, $headText, $countBg, $countText, $colBorder, $colIcon]):
    // Filter tugas untuk kolom ini
    $colTasks = array_values(array_filter($tasks, function($t) use ($status, $isKetua, $isAdmin, $myDivId, $me) {
        if ($t['status'] !== $status) return false;
        // Di kolom REQUESTED: khususkan untuk tugas divisi bersangkutan atau yang dikirim oleh user ini
        if ($status === 'REQUESTED') {
            if ($isAdmin) return true;
            $tDiv = (int)($t['target_division_id'] ?? 0);
            $hasMemberInMyDiv = false;
            foreach ($t['assignees'] ?? [] as $asg) {
                if ((int)($asg['division_id'] ?? 0) === $myDivId) {
                    $hasMemberInMyDiv = true;
                    break;
                }
            }
            $isForMyDiv = ($tDiv === $myDivId || $hasMemberInMyDiv);
            $isFromMe = (int)$t['assigner_id'] === (int)$me['id'];
            return $isForMyDiv || $isFromMe;
        }
        return true;
    }));
?>
    <div class="kanban-column bg-slate-100/80 rounded-2xl flex flex-col max-h-full border <?= $colBorder ?>"
         data-status="<?= $status ?>"
         ondragover="event.preventDefault(); this.classList.add('drag-over')"
         ondragleave="this.classList.remove('drag-over')"
         ondrop="dropTask(event, '<?= $status ?>')">
        
        <div class="p-3.5 border-b border-slate-200/70 flex justify-between items-center <?= $headBg ?> rounded-t-2xl">
            <h3 class="font-bold <?= $headText ?> text-xs uppercase tracking-wider flex items-center gap-2">
                <i class="fa-solid <?= $colIcon ?> text-xs"></i>
                <span><?= $label ?></span>
                <span class="<?= $countBg ?> <?= $countText ?> px-2 py-0.5 rounded-full text-[11px] font-bold"><?= count($colTasks) ?></span>
            </h3>
            <?php if ($status === 'REQUESTED'): ?>
                <span class="text-[9px] font-bold text-amber-700 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">Lintas Divisi</span>
            <?php endif; ?>
        </div>

        <div class="p-3 flex-1 overflow-y-auto space-y-3">
        <?php if ($colTasks === []): ?>
            <p class="text-slate-400 text-xs italic text-center py-6">
                <?= $status === 'REQUESTED' ? 'Belum ada permintaan tugas dari divisi lain.' : 'Tidak ada tugas di kolom ini.' ?>
            </p>
        <?php endif; ?>

        <?php foreach ($colTasks as $t): ?>
            <?php
            $assignees = $t['assignees'] ?? [];
            $assigneeCount = count($assignees);
            $assigneeNames = implode(', ', array_map(fn($a) => $a['nama'], $assignees));

            $isAssignee = in_array((int)$me['id'], array_column($assignees, 'id'), true)
                || (int)($t['assignee_id'] ?? 0) === (int)$me['id'];
            $isAssigner = (int)$t['assigner_id'] === (int)$me['id'];

            // Cek apakah tugas ditujukan ke divisi user ini
            $targetDiv = (int)($t['target_division_id'] ?? 0);
            $isTargetForMyDiv = ($targetDiv > 0 && $targetDiv === $myDivId);
            if (!$isTargetForMyDiv) {
                foreach ($assignees as $asg) {
                    if ((int)($asg['division_id'] ?? 0) === $myDivId) {
                        $isTargetForMyDiv = true;
                        break;
                    }
                }
            }

            // Wewenang Ketua Divisi Penerima untuk Ambil / Tolak tugas di kolom REQUESTED
            $canDecideRequest = ($t['status'] === 'REQUESTED') && ($isAdmin || ($isKetua && $isTargetForMyDiv && !$isAssigner));

            // Hak akses memindahkan status (drag & drop):
            $canMove = ($t['status'] !== 'REQUESTED') && ($isAssignee || $isAssigner || $hasAssignAll || $isAdmin);

            // Hak akses mengedit/menghapus tugas:
            $canEdit = $isAssigner || $hasAssignAll || $isAdmin;
            ?>
            <div class="bg-white p-3.5 rounded-xl shadow-xs border <?= $t['status'] === 'REQUESTED' ? 'border-amber-300 ring-1 ring-amber-200/50' : 'border-slate-200' ?> <?= $canMove ? 'cursor-grab hover:border-indigo-400 hover:shadow-md' : '' ?> transition relative"
                 <?= $canMove ? 'draggable="true" ondragstart="dragStart(event, ' . (int)$t['id'] . ')"' : '' ?>
                 id="task-card-<?= (int)$t['id'] ?>">
                
                <!-- Header Kartu -->
                <div class="flex justify-between items-start mb-2 gap-2">
                    <div class="flex flex-wrap items-center gap-1">
                        <?php if ($t['status'] === 'REQUESTED'): ?>
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300 flex items-center gap-1">
                                <i class="fa-solid fa-bell text-[8px]"></i> Permintaan Masuk
                            </span>
                        <?php else: ?>
                            <span class="text-[9px] font-bold px-2 py-0.5 rounded-full <?= $t['status'] === 'DONE' ? 'bg-emerald-100 text-emerald-700' : ($t['status'] === 'IN_PROGRESS' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600') ?>">
                                <?= e($t['status'] === 'IN_PROGRESS' ? 'IN PROGRESS' : $t['status']) ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($t['target_divisi_nama'])): ?>
                            <span class="text-[9px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-medium truncate max-w-[110px]" title="Untuk: <?= e($t['target_divisi_nama']) ?>">
                                <i class="fa-solid fa-arrow-right text-[8px] text-slate-400 mr-0.5"></i><?= e($t['target_divisi_nama']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-1 flex-shrink-0">
                        <!-- Tombol Komentar / Tanya Jawab -->
                        <button type="button"
                                onclick="openTaskCommentsModal(<?= (int)$t['id'] ?>, <?= htmlspecialchars(json_encode($t['judul']), ENT_QUOTES, 'UTF-8') ?>)"
                                class="text-slate-400 hover:text-indigo-600 px-1.5 py-0.5 rounded text-[10px] font-bold flex items-center gap-1 hover:bg-slate-100 transition"
                                title="Buka Diskusi / Tanya Jawab Tugas">
                            <i class="fa-regular fa-comment-dots"></i>
                            <span><?= (int)($t['comment_count'] ?? 0) ?></span>
                        </button>

                        <?php if ($canEdit && $t['status'] !== 'REQUESTED'): ?>
                            <button type="button"
                                    onclick="promptEditTask(<?= htmlspecialchars(json_encode([
                                        'id'           => (int)$t['id'],
                                        'judul'        => $t['judul'],
                                        'deskripsi'    => $t['deskripsi'] ?? '',
                                        'assignee_ids' => array_column($assignees, 'id'),
                                        'status'       => $t['status'],
                                    ]), ENT_QUOTES, 'UTF-8') ?>)"
                                    class="text-slate-400 hover:text-indigo-600 p-1 rounded hover:bg-slate-100 transition"
                                    title="Edit Tugas">
                                <i class="fa-solid fa-pen-to-square text-[10px]"></i>
                            </button>
                        <?php endif; ?>

                        <?php if ($canEdit): ?>
                            <form method="post" action="<?= base_url('/tugas/' . (int)$t['id'] . '/delete') ?>" onsubmit="return confirm('Hapus tugas ini?')" class="inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="text-slate-400 hover:text-red-600 p-1 rounded hover:bg-red-50 transition" title="Hapus Tugas">
                                    <i class="fa-solid fa-trash-can text-[10px]"></i>
                                </button>
                            </form>
                        <?php endif; ?>

                        <?php if ($canMove): ?>
                            <i class="fa-solid fa-grip-vertical text-slate-300 ml-0.5 cursor-grab" title="Seret kartu untuk memindahkan status"></i>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Konten Judul & Deskripsi -->
                <h4 class="text-sm font-bold text-slate-800 mb-1 leading-snug"><?= e($t['judul']) ?></h4>
                <p class="text-xs text-slate-500 mb-3 line-clamp-2 leading-relaxed"><?= e($t['deskripsi'] ?? '') ?></p>

                <!-- Footer Kartu: Assigner & Assignee -->
                <div class="flex justify-between items-center border-t border-slate-100 pt-2.5 mt-2">
                    <div class="text-[10px] text-slate-500 truncate max-w-[115px]" title="Pemberi: <?= e($t['assigner_nama']) ?> (<?= e($t['assigner_divisi_nama'] ?? 'Tanpa Divisi') ?>)">
                        Dari: <span class="font-semibold text-slate-700"><?= e(explode(' ', $t['assigner_nama'])[0]) ?></span>
                        <?php if (!empty($t['assigner_divisi_nama'])): ?>
                            <span class="text-[9px] text-indigo-600 block truncate"><?= e($t['assigner_divisi_nama']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="flex items-center gap-1.5 flex-shrink-0" title="Penerima: <?= e($assigneeNames !== '' ? $assigneeNames : 'Menunggu ketua divisi') ?>">
                        <span class="text-[10px] text-slate-400">Kpd:</span>
                        <?php if ($assigneeCount === 1): ?>
                            <?php $a0 = $assignees[0]; ?>
                            <div class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-[9px] text-white <?= avatar_color((int)$a0['id']) ?> shadow-xs" title="<?= e($a0['nama']) ?> (<?= e($a0['divisi_nama']) ?>)">
                                <?= e(initials($a0['nama'])) ?>
                            </div>
                            <span class="text-[10px] font-semibold text-slate-700 truncate max-w-[85px]"><?= e(explode(' ', $a0['nama'])[0]) ?></span>
                        <?php elseif ($assigneeCount > 1): ?>
                            <div class="flex -space-x-1.5 overflow-hidden items-center">
                                <?php foreach (array_slice($assignees, 0, 3) as $a): ?>
                                    <div class="w-6 h-6 rounded-full ring-2 ring-white flex items-center justify-center font-bold text-[9px] text-white <?= avatar_color((int)$a['id']) ?> shadow-xs" title="<?= e($a['nama']) ?> (<?= e($a['divisi_nama']) ?>)">
                                        <?= e(initials($a['nama'])) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <span class="text-[10px] font-bold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded-full" title="<?= e($assigneeNames) ?>">
                                <?= $assigneeCount ?> tim
                            </span>
                        <?php else: ?>
                            <span class="text-[10px] font-medium text-amber-600 italic">Ketua Divisi</span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- AKSI KHUSUS KETUA DIVISI PADA PERMINTAAN MASUK -->
                <?php if ($canDecideRequest): ?>
                    <div class="mt-3 pt-2.5 border-t border-amber-200 flex items-center justify-between gap-1.5 bg-amber-50/70 -mx-3.5 -mb-3.5 p-2.5 rounded-b-xl">
                        <button type="button"
                                onclick="promptAcceptTask(<?= (int)$t['id'] ?>, <?= htmlspecialchars(json_encode($t['judul']), ENT_QUOTES, 'UTF-8') ?>)"
                                class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-1 px-2 rounded-lg text-[10px] transition flex items-center justify-center gap-1 shadow-xs">
                            <i class="fa-solid fa-check text-[9px]"></i> Ambil / Terima
                        </button>
                        <button type="button"
                                onclick="promptRejectTask(<?= (int)$t['id'] ?>, <?= htmlspecialchars(json_encode($t['judul']), ENT_QUOTES, 'UTF-8') ?>)"
                                class="flex-1 bg-white hover:bg-rose-50 text-rose-600 hover:text-rose-700 border border-rose-200 font-bold py-1 px-2 rounded-lg text-[10px] transition flex items-center justify-center gap-1">
                            <i class="fa-solid fa-xmark text-[9px]"></i> Tolak Tugas
                        </button>
                    </div>
                <?php elseif ($t['status'] === 'REQUESTED' && $isAssigner): ?>
                    <div class="mt-2.5 pt-2 border-t border-dashed border-slate-100 text-center">
                        <span class="text-[10px] font-medium text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md inline-block">
                            <i class="fa-regular fa-hourglass-half mr-1"></i>Menunggu Respon Ketua Divisi
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Tombol Pindah Status Cepat (One-Click) pada Kolom Kerja Biasa -->
                <?php if ($canMove && $t['status'] !== 'REQUESTED'): ?>
                    <div class="mt-3 pt-2 border-t border-dashed border-slate-100 flex items-center justify-between text-[10px]">
                        <span class="text-slate-400 text-[9px]">Pindah Cepat:</span>
                        <div class="flex items-center gap-1">
                            <?php if ($t['status'] !== 'TODO'): ?>
                                <button type="button" onclick="quickMoveTask(<?= (int)$t['id'] ?>, 'TODO')" class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold transition" title="Kembalikan ke To Do">To Do</button>
                            <?php endif; ?>
                            <?php if ($t['status'] !== 'IN_PROGRESS'): ?>
                                <button type="button" onclick="quickMoveTask(<?= (int)$t['id'] ?>, 'IN_PROGRESS')" class="px-2 py-0.5 rounded bg-blue-50 hover:bg-blue-100 text-blue-700 font-semibold transition" title="Pindahkan ke In Progress">Progress</button>
                            <?php endif; ?>
                            <?php if ($t['status'] !== 'DONE'): ?>
                                <button type="button" onclick="quickMoveTask(<?= (int)$t['id'] ?>, 'DONE')" class="px-2 py-0.5 rounded bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-semibold transition" title="Tandai Selesai">Done</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- Modal Diskusi / Tanya Jawab Tugas -->
<div id="comments-modal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden items-center justify-center opacity-0 transition-opacity">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[85vh] flex flex-col transform scale-95 transition-transform" id="comments-modal-content">
        <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50/60 rounded-t-2xl">
            <div>
                <h3 class="font-bold text-sm text-slate-800 flex items-center gap-2">
                    <i class="fa-regular fa-comments text-indigo-600"></i> Diskusi &amp; Klarifikasi Tugas
                </h3>
                <p id="comments-task-title" class="text-xs text-slate-500 font-medium truncate max-w-sm mt-0.5"></p>
            </div>
            <button onclick="closeCommentsModal()" class="text-slate-400 hover:text-red-500 transition"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <!-- Wadah List Komentar -->
        <div id="comments-list" class="flex-1 p-5 overflow-y-auto space-y-3.5 bg-white text-xs">
            <div class="text-center py-8 text-slate-400 italic">Memuat riwayat diskusi...</div>
        </div>

        <!-- Input Form Komentar -->
        <div class="p-4 bg-slate-50 border-t border-slate-100 rounded-b-2xl">
            <form id="comment-form" onsubmit="submitTaskComment(event)" class="space-y-2">
                <input type="hidden" id="comment-task-id" value="">
                <textarea id="comment-input" rows="2" required placeholder="Ketik pertanyaan, instruksi klarifikasi, atau jawaban Anda di sini..." class="w-full bg-white border border-slate-200 focus:border-indigo-400 outline-none rounded-xl p-2.5 text-xs transition resize-none"></textarea>
                <div class="flex justify-between items-center">
                    <span class="text-[10px] text-slate-400">Pesan ini dapat dibaca oleh pemberi dan penerima tugas.</span>
                    <button type="submit" id="btn-submit-comment" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3.5 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 shadow-xs">
                        <i class="fa-solid fa-paper-plane text-[10px]"></i> Kirim Pesan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const ME_ID = <?= (int)$me['id'] ?>;
const IS_KETUA = <?= $isKetua ? 'true' : 'false' ?>;
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
const MY_DIV_ID = <?= (int)$myDivId ?>;

const ASSIGNABLE_USERS = <?= json_encode(array_map(fn($u) => [
    'id'      => (int)$u['id'],
    'nama'    => $u['nama'],
    'jabatan' => $u['jabatan'],
    'divisi'  => $u['divisi_nama'] ?? 'Tanpa Divisi',
], $assignable), JSON_UNESCAPED_UNICODE) ?>;

const DIVISIONS = <?= json_encode(array_map(fn($d) => [
    'id'         => (int)$d['id'],
    'nama'       => $d['nama'],
    'kode'       => $d['kode'],
    'ketua_nama' => $d['ketua_nama'] ?? 'Belum ada ketua',
], $divisions), JSON_UNESCAPED_UNICODE) ?>;

const MY_DIVISION_MEMBERS = <?= json_encode(array_map(fn($u) => [
    'id'      => (int)$u['id'],
    'nama'    => $u['nama'],
    'jabatan' => $u['jabatan'],
], $myDivisionMembers), JSON_UNESCAPED_UNICODE) ?>;

// Pindah modal comments ke body
document.addEventListener('DOMContentLoaded', () => {
    const cm = document.getElementById('comments-modal');
    if (cm && cm.parentElement !== document.body) {
        document.body.appendChild(cm);
    }
});

function filterMobileKanban(status) {
    document.querySelectorAll('.mob-col-tab').forEach(b => {
        b.classList.remove('bg-slate-800', 'text-white');
        b.classList.add('bg-white');
    });
    const key = status.toLowerCase().replace('_', '');
    const activeBtn = document.getElementById('mob-col-' + key);
    if (activeBtn) {
        activeBtn.classList.add('bg-slate-800', 'text-white');
        activeBtn.classList.remove('bg-white');
    }
    document.querySelectorAll('.kanban-column').forEach(col => {
        if (status === 'ALL' || col.getAttribute('data-status') === status) {
            col.style.display = '';
        } else {
            col.style.display = 'none';
        }
    });
}

function dragStart(e, id) {
    e.dataTransfer.setData('taskId', String(id));
    e.target.style.opacity = '0.4';
    setTimeout(() => { if (e.target) e.target.style.opacity = '1'; }, 100);
}

async function dropTask(e, newStatus) {
    e.preventDefault();
    const col = e.target.closest('.rounded-2xl');
    if (col) col.classList.remove('drag-over');
    const id = e.dataTransfer.getData('taskId');
    if (!id) return;
    await quickMoveTask(id, newStatus);
}

async function quickMoveTask(taskId, newStatus) {
    const res = await post(`/api/tasks/${taskId}/status`, { status: newStatus });
    if (res.ok) {
        showToast('Tugas dipindahkan ke ' + newStatus.replace('_', ' ').toLowerCase());
        setTimeout(() => location.reload(), 400);
    } else {
        const data = await res.json().catch(() => ({}));
        showToast(data.error || 'Gagal memindahkan tugas', true);
    }
}

/**
 * Modal Terima / Ambil Tugas oleh Ketua Divisi
 */
function promptAcceptTask(taskId, judul) {
    let memberOptionsHtml = '';
    if (MY_DIVISION_MEMBERS.length > 0) {
        memberOptionsHtml = `
            <div class="mt-3">
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">
                    Distribusikan ke Personil Divisi Saya (Opsional)
                </label>
                <div class="max-h-36 overflow-y-auto space-y-1 p-1 bg-slate-50 border border-slate-200 rounded-xl">
                    ${MY_DIVISION_MEMBERS.map(m => `
                        <label class="flex items-center gap-2 p-1.5 hover:bg-white rounded-lg cursor-pointer text-xs transition">
                            <input type="checkbox" name="accept_assignee_ids[]" value="${m.id}" class="rounded text-indigo-600 focus:ring-indigo-500 w-3.5 h-3.5">
                            <span class="font-medium text-slate-700">${escapeHtml(m.nama)}</span>
                            <span class="text-[10px] text-slate-400">(${escapeHtml(m.jabatan)})</span>
                        </label>
                    `).join('')}
                </div>
                <p class="text-[10px] text-slate-400 mt-1">Jika tidak dicentang, tugas otomatis Anda ambil sebagai Ketua.</p>
            </div>
        `;
    }

    openModal('Terima &amp; Ambil Tugas', `
        <div class="space-y-3 text-xs">
            <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-emerald-800">
                <div class="font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-check text-emerald-600"></i> Konfirmasi Penerimaan Tugas
                </div>
                <p class="text-[11px] mt-1 leading-relaxed">
                    Tugas <strong>"${escapeHtml(judul)}"</strong> akan diterima dan masuk ke kolom <strong>To Do</strong> divisi Anda.
                </p>
            </div>

            ${memberOptionsHtml}

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Catatan Penerimaan (Opsional)</label>
                <input type="text" id="accept-catatan" placeholder="Contoh: Diterima, akan dikerjakan dalam sprint minggu ini." class="w-full bg-slate-50 border border-slate-200 focus:border-indigo-400 focus:bg-white outline-none rounded-xl p-2.5 text-xs transition">
            </div>
        </div>`,
        async () => {
            const chks = document.querySelectorAll('input[name="accept_assignee_ids[]"]:checked');
            const assigneeIds = Array.from(chks).map(c => c.value);
            const catatan = document.getElementById('accept-catatan')?.value || '';

            const res = await post(`/api/tasks/${taskId}/accept`, {
                assignee_ids: assigneeIds.join(','),
                catatan: catatan
            });

            if (res.ok) {
                showToast('Tugas berhasil diterima dan masuk ke To Do');
                setTimeout(() => location.reload(), 400);
            } else {
                const data = await res.json().catch(() => ({}));
                showToast(data.error || 'Gagal menerima tugas', true);
            }
        });
}

/**
 * Modal Tolak Tugas oleh Ketua Divisi
 */
function promptRejectTask(taskId, judul) {
    openModal('Tolak Permintaan Tugas', `
        <div class="space-y-3 text-xs">
            <div class="p-3 bg-rose-50 border border-rose-200 rounded-xl text-rose-800">
                <div class="font-bold flex items-center gap-1.5">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Penolakan Tugas Antar-Divisi
                </div>
                <p class="text-[11px] mt-1 leading-relaxed">
                    Anda akan menolak tugas <strong>"${escapeHtml(judul)}"</strong>. Berikan alasan yang jelas agar divisi pengusul memahami kendala Anda.
                </p>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Alasan Penolakan Tugas *</label>
                <textarea id="reject-alasan" rows="3" required placeholder="Jelaskan alasan penolakan (misal: beban kerja divisi penuh, di luar kewenangan tupoksi, dll)..." class="w-full bg-slate-50 border border-slate-200 focus:border-rose-400 focus:bg-white outline-none rounded-xl p-2.5 text-xs transition resize-none"></textarea>
            </div>
        </div>`,
        async () => {
            const alasan = document.getElementById('reject-alasan')?.value.trim();
            if (!alasan) {
                showToast('Alasan penolakan wajib diisi!', true);
                return;
            }

            const res = await post(`/api/tasks/${taskId}/reject`, { alasan: alasan });
            if (res.ok) {
                showToast('Tugas ditolak dengan alasan tercatat');
                setTimeout(() => location.reload(), 400);
            } else {
                const data = await res.json().catch(() => ({}));
                showToast(data.error || 'Gagal menolak tugas', true);
            }
        });
}

/**
 * Modal Diskusi / Tanya Jawab Komentar
 */
async function openTaskCommentsModal(taskId, taskJudul) {
    const modal = document.getElementById('comments-modal');
    document.getElementById('comments-task-title').innerText = taskJudul;
    document.getElementById('comment-task-id').value = taskId;
    document.getElementById('comment-input').value = '';

    modal.classList.remove('hidden');
    setTimeout(() => {
        modal.classList.remove('opacity-0');
        document.getElementById('comments-modal-content').classList.remove('scale-95');
    }, 10);

    await loadTaskComments(taskId);
}

function closeCommentsModal() {
    const modal = document.getElementById('comments-modal');
    modal.classList.add('opacity-0');
    document.getElementById('comments-modal-content').classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 200);
}

async function loadTaskComments(taskId) {
    const container = document.getElementById('comments-list');
    container.innerHTML = '<div class="text-center py-8 text-slate-400 italic">Memuat riwayat diskusi...</div>';

    try {
        const res = await fetch(`${BASE}/api/tasks/${taskId}/comments`);
        const data = await res.json();
        if (!data.ok || !data.comments) {
            container.innerHTML = '<div class="text-center py-8 text-red-500">Gagal memuat diskusi.</div>';
            return;
        }

        if (data.comments.length === 0) {
            container.innerHTML = `
                <div class="text-center py-8 text-slate-400">
                    <i class="fa-regular fa-comment-dots text-3xl mb-2 opacity-50 block"></i>
                    Belum ada pesan atau pertanyaan untuk tugas ini.<br>Ketik di bawah untuk memulai diskusi / klarifikasi.
                </div>`;
            return;
        }

        container.innerHTML = data.comments.map(c => {
            const isMe = c.author_id === ME_ID;
            if (c.tipe === 'REJECT') {
                return `
                    <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-900">
                        <div class="flex justify-between items-center mb-1 text-[10px]">
                            <span class="font-bold text-rose-700 flex items-center gap-1">
                                <i class="fa-solid fa-circle-xmark"></i> DITOLAK OLEH ${escapeHtml(c.author_nama)}
                            </span>
                            <span class="text-rose-400">${escapeHtml(c.time_ago)}</span>
                        </div>
                        <div class="whitespace-pre-line text-xs font-medium">${escapeHtml(c.isi)}</div>
                    </div>`;
            }

            if (c.tipe === 'ACCEPT') {
                return `
                    <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-900">
                        <div class="flex justify-between items-center mb-1 text-[10px]">
                            <span class="font-bold text-emerald-700 flex items-center gap-1">
                                <i class="fa-solid fa-circle-check"></i> DITERIMA OLEH ${escapeHtml(c.author_nama)}
                            </span>
                            <span class="text-emerald-400">${escapeHtml(c.time_ago)}</span>
                        </div>
                        <div class="whitespace-pre-line text-xs font-medium">${escapeHtml(c.isi)}</div>
                    </div>`;
            }

            return `
                <div class="flex gap-2.5 ${isMe ? 'flex-row-reverse' : ''}">
                    <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center font-bold text-[10px] text-white ${isMe ? 'bg-indigo-600' : 'bg-slate-600'} shadow-xs">
                        ${escapeHtml(c.author_nama.substring(0, 2).toUpperCase())}
                    </div>
                    <div class="max-w-[75%]">
                        <div class="flex items-center gap-1.5 mb-0.5 text-[10px] ${isMe ? 'justify-end' : ''}">
                            <span class="font-bold text-slate-700">${escapeHtml(c.author_nama)}</span>
                            <span class="text-slate-400 text-[9px]">${escapeHtml(c.author_divisi)}</span>
                            <span class="text-slate-300">&bull;</span>
                            <span class="text-slate-400 text-[9px]">${escapeHtml(c.time_ago)}</span>
                        </div>
                        <div class="p-2.5 rounded-2xl ${isMe ? 'bg-indigo-600 text-white rounded-tr-none' : 'bg-slate-100 text-slate-800 rounded-tl-none'} text-xs leading-relaxed whitespace-pre-line">
                            ${escapeHtml(c.isi)}
                        </div>
                    </div>
                </div>`;
        }).join('');

        container.scrollTop = container.scrollHeight;
    } catch (err) {
        container.innerHTML = '<div class="text-center py-8 text-red-500">Terjadi kesalahan memuat diskusi.</div>';
    }
}

async function submitTaskComment(e) {
    e.preventDefault();
    const taskId = document.getElementById('comment-task-id').value;
    const input = document.getElementById('comment-input');
    const isi = input.value.trim();
    if (!isi) return;

    const btn = document.getElementById('btn-submit-comment');
    btn.disabled = true;

    try {
        const res = await post(`/api/tasks/${taskId}/comments`, { isi: isi });
        const data = await res.json();
        if (data.ok) {
            input.value = '';
            await loadTaskComments(taskId);
        } else {
            showToast(data.error || 'Gagal mengirim komentar', true);
        }
    } catch (err) {
        showToast('Koneksi gagal', true);
    } finally {
        btn.disabled = false;
    }
}

/**
 * Komponen Pemilih Personil (Multi-Select)
 */
function renderPersonnelSelector(selectedIds = [], prefix = 'task') {
    const selectedSet = new Set(selectedIds.map(Number));
    
    return `
        <div>
            <div class="flex justify-between items-center mb-1.5">
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px]">
                    Penerima Tugas * <span class="text-slate-400 font-normal lowercase">(bisa pilih lebih dari 1)</span>
                </label>
                <div class="flex items-center gap-2">
                    <span id="${prefix}-count" class="text-[10px] font-bold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-100">
                        ${selectedSet.size} dipilih
                    </span>
                    <button type="button" onclick="toggleSelectAllPersonnel('${prefix}', true)" class="text-[10px] text-indigo-600 hover:underline">Semua</button>
                    <span class="text-slate-300 text-[10px]">|</span>
                    <button type="button" onclick="toggleSelectAllPersonnel('${prefix}', false)" class="text-[10px] text-slate-500 hover:underline">Reset</button>
                </div>
            </div>

            <!-- Filter Pencarian -->
            <div class="relative mb-2">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                <input type="text"
                       id="${prefix}-search"
                       oninput="filterPersonnelList('${prefix}', this.value)"
                       placeholder="Cari nama, divisi, atau jabatan..."
                       class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 focus:border-indigo-400 focus:bg-white rounded-lg text-xs outline-none transition">
            </div>

            <!-- List Personil -->
            <div id="${prefix}-list" class="max-h-40 overflow-y-auto space-y-1 p-1 bg-slate-50/70 border border-slate-200 rounded-xl divide-y divide-slate-100">
                ${ASSIGNABLE_USERS.map(u => {
                    const checked = selectedSet.has(u.id);
                    return `
                    <label class="personnel-item flex items-center justify-between p-2 rounded-lg hover:bg-white hover:shadow-xs cursor-pointer transition select-none"
                           data-name="${escapeHtml(u.nama).toLowerCase()}"
                           data-divisi="${escapeHtml(u.divisi).toLowerCase()}"
                           data-jabatan="${escapeHtml(u.jabatan).toLowerCase()}">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <input type="checkbox"
                                   name="assignee_ids[]"
                                   value="${u.id}"
                                   ${checked ? 'checked' : ''}
                                   onchange="updatePersonnelCount('${prefix}')"
                                   class="personnel-chk rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 cursor-pointer">
                            <div class="min-w-0">
                                <div class="font-bold text-slate-800 text-xs truncate">${escapeHtml(u.nama)}</div>
                                <div class="text-[10px] text-slate-500 truncate">${escapeHtml(u.jabatan)} &bull; <span class="text-indigo-600">${escapeHtml(u.divisi)}</span></div>
                            </div>
                        </div>
                    </label>
                    `;
                }).join('')}
            </div>
        </div>
    `;
}

function filterPersonnelList(prefix, query) {
    const q = (query || '').toLowerCase().trim();
    const container = document.getElementById(`${prefix}-list`);
    if (!container) return;
    const items = container.querySelectorAll('.personnel-item');
    items.forEach(el => {
        const name = el.getAttribute('data-name') || '';
        const divisi = el.getAttribute('data-divisi') || '';
        const jabatan = el.getAttribute('data-jabatan') || '';
        el.style.display = (name.includes(q) || divisi.includes(q) || jabatan.includes(q)) ? 'flex' : 'none';
    });
}

function updatePersonnelCount(prefix) {
    const container = document.getElementById(`${prefix}-list`);
    const countBadge = document.getElementById(`${prefix}-count`);
    if (!container || !countBadge) return;
    const checked = container.querySelectorAll('.personnel-chk:checked').length;
    countBadge.innerText = `${checked} dipilih`;
}

function toggleSelectAllPersonnel(prefix, checkAll) {
    const container = document.getElementById(`${prefix}-list`);
    if (!container) return;
    const chks = container.querySelectorAll('.personnel-chk');
    chks.forEach(chk => {
        const parent = chk.closest('.personnel-item');
        if (parent && parent.style.display !== 'none') {
            chk.checked = checkAll;
        }
    });
    updatePersonnelCount(prefix);
}

/**
 * Modal Delegasikan Tugas Baru (Bisa Internal atau Lintas Divisi)
 */
function promptAddTask() {
    openModal('Delegasikan Tugas Baru', `
        <form id="task-form" method="post" action="${BASE}/tugas/create" class="space-y-3.5 text-xs">
            <input type="hidden" name="_csrf" value="${CSRF}">
            
            <!-- Pilihan Tipe Delegasi -->
            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1.5">Tipe Penugasan</label>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white hover:border-indigo-400 cursor-pointer transition">
                        <input type="radio" name="mode" value="internal" checked onchange="toggleTaskMode(this.value)" class="text-indigo-600 focus:ring-indigo-500">
                        <div>
                            <span class="font-bold text-slate-800 block text-xs">Delegasi Internal</span>
                            <span class="text-[10px] text-slate-400">Tim sub-tree Anda</span>
                        </div>
                    </label>
                    <label class="flex items-center gap-2 p-2.5 rounded-xl border border-slate-200 bg-white hover:border-indigo-400 cursor-pointer transition">
                        <input type="radio" name="mode" value="cross_division" onchange="toggleTaskMode(this.value)" class="text-indigo-600 focus:ring-indigo-500">
                        <div>
                            <span class="font-bold text-slate-800 block text-xs">Permintaan ke Divisi Lain</span>
                            <span class="text-[10px] text-amber-600 font-medium">Masuk Permintaan</span>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Wadah Divisi Tujuan (Khusus Permintaan Lintas Divisi) -->
            <div id="target-division-box" class="hidden p-3 bg-amber-50/70 border border-amber-200 rounded-xl space-y-2">
                <label class="block font-bold text-amber-900 uppercase tracking-wider text-[10px]">Pilih Divisi Tujuan *</label>
                <select name="target_division_id" id="target_division_id" class="w-full bg-white border border-amber-300 focus:border-indigo-400 outline-none rounded-xl p-2.5 text-xs font-semibold">
                    <option value="">— Pilih Divisi Penerima Tugas —</option>
                    ${DIVISIONS.filter(d => d.id !== MY_DIV_ID).map(d => `
                        <option value="${d.id}">${escapeHtml(d.nama)} (Ketua: ${escapeHtml(d.ketua_nama)})</option>
                    `).join('')}
                </select>
                <p class="text-[10px] text-amber-700">
                    <i class="fa-solid fa-circle-info mr-1"></i>Tugas ini akan masuk ke kolom <strong>Permintaan Masuk</strong> Ketua Divisi tujuan untuk ditinjau dan disetujui.
                </p>
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Judul Tugas *</label>
                <input type="text" name="judul" required maxlength="150" placeholder="Contoh: Audit Keamanan Server &amp; Jaringan" class="w-full bg-slate-50 border border-slate-200 focus:border-indigo-400 focus:bg-white outline-none rounded-xl p-2.5 text-xs font-semibold transition">
            </div>

            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Deskripsi &amp; Instruksi Pekerjaan</label>
                <textarea name="deskripsi" rows="3" placeholder="Rincian instruksi pekerjaan bagi tim penerima..." class="w-full bg-slate-50 border border-slate-200 focus:border-indigo-400 focus:bg-white outline-none rounded-xl p-2.5 text-xs transition resize-none"></textarea>
            </div>
            
            <div id="internal-personnel-box">
                ${renderPersonnelSelector([], 'create-task')}
            </div>
        </form>`,
        () => {
            const form = document.getElementById('task-form');
            const mode = form.querySelector('input[name="mode"]:checked').value;

            if (mode === 'cross_division') {
                const targetDiv = document.getElementById('target_division_id').value;
                if (!targetDiv) {
                    showToast('Pilih divisi tujuan untuk permintaan tugas lintas divisi!', true);
                    return;
                }
            } else {
                const checked = form.querySelectorAll('.personnel-chk:checked');
                if (checked.length === 0) {
                    showToast('Pilih minimal satu personil penerima tugas!', true);
                    return;
                }
            }
            form.submit();
        });
}

function toggleTaskMode(mode) {
    const divBox = document.getElementById('target-division-box');
    const personBox = document.getElementById('internal-personnel-box');
    if (mode === 'cross_division') {
        divBox.classList.remove('hidden');
        personBox.classList.add('hidden');
    } else {
        divBox.classList.add('hidden');
        personBox.classList.remove('hidden');
    }
}

/**
 * Modal Edit Tugas
 */
function promptEditTask(task) {
    const selectedIds = task.assignee_ids || [];
    
    openModal('Edit &amp; Alihkan Delegasi Tugas', `
        <form id="edit-task-form" method="post" action="${BASE}/tugas/${task.id}/update" class="space-y-3.5 text-xs">
            <input type="hidden" name="_csrf" value="${CSRF}">
            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Judul Tugas *</label>
                <input type="text" name="judul" value="${escapeHtml(task.judul)}" required maxlength="150" class="w-full bg-slate-50 border border-slate-200 focus:border-indigo-400 focus:bg-white outline-none rounded-xl p-2.5 text-xs font-semibold transition">
            </div>
            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Deskripsi Tugas</label>
                <textarea name="deskripsi" rows="3" class="w-full bg-slate-50 border border-slate-200 focus:border-indigo-400 focus:bg-white outline-none rounded-xl p-2.5 text-xs transition resize-none">${escapeHtml(task.deskripsi)}</textarea>
            </div>
            <div>
                <label class="block font-bold text-slate-700 uppercase tracking-wider text-[10px] mb-1">Status Kolom</label>
                <select name="status" required class="w-full bg-slate-50 border border-slate-200 focus:border-indigo-400 outline-none rounded-xl p-2.5 text-xs font-semibold">
                    <option value="TODO" ${task.status === 'TODO' ? 'selected' : ''}>To Do</option>
                    <option value="IN_PROGRESS" ${task.status === 'IN_PROGRESS' ? 'selected' : ''}>In Progress</option>
                    <option value="DONE" ${task.status === 'DONE' ? 'selected' : ''}>Done</option>
                </select>
            </div>

            ${renderPersonnelSelector(selectedIds, 'edit-task')}
        </form>`,
        () => {
            const form = document.getElementById('edit-task-form');
            form.submit();
        });
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
</script>

<?php layout_footer(); ?>
