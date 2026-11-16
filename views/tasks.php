<?php /** $tasks, $me, $assignable */ ?>
<div class="flex justify-between items-center mb-6">
    <p class="text-slate-500 text-sm">Seret &amp; lepas kartu tugas antar kolom untuk mengubah statusnya.</p>
    <?php if ($assignable !== []): ?>
    <button onclick="promptAddTask()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm">
        <i class="fa-solid fa-plus mr-2"></i> Tambah Tugas
    </button>
    <?php endif; ?>
</div>

<div class="flex-1 grid grid-cols-1 md:grid-cols-3 gap-6 overflow-hidden pb-4" id="kanban-container">
<?php
$columns = ['TODO' => ['To Do', 'bg-slate-200/80', 'text-slate-700', 'bg-slate-300', 'text-slate-600'],
            'IN_PROGRESS' => ['In Progress', 'bg-blue-100/80', 'text-blue-800', 'bg-blue-200', 'text-blue-700'],
            'DONE' => ['Done', 'bg-green-100/80', 'text-green-800', 'bg-green-200', 'text-green-700']];
foreach ($columns as $status => [$label, $headBg, $headText, $countBg, $countText]):
    $colTasks = array_filter($tasks, fn($t) => $t['status'] === $status);
?>
    <div class="bg-slate-100/80 rounded-xl flex flex-col max-h-full border border-slate-200"
         ondragover="event.preventDefault(); this.classList.add('drag-over')"
         ondragleave="this.classList.remove('drag-over')"
         ondrop="dropTask(event, '<?= $status ?>')">
        <div class="p-3 border-b border-slate-200 flex justify-between items-center <?= $headBg ?> rounded-t-xl">
            <h3 class="font-semibold <?= $headText ?> text-sm uppercase tracking-wider"><?= $label ?>
                <span class="<?= $countBg ?> <?= $countText ?> px-2 py-0.5 rounded-full ml-2 text-xs"><?= count($colTasks) ?></span>
            </h3>
        </div>
        <div class="p-3 flex-1 overflow-y-auto space-y-3">
        <?php foreach ($colTasks as $t): ?>
            <?php $mine = (int)$t['assignee_id'] === (int)$me['id'] || (int)$t['assigner_id'] === (int)$me['id']; ?>
            <div class="bg-white p-3.5 rounded-lg shadow-sm border border-slate-200 <?= $mine ? 'cursor-grab hover:border-blue-400 hover:shadow-md' : 'opacity-80' ?> transition"
                 <?= $mine ? 'draggable="true" ondragstart="dragStart(event, ' . (int)$t['id'] . ')"' : '' ?>>
                <div class="flex justify-between items-start mb-2">
                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded <?= $t['status'] === 'DONE' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' ?>"><?= e($t['status'] === 'IN_PROGRESS' ? 'PROGRESS' : $t['status']) ?></span>
                    <i class="fa-solid fa-grip-vertical text-slate-300"></i>
                </div>
                <h4 class="text-sm font-semibold text-slate-800 mb-1 leading-tight"><?= e($t['judul']) ?></h4>
                <p class="text-[11px] text-slate-500 mb-3 line-clamp-2 leading-relaxed"><?= e($t['deskripsi'] ?? '') ?></p>
                <div class="flex justify-between items-center border-t border-slate-100 pt-2.5 mt-2">
                    <div class="text-[9px] text-slate-400 font-medium truncate">Dari: <?= e($t['assigner_nama']) ?></div>
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        <span class="text-[9px] text-slate-500">Kpd:</span>
                        <div class="w-5 h-5 rounded-full flex items-center justify-center font-bold text-[8px] text-white <?= avatar_color((int)$t['assignee_id']) ?>" title="<?= e($t['assignee_nama']) ?>"><?= e(initials($t['assignee_nama'])) ?></div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<script>
function dragStart(e, id) {
    e.dataTransfer.setData('taskId', String(id));
    e.target.style.opacity = '0.5';
    setTimeout(() => e.target.style.opacity = '1', 100);
}
async function dropTask(e, newStatus) {
    e.preventDefault();
    const col = e.target.closest('.rounded-xl');
    if (col) col.classList.remove('drag-over');
    const id = e.dataTransfer.getData('taskId');
    if (!id) return;
    const res = await post(`/api/tasks/${id}/status`, { status: newStatus });
    if (res.ok) { showToast('Tugas dipindah ke ' + newStatus.replace('_', ' ').toLowerCase()); setTimeout(() => location.reload(), 600); }
    else showToast('Gagal memindah tugas', true);
}
function promptAddTask() {
    const options = <?= json_encode(array_map(fn($u) => ['id' => (int)$u['id'], 'nama' => $u['nama'], 'jabatan' => $u['jabatan']], $assignable), JSON_UNESCAPED_UNICODE) ?>;
    openModal('Delegasikan Tugas Baru', `
        <form id="task-form" method="post" action="${BASE}/tugas/create">
            <input type="hidden" name="_csrf" value="${CSRF}">
            <div class="space-y-3">
                <input type="text" name="judul" required maxlength="150" placeholder="Judul Tugas *" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-sm outline-none focus:border-blue-500">
                <textarea name="deskripsi" rows="2" placeholder="Deskripsi Singkat" class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-sm outline-none focus:border-blue-500"></textarea>
                <select name="assignee_id" required class="w-full bg-slate-50 border border-slate-300 rounded p-2 text-sm outline-none focus:border-blue-500">
                    <?php foreach ($assignable as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"><?= e($u['nama']) ?> — <?= e($u['jabatan']) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-[10px] text-slate-400">Pendelegasian hanya boleh searah jalur komando (ke bawah sub-tree Anda).</p>
            </div>
        </form>`,
        () => document.getElementById('task-form').submit());
}
</script>

<?php layout_footer(); ?>
