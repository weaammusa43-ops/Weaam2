<?php
/**
 * معالج تسجيل الدخول / إنشاء الحساب / طلب استعادة كلمة المرور
 * (نماذج index.php ترسل إلى هنا)
 */
require __DIR__ . '/includes/bootstrap.php';

require_post('index.php');

$action = $_POST['action'] ?? '';
$ip = client_ip();
attempts_cleanup();

/** يحفظ ما كتبه المستخدم ليعاد عرضه عند الخطأ (بدون كلمة المرور) */
function remember_old(array $keys): void {
    $_SESSION['old'] = [];
    foreach ($keys as $k) { $_SESSION['old'][$k] = trim((string)($_POST[$k] ?? '')); }
}

function site_base_url(): string {
    if (defined('SITE_URL') && SITE_URL !== '') { return rtrim(SITE_URL, '/'); }
    $host = preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $_SERVER['HTTP_HOST'] ?? '') ? $_SERVER['HTTP_HOST'] : 'localhost';
    $dir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    return (portal_is_https() ? 'https' : 'http') . '://' . $host . $dir;
}

/* ========================= تسجيل الدخول ========================= */
if ($action === 'login') {
    $reg  = trim((string)($_POST['reg_number'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');

    if ($reg === '' || $pass === '') {
        flash('error', 'الرجاء إدخال رقم التسجيل وكلمة المرور.');
        redirect('index.php');
    }

    $identUser = 'u:' . hash('sha256', $ip . '|' . mb_strtolower($reg));
    $identIp   = 'ip:' . $ip;
    if (attempts_count($identUser, 900) >= 5 || attempts_count($identIp, 900) >= 30) {
        flash('error', 'محاولات دخول كثيرة. انتظر 15 دقيقة ثم حاول مجدداً.');
        redirect('index.php');
    }

    $st = db()->prepare('SELECT id, password_hash FROM users WHERE reg_number = ?');
    $st->execute([$reg]);
    $user = $st->fetch();

    $ok = false;
    if ($user) {
        $ok = password_verify($pass, $user['password_hash']);
    } else {
        password_hash($pass, PASSWORD_DEFAULT); // موازنة زمن الاستجابة
    }

    if (!$ok) {
        attempts_add($identUser);
        attempts_add($identIp);
        flash('error', 'رقم التسجيل أو كلمة المرور غير صحيحة.');
        redirect('index.php');
    }

    attempts_clear($identUser);
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), $user['id']]);
    }
    login_user((int)$user['id']);
    redirect('home.php');
}

/* ========================= إنشاء حساب ========================= */
if ($action === 'register') {
    remember_old(['fullname', 'reg_new', 'email_new']);

    $name  = trim(preg_replace('/\s+/u', ' ', (string)($_POST['fullname'] ?? '')));
    $reg   = trim((string)($_POST['reg_new'] ?? ''));
    $email = trim((string)($_POST['email_new'] ?? ''));
    $pass  = (string)($_POST['pass_new'] ?? '');

    $identReg = 'reg:' . $ip;
    if (attempts_count($identReg, 3600) >= 10) {
        flash('error', 'تم تجاوز عدد محاولات التسجيل. حاول لاحقاً.');
        redirect('index.php?s=register');
    }
    attempts_add($identReg);

    $errors = [];
    if (mb_strlen($name) < 3 || mb_strlen($name) > 100) { $errors[] = 'الاسم الكامل يجب أن يكون بين 3 و100 حرف.'; }
    if (!preg_match('/^[A-Za-z0-9_\-\/]{3,30}$/', $reg)) { $errors[] = 'رقم التسجيل غير صالح (أحرف إنجليزية وأرقام فقط، 3–30 خانة).'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190) { $errors[] = 'البريد الإلكتروني غير صالح.'; }
    if (mb_strlen($pass) < 8) { $errors[] = 'كلمة المرور يجب ألا تقل عن 8 خانات.'; }
    if (strlen($pass) > 200) { $errors[] = 'كلمة المرور طويلة جداً.'; }

    if ($errors) {
        flash('error', implode(' ', $errors));
        redirect('index.php?s=register');
    }

    $st = db()->prepare('SELECT id FROM users WHERE reg_number = ?');
    $st->execute([$reg]);
    if ($st->fetch()) {
        flash('error', 'رقم التسجيل مسجّل مسبقاً. جرّب تسجيل الدخول أو استعادة كلمة المرور.');
        redirect('index.php?s=register');
    }

    try {
        db()->prepare('INSERT INTO users (full_name, reg_number, email, password_hash, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $reg, $email, password_hash($pass, PASSWORD_DEFAULT), now()]);
    } catch (PDOException $ex) {
        flash('error', 'تعذّر إنشاء الحساب. تأكد من أن رقم التسجيل غير مستخدم.');
        redirect('index.php?s=register');
    }

    unset($_SESSION['old']);
    $_SESSION['welcome_name'] = $name;
    $_SESSION['welcome_reg']  = $reg;
    flash('success', 'تم إنشاء حسابك بنجاح! يمكنك تسجيل الدخول الآن.');
    redirect('index.php');
}

/* ========================= استعادة كلمة المرور ========================= */
if ($action === 'forgot') {
    $reg = trim((string)($_POST['reset_reg'] ?? ''));

    $identFg = 'fg:' . $ip;
    if (attempts_count($identFg, 3600) >= 5) {
        flash('error', 'تم تجاوز عدد طلبات الاستعادة. حاول بعد ساعة.');
        redirect('index.php?s=forgot');
    }
    attempts_add($identFg);

    // نفس الرسالة دائماً حتى لا نكشف وجود الحساب من عدمه
    $generic = 'إن كان رقم التسجيل مسجّلاً فقد أُرسل رابط إعادة التعيين إلى بريد الحساب (صالح لمدة ساعة).';

    $st = db()->prepare('SELECT id, full_name, email FROM users WHERE reg_number = ?');
    $st->execute([$reg]);
    $user = $st->fetch();

    if ($user) {
        $pdo = db();
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$user['id']]);

        $token = bin2hex(random_bytes(32));
        $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at, used) VALUES (?, ?, ?, 0)')
            ->execute([$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);

        $link = site_base_url() . '/reset_password.php?token=' . $token;

        $subject = '=?UTF-8?B?' . base64_encode('استعادة كلمة المرور - بوابة الطالب') . '?=';
        $body = "مرحباً {$user['full_name']},\r\n\r\n"
              . "وصلنا طلب لإعادة تعيين كلمة المرور. اضغط على الرابط التالي (صالح لمدة ساعة):\r\n$link\r\n\r\n"
              . "إن لم تطلب ذلك فتجاهل هذه الرسالة.\r\n";
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: " . MAIL_FROM . "\r\n";
        $sent = @mail($user['email'], $subject, $body, $headers);

        if (!$sent) {
            // لا يوجد خادم بريد: نسجّل الرابط ليتمكن المشرف من إرساله يدوياً
            @file_put_contents(DATA_DIR . '/reset_links.log',
                '[' . now() . '] ' . $reg . ' => ' . $link . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        if (DEV_SHOW_RESET_LINK) {
            flash('info', 'وضع التجربة — رابط الاستعادة: ' . $link);
        }
    }

    flash('success', $generic);
    redirect('index.php');
}

flash('error', 'طلب غير معروف.');
redirect('index.php');
