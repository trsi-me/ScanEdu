<?php
// إدارة التحضير — أسابيع الترم (15) جاهزة لكل مقرر؛ يختار الدكتور الأسبوع ثم يولّد QR
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('doctor');
$user = getCurrentUser();
$doctorId = (int) $_SESSION['user_id'];
$flash = '';

$courses = [];
$stmt = $conn->prepare('SELECT id, name, code FROM courses WHERE doctor_id = ? ORDER BY name ASC');
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $courses[] = $row;
    ensureCourseWeeks($conn, (int) $row['id']);
}
$stmt->close();

$selectedCourse = isset($_GET['course_id']) ? (int) $_GET['course_id'] : ($courses[0]['id'] ?? 0);
$lectures = [];
if ($selectedCourse > 0) {
    $own = $conn->prepare('SELECT id FROM courses WHERE id = ? AND doctor_id = ? LIMIT 1');
    $own->bind_param('ii', $selectedCourse, $doctorId);
    $own->execute();
    $own->store_result();
    if ($own->num_rows > 0) {
        $own->close();
        $lq = $conn->prepare(
            'SELECT id, week_number, title, lecture_date, qr_expires_at FROM lectures WHERE course_id = ? ORDER BY week_number ASC, id ASC'
        );
        $lq->bind_param('i', $selectedCourse);
        $lq->execute();
        $lr = $lq->get_result();
        while ($row = $lr->fetch_assoc()) {
            $lectures[] = $row;
        }
        $lq->close();
    } else {
        $own->close();
    }
}

$selectedLecture = isset($_GET['lecture_id']) ? (int) $_GET['lecture_id'] : ($lectures[0]['id'] ?? 0);

$pageTitle = 'التحضير';
$activeNav = 'attendance';
$role = 'doctor';
$extraCss = ['attendance.css'];
$av = scanedu_asset_v();
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">إدارة التحضير</h1>
<p class="text-muted">كل مقرر يضم 15 أسبوعاً مسجّلة تلقائياً. اختر المقرر ثم رقم الأسبوع، واعرض رمز QR للطلاب المسجّلين في المقرر.</p>
<?php if ($flash !== ''): ?>
    <div class="alert <?php echo str_contains($flash, 'تم') ? 'alert-success' : 'alert-danger'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<form method="get" class="attendance-toolbar">
    <div class="form-group">
        <label>المقرر</label>
        <select name="course_id" class="form-control" onchange="this.form.submit()">
            <?php foreach ($courses as $c): ?>
                <option value="<?php echo (int) $c['id']; ?>" <?php echo (int) $c['id'] === $selectedCourse ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label>أسبوع الترم (1–15)</label>
        <select name="lecture_id" class="form-control" onchange="this.form.submit()">
            <?php foreach ($lectures as $l): ?>
                <option value="<?php echo (int) $l['id']; ?>" <?php echo (int) $l['id'] === $selectedLecture ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars('الأسبوع ' . (int) $l['week_number'] . ' — ' . $l['lecture_date'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($selectedLecture > 0): ?>
    <p class="attendance-actions">
        <button type="button" class="btn btn-primary" id="btn-gen-qr"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> توليد QR</button>
    </p>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>رقم</th>
                    <th>اسم الطالب</th>
                    <th>الرقم الجامعي</th>
                    <th>البريد</th>
                    <th>القسم</th>
                    <th>وقت التسجيل</th>
                    <th>الحالة</th>
                </tr>
            </thead>
            <tbody id="attendance-body"></tbody>
        </table>
    </div>
<?php else: ?>
    <p class="text-muted">لا توجد أسابيع مسجّلة لهذا المقرر بعد. افتح صفحة «المقررات» مرة لإنشاء الجدول تلقائياً.</p>
<?php endif; ?>

<div class="modal-overlay" id="qr-modal" aria-hidden="true">
    <div class="modal-box">
        <button type="button" class="modal-close" id="qr-modal-close">إغلاق</button>
        <h2 class="h2-title" style="text-align:center;">رمز التحضير</h2>
        <div id="qr-canvas-wrap"><div id="qr-modal-inner"></div></div>
        <div class="qr-modal-meta" id="qr-meta"></div>
        <div class="countdown-display" id="qr-countdown"></div>
        <p style="text-align:center;margin-top:12px;">
            <button type="button" class="btn btn-outline" id="btn-dl-qr"><i class="fa-solid fa-download" aria-hidden="true"></i> تحميل الصورة</button>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
<script src="../assets/js/qr-generator.js<?php echo $av; ?>"></script>
<script src="../assets/js/attendance.js<?php echo $av; ?>"></script>
<script>
(function() {
    var lectureId = <?php echo (int) $selectedLecture; ?>;
    var courseName = <?php
        $cn = '';
        foreach ($courses as $c) {
            if ((int) $c['id'] === (int) $selectedCourse) {
                $cn = $c['name'];
                break;
            }
        }
        echo json_encode($cn, JSON_UNESCAPED_UNICODE);
    ?>;
    var lecTitle = '';
    <?php foreach ($lectures as $l): if ((int) $l['id'] === (int) $selectedLecture): ?>
    lecTitle = <?php echo json_encode('الأسبوع ' . (int) $l['week_number'], JSON_UNESCAPED_UNICODE); ?>;
    <?php endif; endforeach; ?>

    if (lectureId > 0) {
        startLiveAttendance(lectureId, 'attendance-body');
    }

    var modal = document.getElementById('qr-modal');
    document.getElementById('qr-modal-close').addEventListener('click', function() {
        modal.classList.remove('is-open');
    });

    var btnGen = document.getElementById('btn-gen-qr');
    if (btnGen) {
        btnGen.addEventListener('click', function() {
        var fd = new FormData();
        fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
        fd.append('lecture_id', String(lectureId));
        fetch('../api/attendance/generate_qr.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    alert(data.message || 'فشل التوليد');
                    return;
                }
                var token = data.data.qr_token;
                var exp = data.data.expires_at;
                var payload = JSON.stringify({ type: 'attendance', token: token });
                document.getElementById('qr-modal-inner').innerHTML = '';
                generateQR('qr-modal-inner', payload, 320);
                document.getElementById('qr-meta').textContent = courseName + ' — ' + lecTitle + ' — ينتهي: ' + exp;
                modal.classList.add('is-open');
                startCountdown(exp, 'qr-countdown', function() {
                    alert('انتهت صلاحية عرض العداد — أعد توليد الرمز إن لزم');
                });
                document.getElementById('btn-dl-qr').onclick = function() {
                    downloadQR('qr-modal-inner', 'scanedu-attendance.png');
                };
            })
            .catch(function() { alert('خطأ في الاتصال'); });
        });
    }
})();
</script>

        </main>
    </div>
    </div>
</div>
</body>
</html>
