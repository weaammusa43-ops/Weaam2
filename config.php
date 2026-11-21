<?php
/**
 * إعدادات الموقع — عدّل هذا الملف فقط عند الرفع على الاستضافة.
 */

// ===== قاعدة البيانات =====
// 'sqlite' : لا تحتاج أي إعداد (الملف ينشأ تلقائياً في storage/data)
// 'mysql'  : املأ البيانات بالأسفل من لوحة الاستضافة (cPanel)
define('DB_DRIVER', 'sqlite');
define('DB_HOST', 'localhost');
define('DB_NAME', 'student_portal');
define('DB_USER', 'root');
define('DB_PASS', '');

// ===== المشرفون =====
// أرقام التسجيل التي تملك صلاحية إدارة الرسائل وحذف أي ملف.
// سجّل حساباً أولاً ثم ضع رقم تسجيلك هنا.
define('ADMIN_REG_NUMBERS', []);   // مثال: ['43001', '43002']

// ===== الرفع =====
define('MAX_UPLOAD_MB', 20);

// ===== استعادة كلمة المرور =====
// عنوان موقعك الكامل بدون / في النهاية، مثال: 'https://mysite.com' أو 'https://mysite.com/portal'
// مهم لأمان روابط الاستعادة. إن تُرك فارغاً يُستنتج تلقائياً.
define('SITE_URL', '');
// البريد الذي يظهر كمرسل في رسائل الاستعادة (يفضّل بريد من نفس نطاق موقعك)
define('MAIL_FROM', 'no-reply@' . preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
// للتجربة المحلية فقط! يعرض رابط الاستعادة على الشاشة. اتركه false على السيرفر الحقيقي.
define('DEV_SHOW_RESET_LINK', false);

// ===== رسائل التواصل =====
// بريد يصله إشعار عند وصول رسالة جديدة (اتركه '' لتعطيل الإشعار — الرسائل تُحفظ دائماً في لوحة المشرف)
define('CONTACT_NOTIFY_EMAIL', 'weaammusa43@gmail.com');

// ===== مسارات داخلية (لا تغيّرها) =====
define('ROOT_DIR', dirname(__DIR__));
define('FILES_DIR', ROOT_DIR . '/storage/files');
define('DATA_DIR', ROOT_DIR . '/storage/data');
