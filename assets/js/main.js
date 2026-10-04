/* دوال مشتركة للواجهة */

/**
 * يجلب قيمة توكن CSRF من وسم meta
 */
function getCsrfMeta() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
}

/**
 * يضيف توكن CSRF إلى كائن FormData إن وُجد
 */
function appendCsrf(fd) {
    var t = getCsrfMeta();
    if (t) {
        fd.set('csrf_token', t);
    }
    return fd;
}
