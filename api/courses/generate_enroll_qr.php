<?php
// توليد رمز QR لتسجيل الطلاب في مقرر الدكتور (مسح من حساب طالب)
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/functions.php';
    startSession();

    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'doctor') {
        sendJSON(['success' => false, 'message' => 'غير مصرح'], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'message' => 'طريقة غير مسموحة'], 405);
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken(is_string($csrf) ? $csrf : null)) {
        sendJSON(['success' => false, 'message' => 'رمز الحماية غير صالح'], 403);
    }

    $courseId = (int) ($_POST['course_id'] ?? 0);
    if ($courseId <= 0) {
        sendJSON(['success' => false, 'message' => 'معرّف المقرر مطلوب'], 400);
    }

    $doctorId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT id FROM courses WHERE id = ? AND doctor_id = ? LIMIT 1');
    $stmt->bind_param('ii', $courseId, $doctorId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        sendJSON(['success' => false, 'message' => 'المقرر غير موجود'], 404);
    }
    $stmt->close();

    $expires = new DateTimeImmutable('+25 minutes');
    $expiresStr = $expires->format('Y-m-d H:i:s');
    $payload = [
        'purpose' => 'course_enroll',
        'course_id' => $courseId,
        'expires_at' => $expiresStr,
    ];
    $token = generateQRToken($payload);

    sendJSON([
        'success' => true,
        'message' => 'تم توليد الرمز',
        'data' => [
            'qr_token' => $token,
            'expires_at' => $expiresStr,
        ],
    ]);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
