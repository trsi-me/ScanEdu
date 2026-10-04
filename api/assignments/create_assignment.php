<?php
// إنشاء واجب وتوليد رمز QR
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

    $title = sanitize($_POST['title'] ?? '');
    $description = strip_tags(trim($_POST['description'] ?? ''));
    $courseId = (int) ($_POST['course_id'] ?? 0);
    $dueRaw = $_POST['due_date'] ?? '';
    $maxScore = (int) ($_POST['max_score'] ?? 100);

    if ($title === '' || $description === '' || $courseId <= 0 || $dueRaw === '') {
        sendJSON(['success' => false, 'message' => 'البيانات ناقصة'], 400);
    }

    $due = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', str_replace(' ', 'T', $dueRaw));
    if (!$due) {
        $due = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dueRaw);
    }
    if (!$due) {
        sendJSON(['success' => false, 'message' => 'تاريخ التسليم غير صالح'], 400);
    }
    $dueStr = $due->format('Y-m-d H:i:s');

    $doctorId = (int) $_SESSION['user_id'];
    $chk = $conn->prepare('SELECT id FROM courses WHERE id = ? AND doctor_id = ? LIMIT 1');
    $chk->bind_param('ii', $courseId, $doctorId);
    $chk->execute();
    $chk->store_result();
    if ($chk->num_rows === 0) {
        $chk->close();
        sendJSON(['success' => false, 'message' => 'المقرر غير موجود'], 404);
    }
    $chk->close();

    $tempToken = bin2hex(random_bytes(24));
    $ins = $conn->prepare('INSERT INTO assignments (course_id, title, description, qr_token, due_date, max_score) VALUES (?, ?, ?, ?, ?, ?)');
    $ins->bind_param('issssi', $courseId, $title, $description, $tempToken, $dueStr, $maxScore);
    if (!$ins->execute()) {
        $ins->close();
        sendJSON(['success' => false, 'message' => 'تعذر الحفظ'], 500);
    }
    $newId = (int) $conn->insert_id;
    $ins->close();

    $payload = ['assignment_id' => $newId, 'type' => 'assignment'];
    $qrToken = generateQRToken($payload);
    $upd = $conn->prepare('UPDATE assignments SET qr_token = ? WHERE id = ?');
    $upd->bind_param('si', $qrToken, $newId);
    $upd->execute();
    $upd->close();

    sendJSON([
        'success' => true,
        'message' => 'تم إنشاء الواجب',
        'data' => ['assignment_id' => $newId, 'qr_token' => $qrToken],
    ]);
} catch (Throwable $e) {
    sendJSON(['success' => false, 'message' => 'خطأ في الخادم'], 500);
}
