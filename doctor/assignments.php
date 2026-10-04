<?php
// إدارة الواجبات
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('doctor');
$user = getCurrentUser();
$doctorId = (int) $_SESSION['user_id'];

$courses = [];
$stmt = $conn->prepare('SELECT id, name, code FROM courses WHERE doctor_id = ? ORDER BY name ASC');
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

$pageTitle = 'الواجبات';
$activeNav = 'assignments';
$role = 'doctor';
$extraCss = ['assignments.css', 'attendance.css'];
$av = scanedu_asset_v();
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">إدارة الواجبات</h1>

<div class="assign-form-card">
    <h2 class="h2-title">إضافة واجب</h2>
    <form id="form-create-assign">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="form-group">
            <label>العنوان</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="form-group">
            <label>الوصف</label>
            <textarea name="description" class="form-control" rows="4" required></textarea>
        </div>
        <div class="form-group">
            <label>المقرر</label>
            <select name="course_id" class="form-control" required>
                <?php foreach ($courses as $c): ?>
                    <option value="<?php echo (int) $c['id']; ?>"><?php echo htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8'); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>تاريخ ووقت التسليم</label>
            <input type="datetime-local" name="due_date" class="form-control" required>
        </div>
        <div class="form-group">
            <label>الدرجة القصوى</label>
            <input type="number" name="max_score" class="form-control" value="100" min="1">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> حفظ وتوليد QR</button>
    </form>
    <div id="assign-msg" class="alert" style="display:none;margin-top:12px;"></div>
</div>

<div class="table-wrap">
    <table class="data-table" id="assign-table">
        <thead>
            <tr>
                <th>العنوان</th>
                <th>المادة</th>
                <th>التسليم</th>
                <th>المسلّمون</th>
                <th>إجراءات</th>
            </tr>
        </thead>
        <tbody id="assign-rows"></tbody>
    </table>
</div>

<div class="modal-overlay" id="sub-modal">
    <div class="modal-box" style="min-width:280px;max-width:560px;">
        <button type="button" class="modal-close" id="sub-close">إغلاق</button>
        <h2 class="h2-title">التسليمات</h2>
        <div class="submissions-list table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>الطالب</th>
                        <th>الرقم</th>
                        <th>الوقت</th>
                    </tr>
                </thead>
                <tbody id="sub-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="qr-modal-asg">
    <div class="modal-box">
        <button type="button" class="modal-close" id="qr-asg-close">إغلاق</button>
        <h2 class="h2-title" style="text-align:center;">رمز الواجب</h2>
        <div id="qr-asg-inner"></div>
        <p style="text-align:center;"><button type="button" class="btn btn-outline" id="btn-dl-asg"><i class="fa-solid fa-download" aria-hidden="true"></i> تحميل الصورة</button></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
<script src="../assets/js/qr-generator.js<?php echo $av; ?>"></script>
<script src="../assets/js/assignments.js<?php echo $av; ?>"></script>
<script>
function loadAssignments() {
    fetch('../api/assignments/get_assignments.php', { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tb = document.getElementById('assign-rows');
            tb.innerHTML = '';
            if (!data.success || !data.data.rows.length) {
                tb.innerHTML = '<tr><td colspan="5" class="text-muted">لا توجد واجبات بعد.</td></tr>';
                return;
            }
            data.data.rows.forEach(function(row) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' + escapeHtml(row.title) + '</td>' +
                    '<td>' + escapeHtml(row.course_name) + '</td>' +
                    '<td>' + escapeHtml(row.due_date) + '</td>' +
                    '<td>' + row.submitted_count + '</td>' +
                    '<td><button type="button" class="btn btn-outline btn-sm-sub" data-id="' + row.id + '">عرض التسليمات</button> ' +
                    '<button type="button" class="btn btn-secondary btn-sm-qr" data-token="' + encodeURIComponent(row.qr_token) + '" data-title="' + encodeURIComponent(row.title) + '">QR</button></td>';
                tb.appendChild(tr);
            });
            bindSubButtons();
            bindQrButtons();
        });
}

function escapeHtml(t) {
    var d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

function bindSubButtons() {
    document.querySelectorAll('.btn-sm-sub').forEach(function(btn) {
        btn.onclick = function() {
            var id = this.getAttribute('data-id');
            fetch('../api/assignments/get_assignments.php?submissions_for=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var tb = document.getElementById('sub-tbody');
                    tb.innerHTML = '';
                    if (!data.success || !data.data.submissions.length) {
                        tb.innerHTML = '<tr><td colspan="3" class="text-muted">لا توجد تسليمات.</td></tr>';
                    } else {
                        data.data.submissions.forEach(function(s) {
                            var tr = document.createElement('tr');
                            tr.innerHTML = '<td>' + escapeHtml(s.name) + '</td><td>' + escapeHtml(s.student_id || '—') + '</td><td>' + escapeHtml(s.submitted_at) + '</td>';
                            tb.appendChild(tr);
                        });
                    }
                    document.getElementById('sub-modal').classList.add('is-open');
                });
        };
    });
}

function bindQrButtons() {
    document.querySelectorAll('.btn-sm-qr').forEach(function(btn) {
        btn.onclick = function() {
            var tok = decodeURIComponent(this.getAttribute('data-token'));
            var tit = decodeURIComponent(this.getAttribute('data-title'));
            var payload = JSON.stringify({ type: 'assignment', token: tok });
            document.getElementById('qr-asg-inner').innerHTML = '';
            generateQR('qr-asg-inner', payload, 300);
            document.getElementById('qr-modal-asg').classList.add('is-open');
            document.getElementById('btn-dl-asg').onclick = function() {
                downloadQR('qr-asg-inner', 'scanedu-assign-' + tit.slice(0, 8) + '.png');
            };
        };
    });
}

document.getElementById('sub-close').onclick = function() {
    document.getElementById('sub-modal').classList.remove('is-open');
};
document.getElementById('qr-asg-close').onclick = function() {
    document.getElementById('qr-modal-asg').classList.remove('is-open');
};

document.getElementById('form-create-assign').addEventListener('submit', function(e) {
    e.preventDefault();
    var fd = new FormData(this);
    fetch('../api/assignments/create_assignment.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var m = document.getElementById('assign-msg');
            if (data.success) {
                m.className = 'alert alert-success';
                m.textContent = 'تم إنشاء الواجب. يمكنك عرض رمز QR من الجدول.';
                m.style.display = 'block';
                loadAssignments();
                var tok = data.data.qr_token;
                var payload = JSON.stringify({ type: 'assignment', token: tok });
                document.getElementById('qr-asg-inner').innerHTML = '';
                generateQR('qr-asg-inner', payload, 300);
                document.getElementById('qr-modal-asg').classList.add('is-open');
                document.getElementById('btn-dl-asg').onclick = function() {
                    downloadQR('qr-asg-inner', 'scanedu-assign.png');
                };
            } else {
                m.className = 'alert alert-danger';
                m.textContent = data.message || 'فشل';
                m.style.display = 'block';
            }
        });
});

loadAssignments();
</script>

        </main>
    </div>
    </div>
</div>
</body>
</html>
