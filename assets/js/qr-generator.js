/* توليد QR عبر مكتبة qrcode.js من CDN */

/**
 * يرسم رمز QR داخل العنصر المحدد
 */
function generateQR(containerId, data, size) {
    size = size || 300;
    var el = document.getElementById(containerId);
    if (!el) {
        return;
    }
    el.innerHTML = '';
    if (typeof QRCode === 'undefined' || typeof QRCode.toCanvas !== 'function') {
        el.innerHTML = '<p style="padding:12px;text-align:center;color:#C62828;">تعذّر تحميل مكتبة QR. تحقق من الاتصال أو أعد تحميل الصفحة.</p>';
        console.error('QRCode library missing — check script URL (qrcode npm browser bundle).');
        return;
    }
    var canvas = document.createElement('canvas');
    el.appendChild(canvas);
    QRCode.toCanvas(canvas, String(data), {
        width: size,
        margin: 2,
        color: { dark: '#1B2A4A', light: '#FFFFFF' }
    }, function(error) {
        if (error) {
            console.error(error);
            el.innerHTML = '<p style="padding:12px;text-align:center;color:#C62828;">فشل رسم الرمز: ' + String(error.message || error) + '</p>';
        }
    });
}

/**
 * يحمّل صورة QR من canvas داخل الحاوية كملف PNG
 */
function downloadQR(containerId, filename) {
    filename = filename || 'scanedu-qr.png';
    var el = document.getElementById(containerId);
    if (!el) {
        return;
    }
    var canvas = el.querySelector('canvas');
    if (!canvas) {
        return;
    }
    var link = document.createElement('a');
    link.download = filename;
    link.href = canvas.toDataURL('image/png');
    link.click();
}
