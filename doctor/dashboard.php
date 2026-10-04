<?php
// لوحة تحكم الدكتور
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('doctor');
$user = getCurrentUser();
$doctorId = (int) $_SESSION['user_id'];

$stmt = $conn->prepare('SELECT COUNT(*) AS c FROM courses WHERE doctor_id = ?');
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$coursesCount = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare(
    'SELECT COUNT(DISTINCT ce.student_id) AS c FROM course_enrollments ce
     INNER JOIN courses c ON c.id = ce.course_id WHERE c.doctor_id = ?'
);
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$studentsCount = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare(
    'SELECT l.title, l.lecture_date, co.name AS course_name FROM lectures l
     INNER JOIN courses co ON co.id = l.course_id
     WHERE co.doctor_id = ? ORDER BY l.created_at DESC LIMIT 1'
);
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$lastLecture = $stmt->get_result()->fetch_assoc();
$stmt->close();

$attendanceRate = 0.0;
$stmt = $conn->prepare(
    'SELECT l.id, (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = l.course_id) AS enr,
     (SELECT COUNT(*) FROM attendance a WHERE a.lecture_id = l.id) AS att
     FROM lectures l INNER JOIN courses co ON co.id = l.course_id
     WHERE co.doctor_id = ? ORDER BY l.created_at DESC LIMIT 5'
);
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$res = $stmt->get_result();
$rates = [];
while ($row = $res->fetch_assoc()) {
    $enr = (int) $row['enr'];
    if ($enr > 0) {
        $rates[] = min(100, round(((int) $row['att'] / $enr) * 100, 1));
    }
}
$stmt->close();
if (count($rates) > 0) {
    $attendanceRate = round(array_sum($rates) / count($rates), 1);
}

$chartLabels = ['أسبوع 1', 'أسبوع 2', 'أسبوع 3', 'أسبوع 4', 'أسبوع 5', 'أسبوع 6'];
$pad = $attendanceRate;
if (count($rates) === 0) {
    $pad = 0;
}
$chartValues = array_slice(array_pad(array_slice($rates, -6), 6, $pad), 0, 6);
$chartValues = array_map(function ($v) {
    return round((float) $v, 1);
}, $chartValues);

$activities = [];
$stmt = $conn->prepare(
    'SELECT \'lecture\' AS type, l.title AS label, l.created_at AS at FROM lectures l
     INNER JOIN courses co ON co.id = l.course_id WHERE co.doctor_id = ?
     UNION ALL
     SELECT \'assignment\', a.title, a.created_at FROM assignments a
     INNER JOIN courses co ON co.id = a.course_id WHERE co.doctor_id = ?
     UNION ALL
     SELECT \'exam\', e.title, e.created_at FROM exams e
     INNER JOIN courses co ON co.id = e.course_id WHERE co.doctor_id = ?
     ORDER BY at DESC LIMIT 8'
);
$stmt->bind_param('iii', $doctorId, $doctorId, $doctorId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $activities[] = $row;
}
$stmt->close();

$pageTitle = 'لوحة التحكم';
$activeNav = 'dashboard';
$role = 'doctor';
$extraCss = [];
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">مرحباً، <?php echo htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
<p class="text-muted">نظرة سريعة على مقرراتك ونشاط الطلاب.</p>

<div class="stats-grid">
    <div class="stat-card">
        <div class="label">عدد المقررات</div>
        <div class="value"><?php echo $coursesCount; ?></div>
    </div>
    <div class="stat-card">
        <div class="label">إجمالي الطلاب المسجّلين</div>
        <div class="value"><?php echo $studentsCount; ?></div>
    </div>
    <div class="stat-card">
        <div class="label">آخر محاضرة</div>
        <div class="value" style="font-size:16px;">
            <?php echo $lastLecture ? htmlspecialchars($lastLecture['title'], ENT_QUOTES, 'UTF-8') : '—'; ?>
        </div>
        <?php if ($lastLecture): ?>
            <div class="text-muted"><?php echo htmlspecialchars($lastLecture['course_name'] . ' — ' . formatDate($lastLecture['lecture_date']), ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
    </div>
    <div class="stat-card">
        <div class="label">متوسط نسبة الحضور (آخر محاضرات)</div>
        <div class="value"><?php echo $attendanceRate; ?>٪</div>
    </div>
</div>

<div class="quick-links">
    <a class="btn btn-primary" href="attendance.php"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> توليد QR تحضير</a>
    <a class="btn btn-secondary" href="assignments.php"><i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i> إضافة واجب</a>
    <a class="btn btn-primary" href="exams.php"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i> إنشاء اختبار</a>
    <a class="btn btn-outline" href="courses.php"><i class="fa-solid fa-book" aria-hidden="true"></i> إدارة المقررات</a>
</div>

<div class="chart-card">
    <h2 class="h2-title">نسب الحضور عبر الأسابيع</h2>
    <canvas id="attChart" height="120"></canvas>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>النوع</th>
                <th>العنوان</th>
                <th>الوقت</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($activities) === 0): ?>
                <tr><td colspan="3" class="text-muted">لا يوجد نشاط بعد.</td></tr>
            <?php else: ?>
                <?php foreach ($activities as $act): ?>
                    <tr>
                        <td><?php echo $act['type'] === 'lecture' ? 'محاضرة' : ($act['type'] === 'assignment' ? 'واجب' : 'اختبار'); ?></td>
                        <td><?php echo htmlspecialchars($act['label'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($act['at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function() {
    var ctx = document.getElementById('attChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                label: 'متوسط نسبة الحضور ٪',
                data: <?php echo json_encode($chartValues, JSON_UNESCAPED_UNICODE); ?>,
                backgroundColor: 'rgba(21, 101, 192, 0.7)',
                borderColor: '#1565C0',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: { y: { beginAtZero: true, max: 100 } }
        }
    });
})();
</script>

        </main>
    </div>
    </div>
</div>
</body>
</html>
