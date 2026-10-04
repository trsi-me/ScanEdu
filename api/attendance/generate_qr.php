<?php
// توليد رمز QR للمحاضرة
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

    $lectureId = (int) ($_POST['lecture_id'] ?? 0);
    if ($lectureId <= 0) {
        sendJSON(['success' => false, 'message' => 'معرّف المحاضرة مطلوب'], 400);
    }

    $doctorId = (int) $_SESSION['user_id'];
    $stmt = $conn->prepare(
        'SELECT l.id, l.course_id FROM lectures l
         INNER JOIN courses c ON c.id = l.course_id
         WHERE l.id = ? AND c.doctor_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $lectureId, $doctorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $lec = $res->fetch_assoc();
    $stmt->close();

    if (!$lec) {
        sendJSON(['success' => false, 'message' => 'المحاضرة غير موجودة'], 404);
    }

    $expires = new DateTimeImmutable('+90 minutes');
    $expiresStr = $expires->format('Y-m-d H:i:s');
    $payload = [
        'lecture_id' => $lectureId,
        'expires_at' => $expiresStr,
    ];
    $token = generateQRToken($payload);

    $upd = $conn->prepare('UPDATE lectures SET qr_token = ?, qr_expires_at = ? WHERE id = ?');
    $upd->bind_param('ssi', $token, $expiresStr, $lectureId);
    if (!$upd->execute()) {
        $upd->close();
        sendJSON(['success' => false, 'message' => 'تعذر حفظ الرمز'], 500);
    }
    $upd->close();

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
