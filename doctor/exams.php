<?php
// إدارة الاختبارات
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('doctor');
$user = getCurrentUser();
$doctorId = (int) $_SESSION['user_id'];

$courses = [];
$stmt = $conn->prepare('SELECT id, name FROM courses WHERE doctor_id = ? ORDER BY name ASC');
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $courses[] = $row;
}
$stmt->close();

$exams = [];
$stmt = $conn->prepare(
    'SELECT e.id, e.title, e.start_time, e.end_time, e.qr_token, c.name AS course_name FROM exams e
     INNER JOIN courses c ON c.id = e.course_id WHERE c.doctor_id = ? ORDER BY e.start_time DESC'
);
$stmt->bind_param('i', $doctorId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $exams[] = $row;
}
$stmt->close();

$pageTitle = 'الاختبارات';
$activeNav = 'exams';
$role = 'doctor';
$extraCss = ['exams.css', 'attendance.css'];
$av = scanedu_asset_v();
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">إدارة الاختبارات</h1>

<div class="assign-form-card exam-builder">
    <h2 class="h2-title">إنشاء اختبار</h2>
    <form id="exam-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="form-group">
            <label>عنوان الاختبار</label>
            <input type="text" name="title" class="form-control" required>
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
            <label>وقت البدء</label>
            <input type="datetime-local" name="start_time" class="form-control" required>
        </div>
        <div class="form-group">
            <label>وقت الانتهاء</label>
            <input type="datetime-local" name="end_time" class="form-control" required>
        </div>
        <div class="form-group">
            <label>المدة بالدقائق (للعرض للطالب)</label>
            <input type="number" name="duration_minutes" class="form-control" value="45" min="1">
        </div>
        <div class="form-group">
            <label>الدرجة الكلية</label>
            <input type="number" name="total_score" class="form-control" value="100" min="1">
        </div>

        <h2 class="h2-title">الأسئلة</h2>
        <div id="questions-wrap"></div>
        <button type="button" class="btn btn-outline" id="btn-add-q"><i class="fa-solid fa-plus" aria-hidden="true"></i> إضافة سؤال</button>
        <input type="hidden" name="questions" id="questions-json">

        <p style="margin-top:16px;">
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> حفظ وتوليد QR</button>
        </p>
    </form>
    <div id="exam-msg" class="alert" style="display:none;"></div>
</div>

