<?php
/**
 * حذف محاضرة / امتحان / حل — صاحب الملف أو المشرف فقط (POST + CSRF)
 */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
require_post('home.php');

$id = (int)($_POST['id'] ?? 0);
$pdo = db();

$st = $pdo->prepare('SELECT id, kind, subject_key, stored_name, user_id FROM files WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();

if (!$row) {
    flash('error', 'الملف غير موجود أو حُذف مسبقاً.');
    redirect('home.php');
}

$back = section_url($row['kind'] === 'lecture' ? 'lecture' : 'exam', $row['subject_key']);

if ((int)$row['user_id'] !== (int)$user['id'] && !is_admin()) {
    flash('error', 'لا تملك صلاحية حذف هذا الملف.');
    redirect($back);
}

// عند حذف امتحان تُحذف حلوله معه
$toDelete = [$row];
if ($row['kind'] === 'exam') {
    $st = $pdo->prepare('SELECT id, stored_name FROM files WHERE parent_id = ?');
    $st->execute([$row['id']]);
    foreach ($st->fetchAll() as $child) { $toDelete[] = $child; }
}

foreach ($toDelete as $item) {
    if (preg_match('/^[a-f0-9]{32}\.dat$/', $item['stored_name'])) {
        @unlink(FILES_DIR . '/' . $item['stored_name']);
    }
    $pdo->prepare('DELETE FROM files WHERE id = ?')->execute([$item['id']]);
}

flash('success', 'تم الحذف بنجاح 🗑️');
redirect($back);
