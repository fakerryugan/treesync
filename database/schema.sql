CREATE DATABASE IF NOT EXISTS treesync
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE treesync;

SET FOREIGN_KEY_CHECKS = 0;

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
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(100) NOT NULL,
  kode        VARCHAR(20)  NOT NULL UNIQUE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE roles (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama        VARCHAR(60)  NOT NULL UNIQUE,
  slug        VARCHAR(60)  NOT NULL UNIQUE,
  deskripsi   VARCHAR(255) NOT NULL DEFAULT '',
  is_system   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode        VARCHAR(60)  NOT NULL UNIQUE,
  nama        VARCHAR(120) NOT NULL,
  deskripsi   VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id       INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id)       REFERENCES roles(id)       ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  nama          VARCHAR(100) NOT NULL,
  jabatan       VARCHAR(100) NOT NULL,
  role_id       INT UNSIGNED NOT NULL,
  division_id   INT UNSIGNED NULL,
  parent_id     INT UNSIGNED NULL,
  is_ketua      TINYINT(1)   NOT NULL DEFAULT 0,
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id)     REFERENCES roles(id),
  FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE SET NULL,
  FOREIGN KEY (parent_id)   REFERENCES users(id)     ON DELETE SET NULL,
  INDEX idx_users_parent (parent_id),
  INDEX idx_users_division (division_id)
) ENGINE=InnoDB;

CREATE TABLE announcements (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id           INT UNSIGNED NOT NULL,
  target_division_id  INT UNSIGNED NULL,
  judul               VARCHAR(150) NOT NULL,
  isi                 TEXT NOT NULL,
  lampiran            VARCHAR(255) NULL,
  created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id)          REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (target_division_id) REFERENCES divisions(id) ON DELETE SET NULL,
  INDEX idx_announcements_author (author_id),
  INDEX idx_announcements_target (target_division_id)
) ENGINE=InnoDB;

CREATE TABLE announcement_likes (
  announcement_id INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id, user_id),
  FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE announcement_comments (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  announcement_id INT UNSIGNED NOT NULL,
  author_id       INT UNSIGNED NOT NULL,
  parent_id       INT UNSIGNED NULL,
  isi             TEXT NOT NULL,
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (announcement_id) REFERENCES announcements(id)      ON DELETE CASCADE,
  FOREIGN KEY (author_id)       REFERENCES users(id)              ON DELETE CASCADE,
  FOREIGN KEY (parent_id)       REFERENCES announcement_comments(id) ON DELETE CASCADE,
  INDEX idx_comments_announcement (announcement_id),
  INDEX idx_comments_parent (parent_id)
) ENGINE=InnoDB;

CREATE TABLE announcement_reads (
  announcement_id INT UNSIGNED NOT NULL,
  user_id         INT UNSIGNED NOT NULL,
  read_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (announcement_id, user_id),
  FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id)         REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE whiteboard_notes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  division_id  INT UNSIGNED NOT NULL,
  author_id    INT UNSIGNED NOT NULL,
  isi          TEXT NOT NULL,
  warna        VARCHAR(20) NOT NULL DEFAULT 'yellow',
  lebar        INT NOT NULL DEFAULT 192,
  pos_x        INT NOT NULL DEFAULT 20,
  pos_y        INT NOT NULL DEFAULT 20,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (division_id) REFERENCES divisions(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id)   REFERENCES users(id)     ON DELETE CASCADE,
  INDEX idx_notes_division (division_id)
) ENGINE=InnoDB;

CREATE TABLE tasks (
  id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  assigner_id        INT UNSIGNED NOT NULL,
  assignee_id        INT UNSIGNED NULL,
  target_division_id INT UNSIGNED NULL,
  judul              VARCHAR(150) NOT NULL,
  deskripsi          TEXT,
  status             VARCHAR(20) NOT NULL DEFAULT 'TODO',
  created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (assigner_id) REFERENCES users(id)     ON DELETE CASCADE,
  FOREIGN KEY (assignee_id) REFERENCES users(id)     ON DELETE SET NULL,
  FOREIGN KEY (target_division_id) REFERENCES divisions(id) ON DELETE SET NULL,
  INDEX idx_tasks_assignee (assignee_id),
  INDEX idx_tasks_assigner (assigner_id),
  INDEX idx_tasks_target_division (target_division_id)
) ENGINE=InnoDB;

CREATE TABLE task_assignees (
  task_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (task_id, user_id),
  FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_task_assignees_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE task_comments (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  task_id    INT UNSIGNED NOT NULL,
  author_id  INT UNSIGNED NOT NULL,
  isi        TEXT NOT NULL,
  tipe       VARCHAR(20) NOT NULL DEFAULT 'COMMENT',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (task_id)   REFERENCES tasks(id) ON DELETE CASCADE,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_task_comments_task (task_id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  aksi       VARCHAR(60)  NOT NULL,
  detail     VARCHAR(255) NOT NULL DEFAULT '',
  ip_address VARCHAR(45)  NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
