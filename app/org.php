<?php
/** Kueri pohon organisasi (WITH RECURSIVE) & render tree. */

declare(strict_types=1);

/** Semua anggota organisasi aktif, lengkap. */
function org_all_users(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = all(
            'SELECT u.id, u.nama, u.jabatan, u.username, u.parent_id, u.division_id, u.is_ketua, u.is_active,
                    r.nama AS role_nama, r.slug AS role_slug, d.nama AS divisi_nama
             FROM users u
             JOIN roles r ON r.id = u.role_id
             LEFT JOIN divisions d ON d.id = u.division_id
             ORDER BY u.id'
        );
    }
    return $cache;
}

/** Keturunan langsung maupun tidak langsung (sub-tree) dari sebuah node. */
function descendant_users(int $rootId): array
{
    if ($rootId <= 0) return [];
    return all(
        'WITH RECURSIVE subtree AS (
             SELECT id FROM users WHERE parent_id = :root
             UNION ALL
             SELECT u.id FROM users u JOIN subtree s ON u.parent_id = s.id
         )
         SELECT u.*, d.nama AS divisi_nama FROM subtree st
         JOIN users u ON u.id = st.id AND u.is_active = 1
         LEFT JOIN divisions d ON d.id = u.division_id',
        [':root' => $rootId]
    );
}

/** Rantai atasan dari root sampai diri sendiri (untuk breadcrumb). */
function ancestor_chain(int $userId): array
{
    return array_reverse(all(
        'WITH RECURSIVE chain AS (
             SELECT id, parent_id, 0 AS lvl FROM users WHERE id = :uid
             UNION ALL
             SELECT p.id, p.parent_id, c.lvl + 1 FROM chain c JOIN users p ON p.id = c.parent_id
         )
         SELECT u.* FROM chain c JOIN users u ON u.id = c.id ORDER BY lvl DESC',
        [':uid' => $userId]
    ));
}

/** Bangun struktur pohon dari daftar user flat (parent_id NULL/0 = root). */
function build_tree(array $users, int $rootId = 0): array
{
    $byParent = [];
    foreach ($users as $u) $byParent[(int)($u['parent_id'] ?? 0)][] = $u;
    $make = function (array $user) use (&$make, $byParent): array {
        $node = $user + ['children' => []];
        foreach ($byParent[(int)$user['id']] ?? [] as $child) {
            $node['children'][] = $make($child);
        }
        return $node;
    };
    $roots = [];
    foreach ($byParent[$rootId] ?? [] as $u) {
        $roots[] = $make($u);
    }
    return $roots;
}

/** Filter pohon: hanya sub-tree yang boleh dilihat user (atau semua jika org.view_all). */
function filter_tree_for(array $trees, array $user): array
{
    if (can(PERM['ORG_VIEW_ALL'], $user)) return $trees;

    $visibleIds = [];
    if ((int)$user['id'] > 0) $visibleIds[] = (int)$user['id'];
    foreach (descendant_users((int)$user['id']) as $d) $visibleIds[] = (int)$d['id'];
    // sertakan rantai atasan agar user melihat posisinya
    foreach (ancestor_chain((int)$user['id']) as $a) $visibleIds[] = (int)$a['id'];
    $visible = array_fill_keys($visibleIds, true);

    $prune = function (array $node) use (&$prune, $visible): ?array {
        $kept = [];
        foreach ($node['children'] as $c) {
            $pr = $prune($c);
            if ($pr !== null) $kept[] = $pr;
        }
        if (!isset($visible[(int)$node['id']]) && $kept === []) return null;
        $node['children'] = $kept;
        return $node;
    };

    $out = [];
    foreach ($trees as $t) {
        $p = $prune($t);
        if ($p !== null) $out[] = $p;
    }
    return $out;
}

