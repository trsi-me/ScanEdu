<?php
// واجبات الطالب
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('student');
$user = getCurrentUser();
$studentId = (int) $_SESSION['user_id'];
$viewId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$detail = null;
$already = false;
if ($viewId > 0) {
    $stmt = $conn->prepare(
        'SELECT a.*, c.name AS course_name FROM assignments a
         INNER JOIN courses c ON c.id = a.course_id
         INNER JOIN course_enrollments ce ON ce.course_id = c.id AND ce.student_id = ?
         WHERE a.id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $studentId, $viewId);
    $stmt->execute();
    $detail = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($detail) {
        $chk = $conn->prepare('SELECT id FROM assignment_submissions WHERE assignment_id = ? AND student_id = ? LIMIT 1');
        $chk->bind_param('ii', $viewId, $studentId);
        $chk->execute();
        $chk->store_result();
        $already = $chk->num_rows > 0;
        $chk->close();
    }
}

$list = [];
$stmt = $conn->prepare(
    'SELECT a.id, a.title, a.due_date, a.max_score, c.name AS course_name,
     (SELECT COUNT(*) FROM assignment_submissions s WHERE s.assignment_id = a.id AND s.student_id = ?) AS submitted
     FROM assignments a
     INNER JOIN courses c ON c.id = a.course_id
     INNER JOIN course_enrollments ce ON ce.course_id = c.id AND ce.student_id = ?
     ORDER BY a.due_date DESC'
);
$stmt->bind_param('ii', $studentId, $studentId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $list[] = $row;
}
$stmt->close();

$pageTitle = 'الواجبات';
$activeNav = 'assignments';
$role = 'student';
$extraCss = ['assignments.css'];
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">الواجبات</h1>

<?php if ($detail): ?>
    <div class="assign-form-card assign-submit">
        <h2 class="h2-title"><?php echo htmlspecialchars($detail['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="text-muted"><?php echo htmlspecialchars($detail['course_name'], ENT_QUOTES, 'UTF-8'); ?> — التسليم: <?php echo htmlspecialchars($detail['due_date'], ENT_QUOTES, 'UTF-8'); ?> — الدرجة القصوى: <?php echo (int) $detail['max_score']; ?></p>
        <p><?php echo nl2br(htmlspecialchars($detail['description'], ENT_QUOTES, 'UTF-8')); ?></p>
        <?php if ($already): ?>
            <div class="alert alert-success">تم تسليم هذا الواجب مسبقاً.</div>
        <?php else: ?>
            <div id="asg-msg" class="alert" style="display:none;"></div>
            <form id="form-submit-asg" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="assignment_id" value="<?php echo (int) $detail['id']; ?>">
                <div class="form-group">
                    <label>إجابتك</label>
                    <textarea name="answer" class="form-control" required rows="8"></textarea>
                </div>
                <div class="form-group">
                    <label>مرفق (اختياري)</label>
                    <input type="file" name="file" class="form-control">
                </div>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> تسليم الواجب</button>
            </form>
            <script>
            document.getElementById('form-submit-asg').addEventListener('submit', function(e) {
                e.preventDefault();
                var fd = new FormData(this);
                fetch('../api/assignments/submit_assignment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var m = document.getElementById('asg-msg');
                        m.style.display = 'block';
                        if (data.success) {
                            m.className = 'alert alert-success';
                            m.textContent = data.message;
                        } else {
                            m.className = 'alert alert-danger';
                            m.textContent = data.message || 'فشل';
                        }
                    });
            });
            </script>
        <?php endif; ?>
        <p style="margin-top:16px;"><a href="assignments.php" class="btn btn-outline"><i class="fa-solid fa-list" aria-hidden="true"></i> العودة للقائمة</a></p>
    </div>
<?php endif; ?>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr><th>العنوان</th><th>المقرر</th><th>التسليم</th><th>الحالة</th></tr>
        </thead>
        <tbody>
            <?php foreach ($list as $row): ?>
                <tr>
                    <td><a href="assignments.php?id=<?php echo (int) $row['id']; ?>"><?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></a></td>
                    <td><?php echo htmlspecialchars($row['course_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($row['due_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo ((int) $row['submitted'] > 0) ? 'مُسلَّم' : 'مطلوب'; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($list) === 0): ?>
                <tr><td colspan="4" class="text-muted">لا توجد واجبات.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

        </main>
    </div>
    </div>
</div>
</body>
</html>
