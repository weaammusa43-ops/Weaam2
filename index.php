<?php
require __DIR__ . '/includes/bootstrap.php';
// المسجّل دخوله يذهب مباشرة للرئيسية
if (current_user() !== null) { redirect('home.php'); }

$section = $_GET['s'] ?? 'login';
if (!in_array($section, ['login', 'register', 'forgot'], true)) { $section = 'login'; }
$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);
$welcomeName = $_SESSION['welcome_name'] ?? null;
$welcomeReg  = $_SESSION['welcome_reg'] ?? null;
unset($_SESSION['welcome_name'], $_SESSION['welcome_reg']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة الطالب - الدفعة 43</title>
    <link rel="stylesheet" href="css/style.css">

    <style>
        body.login-body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-color: #f7f4ed;
            margin: 0;
            padding: 15px;
        }

        .login-card {
            background: white;
            padding: 35px 30px;
            border-radius: 12px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
            border-top: 6px solid #1e382d;
            border-bottom: 6px solid #b8860b;
        }

        /* تصحيح عرض الشعار لضمان ظهوره بشكل دائري وفخم */
        .login-logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #b8860b;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
            margin-bottom: 15px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .form-group {
            margin-bottom: 20px;
            text-align: right;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #1e382d;
            font-size: 14px;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #dcd6c8;
            border-radius: 6px;
            box-sizing: border-box;
            font-size: 14px;
            background-color: #faf8f5;
            outline: none;
        }

        .form-group input:focus {
            border-color: #1e382d;
            background-color: #fff;
        }

        .login-btn {
            background-color: #1e382d;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 6px;
            width: 100%;
            font-size: 16px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }

        .login-btn:hover {
            background-color: #2c4a3e;
        }

        .extra-links {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
        }

        .extra-links a {
            color: #1e382d;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: color 0.3s;
        }

        .extra-links a:hover {
            color: #b8860b;
            text-decoration: underline;
        }

        .pw-hint { display:block; margin-top:6px; font-size:12px; min-height:16px; }
        /* تنسيق الأقسام للتبديل الفوري بدون صفحات خارجية */
        .form-section {
            display: none;
        }

        .form-section.active {
            display: block;
        }
    </style>
</head>

<body class="login-body">

    <div class="login-card">
        <!-- شعار الكلية الدائري الثابت -->
        <img src="image/image.jpg" alt="شعار كلية العلوم الرياضية والحاسوب" class="login-logo" onerror="this.onerror=null; this.src='image.jpg';">

        <?= flash_html() ?>
        <?php if ($welcomeName): ?>
        <div class="flash flash-success" role="alert">🌟 أهلاً بك يا مهندس/ـة "<?= e($welcomeName) ?>" في بوابة الدفعة 43!<br>✨ تم إنشاء حسابك برقم التسجيل (<?= e($welcomeReg) ?>). واصل الإبداع، فأساس البرمجيات هو الصبر والنجاح!</div>
        <?php endif; ?>

        <!-- ================= 1. واجهة تسجيل الدخول ================= -->
        <div id="loginSection" class="form-section<?= $section === 'login' ? ' active' : '' ?>">
            <h2 style="color: #1e382d; margin-top: 5px; font-size: 22px;">بوابة الطالب</h2>
            <p style="color: #666; font-size: 13px; margin-bottom: 25px;">تسجيل الدخول للدفعة 43 - جامعة الجزيرة</p>

            <form id="loginForm" method="post" action="auth.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="reg_number">رقم التسجيل الجامعي:</label>
                    <input type="text" id="reg_number" name="reg_number" placeholder="مثال: 43..." required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="password">كلمة المرور:</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
                </div>

                <button type="submit" class="login-btn">تسجيل الدخول [Execute]</button>
            </form>

            <div class="extra-links">
                <a href="#" onclick="switchSection('registerSection'); return false;">إنشاء حساب جديد</a>
                <a href="#" onclick="switchSection('forgotSection'); return false;">نسيت كلمة المرور؟</a>
            </div>
        </div>


        <!-- ================= 2. واجهة إنشاء حساب جديد ================= -->
        <div id="registerSection" class="form-section<?= $section === 'register' ? ' active' : '' ?>">
            <h2 style="color: #1e382d; margin-top: 5px; font-size: 22px;">إنشاء حساب جديد</h2>
            <p style="color: #666; font-size: 13px; margin-bottom: 20px;">تسجيل طالب جديد - الدفعة 43</p>

            <form id="registerForm" method="post" action="auth.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="register">
                <div class="form-group">
                    <label for="fullname">الاسم الكامل:</label>
                    <input type="text" id="fullname" name="fullname" maxlength="100" value="<?= e($old['fullname'] ?? '') ?>" placeholder="أدخل اسمك الثلاثي..." required autocomplete="off">
                </div>

                <div class="form-group">
                    <label for="reg_new">رقم التسجيل الجامعي:</label>
                    <input type="text" id="reg_new" name="reg_new" maxlength="30" value="<?= e($old['reg_new'] ?? '') ?>" placeholder="مثال: 43..." required>
                </div>

                <div class="form-group">
                    <label for="email_new">البريد الإلكتروني (لاستعادة كلمة المرور):</label>
                    <input type="email" id="email_new" name="email_new" maxlength="190" value="<?= e($old['email_new'] ?? '') ?>" placeholder="example@domain.com" required>
                </div>

                <div class="form-group">
                    <label for="pass_new">كلمة المرور:</label>
                    <input type="password" id="pass_new" name="pass_new" minlength="8" placeholder="8 خانات على الأقل" required autocomplete="new-password">
                    <small class="pw-hint" id="pwHint"></small>
                </div>

                <button type="submit" class="login-btn">إنشاء الحساب</button>
            </form>

            <div class="extra-links" style="justify-content: center; margin-top: 15px;">
                <a href="#" onclick="switchSection('loginSection'); return false;">← العودة لتسجيل الدخول</a>
            </div>
        </div>


        <!-- ================= 3. واجهة استعادة كلمة المرور ================= -->
        <div id="forgotSection" class="form-section<?= $section === 'forgot' ? ' active' : '' ?>">
            <h2 style="color: #1e382d; margin-top: 5px; font-size: 20px;">استعادة كلمة المرور</h2>
            <p style="color: #666; font-size: 13px; margin-bottom: 20px;">أدخل رقم التسجيل لإرسال رابط إعادة التعيين إلى بريدك</p>

            <form id="forgotForm" method="post" action="auth.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="forgot">
                <div class="form-group">
                    <label for="reset_reg">رقم التسجيل الجامعي:</label>
                    <input type="text" id="reset_reg" name="reset_reg" maxlength="30" placeholder="مثال: 43..." required autocomplete="off">
                </div>

                <button type="submit" class="login-btn">إرسال طلب الاستعادة</button>
            </form>

            <div class="extra-links" style="justify-content: center; margin-top: 15px;">
                <a href="#" onclick="switchSection('loginSection'); return false;">← العودة لتسجيل الدخول</a>
            </div>
        </div>

    </div>

    <!-- التبديل الفوري بين الواجهات (الإرسال الفعلي يتم عبر PHP في auth.php) -->
    <script>
        function switchSection(sectionId) {
            document.querySelectorAll('.form-section').forEach(function (section) {
                section.classList.remove('active');
            });
            document.getElementById(sectionId).classList.add('active');
        }
    </script>
    <script src="js/script.js"></script>
</body>

</html>
