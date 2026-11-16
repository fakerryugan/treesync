-- ============================================================
-- TreeSync — Seed Data Dummy (entitas fiktif)
-- Kredensial:
--   admin  / admin123     → Administrator
--   budi   / password123  → Pimpinan (root)
--   siti   / password123  → Kepala Divisi Riset
--   andi   / password123  → Kepala Divisi Teknis
--   rina   / password123  → Kepala Divisi Keuangan
--   agus   / password123  → Staf Riset
--   dewi   / password123  → Staf Riset
--   tono   / password123  → Staf Teknis
--   lina   / password123  → Staf Teknis
--   joko   / password123  → Staf Keuangan
--   maya   / password123  → Staf Keuangan (role lintas divisi)
-- ============================================================
USE treesync;

-- Bersihkan tabel agar seed bisa dijalankan ulang (idempotent)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE audit_logs;
TRUNCATE TABLE announcement_comments;
TRUNCATE TABLE announcement_likes;
TRUNCATE TABLE announcement_reads;
TRUNCATE TABLE announcements;
TRUNCATE TABLE whiteboard_notes;
TRUNCATE TABLE task_comments;
TRUNCATE TABLE task_assignees;
TRUNCATE TABLE tasks;
TRUNCATE TABLE users;
TRUNCATE TABLE role_permissions;
TRUNCATE TABLE roles;
TRUNCATE TABLE permissions;
TRUNCATE TABLE divisions;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------- Divisi ----------------
INSERT INTO divisions (id, nama, kode) VALUES
 (1, 'Manajemen Pusat',   'MGT'),
 (2, 'Divisi Riset',      'RSC'),
 (3, 'Divisi Teknis',     'TSC'),
 (4, 'Divisi Keuangan',   'FIN'),
 (5, 'Divisi SDM',        'HRD');

-- ---------------- Peran ----------------
INSERT INTO roles (id, nama, slug, deskripsi, is_system) VALUES
 (1, 'Administrator',     'administrator', 'Kelola pengguna, divisi, peran, dan seluruh data.', 1),
 (2, 'Pimpinan',          'pimpinan',      'Root organisasi: lihat & publikasikan ke seluruh organisasi.', 1),
 (3, 'Kepala Divisi',     'kepala-divisi', 'Kelola sub-tree divisinya dan publikasi tingkat divisi.', 1),
 (4, 'Manajer Lintas Divisi', 'manajer-lintas-divisi', 'Karyawan dengan kewenangan lintas divisi (contoh role kustom).', 0),
 (5, 'Karyawan',          'karyawan',      'Akses dasar: baca pengumuman, kolaborasi, kerjakan tugas.', 1);

-- ---------------- Katalog Izin ----------------
INSERT INTO permissions (id, kode, nama, deskripsi) VALUES
 (1, 'users.manage',            'Kelola Pengguna',       'Tambah, ubah, nonaktifkan pengguna beserta atasan & divisinya.'),
 (2, 'divisions.manage',        'Kelola Divisi',         'Tambah, ubah, hapus divisi organisasi.'),
 (3, 'roles.manage',            'Kelola Peran & Izin',   'Buat peran baru, atur izin akses per peran.'),
 (4, 'announcements.view_all',  'Lihat Semua Pengumuman','Melihat pengumuman dari seluruh divisi.'),
 (5, 'announcements.publish_all','Publikasi Organisasi', 'Membuat pengumuman untuk seluruh organisasi.'),
 (6, 'announcements.publish_division','Publikasi Divisi','Membuat pengumuman untuk divisi/sub-tree miliknya.'),
 (7, 'whiteboard.view_all',     'Lihat Semua Whiteboard','Melihat whiteboard divisi lain.'),
 (8, 'whiteboard.edit_all',     'Edit Semua Whiteboard', 'Mengubah sticky note di whiteboard divisi mana pun.'),
 (9, 'tasks.view_all',          'Lihat Semua Tugas',     'Melihat tugas seluruh organisasi.'),
 (10,'tasks.assign_all',        'Delegasi Bebas',        'Mendelegasikan tugas tanpa batasan sub-tree (divalidasi juga dari pohon).'),
 (11,'org.view_all',            'Lihat Seluruh Pohon',   'Melihat struktur organisasi lengkap, bukan hanya cabangnya.'),
 (12,'org.manage',              'Kelola Struktur Organisasi','Mengatur ketua, anggota divisi, dan hubungan atasan pada pohon organisasi.');

