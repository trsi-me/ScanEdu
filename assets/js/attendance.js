/* منطق التحضير — عداد وتحديث لحظي */

/**
 * يشغّل عداداً تنازلياً حتى وقت انتهاء
 */
function startCountdown(targetTime, elementId, onExpire) {
    var el = document.getElementById(elementId);
    if (!el) {
        return;
    }
    var target = new Date(targetTime.replace(' ', 'T')).getTime();

    var fired = false;
    function tick() {
        var now = Date.now();
        var diff = Math.max(0, target - now);
        if (diff <= 0) {
            el.textContent = '00:00:00';
            el.classList.add('is-urgent');
            if (!fired) {
                fired = true;
                if (typeof onExpire === 'function') {
                    onExpire();
                }
            }
            return;
        }
        var totalSec = Math.floor(diff / 1000);
        var h = Math.floor(totalSec / 3600);
        var m = Math.floor((totalSec % 3600) / 60);
        var s = totalSec % 60;
        var pad = function(n) { return n < 10 ? '0' + n : '' + n; };
        el.textContent = pad(h) + ':' + pad(m) + ':' + pad(s);
        if (diff <= 5 * 60 * 1000) {
            el.classList.add('is-urgent');
        } else {
            el.classList.remove('is-urgent');
        }
    }
    tick();
    setInterval(tick, 1000);
}

/**
 * يحدّث جدول الحضور كل 10 ثوانٍ
 */
function startLiveAttendance(lectureId, tableBodyId) {
    var tbody = document.getElementById(tableBodyId);
    if (!tbody) {
        return;
    }
    var prevPresent = {};
    var initialized = false;

    function updateTable(data) {
        if (!data.success || !data.data || !data.data.rows) {
            return;
        }
        var rows = data.data.rows;
        var nextPresent = {};
        tbody.innerHTML = '';
        rows.forEach(function(row, idx) {
            var tr = document.createElement('tr');
            if (row.present) {
                nextPresent[row.student_id] = true;
            }
            if (initialized && row.present && !prevPresent[row.student_id]) {
                tr.classList.add('row-new');
            }
            var statusCell = row.present
                ? '<span class="badge badge-success">حاضر</span>'
                : '<span class="badge badge-danger">غائب</span>';
            var scanCell = row.present && row.scanned_at ? escapeHtml(row.scanned_at) : '—';
            tr.innerHTML =
                '<td>' + (idx + 1) + '</td>' +
                '<td>' + escapeHtml(row.name) + '</td>' +
                '<td>' + escapeHtml(row.student_id_num || '—') + '</td>' +
                '<td>' + escapeHtml(row.email || '—') + '</td>' +
                '<td>' + escapeHtml(row.department || '—') + '</td>' +
                '<td>' + scanCell + '</td>' +
                '<td>' + statusCell + '</td>';
            tbody.appendChild(tr);
        });
        prevPresent = nextPresent;
        initialized = true;
    }

    function escapeHtml(t) {
        if (!t) {
            return '';
        }
        var d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }

    function fetchOnce() {
        fetch('../api/attendance/get_attendance.php?lecture_id=' + encodeURIComponent(lectureId), { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(updateTable)
            .catch(function() {});
    }
    fetchOnce();
    setInterval(fetchOnce, 10000);
}
