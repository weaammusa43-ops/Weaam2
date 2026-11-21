<?php
/**
 * النواة المشتركة: أخطاء، جلسات، قاعدة بيانات، CSRF، مساعدات.
 * تُستدعى في أول كل صفحة:  require __DIR__ . '/includes/bootstrap.php';
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/subjects_data.php';

// ---------- الأخطاء: لا تُعرض للزائر، تُسجّل في ملف ----------
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
if (!is_dir(DATA_DIR)) { @mkdir(DATA_DIR, 0755, true); }
ini_set('error_log', DATA_DIR . '/error.log');

mb_internal_encoding('UTF-8');
date_default_timezone_set('Africa/Khartoum');

// ---------- ترويسات أمان ----------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

// ---------- الجلسة ----------
function portal_is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

if (session_status() === PHP_SESSION_NONE) {
    $sessDir = DATA_DIR . '/sessions';
    if (!is_dir($sessDir)) { @mkdir($sessDir, 0700, true); }
    if (is_dir($sessDir) && is_writable($sessDir)) { session_save_path($sessDir); }

    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';
    session_name('PORTALSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => $basePath,
        'secure'   => portal_is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------- مساعدات عامة ----------
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** تحويل بمسار نسبي (يعمل في أي مجلد أو استضافة) */
function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

function client_ip(): string {
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function now(): string { return date('Y-m-d H:i:s'); }

// ---------- قاعدة البيانات ----------
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) { return $pdo; }

    if (DB_DRIVER === 'mysql') {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS
        );
    } else {
        $pdo = new PDO('sqlite:' . DATA_DIR . '/portal.sqlite');
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    portal_install($pdo);
    return $pdo;
}

/** إنشاء الجداول تلقائياً أول مرة */
function portal_install(PDO $pdo): void {
    $marker = DATA_DIR . '/.installed-' . DB_DRIVER;
    if (is_file($marker)) { return; }

    $id   = DB_DRIVER === 'mysql' ? 'INT UNSIGNED AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tail = DB_DRIVER === 'mysql' ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    $tables = [
        "CREATE TABLE IF NOT EXISTS users (
            id $id,
            full_name VARCHAR(150) NOT NULL,
            reg_number VARCHAR(40) NOT NULL UNIQUE,
            email VARCHAR(190) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at VARCHAR(19) NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS files (
            id $id,
            kind VARCHAR(10) NOT NULL,
            subject_key VARCHAR(30) NOT NULL,
            parent_id INT NULL,
            title VARCHAR(200) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(64) NOT NULL,
            ext VARCHAR(10) NOT NULL,
            size INT NOT NULL,
            user_id INT NOT NULL,
            created_at VARCHAR(19) NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS messages (
            id $id,
            user_id INT NULL,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(190) NOT NULL,
            message TEXT NOT NULL,
            is_read INT NOT NULL DEFAULT 0,
            ip VARCHAR(45) NOT NULL,
            created_at VARCHAR(19) NOT NULL
        )$tail",
        "CREATE TABLE IF NOT EXISTS password_resets (
            id $id,
            user_id INT NOT NULL,
            token_hash VARCHAR(64) NOT NULL,
            expires_at VARCHAR(19) NOT NULL,
            used INT NOT NULL DEFAULT 0
        )$tail",
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id $id,
            ident VARCHAR(100) NOT NULL,
            created_at VARCHAR(19) NOT NULL
        )$tail",
    ];
    foreach ($tables as $sql) { $pdo->exec($sql); }
    @file_put_contents($marker, now());
}

// ---------- CSRF ----------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valid($token): bool {
    return is_string($token) && $token !== '' && hash_equals((string)($_SESSION['csrf'] ?? ''), $token);
}

/** يوقف الطلب إن لم يكن POST صحيحاً مع CSRF سليم */
function require_post(string $backTo): void {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !csrf_valid($_POST['csrf'] ?? null)) {
        flash('error', 'انتهت صلاحية الصفحة أو الطلب غير صالح. أعد المحاولة.');
        redirect($backTo);
    }
}

