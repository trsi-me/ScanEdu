<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'scanedu_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'فشل الاتصال بقاعدة البيانات']));
}

/** رقم إصدار للمتصفح (?v=) لملفات CSS/JS المحلية — زِدْه عند تغيير الأصول */
if (!defined('SCANEDU_ASSET_VERSION')) {
    define('SCANEDU_ASSET_VERSION', 3);
}

/**
 * سلسلة استعلام للتخزين المؤقت (cache busting) للأصول المحلية
 */
function scanedu_asset_v(): string
{
    return '?v=' . (string) SCANEDU_ASSET_VERSION;
}
