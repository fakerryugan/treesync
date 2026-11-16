<?php /** $divisions, $notes, $me, $activeDiv, $editable */ ?>
<div class="flex flex-wrap justify-between items-center gap-3 mb-4">
    <div class="flex items-center gap-2">
        <form method="get" action="<?= base_url('/whiteboard') ?>">
            <select name="div" onchange="this.form.submit()" class="bg-white border border-slate-300 rounded-lg px-3 py-2 text-sm outline-none focus:border-blue-400">
                <?php foreach ($divisions as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= $activeDiv === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['nama']) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="ml-2 text-xs bg-slate-200 rounded px-2 py-1">Lihat</button></noscript>
        </form>
        <?php $canEditBoard = $activeDiv !== null && in_array($activeDiv, $editable, true); ?>
        <?php if ($canEditBoard): ?>
        <button onclick="addWhiteboardNote()" class="bg-yellow-100 text-yellow-700 hover:bg-yellow-200 border border-yellow-300 px-3 py-2 rounded-lg text-sm font-medium shadow-sm transition">
            <i class="fa-solid fa-note-sticky mr-1"></i> + Catatan
        </button>
        <?php else: ?>
        <span class="text-[11px] bg-slate-100 border border-slate-200 text-slate-500 px-3 py-2 rounded-lg"><i class="fa-solid fa-eye mr-1"></i>Mode lihat saja (divisi lain)</span>
        <?php endif; ?>
    </div>
    <p class="text-slate-500 text-sm">Seret kepala catatan untuk memindah — posisi tersimpan otomatis.</p>
</div>

<div id="whiteboard-canvas" data-editable="<?= $canEditBoard ? '1' : '0' ?>" data-division="<?= (int)($activeDiv ?? 0) ?>"
     class="flex-1 min-h-[70vh] relative bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIyMCIgaGVpZ2Q9IjIwIj48cGF0aCBmaWxsPSJub25lIiBzdHJva2U9IiNlMmU4ZjAiIHN0cm9rZS13aWR0aD0iMSIgZD0iTSAyMCAwIEwwIDAgMCAyMCIvPjwvc3ZnPg==')] bg-white border-2 border-slate-300 rounded-xl overflow-hidden shadow-inner">
<?php
$colorMap = [
    'yellow' => 'bg-yellow-200 border-yellow-300',
    'pink'   => 'bg-pink-200 border-pink-300',
    'blue'   => 'bg-blue-200 border-blue-300',
    'green'  => 'bg-green-200 border-green-300',
];
foreach ($notes as $n):
    $noteEditable = $canEditBoard;
?>
    <div class="sticky-note <?= $colorMap[$n['warna']] ?? $colorMap['yellow'] ?> rounded p-3 <?= ((int)$n['id'] % 2) ? 'rotate-1' : '-rotate-1' ?>"
         style="left: <?= (int)$n['pos_x'] ?>px; top: <?= (int)$n['pos_y'] ?>px; width: <?= (int)($n['lebar'] ?: 192) ?>px;" data-id="<?= (int)$n['id'] ?>">
        <div class="flex justify-between items-start mb-1 cursor-grab handler-area">
            <span class="text-[9px] bg-white/50 text-slate-800 px-1 rounded font-bold uppercase tracking-wider truncate"><?= e($n['author_nama']) ?></span>
            <?php if ($noteEditable): ?>
            <span class="flex items-center gap-1.5">
                <button class="text-[9px] text-slate-600 hover:text-blue-700 font-bold" onclick="cycleNoteColor(<?= (int)$n['id'] ?>)" title="Ganti warna"><i class="fa-solid fa-palette"></i></button>
                <i class="fa-solid fa-xmark text-slate-500 cursor-pointer hover:text-red-500" onclick="deleteNote(<?= (int)$n['id'] ?>)" title="Hapus"></i>
            </span>
            <?php endif; ?>
        </div>
        <textarea class="text-sm font-medium leading-tight text-slate-800" spellcheck="false" <?= $noteEditable ? '' : 'disabled' ?>
                  oninput="this.style.height='auto'; this.style.height=this.scrollHeight+'px';"
                  onblur="saveNoteText(<?= (int)$n['id'] ?>, this.value)"><?= e($n['isi']) ?></textarea>
        <?php if ($noteEditable): ?>
        <span class="resize-handle" title="Seret untuk atur lebar"><i class="fa-solid fa-grip-lines-vertical rotate-45 text-[10px]"></i></span>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>

