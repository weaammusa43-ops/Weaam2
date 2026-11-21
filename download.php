<?php
/**
 * تحميل ملف (للمسجّلين فقط):  download.php?id=123
 * الملفات مخزّنة بأسماء عشوائية خارج متناول الروابط المباشرة.
 */
require __DIR__ . '/includes/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT kind, subject_key, original_name, stored_name, ext FROM files WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();

if (!$row || !preg_match('/^[a-f0-9]{32}\.dat$/', $row['stored_name'])) {
    flash('error', 'الملف المطلوب غير موجود.');
    redirect('home.php');
}

$path = FILES_DIR . '/' . $row['stored_name'];
if (!is_file($path) || !is_readable($path)) {
    flash('error', 'الملف غير متوفر على الخادم (ربما حُذف).');
    redirect(section_url($row['kind'] === 'lecture' ? 'lecture' : 'exam', $row['subject_key']));
}

// اسم الملف عند التنزيل (نسخة ASCII احتياطية + نسخة UTF-8 للعربية)
$name  = $row['original_name'] !== '' ? $row['original_name'] : ('file.' . $row['ext']);
$ascii = preg_replace('/[^A-Za-z0-9._\-]/', '_', $name);
if ($ascii === '' || $ascii[0] === '.') { $ascii = 'file.' . $row['ext']; }

while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: ' . mime_for_ext($row['ext']));
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=0, must-revalidate');
readfile($path);
exit;
