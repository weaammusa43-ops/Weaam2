<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/render.php';
$user      = require_login();
$groups    = portal_groups();
$subjects  = portal_subjects();
$files     = files_by_subject('exam');
$solutions = solutions_by_exam();
$maxMb     = MAX_UPLOAD_MB;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal - أرشيف الامتحانات السابقة | Past Exams</title>
    <!-- ربط ملف الـ CSS الشامل داخل مجلد css -->
    <link rel="stylesheet" href="css/style.css">

    <style>

        .exam-section {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            overflow: hidden;
            border-right: 6px solid #1e382d;
            transition: all 0.4s ease;
        }
        details { width: 100%; }
        summary {
            background-color: #f9f8f5;
            padding: 16px 20px;
            font-size: 15px;
            font-weight: bold;
            color: #1e382d;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e8e3d5;
            outline: none;
            user-select: none;
        }
        .exam-content { padding: 20px; }
        .admin-controls-box {
            background: rgba(184, 134, 11, 0.08);
            border: 2px dashed #b8860b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .input-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .input-group input[type="text"] {
            padding: 9px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            flex: 1;
            min-width: 180px;
            font-size: 13px;
        }
        .input-group input[type="file"] {
            font-size: 12px;
            background: #fff;
            padding: 6px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
        }
        .btn-add {
            background: #b8860b;
            color: white;
            border: none;
            padding: 9px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .table-responsive { width: 100%; overflow-x: auto; margin-top: 10px; }
        .exams-table { width: 100%; border-collapse: collapse; min-width: 450px; }
        .exams-table th, .exams-table td { padding: 12px 15px; text-align: right; border-bottom: 1px solid #f1eeec; font-size: 14px; }
        .exams-table th { background-color: #1e382d; color: white; }
        .pdf-btn { background: #10b981; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: bold; display: inline-block; }
        .delete-btn { background: #ef4444; color: white; border: none; padding: 6px 12px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: bold; }
        .spec-header-title { border-bottom: 2px solid; padding-bottom: 8px; margin-top: 30px; margin-bottom: 15px; font-size: 18px; }

        /* شريط التنقل العلوي المتمركز في المنتصف للأزرار التفاعلية */
        .portal-nav-bar {
            background-color: #2c4a3e;
            padding: 14px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .portal-nav-bar a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 8px 18px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.25);
            transition: all 0.3s ease;
            display: inline-block;
        }
        .portal-nav-bar a:hover {
            background-color: #b8860b;
            border-color: #b8860b;
            transform: translateY(-2px);
        }
        .logout-nav-btn {
            background: rgba(239, 68, 68, 0.3) !important;
            border-color: rgba(239, 68, 68, 0.6) !important;
        }
        .logout-nav-btn:hover {
            background: #ef4444 !important;
            border-color: #ef4444 !important;
        }
    </style>
</head>

<body>
    <header>
        <h1>Student Portal | أرشيف الامتحانات السابقة</h1>
        <p>جامعة الجزيرة - كلية العلوم الرياضية والحاسوب (بنك الامتحانات الشامل للدفعة 43)</p>
    </header>

    <!-- شريط الأزرار العلوي -->
    <div class="portal-nav-bar">
        <a href="home.php">🏠 الرئيسية | Home</a>
        <a href="logout.php?t=<?= e(csrf_token()) ?>" class="logout-nav-btn" data-logout>🚪 تسجيل الخروج | Logout</a>
    </div>

    <main>
        <?= flash_html() ?>

        <section id="past-exams">
            <h2>📝 أرشيف الامتحانات السابقة والأوراق الإرشادية</h2>
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">تصفح الامتحانات السابقة لكافة مواد الفصل الثامن مصنفة حسب الجدول الرسمي المعتمد للدفعة 43:</p>

            <!-- بحث سريع عن مادة -->
            <div class="filter-box">
                <input type="search" id="subjectFilter" placeholder="🔍 ابحث عن مادة أو دكتور..." autocomplete="off">
            </div>

            <?php foreach ($groups as $gKey => $g): ?>
            <h3 class="spec-header-title" style="color: <?= e($g['color']) ?>; border-color: <?= e($g['color']) ?>;"><?= e($g['title']) ?></h3>

                <?php foreach ($subjects as $key => $s): if ($s['group'] !== $gKey) { continue; }
                    $rows = $files[$key] ?? []; ?>
            <div id="<?= e($key) ?>-sec" class="exam-section" style="border-right-color: <?= e($g['color']) ?>;">
                <details>
                    <summary><span>📁 امتحان <?= e($s['name'] . ' (43) — ' . $s['teacher']) ?></span> <span class="count-badge"><?= count($rows) ?> ملف</span></summary>
                    <div class="exam-content">
                        <div class="admin-controls-box">
                            <h4>➕ رفع امتحان أو نموذج جديد</h4>
                            <form class="input-group upload-form" method="post" action="upload.php" enctype="multipart/form-data">
                                <?= csrf_field() ?>
                                <input type="hidden" name="kind" value="exam">
                                <input type="hidden" name="subject" value="<?= e($key) ?>">
                                <input type="text" id="title-<?= e($key) ?>" name="title" maxlength="200" placeholder="عنوان النموذج أو السنة...">
                                <input type="file" id="file-<?= e($key) ?>" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" data-max-mb="<?= (int)$maxMb ?>" required>
                                <button class="btn-add" type="submit">رفع الملف</button>
                            </form>
                            <small class="hint">الأنواع المسموحة: PDF, DOC, DOCX, JPG, PNG — الحد الأقصى <?= (int)$maxMb ?> MB</small>
                        </div>
                        <div class="table-responsive">
                            <table class="exams-table">
                                <thead><tr><th>عنوان الامتحان / السنة</th><th>الملف المرفوع</th><th>الإجراء</th></tr></thead>
                                <tbody id="tbody-<?= e($key) ?>">
                                    <tr><td><?= e($s['exam']) ?></td><td><span class="pdf-btn pdf-btn-soon">قريباً ⏳</span></td><td>—</td></tr>
                                    <?php foreach ($rows as $r): $sols = $solutions[(int)$r['id']] ?? []; ?>
                                    <tr>
                                        <td><?= e($r['title']) ?><br><?= meta_line($r) ?></td>
                                        <td><?= download_link($r) ?></td>
                                        <td>
                                            <?= can_delete_file($r) ? delete_form((int)$r['id']) : '' ?>
                                            <details class="solutions-box">
                                                <summary>💡 الحلول (<?= count($sols) ?>)</summary>
                                                <?php if ($sols): ?>
                                                <ul class="solutions-list">
                                                    <?php foreach ($sols as $sol): ?>
                                                    <li>
                                                        <span><?= e($sol['title']) ?><br><?= meta_line($sol) ?></span>
                                                        <span class="sol-actions">
                                                            <?= download_link($sol, 'حل') ?>
                                                            <?= can_delete_file($sol) ? delete_form((int)$sol['id']) : '' ?>
                                                        </span>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <?php else: ?>
                                                <p class="hint" style="margin:6px 0;">لا توجد حلول بعد — كن أول من يشارك حلاً.</p>
                                                <?php endif; ?>
                                                <form class="mini-form upload-form" method="post" action="upload.php" enctype="multipart/form-data">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="kind" value="solution">
                                                    <input type="hidden" name="subject" value="<?= e($key) ?>">
                                                    <input type="hidden" name="parent" value="<?= (int)$r['id'] ?>">
                                                    <input type="text" name="title" maxlength="200" placeholder="عنوان الحل (اختياري)">
                                                    <input type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" data-max-mb="<?= (int)$maxMb ?>" required>
                                                    <button class="btn-add" type="submit">رفع حل</button>
                                                </form>
                                            </details>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </details>
            </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </section>
    </main>

    <footer>
        <p>Student Portal & بوابة الطالب - كلية العلوم الرياضية والحاسوب © 2026</p>
    </footer>

    <script src="js/script.js"></script>
</body>

</html>
