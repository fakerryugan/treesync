<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — TreeSync</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>body{font-family:'Inter',sans-serif}</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-4xl bg-white rounded-2xl shadow-xl overflow-hidden grid md:grid-cols-2">
    <div class="bg-gradient-to-br from-blue-600 to-indigo-700 p-10 text-white hidden md:flex flex-col justify-between">
        <div>
            <i class="fa-solid fa-diagram-project text-3xl"></i>
            <h1 class="text-3xl font-bold mt-4">TreeSync</h1>
            <p class="text-blue-100 mt-2 text-sm leading-relaxed">Platform kolaborasi internal: papan pengumuman berjenjang, whiteboard divisi, dan delegasi tugas berbasis pohon organisasi.</p>
        </div>
        <ul class="text-sm text-blue-100 space-y-2">
            <li><i class="fa-solid fa-check mr-2"></i>Distribusi informasi searah jalur komando</li>
            <li><i class="fa-solid fa-check mr-2"></i>Hak akses dinamis per peran</li>
            <li><i class="fa-solid fa-check mr-2"></i>Delegasi tugas tervalidasi</li>
        </ul>
        <p class="text-[11px] text-blue-200">Simulasi data fiktif — bukan data nyata.</p>
    </div>
    <div class="p-10 flex flex-col justify-center">
        <h2 class="text-2xl font-bold text-slate-800">Masuk ke akun Anda</h2>
        <p class="text-sm text-slate-500 mt-1 mb-6">Gunakan username/email dan password.</p>
        <?php if ($f = flash_get()): ?>
        <div class="mb-4 px-4 py-3 rounded-lg text-sm <?= $f['type'] === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700' ?> border <?= $f['type'] === 'success' ? 'border-green-200' : 'border-red-200' ?>">
            <?= e($f['msg']) ?>
        </div>
        <?php endif; ?>
        <form method="post" action="<?= base_url('/login') ?>" class="space-y-4">
            <?= csrf_field() ?>
            <div>
                <label class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Username / Email</label>
                <input name="username" required autofocus class="mt-1 w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </div>
            <div>
                <label class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Password</label>
                <input type="password" name="password" required class="mt-1 w-full border border-slate-300 rounded-lg px-4 py-2.5 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
            </div>
            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg py-2.5 transition shadow">
                <i class="fa-solid fa-right-to-bracket mr-2"></i> Masuk
            </button>
        </form>

        <div class="mt-6">
            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Masuk cepat (mode demo)</p>
            <div class="grid grid-cols-2 gap-2">
                <button onclick="quickLogin('admin','admin123')" class="text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 rounded-lg px-3 py-2 transition">
                    <i class="fa-solid fa-user-shield mr-1"></i> Admin
                </button>
                <button onclick="quickLogin('budi','password123')" class="text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 rounded-lg px-3 py-2 transition">
                    <i class="fa-solid fa-crown mr-1"></i> Pimpinan
                </button>
                <button onclick="quickLogin('siti','password123')" class="text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 rounded-lg px-3 py-2 transition">
                    <i class="fa-solid fa-user-tie mr-1"></i> Ketua Divisi
                </button>
                <button onclick="quickLogin('agus','password123')" class="text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200 hover:bg-slate-200 rounded-lg px-3 py-2 transition">
                    <i class="fa-solid fa-user mr-1"></i> Karyawan
                </button>
            </div>
        </div>
        <div class="mt-4 text-[11px] text-slate-400 bg-slate-50 border border-slate-100 rounded-lg p-3 leading-relaxed">
            <b class="text-slate-500">Akun lain:</b> andi, rina, dewi, tono, lina, joko, maya — semua <code class="bg-white border border-slate-200 px-1 rounded">password123</code>. Login manual memakai form di atas.
        </div>
        <script>
        function quickLogin(u, p) {
            document.querySelector('input[name=username]').value = u;
            document.querySelector('input[name=password]').value = p;
            document.querySelector('form').submit();
        }
        </script>
    </div>
</div>
</body>
</html>
