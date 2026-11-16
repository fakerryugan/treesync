    </div>
</main>

<!-- Bottom Navigation Bar untuk Tampilan Mobile -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-30 bg-white/95 backdrop-blur-md border-t border-slate-200 px-1 py-1.5 flex items-center justify-around shadow-lg">
    <a href="<?= base_url('/pengumuman') ?>" class="flex flex-col items-center justify-center flex-1 py-1 <?= ($active ?? '') === 'pengumuman' ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-slate-800' ?>">
        <i class="fa-solid fa-bullhorn text-sm mb-0.5"></i>
        <span class="text-[9px]">Pengumuman</span>
    </a>
    <a href="<?= base_url('/tugas') ?>" class="flex flex-col items-center justify-center flex-1 py-1 <?= ($active ?? '') === 'tugas' ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-slate-800' ?>">
        <i class="fa-solid fa-list-check text-sm mb-0.5"></i>
        <span class="text-[9px]">Tugas</span>
    </a>
    <a href="<?= base_url('/struktur') ?>" class="flex flex-col items-center justify-center flex-1 py-1 <?= ($active ?? '') === 'struktur' ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-slate-800' ?>">
        <i class="fa-solid fa-sitemap text-sm mb-0.5"></i>
        <span class="text-[9px]">Struktur</span>
    </a>
    <a href="<?= base_url('/whiteboard') ?>" class="flex flex-col items-center justify-center flex-1 py-1 <?= ($active ?? '') === 'whiteboard' ? 'text-blue-600 font-bold' : 'text-slate-500 hover:text-slate-800' ?>">
        <i class="fa-solid fa-chalkboard text-sm mb-0.5"></i>
        <span class="text-[9px]">Board</span>
    </a>
    <button type="button" onclick="toggleMobileSidebar(true)" class="flex flex-col items-center justify-center flex-1 py-1 text-slate-500 hover:text-slate-800">
        <i class="fa-solid fa-bars text-sm mb-0.5"></i>
        <span class="text-[9px]">Menu</span>
    </button>
</nav>

<style>
    .nav-link { display: flex; align-items: center; width: 100%; padding: .625rem .75rem; border-radius: .75rem; font-size: .875rem; font-weight: 500; color: #475569; transition: all .15s; }
    .nav-link:hover { background: #f1f5f9; color: #1e293b; }
    .nav-link.active { background: #eff6ff; color: #1d4ed8; font-weight: 600; }
</style>
<script>
/* ============ Util UI ============ */
const CSRF = <?= json_encode(csrf_token()) ?>;
const BASE = <?= json_encode(rtrim(base_url(''), '/')) ?>;

function post(path, data = {}) {
    const body = new URLSearchParams({ _csrf: CSRF, ...data });
    return fetch(BASE + path, { method: 'POST', body, headers: { 'X-CSRF-Token': CSRF } });
}

function showToast(msg, isError = false) {
    const toast = document.getElementById('toast');
    document.getElementById('toast-msg').innerText = msg;
    document.getElementById('toast-icon').className = 'mr-2.5 text-base flex-shrink-0 ' + (isError ? 'fa-solid fa-circle-exclamation text-red-400' : 'fa-solid fa-circle-check text-green-400');
    toast.classList.remove('translate-x-full', 'opacity-0');
    clearTimeout(toast._t);
    toast._t = setTimeout(() => toast.classList.add('translate-x-full', 'opacity-0'), 3000);
}

let modalConfirmAction = null;
function openModal(title, htmlContent, onConfirm) {
    document.getElementById('modal-title').innerText = title;
    document.getElementById('modal-body').innerHTML = htmlContent;
    const modal = document.getElementById('custom-modal');
    modal.classList.remove('hidden');
    setTimeout(() => { modal.classList.remove('opacity-0'); document.getElementById('custom-modal-content').classList.remove('scale-95'); }, 10);
    document.getElementById('modal-confirm').onclick = () => { if (onConfirm) onConfirm(); };
}

function closeModal() {
    const modal = document.getElementById('custom-modal');
    modal.classList.add('opacity-0');
    document.getElementById('custom-modal-content').classList.add('scale-95');
    setTimeout(() => modal.classList.add('hidden'), 200);
}

/**
 * Kontrol Buka/Tutup Sidebar Mobile Drawer
 */
function toggleMobileSidebar(open) {
    const sidebar = document.getElementById('app-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    if (!sidebar || !backdrop) return;

    const willOpen = (open !== undefined) ? open : sidebar.classList.contains('-translate-x-full');
    if (willOpen) {
        sidebar.classList.remove('-translate-x-full');
        backdrop.classList.remove('hidden');
        setTimeout(() => backdrop.classList.remove('opacity-0'), 10);
        document.body.classList.add('overflow-hidden');
    } else {
        sidebar.classList.add('-translate-x-full');
        backdrop.classList.add('opacity-0');
        setTimeout(() => backdrop.classList.add('hidden'), 300);
        document.body.classList.remove('overflow-hidden');
    }
}
</script>
</body>
</html>