/** Render HTML pohon organisasi (mengikuti gaya CSS .org-tree prototype). */
function render_tree_nodes(array $nodes, array $user): string
{
    $myDivId = (int)($user['division_id'] ?? 0);
    $myUid   = (int)($user['id'] ?? 0);
    $html = '';
    foreach ($nodes as $node) {
        $nodeDivId = (int)($node['division_id'] ?? 0);
        $isMyDiv   = $myDivId > 0 && $nodeDivId === $myDivId;
        $isMe      = (int)$node['id'] === $myUid;
        $isAdmin   = $node['division_id'] === null && $node['parent_id'] === null && ($node['role_slug'] ?? '') === 'administrator';

        if ($isAdmin) {
            $border  = 'border-slate-400';
            $bgCard  = 'bg-white';
            $textCol = 'text-slate-500';
            $badge   = 'ADMIN';
            $badgeBg = 'bg-slate-100 text-slate-600';
        } elseif ($isMyDiv) {
            // Divisi karyawan tersebut berwarna ORANGE untuk menandai divisinya
            $border  = 'border-orange-500 ring-2 ring-orange-300';
            $bgCard  = 'bg-orange-50/50';
            $textCol = 'text-orange-700';
            $badge   = $isMe ? 'SAYA' : ($node['is_ketua'] ? 'KETUA' : 'ANGGOTA');
            $badgeBg = $isMe ? 'bg-orange-500 text-white font-extrabold' : ($node['is_ketua'] ? 'bg-orange-100 text-orange-800 font-bold border border-orange-300' : 'bg-orange-100 text-orange-700 font-medium');
        } elseif ($node['is_ketua']) {
            $border  = 'border-blue-500';
            $bgCard  = 'bg-white';
            $textCol = 'text-blue-600';
            $badge   = 'KETUA';
            $badgeBg = 'bg-blue-50 text-blue-700 border border-blue-200';
        } else {
            $border  = 'border-slate-300';
            $bgCard  = 'bg-white';
            $textCol = 'text-slate-500';
            $badge   = 'ANGGOTA';
            $badgeBg = 'bg-slate-100 text-slate-600';
        }

        $inact = !(int)$node['is_active'];
        $divLabel = !empty($node['division_id'])
            ? '<button type="button" onclick="event.stopPropagation(); openDivisionModal(' . (int)$node['division_id'] . ', ' . (int)$node['id'] . ')" class="text-[10px] font-bold ' . $textCol . ' hover:underline inline-flex items-center gap-1 group/btn text-left" title="Klik untuk lihat anggota &amp; ketua divisi ini"><span>' . e($node['divisi_nama']) . '</span><i class="fa-solid fa-arrow-up-right-from-square text-[7px] opacity-70 group-hover/btn:opacity-100"></i></button>'
            : '<span class="text-[10px] font-bold ' . $textCol . '">' . e($node['role_nama']) . '</span>';

        $html .= '<li>';
        $html .= '<div class="org-node cursor-pointer border-t-4 ' . $border . ' ' . $bgCard . ($inact ? ' opacity-50' : '') . '"
                      onclick="showNodeDetail(this.dataset)" data-id="' . (int)$node['id'] . '"
                      data-nama="' . e($node['nama']) . '" data-jabatan="' . e($node['jabatan']) . '"
                      data-divisi="' . e($node['divisi_nama'] ?? '—') . '" data-division-id="' . (int)($node['division_id'] ?? 0) . '"
                      data-role="' . e($node['role_nama']) . '" data-badge="' . $badge . '">';
        $html .= '<div class="flex items-center justify-between mb-1 gap-1">' . $divLabel . '<span class="text-[8px] font-bold px-1.5 py-0.5 rounded ' . $badgeBg . '">' . $badge . '</span></div>';
        $html .= '<div class="font-semibold text-slate-800 text-sm">' . e($node['nama']) . '</div>';
        $html .= '<div class="text-xs text-slate-500 mt-1">' . e($node['jabatan']) . '</div>';
        if ($isMyDiv) {
            $html .= '<div class="mt-2 pt-1.5 border-t border-orange-200/80 text-[9px] font-bold text-orange-600 flex items-center justify-center gap-1"><i class="fa-solid fa-circle-check text-[8px]"></i> Divisi Anda</div>';
        }
        $html .= '</div>';
        if (!empty($node['children'])) {
            $html .= '<ul>' . render_tree_nodes($node['children'], $user) . '</ul>';
        }
        $html .= '</li>';
    }
    return $html;
}