<div class="table-wrap">
    <table class="data-table">
        <thead>
            <tr>
                <th>العنوان</th>
                <th>المادة</th>
                <th>البدء</th>
                <th>الانتهاء</th>
                <th>الحالة</th>
                <th>نتائج</th>
                <th>QR</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $now = new DateTimeImmutable('now');
            foreach ($exams as $ex):
                $st = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ex['start_time']);
                $en = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $ex['end_time']);
                $status = 'لم يبدأ';
                $badge = 'badge-muted';
                if ($st && $en) {
                    if ($now < $st) {
                        $status = 'لم يبدأ';
                    } elseif ($now <= $en) {
                        $status = 'جارٍ';
                        $badge = 'badge-success';
                    } else {
                        $status = 'انتهى';
                    }
                }
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($ex['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($ex['course_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($ex['start_time'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?php echo htmlspecialchars($ex['end_time'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><span class="badge <?php echo $badge; ?>"><?php echo $status; ?></span></td>
                    <td><button type="button" class="btn btn-outline btn-results" data-eid="<?php echo (int) $ex['id']; ?>"><i class="fa-solid fa-square-poll-vertical" aria-hidden="true"></i> عرض</button></td>
                    <td><button type="button" class="btn btn-secondary btn-qr-ex" data-eid="<?php echo (int) $ex['id']; ?>" data-token="<?php echo htmlspecialchars($ex['qr_token'], ENT_QUOTES, 'UTF-8'); ?>"><i class="fa-solid fa-qrcode" aria-hidden="true"></i> QR</button></td>
                </tr>
            <?php endforeach; ?>
            <?php if (count($exams) === 0): ?>
                <tr><td colspan="7" class="text-muted">لا توجد اختبارات بعد.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal-overlay" id="res-modal">
    <div class="modal-box" style="max-width:640px;">
        <button type="button" class="modal-close" id="res-close">إغلاق</button>
        <h2 class="h2-title">النتائج</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>الطالب</th><th>الرقم</th><th>الدرجة</th><th>التسليم</th></tr>
                </thead>
                <tbody id="res-tbody"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="qr-ex-modal">
    <div class="modal-box">
        <button type="button" class="modal-close" id="qr-ex-close">إغلاق</button>
        <h2 class="h2-title" style="text-align:center;">رمز الاختبار</h2>
        <div id="qr-ex-inner"></div>
        <p style="text-align:center;"><button type="button" class="btn btn-outline" id="btn-dl-ex"><i class="fa-solid fa-download" aria-hidden="true"></i> تحميل</button></p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
<script src="../assets/js/qr-generator.js<?php echo $av; ?>"></script>
<script>
(function() {
    var wrap = document.getElementById('questions-wrap');

    function addQuestion() {
        var i = wrap.children.length;
        var div = document.createElement('div');
        div.className = 'question-block';
        div.innerHTML =
            '<div class="form-group"><label>نوع السؤال</label>' +
            '<select class="form-control q-type">' +
            '<option value="mcq">اختيار من متعدد</option>' +
            '<option value="true_false">صح / خطأ</option>' +
            '<option value="essay">مقالي</option></select></div>' +
            '<div class="form-group"><label>نص السؤال</label><textarea class="form-control q-text" rows="2" required></textarea></div>' +
            '<div class="form-group q-opts"><label>الخيارات (سطر لكل خيار — للمقالي اتركه فارغاً)</label>' +
            '<textarea class="form-control q-options" rows="3" placeholder="أ\nب\nج"></textarea></div>' +
            '<div class="form-group"><label>الإجابة الصحيحة (نص الخيار أو «صح»/«خطأ»)</label>' +
            '<input type="text" class="form-control q-correct"></div>' +
            '<div class="form-group"><label>درجة السؤال</label><input type="number" class="form-control q-score" value="10" min="1"></div>' +
            '<button type="button" class="btn btn-outline btn-rm"><i class="fa-solid fa-trash" aria-hidden="true"></i> حذف السؤال</button>';
        div.querySelector('.btn-rm').onclick = function() { div.remove(); };
        wrap.appendChild(div);
    }

    document.getElementById('btn-add-q').onclick = addQuestion;
    addQuestion();

    function collectQuestions() {
        var out = [];
        wrap.querySelectorAll('.question-block').forEach(function(block) {
            var type = block.querySelector('.q-type').value;
            var text = block.querySelector('.q-text').value.trim();
            var optsRaw = block.querySelector('.q-options').value.trim();
            var correct = block.querySelector('.q-correct').value.trim();
            var score = parseInt(block.querySelector('.q-score').value, 10) || 10;
            if (!text) return;
            var opts = null;
            if (type === 'mcq' && optsRaw) {
                opts = optsRaw.split(/\r?\n/).map(function(s) { return s.trim(); }).filter(Boolean);
            }
            if (type === 'true_false') {
                opts = ['صح', 'خطأ'];
            }
            out.push({
                question_text: text,
                question_type: type,
                options: opts,
                correct_answer: correct,
                score: score
            });
        });
        return out;
    }

    document.getElementById('exam-form').onsubmit = function(e) {
        e.preventDefault();
        var qs = collectQuestions();
        if (!qs.length) {
            alert('أضف سؤالاً واحداً على الأقل');
            return;
        }
        document.getElementById('questions-json').value = JSON.stringify(qs);
        var fd = new FormData(this);
        fetch('../api/exams/create_exam.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var m = document.getElementById('exam-msg');
                m.style.display = 'block';
                if (data.success) {
                    m.className = 'alert alert-success';
                    m.textContent = 'تم إنشاء الاختبار';
                    var tok = data.data.qr_token;
                    var payload = JSON.stringify({ type: 'exam', token: tok });
                    document.getElementById('qr-ex-inner').innerHTML = '';
                    generateQR('qr-ex-inner', payload, 300);
                    document.getElementById('qr-ex-modal').classList.add('is-open');
                    document.getElementById('btn-dl-ex').onclick = function() {
                        downloadQR('qr-ex-inner', 'scanedu-exam.png');
                    };
                    setTimeout(function() { location.reload(); }, 800);
                } else {
                    m.className = 'alert alert-danger';
                    m.textContent = data.message || 'فشل';
                }
            });
    };

    document.querySelectorAll('.btn-results').forEach(function(btn) {
        btn.onclick = function() {
            var id = this.getAttribute('data-eid');
            fetch('../api/exams/get_results.php?exam_id=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var tb = document.getElementById('res-tbody');
                    tb.innerHTML = '';
                    if (!data.success || !data.data.rows) {
                        tb.innerHTML = '<tr><td colspan="4" class="text-muted">لا توجد نتائج.</td></tr>';
                    } else {
                        data.data.rows.forEach(function(row) {
                            var tr = document.createElement('tr');
                            tr.innerHTML = '<td>' + row.name + '</td><td>' + (row.student_id || '—') + '</td><td>' + row.total_score + '</td><td>' + row.submitted_at + '</td>';
                            tb.appendChild(tr);
                        });
                    }
                    document.getElementById('res-modal').classList.add('is-open');
                });
        };
    });

    document.getElementById('res-close').onclick = function() {
        document.getElementById('res-modal').classList.remove('is-open');
    };
    document.getElementById('qr-ex-close').onclick = function() {
        document.getElementById('qr-ex-modal').classList.remove('is-open');
    };

    document.querySelectorAll('.btn-qr-ex').forEach(function(btn) {
        btn.onclick = function() {
            var id = this.getAttribute('data-eid');
            var tok = this.getAttribute('data-token');
            if (!tok) {
                alert('لا يوجد رمز');
                return;
            }
            var payload = JSON.stringify({ type: 'exam', token: tok });
            document.getElementById('qr-ex-inner').innerHTML = '';
            generateQR('qr-ex-inner', payload, 300);
            document.getElementById('qr-ex-modal').classList.add('is-open');
            document.getElementById('btn-dl-ex').onclick = function() {
                downloadQR('qr-ex-inner', 'scanedu-exam-' + id + '.png');
            };
        };
    });
})();
</script>

        </main>
    </div>
    </div>
</div>
</body>
</html>
