<?php
/**
 * استقبال رسائل "تواصل معنا" وحفظها لمراجعتها من لوحة المشرف
 */
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();
require_post('account.php#contact-section');

$name    = trim(preg_replace('/\s+/u', ' ', (string)($_POST['studentName'] ?? '')));
$email   = trim((string)($_POST['studentEmail'] ?? ''));
$message = trim((string)($_POST['studentMessage'] ?? ''));

$errors = [];
if (mb_strlen($name) < 2 || mb_strlen($name) > 100)            { $errors[] = 'الاسم يجب أن يكون بين 2 و100 حرف.'; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) { $errors[] = 'البريد الإلكتروني غير صالح.'; }
if (mb_strlen($message) < 5 || mb_strlen($message) > 3000)     { $errors[] = 'الرسالة يجب أن تكون بين 5 و3000 حرف.'; }
if ($errors) {
    flash('error', implode(' ', $errors));
    redirect('account.php#contact-section');
}

// حد: 5 رسائل في الساعة لكل مستخدم
$st = db()->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ? AND created_at > ?');
$st->execute([$user['id'], date('Y-m-d H:i:s', time() - 3600)]);
if ((int)$st->fetchColumn() >= 5) {
    flash('error', 'أرسلت عدة رسائل مؤخراً. انتظر قليلاً ثم حاول مجدداً.');
    redirect('account.php#contact-section');
}

db()->prepare('INSERT INTO messages (user_id, name, email, message, is_read, ip, created_at) VALUES (?, ?, ?, ?, 0, ?, ?)')
    ->execute([$user['id'], $name, $email, $message, client_ip(), now()]);

if (CONTACT_NOTIFY_EMAIL !== '') {
    $subject = '=?UTF-8?B?' . base64_encode('رسالة جديدة من بوابة الطالب') . '?=';
    $body = "الاسم: $name\r\nالبريد: $email\r\nرقم التسجيل: {$user['reg_number']}\r\n\r\n$message\r\n";
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: " . MAIL_FROM . "\r\nReply-To: $email\r\n";
    @mail(CONTACT_NOTIFY_EMAIL, $subject, $body, $headers);
}

$_SESSION['contact_name'] = $name;
redirect('account.php#contact-section');
