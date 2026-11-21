<?php
/**
 * دوال عرض مشتركة لصفحتي المحاضرات والامتحانات
 */

/** يرجع الملفات مجمّعة حسب المادة: [subject_key => [rows...]] */
function files_by_subject(string $kind): array {
    $st = db()->prepare(
        'SELECT f.id, f.subject_key, f.parent_id, f.title, f.ext, f.size, f.user_id, f.created_at, u.full_name
           FROM files f LEFT JOIN users u ON u.id = f.user_id
          WHERE f.kind = ? ORDER BY f.id DESC'
    );
    $st->execute([$kind]);
    $out = [];
    foreach ($st->fetchAll() as $r) { $out[$r['subject_key']][] = $r; }
    return $out;
}

/** الحلول مجمّعة حسب الامتحان: [exam_id => [rows...]] */
function solutions_by_exam(): array {
    $st = db()->query(
        "SELECT f.id, f.parent_id, f.title, f.ext, f.size, f.user_id, f.created_at, u.full_name
           FROM files f LEFT JOIN users u ON u.id = f.user_id
          WHERE f.kind = 'solution' ORDER BY f.id ASC"
    );
    $out = [];
    foreach ($st->fetchAll() as $r) { $out[(int)$r['parent_id']][] = $r; }
    return $out;
}

function can_delete_file(array $row): bool {
    $u = current_user();
    return $u !== null && ((int)$row['user_id'] === (int)$u['id'] || is_admin());
}

function delete_form(int $id): string {
    return '<form method="post" action="delete.php" class="inline-form" '
         . 'onsubmit="return confirm(\'هل أنت متأكد من حذف هذا الملف؟\');">'
         . csrf_field()
         . '<input type="hidden" name="id" value="' . $id . '">'
         . '<button type="submit" class="delete-btn">حذف 🗑️</button></form>';
}

function download_link(array $row, string $label = 'تحميل'): string {
    return '<a href="download.php?id=' . (int)$row['id'] . '" class="pdf-btn">'
         . e($label . ' ' . strtoupper($row['ext'])) . ' 📄</a>';
}

function meta_line(array $row): string {
    $who = $row['full_name'] ? $row['full_name'] : 'مستخدم محذوف';
    return '<small class="file-meta">' . e($who) . ' • ' . e(substr($row['created_at'], 0, 10))
         . ' • ' . e(human_size((int)$row['size'])) . '</small>';
}

function subject_label(array $s): string {
    return e($s['icon'] . ' ' . $s['name'] . ' (43) — ' . $s['teacher']);
}
