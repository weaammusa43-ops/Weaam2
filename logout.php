<?php
/**
 * تسجيل الخروج — الرابط يحمل رمز CSRF:  logout.php?t=<token>
 */
require __DIR__ . '/includes/bootstrap.php';

if (current_user() === null) {
    redirect('index.php');
}

if (!csrf_valid($_GET['t'] ?? null)) {
    flash('error', 'تعذّر تسجيل الخروج، حاول مرة أخرى من الصفحة الرئيسية.');
    redirect('home.php');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 42000,
        'path'     => $p['path'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => 'Lax',
    ]);
}
session_destroy();

// جلسة جديدة فقط لإظهار رسالة الخروج
session_start();
session_regenerate_id(true);
flash('success', 'تم تسجيل الخروج بنجاح. إلى اللقاء 👋');
redirect('index.php');
