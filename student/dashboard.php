<?php
// لوحة تحكم الطالب
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('student');
$user = getCurrentUser();
$studentId = (int) $_SESSION['user_id'];

$courses = [];
$stmt = $conn->prepare(
    'SELECT c.id, c.name, c.code FROM courses c
     INNER JOIN course_enrollments ce ON ce.course_id = c.id
     WHERE ce.student_id = ? ORDER BY c.name ASC'
);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

$lastAtt = null;
$stmt = $conn->prepare(
    'SELECT l.title, l.lecture_date, co.name AS course_name FROM attendance a
     INNER JOIN lectures l ON l.id = a.lecture_id
     INNER JOIN courses co ON co.id = l.course_id
     WHERE a.student_id = ? ORDER BY a.scanned_at DESC LIMIT 1'
);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$lastAtt = $stmt->get_result()->fetch_assoc();
$stmt->close();

$dueAssign = [];
$stmt = $conn->prepare(
    'SELECT a.id, a.title, a.due_date, c.name AS course_name FROM assignments a
     INNER JOIN courses c ON c.id = a.course_id
     INNER JOIN course_enrollments ce ON ce.course_id = c.id AND ce.student_id = ?
     LEFT JOIN assignment_submissions s ON s.assignment_id = a.id AND s.student_id = ?
     WHERE s.id IS NULL AND a.due_date >= NOW()
     ORDER BY a.due_date ASC LIMIT 5'
);
$stmt->bind_param('ii', $studentId, $studentId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $dueAssign[] = $row;
}
$stmt->close();

$upcomingExams = [];
$stmt = $conn->prepare(
    'SELECT e.id, e.title, e.start_time, e.end_time, c.name AS course_name FROM exams e
     INNER JOIN courses c ON c.id = e.course_id
     INNER JOIN course_enrollments ce ON ce.course_id = c.id AND ce.student_id = ?
     WHERE e.end_time >= NOW()
     ORDER BY e.start_time ASC LIMIT 5'
);
$stmt->bind_param('i', $studentId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $upcomingExams[] = $row;
}
$stmt->close();

$pageTitle = 'لوحة الطالب';
$activeNav = 'dashboard';
$role = 'student';
$extraCss = [];
include __DIR__ . '/../includes/header.php';
?>
<div class="student-dashboard">
    <section class="dashboard-hero" aria-label="ترحيب وإجراء سريع">
        <div class="dashboard-hero-text">
            <p class="dashboard-hero-kicker">لوحة الطالب</p>
            <h1 class="dashboard-hero-title">مرحباً، <?php echo htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
            <p class="dashboard-hero-meta">
                <span class="hero-badge"><i class="fa-solid fa-book-open" aria-hidden="true"></i> <?php echo count($courses); ?> مقرر مسجّل</span>
                <span>سجّل حضورك عبر مسح رمز التحضير من شاشة الدكتور.</span>
            </p>
        </div>
        <div class="dashboard-hero-cta">
            <a href="scan.php" class="btn btn-primary btn-lg"><i class="fa-solid fa-camera-viewfinder" aria-hidden="true"></i> سكان QR الآن</a>
        </div>
    </section>

    <div class="student-dashboard-grid">
        <section class="panel-card" aria-labelledby="student-courses-heading">
            <h2 class="panel-card-title" id="student-courses-heading"><i class="fa-solid fa-layer-group" aria-hidden="true"></i> مقرراتي</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>المقرر</th><th>الرمز</th></tr></thead>
                    <tbody>
                        <?php foreach ($courses as $c): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><span class="badge badge-muted"><?php echo htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (count($courses) === 0): ?>
                            <tr><td colspan="2" class="text-muted">لم يُسجّل لك أي مقرر بعد.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel-card" aria-labelledby="student-last-att-heading">
            <h2 class="panel-card-title" id="student-last-att-heading"><i class="fa-solid fa-user-check" aria-hidden="true"></i> آخر حضور</h2>
            <div class="panel-card-body">
                <?php if ($lastAtt): ?>
                    <p><?php echo htmlspecialchars($lastAtt['course_name'] . ' — ' . $lastAtt['title'] . ' — ' . formatDate($lastAtt['lecture_date']), ENT_QUOTES, 'UTF-8'); ?></p>
                <?php else: ?>
                    <p class="text-muted">لا يوجد سجل حضور بعد. استخدم «سكان QR» عند عرض الدكتور للرمز.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="panel-card panel-card-wide" aria-labelledby="student-assign-heading">
            <h2 class="panel-card-title" id="student-assign-heading"><i class="fa-solid fa-file-lines" aria-hidden="true"></i> واجبات تحتاج تسليم</h2>
            <?php if (count($dueAssign) === 0): ?>
                <p class="dashboard-empty">لا توجد واجبات مستحقة حالياً.</p>
            <?php else: ?>
                <ul class="dashboard-tiles">
                    <?php foreach ($dueAssign as $a): ?>
                        <li>
                            <a class="dashboard-tile-link" href="assignments.php?id=<?php echo (int) $a['id']; ?>">
                                <span class="dashboard-tile-main"><?php echo htmlspecialchars($a['title'] . ' — ' . $a['course_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="dashboard-tile-meta"><i class="fa-solid fa-clock" aria-hidden="true"></i> آخر أجل: <?php echo htmlspecialchars($a['due_date'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section class="panel-card panel-card-wide" aria-labelledby="student-exams-heading">
            <h2 class="panel-card-title" id="student-exams-heading"><i class="fa-solid fa-clipboard-question" aria-hidden="true"></i> اختبارات قادمة</h2>
            <?php if (count($upcomingExams) === 0): ?>
                <p class="dashboard-empty">لا توجد اختبارات قادمة.</p>
            <?php else: ?>
                <ul class="dashboard-tiles">
                    <?php foreach ($upcomingExams as $e): ?>
                        <li>
                            <a class="dashboard-tile-link" href="exams.php?id=<?php echo (int) $e['id']; ?>">
                                <span class="dashboard-tile-main"><?php echo htmlspecialchars($e['title'] . ' — ' . $e['course_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="dashboard-tile-meta"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i> يبدأ: <?php echo htmlspecialchars($e['start_time'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </div>
</div>

        </main>
    </div>
    </div>
</div>
</body>
</html>
