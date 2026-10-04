<?php
// إدارة المقررات وتسجيل الطلاب
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('doctor');
$user = getCurrentUser();
$doctorId = (int) $_SESSION['user_id'];
$departmentId = (int) ($user['department_id'] ?? 0);
$flash = '';
$terms = getActiveTerms($conn);
$currentTerm = getCurrentTerm($conn);

$deptStudentCount = 0;
if ($departmentId > 0) {
    $dc = $conn->prepare('SELECT COUNT(*) AS c FROM users WHERE role = \'student\' AND status = \'active\' AND department_id = ?');
    $dc->bind_param('i', $departmentId);
    $dc->execute();
    $deptStudentCount = (int) ($dc->get_result()->fetch_assoc()['c'] ?? 0);
    $dc->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_course'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $code = sanitize($_POST['code'] ?? '');
        $termId = (int) ($_POST['term_id'] ?? 0);
        $autoEnroll = isset($_POST['auto_enroll_dept']);
        if ($name === '' || $code === '' || $termId <= 0) {
            $flash = 'أكمل الحقول واختر الترم';
        } else {
            $tStmt = $conn->prepare('SELECT name FROM terms WHERE id = ? AND is_active = 1 LIMIT 1');
            $tStmt->bind_param('i', $termId);
            $tStmt->execute();
            $termRow = $tStmt->get_result()->fetch_assoc();
            $tStmt->close();
            if (!$termRow) {
                $flash = 'الترم غير صالح';
            } else {
                $sem = $termRow['name'];
                $ins = $conn->prepare('INSERT INTO courses (name, code, doctor_id, semester, term_id) VALUES (?, ?, ?, ?, ?)');
                $ins->bind_param('ssisi', $name, $code, $doctorId, $sem, $termId);
                if ($ins->execute()) {
                    $newCourseId = (int) $conn->insert_id;
                    ensureCourseWeeks($conn, $newCourseId);
                    $enrolled = 0;
                    if ($autoEnroll && $departmentId > 0) {
                        $enrolled = enrollDepartmentStudents($conn, $newCourseId, $departmentId);
                    }
                    $flash = $enrolled > 0
                        ? 'تم إنشاء المقرر وتسجيل ' . $enrolled . ' طالباً من القسم'
                        : 'تم إنشاء المقرر مع جدول 15 أسبوعاً';
                } else {
                    $flash = 'تعذر الحفظ (ربما الكود مكرر)';
                }
                $ins->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_department'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        if ($courseId <= 0 || $departmentId <= 0) {
            $flash = 'بيانات غير صالحة أو لم يُعيَّن لك قسم';
        } else {
            $own = $conn->prepare('SELECT id FROM courses WHERE id = ? AND doctor_id = ? LIMIT 1');
            $own->bind_param('ii', $courseId, $doctorId);
            $own->execute();
            $own->store_result();
            if ($own->num_rows === 0) {
                $flash = 'مقرر غير موجود';
            } else {
                $count = enrollDepartmentStudents($conn, $courseId, $departmentId);
                $flash = $count > 0 ? 'تم تسجيل ' . $count . ' طالباً من القسم' : 'جميع طلاب القسم مسجّلون مسبقاً';
            }
            $own->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['copy_enrollments'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $fromCourseId = (int) ($_POST['from_course_id'] ?? 0);
        if ($courseId <= 0 || $fromCourseId <= 0) {
            $flash = 'اختر المقررين';
        } else {
            $own = $conn->prepare('SELECT id FROM courses WHERE id = ? AND doctor_id = ? LIMIT 1');
            $own->bind_param('ii', $courseId, $doctorId);
            $own->execute();
            $own->store_result();
            $from = $conn->prepare('SELECT id FROM courses WHERE id = ? AND doctor_id = ? LIMIT 1');
            $from->bind_param('ii', $fromCourseId, $doctorId);
            $from->execute();
            $from->store_result();
            if ($own->num_rows === 0 || $from->num_rows === 0) {
                $flash = 'مقرر غير موجود';
            } else {
                $count = copyCourseEnrollments($conn, $fromCourseId, $courseId);
                $flash = $count > 0 ? 'تم نسخ تسجيل ' . $count . ' طالباً من المقرر السابق' : 'لا يوجد طلاب جدد للنسخ';
            }
            $own->close();
            $from->close();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $flash = 'رمز الحماية غير صالح';
    } else {
        $courseId = (int) ($_POST['course_id'] ?? 0);
        $studentEmail = sanitize($_POST['student_email'] ?? '');
        if ($courseId <= 0 || !validateEmail($studentEmail)) {
            $flash = 'بيانات غير صالحة';
        } else {
            $own = $conn->prepare('SELECT id FROM courses WHERE id = ? AND doctor_id = ? LIMIT 1');
            $own->bind_param('ii', $courseId, $doctorId);
            $own->execute();
            $own->store_result();
            if ($own->num_rows === 0) {
                $flash = 'مقرر غير موجود';
                $own->close();
            } else {
                $own->close();
                $su = $conn->prepare('SELECT id, status FROM users WHERE email = ? AND role = \'student\' LIMIT 1');
                $su->bind_param('s', $studentEmail);
                $su->execute();
                $sid = $su->get_result()->fetch_assoc();
                $su->close();
                if (!$sid) {
                    $flash = 'لم يُعثر على طالب بهذا البريد';
                } elseif (($sid['status'] ?? 'active') === 'suspended') {
                    $flash = 'حساب الطالب معلّق';
                } else {
                    $studentUserId = (int) $sid['id'];
                    $dup = $conn->prepare('SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
                    $dup->bind_param('ii', $courseId, $studentUserId);
                    $dup->execute();
                    $dup->store_result();
                    if ($dup->num_rows > 0) {
                        $flash = 'الطالب مسجّل مسبقاً';
                    } else {
                        $en = $conn->prepare('INSERT INTO course_enrollments (course_id, student_id) VALUES (?, ?)');
                        $en->bind_param('ii', $courseId, $studentUserId);
                        if ($en->execute()) {
                            ensureCourseWeeks($conn, $courseId);
                            $flash = 'تم تسجيل الطالب';
                        } else {
                            $flash = 'تعذر التسجيل';
                        }
                        $en->close();
                    }
                    $dup->close();
                }
            }
        }
    }
}

$courses = [];
$stmt = $conn->prepare(
    'SELECT c.id, c.name, c.code, c.semester, c.term_id,
     (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.id) AS students
     FROM courses c WHERE c.doctor_id = ? ORDER BY c.semester DESC, c.name ASC'
);
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $courses[] = $row;
    ensureCourseWeeks($conn, (int) $row['id']);
}
$stmt->close();

$enrolledRows = [];
$eq = $conn->prepare(
    'SELECT c.id AS course_id, c.name AS course_name, c.code AS course_code,
            u.name AS student_name, u.email, u.student_id AS sid,
            COALESCE(d.name, u.department) AS department
     FROM course_enrollments ce
     INNER JOIN courses c ON c.id = ce.course_id AND c.doctor_id = ?
     INNER JOIN users u ON u.id = ce.student_id
     LEFT JOIN departments d ON d.id = u.department_id
     ORDER BY c.name ASC, u.name ASC'
);
$eq->bind_param('i', $doctorId);
$eq->execute();
$er = $eq->get_result();
while ($row = $er->fetch_assoc()) {
    $enrolledRows[] = $row;
}
$eq->close();

$pageTitle = 'المقررات';
$activeNav = 'courses';
$role = 'doctor';
$extraCss = ['assignments.css', 'attendance.css'];
$av = scanedu_asset_v();
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">إدارة المقررات</h1>
<?php if ($departmentId > 0 && $deptStudentCount > 0): ?>
    <p class="text-muted">طلاب قسمك المسجّلون في النظام: <strong><?php echo $deptStudentCount; ?></strong> — يمكنك تسجيلهم دفعة واحدة في أي مقرر جديد.</p>
<?php endif; ?>
<?php if ($flash !== ''): ?>
    <div class="alert <?php echo str_contains($flash, 'تم') ? 'alert-success' : 'alert-danger'; ?>"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<div class="assign-form-card">
    <h2 class="h2-title">إضافة مقرر</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="create_course" value="1">
        <div class="form-group">
            <label>اسم المقرر</label>
            <input type="text" name="name" class="form-control" required>
        </div>
        <div class="form-group">
            <label>رمز المقرر</label>
            <input type="text" name="code" class="form-control" required placeholder="CS101">
        </div>
        <div class="form-group">
            <label>الترم الدراسي</label>
            <select name="term_id" class="form-control" required>
                <?php foreach ($terms as $t): ?>
                    <option value="<?php echo (int) $t['id']; ?>" <?php echo ($currentTerm && (int) $currentTerm['id'] === (int) $t['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?>
                        <?php echo (int) $t['is_current'] === 1 ? '(حالي)' : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($departmentId > 0): ?>
            <div class="form-group">
                <label><input type="checkbox" name="auto_enroll_dept" value="1" checked> تسجيل جميع طلاب القسم تلقائياً (<?php echo $deptStudentCount; ?> طالب)</label>
            </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> حفظ</button>
    </form>
</div>

<div class="assign-form-card">
    <h2 class="h2-title">تسجيل طالب في مقرر</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="enroll_student" value="1">
        <div class="form-group">
            <label>المقرر</label>
            <select name="course_id" class="form-control" required>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>بريد الطالب المسجّل في النظام</label>
            <input type="email" name="student_email" class="form-control" required placeholder="student@university.edu">
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-user-check" aria-hidden="true"></i> تسجيل</button>
    </form>
</div>

<?php if ($departmentId > 0 && count($courses) > 0): ?>
<div class="assign-form-card">
    <h2 class="h2-title">تسجيل جميع طلاب القسم في مقرر</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="enroll_department" value="1">
        <div class="form-group">
            <label>المقرر</label>
            <select name="course_id" class="form-control" required>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['name'] . ' — ' . $c['semester'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-users" aria-hidden="true"></i> تسجيل كل طلاب القسم</button>
    </form>
</div>

<div class="assign-form-card">
    <h2 class="h2-title">نسخ طلاب من مقرر سابق (نفس الترم أو ترم سابق)</h2>
    <p class="text-muted">مفيد عند بدء ترم جديد: انسخ قائمة الطلاب من مقرر بنفس الرمز في ترم سابق.</p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="copy_enrollments" value="1">
        <div class="form-group">
            <label>المقرر الهدف (الترم الجديد)</label>
            <select name="course_id" class="form-control" required>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['name'] . ' — ' . $c['semester'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>المقرر المصدر (نسخ منه)</label>
            <select name="from_course_id" class="form-control" required>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['name'] . ' — ' . $c['semester'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-copy" aria-hidden="true"></i> نسخ التسجيلات</button>
    </form>
</div>
<?php endif; ?>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>الاسم</th>
                <th>الرمز</th>
                <th>الفصل</th>
                <th>عدد الطلاب</th>
                <th>QR للتسجيل</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($courses as $c): ?>
                <tr>
                    <td><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($c['code'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($c['semester'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo (int) $c['students']; ?></td>
                    <td>
                        <button type="button" class="btn btn-outline btn-sm btn-enroll-qr" data-course-id="<?php echo (int) $c['id']; ?>" data-course-name="<?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fa-solid fa-qrcode" aria-hidden="true"></i> عرض
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($courses) === 0): ?>
                <tr><td colspan="5" class="text-muted">لا توجد مقررات بعد.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="assign-form-card">
    <h2 class="h2-title">جدول الطلاب المسجّلين في مقرراتك</h2>
    <p class="text-muted">يظهر هنا كل طالب بمجرد تسجيله (يدوياً أو عبر QR). في صفحة التحضير يظهر بجانب اسمه «غائب» حتى يمسح رمز الحضور فيصبح «حاضر».</p>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>المقرر</th>
                    <th>الرمز</th>
                    <th>اسم الطالب</th>
                    <th>البريد</th>
                    <th>الرقم الجامعي</th>
                    <th>القسم</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enrolledRows as $erow): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($erow['course_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($erow['course_code'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($erow['student_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($erow['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($erow['sid'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($erow['department'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($enrolledRows) === 0): ?>
                    <tr><td colspan="6" class="text-muted">لا يوجد طلاب مسجّلون بعد.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="enroll-qr-modal" aria-hidden="true">
    <div class="modal-box">
        <button type="button" class="modal-close" id="enroll-qr-close">إغلاق</button>
        <h2 class="h2-title" style="text-align:center;">رمز تسجيل الطلاب في المقرر</h2>
        <p class="text-muted" style="text-align:center;margin-bottom:8px;" id="enroll-qr-course-label"></p>
        <div id="enroll-qr-inner"></div>
        <div class="qr-modal-meta" id="enroll-qr-meta"></div>
        <div class="countdown-display" id="enroll-qr-countdown"></div>
        <p style="text-align:center;margin-top:12px;">
            <button type="button" class="btn btn-outline" id="btn-dl-enroll-qr"><i class="fa-solid fa-download" aria-hidden="true"></i> تحميل الصورة</button>
        </p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
<script src="../assets/js/qr-generator.js<?php echo $av; ?>"></script>
<script src="../assets/js/attendance.js<?php echo $av; ?>"></script>
<script>
(function() {
    var modal = document.getElementById('enroll-qr-modal');
    document.getElementById('enroll-qr-close').addEventListener('click', function() {
        modal.classList.remove('is-open');
    });
    document.querySelectorAll('.btn-enroll-qr').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var cid = this.getAttribute('data-course-id');
            var cname = this.getAttribute('data-course-name') || '';
            document.getElementById('enroll-qr-course-label').textContent = cname;
            var fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            fd.append('course_id', cid);
            fetch('../api/courses/generate_enroll_qr.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) {
                        alert(data.message || 'فشل التوليد');
                        return;
                    }
                    var token = data.data.qr_token;
                    var exp = data.data.expires_at;
                    var payload = JSON.stringify({ type: 'course_enroll', token: token });
                    document.getElementById('enroll-qr-inner').innerHTML = '';
                    generateQR('enroll-qr-inner', payload, 300);
                    document.getElementById('enroll-qr-meta').textContent = 'صلاحية الرمز حتى: ' + exp;
                    modal.classList.add('is-open');
                    startCountdown(exp, 'enroll-qr-countdown', function() {
                        alert('انتهت صلاحية عرض العداد — أعد توليد الرمز');
                    });
                    document.getElementById('btn-dl-enroll-qr').onclick = function() {
                        downloadQR('enroll-qr-inner', 'scanedu-enroll-course.png');
                    };
                })
                .catch(function() { alert('خطأ في الاتصال'); });
        });
    });
})();
</script>

        </main>
    </div>
    </div>
</div>
</body>
</html>
