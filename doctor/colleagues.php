<?php
// زملاء الدكتور في نفس القسم
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('doctor');
$user = getCurrentUser();
$doctorId = (int) $_SESSION['user_id'];
$departmentId = (int) ($user['department_id'] ?? 0);
$departmentName = $user['department_name'] ?? ($user['department'] ?? '');

$colleagues = [];
if ($departmentId > 0) {
    $stmt = $conn->prepare(
        'SELECT u.id, u.name, u.email,
                (SELECT COUNT(*) FROM courses c WHERE c.doctor_id = u.id) AS courses_count
         FROM users u
         WHERE u.role = \'doctor\' AND u.status = \'active\' AND u.department_id = ? AND u.id != ?
         ORDER BY u.name ASC'
    );
    $stmt->bind_param('ii', $departmentId, $doctorId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $colleagues[] = $row;
    }
    $stmt->close();
}

$colleagueCourses = [];
if ($departmentId > 0) {
    $stmt = $conn->prepare(
        'SELECT c.id, c.name, c.code, c.semester, u.name AS doctor_name
         FROM courses c
         INNER JOIN users u ON u.id = c.doctor_id
         WHERE u.role = \'doctor\' AND u.department_id = ? AND c.doctor_id != ?
         ORDER BY c.semester DESC, c.name ASC'
    );
    $stmt->bind_param('ii', $departmentId, $doctorId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $colleagueCourses[] = $row;
    }
    $stmt->close();
}

$pageTitle = 'زملاء القسم';
$activeNav = 'colleagues';
$role = 'doctor';
$extraCss = ['assignments.css'];
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">زملاء القسم</h1>
<?php if ($departmentId <= 0): ?>
    <div class="alert alert-danger">لم يُعيَّن لك قسم بعد. تواصل مع الإدارة لتعيينك لقسم.</div>
<?php else: ?>
    <p class="text-muted">قسمك: <strong><?php echo htmlspecialchars($departmentName, ENT_QUOTES, 'UTF-8'); ?></strong> — يمكن لعدة دكاترة في نفس القسم تدريس مواد متقاربة.</p>

    <div class="assign-form-card">
        <h2 class="h2-title">الدكاترة في قسمك</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>البريد</th>
                        <th>عدد المقررات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colleagues as $col): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($col['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($col['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo (int) $col['courses_count']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($colleagues) === 0): ?>
                        <tr><td colspan="3" class="text-muted">لا يوجد دكاترة آخرون في قسمك حالياً.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="assign-form-card">
        <h2 class="h2-title">مقررات الزملاء في القسم</h2>
        <p class="text-muted">للاطلاع فقط — مقررات الدكاترة الآخرين في قسمك.</p>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>المقرر</th>
                        <th>الرمز</th>
                        <th>الترم</th>
                        <th>الدكتور</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($colleagueCourses as $cc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($cc['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($cc['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($cc['semester'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($cc['doctor_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (count($colleagueCourses) === 0): ?>
                        <tr><td colspan="4" class="text-muted">لا توجد مقررات لزملائك بعد.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

        </main>
    </div>
    </div>
</div>
</body>
</html>
