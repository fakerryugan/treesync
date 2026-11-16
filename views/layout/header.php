<?php /** Layout: header + sidebar + flash. $title, $active. */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?= e($title ?? 'TreeSync') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    body { font-family: 'Inter', sans-serif; }
    .org-tree { display: inline-block; padding: 10px; }
    .org-tree ul { padding-top: 24px; position: relative; transition: all .3s; display: flex; justify-content: center; margin: 0; padding-left: 0; }
    .org-tree li { text-align: center; list-style-type: none; position: relative; padding: 24px 12px 0 12px; transition: all .3s; }
    /* Garis horizontal dan vertikal ke atas untuk setiap cabang */
    .org-tree li::before, .org-tree li::after { content: ''; position: absolute; top: 0; right: 50%; border-top: 2px solid #64748b; width: 50%; height: 24px; z-index: 1; }
    .org-tree li::after { right: auto; left: 50%; border-left: 2px solid #64748b; }
    /* Anak tunggal (only-child): tetap memiliki garis vertikal ke atas menyambung ke atasan */
    .org-tree li:only-child::before { display: none; }
    .org-tree li:only-child::after { display: block; border-top: none; border-left: 2px solid #64748b; left: 50%; width: 0; height: 24px; }
    .org-tree li:only-child { padding-top: 24px; }
    /* Node root paling atas tidak memiliki garis ke atas */
    .org-tree > ul > li::before,
    .org-tree > ul > li::after { display: none !important; }
    .org-tree > ul > li { padding-top: 0 !important; }
    /* Hapus kelebihan garis pada anak pertama dan terakhir */
    .org-tree li:first-child::before, .org-tree li:last-child::after { border: 0 none; }
    .org-tree li:last-child::before { border-right: 2px solid #64748b; border-radius: 0 6px 0 0; }
    .org-tree li:first-child::after { border-radius: 6px 0 0 0; }
    /* Garis vertikal ke bawah dari node induk ke cabang anak-anaknya */
    .org-tree ul ul::before { content: ''; position: absolute; top: 0; left: 50%; border-left: 2px solid #64748b; width: 0; height: 24px; z-index: 1; transform: translateX(-50%); }
    .org-node { position: relative; z-index: 2; display: inline-block; background: #ffffff; border: 1px solid #cbd5e1; border-radius: .75rem; padding: 1rem 1.25rem; box-shadow: 0 4px 6px -1px rgb(0 0 0 / .08); transition: all .2s; min-width: 175px; }
    .org-node:hover { box-shadow: 0 10px 15px -3px rgb(0 0 0 / .12); transform: translateY(-2px); border-color: #3b82f6; }
    .animate-fade-in { animation: fadeIn .3s ease-in-out; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
    .sticky-note { position: absolute; box-shadow: 2px 4px 10px rgb(0 0 0 / .15); cursor: grab; }
    .sticky-note:active { cursor: grabbing; z-index: 100 !important; }
    .sticky-note textarea { resize: none; background: transparent; border: none; outline: none; width: 100%; overflow: hidden; cursor: text; display: block; }
    .resize-handle { position: absolute; right: 2px; bottom: 2px; width: 14px; height: 14px; cursor: nwse-resize; color: rgba(0,0,0,.35); }
    .resize-handle:hover { color: rgba(0,0,0,.7); }
    ::-webkit-scrollbar { width: 6px; height: 6px; }
    ::-webkit-scrollbar-track { background: #f1f5f9; }
    ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    .drag-over { background-color: #e2e8f0 !important; border: 2px dashed #94a3b8 !important; }
</style>
</head>
<body class="bg-slate-50 h-screen overflow-hidden flex text-slate-800">

<!-- Modal global -->
<div id="custom-modal" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-50 hidden items-center justify-center opacity-0 transition-opacity p-3">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[85vh] overflow-y-auto transform scale-95 transition-transform" id="custom-modal-content">
        <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-base text-slate-800" id="modal-title">Judul Modal</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-red-500 transition p-1"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>
        <div class="p-5" id="modal-body"></div>
        <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex justify-end gap-2.5 rounded-b-2xl" id="modal-footer">
            <button onclick="closeModal()" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded-xl transition">Batal</button>
            <button id="modal-confirm" class="px-4 py-2 text-xs font-semibold bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition shadow-xs">Simpan</button>
        </div>
    </div>
</div>

<!-- Toast -->
<div id="toast" class="fixed top-4 right-4 sm:top-6 sm:right-6 bg-slate-800 text-white px-4 py-3 rounded-xl shadow-xl transform transition-transform duration-300 translate-x-full opacity-0 z-50 flex items-center text-xs sm:text-sm max-w-[90vw]">
    <i class="fa-solid fa-circle-check text-green-400 mr-2.5 text-base flex-shrink-0" id="toast-icon"></i>
    <span id="toast-msg" class="font-medium truncate">Notifikasi</span>
</div>

<!-- Backdrop Mobile Sidebar -->
<div id="sidebar-backdrop" onclick="toggleMobileSidebar(false)" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-40 hidden md:hidden transition-opacity duration-300 opacity-0"></div>

<!-- Sidebar Responsif (Drawer pada Mobile, Kolom pada Desktop) -->
<aside id="app-sidebar" class="fixed inset-y-0 left-0 z-50 w-72 bg-white border-r border-slate-200 flex flex-col h-full flex-shrink-0 transform -translate-x-full md:translate-x-0 md:static md:w-64 transition-transform duration-300 ease-in-out shadow-2xl md:shadow-none">
    <div class="h-16 flex items-center justify-between px-6 border-b border-slate-200">
        <a href="<?= base_url('/pengumuman') ?>" class="flex items-center">
            <i class="fa-solid fa-diagram-project text-blue-600 text-xl mr-3"></i>
            <span class="text-xl font-bold text-slate-800 tracking-tight">TreeSync</span>
        </a>
        <button type="button" onclick="toggleMobileSidebar(false)" class="md:hidden text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition" aria-label="Tutup Menu">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>
    <nav class="flex-1 overflow-y-auto py-4">
        <p class="text-[10px] font-bold text-slate-400 px-6 mb-2 uppercase tracking-wider">Kolaborasi</p>
        <ul class="space-y-1 px-3">
            <li><a href="<?= base_url('/pengumuman') ?>" id="btn-pengumuman" class="nav-link <?= ($active ?? '') === 'pengumuman' ? 'active' : '' ?>"><i class="fa-solid fa-bullhorn w-6 text-center mr-2"></i> Pengumuman</a></li>
            <li><a href="<?= base_url('/tugas') ?>" id="btn-tugas" class="nav-link <?= ($active ?? '') === 'tugas' ? 'active' : '' ?>"><i class="fa-solid fa-list-check w-6 text-center mr-2"></i> Delegasi Tugas</a></li>
            <li><a href="<?= base_url('/struktur') ?>" id="btn-struktur" class="nav-link <?= ($active ?? '') === 'struktur' ? 'active' : '' ?>"><i class="fa-solid fa-sitemap w-6 text-center mr-2"></i> Struktur Organisasi</a></li>
            <li><a href="<?= base_url('/whiteboard') ?>" id="btn-whiteboard" class="nav-link <?= ($active ?? '') === 'whiteboard' ? 'active' : '' ?>"><i class="fa-solid fa-chalkboard w-6 text-center mr-2"></i> Whiteboard</a></li>
        </ul>
        <?php if (can(PERM['USERS_MANAGE']) || can(PERM['ROLES_MANAGE']) || can(PERM['DIVISIONS_MANAGE'])): ?>
        <p class="text-[10px] font-bold text-slate-400 px-6 mt-6 mb-2 uppercase tracking-wider">Administrasi</p>
        <ul class="space-y-1 px-3">
            <?php if (can(PERM['USERS_MANAGE'])): ?>
            <li><a href="<?= base_url('/admin/pengguna') ?>" class="nav-link <?= ($active ?? '') === 'admin-pengguna' ? 'active' : '' ?>"><i class="fa-solid fa-users-gear w-6 text-center mr-2"></i> Kelola Pengguna</a></li>
            <?php endif; ?>
            <?php if (can(PERM['ROLES_MANAGE'])): ?>
            <li><a href="<?= base_url('/admin/peran') ?>" class="nav-link <?= ($active ?? '') === 'admin-peran' ? 'active' : '' ?>"><i class="fa-solid fa-user-shield w-6 text-center mr-2"></i> Peran &amp; Izin</a></li>
            <?php endif; ?>
            <?php if (can(PERM['ORG_MANAGE'])): ?>
            <li><a href="<?= base_url('/admin/struktur') ?>" class="nav-link <?= ($active ?? '') === 'admin-struktur' ? 'active' : '' ?>"><i class="fa-solid fa-sitemap w-6 text-center mr-2"></i> Struktur Organisasi</a></li>
            <?php endif; ?>
            <?php if (can(PERM['DIVISIONS_MANAGE'])): ?>
            <li><a href="<?= base_url('/admin/divisi') ?>" class="nav-link <?= ($active ?? '') === 'admin-divisi' ? 'active' : '' ?>"><i class="fa-solid fa-building-user w-6 text-center mr-2"></i> Kelola Divisi</a></li>
            <?php endif; ?>
        </ul>
        <?php endif; ?>
    </nav>
    <div class="p-4 border-t border-slate-200 bg-slate-50">
        <div class="flex items-center">
            <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white border-2 border-white shadow-sm flex-shrink-0 <?= avatar_color((int)$user['id']) ?>"><?= e(initials($user['nama'])) ?></div>
            <div class="ml-3 flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-800 truncate"><?= e($user['nama']) ?></p>
                <p class="text-[11px] text-slate-500 leading-tight truncate"><?= e($user['jabatan']) ?> &bull; <?= e($user['role_nama']) ?></p>
            </div>
        </div>
        <form method="post" action="<?= base_url('/logout') ?>" class="mt-3">
            <?= csrf_field() ?>
            <button class="w-full text-xs font-semibold text-slate-600 hover:text-red-600 bg-white border border-slate-200 hover:border-red-200 rounded-xl py-2 transition shadow-xs flex items-center justify-center gap-1.5">
                <i class="fa-solid fa-right-from-bracket"></i> Keluar
            </button>
        </form>
    </div>
</aside>

<!-- Konten utama -->
<main class="flex-1 flex flex-col h-full overflow-hidden">
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-4 sm:px-6 flex-shrink-0 z-10">
        <div class="flex items-center min-w-0">
            <!-- Hamburger Menu Mobile -->
            <button type="button" onclick="toggleMobileSidebar(true)" class="md:hidden text-slate-600 hover:text-slate-900 p-2 -ml-2 rounded-lg hover:bg-slate-100 transition mr-2 flex-shrink-0" aria-label="Buka Menu">
                <i class="fa-solid fa-bars text-lg"></i>
            </button>
            <a href="<?= base_url('/pengumuman') ?>" class="flex items-center gap-1.5 md:hidden mr-2.5 flex-shrink-0">
                <i class="fa-solid fa-diagram-project text-blue-600 text-base"></i>
                <span class="font-bold text-slate-800 text-sm tracking-tight">TreeSync</span>
            </a>
            <h1 class="text-sm sm:text-base md:text-lg font-bold text-slate-800 truncate"><?= e($title) ?></h1>
        </div>
        <div class="flex items-center gap-2 text-xs text-slate-500 flex-shrink-0">
            <span class="hidden md:inline font-medium"><i class="fa-solid fa-circle-user mr-1 text-blue-500"></i><?= e($user['role_nama']) ?><?= $user['divisi_nama'] ? ' &bull; ' . e($user['divisi_nama']) : '' ?></span>
            <div class="md:hidden flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center font-bold text-white text-[10px] <?= avatar_color((int)$user['id']) ?>" title="<?= e($user['nama']) ?>">
                    <?= e(initials($user['nama'])) ?>
                </div>
            </div>
        </div>
    </header>
    <div class="flex-1 overflow-auto p-3.5 sm:p-6 pb-20 md:pb-6 relative bg-slate-50">
<?php if ($flash = flash_get()): ?>
        <div class="mb-4 px-4 py-3 rounded-xl border text-xs sm:text-sm flex items-center animate-fade-in shadow-xs <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?>">
            <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check text-green-500' : 'fa-circle-exclamation text-red-500' ?> mr-2.5 flex-shrink-0"></i>
            <span class="font-medium"><?= e($flash['msg']) ?></span>
        </div>
<?php endif; ?>
