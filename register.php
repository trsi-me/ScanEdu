<?php
// صفحة التسجيل
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . scaneduUrl(dashboardPathForRole($_SESSION['role'] ?? 'student')));
    exit;
}
$csrf = getCsrfToken();
$departments = getActiveDepartments($conn);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <title>إنشاء حساب — ScanEdu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/main.css<?php echo scanedu_asset_v(); ?>">
    <link rel="stylesheet" href="assets/css/auth.css<?php echo scanedu_asset_v(); ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <img src="assets/images/Logo.png" alt="ScanEdu" class="auth-logo">
        <h1 class="auth-title">إنشاء حساب جديد</h1>
        <p class="auth-sub">املأ البيانات التالية</p>
        <div id="reg-alert" class="alert alert-danger" style="display:none;"></div>
        <form id="register-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="name">الاسم الكامل</label>
                <input type="text" class="form-control" id="name" name="name" required minlength="2">
            </div>
            <div class="form-group">
                <label for="email">البريد الإلكتروني</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" class="form-control" id="password" name="password" required minlength="6">
            </div>
            <div class="form-group">
                <label for="password2">تأكيد كلمة المرور</label>
                <input type="password" class="form-control" id="password2" name="password2" required>
            </div>
            <div class="form-group">
                <label for="role">الدور</label>
                <select class="form-control" id="role" name="role" required>
                    <option value="">— اختر —</option>
                    <option value="student">طالب</option>
                    <option value="doctor">دكتور</option>
                </select>
            </div>
            <div id="student-fields">
                <div class="form-group">
                    <label for="student_id">رقم الطالب الجامعي</label>
                    <input type="text" class="form-control" id="student_id" name="student_id">
                </div>
            </div>
            <div class="form-group">
                <label for="department_id">القسم</label>
                <select class="form-control" id="department_id" name="department_id">
                    <option value="">— اختر القسم —</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo (int) $dept['id']; ?>"><?php echo htmlspecialchars($dept['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> تسجيل</button>
        </form>
        <p class="auth-footer">لديك حساب؟ <a href="login.php">تسجيل الدخول</a></p>
    </div>
</div>
<script src="assets/js/main.js<?php echo scanedu_asset_v(); ?>"></script>
<script>
(function() {
    var roleEl = document.getElementById('role');
    var studentFields = document.getElementById('student-fields');
    function toggleStudent() {
        var deptEl = document.getElementById('department_id');
        if (roleEl.value === 'student') {
            studentFields.classList.add('is-visible');
            document.getElementById('student_id').required = true;
            deptEl.required = true;
        } else {
            studentFields.classList.remove('is-visible');
            document.getElementById('student_id').required = false;
            deptEl.required = roleEl.value === 'doctor';
        }
    }
    roleEl.addEventListener('change', toggleStudent);
    toggleStudent();

    document.getElementById('register-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var alertEl = document.getElementById('reg-alert');
        alertEl.style.display = 'none';
        var p1 = document.getElementById('password').value;
        var p2 = document.getElementById('password2').value;
        if (p1 !== p2) {
            alertEl.textContent = 'كلمتا المرور غير متطابقتين';
            alertEl.style.display = 'block';
            return;
        }
        if (document.getElementById('role').value === 'student') {
            var sid = document.getElementById('student_id').value.trim();
            if (!sid) {
                alertEl.textContent = 'رقم الطالب مطلوب للطلاب';
                alertEl.style.display = 'block';
                return;
            }
        }
        var fd = new FormData(this);
        fetch('api/register.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.href = 'login.php';
                } else {
                    alertEl.textContent = data.message || 'فشل التسجيل';
                    alertEl.style.display = 'block';
                }
            })
            .catch(function() {
                alertEl.textContent = 'حدث خطأ في الاتصال';
                alertEl.style.display = 'block';
            });
    });
})();
</script>
</body>
</html>
