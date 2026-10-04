<?php
// صفحة تسجيل الدخول
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
startSession();

if (isLoggedIn()) {
    if (($_SESSION['role'] ?? '') === 'doctor') {
        header('Location: doctor/dashboard.php');
    } elseif (($_SESSION['role'] ?? '') === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: student/dashboard.php');
    }
    exit;
}
$csrf = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
    <title>تسجيل الدخول — ScanEdu</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="assets/css/main.css<?php echo scanedu_asset_v(); ?>">
    <link rel="stylesheet" href="assets/css/auth.css<?php echo scanedu_asset_v(); ?>">
</head>
<body>
<div class="auth-page">
    <div class="auth-card">
        <img src="assets/images/Logo.png" alt="ScanEdu" class="auth-logo">
        <h1 class="auth-title">تسجيل الدخول</h1>
        <p class="auth-sub">أدخل بياناتك للوصول إلى النظام</p>
        <div id="login-alert" class="alert alert-danger" style="display:none;"></div>
        <form id="login-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="form-group">
                <label for="email">البريد الإلكتروني</label>
                <input type="email" class="form-control" id="email" name="email" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> تسجيل الدخول</button>
        </form>
        <p class="auth-footer">ليس لديك حساب؟ <a href="register.php">إنشاء حساب</a></p>
    </div>
</div>
<script src="assets/js/main.js<?php echo scanedu_asset_v(); ?>"></script>
<script>
document.getElementById('login-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this);
    var alertEl = document.getElementById('login-alert');
    alertEl.style.display = 'none';
    fetch('api/login.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                window.location.href = data.data && data.data.redirect ? data.data.redirect : 'index.php';
            } else {
                alertEl.textContent = data.message || 'فشل تسجيل الدخول';
                alertEl.style.display = 'block';
            }
        })
        .catch(function() {
            alertEl.textContent = 'حدث خطأ في الاتصال';
            alertEl.style.display = 'block';
        });
});
</script>
</body>
</html>
