<?php
// هيدر مشترك للوحات التحكم — يتطلع: $pageTitle, $activeNav, $role، واختياري $extraCss (مصفوفة أسماء ملفات css تحت assets/css)
if (!isset($pageTitle)) {
    $pageTitle = 'ScanEdu';
}
if (!isset($activeNav)) {
    $activeNav = '';
}
if (!isset($role)) {
    $role = $_SESSION['role'] ?? 'student';
}
if (!isset($user)) {
    $user = getCurrentUser();
}
$name = $user['name'] ?? 'مستخدم';
$extraCss = $extraCss ?? [];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($pageTitle . ' — ScanEdu', ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="../assets/css/main.css<?php echo scanedu_asset_v(); ?>">
    <link rel="stylesheet" href="../assets/css/dashboard.css<?php echo scanedu_asset_v(); ?>">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="../assets/css/<?php echo htmlspecialchars($css, ENT_QUOTES, 'UTF-8'); ?><?php echo scanedu_asset_v(); ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="app-shell">
    <header class="top-bar">
        <div class="top-bar-inner">
            <div class="top-bar-brand">
                <span class="top-bar-logo-badge">
                    <img class="top-bar-logo-img" src="../assets/images/Logo.png" alt="شعار ScanEdu" decoding="async">
                </span>
                <strong class="top-bar-title">ScanEdu</strong>
            </div>
            <div class="top-bar-user">
                <span class="top-bar-user-name"><i class="fa-solid fa-user" aria-hidden="true"></i> <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="top-bar-sep" aria-hidden="true">|</span>
                <a class="top-bar-logout" href="../api/logout.php"><i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i> تسجيل الخروج</a>
            </div>
        </div>
    </header>
    <div class="layout-body">
        <div class="layout-zone">
        <aside class="sidebar">
            <nav>
                <?php if ($role === 'doctor'): ?>
                    <a href="dashboard.php" class="<?php echo $activeNav === 'dashboard' ? 'is-active' : ''; ?>"><i class="fa-solid fa-chart-line sidebar-icon" aria-hidden="true"></i><span>لوحة التحكم</span></a>
                    <a href="attendance.php" class="<?php echo $activeNav === 'attendance' ? 'is-active' : ''; ?>"><i class="fa-solid fa-qrcode sidebar-icon" aria-hidden="true"></i><span>التحضير</span></a>
                    <a href="assignments.php" class="<?php echo $activeNav === 'assignments' ? 'is-active' : ''; ?>"><i class="fa-solid fa-file-lines sidebar-icon" aria-hidden="true"></i><span>الواجبات</span></a>
                    <a href="exams.php" class="<?php echo $activeNav === 'exams' ? 'is-active' : ''; ?>"><i class="fa-solid fa-clipboard-question sidebar-icon" aria-hidden="true"></i><span>الاختبارات</span></a>
                    <a href="courses.php" class="<?php echo $activeNav === 'courses' ? 'is-active' : ''; ?>"><i class="fa-solid fa-book sidebar-icon" aria-hidden="true"></i><span>المقررات</span></a>
                    <a href="colleagues.php" class="<?php echo $activeNav === 'colleagues' ? 'is-active' : ''; ?>"><i class="fa-solid fa-user-group sidebar-icon" aria-hidden="true"></i><span>زملاء القسم</span></a>
                <?php elseif ($role === 'admin'): ?>
                    <a href="dashboard.php" class="<?php echo $activeNav === 'dashboard' ? 'is-active' : ''; ?>"><i class="fa-solid fa-chart-line sidebar-icon" aria-hidden="true"></i><span>لوحة التحكم</span></a>
                    <a href="departments.php" class="<?php echo $activeNav === 'departments' ? 'is-active' : ''; ?>"><i class="fa-solid fa-building sidebar-icon" aria-hidden="true"></i><span>الأقسام</span></a>
                    <a href="terms.php" class="<?php echo $activeNav === 'terms' ? 'is-active' : ''; ?>"><i class="fa-solid fa-calendar-days sidebar-icon" aria-hidden="true"></i><span>الترمات</span></a>
                    <a href="users.php" class="<?php echo $activeNav === 'users' ? 'is-active' : ''; ?>"><i class="fa-solid fa-users sidebar-icon" aria-hidden="true"></i><span>المستخدمون</span></a>
                <?php else: ?>
                    <a href="dashboard.php" class="<?php echo $activeNav === 'dashboard' ? 'is-active' : ''; ?>"><i class="fa-solid fa-house sidebar-icon" aria-hidden="true"></i><span>لوحة التحكم</span></a>
                    <a href="scan.php" class="<?php echo $activeNav === 'scan' ? 'is-active' : ''; ?>"><i class="fa-solid fa-camera-viewfinder sidebar-icon" aria-hidden="true"></i><span>سكان QR</span></a>
                    <a href="assignments.php" class="<?php echo $activeNav === 'assignments' ? 'is-active' : ''; ?>"><i class="fa-solid fa-file-lines sidebar-icon" aria-hidden="true"></i><span>الواجبات</span></a>
                    <a href="exams.php" class="<?php echo $activeNav === 'exams' ? 'is-active' : ''; ?>"><i class="fa-solid fa-pen-to-square sidebar-icon" aria-hidden="true"></i><span>الاختبارات</span></a>
                <?php endif; ?>
            </nav>
        </aside>
        <main class="main-content">
