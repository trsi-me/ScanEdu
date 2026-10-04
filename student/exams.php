<?php
// اختبارات الطالب
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('student');
$user = getCurrentUser();
$studentId = (int) $_SESSION['user_id'];
$examId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$take = isset($_GET['take']) && $_GET['take'] === '1';

$pageTitle = 'الاختبارات';
$activeNav = 'exams';
$role = 'student';
$extraCss = ['exams.css'];
$av = scanedu_asset_v();
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">الاختبارات</h1>

<?php if ($examId > 0 && $take): ?>
    <div id="exam-app">
        <div class="exam-timer-bar">
            <span><?php echo htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="timer" id="exam-timer">--:--</span>
        </div>
        <div class="exam-progress"><div class="exam-progress-inner" style="width:0%;"></div></div>
        <div id="exam-meta" class="text-muted"></div>
        <div id="question-mount"></div>
        <div class="exam-nav-btns">
            <button type="button" class="btn btn-outline" id="btn-prev"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i> السابق</button>
            <button type="button" class="btn btn-primary" id="btn-next">التالي <i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-secondary" id="btn-submit-exam"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> تسليم</button>
        </div>
        <div id="exam-msg" class="alert" style="display:none;margin-top:12px;"></div>
    </div>
    <script src="../assets/js/exams.js<?php echo $av; ?>"></script>
    <script>
    (function() {
        var examId = <?php echo (int) $examId; ?>;
        var questions = [];
        var answers = {};
        var idx = 0;
        var endTimeIso = '';
        var submitted = false;

        function render() {
            var m = document.getElementById('question-mount');
            m.innerHTML = '';
            if (!questions.length) return;
            var q = questions[idx];
            var card = document.createElement('div');
            card.className = 'question-card';
            var h = document.createElement('h2');
            h.className = 'h2-title';
            h.textContent = 'سؤال ' + (idx + 1) + ' / ' + questions.length;
            card.appendChild(h);
            var p = document.createElement('p');
            p.style.fontSize = '16px';
            p.textContent = q.question_text;
            card.appendChild(p);
            var key = String(q.id);
            if (q.question_type === 'essay') {
                var ta = document.createElement('textarea');
                ta.className = 'form-control';
                ta.rows = 6;
                ta.value = answers[key] || '';
                ta.oninput = function() { answers[key] = ta.value; };
                card.appendChild(ta);
            } else if (q.question_type === 'mcq' || q.question_type === 'true_false') {
                var opts = q.options || [];
                opts.forEach(function(opt) {
                    var id = 'opt_' + q.id + '_' + opt;
                    var lab = document.createElement('label');
                    lab.style.display = 'block';
                    lab.style.margin = '8px 0';
                    var rb = document.createElement('input');
                    rb.type = 'radio';
                    rb.name = 'q_' + q.id;
                    rb.value = opt;
                    rb.checked = answers[key] === opt;
                    rb.onchange = function() { answers[key] = opt; };
                    lab.appendChild(rb);
                    lab.appendChild(document.createTextNode(' ' + opt));
                    card.appendChild(lab);
                });
            }
            m.appendChild(card);
            setExamProgress(idx, questions.length);
        }

        fetch('../api/exams/get_exam.php?exam_id=' + encodeURIComponent(examId) + '&take=1', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    document.getElementById('exam-msg').style.display = 'block';
                    document.getElementById('exam-msg').className = 'alert alert-danger';
                    document.getElementById('exam-msg').textContent = data.message || 'تعذر فتح الاختبار';
                    document.getElementById('exam-app').style.display = 'none';
                    return;
                }
                questions = data.data.questions || [];
                var ex = data.data.exam;
                endTimeIso = ex.end_time;
                document.getElementById('exam-meta').textContent = ex.title + ' — ' + ex.course_name;
                render();
                startExamCountdown(endTimeIso.replace(' ', 'T'), 'exam-timer', function() {
                    doSubmit(true);
                });
                document.getElementById('btn-prev').onclick = function() {
                    idx = Math.max(0, idx - 1);
                    render();
                };
                document.getElementById('btn-next').onclick = function() {
                    idx = Math.min(questions.length - 1, idx + 1);
                    render();
                };
                document.getElementById('btn-submit-exam').onclick = function() { doSubmit(false); };
            });

        function doSubmit(auto) {
            if (submitted) {
                return;
            }
            submitted = true;
            var fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            fd.append('exam_id', String(examId));
            fd.append('answers', JSON.stringify(answers));
            fetch('../api/exams/submit_exam.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    var m = document.getElementById('exam-msg');
                    m.style.display = 'block';
                    if (data.success) {
                        m.className = 'alert alert-success';
                        m.textContent = (auto ? 'تم التسليم تلقائياً. ' : '') + 'درجتك المحسوبة للأسئلة الموضوعية: ' + (data.data.total_score != null ? data.data.total_score : '');
                        document.getElementById('exam-app').querySelectorAll('button').forEach(function(b) { b.disabled = true; });
                    } else {
                        m.className = 'alert alert-danger';
                        m.textContent = data.message || 'فشل التسليم';
                    }
                });
        }
    })();
    </script>
<?php else: ?>
    <?php
    $list = [];
    $stmt = $conn->prepare(
        'SELECT e.id, e.title, e.start_time, e.end_time, c.name AS course_name FROM exams e
         INNER JOIN courses c ON c.id = e.course_id
         INNER JOIN course_enrollments ce ON ce.course_id = c.id AND ce.student_id = ?
         ORDER BY e.start_time DESC'
    );
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $list[] = $row;
    }
    $stmt->close();
    $now = new DateTimeImmutable('now');
    ?>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>العنوان</th><th>المقرر</th><th>البدء</th><th>الانتهاء</th><th>حالة</th></tr>
            </thead>
            <tbody>
                <?php foreach ($list as $row):
                    $st = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['start_time']);
                    $en = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $row['end_time']);
                    $status = '—';
                    if ($st && $en) {
                        if ($now < $st) {
                            $status = 'لم يبدأ';
                        } elseif ($now <= $en) {
                            $status = 'جارٍ';
                        } else {
                            $status = 'انتهى';
                        }
                    }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['start_time'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($row['end_time'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>
                            <?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($status === 'جارٍ'): ?>
                                <a class="btn btn-primary" style="padding:6px 12px;font-size:14px;" href="exams.php?id=<?php echo (int) $row['id']; ?>&take=1"><i class="fa-solid fa-door-open" aria-hidden="true"></i> دخول</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (count($list) === 0): ?>
                    <tr><td colspan="5" class="text-muted">لا توجد اختبارات.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

        </main>
    </div>
    </div>
</div>
</body>
</html>
