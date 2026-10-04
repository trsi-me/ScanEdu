/* سكان QR باستخدام html5-qrcode من CDN — يُحمّل السكربت في الصفحة قبل هذا الملف */

/**
 * يتحقق من إمكانية تشغيل الكاميرا (localhost أو https)
 */
function isCameraContextOk() {
    var host = window.location.hostname;
    if (host === 'localhost' || host === '127.0.0.1') {
        return true;
    }
    return window.location.protocol === 'https:';
}

/**
 * يبدأ سكان الكاميرا الخلفية ويستدعي onSuccess عند نجاح القراءة
 */
function initScanner(onSuccess, elementId) {
    elementId = elementId || 'qr-reader';
    if (!isCameraContextOk()) {
        alert('يجب فتح الموقع عبر https أو localhost لاستخدام الكاميرا.');
        return;
    }
    if (typeof Html5Qrcode === 'undefined') {
        alert('مكتبة السكان غير محمّلة. تأكد من اتصال الإنترنت.');
        return;
    }
    var scanner = new Html5Qrcode(elementId);
    var config = { fps: 10, qrbox: { width: 280, height: 280 } };

    Html5Qrcode.getCameras().then(function(cameras) {
        if (!cameras || cameras.length === 0) {
            alert('لم يتم العثور على كاميرا على هذا الجهاز.');
            return;
        }
        var backCam = cameras.find(function(c) {
            return /back|rear|environment/i.test(c.label);
        });
        var camId = backCam ? backCam.id : cameras[0].id;
        scanner.start(
            camId,
            config,
            function(decodedText) {
                scanner.stop().then(function() {
                    scanner.clear();
                    if (typeof onSuccess === 'function') {
                        onSuccess(decodedText);
                    }
                }).catch(function() {
                    if (typeof onSuccess === 'function') {
                        onSuccess(decodedText);
                    }
                });
            },
            function() { /* تجاهل أخطاء الإطار */ }
        ).catch(function(err) {
            alert('تعذر تشغيل الكاميرا. تأكد من منح الصلاحية للمتصفح.\n' + (err && err.message ? err.message : ''));
        });
    }).catch(function() {
        alert('تعذر الوصول إلى الكاميرا. تحقق من الصلاحيات.');
    });
}
