/* منطق الواجبات في الواجهة — نماذج وقوائم */

/**
 * يعرض رسالة قصيرة للمستخدم
 */
function showAssignMessage(elId, text, isError) {
    var el = document.getElementById(elId);
    if (!el) {
        return;
    }
    el.className = 'alert ' + (isError ? 'alert-danger' : 'alert-success');
    el.textContent = text;
    el.style.display = 'block';
}
