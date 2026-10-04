/* منطق الاختبارات — مؤقت وشريط تقدم */

/**
 * مؤقت للاختبار مع تحذير آخر 5 دقائق
 */
function startExamCountdown(endTimeIso, displayId, onEnd) {
    var el = document.getElementById(displayId);
    if (!el) {
        return;
    }
    var end = new Date(endTimeIso.replace(' ', 'T')).getTime();
    var ended = false;

    function tick() {
        var now = Date.now();
        var diff = Math.max(0, end - now);
        if (diff <= 0) {
            el.textContent = '00:00';
            el.classList.add('is-danger');
            if (!ended) {
                ended = true;
                if (typeof onEnd === 'function') {
                    onEnd();
                }
            }
            return;
        }
        var m = Math.floor(diff / 60000);
        var s = Math.floor((diff % 60000) / 1000);
        el.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        if (diff <= 5 * 60 * 1000) {
            el.classList.add('is-danger');
        } else {
            el.classList.remove('is-danger');
        }
    }
    tick();
    setInterval(tick, 1000);
}

/**
 * يحدّث عرض شريط التقدم
 */
function setExamProgress(current, total) {
    var inner = document.querySelector('.exam-progress-inner');
    if (!inner || total <= 0) {
        return;
    }
    var pct = ((current + 1) / total) * 100;
    inner.style.width = pct + '%';
}