-- ---------------- Izin per Peran ----------------
INSERT INTO role_permissions (role_id, permission_id) VALUES
 -- Administrator: kelola sistem
 (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),
 -- Pimpinan (root)
 (2,4),(2,5),(2,7),(2,8),(2,9),(2,10),(2,11),
 -- Kepala Divisi
 (3,6),(3,7),(3,9),(3,11),
 -- Manajer Lintas Divisi (contoh kewenangan lintas divisi via role)
 (4,4),(4,7),(4,11);
 -- Karyawan: akses dasar (tanpa izin khusus) — tidak punya baris di sini

-- ---------------- Pengguna ----------------
INSERT INTO users
 (id, username, email, password_hash, nama, jabatan, role_id, division_id, parent_id, is_ketua, is_active) VALUES
 (1, 'admin','admin@treesync.local','$2y$12$gQFidNyva6OjM/Ib/k0CCuF3jf8VN7ovmBPUVx7laaLeBkmGrW8XW','Admin Simulasi','Administrator Sistem',1,NULL,NULL,0,1),
 (2, 'budi', 'budi@treesync.local', '$2y$12$N0A7ZF5ODNk8Xp2ol2C.1eHQ09hf882Y2QhWEjNKJrX0L0KHCJV/e','Budi Simulasi','Direktur Utama',2,1,NULL,1,1),
 (3, 'siti', 'siti@treesync.local', '$2y$12$N0A7ZF5ODNk8Xp2ol2C.1eHQ09hf882Y2QhWEjNKJrX0L0KHCJV/e','Siti Contoh','Kepala Divisi Riset',3,2,2,1,1),
 (4, 'andi', 'andi@treesync.local', '$2y$12$N0A7ZF5ODNk8Xp2ol2C.1eHQ09hf882Y2QhWEjNKJrX0L0KHCJV/e','Andi Fiktif','Kepala Divisi Teknis',3,3,2,1,1),
 (5, 'rina', 'rina@treesync.local', '$2y$12$N0A7ZF5ODNk8Xp2ol2C.1eHQ09hf882Y2QhWEjNKJrX0L0KHCJV/e','Rina Dugaan','Kepala Divisi Keuangan',3,4,2,1,1),
 (6, 'agus', 'agus@treesync.local', '$2y$12$N0A7ZF5ODNk8Xp2ol2C.1eHQ09hf882Y2QhWEjNKJrX0L0KHCJV/e','Agus Staf A','Analis Riset Senior',5,2,3,0,1),
 (7, 'dewi', 'dewi@treesync.local', '$2y$12$N0A7ZF5ODNk8Xp2ol2C.1eHQ09hf882Y2QhWEjNKJrX0L0KHCJV/e','Dewi Uji','Analis Data',5,2,3,0,1),
 (8, 'tono', 'tono@treesync.local', '$2y$12$N0A7ZF5ODNk8Xp2ol2C.1eHQ09hf882Y2QhWEjNKJrX0L0KHCJV/e','Tono Percobaan','Engineer Sistem',5,3,4,0,1),
 (9, 'lina', 'lina@treesync.local', '$2y$12$N0A7ZF5ODNk8Xp2ol2C.1eHQ09hf882Y2QhWEjNKJrX0L0KHCJV/e','Lina Contoh','Engineer Jaringan',5,3,8,0,1),
 (10,'joko', 'joko@treesync.local', '$2y$12$N0A7ZF5ODNk8Xp2ol2C.1eHQ09hf882Y2QhWEjNKJrX0L0KHCJV/e','Joko Pratinjau','Akuntan',5,4,5,0,1),
 (11,'maya', 'maya@treesync.local', '$2y$12$N0A7ZF5ODNk8Xp2ol2C.1eHQ09hf882Y2QhWEjNKJrX0L0KHCJV/e','Maya Sampel','Koordinator Keuangan',4,4,10,0,1);

