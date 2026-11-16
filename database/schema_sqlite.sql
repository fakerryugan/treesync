-- TreeSync SQLite Schema
PRAGMA foreign_keys = OFF;

DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS task_comments;
DROP TABLE IF EXISTS task_assignees;
DROP TABLE IF EXISTS tasks;
DROP TABLE IF EXISTS whiteboard_notes;
DROP TABLE IF EXISTS announcement_reads;
DROP TABLE IF EXISTS announcement_comments;
DROP TABLE IF EXISTS announcement_likes;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS divisions;

CREATE TABLE divisions (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  nama        TEXT NOT NULL,
  kode        TEXT NOT NULL UNIQUE,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE roles (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  nama        TEXT NOT NULL UNIQUE,
  slug        TEXT NOT NULL UNIQUE,
  deskripsi   TEXT NOT NULL DEFAULT '',
  is_system   INTEGER NOT NULL DEFAULT 0,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE permissions (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  kode        TEXT NOT NULL UNIQUE,
  nama        TEXT NOT NULL,
  deskripsi   TEXT NOT NULL DEFAULT ''
);

CREATE TABLE role_permissions (
  role_id       INTEGER NOT NULL,
  permission_id INTEGER NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  username      TEXT NOT NULL UNIQUE,
  email         TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  nama          TEXT NOT NULL,
  jabatan       TEXT NOT NULL,
  role_id       INTEGER NOT NULL,
  division_id   INTEGER NULL,
  parent_id     INTEGER NULL,
  is_ketua      INTEGER NOT NULL DEFAULT 0,
  is_active     INTEGER NOT NULL DEFAULT 1,
  avatar_url    TEXT NULL,
  created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id)     REFERENCES roles(id),
  FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE SET NULL,
  FOREIGN KEY (parent_id)   REFERENCES users(id)     ON DELETE SET NULL
);

CREATE TABLE announcements (
  id                 INTEGER PRIMARY KEY AUTOINCREMENT,
  author_id          INTEGER NOT NULL,
  target_division_id INTEGER NULL,
  judul              TEXT NOT NULL,
  isi                TEXT NOT NULL,
  lampiran           TEXT NULL,
  created_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id)          REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (target_division_id) REFERENCES divisions(id) ON DELETE CASCADE
);

CREATE TABLE announcement_likes (
  announcement_id INTEGER NOT NULL,
  user_id         INTEGER NOT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id, user_id),
  FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
);

CREATE TABLE announcement_comments (
  id              INTEGER PRIMARY KEY AUTOINCREMENT,
  announcement_id INTEGER NOT NULL,
  author_id       INTEGER NOT NULL,
  parent_id       INTEGER NULL,
  isi             TEXT NOT NULL,
  created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (announcement_id) REFERENCES announcements(id)         ON DELETE CASCADE,
  FOREIGN KEY (author_id)       REFERENCES users(id)                 ON DELETE CASCADE,
  FOREIGN KEY (parent_id)       REFERENCES announcement_comments(id) ON DELETE CASCADE
);

CREATE TABLE announcement_reads (
  announcement_id INTEGER NOT NULL,
  user_id         INTEGER NOT NULL,
  read_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id, user_id),
  FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
);

CREATE TABLE whiteboard_notes (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  division_id INTEGER NOT NULL,
  author_id   INTEGER NOT NULL,
  isi         TEXT NOT NULL,
  warna       TEXT NOT NULL DEFAULT 'yellow',
  pos_x       INTEGER NOT NULL DEFAULT 20,
  pos_y       INTEGER NOT NULL DEFAULT 20,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id)   REFERENCES users(id)     ON DELETE CASCADE
);

CREATE TABLE tasks (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  assigner_id INTEGER NOT NULL,
  assignee_id INTEGER NULL,
  judul       TEXT NOT NULL,
  deskripsi   TEXT NOT NULL DEFAULT '',
  status      TEXT NOT NULL DEFAULT 'TODO',
  lampiran    TEXT NULL,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (assigner_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (assignee_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE task_assignees (
  task_id INTEGER NOT NULL,
  user_id INTEGER NOT NULL,
  PRIMARY KEY (task_id, user_id),
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE task_comments (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  task_id    INTEGER NOT NULL,
  author_id  INTEGER NOT NULL,
  isi        TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id)   REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE audit_logs (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NULL,
  aksi       TEXT NOT NULL,
  detail     TEXT NULL,
  ip_address TEXT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

PRAGMA foreign_keys = ON;
