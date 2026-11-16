    </div>
</main>

<style>
    .nav-link { display: block; width: 100%; padding: .625rem .75rem; border-radius: .5rem; font-size: .875rem; font-weight: 500; color: #475569; transition: colors .15s; }
    .nav-link:hover { background: #f1f5f9; color: #334155; }
    .nav-link.active { background: #eff6ff; color: #1d4ed8; }
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
    document.getElementById('toast-icon').className = 'mr-3 ' + (isError ? 'fa-solid fa-circle-exclamation text-red-400' : 'fa-solid fa-circle-check text-green-400');
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
</script>
</body>
</html>