<script>
const NOTE_COLORS = ['yellow', 'pink', 'blue', 'green'];
const BOARD_EDITABLE = document.getElementById('whiteboard-canvas').dataset.editable === '1';
const BOARD_DIVISION = document.getElementById('whiteboard-canvas').dataset.division;

async function addWhiteboardNote() {
    const res = await post('/api/notes', { division_id: BOARD_DIVISION, isi: '' });
    if (res.ok) location.reload();
}
async function deleteNote(id) {
    if (!confirm('Hapus catatan ini?')) return;
    const res = await post(`/api/notes/${id}/delete`);
    if (res.ok) location.reload(); else showToast('Gagal menghapus catatan', true);
}
async function saveNoteText(id, value) {
    const res = await post(`/api/notes/${id}/text`, { isi: value });
    if (res.ok) showToast('Catatan tersimpan');
}
async function cycleNoteColor(id) {
    const el = document.querySelector(`.sticky-note[data-id='${id}']`);
    const cur = ['yellow','pink','blue','green'].find(c => el.className.includes(`bg-${c}-200`)) || 'yellow';
    const next = NOTE_COLORS[(NOTE_COLORS.indexOf(cur) + 1) % NOTE_COLORS.length];
    const res = await post(`/api/notes/${id}/color`, { warna: next });
    if (res.ok) location.reload();
}

/* Drag & drop tersimpan asinkron */
let dragNoteId = null, dragOffX = 0, dragOffY = 0, dirtyNote = null;

/* Tinggi catatan otomatis mengikuti teks */
function fitTextarea(ta) {
    if (!ta) return;
    ta.style.height = 'auto';
    ta.style.height = (ta.scrollHeight + 2) + 'px';
}
document.querySelectorAll('.sticky-note textarea').forEach(fitTextarea);
window.addEventListener('load', () => document.querySelectorAll('.sticky-note textarea').forEach(fitTextarea));

document.addEventListener('mousedown', e => {
    if (!BOARD_EDITABLE) return;
    const area = e.target.closest('.handler-area');
    if (!area) return;
    const noteEl = area.closest('.sticky-note');
    dragNoteId = parseInt(noteEl.dataset.id);
    const rect = noteEl.getBoundingClientRect();
    dragOffX = e.clientX - rect.left;
    dragOffY = e.clientY - rect.top;
    e.preventDefault();
});
document.addEventListener('mousemove', e => {
    if (!dragNoteId) return;
    const noteEl = document.querySelector(`.sticky-note[data-id='${dragNoteId}']`);
    const board = document.getElementById('whiteboard-canvas');
    if (noteEl && board) {
        const br = board.getBoundingClientRect();
        noteEl.style.left = `${e.clientX - br.left - dragOffX}px`;
        noteEl.style.top = `${e.clientY - br.top - dragOffY}px`;
        dirtyNote = dragNoteId;
    }
});
document.addEventListener('mouseup', () => {
    if (dragNoteId && dirtyNote !== null) {
        const noteEl = document.querySelector(`.sticky-note[data-id='${dragNoteId}']`);
        post(`/api/notes/${dragNoteId}/move`, { x: parseInt(noteEl.style.left), y: parseInt(noteEl.style.top) });
        dirtyNote = null;
    }
    dragNoteId = null;
});

/* Atur lebar catatan dengan seret sudut kanan-bawah (tersimpan otomatis) */
let resizeNoteId = null, resizeStartX = 0, resizeStartW = 0;
document.addEventListener('mousedown', e => {
    if (!BOARD_EDITABLE) return;
    const handle = e.target.closest('.resize-handle');
    if (!handle) return;
    const noteEl = handle.closest('.sticky-note');
    resizeNoteId = parseInt(noteEl.dataset.id);
    resizeStartX = e.clientX;
    resizeStartW = noteEl.getBoundingClientRect().width;
    e.preventDefault();
    e.stopPropagation();
});
document.addEventListener('mousemove', e => {
    if (!resizeNoteId) return;
    const noteEl = document.querySelector(`.sticky-note[data-id='${resizeNoteId}']`);
    if (noteEl) {
        noteEl.style.width = Math.max(140, Math.min(600, resizeStartW + e.clientX - resizeStartX)) + 'px';
        fitTextarea(noteEl.querySelector('textarea'));
    }
});
document.addEventListener('mouseup', () => {
    if (resizeNoteId) {
        const noteEl = document.querySelector(`.sticky-note[data-id='${resizeNoteId}']`);
        if (noteEl) post(`/api/notes/${resizeNoteId}/size`, { lebar: parseInt(noteEl.style.width) });
        resizeNoteId = null;
    }
});
</script>

<?php layout_footer(); ?>
