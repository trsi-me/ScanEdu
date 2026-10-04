<?php
// تسجيل طالب في مقرر بعد مسح رمز QR من الدكتور
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../includes/functions.php';
    startSession();

    if (!isLoggedIn() || ($_SESSION['role'] ?? '') !== 'student') {
        sendJSON(['success' => false, 'message' => 'يجب تسجيل الدخول كطالب'], 403);
    }

    $stu = $conn->prepare('SELECT status FROM users WHERE id = ? LIMIT 1');
    $stu->bind_param('i', $_SESSION['user_id']);
    $stu->execute();
    $stuRow = $stu->get_result()->fetch_assoc();
    $stu->close();
    if (!$stuRow || ($stuRow['status'] ?? 'active') === 'suspended') {
        sendJSON(['success' => false, 'message' => 'حسابك معلّق'], 403);
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
    if (!$data || ($data['purpose'] ?? '') !== 'course_enroll' || empty($data['course_id']) || empty($data['expires_at'])) {
        sendJSON(['success' => false, 'message' => 'رمز غير صالح'], 400);
    }

    $courseId = (int) $data['course_id'];
    $expiresAt = $data['expires_at'];
    $now = new DateTimeImmutable('now');
    $exp = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $expiresAt) ?: null;
    if (!$exp || $now > $exp) {
        sendJSON(['success' => false, 'message' => 'انتهت صلاحية رمز التسجيل'], 410);
    }

    $chk = $conn->prepare('SELECT id, name, code FROM courses WHERE id = ? LIMIT 1');
    $chk->bind_param('i', $courseId);
    $chk->execute();
    $course = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$course) {
        sendJSON(['success' => false, 'message' => 'المقرر غير موجود'], 404);
    }

    $studentUserId = (int) $_SESSION['user_id'];
    $dup = $conn->prepare('SELECT id FROM course_enrollments WHERE course_id = ? AND student_id = ? LIMIT 1');
    $dup->bind_param('ii', $courseId, $studentUserId);
    $dup->execute();
    $dup->store_result();
    if ($dup->num_rows > 0) {
        $dup->close();
        sendJSON(['success' => false, 'message' => 'أنت مسجّل مسبقاً في هذا المقرر'], 409);
    }
    $dup->close();

    $en = $conn->prepare('INSERT INTO course_enrollments (course_id, student_id) VALUES (?, ?)');
    $en->bind_param('ii', $courseId, $studentUserId);
    if (!$en->execute()) {
        $en->close();
        sendJSON(['success' => false, 'message' => 'تعذر التسجيل'], 500);
    }
    $en->close();

    ensureCourseWeeks($conn, $courseId);

    sendJSON([
        'success' => true,
        'message' => 'تم تسجيلك في المقرر',
        'data' => [
            'course_name' => $course['name'],
            'course_code' => $course['code'],
        ],
    ]);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
