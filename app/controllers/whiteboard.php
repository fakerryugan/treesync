<?php
/** Controller: Whiteboard divisi (sticky notes, drag & drop tersimpan asinkron). */

declare(strict_types=1);

const NOTE_COLORS = ['yellow', 'pink', 'blue', 'green'];

function whiteboard_index(): void
{
    require_login();
    $user  = current_user();
    $viewable   = visible_division_ids($user);
    $editable   = editable_division_ids($user);

    if ($viewable === []) {
        layout_header(['title' => 'Whiteboard', 'active' => 'whiteboard']);
        view('whiteboard', ['divisions' => [], 'notes' => [], 'me' => $user, 'activeDiv' => null, 'editable' => $editable]);
        return;
    }

    $divisions = all('SELECT id, nama FROM divisions WHERE id IN (' . implode(',', $viewable) . ') ORDER BY id');

    $requested = (int)($_GET['div'] ?? 0);
    $activeDiv = in_array($requested, $viewable, true) ? $requested : (int)($user['division_id'] ?? 0);
    if (!in_array($activeDiv, $viewable, true)) {
        $activeDiv = (int)$divisions[0]['id'];
    }

    $notes = all(
        'SELECT n.*, u.nama AS author_nama FROM whiteboard_notes n
         JOIN users u ON u.id = n.author_id
         WHERE n.division_id = ? ORDER BY n.id ASC', [$activeDiv]);

    layout_header(['title' => 'Whiteboard Divisi', 'active' => 'whiteboard']);
    view('whiteboard', ['divisions' => $divisions, 'notes' => $notes, 'me' => $user, 'activeDiv' => $activeDiv, 'editable' => $editable]);
}

/** Verifikasi: catatan berada di divisi yang boleh diedit user. */
function assert_note_editable(int $noteId): array
{
    require_login();
    $note = one('SELECT n.*, u.nama AS author_nama FROM whiteboard_notes n JOIN users u ON u.id=n.author_id WHERE n.id=?', [$noteId]);
    if ($note === null) json_error('Catatan tidak ditemukan.', 404);
    if (!in_array((int)$note['division_id'], editable_division_ids(), true)) {
        json_error('Anda tidak berwenang mengubah catatan divisi ini.', 403);
    }
    return $note;
}

function whiteboard_api_create(): void
{
    require_login();
    $user = current_user();
    $divisionId = (int)($_POST['division_id'] ?? 0);
    if (!in_array($divisionId, editable_division_ids(), true)) {
        json_error('Tidak berwenang menambah catatan di divisi ini.', 403);
    }
    $colorReq = $_POST['warna'] ?? 'yellow';
    $color = in_array($colorReq, NOTE_COLORS, true) ? $colorReq : 'yellow';
    q('INSERT INTO whiteboard_notes (division_id, author_id, isi, warna, pos_x, pos_y) VALUES (?,?,?,?,?,?)',
        [$divisionId, $user['id'], mb_substr(trim($_POST['isi'] ?? ''), 0, 500), $color,
         20 + random_int(0, 120), 20 + random_int(0, 80)]);
    $id = (int)db()->lastInsertId();
    json_response(['ok' => true, 'id' => $id]);
}

/** PATCH tingkat atribut: hanya posisi (mitigasi race condition). */
function whiteboard_api_move(int $id): void
{
    $note = assert_note_editable($id);
    $x = max(0, min(100000, (int)($_POST['x'] ?? $note['pos_x'])));
    $y = max(0, min(100000, (int)($_POST['y'] ?? $note['pos_y'])));
    q('UPDATE whiteboard_notes SET pos_x=?, pos_y=? WHERE id=?', [$x, $y, $id]);
    json_response(['ok' => true, 'x' => $x, 'y' => $y]);
}

function whiteboard_api_text(int $id): void
{
    assert_note_editable($id);
    q('UPDATE whiteboard_notes SET isi=? WHERE id=?', [mb_substr(trim($_POST['isi'] ?? ''), 0, 500), $id]);
    json_response(['ok' => true]);
}

function whiteboard_api_color(int $id): void
{
    assert_note_editable($id);
    $color = in_array($_POST['warna'] ?? '', NOTE_COLORS, true) ? $_POST['warna'] : 'yellow';
    q('UPDATE whiteboard_notes SET warna=? WHERE id=?', [$color, $id]);
    json_response(['ok' => true, 'warna' => $color]);
}

function whiteboard_api_delete(int $id): void
{
    require_login();
    $user = current_user();
    $note = one('SELECT * FROM whiteboard_notes WHERE id=?', [$id]);
    if ($note === null) json_error('Catatan tidak ditemukan.', 404);
    $mayDelete = (int)$note['author_id'] === (int)$user['id']
        || in_array((int)$note['division_id'], editable_division_ids(), true);
    if (!$mayDelete) json_error('Tidak berwenang menghapus catatan ini.', 403);
    q('DELETE FROM whiteboard_notes WHERE id=?', [$id]);
    json_response(['ok' => true]);
}

/** PATCH tingkat atribut: lebar catatan (tinggi mengikuti panjang teks). */
function whiteboard_api_size(int $id): void
{
    assert_note_editable($id);
    $lebar = max(140, min(600, (int)($_POST['lebar'] ?? 192)));
    q('UPDATE whiteboard_notes SET lebar=? WHERE id=?', [$lebar, $id]);
    json_response(['ok' => true, 'lebar' => $lebar]);
}
