<?php
// الصفحة الرئيسية — تحويل حسب حالة الجلسة
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
startSession();

if (isLoggedIn()) {
    header('Location: ' . scaneduUrl(dashboardPathForRole($_SESSION['role'] ?? 'student')));
    exit;
}
header('Location: login.php');
exit;
