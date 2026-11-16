USE treesync;

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

INSERT INTO divisions (id, nama, kode) VALUES
 (1, 'Manajemen Pusat',   'MGT'),
 (2, 'Divisi Riset',      'RSC'),
 (3, 'Divisi Teknis',     'TSC'),
 (4, 'Divisi Keuangan',   'FIN'),
 (5, 'Divisi SDM',        'HRD');

INSERT INTO roles (id, nama, slug, deskripsi, is_system) VALUES
 (1, 'Administrator',     'administrator', 'Kelola pengguna, divisi, peran, dan seluruh data.', 1),
 (2, 'Pimpinan',          'pimpinan',      'Root organisasi: lihat & publikasikan ke seluruh organisasi.', 1),
 (3, 'Kepala Divisi',     'kepala-divisi', 'Kelola sub-tree divisinya dan publikasi tingkat divisi.', 1),
 (4, 'Manajer Lintas Divisi', 'manajer-lintas-divisi', 'Karyawan dengan kewenangan lintas divisi (contoh role kustom).', 0),
 (5, 'Karyawan',          'karyawan',      'Akses dasar: baca pengumuman, kolaborasi, kerjakan tugas.', 1);

INSERT INTO permissions (id, kode, nama, deskripsi) VALUES
 (1, 'users.manage',           'Kelola Pengguna',       'Menambah, mengedit, dan menonaktifkan akun pengguna.'),
 (2, 'roles.manage',           'Kelola Peran & Izin',   'Mengatur peran dan pemetaan izin.'),
 (3, 'divisions.manage',       'Kelola Divisi',         'Mengatur daftar divisi perusahaan.'),
 (4, 'announcements.publish_all','Publikasi Seluruh Org','Membuat pengumuman yang ditujukan ke seluruh organisasi.'),
 (5, 'announcements.publish_division','Publikasi per Divisi','Membuat pengumuman untuk divisinya sendiri.'),
 (6, 'announcements.view_all', 'Lihat Semua Pengumuman','Melihat seluruh pengumuman tanpa dibatasi hierarki.'),
 (7, 'whiteboard.manage',      'Kelola Whiteboard',     'Menambah, menggeser, dan menghapus sticky note.'),
 (8, 'whiteboard.view',        'Lihat Whiteboard',      'Melihat sticky note divisi.'),
 (9, 'tasks.view_all',         'Lihat Semua Tugas',     'Melihat tugas seluruh organisasi.'),
 (10,'tasks.assign_all',       'Delegasi Bebas',        'Mendelegasikan tugas tanpa batasan sub-tree.'),
 (11,'org.view_all',           'Lihat Seluruh Pohon',   'Melihat struktur organisasi lengkap.'),
 (12,'org.manage',             'Kelola Struktur Organisasi','Mengatur ketua, anggota divisi, dan atasan.');

INSERT INTO role_permissions (role_id, permission_id) VALUES
 (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),
 (2,4),(2,5),(2,7),(2,8),(2,9),(2,10),(2,11),
 (3,5),(3,7),(3,8),
 (4,5),(4,7),(4,8),(4,10),
 (5,7),(5,8);

INSERT INTO users (id, username, email, password_hash, nama, jabatan, role_id, division_id, parent_id, is_ketua, is_active) VALUES
 (1,  'admin', 'admin@treesync.local', '$2y$10$vK3sD4W0e33wK2aZk2k/O.eWp5KcqeTfxh6Z1fP3CZy1h8eS5L.y6', 'Admin Simulasi', 'System Administrator', 1, NULL, NULL, 0, 1),
 (2,  'budi',  'budi@treesync.local',  '$2y$10$fV2.R2zYpP7Q8uF4F7v5.OuG8V8P3zK7s3W9x1f8Zy1h8eS5L.y6a', 'Budi Simulasi',  'Direktur Utama',     2, 1,    NULL, 1, 1),
 (3,  'siti',  'siti@treesync.local',  '$2y$10$fV2.R2zYpP7Q8uF4F7v5.OuG8V8P3zK7s3W9x1f8Zy1h8eS5L.y6a', 'Siti Contoh',   'Kadiv Riset',        3, 2,    2,    1, 1),
 (4,  'andi',  'andi@treesync.local',  '$2y$10$fV2.R2zYpP7Q8uF4F7v5.OuG8V8P3zK7s3W9x1f8Zy1h8eS5L.y6a', 'Andi Fiktif',   'Kadiv Teknis',       3, 3,    2,    1, 1),
 (5,  'rina',  'rina@treesync.local',  '$2y$10$fV2.R2zYpP7Q8uF4F7v5.OuG8V8P3zK7s3W9x1f8Zy1h8eS5L.y6a', 'Rina Dugaan',   'Kadiv Keuangan',     3, 4,    2,    1, 1),
 (6,  'agus',  'agus@treesync.local',  '$2y$10$fV2.R2zYpP7Q8uF4F7v5.OuG8V8P3zK7s3W9x1f8Zy1h8eS5L.y6a', 'Agus Staf A',   'Peneliti Senior',    5, 2,    3,    0, 1),
 (7,  'dewi',  'dewi@treesync.local',  '$2y$10$fV2.R2zYpP7Q8uF4F7v5.OuG8V8P3zK7s3W9x1f8Zy1h8eS5L.y6a', 'Dewi Uji',      'Peneliti Junior',    5, 5,    3,    1, 1),
 (8,  'tono',  'tono@treesync.local',  '$2y$10$fV2.R2zYpP7Q8uF4F7v5.OuG8V8P3zK7s3W9x1f8Zy1h8eS5L.y6a', 'Tono Percobaan','DevOps Engineer',   5, 3,    4,    0, 1),
 (9,  'lina',  'lina@treesync.local',  '$2y$10$fV2.R2zYpP7Q8uF4F7v5.OuG8V8P3zK7s3W9x1f8Zy1h8eS5L.y6a', 'Lina Reviu',    'Backend Developer',  5, 3,    4,    0, 1),
 (10, 'joko',  'joko@treesync.local',  '$2y$10$fV2.R2zYpP7Q8uF4F7v5.OuG8V8P3zK7s3W9x1f8Zy1h8eS5L.y6a', 'Joko Tiruan',   'Akuntan',            5, 4,    5,    0, 1),
 (11, 'maya',  'maya@treesync.local',  '$2y$10$fV2.R2zYpP7Q8uF4F7v5.OuG8V8P3zK7s3W9x1f8Zy1h8eS5L.y6a', 'Maya Sampel',   'Auditor Internal',   4, 4,    5,    0, 1);

