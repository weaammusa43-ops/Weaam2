<?php
/**
 * رفع محاضرة / امتحان / حل امتحان
 * الحقول: kind (lecture|exam|solution) ، subject ، title ، parent (للحلول) ، file
 */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

// إن تجاوز الطلب حد post_max_size يصل PHP فارغاً (بدون $_POST و $_FILES)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && empty($_POST) && empty($_FILES)
    && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'حجم الملف أكبر من المسموح به على هذا الخادم (الحد: ' . MAX_UPLOAD_MB . ' MB).');
    redirect(strpos((string)($_SERVER['HTTP_REFERER'] ?? ''), 'exams.php') !== false ? 'exams.php' : 'lectures.php');
}

require_post('home.php');

$kind    = (string)($_POST['kind'] ?? '');
$subject = (string)($_POST['subject'] ?? '');
$subjects = portal_subjects();

if (!in_array($kind, ['lecture', 'exam', 'solution'], true) || !isset($subjects[$subject])) {
    flash('error', 'بيانات الرفع غير صحيحة.');
    redirect('home.php');
}

$back = section_url($kind === 'lecture' ? 'lecture' : 'exam', $subject);
$extKind = $kind === 'lecture' ? 'lecture' : 'exam';   // أنواع الملفات المسموحة

// ----- حل امتحان: يجب أن يتبع امتحاناً موجوداً في نفس المادة -----
$parentId = null;
if ($kind === 'solution') {
    $parentId = (int)($_POST['parent'] ?? 0);
    $st = db()->prepare("SELECT id FROM files WHERE id = ? AND kind = 'exam' AND subject_key = ?");
    $st->execute([$parentId, $subject]);
    if (!$st->fetch()) {
        flash('error', 'الامتحان المطلوب إرفاق الحل به غير موجود.');
        redirect($back);
    }
}

// ----- حد معقول للرفع لكل مستخدم -----
$st = db()->prepare('SELECT COUNT(*) FROM files WHERE user_id = ? AND created_at > ?');
$st->execute([$user['id'], date('Y-m-d H:i:s', time() - 3600)]);
if ((int)$st->fetchColumn() >= 30) {
    flash('error', 'وصلت للحد الأقصى من الرفع في الساعة الأخيرة. حاول لاحقاً.');
    redirect($back);
}

// ----- فحص الملف -----
$f = $_FILES['file'] ?? null;
if (!$f || !is_array($f) || !isset($f['error']) || is_array($f['error'])) {
    flash('error', 'لم يتم اختيار ملف.');
    redirect($back);
}

switch ($f['error']) {
    case UPLOAD_ERR_OK: break;
    case UPLOAD_ERR_NO_FILE:
        flash('error', 'الرجاء اختيار ملف أولاً.'); redirect($back);
    case UPLOAD_ERR_INI_SIZE:
    case UPLOAD_ERR_FORM_SIZE:
        flash('error', 'حجم الملف أكبر من المسموح به على هذا الخادم (الحد: ' . MAX_UPLOAD_MB . ' MB).'); redirect($back);
    default:
        flash('error', 'حدث خطأ أثناء الرفع. حاول مرة أخرى.'); redirect($back);
}

if (!is_uploaded_file($f['tmp_name'])) {
    flash('error', 'ملف غير صالح.');
    redirect($back);
}
if ($f['size'] <= 0 || $f['size'] > MAX_UPLOAD_MB * 1048576) {
    flash('error', 'حجم الملف يجب ألا يتجاوز ' . MAX_UPLOAD_MB . ' MB.');
    redirect($back);
}

$original = (string)$f['name'];
$original = preg_replace('/[\x00-\x1F\x7F\/\\\\]/u', '', basename(str_replace('\\', '/', $original)));
$original = mb_substr($original, 0, 200);
$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

if (!in_array($ext, allowed_exts($extKind), true)) {
    flash('error', 'نوع الملف غير مسموح. المسموح: ' . implode(', ', allowed_exts($extKind)));
    redirect($back);
}
if (!file_signature_ok($f['tmp_name'], $ext)) {
    flash('error', 'محتوى الملف لا يطابق نوعه (.' . $ext . '). تأكد أن الملف سليم.');
    redirect($back);
}

// ----- العنوان -----
$title = trim(preg_replace('/\s+/u', ' ', (string)($_POST['title'] ?? '')));
if ($title === '') {
    $title = pathinfo($original, PATHINFO_FILENAME);
    if ($kind === 'solution') { $title = 'حل: ' . $title; }
}
$title = mb_substr($title, 0, 200);

// ----- التخزين: اسم عشوائي بامتداد .dat (لا يمكن تنفيذه كـ PHP أبداً) -----
if (!is_dir(FILES_DIR) && !@mkdir(FILES_DIR, 0755, true)) {
    flash('error', 'تعذّر إنشاء مجلد التخزين. تأكد من صلاحيات مجلد storage.');
    redirect($back);
}
$stored = bin2hex(random_bytes(16)) . '.dat';
if (!move_uploaded_file($f['tmp_name'], FILES_DIR . '/' . $stored)) {
    flash('error', 'تعذّر حفظ الملف على الخادم. تأكد من صلاحيات مجلد storage.');
    redirect($back);
}
@chmod(FILES_DIR . '/' . $stored, 0644);

try {
    db()->prepare('INSERT INTO files (kind, subject_key, parent_id, title, original_name, stored_name, ext, size, user_id, created_at)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$kind, $subject, $parentId, $title, $original, $stored, $ext, (int)$f['size'], $user['id'], now()]);
} catch (PDOException $ex) {
    @unlink(FILES_DIR . '/' . $stored);
    error_log('upload insert failed: ' . $ex->getMessage());
    flash('error', 'تعذّر حفظ بيانات الملف.');
    redirect($back);
}

flash('success', $kind === 'solution' ? 'تم رفع الحل بنجاح ✅' : 'تم رفع الملف بنجاح ✅');
redirect($back);
