<?php
/**
 * لوحة المشرف: عرض رسائل المستخدمين وإدارتها
 * (الصلاحية: أرقام التسجيل المذكورة في ADMIN_REG_NUMBERS داخل includes/config.php)
 */
require __DIR__ . '/includes/bootstrap.php';
require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post('admin_messages.php');
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['do'] ?? '');
    if ($act === 'read')        { $pdo->prepare('UPDATE messages SET is_read = 1 WHERE id = ?')->execute([$id]); }
    elseif ($act === 'unread')  { $pdo->prepare('UPDATE messages SET is_read = 0 WHERE id = ?')->execute([$id]); }
    elseif ($act === 'delete')  { $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([$id]); flash('success', 'تم حذف الرسالة.'); }
    redirect('admin_messages.php');
}

$rows = $pdo->query('SELECT m.*, u.reg_number FROM messages m LEFT JOIN users u ON u.id = m.user_id ORDER BY m.id DESC LIMIT 200')->fetchAll();
$unread = (int)$pdo->query('SELECT COUNT(*) FROM messages WHERE is_read = 0')->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - رسائل المستخدمين | Admin</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .portal-nav-bar { background-color:#2c4a3e; padding:14px 20px; display:flex; justify-content:center; align-items:center; flex-wrap:wrap; gap:15px; box-shadow:0 2px 8px rgba(0,0,0,.15); }
        .portal-nav-bar a { color:#fff; text-decoration:none; font-size:14px; font-weight:600; padding:8px 18px; border-radius:8px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25); transition:all .3s ease; display:inline-block; }
        .portal-nav-bar a:hover { background-color:#b8860b; border-color:#b8860b; transform:translateY(-2px); }
        .msg-card { background:#fff; border-radius:12px; padding:18px; margin-bottom:15px; box-shadow:0 4px 15px rgba(0,0,0,.06); border-right:6px solid #cbd5e1; }
        .msg-card.unread { border-right-color:#b8860b; background:#fffdf5; }
        .msg-head { display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px; font-size:13px; color:#555; margin-bottom:10px; }
        .msg-body { white-space:normal; line-height:1.9; font-size:14px; }
        .msg-actions { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
        .msg-actions form { margin:0; }
        .msg-actions button, .msg-actions a { width:auto; padding:7px 14px; font-size:12px; border-radius:6px; text-decoration:none; color:#fff; background:#1e382d; display:inline-block; }
        .msg-actions .danger { background:#ef4444; }
        .msg-actions .reply { background:#0369a1; }
    </style>
</head>
<body>
    <header>
        <h1>Student Portal | رسائل المستخدمين</h1>
        <p>لوحة المشرف — الرسائل غير المقروءة: <?= $unread ?></p>
    </header>
    <div class="portal-nav-bar">
        <a href="home.php">🏠 الرئيسية | Home</a>
        <a href="account.php">⚙️ الحساب والمساعدة</a>
    </div>
    <main>
        <?= flash_html() ?>
        <?php if (!$rows): ?>
            <div class="welcome-card" style="text-align:center;">📭 لا توجد رسائل حتى الآن.</div>
        <?php endif; ?>

        <?php foreach ($rows as $m): ?>
        <div class="msg-card <?= $m['is_read'] ? '' : 'unread' ?>">
            <div class="msg-head">
                <span><strong><?= e($m['name']) ?></strong><?= $m['reg_number'] ? ' (' . e($m['reg_number']) . ')' : '' ?> — <?= e($m['email']) ?></span>
                <span><?= e($m['created_at']) ?></span>
            </div>
            <div class="msg-body"><?= nl2br(e($m['message'])) ?></div>
            <div class="msg-actions">
                <a class="reply" href="mailto:<?= e($m['email']) ?>">↩️ رد بالبريد</a>
                <form method="post" action="admin_messages.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <input type="hidden" name="do" value="<?= $m['is_read'] ? 'unread' : 'read' ?>">
                    <button type="submit"><?= $m['is_read'] ? '📩 تعليم كغير مقروءة' : '✅ تعليم كمقروءة' ?></button>
                </form>
                <form method="post" action="admin_messages.php" onsubmit="return confirm('حذف هذه الرسالة نهائياً؟');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                    <input type="hidden" name="do" value="delete">
                    <button type="submit" class="danger">🗑️ حذف</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </main>
    <footer><p>Student Portal & بوابة الطالب - كلية العلوم الرياضية والحاسوب © 2026</p></footer>
    <script src="js/script.js"></script>
</body>
</html>
