<?php
// لوحة تحكم الأدمن
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('admin');

$stats = [
    'departments' => 0,
    'doctors' => 0,
    'students' => 0,
    'terms' => 0,
    'courses' => 0,
];

$res = $conn->query('SELECT COUNT(*) AS c FROM departments WHERE is_active = 1');
if ($res) {
    $stats['departments'] = (int) ($res->fetch_assoc()['c'] ?? 0);
}
$res = $conn->query('SELECT COUNT(*) AS c FROM users WHERE role = \'doctor\' AND status = \'active\'');
if ($res) {
    $stats['doctors'] = (int) ($res->fetch_assoc()['c'] ?? 0);
}
$res = $conn->query('SELECT COUNT(*) AS c FROM users WHERE role = \'student\' AND status = \'active\'');
if ($res) {
    $stats['students'] = (int) ($res->fetch_assoc()['c'] ?? 0);
}
$res = $conn->query('SELECT COUNT(*) AS c FROM terms WHERE is_active = 1');
if ($res) {
    $stats['terms'] = (int) ($res->fetch_assoc()['c'] ?? 0);
}
$res = $conn->query('SELECT COUNT(*) AS c FROM courses');
if ($res) {
    $stats['courses'] = (int) ($res->fetch_assoc()['c'] ?? 0);
}

$currentTerm = getCurrentTerm($conn);

$pageTitle = 'لوحة التحكم';
$activeNav = 'dashboard';
$role = 'admin';
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">لوحة تحكم الإدارة</h1>
<p class="text-muted">إدارة الأقسام والترمات والمستخدمين — الطلاب مسجّلون مرة واحدة ويبقون في النظام بين الترمات.</p>

<?php if ($currentTerm): ?>
    <div class="alert alert-success">الترم الحالي: <strong><?php echo htmlspecialchars($currentTerm['name'], ENT_QUOTES, 'UTF-8'); ?></strong></div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">الأقسام</div>
        <div class="value"><?php echo $stats['departments']; ?></div>
    </div>
    <div class="stat-card">
        <div class="label">الدكاترة</div>
        <div class="value"><?php echo $stats['doctors']; ?></div>
    </div>
    <div class="stat-card">
        <div class="label">الطلاب</div>
        <div class="value"><?php echo $stats['students']; ?></div>
    </div>
    <div class="stat-card">
        <div class="label">الترمات</div>
        <div class="value"><?php echo $stats['terms']; ?></div>
    </div>
    <div class="stat-card">
        <div class="label">المقررات</div>
        <div class="value"><?php echo $stats['courses']; ?></div>
    </div>
</div>

        </main>
    </div>
    </div>
</div>
</body>
</html>
