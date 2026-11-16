#!/usr/bin/env bash
# ============================================================
# TreeSync — Skrip persiapan database MySQL (Linux)
# Pemakaian: bash setup.sh
# ============================================================
set -euo pipefail

DB_NAME="${DB_NAME:-treesync}"
DB_USER="${DB_USER:-treesync}"
DB_PASS="${DB_PASS:-treesync_pass}"
MYSQL="mysql"

DIR="$(cd "$(dirname "$0")" && pwd)"

echo "==> [1/3] Membuat database & skema..."
$MYSQL < "$DIR/database/schema.sql"

echo "==> [2/3] Membuat user database khusus aplikasi (tanpa hak admin)..."
$MYSQL <<SQL
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL

echo "==> [3/3] Mengimpor data dummy..."
$MYSQL "$DB_NAME" < "$DIR/database/seed.sql"

echo ""
echo "Selesai! Database '$DB_NAME' siap."
echo "Perbarui .env:  DB_USER=$DB_USER  DB_PASS=$DB_PASS"
echo ""
echo "Akun demo (lihat database/seed.sql):"
echo "  admin / admin123        -> Administrator"
echo "  budi  / password123     -> Pimpinan (root)"
echo "  siti  / password123     -> Kepala Divisi Riset"
echo "  agus  / password123     -> Staf Riset"
echo "  maya  / password123     -> Staf dengan role lintas divisi"