INSERT INTO announcements (id, author_id, target_division_id, judul, isi, lampiran) VALUES
 (1, 2, NULL, 'Kick-off Transformasi Digital 2026',
     'Seluruh divisi diharapkan menyelaraskan KPI dengan inisiatif efisiensi operasional. Detail pedoman menyusul.',
     NULL),
 (2, 3, 2, 'Workshop Metodologi Riset',
     'Khusus tim riset: sesi pemantapan metodologi kualitatif & kuantitatif diadakan hari Kamis pukul 09.00 WIB.',
     NULL),
 (3, 4, 3, 'Maintenance Server Internal',
     'Server staging akan di-restart malam ini pukul 22.00 WIB untuk patch keamanan. Mohon push pekerjaan sebelum jam tsb.',
     NULL);

INSERT INTO announcement_likes (announcement_id, user_id) VALUES
 (1, 3),(1, 4),(1, 5),(2, 6),(2, 7);

INSERT INTO announcement_comments (announcement_id, author_id, parent_id, isi) VALUES
 (1, 3, NULL, 'Siap, divisi riset akan menyelaraskan agenda kuartal ini.'),
 (1, 4, NULL, 'Tim teknis sedang menyiapkan infrastruktur pendukung.'),
 (1, 6, 1,    'Noted Bu Siti, materi presentasi sudah kami siapkan.');

INSERT INTO announcement_reads (announcement_id, user_id) VALUES
 (1, 2),(1, 3),(1, 4),(1, 5),(1, 6),(2, 3),(2, 6),(2, 7),(3, 4),(3, 8);

INSERT INTO whiteboard_notes (division_id, author_id, isi, warna, pos_x, pos_y) VALUES
 (2, 3, 'Fokus Q4: Validasi model A/B testing', 'yellow', 20, 20),
 (2, 6, 'Deadline draft laporan: Jumat 16.00', 'blue', 240, 40),
 (3, 4, 'Migrasi server ke data center baru', 'pink', 30, 30),
 (3, 9, 'Ganti switch lantai 2', 'green', 380, 200),
 (4, 10, 'Rekonsiliasi kas November', 'yellow', 140, 60);

INSERT INTO tasks (assigner_id, assignee_id, judul, deskripsi, status) VALUES
 (3, 6,  'Susun Laporan Riset',  'Kompilasi hasil riset pasar kuartal III.', 'TODO'),
 (3, 7,  'Bersihkan Dataset',    'Hapus duplikat dan validasi responden ganda.', 'IN_PROGRESS'),
 (4, 8,  'Setup Monitoring',     'Pasang dashboard monitoring uptime server.', 'TODO'),
 (4, 9,  'Audit Jaringan',       'Pemetaan perangkat jaringan gedung A.', 'DONE'),
 (5, 10, 'Rekonsiliasi Bulanan', 'Rekonsiliasi mutasi kas bulan Oktober.', 'IN_PROGRESS'),
 (2, 3,  'Draft Anggaran 2027',  'Susun draf anggaran divisi untuk tahun depan.', 'TODO');

INSERT INTO task_assignees (task_id, user_id)
SELECT id, assignee_id FROM tasks WHERE assignee_id IS NOT NULL;

INSERT INTO audit_logs (user_id, aksi, detail) VALUES
 (1, 'SEED', 'Data dummy diinisialisasi');
