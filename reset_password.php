<?php
/**
 * صفحة تعيين كلمة مرور جديدة عبر الرابط المرسل بالبريد
 */
require __DIR__ . '/includes/bootstrap.php';

function find_valid_reset(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) { return null; }
    $st = db()->prepare('SELECT id, user_id FROM password_resets WHERE token_hash = ? AND used = 0 AND expires_at > ?');
    $st->execute([hash('sha256', $token), now()]);
    $row = $st->fetch();
    return $row ?: null;
}

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$reset = find_valid_reset($token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        flash('error', 'انتهت صلاحية الصفحة. أعد المحاولة.');
        redirect('reset_password.php?token=' . urlencode($token));
    }
    if (!$reset) {
        flash('error', 'رابط الاستعادة غير صالح أو منتهي. اطلب رابطاً جديداً.');
        redirect('index.php?s=forgot');
    }

    $p1 = (string)($_POST['password'] ?? '');
    $p2 = (string)($_POST['password2'] ?? '');
    if (mb_strlen($p1) < 8 || strlen($p1) > 200) {
        flash('error', 'كلمة المرور يجب ألا تقل عن 8 خانات.');
        redirect('reset_password.php?token=' . urlencode($token));
    }
    if (!hash_equals($p1, $p2)) {
        flash('error', 'كلمتا المرور غير متطابقتين.');
        redirect('reset_password.php?token=' . urlencode($token));
    }

    $pdo = db();
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
        ->execute([password_hash($p1, PASSWORD_DEFAULT), $reset['user_id']]);
    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$reset['user_id']]);

    flash('success', 'تم تغيير كلمة المرور بنجاح. سجّل الدخول بكلمتك الجديدة.');
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعيين كلمة مرور جديدة - بوابة الطالب</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body.login-body { display:flex; justify-content:center; align-items:center; min-height:100vh; background-color:#f7f4ed; margin:0; padding:15px; }
        .login-card { background:white; padding:35px 30px; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,.1); width:100%; max-width:400px; text-align:center; border-top:6px solid #1e382d; border-bottom:6px solid #b8860b; }
        .login-logo { width:100px; height:100px; border-radius:50%; object-fit:cover; border:3px solid #b8860b; box-shadow:0 4px 10px rgba(0,0,0,.15); margin:0 auto 15px; display:block; }
        .form-group { margin-bottom:20px; text-align:right; }
        .form-group label { display:block; margin-bottom:8px; font-weight:600; color:#1e382d; font-size:14px; }
        .login-btn { background-color:#1e382d; color:white; padding:12px; border:none; border-radius:6px; width:100%; font-size:16px; cursor:pointer; font-weight:bold; }
        .extra-links { margin-top:20px; font-size:13px; }
        .extra-links a { color:#1e382d; text-decoration:none; font-weight:600; }
        .extra-links a:hover { color:#b8860b; text-decoration:underline; }
    </style>
</head>
<body class="login-body">
    <div class="login-card">
        <img src="image/image.jpg" alt="شعار كلية العلوم الرياضية والحاسوب" class="login-logo">
        <h2 style="color:#1e382d; margin-top:5px; font-size:20px;">تعيين كلمة مرور جديدة</h2>

        <?= flash_html() ?>

        <?php if ($reset): ?>
            <p style="color:#666; font-size:13px; margin-bottom:20px;">اختر كلمة مرور قوية (8 خانات على الأقل)</p>
            <form method="post" action="reset_password.php" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="form-group">
                    <label for="password">كلمة المرور الجديدة:</label>
                    <input type="password" id="password" name="password" minlength="8" required>
                </div>
                <div class="form-group">
                    <label for="password2">تأكيد كلمة المرور:</label>
                    <input type="password" id="password2" name="password2" minlength="8" required>
                </div>
                <button type="submit" class="login-btn">حفظ كلمة المرور</button>
            </form>
        <?php else: ?>
            <p style="color:#b91c1c; font-size:14px; margin:20px 0;">⚠️ رابط الاستعادة غير صالح أو انتهت صلاحيته.</p>
        <?php endif; ?>

        <div class="extra-links"><a href="index.php">← العودة لتسجيل الدخول</a></div>
    </div>
    <script src="js/script.js"></script>
</body>
</html>