-- ---------------- Pengumuman ----------------
INSERT INTO announcements (id, author_id, target_division_id, judul, isi, lampiran, created_at) VALUES
 (1, 2, NULL, 'Pembaruan SOP 2026', 'Mohon seluruh anggota organisasi mereview alur kerja baru pada dokumen SOP 2026. Review selesai dilaporkan ke masing-masing atasan langsung paling lambat akhir bulan.', 'https://picsum.photos/seed/sop2026/800/300', NOW() - INTERVAL 2 HOUR),
 (2, 3, 2, 'Rapat Mingguan Riset', 'Rapat rutin Divisi Riset dipindah ke hari Kamis pukul 10.00 di ruang Rapat A. Mohon hadir tepat waktu membawa progres penelitian masing-masing.', NULL, NOW() - INTERVAL 5 HOUR),
 (3, 4, 3, 'Maintenance Server Berkala', 'Server produksi akan di-maintenance Sabtu malam (22.00-01.00). Dampak: layanan internal tidak tersedia sementara. Mohon simpan pekerjaan sebelum jadwal tersebut.', 'https://picsum.photos/seed/server/800/300', NOW() - INTERVAL 1 DAY),
 (4, 5, 4, 'Deadline Reimbursement Q4', 'Pengajuan reimbursement kuartal IV ditutup tanggal 25. Lampirkan bukti transaksi lengkap agar tidak terjadi penundaan proses.', NULL, NOW() - INTERVAL 2 DAY),
 (5, 3, 2, 'Hasil Survey Kepuasan Internal', 'Terima kasih atas partisipasi survey kemarin. Skor rata-rata 4,2/5. Poin perbaikan utama: ketersediaan alat kerja dan jadwal fleksibel. Tindak lanjut akan dibahas pada rapat divisi.', NULL, NOW() - INTERVAL 3 DAY);

INSERT INTO announcement_likes (announcement_id, user_id) VALUES
 (1,3),(1,6),(1,8),(2,6),(2,7),(3,9),(4,10),(4,11),(5,6),(5,7);

INSERT INTO announcement_reads (announcement_id, user_id) VALUES
 (1,3),(1,4),(1,5),(1,6),(2,6),(2,7),(3,9),(3,10),(4,10);

INSERT INTO announcement_comments (id, announcement_id, author_id, parent_id, isi, created_at) VALUES
 (1, 1, 3, NULL, 'Baik Pak, tim riset segera menyesuaikan dokumen SOP.', NOW() - INTERVAL 1 HOUR),
 (2, 1, 4, NULL, 'Noted, divisi teknis akan review bagian infrastruktur.', NOW() - INTERVAL 50 MINUTE),
 (3, 1, 6, 1,   'Siap Bu, saya yang koordinasikan untuk tim analisis.', NOW() - INTERVAL 40 MINUTE),
 (4, 2, 6, NULL, 'Hadir Bu, terima kasih info jadwalnya.', NOW() - INTERVAL 4 HOUR),
 (5, 3, 8, NULL, 'Siap, saya siapkan checklist backup sebelum maintenance.', NOW() - INTERVAL 20 HOUR),
 (6, 4, 10, NULL, 'Terima kasih pengingatnya, nanti saya susun kan pertengahan bulan.', NOW() - INTERVAL 1 DAY);

-- ---------------- Whiteboard ----------------
INSERT INTO whiteboard_notes (division_id, author_id, isi, warna, pos_x, pos_y) VALUES
 (2, 6, 'Cek ulang data survei Q3', 'yellow', 60, 40),
 (2, 7, 'Normalisasi dataset responden', 'blue', 320, 120),
 (2, 3, 'Prioritas: laporan tren pasar', 'pink', 180, 260),
 (3, 8, 'Pantau integrasi API pembayaran', 'blue', 100, 80),
 (3, 9, 'Ganti switch lantai 2', 'green', 380, 200),
 (4, 10, 'Rekonsiliasi kas November', 'yellow', 140, 60);

-- ---------------- Tugas Kanban ----------------
INSERT INTO tasks (assigner_id, assignee_id, judul, deskripsi, status) VALUES
 (3, 6,  'Susun Laporan Riset',  'Kompilasi hasil riset pasar kuartal III.', 'TODO'),
 (3, 7,  'Bersihkan Dataset',    'Hapus duplikat dan validasi responden ganda.', 'IN_PROGRESS'),
 (4, 8,  'Setup Monitoring',     'Pasang dashboard monitoring uptime server.', 'TODO'),
 (4, 9,  'Audit Jaringan',       'Pemetaan perangkat jaringan gedung A.', 'DONE'),
 (5, 10, 'Rekonsiliasi Bulanan', 'Rekonsiliasi mutasi kas bulan Oktober.', 'IN_PROGRESS'),
 (2, 3,  'Draft Anggaran 2027',  'Susun draf anggaran divisi untuk tahun depan.', 'TODO');

INSERT INTO task_assignees (task_id, user_id)
SELECT id, assignee_id FROM tasks WHERE assignee_id IS NOT NULL;

-- ---------------- Audit dummy ----------------
INSERT INTO audit_logs (user_id, aksi, detail) VALUES
 (1, 'SEED', 'Data dummy diinisialisasi');
