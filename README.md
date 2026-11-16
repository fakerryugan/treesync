# TreeSync — Aplikasi Kolaborasi Organisasi (PHP + MySQL)

Pengembangan dari prototype `tree.html` + `spec.md` menjadi aplikasi web siap pakai:
papan pengumuman berjenjang ala feed, whiteboard per divisi, delegasi tugas Kanban,
dan **administrasi pengguna, peran, serta izin akses (RBAC) dinamis**.

## Arsitektur

| Komponen | Teknologi |
| :--- | :--- |
| Backend | PHP 8.1+ (native, tanpa framework — ringan & mudah di-deploy) |
| Database | MySQL 8.x (hierarki memakai `WITH RECURSIVE`) |
| Frontend | Server-rendered + Tailwind CSS + vanilla JS (drag & drop HTML5) |
| Keamanan | PDO prepared statement, `htmlspecialchars` anti-XSS, CSRF token, `password_hash`, `.env` |

```
treesync/
├── app/
│   ├── config.php        # bootstrap + katalog izin
│   ├── db.php            # PDO helper
│   ├── helpers.php       # escaping, CSRF, flash, audit log
│   ├── auth.php          # sesi login
│   ├── rbac.php          # pemeriksaan izin & kewenangan divisi
│   ├── org.php           # kueri pohon organisasi (recursive CTE)
│   └── controllers/      # org, feed, whiteboard, tasks, admin
├── database/
│   ├── schema.sql        # skema MySQL
│   └── seed.sql          # data dummy (entitas fiktif)
├── public/               # DOCROOT (index.php, uploads/)
└── views/                # layout + halaman
```

## 3 Jenis Pengguna

### 1. Administrator (`admin`)
- **Kelola Pengguna**: tambah/edit akun — nama, jabatan, divisi, **peran akses**, **atasan langsung** (posisi di pohon), status ketua, aktif/nonaktif, reset password. Ada validasi anti-referensi-melingkar (`A -> B -> A`) dan larangan hapus akun yang masih punya bawahan.
- **Kelola Peran & Izin**: buat peran baru, edit peran, centang **izin apa saja yang bisa diakses** (mis. lihat semua pengumuman, publikasi organisasi, edit whiteboard lintas divisi, delegasi bebas, dll). Peran bawaan tidak bisa dihapus; peran kustom bebas.
- **Kelola Divisi**: tambah/edit/hapus divisi.

### 2. Ketua / Atasan (mis. `budi`, `siti`)
- Melihat pengumuman **divisinya + seluruh divisi di bawah jalur komandonya** (sub-tree), atau **semua divisi** bila perannya diberi izin `announcements.view_all`.
- Mempublikasikan pengumuman ke sub-tree / seluruh organisasi (sesuai izin), melihat progres keterbacaan, mendelegasikan tugas ke bawahan.

### 3. Karyawan (mis. `agus`, `tono`)
- Melihat pengumuman divisinya, suka/komentar/balasan (maks. 2 level), konfirmasi "Sudah Dibaca".
- Kolaborasi whiteboard divisinya, menggeser tugas miliknya di Kanban.
- **Karyawan pun bisa melihat divisi lain** bila perannya diberi kewenangan — contoh di data dummy: `maya` (role *Manajer Lintas Divisi* melihat semua pengumuman + whiteboard, walau jabatannya staf).

## Instalasi (Linux — LEMP)

```bash
# 1) Dependensi
sudo apt install -y php8.2-fpm php8.2-mysql mysql-server nginx

# 2) Siapkan database + data dummy
cd treesync
bash setup.sh          # atau: mysql < database/schema.sql && mysql treesync < database/seed.sql
cp .env.example .env   # sesuaikan DB_USER/DB_PASS hasil setup.sh

# 3) Jalankan cepat untuk uji coba (tanpa nginx)
php -S 127.0.0.1:8000 -t public
# buka http://127.0.0.1:8000  (login: admin/admin123)

# 4) Produksi: konfigurasi Nginx di bawah, lalu
sudo systemctl restart nginx php8.2-fpm
```

## Instalasi (Linux — LERD / Podman dev environment)

Aplikasi ini juga sudah disiapkan untuk lingkungan **Lerd** (podman-powered PHP):

```bash
cd treesync
lerd link treesync     # daftarkan situs, docroot otomatis terdeteksi: public/
lerd db:create treesync
bash setup.sh          # buat skema + data dummy (memakai shim mysql lerd)
cp .env.example .env   # lerd memakai: DB_HOST=lerd-mysql, DB_USER=root, DB_PASS=lerd

# nonaktifkan xdebug step-debug bila semua request web tampak menggantung
lerd xdebug status     # jika tertulis mode=debug padahal "disabled", edit:
#   ~/.local/share/lerd/php/8.5/99-xdebug.ini  ->  xdebug.mode=off, lalu `lerd restart treesync`
```

Situs tersedia di **http://treesync.test** (HTTPS via `lerd secure`).

### Contoh server block Nginx

```nginx
server {
    listen 80;
    server_name treesync.local;
    root /var/www/treesync/public;   # PENTING: arahkan ke folder public/
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(env|git) { deny all; }
}
```

## Akun Demo (data dummy)

| Username | Password | Peran | Keterangan |
| :--- | :--- | :--- | :--- |
| `admin` | `admin123` | Administrator | Panel pengguna/peran/divisi |
| `budi` | `password123` | Pimpinan | Root organisasi, lihat semua |
| `siti` | `password123` | Kepala Divisi | Kadiv Riset |
| `andi` | `password123` | Kepala Divisi | Kadiv Teknis |
| `rina` | `password123` | Kepala Divisi | Kadiv Keuangan |
| `agus` | `password123` | Karyawan | Staf Riset |
| `tono` | `password123` | Karyawan | Staf Teknis |
| `maya` | `password123` | Manajer Lintas Divisi | Contoh karyawan ber-kewenangan lintas divisi |

## Katalog Izin (dinamis)

| Kode | Efek |
| :--- | :--- |
| `users.manage` | Kelola pengguna |
| `divisions.manage` | Kelola divisi |
| `roles.manage` | Kelola peran & izin |
| `announcements.view_all` | Lihat pengumuman semua divisi |
| `announcements.publish_all` | Publikasi ke seluruh organisasi |
| `announcements.publish_division` | Publikasi ke divisinya/sub-tree |
| `whiteboard.view_all` | Lihat whiteboard divisi lain |
| `whiteboard.edit_all` | Edit whiteboard divisi mana pun |
| `tasks.view_all` | Lihat semua tugas |
| `tasks.assign_all` | Delegasi tugas lintas sub-tree |
| `org.view_all` | Lihat pohon organisasi lengkap |

> Ketua **tanpa izin khusus** pun otomatis melihat divisi bawahannya lewat pohon organisasi
> (`parent_id`). Izin `*_all` menambah jangkauan lintas divisi — atur lewat halaman Peran.

## Catatan Keamanan

- Semua output data pengguna melalui `e()` (`htmlspecialchars`) — anti-XSS.
- Semua query memakai PDO prepared statement — anti SQL injection.
- Semua form/aksi POST diverifikasi CSRF token.
- Password memakai `password_hash` (bcrypt); sesi `httponly` + regenerasi ID saat login.
- `.env` tidak terekspos (docroot hanya `public/`, server block menolak file tersembunyi).
- Upload gambar tervalidasi MIME + batas 3 MB.
- Perubahan pengguna/peran/divisi tercatat di tabel `audit_logs`.
