<?php
// سكان QR العام
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
startSession();
requireRole('student');

$pageTitle = 'سكان QR';
$activeNav = 'scan';
$role = 'student';
$user = getCurrentUser();
$extraCss = ['attendance.css'];
$av = scanedu_asset_v();
include __DIR__ . '/../includes/header.php';
?>

<h1 class="h1-title">سكان رمز QR</h1>
<p class="scan-hero text-muted">تحضير: رمز الدكتور في المحاضرة. تسجيل في مقرر: رمز «QR للتسجيل» من صفحة المقررات.</p>
<div id="scan-msg" class="alert" style="display:none;"></div>
<div id="qr-reader"></div>

<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="../assets/js/qr-scanner.js<?php echo $av; ?>"></script>
<script>
(function() {
    function showMsg(text, isErr) {
        var el = document.getElementById('scan-msg');
        el.className = 'alert ' + (isErr ? 'alert-danger' : 'alert-success');
        el.textContent = text;
        el.style.display = 'block';
    }

    function decodeInner(token) {
        try {
            var json = JSON.parse(atob(token));
            return json;
        } catch (e) {
            return null;
        }
    }

    initScanner(function(text) {
        var outer;
        try {
            outer = JSON.parse(text);
        } catch (e) {
            showMsg('صيغة الرمز غير صالحة', true);
            return;
        }
        if (!outer || !outer.type || !outer.token) {
            showMsg('رمز غير مكتمل', true);
            return;
        }
        if (outer.type === 'attendance') {
            var fd = new FormData();
            fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            fd.append('qr_token', outer.token);
            fetch('../api/attendance/scan_attendance.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        showMsg('✓ تم تسجيل حضورك', false);
                    } else {
                        showMsg(data.message || 'فشل التسجيل', true);
                    }
                })
                .catch(function() { showMsg('خطأ في الاتصال', true); });
            return;
        }
        if (outer.type === 'course_enroll') {
            var fd2 = new FormData();
            fd2.append('csrf_token', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));
            fd2.append('qr_token', outer.token);
            fetch('../api/courses/enroll_scan.php', { method: 'POST', body: fd2, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        var cn = (data.data && data.data.course_name) ? data.data.course_name : '';
                        showMsg('✓ تم تسجيلك في المقرر: ' + cn, false);
                    } else {
                        showMsg(data.message || 'فشل التسجيل', true);
                    }
                })
                .catch(function() { showMsg('خطأ في الاتصال', true); });
            return;
        }
        if (outer.type === 'assignment') {
            var inner = decodeInner(outer.token);
            if (!inner || !inner.assignment_id) {
                showMsg('رمز واجب غير صالح', true);
                return;
            }
            window.location.href = 'assignments.php?id=' + encodeURIComponent(inner.assignment_id);
            return;
        }
        if (outer.type === 'exam') {
            var ex = decodeInner(outer.token);
            if (!ex || !ex.exam_id) {
                showMsg('رمز اختبار غير صالح', true);
                return;
            }
            window.location.href = 'exams.php?id=' + encodeURIComponent(ex.exam_id) + '&take=1';
            return;
        }
        showMsg('نوع الرمز غير معروف', true);
    }, 'qr-reader');
})();
</script>

        </main>
    </div>
    </div>
</div>
</body>
</html>
