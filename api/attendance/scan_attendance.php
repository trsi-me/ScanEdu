<?php
// تسجيل حضور عبر رمز QR
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/functions.php';
    startSession();

    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
        sendJSON(['success' => false, 'message' => 'يجب تسجيل الدخول كطالب'], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'message' => 'طريقة غير مسموحة'], 405);
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken(is_string($csrf) ? $csrf : null)) {
        sendJSON(['success' => false, 'message' => 'رمز الحماية غير صالح'], 403);
    }

    $tokenRaw = $_POST['qr_token'] ?? '';
    $token = is_string($tokenRaw) ? trim($tokenRaw) : '';
    if ($token === '') {
        sendJSON(['success' => false, 'message' => 'الرمز فارغ'], 400);
    }

    $data = validateQRToken($token);
    if (!$data || empty($data['lecture_id']) || empty($data['expires_at'])) {
        sendJSON(['success' => false, 'message' => 'رمز غير صالح'], 400);
    }

    $lectureId = (int) $data['lecture_id'];
    $expiresAt = $data['expires_at'];
    $now = new DateTimeImmutable('now');
    $exp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expiresAt) ?: null;
    if (!$exp || $now > $exp) {
        sendJSON(['success' => false, 'message' => 'انتهت صلاحية رمز التحضير'], 410);
    }

    $stmt = $conn->prepare('SELECT id, qr_token, qr_expires_at FROM lectures WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $lectureId);
    $stmt->execute();
    $res = $stmt->get_result();
    $lec = $res->fetch_assoc();
    $stmt->close();

    if (!$lec || !hash_equals($lec['qr_token'], $token)) {
        sendJSON(['success' => false, 'message' => 'الرمز لا يطابق المحاضرة'], 400);
    }

    $studentUserId = (int) $_SESSION['user_id'];
    $chk = $conn->prepare(
        'SELECT ce.id FROM course_enrollments ce
         INNER JOIN lectures l ON l.course_id = ce.course_id
         WHERE l.id = ? AND ce.student_id = ? LIMIT 1'
    );
    $chk->bind_param('ii', $lectureId, $studentUserId);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        $chk->close();
        sendJSON(['success' => false, 'message' => 'أنت غير مسجّل في هذا المقرر'], 403);
    }
    $chk->close();

    $dup = $conn->prepare('SELECT id FROM attendance WHERE lecture_id = ? AND student_id = ? LIMIT 1');
    $dup->bind_param('ii', $lectureId, $studentUserId);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        $dup->close();
        sendJSON(['success' => false, 'message' => 'تم تسجيل حضورك مسبقاً لهذه المحاضرة'], 409);
    }
    $dup->close();

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ins = $conn->prepare('INSERT INTO attendance (lecture_id, student_id, ip_address) VALUES (?, ?, ?)');
    $ins->bind_param('iis', $lectureId, $studentUserId, $ip);
    if (!$ins->execute()) {
        $ins->close();
        sendJSON(['success' => false, 'message' => 'تعذر حفظ الحضور'], 500);
    }
    $ins->close();

    $u = $conn->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
    $u->bind_param('i', $studentUserId);
    $u->execute();
    $ur = $u->get_result()->fetch_assoc();
    $u->close();

    sendJSON([
        'success' => true,
        'message' => 'تم تسجيل حضورك بنجاح',
        'data' => ['student_name' => $ur['name'] ?? ''],
    ]);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