// ---------- رسائل التنبيه (Flash) ----------
function flash(string $type, string $msg): void {
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_html(): string {
    if (empty($_SESSION['flash'])) { return ''; }
    $out = '';
    foreach ($_SESSION['flash'] as $f) {
        $cls = $f['type'] === 'success' ? 'flash-success' : ($f['type'] === 'info' ? 'flash-info' : 'flash-error');
        $out .= '<div class="flash ' . $cls . '" role="alert">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return '<div class="flash-wrap">' . $out . '</div>';
}

// ---------- المستخدم الحالي ----------
function current_user(): ?array {
    static $user = false;
    if ($user !== false) { return $user; }
    $uid = (int)($_SESSION['uid'] ?? 0);
    if ($uid <= 0) { return $user = null; }
    $st = db()->prepare('SELECT id, full_name, reg_number, email FROM users WHERE id = ?');
    $st->execute([$uid]);
    $row = $st->fetch();
    if (!$row) { unset($_SESSION['uid']); return $user = null; }
    return $user = $row;
}

function is_admin(): bool {
    $u = current_user();
    return $u !== null && in_array($u['reg_number'], ADMIN_REG_NUMBERS, true);
}

/** حراسة الصفحات المحمية (بدل localStorage القديم) */
function require_login(): array {
    $u = current_user();
    if ($u === null) {
        flash('info', 'الرجاء تسجيل الدخول أولاً.');
        redirect('index.php');
    }
    return $u;
}

function require_admin(): array {
    $u = require_login();
    if (!is_admin()) {
        flash('error', 'هذه الصفحة للمشرفين فقط.');
        redirect('home.php');
    }
    return $u;
}

function login_user(int $uid): void {
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

// ---------- تحديد محاولات الدخول ----------
function attempts_count(string $ident, int $windowSec): int {
    $since = date('Y-m-d H:i:s', time() - $windowSec);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ident = ? AND created_at > ?');
    $st->execute([$ident, $since]);
    return (int)$st->fetchColumn();
}

function attempts_add(string $ident): void {
    db()->prepare('INSERT INTO login_attempts (ident, created_at) VALUES (?, ?)')->execute([$ident, now()]);
}

function attempts_clear(string $ident): void {
    db()->prepare('DELETE FROM login_attempts WHERE ident = ?')->execute([$ident]);
}

function attempts_cleanup(): void {
    $old = date('Y-m-d H:i:s', time() - 86400);
    db()->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([$old]);
}

// ---------- أدوات الملفات ----------
function human_size(int $bytes): string {
    if ($bytes >= 1048576) { return round($bytes / 1048576, 1) . ' MB'; }
    return max(1, (int)round($bytes / 1024)) . ' KB';
}

/** أنواع الملفات المسموحة لكل قسم */
function allowed_exts(string $kind): array {
    return $kind === 'lecture'
        ? ['pdf', 'doc', 'docx', 'ppt', 'pptx']
        : ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
}

/** فحص محتوى الملف الفعلي (وليس الامتداد فقط) */
function file_signature_ok(string $tmpPath, string $ext): bool {
    $fh = @fopen($tmpPath, 'rb');
    if (!$fh) { return false; }
    $head = (string)fread($fh, 8);
    fclose($fh);

    switch ($ext) {
        case 'pdf':  return strncmp($head, '%PDF', 4) === 0;
        case 'docx':
        case 'pptx': return strncmp($head, "PK\x03\x04", 4) === 0;
        case 'doc':
        case 'ppt':  return strncmp($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1", 8) === 0;
        case 'jpg':
        case 'jpeg': return strncmp($head, "\xFF\xD8\xFF", 3) === 0 && @getimagesize($tmpPath) !== false;
        case 'png':  return strncmp($head, "\x89PNG\r\n\x1A\n", 8) === 0 && @getimagesize($tmpPath) !== false;
    }
    return false;
}

/** رابط نسبي للعودة إلى قسم المادة في صفحة المحاضرات أو الامتحانات */
function section_url(string $kind, string $subjectKey): string {
    $page = $kind === 'lecture' ? 'lectures.php' : 'exams.php';
    $anchor = array_key_exists($subjectKey, portal_subjects()) ? '#' . $subjectKey . '-sec' : '';
    return $page . $anchor;
}

/** نوع المحتوى عند التحميل */
function mime_for_ext(string $ext): string {
    $map = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];
    return $map[$ext] ?? 'application/octet-stream';
}
